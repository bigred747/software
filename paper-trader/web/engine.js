/* Paper session for this page. Quotes are supplied by the caller. No broker orders. */
(function (root) {
  const ANCHORS = {
    "BTC-USD": [42000, 2], "ETH-USD": [2300, 2], "XRP-USD": [0.58, 4], "XLM-USD": [0.11, 4],
    "SOL-USD": [145, 2], "ADA-USD": [0.45, 4], "DOGE-USD": [0.12, 5], "BNB-USD": [580, 2],
    "AVAX-USD": [35, 2], "LINK-USD": [14, 2], "LTC-USD": [75, 2], "BCH-USD": [340, 2],
    ES: [5200, 2], NQ: [18500, 2], YM: [40000, 2], RTY: [2100, 2], CL: [78, 2], GC: [2350, 2],
    SI: [28, 2], SPY: [450, 2], QQQ: [430, 2], AAPL: [175, 2],
  };

  function crc32(text) {
    let crc = 0xffffffff;
    for (let index = 0; index < text.length; index += 1) {
      crc ^= text.charCodeAt(index);
      for (let bit = 0; bit < 8; bit += 1) crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
    }
    return (crc ^ 0xffffffff) >>> 0;
  }

  function profile(symbol, category) {
    if (ANCHORS[symbol]) return { start: ANCHORS[symbol][0], decimals: ANCHORS[symbol][1] };
    const seed = crc32(symbol);
    if (category === "crypto") {
      const start = Math.round((0.02 + (seed % 8000) / 100) * 10000) / 10000;
      return { start, decimals: start < 2 ? 4 : 2 };
    }
    if (category === "future") return { start: 15 + (seed % 8000), decimals: 2 };
    return { start: 6 + (seed % 420), decimals: 2 };
  }

  function parseCatalog(text) {
    return String(text || "").split(/\n/).filter(Boolean).map((line) => {
      const [symbol, name, category] = line.split("\t");
      const priced = profile(symbol, category);
      return { symbol, name, category, start: priced.start, decimals: priced.decimals };
    });
  }

  function search(rows, query, category, limit) {
    const size = Math.max(1, Math.min(Number(limit) || 40, 80));
    const kind = String(category || "").trim().toLowerCase();
    const needle = String(query || "").trim().toLowerCase();
    let pool = rows;
    if (["crypto", "stock", "etf", "future"].includes(kind)) pool = rows.filter((row) => row.category === kind);
    let found;
    let matched;
    if (needle) {
      const ranked = [];
      pool.forEach((row) => {
        const symbol = row.symbol.toLowerCase();
        const name = row.name.toLowerCase();
        let rank = -1;
        if (symbol === needle || name === needle) rank = 0;
        else if (symbol.startsWith(needle)) rank = 1;
        else if (name.startsWith(needle)) rank = 2;
        else if (symbol.includes(needle)) rank = 3;
        else if (name.includes(needle)) rank = 4;
        if (rank >= 0) ranked.push([rank, symbol, row]);
      });
      ranked.sort((a, b) => a[0] - b[0] || (a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0));
      matched = ranked.length;
      found = ranked.slice(0, size).map((item) => item[2]);
    } else {
      matched = pool.length;
      found = pool.slice(0, size);
    }
    return {
      total: rows.length,
      matched,
      symbols: found.map((row) => ({ symbol: row.symbol, name: row.name, category: row.category, decimals: row.decimals })),
    };
  }

  function buildBars(symbol, row, count) {
    const total = count || 220;
    const start = Number(row.start);
    const floor = Math.max(start * 0.05, 0.00001);
    const seed = crc32(symbol);
    const phase = (seed % 628) / 100;
    const amp = 0.035 + (seed % 35) / 1000;
    const drift = 0.00004 + (seed % 25) / 1000000;
    const direction = Math.floor(seed / 3) % 2 === 0 ? 1 : -1;
    const bars = [];
    let previous = start;
    let made = 0;
    const day = new Date(Date.UTC(2024, 0, 2));
    while (made < total) {
      const weekday = day.getUTCDay();
      if (weekday !== 0 && weekday !== 6) {
        const slot = made % 90;
        const cycle = 1 + amp * Math.sin(made / 8 + phase) + amp * 0.45 * Math.sin(made / 22 + phase / 2);
        const shock = slot >= 55 ? direction * 0.004 * (slot - 54) : 0;
        const close = Math.max(floor, start * (1 + made * drift) * cycle * (1 + shock));
        const open = previous;
        const width = slot >= 55 ? 0.012 : slot >= 40 ? 0.0008 : 0.004;
        const high = Math.max(open, close) * (1 + width);
        const low = Math.max(floor * 0.5, Math.min(open, close) * (1 - width));
        const month = String(day.getUTCMonth() + 1).padStart(2, "0");
        const date = String(day.getUTCDate()).padStart(2, "0");
        bars.push({ date: day.getUTCFullYear() + "-" + month + "-" + date, open, high, low, close, volume: 1000 + made * 10 });
        previous = close;
        made += 1;
      }
      day.setUTCDate(day.getUTCDate() + 1);
    }
    return bars;
  }

  function sma(values, window) {
    const out = new Array(values.length).fill(null);
    let total = 0;
    values.forEach((value, index) => {
      total += value;
      if (index >= window) total -= values[index - window];
      if (index >= window - 1) out[index] = total / window;
    });
    return out;
  }

  function rsi(values, period) {
    const out = new Array(values.length).fill(null);
    if (!period || period < 2 || values.length <= period) return out;
    let gain = 0;
    let loss = 0;
    for (let index = 1; index <= period; index += 1) {
      const change = values[index] - values[index - 1];
      if (change >= 0) gain += change;
      else loss -= change;
    }
    gain /= period;
    loss /= period;
    out[period] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
    for (let index = period + 1; index < values.length; index += 1) {
      const change = values[index] - values[index - 1];
      const up = Math.max(change, 0);
      const down = Math.max(-change, 0);
      gain = (gain * (period - 1) + up) / period;
      loss = (loss * (period - 1) + down) / period;
      out[index] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
    }
    return out;
  }

  function market(session) {
    const bars = session.bars;
    const closes = bars.map((bar) => bar.close);
    const highs = bars.map((bar) => bar.high);
    const lows = bars.map((bar) => bar.low);
    const length = 20;
    const basis = sma(closes, length);
    const level = new Array(bars.length).fill(0);
    const momentum = new Array(bars.length).fill(null);
    for (let index = length - 1; index < bars.length; index += 1) {
      const chunk = closes.slice(index - length + 1, index + 1);
      const mean = basis[index];
      const variance = chunk.reduce((sum, value) => sum + (value - mean) ** 2, 0) / length;
      const deviation = Math.sqrt(variance);
      let atr = 0;
      for (let cursor = index - length + 1; cursor <= index; cursor += 1) {
        const prev = cursor === 0 ? closes[cursor] : closes[cursor - 1];
        atr += Math.max(highs[cursor] - lows[cursor], Math.abs(highs[cursor] - prev), Math.abs(lows[cursor] - prev));
      }
      atr /= length;
      const upper = mean + 2 * deviation;
      const lower = mean - 2 * deviation;
      let squeezeLevel = 0;
      [[2, 1], [1.5, 2], [1, 3]].forEach(([multiple, code]) => {
        if (upper < mean + multiple * atr && lower > mean - multiple * atr) squeezeLevel = code;
      });
      level[index] = squeezeLevel;
      const highest = Math.max(...highs.slice(index - length + 1, index + 1));
      const lowest = Math.min(...lows.slice(index - length + 1, index + 1));
      momentum[index] = closes[index] - (((highest + lowest) / 2 + mean) / 2);
    }
    return {
      symbol: session.symbol,
      note: "Market quote. Paper orders stay on this device.",
      bars,
      fast: sma(closes, session.params.fast),
      slow: sma(closes, session.params.slow),
      rsi: rsi(closes, session.params.rsi_period),
      squeeze: level,
      momentum,
      params: session.params,
    };
  }

  function blank(symbol, row, params) {
    const settings = {
      fast: Number(params.fast) || 10,
      slow: Number(params.slow) || 30,
      rsi_period: Number(params.rsi_period ?? params.rsi ?? 14),
      cash: Number(params.cash) || 10000,
      fee_bps: Number(params.fee_bps) || 0,
      stop_pct: Number(params.stop_pct) || 0,
    };
    const bars = buildBars(symbol, row, 220);
    return {
      symbol,
      row,
      params: settings,
      bars,
      cursor: Math.min(bars.length, settings.slow + 1),
      cash: settings.cash,
      shares: 0,
      entryPrice: null,
      entryDate: null,
      cost: 0,
      trades: [],
      lastAction: "",
      working: [],
      nextId: 1,
      fills: [],
    };
  }

  function view(session) {
    const bar = session.bars[Math.max(0, session.cursor - 1)];
    const price = bar ? bar.close : 0;
    const equity = session.cash + session.shares * price;
    const markers = [];
    session.trades.forEach((trade) => {
      markers.push({ date: trade.entry_date, price: trade.entry_price, side: "buy" });
      markers.push({ date: trade.exit_date, price: trade.exit_price, side: "sell" });
    });
    if (session.shares > 0 && session.entryDate) markers.push({ date: session.entryDate, price: session.entryPrice, side: "buy" });
    return {
      symbol: session.symbol,
      cursor: session.cursor,
      total_bars: session.bars.length,
      cash: session.cash,
      shares: session.shares,
      entry_price: session.entryPrice,
      last_close: price,
      equity,
      signal: session.shares > 0 ? "HOLD LONG" : "HOLD",
      last_date: bar ? bar.date : null,
      trades: session.trades.map((trade) => ({ ...trade })),
      markers,
      params: { ...session.params },
      finished: session.cursor >= session.bars.length,
      mode: "paper",
      paper: true,
      last_action: session.lastAction,
      fill: session.lastFill || null,
      working: (session.working || []).map((ticket) => ({ ...ticket })),
      ticket_fills: (session.ticketFills || []).map((item) => ({ ...item })),
      fills: (session.fills || []).map((item) => ({ ...item })),
      live: false,
    };
  }

  const KINDS = ["market", "limit", "stop", "stop_limit"];

  function makeTicket(raw) {
    const side = String(raw.side || "").trim().toLowerCase();
    const kind = String(raw.kind || "market").trim().toLowerCase().replace(/[\s-]+/g, "_");
    const tif = String(raw.tif || "DAY").trim().toUpperCase();
    if (side !== "buy" && side !== "sell") throw new Error("Side must be buy or sell");
    if (!KINDS.includes(kind)) throw new Error("Order type must be market, limit, stop, or stop limit");
    if (tif !== "DAY" && tif !== "GTC") throw new Error("Time in force must be DAY or GTC");
    const limit = Number(raw.limit || 0);
    const stop = Number(raw.stop || 0);
    if (!Number.isFinite(limit) || !Number.isFinite(stop) || limit < 0 || stop < 0) throw new Error("Prices must be positive numbers");
    if ((kind === "limit" || kind === "stop_limit") && !(limit > 0)) throw new Error("A limit price is required");
    if ((kind === "stop" || kind === "stop_limit") && !(stop > 0)) throw new Error("A stop price is required");
    return {
      side,
      kind,
      tif,
      limit: kind === "limit" || kind === "stop_limit" ? limit : 0,
      stop: kind === "stop" || kind === "stop_limit" ? stop : 0,
      triggered: false,
    };
  }

  function decide(ticket, last, marketPrice) {
    const buy = ticket.side === "buy";
    if (ticket.kind === "market") return { fills: true, price: marketPrice, triggered: false };
    const limitPrice = buy ? Math.min(last, ticket.limit) : Math.max(last, ticket.limit);
    if (ticket.kind === "limit") {
      return { fills: buy ? last <= ticket.limit : last >= ticket.limit, price: limitPrice, triggered: false };
    }
    const stopHit = buy ? last >= ticket.stop : last <= ticket.stop;
    if (ticket.kind === "stop") return { fills: stopHit, price: marketPrice, triggered: stopHit };
    const triggered = Boolean(ticket.triggered) || stopHit;
    if (!triggered) return { fills: false, price: 0, triggered: false };
    return { fills: buy ? last <= ticket.limit : last >= ticket.limit, price: limitPrice, triggered: true };
  }

  function lastTrade(session) {
    const bar = session.bars[Math.max(0, session.cursor - 1)];
    return bar ? bar.close : 0;
  }

  function slipPrice(last, side, slipBps) {
    const slip = (Number(slipBps) || 0) / 10000;
    return side === "buy" ? last * (1 + slip) : last * (1 - slip);
  }

  function place(session, raw, slipBps, day) {
    const ticket = makeTicket(raw);
    const last = lastTrade(session);
    if (!(last > 0)) throw new Error("No quote yet. Load a market first.");
    if (ticket.side === "buy" && session.shares > 0) throw new Error("A paper long is already open. Sell it before buying again.");
    if (ticket.side === "sell" && !(session.shares > 0)) throw new Error("Nothing to sell. The paper account is flat.");
    if (ticket.kind === "stop" || ticket.kind === "stop_limit") {
      if (ticket.side === "buy" && ticket.stop <= last) throw new Error("A buy stop must sit above the last price.");
      if (ticket.side === "sell" && ticket.stop >= last) throw new Error("A sell stop must sit below the last price.");
    }
    ticket.id = String(session.nextId || 1);
    ticket.day = day || "";
    const verdict = decide(ticket, last, slipPrice(last, ticket.side, slipBps));
    ticket.triggered = verdict.triggered;
    session.nextId = (session.nextId || 1) + 1;
    if (verdict.fills) {
      return fill(session, ticket.side, slipBps, verdict.price, raw.source === "auto" ? "auto" : "ticket");
    }
    session.working = (session.working || []).concat([ticket]);
    session.lastAction = "working";
    return view(session);
  }

  function work(session, slipBps, day, buysStopped) {
    if (!session.working || !session.working.length) return false;
    const last = lastTrade(session);
    let changed = false;
    const keep = [];
    session.working.forEach((ticket) => {
      if (ticket.tif === "DAY" && ticket.day && day && ticket.day !== day) {
        changed = true;
        return;
      }
      if (ticket.side === "buy" && buysStopped) {
        changed = true;
        return;
      }
      if ((ticket.side === "buy" && session.shares > 0) || (ticket.side === "sell" && !(session.shares > 0))) {
        keep.push(ticket);
        return;
      }
      const verdict = decide(ticket, last, slipPrice(last, ticket.side, slipBps));
      if (verdict.triggered && !ticket.triggered) {
        ticket.triggered = true;
        changed = true;
      }
      if (verdict.fills) {
        fill(session, ticket.side, slipBps, verdict.price, "working order");
        session.ticketFills = (session.ticketFills || []).concat([{ ...session.lastFill, order_id: ticket.id, kind: ticket.kind }]).slice(-12);
        changed = true;
        return;
      }
      keep.push(ticket);
    });
    session.working = keep;
    return changed;
  }

  function cancel(session, id) {
    const before = (session.working || []).length;
    session.working = id === "all" ? [] : (session.working || []).filter((ticket) => ticket.id !== String(id));
    if (id !== "all" && session.working.length === before) throw new Error("That working order was not found");
    return view(session);
  }

  function logFill(session, side, qty, price, bar, pnl, reason) {
    session.fills = (session.fills || []).concat([{
      at: Math.floor(Date.now() / 1000),
      symbol: session.symbol,
      side,
      qty,
      price,
      quote: bar.close,
      fee_bps: session.params.fee_bps,
      pnl,
      reason: reason || "trade",
    }]).slice(-500);
  }

  function snapshot(session) {
    return {
      symbol: session.symbol,
      params: { ...session.params },
      cash: session.cash,
      shares: session.shares,
      entryPrice: session.entryPrice,
      entryDate: session.entryDate,
      cost: session.cost,
      trades: session.trades.map((trade) => ({ ...trade })),
      working: (session.working || []).map((ticket) => ({ ...ticket })),
      nextId: session.nextId || 1,
      fills: (session.fills || []).map((item) => ({ ...item })),
      ticketFills: (session.ticketFills || []).map((item) => ({ ...item })),
      lastAction: session.lastAction || "",
      last: session.bars && session.bars.length ? session.bars[session.bars.length - 1].close : null,
    };
  }

  function restore(session, saved) {
    if (!saved || saved.symbol !== session.symbol) return false;
    const number = (value, fallback) => (Number.isFinite(Number(value)) ? Number(value) : fallback);
    const cash = number(saved.cash, NaN);
    const shares = number(saved.shares, NaN);
    if (!(cash >= 0) || !(shares >= 0)) return false;
    session.params = { ...session.params, ...(saved.params || {}) };
    session.cash = cash;
    session.shares = shares;
    session.entryPrice = saved.entryPrice == null ? null : number(saved.entryPrice, null);
    session.entryDate = saved.entryDate || null;
    session.cost = number(saved.cost, 0);
    session.trades = Array.isArray(saved.trades) ? saved.trades.map((trade) => ({ ...trade })) : [];
    session.working = Array.isArray(saved.working) ? saved.working.map((ticket) => ({ ...ticket })) : [];
    session.nextId = Math.max(1, number(saved.nextId, 1));
    session.fills = Array.isArray(saved.fills) ? saved.fills.map((item) => ({ ...item })) : [];
    session.ticketFills = Array.isArray(saved.ticketFills) ? saved.ticketFills.map((item) => ({ ...item })) : [];
    session.lastAction = saved.lastAction || "";
    return true;
  }

  function fill(session, side, slipBps, exactPrice, reason) {
    const bar = session.bars[Math.max(0, session.cursor - 1)];
    if (!bar) return view(session);
    const fee = session.params.fee_bps / 10000;
    const slip = (Number(slipBps) || 0) / 10000;
    const price = exactPrice > 0 ? exactPrice : side === "buy" ? bar.close * (1 + slip) : bar.close * (1 - slip);
    if (side === "buy" && session.shares <= 0) {
      const shares = session.cash / (price * (1 + fee));
      const notional = shares * price;
      const cost = notional * (1 + fee);
      session.cash -= cost;
      session.shares = shares;
      session.cost = cost;
      session.entryPrice = price;
      session.entryDate = bar.date;
      session.lastAction = "buy";
      session.lastFill = { side, quote: bar.close, price, slip_bps: Number(slipBps) || 0, fee_bps: session.params.fee_bps, at: Math.floor(Date.now() / 1000) };
      logFill(session, "buy", shares, price, bar, 0, reason);
    } else if (side === "sell" && session.shares > 0) {
      const notional = session.shares * price;
      const proceeds = notional * (1 - fee);
      session.trades.push({
        entry_date: session.entryDate,
        exit_date: bar.date,
        entry_price: session.entryPrice,
        exit_price: price,
        shares: session.shares,
        pnl: proceeds - session.cost,
        reason: "trade",
      });
      session.cash += proceeds;
      session.shares = 0;
      session.entryPrice = null;
      session.entryDate = null;
      session.cost = 0;
      session.lastAction = "sell";
      session.lastFill = { side, quote: bar.close, price, slip_bps: Number(slipBps) || 0, fee_bps: session.params.fee_bps, at: Math.floor(Date.now() / 1000) };
      const closed = session.trades[session.trades.length - 1];
      logFill(session, "sell", closed.shares, price, bar, closed.pnl, reason);
    }
    return view(session);
  }

  root.PaperEngine = {
    parseCatalog,
    search,
    buildBars,
    open(rows, symbol, params) {
      const row = rows.find((item) => item.symbol === symbol) || rows[0];
      return blank(row.symbol, row, params || {});
    },
    view,
    market,
    step(session, days) {
      session.cursor = Math.min(session.bars.length, session.cursor + Math.max(1, days || 1));
      return view(session);
    },
    run(session) {
      session.cursor = session.bars.length;
      return view(session);
    },
    fill,
    snapshot,
    restore,
    place,
    work,
    cancel,
    makeTicket,
    decide,
    trade(session, slipBps, reason) {
      return fill(session, session.shares > 0 ? "sell" : "buy", slipBps, 0, reason || "trade");
    },
    sessionFromBars(symbol, bars, params) {
      const settings = {
        fast: Number((params || {}).fast) || 10,
        slow: Number((params || {}).slow) || 30,
        rsi_period: Number((params || {}).rsi_period ?? (params || {}).rsi ?? 14),
        cash: Number((params || {}).cash) || 10000,
        fee_bps: Number((params || {}).fee_bps) || 0,
        stop_pct: Number((params || {}).stop_pct) || 0,
      };
      const last = bars.length ? bars[bars.length - 1].close : 1;
      return {
        symbol,
        row: { symbol, name: symbol, category: "crypto", decimals: last < 2 ? 4 : 2, start: bars[0] ? bars[0].open : 1 },
        params: settings,
        bars,
        cursor: bars.length,
        cash: settings.cash,
        shares: 0,
        entryPrice: null,
        entryDate: null,
        cost: 0,
        trades: [],
        lastAction: "",
        working: [],
        nextId: 1,
        fills: [],
      };
    },
    replaceBars(session, bars) {
      session.bars = bars;
      session.cursor = bars.length;
      return view(session);
    },
    reset(rows, params) {
      return this.open(rows, params.symbol, {
        fast: params.fast,
        slow: params.slow,
        rsi_period: params.rsi,
        cash: params.cash,
        fee_bps: params.fee_bps,
        stop_pct: params.stop_pct,
      });
    },
  };
})(typeof globalThis !== "undefined" ? globalThis : this);
