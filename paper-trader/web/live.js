/* Live crypto quotes for the phone page. The computer desk loads every market through its own quote feed. */
(function (root) {
  function stamp(unix, withTime) {
    const moment = new Date(unix * 1000);
    const pad = (value) => String(value).padStart(2, "0");
    const day = moment.getUTCFullYear() + "-" + pad(moment.getUTCMonth() + 1) + "-" + pad(moment.getUTCDate());
    if (!withTime) return day;
    return day + " " + pad(moment.getUTCHours()) + ":" + pad(moment.getUTCMinutes());
  }

  function merge(chunk) {
    return {
      date: chunk[chunk.length - 1].date,
      open: chunk[0].open,
      high: Math.max(...chunk.map((bar) => bar.high)),
      low: Math.min(...chunk.map((bar) => bar.low)),
      close: chunk[chunk.length - 1].close,
      volume: chunk.reduce((sum, bar) => sum + bar.volume, 0),
    };
  }

  function group(bars, size) {
    const out = [];
    for (let index = 0; index < bars.length; index += size) {
      const chunk = bars.slice(index, index + size);
      if (chunk.length) out.push(merge(chunk));
    }
    return out;
  }

  async function coinbase(symbol, granularity, withTime) {
    const url = "https://api.exchange.coinbase.com/products/" + encodeURIComponent(symbol) + "/candles?granularity=" + granularity;
    let response;
    try {
      response = await fetch(url);
    } catch (error) {
      throw new Error("The quote feed did not answer. Check the connection.");
    }
    if (!response.ok) throw new Error("No live quote for " + symbol);
    const rows = await response.json();
    if (!Array.isArray(rows) || !rows.length) throw new Error("No live quote for " + symbol);
    const ordered = rows.filter((row) => Array.isArray(row) && row.length >= 5).sort((a, b) => Number(a[0]) - Number(b[0]));
    const bars = [];
    let previous = "";
    ordered.forEach((row) => {
      const open = Number(row[3]);
      const high = Number(row[2]);
      const low = Number(row[1]);
      const close = Number(row[4]);
      if (!(open > 0 && high > 0 && low > 0 && close > 0)) return;
      const date = stamp(Number(row[0]), withTime);
      if (date <= previous) return;
      bars.push({
        date,
        open,
        high: Math.max(high, open, close),
        low: Math.min(low, open, close),
        close,
        volume: Math.max(0, Number(row[5] || 0)),
      });
      previous = date;
    });
    if (!bars.length) throw new Error("No live quote for " + symbol);
    bars.asOf = Math.floor(Date.now() / 1000);
    return bars;
  }

  function crypto(symbol, category) {
    return category === "crypto" || /-USD$/.test(symbol);
  }

  async function daily(symbol, category) {
    if (!crypto(symbol, category)) {
      throw new Error(symbol + " loads on the computer desk. This phone loads live crypto quotes.");
    }
    return coinbase(symbol, 86400, false);
  }

  async function frame(symbol, category, frameId) {
    if (!crypto(symbol, category)) {
      throw new Error(symbol + " loads on the computer desk. This phone loads live crypto quotes.");
    }
    if (frameId === "30m") return group(await coinbase(symbol, 900, true), 2);
    if (frameId === "4h") return group(await coinbase(symbol, 3600, true), 4);
    if (frameId === "1W" || frameId === "1M" || frameId === "1Q") {
      const dailyBars = await coinbase(symbol, 86400, false);
      return root.PaperFrames.barsFor(dailyBars, root.PaperFrames.frameById(frameId));
    }
    const scale = { "1m": 60, "5m": 300, "15m": 900, "1h": 3600, "1D": 86400 };
    const granularity = scale[frameId];
    if (!granularity) throw new Error("Unknown timeframe");
    return coinbase(symbol, granularity, frameId !== "1D");
  }

  root.PaperLive = { daily, frame };
})(typeof globalThis !== "undefined" ? globalThis : this);
