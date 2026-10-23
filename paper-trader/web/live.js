/* Live quotes for the mobile page. Crypto uses the exchanges. Stocks, ETFs, and futures use the public quote feed. The computer desk loads every market through its own quote feed. */
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
    const last = ordered[ordered.length - 1];
    bars.asOf = last && last.unix > 0 ? Math.floor(last.unix) : Math.floor(Date.now() / 1000);
    return bars;
  }

  function applyTrade(bars, trade) {
    if (!trade || !(Number(trade.price) > 0) || !bars.length) return bars;
    const last = bars[bars.length - 1];
    const live = Number(trade.price);
    bars[bars.length - 1] = {
      date: last.date,
      open: last.open,
      high: Math.max(last.high, live, last.open),
      low: Math.min(last.low, live, last.open),
      close: live,
      volume: last.volume,
    };
    if (trade.asOf > 0) bars.asOf = trade.asOf;
    return bars;
  }

  async function coinbaseTicker(symbol) {
    const payload = await pull("https://api.exchange.coinbase.com/products/" + encodeURIComponent(symbol) + "/ticker");
    const price = Number(payload && payload.price);
    const asOf = payload && payload.time ? Math.floor(Date.parse(payload.time) / 1000) : 0;
    if (!(price > 0)) throw new Error("No live quote for " + symbol);
    return Object.assign({ price, asOf }, tapeFields({ bid: payload && payload.bid, ask: payload && payload.ask }));
  }

  async function bitstampTicker(symbol) {
    const pair = symbol.replace("-", "").toLowerCase();
    const payload = await pull("https://www.bitstamp.net/api/v2/ticker/" + pair + "/");
    const price = Number(payload && payload.last);
    const asOf = Number(payload && payload.timestamp) || 0;
    if (!(price > 0)) throw new Error("No live quote for " + symbol);
    return Object.assign({ price, asOf }, tapeFields({ bid: payload && payload.bid, ask: payload && payload.ask }));
  }

  async function lastTrade(symbol, category) {
    if (!crypto(symbol, category)) return cnbcLast(symbol, category);
    let last = new Error("The quote feed did not answer. Check the connection.");
    const readers = [coinbaseTicker, bitstampTicker];
    for (let index = 0; index < readers.length; index += 1) {
      try {
        return await readers[index](symbol);
      } catch (error) {
        last = error instanceof Error ? error : last;
      }
    }
    throw last;
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
      response = await timed(fetch(url));
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

  async function bitstamp(symbol, frameId, withTime) {
    const scale = { "1m": 60, "5m": 300, "15m": 900, "30m": 1800, "1h": 3600, "4h": 14400, "1D": 86400 };
    const step = scale[frameId];
    if (!step) throw new Error("Unknown timeframe");
    const pair = symbol.replace("-", "").toLowerCase();
    const payload = await pull("https://www.bitstamp.net/api/v2/ohlc/" + pair + "/?step=" + step + "&limit=300");
    const rows = payload && payload.data && payload.data.ohlc;
    if (!Array.isArray(rows)) throw new Error("No live quote for " + symbol);
    return fromRows(rows, withTime, (row) => {
      if (!row) return null;
      return {
        unix: Number(row.timestamp),
        open: Number(row.open),
        high: Number(row.high),
        low: Number(row.low),
        close: Number(row.close),
        volume: Number(row.volume || 0),
      };
    });
  }

  const feeds = [gemini, kraken, coinbase, bitstamp];
  let preferredFeed = null;

  async function candles(symbol, frameId, withTime) {
    const attempts = preferredFeed ? [preferredFeed].concat(feeds.filter((feed) => feed !== preferredFeed)) : feeds;
    let last = new Error("The quote feed did not answer. Check the connection.");
    for (let index = 0; index < attempts.length; index += 1) {
      try {
        const bars = await attempts[index](symbol, frameId, withTime);
        if (bars && bars.length) {
          preferredFeed = attempts[index];
          return bars;
        }
      } catch (error) {
        if (attempts[index] === preferredFeed) preferredFeed = null;
        last = error instanceof Error ? error : last;
      }
    }
    throw last;
  }

  function crypto(symbol, category) {
    return category === "crypto" || /-USD$/.test(symbol);
  }

  // Front-month prices checked against the computer's futures quotes. Micros use the same price as the full contract.
  const FUTURES = {
    ES: "@SP.1",
    NQ: "@ND.1",
    RTY: "@TFS.1",
    YM: "@DJ.1",
    MES: "@SP.1",
    MNQ: "@ND.1",
    M2K: "@TFS.1",
    MYM: "@DJ.1",
    CL: "@CL.1",
    MCL: "@CL.1",
    NG: "@NG.1",
    GC: "@GC.1",
    MGC: "@GC.1",
    SI: "@SI.1",
    HG: "@HG.1",
    ZB: "@US.1",
    ZN: "@TY.1",
    ZF: "@FV.1",
    ZT: "@TU.1",
    "6E": "@URO.1",
    "6J": "@JY.1",
    "6B": "@BP.1",
    "6A": "@AD.1",
    "6C": "@CD.1",
    ZC: "@C.1",
    ZW: "@W.1",
    LE: "@LC.1",
    HE: "@LH.1",
  };

  function feedSymbol(symbol, category) {
    if (category === "future") {
      const mapped = FUTURES[symbol];
      if (!mapped) throw new Error("No live quote for " + symbol);
      return mapped;
    }
    return symbol;
  }

  function priceOf(text) {
    const value = Number(String(text == null ? "" : text).replace(/,/g, ""));
    return value > 0 ? value : 0;
  }

  function volumeOf(text) {
    const raw = String(text == null ? "" : text).trim().replace(/,/g, "");
    const match = raw.match(/^([0-9]*\.?[0-9]+)\s*([KMB])?$/i);
    if (!match) {
      const value = Number(raw);
      return value > 0 ? value : 0;
    }
    const scale = { K: 1e3, M: 1e6, B: 1e9 }[(match[2] || "").toUpperCase()] || 1;
    const value = Number(match[1]) * scale;
    return value > 0 ? value : 0;
  }

  function tapeFields(source) {
    const out = {};
    if (!source) return out;
    const bid = Number(source.bid);
    const ask = Number(source.ask);
    if (bid > 0) out.bid = bid;
    if (ask > 0) out.ask = ask;
    if (Number(source.relVol) > 0) out.relVol = Number(source.relVol);
    if (Number(source.tenDayAvg) > 0) out.tenDayAvg = Number(source.tenDayAvg);
    if (Number(source.afterVolume) > 0) out.afterVolume = Number(source.afterVolume);
    return out;
  }

  function attachTape(bars, source) {
    if (!bars) return bars;
    const next = tapeFields(source);
    if (Object.keys(next).length) bars.tape = Object.assign({}, bars.tape || {}, next);
    return bars;
  }

  function cnbcClock(offsetDays) {
    const moment = new Date(Date.now() + offsetDays * 86400000);
    const pad = (value) => String(value).padStart(2, "0");
    return (
      moment.getUTCFullYear() +
      pad(moment.getUTCMonth() + 1) +
      pad(moment.getUTCDate()) +
      pad(moment.getUTCHours()) +
      pad(moment.getUTCMinutes()) +
      "00"
    );
  }

  function barsFromFeed(rows, withTime) {
    const bars = [];
    let previous = "";
    rows.forEach((row) => {
      const stampText = String((row && row.tradeTime) || "");
      if (stampText.length < 12) return;
      const open = Number(row.open);
      const high = Number(row.high);
      const low = Number(row.low);
      const close = Number(row.close);
      if (!(open > 0 && high > 0 && low > 0 && close > 0)) return;
      const date =
        stampText.slice(0, 4) +
        "-" +
        stampText.slice(4, 6) +
        "-" +
        stampText.slice(6, 8) +
        (withTime ? " " + stampText.slice(8, 10) + ":" + stampText.slice(10, 12) : "");
      if (date <= previous) return;
      bars.push({
        date,
        open,
        high: Math.max(high, open, close),
        low: Math.min(low, open, close),
        close,
        volume: Math.max(0, Number(row.volume) || 0),
      });
      previous = date;
    });
    if (!bars.length) throw new Error("No live quote");
    const last = rows[rows.length - 1];
    const mills = Number(last && last.tradeTimeinMills);
    bars.asOf = mills > 0 ? Math.floor(mills / 1000) : Math.floor(Date.now() / 1000);
    return bars;
  }

  async function cnbcQuote(symbol, category) {
    const feed = feedSymbol(symbol, category);
    const payload = await pull(
      "https://quote.cnbc.com/quote-html-webservice/restQuote/symbolType/symbol?symbols=" +
        encodeURIComponent(feed) +
        "&requestMethod=itv&noform=1&partnerId=2&fund=1&exthrs=1&output=json"
    );
    const rows = payload && payload.FormattedQuoteResult && payload.FormattedQuoteResult.FormattedQuote;
    const quote = Array.isArray(rows) ? rows[0] : rows;
    if (!quote || Number(quote.code) !== 0) throw new Error("No live quote for " + symbol);
    return quote;
  }

  function sessionFromQuote(quote) {
    const open = priceOf(quote.open);
    const high = priceOf(quote.high);
    const low = priceOf(quote.low);
    const close = priceOf(quote.last);
    const when = quote.last_time ? Date.parse(quote.last_time) : 0;
    const date = quote.last_time ? String(quote.last_time).slice(0, 10) : "";
    const ext = quote.ExtendedMktQuote || {};
    const extPrice = priceOf(ext.last);
    const extWhen = ext.last_time ? Date.parse(ext.last_time) : 0;
    let live = close;
    let asOf = when > 0 ? Math.floor(when / 1000) : 0;
    if (extPrice > 0 && extWhen > when) {
      live = extPrice;
      asOf = Math.floor(extWhen / 1000);
    }
    const rel = Number(quote.pcttendayvol);
    return {
      open,
      high,
      low,
      close,
      live,
      date,
      asOf,
      volume: volumeOf(quote.volume),
      tape: tapeFields({
        relVol: rel > 0 ? rel : 0,
        tenDayAvg: volumeOf(quote.tendayavgvol),
        afterVolume: volumeOf(ext.volume),
      }),
    };
  }

  function paintSession(bars, session) {
    if (!session || !(session.live > 0) || !session.date) return bars;
    const live = session.live;
    const ready = session.open > 0 && session.high > 0 && session.low > 0;
    const bar = {
      date: session.date,
      open: ready ? session.open : live,
      high: Math.max(ready ? session.high : live, ready ? session.open : live, live),
      low: Math.min(ready ? session.low : live, ready ? session.open : live, live),
      close: live,
      volume: session.volume || 0,
    };
    if (!bars.length || bars[bars.length - 1].date < session.date) {
      if (!ready) return bars;
      bars.push(bar);
    } else if (bars[bars.length - 1].date === session.date && ready) {
      bars[bars.length - 1] = bar;
    } else if (bars[bars.length - 1].date === session.date) {
      applyTrade(bars, { price: live, asOf: session.asOf });
    }
    if (session.asOf > 0) bars.asOf = session.asOf;
    attachTape(bars, session.tape);
    return bars;
  }

  async function cnbcBars(symbol, category, interval, days, withTime) {
    const feed = feedSymbol(symbol, category);
    const payload = await pull(
      "https://ts-api.cnbc.com/harmony/app/bars/" +
        encodeURIComponent(feed) +
        "/" +
        interval +
        "/" +
        cnbcClock(-days) +
        "/" +
        cnbcClock(2) +
        "/adjusted/EST5EDT.json"
    );
    const rows = payload && payload.barData && payload.barData.priceBars;
    if (!Array.isArray(rows) || !rows.length) throw new Error("No live quote for " + symbol);
    return barsFromFeed(rows, withTime);
  }

  async function cnbcLast(symbol, category) {
    const session = sessionFromQuote(await cnbcQuote(symbol, category));
    if (!(session.live > 0)) throw new Error("No live quote for " + symbol);
    return Object.assign(
      {
        price: session.live,
        asOf: session.asOf,
        date: session.date,
        open: session.open,
        high: session.high,
        low: session.low,
        volume: session.volume,
      },
      session.tape || {}
    );
  }

  async function cnbcDaily(symbol, category) {
    const bars = await cnbcBars(symbol, category, "1D", 800, false);
    try {
      return paintSession(bars, sessionFromQuote(await cnbcQuote(symbol, category)));
    } catch (error) {
      return bars;
    }
  }

  async function withLastTrade(symbol, bars) {
    try {
      const trade = await lastTrade(symbol);
      applyTrade(bars, trade);
      return attachTape(bars, trade);
    } catch (error) {
      return bars;
    }
  }

  async function daily(symbol, category) {
    if (!crypto(symbol, category)) return cnbcDaily(symbol, category);
    return withLastTrade(symbol, await candles(symbol, "1D", false));
  }

  async function frame(symbol, category, frameId) {
    if (!crypto(symbol, category)) {
      if (frameId === "1W" || frameId === "1M" || frameId === "1Q") {
        const dailyBars = await cnbcDaily(symbol, category);
        return root.PaperFrames.barsFor(dailyBars, root.PaperFrames.frameById(frameId));
      }
      if (frameId === "1D") return cnbcDaily(symbol, category);
      const scale = { "1m": ["1M", 5, 1], "5m": ["5M", 30, 1], "15m": ["5M", 30, 3], "30m": ["30M", 60, 1], "1h": ["1H", 120, 1], "4h": ["1H", 180, 4] };
      const plan = scale[frameId];
      if (!plan) throw new Error("Unknown timeframe");
      const bars = await cnbcBars(symbol, category, plan[0], plan[1], true);
      return plan[2] > 1 ? group(bars, plan[2]) : bars;
    }
    if (frameId === "1W" || frameId === "1M" || frameId === "1Q") {
      const dailyBars = await withLastTrade(symbol, await candles(symbol, "1D", false));
      return root.PaperFrames.barsFor(dailyBars, root.PaperFrames.frameById(frameId));
    }
    if (!{ "1m": 1, "5m": 1, "15m": 1, "30m": 1, "1h": 1, "4h": 1, "1D": 1 }[frameId]) throw new Error("Unknown timeframe");
    return withLastTrade(symbol, await candles(symbol, frameId, frameId !== "1D"));
  }

  root.PaperLive = { daily, frame, lastTrade };
})(typeof globalThis !== "undefined" ? globalThis : this);
