/* Ten timeframes. Higher frames use the daily quotes on this page. Intraday frames load their own market bars. */
(function (root) {
  const SESSION_MINUTES = 480;
  const FRAMES = [
    { id: "1m", short: "1m", label: "1 minute", kind: "intra", minutes: 1 },
    { id: "5m", short: "5m", label: "5 minute", kind: "intra", minutes: 5 },
    { id: "15m", short: "15m", label: "15 minute", kind: "intra", minutes: 15 },
    { id: "30m", short: "30m", label: "30 minute", kind: "intra", minutes: 30 },
    { id: "1h", short: "1h", label: "1 hour", kind: "intra", minutes: 60 },
    { id: "4h", short: "4h", label: "4 hour", kind: "intra", minutes: 240 },
    { id: "1D", short: "1D", label: "Daily", kind: "day" },
    { id: "1W", short: "1W", label: "Weekly", kind: "week" },
    { id: "1M", short: "1M", label: "Monthly", kind: "month" },
    { id: "1Q", short: "1Q", label: "Quarterly", kind: "quarter" },
  ];

  function frameById(id) {
    return FRAMES.find((frame) => frame.id === id) || FRAMES[6];
  }

  function valueOnPath(points, t) {
    const clamped = Math.max(0, Math.min(0.999999, t));
    const segment = Math.min(2, Math.floor(clamped * 3));
    const local = clamped * 3 - segment;
    return points[segment] + (points[segment + 1] - points[segment]) * local;
  }

  function intradayDay(bar, minutes) {
    const count = SESSION_MINUTES / minutes;
    const points = bar.close >= bar.open
      ? [bar.open, bar.low, bar.high, bar.close]
      : [bar.open, bar.high, bar.low, bar.close];
    const marks = [0, 1 / 3, 2 / 3, 1];
    const out = [];
    for (let index = 0; index < count; index += 1) {
      const start = index / count;
      const end = (index + 1) / count;
      let open = index === 0 ? bar.open : valueOnPath(points, start);
      let close = index === count - 1 ? bar.close : valueOnPath(points, end);
      let high = Math.max(open, close);
      let low = Math.min(open, close);
      marks.forEach((mark, markIndex) => {
        if (mark > start && mark < end) {
          high = Math.max(high, points[markIndex]);
          low = Math.min(low, points[markIndex]);
        }
      });
      high = Math.min(bar.high, high);
      low = Math.max(bar.low, low);
      high = Math.max(high, open, close);
      low = Math.min(low, open, close);
      const minute = index * minutes;
      const hour = String(Math.floor(minute / 60) + 9).padStart(2, "0");
      const min = String(minute % 60).padStart(2, "0");
      out.push({
        date: bar.date + " " + hour + ":" + min,
        open, high, low, close,
        volume: bar.volume / count,
      });
    }
    return out;
  }

  function intraday(daily, minutes, limit) {
    const perDay = SESSION_MINUTES / minutes;
    const days = Math.min(daily.length, Math.ceil(limit / perDay) + 1);
    const bars = [];
    daily.slice(-days).forEach((bar) => {
      intradayDay(bar, minutes).forEach((child) => bars.push(child));
    });
    return bars.slice(-limit);
  }

  function weekKey(date) {
    const day = new Date(date + "T00:00:00Z");
    const weekday = day.getUTCDay() || 7;
    const thursday = new Date(day);
    thursday.setUTCDate(day.getUTCDate() + 4 - weekday);
    const yearStart = new Date(Date.UTC(thursday.getUTCFullYear(), 0, 1));
    const week = Math.ceil(((thursday - yearStart) / 86400000 + 1) / 7);
    return thursday.getUTCFullYear() + "-W" + String(week).padStart(2, "0");
  }

  function aggregate(daily, keyFn) {
    const groups = [];
    daily.forEach((bar) => {
      const key = keyFn(bar.date);
      const last = groups[groups.length - 1];
      if (!last || last.key !== key) {
        groups.push({
          key,
          date: bar.date,
          open: bar.open,
          high: bar.high,
          low: bar.low,
          close: bar.close,
          volume: bar.volume,
        });
        return;
      }
      last.high = Math.max(last.high, bar.high);
      last.low = Math.min(last.low, bar.low);
      last.close = bar.close;
      last.volume += bar.volume;
      last.date = bar.date;
    });
    return groups;
  }

  function barsFor(daily, frame, limit) {
    const source = daily || [];
    if (!source.length) return [];
    if (frame.kind === "intra") return intraday(source, frame.minutes, limit || 160);
    if (frame.kind === "week") return aggregate(source, weekKey);
    if (frame.kind === "month") return aggregate(source, (date) => date.slice(0, 7));
    if (frame.kind === "quarter") {
      return aggregate(source, (date) => {
        const month = Number(date.slice(5, 7));
        return date.slice(0, 4) + "-Q" + Math.ceil(month / 3);
      });
    }
    return source.map((bar) => ({ ...bar }));
  }

  function summarize(bars) {
    if (!bars.length) return { bias: "flat", last: null, change: 0, count: 0, asOf: "" };
    const last = bars[bars.length - 1];
    const previous = bars[bars.length - 2] || last;
    const window = bars.slice(-Math.min(8, bars.length));
    const average = window.reduce((sum, bar) => sum + bar.close, 0) / window.length;
    let bias = "flat";
    if (last.close > average * 1.001) bias = "up";
    else if (last.close < average * 0.999) bias = "down";
    const change = previous.close ? ((last.close - previous.close) / previous.close) * 100 : 0;
    return { bias, last: last.close, change, count: bars.length, asOf: last.date };
  }

  function analyze(daily) {
    return FRAMES.map((frame) => {
      if (frame.kind === "intra") {
        return { ...frame, bias: "flat", last: null, change: 0, count: 0, asOf: "", bars: [] };
      }
      const bars = barsFor(daily, frame, 160);
      return { ...frame, ...summarize(bars), bars };
    });
  }

  function brief(symbol, rows) {
    const ups = rows.filter((row) => row.bias === "up").length;
    const downs = rows.filter((row) => row.bias === "down").length;
    const lines = rows.map((row) => {
      const change = (row.change >= 0 ? "+" : "") + row.change.toFixed(2) + "%";
      return row.label + " is " + row.bias + " (" + change + " on the last bar, " + row.count + " bars through " + row.asOf + ").";
    });
    return {
      title: symbol + " timeframe brief",
      lead: ups + " of the higher timeframes are up and " + downs + " are down. Intraday windows load market bars.",
      lines,
    };
  }

  function ema(values, length) {
    const out = new Array(values.length).fill(null);
    if (length < 1) return out;
    const alpha = 2 / (length + 1);
    let previous = null;
    values.forEach((value, index) => {
      if (value == null || Number.isNaN(value)) return;
      previous = previous == null ? value : value * alpha + previous * (1 - alpha);
      out[index] = previous;
    });
    return out;
  }

  function averages(bars, length) {
    const window = Math.max(2, Math.round(Number(length) || 34));
    return {
      high: ema(bars.map((bar) => bar.high), window),
      close: ema(bars.map((bar) => bar.close), window),
      low: ema(bars.map((bar) => bar.low), window),
    };
  }

  function momentumTone(current, previous) {
    if (current == null) return null;
    const rising = previous == null ? current >= 0 : current > previous;
    if (rising && current >= 0) return "#00e5ff";
    if (rising && current < 0) return "#ffe14a";
    if (current >= 0) return "#3b6cff";
    return "#ff4d4d";
  }

  function squeezeTone(level) {
    if (level >= 3) return "#ff9f1c";
    if (level === 2) return "#ff4d4d";
    if (level === 1) return "#101418";
    return "#3ddc97";
  }

  function squeezeAlert(bars) {
    const study = compression(bars || [], 20);
    const level = study.level || [];
    const momentum = study.momentum || [];
    let dots = 0;
    for (let index = level.length - 1; index >= 0 && level[index] > 0; index -= 1) dots += 1;
    const recent = dots > 0 || (level.length >= 2 && level[level.length - 2] > 0);
    const now = momentum[momentum.length - 1];
    const previous = momentum[momentum.length - 2];
    const older = momentum.length >= 3 ? momentum[momentum.length - 3] : null;
    const fading = now != null && previous != null && previous > 0 && now < previous && !(older != null && older > 0 && previous < older);
    if (recent && fading) return { side: "sell", kind: "steam", dots, index: Math.max(0, level.length - 1) };
    if (dots >= 5) {
      const side = now != null && previous != null && now > 0 && now >= previous ? "buy" : "sell";
      return { side, kind: "dots", dots, index: level.length - dots + 4 };
    }
    return { side: "", kind: "", dots, index: -1 };
  }

  function squeezeName(level) {
    if (level >= 3) return "High";
    if (level === 2) return "Mid";
    if (level === 1) return "Low";
    return "Off";
  }

  function trueMomentum(bars, length, calcLength, smoothLength) {
    const window = Math.max(2, Math.round(Number(length) || 14));
    const calc = Math.max(1, Math.round(Number(calcLength) || 5));
    const smooth = Math.max(1, Math.round(Number(smoothLength) || 3));
    const count = bars.length;
    const data = new Array(count).fill(0);
    for (let index = 0; index < count; index += 1) {
      let score = 0;
      const close = bars[index].close;
      for (let back = 0; back < window; back += 1) {
        const open = bars[Math.max(0, index - back)].open;
        if (close > open) score += 1;
        else if (close < open) score -= 1;
      }
      data[index] = score;
    }
    const main = ema(ema(data, calc), smooth);
    const signal = ema(main, smooth);
    const up = new Array(count).fill(false);
    const down = new Array(count).fill(false);
    // Arrows mark a Main/Signal cross on the closed bar only, so a forming bar never moves them.
    for (let index = 2; index < count; index += 1) {
      const crossedUp = main[index - 1] > signal[index - 1] && main[index - 2] <= signal[index - 2];
      const crossedDown = main[index - 1] < signal[index - 1] && main[index - 2] >= signal[index - 2];
      if (crossedUp) up[index - 1] = true;
      if (crossedDown) down[index - 1] = true;
    }
    const bound = Math.round(window * 0.7);
    return { main, signal, up, down, length: window, ob: bound, os: -bound };
  }

  // Desk rails: 5 white · 14 yellow · 50 blue · 200 red (CCI palette). Keep 9/100 for stack order checks.
  const STACK_AVGS = [
    { length: 5, color: "#f4f7fb", name: "5" },
    { length: 9, color: "#22d3ee", name: "9" },
    { length: 14, color: "#facc15", name: "14" },
    { length: 50, color: "#1e5aa8", name: "50" },
    { length: 100, color: "#c9a000", name: "100" },
    { length: 200, color: "#ff4d4d", name: "200" },
  ];

  function stackAverages(bars) {
    const closes = (bars || []).map((bar) => bar.close);
    return STACK_AVGS.map((row) => ({
      length: row.length,
      color: row.color,
      name: row.name,
      values: simpleAverage(closes, row.length),
    }));
  }

  // Desk momentum bars (ADX · DMI): green = uptrend conviction, red = downtrend, yellow = no strong direction.
  // Own closed-bar Wilder math — locks on close so prior candles do not repaint. Not a third-party product.
  let momentumBarsCache = { key: "", value: null };

  function momentumBars(bars, options) {
    const opts = options || {};
    const length = Math.max(2, Math.round(Number(opts.length) || 14));
    const adxFloor = Number.isFinite(Number(opts.adxFloor)) ? Number(opts.adxFloor) : 20;
    const volLook = Math.max(5, Math.round(Number(opts.volumeLookback) || 50));
    const volMult = Number.isFinite(Number(opts.volumeMult)) ? Number(opts.volumeMult) : 1.3;
    const list = bars || [];
    const count = list.length;
    const live = opts.live === true || (opts.live == null && liveBar(list, !!opts.crypto, opts.now));
    const last = count ? list[count - 1] : null;
    const cacheKey = [
      count,
      last ? last.date : "",
      last ? last.close : "",
      last ? last.high : "",
      last ? last.low : "",
      last ? last.volume : "",
      live ? 1 : 0,
      length,
      adxFloor,
      volLook,
      volMult,
    ].join("|");
    if (momentumBarsCache.key === cacheKey && momentumBarsCache.value) return momentumBarsCache.value;
    const tr = new Array(count).fill(0);
    const plusDM = new Array(count).fill(0);
    const minusDM = new Array(count).fill(0);
    for (let index = 1; index < count; index += 1) {
      const high = list[index].high;
      const low = list[index].low;
      const prevClose = list[index - 1].close;
      const upMove = high - list[index - 1].high;
      const downMove = list[index - 1].low - low;
      tr[index] = Math.max(high - low, Math.abs(high - prevClose), Math.abs(low - prevClose));
      plusDM[index] = upMove > downMove && upMove > 0 ? upMove : 0;
      minusDM[index] = downMove > upMove && downMove > 0 ? downMove : 0;
    }
    function smoothDM(series) {
      const out = new Array(count).fill(null);
      if (count <= length) return out;
      let sum = 0;
      for (let index = 1; index <= length; index += 1) sum += series[index];
      out[length] = sum;
      for (let index = length + 1; index < count; index += 1) {
        out[index] = out[index - 1] - out[index - 1] / length + series[index];
      }
      return out;
    }
    const atr = smoothDM(tr);
    const smPlus = smoothDM(plusDM);
    const smMinus = smoothDM(minusDM);
    const plusDI = new Array(count).fill(null);
    const minusDI = new Array(count).fill(null);
    const dx = new Array(count).fill(null);
    for (let index = 0; index < count; index += 1) {
      if (atr[index] == null || !(atr[index] > 0)) continue;
      plusDI[index] = (100 * smPlus[index]) / atr[index];
      minusDI[index] = (100 * smMinus[index]) / atr[index];
      const den = plusDI[index] + minusDI[index];
      dx[index] = den === 0 ? 0 : (100 * Math.abs(plusDI[index] - minusDI[index])) / den;
    }
    const adx = new Array(count).fill(null);
    const adxStart = length * 2 - 1;
    if (count > adxStart) {
      let sum = 0;
      for (let index = length; index <= adxStart; index += 1) sum += dx[index] == null ? 0 : dx[index];
      adx[adxStart] = sum / length;
      for (let index = adxStart + 1; index < count; index += 1) {
        adx[index] = (adx[index - 1] * (length - 1) + (dx[index] == null ? 0 : dx[index])) / length;
      }
    }
    // Same bright green/red as Volume Wave · Desk Waves — never gray, yellow, soft, or mixed.
    const GREEN = "#3ddc97";
    const GREEN_RIM = "#1fae78";
    const RED = "#ff4d4d";
    const RED_RIM = "#d63b4a";
    const tone = new Array(count).fill(null);
    const rim = new Array(count).fill(null);
    const bias = new Array(count).fill("warm");
    const locked = new Array(count).fill(true);
    const volumeDot = new Array(count).fill(false);
    const volumes = list.map((bar) => Math.max(0, Number(bar.volume) || 0));
    const volAvg = simpleAverage(volumes, volLook);
    for (let index = 0; index < count; index += 1) {
      if (live && index === count - 1) locked[index] = false;
      // Classic tape body color (matches Volume Wave hist): close vs open.
      const upBar = list[index].close >= list[index].open;
      tone[index] = upBar ? GREEN : RED;
      rim[index] = upBar ? GREEN_RIM : RED_RIM;
      bias[index] = upBar ? "up" : "down";
      // ADX/DMI still computed for dots / callers, but never paints gray/yellow bodies.
      void adxFloor;
      // Volume conviction dots only on locked closed bars — live bar never paints a sticky dot.
      if (locked[index] && volAvg[index] != null && volAvg[index] > 0 && volumes[index] >= volAvg[index] * volMult) {
        volumeDot[index] = true;
      }
    }
    const study = {
      tone,
      rim,
      bias,
      locked,
      volumeDot,
      plusDI,
      minusDI,
      adx,
      length,
      adxFloor,
      live,
    };
    momentumBarsCache = { key: cacheKey, value: study };
    return study;
  }

  function paintCandles(ctx, bars, xAt, y, slot, paint) {
    const list = bars || [];
    // Bias list from big4indicator: bull / mixed (gray) / bear. Otherwise Volume Wave green/red.
    const biasList = Array.isArray(paint) ? paint : null;
    const study = !biasList && paint && Array.isArray(paint.tone) ? paint : null;
    const bodyW = Math.max(2, Math.min(slot * 0.72, Math.max(2, slot - 1)));
    const GREEN = "#3ddc97";
    const GREEN_RIM = "#1fae78";
    const RED = "#ff4d4d";
    const RED_RIM = "#d63b4a";
    const GRAY = "#9aa6b2";
    const GRAY_RIM = "#6b7785";
    list.forEach((bar, index) => {
      const x = xAt(index);
      const topPrice = Math.max(bar.open, bar.close);
      const bottomPrice = Math.min(bar.open, bar.close);
      const top = y(topPrice);
      const bottom = y(bottomPrice);
      const bodyH = Math.max(1, bottom - top);
      const up = bar.close >= bar.open;
      let tone;
      let rim;
      if (biasList) {
        const mode = biasList[index];
        if (mode === "bull") {
          tone = GREEN;
          rim = GREEN_RIM;
        } else if (mode === "bear") {
          tone = RED;
          rim = RED_RIM;
        } else if (mode === "mixed") {
          tone = GRAY;
          rim = GRAY_RIM;
        } else {
          tone = up ? GREEN : RED;
          rim = up ? GREEN_RIM : RED_RIM;
        }
      } else {
        tone = up ? GREEN : RED;
        rim = up ? GREEN_RIM : RED_RIM;
      }
      const locked = !study || !study.locked || study.locked[index] !== false;
      ctx.globalAlpha = locked ? 1 : 0.95;
      ctx.strokeStyle = tone;
      ctx.lineWidth = Math.max(1.35, Math.min(2.75, slot * 0.18));
      ctx.beginPath();
      ctx.moveTo(x, y(bar.high));
      ctx.lineTo(x, y(bar.low));
      ctx.stroke();
      const left = x - bodyW / 2;
      ctx.fillStyle = tone;
      ctx.fillRect(left, top, bodyW, bodyH);
      ctx.strokeStyle = rim;
      ctx.lineWidth = 1;
      ctx.strokeRect(left + 0.5, top + 0.5, Math.max(1, bodyW - 1), Math.max(1, bodyH - 1));
      if (study && study.volumeDot && study.volumeDot[index]) {
        const radius = Math.max(1.75, Math.min(3.5, slot * 0.22));
        ctx.beginPath();
        ctx.fillStyle = "#7dd3fc";
        ctx.arc(x, y(bar.low) + radius + 3, radius, 0, Math.PI * 2);
        ctx.fill();
      }
      ctx.globalAlpha = 1;
    });
    ctx.lineWidth = 1;
  }

  function big4Indicator(bars) {
    const list = bars || [];
    const count = list.length;
    const closes = list.map((bar) => bar.close);
    const ema50 = ema(closes, 50);
    const ema200 = ema(closes, 200);
    const trend = macd(list);
    const rsi = proRsi(closes, 14);
    const bias = new Array(count).fill("mixed");
    const ok = new Array(count).fill(false);
    for (let index = 0; index < count; index += 1) {
      if (ema50[index] == null || ema200[index] == null) continue;
      const bullTrend = closes[index] > ema50[index] && ema50[index] >= ema200[index];
      const bearTrend = closes[index] < ema50[index] && ema50[index] <= ema200[index];
      const macdOk = trend.line[index] != null && trend.signal[index] != null && trend.line[index] > trend.signal[index];
      const rsiOk = rsi[index] != null && rsi[index] < 70 && rsi[index] > 30;
      if (bullTrend) {
        bias[index] = "bull";
        // Strong agreement: trend plus MACD and RSI in range.
        ok[index] = !!(macdOk && rsiOk);
      } else if (bearTrend) {
        bias[index] = "bear";
      } else {
        bias[index] = "mixed";
      }
    }
    const last = count - 1;
    const label = last >= 0 && bias[last] === "bull" ? "Bullish" : last >= 0 && bias[last] === "bear" ? "Bearish" : last >= 0 ? "Mixed" : "Waiting";
    return {
      name: "big4indicator",
      bias,
      ok,
      last: last >= 0 ? bias[last] : "mixed",
      lastOk: last >= 0 ? !!ok[last] : false,
      label,
    };
  }

  function tickSize(price) {
    const abs = Math.abs(price) || 1;
    if (abs >= 1000) return 1;
    if (abs >= 1) return 0.01;
    if (abs >= 0.1) return 0.0001;
    return 0.00001;
  }

  function roundTick(value, tick) {
    return Math.round(value / tick) * tick;
  }

  function trendPivots(bars, length, multiple) {
    const window = Math.max(2, Math.round(Number(length) || 5));
    const factor = Number.isFinite(Number(multiple)) ? Number(multiple) : 0.7;
    const count = bars.length;
    if (count < 2) return null;
    const tick = tickSize(bars[count - 1].close);
    const range = bars.map((bar, index) => {
      const span = bar.high - bar.low;
      if (index === 0) return span;
      const previous = bars[index - 1].close;
      return Math.max(span, Math.abs(bar.high - previous), Math.abs(bar.low - previous));
    });
    let resistance = bars[0].high;
    let support = bars[0].low;
    let fromHigh = 0;
    let fromLow = 0;
    let highRange = range[0];
    let lowRange = range[0];
    let priorLow = bars[0].low;
    let priorHigh = bars[0].high;
    for (let index = 1; index < count; index += 1) {
      let higherHighs = 0;
      let higherLows = 0;
      let lowerLows = 0;
      let lowerHighs = 0;
      const steps = Math.min(window, index);
      for (let step = 0; step < steps; step += 1) {
        const cursor = index - step;
        if (bars[cursor].high > bars[cursor - 1].high) higherHighs += 1;
        if (bars[cursor].low > bars[cursor - 1].low) higherLows += 1;
        if (bars[cursor].low < bars[cursor - 1].low) lowerLows += 1;
        if (bars[cursor].high < bars[cursor - 1].high) lowerHighs += 1;
      }
      if (index >= window && higherHighs >= window && higherLows >= window - 1) resistance = bars[index].high;
      else if (bars[index].high > resistance) resistance = bars[index].high;
      if (bars[index].high === resistance) {
        fromHigh = index;
        const used = Math.min(window, index + 1);
        let sum = 0;
        for (let step = 0; step < used; step += 1) sum += range[index - step];
        highRange = roundTick(sum / used, tick);
        priorLow = bars[index - 1].low;
      }
      if (index >= window && lowerLows >= window && lowerHighs >= window - 1) support = bars[index].low;
      else if (bars[index].low < support) support = bars[index].low;
      if (bars[index].low === support) {
        fromLow = index;
        const used = Math.min(window, index + 1);
        let sum = 0;
        for (let step = 0; step < used; step += 1) sum += range[index - step];
        lowRange = roundTick(sum / used, tick);
        priorHigh = bars[index - 1].high;
      }
    }
    const confirmHigh = roundTick(Math.max(priorLow, resistance - highRange * factor), tick);
    const confirmLow = roundTick(Math.min(priorHigh, support + lowRange * factor), tick);
    return {
      resistance,
      support,
      confirmHigh,
      confirmLow,
      riskOffHigh: roundTick(confirmHigh - Math.min(highRange, tick * 16), tick),
      riskOffLow: roundTick(confirmLow + Math.min(lowRange, tick * 16), tick),
      fromHigh,
      fromLow,
    };
  }

  function paintPivots(ctx, pivots, xAt, y, lastIndex) {
    if (!pivots) return;
    const bands = [
      [pivots.fromHigh, pivots.confirmHigh, pivots.resistance, "rgba(255, 70, 80, 0.28)", "rgba(255, 90, 100, 0.85)"],
      [pivots.fromLow, pivots.support, pivots.confirmLow, "rgba(20, 140, 80, 0.30)", "rgba(50, 210, 130, 0.90)"],
    ];
    ctx.save();
    bands.forEach(([from, first, second, fill, edge]) => {
      const x1 = xAt(Math.max(0, from));
      const x2 = xAt(lastIndex) + Math.max(2, (xAt(1) - xAt(0)) * 0.35);
      const top = Math.min(y(first), y(second));
      const height = Math.max(4, Math.abs(y(second) - y(first)));
      const left = Math.min(x1, x2);
      const width = Math.max(8, Math.abs(x2 - x1));
      ctx.fillStyle = fill;
      ctx.fillRect(left, top, width, height);
      ctx.strokeStyle = edge;
      ctx.lineWidth = 1.5;
      ctx.strokeRect(left + 0.5, top + 0.5, width - 1, height - 1);
    });
    const lines = [
      [pivots.fromHigh, pivots.resistance, "#ff6b7a", false, "Resistance"],
      [pivots.fromHigh, pivots.confirmHigh, "#ffb0b8", true, "Confirm"],
      [pivots.fromHigh, pivots.riskOffHigh, "#d7e2ea", true, "Risk off"],
      [pivots.fromLow, pivots.support, "#3ddc97", false, "Support"],
      [pivots.fromLow, pivots.confirmLow, "#9af0c8", true, "Confirm"],
      [pivots.fromLow, pivots.riskOffLow, "#d7e2ea", true, "Risk off"],
    ];
    ctx.font = "11px Segoe UI, sans-serif";
    const narrow = (ctx.canvas.paperWidth || 0) < 640;
    const placed = [];
    lines.forEach(([from, price, color, dashed, label]) => {
      if (dashed && lines.some((other) => !other[3] && Math.abs(y(other[1]) - y(price)) < 8 && other[0] === from)) return;
      const x1 = xAt(Math.max(0, from));
      const x2 = xAt(lastIndex);
      ctx.strokeStyle = color;
      ctx.fillStyle = color;
      ctx.lineWidth = dashed ? 1 : 2;
      ctx.setLineDash(dashed ? [5, 4] : []);
      ctx.beginPath();
      ctx.moveTo(x1, y(price));
      ctx.lineTo(x2, y(price));
      ctx.stroke();
      ctx.setLineDash([]);
      if (narrow && dashed) return;
      const text = label + " " + (Number.isFinite(price) ? String(Math.round(price * 100) / 100) : "");
      const textY = y(price) - 4;
      if (placed.some((prior) => Math.abs(prior - textY) < 13)) return;
      placed.push(textY);
      const tagW = ctx.measureText(text).width + 8;
      const tagX = Math.max(x1 + 4, x2 - tagW);
      ctx.fillStyle = "rgba(5, 8, 12, 0.82)";
      ctx.fillRect(tagX - 2, textY - 11, tagW, 14);
      ctx.fillStyle = color;
      ctx.fillText(text, tagX, textY);
    });
    ctx.restore();
  }

  function periodVwap(bars, keyFn) {
    const list = bars || [];
    if (!list.length || typeof keyFn !== "function") return null;
    const last = list[list.length - 1];
    const key = keyFn(last.date);
    let money = 0;
    let volume = 0;
    for (let index = list.length - 1; index >= 0; index -= 1) {
      const bar = list[index];
      if (keyFn(bar.date) !== key) break;
      const vol = Math.max(0, Number(bar.volume) || 0);
      const typical = (bar.high + bar.low + bar.close) / 3;
      if (!(vol > 0)) continue;
      money += typical * vol;
      volume += vol;
    }
    if (!(volume > 0)) return null;
    return money / volume;
  }

  function deskClearRead(bars) {
    const list = bars || [];
    if (list.length < 30) return null;
    const desk = pro(list);
    const last = list.length - 1;
    const close = list[last].close;
    const upper = desk.bands.upper[last];
    const lower = desk.bands.lower[last];
    const pctB = upper != null && lower != null && upper !== lower ? ((close - lower) / (upper - lower)) * 100 : null;
    const ema50 = desk.last.ema50;
    const ema200 = desk.last.ema200;
    const bias =
      ema50 != null && ema200 != null
        ? close >= ema50 && ema50 >= ema200
          ? "Bullish"
          : close <= ema50 && ema50 <= ema200
            ? "Bearish"
            : "Mixed"
        : "Waiting";
    const hist = desk.macd.hist || [];
    const now = hist[last];
    const prior = hist[last - 1];
    let stage = "Steady";
    if (now != null && prior != null) {
      if (Math.abs(now) > Math.abs(prior) + 1e-9) stage = "Acceleration";
      else if (Math.abs(now) < Math.abs(prior) - 1e-9) stage = "Deceleration";
    }
    const weekKey = (date) => {
      const day = new Date(String(date).slice(0, 10) + "T12:00:00Z");
      const utc = day.getUTCDay();
      const monday = new Date(day);
      monday.setUTCDate(day.getUTCDate() - ((utc + 6) % 7));
      return monday.toISOString().slice(0, 10);
    };
    const monthKey = (date) => String(date).slice(0, 7);
    return {
      bias,
      pctB,
      stage,
      ema50,
      vwapD: desk.last.vwap,
      vwapW: periodVwap(list, weekKey),
      vwapM: periodVwap(list, monthKey),
      close,
    };
  }

  function candlePlace(bar, highLine, lowLine) {
    if (!bar || highLine == null || lowLine == null) return "Waiting";
    if (bar.close > highLine) return "Above the high average";
    if (bar.close < lowLine) return "Below the low average";
    return "Between the averages";
  }

  function compression(bars, length) {
    const window = length || 20;
    const count = bars.length;
    const momentum = new Array(count).fill(null);
    const level = new Array(count).fill(0);
    if (window < 5 || count < window) return { momentum, level };
    const closes = bars.map((bar) => bar.close);
    const highs = bars.map((bar) => bar.high);
    const lows = bars.map((bar) => bar.low);
    const basis = [];
    let total = 0;
    for (let index = 0; index < count; index += 1) {
      total += closes[index];
      if (index >= window) total -= closes[index - window];
      basis[index] = index >= window - 1 ? total / window : null;
    }
    const ranges = closes.map((close, index) => {
      const span = highs[index] - lows[index];
      if (index === 0) return span;
      return Math.max(span, Math.abs(highs[index] - closes[index - 1]), Math.abs(lows[index] - closes[index - 1]));
    });
    const width = [];
    let rangeTotal = 0;
    for (let index = 0; index < count; index += 1) {
      rangeTotal += ranges[index];
      if (index >= window) rangeTotal -= ranges[index - window];
      width[index] = index >= window - 1 ? rangeTotal / window : null;
    }
    const delta = new Array(count).fill(0);
    for (let index = window - 1; index < count; index += 1) {
      const mean = basis[index];
      let variance = 0;
      let highest = -Infinity;
      let lowest = Infinity;
      for (let cursor = index - window + 1; cursor <= index; cursor += 1) {
        variance += (closes[cursor] - mean) ** 2;
        if (highs[cursor] > highest) highest = highs[cursor];
        if (lows[cursor] < lowest) lowest = lows[cursor];
      }
      const deviation = Math.sqrt(variance / window);
      delta[index] = closes[index] - ((highest + lowest) / 2 + mean) / 2;
      const upper = mean + 2 * deviation;
      const lower = mean - 2 * deviation;
      const band = width[index];
      let squeezeLevel = 0;
      [[2, 1], [1.5, 2], [1, 3]].forEach(([multiple, code]) => {
        if (upper < mean + multiple * band && lower > mean - multiple * band) squeezeLevel = code;
      });
      level[index] = squeezeLevel;
    }
    const sumX = (window * (window - 1)) / 2;
    const sumX2 = ((window - 1) * window * (2 * window - 1)) / 6;
    const denominator = window * sumX2 - sumX * sumX;
    for (let index = window - 1; index < count; index += 1) {
      let sumY = 0;
      let sumXY = 0;
      for (let offset = 0; offset < window; offset += 1) {
        const value = delta[index - window + 1 + offset];
        sumY += value;
        sumXY += offset * value;
      }
      const slope = (window * sumXY - sumX * sumY) / denominator;
      const intercept = (sumY - slope * sumX) / window;
      momentum[index] = intercept + slope * (window - 1);
    }
    return { momentum, level };
  }

  function strokeSeries(ctx, values, xAt, y, color, weight) {
    ctx.beginPath();
    let drawing = false;
    values.forEach((value, index) => {
      if (value == null) {
        drawing = false;
        return;
      }
      const x = xAt(index);
      if (!drawing) {
        ctx.moveTo(x, y(value));
        drawing = true;
      } else ctx.lineTo(x, y(value));
    });
    ctx.strokeStyle = color;
    ctx.lineWidth = weight;
    ctx.stroke();
    ctx.lineWidth = 1;
  }

  const ZOOM_STOPS = [30, 50, 80, 120, 180, 260, 400, 700];
  const DEFAULT_ZOOM = 4;
  const lessonCache = new WeakMap();

  function zoomIndex(stop) {
    const parsed = Number(stop);
    const index = Number.isFinite(parsed) ? Math.round(parsed) : DEFAULT_ZOOM;
    return Math.max(0, Math.min(ZOOM_STOPS.length - 1, index));
  }

  function zoomBars(bars, stop) {
    const count = bars ? bars.length : 0;
    const index = zoomIndex(stop);
    const size = Math.min(count, ZOOM_STOPS[index]);
    const start = Math.max(0, count - size);
    return {
      bars: (bars || []).slice(start),
      start,
      size,
      stop: index,
      atMin: index <= 0,
      atMax: ZOOM_STOPS[index] >= count,
    };
  }

  function zoomStep(stop, direction, count) {
    const index = zoomIndex(stop);
    if (direction > 0 && ZOOM_STOPS[index] >= count) return index;
    const next = index + direction;
    if (next < 0 || next >= ZOOM_STOPS.length) return index;
    return next;
  }

  function mood(bars, length) {
    const window = Math.max(5, Math.round(Number(length) || 20));
    if (!bars || bars.length < window) {
      return { score: null, label: "Waiting", note: "Not enough closed bars for a fear or greed score." };
    }
    const recent = bars.slice(bars.length - window);
    let high = -Infinity;
    let low = Infinity;
    recent.forEach((bar) => {
      if (bar.high > high) high = bar.high;
      if (bar.low < low) low = bar.low;
    });
    const span = high - low;
    const last = recent[recent.length - 1].close;
    const position = span > 0 ? (last - low) / span : 0.5;
    const earlier = recent[0].close;
    const change = earlier ? (last - earlier) / earlier : 0;
    const thrust = Math.max(0, Math.min(1, 0.5 + change / 0.3));
    const score = Math.round(100 * (0.65 * position + 0.35 * thrust));
    const label = score <= 35 ? "Fear" : score >= 65 ? "Greed" : "Neutral";
    return { score, label, note: label + " " + score + ". The last close sits in the recent range. Not a forecast." };
  }

  function choose(book, fallback) {
    let winner = fallback;
    let bestRate = -1;
    let bestSeen = -1;
    Object.keys(book).forEach((name) => {
      const row = book[name];
      if (row.seen < 8) return;
      const rate = row.matched / row.seen;
      if (rate <= 0.5) return;
      if (rate > bestRate || (rate === bestRate && row.seen > bestSeen)) {
        winner = name;
        bestRate = rate;
        bestSeen = row.seen;
      }
    });
    const row = book[winner] || { seen: 0, matched: 0 };
    return { name: winner, seen: row.seen, matched: row.matched };
  }

  function withCandle(up, down, bars) {
    return [
      up.map((flag, index) => flag && bars[index].close >= bars[index].open),
      down.map((flag, index) => flag && bars[index].close < bars[index].open),
    ];
  }

  function averageMarks(bars, length) {
    const lines = averages(bars, length);
    const up = bars.map(() => false);
    const down = bars.map(() => false);
    for (let index = 1; index < bars.length; index += 1) {
      if (lines.high[index] == null || lines.high[index - 1] == null || lines.low[index] == null) continue;
      if (bars[index].close > lines.high[index] && bars[index - 1].close <= lines.high[index - 1]) up[index] = true;
      if (bars[index].close < lines.low[index] && bars[index - 1].close >= lines.low[index - 1]) down[index] = true;
    }
    return [up, down];
  }

  function squeezeMarks(bars, length) {
    const study = compression(bars, length);
    const up = bars.map(() => false);
    const down = bars.map(() => false);
    for (let index = 1; index < bars.length; index += 1) {
      const now = study.momentum[index];
      const previous = study.momentum[index - 1];
      if (now == null || previous == null) continue;
      const released = study.level[index - 1] > 0 && study.level[index] === 0;
      if (released && now > 0 && now > previous) up[index] = true;
      if (previous >= 0 && now < 0) down[index] = true;
      else if (released && now < 0 && now < previous) down[index] = true;
    }
    return [up, down];
  }

  function parseRule(name) {
    const candle = name.endsWith(" with the candle");
    const parts = name.split(" ");
    if (name.startsWith("True momentum") && parts[2] && parts[2].includes("/")) {
      const [calc, smooth] = parts[2].split("/").map((value) => Number(value));
      return { calc, smooth, candle };
    }
    if (name.startsWith("Squeeze") && parts[1]) return { squeeze: Number(parts[1]), candle };
    if (name.startsWith("Average") && parts[1]) return { average: Number(parts[1]), candle };
    return { candle };
  }

  function lesson(bars) {
    const count = bars.length;
    const last = count ? bars[count - 1] : null;
    const stamp = count + ":" + (last ? last.close : "");
    const cached = lessonCache.get(bars);
    if (cached && cached.stamp === stamp) return cached.value;
    const signals = {};
    const add = (name, pair) => {
      signals[name] = pair;
    };
    const base = trueMomentum(bars, 14, 5, 3);
    add("True momentum 5/3", [base.up, base.down]);
    add("True momentum 5/3 with the candle", withCandle(base.up, base.down, bars));
    [3, 5, 8].forEach((calc) => {
      [2, 3, 5].forEach((smooth) => {
        if (calc === 5 && smooth === 3) return;
        const study = trueMomentum(bars, 14, calc, smooth);
        const name = "True momentum " + calc + "/" + smooth;
        add(name, [study.up, study.down]);
        add(name + " with the candle", withCandle(study.up, study.down, bars));
      });
    });
    [20, 14, 30].forEach((length) => {
      const pair = squeezeMarks(bars, length);
      const name = "Squeeze " + length;
      add(name, pair);
      add(name + " with the candle", withCandle(pair[0], pair[1], bars));
    });
    [34, 21, 55].forEach((length) => {
      const pair = averageMarks(bars, length);
      const name = "Average " + length;
      add(name, pair);
      add(name + " with the candle", withCandle(pair[0], pair[1], bars));
    });
    const book = {};
    Object.keys(signals).forEach((name) => {
      book[name] = { seen: 0, matched: 0 };
    });
    const choice = [];
    for (let index = 0; index < count; index += 1) {
      choice.push(choose(book, "True momentum 5/3 with the candle").name);
      if (index < 1) continue;
      const earlier = index - 1;
      const move = bars[index].close - bars[earlier].close;
      Object.keys(signals).forEach((name) => {
        const up = signals[name][0][earlier];
        const down = signals[name][1][earlier];
        const side = up && !down ? 1 : down && !up ? -1 : 0;
        if (!side) return;
        book[name].seen += 1;
        if ((side > 0 && move > 0) || (side < 0 && move < 0)) book[name].matched += 1;
      });
    }
    const picked = choose(book, "True momentum 5/3 with the candle");
    const tmoBook = {};
    const squeezeBook = {};
    const averageBook = {};
    Object.keys(book).forEach((name) => {
      if (name.startsWith("True momentum")) tmoBook[name] = book[name];
      else if (name.startsWith("Squeeze")) squeezeBook[name] = book[name];
      else if (name.startsWith("Average")) averageBook[name] = book[name];
    });
    const tmo = parseRule(choose(tmoBook, "True momentum 5/3 with the candle").name);
    const squeezeChoice = parseRule(choose(squeezeBook, "Squeeze 20").name);
    const average = parseRule(choose(averageBook, "Average 34").name);
    const arrows = [];
    for (let index = 0; index < count - 1; index += 1) {
      const pair = signals[choice[index]];
      if (!pair) continue;
      if (pair[0][index] && !pair[1][index]) arrows.push({ index, side: "buy" });
      else if (pair[1][index] && !pair[0][index]) arrows.push({ index, side: "sell" });
    }
    const value = {
      rule: picked.name,
      seen: picked.seen,
      matched: picked.matched,
      calc: tmo.calc || 5,
      smooth: tmo.smooth || 3,
      squeeze: squeezeChoice.squeeze || 20,
      average: average.average || 34,
      candle: picked.name.endsWith(" with the candle"),
      arrows,
      mood: mood(bars),
      note: picked.matched + " of " + picked.seen + " next closes matched " + picked.name + ". Score of bars already printed. Not a forecast.",
    };
    lessonCache.set(bars, { stamp, value });
    return value;
  }

  function drawEdgeCalls(ctx, marks, dropped, count, xAt, top, bottom, left, right) {
    ctx.save();
    ctx.font = "bold 10px Segoe UI, sans-serif";
    ctx.textBaseline = "middle";
    (marks || []).forEach((mark) => {
      const index = mark.index - dropped;
      if (index < 0 || index >= count) return;
      const buy = mark.side === "buy";
      const label = buy ? "BUY" : "SELL";
      const width = ctx.measureText(label).width + 8;
      const x = Math.max(left + width / 2, Math.min(right - width / 2, xAt(index)));
      const y = buy ? top + 8 : bottom - 8;
      ctx.setLineDash([2, 3]);
      ctx.strokeStyle = buy ? "rgba(61,220,151,.55)" : "rgba(255,107,122,.55)";
      ctx.beginPath();
      ctx.moveTo(xAt(index), top);
      ctx.lineTo(xAt(index), bottom);
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.fillStyle = buy ? "#3ddc97" : "#ff6b7a";
      ctx.fillRect(x - width / 2, y - 7, width, 14);
      ctx.fillStyle = "#07090b";
      ctx.textAlign = "center";
      ctx.fillText(label, x, y);
    });
    ctx.restore();
  }

  function draw(canvas, bars, zoomStop, options) {
    if (!canvas || !bars || !bars.length) return;
    const history = bars;
    const fitted = lesson(history);
    const view = zoomBars(history, zoomStop);
    bars = view.bars;
    const dropped = view.start;
    const ratio = root.devicePixelRatio || 1;
    const width = canvas.clientWidth || 640;
    const height = canvas.clientHeight || 280;
    canvas.width = Math.floor(width * ratio);
    canvas.height = Math.floor(height * ratio);
    const ctx = canvas.getContext("2d");
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);
    const fullLines = averages(history, 34);
    const lines = {
      high: fullLines.high.slice(dropped),
      close: fullLines.close.slice(dropped),
      low: fullLines.low.slice(dropped),
    };
    const pivotsFull = trendPivots(history);
    const pivots = pivotsFull ? {
      ...pivotsFull,
      fromHigh: Math.max(0, pivotsFull.fromHigh - dropped),
      fromLow: Math.max(0, pivotsFull.fromLow - dropped),
    } : null;
    const fullSqueeze = compression(history, 20);
    const study = {
      momentum: fullSqueeze.momentum.slice(dropped),
      level: fullSqueeze.level.slice(dropped),
    };
    const lower = height >= 220;
    const priceBottom = lower ? Math.round(height * 0.7) : height - (height < 150 ? 8 : 18);
    const stack = stackAverages(history);
    let low = Math.min(...bars.map((bar) => bar.low));
    let high = Math.max(...bars.map((bar) => bar.high));
    [lines.high, lines.close, lines.low].concat(stack.map((row) => row.values.slice(dropped))).forEach((series) => {
      series.forEach((value) => {
        if (value == null) return;
        if (value < low) low = value;
        if (value > high) high = value;
      });
    });
    if (pivots) {
      [pivots.resistance, pivots.support, pivots.confirmHigh, pivots.confirmLow, pivots.riskOffHigh, pivots.riskOffLow].forEach((price) => {
        if (price < low) low = price;
        if (price > high) high = price;
      });
    }
    const span = high - low || 1;
    low -= span * 0.08;
    high += span * 0.08;
    ctx.font = "12px Segoe UI, sans-serif";
    const padLeft = Math.max(44, Math.ceil(ctx.measureText(high.toFixed(high >= 100 ? 2 : 4)).width) + 12);
    const padTop = height < 150 ? 8 : 22;
    const y = (price) => padTop + ((high - price) / (high - low)) * (priceBottom - padTop);
    const padRight = Math.max(72, Math.round(width * 0.16));
    const slot = (width - padLeft - padRight) / bars.length;
    const xAt = (index) => padLeft + index * slot + slot / 2;
    canvas.paperMap = { bars, history, dropped, pad: padLeft, slot, crypto: !!(options && options.crypto), symbol: (options && options.symbol) || "", label: (options && options.label) || "" };
    const edgeRead = history.length > EDGE_WARM + 2 && !(options && options.edge === false) ? edge(history, { live: liveBar(history, !!(options && options.crypto)) }) : null;
    ctx.strokeStyle = "#2a3340";
    ctx.fillStyle = "#93a0ad";
    const grids = height < 150 ? 2 : 4;
    for (let grid = 0; grid < grids; grid += 1) {
      const price = low + ((high - low) * grid) / Math.max(1, grids - 1);
      const line = y(price);
      ctx.beginPath();
      ctx.moveTo(padLeft, line);
      ctx.lineTo(width - padRight, line);
      ctx.stroke();
      ctx.fillText(price.toFixed(price >= 100 ? 2 : 4), 4, line + 4);
    }
    const momFull = momentumBars(history, { crypto: !!(options && options.crypto), live: liveBar(history, !!(options && options.crypto)) });
    paintCandles(ctx, bars, xAt, y, slot, {
      tone: momFull.tone.slice(dropped),
      rim: momFull.rim.slice(dropped),
      bias: momFull.bias.slice(dropped),
      locked: momFull.locked.slice(dropped),
      volumeDot: momFull.volumeDot.slice(dropped),
    });
    // Product name on the tape (symbol · label when provided).
    const product = String((options && (options.label || options.symbol)) || "").trim();
    if (product && height >= 120) {
      ctx.save();
      let size = Math.max(16, Math.min(28, Math.floor(width / 18)));
      ctx.font = "700 " + size + "px Segoe UI, sans-serif";
      const maxW = Math.max(60, width - padLeft - padRight - 24);
      while (size > 12 && ctx.measureText(product).width > maxW) {
        size -= 1;
        ctx.font = "700 " + size + "px Segoe UI, sans-serif";
      }
      const textW = ctx.measureText(product).width;
      const px = Math.max(padLeft + 6, (width - textW) / 2);
      const py = Math.max(18, padTop + 4);
      ctx.fillStyle = "rgba(7, 9, 11, 0.45)";
      ctx.fillText(product, px + 1, py + 1);
      ctx.fillStyle = "rgba(231, 237, 243, 0.92)";
      ctx.fillText(product, px, py);
      ctx.restore();
    }
    stack.forEach((row) => {
      strokeSeries(ctx, row.values.slice(dropped), xAt, y, row.color, row.length >= 100 ? 1.75 : 1.25);
    });
    if (options && options.averages === true) {
      strokeSeries(ctx, lines.high, xAt, y, "#3ddc97", 1.5);
      strokeSeries(ctx, lines.close, xAt, y, "#5b9dff", 1.5);
      strokeSeries(ctx, lines.low, xAt, y, "#ff6b7a", 1.5);
    }
    paintPivots(ctx, pivots, xAt, y, bars.length - 1);
    fitted.arrows.forEach((mark) => {
      const index = mark.index - dropped;
      if (index < 0 || index >= bars.length) return;
      const bar = bars[index];
      const x = xAt(index);
      const up = mark.side === "buy";
      ctx.fillStyle = up ? "#3ddc97" : "#ff6b7a";
      const point = Math.max(24, Math.min(priceBottom - 12, up ? y(bar.low) + 18 : y(bar.high) - 18));
      ctx.beginPath();
      if (up) {
        ctx.moveTo(x, point - 8);
        ctx.lineTo(x - 6, point + 5);
        ctx.lineTo(x + 6, point + 5);
      } else {
        ctx.moveTo(x, point + 8);
        ctx.lineTo(x - 6, point - 5);
        ctx.lineTo(x + 6, point - 5);
      }
      ctx.closePath();
      ctx.fill();
    });
    if (lower) {
      const histoTop = priceBottom + 10;
      const histoBottom = height - 10;
      const zero = (histoTop + histoBottom) / 2;
      const scale = Math.max(...study.momentum.map((value) => Math.abs(value || 0)), 1e-9);
      ctx.strokeStyle = "#2a3340";
      ctx.beginPath();
      ctx.moveTo(padLeft, zero);
      ctx.lineTo(width - padRight, zero);
      ctx.stroke();
      study.momentum.forEach((value, index) => {
        if (value == null) return;
        const x = xAt(index);
        const tone = momentumTone(value, index > 0 ? study.momentum[index - 1] : null);
        const barHeight = (Math.abs(value) / scale) * ((histoBottom - histoTop) / 2 - 6);
        ctx.fillStyle = tone;
        ctx.fillRect(x - Math.max(1, slot * 0.28), value >= 0 ? zero - barHeight : zero, Math.max(2, slot * 0.56), Math.max(1, barHeight));
        ctx.fillStyle = squeezeTone(study.level[index] || 0);
        ctx.beginPath();
        ctx.arc(x, zero, Math.max(2, Math.min(4, slot * 0.22)), 0, Math.PI * 2);
        ctx.fill();
      });
      if (edgeRead && edgeRead.ready) drawEdgeCalls(ctx, edgeRead.marks, dropped, bars.length, xAt, histoTop, histoBottom, padLeft, width - padRight);
    }
    const tone = fitted.mood;
    if (tone && tone.score != null && height >= 150) {
      const moodText = tone.label + " " + tone.score;
      ctx.fillStyle = tone.label === "Fear" ? "#ff6b7a" : tone.label === "Greed" ? "#3ddc97" : "#d7e2ea";
      ctx.fillText(moodText, Math.max(padLeft, (width - ctx.measureText(moodText).width) / 2), 14);
    }
    {
      const known = edgeRead;
      if (known && known.ready) {
        ctx.save();
        ctx.strokeStyle = "#e2b15a";
        ctx.lineWidth = 1.25;
        let drawing = false;
        known.trail.slice(dropped).forEach((value, index) => {
          if (value == null) {
            if (drawing) ctx.stroke();
            drawing = false;
            return;
          }
          if (!drawing) {
            ctx.beginPath();
            ctx.moveTo(xAt(index) - slot / 2, y(value));
            drawing = true;
          } else ctx.lineTo(xAt(index) - slot / 2, y(value));
          ctx.lineTo(xAt(index) + slot / 2, y(value));
        });
        if (drawing) ctx.stroke();
        known.marks.forEach((mark) => {
          const index = mark.index - dropped;
          if (index < 0 || index >= bars.length) return;
          ctx.fillStyle = mark.side === "buy" ? "#3ddc97" : "#ff6b7a";
          ctx.strokeStyle = "#07090b";
          ctx.lineWidth = 1.5;
          ctx.beginPath();
          ctx.arc(xAt(index), y(mark.price), Math.max(3, Math.min(4.5, slot * 0.6)), 0, Math.PI * 2);
          ctx.fill();
          ctx.stroke();
        });
        ctx.restore();
      }
    }
    if (canvas.paperPick) {
      const at = bars.findIndex((bar) => bar.date === canvas.paperPick);
      if (at >= 0) {
        ctx.save();
        ctx.setLineDash([4, 4]);
        ctx.strokeStyle = "#cfd8e3";
        ctx.beginPath();
        ctx.moveTo(xAt(at), padTop);
        ctx.lineTo(xAt(at), priceBottom);
        ctx.stroke();
        ctx.restore();
      }
    }
    ctx.fillStyle = "#93a0ad";
    if (height >= 150) {
      ctx.fillText(bars[0].date, padLeft, 14);
      const endLabel = bars[bars.length - 1].date;
      ctx.fillText(endLabel, Math.max(padLeft + 8, width - 8 - ctx.measureText(endLabel).width), 14);
    }
  }

  const EDGE_FEE = 0.001;
  const EDGE_WARM = 50;
  const EDGE_DOTS = 5;
  const EDGE_TRAIL = 2.5;
  // Seven Fire checks: chart-stack TMO · Squeeze · CCI · Desk Waves + closed-bar VIX falling (real ^VIX / .VIX only).
  const EDGE_SCORE = 7;
  const PRO_RISK = 0.01;

  function dayKey(label) {
    return String(label || "").slice(0, 10);
  }

  function vixSeries(vixBars) {
    const rows = [];
    (vixBars || []).forEach((bar) => {
      if (!bar) return;
      const date = dayKey(bar.date);
      const close = Number(bar.close);
      if (!date || !(close > 0)) return;
      rows.push({ date, close });
    });
    rows.sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
    return rows;
  }

  function vixFallingOn(series, label) {
    // Closed-bar VIX gate: latest VIX daily close on/before this bar is strictly below the prior VIX close.
    // Fail closed when the real series is missing — never invent TICK/ADD/TRIN/VOLD or VIX.
    if (!series || series.length < 2) return false;
    const day = dayKey(label);
    if (!day) return false;
    let at = -1;
    for (let index = 0; index < series.length; index += 1) {
      if (series[index].date <= day) at = index;
      else break;
    }
    if (at < 1) return false;
    return series[at].close < series[at - 1].close;
  }

  function edgeAtr(bars, period) {
    const out = new Array(bars.length).fill(null);
    let value = null;
    for (let index = 0; index < bars.length; index += 1) {
      const bar = bars[index];
      const prior = index > 0 ? bars[index - 1].close : bar.close;
      const range = Math.max(bar.high - bar.low, Math.abs(bar.high - prior), Math.abs(bar.low - prior));
      if (index < period) {
        value = value == null ? range : value + range;
        if (index === period - 1) {
          value /= period;
          out[index] = value;
        }
        continue;
      }
      value = (value * (period - 1) + range) / period;
      out[index] = value;
    }
    return out;
  }

  function proRsi(closes, period) {
    const out = new Array(closes.length).fill(null);
    if (closes.length <= period) return out;
    let gain = 0;
    let loss = 0;
    for (let index = 1; index <= period; index += 1) {
      const change = closes[index] - closes[index - 1];
      if (change >= 0) gain += change;
      else loss -= change;
    }
    gain /= period;
    loss /= period;
    out[period] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
    for (let index = period + 1; index < closes.length; index += 1) {
      const change = closes[index] - closes[index - 1];
      gain = (gain * (period - 1) + Math.max(change, 0)) / period;
      loss = (loss * (period - 1) + Math.max(-change, 0)) / period;
      out[index] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
    }
    return out;
  }

  function bollinger(bars, length, width) {
    const window = length || 20;
    const spread = width || 2;
    const count = bars.length;
    const mid = new Array(count).fill(null);
    const upper = new Array(count).fill(null);
    const lower = new Array(count).fill(null);
    for (let index = window - 1; index < count; index += 1) {
      let total = 0;
      for (let cursor = index - window + 1; cursor <= index; cursor += 1) total += bars[cursor].close;
      const mean = total / window;
      let variance = 0;
      for (let cursor = index - window + 1; cursor <= index; cursor += 1) variance += (bars[cursor].close - mean) ** 2;
      const deviation = Math.sqrt(variance / window);
      mid[index] = mean;
      upper[index] = mean + spread * deviation;
      lower[index] = mean - spread * deviation;
    }
    return { mid, upper, lower };
  }

  function macd(bars) {
    const closes = bars.map((bar) => bar.close);
    const fast = ema(closes, 12);
    const slow = ema(closes, 26);
    const line = closes.map((_, index) => (fast[index] != null && slow[index] != null ? fast[index] - slow[index] : null));
    const signal = ema(line, 9);
    const hist = line.map((value, index) => (value != null && signal[index] != null ? value - signal[index] : null));
    return { line, signal, hist };
  }

  function rollingVwap(bars, length) {
    const window = length || 20;
    const out = new Array(bars.length).fill(null);
    let priced = 0;
    let volume = 0;
    for (let index = 0; index < bars.length; index += 1) {
      const bar = bars[index];
      const weight = Math.max(0, Number(bar.volume) || 0);
      priced += ((bar.high + bar.low + bar.close) / 3) * weight;
      volume += weight;
      if (index >= window) {
        const old = bars[index - window];
        const oldWeight = Math.max(0, Number(old.volume) || 0);
        priced -= ((old.high + old.low + old.close) / 3) * oldWeight;
        volume -= oldWeight;
      }
      if (index >= window - 1 && volume > 0) out[index] = priced / volume;
    }
    return out;
  }

  function volumeProfile(bars, buckets) {
    const count = Math.max(8, buckets || 24);
    const rows = [];
    const list = (bars || []).filter((bar) => (Number(bar.volume) || 0) > 0);
    if (!list.length) return { rows, poc: null, high: null, low: null, total: 0 };
    let low = Infinity;
    let high = -Infinity;
    list.forEach((bar) => {
      low = Math.min(low, bar.low);
      high = Math.max(high, bar.high);
    });
    if (!(high > low)) return { rows, poc: null, high: null, low: null, total: 0 };
    const step = (high - low) / count;
    for (let index = 0; index < count; index += 1) rows.push({ low: low + index * step, high: low + (index + 1) * step, volume: 0 });
    let total = 0;
    list.forEach((bar) => {
      const from = Math.max(0, Math.min(count - 1, Math.floor((bar.low - low) / step)));
      const to = Math.max(0, Math.min(count - 1, Math.floor((bar.high - low) / step)));
      const share = bar.volume / (to - from + 1);
      for (let index = from; index <= to; index += 1) rows[index].volume += share;
      total += bar.volume;
    });
    let peak = 0;
    rows.forEach((row, index) => {
      if (row.volume > rows[peak].volume) peak = index;
    });
    let inside = rows[peak].volume;
    let top = peak;
    let bottom = peak;
    while (inside < total * 0.7 && (top < count - 1 || bottom > 0)) {
      const above = top < count - 1 ? rows[top + 1].volume : -1;
      const below = bottom > 0 ? rows[bottom - 1].volume : -1;
      if (above >= below) {
        top += 1;
        inside += above;
      } else {
        bottom -= 1;
        inside += below;
      }
    }
    return {
      rows,
      poc: (rows[peak].low + rows[peak].high) / 2,
      high: rows[top].high,
      low: rows[bottom].low,
      total,
    };
  }

  function volatility(bars, length) {
    const window = length || 20;
    const list = bars || [];
    const count = list.length;
    const hv = new Array(count).fill(null);
    if (count <= window) return { hv, yearly: null, rank: null, width: null, widthRank: null, regime: "" };
    const returns = list.map((bar, index) => (index > 0 && list[index - 1].close > 0 && bar.close > 0 ? Math.log(bar.close / list[index - 1].close) : 0));
    const weekends = list.slice(-30).some((bar) => {
      const day = new Date(bar.date + "T00:00:00Z").getUTCDay();
      return day === 0 || day === 6;
    });
    const year = weekends ? 365 : 252;
    for (let index = window; index < count; index += 1) {
      let mean = 0;
      for (let cursor = index - window + 1; cursor <= index; cursor += 1) mean += returns[cursor];
      mean /= window;
      let variance = 0;
      for (let cursor = index - window + 1; cursor <= index; cursor += 1) variance += (returns[cursor] - mean) ** 2;
      hv[index] = Math.sqrt(variance / (window - 1)) * Math.sqrt(year);
    }
    const bands = bollinger(list, 20, 2);
    const width = bands.mid.map((mid, index) => (mid ? (bands.upper[index] - bands.lower[index]) / mid : null));
    const rankOf = (series) => {
      const now = series[count - 1];
      if (now == null) return null;
      const past = series.slice(-252).filter((value) => value != null);
      if (past.length < 60) return null;
      return past.filter((value) => value <= now).length / past.length;
    };
    const rank = rankOf(hv);
    let regime = "";
    if (rank != null) regime = rank < 0.2 ? "Quiet" : rank < 0.6 ? "Normal" : rank < 0.9 ? "High" : "Extreme";
    return { hv, yearly: hv[count - 1], rank, width: width[count - 1], widthRank: rankOf(width), regime, year };
  }

  function pro(bars, equity) {
    const list = bars || [];
    const count = list.length;
    const closes = list.map((bar) => bar.close);
    const last = count - 1;
    const long = ema(closes, 200);
    const mid = ema(closes, 50);
    const strength = proRsi(closes, 14);
    const bands = bollinger(list, 20, 2);
    const trend = macd(list);
    const range = edgeAtr(list, 14);
    const vwap = rollingVwap(list, 20);
    const profile = volumeProfile(list.slice(-120), 24);
    const swing = volatility(list, 20);
    const close = last >= 0 ? closes[last] : null;
    const atr = last >= 0 ? range[last] : null;
    const stopGap = atr ? EDGE_TRAIL * atr : null;
    const riskCash = equity > 0 ? equity * PRO_RISK : null;
    const units = stopGap && riskCash ? riskCash / stopGap : null;
    const capped = units != null && close ? Math.min(units, equity / close) : null;
    return {
      ema200: long,
      ema50: mid,
      rsi: strength,
      bands,
      macd: trend,
      atr: range,
      vwap,
      profile,
      swing,
      close,
      last: {
        ema200: last >= 0 ? long[last] : null,
        ema50: last >= 0 ? mid[last] : null,
        rsi: last >= 0 ? strength[last] : null,
        upper: last >= 0 ? bands.upper[last] : null,
        mid: last >= 0 ? bands.mid[last] : null,
        lower: last >= 0 ? bands.lower[last] : null,
        macd: last >= 0 ? trend.line[last] : null,
        signal: last >= 0 ? trend.signal[last] : null,
        crossUp: last > 0 && trend.line[last] > trend.signal[last] && trend.line[last - 1] <= trend.signal[last - 1],
        crossDown: last > 0 && trend.line[last] < trend.signal[last] && trend.line[last - 1] >= trend.signal[last - 1],
        atr,
        vwap: last >= 0 ? vwap[last] : null,
      },
      risk: { pct: PRO_RISK, cash: riskCash, stop: close != null && stopGap ? close - stopGap : null, units: capped, capped: units != null && capped < units },
    };
  }

  function dayIn(zone, now) {
    const parts = new Intl.DateTimeFormat("en-CA", { timeZone: zone, year: "numeric", month: "2-digit", day: "2-digit" }).formatToParts(now || new Date());
    const pick = (type) => (parts.find((part) => part.type === type) || {}).value || "";
    return pick("year") + "-" + pick("month") + "-" + pick("day");
  }

  function liveBar(bars, crypto, now) {
    const list = bars || [];
    if (!list.length) return false;
    const last = String(list[list.length - 1].date || "").slice(0, 10);
    return last >= dayIn(crypto ? "UTC" : "America/New_York", now);
  }

  function closedBars(bars, crypto, now) {
    const list = bars || [];
    return liveBar(list, crypto, now) ? list.slice(0, -1) : list;
  }

  function barIndexAt(pad, slot, count, x) {
    if (!(slot > 0) || !(count > 0)) return -1;
    return Math.max(0, Math.min(count - 1, Math.floor((x - pad) / slot)));
  }

  function hiddenTape(bars, extras) {
    const list = bars || [];
    const extra = extras || {};
    const count = list.length;
    const last = count ? list[count - 1] : null;
    const volumes = list.map((bar) => Math.max(0, Number(bar.volume) || 0));
    const prior = volumes.slice(Math.max(0, count - 21), Math.max(0, count - 1)).filter((value) => value > 0);
    const average = prior.length >= 5 ? prior.reduce((sum, value) => sum + value, 0) / prior.length : null;
    const volume = last ? volumes[count - 1] : 0;
    const rvol = average && volume > 0 ? volume / average : null;
    const width = last ? last.high - last.low : 0;
    const ranges = edgeAtr(list, 14);
    const atr = last ? ranges[count - 1] : null;
    const rangePct = atr ? width / atr : null;
    const closeLoc = last && width > 0 ? (last.close - last.low) / width : null;
    let up = 0;
    let down = 0;
    const from = Math.max(1, count - 20);
    for (let index = from; index < count; index += 1) {
      const weight = volumes[index];
      if (list[index].close >= list[index - 1].close) up += weight;
      else down += weight;
    }
    const total = up + down;
    const upShare = total > 0 ? up / total : null;
    let absorption = "";
    if (rvol != null && rangePct != null) {
      if (rvol >= 1.5 && rangePct <= 0.7) absorption = "absorption";
      else if (rvol >= 1.5 && rangePct >= 1.4) absorption = "initiative";
      else if (rvol < 0.7) absorption = "quiet";
      else absorption = "normal";
    } else if (rvol != null && rvol < 0.7) absorption = "quiet";
    let effort = "";
    if (closeLoc != null) {
      if (closeLoc >= 0.66 && (upShare == null || upShare >= 0.5)) effort = "buyers";
      else if (closeLoc <= 0.34 && (upShare == null || upShare <= 0.5)) effort = "sellers";
      else effort = "mixed";
    }
    const numberOrNull = (value) => (Number(value) > 0 ? Number(value) : null);
    const bid = numberOrNull(extra.bid);
    const ask = numberOrNull(extra.ask);
    const spread = bid && ask && ask >= bid ? (ask - bid) / ((ask + bid) / 2) : null;
    const kind = extra.kind || (extra.crypto ? "crypto" : "");
    let note = "Dark pool books, iceberg orders, and institutional blotters are private. The public tape includes delayed off-exchange prints after they report. These lines are not a look at resting hidden orders.";
    if (kind === "crypto") note = "Dark pools are stock venues. Crypto here is the public exchange tape.";
    else if (kind === "future") note = "Block desks and hidden futures books are private. These lines use the public tape only.";
    return {
      rvol,
      volume: volume > 0 ? volume : null,
      average,
      rangePct,
      closeLoc,
      upShare,
      absorption,
      effort,
      bid,
      ask,
      spread,
      feedRelVol: numberOrNull(extra.relVol),
      afterVolume: numberOrNull(extra.afterVolume),
      tenDayAvg: numberOrNull(extra.tenDayAvg),
      kind,
      note,
      ready: count >= 21 && average != null,
    };
  }

  // Big Prints · Iceberg Proxy — closed-bar public tape only. Not a dark-pool book.
  function bigPrintRead(bars, extras) {
    const tape = hiddenTape(bars, extras);
    if (!tape.ready) {
      return {
        ready: false,
        reason: "Need 21 closed bars with volume",
        rvol: null,
        absorption: "",
        effort: "",
        volume: null,
        score: 0,
        label: "—",
        proxy: "",
      };
    }
    const rvol = tape.feedRelVol || tape.rvol;
    let score = 0;
    let label = "Quiet tape";
    let proxy = "";
    if (rvol != null) {
      if (rvol >= 2.5) score += 40;
      else if (rvol >= 1.8) score += 28;
      else if (rvol >= 1.5) score += 18;
      else if (rvol >= 1.2) score += 8;
    }
    if (tape.absorption === "absorption") {
      score += 35;
      label = "Iceberg Proxy";
      proxy = "absorption";
    } else if (tape.absorption === "initiative") {
      score += 30;
      label = "Big Print";
      proxy = "initiative";
    } else if (tape.absorption === "normal" && rvol != null && rvol >= 1.5) {
      score += 12;
      label = "Elevated volume";
      proxy = "elevated";
    } else if (tape.absorption === "quiet") {
      label = "Quiet tape";
      proxy = "quiet";
    } else {
      label = "Normal tape";
      proxy = "normal";
    }
    if (tape.effort === "buyers") score += 10;
    else if (tape.effort === "sellers") score += 10;
    if (tape.afterVolume && tape.volume && tape.afterVolume > tape.volume * 0.15) score += 5;
    score = Math.max(0, Math.min(100, score));
    return {
      ready: true,
      reason: "",
      rvol,
      absorption: tape.absorption || "",
      effort: tape.effort || "",
      volume: tape.volume,
      closeLoc: tape.closeLoc,
      upShare: tape.upShare,
      score,
      label,
      proxy,
      note: tape.note,
    };
  }

  function edge(bars, options) {
    const list = bars || [];
    const count = list.length;
    const live = !!(options && options.live) && count > 0;
    const end = live ? count - 1 : count;
    const closes = list.map((bar) => bar.close);
    const study = compression(list, 20);
    const squeeze = study.momentum;
    const tmo = trueMomentum(list, 14, 5, 3);
    const cciSlow = cci(list, 50);
    const cciMid = cci(list, 14);
    const cciFast = cci(list, 5);
    const range = edgeAtr(list, 14);
    const big4 = big4Indicator(list);
    const vix = vixSeries(options && options.vixBars);
    // Desk Waves (Volume Wave) — green/up wave agree on the chart stack under CCI. Fail closed without volume.
    const waves = volumeWave(list, { n: 6, atrMult: 0.7, avgLen: 6, avgType: "sma" });
    const dots = new Array(count).fill(0);
    let run = 0;
    for (let index = 0; index < count; index += 1) {
      run = study.level[index] > 0 ? run + 1 : 0;
      dots[index] = run;
    }
    const score = new Array(count).fill(null);
    const trail = new Array(count).fill(null);
    const parts = new Array(count).fill(null);
    const stages = new Array(count).fill(null);
    for (let index = EDGE_WARM; index < end; index += 1) {
      const now = squeeze[index];
      const main = tmo.main[index];
      const signal = tmo.signal[index];
      // TMO −10: main cleared above the −10 OS line on this closed bar (best Fire expectancy on the desk scan).
      const tmoOs = main != null && main > tmo.os;
      const allCci =
        cciSlow[index] != null &&
        cciMid[index] != null &&
        cciFast[index] != null &&
        cciSlow[index] > 100 &&
        cciMid[index] > 100 &&
        cciFast[index] > 100;
      // Into zone: all three above +100 and a closed-bar cross into +100 on at least one CCI line.
      const cciCross =
        index > 0 &&
        allCci &&
        ((cciSlow[index - 1] != null && cciSlow[index - 1] <= 100 && cciSlow[index] > 100) ||
          (cciMid[index - 1] != null && cciMid[index - 1] <= 100 && cciMid[index] > 100) ||
          (cciFast[index - 1] != null && cciFast[index - 1] <= 100 && cciFast[index] > 100));
      // Desk Waves green / up-wave: dir === 1 (large green hist). Fail closed when volume is missing.
      const waveUp =
        !!(waves && waves.hasVolume && waves.ready && waves.dir[index] === 1);
      const checks = {
        // Chart stack: TMO · Squeeze · CCI · Desk Waves (closed bars only). Optimized Fire gates for Squeeze scan / Fire 5.
        tmoOs,
        // TMO turns green: main above signal (bullish green paint / cloud on the closed bar).
        tmo: main != null && signal != null && main > signal,
        // Five red squeeze dots, or currently in a squeeze (any compression level).
        dots: dots[index] >= EDGE_DOTS || study.level[index] > 0,
        above: now != null && now > 0,
        // CCI 50 · 14 · 5 all into +100 with a closed-bar cross into the zone.
        cci: cciCross,
        // Desk Waves · Volume Wave green/up agree (pane under CCI). Own closed-bar math.
        wave: waveUp,
        // Real VIX only: closed daily VIX falling vs prior close. Fail closed without a feed.
        vix: vixFallingOn(vix, list[index].date),
      };
      parts[index] = checks;
      score[index] = Object.values(checks).filter(Boolean).length;
      // Desk Ready · Aim · Fire staging (closed bars). Ready 5/7 · Aim 6/7 · Fire 7/7.
      if (score[index] >= EDGE_SCORE) stages[index] = "fire";
      else if (score[index] >= EDGE_SCORE - 1) stages[index] = "aim";
      else if (score[index] >= EDGE_SCORE - 2) stages[index] = "ready";
    }
    const trades = [];
    const marks = [];
    let holding = null;
    let pending = "";
    let equity = 1;
    let peak = 1;
    let worst = 0;
    let barsIn = 0;
    for (let index = EDGE_WARM; index < count; index += 1) {
      const bar = list[index];
      if (pending === "buy" && !holding) {
        const price = bar.open;
        const stop = price - EDGE_TRAIL * (range[index - 1] || 0);
        const precise = big4.bias[index - 1] === "bull" || big4.ok[index - 1];
        holding = { index, date: bar.date, price, stop, high: bar.close, stage: "fire", precise };
        marks.push({ index, side: "buy", price, stage: "fire", precise });
      } else if (pending === "sell" && holding) {
        const price = bar.open;
        const pct = (price * (1 - EDGE_FEE)) / (holding.price * (1 + EDGE_FEE)) - 1;
        trades.push({ entryDate: holding.date, exitDate: bar.date, entry: holding.price, exit: price, pct, why: holding.why });
        marks.push({ index, side: "sell", price, stage: "fire", precise: true });
        equity *= 1 + pct;
        holding = null;
      }
      pending = "";
      if (index >= end) {
        if (holding) trail[index] = holding.stop;
        continue;
      }
      if (holding) {
        barsIn += 1;
        holding.high = Math.max(holding.high, bar.close);
        const lift = holding.high - EDGE_TRAIL * (range[index] || 0);
        if (lift > holding.stop) holding.stop = lift;
        trail[index] = holding.stop;
        const open = holding.price * (1 + EDGE_FEE);
        const marked = equity * (bar.close / open);
        if (marked > peak) peak = marked;
        worst = Math.min(worst, marked / peak - 1);
        if (bar.close < holding.stop) holding.why = "Trailing stop";
        else if (squeeze[index] != null && squeeze[index] < 0) holding.why = "Squeeze momentum turned negative";
        else holding.why = "";
        if (holding.why) pending = "sell";
      } else {
        if (equity > peak) peak = equity;
        const prior = index > EDGE_WARM ? score[index - 1] : 0;
        // Fire: first closed bar where all seven Edge checks turn on. big4indicator tags the most precise fires.
        if (score[index] === EDGE_SCORE && prior < EDGE_SCORE) pending = "buy";
      }
    }
    const last = count - 1;
    const settled = end - 1;
    const first = Math.min(EDGE_WARM, Math.max(0, settled));
    const hold = end > EDGE_WARM ? closes[settled] / list[first].open - 1 : 0;
    const total = equity - 1;
    const book = tradeBookStats(trades);
    const recent = recentTradeBook(trades, RECENT_TRADE_WINDOW);
    const wins = book.wins;
    let call = "WAIT";
    const filledToday = live ? marks.find((mark) => mark.index === last) : null;
    if (pending === "buy") call = "BUY";
    else if (pending === "sell") call = "SELL";
    else if (filledToday) call = filledToday.side === "buy" ? "BUY" : "SELL";
    else if (holding) call = "HOLD";
    const stageNow = settled >= 0 ? stages[settled] : null;
    const openPct = holding ? closes[last] / (holding.price * (1 + EDGE_FEE)) - 1 : null;
    return {
      call,
      score: settled >= 0 ? score[settled] : null,
      parts: settled >= 0 ? parts[settled] : null,
      scores: score,
      allParts: parts,
      stages,
      stage: stageNow,
      big4: settled >= 0 ? { bias: big4.bias[settled], ok: big4.ok[settled] || big4.bias[settled] === "bull", label: big4.label, name: big4.name } : null,
      live,
      filled: filledToday ? { side: filledToday.side, price: filledToday.price } : null,
      signalDate: settled >= 0 ? list[settled].date : null,
      reason: pending === "sell" && holding ? holding.why : (filledToday && filledToday.side === "sell" && trades.length ? trades[trades.length - 1].why || "" : ""),
      stop: holding ? holding.stop : (pending === "buy" && range[settled] ? closes[settled] - EDGE_TRAIL * range[settled] : null),
      checks: EDGE_SCORE,
      entry: holding ? holding.price : null,
      openPct,
      trail,
      marks,
      trades,
      record: {
        trades: trades.length,
        wins,
        winRate: trades.length ? wins / trades.length : 0,
        total,
        hold,
        worst,
        maxDD: book.maxDD,
        profitFactor: book.profitFactor,
        recent,
        exposure: end > EDGE_WARM ? barsIn / (end - EDGE_WARM) : 0,
        bars: Math.max(0, end - EDGE_WARM),
      },
      ready: end > EDGE_WARM + 1,
      vixReady: vix.length >= 2,
    };
  }


  const CLOCK_ZONES = {};

  function clockOf(label, convert, zone) {
    const match = /^(\d{4}-\d{2}-\d{2}) (\d{2}):(\d{2})$/.exec(String(label || ""));
    if (!match) return null;
    if (!convert) return { day: match[1], minutes: Number(match[2]) * 60 + Number(match[3]) };
    const moment = new Date(match[1] + "T" + match[2] + ":" + match[3] + ":00Z");
    if (!CLOCK_ZONES[zone]) {
      CLOCK_ZONES[zone] = new Intl.DateTimeFormat("en-CA", { timeZone: zone, hourCycle: "h23", year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit" });
    }
    const parts = {};
    CLOCK_ZONES[zone].formatToParts(moment).forEach((part) => {
      parts[part.type] = part.value;
    });
    return { day: parts.year + "-" + parts.month + "-" + parts.day, minutes: Number(parts.hour) * 60 + Number(parts.minute) };
  }

  function openingDay(rows, position, lastIndex, plan) {
    const opener = rows[position];
    const out = { day: opener.day, openLabel: opener.bar.date, high: opener.bar.high, low: opener.bar.low, state: "FORMING", entry: null, exit: null, r: null };
    out.range = opener.bar.high - opener.bar.low;
    out.trigger = opener.bar.high * (1 + plan.buffer);
    out.floor = opener.bar.low * (1 - plan.buffer);
    out.target = out.trigger + out.range * plan.multiple;
    out.risk = out.trigger - out.floor;
    out.closed = opener.index < lastIndex;
    if (!out.closed || !(out.range > 0)) return out;
    out.state = "ARMED";
    const after = rows.slice(position + 1);
    let longAt = -1;
    for (let at = 0; at < after.length; at += 1) {
      const bar = after[at].bar;
      if (longAt < 0) {
        const up = bar.high >= out.trigger;
        const down = bar.low <= out.floor;
        if (up && down) {
          out.state = "BOTH";
          return out;
        }
        if (down) {
          out.state = "BREAK_DOWN";
          return out;
        }
        if (up) {
          longAt = at;
          out.state = "LONG";
          out.entry = out.trigger;
        }
        continue;
      }
      const stopped = bar.low <= out.floor;
      const hit = bar.high >= out.target;
      if (stopped) {
        out.state = "STOPPED";
        out.exit = out.floor;
        out.r = -1;
        return out;
      }
      if (hit) {
        out.state = "TARGET";
        out.exit = out.target;
        out.r = out.risk > 0 ? (out.target - out.trigger) / out.risk : null;
        return out;
      }
    }
    if (longAt >= 0 && after.length) {
      out.last = after[after.length - 1].bar.close;
      out.r = out.risk > 0 ? (out.last - out.trigger) / out.risk : null;
    } else if (longAt < 0 && after.length) {
      out.last = after[after.length - 1].bar.close;
    }
    return out;
  }

  function openingRange(bars, options) {
    const opt = options || {};
    const list = Array.isArray(bars) ? bars : [];
    const plan = {
      buffer: opt.buffer > 0 ? opt.buffer : 0,
      multiple: opt.multiple > 0 ? opt.multiple : 1,
    };
    const crypto = !!opt.crypto;
    const openAt = Number.isFinite(opt.openAt) ? opt.openAt : crypto ? 0 : 570;
    const convert = !!opt.utcLabels && !crypto;
    const zone = opt.zone || "America/New_York";
    const rows = [];
    list.forEach((bar, index) => {
      const clock = clockOf(bar.date, convert, zone);
      if (clock) rows.push({ index, bar, day: clock.day, minutes: clock.minutes });
    });
    if (rows.length < 2) return { ready: false, reason: "The 15-minute strategy needs intraday bars." };
    const days = [];
    rows.forEach((row) => {
      const last = days[days.length - 1];
      if (last && last[0].day === row.day) last.push(row);
      else days.push([row]);
    });
    const lastIndex = list.length - 1;
    const results = [];
    days.forEach((group, dayIndex) => {
      let position = group.findIndex((row) => row.minutes === openAt);
      let fallback = false;
      if (position < 0 && dayIndex === days.length - 1) {
        position = 0;
        fallback = true;
      }
      if (position < 0) return;
      const result = openingDay(group, position, lastIndex, plan);
      result.fallback = fallback;
      result.latest = dayIndex === days.length - 1;
      results.push(result);
    });
    if (!results.length) return { ready: false, reason: "No opening bar found at that clock time." };
    const latest = results[results.length - 1];
    const past = results.filter((row) => !row.latest && row.closed);
    const taken = past.filter((row) => row.entry != null);
    const wins = taken.filter((row) => row.state === "TARGET").length;
    const losses = taken.filter((row) => row.state === "STOPPED").length;
    const rs = taken.map((row) => row.r).filter((value) => value != null && Number.isFinite(value));
    return {
      ready: true,
      latest,
      state: latest.state,
      plan,
      openAt,
      record: {
        days: past.length,
        trades: taken.length,
        wins,
        losses,
        open: taken.length - wins - losses,
        avgR: rs.length ? rs.reduce((sum, value) => sum + value, 0) / rs.length : null,
        totalR: rs.length ? rs.reduce((sum, value) => sum + value, 0) : null,
        skipped: past.filter((row) => row.state === "BREAK_DOWN" || row.state === "BOTH").length,
      },
    };
  }


  function wilderRsi(closes, period) {
    const out = new Array(closes.length).fill(null);
    if (closes.length <= period) return out;
    let gain = 0;
    let loss = 0;
    for (let index = 1; index <= period; index += 1) {
      const change = closes[index] - closes[index - 1];
      if (change > 0) gain += change;
      else loss -= change;
    }
    gain /= period;
    loss /= period;
    out[period] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
    for (let index = period + 1; index < closes.length; index += 1) {
      const change = closes[index] - closes[index - 1];
      gain = (gain * (period - 1) + Math.max(0, change)) / period;
      loss = (loss * (period - 1) + Math.max(0, -change)) / period;
      out[index] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
    }
    return out;
  }

  function simpleAverage(values, length) {
    const out = new Array(values.length).fill(null);
    let total = 0;
    for (let index = 0; index < values.length; index += 1) {
      total += values[index];
      if (index >= length) total -= values[index - length];
      if (index >= length - 1) out[index] = total / length;
    }
    return out;
  }

  function cci(bars, length) {
    const window = Math.max(2, Math.round(Number(length) || 20));
    const list = bars || [];
    const out = new Array(list.length).fill(null);
    const typical = list.map((bar) => (bar.high + bar.low + bar.close) / 3);
    for (let index = window - 1; index < list.length; index += 1) {
      let sum = 0;
      for (let back = 0; back < window; back += 1) sum += typical[index - back];
      const mean = sum / window;
      let deviation = 0;
      for (let back = 0; back < window; back += 1) deviation += Math.abs(typical[index - back] - mean);
      deviation /= window;
      out[index] = deviation === 0 ? 0 : (typical[index] - mean) / (0.015 * deviation);
    }
    return out;
  }

  function cciPane(bars) {
    const list = bars || [];
    const count = list.length;
    const slow = cci(list, 50);
    const mid = cci(list, 14);
    const fast = cci(list, 5);
    const up = new Array(count).fill(false);
    const down = new Array(count).fill(false);
    for (let index = 1; index < count; index += 1) {
      if (slow[index] == null || slow[index - 1] == null) continue;
      if (slow[index - 1] <= 100 && slow[index] > 100) up[index] = true;
      if (slow[index - 1] >= -100 && slow[index] < -100) down[index] = true;
      const snap = cciSnapState(fast, slow, index);
      if (snap.fireUp) up[index] = true;
      if (snap.fireDown) down[index] = true;
    }
    let hi = 100;
    let lo = -100;
    [slow, mid, fast].forEach((series) => {
      series.forEach((value) => {
        if (value == null) return;
        if (value > hi) hi = value;
        if (value < lo) lo = value;
      });
    });
    const pad = Math.max(40, (hi - lo) * 0.08);
    return {
      fast,
      mid,
      slow,
      up,
      down,
      high: hi + pad,
      low: lo - pad,
      levels: [-100, 0, 100],
    };
  }

  // Weighted moving average for Hull range gates (Desk Waves).
  function wma(values, length) {
    const list = values || [];
    const out = new Array(list.length).fill(null);
    const window = Math.max(1, Math.round(Number(length) || 1));
    const weight = (window * (window + 1)) / 2;
    for (let index = window - 1; index < list.length; index += 1) {
      let total = 0;
      let ok = true;
      for (let step = 0; step < window; step += 1) {
        const value = list[index - window + 1 + step];
        if (value == null || Number.isNaN(value)) {
          ok = false;
          break;
        }
        total += value * (step + 1);
      }
      if (ok) out[index] = total / weight;
    }
    return out;
  }

  // Hull moving average — used on ATR for Desk Waves / Volume Wave range gates.
  function hull(values, length) {
    const window = Math.max(2, Math.round(Number(length) || 6));
    const half = Math.max(1, Math.round(window / 2));
    const root = Math.max(1, Math.round(Math.sqrt(window)));
    const fast = wma(values, half);
    const slow = wma(values, window);
    const mixed = (values || []).map((_, index) => {
      if (fast[index] == null || slow[index] == null) return null;
      return 2 * fast[index] - slow[index];
    });
    return wma(mixed, root);
  }

  function waveAverage(values, length, avgType) {
    const window = Math.max(1, Math.round(Number(length) || 6));
    if (String(avgType || "sma").toLowerCase() === "ema") return ema(values, window);
    const list = values || [];
    const out = new Array(list.length).fill(null);
    for (let index = 0; index < list.length; index += 1) {
      if (index + 1 < window) continue;
      let total = 0;
      let ok = true;
      for (let cursor = index - window + 1; cursor <= index; cursor += 1) {
        if (list[cursor] == null || Number.isNaN(list[cursor])) {
          ok = false;
          break;
        }
        total += list[cursor];
      }
      if (ok) out[index] = total / window;
    }
    return out;
  }

  // Desk Waves / Volume Wave — closed-bar accum/distrib volume waves gated by Hull ATR.
  // Own desk math only. Fail closed when the feed has no volume (no invented tape).
  function volumeWave(bars, options) {
    const opt = options || {};
    const n = Math.max(2, Math.min(80, Math.round(Number(opt.n) || 6)));
    const atrMultRaw = Number(opt.atrMult);
    const atrMult = Number.isFinite(atrMultRaw) && atrMultRaw > 0 ? atrMultRaw : 0.7;
    const avgLen = Math.max(1, Math.min(80, Math.round(Number(opt.avgLen) || n)));
    const avgType = String(opt.avgType || "sma").toLowerCase() === "ema" ? "ema" : "sma";
    const list = bars || [];
    const count = list.length;
    const up = new Array(count).fill(null);
    const dn = new Array(count).fill(null);
    const avg = new Array(count).fill(null);
    const dir = new Array(count).fill(null);
    const wave = new Array(count).fill(null);
    const paint = new Array(count).fill(null);
    const hullAtr = new Array(count).fill(null);
    if (!count) {
      return { up, dn, avg, dir, wave, paint, hullAtr, n, atrMult, avgLen, avgType, ready: false, hasVolume: false };
    }
    let hasVolume = false;
    for (let index = 0; index < count; index += 1) {
      if (Number(list[index].volume) > 0) {
        hasVolume = true;
        break;
      }
    }
    if (!hasVolume) {
      return { up, dn, avg, dir, wave, paint, hullAtr, n, atrMult, avgLen, avgType, ready: false, hasVolume: false };
    }
    const atr = edgeAtr(list, n);
    const hullGate = hull(atr, n);
    for (let index = 0; index < count; index += 1) hullAtr[index] = hullGate[index];
    const mid = list.map((bar) => (Number(bar.high) + Number(bar.low)) / 2);
    let currentDir = 0;
    let currentWave = 0;
    for (let index = 1; index < count; index += 1) {
      const band = hullGate[index] != null ? hullGate[index] * atrMult : null;
      if (band == null || mid[index] == null || mid[index - 1] == null) continue;
      if (mid[index] > mid[index - 1] + band) currentDir = 1;
      else if (mid[index] < mid[index - 1] - band) currentDir = -1;
      else if (currentDir === 0) currentDir = mid[index] >= mid[index - 1] ? 1 : -1;
      // Missing or zero volume adds 0 — never invent tape.
      const vol = Math.max(0, Number(list[index].volume) || 0);
      if (dir[index - 1] == null || currentDir !== dir[index - 1]) currentWave = vol;
      else currentWave += vol;
      dir[index] = currentDir;
      wave[index] = currentWave;
      up[index] = currentDir === 1 ? currentWave : 0;
      dn[index] = currentDir === -1 ? -currentWave : 0;
      paint[index] = currentDir === 1 ? "bull" : "bear";
    }
    const magnitude = wave.map((value) => (value == null ? null : Math.abs(value)));
    const average = waveAverage(magnitude, avgLen, avgType);
    for (let index = 0; index < count; index += 1) avg[index] = average[index];
    const ready = dir.some((value) => value != null);
    return { up, dn, avg, dir, wave, paint, hullAtr, n, atrMult, avgLen, avgType, ready, hasVolume: true };
  }

  function cciSnapState(fast, slow, index) {
    if (fast[index] == null || slow[index] == null) return { readyUp: false, readyDown: false, fireUp: false, fireDown: false, gap: null };
    const gap = slow[index] - fast[index];
    const readyUp = gap > 150;
    const readyDown = gap < -150;
    const priorGap = index > 0 && fast[index - 1] != null && slow[index - 1] != null ? slow[index - 1] - fast[index - 1] : null;
    const priorReadyUp = priorGap != null && priorGap > 150;
    const priorReadyDown = priorGap != null && priorGap < -150;
    return {
      readyUp,
      readyDown,
      fireUp: priorReadyUp && !readyUp,
      fireDown: priorReadyDown && !readyDown,
      gap,
    };
  }

  function aggregateIndexed(daily, keyFn) {
    const groups = [];
    (daily || []).forEach((bar, index) => {
      const key = keyFn(bar.date);
      const last = groups[groups.length - 1];
      if (!last || last.key !== key) {
        groups.push({
          key,
          date: bar.date,
          open: bar.open,
          high: bar.high,
          low: bar.low,
          close: bar.close,
          volume: bar.volume,
          end: index,
        });
        return;
      }
      last.high = Math.max(last.high, bar.high);
      last.low = Math.min(last.low, bar.low);
      last.close = bar.close;
      last.volume += bar.volume;
      last.date = bar.date;
      last.end = index;
    });
    return groups;
  }

  function higherTrendOk(bars, at) {
    if (!bars || at < 25 || at >= bars.length) return false;
    const slice = bars.slice(0, at + 1);
    const closes = slice.map((bar) => bar.close);
    const tmo = trueMomentum(slice, 14, 5, 3);
    const average = ema(closes, 20);
    const last = at;
    return (
      tmo.main[last] != null &&
      tmo.signal[last] != null &&
      tmo.main[last] > tmo.signal[last] &&
      average[last] != null &&
      closes[last] > average[last]
    );
  }

  // Completed higher-timeframe bias only (drops the forming week/month). No look-ahead.
  function multiFrameLongFlags(daily, end) {
    const flags = new Array(end).fill(false);
    const through = (daily || []).slice(0, end);
    if (through.length < 80) return flags;
    const weekGroups = aggregateIndexed(through, weekKey);
    const monthGroups = aggregateIndexed(through, (date) => date.slice(0, 7));
    const weekBars = weekGroups.map((row) => ({ date: row.date, open: row.open, high: row.high, low: row.low, close: row.close, volume: row.volume }));
    const monthBars = monthGroups.map((row) => ({ date: row.date, open: row.open, high: row.high, low: row.low, close: row.close, volume: row.volume }));
    const weekOk = new Array(weekBars.length).fill(false);
    const monthOk = new Array(monthBars.length).fill(false);
    for (let index = 25; index < weekBars.length; index += 1) weekOk[index] = higherTrendOk(weekBars, index);
    for (let index = 25; index < monthBars.length; index += 1) monthOk[index] = higherTrendOk(monthBars, index);
    let weekSignal = false;
    let monthSignal = false;
    let weekCursor = 0;
    let monthCursor = 0;
    for (let day = 0; day < end; day += 1) {
      while (weekCursor < weekGroups.length - 1 && weekGroups[weekCursor].end <= day) {
        if (weekCursor >= 25) weekSignal = weekOk[weekCursor];
        weekCursor += 1;
      }
      while (monthCursor < monthGroups.length - 1 && monthGroups[monthCursor].end <= day) {
        if (monthCursor >= 25) monthSignal = monthOk[monthCursor];
        monthCursor += 1;
      }
      flags[day] = weekSignal && monthSignal;
    }
    return flags;
  }

  function multiFrameSnapshot(daily, end) {
    const through = (daily || []).slice(0, end);
    const weekGroups = aggregateIndexed(through, weekKey);
    const monthGroups = aggregateIndexed(through, (date) => date.slice(0, 7));
    const weekBars = weekGroups.slice(0, Math.max(0, weekGroups.length - 1)).map((row) => ({ date: row.date, open: row.open, high: row.high, low: row.low, close: row.close, volume: row.volume }));
    const monthBars = monthGroups.slice(0, Math.max(0, monthGroups.length - 1)).map((row) => ({ date: row.date, open: row.open, high: row.high, low: row.low, close: row.close, volume: row.volume }));
    const weekOk = weekBars.length > 25 ? higherTrendOk(weekBars, weekBars.length - 1) : false;
    const monthOk = monthBars.length > 25 ? higherTrendOk(monthBars, monthBars.length - 1) : false;
    return {
      weekOk,
      monthOk,
      mtfOk: weekOk && monthOk,
      weekBars: weekBars.length,
      monthBars: monthBars.length,
    };
  }

  // Recent closed-trade window for Strat Health (own desk math — not a third-party pack).
  const RECENT_TRADE_WINDOW = 8;
  const INCUBATE_MIN_TRADES = 5;

  function tradeBookStats(trades) {
    let wins = 0;
    let grossWin = 0;
    let grossLoss = 0;
    let growth = 1;
    let worst = 0;
    let equity = 1;
    let peak = 1;
    let maxDD = 0;
    (trades || []).forEach((trade) => {
      const pct = Number(trade && trade.pct) || 0;
      if (pct > 0) {
        wins += 1;
        grossWin += pct;
      } else if (pct < 0) {
        grossLoss += -pct;
      }
      growth *= 1 + pct;
      equity *= 1 + pct;
      if (equity > peak) peak = equity;
      maxDD = Math.min(maxDD, equity / peak - 1);
      worst = Math.min(worst, pct);
    });
    const count = (trades || []).length;
    let profitFactor = null;
    if (grossLoss > 0) profitFactor = grossWin / grossLoss;
    else if (grossWin > 0) profitFactor = Infinity;
    return {
      trades: count,
      wins,
      winRate: count ? wins / count : 0,
      total: growth - 1,
      worst,
      maxDD,
      profitFactor,
      grossWin,
      grossLoss,
    };
  }

  function recentTradeBook(trades, windowSize) {
    const size = Math.max(3, Number(windowSize) || RECENT_TRADE_WINDOW);
    const slice = (trades || []).slice(-size);
    return tradeBookStats(slice);
  }

  function runRule(list, end, warm, rule) {
    const trades = [];
    let open = null;
    let pending = null;
    let barsIn = 0;
    const close = (index, price, why) => {
      trades.push({ entry: open.entry, exit: price, entryIndex: open.entryIndex, exitIndex: index, pct: price / open.entry - 1, why });
      open = null;
    };
    for (let index = warm; index < end; index += 1) {
      const bar = list[index];
      if (pending && pending.type === "buy" && !open) open = { entry: bar.open, entryIndex: index, stop: pending.stop };
      else if (pending && pending.type === "sell" && open) close(index, bar.open, "signal exit");
      pending = null;
      if (open && open.stop > 0 && bar.low <= open.stop) close(index, Math.min(bar.open, open.stop), "stop");
      if (open) barsIn += 1;
      if (index === end - 1) break;
      if (open) {
        if (rule.exit(index, open)) pending = { type: "sell" };
      } else {
        const entry = rule.enter(index);
        if (entry) pending = { type: "buy", stop: entry.stop || 0 };
      }
    }
    const book = tradeBookStats(trades);
    const recent = recentTradeBook(trades, RECENT_TRADE_WINDOW);
    const lastClose = end > 0 ? list[end - 1].close : 0;
    let openPct = null;
    let growth = 1 + book.total;
    if (open) {
      openPct = lastClose / open.entry - 1;
      growth *= 1 + openPct;
    }
    const startPrice = warm < end ? list[warm].open : 0;
    return {
      trades,
      wins: book.wins,
      open,
      pending,
      openPct,
      total: growth - 1,
      hold: startPrice > 0 ? lastClose / startPrice - 1 : 0,
      worst: book.worst,
      maxDD: book.maxDD,
      profitFactor: book.profitFactor,
      recent,
      exposure: end > warm ? barsIn / (end - warm) : 0,
    };
  }

  // Own desk impulse / flip helpers for scorecard-style strategies (closed-bar only).
  function pulseBandDir(list) {
    const closes = list.map((bar) => bar.close);
    const mid = ema(closes, 8);
    const ranges = edgeAtr(list, 10);
    const dir = new Array(list.length).fill(0);
    let prior = 0;
    for (let index = 0; index < list.length; index += 1) {
      if (mid[index] == null || ranges[index] == null) {
        dir[index] = prior;
        continue;
      }
      const upper = mid[index] + 0.8 * ranges[index];
      const lower = mid[index] - 0.8 * ranges[index];
      if (closes[index] > upper) prior = 1;
      else if (closes[index] < lower) prior = -1;
      dir[index] = prior;
    }
    return dir;
  }

  function rangeFlipDir(list, mult) {
    const factor = mult || 3;
    const ranges = edgeAtr(list, 14);
    const dir = new Array(list.length).fill(0);
    const finalUpper = new Array(list.length).fill(null);
    const finalLower = new Array(list.length).fill(null);
    let prior = 0;
    for (let index = 0; index < list.length; index += 1) {
      if (ranges[index] == null) {
        dir[index] = prior;
        continue;
      }
      const basic = (list[index].high + list[index].low) / 2;
      let upper = basic + factor * ranges[index];
      let lower = basic - factor * ranges[index];
      if (index > 0 && finalUpper[index - 1] != null) {
        if (list[index - 1].close < finalUpper[index - 1]) upper = Math.min(upper, finalUpper[index - 1]);
      }
      if (index > 0 && finalLower[index - 1] != null) {
        if (list[index - 1].close > finalLower[index - 1]) lower = Math.max(lower, finalLower[index - 1]);
      }
      finalUpper[index] = upper;
      finalLower[index] = lower;
      if (index > 0 && finalUpper[index - 1] != null && list[index].close > finalUpper[index - 1]) prior = 1;
      else if (index > 0 && finalLower[index - 1] != null && list[index].close < finalLower[index - 1]) prior = -1;
      dir[index] = prior;
    }
    return dir;
  }

  const STRATEGY_INFO = {
    edge: { name: "Edge · Ready · Aim · Fire", who: "This bot", idea: "Ready at 5 of 7, Aim at 6, Fire when all seven turn on together on a closed bar: TMO above −10, TMO turns green (main above signal / bullish green paint), 5 squeeze dots or in a squeeze, squeeze momentum above zero, CCI 50 · 14 · 5 all into zone +100 on a closed-bar cross, Desk Waves green / up (Volume Wave pane under CCI; fail closed without volume), and VIX falling on its latest closed daily bar vs the prior close (real ^VIX / .VIX only; fail closed if missing). Same gates drive Squeeze scan, Fire 5, and desk scanners. Buy fills at the next open. Auto prefers Fire with bullish big4indicator. Exits on squeeze momentum under zero or a 2.5 ATR trail under the best close. Desk staging only — not a third-party product." },
    squeeze: { name: "Squeeze release", who: "Volatility compression", idea: "Buy the first bar after Bollinger Bands move back outside Keltner Channels when momentum is above zero and rising. Stop under the low of that bar. Exit after two falling momentum bars." },
    quad: {
      name: "Squeeze · TMO · big4indicator · stack · MTF",
      who: "Four desk studies · multi-TF",
      idea: "Buys when this desk's squeeze just released with momentum above zero and rising, true momentum is above its signal, big4indicator agrees (close above the 50 and 200 EMAs, MACD above signal, RSI between 30 and 70), the SMA stack is lined up 5 over 9 over 14 over 50, and completed weekly plus monthly bars also agree (higher-TF true momentum above signal and close above the 20-bar EMA). Stop under that bar's low. Exit when true momentum crosses below its signal or the close falls under the 50 EMA. Own closed-bar math only — not a third-party or trademarked study.",
    },
    ccizoom: {
      name: "CCI zoom zone",
      who: "CCI trend zone",
      idea: "Buys when the 50-bar CCI crosses above +100 (zoom up). Exits when it crosses back below +100. Zoom down (CCI under −100) is shown for context; this long-only desk does not short. Classic public CCI rules. Not a forecast.",
    },
    ccisnap: {
      name: "CCI snap-back",
      who: "CCI continuation",
      idea: "In a zoom-up (50-bar CCI above +100), a snap-back up is ready when CCI(5) sits more than 150 points under CCI(50), and it fires when that gap closes back under 150. Buys on that fire. Exits on a snap-back down fire or when CCI(50) leaves the zoom-up. Classic public CCI rules. Not a forecast.",
    },
    donchian: { name: "20-day breakout", who: "Range breakout", idea: "Buy a close above the highest high of the last 20 bars. Exit on a close below the lowest low of the last 10 bars." },
    rsi2: { name: "RSI 2 pullback", who: "Short pullback", idea: "In an uptrend (close above the 200-bar average) buy when 2-period RSI drops under 10. Exit when price closes above its 5-bar average." },
    golden: { name: "50/200 average cross", who: "Long trend", idea: "Buy when the 50-bar average crosses above the 200-bar average. Exit when the 50-bar average crosses back below." },
    dualema: { name: "20/50 EMA cross", who: "Swing trend", idea: "Buy when the 20-bar EMA crosses above the 50-bar EMA. Exit when it crosses back below." },
    macdx: { name: "MACD cross", who: "Momentum cross", idea: "Buy when the MACD line crosses above its signal line. Exit when it crosses back below." },
    bollrev: { name: "Bollinger reversion", who: "Mean reversion", idea: "In an uptrend above the 200-bar average, buy a close under the lower Bollinger band. Exit when price closes back at or above the middle band." },
    atrtrail: { name: "EMA trend · ATR trail", who: "Volatility trail", idea: "Buy when the 20-bar EMA crosses above the 50-bar EMA. Exit when the close falls 2.5 ATR under the best high since entry." },
    seatrail: {
      name: "Seat Trail",
      who: "Seat-aware breakout trail",
      idea: "Patience breakout on closed bars only: close above the prior 20-bar high, relative volume ≥ 1.8× the 20-bar average, ATR(14) expanding vs its 20-bar average, Range Seat Mid or High, and close above the 50 EMA. Initial stop and trail width follow Range Seat (wider on Seat High, tighter on Mid/Low). Exit on the seat-aware ATR trail under the best high, or after 40 held bars. Own desk math — not a third-party product. Distinct from Edge / Fire 5 / Tide / Go Long. Paper only. Not a forecast.",
    },
    leanbook: {
      name: "Lean Book",
      who: "Small-paper category desk",
      idea: "Original closed-bar Lean Book for a paper small book: Coil Spark (desk squeeze on or just fired with bullish momentum), Stack Climb (EMA 8 over 21 over 34 with close above 34), Seat High (Range Seat Mid or High), and Book Sync (weekly squeeze or weekly close above the 20 EMA). Armed when Coil Spark · Stack Climb · Seat High agree. Stop under close by 2×ATR(14); exit on that trail under the best high, when Coil Spark fades, or after 25 held bars. Cash Guard on the Tools board keeps paper risk lean. Own desk names only — not a third-party course or product. Paper only. Live locked. Not a forecast.",
    },
    pulseband: {
      name: "Pulse Band Lock",
      who: "Desk impulse bands",
      idea: "Own closed-bar impulse bands: mid = EMA(8), band = mid ± 0.8×ATR(10). Direction locks bull when close finishes above the upper band and bear when close finishes below the lower band (holds prior otherwise). BUY on a bar-close bull lock; SELL on a bar-close bear lock. Zero look-ahead. Not a third-party heatmap product.",
    },
    rangeflip: {
      name: "Range Flip Trail",
      who: "Desk ATR flip",
      idea: "Own ATR flip trail on closed bars: basic mid = (high+low)/2, bands at ±3×ATR(14) with sticky upper/lower. BUY when close flips the trail bullish; SELL when close flips it bearish. Long-only desk. Not a third-party Supertrend port.",
    },
    anchorvwap: {
      name: "Anchor VWAP Rebound",
      who: "Desk volume anchor",
      idea: "Own rolling 50-bar VWAP rebound: BUY when close crosses back above VWAP after at least three closes below it, with volume ≥ 1.2× the 20-bar average. Exit when close crosses back below VWAP. Closed bars only. Not a forecast.",
    },
    stretchpivot: {
      name: "Stretch Pivot",
      who: "Desk RSI · band pivot",
      idea: "Own stretch pivot: in an uptrend above the 200 SMA, BUY when close tags or undercuts the lower Bollinger(20,2) while RSI(14) is under 35 and rising vs three bars ago (price stretch with improving RSI). Exit when RSI clears 65 or close reaches the middle band. Closed bars only. Not a third-party divergence pack.",
    },
    confirmgate: {
      name: "Confirm Gate",
      who: "Desk multi-gate confirm",
      idea: "Own confirmation stack: counts four closed-bar gates — Pulse Band bull, Range Flip bull, close above 50-bar VWAP, RSI(14) rising from under 45. BUY when gates cross from under 2 to 2+; SELL when gates fall back under 2. Bar-close locked. Own desk math — not a premium multi-algo product.",
    },
    weeklow: { name: "7-day low pullback", who: "Weekly pullback", idea: "Above the 200-bar average, buy a close that is the lowest of the last 7 bars. Exit on a close that is the highest of the last 7 bars." },
  };
  const STRATEGY_IDS = Object.keys(STRATEGY_INFO);

  function strategies(bars, options) {
    const list = bars || [];
    const count = list.length;
    const live = !!(options && options.live) && count > 0;
    const end = live ? count - 1 : count;
    const rows = [];
    const closes = list.map((bar) => bar.close);
    const finish = (id, run, extra) => {
      const call = run.pending ? (run.pending.type === "buy" ? "BUY" : "SELL") : run.open ? "HOLD" : "WAIT";
      rows.push({
        id,
        ...STRATEGY_INFO[id],
        ready: true,
        call,
        trades: run.trades.length,
        wins: run.wins,
        winRate: run.trades.length ? run.wins / run.trades.length : 0,
        total: run.total,
        hold: run.hold,
        worst: run.worst,
        maxDD: run.maxDD,
        profitFactor: run.profitFactor,
        recent: run.recent || null,
        exposure: run.exposure,
        entry: run.open ? run.open.entry : null,
        openPct: run.openPct,
        stop: run.pending && run.pending.stop > 0 ? run.pending.stop : run.open && run.open.stop > 0 ? run.open.stop : null,
        log: run.trades.slice(-12).map((trade) => ({
          entryDate: list[trade.entryIndex].date,
          exitDate: list[trade.exitIndex].date,
          entry: trade.entry,
          exit: trade.exit,
          pct: trade.pct,
          why: trade.why,
        })),
        openSince: run.open ? list[run.open.entryIndex].date : null,
        ...(extra || {}),
      });
    };
    const notReady = (id, why) =>
      rows.push({
        id,
        ...STRATEGY_INFO[id],
        ready: false,
        call: "WAIT",
        trades: 0,
        winRate: 0,
        total: 0,
        hold: 0,
        worst: 0,
        maxDD: 0,
        profitFactor: null,
        recent: null,
        why,
      });

    const known = edge(list, { live });
    if (known && known.ready) {
      rows.push({
        id: "edge",
        ...STRATEGY_INFO.edge,
        ready: true,
        call: known.call,
        trades: known.record.trades,
        wins: Math.round(known.record.winRate * known.record.trades),
        winRate: known.record.winRate,
        total: known.record.total,
        hold: known.record.hold,
        worst: known.record.worst,
        maxDD: known.record.maxDD,
        profitFactor: known.record.profitFactor,
        recent: known.record.recent || null,
        exposure: known.record.exposure,
        entry: known.entry,
        openPct: known.openPct,
        stop: known.stop,
        signalDate: known.signalDate,
        log: known.trades.slice(-12).map((trade) => ({
          entryDate: trade.entryDate,
          exitDate: trade.exitDate,
          entry: trade.entry,
          exit: trade.exit,
          pct: trade.pct,
          why: trade.why || "Exit rule",
        })),
        openSince: known.entry ? ((known.marks || []).filter((mark) => mark.side === "buy").slice(-1).map((mark) => list[mark.index].date)[0] || null) : null,
        stage: known.stage,
        big4: known.big4,
        stages: known.stages,
      });
    } else notReady("edge", "Needs at least 52 bars.");

    if (end >= 60) {
      const study = compression(list, 20);
      const mom = study.momentum;
      const on = study.level.map((value) => value >= 2);
      const fast = ema(closes, 8);
      const slow = ema(closes, 21);
      const rising = (index) => mom[index] != null && mom[index - 1] != null && mom[index] > 0 && mom[index] > mom[index - 1];
      const run = runRule(list, end, 40, {
        enter: (index) => (!on[index] && on[index - 1] && rising(index) ? { stop: list[index].low } : null),
        exit: (index) => mom[index] != null && mom[index - 1] != null && mom[index - 2] != null && mom[index] < mom[index - 1] && mom[index - 1] < mom[index - 2],
      });
      let dots = 0;
      for (let index = end - 1; index >= 0 && on[index]; index -= 1) dots += 1;
      const last = end - 1;
      const fired = !on[last] && on[last - 1];
      finish("squeeze", run, {
        dots,
        squeezeOn: dots > 0,
        fired,
        momentum: mom[last],
        momentumUp: rising(last),
        trend: fast[last] != null && slow[last] != null && fast[last] > slow[last],
        bearish: fired && mom[last] != null && mom[last] < 0 && mom[last - 1] != null && mom[last] < mom[last - 1],
      });
    } else notReady("squeeze", "Needs at least 60 bars.");

    if (end >= 260) {
      const study = compression(list, 20);
      const mom = study.momentum;
      const on = study.level.map((value) => value >= 2);
      const tmo = trueMomentum(list, 14, 5, 3);
      const ema50 = ema(closes, 50);
      const stack = stackAverages(list);
      const s5 = stack[0].values;
      const s9 = stack[1].values;
      const s14 = stack[2].values;
      const s50 = stack[3].values;
      const mtfFlags = multiFrameLongFlags(list, end);
      const rising = (index) => mom[index] != null && mom[index - 1] != null && mom[index] > 0 && mom[index] > mom[index - 1];
      const tmoUp = (index) => tmo.main[index] != null && tmo.signal[index] != null && tmo.main[index] > tmo.signal[index];
      const big4 = big4Indicator(list);
      const big4Ok = (index) => !!big4.ok[index];
      const stackOk = (index) =>
        s5[index] != null &&
        s9[index] != null &&
        s14[index] != null &&
        s50[index] != null &&
        s5[index] > s9[index] &&
        s9[index] > s14[index] &&
        s14[index] > s50[index];
      const mtfOk = (index) => !!mtfFlags[index];
      const run = runRule(list, end, 240, {
        enter: (index) => {
          const fired = !on[index] && on[index - 1];
          return fired && rising(index) && tmoUp(index) && big4Ok(index) && stackOk(index) && mtfOk(index)
            ? { stop: list[index].low }
            : null;
        },
        exit: (index) => {
          const tmoDown =
            tmo.main[index] != null &&
            tmo.signal[index] != null &&
            tmo.main[index - 1] != null &&
            tmo.signal[index - 1] != null &&
            tmo.main[index] < tmo.signal[index] &&
            tmo.main[index - 1] >= tmo.signal[index - 1];
          const under50 = ema50[index] != null && closes[index] < ema50[index];
          return tmoDown || under50;
        },
      });
      const last = end - 1;
      const fired = !on[last] && on[last - 1];
      const snap = multiFrameSnapshot(list, end);
      finish("quad", run, {
        fired,
        squeezeOk: fired && rising(last),
        tmoOk: tmoUp(last),
        b4Ok: big4Ok(last),
        big4Ok: big4Ok(last),
        big4Bias: big4.bias[last],
        stackOk: stackOk(last),
        weekOk: snap.weekOk,
        monthOk: snap.monthOk,
        mtfOk: snap.mtfOk,
        momentum: mom[last],
      });
    } else notReady("quad", "Needs at least 260 bars for weekly and monthly multi-TF.");

    if (end >= 60) {
      const slow = cci(list, 50);
      const run = runRule(list, end, 55, {
        enter: (index) => (slow[index] != null && slow[index - 1] != null && slow[index] > 100 && slow[index - 1] <= 100 ? { stop: 0 } : null),
        exit: (index) => slow[index] != null && slow[index - 1] != null && slow[index] < 100 && slow[index - 1] >= 100,
      });
      const last = end - 1;
      finish("ccizoom", run, {
        zoomUp: slow[last] != null && slow[last] > 100,
        zoomDown: slow[last] != null && slow[last] < -100,
        cci50: slow[last],
      });
    } else notReady("ccizoom", "Needs at least 60 bars.");

    if (end >= 60) {
      const fast = cci(list, 5);
      const slow = cci(list, 50);
      const run = runRule(list, end, 55, {
        enter: (index) => {
          if (slow[index] == null || slow[index] <= 100) return null;
          const snap = cciSnapState(fast, slow, index);
          return snap.fireUp ? { stop: list[index].low } : null;
        },
        exit: (index) => {
          const leaveZoom = slow[index] != null && slow[index - 1] != null && slow[index] < 100 && slow[index - 1] >= 100;
          const snap = cciSnapState(fast, slow, index);
          return leaveZoom || snap.fireDown;
        },
      });
      const last = end - 1;
      const snap = cciSnapState(fast, slow, last);
      finish("ccisnap", run, {
        zoomUp: slow[last] != null && slow[last] > 100,
        zoomDown: slow[last] != null && slow[last] < -100,
        readyUp: snap.readyUp,
        readyDown: snap.readyDown,
        fireUp: snap.fireUp,
        fireDown: snap.fireDown,
        cci5: fast[last],
        cci50: slow[last],
        gap: snap.gap,
      });
    } else notReady("ccisnap", "Needs at least 60 bars.");

    if (end >= 40) {
      const run = runRule(list, end, 25, {
        enter: (index) => {
          let top = -Infinity;
          for (let back = 1; back <= 20; back += 1) top = Math.max(top, list[index - back].high);
          return list[index].close > top ? { stop: 0 } : null;
        },
        exit: (index) => {
          let floor = Infinity;
          for (let back = 1; back <= 10; back += 1) floor = Math.min(floor, list[index - back].low);
          return list[index].close < floor;
        },
      });
      finish("donchian", run);
    } else notReady("donchian", "Needs at least 40 bars.");

    if (end >= 230) {
      const rsi = wilderRsi(closes, 2);
      const long = simpleAverage(closes, 200);
      const short = simpleAverage(closes, 5);
      const run = runRule(list, end, 205, {
        enter: (index) => (long[index] != null && closes[index] > long[index] && rsi[index] != null && rsi[index] < 10 ? { stop: 0 } : null),
        exit: (index) => short[index] != null && closes[index] > short[index],
      });
      finish("rsi2", run);
    } else notReady("rsi2", "Needs at least 230 bars.");

    if (end >= 220) {
      const fast = simpleAverage(closes, 50);
      const slow = simpleAverage(closes, 200);
      const run = runRule(list, end, 205, {
        enter: (index) => (fast[index] != null && slow[index] != null && fast[index - 1] != null && slow[index - 1] != null && fast[index] > slow[index] && fast[index - 1] <= slow[index - 1] ? { stop: 0 } : null),
        exit: (index) => fast[index] != null && slow[index] != null && fast[index - 1] != null && slow[index - 1] != null && fast[index] < slow[index] && fast[index - 1] >= slow[index - 1],
      });
      finish("golden", run);
    } else notReady("golden", "Needs at least 220 bars.");

    if (end >= 80) {
      const fast = ema(closes, 20);
      const slow = ema(closes, 50);
      const run = runRule(list, end, 55, {
        enter: (index) => (fast[index] != null && slow[index] != null && fast[index - 1] != null && slow[index - 1] != null && fast[index] > slow[index] && fast[index - 1] <= slow[index - 1] ? { stop: 0 } : null),
        exit: (index) => fast[index] != null && slow[index] != null && fast[index - 1] != null && slow[index - 1] != null && fast[index] < slow[index] && fast[index - 1] >= slow[index - 1],
      });
      finish("dualema", run);
    } else notReady("dualema", "Needs at least 80 bars.");

    if (end >= 60) {
      const trend = macd(list);
      const run = runRule(list, end, 40, {
        enter: (index) => {
          const line = trend.line[index];
          const signal = trend.signal[index];
          const priorLine = trend.line[index - 1];
          const priorSignal = trend.signal[index - 1];
          return line != null && signal != null && priorLine != null && priorSignal != null && line > signal && priorLine <= priorSignal ? { stop: 0 } : null;
        },
        exit: (index) => {
          const line = trend.line[index];
          const signal = trend.signal[index];
          const priorLine = trend.line[index - 1];
          const priorSignal = trend.signal[index - 1];
          return line != null && signal != null && priorLine != null && priorSignal != null && line < signal && priorLine >= priorSignal;
        },
      });
      finish("macdx", run);
    } else notReady("macdx", "Needs at least 60 bars.");

    if (end >= 230) {
      const long = simpleAverage(closes, 200);
      const bands = bollinger(list, 20, 2);
      const run = runRule(list, end, 205, {
        enter: (index) => (long[index] != null && closes[index] > long[index] && bands.lower[index] != null && closes[index] < bands.lower[index] ? { stop: 0 } : null),
        exit: (index) => bands.mid[index] != null && closes[index] >= bands.mid[index],
      });
      finish("bollrev", run);
    } else notReady("bollrev", "Needs at least 230 bars.");

    if (end >= 80) {
      const fast = ema(closes, 20);
      const slow = ema(closes, 50);
      const ranges = edgeAtr(list, 14);
      const run = runRule(list, end, 55, {
        enter: (index) => (fast[index] != null && slow[index] != null && fast[index - 1] != null && slow[index - 1] != null && ranges[index] != null && fast[index] > slow[index] && fast[index - 1] <= slow[index - 1] ? { stop: list[index].close - 2.5 * ranges[index] } : null),
        exit: (index, open) => {
          if (!open || ranges[index] == null) return false;
          let peak = open.entry;
          for (let back = open.entryIndex; back <= index; back += 1) peak = Math.max(peak, list[back].high);
          return closes[index] < peak - 2.5 * ranges[index];
        },
      });
      finish("atrtrail", run);
    } else notReady("atrtrail", "Needs at least 80 bars.");

    if (end >= 80) {
      const ranges = edgeAtr(list, SEAT_TRAIL_ATR);
      const atrBase = simpleAverage(ranges, SEAT_TRAIL_ATR_BASE);
      const volumes = list.map((bar) => Math.max(0, Number(bar.volume) || 0));
      const volAvg = simpleAverage(volumes, SEAT_TRAIL_CHANNEL);
      const ema50 = ema(closes, 50);
      const hasVolume = volumes.some((value) => value > 0);
      const run = runRule(list, end, 55, {
        enter: (index) => {
          if (!hasVolume) return null;
          const seat = rangeSeatAt(list, index);
          if (!seat || (seat.seat !== "high" && seat.seat !== "mid")) return null;
          if (ema50[index] == null || closes[index] <= ema50[index]) return null;
          if (ranges[index] == null || atrBase[index] == null || !(ranges[index] > atrBase[index])) return null;
          if (volAvg[index] == null || !(volAvg[index] > 0) || !(volumes[index] >= SEAT_TRAIL_VOL_MULT * volAvg[index])) return null;
          let top = -Infinity;
          for (let back = 1; back <= SEAT_TRAIL_CHANNEL; back += 1) top = Math.max(top, list[index - back].high);
          if (!(closes[index] > top)) return null;
          const mult = seatTrailMult(seat.seat);
          return { stop: closes[index] - mult * ranges[index], seat: seat.seat, mult };
        },
        exit: (index, open) => {
          if (!open || ranges[index] == null) return false;
          const held = index - open.entryIndex + 1;
          if (held >= SEAT_TRAIL_MAX_HOLD) return true;
          const seat = rangeSeatAt(list, index);
          const mult = seatTrailMult(seat && seat.seat);
          let peak = open.entry;
          for (let back = open.entryIndex; back <= index; back += 1) peak = Math.max(peak, list[back].high);
          return closes[index] < peak - mult * ranges[index];
        },
      });
      const read = seatTrailRead(list, { live });
      finish("seatrail", run, {
        seat: read && read.seat,
        seatLabel: read && read.seatLabel,
        trailMult: read && read.trailMult,
        breakout: !!(read && read.breakout),
        volumeOk: !!(read && read.volumeOk),
        expandOk: !!(read && read.expandOk),
        structureOk: !!(read && read.structureOk),
        armed: !!(read && read.armed),
      });
    } else notReady("seatrail", "Needs at least 80 bars.");

    if (end >= 80) {
      const ranges = edgeAtr(list, LEAN_BOOK_ATR);
      const run = runRule(list, end, 55, {
        enter: (index) => {
          const snap = leanBookAt(list, index);
          if (!snap || !snap.armed || ranges[index] == null) return null;
          return { stop: closes[index] - LEAN_BOOK_STOP_ATR * ranges[index], lean: true };
        },
        exit: (index, open) => {
          if (!open || ranges[index] == null) return false;
          const held = index - open.entryIndex + 1;
          if (held >= LEAN_BOOK_MAX_HOLD) return true;
          const snap = leanBookAt(list, index);
          if (snap && snap.coilSpark === false) return true;
          let peak = open.entry;
          for (let back = open.entryIndex; back <= index; back += 1) peak = Math.max(peak, list[back].high);
          return closes[index] < peak - LEAN_BOOK_STOP_ATR * ranges[index];
        },
      });
      const lean = leanBookRead(list, { live });
      finish("leanbook", run, {
        armed: !!(lean && lean.armed),
        prime: !!(lean && lean.prime),
        categories: lean && lean.categories,
        coilSpark: !!(lean && lean.coilSpark),
        stackClimb: !!(lean && lean.stackClimb),
        seatHigh: !!(lean && lean.seatHigh),
        bookSync: !!(lean && lean.bookSync),
      });
    } else notReady("leanbook", "Needs at least 80 bars.");

    if (end >= 40) {
      const dir = pulseBandDir(list);
      const run = runRule(list, end, 25, {
        enter: (index) => (dir[index] === 1 && dir[index - 1] !== 1 ? { stop: list[index].low } : null),
        exit: (index) => dir[index] === -1 && dir[index - 1] !== -1,
      });
      finish("pulseband", run);
    } else notReady("pulseband", "Needs at least 40 bars.");

    if (end >= 50) {
      const dir = rangeFlipDir(list, 3);
      const run = runRule(list, end, 30, {
        enter: (index) => (dir[index] === 1 && dir[index - 1] !== 1 ? { stop: list[index].low } : null),
        exit: (index) => dir[index] === -1 && dir[index - 1] !== -1,
      });
      finish("rangeflip", run);
    } else notReady("rangeflip", "Needs at least 50 bars.");

    if (end >= 80) {
      const vwap = rollingVwap(list, 50);
      const volumes = list.map((bar) => Math.max(0, Number(bar.volume) || 0));
      const volAvg = simpleAverage(volumes, 20);
      const hasVolume = volumes.some((value) => value > 0);
      const run = runRule(list, end, 55, {
        enter: (index) => {
          if (!hasVolume || vwap[index] == null || vwap[index - 1] == null || volAvg[index] == null) return null;
          if (!(closes[index] > vwap[index] && closes[index - 1] <= vwap[index - 1])) return null;
          if (!(volumes[index] >= 1.2 * volAvg[index])) return null;
          let below = 0;
          for (let back = 1; back <= 3; back += 1) {
            if (vwap[index - back] == null || !(closes[index - back] < vwap[index - back])) return null;
            below += 1;
          }
          return below === 3 ? { stop: Math.min(list[index].low, vwap[index]) } : null;
        },
        exit: (index) => vwap[index] != null && vwap[index - 1] != null && closes[index] < vwap[index] && closes[index - 1] >= vwap[index - 1],
      });
      finish("anchorvwap", run);
    } else notReady("anchorvwap", "Needs at least 80 bars.");

    if (end >= 230) {
      const long = simpleAverage(closes, 200);
      const bands = bollinger(list, 20, 2);
      const rsi = wilderRsi(closes, 14);
      const run = runRule(list, end, 205, {
        enter: (index) => {
          if (long[index] == null || closes[index] <= long[index]) return null;
          if (bands.lower[index] == null || rsi[index] == null || rsi[index - 3] == null) return null;
          if (!(closes[index] <= bands.lower[index] * 1.002)) return null;
          if (!(rsi[index] < 35 && rsi[index] > rsi[index - 3])) return null;
          return { stop: list[index].low };
        },
        exit: (index) => {
          if (rsi[index] != null && rsi[index] >= 65) return true;
          return bands.mid[index] != null && closes[index] >= bands.mid[index];
        },
      });
      finish("stretchpivot", run);
    } else notReady("stretchpivot", "Needs at least 230 bars.");

    if (end >= 80) {
      const pulse = pulseBandDir(list);
      const flip = rangeFlipDir(list, 3);
      const vwap = rollingVwap(list, 50);
      const rsi = wilderRsi(closes, 14);
      const gateCount = (index) => {
        let score = 0;
        if (pulse[index] === 1) score += 1;
        if (flip[index] === 1) score += 1;
        if (vwap[index] != null && closes[index] > vwap[index]) score += 1;
        if (rsi[index] != null && rsi[index - 1] != null && rsi[index] < 45 && rsi[index] > rsi[index - 1]) score += 1;
        return score;
      };
      const run = runRule(list, end, 55, {
        enter: (index) => (gateCount(index) >= 2 && gateCount(index - 1) < 2 ? { stop: list[index].low } : null),
        exit: (index) => gateCount(index) < 2 && gateCount(index - 1) >= 2,
      });
      finish("confirmgate", run);
    } else notReady("confirmgate", "Needs at least 80 bars.");

    if (end >= 220) {
      const long = simpleAverage(closes, 200);
      const run = runRule(list, end, 205, {
        enter: (index) => {
          if (long[index] == null || closes[index] <= long[index]) return null;
          let floor = Infinity;
          for (let back = 0; back < 7; back += 1) floor = Math.min(floor, closes[index - back]);
          return closes[index] === floor ? { stop: 0 } : null;
        },
        exit: (index) => {
          let top = -Infinity;
          for (let back = 0; back < 7; back += 1) top = Math.max(top, closes[index - back]);
          return closes[index] === top;
        },
      });
      finish("weeklow", run);
    } else notReady("weeklow", "Needs at least 220 bars.");

    const ranked = rows
      .filter((row) => row.ready && row.trades >= 5)
      .sort((a, b) => b.total - a.total);
    return { rows, best: ranked.length ? ranked[0].id : "", end };
  }


  function squeezeRead(bars, options) {
    const all = bars || [];
    const live = !!(options && options.live) && all.length > 0;
    const list = live ? all.slice(0, -1) : all;
    const count = list.length;
    if (count < 60) return { ready: false, reason: "Needs at least 60 closed bars." };
    const study = compression(list, 20);
    const mom = study.momentum;
    const on = study.level.map((value) => value >= 2);
    const last = count - 1;
    let dots = 0;
    for (let index = last; index >= 0 && on[index]; index -= 1) dots += 1;
    let firedAgo = -1;
    if (!on[last]) {
      for (let back = 1; back <= 3 && last - back >= 0; back += 1) {
        if (on[last - back]) {
          firedAgo = back - 1;
          break;
        }
      }
    }
    const state = on[last] ? "ON" : firedAgo >= 0 ? "FIRED" : "OFF";
    const value = mom[last];
    const direction = value == null ? "" : value >= 0 ? "bull" : "bear";
    const closes = list.map((bar) => bar.close);
    const e8 = ema(closes, 8)[last];
    const e21 = ema(closes, 21)[last];
    const e50 = ema(closes, 50)[last];
    const stack = e8 > e21 && e21 > e50 ? "positive" : e8 < e21 && e21 < e50 ? "negative" : "mixed";
    let end = last;
    let size = dots;
    if (state === "FIRED") {
      end = last - firedAgo - 1;
      size = 0;
      for (let index = end; index >= 0 && on[index]; index -= 1) size += 1;
    }
    let clean = size > 0 && direction !== "";
    for (let index = end; clean && index > end - size; index -= 1) {
      const level = mom[index];
      if (level == null || (direction === "bull" && level < 0) || (direction === "bear" && level > 0)) clean = false;
    }
    const before = mom[last - 1];
    const rising = value != null && before != null && (direction === "bull" ? value > before : value < before);
    const level = study.level[state === "FIRED" ? end : last];
    let weekly = false;
    try {
      const weeks = barsFor(list, frameById("1W"));
      if (weeks.length >= 30) {
        const weekStudy = compression(weeks, 20);
        weekly = weekStudy.level[weeks.length - 1] >= 2;
      }
    } catch (error) {
      weekly = false;
    }
    const lastBar = list[last];
    return { ready: true, state, dots: size, level, firedAgo, direction, stack, clean, rising, momentum: value, weekly, price: lastBar.close, asOf: lastBar.date };
  }

  function squeezeScore(row, parents) {
    if (!row || !row.ready || row.state === "OFF") return { score: 0, parts: [], triple: false, edgeFire: false };
    const parts = [];
    const add = (name, points) => {
      if (points > 0) parts.push([name, points]);
    };
    add(row.state === "ON" ? "Squeeze on" : "Just fired", row.state === "ON" ? 30 : 25);
    add("Energy built (dots)", Math.round((Math.min(row.dots, 8) / 8) * 10));
    add(row.level >= 3 ? "High compression" : row.level === 2 ? "Mid compression" : row.level === 1 ? "Low compression" : "", row.level >= 3 ? 10 : row.level === 2 ? 5 : row.level === 1 ? 2 : 0);
    const aligned = (row.direction === "bull" && row.stack === "positive") || (row.direction === "bear" && row.stack === "negative");
    add("Averages stacked with momentum", aligned ? 20 : 0);
    add("Clean squeeze, momentum never crossed", row.clean ? 10 : 0);
    add("Momentum building", row.rising ? 15 : 0);
    add("Weekly squeeze too", row.weekly ? 5 : 0);
    const flags = parents || {};
    const triple = !!(row.state === "ON" && flags.sector && flags.index);
    add("Triple squeeze: stock, sector, index", triple ? 10 : 0);
    // Same optimized Edge Fire gates as Trade / Fire 5 (TMO · Squeeze · CCI).
    const edgeFire = !!(flags.edgeFire);
    add("Edge Fire (TMO · Squeeze · CCI · VIX)", edgeFire ? 25 : 0);
    const total = parts.reduce((sum, item) => sum + item[1], 0);
    return { score: Math.min(100, total), parts, triple, edgeFire };
  }


  const SECTOR_FUNDS = ["XLK", "XLI", "XLF", "XLE", "XLV", "XLY", "XLP", "XLU", "XLB", "XLRE"];
  const INTERNAL_SYMBOLS = ["^VIX", "^VXN", "^VIX9D", "^TNX", "DX-Y.NYB", "RSP", "SPY", "QQQ", "IWM", "HYG", "IEF"].concat(SECTOR_FUNDS);

  function internals(data) {
    const source = data || {};
    const closedOf = (symbol) => {
      const bars = source[symbol];
      if (!Array.isArray(bars) || bars.length < 30) return null;
      const list = closedBars(bars, false);
      return list.length >= 30 ? list : null;
    };
    const lastOf = (list) => list[list.length - 1].close;
    const emaLast = (values, length) => {
      const out = ema(values, length);
      return out[out.length - 1];
    };
    const ratioOf = (top, bottom) => {
      const a = closedOf(top);
      const b = closedOf(bottom);
      if (!a || !b) return null;
      const base = {};
      b.forEach((bar) => {
        base[bar.date] = bar.close;
      });
      const out = [];
      a.forEach((bar) => {
        if (base[bar.date] > 0) out.push({ date: bar.date, close: bar.close / base[bar.date] });
      });
      return out.length >= 30 ? out : null;
    };
    const ratioSays = (list) => {
      if (!list) return null;
      const values = list.map((bar) => bar.close);
      const gap = values[values.length - 1] / emaLast(values, 21) - 1;
      return gap > 0.002 ? "good" : gap < -0.002 ? "bad" : "neutral";
    };
    const parts = [];
    const missing = [];
    const add = (id, name, weight, says, text) => {
      if (says == null) {
        missing.push(name);
        return;
      }
      parts.push({ id, name, weight, says, points: says === "good" ? weight : says === "neutral" ? weight / 2 : 0, text });
    };
    const fixed = (value, places) => (Number.isFinite(value) ? value.toFixed(places == null ? 2 : places) : "—");

    const vix = closedOf("^VIX");
    if (vix) {
      const level = lastOf(vix);
      const average = vix.slice(-20).reduce((sum, bar) => sum + bar.close, 0) / Math.min(20, vix.length);
      const says = level >= 25 || level > average * 1.25 ? "bad" : level <= 20 && level <= average * 1.05 ? "good" : "neutral";
      add("vix", "Fear gauge (VIX)", 15, says, "VIX " + fixed(level) + " against its 20-day average " + fixed(average) + ".");
    } else add("vix", "Fear gauge (VIX)", 15, null);

    const short = closedOf("^VIX9D");
    if (vix && short) {
      const gap = lastOf(short) / lastOf(vix);
      add("term", "Short-term fear against 30-day", 10, gap > 1.05 ? "bad" : gap < 0.95 ? "good" : "neutral", "9-day VIX " + fixed(lastOf(short)) + " against VIX " + fixed(lastOf(vix)) + (gap > 1.05 ? ". Near-term fear is higher than the 30-day, which is a stress sign." : "."));
    } else add("term", "Short-term fear against 30-day", 10, null);

    const vxn = closedOf("^VXN");
    if (vxn) {
      const values = vxn.map((bar) => bar.close);
      const gap = values[values.length - 1] / emaLast(values, 21) - 1;
      add("vxn", "Nasdaq fear (VXN)", 5, gap > 0.05 ? "bad" : gap < 0 ? "good" : "neutral", "VXN " + fixed(lastOf(vxn)) + ", " + fixed(gap * 100, 1) + "% from its 21-day average.");
    } else add("vxn", "Nasdaq fear (VXN)", 5, null);

    const trends = ["SPY", "QQQ"].map((symbol) => {
      const list = closedOf(symbol);
      if (!list) return null;
      const closes = list.map((bar) => bar.close);
      const close = closes[closes.length - 1];
      const e21 = emaLast(closes, 21);
      const e50 = emaLast(closes, 50);
      return { symbol, score: close > e21 && e21 > e50 ? 1 : close > e50 ? 0.5 : 0 };
    }).filter(Boolean);
    if (trends.length) {
      const mean = trends.reduce((sum, item) => sum + item.score, 0) / trends.length;
      add("trend", "Index trend (SPY, QQQ)", 20, mean >= 0.75 ? "good" : mean <= 0.25 ? "bad" : "neutral", trends.map((item) => item.symbol + (item.score === 1 ? " stacked up" : item.score === 0.5 ? " above its 50-day" : " below its 50-day")).join(", ") + ".");
    } else add("trend", "Index trend (SPY, QQQ)", 20, null);

    const sectors = SECTOR_FUNDS.map((symbol) => {
      const list = closedOf(symbol);
      if (!list) return null;
      const closes = list.map((bar) => bar.close);
      return closes[closes.length - 1] > emaLast(closes, 21);
    }).filter((value) => value !== null);
    if (sectors.length >= 7) {
      const share = sectors.filter(Boolean).length / sectors.length;
      add("sectors", "Sector participation", 15, share >= 0.7 ? "good" : share <= 0.4 ? "bad" : "neutral", sectors.filter(Boolean).length + " of " + sectors.length + " sector funds are above their 21-day average.");
    } else add("sectors", "Sector participation", 15, null);

    const equal = ratioSays(ratioOf("RSP", "SPY"));
    add("equal", "Equal weight against cap weight (RSP/SPY)", 5, equal, equal === "good" ? "The average stock is keeping up with the biggest ones." : equal === "bad" ? "The biggest names are carrying the index." : "About even.");
    const small = ratioSays(ratioOf("IWM", "SPY"));
    add("small", "Small against large (IWM/SPY)", 5, small, small === "good" ? "Small companies are leading, a risk-on sign." : small === "bad" ? "Small companies are lagging." : "About even.");

    const offense = [ratioSays(ratioOf("XLY", "XLP")), ratioSays(ratioOf("XLK", "XLU"))].filter(Boolean);
    if (offense.length) {
      const value = offense.reduce((sum, item) => sum + (item === "good" ? 1 : item === "bad" ? -1 : 0), 0) / offense.length;
      add("offense", "Offense against defense", 10, value > 0.25 ? "good" : value < -0.25 ? "bad" : "neutral", value > 0.25 ? "Discretionary and tech are leading staples and utilities." : value < -0.25 ? "Staples and utilities are leading, a defensive tilt." : "No clear lead.");
    } else add("offense", "Offense against defense", 10, null);

    const credit = ratioSays(ratioOf("HYG", "IEF"));
    add("credit", "Credit appetite (HYG/IEF)", 10, credit, credit === "good" ? "Junk bonds are holding up against Treasuries." : credit === "bad" ? "Junk bonds are lagging Treasuries, a stress sign." : "About even.");

    const yields = closedOf("^TNX");
    const dollar = closedOf("DX-Y.NYB");
    if (yields || dollar) {
      const move = (list) => (list ? lastOf(list) / list[Math.max(0, list.length - 11)].close - 1 : 0);
      const dy = move(yields);
      const dd = move(dollar);
      const bad = dy > 0.08 || dd > 0.02;
      const good = dy < -0.02 && dd < 0 && !bad;
      add("rates", "Rates and dollar", 5, bad ? "bad" : good ? "good" : "neutral", "10-year yield " + fixed(dy * 100, 1) + "% and dollar " + fixed(dd * 100, 1) + "% over 10 bars.");
    } else add("rates", "Rates and dollar", 5, null);

    const total = parts.reduce((sum, item) => sum + item.weight, 0);
    const earned = parts.reduce((sum, item) => sum + item.points, 0);
    const score = total > 0 ? Math.round((earned / total) * 100) : 0;
    const label = total < 60 ? "Unknown" : score >= 65 ? "Supportive" : score >= 40 ? "Mixed" : "Defensive";
    const dated = closedOf("SPY") || closedOf("^VIX");
    return { ready: total >= 60, label, score, coverage: total, parts, missing, asOf: dated ? dated[dated.length - 1].date : "" };
  }

  // Open-market desk studies for the Internals path (Best setup / Edge / scan). Same closed-bar math as Strategies.
  function deskInternals(bars, options) {
    const list = bars || [];
    const live = !!(options && options.live) && list.length > 0;
    const end = live ? list.length - 1 : list.length;
    const parts = [];
    const add = (id, name, weight, says, text) => {
      parts.push({
        id,
        name,
        weight,
        says,
        points: says === "good" ? weight : says === "neutral" ? weight / 2 : 0,
        text,
        desk: true,
      });
    };
    if (end < 60) {
      return {
        ready: false,
        parts: [],
        missing: ["Squeeze", "TMO", "big4indicator", "SMA stack", "Weekly MTF", "Monthly MTF"],
        squeezeOk: false,
        tmoOk: false,
        big4Ok: false,
        stackOk: false,
        weekOk: false,
        monthOk: false,
        mtfOk: false,
        asOf: "",
      };
    }
    const closes = list.map((bar) => bar.close);
    const study = compression(list, 20);
    const mom = study.momentum;
    const on = study.level.map((value) => value >= 2);
    const last = end - 1;
    const rising = mom[last] != null && mom[last - 1] != null && mom[last] > 0 && mom[last] > mom[last - 1];
    const fired = last > 0 && !on[last] && on[last - 1];
    let dots = 0;
    for (let index = last; index >= 0 && on[index]; index -= 1) dots += 1;
    const squeezeOk = !!(fired && rising);
    const bearish = !!(fired && mom[last] != null && mom[last] < 0 && mom[last - 1] != null && mom[last] < mom[last - 1]);
    const squeezeSays = squeezeOk ? "good" : bearish ? "bad" : dots > 0 ? "neutral" : "neutral";
    add(
      "squeeze",
      "Squeeze (desk)",
      20,
      squeezeSays,
      squeezeOk
        ? "Squeeze just fired with momentum above zero and rising."
        : bearish
          ? "Bearish squeeze fire. This long-only desk stands aside."
          : dots > 0
            ? "Squeeze on, " + dots + " compression dot" + (dots === 1 ? "" : "s") + " building."
            : "No squeeze fire on the last closed bar."
    );

    const tmo = trueMomentum(list, 14, 5, 3);
    const tmoOk = !!(tmo.main[last] != null && tmo.signal[last] != null && tmo.main[last] > tmo.signal[last]);
    add(
      "tmo",
      "TMO 14/5/3",
      20,
      tmoOk ? "good" : tmo.main[last] != null && tmo.signal[last] != null ? "bad" : "neutral",
      tmo.main[last] != null && tmo.signal[last] != null
        ? "True momentum " + tmo.main[last].toFixed(2) + (tmoOk ? " above" : " below") + " signal " + tmo.signal[last].toFixed(2) + "."
        : "True momentum needs more bars."
    );

    const big4 = big4Indicator(list);
    const big4Ok = !!big4.ok[last];
    add(
      "big4",
      "big4indicator",
      20,
      big4Ok ? "good" : big4.bias[last] === "bear" ? "bad" : "neutral",
      "big4indicator " + (big4.label || big4.bias[last] || "Waiting") + (big4Ok ? " (agrees)" : " (waiting).")
    );

    const stack = stackAverages(list);
    const s5 = stack[0].values;
    const s9 = stack[1].values;
    const s14 = stack[2].values;
    const s50 = stack[3].values;
    const stackOk =
      s5[last] != null &&
      s9[last] != null &&
      s14[last] != null &&
      s50[last] != null &&
      s5[last] > s9[last] &&
      s9[last] > s14[last] &&
      s14[last] > s50[last];
    add(
      "stack",
      "SMA stack 5·9·14·50",
      20,
      stackOk ? "good" : "bad",
      stackOk ? "SMA stack lined up 5 over 9 over 14 over 50." : "SMA stack is not lined up on the last closed bar."
    );

    const snap = end >= 260 ? multiFrameSnapshot(list, end) : { weekOk: false, monthOk: false, mtfOk: false };
    if (end < 260) {
      add("week", "Weekly MTF", 10, "neutral", "Needs at least 260 daily bars for weekly multi-TF.");
      add("month", "Monthly MTF", 10, "neutral", "Needs at least 260 daily bars for monthly multi-TF.");
    } else {
      add("week", "Weekly MTF", 10, snap.weekOk ? "good" : "bad", snap.weekOk ? "Completed weekly bars agree (TMO above signal, close above 20-EMA)." : "Completed weekly bars do not agree yet.");
      add("month", "Monthly MTF", 10, snap.monthOk ? "good" : "bad", snap.monthOk ? "Completed monthly bars agree (TMO above signal, close above 20-EMA)." : "Completed monthly bars do not agree yet.");
    }

    const total = parts.reduce((sum, item) => sum + item.weight, 0);
    const earned = parts.reduce((sum, item) => sum + item.points, 0);
    const score = total > 0 ? Math.round((earned / total) * 100) : 0;
    return {
      ready: true,
      parts,
      missing: [],
      score,
      squeezeOk,
      tmoOk,
      big4Ok,
      stackOk,
      weekOk: !!snap.weekOk,
      monthOk: !!snap.monthOk,
      mtfOk: !!snap.mtfOk,
      asOf: list[last] ? list[last].date : "",
    };
  }

  const EDGE_NAMES = {
    tmoOs: "TMO above −10",
    tmo: "TMO turns green",
    dots: "5 squeeze dots or in a squeeze",
    above: "squeeze momentum above 0",
    cci: "CCI 50 · 14 · 5 into zone +100",
    wave: "Desk Waves green / up",
    vix: "VIX falling (closed daily vs prior)",
  };

  // Gate Print — closed-bar Edge component strip (own names). No third-party AI score.
  function gatePrint(parts) {
    const list = parts || {};
    const keys = Object.keys(EDGE_NAMES);
    const on = keys.filter((key) => !!list[key]);
    const missing = keys.filter((key) => !list[key]).map((key) => EDGE_NAMES[key]);
    const onCount = on.length;
    return {
      onCount,
      checks: EDGE_SCORE,
      on: on.map((key) => EDGE_NAMES[key]),
      missing,
      line:
        missing.length
          ? onCount + "/" + EDGE_SCORE + " on · missing " + missing.join(", ")
          : EDGE_SCORE + "/" + EDGE_SCORE + " on · all gates lit",
      shortMiss: missing.length ? missing.slice(0, 2).join("; ") + (missing.length > 2 ? " +" + (missing.length - 2) : "") : "",
    };
  }

  // Desk Call — loud WAIT vs FIRE from Edge result (closed bars).
  function edgeDeskCall(result) {
    if (!result || !result.ready) {
      return { call: "WAIT", label: "WAIT", stage: null, tone: "wait", why: "Edge needs more closed bars.", print: gatePrint(null) };
    }
    const print = gatePrint(result.parts);
    const stage = result.stage || null;
    if (result.call === "BUY" && stage === "fire") {
      return { call: "FIRE", label: "FIRE", stage: "fire", tone: "buy", why: print.line, print };
    }
    if (result.call === "SELL") {
      return { call: "SELL", label: "SELL", stage, tone: "sell", why: result.reason || "Exit armed", print };
    }
    if (result.call === "HOLD") {
      return { call: "HOLD", label: "HOLD", stage, tone: "hold", why: "In an Edge long · trail active", print };
    }
    if (stage === "aim") {
      return { call: "WAIT", label: "WAIT · Aim", stage: "aim", tone: "aim", why: print.line, print };
    }
    if (stage === "ready") {
      return { call: "WAIT", label: "WAIT · Ready", stage: "ready", tone: "ready", why: print.line, print };
    }
    return { call: "WAIT", label: "WAIT", stage, tone: "wait", why: print.line, print };
  }

  // Fire Precision — 0–100 closed-bar rank for Fire 5 (gates · stage · big4 · lane · seat · fresh flip).
  function firePrecision(input) {
    const row = input || {};
    let pts = 0;
    const score = Number(row.score);
    const checks = Number(row.checks) || EDGE_SCORE;
    if (Number.isFinite(score) && checks > 0) pts += Math.round((score / checks) * 40);
    if (row.call === "BUY" && row.stage === "fire") pts += 25;
    else if (row.stage === "aim") pts += 12;
    else if (row.stage === "ready") pts += 6;
    if (row.big4 === "bull" || row.big4Ok) pts += 12;
    if (row.lane === "open") pts += 10;
    else if (row.lane === "soft") pts += 4;
    if (row.seat === "high") pts += 8;
    else if (row.seat === "mid") pts += 4;
    if (row.freshFire) pts += 5;
    if (row.wave || (row.parts && row.parts.wave)) pts += 0; // already in gate score
    if (row.seatTrail) pts += 3;
    return Math.max(0, Math.min(100, pts));
  }

  function fireFreshFlip(result) {
    if (!result || !result.ready || !result.scores) return false;
    const scores = result.scores;
    let last = -1;
    for (let index = scores.length - 1; index >= 0; index -= 1) {
      if (scores[index] != null) {
        last = index;
        break;
      }
    }
    if (last < 1) return false;
    return scores[last] === EDGE_SCORE && scores[last - 1] < EDGE_SCORE;
  }

  // Desk Cards — paper confirm templates we invent (equity vs crypto). Not broker automation.
  const DESK_CARDS = {
    auto: {
      id: "auto",
      name: "Auto by type",
      idea: "Picks Stock Fire Card for stocks/ETFs/futures and Coin Seat Card for crypto. Paper confirm only. Live locked.",
    },
    stockfire: {
      id: "stockfire",
      name: "Stock Fire Card",
      kind: "equity",
      floor: 80,
      needBig4: true,
      needLaneOpen: true,
      seats: ["mid", "high"],
      idea: "Equities · ETF · futures Auto confirm: Edge FIRE + big4 bull + Lane Open + Seat Mid/High + Fire Precision ≥ 80.",
    },
    coinseat: {
      id: "coinseat",
      name: "Coin Seat Card",
      kind: "crypto",
      floor: 75,
      needBig4: false,
      needLaneOpen: false,
      seats: ["mid", "high"],
      preferSeatTrail: true,
      idea: "Crypto Auto confirm: Edge FIRE + Seat Mid/High + Fire Precision ≥ 75. Seat Trail armed lifts the rank. Paper only.",
    },
  };

  function resolveDeskCard(cardId, crypto) {
    const id = cardId || "auto";
    if (id === "stockfire" || id === "coinseat") return DESK_CARDS[id];
    return crypto ? DESK_CARDS.coinseat : DESK_CARDS.stockfire;
  }

  // Confirm Lock — tighter Auto/Best paper-buy filter from the active Desk Card.
  function confirmLock(input) {
    const ctx = input || {};
    const card = resolveDeskCard(ctx.cardId, !!ctx.crypto);
    const reasons = [];
    if (!(ctx.fire || (ctx.call === "BUY" && ctx.stage === "fire"))) {
      reasons.push("Edge not FIRE");
    }
    const precision = Number(ctx.precision);
    const floor = Number(card.floor) || 80;
    if (!(Number.isFinite(precision) && precision >= floor)) {
      reasons.push("Fire Precision " + (Number.isFinite(precision) ? precision : "—") + " < " + floor);
    }
    if (card.needBig4 && !(ctx.big4 === "bull" || ctx.big4Ok)) {
      reasons.push("big4indicator not bull");
    }
    if (card.needLaneOpen && ctx.lane !== "open") {
      reasons.push(ctx.lane === "unread" ? "Lane Align unread" : "Lane Align not open");
    }
    const seats = card.seats || ["mid", "high"];
    if (ctx.seat && seats.indexOf(ctx.seat) < 0) {
      reasons.push("Range Seat " + ctx.seat + " (need Mid/High)");
    } else if (!ctx.seat && seats.length) {
      reasons.push("Range Seat unread");
    }
    if (card.preferSeatTrail && ctx.seatTrail) {
      // Agree lift only — already reflected in precision; no extra fail.
    }
    return {
      ok: reasons.length === 0,
      why: reasons.length ? reasons[0] : card.name + " confirm clear",
      reasons,
      card,
      floor,
      precision: Number.isFinite(precision) ? precision : null,
    };
  }

  function plainPrice(value) {
    if (value == null || !Number.isFinite(Number(value))) return "—";
    const number = Number(value);
    return number.toLocaleString("en-US", { minimumFractionDigits: number >= 100 ? 2 : number >= 1 ? 2 : 4, maximumFractionDigits: number >= 100 ? 2 : number >= 1 ? 3 : 6 });
  }

  function describeBar(bars, index, options) {
    const list = bars || [];
    if (index < 0 || index >= list.length) return null;
    const format = (options && options.format) || plainPrice;
    const bar = list[index];
    const prev = index > 0 ? list[index - 1] : null;
    const rows = [
      ["Open", format(bar.open)],
      ["High", format(bar.high)],
      ["Low", format(bar.low)],
      ["Close", format(bar.close)],
    ];
    if (prev && prev.close > 0) {
      const change = (bar.close / prev.close - 1) * 100;
      rows.push(["Change", (change >= 0 ? "+" : "") + change.toFixed(2) + "% vs the bar before"]);
    }
    if (bar.low > 0) rows.push(["Range", (((bar.high - bar.low) / bar.low) * 100).toFixed(2) + "% high to low"]);
    if (bar.volume > 0) rows.push(["Volume", Math.round(bar.volume).toLocaleString("en-US")]);
    let edgeNote = "";
    if (options && options.edge !== false && list.length > EDGE_WARM + 2) {
      const known = edge(list, { live: liveBar(list, !!(options && options.crypto)) });
      const got = known && known.scores ? known.scores[index] : null;
      if (got != null) {
        const parts = known.allParts[index] || {};
        const missing = Object.keys(EDGE_NAMES).filter((key) => !parts[key]).map((key) => EDGE_NAMES[key]);
        edgeNote = "Edge on this bar: " + got + " of " + EDGE_SCORE + (missing.length ? ". Missing: " + missing.join(", ") + "." : ". All checks on.");
      } else if (index >= list.length - 1 && liveBar(list, !!(options && options.crypto))) {
        edgeNote = "This bar is still forming. Edge reads closed bars only.";
      }
    }
    return { title: (options && options.title ? options.title + " · " : "") + bar.date, rows, edgeNote, up: bar.close >= bar.open };
  }

  function hideTip(doc) {
    const node = doc && doc.getElementById("paper-tip");
    if (node) node.remove();
  }

  function showTip(doc, clientX, clientY, info) {
    if (!doc || !info) return null;
    hideTip(doc);
    const view = doc.defaultView || root;
    const box = doc.createElement("div");
    box.id = "paper-tip";
    box.className = info.up ? "up" : "down";
    box.setAttribute("role", "status");
    const head = doc.createElement("div");
    head.className = "tip-head";
    const title = doc.createElement("span");
    title.textContent = info.title;
    const close = doc.createElement("button");
    close.type = "button";
    close.className = "tip-close";
    close.textContent = "×";
    close.setAttribute("aria-label", "Close price box");
    close.addEventListener("click", (event) => {
      event.stopPropagation();
      hideTip(doc);
    });
    head.append(title, close);
    box.appendChild(head);
    info.rows.forEach(([name, value]) => {
      const line = doc.createElement("div");
      line.className = "tip-row";
      const left = doc.createElement("span");
      left.textContent = name;
      const right = doc.createElement("span");
      right.textContent = value;
      line.append(left, right);
      box.appendChild(line);
    });
    if (info.edgeNote) {
      const note = doc.createElement("div");
      note.className = "tip-edge";
      note.textContent = info.edgeNote;
      box.appendChild(note);
    }
    doc.body.appendChild(box);
    const width = box.offsetWidth;
    const height = box.offsetHeight;
    const maxX = (view.innerWidth || 800) - width - 8;
    const left = Math.max(8, Math.min(maxX, clientX + 14));
    let top = clientY - height - 14;
    if (top < 8) top = Math.min((view.innerHeight || 600) - height - 8, clientY + 18);
    box.style.left = left + "px";
    box.style.top = Math.max(8, top) + "px";
    return box;
  }

  function pickBar(canvas, event, map, options) {
    const target = map || canvas.paperMap;
    if (!target || !target.bars || !target.bars.length) return null;
    const rect = canvas.getBoundingClientRect();
    const cssWidth = canvas.clientWidth || rect.width || 1;
    const point = event.clientX != null ? event.clientX : 0;
    const x = ((point - rect.left) / (rect.width || cssWidth)) * (canvas.paperWidth || cssWidth);
    const index = barIndexAt(target.pad, target.slot, target.bars.length, x);
    if (index < 0) return null;
    const info = describeBar(target.bars, index, { ...(options || {}), crypto: target.crypto, title: (options && options.title) || target.label || target.symbol });
    if (!info) return null;
    canvas.paperPick = target.bars[index].date;
    showTip(canvas.ownerDocument, event.clientX, event.clientY, info);
    return { index, bar: target.bars[index], info };
  }

  function attachPick(canvas, redraw, options) {
    if (!canvas || canvas.paperPickBound) return;
    canvas.paperPickBound = true;
    canvas.style.cursor = "crosshair";
    canvas.addEventListener("click", (event) => {
      if (pickBar(canvas, event, null, options) && redraw) redraw();
    });
    const doc = canvas.ownerDocument;
    doc.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        hideTip(doc);
        canvas.paperPick = "";
        if (redraw) redraw();
      }
    });
  }

  function edgeLine(bars, crypto) {
    const list = bars || [];
    if (list.length <= EDGE_WARM + 2) return "Edge needs at least " + (EDGE_WARM + 2) + " bars.";
    const known = edge(list, { live: liveBar(list, !!crypto) });
    if (!known || !known.ready) return "Edge needs more bars.";
    const stop = known.stop != null ? " · stop " + plainPrice(known.stop) : "";
    return "Edge " + known.call + " · " + (known.score == null ? "—" : known.score + " of " + EDGE_SCORE) + stop + " · " + known.record.trades + " past trades, " + (known.record.trades ? Math.round(known.record.winRate * 100) + "% won" : "none yet");
  }

  // Big 3 Market Scorecard: index/sector strength, strict A+ squeezes, day bias.
  // Desk closed-bar math only — not a third-party or trademarked scorecard product.
  const SCORECARD_INDEXES = ["SPY", "QQQ", "IWM", "DIA", "ES", "NQ"];
  const SCORECARD_SECTORS = ["XLK", "SMH", "XLF", "XLE", "XLV", "XLI", "XLY", "XLP", "XLU", "XLB"];

  function strengthRead(bars) {
    const list = closedBars(bars || [], false);
    if (list.length < 50) return null;
    const closes = list.map((bar) => bar.close);
    const close = closes[closes.length - 1];
    const e21 = ema(closes, 21);
    const e50 = ema(closes, 50);
    const last21 = e21[e21.length - 1];
    const last50 = e50[e50.length - 1];
    if (!(last21 > 0) || !(last50 > 0)) return null;
    const prior = closes[Math.max(0, closes.length - 6)];
    const ret5 = prior > 0 ? close / prior - 1 : 0;
    let score = 0;
    if (close > last21) score += 35;
    if (last21 > last50) score += 25;
    if (close > last50) score += 15;
    score += Math.max(0, Math.min(25, Math.round(ret5 * 500)));
    return {
      ready: true,
      score: Math.min(100, score),
      close,
      ema21: last21,
      ema50: last50,
      ret5,
      stacked: close > last21 && last21 > last50,
      asOf: list[list.length - 1].date,
    };
  }

  function isAPlusSqueeze(row, scored) {
    if (!row || !row.ready) return false;
    if (row.direction !== "bull") return false;
    const score = scored && scored.score != null ? scored.score : 0;
    if (score < 80) return false;
    if (!row.rising) return false;
    if (row.stack !== "positive") return false;
    if (!(row.level >= 2 || row.dots >= 5)) return false;
    return !!(row.clean || score >= 90 || (scored && scored.triple));
  }

  function dayBias(internalsResult, indexRanks) {
    const ranks = indexRanks || [];
    const stacked = ranks.filter((row) => row.stacked).length;
    const share = ranks.length ? stacked / ranks.length : 0;
    const top = ranks[0];
    const int = internalsResult || {};
    if (!int.ready || int.label === "Defensive" || int.score < 40) {
      return {
        bias: "Stay flat",
        why:
          !int.ready
            ? "Internals not ready — stand aside until gauges read."
            : "Defensive / weak market gauges (" + (int.label || "Unknown") + " " + (int.score || 0) + "). Size down or stand aside.",
      };
    }
    if (int.label === "Supportive" && int.score >= 65 && share >= 0.5) {
      return {
        bias: "Swing trades",
        why:
          "Supportive internals " +
          int.score +
          " and " +
          stacked +
          " of " +
          ranks.length +
          " indexes stacked up" +
          (top ? " (leader " + top.symbol + ")" : "") +
          ". Favor multi-day holds on A+ squeezes in strong sectors.",
      };
    }
    return {
      bias: "Day trades",
      why:
        "Market is " +
        int.label +
        " " +
        int.score +
        " with " +
        stacked +
        " of " +
        ranks.length +
        " indexes stacked. Prefer shorter holds; wait for A+ squeezes in the strongest indexes/sectors.",
    };
  }

  // Tide Alert · Go Long 5 — original Paper Trader desk process (closed bars only).
  // Detects broad risk-exit (no sector rotation) + weekly index soft squeeze.
  // Go Long 5 ranks pressure names for review under Tide (not Fire 5). Long-only desk: no short fills.
  // Own names/copy — not a third-party product, catchphrase, or indicator brand.
  const TIDE_SECTOR_MIN = 6;
  const TIDE_INDEX_MIN = 3;
  const TIDE_GREEN_SCORE = 55;
  const FADE_FIVE_LIMIT = 5;

  function weeklyTideOn(dailyBars) {
    const list = closedBars(dailyBars || [], false);
    if (list.length < 120) return { ready: false, on: false, why: "Need more daily bars for weekly Tide." };
    const weeks = barsFor(list, frameById("1W"));
    if (!weeks || weeks.length < 30) return { ready: false, on: false, why: "Weekly bars not ready." };
    const read = squeezeRead(weeks, { live: false });
    if (!read || !read.ready) return { ready: false, on: false, why: "Weekly squeeze unread — Tide fails closed." };
    const closes = weeks.map((bar) => bar.close);
    const e21 = ema(closes, 21);
    const last21 = e21[e21.length - 1];
    const close = closes[closes.length - 1];
    const soft =
      read.direction === "bear" ||
      (read.momentum != null && read.momentum < 0) ||
      (last21 > 0 && close < last21);
    const compressed = read.state === "ON" || read.state === "FIRED" || read.dots >= 5 || (read.level || 0) >= 1;
    const on = !!(compressed && soft);
    return {
      ready: true,
      on,
      state: read.state,
      dots: read.dots,
      level: read.level,
      direction: read.direction,
      momentum: read.momentum,
      why: on
        ? "Weekly Tide on: compression with soft structure on closed weekly bars."
        : compressed
          ? "Weekly compression present but structure not soft."
          : "No weekly compression on closed bars.",
    };
  }

  function tideRead(input) {
    const source = input || {};
    const indexes = source.indexes || [];
    const sectors = source.sectors || [];
    const greenSectors = sectors.filter((row) => row && row.stacked && row.score >= TIDE_GREEN_SCORE);
    const sectorReady = sectors.length >= TIDE_SECTOR_MIN;
    const drain = sectorReady && greenSectors.length === 0;
    const softIndexes = indexes.filter((row) => row && (!row.stacked || row.score < 50));
    const indexReady = indexes.length >= TIDE_INDEX_MIN;
    const indexSoft = indexReady && softIndexes.length >= Math.ceil(indexes.length / 2);
    const week = weeklyTideOn(source.spyBars || source.bars || []);
    // Fail closed: missing sector board or unread weekly keeps Tide off.
    const on = !!(drain && (week.on || indexSoft) && sectorReady && (week.ready || indexReady));
    let why;
    if (!sectorReady) why = "Sector board not ready (" + sectors.length + " of " + TIDE_SECTOR_MIN + ") — Tide fails closed.";
    else if (!drain) why = greenSectors.length + " sector" + (greenSectors.length === 1 ? "" : "s") + " still green — rotation possible, Tide off.";
    else if (!week.ready && !indexReady) why = "Weekly Tide and index board unread — Tide fails closed.";
    else if (on) {
      why =
        "Tide Alert on: Sector Drain (0 green of " +
        sectors.length +
        ")" +
        (week.on ? " · Weekly Tide" : "") +
        (indexSoft ? " · Index Soft " + softIndexes.length + "/" + indexes.length : "") +
        ". Prefer Stay flat; review Go Long 5. No new Edge longs.";
    } else why = "Sector Drain on, but Weekly Tide and Index Soft are both off.";
    return {
      ready: sectorReady && (week.ready || indexReady),
      on,
      drain,
      indexSoft,
      week,
      greenSectors: greenSectors.length,
      sectorCount: sectors.length,
      softIndexes: softIndexes.length,
      indexCount: indexes.length,
      bias: on ? "Stay flat · Tide Alert" : "",
      why,
    };
  }

  function fadeRead(bars, options) {
    const opts = options || {};
    const list = closedBars(bars || [], !!(opts.crypto), opts.now);
    if (list.length < 50) return null;
    const closes = list.map((bar) => bar.close);
    const close = closes[closes.length - 1];
    const e21 = ema(closes, 21);
    const e50 = ema(closes, 50);
    const last21 = e21[e21.length - 1];
    const last50 = e50[e50.length - 1];
    if (!(last21 > 0) || !(last50 > 0)) return null;
    const prior = closes[Math.max(0, closes.length - 6)];
    const ret5 = prior > 0 ? close / prior - 1 : 0;
    const sq = squeezeRead(list, { live: !!opts.live });
    const timing = edge(list, { live: !!opts.live, vixBars: opts.vixBars || [] });
    const edgeFire = !!(timing && timing.ready && timing.call === "BUY" && timing.stage === "fire");
    let score = 0;
    const parts = [];
    const add = (label, points) => {
      if (!(points > 0)) return;
      score += points;
      parts.push([label, points]);
    };
    if (close < last21) add("Under 21 EMA", 20);
    if (close < last50) add("Under 50 EMA", 20);
    if (last21 < last50) add("21 under 50", 10);
    if (ret5 < 0) add("5d soft", Math.min(20, Math.round(Math.abs(ret5) * 400)));
    if (sq && sq.ready && (sq.state === "ON" || sq.dots >= 5 || (sq.level || 0) >= 1)) add("Squeeze compress", 15);
    if (sq && sq.ready && sq.momentum != null && sq.momentum <= 0) add("Mom ≤ 0", 15);
    if (sq && sq.ready && sq.direction === "bear") add("Bear squeeze", 10);
    if (sq && sq.ready && !sq.rising && sq.momentum != null && sq.momentum > 0) add("Mom fading", 5);
    if (edgeFire) score = Math.max(0, score - 40);
    const ready = score >= 45 && !edgeFire && (close < last21 || ret5 < 0);
    return {
      ready,
      score: Math.min(100, score),
      parts,
      ret5,
      under21: close < last21,
      under50: close < last50,
      squeeze: sq && sq.ready ? sq.state : "—",
      dots: sq && sq.ready ? sq.dots : 0,
      edgeFire,
      edge: timing && timing.ready ? timing.call : "",
      asOf: list[list.length - 1].date,
    };
  }

  function bestFades(rows) {
    return (rows || [])
      .filter((row) => row && row.symbol && row.ready && row.score >= 45 && !row.edgeFire)
      .sort((a, b) => b.score - a.score || a.symbol.localeCompare(b.symbol))
      .slice(0, FADE_FIVE_LIMIT);
  }

  // Lane Align · Range Seat — original Paper Trader desk processes (closed bars only).
  // Top-down index → sector → name confirmation, plus 52-week seat for Fire ranking / Journal.
  // Own names and math only — not a third-party product, catchphrase, or indicator brand.
  const LANE_SECTOR_MIN = 6;
  const LANE_TOP_SHARE = 0.5;
  const LANE_GREEN_SCORE = 55;
  const SEAT_HIGH_PCT = 55;
  const SEAT_MID_PCT = 30;

  // Seat Trail — original patience breakout with seat-aware ATR trail (Strategies Auto).
  // Distinct from Edge / Fire 5 / Tide / Go Long. Closed bars only. Own names only.
  const SEAT_TRAIL_CHANNEL = 20;
  const SEAT_TRAIL_VOL_MULT = 1.8;
  const SEAT_TRAIL_ATR = 14;
  const SEAT_TRAIL_ATR_BASE = 20;
  const SEAT_TRAIL_MAX_HOLD = 40;
  const SEAT_TRAIL_HIGH = 3.0;
  const SEAT_TRAIL_MID = 2.2;
  const SEAT_TRAIL_LOW = 1.6;

  // Lean Book — original small-paper category desk (Tools · Strategies). Own names only.
  const LEAN_BOOK_ATR = 14;
  const LEAN_BOOK_STOP_ATR = 2.0;
  const LEAN_BOOK_MAX_HOLD = 25;
  const LEAN_BOOK_MAX_OPEN = 5;
  const LEAN_BOOK_RISK_PCT = 0.02;
  const LEAN_BOOK_CASH_FLOOR = 0.7;

  function seatTrailMult(seat) {
    if (seat === "high") return SEAT_TRAIL_HIGH;
    if (seat === "mid") return SEAT_TRAIL_MID;
    return SEAT_TRAIL_LOW;
  }

  function rangeSeatAt(list, index) {
    if (!list || index < 59 || index >= list.length) return null;
    const window = list.slice(Math.max(0, index - 251), index + 1);
    let hi = -Infinity;
    let lo = Infinity;
    window.forEach((bar) => {
      if (bar.high > hi) hi = bar.high;
      if (bar.low < lo) lo = bar.low;
    });
    const close = window[window.length - 1].close;
    if (!(hi > lo) || !(close > 0)) return null;
    const pct = Math.round(((close - lo) / (hi - lo)) * 100);
    const seat = pct >= SEAT_HIGH_PCT ? "high" : pct >= SEAT_MID_PCT ? "mid" : "low";
    return {
      ready: true,
      pct,
      seat,
      label: seat === "high" ? "Seat High" : seat === "mid" ? "Seat Mid" : "Seat Low",
      asOf: window[window.length - 1].date,
    };
  }

  function rangeSeat(bars) {
    const list = closedBars(bars || [], false);
    if (list.length < 60) return null;
    return rangeSeatAt(list, list.length - 1);
  }

  function seatTrailRead(bars, options) {
    const all = bars || [];
    const live = !!(options && options.live) && all.length > 0;
    const list = live ? all.slice(0, -1) : all;
    const count = list.length;
    if (count < 80) {
      return {
        ready: false,
        armed: false,
        call: "WAIT",
        why: "Needs at least 80 closed bars for Seat Trail.",
        seat: null,
        seatLabel: "",
        trailMult: SEAT_TRAIL_MID,
      };
    }
    const last = count - 1;
    const closes = list.map((bar) => bar.close);
    const ranges = edgeAtr(list, SEAT_TRAIL_ATR);
    const atrBase = simpleAverage(ranges, SEAT_TRAIL_ATR_BASE);
    const volumes = list.map((bar) => Math.max(0, Number(bar.volume) || 0));
    const volAvg = simpleAverage(volumes, SEAT_TRAIL_CHANNEL);
    const ema50 = ema(closes, 50);
    const seat = rangeSeatAt(list, last);
    const hasVolume = volumes.some((value) => value > 0);
    let top = -Infinity;
    for (let back = 1; back <= SEAT_TRAIL_CHANNEL && last - back >= 0; back += 1) {
      top = Math.max(top, list[last - back].high);
    }
    const breakout = closes[last] > top;
    const volumeOk =
      hasVolume && volAvg[last] != null && volAvg[last] > 0 && volumes[last] >= SEAT_TRAIL_VOL_MULT * volAvg[last];
    const expandOk = ranges[last] != null && atrBase[last] != null && ranges[last] > atrBase[last];
    const structureOk = ema50[last] != null && closes[last] > ema50[last];
    const seatOk = !!(seat && (seat.seat === "high" || seat.seat === "mid"));
    const trailMult = seatTrailMult(seat && seat.seat);
    const armed = !!(hasVolume && breakout && volumeOk && expandOk && structureOk && seatOk);
    const parts = [];
    if (!hasVolume) parts.push("no volume — fail closed");
    else {
      parts.push(breakout ? "20-bar high break" : "no break");
      parts.push(volumeOk ? "volume surge" : "thin volume");
      parts.push(expandOk ? "ATR expanding" : "ATR quiet");
      parts.push(structureOk ? "above 50 EMA" : "under 50 EMA");
      parts.push(seat ? seat.label : "Seat unread");
      parts.push("trail ×" + trailMult.toFixed(1));
    }
    return {
      ready: true,
      armed,
      call: armed ? "BUY" : "WAIT",
      why: (armed ? "Seat Trail armed · " : "Seat Trail waiting · ") + parts.join(" · ") + ". Closed bars only. Not a forecast.",
      seat: seat ? seat.seat : null,
      seatLabel: seat ? seat.label : "",
      seatPct: seat ? seat.pct : null,
      trailMult,
      breakout,
      volumeOk,
      expandOk,
      structureOk,
      seatOk,
      hasVolume,
      stop: armed && ranges[last] != null ? closes[last] - trailMult * ranges[last] : null,
      asOf: list[last].date,
      relVol:
        hasVolume && volAvg[last] > 0 ? Math.round((volumes[last] / volAvg[last]) * 10) / 10 : null,
    };
  }

  function leanBookAt(list, index) {
    if (!list || index < 79 || index >= list.length) return null;
    const slice = list.slice(0, index + 1);
    const closes = slice.map((bar) => bar.close);
    const study = compression(slice, 20);
    const mom = study.momentum;
    const on = study.level.map((value) => value >= 2);
    const last = slice.length - 1;
    let dots = 0;
    for (let back = last; back >= 0 && on[back]; back -= 1) dots += 1;
    let firedAgo = -1;
    if (!on[last]) {
      for (let back = 1; back <= 3 && last - back >= 0; back += 1) {
        if (on[last - back]) {
          firedAgo = back - 1;
          break;
        }
      }
    }
    const momNow = mom[last];
    const coilSpark = !!(momNow != null && momNow > 0 && (on[last] || firedAgo >= 0));
    const e8 = ema(closes, 8)[last];
    const e21 = ema(closes, 21)[last];
    const e34 = ema(closes, 34)[last];
    const stackClimb = !!(e8 != null && e21 != null && e34 != null && e8 > e21 && e21 > e34 && closes[last] > e34);
    const seat = rangeSeatAt(slice, last);
    const seatHigh = !!(seat && (seat.seat === "high" || seat.seat === "mid"));
    let bookSync = false;
    try {
      const weeks = barsFor(slice, frameById("1W"));
      if (weeks.length >= 30) {
        const weekStudy = compression(weeks, 20);
        const wLast = weeks.length - 1;
        const wClose = weeks.map((bar) => bar.close);
        const wEma = ema(wClose, 20)[wLast];
        bookSync = !!(weekStudy.level[wLast] >= 2 || (wEma != null && weeks[wLast].close > wEma));
      }
    } catch (error) {
      bookSync = false;
    }
    const armed = !!(coilSpark && stackClimb && seatHigh);
    const prime = !!(armed && bookSync);
    const ranges = edgeAtr(slice, LEAN_BOOK_ATR);
    const atr = ranges[last];
    const stop = atr != null ? closes[last] - LEAN_BOOK_STOP_ATR * atr : null;
    const riskFrac = stop != null && closes[last] > 0 ? (closes[last] - stop) / closes[last] : null;
    const categories = [
      { id: "coilSpark", name: "Coil Spark", ok: coilSpark, detail: coilSpark ? (on[last] ? "Squeeze on · mom up" : "Just fired · mom up") : "No coil spark" },
      { id: "stackClimb", name: "Stack Climb", ok: stackClimb, detail: stackClimb ? "EMA 8>21>34 · close above 34" : "Stack not climbed" },
      { id: "seatHigh", name: "Seat High", ok: seatHigh, detail: seat ? seat.label : "Seat unread" },
      { id: "bookSync", name: "Book Sync", ok: bookSync, detail: bookSync ? "Weekly coil or above 20 EMA" : "Weekly sync off" },
    ];
    const hits = categories.filter((row) => row.ok).length;
    return {
      ready: true,
      armed,
      prime,
      call: armed ? "BUY" : "WAIT",
      coilSpark,
      stackClimb,
      seatHigh,
      bookSync,
      categories,
      hits,
      score: hits,
      seat: seat ? seat.seat : null,
      seatLabel: seat ? seat.label : "",
      stop,
      atr,
      riskFrac,
      asOf: slice[last].date,
      price: closes[last],
    };
  }

  function leanBookRead(bars, options) {
    const all = bars || [];
    const live = !!(options && options.live) && all.length > 0;
    const list = live ? all.slice(0, -1) : all;
    if (list.length < 80) {
      return {
        ready: false,
        armed: false,
        prime: false,
        call: "WAIT",
        why: "Needs at least 80 closed bars for Lean Book.",
        categories: [],
        hits: 0,
        score: 0,
      };
    }
    const snap = leanBookAt(list, list.length - 1);
    if (!snap) {
      return {
        ready: false,
        armed: false,
        prime: false,
        call: "WAIT",
        why: "Lean Book unread.",
        categories: [],
        hits: 0,
        score: 0,
      };
    }
    const tag = snap.prime ? "Prime" : snap.armed ? "Armed" : "Wait";
    const bits = snap.categories.map((row) => (row.ok ? row.name : row.name + " off"));
    return {
      ...snap,
      why:
        "Lean Book " +
        tag +
        " · " +
        snap.hits +
        "/4 · " +
        bits.join(" · ") +
        ". Closed bars only. Own desk names. Not a forecast.",
    };
  }

  function leanBookCashGuard(input) {
    const source = input || {};
    const cash = Number(source.cash);
    const equity = Number(source.equity);
    const openCount = Math.max(0, Math.floor(Number(source.openCount) || 0));
    const riskFrac = source.riskFrac != null ? Number(source.riskFrac) : null;
    const book = Number.isFinite(equity) && equity > 0 ? equity : Number.isFinite(cash) && cash > 0 ? cash : 0;
    const cashAmt = Number.isFinite(cash) ? Math.max(0, cash) : 0;
    const cashPct = book > 0 ? cashAmt / book : null;
    const openOk = openCount <= LEAN_BOOK_MAX_OPEN;
    const cashOk = cashPct == null ? false : cashPct >= LEAN_BOOK_CASH_FLOOR;
    const riskOk = riskFrac == null || !(riskFrac > LEAN_BOOK_RISK_PCT * 1.5);
    const ok = !!(book > 0 && openOk && (cashPct == null || cashOk) && riskOk);
    const sizeHint =
      book > 0 && riskFrac != null && riskFrac > 0
        ? Math.max(0, Math.floor((book * LEAN_BOOK_RISK_PCT) / (riskFrac * (Number(source.price) || 1))))
        : null;
    return {
      ready: book > 0,
      ok,
      cashPct,
      cashFloor: LEAN_BOOK_CASH_FLOOR,
      openCount,
      maxOpen: LEAN_BOOK_MAX_OPEN,
      riskPct: LEAN_BOOK_RISK_PCT,
      riskFrac,
      sizeHint,
      why: !book
        ? "Paper book unread for Cash Guard."
        : (ok ? "Cash Guard lean · " : "Cash Guard tight · ") +
          (cashPct == null ? "cash —" : "cash " + Math.round(cashPct * 100) + "%") +
          " · open " +
          openCount +
          "/" +
          LEAN_BOOK_MAX_OPEN +
          (riskFrac != null ? " · stop risk " + (riskFrac * 100).toFixed(1) + "%" : "") +
          ". Guidance only. Paper only.",
    };
  }

  function laneAlign(input) {
    const source = input || {};
    const symbol = source.symbol || "";
    const sector = source.sector || "";
    const indexSym = source.index || "";
    const seat = source.seat || null;
    if (!sector) {
      return {
        ready: true,
        lane: "skip",
        why: "No sector map — Lane Align skips (crypto or unmapped).",
        sector: "",
        index: indexSym,
        seat,
        symbol,
      };
    }
    const sectors = source.sectors || [];
    const indexes = source.indexes || [];
    if (sectors.length < LANE_SECTOR_MIN) {
      return {
        ready: false,
        lane: "unread",
        why: "Sector board unread — Lane Align fails closed. Run scorecard.",
        sector,
        index: indexSym,
        seat,
        symbol,
      };
    }
    const sectorRow = sectors.find((row) => row && row.symbol === sector);
    if (!sectorRow) {
      return {
        ready: false,
        lane: "unread",
        why: "Sector " + sector + " not on the board — Lane Align fails closed.",
        sector,
        index: indexSym,
        seat,
        symbol,
      };
    }
    const sectorRank = sectors.findIndex((row) => row && row.symbol === sector) + 1;
    const topN = Math.max(1, Math.ceil(sectors.length * LANE_TOP_SHARE));
    const sectorOpen = !!(sectorRow.stacked && sectorRow.score >= LANE_GREEN_SCORE && sectorRank > 0 && sectorRank <= topN);
    const sectorSoft = !!(sectorRow.score >= 45 && sectorRank > 0 && sectorRank <= Math.ceil(sectors.length * 0.7));
    let indexOpen = true;
    let indexSoft = true;
    if (indexSym) {
      const indexRow = indexes.find((row) => row && row.symbol === indexSym);
      if (!indexRow) {
        return {
          ready: false,
          lane: "unread",
          why: "Index " + indexSym + " unread — Lane Align fails closed.",
          sector,
          index: indexSym,
          seat,
          symbol,
          sectorRank,
          sectorScore: sectorRow.score,
        };
      }
      indexOpen = !!(indexRow.stacked && indexRow.score >= 50);
      indexSoft = indexRow.score >= 40;
    }
    let lane;
    let why;
    if (sectorOpen && indexOpen) {
      lane = "open";
      why =
        "Lane Align open: " +
        sector +
        " rank " +
        sectorRank +
        "/" +
        sectors.length +
        (indexSym ? " · " + indexSym + " stacked" : "") +
        ".";
    } else if (sectorSoft && indexSoft) {
      lane = "soft";
      why =
        "Lane Align soft: " +
        sector +
        " rank " +
        sectorRank +
        "/" +
        sectors.length +
        " — Best setup prefers Open.";
    } else {
      lane = "closed";
      why = "Lane Align closed: sector/index not in the lead lane for " + (symbol || "this name") + ".";
    }
    return {
      ready: true,
      lane,
      why,
      sector,
      index: indexSym,
      seat,
      symbol,
      sectorRank,
      sectorScore: sectorRow.score,
    };
  }

  function laneAllowsAuto(lane, mode) {
    const key = String(lane || "");
    if (key === "skip" || key === "open") return true;
    if (key === "soft") return mode !== "best";
    return false;
  }

  function marketScorecard(input) {
    const source = input || {};
    const rankGroup = (symbols, barsMap) =>
      (symbols || [])
        .map((symbol) => {
          const read = strengthRead((barsMap || {})[symbol]);
          if (!read) return null;
          return Object.assign({ symbol }, read);
        })
        .filter(Boolean)
        .sort((left, right) => right.score - left.score || left.symbol.localeCompare(right.symbol));
    const indexes = rankGroup(source.indexSymbols || SCORECARD_INDEXES, source.bars || {});
    const sectors = rankGroup(source.sectorSymbols || SCORECARD_SECTORS, source.bars || {});
    const aPlus = (source.squeezes || [])
      .filter((row) => row && isAPlusSqueeze(row.read || row, row.scored || row))
      .map((row) => ({
        symbol: row.symbol,
        score: (row.scored || row).score,
        state: (row.read || row).state,
        dots: (row.read || row).dots,
        level: (row.read || row).level,
        triple: !!(row.scored || row).triple,
        edgeFire: !!(row.edgeFire || (row.scored && row.scored.edgeFire) || (row.read && row.read.edgeFire)),
        sector: row.sector || "",
        index: row.index || "",
      }))
      .sort((left, right) => Number(!!right.edgeFire) - Number(!!left.edgeFire) || right.score - left.score || left.symbol.localeCompare(right.symbol));
    const barsMap = source.bars || {};
    const spyBars = source.spyBars || barsMap.SPY || barsMap.QQQ || [];
    const tide = tideRead({ indexes, sectors, spyBars });
    let day = dayBias(source.internals, indexes);
    if (tide.on) {
      day = {
        bias: "Stay flat · Tide Alert",
        why: tide.why,
      };
    }
    return {
      ready: indexes.length > 0 || sectors.length > 0 || aPlus.length > 0 || !!(source.internals && source.internals.ready),
      indexes,
      sectors,
      aPlus,
      tide,
      day,
      asOf: (indexes[0] && indexes[0].asOf) || (sectors[0] && sectors[0].asOf) || "",
    };
  }

  // Market Outlook — Day · Week · Month board from SPY closed bars (own desk math).
  // Score 1–10 · green/red/amber · Go long / Stay flat / Short bias.
  // Long-only desk: Short bias means stay flat / pressure review — no short fills.
  // Guidance only. Edge / Best setup / Auto still gate buys. Not a crystal ball.
  function horizonOutlook(bars, label) {
    const list = closedBars(bars || [], false);
    // Day uses EMA 21/50; Week/Month use shorter averages so sparse higher-TF bars still read.
    const fastLen = label === "Day" ? 21 : label === "Week" ? 10 : 5;
    const slowLen = label === "Day" ? 50 : label === "Week" ? 21 : 10;
    const need = Math.max(slowLen + 2, label === "Day" ? 50 : label === "Week" ? 26 : 10);
    if (list.length < need) {
      return {
        ready: false,
        label,
        color: "wait",
        score: null,
        tone: "—",
        stance: "Waiting",
        why: "Need more closed " + String(label || "horizon").toLowerCase() + " bars.",
        asOf: list.length ? list[list.length - 1].date : "",
      };
    }
    const closes = list.map((bar) => bar.close);
    const close = closes[closes.length - 1];
    const eFast = ema(closes, fastLen);
    const eSlow = ema(closes, slowLen);
    const lastFast = eFast[eFast.length - 1];
    const lastSlow = eSlow[eSlow.length - 1];
    if (!(lastFast > 0) || !(lastSlow > 0)) {
      return {
        ready: false,
        label,
        color: "wait",
        score: null,
        tone: "—",
        stance: "Waiting",
        why: "Averages not ready on closed bars.",
        asOf: list[list.length - 1].date,
      };
    }
    const lookback = Math.min(5, closes.length - 1);
    const prior = closes[closes.length - 1 - lookback];
    const ret = prior > 0 ? close / prior - 1 : 0;
    const sq = squeezeRead(list, { live: false });
    let pts = 5;
    if (close > lastSlow) pts += 1;
    else pts -= 1;
    if (close > lastFast) pts += 1;
    else pts -= 1;
    if (lastFast > lastSlow) pts += 1;
    else pts -= 1;
    if (ret > 0.015) pts += 2;
    else if (ret > 0.004) pts += 1;
    else if (ret < -0.015) pts -= 2;
    else if (ret < -0.004) pts -= 1;
    if (sq && sq.ready) {
      if (sq.momentum != null && sq.momentum > 0) pts += 1;
      else if (sq.momentum != null && sq.momentum < 0) pts -= 1;
      if (sq.direction === "bull") pts += 1;
      else if (sq.direction === "bear") pts -= 1;
    }
    const score = Math.max(1, Math.min(10, Math.round(pts)));
    let color = "amber";
    let tone = "Mixed";
    let stance = "Stay flat";
    if (score >= 7) {
      color = "green";
      tone = "Good";
      stance = "Go long";
    } else if (score <= 4) {
      color = "red";
      tone = "Soft";
      stance = "Short bias";
    }
    const retPct = (ret * 100).toFixed(1);
    return {
      ready: true,
      label,
      color,
      score,
      tone,
      stance,
      why:
        tone +
        " " +
        score +
        "/10 · close vs EMA" +
        fastLen +
        "/" +
        slowLen +
        " · " +
        (ret >= 0 ? "+" : "") +
        retPct +
        "% over " +
        lookback +
        " bars" +
        (sq && sq.ready ? " · squeeze " + (sq.direction || "flat") : ""),
      asOf: list[list.length - 1].date,
      ret,
      stacked: close > lastFast && lastFast > lastSlow,
    };
  }

  function marketOutlook(input) {
    const source = input || {};
    const daily = closedBars(source.spyBars || source.bars || [], false);
    const day = horizonOutlook(daily, "Day");
    const week = horizonOutlook(barsFor(daily, frameById("1W")), "Week");
    const month = horizonOutlook(barsFor(daily, frameById("1M")), "Month");
    const readyRows = [day, week, month].filter((row) => row && row.ready && row.score != null);
    const board = readyRows.length
      ? Math.max(1, Math.min(10, Math.round(readyRows.reduce((sum, row) => sum + row.score, 0) / readyRows.length)))
      : null;
    const tideOn = !!(source.tide && source.tide.on);
    let call = "Waiting";
    let color = "wait";
    if (board != null) {
      if (tideOn) {
        call = "Stay flat · Tide";
        color = "amber";
      } else if (board >= 7) {
        call = "Go long";
        color = "green";
      } else if (board <= 4) {
        call = "Short bias · Stay flat";
        color = "red";
      } else {
        call = "Stay flat";
        color = "amber";
      }
    }
    const greens = readyRows.filter((row) => row.color === "green").length;
    const reds = readyRows.filter((row) => row.color === "red").length;
    let why =
      board == null
        ? "Reading SPY closed bars for Day · Week · Month…"
        : "Best call · " +
          call +
          " · board " +
          board +
          "/10 · " +
          greens +
          " green · " +
          reds +
          " red of " +
          readyRows.length +
          " horizons.";
    if (tideOn && source.tide && source.tide.why) why += " " + source.tide.why;
    return {
      ready: readyRows.length >= 2,
      symbol: source.symbol || "SPY",
      day,
      week,
      month,
      board,
      call,
      color,
      why,
      tideOn,
      asOf: day.asOf || week.asOf || month.asOf || "",
    };
  }

  // Cycle Desk · Defensive Shield · Risk Rebound — original Paper Trader only.
  // Closed daily bars vs a long mean. Own stage names and copy. No third-party
  // courses, packs, brands, or trademarked pattern libraries. Guidance only.
  // Edge · Best setup · Auto still gate buys. Paper only. Live locked. Not a forecast.
  const CYCLE_MEAN_LEN = 100;
  const CYCLE_LOOK = 80;
  const DEFENSIVE_SHIELD = ["XLU", "XLP", "XLV", "TLT", "GLD", "SCHD", "HDV", "DGRO"];
  const RISK_REBOUND = ["QQQ", "XLK", "SMH", "XLY", "IWM", "ARKW", "SPMO", "COPX", "NVDA", "TSLA", "AMD", "META", "AMZN", "HOOD"];

  function smaSeries(closes, length) {
    const out = new Array(closes.length).fill(null);
    let sum = 0;
    for (let index = 0; index < closes.length; index += 1) {
      sum += closes[index];
      if (index >= length) sum -= closes[index - length];
      if (index >= length - 1) out[index] = sum / length;
    }
    return out;
  }

  function windowExtrema(closes, start, end) {
    let hi = -Infinity;
    let lo = Infinity;
    let hiAt = start;
    let loAt = start;
    for (let index = start; index <= end; index += 1) {
      const value = closes[index];
      if (value > hi) {
        hi = value;
        hiAt = index;
      }
      if (value < lo) {
        lo = value;
        loAt = index;
      }
    }
    return { hi, lo, hiAt, loAt };
  }

  function cycleMapRead(bars) {
    const list = closedBars(bars || [], false);
    if (list.length < CYCLE_MEAN_LEN + 25) {
      return {
        ready: false,
        stage: "unread",
        label: "Unread",
        signal: "none",
        signalLabel: "Need more closed bars",
        maps: [],
        why: "Need about " + (CYCLE_MEAN_LEN + 25) + " closed daily bars for Cycle Map.",
      };
    }
    const closes = list.map((bar) => bar.close);
    const means = smaSeries(closes, CYCLE_MEAN_LEN);
    const i = closes.length - 1;
    const close = closes[i];
    const mean = means[i];
    if (!(mean > 0) || !(close > 0)) {
      return {
        ready: false,
        stage: "unread",
        label: "Unread",
        signal: "none",
        signalLabel: "Mean unread",
        maps: [],
        why: "Mean line unread on closed bars.",
      };
    }
    const stretch = (close - mean) / mean;
    const ret5 = closes[i - 5] > 0 ? close / closes[i - 5] - 1 : 0;
    const ret20 = closes[i - 20] > 0 ? close / closes[i - 20] - 1 : 0;
    const start = Math.max(CYCLE_MEAN_LEN - 1, i - CYCLE_LOOK);
    const ext = windowExtrema(closes, start, i);
    const nearHigh = ext.hi > 0 && close >= ext.hi * 0.985;
    const nearLow = ext.lo > 0 && close <= ext.lo * 1.015;
    const peakStretch = (() => {
      let best = stretch;
      for (let index = start; index <= i; index += 1) {
        if (!(means[index] > 0)) continue;
        best = Math.max(best, (closes[index] - means[index]) / means[index]);
      }
      return best;
    })();
    const troughStretch = (() => {
      let best = stretch;
      for (let index = start; index <= i; index += 1) {
        if (!(means[index] > 0)) continue;
        best = Math.min(best, (closes[index] - means[index]) / means[index]);
      }
      return best;
    })();
    const sawMania = peakStretch >= 0.08;
    const sawDespair = troughStretch <= -0.06;
    const meanSlope = means[i - 10] > 0 ? (mean - means[i - 10]) / means[i - 10] : 0;

    // Cycle marks (geometry on closed bars — Paper Trader names only).
    const maps = [];
    const pushMap = (id, label, hit, signal) => {
      maps.push({ id, label, hit: !!hit, signal: signal || "none" });
    };

    const bearTrap =
      stretch > 0.005 &&
      stretch < 0.06 &&
      ret5 > 0.005 &&
      (() => {
        for (let index = i - 12; index < i - 2; index += 1) {
          if (index < start || !(means[index] > 0)) continue;
          const s = (closes[index] - means[index]) / means[index];
          if (s > 0 && s < stretch && closes[index] < close * 0.98) return true;
        }
        return false;
      })();
    pushMap("bear_trap", "Bear trap dip", bearTrap, "buy_before_mania");

    const takeoff = stretch > 0.01 && stretch < 0.05 && ret20 > 0.02 && meanSlope >= 0 && !bearTrap;
    pushMap("takeoff", "Takeoff", takeoff, "buy_before_mania");

    const mania = stretch >= 0.1 || (stretch >= 0.07 && ret20 >= 0.12);
    pushMap("mania", "Mania stretch", mania, "sell_into_greed");

    const greedTop = stretch >= 0.08 && (nearHigh || ret5 <= 0.005) && peakStretch >= 0.08;
    pushMap("greed_top", "Greed / delusion top", greedTop, "sell_into_greed");

    const denial = sawMania && stretch > 0.02 && ret5 < -0.03 && !nearHigh;
    pushMap("denial", "Denial drop", denial, "top_in");

    const bullTrap =
      sawMania &&
      ext.hiAt < i - 3 &&
      closes[ext.hiAt] > mean * 1.04 &&
      close < closes[ext.hiAt] * 0.97 &&
      ret5 < 0 &&
      stretch > -0.02;
    pushMap("bull_trap", "Bull trap bounce", bullTrap, "top_in");

    const capitulation = ret5 <= -0.055 && stretch < -0.02 && ret20 < -0.08;
    pushMap("capitulation", "Capitulation drop", capitulation, "bottom_watch");

    const despairBottom = stretch <= -0.07 && nearLow && ret5 > -0.02;
    pushMap("despair_bottom", "Despair bottom", despairBottom, "bottom_in");

    const returnMean = stretch > -0.07 && stretch < 0.03 && sawDespair && ret5 > 0.01 && close > closes[i - 5];
    pushMap("return_mean", "Return to mean", returnMean, "bottom_in");

    const cycleDone = sawMania && sawDespair && Math.abs(stretch) <= 0.025 && ret20 > -0.02 && ret20 < 0.05;
    pushMap("cycle_done", "Cycle finished", cycleDone, "cycle_finished");

    let stage = "steady";
    let label = "Steady · near mean";
    if (despairBottom || (stretch <= -0.09 && nearLow)) {
      stage = "despair";
      label = "Despair · bottom zone";
    } else if (capitulation) {
      stage = "capitulation";
      label = "Capitulation";
    } else if (stretch <= -0.04 && ret20 <= -0.08) {
      stage = "fear";
      label = "Fear";
    } else if (bullTrap) {
      stage = "bull_trap";
      label = "Bull trap";
    } else if (denial) {
      stage = "denial";
      label = "Denial";
    } else if (greedTop || (mania && nearHigh)) {
      stage = stretch >= 0.12 ? "delusion" : "greed";
      label = stretch >= 0.12 ? "Delusion · top" : "Greed · sell zone";
    } else if (mania) {
      stage = "mania";
      label = "Mania / public";
    } else if (bearTrap) {
      stage = "bear_trap";
      label = "Bear trap · buy zone";
    } else if (takeoff) {
      stage = "takeoff";
      label = "Takeoff";
    } else if (returnMean || (sawDespair && stretch < 0.02 && ret5 > 0)) {
      stage = "return_mean";
      label = "Return to mean";
    } else if (cycleDone) {
      stage = "cycle_done";
      label = "Cycle finished";
    } else if (stretch > 0.04) {
      stage = "enthusiasm";
      label = "Enthusiasm";
    }

    let signal = "none";
    let signalLabel = "No cycle signal";
    if (cycleDone) {
      signal = "cycle_finished";
      signalLabel = "Cycle finished · back near mean";
    } else if (despairBottom || (stage === "despair" && ret5 >= 0)) {
      signal = "bottom_in";
      signalLabel = "Bottom watch · despair stall";
    } else if (bearTrap || takeoff) {
      signal = "buy_before_mania";
      signalLabel = "Buy before mania · early rise";
    } else if (greedTop || stage === "delusion" || stage === "greed") {
      signal = "sell_into_greed";
      signalLabel = "Sell into greed · top zone";
    } else if (denial || bullTrap) {
      signal = "top_in";
      signalLabel = "Top in · denial / bull trap";
    } else if (capitulation || stage === "fear") {
      signal = "bottom_watch";
      signalLabel = "Bottom watch · fear / capitulation";
    }

    const hitMaps = maps.filter((row) => row.hit).map((row) => row.label);
    return {
      ready: true,
      stage,
      label,
      signal,
      signalLabel,
      stretch,
      ret5,
      ret20,
      mean,
      close,
      peakStretch,
      troughStretch,
      maps,
      mapHits: hitMaps,
      why:
        label +
        " · stretch " +
        (stretch >= 0 ? "+" : "") +
        (stretch * 100).toFixed(1) +
        "% vs mean" +
        (hitMaps.length ? " · marks: " + hitMaps.join(", ") : "") +
        ". Closed bars only. Own desk read. Not a forecast.",
      asOf: list[i].date,
    };
  }

  function marketTankRead(spyBars) {
    const strength = strengthRead(spyBars);
    const cycle = cycleMapRead(spyBars);
    if (!strength) {
      return {
        ready: false,
        mode: "unread",
        label: "Unread",
        why: "SPY unread — Defensive Shield and Risk Rebound wait.",
        cycle,
      };
    }
    const ret5 = strength.ret5 || 0;
    const ret20 = (() => {
      const list = closedBars(spyBars || [], false);
      if (list.length < 21) return 0;
      const close = list[list.length - 1].close;
      const prior = list[list.length - 21].close;
      return prior > 0 ? close / prior - 1 : 0;
    })();
    let mode = "calm";
    let label = "Market calm";
    if (ret20 <= -0.08 || ret5 <= -0.045 || (cycle && (cycle.stage === "fear" || cycle.stage === "capitulation" || cycle.stage === "despair"))) {
      mode = "tank";
      label = "Market tank";
    } else if (ret5 <= -0.02 || ret20 <= -0.04 || !strength.stacked) {
      mode = "soft";
      label = "Market soft";
    }
    return {
      ready: true,
      mode,
      label,
      ret5,
      ret20,
      stacked: !!strength.stacked,
      score: strength.score,
      cycle,
      why:
        label +
        " · SPY 5d " +
        (ret5 >= 0 ? "+" : "") +
        (ret5 * 100).toFixed(1) +
        "% · 20d " +
        (ret20 >= 0 ? "+" : "") +
        (ret20 * 100).toFixed(1) +
        "%" +
        (cycle && cycle.ready ? " · Cycle " + cycle.label : "") +
        ".",
      asOf: strength.asOf,
    };
  }

  function rankCycleRows(symbols, barsMap, scoreFn) {
    return (symbols || [])
      .map((symbol) => {
        const bars = (barsMap || {})[symbol];
        if (!bars || !bars.length) return null;
        const strength = strengthRead(bars);
        const cycle = cycleMapRead(bars);
        if (!strength || !cycle || !cycle.ready) return null;
        const score = scoreFn(strength, cycle);
        return {
          symbol,
          score,
          strength: strength.score,
          ret5: strength.ret5,
          stage: cycle.stage,
          label: cycle.label,
          signal: cycle.signal,
          signalLabel: cycle.signalLabel,
          mapHits: cycle.mapHits || [],
          why: cycle.why,
          asOf: cycle.asOf,
        };
      })
      .filter(Boolean)
      .sort((left, right) => right.score - left.score || left.symbol.localeCompare(right.symbol));
  }

  function defensiveShieldScan(input) {
    const source = input || {};
    const market = source.market || marketTankRead(source.spyBars);
    const rows = rankCycleRows(source.symbols || DEFENSIVE_SHIELD, source.bars || {}, (strength, cycle) => {
      let score = strength.score;
      if (market && market.mode === "tank") score += 15;
      if (market && market.mode === "soft") score += 8;
      if (strength.stacked) score += 10;
      if (cycle.signal === "buy_before_mania" || cycle.stage === "takeoff" || cycle.stage === "bear_trap") score += 12;
      if (cycle.stage === "greed" || cycle.stage === "delusion" || cycle.stage === "mania") score -= 20;
      return score;
    }).slice(0, 8);
    const active = !!(market && (market.mode === "soft" || market.mode === "tank"));
    return {
      ready: !!(market && market.ready),
      active,
      market,
      rows: active ? rows : rows.slice(0, 3),
      why: !market || !market.ready
        ? "Waiting on SPY for Defensive Shield."
        : active
          ? "Defensive Shield on — " + market.label + ". Prefer utilities · staples · health · bonds · gold while risk is soft."
          : "Market not soft — Defensive Shield stays quiet (showing leaders for review only).",
    };
  }

  function riskReboundScan(input) {
    const source = input || {};
    const market = source.market || marketTankRead(source.spyBars);
    const rows = rankCycleRows(source.symbols || RISK_REBOUND, source.bars || {}, (strength, cycle) => {
      let score = 40;
      if (cycle.signal === "bottom_in" || cycle.stage === "despair") score += 35;
      if (cycle.signal === "bottom_watch" || cycle.stage === "capitulation" || cycle.stage === "fear") score += 20;
      if (cycle.signal === "buy_before_mania" || cycle.stage === "return_mean" || cycle.stage === "bear_trap") score += 18;
      if (cycle.mapHits && cycle.mapHits.some((name) => /Despair|Return|Bear trap/i.test(name))) score += 10;
      if (cycle.stage === "mania" || cycle.stage === "greed" || cycle.stage === "delusion") score -= 30;
      score += Math.max(0, Math.min(20, Math.round((strength.ret5 || 0) * 200 + 10)));
      return score;
    }).slice(0, 8);
    const active = !!(market && market.mode === "tank");
    return {
      ready: !!(market && market.ready),
      active,
      market,
      rows: active ? rows : [],
      why: !market || !market.ready
        ? "Waiting on SPY for Risk Rebound."
        : active
          ? "Market tank — Risk Rebound lists non-defensive names near fear / capitulation / despair for chart review (not Fire 5)."
          : "Risk Rebound waits for a market tank. Soft / calm markets keep this list empty.",
    };
  }

  // Forecaster — original Paper Trader desk process (closed bars only).
  // Scenario board: base · bull · bear, horizons, assumptions, SPY compare,
  // invalidation, paper size guidance, and a desk rank from Edge · Squeeze · Cycle · strength.
  // Risk desk extensions (own names only): Swing Guard · Risk Seat · Name Pulse ·
  // Book Heat · Reclaim Cue · Season Lean — closed-bar proxies we compute; no third-party brands.
  // Complements Market Outlook — does not replace it. Own names/copy only.
  // Not a crystal ball. Not a forecast guarantee. Edge · Best setup · Auto still gate buys.
  // Paper only. Live locked. No third-party product, score brand, or scrape.
  function clampNum(value, lo, hi) {
    return Math.max(lo, Math.min(hi, value));
  }

  function retOverBars(list, barsBack) {
    if (!list || list.length <= barsBack) return null;
    const close = list[list.length - 1].close;
    const prior = list[list.length - 1 - barsBack].close;
    if (!(prior > 0) || !(close > 0)) return null;
    return close / prior - 1;
  }

  function realizedVol(list, window) {
    const n = Math.min(window || 20, (list || []).length - 1);
    if (n < 8) return null;
    const rets = [];
    for (let index = list.length - n; index < list.length; index += 1) {
      const prior = list[index - 1].close;
      const close = list[index].close;
      if (prior > 0 && close > 0) rets.push(close / prior - 1);
    }
    if (rets.length < 5) return null;
    const mean = rets.reduce((sum, value) => sum + value, 0) / rets.length;
    let sumSq = 0;
    rets.forEach((value) => {
      const d = value - mean;
      sumSq += d * d;
    });
    return Math.sqrt(sumSq / rets.length);
  }

  // Swing Guard — closed-bar realized-vol trail under last close (guidance only).
  function swingGuardRead(list, close) {
    const vol21 = realizedVol(list, 21);
    const vol63 = realizedVol(list, 63);
    if (vol21 == null || !(close > 0)) {
      return { ready: false, why: "Need more closed bars for Swing Guard." };
    }
    const annual = vol21 * Math.sqrt(252);
    // Trail width ~ 2× 21-day realized move, floored/capped for paper risk guidance.
    const stopFrac = clampNum(vol21 * 2.0, 0.03, 0.22);
    const guard = Math.round((close * (1 - stopFrac)) * 100) / 100;
    const band =
      annual >= 0.45 ? "Hot" : annual >= 0.28 ? "Warm" : annual >= 0.16 ? "Steady" : "Calm";
    const longer = vol63 != null ? vol63 * Math.sqrt(252) : null;
    return {
      ready: true,
      stopFrac,
      guard,
      annual,
      longer,
      band,
      note:
        "Swing Guard · trail about " +
        (stopFrac * 100).toFixed(1) +
        "% under last close (~$" +
        guard +
        ") from 21-bar realized vol. Band " +
        band +
        (longer != null ? " · longer ~" + (longer * 100).toFixed(0) + "% ann." : "") +
        ". Guidance only — not a live stop.",
    };
  }

  // Season Lean — same-calendar-month average return from available closed bars.
  function seasonLeanRead(list) {
    if (!list || list.length < 120) {
      return { ready: false, label: "Unread", why: "Need more closed bars for Season Lean." };
    }
    const last = list[list.length - 1];
    const stamp = String(last.date || "");
    const month = stamp.length >= 7 ? Number(stamp.slice(5, 7)) : NaN;
    if (!(month >= 1 && month <= 12)) {
      return { ready: false, label: "Unread", why: "Bar date unread for Season Lean." };
    }
    const monthRets = [];
    for (let index = 21; index < list.length; index += 1) {
      const bar = list[index];
      const prior = list[index - 21];
      const m = String(bar.date || "").length >= 7 ? Number(String(bar.date).slice(5, 7)) : NaN;
      if (m !== month) continue;
      if (!(prior.close > 0) || !(bar.close > 0)) continue;
      monthRets.push(bar.close / prior.close - 1);
    }
    if (monthRets.length < 3) {
      return {
        ready: false,
        label: "Thin",
        month,
        why: "Not enough same-month samples for Season Lean.",
      };
    }
    const avg = monthRets.reduce((sum, value) => sum + value, 0) / monthRets.length;
    const label = avg > 0.015 ? "Tailwind" : avg < -0.015 ? "Headwind" : "Neutral";
    return {
      ready: true,
      month,
      samples: monthRets.length,
      avg,
      label,
      why:
        "Season Lean · month " +
        month +
        " avg ~21-bar return " +
        (avg >= 0 ? "+" : "") +
        (avg * 100).toFixed(1) +
        "% across " +
        monthRets.length +
        " samples · " +
        label +
        ". Closed bars only — not a forecast.",
    };
  }

  // Name Pulse — Steady / Watch / Soft from desk + stack + cycle + Swing Guard band.
  function namePulseRead(parts) {
    const source = parts || {};
    let score = 50;
    const bits = [];
    if (source.deskScore != null) {
      score += clampNum(Math.round((source.deskScore - 50) * 0.35), -18, 18);
      bits.push("desk " + source.deskScore);
    }
    if (source.stacked) {
      score += 8;
      bits.push("stacked");
    } else if (source.stacked === false) {
      score -= 4;
      bits.push("unstacked");
    }
    if (source.cycleSignal === "buy_before_mania" || source.cycleSignal === "bottom_in") {
      score += 10;
      bits.push("cycle lift");
    } else if (source.cycleSignal === "sell_into_greed" || source.cycleSignal === "top_in") {
      score -= 10;
      bits.push("cycle soft");
    }
    const band = source.guardBand || "";
    if (band === "Hot") {
      score -= 8;
      bits.push("vol hot");
    } else if (band === "Warm") {
      score -= 3;
      bits.push("vol warm");
    } else if (band === "Calm") {
      score += 4;
      bits.push("vol calm");
    }
    if (source.seasonLabel === "Tailwind") {
      score += 4;
      bits.push("season lift");
    } else if (source.seasonLabel === "Headwind") {
      score -= 4;
      bits.push("season headwind");
    }
    score = Math.round(clampNum(score, 1, 100));
    const pulse = score >= 62 ? "Steady" : score <= 42 ? "Soft" : "Watch";
    return {
      ready: true,
      pulse,
      score,
      why:
        "Name Pulse · " +
        pulse +
        " (" +
        score +
        "/100) · " +
        bits.join(" · ") +
        ". Own closed-bar blend — not a third-party health score.",
    };
  }

  // Reclaim Cue — Quiet / Soft / Reclaim when close re-takes Swing Guard after weakness.
  function reclaimCueRead(list, guard) {
    if (!guard || !guard.ready || !list || list.length < 30) {
      return { ready: false, cue: "Unread", why: "Need Swing Guard for Reclaim Cue." };
    }
    const close = list[list.length - 1].close;
    const stopFrac = guard.stopFrac;
    let softBars = 0;
    for (let index = list.length - 2; index >= Math.max(1, list.length - 16); index -= 1) {
      const peak = list.slice(Math.max(0, index - 20), index + 1).reduce((hi, bar) => Math.max(hi, bar.high || bar.close), 0);
      const line = peak * (1 - stopFrac);
      if (list[index].close < line) softBars += 1;
      else break;
    }
    const peakNow = list
      .slice(Math.max(0, list.length - 21), list.length)
      .reduce((hi, bar) => Math.max(hi, bar.high || bar.close), 0);
    const lineNow = peakNow * (1 - stopFrac);
    let cue = "Quiet";
    if (softBars >= 3 && close < lineNow) cue = "Soft";
    else if (softBars >= 2 && close >= lineNow) cue = "Reclaim";
    return {
      ready: true,
      cue,
      softBars,
      line: Math.round(lineNow * 100) / 100,
      why:
        cue === "Reclaim"
          ? "Reclaim Cue · close back above Swing Guard after " + softBars + " soft bars. Chart review only — Edge still gates buys."
          : cue === "Soft"
            ? "Reclaim Cue · Soft · close still under Swing Guard (" + softBars + " soft bars). Not a short signal."
            : "Reclaim Cue · Quiet · no soft stretch under Swing Guard recently.",
    };
  }

  function scenarioBand(close, basePct, bullPct, bearPct) {
    const roundPx = (pct) => {
      const price = close * (1 + pct);
      if (price >= 1000) return Math.round(price);
      if (price >= 100) return Math.round(price * 10) / 10;
      return Math.round(price * 100) / 100;
    };
    return {
      basePct,
      bullPct,
      bearPct,
      base: roundPx(basePct),
      bull: roundPx(bullPct),
      bear: roundPx(bearPct),
    };
  }

  function roundForecastPx(close, price) {
    if (!(price > 0)) return null;
    if (close >= 1000 || price >= 1000) return Math.round(price);
    if (close >= 100 || price >= 100) return Math.round(price * 10) / 10;
    return Math.round(price * 100) / 100;
  }

  function marketForecastRead(bars, options) {
    const source = options || {};
    const list = closedBars(bars || [], false);
    const symbol = source.symbol || "";
    if (list.length < 80) {
      return {
        ready: false,
        symbol,
        why: "Need more closed daily bars for Forecaster.",
        deskScore: null,
        confidence: null,
      };
    }
    const close = list[list.length - 1].close;
    if (!(close > 0)) {
      return { ready: false, symbol, why: "Last close unread.", deskScore: null, confidence: null };
    }
    const strength = strengthRead(list);
    const cycle = cycleMapRead(list);
    const squeeze = squeezeRead(list, { live: false });
    const seat = rangeSeat(list);
    const timing = edge(list, { live: false, vixBars: source.vixBars || [] });
    // Lane Align needs the sector board from the desk — fail closed when unread (never invent).
    const lane = source.lane && source.lane.ready ? source.lane : null;
    const pivots = trendPivots(list);
    const guard = swingGuardRead(list, close);
    const season = seasonLeanRead(list);
    const vol = realizedVol(list, 21);
    const r63 = retOverBars(list, 63);
    const r252 = retOverBars(list, Math.min(252, list.length - 1));
    const stacked = !!(strength && strength.stacked);
    const ret5 = strength && strength.ret5 != null ? strength.ret5 : 0;
    const midAvg =
      strength && strength.ema50 != null
        ? strength.ema50
        : list.length > 50
          ? ema(
              list.map((bar) => bar.close),
              50
            ).slice(-1)[0]
          : null;

    // Scenario slopes from closed-bar structure (range + vol), then snap to real levels.
    const volAnchor = vol != null ? vol * Math.sqrt(63) : 0.08;
    let nearBase = clampNum((r63 != null ? r63 * 0.35 : 0) + ret5 * 2, -0.12, 0.12);
    if (stacked) nearBase += 0.02;
    if (cycle && (cycle.stage === "greed" || cycle.stage === "delusion" || cycle.stage === "mania")) nearBase -= 0.03;
    if (cycle && (cycle.stage === "despair" || cycle.stage === "return_mean" || cycle.stage === "bear_trap")) nearBase += 0.02;
    if (season && season.ready && season.label === "Tailwind") nearBase += 0.01;
    if (season && season.ready && season.label === "Headwind") nearBase -= 0.01;
    const nearBull = clampNum(nearBase + Math.max(0.04, volAnchor * 0.9), nearBase + 0.02, 0.28);
    const nearBear = clampNum(nearBase - Math.max(0.04, volAnchor * 0.9), -0.28, nearBase - 0.02);

    const farVol = vol != null ? vol * Math.sqrt(252) : 0.16;
    let farBase = clampNum((r252 != null ? r252 * 0.25 : nearBase * 2) + (stacked ? 0.04 : -0.02), -0.25, 0.3);
    if (cycle && (cycle.signal === "sell_into_greed" || cycle.signal === "top_in")) farBase -= 0.05;
    if (cycle && (cycle.signal === "bottom_in" || cycle.signal === "buy_before_mania")) farBase += 0.04;
    const farBull = clampNum(farBase + Math.max(0.08, farVol * 0.85), farBase + 0.04, 0.55);
    const farBear = clampNum(farBase - Math.max(0.08, farVol * 0.85), -0.55, farBase - 0.04);

    const near = Object.assign({ label: "About 3 months", bars: 63 }, scenarioBand(close, nearBase, nearBull, nearBear));
    const far = Object.assign({ label: "About 1 year", bars: 252 }, scenarioBand(close, farBase, farBull, farBear));

    // Snap targets toward closed-bar pivot / average levels when they sit on the right side of price.
    const nextSupport =
      pivots && pivots.support < close
        ? roundForecastPx(close, pivots.support)
        : pivots && pivots.confirmLow < close
          ? roundForecastPx(close, pivots.confirmLow)
          : guard && guard.ready
            ? roundForecastPx(close, guard.guard)
            : roundForecastPx(close, close * (1 + near.bearPct));
    const nextResist =
      pivots && pivots.resistance > close
        ? roundForecastPx(close, pivots.resistance)
        : pivots && pivots.confirmHigh > close
          ? roundForecastPx(close, pivots.confirmHigh)
          : roundForecastPx(close, close * (1 + near.bullPct));
    if (nextResist != null && nextResist > close) {
      near.bull = nextResist;
      near.bullPct = nextResist / close - 1;
    }
    if (nextSupport != null && nextSupport < close) {
      near.bear = nextSupport;
      near.bearPct = nextSupport / close - 1;
    }
    // Base = pull toward mid average when it is between bear and bull; else midpoint of near band.
    let basePx = roundForecastPx(close, close * (1 + near.basePct));
    if (midAvg != null && midAvg > near.bear && midAvg < near.bull) {
      basePx = roundForecastPx(close, midAvg * 0.45 + close * 0.55);
    } else {
      basePx = roundForecastPx(close, (near.bull + near.bear) / 2);
    }
    near.base = basePx;
    near.basePct = basePx / close - 1;

    const spyBars = closedBars(source.spyBars || [], false);
    const spyStrength = spyBars.length >= 50 ? strengthRead(spyBars) : null;
    const spyRet63 = spyBars.length > 63 ? retOverBars(spyBars, 63) : null;
    let vsSpy = {
      ready: !!(spyStrength && r63 != null && spyRet63 != null),
      relative63: null,
      label: "SPY unread",
      why: "Need SPY closed bars for a benchmark compare.",
    };
    if (vsSpy.ready) {
      const rel = r63 - spyRet63;
      vsSpy.relative63 = rel;
      vsSpy.label = rel > 0.02 ? "Ahead of SPY" : rel < -0.02 ? "Behind SPY" : "In line with SPY";
      vsSpy.why =
        "About 3 months: this name " +
        (r63 >= 0 ? "+" : "") +
        (r63 * 100).toFixed(1) +
        "% · SPY " +
        (spyRet63 >= 0 ? "+" : "") +
        (spyRet63 * 100).toFixed(1) +
        "% · relative " +
        (rel >= 0 ? "+" : "") +
        (rel * 100).toFixed(1) +
        "%. Closed bars only.";
    }

    let deskScore = 45;
    const parts = [];
    let confidence = 28;
    const confBits = [];
    if (timing && timing.ready) {
      const edgePts = Math.round((Number(timing.score) || 0) * (100 / EDGE_SCORE) * 0.35);
      deskScore += edgePts - 10;
      parts.push("Edge " + timing.score + "/" + EDGE_SCORE);
      confidence += Math.round((Number(timing.score) || 0) * (18 / EDGE_SCORE));
      if (Number(timing.score) >= EDGE_SCORE - 1) confBits.push("Edge high");
    } else {
      parts.push("Edge unread");
      confBits.push("Edge unread");
    }
    if (squeeze && squeeze.ready) {
      if (squeeze.direction === "bull" && (squeeze.state === "ON" || squeeze.dots >= 5 || squeeze.rising)) {
        deskScore += 12;
        confidence += 8;
        parts.push("Squeeze constructive");
        confBits.push("Squeeze on");
      } else if (squeeze.direction === "bear") {
        deskScore -= 8;
        confidence -= 4;
        parts.push("Squeeze soft");
      } else {
        parts.push("Squeeze mixed");
      }
    }
    if (cycle && cycle.ready) {
      if (cycle.signal === "buy_before_mania" || cycle.signal === "bottom_in") {
        deskScore += 12;
        confidence += 6;
      } else if (cycle.signal === "sell_into_greed" || cycle.signal === "top_in") {
        deskScore -= 12;
        confidence -= 4;
      } else if (cycle.signal === "bottom_watch") deskScore += 4;
      parts.push("Cycle " + (cycle.label || cycle.stage));
    }
    if (strength) {
      deskScore += Math.round((strength.score - 50) * 0.25);
      parts.push(stacked ? "Stacked" : "Unstacked");
      if (stacked) {
        confidence += 8;
        confBits.push("Stack");
      } else confidence -= 3;
    }
    if (seat && seat.seat === "high") {
      deskScore += 4;
      confidence += 3;
      parts.push("Seat High");
    } else if (seat && seat.seat === "low") {
      deskScore -= 2;
      parts.push("Seat Low");
    }
    if (lane && lane.ready && (lane.lane === "open" || lane.lane === "soft")) {
      confidence += 4;
      confBits.push("Lane " + lane.lane);
    }
    if (pivots && pivots.support < close && pivots.resistance > close) {
      confidence += 10;
      confBits.push("Pivots boxed");
    } else if (!pivots) {
      confidence -= 8;
      confBits.push("Pivots unread");
    }
    if (guard && guard.ready) {
      confidence += 4;
      if (guard.band === "Hot") confidence -= 6;
      else if (guard.band === "Calm") confidence += 3;
    } else {
      confidence -= 6;
      confBits.push("Vol unread");
    }
    if (vsSpy.ready && vsSpy.relative63 != null) {
      deskScore += clampNum(Math.round(vsSpy.relative63 * 80), -8, 8);
      parts.push(vsSpy.label);
      if (Math.abs(vsSpy.relative63) < 0.04) confidence += 2;
    }
    if (season && season.ready) {
      if (season.label === "Tailwind") confidence += 2;
      if (season.label === "Headwind") confidence -= 2;
      if (season.label === "Thin") confidence -= 2;
    }
    deskScore = Math.round(clampNum(deskScore, 1, 100));
    confidence = Math.round(clampNum(confidence, 5, 92));
    const deskTone = deskScore >= 65 ? "Constructive" : deskScore <= 40 ? "Cautious" : "Mixed";
    const pulse = namePulseRead({
      deskScore,
      stacked,
      cycleSignal: cycle && cycle.signal,
      guardBand: guard && guard.band,
      seasonLabel: season && season.label,
    });
    const reclaim = reclaimCueRead(list, guard);

    // Concrete invalidation price: Swing Guard, else pivot support, else near bear.
    let invalidatePx =
      guard && guard.ready
        ? roundForecastPx(close, guard.guard)
        : nextSupport != null
          ? nextSupport
          : roundForecastPx(close, near.bear);
    if (midAvg != null && midAvg < close && stacked) {
      // Stacked constructive: lose mid average is a sharper prove-wrong than a distant bear.
      const midLine = roundForecastPx(close, midAvg);
      if (midLine != null && midLine > invalidatePx) invalidatePx = midLine;
    }
    const invalidation =
      "Prove wrong under " +
      plainPrice(invalidatePx) +
      " on a closed daily bar" +
      (guard && guard.ready ? " (Swing Guard)" : nextSupport != null ? " (next support)" : " (near bear)") +
      ".";

    const cash = Number(source.cash) > 0 ? Number(source.cash) : 10000;
    const riskPct = 0.01;
    const stopFrac =
      guard && guard.ready ? Math.max(0.02, guard.stopFrac) : Math.max(0.02, Math.abs(near.bearPct));
    const riskCash = cash * riskPct;
    const maxNotional = riskCash / stopFrac;
    const maxShares = close > 0 ? Math.floor((maxNotional / close) * 10000) / 10000 : 0;
    const potentialLoss = Math.min(riskCash, maxShares * close * stopFrac);
    const riskSeat = {
      ready: true,
      stopFrac,
      maxShares,
      potentialLoss,
      band: guard && guard.band ? guard.band : "Unread",
      note:
        "Risk Seat · risk ~" +
        (riskPct * 100).toFixed(0) +
        "% of cash (~$" +
        Math.round(riskCash) +
        ") to Swing Guard / stop (~" +
        (stopFrac * 100).toFixed(1) +
        "%). About " +
        maxShares +
        " shares · potential loss ~$" +
        Math.round(potentialLoss) +
        ". Higher vol shrinks size. Guidance only — not a broker order.",
    };

    const levels = {
      ready: !!(nextSupport != null || nextResist != null),
      last: roundForecastPx(close, close),
      support: nextSupport,
      resistance: nextResist,
      confirmHigh: pivots ? roundForecastPx(close, pivots.confirmHigh) : null,
      confirmLow: pivots ? roundForecastPx(close, pivots.confirmLow) : null,
      swingGuard: guard && guard.ready ? roundForecastPx(close, guard.guard) : null,
      invalidate: invalidatePx,
      midAvg: midAvg != null ? roundForecastPx(close, midAvg) : null,
      why:
        "Price levels · last " +
        plainPrice(close) +
        (nextSupport != null ? " · next support " + plainPrice(nextSupport) : "") +
        (nextResist != null ? " · next resistance " + plainPrice(nextResist) : "") +
        (invalidatePx != null ? " · invalidate " + plainPrice(invalidatePx) : "") +
        ". Closed-bar pivots + Swing Guard only — fail closed when unread.",
    };

    const assumptions = [
      "Closed daily bars only — today's forming bar is not used as a settled signal.",
      "Near targets snap to next closed-bar support / resistance when available; otherwise vol band.",
      "Confidence rises when Edge · Squeeze · stack · pivots · Lane · Swing Guard agree; unread pieces cut confidence.",
      "Prove-wrong is a concrete price (Swing Guard / mid average / support), not vague copy.",
      "Edge · Best setup · Auto still gate any paper buy. Live stays locked.",
    ];

    return {
      ready: true,
      symbol,
      close: levels.last,
      asOf: list[list.length - 1].date,
      deskScore,
      deskTone,
      confidence,
      confidenceWhy:
        "Price confidence " +
        confidence +
        "/100 · " +
        (confBits.length ? confBits.join(" · ") : parts.join(" · ")) +
        ". Not a guarantee.",
      deskWhy:
        deskTone +
        " desk score " +
        deskScore +
        "/100 · confidence " +
        confidence +
        "/100 · " +
        parts.join(" · ") +
        ". Own closed-bar blend — not a third-party AI score.",
      near,
      far,
      levels,
      swingGuard: guard,
      riskSeat,
      namePulse: pulse,
      reclaimCue: reclaim,
      seasonLean: season,
      assumptions,
      vsSpy,
      invalidation,
      invalidatePx,
      size: riskSeat,
      edge: timing && timing.ready ? { score: timing.score, call: timing.call, stage: timing.stage } : null,
      cycle: cycle && cycle.ready ? { stage: cycle.stage, label: cycle.label, signal: cycle.signal, signalLabel: cycle.signalLabel } : null,
      why:
        "Forecaster price call from closed bars · last " +
        plainPrice(close) +
        " · base " +
        plainPrice(near.base) +
        " · bull " +
        plainPrice(near.bull) +
        " · bear " +
        plainPrice(near.bear) +
        " · confidence " +
        confidence +
        "/100. Complements Market Outlook. Not a crystal ball. Edge still gates buys. Paper only. Live locked.",
    };
  }

  function forecastBookHeat(rows) {
    const list = rows || [];
    if (!list.length) {
      return { ready: false, heat: "Unread", why: "Run Forecaster for Book Heat." };
    }
    let hot = 0;
    let warm = 0;
    let calm = 0;
    list.forEach((row) => {
      const band = (row.read && row.read.swingGuard && row.read.swingGuard.band) || "";
      if (band === "Hot") hot += 1;
      else if (band === "Warm") warm += 1;
      else if (band === "Calm" || band === "Steady") calm += 1;
    });
    const heat = hot >= Math.ceil(list.length * 0.4) ? "Hot" : hot + warm >= Math.ceil(list.length * 0.55) ? "Warm" : "Calm";
    return {
      ready: true,
      heat,
      hot,
      warm,
      calm,
      why:
        "Book Heat · " +
        heat +
        " across " +
        list.length +
        " ranked names (hot " +
        hot +
        " · warm " +
        warm +
        " · calm/steady " +
        calm +
        "). Closed-bar vol bands only — not a dark-pool or breadth print.",
    };
  }

  function forecastBoard(input) {
    const source = input || {};
    const symbols = source.symbols || [];
    const barsMap = source.bars || {};
    const rows = symbols
      .map((symbol) => {
        const read = marketForecastRead(barsMap[symbol], {
          symbol,
          spyBars: source.spyBars,
          vixBars: source.vixBars,
          cash: source.cash,
        });
        if (!read || !read.ready) return null;
        return {
          symbol,
          deskScore: read.deskScore,
          deskTone: read.deskTone,
          confidence: read.confidence,
          nearBase: read.near.base,
          nearBull: read.near.bull,
          nearBear: read.near.bear,
          support: read.levels && read.levels.support,
          resistance: read.levels && read.levels.resistance,
          invalidate: read.invalidatePx,
          pulse: read.namePulse && read.namePulse.pulse,
          vsSpy: read.vsSpy && read.vsSpy.label,
          signal: read.cycle && read.cycle.signalLabel,
          why: read.deskWhy,
          read,
        };
      })
      .filter(Boolean)
      .sort(
        (left, right) =>
          right.confidence - left.confidence ||
          right.deskScore - left.deskScore ||
          left.symbol.localeCompare(right.symbol)
      );
    const bookHeat = forecastBookHeat(rows);
    const limit = Math.max(1, Math.min(40, Number(source.limit) || 12));
    return {
      ready: true,
      rows: rows.slice(0, limit),
      bookHeat,
      why: rows.length
        ? "Ranked by price confidence · desk score (Edge · Squeeze · Cycle · stack · pivots · SPY). Concrete levels. Guidance only. Not a crystal ball."
        : "No forecast rows ready. Open a market or wait for closed bars.",
    };
  }

  root.PaperFrames = {
    edge,
    vixFallingOn,
    vixSeries,
    EDGE_SCORE,
    EDGE_NAMES,
    gatePrint,
    edgeDeskCall,
    firePrecision,
    fireFreshFlip,
    DESK_CARDS,
    resolveDeskCard,
    confirmLock,
    drawEdgeCalls,
    describeBar,
    showTip,
    hideTip,
    pickBar,
    attachPick,
    edgeLine,
    plainPrice,
    internals,
    deskInternals,
    INTERNAL_SYMBOLS,
    squeezeRead,
    squeezeScore,
    strengthRead,
    isAPlusSqueeze,
    dayBias,
    tideRead,
    weeklyTideOn,
    fadeRead,
    bestFades,
    FADE_FIVE_LIMIT,
    rangeSeat,
    rangeSeatAt,
    seatTrailMult,
    seatTrailRead,
    leanBookAt,
    leanBookRead,
    leanBookCashGuard,
    LEAN_BOOK_ATR,
    LEAN_BOOK_STOP_ATR,
    LEAN_BOOK_MAX_HOLD,
    LEAN_BOOK_MAX_OPEN,
    LEAN_BOOK_RISK_PCT,
    LEAN_BOOK_CASH_FLOOR,
    laneAlign,
    laneAllowsAuto,
    LANE_SECTOR_MIN,
    SEAT_HIGH_PCT,
    SEAT_TRAIL_CHANNEL,
    SEAT_TRAIL_VOL_MULT,
    SEAT_TRAIL_MAX_HOLD,
    SEAT_TRAIL_HIGH,
    SEAT_TRAIL_MID,
    SEAT_TRAIL_LOW,
    marketScorecard,
    horizonOutlook,
    marketOutlook,
    cycleMapRead,
    marketTankRead,
    defensiveShieldScan,
    riskReboundScan,
    marketForecastRead,
    forecastBoard,
    forecastBookHeat,
    swingGuardRead,
    seasonLeanRead,
    namePulseRead,
    reclaimCueRead,
    DEFENSIVE_SHIELD,
    RISK_REBOUND,
    SCORECARD_INDEXES,
    SCORECARD_SECTORS,
    strategies,
    STRATEGY_INFO,
    STRATEGY_IDS,
    tradeBookStats,
    recentTradeBook,
    RECENT_TRADE_WINDOW,
    INCUBATE_MIN_TRADES,
    cci,
    cciPane,
    cciSnapState,
    wma,
    hull,
    volumeWave,
    openingRange,
    pro,
    liveBar,
    closedBars,
    barIndexAt,
    hiddenTape,
    bigPrintRead,
    volatility,
    bollinger,
    macd,
    rollingVwap,
    volumeProfile,
    FRAMES,
    frameById,
    barsFor,
    analyze,
    brief,
    draw,
    intradayDay,
    averages,
    stackAverages,
    paintCandles,
    momentumBars,
    momentumTone,
    squeezeTone,
    squeezeName,
    squeezeAlert,
    candlePlace,
    compression,
    trendPivots,
    paintPivots,
    periodVwap,
    deskClearRead,
    big4Indicator,
    trueMomentum,
    zoomBars,
    zoomStep,
    ZOOM_STOPS,
    DEFAULT_ZOOM,
    mood,
    lesson,
  };
})(typeof globalThis !== "undefined" ? globalThis : this);
