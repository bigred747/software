/* Ten sample timeframes built from the daily series already on this page. */
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
      lead: ups + " of 10 timeframes are up and " + downs + " are down. Intraday bars are built from the daily sample. They are not a live tape.",
      lines,
    };
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
    let low = Math.min(...bars.map((bar) => bar.low));
    let high = Math.max(...bars.map((bar) => bar.high));
    const span = high - low || 1;
    low -= span * 0.08;
    high += span * 0.08;
    ctx.font = "12px Segoe UI, sans-serif";
    const padLeft = Math.max(44, Math.ceil(ctx.measureText(high.toFixed(high >= 100 ? 2 : 4)).width) + 12);
    const padTop = height < 150 ? 8 : 22;
    const padBottom = height < 150 ? 8 : 18;
    const y = (price) => padTop + ((high - price) / (high - low)) * (height - padTop - padBottom);
    const slot = (width - padLeft - 10) / bars.length;
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
      const x = padLeft + index * slot + slot / 2;
      const up = bar.close >= bar.open;
      ctx.strokeStyle = up ? "#3ddc97" : "#ff6b7a";
      ctx.fillStyle = ctx.strokeStyle;
      ctx.beginPath();
      ctx.moveTo(x, y(bar.high));
      ctx.lineTo(x, y(bar.low));
      ctx.stroke();
      const top = y(Math.max(bar.open, bar.close));
      const bottom = y(Math.min(bar.open, bar.close));
      ctx.fillRect(x - Math.max(1, slot * 0.28), top, Math.max(2, slot * 0.56), Math.max(1, bottom - top));
    });
    ctx.fillStyle = "#93a0ad";
    if (height >= 150) {
      ctx.fillText(bars[0].date, padLeft, 14);
      const endLabel = bars[bars.length - 1].date;
      ctx.fillText(endLabel, Math.max(padLeft + 8, width - 8 - ctx.measureText(endLabel).width), 14);
    }
  }

  root.PaperFrames = { FRAMES, frameById, barsFor, analyze, brief, draw, intradayDay };
})(typeof globalThis !== "undefined" ? globalThis : this);
