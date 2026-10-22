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

  function averages(bars) {
    return {
      high: ema(bars.map((bar) => bar.high), 34),
      close: ema(bars.map((bar) => bar.close), 34),
      low: ema(bars.map((bar) => bar.low), 34),
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
    if (level === 1) return "#c5d0dc";
    return "#3ddc97";
  }

  function squeezeName(level) {
    if (level >= 3) return "High";
    if (level === 2) return "Mid";
    if (level === 1) return "Wide";
    return "Off";
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
      if (!dashed) ctx.fillText(label, x2 - ctx.measureText(label).width - 4, y(price) - 3);
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

  function draw(canvas, bars) {
    if (!canvas || !bars || !bars.length) return;
    const ratio = root.devicePixelRatio || 1;
    const width = canvas.clientWidth || 640;
    const height = canvas.clientHeight || 280;
    canvas.width = Math.floor(width * ratio);
    canvas.height = Math.floor(height * ratio);
    const ctx = canvas.getContext("2d");
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);
    const lines = averages(bars);
    const pivots = trendPivots(bars);
    const lower = height >= 220;
    const priceBottom = lower ? Math.round(height * 0.7) : height - (height < 150 ? 8 : 18);
    let low = Math.min(...bars.map((bar) => bar.low));
    let high = Math.max(...bars.map((bar) => bar.high));
    [lines.high, lines.close, lines.low].forEach((series) => {
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
    const slot = (width - padLeft - 10) / bars.length;
    const xAt = (index) => padLeft + index * slot + slot / 2;
    ctx.strokeStyle = "#2a3340";
    ctx.fillStyle = "#93a0ad";
    const grids = height < 150 ? 2 : 4;
    for (let grid = 0; grid < grids; grid += 1) {
      const price = low + ((high - low) * grid) / Math.max(1, grids - 1);
      const line = y(price);
      ctx.beginPath();
      ctx.moveTo(padLeft, line);
      ctx.lineTo(width - 8, line);
      ctx.stroke();
      ctx.fillText(price.toFixed(price >= 100 ? 2 : 4), 4, line + 4);
    }
    bars.forEach((bar, index) => {
      const x = xAt(index);
      const rising = bar.close >= bar.open;
      ctx.strokeStyle = rising ? "#3ddc97" : "#ff6b7a";
      ctx.fillStyle = ctx.strokeStyle;
      ctx.beginPath();
      ctx.moveTo(x, y(bar.high));
      ctx.lineTo(x, y(bar.low));
      ctx.stroke();
      const top = y(Math.max(bar.open, bar.close));
      const bottom = y(Math.min(bar.open, bar.close));
      ctx.fillRect(x - Math.max(1, slot * 0.28), top, Math.max(2, slot * 0.56), Math.max(1, bottom - top));
    });
    strokeSeries(ctx, lines.high, xAt, y, "#3ddc97", 2);
    strokeSeries(ctx, lines.close, xAt, y, "#5b9dff", 2);
    strokeSeries(ctx, lines.low, xAt, y, "#ff6b7a", 2);
    paintPivots(ctx, pivots, xAt, y, bars.length - 1);
    if (lower) {
      const study = compression(bars);
      const histoTop = priceBottom + 10;
      const histoBottom = height - 10;
      const zero = (histoTop + histoBottom) / 2;
      const scale = Math.max(...study.momentum.map((value) => Math.abs(value || 0)), 1e-9);
      ctx.strokeStyle = "#2a3340";
      ctx.beginPath();
      ctx.moveTo(padLeft, zero);
      ctx.lineTo(width - 8, zero);
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
    }
    ctx.fillStyle = "#93a0ad";
    if (height >= 150) {
      ctx.fillText(bars[0].date, padLeft, 14);
      const endLabel = bars[bars.length - 1].date;
      ctx.fillText(endLabel, Math.max(padLeft + 8, width - 8 - ctx.measureText(endLabel).width), 14);
    }
  }

  root.PaperFrames = {
    FRAMES,
    frameById,
    barsFor,
    analyze,
    brief,
    draw,
    intradayDay,
    averages,
    momentumTone,
    squeezeTone,
    squeezeName,
    candlePlace,
    compression,
    trendPivots,
    paintPivots,
  };
})(typeof globalThis !== "undefined" ? globalThis : this);
