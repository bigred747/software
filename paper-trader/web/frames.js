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

  const STACK_AVGS = [
    { length: 5, color: "#e879f9", name: "5" },
    { length: 9, color: "#22d3ee", name: "9" },
    { length: 21, color: "#f9a8d4", name: "21" },
    { length: 50, color: "#94a3b8", name: "50" },
    { length: 100, color: "#fb923c", name: "100" },
    { length: 200, color: "#f87171", name: "200" },
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

  function paintCandles(ctx, bars, xAt, y, slot) {
    const bodyW = Math.max(2, Math.min(slot * 0.64, Math.max(2, slot - 1.5)));
    bars.forEach((bar, index) => {
      const x = xAt(index);
      const up = bar.close >= bar.open;
      const topPrice = Math.max(bar.open, bar.close);
      const bottomPrice = Math.min(bar.open, bar.close);
      const top = y(topPrice);
      const bottom = y(bottomPrice);
      const bodyH = Math.max(1, bottom - top);
      ctx.strokeStyle = up ? "#2bbf8a" : "#d95563";
      ctx.lineWidth = Math.max(1, Math.min(2.25, slot * 0.14));
      ctx.beginPath();
      ctx.moveTo(x, y(bar.high));
      ctx.lineTo(x, y(bar.low));
      ctx.stroke();
      const left = x - bodyW / 2;
      if (up) {
        ctx.fillStyle = "rgba(7, 9, 11, 0.55)";
        ctx.fillRect(left, top, bodyW, bodyH);
        ctx.strokeStyle = "#3ddc97";
        ctx.lineWidth = 1.35;
        ctx.strokeRect(left + 0.5, top + 0.5, Math.max(1, bodyW - 1), Math.max(1, bodyH - 1));
      } else {
        ctx.fillStyle = "#ff6b7a";
        ctx.fillRect(left, top, bodyW, bodyH);
        ctx.strokeStyle = "#d95563";
        ctx.lineWidth = 1;
        ctx.strokeRect(left + 0.5, top + 0.5, Math.max(1, bodyW - 1), Math.max(1, bodyH - 1));
      }
    });
    ctx.lineWidth = 1;
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
      [pivots.fromHigh, pivots.confirmHigh, pivots.resistance, "rgba(255, 107, 122, 0.16)"],
      [pivots.fromLow, pivots.support, pivots.confirmLow, "rgba(61, 220, 151, 0.16)"],
    ];
    bands.forEach(([from, first, second, color]) => {
      const x1 = xAt(Math.max(0, from));
      const x2 = xAt(lastIndex);
      const y1 = y(first);
      const y2 = y(second);
      ctx.fillStyle = color;
      ctx.fillRect(Math.min(x1, x2), Math.min(y1, y2), Math.abs(x2 - x1), Math.max(1, Math.abs(y2 - y1)));
    });
    const lines = [
      [pivots.fromHigh, pivots.resistance, "#ff6b7a", false, "Resistance"],
      [pivots.fromHigh, pivots.confirmHigh, "#ff8f9a", false, "Confirm"],
      [pivots.fromHigh, pivots.riskOffHigh, "#d7e2ea", true, "Risk off"],
      [pivots.fromLow, pivots.support, "#3ddc97", false, "Support"],
      [pivots.fromLow, pivots.confirmLow, "#8ee8bf", false, "Confirm"],
      [pivots.fromLow, pivots.riskOffLow, "#d7e2ea", true, "Risk off"],
    ];
    ctx.save();
    ctx.font = "11px Segoe UI, sans-serif";
    const narrow = (ctx.canvas.paperWidth || 0) < 640;
    const placed = [];
    lines.forEach(([from, price, color, dashed, label]) => {
      if (dashed && lines.some((other) => !other[3] && Math.abs(y(other[1]) - y(price)) < 8 && other[0] === from)) return;
      const x1 = xAt(Math.max(0, from));
      const x2 = xAt(lastIndex);
      ctx.strokeStyle = color;
      ctx.fillStyle = color;
      ctx.lineWidth = dashed ? 1 : 1.75;
      ctx.setLineDash(dashed ? [4, 4] : []);
      ctx.beginPath();
      ctx.moveTo(x1, y(price));
      ctx.lineTo(x2, y(price));
      ctx.stroke();
      if (dashed || narrow) return;
      const textY = y(price) - 3;
      if (placed.some((prior) => Math.abs(prior - textY) < 14)) return;
      placed.push(textY);
      ctx.fillText(label, Math.max(x1 + 4, x2 - ctx.measureText(label).width - 4), textY);
    });
    ctx.restore();
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
    paintCandles(ctx, bars, xAt, y, slot);
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
  const EDGE_TRAIL = 2;
  const EDGE_SCORE = 5;
  const PRO_RISK = 0.01;

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

  function edge(bars, options) {
    const list = bars || [];
    const count = list.length;
    const live = !!(options && options.live) && count > 0;
    const end = live ? count - 1 : count;
    const closes = list.map((bar) => bar.close);
    const study = compression(list, 20);
    const squeeze = study.momentum;
    const tmo = trueMomentum(list, 14, 5, 3);
    const range = edgeAtr(list, 14);
    const bands = bollinger(list, 20, 2);
    const dots = new Array(count).fill(0);
    let run = 0;
    for (let index = 0; index < count; index += 1) {
      run = study.level[index] > 0 ? run + 1 : 0;
      dots[index] = run;
    }
    const score = new Array(count).fill(null);
    const trail = new Array(count).fill(null);
    const parts = new Array(count).fill(null);
    for (let index = EDGE_WARM; index < end; index += 1) {
      const now = squeeze[index];
      const prior = squeeze[index - 1];
      const checks = {
        dots: dots[index] >= EDGE_DOTS,
        above: now != null && now > 0,
        rising: now != null && prior != null && now >= prior,
        tmo: tmo.main[index] != null && tmo.signal[index] != null && tmo.main[index] > tmo.signal[index],
        band: bands.upper[index] != null && closes[index] < bands.upper[index],
      };
      parts[index] = checks;
      score[index] = Object.values(checks).filter(Boolean).length;
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
        holding = { index, date: bar.date, price, stop, high: bar.close };
        marks.push({ index, side: "buy", price });
      } else if (pending === "sell" && holding) {
        const price = bar.open;
        const pct = (price * (1 - EDGE_FEE)) / (holding.price * (1 + EDGE_FEE)) - 1;
        trades.push({ entryDate: holding.date, exitDate: bar.date, entry: holding.price, exit: price, pct, why: holding.why });
        marks.push({ index, side: "sell", price });
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
        if (score[index] === EDGE_SCORE && prior < EDGE_SCORE) pending = "buy";
      }
    }
    const last = count - 1;
    const settled = end - 1;
    const first = Math.min(EDGE_WARM, Math.max(0, settled));
    const hold = end > EDGE_WARM ? closes[settled] / list[first].open - 1 : 0;
    const total = equity - 1;
    const wins = trades.filter((trade) => trade.pct > 0).length;
    let call = "WAIT";
    const filledToday = live ? marks.find((mark) => mark.index === last) : null;
    if (pending === "buy") call = "BUY";
    else if (pending === "sell") call = "SELL";
    else if (filledToday) call = filledToday.side === "buy" ? "BUY" : "SELL";
    else if (holding) call = "HOLD";
    const openPct = holding ? closes[last] / (holding.price * (1 + EDGE_FEE)) - 1 : null;
    return {
      call,
      score: settled >= 0 ? score[settled] : null,
      parts: settled >= 0 ? parts[settled] : null,
      scores: score,
      allParts: parts,
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
        exposure: end > EDGE_WARM ? barsIn / (end - EDGE_WARM) : 0,
        bars: Math.max(0, end - EDGE_WARM),
      },
      ready: end > EDGE_WARM + 1,
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
    let wins = 0;
    let growth = 1;
    let worst = 0;
    trades.forEach((trade) => {
      if (trade.pct > 0) wins += 1;
      growth *= 1 + trade.pct;
      worst = Math.min(worst, trade.pct);
    });
    const lastClose = end > 0 ? list[end - 1].close : 0;
    let openPct = null;
    if (open) {
      openPct = lastClose / open.entry - 1;
      growth *= 1 + openPct;
    }
    const startPrice = warm < end ? list[warm].open : 0;
    return {
      trades,
      wins,
      open,
      pending,
      openPct,
      total: growth - 1,
      hold: startPrice > 0 ? lastClose / startPrice - 1 : 0,
      worst,
      exposure: end > warm ? barsIn / (end - warm) : 0,
    };
  }

  const STRATEGY_INFO = {
    edge: { name: "Edge · five checks", who: "This bot", idea: "Enters when all five checks turn on together on a closed bar. Exits on a momentum turn or the trailing stop." },
    squeeze: { name: "Squeeze release", who: "Volatility compression", idea: "Buy the first bar after Bollinger Bands move back outside Keltner Channels when momentum is above zero and rising. Stop under the low of that bar. Exit after two falling momentum bars." },
    donchian: { name: "20-day breakout", who: "Range breakout", idea: "Buy a close above the highest high of the last 20 bars. Exit on a close below the lowest low of the last 10 bars." },
    rsi2: { name: "RSI 2 pullback", who: "Short pullback", idea: "In an uptrend (close above the 200-bar average) buy when 2-period RSI drops under 10. Exit when price closes above its 5-bar average." },
  };

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
    const notReady = (id, why) => rows.push({ id, ...STRATEGY_INFO[id], ready: false, call: "WAIT", trades: 0, winRate: 0, total: 0, hold: 0, worst: 0, why });

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
    if (!row || !row.ready || row.state === "OFF") return { score: 0, parts: [], triple: false };
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
    const total = parts.reduce((sum, item) => sum + item[1], 0);
    return { score: Math.min(100, total), parts, triple };
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

  const EDGE_NAMES = { dots: "5+ squeeze dots", above: "squeeze momentum above 0", rising: "squeeze momentum rising", tmo: "true momentum above signal", band: "under the upper Bollinger band" };

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
    box.setAttribute("role", "status");
    box.style.cssText = "position:fixed;z-index:60;min-width:210px;max-width:300px;background:#101822;color:#e8eef7;border:2px solid " + (info.up ? "#3ddc97" : "#ff6b7a") + ";border-radius:10px;padding:8px 10px;font:13px Segoe UI,sans-serif;box-shadow:0 6px 24px rgba(0,0,0,.55)";
    const head = doc.createElement("div");
    head.style.cssText = "display:flex;justify-content:space-between;gap:10px;font-weight:700;margin-bottom:4px";
    const title = doc.createElement("span");
    title.textContent = info.title;
    const close = doc.createElement("button");
    close.type = "button";
    close.textContent = "×";
    close.setAttribute("aria-label", "Close price box");
    close.style.cssText = "background:none;border:0;color:#9fb0c6;font-size:16px;line-height:1;cursor:pointer;padding:0 2px";
    close.addEventListener("click", (event) => {
      event.stopPropagation();
      hideTip(doc);
    });
    head.append(title, close);
    box.appendChild(head);
    info.rows.forEach(([name, value]) => {
      const line = doc.createElement("div");
      line.style.cssText = "display:flex;justify-content:space-between;gap:12px";
      const left = doc.createElement("span");
      left.style.color = "#9fb0c6";
      left.textContent = name;
      const right = doc.createElement("span");
      right.textContent = value;
      line.append(left, right);
      box.appendChild(line);
    });
    if (info.edgeNote) {
      const note = doc.createElement("div");
      note.style.cssText = "margin-top:6px;color:#e2b15a;font-size:12px";
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

  root.PaperFrames = {
    edge,
    drawEdgeCalls,
    describeBar,
    showTip,
    hideTip,
    pickBar,
    attachPick,
    edgeLine,
    plainPrice,
    internals,
    INTERNAL_SYMBOLS,
    squeezeRead,
    squeezeScore,
    strategies,
    openingRange,
    pro,
    liveBar,
    closedBars,
    barIndexAt,
    hiddenTape,
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
    momentumTone,
    squeezeTone,
    squeezeName,
    squeezeAlert,
    candlePlace,
    compression,
    trendPivots,
    paintPivots,
    trueMomentum,
    zoomBars,
    zoomStep,
    ZOOM_STOPS,
    DEFAULT_ZOOM,
    mood,
    lesson,
  };
})(typeof globalThis !== "undefined" ? globalThis : this);
