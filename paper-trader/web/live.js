/* Live crypto quotes for the mobile page. The computer desk loads every market through its own quote feed. */
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
    out.asOf = bars.asOf;
    return out;
  }

  function fromRows(rows, withTime, read) {
    const ordered = rows.map(read).filter(Boolean).sort((a, b) => a.unix - b.unix);
    const bars = [];
    let previous = "";
    ordered.forEach((row) => {
      if (!(row.open > 0 && row.high > 0 && row.low > 0 && row.close > 0)) return;
      const date = stamp(row.unix, withTime);
      if (date <= previous) return;
      bars.push({
        date,
        open: row.open,
        high: Math.max(row.high, row.open, row.close),
        low: Math.min(row.low, row.open, row.close),
        close: row.close,
        volume: Math.max(0, row.volume || 0),
      });
      previous = date;
    });
    if (!bars.length) throw new Error("No live quote");
    bars.asOf = Math.floor(Date.now() / 1000);
    return bars;
  }

  function timed(promise) {
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error("The quote feed did not answer. Check the connection.")), 12000);
      promise.then(
        (value) => {
          clearTimeout(timer);
          resolve(value);
        },
        (error) => {
          clearTimeout(timer);
          reject(error);
        }
      );
    });
  }

  async function pull(url) {
    let response;
    try {
      response = await timed(fetch(url, { credentials: "omit", cache: "no-store" }));
    } catch (error) {
      throw new Error("The quote feed did not answer. Check the connection.");
    }
    if (!response.ok) throw new Error("No live quote");
    return response.json();
  }

  async function gemini(symbol, frameId, withTime) {
    const scale = { "1m": "1m", "5m": "5m", "15m": "15m", "30m": "30m", "1h": "1hr", "4h": "1hr", "1D": "1day" };
    const frame = scale[frameId];
    if (!frame) throw new Error("Unknown timeframe");
    const rows = await pull("https://api.gemini.com/v2/candles/" + symbol.replace("-", "").toLowerCase() + "/" + frame);
    if (!Array.isArray(rows)) throw new Error("No live quote for " + symbol);
    const bars = fromRows(rows, withTime || frameId === "4h", (row) => {
      if (!Array.isArray(row) || row.length < 5) return null;
      return {
        unix: Number(row[0]) / 1000,
        open: Number(row[1]),
        high: Number(row[2]),
        low: Number(row[3]),
        close: Number(row[4]),
        volume: Number(row[5] || 0),
      };
    });
    return frameId === "4h" ? group(bars, 4) : bars;
  }

  async function kraken(symbol, frameId, withTime) {
    const scale = { "1m": 1, "5m": 5, "15m": 15, "30m": 30, "1h": 60, "4h": 240, "1D": 1440 };
    const interval = scale[frameId];
    if (!interval) throw new Error("Unknown timeframe");
    const base = symbol.replace(/-USD$/, "");
    const alias = { BTC: "XBT", DOGE: "XDG" };
    const pair = (alias[base] || base) + "USD";
    const payload = await pull("https://api.kraken.com/0/public/OHLC?pair=" + encodeURIComponent(pair) + "&interval=" + interval);
    if (!payload || !Array.isArray(payload.error) || payload.error.length) throw new Error("No live quote for " + symbol);
    const result = payload.result || {};
    const key = Object.keys(result).find((name) => Array.isArray(result[name]));
    if (!key) throw new Error("No live quote for " + symbol);
    return fromRows(result[key], withTime, (row) => {
      if (!Array.isArray(row) || row.length < 5) return null;
      return {
        unix: Number(row[0]),
        open: Number(row[1]),
        high: Number(row[2]),
        low: Number(row[3]),
        close: Number(row[4]),
        volume: Number(row[6] || 0),
      };
    });
  }

  async function coinbase(symbol, frameId, withTime) {
    const scale = { "1m": 60, "5m": 300, "15m": 900, "30m": 900, "1h": 3600, "4h": 3600, "1D": 86400 };
    const granularity = scale[frameId];
    if (!granularity) throw new Error("Unknown timeframe");
    const rows = await pull("https://api.exchange.coinbase.com/products/" + encodeURIComponent(symbol) + "/candles?granularity=" + granularity);
    if (!Array.isArray(rows)) throw new Error("No live quote for " + symbol);
    const bars = fromRows(rows, withTime || frameId === "30m" || frameId === "4h", (row) => {
      if (!Array.isArray(row) || row.length < 5) return null;
      return {
        unix: Number(row[0]),
        open: Number(row[3]),
        high: Number(row[2]),
        low: Number(row[1]),
        close: Number(row[4]),
        volume: Number(row[5] || 0),
      };
    });
    if (frameId === "30m") return group(bars, 2);
    if (frameId === "4h") return group(bars, 4);
    return bars;
  }

  async function candles(symbol, frameId, withTime) {
    const attempts = [gemini, kraken, coinbase];
    let last = new Error("The quote feed did not answer. Check the connection.");
    for (let index = 0; index < attempts.length; index += 1) {
      try {
        const bars = await attempts[index](symbol, frameId, withTime);
        if (bars && bars.length) return bars;
      } catch (error) {
        last = error instanceof Error ? error : last;
      }
    }
    throw last;
  }

  function crypto(symbol, category) {
    return category === "crypto" || /-USD$/.test(symbol);
  }

  async function daily(symbol, category) {
    if (!crypto(symbol, category)) {
      throw new Error(symbol + " loads on the computer desk. This device loads live crypto quotes.");
    }
    return candles(symbol, "1D", false);
  }

  async function frame(symbol, category, frameId) {
    if (!crypto(symbol, category)) {
      throw new Error(symbol + " loads on the computer desk. This device loads live crypto quotes.");
    }
    if (frameId === "1W" || frameId === "1M" || frameId === "1Q") {
      const dailyBars = await candles(symbol, "1D", false);
      return root.PaperFrames.barsFor(dailyBars, root.PaperFrames.frameById(frameId));
    }
    if (!{ "1m": 1, "5m": 1, "15m": 1, "30m": 1, "1h": 1, "4h": 1, "1D": 1 }[frameId]) throw new Error("Unknown timeframe");
    return candles(symbol, frameId, frameId !== "1D");
  }

  root.PaperLive = { daily, frame };
})(typeof globalThis !== "undefined" ? globalThis : this);
