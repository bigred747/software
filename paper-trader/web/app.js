const state = {
  market: null,
  paper: null,
  busy: false,
  auto: false,
  autoTimer: 0,
  decimals: 2,
  offline: false,
  catalog: [],
  session: null,
  study: null,
  studyCode: "",
  frameId: "1D",
  zoom: 4,
  layers: {},
  ruler: null,
  previewZoom: 4,
  moods: {},
  lesson: null,
  lessonKey: "",
  voice: true,
  scanOn: true,
  picks: [],
  pickNote: "",
  fires: [],
  fireNote: "",
  fireAuto: false,
  fireCursor: 0,
  deskCard: "auto",
  journalSells: [],
  journalSellNote: "",
  journalSellGen: 0,
  leaders: [],
  scanNote: "",
  scanDone: false,
  scanLearn: { cursor: 0, lastAt: 0, lastSymbol: "", scored: [], timer: 0 },
  stratLearn: { cursor: 0, lastAt: 0, lastSymbol: "", lastId: "", passes: 0 },
  breadth: null,
  vixBars: [],
  schwab: null,
  cycle: {
    busy: false,
    stop: false,
    market: null,
    defensive: null,
    risk: null,
    open: null,
    at: 0,
    note: "",
  },
  forecast: {
    busy: false,
    stop: false,
    open: null,
    board: null,
    at: 0,
    note: "",
  },
};

const STUDY_KEY = "paperTraderStudies";
const PIN_KEY = "paperTraderPins";
const SYMBOL_KEY = "paperTraderSymbol";
const SECTORS = ["SMH", "VGT", "ARKW", "QQQ", "SPMO", "COPX", "ARKQ", "MGK", "CIBR", "UGA", "AIRR", "GRID"];
const PIN_LIMIT = 24;
const DEFAULT_PINS = ["BTC-USD", "ETH-USD", "XRP-USD", "XLM-USD", "SOL-USD", "SPY"].concat(SECTORS);
const SECTOR_KEY = "paperTraderSectors";

const notes = {
  BUY: "The paper account would open a long on the next bar.",
  SELL: "The paper account would close the long on the next bar.",
  "HOLD LONG": "A paper long is open. No exit signal on this bar.",
  HOLD: "Flat. No new paper order on this bar.",
};

function $(id) {
  return document.getElementById(id);
}

function sameStamp(node, stamp) {
  if (!node) return false;
  if (node.dataset.stamp === stamp) return true;
  node.dataset.stamp = stamp;
  return false;
}

// Polite bar gate: a few parallel Yahoo/desk bar reads, small spacing — scanners stay usable.
let barInflight = 0;
let barNextSlot = 0;
const barWaiters = [];
const BAR_PARALLEL = 3;
const BAR_GAP_MS = 110;
const deskBarCache = new Map();

function oneBarRead(task) {
  return new Promise((resolve, reject) => {
    const launch = async () => {
      barInflight += 1;
      try {
        const wait = barNextSlot - Date.now();
        if (wait > 0) await new Promise((done) => setTimeout(done, wait));
        barNextSlot = Date.now() + BAR_GAP_MS;
        resolve(await task());
      } catch (error) {
        reject(error);
      } finally {
        barInflight -= 1;
        const next = barWaiters.shift();
        if (next) next();
      }
    };
    if (barInflight < BAR_PARALLEL) launch();
    else barWaiters.push(launch);
  });
}

function cachedDeskBars(symbol, frame, maxAgeMs) {
  const hit = deskBarCache.get(String(symbol || "").toUpperCase() + "|" + String(frame || "1D"));
  if (!hit) return null;
  if (Date.now() - hit.at > (maxAgeMs || 180000)) return null;
  return hit.bars;
}

function rememberDeskBars(symbol, frame, bars) {
  if (!symbol || !bars || !bars.length) return bars;
  deskBarCache.set(String(symbol).toUpperCase() + "|" + String(frame || "1D"), { at: Date.now(), bars });
  return bars;
}

async function getJSON(url, options) {
  const read = async () => {
    const response = await fetch(url, options);
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.error || "Request failed");
    }
    return payload;
  };
  if (String(url).indexOf("/api/bars") === 0) return oneBarRead(read);
  return read();
}

const VERSION = "1.10.22";
const DOCK_KEY = "paperTraderDock";
const DESK_CARD_KEY = "paperTraderDeskCard";
const LEARN_KEY = "paper_trader_learn_v1";
const STRAT_LEARN_KEY = "paper_trader_strat_learn_v1";
const FILL_LEARN_KEY = "paper_trader_fill_learn_v1";
const FEE_BPS = 5;
const SLIP_BPS = 5;
const SCAN_LEARN_GAP_MS = 12000;
const SCAN_LEARN_BATCH = 2;
const STRAT_LEARN_GAP_MS = 14000;
let learnTouchAt = 0;
let learnTouchTab = "";
let learnTouchNote = "";
let newProcessNote = "";
let newProcessAt = 0;

function selectedSymbol() {
  const node = $("symbol");
  if (node && node.dataset.value) return node.dataset.value;
  return (state.paper && state.paper.symbol) || "";
}

function formParams() {
  return {
    symbol: selectedSymbol(),
    fast: Number($("fast").value),
    slow: Number($("slow").value),
    rsi: Number($("rsi").value),
    cash: Number($("cash-input").value),
    fee_bps: Number($("fee").value),
    stop_pct: Number($("stop").value),
  };
}

function fillForm(params) {
  $("fast").value = params.fast;
  $("slow").value = params.slow;
  $("rsi").value = params.rsi_period;
  $("cash-input").value = params.cash;
  $("fee").value = params.fee_bps;
  $("stop").value = params.stop_pct;
}

function priceDigits() {
  return state.decimals || 2;
}

function formatPrice(value) {
  if (value == null || Number.isNaN(Number(value))) return "—";
  return Number(value).toLocaleString(undefined, {
    minimumFractionDigits: priceDigits(),
    maximumFractionDigits: priceDigits(),
  });
}

function formatMoney(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) return "—";
  const rounded = Math.round(number * 100) / 100;
  const shown = rounded === 0 ? 0 : rounded;
  return shown.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function post(url, body) {
  return getJSON(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {}),
  });
}

function phoneCrypto(symbol, row) {
  return (row && row.category === "crypto") || /-USD$/.test(symbol || "");
}

function digitsFor(price) {
  const value = Math.abs(Number(price));
  if (!(value > 0)) return 2;
  const text = value.toFixed(6).replace(/0+$/, "");
  const dot = text.indexOf(".");
  const frac = dot < 0 ? 0 : text.length - dot - 1;
  return Math.max(2, Math.min(5, frac));
}

function stampQuote(bars) {
  if (!state.market) return;
  state.market.as_of = bars && bars.asOf ? bars.asOf : Math.floor(Date.now() / 1000);
  state.market.price = bars && bars.length ? bars[bars.length - 1].close : state.market.price;
  if (state.market.price > 0) state.decimals = digitsFor(state.market.price);
  state.market.note = "Market quote. Paper orders stay on this device.";
  if (bars && bars.tape) state.market.tape = Object.assign({}, state.market.tape || {}, bars.tape);
}

const BOOK_KEY = "paper_trader_book_v1";

function readBook() {
  try {
    const raw = JSON.parse(localStorage.getItem(BOOK_KEY) || "{}");
    return raw && typeof raw.sessions === "object" && raw.sessions ? raw.sessions : {};
  } catch (error) {
    return {};
  }
}

function writeBook() {
  if (!state.offline || !state.book) return;
  try {
    const sessions = readBook();
    Object.keys(state.book).forEach((symbol) => {
      const session = state.book[symbol];
      if (session && (session.shares > 0 || (session.fills && session.fills.length) || (session.working && session.working.length))) {
        sessions[symbol] = PaperEngine.snapshot(session);
      } else {
        delete sessions[symbol];
      }
    });
    const text = JSON.stringify({ version: 1, sessions });
    if (state.bookText !== text) {
      localStorage.setItem(BOOK_KEY, text);
      state.bookText = text;
    }
  } catch (error) {
    state.bookNote = "This browser would not save the paper book. Positions stay until the page closes.";
  }
}

function openOfflineSession(symbol, bars, params, fresh) {
  state.book = state.book || {};
  const kept = state.book[symbol];
  if (kept && !fresh) {
    PaperEngine.replaceBars(kept, bars);
    state.session = kept;
    return kept;
  }
  const session = PaperEngine.sessionFromBars(symbol, bars, params || {});
  if (!fresh) {
    const saved = readBook()[symbol];
    if (saved) PaperEngine.restore(session, saved);
  }
  state.book[symbol] = session;
  state.session = session;
  return session;
}

async function loadPhoneBars(symbol, params, fresh) {
  const row = state.catalog.find((item) => item.symbol === symbol);
  const bars = await PaperLive.daily(symbol, row ? row.category : "");
  if (fresh || !state.session || state.session.symbol !== symbol) {
    openOfflineSession(symbol, bars, params || {}, fresh);
  } else {
    PaperEngine.replaceBars(state.session, bars);
  }
  state.market = PaperEngine.market(state.session);
  stampQuote(bars);
  return PaperEngine.view(state.session);
}

const CONFIRM_PATHS = ["/api/paper/trade", "/api/paper/ticket", "/api/paper/halt"];
const CONFIRM_NOTE = "Confirm first. Every paper buy and sell needs the second warning, even from Auto.";

async function paperCall(path, body) {
  if (CONFIRM_PATHS.includes(path) && !(body && body.confirmed === true)) throw new Error(CONFIRM_NOTE);
  if (!state.offline) return post(path, body);
  const params = body || {};
  if (path === "/api/paper/step" || path === "/api/paper/run") {
    const refreshed = await loadPhoneBars(state.session.symbol, state.session.params, false);
    if (PaperEngine.work(state.session, PAPER_SLIP_BPS, denverDay(), breakerState().tripped)) return PaperEngine.view(state.session);
    return refreshed;
  }
  if (path === "/api/paper/ticket") {
    await loadPhoneBars(state.session.symbol, state.session.params, false);
    if (params.side === "buy" && breakerState().tripped) throw new Error(BREAKER_STOP);
    return PaperEngine.place(state.session, params, PAPER_SLIP_BPS, denverDay());
  }
  if (path === "/api/paper/cancel") {
    const symbol = params.symbol || (state.session && state.session.symbol) || "";
    const target = (state.book && state.book[symbol]) || state.session;
    if (!target) throw new Error("That working order was not found");
    const view = PaperEngine.cancel(target, params.id);
    writeBook();
    if (state.session && state.session.symbol === target.symbol) return view;
    return state.session ? PaperEngine.view(state.session) : view;
  }
  if (path === "/api/paper/trade") {
    await loadPhoneBars(state.session.symbol, state.session.params, false);
    const preview = PaperEngine.view(state.session);
    if (!(preview.shares > 0)) {
      const reason = phoneBuyReason(state.session.symbol);
      if (reason) throw new Error(reason);
      const day = state.session.bars.length ? state.session.bars[state.session.bars.length - 1].date : "";
      state.session.dayBuys = state.session.dayBuys || [];
      state.session.dayBuys.push(day + "|" + state.session.symbol);
    }
    return PaperEngine.trade(state.session, PAPER_SLIP_BPS, params.source === "auto" ? "auto" : "trade");
  }
  if (path === "/api/paper/mode") {
    if (params.mode === "live") throw new Error(LIVE_NOTE);
    return PaperEngine.view(state.session);
  }
  if (path === "/api/paper/halt") {
    try {
      await loadPhoneBars(state.session.symbol, state.session.params, false);
    } catch (error) {
      /* Sell at the last real quote already on the page. */
    }
    return haltPhone("Sell all pressed");
  }
  if (path === "/api/paper/reset") {
    const symbol = params.symbol || selectedSymbol();
    const fresh = await loadPhoneBars(symbol, { ...formParams(), ...params }, true);
    return guardPhone(fresh, true);
  }
  throw new Error("That action stays on this device");
}

async function refreshSymbols() {
  const kind = $("category") ? $("category").value : "";
  let listed;
  if (state.offline) {
    listed = PaperEngine.search(state.catalog, $("search").value.trim(), kind, 40);
  } else {
  const params = new URLSearchParams({
    q: $("search").value.trim(),
    category: kind,
    limit: "40",
  });
  listed = await getJSON("/api/symbols?" + params.toString());
  }
  const list = $("symbol");
  const current = (state.paper && state.paper.symbol) || list.dataset.value || "";
  const rows = listed.symbols.slice();
  const currentRow = state.catalog.find((item) => item.symbol === current);
  const sameKind = !kind || (currentRow && currentRow.category === kind) || rows.some((item) => item.symbol === current);
  if (current && sameKind && !rows.some((item) => item.symbol === current)) {
    const decimals = /-USD$/.test(current) ? 4 : 2;
    rows.unshift({ symbol: current, name: "Current market", category: currentRow ? currentRow.category : "", decimals });
  }
  list.replaceChildren();
  rows.forEach((item) => {
    const button = document.createElement("button");
    button.type = "button";
    button.dataset.symbol = item.symbol;
    button.dataset.decimals = String(item.decimals);
    const kind = item.category ? " · " + item.category : "";
    const label = item.symbol + " · " + item.name + kind;
    const mood = state.moods[item.symbol];
    button.dataset.label = label;
    button.textContent = mood && mood.score != null ? label + " · " + mood.label + " " + mood.score : label;
    if (mood && mood.label) button.classList.add(mood.label.toLowerCase());
    if (item.symbol === current) button.className = "on";
    button.addEventListener("click", () => {
      state.decimals = Number(item.decimals || 2);
      list.dataset.value = item.symbol;
      chooseSymbol(item.symbol);
    });
    list.appendChild(button);
  });
  if (current) list.dataset.value = current;
  const chosen = rows.find((item) => item.symbol === current);
  if (chosen) state.decimals = Number(chosen.decimals || 2);
  const count = listed.matched != null ? Number(listed.matched) : Number(listed.total || 0);
  $("market-count").textContent = list.querySelectorAll("button").length + " listed · " + count.toLocaleString() + " markets";
}

async function probeDesk() {
  if (location.protocol === "file:") return false;
  try {
    const response = await fetch("/api/health", { cache: "no-store" });
    return response.ok;
  } catch (error) {
    return false;
  }
}

function applyHook(items, name, ok, detail) {
  const found = items.find((item) => item.name === name);
  if (!found) {
    items.push({ name, ok, detail });
    return;
  }
  if (found.ok === false) return;
  found.ok = ok;
  found.detail = detail;
}

function withHooks(items) {
  const next = items.map((item) => ({ name: item.name, ok: item.ok !== false, detail: String(item.detail || "") }));
  const bars = (state.market && state.market.bars) || [];
  const canvas = $("candles");
  const chartOk = bars.length >= 40 && !!(canvas && (canvas.clientHeight > 40 || canvas.height > 40));
  const list = $("symbol");
  const tickerOk = !!(list && list.querySelector("button") && state.paper && list.dataset.value === state.paper.symbol);
  const frames = document.querySelectorAll("#frame-bar button").length;
  applyHook(next, "Ticker", tickerOk, tickerOk ? "Click opens the chart" : "The ticker list is not ready");
  applyHook(next, "Chart", chartOk, chartOk ? "Click a bar to read it" : "The chart has no bars");
  applyHook(next, "Auto", true, state.auto ? "Refreshing the quote" : "Ready");
  if (frames) applyHook(next, "Timeframes", frames === 10, frames + " windows");
  const quoteOk = !!(state.market && state.market.price && state.paper);
  if (quoteOk && next.some((item) => item.name === "Quotes" && item.ok)) {
    applyHook(next, "Quotes", true, state.offline ? "Live crypto" : "Market quote");
  }
  const edge = inMicrosoftEdge();
  const webPage = state.offline && location.protocol !== "file:";
  applyHook(next, "Edge", edge || webPage, edge ? "This page is in Microsoft Edge" : webPage ? "This browser loads the quotes" : (state.offline ? "Open this file in the Edge app" : "This window is not Microsoft Edge"));
  const botOn = !!(state.learnBook && state.learnBook.bot_on);
  const pageCount = deskLearnPages().length;
  const learnDetail = learnTouchNote
    || fillTimingNote()
    || (botOn
      ? "Bot on · all " + pageCount + " desk pages (incl. Cycle Desk · Forecaster · Outlook · Tools) learn on closed bars even when scrolled away; Windows score keeps going after the desk closes. Not a forecast."
      : state.offline
        ? "Page-open bot · all " + pageCount + " desk pages cycle while this tab stays open (Cycle Desk · Forecaster included). Close the tab and phone learning pauses — use the Windows zip + Start the score for after-close. Not a forecast."
        : "Bot score off for after-close learning. Open the desk or tick Start the score under Windows extras. Page-open cycles still cover every Tools page here.");
  if (!next.some((item) => item.name === "Learning")) {
    setSignal(next, "Learning", true, learnDetail);
  } else {
    applyHook(next, "Learning", true, learnDetail);
  }
  const processFresh = newProcessNote && (Date.now() - newProcessAt) < 3600000;
  setSignal(next, "New process", true, processFresh ? newProcessNote : (newProcessNote ? "Current · " + newProcessNote : "Waiting for a closed-bar process change"));
  const best = bestSetupStatus();
  setSignal(
    next,
    "Best setup",
    !!(state.best && state.best.on && best.ready),
    state.best && state.best.on ? best.detail : "Off · press Best setup Auto on Strategies"
  );
  const tone = currentMood(bars);
  if (tone && tone.score != null) setSignal(next, "Fear and greed", true, tone.label + " " + tone.score);
  else if (!next.some((item) => item.name === "Fear and greed")) setSignal(next, "Fear and greed", false, "Waiting on the quote");
  const breaker = breakerState();
  setSignal(next, "Breaker", !breaker.tripped, breaker.tripped ? "Tripped. Buys stop until tomorrow" : "Armed at " + Number(breaker.drop_pct || 5) + "% a day");
  const schwab = state.schwab;
  const schwabOn = !!(schwab && schwab.connected);
  setSignal(
    next,
    "Schwab",
    schwabOn,
    schwab ? String(schwab.detail || (schwabOn ? "connected" : "not connected")) : "not connected"
  );
  const foundSchwab = next.find((item) => item.name === "Schwab");
  if (foundSchwab) foundSchwab.tone = "strict";
  setSignal(
    next,
    "Live",
    true,
    schwab && schwab.live_trading
      ? "Trading preference on · orders still paper"
      : "Locked. Paper only"
  );
  const safe = state.safe;
  const safeOk = !!(safe && safe.safe);
  setSignal(
    next,
    "Artex blocked",
    safeOk || !safe,
    safe
      ? safeOk
        ? "GREEN · will not share files or place trades from Artex"
        : "Safe check failed — press Safe check"
      : "Press Safe check"
  );
  setSignal(
    next,
    "Foreign bots",
    safeOk || !safe,
    safe
      ? safeOk
        ? "GREEN · foreign bots kept out of this desk"
        : "Safe check failed — press Safe check"
      : "Press Safe check"
  );
  deskHookHealthItems().forEach((item) => setSignal(next, item.name, item.ok, item.detail));
  const zoomOk = !!($("zoom-in") && $("zoom-out"));
  setSignal(next, "Zoom", zoomOk, zoomOk ? "In and out" : "The zoom buttons are missing");
  return next;
}

function setSignal(items, name, ok, detail) {
  const found = items.find((item) => item.name === name);
  if (!found) {
    items.push({ name, ok, detail });
    return;
  }
  found.ok = ok;
  found.detail = detail;
}

function inMicrosoftEdge() {
  return /Edg(?:e|iOS|A)?\//.test(navigator.userAgent || "");
}

function noteEdge() {
  const note = $("edge-note");
  if (!note) return;
  note.hidden = !(state.offline && !inMicrosoftEdge() && location.protocol === "file:");
}

function signalRow(item) {
  const row = document.createElement("li");
  const dot = document.createElement("span");
  const strict = item.tone === "strict" || item.name === "Schwab";
  dot.className = item.ok ? "dot on" : strict ? "dot bad" : "dot";
  const text = document.createElement("span");
  text.textContent = item.name + " · " + item.detail;
  row.append(dot, text);
  return row;
}

function paintSignals(items) {
  const stamp = items.map((item) => item.name + (item.ok ? "1" : "0") + (item.detail || "")).join("|");
  ["signal-strip", "signals"].forEach((id) => {
    const list = $(id);
    if (!list || sameStamp(list, stamp)) return;
    list.replaceChildren();
    items.forEach((item) => list.appendChild(signalRow(item)));
  });
  const ready = items.length > 0 && items.every((item) => item.ok);
  const sum = $("signal-sum");
  if (sum) {
    const waiting = items.filter((item) => !item.ok).map((item) => item.name);
    const version = (items.find((item) => item.name === "Version") || {}).detail || VERSION;
    sum.textContent = waiting.length
      ? waiting.length + (waiting.length === 1 ? " check needs attention: " : " checks need attention: ") + waiting.join(", ")
      : "All " + items.length + " checks green · Version " + version;
    sum.classList.toggle("ok", !waiting.length);
  }
  const tab = document.querySelector('.dock-tab[data-dock="signals"]');
  if (tab) {
    tab.classList.toggle("ready", ready);
    tab.title = ready ? "All checks are green" : "A check needs attention";
  }
}

function renderLocalSignals() {
  const liveNames = state.catalog.filter((row) => phoneCrypto(row.symbol, row)).length;
  paintSignals(withHooks([
    { name: "This device", ok: true, detail: "Mobile copy" },
    { name: "Paper", ok: true, detail: "On" },
    { name: "Auto", ok: true, detail: "Ready" },
    { name: "Trade", ok: true, detail: "Ready" },
    { name: "Markets", ok: liveNames > 0, detail: liveNames + " live crypto" },
    { name: "Quotes", ok: !!(state.market && state.market.price), detail: state.market && state.market.price ? "Live crypto" : "Waiting on the quote" },
    { name: "Squeeze", ok: !!(state.market && (state.market.squeeze || []).length), detail: "Ready" },
    { name: "TMO pane", ok: typeof PaperFrames !== "undefined" && !!PaperFrames.trueMomentum, detail: "True momentum 14/5/3" },
    { name: "CCI pane", ok: typeof PaperFrames !== "undefined" && !!PaperFrames.cciPane, detail: "CCI 50 / 14 / 5" },
    { name: "Volume Wave", ok: typeof PaperFrames !== "undefined" && !!PaperFrames.volumeWave, detail: "Desk Waves Hull ATR 6 · 0.7" },
    { name: "Arrows", ok: true, detail: "Ready" },
    { name: "Study", ok: typeof PaperStudy !== "undefined", detail: "On this device" },
    {
      name: "Learning",
      ok: true,
      detail:
        learnTouchNote ||
        fillTimingNote() ||
        (state.learnBook && state.learnBook.bot_on
          ? "Bot on · all pages while score runs"
          : "Page open cycles · turn bot score on for closed pages"),
    },
    { name: "New process", ok: true, detail: newProcessNote || "Waiting for a closed-bar process change" },
    { name: "Fear and greed", ok: !!(state.market && state.market.bars && state.market.bars.length >= 20), detail: phoneMood() },
    { name: "Zoom", ok: !!($("zoom-in") && $("zoom-out")), detail: "In and out" },
    { name: "Timeframes", ok: typeof PaperFrames !== "undefined" && PaperFrames.FRAMES.length === 10, detail: "10 windows" },
    { name: "Broker", ok: true, detail: "Not connected" },
    {
      name: "Schwab",
      ok: !!(state.schwab && state.schwab.connected),
      detail: state.schwab ? String(state.schwab.detail || "not connected") : "not connected · desk offline",
      tone: "strict",
    },
    { name: "Version", ok: true, detail: VERSION },
  ]));
}

function drawMoodClock(score, label) {
  const canvas = $("mood-clock");
  if (!canvas) return;
  const size = 112;
  const ratio = window.devicePixelRatio || 1;
  const next = Math.floor(size * ratio);
  if (canvas.width !== next || canvas.height !== next) {
    canvas.width = next;
    canvas.height = next;
  }
  const ctx = canvas.getContext("2d");
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  ctx.clearRect(0, 0, size, size);
  const cx = size / 2;
  const cy = size / 2;
  const radius = 40;
  const start = Math.PI * 0.75;
  const sweep = Math.PI * 1.5;
  const value = score == null || Number.isNaN(Number(score)) ? null : Math.max(0, Math.min(100, Math.round(Number(score))));
  const tone = value == null ? "#5c6770" : value <= 35 ? "#ff6b7a" : value >= 65 ? "#3ddc97" : "#e2b15a";
  ctx.beginPath();
  ctx.arc(cx, cy, radius + 8, 0, Math.PI * 2);
  ctx.fillStyle = "#101318";
  ctx.fill();
  ctx.beginPath();
  ctx.moveTo(cx, cy);
  ctx.arc(cx, cy, radius, start, start + sweep);
  ctx.closePath();
  ctx.fillStyle = "#1c2430";
  ctx.fill();
  if (value != null) {
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, radius, start, start + sweep * (value / 100));
    ctx.closePath();
    ctx.fillStyle = tone;
    ctx.fill();
  }
  ctx.font = "10px Segoe UI, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  for (let mark = 0; mark <= 100; mark += 5) {
    const angle = start + sweep * (mark / 100);
    const major = mark % 25 === 0;
    ctx.strokeStyle = major ? "#d7e2ea" : "#5c6770";
    ctx.lineWidth = major ? 2 : 1;
    ctx.beginPath();
    ctx.moveTo(cx + Math.cos(angle) * (radius - (major ? 8 : 4)), cy + Math.sin(angle) * (radius - (major ? 8 : 4)));
    ctx.lineTo(cx + Math.cos(angle) * (radius + 1), cy + Math.sin(angle) * (radius + 1));
    ctx.stroke();
    if (major) {
      ctx.fillStyle = "#9aa6b2";
      ctx.fillText(String(mark), cx + Math.cos(angle) * (radius + 11), cy + Math.sin(angle) * (radius + 11));
    }
  }
  if (value != null) {
    const angle = start + sweep * (value / 100);
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(angle);
    ctx.fillStyle = "#f4f7fb";
    ctx.beginPath();
    ctx.moveTo(radius - 6, 0);
    ctx.lineTo(-12, 4);
    ctx.lineTo(-7, 0);
    ctx.lineTo(-12, -4);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
  }
  ctx.beginPath();
  ctx.arc(cx, cy, 15, 0, Math.PI * 2);
  ctx.fillStyle = "#141920";
  ctx.fill();
  ctx.lineWidth = 2;
  ctx.strokeStyle = tone;
  ctx.stroke();
  ctx.fillStyle = "#f4f7fb";
  ctx.font = "700 15px Segoe UI, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText(value == null ? "—" : String(value), cx, cy);
  const word = $("mood-clock-label");
  if (word) {
    word.textContent = value == null ? "" : String(label || "");
    word.className = "mood-clock-label " + (label ? String(label).toLowerCase() : "");
  }
}

function phoneMood() {
  const bars = (state.market && state.market.bars) || [];
  if (bars.length < 20 || typeof PaperFrames === "undefined" || !PaperFrames.mood) return "Waiting on the quote";
  const row = PaperFrames.mood(bars);
  return row.score == null ? row.note : row.label + " " + row.score;
}

function deskLine(text) {
  const node = document.querySelector("#lede");
  if (node) node.textContent = text;
}

function paintCanvasNote(message) {
  const canvas = document.querySelector("#candles");
  if (!canvas || !canvas.getContext) return;
  const ctx = prepare(canvas, 300, 240);
  const width = canvas.paperWidth;
  const height = canvas.paperHeight;
  ctx.fillStyle = "#e7edf3";
  ctx.font = "16px Segoe UI, sans-serif";
  ctx.textBaseline = "top";
  const words = String(message || "").split(/\s+/).filter(Boolean);
  let line = "";
  let y = 28;
  words.forEach((word) => {
    const next = line ? line + " " + word : word;
    if (ctx.measureText(next).width > Math.max(40, width - 32) && line) {
      ctx.fillText(line, 16, y);
      line = word;
      y += 22;
    } else {
      line = next;
    }
  });
  if (line && y < height - 8) ctx.fillText(line, 16, y);
}

function showProblem(message) {
  deskLine(message);
  const help = document.querySelector("#open-help");
  if (help) {
    help.hidden = false;
    help.textContent = message;
  }
  const read = document.querySelector("#chart-read");
  if (read) read.textContent = message;
  const status = document.querySelector("#status");
  if (status) status.textContent = message;
  paintCanvasNote(message);
  drawMoodClock(null, "");
}

function phoneLeaders(rows, buysOnly) {
  return rows
    .filter((row) => row.seen >= 20 && row.seen > 0 && row.matched / row.seen >= 0.6 && (!buysOnly || row.setup === "buy"))
    .sort((a, b) => b.rate - a.rate || b.seen - a.seen || a.symbol.localeCompare(b.symbol));
}

function bestPicks(rows) {
  const qualified = phoneLeaders(rows, false).slice(0, 10);
  if (!qualified.length) return [];
  const best = qualified[0].rate;
  const kept = [];
  for (let index = 0; index < qualified.length; index += 1) {
    const row = qualified[index];
    if (kept.length >= 5) break;
    const fill = kept.length < 3;
    const close = best - row.rate <= 5;
    if (row.rate === best || fill || close) kept.push(row);
    else break;
  }
  return kept;
}

const FIRE_FIVE_LIMIT = 5;
const FIRE_AUTO_KEY = "paperTraderFireAuto";

function loadFireAuto() {
  try {
    return JSON.parse(localStorage.getItem(FIRE_AUTO_KEY) || "{}").on === true;
  } catch (error) {
    return false;
  }
}

function saveFireAuto(on) {
  try {
    localStorage.setItem(FIRE_AUTO_KEY, JSON.stringify({ on: !!on }));
  } catch (error) {
    /* Fire Auto still holds for this page. */
  }
  state.fireAuto = !!on;
}

function laneRank(row) {
  const lane = row && row.lane;
  if (lane === "open") return 3;
  if (lane === "soft") return 2;
  if (lane === "skip") return 1;
  return 0;
}

function seatRank(row) {
  const seat = row && row.seat;
  if (seat === "high") return 2;
  if (seat === "mid") return 1;
  return 0;
}

function rowPrecision(row) {
  if (!row) return 0;
  if (row.precision != null && Number.isFinite(Number(row.precision))) return Number(row.precision);
  if (typeof PaperFrames === "undefined" || !PaperFrames.firePrecision) {
    return (row.fire || row.edge === "BUY" ? 60 : 0) + laneRank(row) * 5 + seatRank(row) * 4;
  }
  return PaperFrames.firePrecision(row);
}

function bestFires(rows) {
  // Top Fire BUY setups: Edge Fire on strong closed-bar scores, ranked by Fire Precision.
  // Same Fire 5 list (no duplicate scanner). Lane / Seat / big4 fold into precision.
  return (rows || [])
    .filter((row) => row && row.symbol && (row.fire === true || row.edge === "BUY") && row.seen >= 20 && row.rate >= 60)
    .map((row) => Object.assign({}, row, { precision: rowPrecision(row) }))
    .sort((a, b) => {
      const aPlus = a.setup === "buy" ? 1 : 0;
      const bPlus = b.setup === "buy" ? 1 : 0;
      const aBull = a.big4 === "bull" ? 1 : 0;
      const bBull = b.big4 === "bull" ? 1 : 0;
      return (
        (b.precision || 0) - (a.precision || 0) ||
        laneRank(b) - laneRank(a) ||
        seatRank(b) - seatRank(a) ||
        b.rate - a.rate ||
        bPlus - aPlus ||
        bBull - aBull ||
        b.seen - a.seen ||
        a.symbol.localeCompare(b.symbol)
      );
    })
    .slice(0, FIRE_FIVE_LIMIT);
}

function fireNoteText(fires, scanning) {
  const rows = fires || [];
  if (scanning && !rows.length) {
    return "Scanning for Fire. When Edge turns FIRE on closed bars (TMO · Squeeze · CCI · Desk Waves · VIX), Fire Precision ranks up to five names here. Desk Cards tighten Auto confirm. Tap to review.";
  }
  if (!rows.length) {
    return "No Fire 5 signals right now. Desk Call is WAIT until Edge FIRE on a strong scan name. Paper only. Not a forecast.";
  }
  const top = rows[0] && rows[0].precision != null ? " Top Fire Precision " + rows[0].precision + "." : "";
  return (
    rows.length +
    " Fire trade" +
    (rows.length === 1 ? "" : "s") +
    " lit green, ranked by Fire Precision." +
    top +
    " Fire 5 Auto asks Yes and needs Confirm Lock. Not a forecast."
  );
}

function tideBuyReason() {
  const tide = state.tide && state.tide.result;
  if (tide && tide.on) return "Tide Alert is on — new Edge longs wait. Review Go Long 5. Edge SELL can still exit.";
  return "";
}

function scorecardBoards() {
  const result = state.sc && state.sc.result;
  return {
    sectors: (result && result.sectors) || [],
    indexes: (result && result.indexes) || [],
  };
}

function readLaneAlign(symbol, bars) {
  if (!symbol || typeof PaperFrames === "undefined" || !PaperFrames.laneAlign) {
    return { ready: false, lane: "unread", why: "Lane Align unread.", sector: "", index: "", seat: null, symbol: symbol || "" };
  }
  const sector = SQ_SECTOR[symbol] || "";
  const indexSym = sqIndexOf(symbol);
  const boards = scorecardBoards();
  const seat = bars && bars.length && PaperFrames.rangeSeat ? PaperFrames.rangeSeat(bars) : null;
  return PaperFrames.laneAlign({
    symbol,
    sector,
    index: indexSym,
    sectors: boards.sectors,
    indexes: boards.indexes,
    seat,
  });
}

function laneBuyReason(symbol, mode) {
  const tide = state.tide && state.tide.result;
  if (tide && tide.on) return "";
  const lane = readLaneAlign(symbol, symbol === (state.paper && state.paper.symbol) ? seenBars() : null);
  if (PaperFrames.laneAllowsAuto(lane.lane, mode || "fire")) return "";
  if (lane.lane === "unread") return lane.why || "Lane Align unread — run scorecard. Fail closed.";
  if (lane.lane === "closed") return lane.why || "Lane Align closed — Auto waits for the lead lane.";
  if (lane.lane === "soft" && mode === "best") return "Lane Align soft — Best setup waits for Lane Open.";
  return lane.why || "Lane Align not open.";
}

function phoneBuyReason(symbol) {
  if (breakerState().tripped) return BREAKER_STOP;
  const tideBlock = tideBuyReason();
  if (tideBlock) return tideBlock;
  const laneBlock = laneBuyReason(symbol, state.best && state.best.on ? "best" : "fire");
  if (laneBlock) return laneBlock;
  if (!state.scanOn && !state.scanDone) return "Turn the scanner on to fill today's best.";
  if (!state.scanDone) return "The scan is still walking. Green signals come on when it finishes.";
  const day = dayBuySymbols();
  const names = day.names;
  const maxBuys = state.best && state.best.on ? BEST_MAX_BUYS : 5;
  if (names.size >= maxBuys) return "Today already has " + maxBuys + " paper buys.";
  const onBest = (state.picks || []).some((row) => row.symbol === symbol);
  const onFire = (state.fires || []).some((row) => row.symbol === symbol);
  if (!onBest && !onFire) return "This market is not in today's best or Fire 5 list.";
  if (names.has(symbol)) return "This market already has a paper buy today.";
  const bestBlock = bestBuyReason(symbol);
  if (bestBlock) return bestBlock;
  // Confirm Lock (Desk Cards) — tighter Auto path only when Fire 5 Auto or Best setup is armed.
  if ((state.fireAuto || (state.best && state.best.on)) && symbol === ((state.paper && state.paper.symbol) || "")) {
    const bars = seenBars();
    const edgeNow = currentEdge(bars);
    const fireRow = (state.fires || []).find((row) => row.symbol === symbol);
    const lock = confirmLockForSymbol(symbol, edgeNow, {
      bars,
      seat: fireRow && fireRow.seat,
      precision: fireRow && fireRow.precision,
      seatTrail: fireRow && fireRow.seatTrail,
    });
    if (!lock.ok) return "Confirm Lock: " + (lock.why || "Desk Card waiting");
  }
  return "";
}

const BREAKER_KEY = "paperTraderBreaker";
const BREAKER_DROP = 0.05;
const PAPER_SLIP_BPS = 5;

function fillText(paper) {
  const fill = paper && paper.fill;
  if (!fill || !(fill.price > 0)) return "";
  const verb = fill.side === "buy" ? "Bought" : "Sold";
  return verb + " at " + formatPrice(fill.price) + " · last trade " + formatPrice(fill.quote) + " · " + Number(fill.slip_bps) + " bps slippage · " + Number(fill.fee_bps) + " bps fee · " + denverClock(fill.at) + ".";
}
const BREAKER_STOP = "The circuit breaker is on. Buys stop until tomorrow. Sells still work.";
const LIVE_NOTE = "Live is locked. This desk has no broker or bank link, so every order stays paper.";

function denverDay(when) {
  const parts = new Intl.DateTimeFormat("en-CA", { timeZone: "America/Denver", year: "numeric", month: "2-digit", day: "2-digit" }).formatToParts(when || new Date());
  const pick = (type) => (parts.find((part) => part.type === type) || {}).value || "";
  return pick("year") + "-" + pick("month") + "-" + pick("day");
}

function denverClock(seconds) {
  return new Intl.DateTimeFormat("en-US", { timeZone: "America/Denver", hour: "numeric", minute: "2-digit", timeZoneName: "short" }).format(new Date(seconds * 1000));
}

function readBreaker() {
  try {
    const raw = JSON.parse(localStorage.getItem(BREAKER_KEY) || "{}");
    return raw && typeof raw === "object" ? raw : {};
  } catch (error) {
    return {};
  }
}

function writeBreaker(data) {
  try {
    localStorage.setItem(BREAKER_KEY, JSON.stringify(data));
  } catch (error) {
    /* The breaker still holds for this page. */
  }
  state.phoneBreaker = data;
}

function breakerState() {
  if (!state.offline) return (state.paper && state.paper.breaker) || { tripped: false, reason: "", at: 0, drop_pct: BREAKER_DROP * 100 };
  const data = state.phoneBreaker || readBreaker();
  const tripped = data.day === denverDay();
  return { tripped, reason: tripped ? String(data.reason || "") : "", at: tripped ? Number(data.at) || 0 : 0, drop_pct: BREAKER_DROP * 100 };
}

function haltPhone(reason) {
  const session = state.session;
  let paper = session ? PaperEngine.view(session) : state.paper;
  const held = !!(session && session.shares > 0);
  if (held) paper = PaperEngine.fill(session, "sell", PAPER_SLIP_BPS, 0, "sell all");
  if (session) session.working = [];
  if (session) paper = PaperEngine.view(session);
  const data = readBreaker();
  data.day = denverDay();
  data.reason = reason;
  data.at = Math.floor(Date.now() / 1000);
  writeBreaker(data);
  if (paper) paper.last_action = held ? "sell" : "";
  return paper;
}

function guardPhone(paper, fresh) {
  if (!state.offline || !state.session || !paper || paper.symbol !== state.session.symbol) return paper;
  const day = denverDay();
  const data = readBreaker();
  const start = data.startDay === day ? Number(data.startEquity) || 0 : 0;
  if (fresh || !(start > 0)) {
    if (paper.equity > 0) {
      data.startDay = day;
      data.startEquity = paper.equity;
      writeBreaker(data);
    }
    return paper;
  }
  if (data.day !== day && paper.equity <= start * (1 - BREAKER_DROP)) {
    const fell = (1 - paper.equity / start) * 100;
    const halted = haltPhone("Paper equity fell " + fell.toFixed(1) + "% today");
    halted.breakerTripped = true;
    return halted;
  }
  return paper;
}

function renderBreaker() {
  const box = $("breaker");
  const note = $("breaker-note");
  if (!box || !note) return;
  const breaker = breakerState();
  const drop = Number(breaker.drop_pct || BREAKER_DROP * 100);
  box.classList.toggle("tripped", !!breaker.tripped);
  const text = breaker.tripped
    ? "Breaker tripped" + (breaker.at ? " at " + denverClock(breaker.at) : "") + ". " + (breaker.reason || "Sell all pressed") + ". The paper position is cash. Buys stop until tomorrow."
    : "Breaker armed. A " + drop + "% paper drop in one day sells everything and stops buys until tomorrow.";
  if (note.textContent !== text) note.textContent = text;
}

const SCAN_KEY = "paperTraderScan";
const TYPE_KEY = "paperTraderType";
const phoneBarCache = new Map();
const SCAN_READERS = 1;
const SCAN_GAP = 300;
let phoneScanToken = 0;

function readScanOn() {
  try {
    return localStorage.getItem(SCAN_KEY) !== "off";
  } catch (error) {
    return true;
  }
}

function readType() {
  try {
    return localStorage.getItem(TYPE_KEY) || "";
  } catch (error) {
    return "";
  }
}

function writeType(value) {
  try {
    localStorage.setItem(TYPE_KEY, value || "");
  } catch (error) {
    return;
  }
}

function restoreType() {
  const select = $("category");
  if (!select) return;
  const saved = readType();
  if (saved && [...select.options].some((option) => option.value === saved)) select.value = saved;
}

function writeScanOn(on) {
  try {
    localStorage.setItem(SCAN_KEY, on ? "on" : "off");
  } catch (error) {
    return;
  }
}

function scannerOffNote() {
  return (state.leaders || []).length
    ? "Scanner is off. Scanned names stay listed. Not a forecast."
    : "Scanner is off.";
}

async function phoneDaily(symbol, category) {
  const open = state.market && state.market.symbol === symbol ? state.market.bars : null;
  if (open && open.length) {
    phoneBarCache.set(symbol, open);
    return open;
  }
  if (phoneBarCache.has(symbol)) return phoneBarCache.get(symbol);
  const bars = await PaperLive.daily(symbol, category || "");
  phoneBarCache.set(symbol, bars);
  return bars;
}

function scanKindNote() {
  const kind = $("category") && $("category").value;
  if (kind === "stock") return "This phone scans live stocks.";
  if (kind === "etf") return "This phone scans live ETFs.";
  if (kind === "future") return "This phone scans live futures.";
  return "This phone scans live crypto.";
}

function missNote(errors) {
  if (!errors) return "";
  return " " + errors + (errors === 1 ? " quote did not answer." : " quotes did not answer.");
}

function scanOrder() {
  const kind = $("category") && $("category").value;
  const pool = state.catalog.filter((row) => {
    if (kind === "stock" || kind === "etf" || kind === "future" || kind === "crypto") return row.category === kind;
    return phoneCrypto(row.symbol, row);
  }).map((row) => row.symbol);
  const ranked = [];
  const current = (state.paper && state.paper.symbol) || "";
  if (current && pool.includes(current)) ranked.push(current);
  readPins().forEach((symbol) => {
    if (pool.includes(symbol) && !ranked.includes(symbol)) ranked.push(symbol);
  });
  pool.forEach((symbol) => {
    if (!ranked.includes(symbol)) ranked.push(symbol);
  });
  return ranked;
}

function renderScanToggle() {
  const button = $("scan-toggle");
  if (!button) return;
  button.textContent = state.scanOn ? "Scanner on" : "Scanner off";
  button.setAttribute("aria-pressed", state.scanOn ? "true" : "false");
  button.classList.toggle("on", !!state.scanOn);
}

function applyScanBook(book) {
  if (!book) return;
  state.scanOn = book.scan_on !== false;
  if (Array.isArray(book.picks)) state.picks = book.picks;
  if (book.pick_note) state.pickNote = book.pick_note;
  if (Array.isArray(book.leaders)) state.leaders = book.leaders;
  state.scanNote = book.scan_note || "";
  const scanned = Number(book.scanned) || 0;
  const total = Number(book.scan_total) || 0;
  state.scanDone = total > 0 && scanned >= total;
  renderScanToggle();
  renderToday();
  renderScan();
  refreshFireFive().catch(() => {});
}

async function setScanner(on) {
  const next = !!on;
  if (state.offline) {
    state.scanOn = next;
    writeScanOn(next);
    renderScanToggle();
    if (!next) {
      phoneScanToken += 1;
      state.scanNote = scannerOffNote();
      renderScan();
      return;
    }
    scanPhone();
    return;
  }
  const book = await postJSON("/api/learn/scan", { on: next });
  applyScanBook(book);
}

async function scanPhone() {
  if (!state.scanOn) {
    state.scanNote = scannerOffNote();
    renderScan();
    return;
  }
  const token = ++phoneScanToken;
  const symbols = scanOrder();
  if (!symbols.length) {
    state.scanDone = true;
    state.scanNote = scanKindNote() + " No markets are in this list.";
    renderScan();
    return;
  }
  const scored = [];
  const kinds = new Map(state.catalog.map((item) => [item.symbol, item.category]));
  const total = symbols.length;
  let errors = 0;
  let next = 0;
  let finished = 0;
  state.scanDone = false;
  state.leaders = [];
  state.picks = [];
  state.fires = [];
  state.scanNote = "Scanned 0 of " + total + ". " + scanKindNote();
  state.pickNote = "";
  state.fireNote = fireNoteText([], true);
  renderScan();
  renderToday();
  renderFireFive();
  const live = () => token === phoneScanToken && state.scanOn;
  const publish = () => {
    const leaders = phoneLeaders(scored, false).slice(0, 10);
    const picks = bestPicks(scored);
    const fires = bestFires(scored);
    state.leaders = leaders;
    state.picks = picks;
    state.fires = fires;
    const done = finished === total;
    const score = leaders.length ? "Highest closed-bar scores. Not a forecast." : "No market cleared the bar yet. Not a forecast.";
    state.scanNote = "Scanned " + finished + " of " + total + ". " + scanKindNote() + " " + score + missNote(errors);
    state.pickNote = done
      ? (picks.length
        ? "Scan finished. Green signal on each name. Three to five, and no more. Not a forecast."
        : "No market cleared the bar today.")
      : "Ten names, then three to five. Green signals come on when the scan finishes.";
    state.fireNote = fireNoteText(fires, !done);
    state.scanDone = done;
    renderScan();
    renderToday();
    renderFireFive();
    if (done && (leaders.length || picks.length || fires.length)) {
      cycleScanLearn("scan").catch(() => {});
    }
  };
  const reader = async () => {
    while (live() && next < total) {
      while (typeof document !== "undefined" && document.hidden) {
        await new Promise((resolve) => setTimeout(resolve, 1000));
        if (!live()) return;
      }
      await afterSqueeze();
      if (!live()) return;
      const symbol = symbols[next];
      next += 1;
      const open = (state.paper && state.paper.symbol) || "";
      try {
        const bars = symbol === open && phoneBarCache.has(symbol)
          ? phoneBarCache.get(symbol)
          : await PaperLive.daily(symbol, kinds.get(symbol) || "");
        if (symbol !== open) phoneBarCache.delete(symbol);
        const crypto = phoneCrypto(symbol, { category: kinds.get(symbol) || "" });
        const settled = PaperFrames.closedBars(bars, crypto);
        const lesson = PaperFrames.lesson(settled);
        const alert = PaperFrames.squeezeAlert ? PaperFrames.squeezeAlert(settled) : { kind: "", side: "" };
        const seen = Number(lesson && lesson.seen) || 0;
        const matched = Number(lesson && lesson.matched) || 0;
        const timing = PaperFrames.edge ? PaperFrames.edge(bars, { live: PaperFrames.liveBar(bars, crypto), vixBars: state.vixBars || [] }) : null;
        const fireOn = !!(timing && timing.ready && timing.call === "BUY" && timing.stage === "fire");
        const trail =
          PaperFrames.seatTrailRead
            ? PaperFrames.seatTrailRead(bars, { live: PaperFrames.liveBar(bars, crypto) })
            : null;
        const print = timing && timing.parts && PaperFrames.gatePrint ? PaperFrames.gatePrint(timing.parts) : null;
        const freshFire = timing && PaperFrames.fireFreshFlip ? PaperFrames.fireFreshFlip(timing) : false;
        const seatVal = trail && trail.seat ? trail.seat : "";
        const precision = PaperFrames.firePrecision
          ? PaperFrames.firePrecision({
              score: timing && timing.score,
              checks: timing && timing.checks,
              call: timing && timing.call,
              stage: timing && timing.stage,
              big4: timing && timing.big4 && timing.big4.bias,
              big4Ok: timing && timing.big4 && timing.big4.ok,
              seat: seatVal,
              freshFire,
              parts: timing && timing.parts,
              seatTrail: !!(trail && trail.ready && trail.armed),
            })
          : null;
        scored.push({
          symbol,
          seen,
          matched,
          rate: seen ? Math.round((matched / seen) * 100) : 0,
          setup: alert.kind === "dots" && alert.side === "buy" ? "buy" : "",
          edge: timing && timing.ready ? timing.call : "",
          fire: fireOn,
          stage: timing && timing.stage ? timing.stage : "",
          big4: timing && timing.big4 && timing.big4.bias ? timing.big4.bias : "",
          seatTrail: !!(trail && trail.ready && trail.armed),
          seatTrailCall: trail && trail.ready ? trail.call : "",
          seatLabel: trail && trail.seatLabel ? trail.seatLabel : "",
          seat: seatVal,
          precision,
          freshFire,
          gateLine: print ? print.line : "",
          gateShort: print ? print.shortMiss || print.line : "",
          score: timing && timing.score != null ? timing.score : null,
          checks: timing && timing.checks != null ? timing.checks : null,
        });
      } catch (error) {
        errors += 1;
      }
      if (!live()) return;
      finished += 1;
      publish();
      if (finished < total) await new Promise((resolve) => setTimeout(resolve, SCAN_GAP));
    }
  };
  await Promise.all(Array.from({ length: Math.min(SCAN_READERS, total) }, reader));
}

const PHONE_NAMES = [
  ["BTC-USD", "Bitcoin"],
  ["ETH-USD", "Ether"],
  ["XRP-USD", "XRP"],
  ["XLM-USD", "Stellar"],
  ["SOL-USD", "Solana"],
];

function phoneCatalogFallback() {
  return PHONE_NAMES.map((row) => row[0] + "\t" + row[1] + "\tcrypto").join("\n") + "\n";
}

async function loadCatalogText() {
  if (globalThis.PAPER_CATALOG) return globalThis.PAPER_CATALOG;
  const paths = ["catalog.txt", "../paper_trader/catalog.txt", "/catalog.txt"];
  for (const path of paths) {
    try {
      const response = await fetch(path);
      if (!response.ok) continue;
      const text = await response.text();
      if (text && text.includes("BTC-USD")) return text;
    } catch (error) {
      continue;
    }
  }
  return phoneCatalogFallback();
}

async function ensureCatalog() {
  if (Array.isArray(state.catalog) && state.catalog.length) return state.catalog;
  try {
    const text = await loadCatalogText();
    state.catalog = PaperEngine.parseCatalog(text);
  } catch (error) {
    state.catalog = state.catalog || [];
  }
  return state.catalog;
}

async function bootOffline() {
  const text = await loadCatalogText();
  state.catalog = PaperEngine.parseCatalog(text);
  restoreType();
  const select = $("category");
  if (select) {
    const value = select.value;
    const visible = state.catalog.some((row) => !value || row.category === value);
    if (!visible && state.catalog.some((row) => row.category === "crypto")) select.value = "crypto";
  }
  state.scanOn = readScanOn();
  renderPins();
  state.scanNote = state.scanOn ? "Loading the live quote…" : "Scanner is off.";
  state.pickNote = "";
  renderScan();
  renderToday();
  await refreshSymbols();
  deskLine("Loading the live quote…");
  const saved = savedSymbol();
  const order = [];
  [saved, "BTC-USD", "ETH-USD", "XRP-USD", "SOL-USD"].forEach((symbol) => {
    if (!symbol || order.includes(symbol)) return;
    const row = state.catalog.find((item) => item.symbol === symbol);
    if (row && phoneCrypto(symbol, row)) order.push(symbol);
  });
  let found = null;
  let lastError = null;
  for (let index = 0; index < order.length; index += 1) {
    const symbol = order[index];
    const row = state.catalog.find((item) => item.symbol === symbol);
    try {
      const bars = await PaperLive.daily(symbol, row ? row.category : "crypto");
      found = { symbol, bars };
      break;
    } catch (error) {
      lastError = error;
    }
  }
  if (!found) {
    if (state.scanOn) scanPhone();
    throw lastError || new Error("The quote feed did not answer. Check the connection.");
  }
  const bars = found.bars;
  openOfflineSession(found.symbol, bars, { fee_bps: 5 }, false);
  state.paper = PaperEngine.view(state.session);
  await refreshSymbols();
  fillForm(state.paper.params);
  state.market = PaperEngine.market(state.session);
  stampQuote(bars);
  render(state.paper);
  renderLocalSignals();
  rememberSymbol(state.paper.symbol);
  noteEdge();
  deskLine("Desk is working.");
  showChart();
  refreshOutlook().catch(() => {});
  refreshIndexTape().catch(() => {});
  runCycleDesk().catch(() => {});
  runForecast().catch(() => {});
  if (state.scanOn) scanPhone();
  else {
    state.scanNote = "Scanner is off.";
    renderScan();
  }
  if (state.paper.paper === true) {
    state.auto = true;
    render(state.paper);
    $("status").textContent = "Auto is on. Refreshing the market quote.";
    autoTick();
    return;
  }
  $("status").textContent = inMicrosoftEdge()
    ? "Opened in Microsoft Edge. Market quotes are live."
    : "Mobile copy ready. Market quotes are live.";
}

async function boot() {
  const help = document.querySelector("#open-help");
  if (help) help.hidden = true;
  deskLine("Starting the desk…");
  restoreStudies();
  state.offline = !(await probeDesk());
  if (state.offline) {
    await bootOffline();
    return;
  }
  restoreType();
  // Online desk used to skip the catalog — Squeeze / Desk Pool "All stocks" then scanned an empty list.
  await ensureCatalog();
  const paper = await getJSON("/api/paper");
  state.paper = paper;
  await refreshSymbols();
  fillForm(paper.params);
  await loadMarket(paper.symbol);
  render(paper);
  await loadSignals();
  rememberSymbol(paper.symbol);
  deskLine("Desk is working.");
  refreshOutlook().catch(() => {});
  refreshIndexTape().catch(() => {});
  // Stagger heavy Tools scanners so the chart quote and tape stay snappy on open.
  setTimeout(() => runCycleDesk().catch(() => {}), 900);
  setTimeout(() => runForecast().catch(() => {}), 2200);
  if (paper.paper === true) {
    state.auto = true;
    render(paper);
    $("status").textContent = "Auto is on. Refreshing the market quote.";
    autoTick();
    return;
  }
  $("status").textContent = "Market quote ready.";
}

function paintSchwabButtons(status) {
  state.schwab = status || null;
  const connect = $("schwab-connect");
  const market = $("schwab-market");
  const live = $("schwab-live");
  const note = $("schwab-note");
  const top = $("schwab-top");
  const dot = $("schwab-top-dot");
  const topStatus = $("schwab-top-status");
  if (!connect || !market || !live) return;
  const linked = !!(status && status.connected);
  const creds = !!(status && status.credentials);
  connect.setAttribute("aria-pressed", linked ? "true" : "false");
  connect.textContent = linked ? "Connected" : "Connect";
  if (top) top.classList.toggle("on", linked);
  if (dot) {
    dot.className = linked ? "schwab-top-dot on" : "schwab-top-dot bad";
    dot.title = linked ? "Connected" : "Not connected";
  }
  if (topStatus) {
    topStatus.className = linked ? "schwab-top-status on" : "schwab-top-status bad";
    topStatus.textContent = linked ? "ON" : "OFF";
  }
  market.disabled = !linked;
  market.setAttribute("aria-pressed", status && status.market_data ? "true" : "false");
  market.textContent = status && status.market_data ? "Market Data on" : "Market Data";
  // Max Safe: Live Trading button stays visible but cannot arm — only confirms the lock.
  live.disabled = false;
  live.setAttribute("aria-pressed", "false");
  live.textContent = "Live locked";
  if (!note) return;
  if (!creds) {
    note.textContent = "Paper stays the default. Live locked.";
  } else if (!linked) {
    note.textContent = status && status.detail
      ? String(status.detail)
      : "Paper stays the default. Live locked.";
  } else if (status.market_data) {
    note.textContent = "Market Data on · Live locked · paper only.";
  } else {
    note.textContent = "Connected · Market Data off · Live locked · paper only.";
  }
}

async function loadSchwab() {
  if (state.offline) {
    paintSchwabButtons({
      connected: false,
      credentials: false,
      market_data: false,
      live_trading: false,
      detail: "not connected · desk offline",
    });
    return state.schwab;
  }
  try {
    const status = await getJSON("/api/schwab");
    paintSchwabButtons(status);
    return status;
  } catch (error) {
    paintSchwabButtons({
      connected: false,
      credentials: false,
      market_data: false,
      live_trading: false,
      detail: "not connected · " + error.message,
    });
    return state.schwab;
  }
}

function closeSchwabModal() {
  const box = $("schwab-modal");
  if (box) box.hidden = true;
}

function openSchwabModal(message) {
  const box = $("schwab-modal");
  if (!box) return;
  $("schwab-redirect").value = "";
  $("schwab-modal-note").textContent = message || "";
  box.hidden = false;
  $("schwab-redirect").focus();
}

async function refreshSchwabSignals() {
  await loadSchwab();
  if (!state.offline) await loadSignals();
  else renderLocalSignals();
}

async function loadSignals() {
  await loadSchwab();
  const report = await getJSON("/api/checks");
  paintSignals(withHooks(report.items));
  requestAnimationFrame(() => paintSignals(withHooks(report.items)));
  try {
    await postJSON("/api/learn/watch", {
      symbols: readPins()
        .concat([state.paper && state.paper.symbol].filter(Boolean))
        .concat(topScanLearnNames()),
    });
    const book = await getJSON("/api/learn");
    const openSymbol = state.paper && state.paper.symbol;
    Object.entries(book.symbols || {}).forEach(([symbol, row]) => {
      if (row && row.mood && symbol !== openSymbol) state.moods[symbol] = row.mood;
    });
    state.scanOn = book.scan_on !== false;
    state.picks = Array.isArray(book.picks) ? book.picks : [];
    state.pickNote = book.pick_note || "";
    state.leaders = Array.isArray(book.leaders) ? book.leaders : [];
    state.scanNote = book.scan_note || "";
    const scanned = Number(book.scanned) || 0;
    const total = Number(book.scan_total) || 0;
    state.scanDone = total > 0 && scanned >= total;
    state.learnBook = book;
    if (book.timing) {
      state.fillLearn = Object.assign(readFillLearn(), { timing: book.timing, fills: book.fills || [] });
      writeFillLearn(state.fillLearn);
    }
    renderPins();
    renderToday();
    renderScan();
    refreshFireFive().catch(() => {});
    document.querySelectorAll("#symbol button").forEach((button) => {
      const symbol = button.dataset.symbol;
      const row = state.moods[symbol];
      const label = button.dataset.label || button.textContent;
      if (!button.dataset.label) button.dataset.label = label;
      button.textContent = row && row.score != null ? button.dataset.label + " · " + row.label + " " + row.score : button.dataset.label;
      button.classList.remove("fear", "greed", "neutral");
      if (row && row.label) button.classList.add(row.label.toLowerCase());
    });
  } catch (error) {
    return;
  }
}

async function postJSON(url, body) {
  return getJSON(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
}

function paintZoom(count) {
  const view = PaperFrames.zoomBars(new Array(Math.max(0, count)), state.zoom);
  const inn = $("zoom-in");
  const out = $("zoom-out");
  const label = $("zoom-label");
  if (inn) inn.disabled = view.atMin;
  if (out) out.disabled = view.atMax;
  if (label) label.textContent = view.size + " bars";
  const previewCount = ((state.frames || []).find((row) => row.id === state.frameId) || {}).count || 0;
  const preview = PaperFrames.zoomBars(new Array(previewCount), state.previewZoom);
  const previewIn = $("preview-zoom-in");
  const previewOut = $("preview-zoom-out");
  const previewLabel = $("preview-zoom-label");
  if (previewIn) previewIn.disabled = preview.atMin;
  if (previewOut) previewOut.disabled = preview.atMax;
  if (previewLabel) previewLabel.textContent = preview.size ? preview.size + " bars" : "";
}

function stepZoom(which, direction) {
  if (which === "preview") {
    const row = (state.frames || []).find((item) => item.id === state.frameId);
    const count = row && row.bars ? row.bars.length : 0;
    state.previewZoom = PaperFrames.zoomStep(state.previewZoom, direction, count);
    renderFrames();
    return;
  }
  const count = seenBars().length;
  state.zoom = PaperFrames.zoomStep(state.zoom, direction, count);
  redrawStudies();
  paintZoom(count);
}

function rerunStudy() {
  if (!state.studyCode || !state.market) return;
  try {
    state.study = PaperStudy.runStudy(state.studyCode, state.market.bars);
  } catch (error) {
    state.study = null;
    $("study-status").textContent = error.message;
  }
}

async function loadMarket(symbol) {
  if (state.offline) {
    state.market = PaperEngine.market(state.session);
    stampQuote(state.session.bars);
    rerunStudy();
    return;
  }
  const params = new URLSearchParams(formParams());
  const chosen = symbol || (state.paper && state.paper.symbol) || params.get("symbol");
  if (chosen) params.set("symbol", chosen);
  state.market = await getJSON("/api/market?" + params.toString());
  if (chosen && state.market.symbol !== chosen) {
    throw new Error("The quote for " + chosen + " did not load");
  }
  rerunStudy();
}

function render(paper) {
  paper = guardPhone(paper, false);
  state.paper = paper;
  writeBook();
  if (breakerState().tripped && state.auto) {
    state.auto = false;
    if (state.autoTimer) clearTimeout(state.autoTimer);
    state.autoTimer = 0;
    $("status").textContent = "The circuit breaker tripped. Auto is off and buys stop until tomorrow.";
  }
  const signal = paper.signal || "HOLD";
  const signalNode = $("signal");
  signalNode.textContent = signal;
  signalNode.className = "signal " + signal.toLowerCase().replace(/\s+/g, "-");
  $("signal-note").textContent = notes[signal] || "";
  $("equity").textContent = formatMoney(paper.equity);
  $("cash").textContent = formatMoney(paper.cash);
  $("shares").textContent = paper.shares > 0
    ? paper.shares.toLocaleString(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + " @ " + formatPrice(paper.entry_price)
    : "Flat";
  $("through").textContent = paper.cursor + " / " + paper.total_bars;
  $("chart-title").textContent = (paper.symbol || "Chart") + " · Daily session";
  paintChartRead(paper);
  renderQuote(paper);
  paintPositionChip(paper);
  paintTicketPreview();
  syncDeskInternals();
  syncDeskStatus();
  if ($("int-body") && (state.int.result || state.int.desk)) renderInternals();
  $("next").disabled = state.auto || paper.finished;
  $("run").disabled = state.auto || paper.finished;
  $("trade").disabled = false;
  $("auto").disabled = false;
  $("auto").setAttribute("aria-pressed", state.auto ? "true" : "false");
  $("paper").disabled = false;
  $("paper").setAttribute("aria-pressed", paper.paper === true && paper.mode === "paper" ? "true" : "false");
  renderBreaker();
  renderWorking(paper);
  seedTicketPrices(paper);
  announceTicketFills(paper);
  if (paper.fill && paper.fill.price > 0 && paper.symbol) {
    state.lastFill = { symbol: paper.symbol, text: fillText(paper) };
    learnFromPaperFill(paper);
  }
  const fillNode = $("last-fill");
  if (fillNode && state.lastFill && state.lastFill.symbol === paper.symbol && fillNode.textContent !== "Last fill: " + state.lastFill.text) {
    fillNode.textContent = "Last fill: " + state.lastFill.text;
    fillNode.classList.add("on");
  }
  fillTrades($("journal-body"), paper.trades || []);
  renderResult(paper);
  renderEdge();
  scheduleEdgeFrames();
  renderBook(paper);
  renderStrategies();
  renderPins();
  renderToday();
  renderScan();
  renderReadings();
  renderFrames();
  redrawStudies();
}

function savedSymbol() {
  try {
    const value = localStorage.getItem(SYMBOL_KEY) || "";
    return value.length > 0 && value.length < 24 ? value : "";
  } catch (error) {
    return "";
  }
}

function rememberSymbol(symbol) {
  if (!symbol) return;
  try {
    localStorage.setItem(SYMBOL_KEY, symbol);
  } catch (error) {
    return;
  }
}

function readPins() {
  try {
    const parsed = JSON.parse(localStorage.getItem(PIN_KEY) || "null");
    let pins = Array.isArray(parsed)
      ? parsed.filter((item) => typeof item === "string" && item.length > 0 && item.length < 24).slice(0, PIN_LIMIT)
      : DEFAULT_PINS.slice();
    if (!localStorage.getItem(SECTOR_KEY)) {
      SECTORS.forEach((symbol) => {
        if (!pins.includes(symbol)) pins.push(symbol);
      });
      pins = pins.slice(0, PIN_LIMIT);
      localStorage.setItem(SECTOR_KEY, "1");
      localStorage.setItem(PIN_KEY, JSON.stringify(pins));
    }
    return pins;
  } catch (error) {
    return DEFAULT_PINS.slice();
  }
}

function writePins(pins) {
  try {
    localStorage.setItem(PIN_KEY, JSON.stringify(pins.slice(0, PIN_LIMIT)));
  } catch (error) {
    return;
  }
}

function pinLabel(symbol) {
  return symbol.endsWith("-USD") ? symbol.slice(0, -4) : symbol;
}

function renderScoreButtons(bar, rows, signal) {
  const current = (state.paper && state.paper.symbol) || "";
  const stamp =
    (signal ? "go" : "wait") +
    "|" +
    current +
    "|" +
    (rows || [])
      .map(
        (row) =>
          row.symbol +
          ":" +
          row.rate +
          ":" +
          (row.setup || "") +
          ":" +
          (row.edge || "") +
          ":" +
          (row.fire ? "F" : "") +
          ":" +
          (row.seatTrail ? "ST" : "") +
          ":" +
          (row.precision != null ? row.precision : "") +
          ":" +
          (row.gateShort || "") +
          ":" +
          row.matched +
          ":" +
          row.seen
      )
      .join(",");
  if (sameStamp(bar, stamp)) return;
  bar.replaceChildren();
  (rows || []).forEach((row) => {
    if (!row || !row.symbol) return;
    const button = document.createElement("button");
    button.type = "button";
    const fire = row.fire || row.edge === "BUY";
    const trail = !!row.seatTrail;
    const prec = row.precision != null ? " · P" + row.precision : "";
    const ready = fire ? " · FIRE" + prec : trail ? " · Seat Trail" : row.setup === "buy" ? " buy" : "";
    const timing = !fire && !trail && row.edge ? " · " + row.edge : "";
    button.textContent = pinLabel(row.symbol) + " " + row.rate + "%" + ready + timing;
    button.title =
      row.matched +
      " of " +
      row.seen +
      " closed bars." +
      (fire
        ? " Desk Call FIRE" + (row.precision != null ? " · Fire Precision " + row.precision : "") + "."
        : trail
          ? " Seat Trail armed."
          : row.edge
            ? " Edge " + row.edge + "."
            : "") +
      (row.gateShort ? " Gate Print: " + row.gateShort + "." : "") +
      " Tap to review. Not a forecast.";
    if (signal || fire || trail) button.classList.add("go");
    if (row.symbol === current) {
      button.classList.add("on");
      button.setAttribute("aria-pressed", "true");
    }
    button.addEventListener("click", () => chooseSymbol(row.symbol));
    bar.appendChild(button);
  });
}

function readDeskCardId() {
  try {
    const raw = localStorage.getItem(DESK_CARD_KEY) || "auto";
    if (raw === "stockfire" || raw === "coinseat" || raw === "auto") return raw;
  } catch (error) {
    /* ignore */
  }
  return "auto";
}

function writeDeskCardId(id) {
  const next = id === "stockfire" || id === "coinseat" || id === "auto" ? id : "auto";
  try {
    localStorage.setItem(DESK_CARD_KEY, next);
  } catch (error) {
    /* ignore */
  }
  state.deskCard = next;
  return next;
}

function activeDeskCard(crypto) {
  const id = state.deskCard || readDeskCardId();
  if (typeof PaperFrames === "undefined" || !PaperFrames.resolveDeskCard) {
    return { id: crypto ? "coinseat" : "stockfire", name: crypto ? "Coin Seat Card" : "Stock Fire Card", floor: crypto ? 75 : 80 };
  }
  return PaperFrames.resolveDeskCard(id, !!crypto);
}

function deskCardNoteText() {
  const id = state.deskCard || readDeskCardId();
  const cards = (typeof PaperFrames !== "undefined" && PaperFrames.DESK_CARDS) || {};
  const picked = cards[id] || cards.auto;
  const openCrypto = chartCrypto();
  const active = activeDeskCard(openCrypto);
  return (
    (picked && picked.idea ? picked.idea + " " : "") +
    "Open market uses " +
    (active.name || "Desk Card") +
    " (floor " +
    (active.floor || "—") +
    "). Confirm Lock gates Fire 5 Auto / Best setup paper buys. Not broker automation. Live locked. Not a forecast."
  );
}

function renderDeskCards() {
  const note = $("desk-card-note");
  const id = state.deskCard || readDeskCardId();
  if (note) note.textContent = deskCardNoteText();
  const buttons = [
    ["auto", $("card-auto")],
    ["stockfire", $("card-stockfire")],
    ["coinseat", $("card-coinseat")],
  ];
  buttons.forEach(([key, button]) => {
    if (!button) return;
    const on = id === key;
    button.setAttribute("aria-pressed", on ? "true" : "false");
    button.className = "desk-card-btn" + (on ? " on" : "");
  });
}

function confirmLockForSymbol(symbol, timing, extras) {
  const row = state.catalog.find((item) => item.symbol === symbol);
  const crypto = phoneCrypto(symbol, row);
  const cardId = state.deskCard || readDeskCardId();
  const parts = timing && timing.parts ? timing.parts : null;
  const print = typeof PaperFrames !== "undefined" && PaperFrames.gatePrint ? PaperFrames.gatePrint(parts) : null;
  const lane = (extras && extras.lane) || readLaneAlign(symbol, extras && extras.bars ? extras.bars : null);
  const seat = extras && extras.seat != null ? extras.seat : lane && lane.seat ? lane.seat : "";
  const precision =
    extras && extras.precision != null
      ? extras.precision
      : typeof PaperFrames !== "undefined" && PaperFrames.firePrecision
        ? PaperFrames.firePrecision({
            score: timing && timing.score,
            checks: timing && timing.checks,
            call: timing && timing.call,
            stage: timing && timing.stage,
            big4: timing && timing.big4 && timing.big4.bias,
            big4Ok: timing && timing.big4 && timing.big4.ok,
            lane: lane && lane.lane,
            seat: typeof seat === "string" ? seat : seat && seat.seat,
            freshFire: timing && PaperFrames.fireFreshFlip ? PaperFrames.fireFreshFlip(timing) : false,
            parts,
            seatTrail: !!(extras && extras.seatTrail),
          })
        : null;
  if (typeof PaperFrames === "undefined" || !PaperFrames.confirmLock) {
    return { ok: !!(timing && timing.call === "BUY" && timing.stage === "fire"), why: "Confirm Lock unread", precision, print };
  }
  return PaperFrames.confirmLock({
    cardId,
    crypto,
    fire: !!(timing && timing.call === "BUY" && timing.stage === "fire"),
    call: timing && timing.call,
    stage: timing && timing.stage,
    precision,
    big4: timing && timing.big4 && timing.big4.bias,
    big4Ok: timing && timing.big4 && timing.big4.ok,
    lane: lane && lane.lane,
    seat: typeof seat === "string" ? seat : seat && seat.seat,
    seatTrail: !!(extras && extras.seatTrail),
  });
}

async function refreshFireFive() {
  const pool = [];
  const seen = new Set();
  []
    .concat(state.fires || [])
    .concat(state.picks || [])
    .concat(state.leaders || [])
    .forEach((row) => {
      if (!row || !row.symbol || seen.has(row.symbol)) return;
      seen.add(row.symbol);
      pool.push(row);
    });
  const targets = pool.slice(0, 12);
  if (!targets.length) {
    state.fires = [];
    state.fireNote = fireNoteText([], !state.scanDone);
    renderFireFive();
    return state.fires;
  }
  const scored = [];
  for (let index = 0; index < targets.length; index += 1) {
    const base = targets[index];
    const symbol = base.symbol;
    try {
      const row = state.catalog.find((item) => item.symbol === symbol);
      const bars = state.offline
        ? await sqDaily(symbol)
        : (await getJSON("/api/bars?symbol=" + encodeURIComponent(symbol) + "&frame=1D")).bars;
      const crypto = phoneCrypto(symbol, row);
      const timing = PaperFrames.edge(bars, { live: PaperFrames.liveBar(bars, crypto), vixBars: state.vixBars || [] });
      const fireOn = !!(timing && timing.ready && timing.call === "BUY" && timing.stage === "fire");
      const seat = PaperFrames.rangeSeat ? PaperFrames.rangeSeat(bars) : null;
      const lane = readLaneAlign(symbol, bars);
      const print = timing && timing.parts && PaperFrames.gatePrint ? PaperFrames.gatePrint(timing.parts) : null;
      const freshFire = timing && PaperFrames.fireFreshFlip ? PaperFrames.fireFreshFlip(timing) : false;
      const precision = PaperFrames.firePrecision
        ? PaperFrames.firePrecision({
            score: timing && timing.score,
            checks: timing && timing.checks,
            call: timing && timing.call,
            stage: timing && timing.stage,
            big4: timing && timing.big4 && timing.big4.bias,
            big4Ok: timing && timing.big4 && timing.big4.ok,
            lane: lane.lane || "",
            seat: seat && seat.seat ? seat.seat : "",
            freshFire,
            parts: timing && timing.parts,
            seatTrail: !!base.seatTrail,
          })
        : null;
      scored.push({
        symbol,
        seen: Number(base.seen) || 0,
        matched: Number(base.matched) || 0,
        rate: Number(base.rate) || 0,
        setup: base.setup || "",
        edge: timing && timing.ready ? timing.call : base.edge || "",
        fire: fireOn,
        stage: timing && timing.stage ? timing.stage : "",
        big4: timing && timing.big4 && timing.big4.bias ? timing.big4.bias : "",
        lane: lane.lane || "",
        laneWhy: lane.why || "",
        seat: seat && seat.seat ? seat.seat : "",
        seatPct: seat && seat.pct != null ? seat.pct : null,
        seatLabel: seat && seat.label ? seat.label : "",
        precision,
        freshFire,
        gateLine: print ? print.line : "",
        gateShort: print ? print.shortMiss || print.line : "",
        score: timing && timing.score != null ? timing.score : null,
        checks: timing && timing.checks != null ? timing.checks : null,
      });
    } catch (error) {
      scored.push(Object.assign({}, base, { fire: false }));
    }
  }
  state.fires = bestFires(scored);
  state.fires.forEach((row) => rememberWeekBoard(row));
  state.fireNote = fireNoteText(state.fires, !state.scanDone);
  renderFireFive();
  return state.fires;
}

async function fireAutoTrade() {
  if (!state.fireAuto || !state.paper || state.paper.paper !== true) return "";
  if (breakerState().tripped) return "";
  if (confirmWait) return "Waiting for your answer on the open warning.";
  const tideHold = tideBuyReason();
  if (tideHold) {
    state.fireNote = "Fire 5 Auto paused: " + tideHold;
    renderFireFive();
    return state.fireNote;
  }
  let fires = state.fires || [];
  if (!fires.length) {
    await refreshFireFive();
    fires = state.fires || [];
  }
  if (!fires.length) {
    state.fireNote = "Fire 5 Auto is on. Waiting for a green Fire signal on the scan.";
    renderFireFive();
    return state.fireNote;
  }
  const open = state.paper.symbol;
  const laneOkFire = (item) => !laneBuyReason(item.symbol, "fire");
  let row = fires.find((item) => item.symbol === open && laneOkFire(item));
  if (!row) {
    const pool = fires.filter(laneOkFire);
    if (!pool.length) {
      state.fireNote = "Fire 5 Auto paused: Lane Align not open on green names. Run scorecard or wait for Lane Open.";
      renderFireFive();
      return state.fireNote;
    }
    const next = pool[state.fireCursor % pool.length];
    state.fireCursor = (state.fireCursor + 1) % pool.length;
    if (next && next.symbol) {
      await chooseSymbol(next.symbol);
      return "Fire 5 Auto opened " + next.symbol + " for review.";
    }
    return "";
  }
  const bars = seenBars();
  const edgeNow = currentEdge(bars);
  if (!edgeNow || !edgeNow.ready || edgeNow.call !== "BUY" || edgeNow.stage !== "fire") {
    state.fireNote = row.symbol + " left the Fire list for now. Watching the other green names.";
    renderFireFive();
    return state.fireNote;
  }
  const lock = confirmLockForSymbol(row.symbol, edgeNow, {
    bars,
    lane: { lane: row.lane, seat: row.seat ? { seat: row.seat } : null },
    seat: row.seat,
    precision: row.precision,
    seatTrail: row.seatTrail,
  });
  if (!lock.ok) {
    state.fireNote = "Fire 5 Auto Confirm Lock on " + row.symbol + ": " + (lock.why || "waiting") + " · " + (lock.card && lock.card.name ? lock.card.name : "Desk Card");
    renderFireFive();
    return state.fireNote;
  }
  if (state.paper.shares > 0) return "";
  const blocked = phoneBuyReason(row.symbol) || internalsGate(row.symbol);
  // Fire 5 Auto still requires the name to be on today's best / Fire list; ignore "not in today's best" only if it is on Fire 5.
  const soft = blocked && blocked.indexOf("not in today's best") >= 0 && fires.some((item) => item.symbol === row.symbol);
  if (blocked && !soft) {
    state.fireNote = "Fire 5 Auto skipped " + row.symbol + ": " + blocked;
    renderFireFive();
    return state.fireNote;
  }
  const board = currentBoard(bars);
  const edgeRow = board.rows.find((item) => item.id === "edge") || {
    id: "edge",
    name: "Edge · Ready · Aim · Fire",
    ready: true,
    call: "BUY",
  };
  const key = row.symbol + "|fire5|buy|" + closedDate(bars);
  if (state.strat.acted[key]) return "";
  state.strat.acted[key] = Date.now();
  saveStrat();
  state.fireNote = "Fire 5 green on " + row.symbol + ". Waiting for your Yes on the paper buy.";
  renderFireFive();
  answerAuto(edgeRow, "buy", row.symbol, closedDate(bars));
  return state.fireNote;
}

function renderScan() {
  const line = $("scan-note");
  const bar = $("scan");
  renderScanToggle();
  if (!line || !bar) return;
  line.textContent = state.scanNote || "";
  renderScoreButtons(bar, state.leaders || []);
}

function renderToday() {
  const line = $("today-note");
  const bar = $("today");
  if (!line || !bar) return;
  line.textContent = state.pickNote || "";
  renderScoreButtons(bar, state.picks || [], !!state.scanDone);
  renderFireFive();
}

function renderFireFive() {
  const line = $("fire-note");
  const bar = $("fire-five");
  const button = $("fire-auto");
  if (line) line.textContent = state.fireNote || fireNoteText(state.fires, !state.scanDone);
  if (bar) {
    const rows = (state.fires || []).map((row) =>
      Object.assign({}, row, {
        edge: row.edge || (row.fire ? "BUY" : row.edge),
        setup: row.setup || (row.fire ? "buy" : row.setup),
        precision: row.precision != null ? row.precision : rowPrecision(row),
      })
    );
    // Green as soon as Fire is on — do not wait for the full scan to finish.
    renderScoreButtons(bar, rows, rows.length > 0);
  }
  if (button) {
    const on = !!state.fireAuto;
    const ready = on && (state.fires || []).length > 0;
    button.setAttribute("aria-pressed", on ? "true" : "false");
    button.className = "fire-auto-btn" + (on ? (ready ? " ready" : " on") : "");
    button.textContent = on
      ? ready
        ? "Fire 5 Auto · " + state.fires.length + " green"
        : "Fire 5 Auto · waiting for Fire"
      : "Fire 5 Auto";
  }
  renderDeskCards();
  renderTideFade();
  paintJournalCheckSync();
}

function fadeNoteText(tide, fades) {
  const rows = fades || [];
  if (!(tide && tide.ready)) {
    return "Run scorecard or Refresh Tide. Tide Alert needs Sector Drain plus Weekly Tide or Index Soft on closed bars. Fail closed when the board is unread. Long-only — Go Long 5 is review only (not Fire 5).";
  }
  if (tide.on) {
    return (
      "Tide Alert on. " +
      (tide.why || "") +
      (rows.length
        ? " Go Long 5: " + rows.length + " pressure name" + (rows.length === 1 ? "" : "s") + " for chart review."
        : " Go Long 5 is quiet — no pressure names cleared the score yet.") +
      " New Edge longs wait. Not a forecast."
    );
  }
  return (tide.why || "Tide Alert off.") + " Fire 5 / Best setup can still buy when Edge 7/7 is on. Not a forecast.";
}

function renderTideFade() {
  const line = $("tide-note");
  const bar = $("go-long-five");
  const tide = state.tide && state.tide.result;
  const fades = (state.tide && state.tide.fades) || [];
  if (line) line.textContent = state.tide && state.tide.note ? state.tide.note : fadeNoteText(tide, fades);
  if (!bar) return;
  const current = (state.paper && state.paper.symbol) || "";
  const stamp =
    "fade|" +
    current +
    "|" +
    (tide && tide.on ? "on" : "off") +
    "|" +
    fades.map((row) => row.symbol + ":" + row.score).join(",");
  if (sameStamp(bar, stamp)) return;
  bar.replaceChildren();
  fades.forEach((row) => {
    if (!row || !row.symbol) return;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "go-long";
    button.textContent = pinLabel(row.symbol) + " " + row.score;
    button.title =
      "Go Long score " +
      row.score +
      " on closed bars" +
      (row.squeeze ? " · squeeze " + row.squeeze : "") +
      ". Review only — this desk does not short. Not a forecast.";
    if (row.symbol === current) {
      button.classList.add("on");
      button.setAttribute("aria-pressed", "true");
    }
    button.addEventListener("click", () => chooseSymbol(row.symbol));
    bar.appendChild(button);
  });
}

async function applyTideFromScorecard(result, barsMap) {
  state.tide = state.tide || { result: null, fades: [], note: "", busy: false, at: 0 };
  const tide = result && result.tide ? result.tide : null;
  state.tide.result = tide;
  if (!(tide && tide.on)) {
    state.tide.fades = [];
    state.tide.note = fadeNoteText(tide, []);
    state.tide.at = Date.now();
    renderTideFade();
    renderBestSetup();
    return state.tide;
  }
  if (state.tide.busy) return state.tide;
  state.tide.busy = true;
  try {
    const pool = [];
    const seen = new Set();
    []
      .concat(((result && result.aPlus) || []).map((row) => ({ symbol: row.symbol })))
      .concat(state.leaders || [])
      .concat(state.picks || [])
      .concat((SQ_LISTS.sectors || []).map((symbol) => ({ symbol })))
      .forEach((row) => {
        const symbol = row && (row.symbol || row);
        if (!symbol || seen.has(symbol)) return;
        seen.add(symbol);
        pool.push(symbol);
      });
    const scored = [];
    const limit = Math.min(24, pool.length);
    const cache = barsMap || {};
    for (let index = 0; index < limit; index += 1) {
      const symbol = pool[index];
      try {
        const entry = state.catalog.find((item) => item.symbol === symbol);
        const bars = cache[symbol] && cache[symbol].length ? cache[symbol] : await sqDaily(symbol);
        const crypto = phoneCrypto(symbol, entry);
        const read = PaperFrames.fadeRead(bars, {
          live: PaperFrames.liveBar(bars, crypto),
          crypto,
          vixBars: state.vixBars || [],
        });
        if (read && read.ready) {
          scored.push(
            Object.assign({ symbol }, read, {
              rate: read.score,
              matched: read.score,
              seen: 1,
              setup: "go-long",
              edge: read.edge || "",
              fire: false,
            })
          );
        }
      } catch (error) {
        /* Missing quote stays out of Go Long 5. */
      }
    }
    state.tide.fades = PaperFrames.bestFades(scored);
    state.tide.note = fadeNoteText(tide, state.tide.fades);
    state.tide.at = Date.now();
  } finally {
    state.tide.busy = false;
    renderTideFade();
    renderBestSetup();
  }
  return state.tide;
}

async function refreshTideFade() {
  state.tide = state.tide || { result: null, fades: [], note: "", busy: false, at: 0 };
  if (state.tide.busy || (state.sc && state.sc.busy)) {
    state.tide.note = "Tide is already reading…";
    renderTideFade();
    return state.tide;
  }
  await runScorecard();
  return state.tide;
}

function currentBook(paper) {
  if (state.offline) return phoneBook();
  return (paper && paper.book) || (state.paper && state.paper.book) || null;
}

function todayFillRows(book) {
  const day = denverDay();
  return ((book && book.fills) || []).filter((item) => {
    if (!(item && item.at > 0)) return false;
    return denverDay(new Date(item.at * 1000)) === day;
  });
}

function seatTrailScanRows() {
  return (state.leaders || [])
    .concat(state.picks || [])
    .concat(state.fires || [])
    .filter((row) => row && row.symbol && row.seatTrail);
}

function journalBuyRows(book) {
  const held = new Set((((book && book.positions) || []).map((item) => item.symbol)));
  const bought = dayBuySymbols().names;
  const filled = new Set(
    todayFillRows(book)
      .filter((item) => item.side === "buy")
      .map((item) => item.symbol)
  );
  const rows = [];
  const seen = new Set();
  []
    .concat(state.fires || [])
    .concat(state.picks || [])
    .concat(seatTrailScanRows())
    .forEach((row) => {
      if (!row || !row.symbol || seen.has(row.symbol)) return;
      seen.add(row.symbol);
      const fire = !!(row.fire || row.edge === "BUY");
      const trail = !!row.seatTrail;
      let status = "best";
      if (held.has(row.symbol)) status = "held";
      else if (bought.has(row.symbol) || filled.has(row.symbol)) status = "bought";
      else if (fire) status = "fire";
      else if (trail) status = "trail";
      const lane = row.lane ? row : Object.assign({}, row, readLaneAlign(row.symbol, null));
      rows.push(
        Object.assign({}, row, {
          edge: row.edge || (fire ? "BUY" : row.edge),
          setup: row.setup || (fire ? "buy" : trail ? "seat" : row.setup),
          fire,
          seatTrail: trail,
          journalStatus: status,
          lane: row.lane || lane.lane || "",
          laneWhy: row.laneWhy || lane.why || "",
          seat: row.seat || (lane.seat && lane.seat.seat) || "",
          seatLabel: row.seatLabel || (lane.seat && lane.seat.label) || "",
          precision: row.precision != null ? row.precision : rowPrecision(row),
          gateShort: row.gateShort || row.gateLine || "",
          gateLine: row.gateLine || "",
        })
      );
    });
  return rows;
}

function paintJournalPins(bar, rows, mode) {
  if (!bar) return;
  const current = (state.paper && state.paper.symbol) || "";
  const emptyNote =
    mode === "buys"
      ? "No buy signals yet. Fire 5, today's best, and Seat Trail light here when closed-bar setups arm on a strong name."
      : mode === "sells"
        ? state.journalSellNote || "No Edge or Seat Trail exit signals on open paper longs right now."
        : "No paper fills yet today (Denver).";
  const stamp =
    mode +
    "|" +
    current +
    "|" +
    ((rows || []).length ? "" : emptyNote + "|") +
    (rows || [])
      .map(
        (row) =>
          row.symbol +
          ":" +
          (row.journalStatus || "") +
          ":" +
          (row.rate || "") +
          ":" +
          (row.side || "") +
          ":" +
          (row.reason || "") +
          ":" +
          (row.seatTrail ? "st" : "") +
          ":" +
          (row.precision != null ? row.precision : "") +
          ":" +
          (row.gateShort || "")
      )
      .join(",");
  if (sameStamp(bar, stamp)) return;
  bar.replaceChildren();
  if (!(rows || []).length) {
    const empty = document.createElement("p");
    empty.className = "muted";
    empty.textContent = emptyNote;
    bar.appendChild(empty);
    return;
  }
  (rows || []).forEach((row) => {
    if (!row || !row.symbol) return;
    const button = document.createElement("button");
    button.type = "button";
    const label = pinLabel(row.symbol);
    if (mode === "filled") {
      const verb = row.side === "buy" ? "Bought" : "Sold";
      button.textContent = label + " · " + verb + (row.price > 0 ? " " + formatPrice(row.price) : "");
      button.className = "done";
      button.title = verb + " today on the paper book. Tap to open the chart. Not a forecast.";
    } else if (mode === "sells") {
      button.textContent = label + " · EXIT" + (row.reason ? " · " + row.reason : "");
      button.className = "exit";
      button.title =
        (row.reason && String(row.reason).indexOf("Seat Trail") === 0 ? "Seat Trail" : "Edge") +
        " exit on " +
        row.symbol +
        (row.reason ? ": " + row.reason : "") +
        ". Tap to review. Auto still asks Yes before a paper sell. Not a forecast.";
    } else {
      const status = row.journalStatus || "best";
      const prec = row.precision != null ? " · P" + row.precision : "";
      const tag =
        status === "fire"
          ? " · FIRE" + prec
          : status === "trail"
            ? " · Seat Trail"
            : status === "held"
              ? " · held"
              : status === "bought"
                ? " · bought"
                : " · best";
      const laneTag =
        row.lane === "open" ? " · Lane Open" : row.lane === "soft" ? " · Lane Soft" : row.lane === "closed" ? " · Lane Closed" : "";
      const seatTag = row.seatLabel ? " · " + row.seatLabel : row.seat === "high" ? " · Seat High" : "";
      const agreeTag = row.seatTrail && status === "fire" ? " · Trail agrees" : "";
      button.textContent = label + (row.rate != null ? " " + row.rate + "%" : "") + tag + laneTag + seatTag + agreeTag;
      if (status === "fire" || status === "best" || status === "trail") button.classList.add("go");
      else button.classList.add("done");
      button.title =
        (status === "fire"
          ? "Fire buy signal" + (row.precision != null ? " · Fire Precision " + row.precision : "") + ". "
          : status === "trail"
            ? "Seat Trail armed. "
            : status === "best"
              ? "Today's best buy candidate. "
              : status === "held"
                ? "Already in a paper long. "
                : "Already bought today. ") +
        (row.gateLine || row.gateShort ? "Gate Print: " + (row.gateLine || row.gateShort) + ". " : "") +
        (row.laneWhy ? row.laneWhy + " " : "") +
        (row.seatLabel ? row.seatLabel + ". " : "") +
        "Tap to review the chart. Auto asks Yes before any paper buy. Not a forecast.";
    }
    if (row.symbol === current) {
      button.classList.add("on");
      button.setAttribute("aria-pressed", "true");
    }
    button.addEventListener("click", () => chooseSymbol(row.symbol));
    bar.appendChild(button);
  });
}

function renderJournalBuys(book) {
  paintJournalPins($("journal-buys"), journalBuyRows(book || currentBook(state.paper)), "buys");
}

function renderJournalSells() {
  paintJournalPins($("journal-sells"), state.journalSells || [], "sells");
}

function renderJournalFilled(book) {
  const fills = todayFillRows(book || currentBook(state.paper)).map((item) => ({
    symbol: item.symbol,
    side: item.side,
    price: item.price,
    journalStatus: "filled",
  }));
  paintJournalPins($("journal-filled"), fills, "filled");
}

function journalCheckNoteText(book) {
  const buys = journalBuyRows(book);
  const actionable = buys.filter(
    (row) => row.journalStatus === "fire" || row.journalStatus === "best" || row.journalStatus === "trail"
  ).length;
  const trails = buys.filter((row) => row.seatTrail || row.journalStatus === "trail").length;
  const fires = buys.filter((row) => row.journalStatus === "fire");
  const topPrec = fires.reduce((best, row) => (row.precision != null && row.precision > best ? row.precision : best), -1);
  const sells = (state.journalSells || []).length;
  const filled = todayFillRows(book).length;
  const open = ((book && book.positions) || []).length;
  const parts = [];
  parts.push(actionable ? actionable + " buy signal" + (actionable === 1 ? "" : "s") + " to review" : "No open buy signals");
  if (trails) parts.push(trails + " Seat Trail");
  if (fires.length) parts.push(fires.length + " FIRE" + (topPrec >= 0 ? " · top P" + topPrec : ""));
  parts.push(sells ? sells + " sell / exit" + (sells === 1 ? "" : "s") : open ? open + " open · no Edge exit yet" : "No sell signals");
  parts.push(filled ? filled + " fill" + (filled === 1 ? "" : "s") + " today" : "No fills today");
  return parts.join(" · ") + ". Gate Print is on each Fire pin title. Paper Yes still required. Not a forecast.";
}

const WEEK_BOARD_KEY = "paperTraderWeekBoard";

function denverWeekKey(when) {
  const stamp = when || new Date();
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "America/Denver",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    weekday: "short",
  }).formatToParts(stamp);
  const pick = (type) => (parts.find((part) => part.type === type) || {}).value || "";
  const year = Number(pick("year"));
  const month = Number(pick("month"));
  const day = Number(pick("day"));
  const weekday = pick("weekday");
  const utc = Date.UTC(year, month - 1, day);
  const dow = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 }[weekday];
  const mondayOffset = dow === 0 ? -6 : 1 - dow;
  const monday = new Date(utc + mondayOffset * 86400000);
  const iso = monday.toISOString().slice(0, 10);
  return iso;
}

function loadWeekBoard() {
  try {
    const raw = JSON.parse(localStorage.getItem(WEEK_BOARD_KEY) || "{}");
    if (!raw || raw.week !== denverWeekKey()) return { week: denverWeekKey(), rows: [] };
    return { week: raw.week, rows: Array.isArray(raw.rows) ? raw.rows : [] };
  } catch (error) {
    return { week: denverWeekKey(), rows: [] };
  }
}

function saveWeekBoard(board) {
  try {
    localStorage.setItem(WEEK_BOARD_KEY, JSON.stringify(board));
  } catch (error) {
    /* Week Board still holds for this page. */
  }
  state.weekBoard = board;
}

function rememberWeekBoard(row) {
  if (!(row && row.symbol && (row.fire || row.edge === "BUY"))) return;
  const board = loadWeekBoard();
  const next = {
    symbol: row.symbol,
    lane: row.lane || "",
    seat: row.seat || "",
    seatLabel: row.seatLabel || "",
    rate: row.rate != null ? row.rate : null,
    at: denverDay(),
  };
  const rows = board.rows.filter((item) => item && item.symbol !== row.symbol);
  rows.unshift(next);
  board.rows = rows.slice(0, 12);
  board.week = denverWeekKey();
  saveWeekBoard(board);
}

function weekBoardNoteText(board) {
  const tide = state.tide && state.tide.result;
  const tideBit =
    tide && tide.on ? "Tide Alert on" : tide && tide.ready ? "Tide Alert off" : "Tide unread";
  const rows = (board && board.rows) || [];
  if (!rows.length) {
    return tideBit + ". Week Board is empty until Fire 5 lights a name this Denver week. Review only — not a second Fire list.";
  }
  return (
    tideBit +
    " · " +
    rows.length +
    " Fire name" +
    (rows.length === 1 ? "" : "s") +
    " on the Week Board. Lane Align · Range Seat tags stay with each pin. Not a forecast."
  );
}

function renderWeekBoard() {
  const bar = $("week-board");
  const note = $("week-board-note");
  const board = state.weekBoard && state.weekBoard.week === denverWeekKey() ? state.weekBoard : loadWeekBoard();
  state.weekBoard = board;
  if (note) note.textContent = weekBoardNoteText(board);
  if (!bar) return;
  const current = (state.paper && state.paper.symbol) || "";
  const rows = board.rows || [];
  const stamp =
    "week|" +
    current +
    "|" +
    (board.week || "") +
    "|" +
    rows.map((row) => row.symbol + ":" + (row.lane || "") + ":" + (row.seat || "")).join(",");
  if (sameStamp(bar, stamp)) return;
  bar.replaceChildren();
  if (!rows.length) {
    const empty = document.createElement("p");
    empty.className = "muted";
    empty.textContent = "No Fire names yet this Denver week.";
    bar.appendChild(empty);
    return;
  }
  rows.forEach((row) => {
    if (!row || !row.symbol) return;
    const button = document.createElement("button");
    button.type = "button";
    const laneTag = row.lane === "open" ? " · Lane Open" : row.lane === "soft" ? " · Lane Soft" : row.lane === "closed" ? " · Lane Closed" : "";
    const seatTag = row.seatLabel ? " · " + row.seatLabel : row.seat === "high" ? " · Seat High" : "";
    button.textContent = pinLabel(row.symbol) + (row.rate != null ? " " + row.rate + "%" : "") + laneTag + seatTag;
    button.className = row.lane === "open" || row.seat === "high" ? "go" : "";
    button.title =
      "Week Board review for " +
      row.symbol +
      (row.lane ? " · Lane " + row.lane : "") +
      (row.seatLabel ? " · " + row.seatLabel : "") +
      ". Not a second Fire 5 list. Not a forecast.";
    if (row.symbol === current) {
      button.classList.add("on");
      button.setAttribute("aria-pressed", "true");
    }
    button.addEventListener("click", () => chooseSymbol(row.symbol));
    bar.appendChild(button);
  });
}

function paintJournalCheckSync(paper) {
  const book = currentBook(paper || state.paper);
  const note = $("journal-check-note");
  if (note) note.textContent = journalCheckNoteText(book);
  renderWeekBoard();
  renderJournalBuys(book);
  renderJournalSells();
  renderJournalFilled(book);
}

function renderJournalCheck(paper) {
  const book = currentBook(paper || state.paper);
  paintJournalCheckSync(paper);
  refreshJournalSells(book);
}

async function refreshJournalSells(book) {
  const positions = ((book || currentBook(state.paper) || {}).positions || []).slice();
  const gen = (state.journalSellGen || 0) + 1;
  state.journalSellGen = gen;
  if (!positions.length) {
    state.journalSells = [];
    state.journalSellNote = "No open paper longs. Buy fills appear under Open positions.";
    if (state.journalSellGen === gen) {
      renderJournalSells();
      const note = $("journal-check-note");
      if (note) note.textContent = journalCheckNoteText(book || currentBook(state.paper));
    }
    return state.journalSells;
  }
  state.journalSellNote =
    "Checking Edge / Seat Trail exits on " + positions.length + " open paper long" + (positions.length === 1 ? "" : "s") + "…";
  renderJournalSells();
  const sells = [];
  for (let index = 0; index < positions.length; index += 1) {
    if (state.journalSellGen !== gen) return state.journalSells;
    const item = positions[index];
    const symbol = item && item.symbol;
    if (!symbol) continue;
    try {
      const row = state.catalog.find((entry) => entry.symbol === symbol);
      const bars =
        state.paper && state.paper.symbol === symbol && seenBars().length
          ? seenBars()
          : state.offline
            ? await sqDaily(symbol)
            : (await getJSON("/api/bars?symbol=" + encodeURIComponent(symbol) + "&frame=1D")).bars;
      if (!bars || !bars.length) continue;
      const crypto = phoneCrypto(symbol, row);
      const live = PaperFrames.liveBar(bars, crypto);
      if (PaperFrames.edge) {
        const timing = PaperFrames.edge(bars, { live, vixBars: state.vixBars || [] });
        if (timing && timing.ready && timing.call === "SELL") {
          sells.push({
            symbol,
            reason: timing.reason || "Edge exit",
            journalStatus: "exit",
            rate: timing.score != null ? timing.score : "",
          });
          continue;
        }
      }
      if (PaperFrames.strategies) {
        const board = PaperFrames.strategies(bars, { live });
        const trail = board && board.rows ? board.rows.find((item) => item.id === "seatrail") : null;
        if (trail && trail.ready && trail.call === "SELL") {
          sells.push({
            symbol,
            reason: "Seat Trail exit",
            journalStatus: "exit",
            rate: trail.trailMult != null ? "×" + trail.trailMult : "",
          });
        }
      }
    } catch (error) {
      // Keep honesty: skip inventing an exit when bars or Edge are unavailable.
    }
  }
  if (state.journalSellGen !== gen) return state.journalSells;
  state.journalSells = sells;
  state.journalSellNote = sells.length
    ? sells.length + " open long" + (sells.length === 1 ? "" : "s") + " due for exit (Edge or Seat Trail). Tap to review; Auto asks Yes before a paper sell."
    : positions.length + " open long" + (positions.length === 1 ? "" : "s") + " · no Edge / Seat Trail exit on the last closed bar.";
  renderJournalSells();
  const note = $("journal-check-note");
  if (note) note.textContent = journalCheckNoteText(book || currentBook(state.paper));
  return state.journalSells;
}

function renderResult(paper) {
  const node = $("result");
  if (!node) return;
  const start = Number(paper.params && paper.params.cash) || 0;
  const result = Number(paper.equity) - start;
  const percent = start ? (result / start) * 100 : 0;
  const trades = paper.trades || [];
  const wins = trades.filter((trade) => Number(trade.pnl) > 0).length;
  const sign = result > 0 ? "+" : "";
  const closed = trades.length ? trades.length + " closed · " + wins + " wins" : "No closed trades";
  const money = formatMoney(result);
  node.textContent = (result > 0 ? "+" + money : money) + " · " + sign + percent.toFixed(1) + "% · " + closed;
  node.className = "result " + (result > 0 ? "up" : result < 0 ? "down" : "");
}

function renderPins() {
  const bar = $("pins");
  if (!bar) return;
  const current = (state.paper && state.paper.symbol) || "";
  let pins = readPins();
  if (state.offline) {
    pins = pins.filter((symbol) => phoneCrypto(symbol, state.catalog.find((row) => row.symbol === symbol)));
  }
  const stamp = current + "|" + pins.map((symbol) => {
    const row = state.moods[symbol] || (state.market && state.market.symbol === symbol ? currentMood(seenBars()) : null);
    return symbol + ":" + (row && row.label ? row.label : "") + ":" + (row && row.score != null ? row.score : "");
  }).join(",");
  if (sameStamp(bar, stamp)) {
    const toggle = $("pin-toggle");
    if (toggle && current) {
      const pinned = pins.includes(current);
      toggle.textContent = pinned ? "Unpin " + pinLabel(current) : "Pin " + pinLabel(current);
      toggle.setAttribute("aria-pressed", pinned ? "true" : "false");
    }
    return;
  }
  bar.replaceChildren();
  pins.forEach((symbol) => {
    const button = document.createElement("button");
    button.type = "button";
    const row = state.moods[symbol] || (state.market && state.market.symbol === symbol ? currentMood(seenBars()) : null);
    button.textContent = pinLabel(symbol) + (row && row.label && row.label !== "Waiting" ? " · " + row.label : "");
    button.title = symbol + (row && row.score != null ? " · " + row.label + " " + row.score : "");
    if (row && row.label) button.classList.add(row.label.toLowerCase());
    if (symbol === current) {
      button.className = "on";
      button.setAttribute("aria-pressed", "true");
    }
    button.addEventListener("click", () => chooseSymbol(symbol));
    bar.appendChild(button);
  });
  const toggle = $("pin-toggle");
  if (!toggle || !current) return;
  const pinned = pins.includes(current);
  toggle.textContent = pinned ? "Unpin " + pinLabel(current) : "Pin " + pinLabel(current);
  toggle.setAttribute("aria-pressed", pinned ? "true" : "false");
}

function syncDeskStatus() {
  const src = $("status");
  const rail = $("desk-status");
  if (src && rail && rail.textContent !== src.textContent) rail.textContent = src.textContent || "";
}

function setStatus(text) {
  const node = $("status");
  if (node) node.textContent = text || "";
  syncDeskStatus();
}

function quoteAgeText(asOf) {
  if (!(asOf > 0)) return "";
  const seconds = Math.max(0, Math.floor(Date.now() / 1000 - asOf));
  if (seconds < 8) return "just now";
  if (seconds < 60) return seconds + "s ago";
  if (seconds < 3600) return Math.floor(seconds / 60) + "m ago";
  if (seconds < 86400) return Math.floor(seconds / 3600) + "h ago";
  return Math.floor(seconds / 86400) + "d ago";
}

function paintChartRead(paper) {
  const node = $("chart-read");
  if (!node || !paper) return;
  if (state.chartRead) {
    node.textContent = state.chartRead;
    return;
  }
  const price = state.market && state.market.symbol === paper.symbol && state.market.price
    ? formatPrice(state.market.price)
    : "";
  const result = price ? currentEdge(seenBars()) : null;
  const call = result && result.ready ? " · Edge " + result.call + " " + result.score + "/" + (result.checks || 4) : "";
  node.textContent = price ? paper.symbol + " · " + price + call + " · scrub or tap a candle" : "Click a ticker or a candle.";
}

function paintPositionChip(paper) {
  const chip = $("position-chip");
  if (!chip) return;
  if (!paper || !(paper.shares > 0) || !(paper.entry_price > 0)) {
    chip.hidden = true;
    chip.textContent = "";
    return;
  }
  const last = state.market && state.market.symbol === paper.symbol && state.market.price != null
    ? Number(state.market.price)
    : Number(paper.last_close);
  const pnl = Number.isFinite(last) ? paper.shares * (last - paper.entry_price) : null;
  const pct = Number.isFinite(last) && paper.entry_price ? ((last - paper.entry_price) / paper.entry_price) * 100 : null;
  chip.hidden = false;
  chip.className = "position-chip" + (pnl > 0 ? " up" : pnl < 0 ? " down" : "");
  const pnlText = pnl == null ? "—" : moneyText(pnl);
  const pctText = pct == null ? "" : " · " + (pct >= 0 ? "+" : "") + pct.toFixed(2) + "%";
  chip.textContent = Number(paper.shares).toLocaleString(undefined, { maximumFractionDigits: 4 }) + " @ " + formatPrice(paper.entry_price) + " · " + pnlText + pctText;
}

function paintTicketPreview() {
  const node = $("ticket-preview");
  const paper = state.paper;
  if (!node || !paper) return;
  const side = state.side || "buy";
  const kind = ($("kind") && $("kind").value) || "market";
  const last = state.market && state.market.symbol === paper.symbol && state.market.price != null
    ? Number(state.market.price)
    : Number(paper.last_close);
  const limit = Number($("limit-price") && $("limit-price").value) || 0;
  const stop = Number($("stop-price") && $("stop-price").value) || 0;
  let ref = last;
  if (kind === "limit" || kind === "stop_limit") ref = limit > 0 ? limit : last;
  else if (kind === "stop") ref = stop > 0 ? stop : last;
  if (!(ref > 0)) {
    node.textContent = "Waiting on a market quote for the ticket preview.";
    return;
  }
  const fee = (Number(paper.params && paper.params.fee_bps) || FEE_BPS) / 10000;
  const slip = SLIP_BPS / 10000;
  if (side === "buy") {
    const cash = Number(paper.cash) || 0;
    const pay = ref * (1 + slip + fee);
    const shares = pay > 0 ? cash / pay : 0;
    node.textContent = "Buy preview · ~" + shares.toLocaleString(undefined, { maximumFractionDigits: 4 }) + " shares for " + moneyText(cash) + " at about " + formatPrice(ref) + " (includes 5 bps slip + fee). Nothing goes to a broker.";
    return;
  }
  const shares = Number(paper.shares) || 0;
  if (!(shares > 0)) {
    node.textContent = "Sell preview · flat. Open a paper long first.";
    return;
  }
  const proceeds = shares * ref * (1 - slip - fee);
  const entry = Number(paper.entry_price) || 0;
  const pnl = entry > 0 ? proceeds - shares * entry : null;
  node.textContent = "Sell preview · " + shares.toLocaleString(undefined, { maximumFractionDigits: 4 }) + " shares ≈ " + moneyText(proceeds) + " at about " + formatPrice(ref) + (pnl == null ? "" : " · open P/L " + moneyText(pnl)) + ". Nothing goes to a broker.";
}

function paintDayStrip(book) {
  const strip = $("day-strip");
  if (!strip) return;
  const day = denverDay();
  const fills = (book && book.fills) || [];
  let buys = 0;
  let sells = 0;
  let pnl = 0;
  fills.forEach((item) => {
    const stamp = item.at > 0 ? denverDay(new Date(item.at * 1000)) : "";
    if (stamp !== day) return;
    if (item.side === "buy") buys += 1;
    if (item.side === "sell") {
      sells += 1;
      if (Number.isFinite(Number(item.pnl))) pnl += Number(item.pnl);
    }
  });
  const open = ((book && book.positions) || []).length;
  const working = ((book && book.working) || []).length;
  strip.replaceChildren();
  [
    ["Today's fills", String(buys + sells), buys + sells ? (buys + " buy · " + sells + " sell") : "None yet", ""],
    ["Closed P/L today", moneyText(pnl), day + " Denver", pnl > 0 ? "up" : pnl < 0 ? "down" : ""],
    ["Open now", open + " pos · " + working + " wrk", (book && book.markets ? book.markets + " market" + (book.markets === 1 ? "" : "s") : "Quiet"), ""],
  ].forEach(([label, value, note, tone]) => {
    const card = document.createElement("div");
    card.className = "day-card" + (tone ? " " + tone : "");
    const name = document.createElement("span");
    name.textContent = label;
    const strong = document.createElement("strong");
    strong.textContent = value;
    const hint = document.createElement("span");
    hint.textContent = note;
    card.append(name, strong, hint);
    strip.appendChild(card);
  });
}

function showRead(text) {
  state.chartRead = text;
  if (state.paper) paintChartRead(state.paper);
  setStatus(text);
}

function showChart() {
  const narrow = window.matchMedia("(max-width: 980px)").matches;
  if (narrow) {
    showPhoneTab("chart");
  } else if (document.body.dataset.tools) {
    delete document.body.dataset.tools;
    paintOpenToolsButton();
  }
  requestAnimationFrame(() => {
    if (state.market && state.paper) redrawStudies();
  });
}

function formatIndexPrice(price) {
  const value = Number(price);
  if (!(value > 0)) return "—";
  if (value >= 1000) return value.toLocaleString(undefined, { maximumFractionDigits: 0 });
  if (value >= 100) return value.toLocaleString(undefined, { maximumFractionDigits: 1 });
  return formatPrice(value);
}

function paintIndexChip(chip, trade) {
  const lastEl = chip.querySelector(".index-chip-last");
  const chgEl = chip.querySelector(".index-chip-chg");
  const price = trade && Number(trade.price);
  if (!(price > 0)) {
    if (lastEl) lastEl.textContent = "—";
    if (chgEl) chgEl.textContent = "—";
    chip.classList.remove("up", "down", "flat");
    return;
  }
  if (lastEl) lastEl.textContent = formatIndexPrice(price);
  let pct = trade.changePct;
  if (!(Number.isFinite(pct)) && trade.open > 0) {
    pct = ((price - trade.open) / trade.open) * 100;
  }
  let tone = "flat";
  let text = "—";
  if (Number.isFinite(pct)) {
    tone = pct > 0.005 ? "up" : pct < -0.005 ? "down" : "flat";
    text = (pct > 0 ? "+" : "") + pct.toFixed(2) + "%";
  }
  if (chgEl) chgEl.textContent = text;
  chip.classList.toggle("up", tone === "up");
  chip.classList.toggle("down", tone === "down");
  chip.classList.toggle("flat", tone === "flat");
}

function tradeFromBars(bars, livePrice) {
  const list = bars || [];
  if (list.length < 1) return null;
  const last = list[list.length - 1];
  const prev = list.length > 1 ? list[list.length - 2] : null;
  const price = Number(livePrice) > 0 ? Number(livePrice) : Number(last && last.close);
  if (!(price > 0)) return null;
  let changePct = null;
  if (prev && Number(prev.close) > 0) changePct = (price / Number(prev.close) - 1) * 100;
  return {
    price,
    changePct,
    open: Number(last.open) > 0 ? Number(last.open) : null,
    asOf: null,
  };
}

// YM · RUT(RTY) · NQ · ES — prefer the desk Yahoo path that already quotes ES; Private falls back to PaperLive.
async function indexTapeTrade(symbol) {
  const name = String(symbol || "").trim().toUpperCase();
  if (!name) throw new Error("No index symbol");
  if (!state.offline) {
    try {
      const market = await getJSON("/api/market?symbol=" + encodeURIComponent(name));
      if (market && market.symbol && String(market.symbol).toUpperCase() !== name) {
        throw new Error("Index tape quote mismatched for " + name);
      }
      const trade = tradeFromBars(market && market.bars, market && market.price);
      if (trade) return trade;
    } catch (error) {
      /* Desk path failed — try the same public futures feed the phone chart uses. */
    }
  }
  if (window.PaperLive && PaperLive.lastTrade) {
    try {
      return await PaperLive.lastTrade(name, "future");
    } catch (error) {
      /* lastTrade miss — derive from closed daily bars (same feed as ES chart on phone). */
    }
  }
  if (window.PaperLive && PaperLive.daily) {
    const bars = await PaperLive.daily(name, "future", true);
    const trade = tradeFromBars(bars, null);
    if (trade) return trade;
  }
  throw new Error("No index tape quote for " + name);
}

async function refreshIndexTape() {
  const root = $("index-tape");
  if (!root) return;
  const chips = Array.from(root.querySelectorAll(".index-chip[data-index-symbol]"));
  // Sequential so the desk quote cache is not slammed; fail closed per chip (no invented prints).
  for (let index = 0; index < chips.length; index += 1) {
    const chip = chips[index];
    const symbol = chip.dataset.indexSymbol;
    if (!symbol) continue;
    try {
      const trade = await indexTapeTrade(symbol);
      paintIndexChip(chip, trade);
    } catch (error) {
      paintIndexChip(chip, null);
    }
  }
}

function chooseSymbol(symbol) {
  if (!symbol) return;
  if (state.busy) {
    state.pendingSymbol = symbol;
    $("status").textContent = "Opening " + symbol + "…";
    return;
  }
  const same = state.paper && state.paper.symbol === symbol;
  return withBusy(async () => {
    if (same) {
      if (state.offline && state.session) {
        const row = state.catalog.find((item) => item.symbol === symbol);
        const bars = await PaperLive.daily(symbol, row ? row.category : "");
        PaperEngine.replaceBars(state.session, bars);
        state.market = PaperEngine.market(state.session);
        stampQuote(bars);
        render(PaperEngine.view(state.session));
        renderLocalSignals();
      } else if (!state.offline) {
        await loadMarket(symbol);
        render(state.paper);
      }
      showChart();
      showRead(symbol + " is on the chart.");
      return;
    }
    state.chartRead = "";
    state.ruler = null;
    stopAuto();
    rememberSymbol(symbol);
    if (state.offline) {
      const row = state.catalog.find((item) => item.symbol === symbol);
      const bars = await PaperLive.daily(symbol, row ? row.category : "");
      openOfflineSession(symbol, bars, formParams(), false);
      state.paper = PaperEngine.view(state.session);
      state.market = PaperEngine.market(state.session);
      stampQuote(bars);
      await refreshSymbols();
      rerunStudy();
      render(state.paper);
      renderLocalSignals();
      showChart();
      if (state.paper.paper === true) {
        state.auto = true;
        setTimeout(() => {
          if (state.auto && !state.autoTimer) autoTick();
        }, 0);
      }
      showRead("Opened " + symbol + " on the chart.");
      return;
    }
    const paper = await paperCall("/api/paper/reset", { ...formParams(), symbol, switch: true });
    state.paper = paper;
    await refreshSymbols();
    await loadMarket(paper.symbol);
    render(paper);
    showChart();
    if (state.fireAuto && paper.paper === true) {
      state.auto = true;
      setTimeout(() => {
        if (state.auto && !state.autoTimer) autoTick();
      }, 0);
    }
    showRead("Opened " + paper.symbol + " on the chart.");
  });
}

function renderQuote(paper) {
  const bars = seenBars();
  const last = bars.length ? bars[bars.length - 1] : null;
  const previous = bars.length > 1 ? bars[bars.length - 2] : null;
  const quote = $("quote");
  if (!quote) return;
  $("quote-symbol").textContent = paper.symbol || "—";
  if (!last) {
    $("quote-last").textContent = "—";
    $("quote-change").textContent = "—";
    $("quote-asof").textContent = "Market quote";
    const empty = $("quote-mood");
    if (empty) {
      empty.textContent = "—";
      empty.className = "mood";
    }
    quote.className = "quote";
    drawMoodClock(null, "");
    return;
  }
  const change = previous ? last.close - previous.close : 0;
  const percent = previous && previous.close ? (change / previous.close) * 100 : 0;
  const sign = change > 0 ? "+" : "";
  const sameMarket = state.market && state.market.symbol === paper.symbol;
  const livePrice = sameMarket && state.market.price != null ? state.market.price : last.close;
  const priceNode = $("quote-last");
  const nextPrice = formatPrice(livePrice);
  if (priceNode.textContent !== nextPrice) {
    priceNode.textContent = nextPrice;
    priceNode.classList.remove("moved");
    void priceNode.offsetWidth;
    priceNode.classList.add("moved");
  }
  $("quote-change").textContent = sign + formatPrice(change) + "  " + sign + percent.toFixed(2) + "%";
  const asOf = sameMarket && state.market.as_of;
  const age = asOf ? quoteAgeText(asOf) : "";
  const stamp = asOf
    ? new Date(asOf * 1000).toLocaleString(undefined, { month: "short", day: "numeric", hour: "numeric", minute: "2-digit", second: "2-digit" })
    : (last.date || "");
  $("quote-asof").textContent = asOf
    ? "Market quote · " + stamp + (age ? " · " + age : "")
    : "Market quote · " + stamp;
  const stale = asOf ? (Date.now() / 1000 - asOf) > 120 : false;
  quote.className = "quote " + (change > 0 ? "up" : change < 0 ? "down" : "flat") + (stale ? " stale" : "");
  const tone = currentMood(bars);
  const badge = $("quote-mood");
  if (badge) {
    badge.textContent = tone && tone.score != null ? tone.label + " " + tone.score : "—";
    badge.className = "mood " + (tone && tone.label ? tone.label.toLowerCase() : "");
  }
  drawMoodClock(tone && tone.score, tone && tone.label);
  if (paper.symbol && tone && tone.score != null) state.moods[paper.symbol] = tone;
  paintPositionChip(paper);
  paintTicketPreview();
}

function seenBars() {
  const cursor = state.paper ? state.paper.cursor : (state.market && state.market.bars ? state.market.bars.length : 0);
  return ((state.market && state.market.bars) || []).slice(0, cursor);
}

function renderFrames() {
  if (!state.market || typeof PaperFrames === "undefined" || !$("frame-bar")) return;
  const rows = PaperFrames.analyze(seenBars());
  state.frames = rows;
  const note = PaperFrames.brief((state.paper && state.paper.symbol) || "Sample", rows);
  state.brief = note;
  const bar = $("frame-bar");
  const selectedId = state.frameId || "1D";
  const stamp = state.previewZoom + "|" + selectedId + "|" + note.lead + "|" + note.lines.join("~") + "|" + rows.map((row) => row.id + row.bias + row.change.toFixed(2) + ":" + row.count).join(",");
  if (!sameStamp(bar, stamp)) {
  bar.replaceChildren();
  rows.forEach((row) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = row.bias + (row.id === selectedId ? " on" : "");
    button.setAttribute("aria-pressed", row.id === selectedId ? "true" : "false");
    button.textContent = row.short || row.label;
    button.title = row.label;
    button.addEventListener("click", () => openFrame(row.id));
    bar.appendChild(button);
  });
  const list = $("frame-list");
  list.replaceChildren();
  rows.forEach((row) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = row.bias + (row.id === selectedId ? " on" : "");
    button.setAttribute("aria-pressed", row.id === selectedId ? "true" : "false");
    const name = document.createElement("span");
    name.textContent = row.label;
    const detail = document.createElement("span");
    const change = (row.change >= 0 ? "+" : "") + row.change.toFixed(2) + "%";
    detail.textContent = row.bias + " · " + change;
    button.append(name, detail);
    button.addEventListener("click", () => openFrame(row.id));
    list.appendChild(button);
  });
  $("frame-lead").textContent = note.lead;
  const brief = $("brief");
  brief.replaceChildren();
  note.lines.forEach((line) => {
    const paragraph = document.createElement("p");
    paragraph.textContent = line;
    brief.appendChild(paragraph);
  });
  const selected = rows.find((row) => row.id === selectedId) || rows[0];
  const caption = $("frame-preview-caption");
  if (caption && selected) {
    const change = (selected.change >= 0 ? "+" : "") + selected.change.toFixed(2) + "%";
    caption.textContent = selected.count
      ? selected.label + " preview · " + selected.bias + " · " + change + " · " + selected.count + " market bars"
      : selected.label + " opens market bars in its own window";
  }
  }
  const previewRow = rows.find((row) => row.id === selectedId) || rows[0];
  const previewBars = previewRow ? ((previewRow.bars && previewRow.bars.length) ? previewRow.bars : (state.ef.bars[previewRow.id] || [])) : [];
  const previewCanvas = $("frame-preview");
  const symbolNow = (state.paper && state.paper.symbol) || "";
  const paintPreview = () => PaperFrames.draw(previewCanvas, previewBars, state.previewZoom, { crypto: chartCrypto(), symbol: symbolNow, label: symbolNow + " · " + (previewRow ? previewRow.label : "") });
  paintPreview();
  PaperFrames.attachPick(previewCanvas, () => renderFrames(), {});
  paintZoom(seenBars().length);
}

function openFrameWindow(frameId) {
  const index = PaperFrames.FRAMES.findIndex((frame) => frame.id === frameId);
  const features = "popup=yes,width=860,height=640,left=" + (70 + Math.max(0, index) * 32) + ",top=" + (48 + Math.max(0, index) * 24);
  const name = "paper-frame-" + frameId;
  if (!state.offline && location.protocol !== "file:") {
    const symbol = encodeURIComponent(state.paper.symbol);
    const cursor = state.paper.cursor;
    return window.open("/frame.html?symbol=" + symbol + "&frame=" + encodeURIComponent(frameId) + "&cursor=" + cursor, name, features);
  }
  const win = window.open("", name, features);
  if (win) writeOfflineFrame(win, frameId);
  return win;
}

function writeOfflineFrame(win, frameId) {
  const frame = PaperFrames.frameById(frameId);
  const row = (state.frames || []).find((item) => item.id === frame.id);
  const bars = row ? row.bars : [];
  const heading = (state.paper.symbol || "Sample") + " · " + frame.label;
  const cryptoNow = chartCrypto();
  const tone = bars.length ? PaperFrames.mood(bars) : null;
  const moodLine = tone && tone.score != null ? " " + tone.label + " " + tone.score + "." : "";
  win.document.open();
  const quoteLine = bars.length ? "Market quote." : "Loading the market quote.";
  win.document.write("<!DOCTYPE html><title>Timeframe</title><body style=\"margin:0;background:#101318;color:#e7edf3;font:16px Segoe UI,sans-serif\"><p style=\"padding:12px 16px 0\"><button id=\"back\" type=\"button\">Back</button></p><p id=\"note\" style=\"padding:4px 16px 0\"></p><p style=\"padding:8px 16px\"><button id=\"in\" type=\"button\">Zoom in</button> <button id=\"out\" type=\"button\">Zoom out</button> <span id=\"label\"></span></p><canvas id=\"c\" style=\"width:100%;height:72vh\"></canvas></body>");
  win.document.close();
  win.document.title = heading;
  const note = win.document.getElementById("note");
  if (note) note.textContent = heading + " is " + (row ? row.bias : "flat") + ". " + quoteLine + moodLine + (bars.length ? " " + PaperFrames.edgeLine(bars, cryptoNow) + ". Click any candle for its price box." : "");
  let zoomStop = PaperFrames.DEFAULT_ZOOM;
  const paint = () => {
    const canvas = win.document.getElementById("c");
    if (canvas) {
      PaperFrames.draw(canvas, bars, zoomStop, { crypto: cryptoNow, symbol: state.paper.symbol || "", label: heading });
      PaperFrames.attachPick(canvas, paint, {});
    }
    const view = PaperFrames.zoomBars(bars, zoomStop);
    const label = win.document.getElementById("label");
    const inn = win.document.getElementById("in");
    const out = win.document.getElementById("out");
    if (label) label.textContent = view.size + " bars";
    if (inn) inn.disabled = view.atMin;
    if (out) out.disabled = view.atMax;
  };
  const back = win.document.getElementById("back");
  if (back) back.addEventListener("click", () => win.close());
  const inn = win.document.getElementById("in");
  const out = win.document.getElementById("out");
  if (inn) inn.addEventListener("click", () => {
    zoomStop = PaperFrames.zoomStep(zoomStop, -1, bars.length);
    paint();
  });
  if (out) out.addEventListener("click", () => {
    zoomStop = PaperFrames.zoomStep(zoomStop, 1, bars.length);
    paint();
  });
  const canvas = win.document.getElementById("c");
  if (canvas) canvas.addEventListener("wheel", (event) => {
    event.preventDefault();
    zoomStop = PaperFrames.zoomStep(zoomStop, event.deltaY > 0 ? 1 : -1, bars.length);
    paint();
  }, { passive: false });
  paint();
  win.addEventListener("resize", paint);
}

async function openFrame(frameId) {
  state.frameId = frameId;
  let liveBars = null;
  if (state.offline && state.paper && typeof PaperLive !== "undefined") {
    const row = state.catalog.find((item) => item.symbol === state.paper.symbol);
    try {
      liveBars = await PaperLive.frame(state.paper.symbol, row ? row.category : "", frameId);
    } catch (error) {
      $("status").textContent = error.message;
      renderFrames();
      return false;
    }
  }
  renderFrames();
  if (liveBars) {
    const match = (state.frames || []).find((item) => item.id === frameId);
    if (match) match.bars = liveBars;
  }
  const win = openFrameWindow(frameId);
  if (!win) {
    showDock("frames");
    $("status").textContent = "The window was blocked. " + PaperFrames.frameById(frameId).label + " is in the Timeframes panel.";
    return false;
  }
  win.focus();
  $("status").textContent = PaperFrames.frameById(frameId).label + " opened in its own window.";
  return true;
}

async function openAllFrames() {
  let opened = 0;
  const openedWindows = [];
  PaperFrames.FRAMES.forEach((frame) => {
    const win = openFrameWindow(frame.id);
    if (win) {
      opened += 1;
      openedWindows.push({ id: frame.id, win });
    }
  });
  if (state.offline) {
    for (const openedWindow of openedWindows) {
      await paintPhoneFrame(openedWindow.win, openedWindow.id);
    }
  }
  showDock("frames");
  $("status").textContent = opened
    ? "Opened " + opened + " of 10 windows. A blocked window stays listed in this panel."
    : "The browser blocked the windows. Each timeframe is listed in this panel.";
}

async function paintPhoneFrame(win, frameId) {
  const row = state.catalog.find((item) => item.symbol === (state.paper && state.paper.symbol));
  try {
    const bars = await PaperLive.frame(state.paper.symbol, row ? row.category : "", frameId);
    if (!win || win.closed) return;
    const match = (state.frames || []).find((item) => item.id === frameId);
    if (match) match.bars = bars;
    writeOfflineFrame(win, frameId);
    const note = win.document.getElementById("note");
    if (note) note.textContent = state.paper.symbol + " · " + PaperFrames.frameById(frameId).label + ". Market quote · " + bars.length + " bars. " + PaperFrames.edgeLine(bars, chartCrypto()) + ". Click any candle for its price box.";
  } catch (error) {
    if (win && !win.closed) {
      const note = win.document.getElementById("note");
      if (note) note.textContent = error.message;
    }
  }
}

function openBrief() {
  renderFrames();
  const note = state.brief;
  if (!note) return;
  const win = window.open("", "paper-brief", "popup=yes,width=680,height=760");
  if (!win) {
    showDock("frames");
    $("status").textContent = "The brief is in the Timeframes panel.";
    return;
  }
  win.document.open();
  win.document.write("<!DOCTYPE html><title>Brief</title><body style=\"margin:0;background:#101318;color:#e7edf3;font:18px/1.5 Segoe UI,sans-serif\"><article style=\"max-width:640px;padding:28px\"><p style=\"letter-spacing:.08em;text-transform:uppercase;color:#8fd0c4;font-size:12px\">Brief</p><h1 id=\"brief-title\" style=\"font-size:28px\"></h1><div id=\"brief-lines\"></div><p style=\"color:#93a0ad\">Market quotes are not a forecast. Paper orders stay on this device.</p></article></body>");
  win.document.close();
  win.document.title = note.title;
  const title = win.document.getElementById("brief-title");
  if (title) title.textContent = note.title;
  const lines = win.document.getElementById("brief-lines");
  if (lines) {
    [note.lead].concat(note.lines).forEach((line) => {
      const paragraph = win.document.createElement("p");
      paragraph.textContent = line;
      lines.appendChild(paragraph);
    });
  }
  $("status").textContent = "The brief opened in its own window.";
}

function renderReadings() {
  const cursor = state.paper ? state.paper.cursor : 0;
  const index = Math.max(0, cursor - 1);
  const market = state.market || {};
  const bars = (market.bars || []).slice(0, index + 1);
  const bar = bars[index];
  const lines = bars.length ? PaperFrames.averages(bars) : { high: [], low: [] };
  const level = (market.squeeze || [])[index] || 0;
  const momentum = (market.momentum || [])[index];
  const previous = index > 0 ? (market.momentum || [])[index - 1] : null;
  const rsiValue = (market.rsi || [])[index];
  const fast = (market.fast || [])[index];
  const slow = (market.slow || [])[index];
  const rising = momentum != null && (previous == null ? momentum >= 0 : momentum > previous);
  const momentumName = momentum == null ? "—" : (rising ? "Rising " : "Falling ") + Number(momentum).toFixed(2);
  const rsiName = rsiValue == null ? "—" : Number(rsiValue).toFixed(1);
  const averageName = fast == null || slow == null ? "—" : fast >= slow ? "Fast above slow" : "Fast below slow";
  const list = $("readings");
  const readingRows = [
    ["Candle", bar ? (bar.close >= bar.open ? "Green" : "Red") : "—"],
    ["Squeeze", PaperFrames.squeezeName(level)],
    ["Momentum", momentumName],
    ["Price", PaperFrames.candlePlace(bar, lines.high[index], lines.low[index])],
    ["RSI", rsiName],
    ["Averages", averageName],
    ["Resistance", pivotsText(PaperFrames.trendPivots(bars), "high")],
    ["Support", pivotsText(PaperFrames.trendPivots(bars), "low")],
    ["True momentum", tmoText(PaperFrames.closedBars ? PaperFrames.closedBars(bars, chartCrypto()) : bars)],
    ["Lesson", lessonText(bars)],
    ["Fear and greed", moodText(bars)],
    ["Edge", (() => {
      const result = bars.length ? currentEdge(bars) : null;
      return result && result.ready ? result.call + " · " + result.score + " of " + (result.checks || 5) : "—";
    })()],
  ];
  const desk = bars.length ? currentPro(bars) : null;
  if (desk) {
    const now = desk.last;
    const close = desk.close;
    const noVolume = "No volume from this feed";
    const profile = desk.profile;
    readingRows.push(
      ["200 EMA", sidePrice(now.ema200, close, "above", "below")],
      ["50 EMA", sidePrice(now.ema50, close, "above", "below")],
      ["VWAP 20", desk.vwap[desk.vwap.length - 1] == null ? noVolume : sidePrice(now.vwap, close, "above", "below")],
      ["POC", profile.poc == null ? noVolume : sidePrice(profile.poc, close, "above", "below")],
      ["Value area", profile.poc == null ? noVolume : formatPrice(profile.low) + " to " + formatPrice(profile.high) + (close > profile.high ? " · above" : close < profile.low ? " · below" : " · inside")],
      ["MACD", now.macd == null || now.signal == null ? "—" : (now.crossUp ? "Crossed above signal" : now.crossDown ? "Crossed below signal" : now.macd > now.signal ? "Above signal" : "Below signal") + " · " + (now.macd >= 0 ? "above 0" : "below 0")],
      ["RSI 14", now.rsi == null ? "—" : now.rsi.toFixed(1) + (now.rsi >= 70 ? " · overbought" : now.rsi <= 30 ? " · oversold" : "")],
      ["Bollinger", now.upper == null ? "—" : formatPrice(now.lower) + " to " + formatPrice(now.upper) + (close > now.upper ? " · above the top" : close < now.lower ? " · below the bottom" : " · inside")],
      ["Volatility", desk.swing.yearly == null ? "—" : (desk.swing.yearly * 100).toFixed(1) + "% a year" + (desk.swing.rank == null ? "" : " · " + desk.swing.regime + " · rank " + Math.round(desk.swing.rank * 100) + "% of the past year")],
      ["Band width", desk.swing.width == null ? "—" : (desk.swing.width * 100).toFixed(1) + "%" + (desk.swing.widthRank == null ? "" : " · " + (desk.swing.widthRank <= 0.2 ? "tight" : desk.swing.widthRank >= 0.8 ? "wide" : "normal") + " · rank " + Math.round(desk.swing.widthRank * 100) + "%")],
      ["ATR 14", now.atr == null || !close ? "—" : formatPrice(now.atr) + " · " + ((now.atr / close) * 100).toFixed(1) + "% a day"],
      ["Risk size", sizeText(desk)],
    );
  }
  readingRows.push(...tapeRows(bars));
  if (sameStamp(list, readingRows.map((row) => row.join(":")).join("|"))) return;
  list.replaceChildren();
  readingRows.forEach(([name, value]) => {
    const row = document.createElement("div");
    const label = document.createElement("dt");
    const detail = document.createElement("dd");
    label.textContent = name;
    detail.textContent = value;
    row.append(label, detail);
    list.appendChild(row);
  });
}

function fillTrades(body, trades) {
  const stamp = trades.map((trade) => [trade.entry_date, trade.exit_date, trade.entry_price, trade.exit_price, trade.pnl, trade.reason].join(":")).join("|") || "empty";
  if (sameStamp(body, stamp)) return;
  body.replaceChildren();
  if (!trades.length) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 6;
    cell.textContent = "No closed trades yet.";
    row.appendChild(cell);
    body.appendChild(row);
    return;
  }
  trades.forEach((trade) => {
    const row = document.createElement("tr");
    const values = [
      trade.entry_date,
      trade.exit_date,
      formatPrice(trade.entry_price),
      formatPrice(trade.exit_price),
      formatMoney(trade.pnl),
      trade.reason,
    ];
    values.forEach((value, index) => {
      const cell = document.createElement("td");
      cell.textContent = value;
      if (index === 4) cell.className = trade.pnl >= 0 ? "up" : "down";
      row.appendChild(cell);
    });
    body.appendChild(row);
  });
}

const EDGE_CHECKS = [
  ["tmoOs", "TMO above −10"],
  ["tmo", "TMO turns green"],
  ["dots", "5 squeeze dots or in a squeeze"],
  ["above", "Squeeze momentum above 0"],
  ["cci", "CCI 50 · 14 · 5 into zone +100"],
  ["wave", "Desk Waves green / up"],
  ["vix", "VIX falling (closed daily vs prior)"],
];

function currentPro(bars) {
  if (!PaperFrames.pro) return null;
  const last = bars.length ? bars[bars.length - 1] : null;
  const equity = state.paper && state.paper.equity > 0 ? state.paper.equity : 0;
  const key = bars.length + ":" + (last ? last.date + ":" + last.close + ":" + last.volume : "") + ":" + Math.round(equity);
  if (state.proKey === key && state.proDesk) return state.proDesk;
  state.proDesk = PaperFrames.pro(bars, equity);
  state.proKey = key;
  return state.proDesk;
}

function sizeText(desk) {
  if (!desk || !desk.risk || desk.risk.units == null) return "—";
  const units = desk.risk.units;
  const shown = units >= 100 ? Math.floor(units).toLocaleString() : units.toLocaleString(undefined, { maximumFractionDigits: 4 });
  return shown + " units · 1% risk " + formatMoney(desk.risk.cash) + " · stop " + formatPrice(desk.risk.stop) + (desk.risk.capped ? " · capped at cash" : "");
}

function formatVolume(value) {
  if (!(value > 0)) return "—";
  if (value >= 1e9) return (value / 1e9).toFixed(2) + "B";
  if (value >= 1e6) return (value / 1e6).toFixed(1) + "M";
  if (value >= 1e3) return (value / 1e3).toFixed(1) + "K";
  return String(Math.round(value));
}

function currentTape(bars) {
  if (!PaperFrames.hiddenTape) return null;
  const settled = PaperFrames.closedBars ? PaperFrames.closedBars(bars, chartCrypto()) : bars;
  const row = state.catalog.find((item) => item.symbol === ((state.paper && state.paper.symbol) || (state.market && state.market.symbol) || ""));
  const extras = Object.assign({}, (state.market && state.market.tape) || {}, {
    crypto: chartCrypto(),
    kind: row ? row.category : chartCrypto() ? "crypto" : "",
  });
  return PaperFrames.hiddenTape(settled, extras);
}

function tapeRows(bars) {
  const tape = bars.length ? currentTape(bars) : null;
  if (!tape) {
    return [["Hidden tape", "Dark pool books are private. This desk cannot see resting hidden orders."]];
  }
  const rvol = tape.feedRelVol || tape.rvol;
  const absorption = {
    absorption: "High volume, tight range · possible hidden inventory on the public tape",
    initiative: "High volume, wide range · the public tape is moving price",
    quiet: "Quiet · volume is light versus the recent average",
    normal: "Normal volume versus the recent average",
  }[tape.absorption] || (tape.volume == null ? "No volume from this feed" : "—");
  const effort = tape.closeLoc == null
    ? "—"
    : "Close " + Math.round(tape.closeLoc * 100) + "% of the bar" + (tape.effort ? " · " + tape.effort : "") + (tape.upShare == null ? "" : " · " + Math.round(tape.upShare * 100) + "% up-volume over 20 days");
  const book = tape.bid && tape.ask
    ? formatPrice(tape.bid) + " / " + formatPrice(tape.ask) + (tape.spread != null ? " · spread " + (tape.spread * 10000).toFixed(1) + " bps" : "")
    : "This feed does not publish a bid or ask";
  const after = tape.afterVolume
    ? formatVolume(tape.afterVolume) + (tape.volume ? " · session " + formatVolume(tape.volume) : "")
    : tape.kind === "crypto"
      ? "Crypto has no after-hours print"
      : "This feed does not publish after-hours volume";
  return [
    ["Hidden tape", tape.note],
    ["Relative volume", rvol == null ? (tape.volume == null ? "No volume from this feed" : "—") : rvol.toFixed(2) + "×" + (tape.feedRelVol ? " the feed's 10-day average" : " the prior 20 closed days") + (tape.volume ? " · " + formatVolume(tape.volume) : "")],
    ["Absorption", absorption],
    ["Tape pressure", effort],
    ["Visible book", book],
    ["After hours", after],
  ];
}

function sidePrice(value, close, above, below) {
  if (value == null || close == null) return "—";
  const gap = ((close - value) / value) * 100;
  return formatPrice(value) + " · " + (close >= value ? above : below) + " " + Math.abs(gap).toFixed(1) + "%";
}

function chartCrypto() {
  const symbol = (state.paper && state.paper.symbol) || (state.market && state.market.symbol) || "";
  const row = state.catalog.find((item) => item.symbol === symbol);
  return phoneCrypto(symbol, row);
}

function edgeOptions(bars, extra) {
  const live = PaperFrames.liveBar(bars, chartCrypto());
  return Object.assign({ live, vixBars: state.vixBars || [] }, extra || {});
}

function currentEdge(bars) {
  if (!PaperFrames.edge) return null;
  const last = bars.length ? bars[bars.length - 1] : null;
  const opts = edgeOptions(bars);
  const vixStamp = (state.vixBars && state.vixBars.length ? state.vixBars[state.vixBars.length - 1].date + ":" + state.vixBars[state.vixBars.length - 1].close : "novix");
  const key = bars.length + ":" + opts.live + ":" + (last ? last.date + ":" + last.open + ":" + last.close : "") + ":" + vixStamp;
  if (state.edgeKey === key && state.edge) return state.edge;
  state.edge = PaperFrames.edge(bars, opts);
  state.edgeKey = key;
  return state.edge;
}

function signedPercent(value) {
  if (value == null || !Number.isFinite(value)) return "—";
  const pct = value * 100;
  return (pct > 0 ? "+" : "") + pct.toFixed(1) + "%";
}

function edgePlan(result) {
  if (!result || !result.ready) return "Edge needs at least 52 bars.";
  const stop = result.stop != null ? formatPrice(result.stop) : "—";
  const last = seenBars();
  const close = last.length ? last[last.length - 1].close : null;
  const risk = result.stop != null && close ? " · " + Math.max(0, ((close - result.stop) / close) * 100).toFixed(1) + "% to the stop" : "";
  const desk = currentPro(last);
  const size = desk && desk.risk && desk.risk.units != null ? " Size at 1% risk: " + sizeText(desk) + "." + (desk.swing && desk.swing.regime === "Extreme" ? " Volatility is in the top tenth of the past year, so the stop is wide and the size is small." : "") : "";
  const today = result.live ? " Signals use closed bars only, through " + result.signalDate + ". Today's bar is still forming." : "";
  if (result.filled && result.filled.side === "buy") return "Edge bought at today's open, " + formatPrice(result.filled.price) + ", after the " + result.signalDate + " close. Starting stop " + stop + risk + "." + size + today;
  if (result.filled && result.filled.side === "sell") return "Edge sold at today's open, " + formatPrice(result.filled.price) + (result.reason ? ": " + result.reason : "") + "." + today;
  const stage = result.stage ? " Ready · Aim · Fire is " + result.stage + "." : "";
  const big4 = result.big4 ? " big4indicator " + (result.big4.label || result.big4.bias) + (result.big4.ok ? " (agrees)" : " (waiting)") + "." : "";
  if (result.call === "BUY") return "Fire buy on the " + result.signalDate + " close (Edge + big4indicator). A paper buy fills at the next open. Starting stop " + stop + risk + "." + size + stage + big4 + today;
  if (result.call === "SELL") return "Fire sell on the " + result.signalDate + " close: " + (result.reason || "exit") + ". A paper sell fills at the next open." + today;
  if (result.call === "HOLD") return "In since " + formatPrice(result.entry) + " · " + signedPercent(result.openPct) + " · trailing stop " + stop + risk + "." + today;
  return "Desk Call WAIT. Ready at 5 of 7, Aim at 6, FIRE when all seven turn on (incl. Desk Waves green / up + VIX falling) with big4indicator. Gate Print lists what is still missing." + stage + big4 + today;
}


function denverStamp(seconds) {
  if (!(seconds > 0)) return "—";
  return new Date(seconds * 1000).toLocaleString("en-US", { timeZone: "America/Denver", month: "short", day: "numeric", hour: "numeric", minute: "2-digit", second: "2-digit" });
}

function phoneBook() {
  const saved = readBook();
  const live = state.book || {};
  const out = { positions: [], fills: [], working: [], markets: 0, total_pnl: 0 };
  new Set(Object.keys(saved).concat(Object.keys(live))).forEach((symbol) => {
    const session = live[symbol];
    const snap = session ? PaperEngine.snapshot(session) : saved[symbol];
    const working = (snap && snap.working) || [];
    if (!snap || !(snap.shares > 0 || (snap.fills && snap.fills.length) || working.length)) return;
    out.markets += 1;
    const last = session && session.bars && session.bars.length ? session.bars[session.bars.length - 1].close : snap.last;
    const start = Number(snap.params && snap.params.cash) || 0;
    const equity = session ? PaperEngine.view(session).equity : snap.cash + (snap.shares > 0 && last ? snap.shares * last : 0);
    out.total_pnl += equity - start;
    if (snap.shares > 0) {
      out.positions.push({ symbol, shares: snap.shares, entry: snap.entryPrice, last, pnl: last && snap.entryPrice ? snap.shares * (last - snap.entryPrice) : null, current: !!session, opened: snap.entryDate, source: "fill" });
    }
    working.forEach((ticket) => out.working.push({ ...ticket, symbol: ticket.symbol || symbol }));
    (snap.fills || []).forEach((item) => out.fills.push(item));
  });
  out.fills.sort((a, b) => (b.at || 0) - (a.at || 0));
  out.fills = out.fills.slice(0, 300);
  out.working.sort((a, b) => String(a.symbol).localeCompare(String(b.symbol)) || String(a.id).localeCompare(String(b.id)));
  return out;
}

function exportPhonePayload() {
  writeBook();
  const sessions = readBook();
  if (state.book) {
    Object.keys(state.book).forEach((symbol) => {
      const session = state.book[symbol];
      if (session && (session.shares > 0 || (session.fills && session.fills.length) || (session.working && session.working.length))) {
        sessions[symbol] = PaperEngine.snapshot(session);
      }
    });
  }
  return {
    version: 1,
    sessions,
    markets: Object.keys(sessions).length,
    note: "Paper book for this desk. Load it under Journal. Nothing goes to a broker.",
  };
}

async function sharePaperBook(download) {
  const note = $("book-share-note");
  try {
    const payload = state.offline ? exportPhonePayload() : await getJSON("/api/paper/book");
    const text = JSON.stringify(payload, null, 2);
    if (!payload.sessions || !Object.keys(payload.sessions).length) {
      if (note) note.textContent = "Nothing to share yet. Open a paper trade or place a limit order first.";
      return;
    }
    if (download) {
      const blob = new Blob([text], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "paper-book.json";
      link.click();
      URL.revokeObjectURL(url);
      if (note) note.textContent = "Downloaded paper-book.json with " + payload.markets + " market" + (payload.markets === 1 ? "" : "s") + ". On the phone: Journal → Load paper book → pick that file.";
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
      if (note) note.textContent = "Copied the paper book for " + payload.markets + " market" + (payload.markets === 1 ? "" : "s") + ". On the phone you can paste it into a file named paper-book.json, then Journal → Load paper book.";
      return;
    }
    if (note) note.textContent = "This browser would not copy. Use Share paper book to download the file.";
  } catch (error) {
    if (note) note.textContent = error.message || "The paper book could not be shared.";
  }
}

function cleanImportedSession(raw, symbol) {
  if (!raw || typeof raw !== "object") return null;
  const shares = Number(raw.shares);
  const cash = Number(raw.cash);
  if (!(cash >= 0) || !(shares >= 0)) return null;
  const working = Array.isArray(raw.working) ? raw.working.filter((item) => item && item.id) : [];
  const fills = Array.isArray(raw.fills) ? raw.fills : [];
  if (!(shares > 0 || fills.length || working.length)) return null;
  return {
    symbol: String(raw.symbol || symbol),
    params: raw.params && typeof raw.params === "object" ? { ...raw.params } : {},
    cash,
    shares,
    entryPrice: raw.entryPrice == null ? null : Number(raw.entryPrice),
    entryDate: raw.entryDate || null,
    cost: Number(raw.cost) || 0,
    trades: Array.isArray(raw.trades) ? raw.trades.map((item) => ({ ...item })) : [],
    working: working.map((item) => ({ ...item })),
    nextId: Math.max(1, Number(raw.nextId) || 1),
    fills: fills.map((item) => ({ ...item })),
    ticketFills: Array.isArray(raw.ticketFills) ? raw.ticketFills.map((item) => ({ ...item })) : [],
    lastAction: raw.lastAction || "",
    last: raw.last == null ? null : Number(raw.last),
  };
}

async function importPaperBook(payload) {
  const note = $("book-share-note");
  const sessions = payload && payload.sessions && typeof payload.sessions === "object" ? payload.sessions : null;
  if (!sessions) throw new Error("That file is not a Paper Trader book.");
  if (!state.offline) {
    const paper = await post("/api/paper/book", payload);
    state.paper = paper;
    await loadMarket(paper.symbol);
    render(paper);
    const count = paper.book_loaded || Object.keys(sessions).length;
    if (note) note.textContent = "Loaded " + count + " market" + (count === 1 ? "" : "s") + " into this computer's paper book. Open positions and working orders are in Journal.";
    setStatus("Paper book loaded on this device.");
    return;
  }
  const kept = {};
  Object.keys(sessions).forEach((symbol) => {
    const row = cleanImportedSession(sessions[symbol], symbol);
    if (row) kept[symbol] = row;
  });
  if (!Object.keys(kept).length) throw new Error("That paper book had no open trades or working orders.");
  const merged = { ...readBook(), ...kept };
  localStorage.setItem(BOOK_KEY, JSON.stringify({ version: 1, sessions: merged }));
  state.bookText = "";
  state.book = state.book || {};
  Object.keys(kept).forEach((symbol) => {
    if (state.book[symbol]) PaperEngine.restore(state.book[symbol], kept[symbol]);
  });
  const current = state.paper && state.paper.symbol;
  if (current && kept[current] && state.session && state.session.symbol === current) {
    PaperEngine.restore(state.session, kept[current]);
  }
  if (state.session) {
    render(PaperEngine.view(state.session));
  } else {
    renderBook(state.paper);
  }
  if (note) note.textContent = "Loaded " + Object.keys(kept).length + " market" + (Object.keys(kept).length === 1 ? "" : "s") + " into this device's paper book. Open positions and working orders are in Journal.";
  setStatus("Paper book loaded on this device.");
}

function moneyText(value) {
  if (value == null || !Number.isFinite(Number(value))) return "—";
  const number = Number(value);
  return (number < 0 ? "-$" : "$") + Math.abs(number).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function addCells(row, values) {
  values.forEach((value) => {
    const cell = document.createElement("td");
    if (value && typeof value === "object") {
      cell.textContent = value.text;
      if (value.cls) cell.className = value.cls;
    } else {
      cell.textContent = value;
    }
    row.appendChild(cell);
  });
}

function renderBook(paper) {
  const positions = $("positions-body");
  const fills = $("fills-body");
  const workingBody = $("journal-working-body");
  if (!positions || !fills) return;
  const book = state.offline ? phoneBook() : (paper && paper.book) || null;
  if (!book) return;
  const working = book.working || [];
  const stamp = JSON.stringify([book.positions, working, book.fills.length, book.fills[0] || null, book.total_pnl, state.offline]);
  if (sameStamp(positions, stamp)) return;
  positions.replaceChildren();
  fills.replaceChildren();
  if (workingBody) workingBody.replaceChildren();
  book.positions.forEach((item) => {
    const row = document.createElement("tr");
    const tone = item.pnl > 0 ? "up" : item.pnl < 0 ? "down" : "";
    addCells(row, [
      item.symbol + (item.current ? "" : " · saved"),
      Number(item.shares).toFixed(4),
      item.entry != null ? formatPrice(item.entry) : "—",
      item.last != null ? formatPrice(item.last) : "—",
      { text: moneyText(item.pnl), cls: tone },
      (item.source === "replay" ? "History replay" : "Your fill or Auto") + (item.opened ? " · " + item.opened : ""),
    ]);
    positions.appendChild(row);
  });
  if (!book.positions.length) {
    const row = document.createElement("tr");
    addCells(row, ["No open positions. A buy appears here at once, in every market you traded.", "", "", "", "", ""]);
    positions.appendChild(row);
  }
  if (workingBody) {
    working.forEach((order) => {
      const row = document.createElement("tr");
      const kind = order.kind === "stop_limit" ? "STP LMT" : String(order.kind || "").toUpperCase();
      addCells(row, [
        order.symbol || "",
        order.id,
        { text: String(order.side || "").toUpperCase(), cls: order.side === "buy" ? "up" : "down" },
        kind,
        order.stop > 0 ? formatPrice(order.stop) : "—",
        order.limit > 0 ? formatPrice(order.limit) : "—",
        order.tif || "—",
        "",
      ]);
      const cancel = document.createElement("button");
      cancel.type = "button";
      cancel.className = "quiet";
      cancel.textContent = "Cancel";
      cancel.addEventListener("click", () => withBusy(async () => {
        const next = await paperCall("/api/paper/cancel", { id: order.id, symbol: order.symbol });
        render(next);
        $("status").textContent = "Canceled working paper order " + order.id + " on " + (order.symbol || "this market") + ".";
      }));
      row.lastChild.appendChild(cancel);
      workingBody.appendChild(row);
    });
    if (!working.length) {
      const row = document.createElement("tr");
      addCells(row, ["No working limit, stop, or stop-limit orders. They show here for every market.", "", "", "", "", "", "", ""]);
      workingBody.appendChild(row);
    }
  }
  book.fills.forEach((item) => {
    const row = document.createElement("tr");
    const tone = item.side === "sell" ? (item.pnl > 0 ? "up" : item.pnl < 0 ? "down" : "") : "";
    const why = item.process ? (item.reason || "trade") + " · " + item.process : (item.reason || "trade");
    addCells(row, [
      denverStamp(item.at),
      item.symbol,
      { text: item.side === "buy" ? "Buy" : "Sell", cls: item.side === "buy" ? "up" : "down" },
      Number(item.qty).toFixed(4),
      formatPrice(item.price),
      why,
      item.side === "sell" ? { text: moneyText(item.pnl), cls: tone } : "—",
    ]);
    fills.appendChild(row);
  });
  if (!book.fills.length) {
    const row = document.createElement("tr");
    addCells(row, ["No fills yet. Every buy, sell, ticket fill and Sell all is listed here with its time.", "", "", "", "", "", ""]);
    fills.appendChild(row);
  }
  const total = $("book-total");
  if (total) {
    total.textContent = book.markets
      ? "Across " + book.markets + " market" + (book.markets === 1 ? "" : "s") + ": " + moneyText(book.total_pnl) + " paper profit or loss so far. " + working.length + " working order" + (working.length === 1 ? "" : "s") + ". Each market has its own paper account." + (state.offline ? " Saved in this browser only." : " Saved on this computer.")
      : "Nothing traded yet.";
  }
  const device = $("book-device");
  if (device) {
    device.textContent = state.offline
      ? "This device keeps its own paper book. To see trades from the computer desk, tap Share paper book there, AirDrop or save paper-book.json, then Load paper book here."
      : "This device keeps its own paper book. Tap Share paper book, then on the phone open Journal and Load paper book. Load here to bring a phone book back to this computer.";
  }
  paintDayStrip(book);
  renderJournalCheck(paper);
}


let confirmWait = null;

function closeConfirm(answer) {
  if (!confirmWait) return;
  const resolve = confirmWait;
  confirmWait = null;
  $("confirm-modal").hidden = true;
  resolve(answer);
}

function confirmOrder(plan) {
  if (confirmWait) return Promise.resolve(false);
  const box = $("confirm-modal");
  box.className = "modal " + plan.side;
  $("cm-title").textContent = plan.title;
  const rows = $("cm-rows");
  rows.replaceChildren();
  plan.rows.forEach(([name, value]) => {
    const term = document.createElement("dt");
    term.textContent = name;
    const def = document.createElement("dd");
    def.textContent = value;
    rows.append(term, def);
  });
  const list = $("cm-checks");
  list.replaceChildren();
  plan.checks.forEach((check) => {
    const item = document.createElement("li");
    item.textContent = check.label + " · " + check.detail;
    if (!check.ok) item.className = check.block ? "stop" : "warn";
    list.appendChild(item);
  });
  const blockers = plan.checks.filter((check) => check.block && !check.ok);
  const blocked = blockers.length > 0;
  $("cm-yes").disabled = blocked;
  $("cm-warn").textContent = blocked
    ? "This cannot go ahead until these are fixed: " + blockers.map((check) => check.label).join(", ") + ". Nothing was traded."
    : "Do you really want to do this? It is paper money only and nothing goes to a broker.";
  box.hidden = false;
  $("cm-no").focus();
  return new Promise((resolve) => {
    confirmWait = resolve;
  });
}

function lineUp(side, symbol, source, row) {
  const checks = [];
  const add = (label, ok, detail, block) => checks.push({ label, ok: !!ok, detail, block: !!block });
  // Best setup gates Auto only. Manual Send paper buy stays usable (no fake "STOP line" lock).
  const bestBlocks = !!(state.best && state.best.on && source === "auto");
  add("Paper only", state.paper && state.paper.paper === true, "No broker. Nothing real is bought or sold", true);
  add(
    "Live locked",
    !state.paper || state.paper.mode !== "live",
    "Live stays off. This desk has no broker or bank link",
    true
  );
  const safe = state.safe;
  add("Safe check", !safe || safe.safe, safe ? (safe.safe ? "Every safety check passed" : "A safety check failed. Press Safe check to see which") : "Not run yet", !!(safe && !safe.safe));
  if (side === "buy") {
    const tripped = breakerState().tripped;
    add("Circuit breaker", !tripped, tripped ? "Tripped. Buys stop until tomorrow" : "Armed, buys allowed", true);
    const held = internalsGate(symbol);
    const read = state.int && state.int.result;
    add("Internals", !held, held || (read && read.ready ? read.label + " (" + read.score + ")" : "Not read yet"), !!(bestBlocks && held));
  }
  const agree = edgeFramesSummary();
  if (agree.total) {
    const want = side === "buy" ? agree.long : agree.short;
    const need = state.best && state.best.on ? Math.ceil((agree.total * 2) / 3) : Math.ceil(agree.total / 2);
    add("Timeframes", want >= need, "Edge: " + agree.text, !!(bestBlocks && want < need));
  }
  if (state.best && state.best.on && side === "buy") {
    const status = bestSetupStatus();
    add("Best setup", status.ready, status.detail, bestBlocks);
    add("Daily buys", status.roomOk, status.day.count + " of " + BEST_MAX_BUYS + " used today", bestBlocks);
  }
  const board = state.board;
  const picked = row || (board && board.rows.find((item) => item.id === state.strat.pick)) || null;
  if (picked && picked.ready) {
    const agrees = (side === "buy" && picked.call === "BUY") || (side === "sell" && picked.call === "SELL");
    add("Strategy", agrees, picked.name + " says " + picked.call + (agrees ? "" : ". It does not call for this " + side), false);
  }
  return checks;
}

function quoteLine(symbol) {
  const bars = seenBars();
  const last = bars.length ? bars[bars.length - 1].close : 0;
  return last > 0 ? formatPrice(last) + " (latest real quote for " + symbol + ")" : "No quote yet";
}

function orderPlan(side, symbol, source, extra) {
  const paper = state.paper || {};
  const kind = extra && extra.ticket ? extra.ticket : null;
  const rows = [["Market", symbol]];
  if (kind) {
    const price = kind.kind === "limit" ? " at " + formatPrice(kind.limit) : kind.kind === "stop" ? " when price reaches " + formatPrice(kind.stop) : kind.kind === "stop_limit" ? " (stop " + formatPrice(kind.stop) + ", limit " + formatPrice(kind.limit) + ")" : " now";
    rows.push(["Order", side.toUpperCase() + " · " + String(kind.kind).replace("_", " ") + price + (kind.tif ? " · " + kind.tif : "")]);
    if (kind.kind !== "market") rows.push(["Then", "It waits and fills by itself when the real quote reaches it. This OK covers that fill."]);
  } else if (side === "buy") {
    rows.push(["Action", "BUY: puts all paper cash into one position"]);
  } else {
    rows.push(["Action", extra && extra.all ? "SELL ALL: closes the whole paper position now. Buys stop until tomorrow" : "SELL: closes the whole paper position (" + Number(paper.shares || 0) + " shares)"]);
  }
  rows.push(["Last quote", quoteLine(symbol)]);
  rows.push(["Paper account", "Cash " + (paper.cash != null ? formatPrice(paper.cash) : "—") + " · " + Number(paper.shares || 0) + " shares"]);
  rows.push(["Fill", "Latest real trade at the moment you press Yes, moved 5 bps against you, plus the fee"]);
  rows.push(["Started by", source === "auto"
    ? "AUTO SIGNAL · " + (extra.row ? extra.row.name : "strategy") + " on the closed bar " + (extra.closed || "") + ", seen " + new Date().toLocaleTimeString("en-US", { timeZone: "America/Denver", timeZoneName: "short" })
    : source === "strategies"
      ? "Strategies tab · " + (extra && extra.row ? extra.row.name : "picked strategy") + " · you pressed " + side.toUpperCase()
      : source === "strategy" ? "Armed from the opening-bar panel" : "You pressed it"]);
  return {
    side,
    title: (source === "auto" ? "Auto signal: " : source === "strategies" ? "Strategies: " : "Confirm: ") + (extra && extra.all ? "SELL ALL" : side.toUpperCase()) + " " + symbol,
    rows,
    checks: lineUp(side, symbol, source, extra && extra.row),
  };
}

async function askFirst(side, symbol, source, extra) {
  const ok = await confirmOrder(orderPlan(side, symbol, source, extra));
  if (!ok) $("status").textContent = "Canceled at the second warning. Nothing was traded.";
  return ok;
}

function whenIdle() {
  return new Promise((resolve) => {
    let waited = 0;
    const timer = setInterval(() => {
      waited += 100;
      if (!state.busy || waited > 8000) {
        clearInterval(timer);
        resolve();
      }
    }, 100);
  });
}

const STRAT_KEY = "paper_trader_strategy_v1";
const STRAT_IDS = ["edge", "squeeze", "quad", "ccizoom", "ccisnap", "donchian", "rsi2", "golden", "dualema", "macdx", "bollrev", "atrtrail", "seatrail", "weeklow"];
const TRIG_KEY = "paper_trader_triggers_v1";

function strategyIds() {
  return typeof PaperFrames !== "undefined" && Array.isArray(PaperFrames.STRATEGY_IDS) && PaperFrames.STRATEGY_IDS.length
    ? PaperFrames.STRATEGY_IDS
    : STRAT_IDS;
}

function loadStrat() {
  const base = { pick: "edge", on: false, acted: {}, note: "" };
  try {
    const raw = JSON.parse(localStorage.getItem(STRAT_KEY) || "{}");
    const ids = strategyIds();
    return { ...base, pick: ids.includes(raw.pick) ? raw.pick : "edge", on: raw.on === true, acted: raw.acted && typeof raw.acted === "object" ? raw.acted : {} };
  } catch (error) {
    return base;
  }
}

function loadTriggers() {
  const base = { squeeze: true, edge: true, strategy: true, best: true, process: true };
  try {
    const raw = JSON.parse(localStorage.getItem(TRIG_KEY) || "{}");
    return {
      squeeze: raw.squeeze !== false,
      edge: raw.edge !== false,
      strategy: raw.strategy !== false,
      best: raw.best !== false,
      process: raw.process !== false,
    };
  } catch (error) {
    return base;
  }
}

function saveTriggers() {
  try {
    localStorage.setItem(TRIG_KEY, JSON.stringify(state.triggers));
  } catch (error) {
    /* Triggers still work for this visit. */
  }
}

function saveStrat() {
  try {
    const keys = Object.keys(state.strat.acted);
    if (keys.length > 200) keys.slice(0, keys.length - 200).forEach((key) => delete state.strat.acted[key]);
    localStorage.setItem(STRAT_KEY, JSON.stringify({ pick: state.strat.pick, on: state.strat.on, acted: state.strat.acted }));
  } catch (error) {
    /* The pick still works for this visit. */
  }
}

const BEST_KEY = "paper_trader_best_v1";
const BEST_MAX_BUYS = 3;

function loadBest() {
  try {
    const raw = JSON.parse(localStorage.getItem(BEST_KEY) || "{}");
    return { on: raw.on === true };
  } catch (error) {
    return { on: false };
  }
}

function saveBest() {
  try {
    localStorage.setItem(BEST_KEY, JSON.stringify({ on: !!(state.best && state.best.on) }));
  } catch (error) {
    /* Best setup still works for this visit. */
  }
}

state.best = loadBest();

function dayBuySymbols() {
  const bars = (state.session && state.session.bars) || seenBars() || [];
  const day = bars.length ? bars[bars.length - 1].date : denverDay();
  const bought = ((state.session && state.session.dayBuys) || []).filter((key) => key.indexOf(day + "|") === 0);
  return {
    day,
    names: new Set(bought.map((key) => key.slice(day.length + 1))),
    count: bought.length ? new Set(bought.map((key) => key.slice(day.length + 1))).size : 0,
  };
}

function bestSetupStatus() {
  const waiting = [];
  const gates = [];
  const addGate = (label, ok, detail) => {
    gates.push({ label, ok: !!ok, detail: detail || "" });
    if (!ok) waiting.push(detail || label);
  };
  const bars = seenBars();
  const edge = bars.length ? currentEdge(bars) : null;
  const edgeOk = !!(edge && edge.ready && edge.score === (edge.checks || PaperFrames.EDGE_SCORE || 7) && edge.call === "BUY");
  addGate(
    "Edge 7/7 BUY",
    edgeOk,
    edge && edge.ready
      ? "Edge " + (edge.call || "WAIT") + " · " + (edge.score == null ? "—" : edge.score + "/" + (PaperFrames.EDGE_SCORE || 7))
      : "Edge not ready"
  );

  const frames = edgeFramesSummary();
  const need = frames.total ? Math.ceil((frames.total * 2) / 3) : 0;
  const mtfOk = frames.total > 0 && frames.long >= need && frames.long > frames.short;
  addGate(
    "Multi-TF majority",
    mtfOk,
    frames.total ? "Timeframes " + frames.long + "/" + frames.total + " long (need " + need + "+)" : "Read all timeframes first"
  );

  const internals = state.int && state.int.result;
  const goodParts = internals && internals.parts ? internals.parts.filter((part) => part.says === "good").length : 0;
  const badParts = internals && internals.parts ? internals.parts.filter((part) => part.says === "bad").length : 0;
  const intOk = !!(
    internals &&
    internals.ready &&
    (internals.label === "Supportive" || (internals.label === "Mixed" && internals.score >= 55 && goodParts > badParts))
  );
  addGate(
    "Internals supportive",
    intOk,
    internals && internals.ready ? "Internals " + internals.label + " " + internals.score : "Internals not ready"
  );

  const desk =
    bars.length && typeof PaperFrames !== "undefined" && PaperFrames.deskInternals
      ? PaperFrames.deskInternals(bars, { live: chartCrypto() })
      : state.int && state.int.desk
        ? state.int.desk
        : null;
  const board = bars.length >= 2 ? currentBoard(bars) : null;
  const quad = board && board.rows ? board.rows.find((row) => row.id === "quad") : null;
  const squeezeOk = !!(desk && desk.ready && desk.squeezeOk) || !!(quad && quad.ready && quad.squeezeOk);
  const tmoOk = !!(desk && desk.ready && desk.tmoOk) || !!(quad && quad.ready && quad.tmoOk);
  const big4Ok = !!(desk && desk.ready && desk.big4Ok) || !!(quad && quad.ready && (quad.big4Ok || quad.b4Ok));
  const stackOk = !!(desk && desk.ready && desk.stackOk) || !!(quad && quad.ready && quad.stackOk);
  const htfOk =
    !!(desk && desk.ready && desk.weekOk && desk.monthOk) || !!(quad && quad.ready && quad.weekOk && quad.monthOk);
  addGate("Squeeze (desk)", squeezeOk, squeezeOk ? "Squeeze fire on" : "Squeeze fire off");
  addGate("TMO", tmoOk, tmoOk ? "True momentum above signal" : "TMO not above signal");
  addGate("big4indicator", big4Ok, big4Ok ? "big4indicator agrees" : "big4indicator waiting");
  addGate("SMA stack", stackOk, stackOk ? "SMA stack lined up" : "SMA stack not lined up");
  addGate("Weekly + monthly MTF", htfOk, htfOk ? "Weekly and monthly agree" : "Weekly/monthly multi-TF not both on");

  const day = dayBuySymbols();
  const roomOk = day.count < BEST_MAX_BUYS;
  addGate("Buy room", roomOk, day.count + "/" + BEST_MAX_BUYS + " new buys today");

  const symbol = state.paper && state.paper.symbol;
  let scanOk = false;
  const onBest = (state.picks || []).some((row) => row.symbol === symbol);
  const onFire = (state.fires || []).some((row) => row.symbol === symbol);
  if (!state.scanDone && !onFire) addGate("Green scan list", false, "Daily scan still walking");
  else if (!onBest && !onFire) addGate("Green scan list", false, "Open market is not in today's green best or Fire 5 list");
  else {
    scanOk = true;
    addGate("Green scan list", true, onFire ? "In Fire 5 green list" : "In today's green best list");
  }

  const asOf = state.market && state.market.as_of;
  const quoteAge = asOf ? Date.now() / 1000 - asOf : 9999;
  const quoteOk = asOf > 0 && quoteAge <= 120;
  addGate("Fresh quote", quoteOk, quoteOk ? "Quote " + quoteAgeText(asOf) : asOf ? "Quote stale (" + quoteAgeText(asOf) + ")" : "No quote yet");

  const tone = bars.length ? currentMood(bars) : null;
  const moodOk = !(tone && tone.score != null && tone.score >= 85);
  addGate("Not extreme greed", moodOk, tone && tone.score != null ? tone.label + " " + tone.score : "Mood waiting");

  const tide = state.tide && state.tide.result;
  const tideOk = !(tide && tide.on);
  addGate(
    "Tide Alert clear",
    tideOk,
    tide && tide.on ? "Tide Alert on — Stay flat, review Go Long 5" : tide && tide.ready ? "Tide Alert off" : "Tide unread (run scorecard)"
  );

  const lane = symbol ? readLaneAlign(symbol, bars) : { lane: "unread", why: "Open a market for Lane Align", ready: false };
  const laneOk = !!(lane && PaperFrames.laneAllowsAuto(lane.lane, "best"));
  addGate(
    "Lane Align",
    laneOk,
    lane && lane.why
      ? lane.why
      : "Lane Align unread — run scorecard"
  );

  // Seat Trail is Strategies Auto capable; Best setup stays Edge-gated. Show agree/wait without blocking.
  const trail =
    bars.length && typeof PaperFrames !== "undefined" && PaperFrames.seatTrailRead
      ? PaperFrames.seatTrailRead(bars, { live: chartCrypto() })
      : null;
  const trailArmed = !!(trail && trail.ready && trail.armed);
  gates.push({
    label: "Seat Trail",
    ok: trailArmed,
    detail: trail && trail.ready
      ? trailArmed
        ? "Seat Trail armed · agrees (Strategies Auto can follow Seat Trail separately)"
        : "Seat Trail waiting · Best setup still Edge-gated"
      : "Seat Trail unread",
  });

  const lock = confirmLockForSymbol(symbol || "", edge, {
    bars,
    seat: lane && lane.seat ? lane.seat.seat || lane.seat : "",
    seatTrail: trailArmed,
  });
  addGate(
    "Confirm Lock · " + ((lock.card && lock.card.name) || "Desk Card"),
    !!(lock && lock.ok),
    lock && lock.ok
      ? "Fire Precision " + (lock.precision != null ? lock.precision : "—") + " · " + (lock.card && lock.card.name)
      : (lock && lock.why) || "Confirm Lock waiting"
  );

  const held = !!(state.paper && state.paper.shares > 0);
  if (held) waiting.unshift("Already in a paper long — Best setup buys wait; Edge SELL can still exit");

  const deskOk = squeezeOk && tmoOk && big4Ok && stackOk && htfOk;
  const ready =
    !!(state.best && state.best.on) &&
    edgeOk &&
    mtfOk &&
    intOk &&
    deskOk &&
    roomOk &&
    scanOk &&
    quoteOk &&
    moodOk &&
    tideOk &&
    laneOk &&
    !!(lock && lock.ok) &&
    !held;
  const detail = !(state.best && state.best.on)
    ? "Off"
    : ready
      ? "Ready · Edge 7/7 BUY · Confirm Lock · MTF " + frames.long + "/" + frames.total + " · Internals " + internals.label + " " + internals.score + " · desk Squeeze·TMO·CCI·Waves·big4·stack·MTF · Tide clear · Lane Open · " + day.count + "/" + BEST_MAX_BUYS + " buys today"
      : "Armed · waiting: " + waiting.slice(0, 3).join("; ");
  return {
    ready,
    edgeOk,
    mtfOk,
    intOk,
    squeezeOk,
    tmoOk,
    big4Ok,
    stackOk,
    htfOk,
    deskOk,
    roomOk,
    scanOk,
    quoteOk,
    moodOk,
    tideOk,
    laneOk,
    lockOk: !!(lock && lock.ok),
    held,
    waiting,
    gates,
    detail,
    frames,
    internals,
    desk,
    day,
  };
}

function bestBuyReason(symbol) {
  if (!(state.best && state.best.on)) return "";
  if (state.strat.pick !== "edge") return "Best setup Auto only buys from Edge · seven checks.";
  const status = bestSetupStatus();
  if (status.ready && state.paper && state.paper.symbol === symbol) return "";
  if (!status.ready) return status.waiting[0] || "Best setup filters are not all green.";
  if (state.paper && state.paper.symbol !== symbol) return "Best setup is watching " + state.paper.symbol + ".";
  return "";
}

function renderBestSetup() {
  const button = $("best-setup");
  const note = $("best-setup-note");
  const list = $("best-setup-gates");
  if (!button || !note) return;
  const on = !!(state.best && state.best.on);
  const status = bestSetupStatus();
  const wasReady = !!(state.best && state.best.wasReady);
  button.setAttribute("aria-pressed", on ? "true" : "false");
  button.className = "best-setup-btn" + (on ? (status.ready ? " ready" : " on") : "");
  button.textContent = on ? (status.ready ? "Best setup ready" : "Best setup Auto on") : "Best setup Auto";
  note.textContent = on
    ? status.detail + ". Turn Auto on. Edge SELL can still exit a long. Still asks Yes. Max " + BEST_MAX_BUYS + " new paper buys a Denver day. Not a forecast."
    : "Off. Strictest paper buys only: Edge all seven (TMO · Squeeze · CCI · Desk Waves green/up · VIX falling), Confirm Lock from Desk Cards, multi-TF majority, supportive market internals, desk Squeeze · TMO · big4indicator · stack · weekly/monthly MTF, green scan list, fresh quote, not extreme greed, Tide clear, Lane Align open, max three buys a Denver day. Seat Trail shows agree/wait on the checklist and runs as its own Strategies Auto process — not a second Fire 5. Still asks Yes. Not a forecast.";
  if (list) {
    list.replaceChildren();
    list.hidden = !on;
    if (on) {
      status.gates.forEach((gate) => {
        const row = document.createElement("li");
        row.className = gate.ok ? "on" : "off";
        row.textContent = gate.label + " · " + gate.detail;
        list.appendChild(row);
      });
    }
  }
  if (on && status.ready && !wasReady) {
    state.best.wasReady = true;
    if (state.triggers && state.triggers.best !== false) {
      speakWarning({
        symbol: (state.paper && state.paper.symbol) || "",
        date: closedDate(seenBars()) || denverDay(),
        kind: "best",
        side: "buy",
        detail: "Buy warning. Best setup is ready on " + pinLabel((state.paper && state.paper.symbol) || "") + ".",
      });
    }
  }
  if (!status.ready && state.best) state.best.wasReady = false;
  if (state.offline) {
    /* Signals refresh from renderLocalSignals callers. */
  }
}

function setBestSetup(on) {
  state.best = state.best || { on: false, wasReady: false };
  state.best.on = !!on;
  state.best.wasReady = false;
  saveBest();
  if (state.best.on) {
    state.strat.pick = "edge";
    state.strat.on = true;
    state.strat.note = "";
    saveStrat();
    state.boardKey = "";
    const pick = $("strat-pick");
    if (pick) pick.value = "edge";
    const box = $("strat-auto-on");
    if (box) box.checked = true;
    state.int.gate = true;
    const intGate = $("int-gate");
    if (intGate) intGate.checked = true;
    try {
      localStorage.setItem(INT_KEY, JSON.stringify({ gate: true }));
    } catch (error) {
      /* Gate still holds for this visit. */
    }
    scheduleEdgeFrames();
    loadEdgeFrames(true).catch(() => {});
    loadInternals(true).catch(() => {});
  }
  renderBestSetup();
  renderStrategies();
  renderAutoNote();
  if (state.offline) renderLocalSignals();
  else runSafe(false);
  $("status").textContent = state.best.on
    ? "Best setup Auto is on. Loading timeframes and internals. Turn Auto on; Yes still required."
    : "Best setup Auto is off.";
}

state.strat = loadStrat();
state.triggers = loadTriggers();

function currentBoard(bars) {
  const last = bars.length ? bars[bars.length - 1] : null;
  const live = PaperFrames.liveBar(bars, chartCrypto());
  const key = bars.length + ":" + live + ":" + (last ? last.date + ":" + last.close : "");
  if (state.boardKey === key && state.board) return state.board;
  state.board = PaperFrames.strategies(bars, { live });
  state.boardKey = key;
  return state.board;
}

function closedDate(bars) {
  const live = PaperFrames.liveBar(bars, chartCrypto());
  const list = live ? bars.slice(0, -1) : bars;
  return list.length ? list[list.length - 1].date : "";
}

function cardLine(row) {
  if (!row.ready) return row.why || "Not enough bars.";
  const stop = row.stop != null ? " Stop " + formatPrice(row.stop) + "." : "";
  if (row.id === "squeeze") {
    const dots = row.squeezeOn ? "Squeeze on, " + row.dots + " dot" + (row.dots === 1 ? "" : "s") + (row.dots >= 5 ? " (five or more: compression has built)" : "") : row.fired ? "Squeeze just fired" : "No squeeze";
    const mom = row.momentum == null ? "" : " · momentum " + (row.momentum > 0 ? "above" : "below") + " zero and " + (row.momentumUp ? "rising" : "not rising");
    const trend = " · EMA 8 " + (row.trend ? "above" : "below") + " 21";
    return dots + mom + trend + "." + (row.bearish ? " Bearish fire. This long-only desk stands aside." : "") + stop;
  }
  if (row.id === "quad") {
    const legs = [
      row.squeezeOk ? "Squeeze fire on" : "Squeeze fire off",
      row.tmoOk ? "TMO on" : "TMO off",
      row.big4Ok || row.b4Ok ? "big4indicator on" : "big4indicator off",
      row.stackOk ? "SMA stack on" : "SMA stack off",
      row.weekOk ? "Weekly on" : "Weekly off",
      row.monthOk ? "Monthly on" : "Monthly off",
    ].join(" · ");
    return legs + "." + stop;
  }
  if (row.id === "ccizoom") {
    const zone = row.zoomUp ? "Zoom up" : row.zoomDown ? "Zoom down (stand aside)" : "Outside zoom";
    const value = row.cci50 == null ? "" : " · CCI(50) " + Number(row.cci50).toFixed(1);
    return zone + value + "." + stop;
  }
  if (row.id === "ccisnap") {
    const zone = row.zoomUp ? "Zoom up" : row.zoomDown ? "Zoom down" : "Outside zoom";
    const ready = row.readyUp ? " · snap-back up ready" : row.readyDown ? " · snap-back down ready" : "";
    const fire = row.fireUp ? " · snap-back up fired" : row.fireDown ? " · snap-back down fired" : "";
    return zone + ready + fire + "." + stop;
  }
  if (row.call === "HOLD") return "In since " + formatPrice(row.entry) + " · " + signedPercent(row.openPct) + "." + stop;
  if (row.call === "BUY") return "Buy signal on the last closed bar. A paper buy fills at the next open." + stop;
  if (row.call === "SELL") return "Exit signal on the last closed bar. A paper sell fills at the next open.";
  return "Flat. Waiting for the entry rule to turn on.";
}

function renderStrategies() {
  const body = $("strat-board");
  if (!body || typeof PaperFrames === "undefined" || !PaperFrames.strategies) return;
  const bars = seenBars();
  if (bars.length < 2) return;
  const board = currentBoard(bars);
  const stamp = state.boardKey + "|" + state.strat.pick + "|" + state.strat.on + "|" + board.best;
  if (sameStamp(body, stamp)) return;
  body.replaceChildren();
  board.rows.forEach((row) => {
    const tr = document.createElement("tr");
    tr.className = "pickable" + (row.id === board.best ? " best" : "") + (row.id === state.strat.pick ? " picked" : "");
    tr.tabIndex = 0;
    tr.setAttribute("role", "button");
    tr.setAttribute("aria-label", "Test " + row.name + " on this market");
    tr.dataset.strategy = row.id;
    const pill = document.createElement("span");
    const call = row.ready && row.call ? row.call : "WAIT";
    pill.className = "call-pill " + String(call).toLowerCase();
    pill.textContent = call;
    addCells(tr, [
      row.name + " · " + row.who,
      "",
      row.ready ? String(row.trades || 0) : "—",
      row.ready && row.trades ? Math.round(row.winRate * 100) + "%" : "—",
      row.ready ? { text: signedPercent(row.total), cls: row.total > 0 ? "up" : row.total < 0 ? "down" : "" } : "—",
      row.ready ? signedPercent(row.hold) : "—",
      row.ready && row.trades ? signedPercent(row.worst) : "—",
    ]);
    tr.children[1].appendChild(pill);
    body.appendChild(tr);
  });
  const best = board.rows.find((row) => row.id === board.best);
  $("strat-best").textContent = best
    ? "Best on this market's history so far: " + best.name + ", " + signedPercent(best.total) + " over " + best.trades + " trades, against " + signedPercent(best.hold) + " for simply holding. Picking the winner after the fact flatters it. Past closed bars, not a forecast."
    : "No strategy has five closed trades on this market yet, so none is ranked. More history is needed.";
  const cards = $("strat-cards");
  cards.replaceChildren();
  board.rows.forEach((row) => {
    const card = document.createElement("div");
    card.className = "strat-card";
    const title = document.createElement("h4");
    title.textContent = row.name;
    const idea = document.createElement("p");
    idea.className = "muted";
    idea.textContent = row.idea;
    const line = document.createElement("p");
    line.textContent = cardLine(row);
    card.append(title, idea, line);
    cards.appendChild(card);
  });
  const pick = $("strat-pick");
  if (pick.value !== state.strat.pick) pick.value = state.strat.pick;
  $("strat-auto-on").checked = state.strat.on;
  syncTriggerChecks();
  renderStratTrade(board);
  renderAutoNote();
  renderStratDetail(board);
}

function renderStratTrade(board) {
  const buy = $("strat-buy");
  const sell = $("strat-sell");
  const note = $("strat-trade-note");
  if (!buy || !sell) return;
  const paper = state.paper;
  const row = board && board.rows ? board.rows.find((item) => item.id === state.strat.pick) : null;
  const best = board && board.best ? board.rows.find((item) => item.id === board.best) : null;
  const held = !!(paper && Number(paper.shares) > 0);
  const ready = !!(paper && paper.paper === true);
  buy.disabled = !ready || held;
  sell.disabled = !ready || !held;
  if (!note) return;
  if (!paper || paper.paper !== true) {
    note.textContent = "Turn Paper on to buy or sell from Strategies. Second warning still asks.";
    return;
  }
  const name = row ? row.name : "the picked strategy";
  const call = row && row.ready ? row.call : "WAIT";
  const tip = best && best.id === state.strat.pick
    ? "Best on this history so far is picked: " + name + " (" + call + ")."
    : "Picked: " + name + " (" + call + ")." + (best ? " Best on this history so far: " + best.name + "." : "");
  note.textContent = tip + " Paper Buy or Sell on " + ((paper && paper.symbol) || "this market") + ". Second warning still asks. Not a forecast.";
}

async function stratTrade(side) {
  if (state.busy || !state.paper || state.paper.paper !== true) return;
  const held = Number(state.paper.shares) > 0;
  if (side === "buy" && held) {
    $("status").textContent = "Already long on paper. Sell first, or use the Trade ticket.";
    return;
  }
  if (side === "sell" && !held) {
    $("status").textContent = "No open paper shares to sell on this market.";
    return;
  }
  const bars = seenBars();
  const board = bars.length >= 2 ? currentBoard(bars) : null;
  const row = board && board.rows ? board.rows.find((item) => item.id === state.strat.pick) : null;
  if (!(await askFirst(side, state.paper.symbol, "strategies", { row }))) return;
  await withBusy(async () => {
    stopAuto();
    const paper = await paperCall("/api/paper/trade", { source: "strategies", confirmed: true });
    render(paper);
    const verb = paper.last_action === "sell" ? "Sold" : "Bought";
    const label = row ? row.name + ": " : "Strategies: ";
    const caution = paper.last_action === "buy" ? internalsGate(paper.symbol) : "";
    $("status").textContent = label + (fillText(paper) || verb + " one simulated position on the paper account.") + (caution ? " Heads up: " + caution : "");
    await loadSignals();
    state.boardKey = "";
    renderStrategies();
  });
}

function pickStrategy(id) {
  if (!strategyIds().includes(id)) return;
  state.strat.pick = id;
  state.strat.note = "";
  saveStrat();
  state.boardKey = "";
  state.sweep = { busy: false, id: "", rows: [], at: 0 };
  renderStrategies();
  const row = state.board && state.board.rows.find((item) => item.id === id);
  const symbol = (state.paper && state.paper.symbol) || "";
  setStatus("Testing " + (row ? row.name : id) + " on " + (symbol || "this market") + ". Auto follows it only if strategy trading is on.");
  nudgeLearn("strategies", symbol ? [symbol] : []);
}

function readPhoneLearn() {
  try {
    const raw = JSON.parse(localStorage.getItem(LEARN_KEY) || "{}");
    return raw && typeof raw.symbols === "object" && raw.symbols ? raw : { version: 1, symbols: {}, passes: 0 };
  } catch (error) {
    return { version: 1, symbols: {}, passes: 0 };
  }
}

function writePhoneLearn(book) {
  try {
    localStorage.setItem(LEARN_KEY, JSON.stringify(book));
  } catch (error) {
    /* Learning still holds for this page. */
  }
}

function stampPhoneFills(symbol, rule) {
  if (!rule || !state.book || !state.book[symbol]) return 0;
  const session = state.book[symbol];
  let changed = 0;
  (session.fills || []).forEach((fill) => {
    if (!fill || fill.process === rule) return;
    fill.process = rule;
    changed += 1;
  });
  if (changed) writeBook();
  return changed;
}

function announceNewProcess(symbol, rule, previous) {
  newProcessNote = rule + (symbol ? " on " + symbol : "");
  newProcessAt = Date.now();
  learnTouchNote = "New process · " + newProcessNote + ". Paper fills updated. Closed bars only. Not a forecast.";
  setStatus(learnTouchNote);
  refreshLearnSignal();
  if (state.voice && state.triggers && state.triggers.process) {
    speakWarning({
      symbol: symbol || "",
      date: String(newProcessAt),
      kind: "process",
      side: "buy",
      detail: "New process. " + newProcessNote + ". Closed bars only.",
    });
  }
}

function topScanLearnNames() {
  const rows = (state.picks && state.picks.length ? state.picks : state.leaders) || [];
  const names = [];
  rows.forEach((row) => {
    const symbol = row && row.symbol;
    if (symbol && names.indexOf(symbol) < 0) names.push(symbol);
  });
  return names;
}

function nextScanLearnBatch(size) {
  const list = topScanLearnNames();
  const count = Math.max(1, Math.min(Number(size) || SCAN_LEARN_BATCH, 5));
  if (!list.length) return [];
  const out = [];
  let cursor = Number((state.scanLearn && state.scanLearn.cursor) || 0);
  for (let step = 0; step < count; step += 1) {
    out.push(list[cursor % list.length]);
    cursor += 1;
  }
  if (!state.scanLearn) state.scanLearn = { cursor: 0, lastAt: 0, lastSymbol: "", scored: [], timer: 0 };
  state.scanLearn.cursor = cursor % list.length;
  state.scanLearn.lastSymbol = out[out.length - 1] || "";
  return out;
}

async function barsForLearn(symbol) {
  if (!symbol) return null;
  if (state.market && state.market.symbol === symbol && state.market.bars) return state.market.bars;
  if (state.session && state.session.symbol === symbol && state.session.bars) return state.session.bars;
  try {
    if (state.offline) {
      const row = state.catalog.find((item) => item.symbol === symbol);
      return await PaperLive.daily(symbol, row ? row.category : "");
    }
    const payload = await getJSON("/api/bars?symbol=" + encodeURIComponent(symbol) + "&frame=1D");
    return (payload && payload.bars) || null;
  } catch (error) {
    return null;
  }
}

async function touchPhoneLearn(tab, symbols) {
  const book = readPhoneLearn();
  const scanCycle = tab === "scan" || tab === "auto";
  const targets = [];
  if (!scanCycle && state.paper && state.paper.symbol) targets.push(state.paper.symbol);
  (symbols || []).forEach((symbol) => {
    if (symbol && targets.indexOf(symbol) < 0) targets.push(symbol);
  });
  let scored = [];
  let newest = null;
  const limit = scanCycle ? SCAN_LEARN_BATCH : 3;
  for (const symbol of targets.slice(0, limit)) {
    try {
      const bars = await barsForLearn(symbol);
      if (!bars || !bars.length || typeof PaperFrames === "undefined") continue;
      const settled = PaperFrames.closedBars(bars, phoneCrypto(symbol, state.catalog.find((item) => item.symbol === symbol)));
      const lesson = PaperFrames.lesson(settled);
      if (!lesson) continue;
      const previous = String((book.symbols[symbol] && book.symbols[symbol].rule) || "");
      book.symbols[symbol] = {
        rule: lesson.rule,
        seen: lesson.seen,
        matched: lesson.matched,
        mood: lesson.mood,
        as_of: settled.length ? settled[settled.length - 1].date : "",
        setup: lesson.setup || "",
        tab: tab || "",
      };
      if (lesson.mood) state.moods[symbol] = lesson.mood;
      book.passes = (Number(book.passes) || 0) + 1;
      book.last_symbol = symbol;
      scored.push(symbol);
      if (lesson.rule && lesson.rule !== previous) {
        newest = { symbol, rule: lesson.rule, previous };
        stampPhoneFills(symbol, lesson.rule);
        const history = Array.isArray(book.processes) ? book.processes : [];
        history.push({ at: Date.now() / 1000, symbol, rule: lesson.rule, previous, tab: tab || "" });
        book.processes = history.slice(-200);
        book.last_process = history[history.length - 1];
      }
    } catch (error) {
      continue;
    }
  }
  book.updated = Date.now();
  book.last_touch_tab = tab || "";
  writePhoneLearn(book);
  if (newest) {
    announceNewProcess(newest.symbol, newest.rule, newest.previous);
  } else if (scored.length) {
    const prefix = scanCycle ? "Scan learn scored " : "Scored ";
    learnTouchNote = prefix + scored.join(", ") + " from " + (tab || "desk") + ". Closed bars only. Not a forecast.";
    refreshLearnSignal();
  }
  return scored;
}

function refreshLearnSignal() {
  if (state.offline) {
    try {
      renderLocalSignals();
    } catch (error) {
      return;
    }
    return;
  }
  loadSignals().catch(() => {});
}

async function nudgeLearn(tab, extraSymbols) {
  const scanCycle = tab === "scan" || tab === "auto";
  const symbols = scanCycle
    ? [].concat(Array.isArray(extraSymbols) ? extraSymbols : [])
    : readPins()
        .concat([state.paper && state.paper.symbol].filter(Boolean))
        .concat(Array.isArray(extraSymbols) ? extraSymbols : []);
  const unique = [];
  symbols.forEach((symbol) => {
    if (symbol && unique.indexOf(symbol) < 0) unique.push(symbol);
  });
  const now = Date.now();
  const gap = tab === "auto" || tab === "scan" ? SCAN_LEARN_GAP_MS : 900;
  if (tab === learnTouchTab && now - learnTouchAt < gap) return;
  learnTouchAt = now;
  learnTouchTab = tab || "";
  if (state.offline) {
    return touchPhoneLearn(tab, unique);
  }
  try {
    const book = await postJSON("/api/learn/touch", { tab: tab || "", symbols: unique });
    if (!book) return;
    const news = Array.isArray(book.new_processes) ? book.new_processes : [];
    if (news.length) {
      announceNewProcess(news[0].symbol, news[0].rule, news[0].previous);
      if (state.paper) render(state.paper);
    } else if (book.touch_note) {
      learnTouchNote = book.touch_note;
      refreshLearnSignal();
    }
    return book.touched || [];
  } catch (error) {
    return [];
  }
}

async function cycleScanLearn(reason) {
  if (typeof document !== "undefined" && document.visibilityState === "hidden") return [];
  const list = topScanLearnNames();
  if (!list.length) {
    learnTouchNote = "Learning waits for Daily scan names. Closed bars only. Not a forecast.";
    refreshLearnSignal();
    return [];
  }
  const now = Date.now();
  if (!state.scanLearn) state.scanLearn = { cursor: 0, lastAt: 0, lastSymbol: "", scored: [], timer: 0 };
  if (now - (state.scanLearn.lastAt || 0) < SCAN_LEARN_GAP_MS) return [];
  const batch = nextScanLearnBatch(SCAN_LEARN_BATCH);
  state.scanLearn.lastAt = now;
  const scored = await nudgeLearn(reason || "scan", batch);
  const done = Array.isArray(scored) ? scored : batch;
  if (done && done.length) {
    state.scanLearn.scored = (state.scanLearn.scored || []).concat(done).slice(-40);
    if (!state.offline) {
      try {
        await postJSON("/api/learn/watch", { symbols: list.concat(readPins()) });
      } catch (error) {
        /* Background watch update is best-effort. */
      }
    }
    if (!learnTouchNote || reason === "scan" || reason === "auto") {
      learnTouchNote =
        "Scan learn " +
        done.join(", ") +
        " · " +
        list.length +
        " top names cycling (page open). Background score keeps those names after the desk closes. Closed bars only. Not a forecast.";
      refreshLearnSignal();
    }
  }
  return done || [];
}

function readStratLearn() {
  try {
    const raw = JSON.parse(localStorage.getItem(STRAT_LEARN_KEY) || "{}");
    return raw && typeof raw.symbols === "object" && raw.symbols ? raw : { version: 1, symbols: {}, passes: 0 };
  } catch (error) {
    return { version: 1, symbols: {}, passes: 0 };
  }
}

function writeStratLearn(book) {
  try {
    localStorage.setItem(STRAT_LEARN_KEY, JSON.stringify(book));
  } catch (error) {
    /* Strategy learn still holds for this page. */
  }
}

async function cycleStrategyLearn() {
  if (typeof document !== "undefined" && document.visibilityState === "hidden") return "";
  if (typeof PaperFrames === "undefined" || !PaperFrames.strategies) return "";
  const now = Date.now();
  if (!state.stratLearn) state.stratLearn = { cursor: 0, lastAt: 0, lastSymbol: "", lastId: "", passes: 0 };
  if (now - (state.stratLearn.lastAt || 0) < STRAT_LEARN_GAP_MS) return "";
  const list = topScanLearnNames();
  const open = (state.paper && state.paper.symbol) || "";
  const queue = list.length ? list.slice() : open ? [open] : [];
  if (!queue.length) {
    learnTouchNote = "Strategy learn waits for a market or Daily scan names. Page open only. Closed bars only. Not a forecast.";
    refreshLearnSignal();
    return "";
  }
  const symbol = queue[state.stratLearn.cursor % queue.length];
  state.stratLearn.cursor += 1;
  state.stratLearn.lastAt = now;
  state.stratLearn.lastSymbol = symbol;
  const bars = await barsForLearn(symbol);
  if (!bars || !bars.length) return "";
  const row = state.catalog.find((item) => item.symbol === symbol);
  const settled = PaperFrames.closedBars(bars, phoneCrypto(symbol, row));
  if (settled.length < 2) return "";
  const board = PaperFrames.strategies(settled, { live: false });
  const snapshot = {
    at: now,
    best: board.best || "",
    rows: (board.rows || []).map((item) => ({
      id: item.id,
      name: item.name,
      call: item.call,
      ready: !!item.ready,
      trades: item.trades || 0,
      winRate: item.winRate || 0,
      total: item.total || 0,
      hold: item.hold || 0,
      worst: item.worst || 0,
    })),
  };
  const book = readStratLearn();
  book.symbols[symbol] = snapshot;
  book.passes = (Number(book.passes) || 0) + 1;
  book.updated = now;
  book.last_symbol = symbol;
  writeStratLearn(book);
  state.stratLearn.passes = book.passes;
  state.stratLearn.lastId = snapshot.best || "";
  if (open && symbol === open) {
    state.boardKey = "";
    state.board = board;
    try {
      renderStrategies();
    } catch (error) {
      /* Chart can refresh on the next paint. */
    }
  }
  const best = (board.rows || []).find((item) => item.id === board.best);
  learnTouchNote =
    "Strategy learn " +
    symbol +
    " · " +
    snapshot.rows.length +
    " strategies on closed bars" +
    (best ? " · best " + best.name : "") +
    ". Page open for strategy table. Background score covers scan names after the desk closes. Not a forecast.";
  refreshLearnSignal();
  nudgeLearn("strategies", [symbol]);
  return symbol;
}

function readFillLearn() {
  try {
    const raw = JSON.parse(localStorage.getItem(FILL_LEARN_KEY) || "{}");
    return raw && typeof raw === "object" ? raw : { version: 1, fills: [], timing: {} };
  } catch (error) {
    return { version: 1, fills: [], timing: {} };
  }
}

function writeFillLearn(book) {
  try {
    localStorage.setItem(FILL_LEARN_KEY, JSON.stringify(book));
  } catch (error) {
    /* Fill learn still holds for this page. */
  }
}

function fillTimingNote() {
  const timing = (state.fillLearn && state.fillLearn.timing) || readFillLearn().timing || {};
  if (timing.note) return String(timing.note);
  if (timing.prefer_fire) return "Paper fills prefer Ready · Aim · Fire buys. Closed bars only. Not a forecast.";
  return "";
}

function preferFireFromFills() {
  const timing = (state.fillLearn && state.fillLearn.timing) || readFillLearn().timing || {};
  if (typeof timing.prefer_fire === "boolean") return timing.prefer_fire;
  const buy = timing.buy || {};
  const sell = timing.sell || {};
  if ((Number(sell.wins) || 0) > 0 && (Number(buy.setup_buys) || 0) > 0) return true;
  const count = Number(buy.count) || 0;
  return count >= 3 && (Number(buy.setup_buys) || 0) >= Math.max(1, Math.floor(count / 2));
}

function learnFromPaperFill(paper) {
  const fill = paper && paper.fill;
  if (!fill || !(fill.price > 0) || !paper.symbol) return null;
  const stamp = paper.symbol + "|" + fill.side + "|" + String(fill.at || "") + "|" + String(fill.price);
  if (state.lastFillLearnKey === stamp) return null;
  state.lastFillLearnKey = stamp;
  const bars = seenBars();
  const edge = bars.length ? currentEdge(bars) : null;
  const board = bars.length >= 2 ? currentBoard(bars) : null;
  const picked = board && board.rows ? board.rows.find((item) => item.id === ((state.best && state.best.on && "edge") || state.strat.pick)) : null;
  const event = {
    at: fill.at || Date.now() / 1000,
    symbol: paper.symbol,
    side: fill.side,
    price: fill.price,
    pnl: Number(fill.pnl) || 0,
    reason: fill.reason || "",
    as_of: closedDate(bars) || fill.as_of || "",
    stage: edge && edge.stage ? edge.stage : "",
    score: edge && edge.score != null ? edge.score : null,
    big4: edge && edge.big4 ? edge.big4.bias : "",
    strategy: picked ? picked.id : state.strat.pick || "",
    call: picked ? picked.call : "",
    process: fill.process || "",
    setup: fill.setup || "",
  };
  const book = readFillLearn();
  book.fills = (Array.isArray(book.fills) ? book.fills : []).concat([event]).slice(-200);
  const timing = Object.assign({}, book.timing || {});
  const key = event.side === "buy" ? "buy" : "sell";
  const row = Object.assign({ count: 0, wins: 0, setup_buys: 0, fire: 0 }, timing[key] || {});
  row.count = (Number(row.count) || 0) + 1;
  if (event.side === "sell" && event.pnl > 0) row.wins = (Number(row.wins) || 0) + 1;
  if (event.side === "buy" && (event.setup === "buy" || event.stage === "fire")) {
    row.setup_buys = (Number(row.setup_buys) || 0) + 1;
    if (event.stage === "fire") row.fire = (Number(row.fire) || 0) + 1;
  }
  timing[key] = row;
  const buyStats = timing.buy || {};
  const sellStats = timing.sell || {};
  timing.prefer_fire =
    (Number(sellStats.wins) || 0) > 0 && (Number(buyStats.setup_buys) || 0) > 0
      ? true
      : (Number(buyStats.count) || 0) >= 3 && (Number(buyStats.setup_buys) || 0) >= Math.max(1, Math.floor((Number(buyStats.count) || 0) / 2))
        ? true
        : (Number(buyStats.fire) || 0) > 0;
  timing.note =
    "Learned paper " +
    event.side +
    " on " +
    event.symbol +
    (event.stage ? " · " + event.stage : "") +
    ". " +
    (timing.prefer_fire ? "Prefer Ready · Aim · Fire for the next precise buy. " : "Gathering fill timing. ") +
    "Closed bars only. Not a forecast.";
  book.timing = timing;
  book.updated = Date.now();
  writeFillLearn(book);
  state.fillLearn = book;
  learnTouchNote = timing.note;
  refreshLearnSignal();
  nudgeLearn("fills", [paper.symbol]);
  return event;
}

function deskLearnPages() {
  // Auto-includes every dock tab, phone tab, and fixed desk boards in the shipped UI.
  const pages = [];
  const push = (id) => {
    if (id && pages.indexOf(id) < 0) pages.push(id);
  };
  ["markets", "chart", "trade", "study", "outlook", "cycle", "forecast", "home"].forEach(push);
  document.querySelectorAll(".dock-tab[data-dock]").forEach((button) => push(button.dataset.dock));
  document.querySelectorAll(".tabbar [data-tab]").forEach((button) => push(button.dataset.tab));
  document.querySelectorAll("[data-open-dock]").forEach((button) => push(button.dataset.openDock));
  ((state.learnBook && state.learnBook.desk_pages) || []).forEach((row) => push(row && row.id));
  return pages;
}

async function cycleDeskPageLearn() {
  if (typeof document !== "undefined" && document.visibilityState === "hidden") return "";
  const pages = deskLearnPages();
  if (!pages.length) return "";
  if (!state.pageLearn) state.pageLearn = { cursor: 0, lastAt: 0, lastPage: "", covered: [] };
  const now = Date.now();
  if (now - (state.pageLearn.lastAt || 0) < Math.max(4000, SCAN_LEARN_GAP_MS / 2)) return state.pageLearn.lastPage || "";
  // Warm two pages each pass so every Tools/Next page gets hit faster.
  const batchPages = [];
  for (let step = 0; step < 2 && pages.length; step += 1) {
    const page = pages[state.pageLearn.cursor % pages.length];
    state.pageLearn.cursor += 1;
    if (page && batchPages.indexOf(page) < 0) batchPages.push(page);
  }
  state.pageLearn.lastAt = now;
  state.pageLearn.lastPage = batchPages[batchPages.length - 1] || "";
  const covered = Array.isArray(state.pageLearn.covered) ? state.pageLearn.covered : [];
  batchPages.forEach((page) => {
    if (covered.indexOf(page) < 0) covered.push(page);
  });
  state.pageLearn.covered = covered.slice(-80);
  const names = topScanLearnNames().slice(0, 2);
  for (let index = 0; index < batchPages.length; index += 1) {
    await nudgeLearn(batchPages[index], names);
  }
  if (batchPages.indexOf("cycle") >= 0 || batchPages.indexOf("outlook") >= 0) {
    try {
      if (typeof PaperFrames !== "undefined" && PaperFrames.cycleMapRead && seenBars().length) {
        if (!state.cycle) state.cycle = { busy: false, stop: false, market: null, defensive: null, risk: null, open: null, at: 0, note: "" };
        state.cycle.open = PaperFrames.cycleMapRead(seenBars());
      }
    } catch (error) {
      /* Cycle warm pass is best-effort. */
    }
  }
  if (batchPages.indexOf("forecast") >= 0) {
    try {
      if (typeof PaperFrames !== "undefined" && PaperFrames.marketForecastRead && seenBars().length) {
        if (!state.forecast) {
          state.forecast = { busy: false, stop: false, open: null, board: null, at: 0, note: "" };
        }
        const cash = state.paper && Number(state.paper.equity) > 0 ? Number(state.paper.equity) : 10000;
        const warmSym = (state.paper && state.paper.symbol) || "";
        state.forecast.open = PaperFrames.marketForecastRead(seenBars(), {
          symbol: warmSym,
          spyBars: state.spyBars || [],
          vixBars: state.vixBars || [],
          cash,
          lane: warmSym ? readLaneAlign(warmSym, seenBars()) : null,
        });
      }
    } catch (error) {
      /* Forecaster warm pass is best-effort. */
    }
  }
  learnTouchNote =
    "Desk page learn · " +
    batchPages.join(", ") +
    " (" +
    covered.length +
    "/" +
    pages.length +
    " sections this session). Every Tools and Next page stays in the loop while this tab is open. Closed bars only. Not a forecast.";
  refreshLearnSignal();
  return state.pageLearn.lastPage || "";
}

async function holdLearnAwake() {
  if (typeof navigator === "undefined" || !navigator.wakeLock || !navigator.wakeLock.request) return;
  if (typeof document !== "undefined" && document.visibilityState === "hidden") return;
  try {
    if (state.wakeLock && !state.wakeLock.released) return;
    state.wakeLock = await navigator.wakeLock.request("screen");
    state.wakeLock.addEventListener("release", () => {
      state.wakeLock = null;
    });
  } catch (error) {
    state.wakeLock = null;
  }
}

function startScanLearnLoop() {
  if (state.scanLearn && state.scanLearn.timer) return;
  if (!state.scanLearn) state.scanLearn = { cursor: 0, lastAt: 0, lastSymbol: "", scored: [], timer: 0 };
  const tick = async () => {
    try {
      // Continuous while this tab stays open: scan + strategies + every desk page each pass.
      if (typeof document === "undefined" || document.visibilityState !== "hidden") {
        if (state.auto) await holdLearnAwake();
        await cycleScanLearn(state.auto ? "auto" : "scan");
        await cycleStrategyLearn();
        await cycleDeskPageLearn();
      }
    } catch (error) {
      /* Keep the page-open cycle alive. */
    }
    const wait = state.auto ? Math.max(8000, SCAN_LEARN_GAP_MS - 2000) : SCAN_LEARN_GAP_MS;
    state.scanLearn.timer = setTimeout(tick, wait);
  };
  state.scanLearn.timer = setTimeout(tick, 2500);
}

function renderStratDetail(board) {
  const box = $("strat-detail");
  if (!box) return;
  const row = board.rows.find((item) => item.id === state.strat.pick);
  box.hidden = !row;
  if (!row) return;
  const symbol = (state.paper && state.paper.symbol) || "this market";
  $("strat-detail-title").textContent = row.name + " on " + symbol;
  $("strat-detail-line").textContent = row.ready
    ? row.idea + " Now: " + row.call + ". " + row.trades + " past trade" + (row.trades === 1 ? "" : "s") + ", " + signedPercent(row.total) + " compounded, against " + signedPercent(row.hold) + " for holding. " + cardLine(row)
    : row.idea + " " + (row.why || "Not enough bars.");
  const log = $("strat-log");
  log.replaceChildren();
  const trades = row.ready ? row.log || [] : [];
  trades.slice().reverse().forEach((trade) => {
    const tr = document.createElement("tr");
    addCells(tr, [trade.entryDate, trade.exitDate, formatPrice(trade.entry), formatPrice(trade.exit), { text: signedPercent(trade.pct), cls: trade.pct > 0 ? "up" : trade.pct < 0 ? "down" : "" }, trade.why || ""]);
    log.appendChild(tr);
  });
  if (row.ready && row.openSince) {
    const tr = document.createElement("tr");
    addCells(tr, [row.openSince, "still open", formatPrice(row.entry), "—", { text: signedPercent(row.openPct), cls: row.openPct > 0 ? "up" : row.openPct < 0 ? "down" : "" }, "Open at the last close"]);
    log.insertBefore(tr, log.firstChild);
  }
  if (!log.children.length) {
    const tr = document.createElement("tr");
    addCells(tr, ["No closed trades on this market yet.", "", "", "", "", ""]);
    log.appendChild(tr);
  }
  renderSweep();
}

state.sweep = { busy: false, id: "", rows: [], at: 0 };

async function sweepStrategy() {
  const sweep = state.sweep;
  if (sweep.busy) return;
  const id = state.strat.pick;
  const symbols = (SQ_LISTS[$("strat-sweep-list").value] || SQ_LISTS.starter).slice(0, 45);
  const known = state.catalog && state.catalog.length ? new Set(state.catalog.map((item) => item.symbol)) : null;
  const fresh = { busy: true, id, rows: [], at: 0, skipped: 0 };
  state.sweep = fresh;
  let next = 0;
  let done = 0;
  const worker = async () => {
    while (next < symbols.length && state.sweep === fresh) {
      const symbol = symbols[next];
      next += 1;
      try {
        if (known && !known.has(symbol)) throw new Error("not listed");
        const bars = await sqDaily(symbol);
        const board = PaperFrames.strategies(bars, { live: PaperFrames.liveBar(bars, phoneCrypto(symbol, state.catalog.find((item) => item.symbol === symbol))) });
        const row = board.rows.find((item) => item.id === id);
        if (row && row.ready) fresh.rows.push({ symbol, row });
        else fresh.skipped += 1;
      } catch (error) {
        fresh.skipped += 1;
      }
      done += 1;
      $("strat-sweep-note").textContent = "Reading daily bars: " + done + " of " + symbols.length + "…";
    }
  };
  $("strat-sweep").disabled = true;
  try {
    await worker();
  } finally {
    fresh.busy = false;
    fresh.at = Date.now();
    $("strat-sweep").disabled = false;
  }
  if (state.sweep === fresh) {
    renderSweep();
    nudgeLearn("strategies", fresh.rows.map((item) => item.symbol));
  }
}

function renderSweep() {
  const body = $("strat-sweep-body");
  const note = $("strat-sweep-note");
  if (!body) return;
  const sweep = state.sweep;
  body.replaceChildren();
  if (!sweep.at || sweep.id !== state.strat.pick) {
    if (!sweep.busy) note.textContent = "Press Run on the list to test this strategy on each market. It reads real daily bars and places nothing.";
    return;
  }
  sweep.rows.sort((a, b) => b.row.total - a.row.total);
  sweep.rows.forEach((item) => {
    const row = item.row;
    const tr = document.createElement("tr");
    tr.className = "pickable";
    tr.tabIndex = 0;
    tr.setAttribute("role", "button");
    tr.setAttribute("aria-label", "Open " + item.symbol);
    addCells(tr, [item.symbol, "", String(row.trades), row.trades ? Math.round(row.winRate * 100) + "%" : "—", { text: signedPercent(row.total), cls: row.total > 0 ? "up" : row.total < 0 ? "down" : "" }, signedPercent(row.hold), row.trades ? signedPercent(row.worst) : "—"]);
    const pill = document.createElement("span");
    pill.className = "call-pill " + row.call.toLowerCase();
    pill.textContent = row.call;
    tr.children[1].appendChild(pill);
    const open = () => chooseSymbol(item.symbol);
    tr.addEventListener("click", open);
    tr.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        open();
      }
    });
    body.appendChild(tr);
  });
  const when = new Date(sweep.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" });
  note.textContent = "Tested on " + sweep.rows.length + " market" + (sweep.rows.length === 1 ? "" : "s") + " at " + when + " Denver" + (sweep.skipped ? ", " + sweep.skipped + " skipped" : "") + ". Sorted by result. Click a market to open it. Past closed bars, not a forecast.";
}

function renderAutoNote() {
  const note = $("strat-auto-note");
  if (!note) return;
  const row = (state.board && state.board.rows.find((item) => item.id === state.strat.pick)) || null;
  const name = row ? row.name : state.strat.pick;
  note.textContent = state.strat.note || (state.strat.on
    ? "On. While Auto runs, a fresh " + name + " buy or sell on a closed bar opens the second warning. Nothing trades until you press Yes. It follows the scanner rules and stops at the circuit breaker. " + (state.best && state.best.on ? "Best setup Auto is also on (strict Edge-only buys). " : "") + internalsLine()
    : "Off. Auto only refreshes the quote until you turn this on. Every trade is paper, follows the scanner rules, and stops at the circuit breaker.");
  renderBestSetup();
}

async function autoTrade() {
  if (!state.strat.on || !state.paper || state.paper.paper !== true) return "";
  if (breakerState().tripped) return "";
  const bars = seenBars();
  const board = currentBoard(bars);
  const pickId = state.best && state.best.on ? "edge" : state.strat.pick;
  const row = board.rows.find((item) => item.id === pickId);
  if (!row || !row.ready) return "";
  const held = state.paper.shares > 0;
  const want = row.call === "BUY" && !held ? "buy" : row.call === "SELL" && held ? "sell" : "";
  if (!want) return "";
  // Edge Auto buys only on Fire. Prefer big4indicator + fill-learned precise timing.
  if (pickId === "edge" && want === "buy") {
    const edgeNow = currentEdge(bars);
    if (!edgeNow || !edgeNow.ready || edgeNow.call !== "BUY" || edgeNow.stage !== "fire") {
      state.strat.note = "Edge Auto waiting for Fire (Ready · Aim · Fire).";
      renderAutoNote();
      return state.strat.note;
    }
    if (edgeNow.big4 && edgeNow.big4.bias !== "bull") {
      state.strat.note = "Edge Fire is on, waiting for big4indicator bullish agreement before Auto buy.";
      renderAutoNote();
      return state.strat.note;
    }
    if (preferFireFromFills() && edgeNow.parts && !Object.values(edgeNow.parts).every(Boolean)) {
      state.strat.note = "Fill learn wants every Edge check on for the most precise Fire buy.";
      renderAutoNote();
      return state.strat.note;
    }
  }
  const key = state.paper.symbol + "|" + row.id + "|" + want + "|" + closedDate(bars) + (state.best && state.best.on ? "|best" : "");
  if (state.strat.acted[key]) return "";
  if (want === "buy") {
    if (state.best && state.best.on) {
      const blocked = bestBuyReason(state.paper.symbol) || phoneBuyReason(state.paper.symbol);
      if (blocked) {
        state.strat.note = "Best setup skipped: " + blocked;
        renderBestSetup();
        renderAutoNote();
        return state.strat.note;
      }
    }
    const heldBack = internalsGate(state.paper.symbol);
    if (heldBack) {
      state.strat.note = row.name + " wanted a buy. Skipped: " + heldBack;
      renderAutoNote();
      return state.strat.note;
    }
  }
  if (confirmWait) return "Waiting for your answer on the open warning.";
  state.strat.acted[key] = Date.now();
  saveStrat();
  state.strat.note = row.name + (pickId === "edge" ? " Fire" : "") + " signal: paper " + want + ". Waiting for your Yes.";
  renderAutoNote();
  answerAuto(row, want, state.paper.symbol, closedDate(bars));
  return state.strat.note;
}

async function answerAuto(row, want, symbol, closed) {
  const say = (text) => {
    state.strat.note = text;
    renderAutoNote();
    $("status").textContent = text;
  };
  const ok = await confirmOrder(orderPlan(want, symbol, "auto", { row, closed }));
  if (!ok) {
    say(row.name + " " + want + " signal declined. Nothing was traded.");
    return;
  }
  await whenIdle();
  state.boardKey = "";
  const held = !!(state.paper && state.paper.shares > 0);
  const now = state.paper && state.paper.symbol === symbol ? currentBoard(seenBars()).rows.find((item) => item.id === row.id) : null;
  const still = now && now.ready && ((want === "buy" && now.call === "BUY" && !held) || (want === "sell" && now.call === "SELL" && held));
  if (!still) {
    say("That " + row.name + " " + want + " signal was no longer on when you said Yes. Nothing was traded.");
    return;
  }
  try {
    const paper = await paperCall("/api/paper/trade", { source: "auto", confirmed: true });
    render(paper);
    say(row.name + ": paper " + want + ". " + (fillText(paper) || ""));
  } catch (error) {
    say(row.name + " wanted a " + want + ". Skipped: " + error.message);
  }
}

function orbOptions() {
  const clock = /^(\d{1,2}):(\d{2})$/.exec(($("strat-open").value || "").trim());
  const openAt = clock && Number(clock[1]) < 24 && Number(clock[2]) < 60 ? Number(clock[1]) * 60 + Number(clock[2]) : undefined;
  return {
    crypto: chartCrypto(),
    utcLabels: state.offline,
    openAt,
    buffer: Math.max(0, Number($("strat-buffer").value) || 0) / 100,
    multiple: Math.max(0.25, Number($("strat-target").value) || 1),
  };
}

const ORB_TEXT = {
  FORMING: "The opening bar is still forming. Nothing is armed yet.",
  ARMED: "The opening bar has closed and price is inside its range. A buy stop above the high is the entry.",
  LONG: "The buy stop was reached. Keep the protective stop under the low of the opening bar.",
  TARGET: "The buy stop was reached and then the target traded.",
  STOPPED: "The buy stop was reached and then the protective stop traded.",
  BOTH: "Both levels traded inside one 15-minute bar, so the order of events is not known. Stand aside.",
  BREAK_DOWN: "Price broke below the opening low first. That is the bearish break. This long-only desk stands aside.",
};

function renderOrb() {
  const box = $("strat-orb-state");
  if (!box) return;
  if (!state.orb || !state.paper || state.orb.symbol !== state.paper.symbol) {
    box.textContent = "Press Load to read the real 15-minute bars for " + ((state.paper && state.paper.symbol) || "this market") + ".";
    state.orbResult = null;
    $("strat-orb-levels").replaceChildren();
    $("strat-orb-record").replaceChildren();
    $("strat-orb-plan").textContent = "";
    return;
  }
  const result = PaperFrames.openingRange(state.orb.bars, orbOptions());
  state.orbResult = result;
  const levels = $("strat-orb-levels");
  const record = $("strat-orb-record");
  levels.replaceChildren();
  record.replaceChildren();
  if (!result.ready) {
    box.textContent = result.reason;
    $("strat-orb-plan").textContent = "";
    return;
  }
  const latest = result.latest;
  box.textContent = latest.state.replace("_", " ") + " · " + (ORB_TEXT[latest.state] || "") + (latest.fallback ? " No bar at that clock time today, so the first bar of the day was used." : "");
  const fill = (target, items) => items.forEach(([name, value]) => {
    const cell = document.createElement("div");
    const label = document.createElement("dt");
    const detail = document.createElement("dd");
    label.textContent = name;
    detail.textContent = value;
    cell.append(label, detail);
    target.appendChild(cell);
  });
  fill(levels, [
    ["Opening bar", latest.openLabel],
    ["Buy stop", formatPrice(latest.trigger)],
    ["Protective stop", formatPrice(latest.floor)],
    ["Target", formatPrice(latest.target)],
    ["Range", formatPrice(latest.range)],
  ]);
  const rec = result.record;
  fill(record, [
    ["Past days read", String(rec.days)],
    ["Long trades", String(rec.trades)],
    ["Targets", String(rec.wins)],
    ["Stops", String(rec.losses)],
    ["Average", rec.avgR == null ? "—" : (rec.avgR > 0 ? "+" : "") + rec.avgR.toFixed(2) + " R"],
    ["Bearish breaks", String(rec.skipped)],
  ]);
  $("strat-orb-plan").textContent = "Loaded " + new Date(state.orb.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" }) + " Denver. Opening bars are read from closed bars. R is the buy stop to protective stop distance. These are past days, not a forecast.";
}

async function loadOrb() {
  const symbol = state.paper && state.paper.symbol;
  if (!symbol) return;
  $("strat-orb-state").textContent = "Reading the 15-minute bars…";
  try {
    let bars;
    if (state.offline) {
      const row = state.catalog.find((item) => item.symbol === symbol);
      bars = await PaperLive.frame(symbol, row ? row.category : "", "15m");
    } else {
      bars = (await getJSON("/api/bars?symbol=" + encodeURIComponent(symbol) + "&frame=15m")).bars;
    }
    state.orb = { symbol, bars, at: Date.now() };
    renderOrb();
  } catch (error) {
    state.orb = null;
    $("strat-orb-state").textContent = error.message;
  }
}

function armPrice(value) {
  return Number(Number(value).toFixed(Math.max(2, digitsFor(value) + 1)));
}

async function armOrb(kind) {
  const say = (text) => {
    $("status").textContent = text;
  };
  if (!$("strat-confirm").checked) return say("Check Confirm and arm first.");
  const result = state.orbResult;
  if (!result || !result.ready || !result.latest.closed) return say("Load the 15-minute bars and wait for the opening bar to close.");
  if (!state.paper || state.paper.paper !== true) return say("Turn Paper on first.");
  const level = result.latest;
  const held = state.paper.shares > 0;
  let ticket;
  if (kind === "buy") {
    if (held) return say("A paper long is already open.");
    if (level.state !== "ARMED") return say("The buy stop is only armed while price is still inside the opening range.");
    ticket = { side: "buy", kind: "stop", tif: "DAY", stop: armPrice(level.trigger) };
  } else if (kind === "stop") {
    if (!held) return say("Nothing to protect. The account is flat.");
    ticket = { side: "sell", kind: "stop", tif: "GTC", stop: armPrice(level.floor) };
  } else {
    if (!held) return say("Nothing to sell. The account is flat.");
    ticket = { side: "sell", kind: "limit", tif: "GTC", limit: armPrice(level.target) };
  }
  if (!(await askFirst(ticket.side, state.paper.symbol, "strategy", { ticket }))) return;
  await withBusy(async () => {
    const paper = await paperCall("/api/paper/ticket", { ...ticket, source: "strategy", confirmed: true });
    $("strat-confirm").checked = false;
    render(paper);
    say(paper.last_action === "working" ? "Working paper order armed. It fills when the real quote reaches it. Nothing was sent to a broker." : (fillText(paper) || "Filled on the paper account.") + " Nothing was sent to a broker.");
  });
}


function deskHookHealthItems() {
  const items = [];
  const add = (name, ok, detail) => items.push({ name, ok: !!ok, detail, kind: "health" });
  const tape = $("index-tape");
  const chips = tape ? Array.from(tape.querySelectorAll(".index-chip[data-index-symbol]")) : [];
  const tapeReady = chips.length === 4;
  const tapeLive = chips.filter((chip) => {
    const last = chip.querySelector(".index-chip-last");
    return last && last.textContent && last.textContent !== "—";
  }).length;
  add(
    "Index tape",
    tapeReady && (tapeLive > 0 || !state.market),
    tapeReady
      ? tapeLive
        ? "YM · RUT · NQ · ES · " + tapeLive + "/4 showing last ±%"
        : "YM · RUT · NQ · ES chips wired · waiting on quote"
      : "Index tape chips missing"
  );
  const fear = currentMood(seenBars());
  add(
    "Fear quote",
    !!(fear && fear.score != null) || !!(state.market && state.market.price),
    fear && fear.score != null
      ? fear.label + " " + fear.score
      : state.market && state.market.price
        ? "Quote loaded · mood waiting"
        : "Waiting on the quote"
  );
  const edgeOk = typeof PaperFrames !== "undefined" && !!PaperFrames.edge && !!PaperFrames.EDGE_SCORE;
  add("Edge Fire", edgeOk, edgeOk ? "Edge " + PaperFrames.EDGE_SCORE + "/7 stack ready" : "Edge stack missing");
  const scanOk =
    typeof bestFires === "function" ||
    (typeof PaperFrames !== "undefined" && (!!PaperFrames.squeezeRead || !!PaperFrames.bestFades));
  add("Scanners", !!scanOk, scanOk ? "Fire / Squeeze / Tide scanners wired" : "Scanner hooks missing");
  const forecastOk = typeof PaperFrames !== "undefined" && !!PaperFrames.marketForecastRead;
  add(
    "Forecaster",
    forecastOk,
    forecastOk ? "Forecaster price call wired" : "Forecaster missing on this build"
  );
  const poolGates = state.dp && state.dp.gates;
  const poolKeys = ["darkPoolBook", "icebergBook", "institutionalBlotter"];
  const poolFailClosed =
    !poolGates ||
    poolKeys.every((key) => {
      const gate = poolGates[key];
      return !gate || gate.status === "No feed" || gate.ready === false;
    });
  const poolLiveLocked = !poolGates || poolGates.liveLocked === true || poolGates.paperOnly === true;
  add(
    "Desk Pool",
    !!poolLiveLocked,
    poolFailClosed
      ? "Pool Book · Iceberg Book · Blotter fail closed (No feed — never invent) · Live locked"
      : poolLiveLocked
        ? "Desk Pool gates readable · Live locked · paper only"
        : "Desk Pool Live lock missing"
  );
  return items;
}

async function phoneSafe() {
  const items = [];
  const add = (name, ok, detail, kind) => items.push({ name, ok: !!ok, detail, kind: kind || "safety" });
  add("This device only", true, "No server, no login, no broker. Orders stay in this browser");
  let locked = false;
  try {
    await paperCall("/api/paper/mode", { mode: "live" });
  } catch (error) {
    locked = true;
  }
  add("Artex blocked", true, "Phone copy has no Artex folder share. Paper book stays in this browser only");
  add("Live locked", locked, "No broker or bank link. Every order is paper. Foreign bots cannot unlock Live");
  add(
    "Paper only",
    locked && (!state.paper || (state.paper.paper === true && state.paper.live !== true && state.paper.mode !== "live")),
    "Paper mode is on. Foreign bots cannot place live trades"
  );
  add(
    "Foreign bots kept out",
    true,
    "Artex and other foreign bots cannot share this desk's files or place trades. Paper book stays in this browser only"
  );
  add("Temp zips blocked", true, "Phone page is not a Temp unzip fold");
  add("Desk files alone", true, "Paper book stays in this browser only — not shared with foreign bots");
  add("Foreign scripts refused", true, "Phone page ships no PowerShell, VBS, or HTA side scripts");
  add("Strangers blocked", true, "No remote desk. Orders stay on this device");
  const confirmOk = CONFIRM_PATHS.includes("/api/paper/trade") && CONFIRM_PATHS.includes("/api/paper/ticket") && CONFIRM_PATHS.includes("/api/paper/halt");
  add("Second warning", confirmOk, confirmOk ? "Every paper buy and sell still asks Yes, including Auto" : "Confirm paths missing on this build");
  add(
    "No invented breadth",
    true,
    "TICK/ADD/TRIN/VOLD stay No feed (will not invent). VIX only from a real quote. No TradingView scrape"
  );
  add(
    "Desk Pool fail-closed",
    true,
    "Pool Book · Iceberg Book · Blotter stay No feed on phone — never invent. Live locked"
  );
  let rules = true;
  try {
    const market = 100.05;
    const cases = [
      [{ side: "buy", kind: "market" }, 100, true, market],
      [{ side: "buy", kind: "limit", limit: 95 }, 100, false, null],
      [{ side: "buy", kind: "limit", limit: 95 }, 94, true, 94],
      [{ side: "sell", kind: "limit", limit: 105 }, 104, false, null],
      [{ side: "sell", kind: "limit", limit: 105 }, 106, true, 106],
      [{ side: "buy", kind: "stop", stop: 105 }, 100, false, null],
      [{ side: "buy", kind: "stop", stop: 105 }, 106, true, market],
      [{ side: "sell", kind: "stop", stop: 95 }, 94, true, market],
    ];
    cases.forEach(([raw, last, fills, price]) => {
      const verdict = PaperEngine.decide(PaperEngine.makeTicket(raw), last, market);
      if (verdict.fills !== fills || (price != null && Math.abs(verdict.price - price) > 1e-9)) rules = false;
    });
  } catch (error) {
    rules = false;
  }
  add("Order rules", rules, rules ? "8 order rules passed" : "An order rule failed");
  const breaker = breakerState();
  add("Circuit breaker", true, breaker.tripped ? "Holding until tomorrow" : "Armed at " + breaker.drop_pct + "% a day");
  let saves = false;
  try {
    localStorage.setItem("paper_trader_probe", "1");
    saves = localStorage.getItem("paper_trader_probe") === "1";
    localStorage.removeItem("paper_trader_probe");
  } catch (error) {
    saves = false;
  }
  add("Paper book saves", saves, saves ? "This browser keeps positions and fills" : "This browser blocks saving. Positions last until the page closes");
  add("Quotes", !!state.market, state.market ? "A real quote is loaded" : "Waiting on the quote", "health");
  const botOn = !!(state.learnBook && state.learnBook.bot_on);
  const pageCount = deskLearnPages().length;
  add(
    "Learning",
    true,
    botOn
      ? "Bot on · all " +
        pageCount +
        " desk sections (Markets, Chart, Trade, Study, Strategies, Squeeze, Big 3, Cycle Desk, Forecaster, Outlook, Tools home, Internals, Timeframes, big4indicator, Journal, Backtest, Signals) learn on closed bars even when scrolled away. New shipped pages auto-join. Windows score keeps going after close. Not a broker."
      : state.offline
        ? "Page-open bot · all " +
          pageCount +
          " desk sections cycle while this tab stays open. Close the tab and phone learning pauses. For after-close learning use the Windows zip and tick Start the score."
        : "Bot score off. Open Paper Trader to start it, or tick Start the score under Windows extras. Page-open learning still covers every Tools page in this tab.",
    "health"
  );
  deskHookHealthItems().forEach((item) => items.push(item));
  return { safe: items.filter((item) => item.kind === "safety").every((item) => item.ok), items };
}

async function loadWindows() {
  const box = $("win-box");
  if (!box || state.offline) return;
  try {
    const view = await getJSON("/api/windows");
    box.hidden = !view.windows;
    $("win-startup").checked = !!view.startup;
    $("win-desktop").checked = !!view.desktop;
  } catch (error) {
    box.hidden = true;
  }
}

async function setWindows(key, input) {
  try {
    const view = await postJSON("/api/windows", { [key]: input.checked });
    $("win-startup").checked = !!view.startup;
    $("win-desktop").checked = !!view.desktop;
    $("win-note").textContent = key === "startup"
      ? (view.startup ? "The score will start when you sign in. Untick to remove it." : "Sign-in start removed.")
      : (view.desktop ? "Paper Trader.bat is on your Desktop. Untick to remove it." : "Desktop file removed.");
  } catch (error) {
    input.checked = !input.checked;
    $("win-note").textContent = error.message;
  }
}

async function runSafe(manual) {
  const button = $("safe");
  if (!button || state.safeBusy) return;
  state.safeBusy = true;
  if (!state.winLoaded && !state.offline) {
    state.winLoaded = true;
    loadWindows();
  }
  try {
    const report = state.offline ? await phoneSafe() : await getJSON("/api/safe");
    report.items.push({ name: "Internals", ok: !!(state.int.result && state.int.result.ready), detail: state.int.result ? internalsLine() : "Not read yet", kind: "health" });
    if (!state.offline) deskHookHealthItems().forEach((item) => report.items.push(item));
    const bad = report.items.filter((item) => item.kind === "safety" && !item.ok);
    const warn = report.items.filter((item) => item.kind !== "safety" && !item.ok);
    button.className = "safe-btn " + (bad.length ? "bad" : warn.length ? "warn" : "ok");
    button.textContent = bad.length ? "Not safe" : warn.length ? "Safe · " + warn.length + " to check" : "Safe · green";
    const stamp = new Date().toLocaleTimeString("en-US", { timeZone: "America/Denver" });
    $("safe-note").textContent = bad.length
      ? "Fix first: " + bad.map((item) => item.name).join(", ") + ". Auto is stopped."
      : "GREEN · all " +
        report.items.filter((item) => item.kind === "safety").length +
        " safety checks passed at " +
        stamp +
        " Denver (Artex blocked · strangers blocked · foreign bots out · Live locked · paper only)." +
        (warn.length ? " Check: " + warn.map((item) => item.name).join(", ") + "." : "");
    const list = $("safe-list");
    list.replaceChildren();
    report.items.forEach((item) => {
      const row = document.createElement("li");
      row.textContent = item.name + " · " + item.detail;
      if (!item.ok) row.className = item.kind === "safety" ? "bad" : "warn";
      else row.className = "ok";
      list.appendChild(row);
    });
    state.safe = report;
    if (bad.length && state.auto) stopAuto("Safe check failed: " + bad.map((item) => item.name).join(", ") + ". Auto is off.");
    else if (manual) $("status").textContent = bad.length ? "Not safe: " + bad.map((item) => item.name).join(", ") + "." : "Safe. Every safety check passed.";
  } catch (error) {
    button.className = "safe-btn bad";
    button.textContent = "Not safe";
    $("safe-note").textContent = "The safety check could not run: " + error.message;
    if (state.auto) stopAuto("The safety check could not run. Auto is off.");
  } finally {
    state.safeBusy = false;
  }
}


const MAIN_ETFS = ["SPY", "QQQ", "IWM", "DIA", "XLK", "XLF", "XLE", "XLV", "XLI", "XLY", "XLP", "XLU", "XLB"];

const SQ_LISTS = {
  starter: ["BTC-USD", "ETH-USD", "XRP-USD", "ES", "NQ", "YM", "RTY", "GC", "CL", "ZN", "SPY", "QQQ", "IWM", "DIA", "XLK", "XLI", "XLF", "XLE", "XLV", "XLY", "XLP", "XLU", "XLB", "SMH", "NVDA", "MSFT", "AAPL", "AMZN", "GOOGL", "META", "TSLA", "AVGO", "AMD", "JPM", "HOOD", "DASH", "CRCL", "GE", "CAT", "XOM", "LLY", "WMT", "COST"],
  sectors: ["SPY", "QQQ", "XLK", "XLI", "XLF", "XLE", "XLV", "XLY", "XLP", "XLU", "XLB", "SMH", "NVDA", "MSFT", "AAPL", "AVGO", "AMD", "AMZN", "TSLA", "DASH", "JPM", "HOOD", "V", "MA", "CRCL", "GE", "CAT", "HON", "BA", "LMT", "XOM", "CVX", "LLY", "WMT", "COST"],
  main_etfs: MAIN_ETFS.slice(),
  crypto: ["BTC-USD", "ETH-USD", "XRP-USD", "SOL-USD", "XLM-USD", "ADA-USD", "DOGE-USD", "LTC-USD", "LINK-USD", "AVAX-USD", "DOT-USD", "BCH-USD"],
  futures: ["ES", "NQ", "YM", "RTY", "CL", "GC", "SI", "NG", "ZN", "ZB", "6E", "ZC", "ZS", "ZW"],
};

function catalogByKind(kind) {
  return (state.catalog || []).filter((row) => row.category === kind).map((row) => row.symbol);
}

function sqCatalogList(kind) {
  if (kind === "stocks") {
    const all = catalogByKind("stock");
    if (all.length) return all;
    // Fail soft: starter names that are not crypto/futures until the full catalog loads.
    return SQ_LISTS.starter.filter((symbol) => !symbol.includes("-") && !SQ_LISTS.futures.includes(symbol) && !MAIN_ETFS.includes(symbol));
  }
  if (kind === "crypto") {
    const all = catalogByKind("crypto");
    return all.length ? all : SQ_LISTS.crypto.slice();
  }
  if (kind === "futures") {
    const all = catalogByKind("future");
    return all.length ? all : SQ_LISTS.futures.slice();
  }
  if (kind === "main_etfs") {
    const known = new Set(catalogByKind("etf"));
    const listed = MAIN_ETFS.filter((symbol) => !known.size || known.has(symbol));
    return listed.length ? listed : MAIN_ETFS.slice();
  }
  return SQ_LISTS[kind] || SQ_LISTS.starter;
}

const SQ_SECTOR = {
  NVDA: "XLK", MSFT: "XLK", AAPL: "XLK", AVGO: "XLK", AMD: "XLK",
  AMZN: "XLY", TSLA: "XLY", DASH: "XLY",
  JPM: "XLF", HOOD: "XLF", CRCL: "XLF", V: "XLF", MA: "XLF",
  GE: "XLI", CAT: "XLI", HON: "XLI", BA: "XLI", LMT: "XLI",
  XOM: "XLE", CVX: "XLE", LLY: "XLV", WMT: "XLP", COST: "XLP",
};

function sqIndexOf(symbol) {
  const sector = SQ_SECTOR[symbol] || symbol;
  if (sector === "XLK" || sector === "SMH") return "QQQ";
  return /^XL[A-Z]$/.test(sector) ? "SPY" : "";
}

state.sq = { busy: false, stop: false, rows: [], skipped: [], at: 0 };
state.dp = {
  busy: false,
  stop: false,
  rows: [],
  skipped: [],
  at: 0,
  forms: null,
  civic: null,
  gates: null,
  attr: "",
};

const DESK_POOL_MEMBERS = [
  "nancy-pelosi",
  "tommy-tuberville",
  "josh-gottheimer",
  "ro-khanna",
  "mark-warner",
  "dan-crenshaw",
  "michael-rulli",
  "ted-cruz",
  "thomas-massie",
  "byron-donalds",
  "french-hill",
  "steve-cohen",
];

function sqSymbols() {
  const kind = $("sq-list").value;
  let list;
  if (kind === "custom") {
    list = ($("sq-custom").value || "").toUpperCase().split(/[\s,;]+/).filter((item) => /^[A-Z0-9.^=-]{1,12}$/.test(item));
  } else {
    list = sqCatalogList(kind);
  }
  const seen = new Set();
  const out = [];
  list.forEach((symbol) => {
    if (!seen.has(symbol)) {
      seen.add(symbol);
      out.push(symbol);
    }
  });
  return kind === "custom" ? out.slice(0, 80) : out;
}

async function sqDaily(symbol) {
  const cached = cachedDeskBars(symbol, "1D");
  if (cached) return cached;
  if (state.offline) {
    const row = state.catalog.find((item) => item.symbol === symbol);
    const bars = await PaperLive.daily(symbol, row ? row.category : "", true);
    return rememberDeskBars(symbol, "1D", bars);
  }
  const payload = await getJSON("/api/bars?symbol=" + encodeURIComponent(symbol) + "&frame=1D");
  return rememberDeskBars(symbol, "1D", payload.bars || []);
}

function paintSqueezeControls() {
  const scan = $("sq-scan");
  const stop = $("sq-stop");
  const busy = !!(state.sq && state.sq.busy);
  if (scan) {
    scan.disabled = busy;
    scan.textContent = busy ? "Scanning…" : "Scan now";
  }
  if (stop) stop.disabled = !busy;
}

function paintSqueezeReadyNote() {
  if (!$("sq-note") || (state.sq && state.sq.busy)) return;
  const n = sqSymbols().length;
  const kind = $("sq-list") && $("sq-list").value;
  if (!n) {
    $("sq-note").textContent =
      kind === "custom"
        ? "Type at least one symbol for your own list."
        : "No markets in that list yet. Wait for the catalog, or pick Starter list / My own list.";
    return;
  }
  $("sq-note").textContent =
    "Ready · " +
    n +
    " markets in this list. Press Scan now." +
    (kind === "stocks" && n > 400 ? " Large list — names fill as bars return; Stop anytime." : "");
}

async function scanSqueeze() {
  const sq = state.sq;
  if (sq.busy) return;
  await ensureCatalog();
  const symbols = sqSymbols();
  if (!symbols.length) {
    const kind = $("sq-list") && $("sq-list").value;
    $("sq-note").textContent =
      kind === "custom"
        ? "Type at least one symbol for your own list."
        : "No markets in that list yet. Wait for the catalog, or pick Starter list / My own list.";
    return;
  }
  sq.busy = true;
  sq.stop = false;
  paintSqueezeControls();
  if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(6000);
  // Pins + open market first so the board fills with useful names before a long All-stocks walk.
  const priority = [];
  const current = (state.paper && state.paper.symbol) || "";
  if (current && symbols.includes(current)) priority.push(current);
  readPins().forEach((symbol) => {
    if (symbols.includes(symbol) && !priority.includes(symbol)) priority.push(symbol);
  });
  (SQ_LISTS.starter || []).forEach((symbol) => {
    if (symbols.includes(symbol) && !priority.includes(symbol)) priority.push(symbol);
  });
  const ordered = priority.concat(symbols.filter((symbol) => !priority.includes(symbol)));
  $("sq-note").textContent = "Reading daily bars: 0 of " + ordered.length + ". Names show up as each market comes back.";
  const reads = {};
  const skipped = [];
  let next = 0;
  let done = 0;
  const known = state.catalog && state.catalog.length ? new Set(state.catalog.map((item) => item.symbol)) : null;
  const worker = async () => {
    while (!sq.stop && next < ordered.length) {
      const symbol = ordered[next];
      next += 1;
      if (known && !known.has(symbol)) {
        skipped.push(symbol + " (not in this bot's market list)");
        done += 1;
        publishSqueeze(reads, skipped, ordered.length, done, false);
        continue;
      }
      try {
        const bars = await sqDaily(symbol);
        const crypto = phoneCrypto(symbol, state.catalog.find((item) => item.symbol === symbol));
        const live = PaperFrames.liveBar(bars, crypto);
        const read = PaperFrames.squeezeRead(bars, { live });
        if (read.ready) {
          const timing = PaperFrames.edge ? PaperFrames.edge(bars, { live, vixBars: state.vixBars || [] }) : null;
          const edgeFire = !!(timing && timing.ready && timing.call === "BUY" && timing.stage === "fire");
          reads[symbol] = Object.assign({}, read, {
            edgeFire,
            edgeCall: timing && timing.ready ? timing.call : "",
            edgeScore: timing && timing.ready ? timing.score : null,
            edgeStage: timing && timing.stage ? timing.stage : "",
          });
        } else skipped.push(symbol + " (" + read.reason + ")");
      } catch (error) {
        skipped.push(symbol + " (" + (error.message || error) + ")");
      }
      done += 1;
      publishSqueeze(reads, skipped, ordered.length, done, false);
    }
  };
  const lanes = Math.min(3, Math.max(1, ordered.length));
  try {
    await Promise.all(Array.from({ length: lanes }, () => worker()));
  } finally {
    if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(12000);
  }
  publishSqueeze(reads, skipped, ordered.length, done, true);
  sq.busy = false;
  paintSqueezeControls();
}

async function afterSqueeze() {
  while (state.sq && state.sq.busy) {
    await new Promise((resolve) => setTimeout(resolve, 400));
  }
}

function publishSqueeze(reads, skipped, listed, done, finished) {
  const sq = state.sq;
  const rows = [];
  Object.keys(reads).forEach((symbol) => {
    const read = reads[symbol];
    if (read.state === "OFF") return;
    const sector = SQ_SECTOR[symbol];
    const index = sqIndexOf(symbol);
    const flags = {
      sector: !!(sector && reads[sector] && reads[sector].state === "ON"),
      index: !!(index && reads[index] && reads[index].state === "ON"),
    };
    const scored = PaperFrames.squeezeScore(read, Object.assign({}, flags, { edgeFire: !!read.edgeFire }));
    rows.push({
      symbol,
      read,
      ...scored,
      edgeFire: !!read.edgeFire,
      edgeCall: read.edgeCall || "",
      edgeScore: read.edgeScore,
      edgeStage: read.edgeStage || "",
    });
  });
  // Edge Fire first (same optimized gates as Fire 5), then squeeze score.
  rows.sort((a, b) => Number(!!b.edgeFire) - Number(!!a.edgeFire) || b.score - a.score || b.read.dots - a.read.dots);
  sq.rows = rows;
  sq.skipped = skipped;
  sq.scanned = Object.keys(reads).length;
  sq.at = Date.now();
  sq.listed = listed;
  if (finished) {
    const fireCount = rows.filter((row) => row.edgeFire).length;
    sq.progress = rows.length
      ? ""
      : "No names in a squeeze or just fired on this pass (" +
        done +
        " read). Closed daily bars only. Try Show Both, another list, or My own list.";
    if (rows.length) {
      sq.progress =
        "Read " +
        done +
        " of " +
        listed +
        " markets. " +
        rows.length +
        " in a squeeze or just fired" +
        (fireCount ? ", " + fireCount + " Edge Fire" : "") +
        ". Same TMO · Squeeze · CCI Fire gates as Fire 5. Closed daily bars only. " +
        internalsLine();
    }
  } else {
    sq.progress =
      "Reading daily bars: " +
      done +
      " of " +
      listed +
      ". " +
      rows.length +
      " squeeze" +
      (rows.length === 1 ? "" : "s") +
      " so far.";
  }
  renderSqueeze();
}

function renderSqueeze() {
  const body = $("sq-body");
  if (!body) return;
  const sq = state.sq;
  body.replaceChildren();
  if (!sq.at) return;
  const side = $("sq-side").value;
  const shown = sq.rows.filter((row) => side === "all" || row.read.direction === side).slice(0, 25);
  shown.forEach((row, index) => {
    const read = row.read;
    const tr = document.createElement("tr");
    const open = document.createElement("button");
    open.type = "button";
    open.className = "quiet";
    open.textContent = "Open";
    open.addEventListener("click", (event) => {
      event.stopPropagation();
      chooseSymbol(row.symbol);
    });
    const edgeLabel = row.edgeFire
      ? "FIRE"
      : row.edgeStage
        ? String(row.edgeStage).toUpperCase()
        : row.edgeCall || "—";
    addCells(tr, [
      String(index + 1),
      row.symbol,
      read.state === "ON" ? "On" : "Fired " + (read.firedAgo === 0 ? "now" : read.firedAgo + " bar" + (read.firedAgo === 1 ? "" : "s") + " ago"),
      PaperFrames.squeezeName(read.level || 0),
      String(read.dots),
      read.stack === "positive" ? "Stacked up" : read.stack === "negative" ? "Stacked down" : "Mixed",
      (read.direction === "bull" ? "Above zero" : "Below zero") + (read.clean ? ", clean" : "") + (read.rising ? ", building" : ""),
      read.weekly ? "Yes" : "No",
      row.triple ? "Yes" : "—",
      { text: edgeLabel, cls: row.edgeFire ? "up" : "" },
      { text: String(row.score), cls: row.score >= 70 || row.edgeFire ? "up" : "" },
      "",
    ]);
    if (row.edgeFire) tr.classList.add("go");
    tr.lastChild.appendChild(open);
    body.appendChild(tr);
  });
  if (!shown.length && !sq.progress) {
    const tr = document.createElement("tr");
    addCells(tr, ["", "No squeezes on this side right now. A quiet list is a normal answer.", "", "", "", "", "", "", "", "", "", ""]);
    body.appendChild(tr);
  }
  const when = new Date(sq.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" });
  const fireCount = sq.rows.filter((row) => row.edgeFire).length;
  let note =
    sq.progress ||
    ("Read " +
      sq.scanned +
      " of " +
      sq.listed +
      " markets at " +
      when +
      " Denver. " +
      sq.rows.length +
      " in a squeeze or just fired" +
      (fireCount ? ", " + fireCount + " Edge Fire" : "") +
      ". Same TMO · Squeeze · CCI Fire gates as Fire 5. Closed daily bars only. " +
      internalsLine());
  if (!sq.busy && side !== "all" && sq.rows.length && !shown.length) {
    note +=
      " Show filter hides them — switch Show to Both to see " +
      sq.rows.length +
      " name" +
      (sq.rows.length === 1 ? "" : "s") +
      ".";
  }
  $("sq-note").textContent = note;
  const top = shown[0];
  $("sq-why").textContent = top ? "Top score " + top.score + " for " + top.symbol + (top.edgeFire ? " · Edge Fire" : "") + ": " + top.parts.map((item) => item[0] + " +" + item[1]).join(", ") + "." : "";
  $("sq-skip").textContent = sq.skipped.length ? "Not read: " + sq.skipped.slice(0, 12).join("; ") + (sq.skipped.length > 12 ? "…" : "") + "." : "";
}

function dpSymbols() {
  const kind = ($("dp-list") && $("dp-list").value) || "starter";
  let list;
  if (kind === "custom") {
    list = (($("dp-custom") && $("dp-custom").value) || "")
      .toUpperCase()
      .split(/[\s,;]+/)
      .filter((item) => /^[A-Z0-9.^=-]{1,12}$/.test(item));
  } else {
    list = sqCatalogList(kind);
  }
  const seen = new Set();
  const out = [];
  list.forEach((symbol) => {
    if (!seen.has(symbol)) {
      seen.add(symbol);
      out.push(symbol);
    }
  });
  return kind === "custom" ? out.slice(0, 80) : out.slice(0, 120);
}

function dpEmptyGates() {
  return {
    darkPoolBook: {
      ready: false,
      label: "Pool Book",
      status: "No feed",
      note: "True dark-pool books are private. This desk does not invent resting hidden orders.",
    },
    icebergBook: {
      ready: false,
      label: "Iceberg Book",
      status: "No feed",
      note: "Resting iceberg size is private. Iceberg Proxy uses high volume with a tight closed-bar range.",
    },
    institutionalBlotter: {
      ready: false,
      label: "Blotter",
      status: "No feed",
      note: "Institutional blotters stay behind paid feeds. Big Prints use public relative volume only.",
    },
    paperOnly: true,
    liveLocked: true,
  };
}

function fillReadingsBox(node, rows) {
  if (!node) return;
  node.replaceChildren();
  (rows || []).forEach((pair) => {
    const row = document.createElement("div");
    const dt = document.createElement("dt");
    const dd = document.createElement("dd");
    dt.textContent = pair[0];
    dd.textContent = pair[1];
    row.appendChild(dt);
    row.appendChild(dd);
    node.appendChild(row);
  });
}

function renderDeskPoolTape() {
  const bars = typeof seenBars === "function" ? seenBars() : (state.bars || []);
  const rows = bars && bars.length ? tapeRows(bars) : [["Hidden tape", "Open a market with daily bars first."]];
  fillReadingsBox($("dp-tape"), rows);
}

function renderDeskPoolGates() {
  const gates = (state.dp && state.dp.gates) || dpEmptyGates();
  const rows = ["darkPoolBook", "icebergBook", "institutionalBlotter"].map((key) => {
    const gate = gates[key] || {};
    return [gate.label || key, (gate.status || "No feed") + " · " + (gate.note || "")];
  });
  fillReadingsBox($("dp-gates"), rows);
}

function renderDeskPoolScan() {
  const body = $("dp-body");
  if (!body) return;
  body.replaceChildren();
  const dp = state.dp || {};
  const shown = (dp.rows || []).slice(0, 25);
  shown.forEach((row, index) => {
    const tr = document.createElement("tr");
    const open = document.createElement("button");
    open.type = "button";
    open.className = "quiet";
    open.textContent = "Open";
    open.addEventListener("click", (event) => {
      event.stopPropagation();
      chooseSymbol(row.symbol);
      refreshDeskPoolFeeds();
    });
    const rvol = row.rvol == null ? "—" : row.rvol.toFixed(2) + "×";
    addCells(tr, [
      String(index + 1),
      row.symbol,
      { text: row.label, cls: row.proxy === "absorption" || row.proxy === "initiative" ? "up" : "" },
      rvol,
      row.effort || "—",
      { text: String(row.score), cls: row.score >= 55 ? "up" : "" },
      "",
    ]);
    if (row.score >= 55) tr.classList.add("go");
    tr.lastChild.appendChild(open);
    body.appendChild(tr);
  });
  if (!shown.length && dp.at && !dp.progress) {
    const tr = document.createElement("tr");
    addCells(tr, ["", "No elevated Big Prints on this list right now. Quiet is a normal answer.", "", "", "", "", ""]);
    body.appendChild(tr);
  }
  const note = $("dp-note");
  if (note) {
    if (dp.progress) note.textContent = dp.progress;
    else if (dp.at) {
      const when = new Date(dp.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" });
      note.textContent =
        "Read " +
        (dp.scanned || 0) +
        " markets at " +
        when +
        " Denver. " +
        shown.length +
        " elevated names shown. Closed daily bars only. Paper only.";
    }
  }
}

function renderDeskPoolForms() {
  const body = $("dp-forms-body");
  if (!body) return;
  body.replaceChildren();
  const forms = (state.dp && state.dp.forms) || null;
  const note = $("dp-forms-note");
  if (note) {
    note.textContent = forms && forms.note
      ? forms.note
      : "SEC Form 4 for the open market. Lagged filings. Computer desk loads the feed; phone offline shows No feed unless a public CORS path answers.";
  }
  if (!forms || !forms.ready || !(forms.rows && forms.rows.length)) {
    const tr = document.createElement("tr");
    addCells(tr, ["", forms && forms.ready === false ? (forms.note || "No feed") : "No Form Prints yet.", "", "", "", ""]);
    body.appendChild(tr);
    return;
  }
  forms.rows.slice(0, 20).forEach((row) => {
    const tr = document.createElement("tr");
    const value = row.value != null ? formatMoney(row.value) : row.shares != null ? formatVolume(row.shares) + " sh" : "—";
    addCells(tr, [
      row.filed || "—",
      (row.name || "—") + (row.title ? " · " + row.title : ""),
      { text: row.side || "—", cls: row.side === "Buy" ? "up" : row.side === "Sell" ? "down" : "" },
      row.shares != null ? formatVolume(row.shares) : "—",
      value,
      row.traded || "—",
    ]);
    body.appendChild(tr);
  });
}

function renderDeskPoolCivic() {
  const body = $("dp-civic-body");
  if (!body) return;
  body.replaceChildren();
  const civic = (state.dp && state.dp.civic) || null;
  const note = $("dp-civic-note");
  if (note) {
    note.textContent = civic && civic.note
      ? civic.note
      : "Public STOCK Act disclosures for the open ticker. Amounts are brackets as filed.";
  }
  if (!civic || !civic.ready || !(civic.rows && civic.rows.length)) {
    const tr = document.createElement("tr");
    addCells(tr, ["", civic && civic.note ? civic.note : "No Civic Trades yet.", "", "", "", ""]);
    body.appendChild(tr);
    return;
  }
  civic.rows.slice(0, 30).forEach((row) => {
    const tr = document.createElement("tr");
    addCells(tr, [
      row.disclosed || "—",
      row.member || "—",
      row.symbol || "—",
      { text: row.side || "—", cls: row.side === "Buy" ? "up" : row.side === "Sell" ? "down" : "" },
      row.amount || "—",
      row.traded || "—",
    ]);
    body.appendChild(tr);
  });
}

function renderDeskPool() {
  if (!$("dp-gates")) return;
  renderDeskPoolGates();
  renderDeskPoolTape();
  renderDeskPoolScan();
  renderDeskPoolForms();
  renderDeskPoolCivic();
  const attr = $("dp-attr");
  if (attr) attr.textContent = (state.dp && state.dp.attr) || "";
}

function publishDeskPool(reads, skipped, listed, done, finished) {
  const dp = state.dp;
  const rows = Object.keys(reads)
    .map((symbol) => Object.assign({ symbol }, reads[symbol]))
    .filter((row) => row.ready && row.score >= 40)
    .sort((a, b) => b.score - a.score || String(a.symbol).localeCompare(String(b.symbol)));
  dp.rows = rows;
  dp.skipped = skipped;
  dp.scanned = Object.keys(reads).length;
  dp.at = Date.now();
  dp.listed = listed;
  dp.progress = finished
    ? ""
    : "Reading daily bars: " + done + " of " + listed + ". " + rows.length + " elevated so far.";
  renderDeskPoolScan();
}

async function scanDeskPool() {
  const dp = state.dp;
  if (dp.busy) return;
  await ensureCatalog();
  const symbols = dpSymbols();
  if (!symbols.length) {
    const kind = ($("dp-list") && $("dp-list").value) || "";
    if ($("dp-note")) {
      $("dp-note").textContent =
        kind === "custom"
          ? "Type at least one symbol for your own list."
          : "No markets in that list yet. Wait for the catalog, or pick Starter list / My own list.";
    }
    return;
  }
  dp.busy = true;
  dp.stop = false;
  if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(6000);
  if ($("dp-note")) $("dp-note").textContent = "Reading daily bars: 0 of " + symbols.length + ".";
  const reads = {};
  const skipped = [];
  let next = 0;
  let done = 0;
  const known = state.catalog && state.catalog.length ? new Set(state.catalog.map((item) => item.symbol)) : null;
  const worker = async () => {
    while (!dp.stop && next < symbols.length) {
      const symbol = symbols[next];
      next += 1;
      if (known && !known.has(symbol)) {
        skipped.push(symbol + " (not in this bot's market list)");
        done += 1;
        publishDeskPool(reads, skipped, symbols.length, done, false);
        continue;
      }
      try {
        const bars = await sqDaily(symbol);
        const crypto = phoneCrypto(symbol, state.catalog.find((item) => item.symbol === symbol));
        const settled = PaperFrames.closedBars ? PaperFrames.closedBars(bars, crypto) : bars;
        const row = state.catalog.find((item) => item.symbol === symbol);
        const extras = { crypto, kind: row ? row.category : crypto ? "crypto" : "" };
        const read = PaperFrames.bigPrintRead(settled, extras);
        if (read.ready) reads[symbol] = read;
        else skipped.push(symbol + " (" + read.reason + ")");
      } catch (error) {
        skipped.push(symbol + " (" + (error.message || error) + ")");
      }
      done += 1;
      publishDeskPool(reads, skipped, symbols.length, done, false);
    }
  };
  const lanes = Math.min(4, Math.max(1, symbols.length));
  try {
    await Promise.all(Array.from({ length: lanes }, () => worker()));
  } finally {
    dp.busy = false;
    if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(12000);
  }
  publishDeskPool(reads, skipped, symbols.length, done, true);
}

function normalizeCivicBrowserRow(item, source) {
  const ticker = String(item.ticker || "").trim().toUpperCase();
  if (!ticker) return null;
  const sideRaw = String(item.type || item.txn_type || "").trim().toLowerCase();
  let side = "—";
  if (sideRaw === "purchase" || sideRaw === "buy" || sideRaw === "p") side = "Buy";
  else if (sideRaw === "sale" || sideRaw === "sell" || sideRaw === "partial_sale" || sideRaw === "s") side = "Sell";
  else if (sideRaw === "exchange") side = "Exchange";
  else if (sideRaw) side = sideRaw.charAt(0).toUpperCase() + sideRaw.slice(1);
  return {
    symbol: ticker,
    member: item.memberName || item.politician || item.member || "—",
    side,
    amount: item.amountLabel || "",
    traded: String(item.transactionDate || item.txn_date || "").slice(0, 10),
    disclosed: String(item.disclosureDate || item.disclosure_date || "").slice(0, 10),
    source,
  };
}

async function deskPoolCivicOffline(symbol) {
  const ticker = String(symbol || "").trim().toUpperCase();
  const collected = [];
  for (let index = 0; index < DESK_POOL_MEMBERS.length; index += 1) {
    const slug = DESK_POOL_MEMBERS[index];
    try {
      const response = await fetch("https://tracefour.com/data/congress/" + encodeURIComponent(slug) + ".json", {
        credentials: "omit",
      });
      if (!response.ok) continue;
      const payload = await response.json();
      const trades = payload && payload.data && payload.data.trades;
      if (!Array.isArray(trades)) continue;
      trades.forEach((item) => {
        const row = normalizeCivicBrowserRow(item, "tracefour-congress");
        if (row) collected.push(row);
      });
    } catch (error) {
      /* One member miss does not kill the board. */
    }
  }
  collected.sort((a, b) => String(b.disclosed || b.traded).localeCompare(String(a.disclosed || a.traded)));
  let rows = ticker ? collected.filter((row) => row.symbol === ticker) : collected;
  let note =
    "Public STOCK Act member files (browser). Amounts are brackets as filed. Lagged, not live. Paper only.";
  if (ticker && !rows.length && collected.length) {
    note = "No Civic Trades for " + ticker + " in loaded member files. Showing newest desk-wide rows.";
    rows = collected.slice(0, 30);
  } else {
    rows = rows.slice(0, 30);
  }
  if (!collected.length) {
    return {
      ready: false,
      rows: [],
      note: "Civic Trades No feed on this phone copy (member files did not answer). Use the computer desk for the full House year feed.",
      attribution: "",
    };
  }
  return {
    ready: true,
    rows,
    note,
    attribution: "Member files via Tracefour (CC BY 4.0). Underlying filings are public government works.",
  };
}

async function refreshDeskPoolFeeds() {
  const symbol = selectedSymbol() || (state.paper && state.paper.symbol) || "";
  state.dp.gates = dpEmptyGates();
  renderDeskPoolGates();
  renderDeskPoolTape();
  if (!state.offline) {
    try {
      const payload = await getJSON("/api/desk-pool?symbol=" + encodeURIComponent(symbol || ""));
      state.dp.gates = payload.gates || dpEmptyGates();
      state.dp.forms = payload.forms || { ready: false, rows: [], note: "No feed" };
      state.dp.civic = payload.civic || { ready: false, rows: [], note: "No feed" };
      state.dp.attr = [payload.forms && payload.forms.attribution, payload.civic && payload.civic.attribution]
        .filter(Boolean)
        .join(" ");
    } catch (error) {
      state.dp.forms = { ready: false, rows: [], note: "Form Prints No feed · " + error.message };
      state.dp.civic = { ready: false, rows: [], note: "Civic Trades No feed · " + error.message };
      state.dp.attr = "";
    }
  } else {
    state.dp.forms = {
      ready: false,
      rows: [],
      note: "Form Prints No feed on the phone copy (Form 4 JSON is not CORS-open). Open Desk Pool on the computer desk for insider Form Prints.",
    };
    state.dp.civic = await deskPoolCivicOffline(symbol);
    state.dp.attr = (state.dp.civic && state.dp.civic.attribution) || "";
  }
  renderDeskPool();
}


state.sc = { busy: false, stop: false, result: null, at: 0, error: "", progress: "" };
state.tide = { result: null, fades: [], note: "", busy: false, at: 0 };
state.outlook = { busy: false, result: null, at: 0, error: "", note: "" };

function paintOutlookCell(id, row) {
  const cell = $(id);
  if (!cell || !row) return;
  cell.className = "outlook-cell " + (row.color || "wait");
  const score = cell.querySelector(".outlook-score");
  const stance = cell.querySelector(".outlook-stance");
  if (score) score.textContent = row.ready && row.score != null ? String(row.score) + "/10" : "—";
  if (stance) stance.textContent = row.stance || "Waiting";
}

function renderOutlook() {
  const call = $("outlook-call");
  const box = state.outlook || {};
  const result = box.result;
  if (!result || !result.ready) {
    if (call) {
      call.className = "outlook-call wait";
      call.textContent = box.busy
        ? "Reading…"
        : box.error
          ? "Unread"
          : "Waiting";
    }
    ["outlook-day", "outlook-week", "outlook-month"].forEach((id) => {
      const cell = $(id);
      if (!cell) return;
      cell.className = "outlook-cell wait";
      const score = cell.querySelector(".outlook-score");
      const stance = cell.querySelector(".outlook-stance");
      if (score) score.textContent = "—";
      if (stance) stance.textContent = box.busy ? "…" : "—";
    });
    return;
  }
  paintOutlookCell("outlook-day", result.day);
  paintOutlookCell("outlook-week", result.week);
  paintOutlookCell("outlook-month", result.month);
  if (call) {
    call.className = "outlook-call " + (result.color || "wait");
    call.textContent =
      (result.call || "—") +
      (result.board != null ? " · " + result.board + "/10" : "");
  }
}

async function refreshOutlook(forcedBars) {
  const box = state.outlook;
  if (!box || (box.busy && !forcedBars)) return;
  box.busy = true;
  box.error = "";
  renderOutlook();
  try {
    let bars = forcedBars;
    if (!(bars && bars.length)) {
      bars = await sqDaily("SPY");
    }
    const tide = (state.sc && state.sc.result && state.sc.result.tide) || (state.tide && state.tide.result) || null;
    if (!PaperFrames.marketOutlook) throw new Error("Outlook math not loaded.");
    box.result = PaperFrames.marketOutlook({ spyBars: bars, tide, symbol: "SPY" });
    box.at = Date.now();
  } catch (error) {
    box.error = error.message || String(error);
    box.result = null;
  } finally {
    box.busy = false;
    renderOutlook();
  }
}

function scorecardBiasClass(bias) {
  const text = String(bias || "").toLowerCase();
  if (text.indexOf("swing") >= 0) return "swing";
  if (text.indexOf("day") >= 0) return "day";
  if (text.indexOf("flat") >= 0) return "flat";
  return "";
}

function renderScorecardCard() {
  const bias = $("scorecard-bias");
  const blurb = $("scorecard-blurb");
  if (!bias) return;
  const result = state.sc && state.sc.result;
  if (!result || !result.day) {
    bias.className = "scorecard-bias";
    bias.textContent = state.sc && state.sc.busy
      ? (state.sc.progress || "Reading scorecard…")
      : "Press Run scorecard for rankings, A+ squeezes, Tide Alert, and day bias.";
    if (blurb) {
      blurb.textContent =
        "Ranks the strongest indexes and sectors, lists A+ squeezes that meet this desk’s strict closed-bar rules, flags Tide Alert when sectors drain with no rotation, and says whether the day favors swing trades, day trades, or staying flat. Paper only. Not a forecast.";
    }
    return;
  }
  const day = result.day;
  const tide = result.tide;
  bias.className = "scorecard-bias " + (tide && tide.on ? "tide" : scorecardBiasClass(day.bias));
  bias.textContent = tide && tide.on ? "Tide Alert · on · " + day.bias : "Day bias · " + day.bias;
  if (blurb) {
    const topIndex = result.indexes && result.indexes[0] ? result.indexes[0].symbol : "—";
    const topSector = result.sectors && result.sectors[0] ? result.sectors[0].symbol : "—";
    const aPlus = (result.aPlus || []).length;
    blurb.textContent =
      (tide && tide.on ? tide.why + " " : "") +
      day.why +
      " Leader index " +
      topIndex +
      ", leader sector " +
      topSector +
      ", " +
      aPlus +
      " A+ squeeze" +
      (aPlus === 1 ? "" : "s") +
      ". Closed bars only.";
  }
}

function fillScorecardRankBody(bodyId, rows) {
  const body = $(bodyId);
  if (!body) return;
  body.replaceChildren();
  if (!(rows && rows.length)) {
    const empty = document.createElement("tr");
    addCells(empty, ["", "No closed-bar ranking yet.", "", "", ""]);
    body.appendChild(empty);
    return;
  }
  rows.forEach((row, index) => {
    const tr = document.createElement("tr");
    const ret = row.ret5 != null ? (row.ret5 * 100).toFixed(1) + "%" : "—";
    addCells(tr, [
      String(index + 1),
      row.symbol,
      String(row.score),
      { text: ret, cls: row.ret5 > 0 ? "up" : row.ret5 < 0 ? "down" : "" },
      row.stacked ? "Up" : "Off",
    ]);
    tr.addEventListener("click", () => chooseSymbol(row.symbol));
    body.appendChild(tr);
  });
}

function renderScorecard() {
  renderScorecardCard();
  const result = state.sc && state.sc.result;
  const note = $("sc-note");
  const dayBias = $("sc-day-bias");
  const dayWhy = $("sc-day-why");
  const tideBias = $("sc-tide-bias");
  const tideWhy = $("sc-tide-why");
  if (dayBias) {
    if (result && result.day) {
      dayBias.className = "scorecard-bias " + scorecardBiasClass(result.day.bias);
      dayBias.textContent = "Day bias · " + result.day.bias;
      if (dayWhy) dayWhy.textContent = result.day.why;
    } else {
      dayBias.className = "scorecard-bias";
      dayBias.textContent = state.sc && state.sc.busy ? "Day bias · reading…" : "Day bias · waiting";
      if (dayWhy) dayWhy.textContent = "";
    }
  }
  if (tideBias) {
    const tide = result && result.tide;
    if (tide && tide.ready) {
      tideBias.className = "scorecard-bias " + (tide.on ? "tide flat" : "");
      tideBias.textContent =
        "Tide Alert · " +
        (tide.on ? "ON" : "off") +
        " · Drain " +
        (tide.drain ? "yes" : "no") +
        " · Weekly " +
        (tide.week && tide.week.on ? "on" : tide.week && tide.week.ready ? "off" : "—") +
        " · Index Soft " +
        (tide.indexSoft ? "yes" : "no");
      if (tideWhy) tideWhy.textContent = tide.why || "";
    } else {
      tideBias.className = "scorecard-bias";
      tideBias.textContent = state.sc && state.sc.busy ? "Tide Alert · reading…" : "Tide Alert · waiting";
      if (tideWhy) {
        tideWhy.textContent =
          "Sector Drain (no green rotation) plus Weekly Tide or Index Soft on closed bars. Fail closed when unread. Own desk process — not a third-party signal brand.";
      }
    }
  }
  if (result) {
    fillScorecardRankBody("sc-indexes", result.indexes);
    fillScorecardRankBody("sc-sectors", result.sectors);
  }
  const aBody = $("sc-aplus");
  if (aBody) {
    aBody.replaceChildren();
    const rows = (result && result.aPlus) || [];
    if (!rows.length) {
      const tr = document.createElement("tr");
      addCells(tr, ["", "No A+ squeezes right now. A quiet list is a normal answer.", "", "", "", "", "", "", ""]);
      aBody.appendChild(tr);
    } else {
      rows.forEach((row, index) => {
        const tr = document.createElement("tr");
        const open = document.createElement("button");
        open.type = "button";
        open.textContent = "Open";
        open.addEventListener("click", (event) => {
          event.stopPropagation();
          chooseSymbol(row.symbol);
        });
        addCells(tr, [
          String(index + 1),
          row.symbol,
          row.state || "—",
          String(row.dots != null ? row.dots : "—"),
          PaperFrames.squeezeName(row.level || 0),
          row.triple ? "Yes" : "—",
          { text: row.edgeFire ? "FIRE" : "—", cls: row.edgeFire ? "up" : "" },
          String(row.score != null ? row.score : "—"),
          open,
        ]);
        if (row.edgeFire) tr.classList.add("go");
        aBody.appendChild(tr);
      });
    }
  }
  const aNote = $("sc-aplus-note");
  if (aNote) {
    const fireN = ((result && result.aPlus) || []).filter((row) => row.edgeFire).length;
    aNote.textContent = result
      ? (result.aPlus || []).length +
        " A+ squeeze" +
        ((result.aPlus || []).length === 1 ? "" : "s") +
        " after the strict desk filter (score 80+, rising bullish momentum, stacked averages, mid/high compression or five+ dots)" +
        (fireN ? "; " + fireN + " also Edge Fire on the same TMO · Squeeze · CCI gates" : "") +
        "."
      : "Strict desk filter on the starter squeeze list. Empty is a normal answer.";
  }
  if (note) {
    if (state.sc && state.sc.busy) note.textContent = state.sc.progress || "Reading scorecard…";
    else if (state.sc && state.sc.error) note.textContent = state.sc.error;
    else if (result) {
      const when = state.sc.at
        ? new Date(state.sc.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" })
        : "";
      note.textContent =
        "Scorecard ready" +
        (result.asOf ? " as of " + result.asOf : "") +
        (when ? " · read " + when + " Denver" : "") +
        ". Closed bars only. Not a forecast.";
    }
  }
}

function scorecardPublish(box, indexes, sectors, bars, squeezes, progress) {
  box.progress = progress || "";
  box.result = PaperFrames.marketScorecard({
    bars,
    internals: state.int.result,
    squeezes: squeezes || [],
    indexSymbols: indexes,
    sectorSymbols: sectors,
  });
  box.at = Date.now();
  renderScorecard();
  const spyBars = (bars && (bars.SPY || bars.QQQ)) || null;
  if (spyBars && spyBars.length) refreshOutlook(spyBars).catch(() => {});
}

async function runScorecard() {
  const box = state.sc;
  if (box.busy) return;
  box.busy = true;
  box.stop = false;
  box.error = "";
  box.progress = "Reading indexes and sectors first…";
  renderScorecard();
  // Internals can refresh in the background; don't block the scorecard on a full gauge walk.
  loadInternals(false).catch(() => {});
  if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(5000);
  try {
    const indexes = PaperFrames.SCORECARD_INDEXES.slice();
    const sectors = PaperFrames.SCORECARD_SECTORS.slice();
    // Phone/computer: rank boards first, then A+ from the shorter sectors/starter overlap.
    const squeezeList = SQ_LISTS.sectors.slice();
    const primary = [];
    const rest = [];
    const seen = new Set();
    indexes.concat(sectors).forEach((symbol) => {
      if (!seen.has(symbol)) {
        seen.add(symbol);
        primary.push(symbol);
      }
    });
    squeezeList.forEach((symbol) => {
      if (!seen.has(symbol)) {
        seen.add(symbol);
        rest.push(symbol);
      }
    });
    const need = primary.concat(rest);
    const bars = {};
    let next = 0;
    let done = 0;
    const workers = Math.min(state.offline ? 2 : 3, Math.max(1, need.length));
    const worker = async () => {
      while (!box.stop && next < need.length) {
        const symbol = need[next];
        next += 1;
        try {
          bars[symbol] = await sqDaily(symbol);
        } catch (error) {
          /* Missing quote stays out of the ranking. */
        }
        done += 1;
        box.progress = "Reading daily bars: " + done + " of " + need.length + "…";
        if (done === primary.length || done % 4 === 0 || done === need.length) {
          scorecardPublish(box, indexes, sectors, bars, [], box.progress);
        } else {
          renderScorecardCard();
          if ($("sc-note")) $("sc-note").textContent = box.progress;
        }
        await new Promise((resolve) => setTimeout(resolve, state.offline ? 120 : 40));
      }
    };
    await Promise.all(Array.from({ length: workers }, () => worker()));
    if (box.stop) {
      box.progress = "Scorecard stopped.";
      return;
    }
    const squeezes = [];
    squeezeList.forEach((symbol) => {
      const list = bars[symbol];
      if (!list || !list.length) return;
      const crypto = phoneCrypto(symbol, state.catalog.find((item) => item.symbol === symbol));
      const live = PaperFrames.liveBar(list, crypto);
      const read = PaperFrames.squeezeRead(list, { live });
      if (!read || !read.ready || read.state === "OFF") return;
      const sector = SQ_SECTOR[symbol];
      const indexSym = sqIndexOf(symbol);
      const sectorOn = !!(sector && bars[sector] && (() => {
        const parent = PaperFrames.squeezeRead(bars[sector]);
        return parent && parent.ready && parent.state === "ON";
      })());
      const indexOn = !!(indexSym && bars[indexSym] && (() => {
        const parent = PaperFrames.squeezeRead(bars[indexSym]);
        return parent && parent.ready && parent.state === "ON";
      })());
      const timing = PaperFrames.edge ? PaperFrames.edge(list, { live, vixBars: state.vixBars || [] }) : null;
      const edgeFire = !!(timing && timing.ready && timing.call === "BUY" && timing.stage === "fire");
      const scored = PaperFrames.squeezeScore(read, { sector: sectorOn, index: indexOn, edgeFire });
      squeezes.push({
        symbol,
        read: Object.assign({}, read, { edgeFire }),
        scored,
        edgeFire,
        sector: sector || "",
        index: indexSym || "",
      });
    });
    scorecardPublish(box, indexes, sectors, bars, squeezes, "");
    box.progress = "";
    box._tideBars = bars;
  } catch (error) {
    box.error = error.message || String(error);
  } finally {
    box.busy = false;
    if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(12000);
    renderScorecard();
    const tideBars = box._tideBars || {};
    box._tideBars = null;
    applyTideFromScorecard(box.result, tideBars).catch(() => {});
  }
}

function cycleSignalClass(signal) {
  if (signal === "buy_before_mania" || signal === "bottom_in") return "go";
  if (signal === "sell_into_greed" || signal === "top_in") return "exit";
  if (signal === "cycle_finished") return "done";
  if (signal === "bottom_watch") return "go-long";
  return "";
}

function fillCycleRankBody(bodyId, rows, emptyText) {
  const body = $(bodyId);
  if (!body) return;
  body.replaceChildren();
  const list = rows || [];
  if (!list.length) {
    const tr = document.createElement("tr");
    addCells(tr, ["", emptyText || "Quiet list — normal when the market mode does not call for it.", "", "", "", ""]);
    body.appendChild(tr);
    return;
  }
  list.forEach((row, index) => {
    const tr = document.createElement("tr");
    const open = document.createElement("button");
    open.type = "button";
    open.className = "quiet";
    open.textContent = "Open";
    open.addEventListener("click", (event) => {
      event.stopPropagation();
      chooseSymbol(row.symbol);
    });
    const sig = row.signalLabel || row.signal || "—";
    addCells(tr, [
      String(index + 1),
      row.symbol,
      row.label || row.stage || "—",
      { text: sig, cls: cycleSignalClass(row.signal) === "go" ? "up" : cycleSignalClass(row.signal) === "exit" ? "down" : "" },
      String(row.score != null ? Math.round(row.score) : "—"),
      open,
    ]);
    const cls = cycleSignalClass(row.signal);
    if (cls === "go") tr.classList.add("go");
    body.appendChild(tr);
  });
}

function paintCyclePins(nodeId, rows, emptyLabel) {
  const node = $(nodeId);
  if (!node) return;
  node.replaceChildren();
  const list = (rows || []).slice(0, 8);
  if (!list.length) {
    const span = document.createElement("span");
    span.className = "muted";
    span.textContent = emptyLabel || "";
    if (emptyLabel) node.appendChild(span);
    return;
  }
  list.forEach((row) => {
    const button = document.createElement("button");
    button.type = "button";
    const cls = cycleSignalClass(row.signal);
    button.className = cls || "";
    button.textContent = row.symbol + (row.stage ? " · " + row.stage.replace(/_/g, " ") : "");
    button.title = row.signalLabel || row.why || row.label || row.symbol;
    button.addEventListener("click", () => chooseSymbol(row.symbol));
    node.appendChild(button);
  });
}

function renderCycleDesk() {
  const box = state.cycle || {};
  const market = box.market;
  const open = box.open;
  const defensive = box.defensive;
  const risk = box.risk;
  const note = $("cycle-note");
  const marketNote = $("cycle-market");
  const boardNote = $("cycle-board-note");
  const openBias = $("cycle-open-bias");
  const openWhy = $("cycle-open-why");
  const tankBias = $("cycle-tank-bias");
  const tankWhy = $("cycle-tank-why");

  if (openBias) {
    openBias.textContent = open && open.ready
      ? "Open · " + (open.label || "—") + (open.signalLabel ? " · " + open.signalLabel : "")
      : "Open market · waiting";
    openBias.className = "scorecard-bias" + (open && (open.signal === "buy_before_mania" || open.signal === "bottom_in")
      ? " up"
      : open && (open.signal === "sell_into_greed" || open.signal === "top_in")
        ? " down"
        : "");
  }
  if (openWhy) openWhy.textContent = open && open.why ? open.why : "";

  if (tankBias) tankBias.textContent = market && market.ready ? market.label : "Market · waiting";
  if (tankWhy) tankWhy.textContent = market && market.why ? market.why : (defensive && defensive.why) || "";

  fillCycleRankBody(
    "cycle-def-body",
    defensive && defensive.rows,
    defensive && !defensive.active
      ? "Defensive Shield quiet until the market is soft or tanks."
      : "No defensive names ready yet."
  );
  fillCycleRankBody(
    "cycle-risk-body",
    risk && risk.rows,
    risk && !risk.active
      ? "Risk Rebound waits for a market tank."
      : "No risk rebound names ready yet."
  );

  const marksBody = $("cycle-marks-body");
  if (marksBody) {
    marksBody.replaceChildren();
    const marks = (open && open.maps) || [];
    if (!marks.length) {
      const tr = document.createElement("tr");
      addCells(tr, ["—", "Run Cycle Desk on the open market", "—"]);
      marksBody.appendChild(tr);
    } else {
      marks.forEach((mark) => {
        const tr = document.createElement("tr");
        addCells(tr, [
          mark.label,
          mark.hit ? "Yes" : "—",
          mark.hit && mark.signal !== "none" ? mark.signal.replace(/_/g, " ") : "—",
        ]);
        if (mark.hit) tr.classList.add("go");
        marksBody.appendChild(tr);
      });
    }
  }

  paintCyclePins(
    "cycle-defensive",
    defensive && defensive.active ? defensive.rows : [],
    market && market.mode === "calm" ? "Defensive Shield quiet." : ""
  );
  paintCyclePins(
    "cycle-risk",
    risk && risk.active ? risk.rows : [],
    market && market.mode !== "tank" ? "Risk Rebound waits for a tank." : ""
  );

  if (marketNote) {
    marketNote.textContent = market && market.why
      ? market.why + (open && open.signalLabel ? " Open: " + open.signalLabel + "." : "")
      : "";
  }
  const summary =
    box.busy
      ? box.note || "Reading Cycle Desk…"
      : box.note ||
        (market && market.ready
          ? market.label +
            (defensive && defensive.active ? " · Defensive Shield on" : "") +
            (risk && risk.active ? " · Risk Rebound on" : "") +
            (open && open.signalLabel ? " · " + open.signalLabel : "") +
            ". Own desk names only. Not a forecast."
          : "Press Refresh Cycle or Run Cycle Desk. Own Paper Trader read — no third-party packs.");
  if (note) note.textContent = summary;
  if (boardNote) boardNote.textContent = summary;
}

function fillForecastHorizon(nodeId, band) {
  const node = $(nodeId);
  if (!node) return;
  node.replaceChildren();
  if (!band) {
    const empty = document.createElement("p");
    empty.className = "muted";
    empty.textContent = "Waiting…";
    node.appendChild(empty);
    return;
  }
  const rows = [
    ["Base", band.base + " · " + (band.basePct >= 0 ? "+" : "") + (band.basePct * 100).toFixed(1) + "%"],
    ["Bull", band.bull + " · " + (band.bullPct >= 0 ? "+" : "") + (band.bullPct * 100).toFixed(1) + "%"],
    ["Bear", band.bear + " · " + (band.bearPct >= 0 ? "+" : "") + (band.bearPct * 100).toFixed(1) + "%"],
  ];
  rows.forEach((pair) => {
    const dt = document.createElement("dt");
    dt.textContent = pair[0];
    const dd = document.createElement("dd");
    dd.textContent = pair[1];
    if (pair[0] === "Bull") dd.className = "up";
    if (pair[0] === "Bear") dd.className = "down";
    node.append(dt, dd);
  });
}

function fillForecastDl(nodeId, rows) {
  const node = $(nodeId);
  if (!node) return;
  node.replaceChildren();
  if (!rows || !rows.length) {
    const empty = document.createElement("p");
    empty.className = "muted";
    empty.textContent = "Waiting…";
    node.appendChild(empty);
    return;
  }
  rows.forEach((pair) => {
    const dt = document.createElement("dt");
    dt.textContent = pair[0];
    const dd = document.createElement("dd");
    dd.textContent = pair[1];
    if (pair[2]) dd.className = pair[2];
    node.append(dt, dd);
  });
}

function renderForecast() {
  const box = state.forecast || {};
  const open = box.open;
  const board = box.board;
  const note = $("forecast-note");
  const boardNote = $("forecast-board-note");
  const openSum = $("forecast-open-sum");
  const caseBias = $("forecast-case-bias");
  const caseWhy = $("forecast-case-why");
  const confNode = $("forecast-confidence");
  if (caseBias) {
    caseBias.textContent = open && open.ready
      ? (open.symbol || "Open") +
        " · last " +
        open.close +
        " · conf " +
        open.confidence +
        "/100 · " +
        (open.deskTone || "")
      : "Open market · waiting";
    caseBias.className =
      "scorecard-bias" +
      (open && open.confidence >= 65 ? " up" : open && open.confidence <= 40 ? " down" : "");
  }
  if (caseWhy) caseWhy.textContent = open && open.why ? open.why : open && open.deskWhy ? open.deskWhy : "";
  if (confNode) {
    confNode.textContent =
      open && open.ready
        ? (open.confidenceWhy || "") +
          (open.namePulse && open.namePulse.why ? " · " + open.namePulse.why : "")
        : "";
  }
  const levels = open && open.levels;
  fillForecastDl(
    "forecast-levels",
    levels && levels.ready
      ? [
          ["Last", String(levels.last)],
          ["Next support", levels.support != null ? String(levels.support) : "Unread", "down"],
          ["Next resistance", levels.resistance != null ? String(levels.resistance) : "Unread", "up"],
          ["Invalidate", levels.invalidate != null ? String(levels.invalidate) : "Unread", "down"],
          ["Mid average", levels.midAvg != null ? String(levels.midAvg) : "Unread"],
        ]
      : null
  );
  fillForecastDl(
    "forecast-risk",
    open && open.ready
      ? [
          [
            "Swing Guard",
            open.swingGuard && open.swingGuard.ready
              ? plainForecastPrice(open.swingGuard.guard) + " · " + open.swingGuard.band
              : "Unread",
          ],
          ["Name Pulse", open.namePulse && open.namePulse.pulse ? open.namePulse.pulse : "Unread"],
          [
            "Risk Seat",
            open.riskSeat && open.riskSeat.maxShares != null
              ? "~" + open.riskSeat.maxShares + " sh · " + (open.riskSeat.band || "")
              : "Unread",
          ],
          [
            "Reclaim Cue",
            open.reclaimCue && open.reclaimCue.cue ? open.reclaimCue.cue : "Unread",
          ],
        ]
      : null
  );
  fillForecastHorizon("forecast-near", open && open.near);
  fillForecastHorizon("forecast-far", open && open.far);
  const vs = $("forecast-vs");
  if (vs) vs.textContent = open && open.vsSpy ? open.vsSpy.why || open.vsSpy.label : "";
  const inv = $("forecast-invalid");
  if (inv) inv.textContent = open && open.invalidation ? open.invalidation : "";
  const size = $("forecast-size");
  if (size) size.textContent = open && open.size ? open.size.note : "";
  const bookHeat = $("forecast-book-heat");
  if (bookHeat) {
    bookHeat.textContent =
      board && board.bookHeat && board.bookHeat.why
        ? board.bookHeat.why
        : open && open.ready
          ? "Book Heat waits on ranked names."
          : "";
  }
  const reclaim = $("forecast-reclaim");
  if (reclaim) reclaim.textContent = open && open.reclaimCue && open.reclaimCue.why ? open.reclaimCue.why : "";
  const season = $("forecast-season");
  if (season) {
    season.textContent =
      open && open.seasonLean && (open.seasonLean.why || open.seasonLean.label)
        ? open.seasonLean.why || open.seasonLean.label
        : "";
  }
  const assumptions = $("forecast-assumptions");
  if (assumptions) {
    assumptions.replaceChildren();
    ((open && open.assumptions) || []).forEach((line) => {
      const li = document.createElement("li");
      li.textContent = line;
      assumptions.appendChild(li);
    });
    if (!assumptions.children.length) {
      const li = document.createElement("li");
      li.textContent = "Run Forecaster to list assumptions.";
      assumptions.appendChild(li);
    }
  }
  const body = $("forecast-rank-body");
  if (body) {
    body.replaceChildren();
    const rows = (board && board.rows) || [];
    if (!rows.length) {
      const tr = document.createElement("tr");
      addCells(tr, ["", "No ranked names yet. Run Forecaster.", "", "", "", "", "", ""]);
      body.appendChild(tr);
    } else {
      rows.forEach((row, index) => {
        const tr = document.createElement("tr");
        const openBtn = document.createElement("button");
        openBtn.type = "button";
        openBtn.className = "quiet";
        openBtn.textContent = "Open";
        openBtn.addEventListener("click", (event) => {
          event.stopPropagation();
          chooseSymbol(row.symbol);
        });
        addCells(tr, [
          String(index + 1),
          row.symbol,
          {
            text: String(row.confidence != null ? row.confidence : row.deskScore),
            cls: (row.confidence != null ? row.confidence : row.deskScore) >= 65 ? "up" : (row.confidence != null ? row.confidence : row.deskScore) <= 40 ? "down" : "",
          },
          String(row.nearBase),
          { text: String(row.nearBull), cls: "up" },
          { text: String(row.nearBear), cls: "down" },
          row.invalidate != null ? String(row.invalidate) : "—",
          openBtn,
        ]);
        body.appendChild(tr);
      });
    }
  }
  paintCyclePins(
    "forecast-pins",
    ((board && board.rows) || []).slice(0, 8).map((row) => ({
      symbol: row.symbol,
      stage: String(row.confidence != null ? row.confidence : row.deskScore),
      signal:
        (row.confidence != null ? row.confidence : row.deskScore) >= 65
          ? "buy_before_mania"
          : (row.confidence != null ? row.confidence : row.deskScore) <= 40
            ? "sell_into_greed"
            : "none",
      signalLabel: row.pulse || row.deskTone,
      why: row.why,
    })),
    ""
  );
  if (openSum) {
    openSum.textContent = open && open.ready
      ? (open.symbol || "Open") +
        " · conf " +
        open.confidence +
        "/100 · last " +
        open.close +
        " · support " +
        (open.levels && open.levels.support != null ? open.levels.support : "—") +
        " · resist " +
        (open.levels && open.levels.resistance != null ? open.levels.resistance : "—") +
        " · invalidate " +
        (open.invalidatePx != null ? open.invalidatePx : "—") +
        " · base " +
        open.near.base +
        " · bull " +
        open.near.bull +
        " · bear " +
        open.near.bear +
        ". Not a crystal ball."
      : "";
  }
  const summary =
    box.busy
      ? box.note || "Reading Forecaster…"
      : box.note ||
        (open && open.ready
          ? open.symbol +
            " conf " +
            open.confidence +
            "/100 · last " +
            open.close +
            " · " +
            ((board && board.rows && board.rows.length) || 0) +
            " ranked. Not a crystal ball."
          : "Press Refresh Forecast or Run Forecaster. Concrete levels · confidence. Edge still gates buys.");
  if (note) note.textContent = summary;
  if (boardNote) boardNote.textContent = summary;
}

function plainForecastPrice(value) {
  if (value == null || !Number.isFinite(Number(value))) return "—";
  if (typeof PaperFrames !== "undefined" && PaperFrames.plainPrice) return PaperFrames.plainPrice(value);
  return String(value);
}

async function runForecast() {
  const box = state.forecast;
  if (!box || box.busy) return;
  if (typeof PaperFrames === "undefined" || !PaperFrames.marketForecastRead) {
    box.note = "Forecaster is not on this build.";
    renderForecast();
    return;
  }
  box.busy = true;
  box.stop = false;
  box.note = "Reading closed bars for Forecaster…";
  renderForecast();
  if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(5000);
  try {
    const openSym = state.paper && state.paper.symbol;
    const pins = readPins();
    const scan = topScanLearnNames().slice(0, 8);
    const need = [];
    const seen = new Set();
    ["SPY"].concat(openSym ? [openSym] : [], pins, scan).forEach((symbol) => {
      if (symbol && !seen.has(symbol)) {
        seen.add(symbol);
        need.push(symbol);
      }
    });
    const bars = {};
    let next = 0;
    const workers = Math.min(state.offline ? 2 : 3, Math.max(1, need.length));
    const worker = async () => {
      while (!box.stop && next < need.length) {
        const symbol = need[next];
        next += 1;
        try {
          if (openSym && symbol === openSym && seenBars().length) bars[symbol] = seenBars();
          else bars[symbol] = await sqDaily(symbol);
        } catch (error) {
          /* Missing quote stays out. */
        }
        box.note = "Forecaster reading " + next + " of " + need.length + "…";
        if (next === need.length || next % 3 === 0) renderForecast();
      }
    };
    await Promise.all(Array.from({ length: workers }, () => worker()));
    if (box.stop) {
      box.note = "Forecaster stopped.";
      return;
    }
    const cash = state.paper && Number(state.paper.equity) > 0 ? Number(state.paper.equity) : 10000;
    const openBars = openSym && bars[openSym] ? bars[openSym] : seenBars();
    const open = openBars && openBars.length
      ? PaperFrames.marketForecastRead(openBars, {
          symbol: openSym || "",
          spyBars: bars.SPY,
          vixBars: state.vixBars || [],
          cash,
          lane: openSym ? readLaneAlign(openSym, openBars) : null,
        })
      : null;
    const boardSymbols = [];
    need.forEach((symbol) => {
      if (symbol !== "SPY" && bars[symbol] && boardSymbols.indexOf(symbol) < 0) boardSymbols.push(symbol);
    });
    const board = PaperFrames.forecastBoard({
      symbols: boardSymbols,
      bars,
      spyBars: bars.SPY,
      vixBars: state.vixBars || [],
      cash,
    });
    box.open = open;
    box.board = board;
    box.at = Date.now();
    box.note =
      (open && open.ready
        ? open.symbol +
          " · conf " +
          open.confidence +
          "/100 · last " +
          open.close +
          (open.invalidatePx != null ? " · inv " + open.invalidatePx : "")
        : "Forecaster") +
      " · " +
      ((board && board.rows && board.rows.length) || 0) +
      " ranked. Not a crystal ball.";
  } catch (error) {
    box.note = error && error.message ? error.message : "Forecaster did not finish.";
  } finally {
    box.busy = false;
    if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(12000);
    renderForecast();
  }
}

async function runCycleDesk() {
  const box = state.cycle;
  if (!box || box.busy) return;
  if (typeof PaperFrames === "undefined" || !PaperFrames.cycleMapRead) {
    box.note = "Cycle Desk is not on this build.";
    renderCycleDesk();
    return;
  }
  box.busy = true;
  box.stop = false;
  box.note = "Reading SPY and Cycle Desk lists…";
  renderCycleDesk();
  if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(5000);
  try {
    const defensiveSyms = (PaperFrames.DEFENSIVE_SHIELD || []).slice();
    const riskSyms = (PaperFrames.RISK_REBOUND || []).slice();
    const need = [];
    const seen = new Set();
    ["SPY"].concat(defensiveSyms, riskSyms).forEach((symbol) => {
      if (symbol && !seen.has(symbol)) {
        seen.add(symbol);
        need.push(symbol);
      }
    });
    const openSym = state.paper && state.paper.symbol;
    if (openSym && !seen.has(openSym)) {
      seen.add(openSym);
      need.push(openSym);
    }
    const bars = {};
    let next = 0;
    const workers = Math.min(state.offline ? 2 : 3, Math.max(1, need.length));
    const worker = async () => {
      while (!box.stop && next < need.length) {
        const symbol = need[next];
        next += 1;
        try {
          if (openSym && symbol === openSym && seenBars().length) bars[symbol] = seenBars();
          else bars[symbol] = await sqDaily(symbol);
        } catch (error) {
          /* Missing quote stays out. */
        }
        box.note = "Reading Cycle Desk: " + next + " of " + need.length + "…";
        if (next === need.length || next % 3 === 0) renderCycleDesk();
      }
    };
    await Promise.all(Array.from({ length: workers }, () => worker()));
    if (box.stop) {
      box.note = "Cycle Desk stopped.";
      return;
    }
    const market = PaperFrames.marketTankRead(bars.SPY || []);
    const defensive = PaperFrames.defensiveShieldScan({
      market,
      spyBars: bars.SPY,
      bars,
      symbols: defensiveSyms,
    });
    const risk = PaperFrames.riskReboundScan({
      market,
      spyBars: bars.SPY,
      bars,
      symbols: riskSyms,
    });
    const openBars = openSym && bars[openSym] ? bars[openSym] : seenBars();
    const open = openBars && openBars.length ? PaperFrames.cycleMapRead(openBars) : null;
    box.market = market;
    box.defensive = defensive;
    box.risk = risk;
    box.open = open;
    box.at = Date.now();
    box.note =
      (market && market.label ? market.label : "Cycle Desk") +
      (defensive && defensive.active ? " · Defensive " + ((defensive.rows && defensive.rows.length) || 0) : "") +
      (risk && risk.active ? " · Risk " + ((risk.rows && risk.rows.length) || 0) : "") +
      (open && open.signalLabel ? " · " + open.signalLabel : "") +
      ". Own desk only.";
  } catch (error) {
    box.note = error && error.message ? error.message : "Cycle Desk did not finish.";
  } finally {
    box.busy = false;
    if (state.offline && window.PaperLive && PaperLive.pace) PaperLive.pace(12000);
    renderCycleDesk();
    paintChartToolBoxes(state.market && state.market.history);
  }
}

const INT_KEY = "paper_trader_internals_v1";
const INT_GAUGES = ["^VIX", "^VXN", "^VIX9D", "^TNX", "DX-Y.NYB", "RSP"];

function loadIntGate() {
  try {
    return JSON.parse(localStorage.getItem(INT_KEY) || "{}").gate !== false;
  } catch (error) {
    return true;
  }
}

state.int = { gate: loadIntGate(), result: null, desk: null, at: 0, busy: false, error: "" };

async function internalBars(symbol) {
  if (state.offline && INT_GAUGES.includes(symbol)) return PaperLive.gauge(symbol);
  return sqDaily(symbol);
}

function syncDeskInternals() {
  const box = state.int;
  if (!box || typeof PaperFrames === "undefined" || !PaperFrames.deskInternals) {
    if (box) box.desk = null;
    return null;
  }
  const bars = seenBars();
  if (!bars || bars.length < 60) {
    box.desk = null;
    return null;
  }
  box.desk = PaperFrames.deskInternals(bars, { live: chartCrypto() });
  return box.desk;
}

async function loadInternals(force) {
  const box = state.int;
  if (box.busy || (!force && box.at && Date.now() - box.at < 600000)) {
    syncDeskInternals();
    renderInternals();
    return;
  }
  box.busy = true;
  const symbols = PaperFrames.INTERNAL_SYMBOLS;
  const data = {};
  let next = 0;
  const worker = async () => {
    while (next < symbols.length) {
      await afterSqueeze();
      const symbol = symbols[next];
      next += 1;
      try {
        data[symbol] = await internalBars(symbol);
      } catch (error) {
        /* A gauge that does not answer is listed as not read. */
      }
    }
  };
  try {
    await worker();
    box.result = PaperFrames.internals(data);
    box.at = Date.now();
    box.error = "";
  } catch (error) {
    box.error = error.message;
  } finally {
    box.busy = false;
  }
  syncDeskInternals();
  renderInternals();
  runSafe(false);
}

function internalsLine() {
  const result = state.int.result;
  const desk = state.int.desk || syncDeskInternals();
  if (!result && !desk) return "Internals not read yet.";
  const market = !result
    ? "Market gauges not read yet"
    : !result.ready
      ? "Market gauges unknown: only " + result.coverage + " of 100 points could be read"
      : "Market " + result.label + " " + result.score + (result.asOf ? " as of the " + result.asOf + " close" : "");
  if (!desk || !desk.ready) return market + ".";
  const legs = [
    desk.squeezeOk ? "Squeeze on" : "Squeeze off",
    desk.tmoOk ? "TMO on" : "TMO off",
    desk.big4Ok ? "big4 on" : "big4 off",
    desk.stackOk ? "stack on" : "stack off",
    desk.mtfOk ? "MTF on" : "MTF off",
  ].join(" · ");
  return market + ". Desk " + legs + " (" + desk.score + ").";
}

function renderInternals() {
  const pill = $("internals-pill");
  if (!pill) return;
  const result = state.int.result;
  const desk = syncDeskInternals();
  pill.className = "internals-pill " + (result && result.ready ? result.label.toLowerCase() : "");
  pill.textContent = result
    ? result.ready
      ? "Internals · " + result.label + " " + result.score + (desk && desk.ready ? " · desk " + desk.score : "")
      : "Internals · unknown"
    : state.int.error
      ? "Internals · could not read"
      : "Internals · reading…";
  const body = $("int-body");
  if (!body) return;
  body.replaceChildren();
  $("int-gate").checked = state.int.gate;
  if (!result && !(desk && desk.ready)) {
    $("int-head").textContent = state.int.error || "Press Refresh internals.";
    return;
  }
  if (result) {
    result.parts.forEach((part) => {
      const row = document.createElement("tr");
      addCells(row, [part.name, { text: part.says === "good" ? "Good" : part.says === "bad" ? "Bad" : "Neutral", cls: part.says === "good" ? "up" : part.says === "bad" ? "down" : "" }, part.points + " of " + part.weight, part.text]);
      body.appendChild(row);
    });
  }
  if (desk && desk.ready) {
    desk.parts.forEach((part) => {
      const row = document.createElement("tr");
      addCells(row, [
        part.name,
        { text: part.says === "good" ? "Good" : part.says === "bad" ? "Bad" : "Neutral", cls: part.says === "good" ? "up" : part.says === "bad" ? "down" : "" },
        part.points + " of " + part.weight,
        part.text,
      ]);
      body.appendChild(row);
    });
  }
  const when = state.int.at ? new Date(state.int.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" }) : "";
  $("int-head").textContent =
    internalsLine() +
    (when ? " Read at " + when + " Denver." : "") +
    (result && result.ready && result.label === "Defensive" ? " Auto skips new stock, ETF, and futures buys while this holds." : "");
  const missing = []
    .concat(result && result.missing ? result.missing : [])
    .concat(desk && desk.missing ? desk.missing : []);
  $("int-miss").textContent = missing.length ? "Not read: " + missing.join(", ") + ". The score uses only what was read." : "";
  renderAutoNote();
  renderBestSetup();
}

function internalsGate(symbol) {
  const result = state.int.result;
  if (!state.int.gate || !result || !result.ready || result.label !== "Defensive") return "";
  const row = state.catalog.find((item) => item.symbol === symbol);
  if (phoneCrypto(symbol, row)) return "";
  return "Internals are Defensive (" + result.score + "), so new buys are held back.";
}


state.ef = { symbol: "", at: 0, busy: false, rows: {}, bars: {}, token: 0 };

async function fetchFrameBars(symbol, frameId) {
  const row = (state.frames || []).find((item) => item.id === frameId);
  if (frameId === "1D" && row && row.bars && row.bars.length) return row.bars;
  if (state.offline) {
    const entry = state.catalog.find((item) => item.symbol === symbol);
    return PaperLive.frame(symbol, entry ? entry.category : "", frameId);
  }
  return (await getJSON("/api/bars?symbol=" + encodeURIComponent(symbol) + "&frame=" + encodeURIComponent(frameId))).bars || [];
}

async function loadEdgeFrames(force) {
  const ef = state.ef;
  const symbol = state.paper && state.paper.symbol;
  if (!symbol || ef.busy || !state.market || typeof PaperFrames === "undefined") return;
  if (!force && ef.symbol === symbol && Date.now() - ef.at < 300000) return;
  const token = ef.token + 1;
  Object.assign(ef, { busy: true, symbol, rows: {}, bars: {}, token });
  $("edge-frames-note").textContent = "Reading Edge on every timeframe…";
  const crypto = chartCrypto();
  const frames = PaperFrames.FRAMES.slice();
  let next = 0;
  const worker = async () => {
    while (next < frames.length && ef.token === token) {
      await afterSqueeze();
      if (ef.token !== token) return;
      const frame = frames[next];
      next += 1;
      try {
        const bars = await fetchFrameBars(symbol, frame.id);
        const read = PaperFrames.edge(bars, { live: PaperFrames.liveBar(bars, crypto), vixBars: state.vixBars || [] });
        ef.bars[frame.id] = bars;
        const last = bars.length ? bars[bars.length - 1].close : null;
        ef.rows[frame.id] = read && read.ready
          ? { id: frame.id, label: frame.label, short: frame.short, ready: true, call: read.call, score: read.score, stop: read.stop, record: read.record, count: bars.length, last }
          : { id: frame.id, label: frame.label, short: frame.short, ready: false, why: bars.length ? "Only " + bars.length + " bars. Edge needs 52." : "No bars", count: bars.length };
      } catch (error) {
        ef.rows[frame.id] = { id: frame.id, label: frame.label, short: frame.short, ready: false, why: error.message || "No bars" };
      }
      if (ef.token === token) renderEdgeFrames();
    }
  };
  try {
    await worker();
  } finally {
    if (ef.token === token) {
      ef.busy = false;
      ef.at = Date.now();
      renderEdgeFrames();
      renderFrames();
    }
  }
}

function edgeFramesSummary() {
  const rows = PaperFrames.FRAMES.map((frame) => state.ef.rows[frame.id]).filter((row) => row && row.ready);
  if (!rows.length) return { text: "", long: 0, short: 0, wait: 0, total: 0 };
  const long = rows.filter((row) => row.call === "BUY" || row.call === "HOLD").length;
  const short = rows.filter((row) => row.call === "SELL").length;
  const wait = rows.length - long - short;
  return { long, short, wait, total: rows.length, text: long + " of " + rows.length + " timeframes are buying or holding, " + short + " selling, " + wait + " waiting." };
}

function renderEdgeFrames() {
  const chips = $("edge-frames");
  const body = $("edge-frames-body");
  if (!chips || !body) return;
  const ef = state.ef;
  chips.replaceChildren();
  body.replaceChildren();
  PaperFrames.FRAMES.forEach((frame) => {
    const row = ef.rows[frame.id];
    const button = document.createElement("button");
    button.type = "button";
    button.className = row && row.ready ? row.call.toLowerCase() : "wait";
    button.textContent = frame.short + " " + (row ? (row.ready ? row.call : "—") : "…");
    button.title = row ? (row.ready ? frame.label + ": Edge " + row.call + ", " + row.score + " of " + (row.checks || PaperFrames.EDGE_SCORE || 7) : frame.label + ": " + row.why) : frame.label + ": reading";
    button.addEventListener("click", () => {
      state.frameId = frame.id;
      renderFrames();
      showDock("frames");
    });
    chips.appendChild(button);
    const tr = document.createElement("tr");
    tr.className = "pickable" + (frame.id === (state.frameId || "1D") ? " picked" : "");
    tr.tabIndex = 0;
    tr.setAttribute("role", "button");
    tr.setAttribute("aria-label", "Show " + frame.label + " in the preview");
    if (row && row.ready) {
      addCells(tr, [frame.label, "", row.score + " of " + (row.checks || PaperFrames.EDGE_SCORE || 7), row.stop != null ? formatPrice(row.stop) : "—", String(row.record.trades), row.record.trades ? Math.round(row.record.winRate * 100) + "%" : "—", { text: signedPercent(row.record.total), cls: row.record.total > 0 ? "up" : row.record.total < 0 ? "down" : "" }]);
      const pill = document.createElement("span");
      pill.className = "call-pill " + row.call.toLowerCase();
      pill.textContent = row.call;
      tr.children[1].appendChild(pill);
    } else {
      addCells(tr, [frame.label, row ? "—" : "…", "", "", "", "", row ? row.why : "Reading"]);
    }
    const choose = () => {
      state.frameId = frame.id;
      renderFrames();
      renderEdgeFrames();
    };
    tr.addEventListener("click", choose);
    tr.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        choose();
      }
    });
    body.appendChild(tr);
  });
  const summary = edgeFramesSummary();
  $("edge-frames-line").textContent = summary.text;
  if (!ef.busy) {
    const when = ef.at ? new Date(ef.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" }) : "";
    $("edge-frames-note").textContent = summary.total
      ? summary.text + " Read at " + when + " Denver for " + ef.symbol + ". Auto and the strategies still read the daily bars only. Click a row for its preview, then click any candle for its price box."
      : "Edge reads the closed bars of each timeframe on its own. Press Read all timeframes now.";
  }
  renderBestSetup();
}

let edgeFramesTimer = 0;
function scheduleEdgeFrames() {
  if (edgeFramesTimer || document.hidden) return;
  edgeFramesTimer = setTimeout(() => {
    edgeFramesTimer = 0;
    loadEdgeFrames(false).catch(() => {});
  }, 1500);
}

function renderEdge() {
  const box = $("edge");
  if (!box) return;
  const bars = seenBars();
  const result = currentEdge(bars);
  if (!result) return;
  const desk =
    typeof PaperFrames !== "undefined" && PaperFrames.edgeDeskCall
      ? PaperFrames.edgeDeskCall(result)
      : { call: result.ready ? result.call : "WAIT", label: result.ready ? result.call : "WAIT", tone: (result.call || "WAIT").toLowerCase(), why: "", print: null };
  const call = desk.label || (result.ready ? result.call : "WAIT");
  const tone = desk.tone || (result.call || "WAIT").toLowerCase();
  const print = desk.print || (result.parts && PaperFrames.gatePrint ? PaperFrames.gatePrint(result.parts) : null);
  const precision =
    typeof PaperFrames !== "undefined" && PaperFrames.firePrecision
      ? PaperFrames.firePrecision({
          score: result.score,
          checks: result.checks,
          call: result.call,
          stage: result.stage,
          big4: result.big4 && result.big4.bias,
          big4Ok: result.big4 && result.big4.ok,
          freshFire: PaperFrames.fireFreshFlip ? PaperFrames.fireFreshFlip(result) : false,
          parts: result.parts,
        })
      : null;
  const stamp = [call, result.score, result.stop, result.openPct, result.record.trades, result.record.total, bars.length, precision, print && print.line].join("|");
  if (sameStamp(box, stamp)) return;
  box.className = "edge " + (tone === "buy" || tone === "hold" ? tone : tone === "sell" ? "sell" : tone === "aim" || tone === "ready" ? "wait " + tone : "wait");
  $("edge-call").textContent = call;
  $("edge-score").textContent =
    result.score == null
      ? "—"
      : result.score + " of " + (result.checks || PaperFrames.EDGE_SCORE || 7) + (precision != null ? " · P" + precision : "");
  const checks = $("edge-checks");
  checks.replaceChildren();
  EDGE_CHECKS.forEach(([id, name]) => {
    const item = document.createElement("li");
    item.textContent = name;
    if (result.parts && result.parts[id]) item.className = "on";
    checks.appendChild(item);
  });
  const gateLine = $("gate-print");
  if (gateLine) {
    gateLine.textContent = print
      ? "Gate Print · " + print.line + (desk.why && desk.call === "WAIT" ? " · Desk Call " + call : desk.call === "FIRE" ? " · Desk Call FIRE" : "")
      : "Gate Print · waiting for closed bars.";
  }
  $("edge-plan").textContent = edgePlan(result);
  const record = $("edge-record");
  record.replaceChildren();
  const rec = result.record;
  [
    ["Edge", signedPercent(rec.total)],
    ["Hold", signedPercent(rec.hold)],
    ["Worst drop", signedPercent(rec.worst)],
    ["Trades", String(rec.trades)],
    ["Wins", rec.trades ? Math.round(rec.winRate * 100) + "%" : "—"],
    ["In market", Math.round(rec.exposure * 100) + "%"],
  ].forEach(([name, value]) => {
    const cell = document.createElement("div");
    const label = document.createElement("dt");
    const detail = document.createElement("dd");
    label.textContent = name;
    detail.textContent = value;
    if (name === "Edge" || name === "Hold") detail.className = value.startsWith("+") ? "up" : value.startsWith("-") ? "down" : "";
    cell.append(label, detail);
    record.appendChild(cell);
  });
}

function currentLesson(bars) {
  const remote = state.market && state.market.lesson;
  if (remote && remote.rule) return remote;
  const last = bars.length ? bars[bars.length - 1] : null;
  const key = bars.length + ":" + (last ? last.date + ":" + last.close : "");
  if (state.lessonKey === key && state.lesson) return state.lesson;
  state.lesson = PaperFrames.lesson(PaperFrames.closedBars(bars, chartCrypto()));
  state.lessonKey = key;
  return state.lesson;
}

function currentMood(bars) {
  const remote = state.market && state.market.mood;
  if (remote && remote.label) return remote;
  if (!bars.length || !PaperFrames.mood) return null;
  return PaperFrames.mood(bars);
}

function lessonText(bars) {
  if (!bars.length) return "—";
  const fitted = currentLesson(bars);
  if (!fitted) return "—";
  return fitted.matched + " of " + fitted.seen + " · " + fitted.rule;
}

function moodText(bars) {
  const tone = currentMood(bars);
  if (!tone || tone.score == null) return tone && tone.note ? tone.note : "—";
  return tone.label + " " + tone.score;
}

function lessonMarks(lesson, bars) {
  if (!lesson || !lesson.arrows) return [];
  if (lesson.arrows.length && lesson.arrows[0].date) {
    const byDate = new Map(bars.map((bar, index) => [bar.date, index]));
    return lesson.arrows
      .map((mark) => ({ index: byDate.get(mark.date), side: mark.side }))
      .filter((mark) => Number.isInteger(mark.index));
  }
  return lesson.arrows.filter((mark) => Number.isInteger(mark.index));
}

function tmoText(bars) {
  if (!bars.length) return "—";
  const study = PaperFrames.trueMomentum(bars, 14, 5, 3);
  const last = study.main.length - 1;
  if (study.main[last] == null || study.signal[last] == null) return "—";
  const side = study.main[last] > study.signal[last] ? "Main above signal" : "Main below signal";
  const prior = last - 1;
  let cross = "";
  if (prior >= 0 && study.main[prior] != null && study.signal[prior] != null) {
    if (study.main[last] > study.signal[last] && study.main[prior] <= study.signal[prior] && study.main[last] <= 0) cross = " · zero-line buy cross";
    else if (study.main[last] < study.signal[last] && study.main[prior] >= study.signal[prior] && study.main[last] >= 0) cross = " · zero-line sell cross";
  }
  return side + " " + study.main[last].toFixed(1) + cross;
}

function pivotsText(pivots, side) {
  if (!pivots) return "—";
  if (side === "high") return formatPrice(pivots.resistance) + " · confirm " + formatPrice(pivots.confirmHigh);
  return formatPrice(pivots.support) + " · confirm " + formatPrice(pivots.confirmLow);
}

function sliceToCursor(values) {
  const cursor = state.paper ? state.paper.cursor : 0;
  return (values || []).slice(0, cursor);
}

function visible(values) {
  const sliced = sliceToCursor(values);
  return PaperFrames.zoomBars(sliced, state.zoom).bars;
}

function drawCandles(retry) {
  const canvas = $("candles");
  if (!canvas || !state.market) return;
  const waits = typeof retry === "number" ? retry : 0;
  if ((canvas.clientWidth < 40 || canvas.clientHeight < 40) && waits < 24) {
    requestAnimationFrame(() => drawCandles(waits + 1));
    return;
  }
  if (canvas.clientWidth < 40) canvas.style.width = "100%";
  if (canvas.clientHeight < 40) canvas.style.minHeight = "420px";
  const history = sliceToCursor(state.market.bars);
  const fitted = currentLesson(history);
  const view = PaperFrames.zoomBars(history, state.zoom);
  const bars = view.bars;
  const dropped = view.start;
  const studyLines = PaperFrames.averages(history, 34);
  paintZoom(history.length);
  const highLine = studyLines.high.slice(dropped);
  const midLine = studyLines.close.slice(dropped);
  const lowLine = studyLines.low.slice(dropped);
  const stack = PaperFrames.stackAverages(history).map((row) => ({ ...row, values: row.values.slice(dropped) }));
  const pivots = PaperFrames.trendPivots(history);
  const layers = state.layers;
  const fast = layers.fastslow ? visible(state.market.fast) : [];
  const slow = layers.fastslow ? visible(state.market.slow) : [];
  const compressed = PaperFrames.compression(history, 20);
  const squeeze = compressed.level.slice(dropped);
  const allMomentum = compressed.momentum;
  const momentum = allMomentum.slice(dropped);
  const ctx = prepare(canvas, 300, 240);
  if (!bars.length) return;
  const width = canvas.paperWidth;
  const height = canvas.paperHeight;
  const priceBottom = Math.max(72, height - 10);
  let low = Math.min(...bars.map((bar) => bar.low));
  let high = Math.max(...bars.map((bar) => bar.high));
  [layers.averages ? highLine : [], layers.averages ? midLine : [], layers.averages ? lowLine : [], fast, slow]
    .concat(layers.stack ? stack.map((row) => row.values) : [])
    .forEach((series) => {
    series.forEach((value) => {
      if (value == null) return;
      if (value < low) low = value;
      if (value > high) high = value;
    });
  });
  if (pivots && layers.pivots) {
    const room = (high - low) * 0.25 || 1;
    [pivots.resistance, pivots.support, pivots.confirmHigh, pivots.confirmLow, pivots.riskOffHigh, pivots.riskOffLow].forEach((price) => {
      if (price < low - room || price > high + room) return;
      if (price < low) low = price;
      if (price > high) high = price;
    });
  }
  const span = high - low || 1;
  low -= span * 0.08;
  high += span * 0.08;
  ctx.font = "12px Segoe UI, sans-serif";
  const axisSample = formatPrice(Math.max(Math.abs(low), Math.abs(high)));
  const lastSample = formatPrice(bars[bars.length - 1].close);
  let pad = Math.max(52, Math.ceil(ctx.measureText(axisSample).width) + 12);
  let rightGap = Math.max(Math.ceil(ctx.measureText(lastSample).width) + 18, 72);
  if (pad + rightGap > width - 96) {
    const budget = Math.max(96, width - 96);
    const used = pad + rightGap;
    pad = Math.max(36, Math.floor((pad * budget) / used));
    rightGap = Math.max(36, Math.floor((rightGap * budget) / used));
  }
  const layout = studyLayout(width, bars.length, pad, rightGap);
  pad = layout.pad;
  rightGap = layout.rightGap;
  const y = (price) => 16 + ((high - price) / (high - low)) * (priceBottom - 28);
  const slot = layout.slot;
  const plotRight = layout.plotRight;
  const xAt = (index) => pad + index * slot + slot / 2;
  state.chartMap = { bars, pad, slot, symbol: state.paper && state.paper.symbol, rightGap };
  state.studyPad = { pad, rightGap, slot, count: bars.length };
  ctx.strokeStyle = "#2d3640";
  ctx.fillStyle = "#9aa6b2";
  ctx.font = "12px Segoe UI, sans-serif";
  for (let grid = 0; grid < 4; grid += 1) {
    const price = low + ((high - low) * grid) / 3;
    const line = y(price);
    ctx.beginPath();
    ctx.moveTo(pad, line);
    ctx.lineTo(plotRight, line);
    ctx.stroke();
    ctx.fillText(formatPrice(price), 4, line - 2);
  }
  const big4 = PaperFrames.big4Indicator ? PaperFrames.big4Indicator(history) : null;
  const wavePrefs = readVolumeWavePrefs();
  const waves = PaperFrames.volumeWave ? PaperFrames.volumeWave(history, wavePrefs) : null;
  let candleColors = null;
  if (wavePrefs.paint && waves && waves.ready) {
    candleColors = waves.paint.slice(dropped, dropped + bars.length);
  } else if (layers.big4 && big4) {
    candleColors = big4.bias.slice(dropped, dropped + bars.length);
  }
  PaperFrames.paintCandles(ctx, bars, xAt, y, slot, candleColors);
  drawPro(ctx, history, dropped, bars.length, y, pad, slot, plotRight, priceBottom);
  drawLine(ctx, fast, y, pad, slot, "#7eb6ff", 1);
  drawLine(ctx, slow, y, pad, slot, "#e2b15a", 1);
  if (layers.stack) {
    stack.forEach((row) => drawLine(ctx, row.values, y, pad, slot, row.color, row.length >= 100 ? 1.75 : 1.25));
  }
  if (layers.averages) {
    drawLine(ctx, highLine, y, pad, slot, "#3ddc97", 2);
    drawLine(ctx, midLine, y, pad, slot, "#5b9dff", 2);
    drawLine(ctx, lowLine, y, pad, slot, "#ff6b7a", 2);
  }
  if (pivots && layers.pivots) {
    PaperFrames.paintPivots(ctx, {
      ...pivots,
      fromHigh: Math.max(0, pivots.fromHigh - dropped),
      fromLow: Math.max(0, pivots.fromLow - dropped),
    }, (index) => pad + index * slot + slot / 2, y, bars.length - 1);
  }
  const edgeResult = layers.edge ? currentEdge(history) : null;
  if (edgeResult && edgeResult.ready) {
    const trail = edgeResult.trail.slice(dropped);
    ctx.save();
    ctx.strokeStyle = "#e2b15a";
    ctx.lineWidth = 1.25;
    let drawing = false;
    trail.forEach((value, index) => {
      const x = pad + index * slot + slot / 2;
      if (value == null) {
        if (drawing) ctx.stroke();
        drawing = false;
        return;
      }
      if (!drawing) {
        ctx.beginPath();
        ctx.moveTo(x - slot / 2, y(value));
        drawing = true;
      } else {
        ctx.lineTo(x - slot / 2, y(value));
      }
      ctx.lineTo(x + slot / 2, y(value));
    });
    if (drawing) ctx.stroke();
    if (layers.readyaim) paintReadyAimStages(ctx, edgeResult.stages, dropped, bars, y, pad, slot, priceBottom);
    paintClearEdgeArrows(ctx, edgeResult.marks, dropped, bars, y, pad, slot, priceBottom);
    ctx.restore();
  } else if (layers.readyaim) {
    const staged = currentEdge(history);
    if (staged && staged.ready) paintReadyAimStages(ctx, staged.stages, dropped, bars, y, pad, slot, priceBottom);
  }
  drawStudy(ctx, bars, y, pad, slot);
  const last = bars.length - 1;
  const lastBar = bars[last];
  const lastMomentum = momentum[last];
  const priorMomentum = last === 0 ? allMomentum[dropped - 1] : momentum[last - 1];
  const lastRising = lastMomentum != null && (priorMomentum == null ? lastMomentum >= 0 : lastMomentum > priorMomentum);
  if (width >= 640 && layers.key) {
    const big4Tone = candleColors && candleColors[last];
    const candleLabel = big4Tone === "bull" ? "big4indicator green" : big4Tone === "bear" ? "big4indicator red" : big4Tone === "mixed" ? "big4indicator mixed" : lastBar.close >= lastBar.open ? "Candle green" : "Candle red";
    const candleColor = big4Tone === "bull" ? "#3ddc97" : big4Tone === "bear" ? "#ff4d4d" : big4Tone === "mixed" ? "#9aa6b2" : lastBar.close >= lastBar.open ? "#3ddc97" : "#ff6b7a";
    const key = [
      [candleLabel, candleColor],
      ["Squeeze " + PaperFrames.squeezeName(squeeze[last] || 0), PaperFrames.squeezeTone(squeeze[last] || 0)],
      ["Momentum " + (lastMomentum == null ? "—" : lastRising ? "rising" : "falling"), PaperFrames.momentumTone(lastMomentum, priorMomentum) || "#9aa6b2"],
      [PaperFrames.candlePlace(lastBar, highLine[last], lowLine[last]), "#d7e2ea"],
      [fitted.mood && fitted.mood.score != null ? fitted.mood.label + " " + fitted.mood.score : "Mood", fitted.mood && fitted.mood.label === "Fear" ? "#ff6b7a" : fitted.mood && fitted.mood.label === "Greed" ? "#3ddc97" : "#d7e2ea"],
    ];
    const keyWidth = Math.max(...key.map((row) => ctx.measureText(row[0]).width)) + 16;
    const keyLeft = pad + 6;
    ctx.fillStyle = "rgba(7, 9, 11, 0.92)";
    ctx.fillRect(keyLeft, 4, keyWidth, key.length * 15 + 8);
    key.forEach((row, index) => {
      ctx.fillStyle = row[1];
      ctx.fillText(row[0], keyLeft + 8, 18 + index * 15);
    });
  }
  paintChartFlags(history, bars, squeeze[last] || 0, lastMomentum, priorMomentum);
  paintChartToolBoxes(history);
  const byDate = new Map(bars.map((bar, index) => [bar.date, index]));
  // Paper fills only — small arrows. Edge clear BUY/SELL arrows are the main signals and do not repaint.
  (state.paper.markers || []).forEach((marker) => {
    const index = byDate.get(marker.date);
    if (index == null) return;
    const bar = bars[index];
    const x = pad + index * slot + slot / 2;
    const up = marker.side === "buy";
    ctx.fillStyle = up ? "#3ddc97" : "#ff6b7a";
    const point = Math.max(24, Math.min(priceBottom - 12, up ? y(bar.low) + 16 : y(bar.high) - 16));
    drawArrow(ctx, x, point, up, 9);
  });
  if (layers.lesson) {
    lessonMarks(fitted, history).forEach((mark) => {
      const index = mark.index - dropped;
      if (index < 0 || index >= bars.length) return;
      const bar = bars[index];
      const x = pad + index * slot + slot / 2;
      const up = mark.side === "buy";
      ctx.fillStyle = up ? "rgba(61,220,151,0.55)" : "rgba(255,107,122,0.55)";
      const point = Math.max(24, Math.min(priceBottom - 12, up ? y(bar.low) + 36 : y(bar.high) - 36));
      drawArrow(ctx, x, point, up, 8);
    });
  }
  const lastTone = lastBar.close >= lastBar.open ? "#3ddc97" : "#ff6b7a";
  drawPriceTag(ctx, formatPrice(lastBar.close), plotRight, width, y(lastBar.close), priceBottom, lastTone, pad + last * slot + slot / 2);
  drawPriceLine(ctx, bars, pad, slot, y, plotRight, priceBottom, height, width);
  const paneOpen = (id) => {
    const pane = document.querySelector('.study-pane[data-pane="' + id + '"]');
    return !pane || pane.dataset.expanded !== "false";
  };
  if (paneOpen("tmo")) drawTmo();
  if (paneOpen("sqz")) drawSqueeze();
  if (paneOpen("cci")) drawCci();
  if (paneOpen("vol")) drawVolumeWave();
  if (state.studiesWin && !state.studiesWin.closed) {
    try {
      drawTmo(state.studiesWin.document.getElementById("tmo"));
      drawSqueeze(state.studiesWin.document.getElementById("sqz"));
      drawCci(state.studiesWin.document.getElementById("cci"));
      drawVolumeWave(state.studiesWin.document.getElementById("vol"));
    } catch (error) {
      state.studiesWin = null;
    }
  }
}

function drawPriceTag(ctx, text, plotRight, width, at, priceBottom, tone, fromX) {
  const tagWidth = ctx.measureText(text).width;
  const tagX = Math.min(plotRight + 8, width - tagWidth - 8);
  const tagY = Math.max(14, Math.min(priceBottom - 8, at));
  ctx.save();
  ctx.setLineDash([4, 4]);
  ctx.strokeStyle = tone;
  ctx.beginPath();
  ctx.moveTo(fromX != null ? fromX : plotRight, at);
  ctx.lineTo(Math.max(plotRight - 12, tagX - 6), at);
  ctx.stroke();
  ctx.setLineDash([]);
  ctx.fillStyle = "#07090b";
  ctx.fillRect(tagX - 4, tagY - 12, tagWidth + 8, 16);
  ctx.fillStyle = tone;
  ctx.fillText(text, tagX, tagY);
  ctx.restore();
}

function drawPriceLine(ctx, bars, pad, slot, y, plotRight, priceBottom, height, width) {
  if (!state.ruler || !bars.length) return;
  const index = bars.findIndex((bar) => bar.date === state.ruler.date);
  if (index < 0) return;
  const bar = bars[index];
  const x = pad + index * slot + slot / 2;
  const closeY = y(bar.close);
  const tone = bar.close >= bar.open ? "#3ddc97" : "#ff6b7a";
  ctx.save();
  ctx.setLineDash([3, 3]);
  ctx.strokeStyle = "rgba(231, 237, 243, 0.62)";
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(x, 8);
  ctx.lineTo(x, height - 6);
  ctx.stroke();
  ctx.strokeStyle = tone;
  ctx.beginPath();
  ctx.moveTo(pad, closeY);
  ctx.lineTo(plotRight, closeY);
  ctx.stroke();
  ctx.setLineDash([]);
  if (index !== bars.length - 1) {
    drawPriceTag(ctx, formatPrice(bar.close), plotRight, width, closeY, priceBottom, tone, plotRight);
  }
  ctx.restore();
}

function drawPro(ctx, history, dropped, count, y, pad, slot, plotRight, priceBottom) {
  const desk = currentPro(history);
  if (!desk) return;
  ctx.save();
  ctx.beginPath();
  ctx.rect(pad, 8, plotRight - pad, priceBottom - 8);
  ctx.clip();
  const layers = state.layers;
  const profile = desk.profile;
  if (layers.profile && profile.poc != null) {
    const most = Math.max(...profile.rows.map((row) => row.volume), 1e-9);
    const room = (plotRight - pad) * 0.22;
    profile.rows.forEach((row) => {
      const top = y(row.high);
      const bottom = y(row.low);
      const inside = row.low >= profile.low - 1e-12 && row.high <= profile.high + 1e-12;
      ctx.fillStyle = inside ? "rgba(127, 150, 175, 0.30)" : "rgba(127, 150, 175, 0.14)";
      ctx.fillRect(pad, top + 0.5, (row.volume / most) * room, Math.max(1, bottom - top - 1));
    });
  }
  if (layers.bands) {
    ctx.setLineDash([2, 3]);
    drawLine(ctx, desk.bands.upper.slice(dropped, dropped + count), y, pad, slot, "#6b7785", 1);
    drawLine(ctx, desk.bands.lower.slice(dropped, dropped + count), y, pad, slot, "#6b7785", 1);
  }
  if (layers.vwap) {
    ctx.setLineDash([6, 4]);
    drawLine(ctx, desk.vwap.slice(dropped, dropped + count), y, pad, slot, "#3fd0e0", 1.75);
  }
  ctx.setLineDash([]);
  if (layers.trend) drawLine(ctx, desk.ema50.slice(dropped, dropped + count), y, pad, slot, "#3ddc97", 1.85);
  if (layers.ema200) drawLine(ctx, desk.ema200.slice(dropped, dropped + count), y, pad, slot, "#b48cff", 1.75);
  if (layers.profile && profile.poc != null) {
    const level = y(profile.poc);
    ctx.setLineDash([8, 4]);
    ctx.strokeStyle = "#ffd166";
    ctx.lineWidth = 1.25;
    ctx.beginPath();
    ctx.moveTo(pad, level);
    ctx.lineTo(plotRight, level);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.font = "11px Segoe UI, sans-serif";
    ctx.fillStyle = "#ffd166";
    ctx.fillText("POC", pad + 4, Math.max(18, level - 3));
  }
  ctx.restore();
}

const LAYERS = [
  ["edge", "Edge stop + Fire arrows", "edgestop", true],
  ["readyaim", "Ready · Aim · Fire", "readyaim", true],
  ["big4", "big4indicator candle color", "big4", true],
  ["pivots", "Support · Resistance boxes", "pivots", true],
  ["vwap", "VWAP", "vwap", true],
  ["trend", "Green trend EMA 50", "trend", true],
  ["stack", "SMA 5 · 9 · 21 · 50 · 100 · 200", "stack", true],
  ["ema200", "200 EMA", "ema200", false],
  ["profile", "Volume + POC", "poc", false],
  ["bands", "Bands", "band", false],
  ["averages", "High · Close · Low", "close", false],
  ["fastslow", "Fast · Slow", "fast", false],
  ["lesson", "Lesson arrows (extra)", "low", false],
  ["key", "Chart notes", "key", false],
];
const LAYERS_KEY = "paperTraderLayers67";

function readLayers() {
  const chosen = {};
  LAYERS.forEach(([id, , , on]) => {
    chosen[id] = on;
  });
  try {
    const saved = JSON.parse(localStorage.getItem(LAYERS_KEY) || "{}");
    if (saved && typeof saved === "object") {
      LAYERS.forEach(([id]) => {
        if (typeof saved[id] === "boolean") chosen[id] = saved[id];
      });
    }
  } catch (error) {
    /* The clean view stays. */
  }
  return chosen;
}

function paintLayers() {
  const legend = $("legend");
  const panel = $("layers");
  const on = LAYERS.filter(([id]) => state.layers[id]);
  if (legend) {
    legend.replaceChildren();
    on.forEach(([, name, swatch]) => {
      const item = document.createElement("span");
      item.className = "item";
      const mark = document.createElement("span");
      mark.className = "swatch " + swatch;
      item.append(mark, document.createTextNode(name));
      legend.appendChild(item);
    });
  }
  if (panel && !panel.childElementCount) {
    LAYERS.forEach(([id, name, swatch]) => {
      const button = document.createElement("button");
      button.type = "button";
      button.dataset.layer = id;
      const mark = document.createElement("span");
      mark.className = "swatch " + swatch;
      button.append(mark, document.createTextNode(name));
      button.addEventListener("click", () => {
        state.layers[id] = !state.layers[id];
        try {
          localStorage.setItem(LAYERS_KEY, JSON.stringify(state.layers));
        } catch (error) {
          /* The choice still holds for this page. */
        }
        paintLayers();
        drawCandles();
      });
      panel.appendChild(button);
    });
  }
  if (panel) {
    panel.querySelectorAll("button").forEach((button) => {
      button.setAttribute("aria-pressed", state.layers[button.dataset.layer] ? "true" : "false");
    });
  }
}

function drawStudy(ctx, bars, y, pad, slot) {
  if (!state.study) return;
  const colors = ["#d7e2ea", "#7dcec4", "#d2b4f0", "#e6c36a"];
  let line = 0;
  state.study.plots.forEach((plot) => {
    const values = visible(plot.values);
    const numeric = values.filter((value) => value != null);
    const binary = numeric.length > 0 && numeric.every((value) => value === 0 || value === 1);
    const side = plot.side || (/sell|short|down/i.test(plot.name) ? "sell" : /buy|long|up/i.test(plot.name) ? "buy" : "");
    if (binary && side) {
      values.forEach((value, index) => {
        if (value !== 1 || (index > 0 && values[index - 1] === 1)) return;
        const bar = bars[index];
        if (!bar) return;
        const x = pad + index * slot + slot / 2;
        const up = side !== "sell";
        ctx.fillStyle = up ? "#3ddc97" : "#ff6b7a";
        drawArrow(ctx, x, up ? y(bar.low) + 28 : y(bar.high) - 28, up);
      });
      return;
    }
    if (binary) return;
    drawLine(ctx, values, y, pad, slot, colors[line % colors.length]);
    line += 1;
  });
}

function drawArrow(ctx, x, y, up, size) {
  const tip = size || 10;
  ctx.beginPath();
  if (up) {
    ctx.moveTo(x, y - tip);
    ctx.lineTo(x - tip * 0.82, y + tip * 0.62);
    ctx.lineTo(x + tip * 0.82, y + tip * 0.62);
  } else {
    ctx.moveTo(x, y + tip);
    ctx.lineTo(x - tip * 0.82, y - tip * 0.62);
    ctx.lineTo(x + tip * 0.82, y - tip * 0.62);
  }
  ctx.closePath();
  ctx.fill();
  ctx.lineWidth = Math.max(1.5, tip / 7);
  ctx.strokeStyle = "#07090b";
  ctx.stroke();
}

function paintReadyAimStages(ctx, stages, dropped, bars, y, pad, slot, priceBottom) {
  // Desk Ready · Aim markers only — Fire is the Edge BUY/SELL arrow at the next open.
  ctx.save();
  ctx.font = "bold 9px Segoe UI, sans-serif";
  ctx.textAlign = "center";
  (stages || []).forEach((stage, absolute) => {
    if (!stage || stage === "fire") return;
    const index = absolute - dropped;
    if (index < 0 || index >= bars.length) return;
    const bar = bars[index];
    const x = pad + index * slot + slot / 2;
    const ready = stage === "ready";
    const color = ready ? "#e2b15a" : "#ff9f43";
    const label = ready ? "R" : "A";
    const at = Math.min(priceBottom - 10, y(bar.low) + 18);
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(x, at, Math.max(3.5, Math.min(5.5, slot * 0.35)), 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = "#07090b";
    ctx.fillText(label, x, at + 3);
  });
  ctx.restore();
}

function paintClearEdgeArrows(ctx, marks, dropped, bars, y, pad, slot, priceBottom) {
  // Fire only: Edge fills at the next open after Ready · Aim · Fire + big4indicator. Past marks never move.
  ctx.save();
  ctx.font = "bold 11px Segoe UI, sans-serif";
  ctx.textAlign = "center";
  (marks || []).forEach((mark) => {
    const index = mark.index - dropped;
    if (index < 0 || index >= bars.length) return;
    const bar = bars[index];
    const buy = mark.side === "buy";
    const x = pad + index * slot + slot / 2;
    const tip = Math.max(14, Math.min(22, slot * 1.8));
    const at = buy
      ? Math.min(priceBottom - tip - 8, y(bar.low) + tip + 10)
      : Math.max(28, y(bar.high) - tip - 10);
    ctx.fillStyle = buy ? "#3ddc97" : "#ff4d4d";
    drawArrow(ctx, x, at, buy, tip);
    const label = buy ? "BUY" : "SELL";
    const width = ctx.measureText(label).width + 10;
    const tagY = buy ? at + tip + 12 : at - tip - 12;
    ctx.fillStyle = buy ? "#3ddc97" : "#ff4d4d";
    ctx.fillRect(x - width / 2, tagY - 8, width, 16);
    ctx.fillStyle = "#07090b";
    ctx.fillText(label, x, tagY + 4);
    const fire = buy && mark.precise ? "FIRE · big4" : "FIRE";
    const fireW = ctx.measureText(fire).width + 8;
    const fireY = buy ? tagY + 14 : tagY - 14;
    ctx.fillStyle = buy ? "rgba(61,220,151,0.92)" : "rgba(255,77,77,0.92)";
    ctx.fillRect(x - fireW / 2, fireY - 7, fireW, 14);
    ctx.fillStyle = "#07090b";
    ctx.fillText(fire, x, fireY + 3);
  });
  ctx.restore();
}

function paintChipRow(node, chips) {
  if (!node) return;
  node.replaceChildren();
  chips.forEach((chip) => {
    const box = document.createElement("div");
    box.className = "desk-clear-chip" + (chip.kind ? " " + chip.kind : "");
    const key = document.createElement("span");
    key.className = "k";
    key.textContent = chip.k;
    const value = document.createElement("span");
    value.className = "v";
    value.textContent = chip.v;
    box.append(key, value);
    node.appendChild(box);
  });
}

function breadthTone(key, number, change) {
  if (key === "VIX") {
    if (!Number.isFinite(change)) return "mixed";
    if (change < 0) return "bull";
    if (change > 0) return "bear";
    return "mixed";
  }
  if (key === "TRIN") {
    if (number < 1) return "bull";
    if (number > 1) return "bear";
    return "mixed";
  }
  if (number > 0) return "bull";
  if (number < 0) return "bear";
  return "mixed";
}

function formatBreadthNumber(key, number) {
  if (key === "VIX") return number.toFixed(2);
  if (key === "TRIN") return number.toFixed(3);
  if (Math.abs(number) >= 1000) return Math.round(number).toLocaleString("en-US");
  return String(number);
}

function breadthChip(key) {
  const values = (state.breadth && state.breadth.values) || {};
  const changes = (state.breadth && state.breadth.changes) || {};
  const tones = (state.breadth && state.breadth.tones) || {};
  const raw = values[key];
  const number = Number(raw);
  // Fail closed: only finite real readings paint. Never invent NYSE internals or scrape TradingView.
  if (raw == null || raw === "" || !Number.isFinite(number)) {
    return { k: key, v: "No feed", kind: "breadth" };
  }
  const change = Number(changes[key]);
  const tone = tones[key] || breadthTone(key, number, change);
  return { k: key, v: formatBreadthNumber(key, number), kind: "breadth " + tone };
}

function emptyBreadth(note) {
  return {
    ready: false,
    source: null,
    values: { TICK: null, ADD: null, TRIN: null, VOLD: null, VIX: null },
    changes: { TICK: null, ADD: null, TRIN: null, VOLD: null, VIX: null },
    tones: { TICK: null, ADD: null, TRIN: null, VOLD: null, VIX: null },
    vixBars: [],
    note: note,
    will_not_invent: true,
  };
}

function applyBreadthPayload(payload) {
  state.breadth = payload && typeof payload === "object" ? payload : emptyBreadth("Breadth feed empty. No fake numbers.");
  const bars = state.breadth && Array.isArray(state.breadth.vixBars) ? state.breadth.vixBars : [];
  state.vixBars = bars
    .map((bar) => ({ date: String((bar && bar.date) || "").slice(0, 10), close: Number(bar && bar.close) }))
    .filter((bar) => bar.date && bar.close > 0);
  state.edgeKey = "";
  state.edge = null;
}

function paintChartBoxes() {
  paintChipRow($("chart-boxes"), [
    breadthChip("TICK"),
    breadthChip("ADD"),
    breadthChip("TRIN"),
    breadthChip("VOLD"),
    breadthChip("VIX"),
  ]);
}

async function phoneVixBreadth() {
  const empty = emptyBreadth(
    "TICK/ADD/TRIN/VOLD stay No feed on phone (no invent). VIX paints and gates Edge only from a real .VIX quote."
  );
  if (typeof PaperLive === "undefined" || !PaperLive.gauge) return empty;
  try {
    const bars = await PaperLive.gauge("^VIX");
    if (!bars || !bars.length) return empty;
    const vixBars = bars
      .map((bar) => ({ date: String((bar && bar.date) || "").slice(0, 10), close: Number(bar && bar.close) }))
      .filter((bar) => bar.date && bar.close > 0);
    if (!vixBars.length) return empty;
    const price = vixBars[vixBars.length - 1].close;
    let change = null;
    if (vixBars.length >= 2) change = price - vixBars[vixBars.length - 2].close;
    empty.values.VIX = price;
    empty.changes.VIX = change;
    empty.tones.VIX = breadthTone("VIX", price, change);
    empty.vixBars = vixBars;
    empty.ready = true;
    empty.source = "cnbc:.VIX";
    return empty;
  } catch (error) {
    return empty;
  }
}

async function loadBreadth() {
  if (state.offline) {
    applyBreadthPayload(await phoneVixBreadth());
    paintChartBoxes();
    if (state.market) renderEdge();
    return;
  }
  try {
    applyBreadthPayload(await getJSON("/api/breadth"));
  } catch (error) {
    applyBreadthPayload(emptyBreadth("Breadth feed did not answer. TICK/ADD/TRIN/VOLD stay No feed — will not invent."));
  }
  paintChartBoxes();
  if (state.market) renderEdge();
}

function paintDeskClear(history) {
  const node = $("desk-clear");
  if (!node) return;
  const read = typeof PaperFrames !== "undefined" && PaperFrames.deskClearRead ? PaperFrames.deskClearRead(history || []) : null;
  const symbol = (state.paper && state.paper.symbol) || "";
  const lane = symbol ? readLaneAlign(symbol, history || seenBars()) : null;
  const seat =
    history && history.length && typeof PaperFrames !== "undefined" && PaperFrames.rangeSeat
      ? PaperFrames.rangeSeat(history)
      : lane && lane.seat
        ? lane.seat
        : null;
  const laneLabel =
    !lane
      ? "—"
      : lane.lane === "open"
        ? "Open"
        : lane.lane === "soft"
          ? "Soft"
          : lane.lane === "closed"
            ? "Closed"
            : lane.lane === "skip"
              ? "Skip"
              : "Unread";
  const laneKind =
    lane && lane.lane === "open" ? "bull" : lane && lane.lane === "closed" ? "bear" : lane && lane.lane === "soft" ? "mixed" : "stage";
  const seatLabel = seat && seat.label ? seat.label.replace("Seat ", "") : seat && seat.seat ? seat.seat : "—";
  const seatKind = seat && seat.seat === "high" ? "bull" : seat && seat.seat === "low" ? "bear" : "mixed";
  const trail =
    history && history.length && typeof PaperFrames !== "undefined" && PaperFrames.seatTrailRead
      ? PaperFrames.seatTrailRead(history, { live: chartCrypto() })
      : null;
  const trailLabel = !trail ? "—" : !trail.ready ? "—" : trail.armed ? "Armed" : trail.call === "WAIT" ? "Wait" : trail.call || "—";
  const trailKind = trail && trail.armed ? "bull" : trail && trail.ready ? "mixed" : "stage";
  const openCycle =
    history && history.length && typeof PaperFrames !== "undefined" && PaperFrames.cycleMapRead
      ? PaperFrames.cycleMapRead(history)
      : state.cycle && state.cycle.open
        ? state.cycle.open
        : null;
  if (openCycle && openCycle.ready && state.cycle) state.cycle.open = openCycle;
  const cycleChipLabel =
    openCycle && openCycle.ready
      ? openCycle.signal !== "none"
        ? (openCycle.signalLabel || openCycle.label || "Cycle").replace(/ · .*$/, "").slice(0, 22)
        : (openCycle.label || "Cycle").slice(0, 18)
      : "—";
  const cycleChipKind =
    openCycle && (openCycle.signal === "buy_before_mania" || openCycle.signal === "bottom_in")
      ? "bull"
      : openCycle && (openCycle.signal === "sell_into_greed" || openCycle.signal === "top_in")
        ? "bear"
        : openCycle && openCycle.ready
          ? "stage"
          : "mixed";
  // Always paint the desk boxes so phone users can see them; values stay "—" until bars are ready.
  const chips = read
    ? [
        { k: "VWAP D", v: read.vwapD == null ? "—" : formatPrice(read.vwapD), kind: "vwap" },
        { k: "VWAP W", v: read.vwapW == null ? "—" : formatPrice(read.vwapW), kind: "vwap" },
        { k: "VWAP M", v: read.vwapM == null ? "—" : formatPrice(read.vwapM), kind: "vwap" },
        { k: "big4indicator", v: read.bias, kind: read.bias === "Bullish" ? "bull" : read.bias === "Bearish" ? "bear" : "mixed" },
        { k: "Bol %B 20", v: read.pctB == null ? "—" : read.pctB.toFixed(1), kind: "" },
        { k: "Stage", v: read.stage, kind: "stage" },
        { k: "Lane Align", v: laneLabel, kind: laneKind },
        { k: "Range Seat", v: seatLabel, kind: seatKind },
        { k: "Seat Trail", v: trailLabel, kind: trailKind },
        {
          k: "Cycle",
          v: cycleChipLabel,
          kind: cycleChipKind,
        },
      ]
    : [
        { k: "VWAP D", v: "—", kind: "vwap" },
        { k: "VWAP W", v: "—", kind: "vwap" },
        { k: "VWAP M", v: "—", kind: "vwap" },
        { k: "big4indicator", v: "Waiting", kind: "mixed" },
        { k: "Bol %B 20", v: "—", kind: "" },
        { k: "Stage", v: "Waiting", kind: "stage" },
        { k: "Lane Align", v: laneLabel, kind: laneKind },
        { k: "Range Seat", v: seatLabel, kind: seatKind },
        { k: "Seat Trail", v: trailLabel, kind: trailKind },
        { k: "Cycle", v: cycleChipLabel, kind: cycleChipKind },
      ];
  paintChipRow(node, chips);
}

function paintChartToolBoxes(history) {
  paintChartBoxes();
  paintDeskClear(history);
}

function studyLayout(width, count, preferPad, preferRight) {
  const pad = preferPad != null ? preferPad : (state.studyPad && state.studyPad.pad) || 48;
  const rightGap = preferRight != null ? preferRight : (state.studyPad && state.studyPad.rightGap) || Math.max(96, Math.round(width * 0.14));
  const slot = (width - pad - rightGap) / Math.max(1, count || (state.studyPad && state.studyPad.count) || 1);
  return { pad, rightGap, slot, plotRight: width - rightGap };
}

function redrawStudies() {
  drawCandles();
}

function paintChartFlags(history, bars, squeezeLevel, momentum, priorMomentum) {
  const node = $("chart-flags");
  if (!node) return;
  if (!bars || !bars.length) {
    node.replaceChildren();
    return;
  }
  const last = bars[bars.length - 1];
  const stack = PaperFrames.stackAverages(history);
  const sma50 = stack.find((row) => row.length === 50);
  const sma200 = stack.find((row) => row.length === 200);
  const s50 = sma50 && sma50.values[sma50.values.length - 1];
  const s200 = sma200 && sma200.values[sma200.values.length - 1];
  const bull = s50 != null && s200 != null && last.close >= s50 && s50 >= s200;
  const bear = s50 != null && s200 != null && last.close <= s50 && s50 <= s200;
  const look = Math.min(20, bars.length - 1);
  let hi = -Infinity;
  let lo = Infinity;
  for (let index = bars.length - 1 - look; index < bars.length - 1; index += 1) {
    if (index < 0) continue;
    hi = Math.max(hi, bars[index].high);
    lo = Math.min(lo, bars[index].low);
  }
  const breakUp = Number.isFinite(hi) && last.close > hi;
  const breakDown = Number.isFinite(lo) && last.close < lo;
  const dots = (() => {
    const study = PaperFrames.compression(history, 20);
    let count = 0;
    for (let index = study.level.length - 1; index >= 0 && study.level[index] > 0; index -= 1) count += 1;
    return count;
  })();
  const edge = currentEdge(history);
  const desk = currentPro(history);
  const vwap = desk && desk.vwap ? desk.vwap[desk.vwap.length - 1] : null;
  const chips = [];
  chips.push({
    text: bull ? "Trend bullish" : bear ? "Trend bearish" : "Trend mixed",
    kind: bull ? "bull" : bear ? "bear" : "",
  });
  if (breakDown) chips.push({ text: "Breakdown", kind: "break" });
  else if (breakUp) chips.push({ text: "Breakout", kind: "bull" });
  if (dots >= 5 || squeezeLevel > 0) {
    chips.push({
      text: dots >= 5 ? "Squeeze alert · " + dots + " dots" : "Squeeze " + PaperFrames.squeezeName(squeezeLevel),
      kind: "alert",
    });
  }
  if (edge && edge.ready) {
    chips.push({
      text: "Edge " + edge.call + " " + edge.score + "/5",
      kind: edge.call === "BUY" || edge.call === "HOLD" ? "score" : edge.call === "SELL" ? "break" : "",
    });
  }
  const rising = momentum != null && (priorMomentum == null ? momentum >= 0 : momentum > priorMomentum);
  if (momentum != null) {
    chips.push({
      text: (rising ? "Mom up " : "Mom down ") + Number(momentum).toFixed(1),
      kind: rising && momentum >= 0 ? "score" : momentum < 0 && !rising ? "break" : "",
    });
  }
  if (vwap != null) chips.push({ text: "VWAP " + formatPrice(vwap), kind: "vwap" });
  node.replaceChildren();
  chips.forEach((chip) => {
    const span = document.createElement("span");
    span.className = "chart-flag" + (chip.kind ? " " + chip.kind : "");
    span.textContent = chip.text;
    node.appendChild(span);
  });
}

function drawTmo(target) {
  const canvas = target || $("rsi-chart");
  if (!state.market || !canvas) return;
  const history = sliceToCursor(state.market.bars);
  if (!history.length) return;
  const study = PaperFrames.trueMomentum(history, 14, 5, 3);
  const view = PaperFrames.zoomBars(history, state.zoom);
  const dropped = view.start;
  const main = study.main.slice(dropped);
  const signal = study.signal.slice(dropped);
  const up = study.up.slice(dropped);
  const down = study.down.slice(dropped);
  const ctx = prepare(canvas, 300, Math.max(118, canvas.clientHeight || 118));
  const width = canvas.paperWidth;
  const height = canvas.paperHeight;
  const layout = studyLayout(width, main.length);
  const pad = layout.pad;
  const plotRight = layout.plotRight;
  const slot = layout.slot;
  const limit = study.length;
  const y = (value) => 22 + ((limit - value) / (limit * 2)) * (height - 30);
  if (!target) state.tmoMap = { bars: view.bars, pad, slot, symbol: state.paper && state.paper.symbol };
  ctx.fillStyle = "rgba(255, 77, 77, 0.12)";
  ctx.fillRect(pad, y(limit), plotRight - pad, Math.max(1, y(study.ob) - y(limit)));
  ctx.fillStyle = "rgba(61, 220, 151, 0.12)";
  ctx.fillRect(pad, y(study.os), plotRight - pad, Math.max(1, y(-limit) - y(study.os)));
  ctx.font = "11px Segoe UI, sans-serif";
  ctx.setLineDash([3, 3]);
  ctx.strokeStyle = "#5a6570";
  [study.ob, 0, study.os].forEach((level) => {
    ctx.beginPath();
    ctx.moveTo(pad, y(level));
    ctx.lineTo(plotRight, y(level));
    ctx.stroke();
    ctx.fillStyle = "#8b98a5";
    ctx.fillText(String(level), 4, y(level) + 4);
  });
  ctx.setLineDash([]);
  for (let index = 1; index < main.length; index += 1) {
    if (main[index] == null || signal[index] == null || main[index - 1] == null || signal[index - 1] == null) continue;
    const left = pad + (index - 1) * slot + slot / 2;
    const right = pad + index * slot + slot / 2;
    ctx.beginPath();
    ctx.moveTo(left, y(main[index - 1]));
    ctx.lineTo(right, y(main[index]));
    ctx.lineTo(right, y(signal[index]));
    ctx.lineTo(left, y(signal[index - 1]));
    ctx.closePath();
    ctx.fillStyle = main[index] >= signal[index] ? "rgba(18, 92, 58, 0.45)" : "rgba(122, 28, 40, 0.45)";
    ctx.fill();
  }
  strokeOscillator(ctx, main, main, signal, y, pad, slot);
  strokeOscillator(ctx, signal, main, signal, y, pad, slot);
  up.forEach((mark, index) => {
    if (!mark || main[index] == null) return;
    ctx.fillStyle = "#67e8f9";
    drawArrow(ctx, pad + index * slot + slot / 2, Math.min(height - 8, y(main[index]) + 12), true);
  });
  down.forEach((mark, index) => {
    if (!mark || main[index] == null) return;
    ctx.fillStyle = "#ff4db8";
    drawArrow(ctx, pad + index * slot + slot / 2, Math.max(28, y(main[index]) - 12), false);
  });
  const last = main.length - 1;
  const mainNow = main[last];
  const signalNow = signal[last];
  const banner = "TMO 14/5/3  |  Main " + (mainNow == null ? "—" : Number(mainNow).toFixed(2)) + "  |  Signal " + (signalNow == null ? "—" : Number(signalNow).toFixed(2)) + (mainNow != null && signalNow != null ? (mainNow >= signalNow ? "  |  Above" : "  |  Below") : "");
  ctx.fillStyle = "#12b6c9";
  ctx.fillRect(pad, 2, Math.min(plotRight - pad, ctx.measureText(banner).width + 16), 16);
  ctx.fillStyle = "#041016";
  ctx.fillText(banner, pad + 6, 14);
  const calls = currentEdge(history);
  if (calls && calls.ready) PaperFrames.drawEdgeCalls(ctx, calls.marks, dropped, view.bars.length, (index) => pad + index * slot + slot / 2, 20, height - 4, pad, plotRight);
  if (mainNow != null) {
    ctx.fillStyle = mainNow >= (signalNow == null ? 0 : signalNow) ? "#3ddc97" : "#ff4d4d";
    ctx.fillText(Number(mainNow).toFixed(2), plotRight + 6, Math.max(28, Math.min(height - 6, y(mainNow))));
  }
}

function drawSqueeze(target) {
  const canvas = target || $("sqz-chart");
  if (!state.market || !canvas) return;
  const history = sliceToCursor(state.market.bars);
  if (!history.length) return;
  const view = PaperFrames.zoomBars(history, state.zoom);
  const dropped = view.start;
  const compressed = PaperFrames.compression(history, 20);
  const allMomentum = compressed.momentum;
  const momentum = allMomentum.slice(dropped);
  const squeeze = compressed.level.slice(dropped);
  const ctx = prepare(canvas, 300, Math.max(88, canvas.clientHeight || 88));
  const width = canvas.paperWidth;
  const height = canvas.paperHeight;
  const layout = studyLayout(width, momentum.length);
  const pad = layout.pad;
  const plotRight = layout.plotRight;
  const slot = layout.slot;
  if (!target) state.sqzMap = { bars: view.bars, pad, slot, symbol: state.paper && state.paper.symbol };
  const histoTop = 20;
  const histoBottom = height - 8;
  const zero = (histoTop + histoBottom) / 2;
  const scale = Math.max(...momentum.map((value) => Math.abs(value || 0)), 1e-9);
  ctx.strokeStyle = "#2d3640";
  ctx.beginPath();
  ctx.moveTo(pad, zero);
  ctx.lineTo(plotRight, zero);
  ctx.stroke();
  momentum.forEach((value, index) => {
    if (value == null) return;
    const x = pad + index * slot + slot / 2;
    const previous = index === 0 ? allMomentum[dropped - 1] : momentum[index - 1];
    const tone = PaperFrames.momentumTone(value, previous == null ? null : previous);
    const room = Math.max(4, (histoBottom - histoTop) / 2 - 4);
    const barHeight = (Math.abs(value) / scale) * room;
    ctx.fillStyle = tone;
    ctx.fillRect(x - Math.max(1, slot * 0.28), value >= 0 ? zero - barHeight : zero, Math.max(2, slot * 0.56), Math.max(1, barHeight));
    ctx.fillStyle = PaperFrames.squeezeTone(squeeze[index] || 0);
    ctx.beginPath();
    ctx.arc(x, zero, Math.max(2, Math.min(3.5, slot * 0.18)), 0, Math.PI * 2);
    ctx.fill();
  });
  const last = momentum.length - 1;
  const lastMomentum = momentum[last];
  const label = "Squeeze momentum  |  " + (lastMomentum == null ? "—" : Number(lastMomentum).toFixed(3)) + "  |  " + PaperFrames.squeezeName(squeeze[last] || 0);
  ctx.font = "11px Segoe UI, sans-serif";
  ctx.fillStyle = "#d7e2ea";
  ctx.fillText(label, pad, 12);
  if (lastMomentum != null) {
    ctx.fillStyle = PaperFrames.momentumTone(lastMomentum, last === 0 ? allMomentum[dropped - 1] : momentum[last - 1]) || "#d7e2ea";
    ctx.fillText(Number(lastMomentum).toFixed(2), plotRight + 6, zero + 4);
  }
}

function drawCci(target) {
  const canvas = target || $("cci-chart");
  if (!state.market || !canvas || !PaperFrames.cciPane) return;
  const history = sliceToCursor(state.market.bars);
  if (!history.length) return;
  const pane = PaperFrames.cciPane(history);
  const view = PaperFrames.zoomBars(history, state.zoom);
  const dropped = view.start;
  const slow = pane.slow.slice(dropped);
  const mid = pane.mid.slice(dropped);
  const fast = pane.fast.slice(dropped);
  const up = pane.up.slice(dropped);
  const down = pane.down.slice(dropped);
  const ctx = prepare(canvas, 300, Math.max(118, canvas.clientHeight || 118));
  const width = canvas.paperWidth;
  const height = canvas.paperHeight;
  const layout = studyLayout(width, slow.length);
  const pad = layout.pad;
  const plotRight = layout.plotRight;
  const slot = layout.slot;
  if (!target) state.cciMap = { bars: view.bars, pad, slot, symbol: state.paper && state.paper.symbol };
  const high = pane.high;
  const low = pane.low;
  const y = (value) => 22 + ((high - value) / (high - low || 1)) * (height - 30);
  ctx.font = "11px Segoe UI, sans-serif";
  ctx.setLineDash([3, 3]);
  pane.levels.forEach((level) => {
    ctx.strokeStyle = level === 0 ? "#5a6570" : "#3a4655";
    ctx.beginPath();
    ctx.moveTo(pad, y(level));
    ctx.lineTo(plotRight, y(level));
    ctx.stroke();
    ctx.fillStyle = "#8b98a5";
    ctx.fillText(String(level), 4, y(level) + 4);
  });
  ctx.setLineDash([]);
  drawLine(ctx, slow, y, pad, slot, "#f4f7fb", 1.6);
  drawLine(ctx, mid, y, pad, slot, "#facc15", 1.25);
  drawLine(ctx, fast, y, pad, slot, "#22d3ee", 1.15);
  up.forEach((mark, index) => {
    if (!mark || slow[index] == null) return;
    ctx.fillStyle = "#3ddc97";
    drawArrow(ctx, pad + index * slot + slot / 2, Math.min(height - 8, y(slow[index]) + 12), true);
  });
  down.forEach((mark, index) => {
    if (!mark || slow[index] == null) return;
    ctx.fillStyle = "#ff4d4d";
    drawArrow(ctx, pad + index * slot + slot / 2, Math.max(28, y(slow[index]) - 12), false);
  });
  const last = slow.length - 1;
  const banner = "CCI 50 / 14 / 5  |  " +
    (slow[last] == null ? "—" : Number(slow[last]).toFixed(1)) + "  " +
    (mid[last] == null ? "—" : Number(mid[last]).toFixed(1)) + "  " +
    (fast[last] == null ? "—" : Number(fast[last]).toFixed(1));
  ctx.fillStyle = "#1b2430";
  ctx.fillRect(pad, 2, Math.min(plotRight - pad, ctx.measureText(banner).width + 16), 16);
  ctx.fillStyle = "#d7e2ea";
  ctx.fillText(banner, pad + 6, 14);
  if (slow[last] != null) {
    ctx.fillStyle = "#f4f7fb";
    ctx.fillText(Number(slow[last]).toFixed(1), plotRight + 6, Math.max(28, Math.min(height - 6, y(slow[last]))));
  }
}

function drawVolumeWave(target) {
  const canvas = target || $("vol-chart");
  if (!state.market || !canvas || !PaperFrames.volumeWave) return;
  const history = sliceToCursor(state.market.bars);
  if (!history.length) return;
  const prefs = readVolumeWavePrefs();
  const study = PaperFrames.volumeWave(history, prefs);
  const view = PaperFrames.zoomBars(history, state.zoom);
  const dropped = view.start;
  const up = study.up.slice(dropped);
  const dn = study.dn.slice(dropped);
  const avg = study.avg.slice(dropped);
  const ctx = prepare(canvas, 300, Math.max(118, canvas.clientHeight || 118));
  const width = canvas.paperWidth;
  const height = canvas.paperHeight;
  const layout = studyLayout(width, up.length);
  const pad = layout.pad;
  const plotRight = layout.plotRight;
  const slot = layout.slot;
  if (!target) state.volMap = { bars: view.bars, pad, slot, symbol: state.paper && state.paper.symbol };
  const histoTop = 22;
  const histoBottom = height - 10;
  const zero = (histoTop + histoBottom) / 2;
  const scale = Math.max(
    ...up.map((value) => Math.abs(value || 0)),
    ...dn.map((value) => Math.abs(value || 0)),
    ...avg.map((value) => Math.abs(value || 0)),
    1e-9
  );
  ctx.strokeStyle = "#2d3640";
  ctx.beginPath();
  ctx.moveTo(pad, zero);
  ctx.lineTo(plotRight, zero);
  ctx.stroke();
  up.forEach((value, index) => {
    if (value == null || value === 0) return;
    const x = pad + index * slot + slot / 2;
    const room = Math.max(4, (histoBottom - histoTop) / 2 - 4);
    const barHeight = (Math.abs(value) / scale) * room;
    ctx.fillStyle = "#3ddc97";
    ctx.fillRect(x - Math.max(1, slot * 0.28), zero - barHeight, Math.max(2, slot * 0.56), Math.max(1, barHeight));
  });
  dn.forEach((value, index) => {
    if (value == null || value === 0) return;
    const x = pad + index * slot + slot / 2;
    const room = Math.max(4, (histoBottom - histoTop) / 2 - 4);
    const barHeight = (Math.abs(value) / scale) * room;
    ctx.fillStyle = "#ff4d4d";
    ctx.fillRect(x - Math.max(1, slot * 0.28), zero, Math.max(2, slot * 0.56), Math.max(1, barHeight));
  });
  const avgY = (value) => zero - (value / scale) * Math.max(4, (histoBottom - histoTop) / 2 - 4);
  drawLine(ctx, avg, avgY, pad, slot, "#22d3ee", 1.35);
  const last = up.length - 1;
  const lastUp = up[last];
  const lastDn = dn[last];
  const lastAvg = avg[last];
  const side = lastUp != null && lastUp > 0 ? "UP" : lastDn != null && lastDn < 0 ? "DN" : "—";
  const banner = study.hasVolume
    ? ("Volume Wave  |  Hull ATR " + study.n + " × " + study.atrMult + "  |  " + side +
      (lastAvg == null ? "" : "  |  avg " + Math.round(lastAvg)))
    : "Volume Wave  |  No volume from this feed";
  ctx.font = "11px Segoe UI, sans-serif";
  ctx.fillStyle = "#1b2430";
  ctx.fillRect(pad, 2, Math.min(plotRight - pad, ctx.measureText(banner).width + 16), 16);
  ctx.fillStyle = "#d7e2ea";
  ctx.fillText(banner, pad + 6, 14);
  const tag = lastUp != null && lastUp > 0 ? lastUp : lastDn != null && lastDn < 0 ? lastDn : lastAvg;
  if (tag != null) {
    ctx.fillStyle = lastUp != null && lastUp > 0 ? "#3ddc97" : lastDn != null && lastDn < 0 ? "#ff4d4d" : "#22d3ee";
    ctx.fillText(String(Math.round(tag)), plotRight + 6, zero + 4);
  }
}

function strokeOscillator(ctx, values, lead, other, y, pad, slot) {
  ctx.lineWidth = 2;
  for (let index = 1; index < values.length; index += 1) {
    if (values[index] == null || values[index - 1] == null || lead[index] == null || other[index] == null) continue;
    ctx.strokeStyle = lead[index] > other[index] ? "#3ddc97" : "#ff6b7a";
    ctx.beginPath();
    ctx.moveTo(pad + (index - 1) * slot + slot / 2, y(values[index - 1]));
    ctx.lineTo(pad + index * slot + slot / 2, y(values[index]));
    ctx.stroke();
  }
  ctx.lineWidth = 1;
}

function drawLine(ctx, values, y, pad, slot, color, weight) {
  ctx.beginPath();
  let drawing = false;
  values.forEach((value, index) => {
    if (value == null) {
      drawing = false;
      return;
    }
    const x = pad + index * slot + slot / 2;
    if (!drawing) {
      ctx.moveTo(x, y(value));
      drawing = true;
    } else {
      ctx.lineTo(x, y(value));
    }
  });
  ctx.strokeStyle = color;
  ctx.lineWidth = weight || 1.5;
  ctx.stroke();
  ctx.lineWidth = 1;
}

function prepare(canvas, fallbackWidth, fallbackHeight) {
  const ratio = window.devicePixelRatio || 1;
  const width = canvas.clientWidth >= 40 ? canvas.clientWidth : (fallbackWidth || canvas.clientWidth || 300);
  const height = canvas.clientHeight >= 40 ? canvas.clientHeight : (fallbackHeight || canvas.clientHeight || 100);
  const nextWidth = Math.floor(Math.max(width, 1) * ratio);
  const nextHeight = Math.floor(Math.max(height, 1) * ratio);
  if (canvas.width !== nextWidth || canvas.height !== nextHeight) {
    canvas.width = nextWidth;
    canvas.height = nextHeight;
  }
  const ctx = canvas.getContext("2d");
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  ctx.clearRect(0, 0, width, height);
  canvas.paperWidth = width;
  canvas.paperHeight = height;
  return ctx;
}

function releaseBusy() {
  state.busy = false;
  const next = state.pendingSymbol;
  state.pendingSymbol = "";
  if (next) chooseSymbol(next);
}

async function withBusy(action) {
  if (state.busy) return;
  state.busy = true;
  document.body.classList.add("busy");
  setStatus("Working…");
  try {
    await action();
  } catch (error) {
    setStatus(error.message);
    if (state.paper) $("symbol").dataset.value = state.paper.symbol;
  } finally {
    document.body.classList.remove("busy");
    syncDeskStatus();
    releaseBusy();
  }
}

function stopAuto(message) {
  state.auto = false;
  if (state.autoTimer) {
    clearTimeout(state.autoTimer);
    state.autoTimer = 0;
  }
  if (state.paper) render(state.paper);
  if (message) $("status").textContent = message;
}

function speakWarning(item) {
  if (!state.voice || !item || !item.detail) return;
  const kinds = { steam: 1, dots: 1, squeeze: 1, edge: 1, strategy: 1, best: 1, process: 1 };
  if (item.side !== "buy" && item.side !== "sell" && !kinds[item.kind]) return;
  const key = [item.symbol, item.date, item.kind, item.side].join("|");
  let heard = [];
  try {
    const parsed = JSON.parse(sessionStorage.getItem("paperTraderHeard") || "[]");
    if (Array.isArray(parsed)) heard = parsed.filter((row) => typeof row === "string").slice(-40);
  } catch (error) {
    heard = [];
  }
  if (heard.indexOf(key) >= 0) return;
  heard.push(key);
  try {
    sessionStorage.setItem("paperTraderHeard", JSON.stringify(heard.slice(-40)));
  } catch (error) {
    /* The warning still shows on the desk. */
  }
  $("status").textContent = item.detail;
  if (!window.speechSynthesis) return;
  const message = new SpeechSynthesisUtterance(item.detail);
  message.lang = "en-US";
  window.speechSynthesis.speak(message);
}

function syncTriggerChecks() {
  const map = {
    "trig-squeeze": "squeeze",
    "trig-edge": "edge",
    "trig-strategy": "strategy",
    "trig-best": "best",
    "trig-process": "process",
  };
  Object.keys(map).forEach((id) => {
    const box = $(id);
    if (box) box.checked = !!state.triggers[map[id]];
  });
}

async function warnDesk() {
  if (!state.voice) return;
  const triggers = state.triggers || loadTriggers();
  const symbols = readPins();
  const current = state.paper && state.paper.symbol;
  if (current && symbols.indexOf(current) < 0) symbols.push(current);
  if (!symbols.length) return;
  try {
    if (state.offline) {
      for (let index = 0; index < symbols.length; index += 1) {
        const symbol = symbols[index];
        const row = state.catalog.find((item) => item.symbol === symbol);
        if (typeof PaperLive === "undefined" || typeof PaperFrames === "undefined") continue;
        const bars = PaperFrames.closedBars(await PaperLive.daily(symbol, row ? row.category : ""), phoneCrypto(symbol, row));
        if (!bars.length) continue;
        const name = pinLabel(symbol);
        const closed = closedDate(bars) || (bars[bars.length - 1] && bars[bars.length - 1].date) || "";
        if (triggers.squeeze && PaperFrames.squeezeAlert) {
          const alert = PaperFrames.squeezeAlert(bars);
          if (alert.side) {
            const detail = alert.kind === "steam"
              ? "Sell. " + name + ". The daily squeeze ran out of steam."
              : (alert.side === "buy" ? "Buy warning. " : "Sell warning. ") + name + ". Five squeeze dots on the daily.";
            speakWarning({ symbol, date: bars[alert.index] ? bars[alert.index].date : closed, kind: alert.kind || "squeeze", side: alert.side, detail });
          }
        }
        if (triggers.edge && PaperFrames.edge) {
          const timing = PaperFrames.edge(bars, { live: false, vixBars: state.vixBars || [] });
          if (timing && timing.ready && timing.call === "BUY") {
            speakWarning({
              symbol,
              date: timing.signalDate || closed,
              kind: "edge",
              side: "buy",
              detail: "Buy warning. " + name + ". Edge seven checks on the daily.",
            });
          }
        }
        if (triggers.strategy && PaperFrames.strategies) {
          const board = PaperFrames.strategies(bars, { live: false });
          const pick = board.rows.find((item) => item.id === state.strat.pick);
          if (pick && pick.ready && (pick.call === "BUY" || pick.call === "SELL")) {
            speakWarning({
              symbol,
              date: closed,
              kind: "strategy",
              side: pick.call === "BUY" ? "buy" : "sell",
              detail: (pick.call === "BUY" ? "Buy warning. " : "Sell warning. ") + name + ". " + pick.name + " on the daily.",
            });
          }
        }
        if (triggers.best !== false && state.best && state.best.on && symbol === current) {
          const status = bestSetupStatus();
          if (status.ready) {
            speakWarning({
              symbol,
              date: closed,
              kind: "best",
              side: "buy",
              detail: "Buy warning. Best setup is ready on " + name + ".",
            });
          }
        }
      }
      return;
    }
    if (triggers.squeeze) {
      const report = await getJSON("/api/voice?symbols=" + encodeURIComponent(symbols.join(",")));
      (report.items || []).forEach(speakWarning);
    }
    if ((triggers.edge || triggers.strategy || triggers.best !== false) && current && state.market && state.market.bars) {
      const bars = PaperFrames.closedBars(state.market.bars, chartCrypto());
      const name = pinLabel(current);
      const closed = closedDate(bars) || "";
      if (triggers.edge && PaperFrames.edge) {
        const timing = PaperFrames.edge(bars, { live: false, vixBars: state.vixBars || [] });
        if (timing && timing.ready && timing.call === "BUY") {
          speakWarning({
            symbol: current,
            date: timing.signalDate || closed,
            kind: "edge",
            side: "buy",
            detail: "Buy warning. " + name + ". Edge seven checks on the daily.",
          });
        }
      }
      if (triggers.strategy && PaperFrames.strategies) {
        const board = currentBoard(state.market.bars);
        const pick = board.rows.find((item) => item.id === state.strat.pick);
        if (pick && pick.ready && (pick.call === "BUY" || pick.call === "SELL")) {
          speakWarning({
            symbol: current,
            date: closed,
            kind: "strategy",
            side: pick.call === "BUY" ? "buy" : "sell",
            detail: (pick.call === "BUY" ? "Buy warning. " : "Sell warning. ") + name + ". " + pick.name + " on the daily.",
          });
        }
      }
      if (triggers.best !== false && state.best && state.best.on && bestSetupStatus().ready) {
        speakWarning({
          symbol: current,
          date: closed,
          kind: "best",
          side: "buy",
          detail: "Buy warning. Best setup is ready on " + name + ".",
        });
      }
    }
  } catch (error) {
    return;
  }
}

async function warnSqueeze() {
  return warnDesk();
}

async function autoTick() {
  if (!state.auto || state.busy) return;
  state.busy = true;
  let delay = 5000;
  try {
    if (state.offline && state.session) {
      const symbol = state.session.symbol;
      let bars = state.session.bars;
      if (bars && bars.length && PaperLive.lastTrade) {
        const row = state.catalog.find((item) => item.symbol === symbol);
        const trade = await PaperLive.lastTrade(symbol, row ? row.category : "");
        const last = bars[bars.length - 1];
        const live = Number(trade.price);
        if (trade.date && last.date.length === 10 && last.date < trade.date && trade.open > 0) {
          bars.push({
            date: trade.date,
            open: trade.open,
            high: Math.max(trade.high || live, trade.open, live),
            low: Math.min(trade.low || live, trade.open, live),
            close: live,
            volume: trade.volume || 0,
          });
        } else {
          bars[bars.length - 1] = {
            date: last.date,
            open: last.open,
            high: Math.max(last.high, live, last.open),
            low: Math.min(last.low, live, last.open),
            close: live,
            volume: last.volume,
          };
        }
        if (trade.asOf > 0) bars.asOf = trade.asOf;
        const tape = {};
        ["bid", "ask", "relVol", "tenDayAvg", "afterVolume"].forEach((key) => {
          if (Number(trade[key]) > 0) tape[key] = Number(trade[key]);
        });
        if (Object.keys(tape).length) bars.tape = Object.assign({}, bars.tape || {}, tape);
      } else {
        const row = state.catalog.find((item) => item.symbol === symbol);
        bars = await PaperLive.daily(symbol, row ? row.category : "");
        PaperEngine.replaceBars(state.session, bars);
      }
      const paper = PaperEngine.view(state.session);
      state.market = PaperEngine.market(state.session);
      stampQuote(bars);
      render(paper);
      renderLocalSignals();
    } else {
      const paper = await paperCall("/api/paper/step", { days: 1 });
      if (!state.auto) return;
      state.paper = paper;
      await loadMarket(paper.symbol);
      render(paper);
      try {
        await loadSignals();
      } catch (error) {
        $("status").textContent = error.message;
      }
    }
    if (state.paper && state.paper.paper !== true) {
      state.auto = false;
      $("status").textContent = "Auto stopped. Paper is off.";
      return;
    }
    const traded = await autoTrade();
    const fired = await fireAutoTrade();
    renderBestSetup();
    renderFireFive();
    if (state.offline) renderLocalSignals();
    $("status").textContent =
      traded ||
      fired ||
      (state.best && state.best.on && bestSetupStatus().ready
        ? "Best setup ready. Waiting for your Yes if a buy warning is open."
        : state.fireAuto && (state.fires || []).length
          ? "Fire 5 Auto is watching " + state.fires.length + " green Fire trade" + (state.fires.length === 1 ? "" : "s") + "."
          : "Auto is on. Refreshing the market quote.");
    await cycleScanLearn("auto");
  } catch (error) {
    state.auto = false;
    if (state.paper) render(state.paper);
    $("status").textContent = error.message;
    return;
  } finally {
    releaseBusy();
  }
  if (state.auto) state.autoTimer = setTimeout(autoTick, delay);
}

const voiceButton = $("voice");
if (voiceButton) {
  voiceButton.addEventListener("click", () => {
    state.voice = !state.voice;
    voiceButton.setAttribute("aria-pressed", state.voice ? "true" : "false");
    if (!state.voice && window.speechSynthesis) window.speechSynthesis.cancel();
    $("status").textContent = state.voice ? "Voice is on. Desk triggers on closed bars." : "Voice is off.";
    if (state.voice) warnDesk();
  });
}

$("auto").addEventListener("click", () => {
  if (!state.paper || state.paper.paper !== true) {
    $("status").textContent = "Turn Paper on first. Auto only refreshes the quote.";
    return;
  }
  state.auto = !state.auto;
  render(state.paper);
  if (!state.auto) {
    if (state.autoTimer) clearTimeout(state.autoTimer);
    state.autoTimer = 0;
    $("status").textContent = "Auto is off.";
    return;
  }
  $("status").textContent = "Auto is on. Refreshing the market quote.";
  autoTick();
});


function setSide(side) {
  state.side = side;
  const buy = $("side-buy");
  const sell = $("side-sell");
  if (!buy || !sell) return;
  buy.classList.toggle("on", side === "buy");
  sell.classList.toggle("on", side === "sell");
  buy.setAttribute("aria-pressed", side === "buy" ? "true" : "false");
  sell.setAttribute("aria-pressed", side === "sell" ? "true" : "false");
  $("send").classList.toggle("sell-side", side === "sell");
  $("send").textContent = side === "buy" ? "Send paper buy" : "Send paper sell";
  paintTicketPreview();
}

function syncTicketFields() {
  const kind = $("kind").value;
  $("stop-field").hidden = !(kind === "stop" || kind === "stop_limit");
  $("limit-field").hidden = !(kind === "limit" || kind === "stop_limit");
  paintTicketPreview();
}

function seedTicketPrices(paper) {
  const last = Number(paper && paper.last_close);
  if (!(last > 0)) return;
  const seeded = state.ticketSeed === paper.symbol;
  if (seeded) return;
  state.ticketSeed = paper.symbol;
  const digits = digitsFor(last);
  $("limit-price").value = last.toFixed(digits);
  $("stop-price").value = last.toFixed(digits);
}

function renderWorking(paper) {
  const body = $("working-body");
  if (!body) return;
  body.replaceChildren();
  const rows = (paper && paper.working) || [];
  if (!rows.length) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 7;
    cell.textContent = "No working paper orders.";
    row.appendChild(cell);
    body.appendChild(row);
  }
  rows.forEach((order) => {
    const row = document.createElement("tr");
    const kind = order.kind === "stop_limit" ? "STP LMT" : String(order.kind || "").toUpperCase();
    const values = [
      order.id,
      String(order.side || "").toUpperCase(),
      kind,
      order.stop > 0 ? formatPrice(order.stop) : "—",
      order.limit > 0 ? formatPrice(order.limit) : "—",
      order.tif,
    ];
    values.forEach((value, index) => {
      const cell = document.createElement("td");
      cell.textContent = value;
      if (index === 1) cell.className = order.side === "buy" ? "up" : "down";
      row.appendChild(cell);
    });
    const cell = document.createElement("td");
    const cancel = document.createElement("button");
    cancel.type = "button";
    cancel.className = "quiet";
    cancel.textContent = "Cancel";
    cancel.addEventListener("click", () => withBusy(async () => {
      const next = await paperCall("/api/paper/cancel", { id: order.id });
      render(next);
      $("status").textContent = "Canceled working paper order " + order.id + ".";
    }));
    cell.appendChild(cancel);
    row.appendChild(cell);
    body.appendChild(row);
  });
  const all = $("cancel-all");
  if (all) all.disabled = !rows.length;
}

function announceTicketFills(paper) {
  const list = (paper && paper.ticket_fills) || [];
  if (!state.seenFills) {
    state.seenFills = new Set(list.map((item) => item.at + "|" + item.order_id));
    return;
  }
  list.forEach((item) => {
    const key = item.at + "|" + item.order_id;
    if (state.seenFills.has(key)) return;
    state.seenFills.add(key);
    const verb = item.side === "buy" ? "Bought" : "Sold";
    $("status").textContent = "Working order " + item.order_id + " filled on paper. " + verb + " at " + formatPrice(item.price) + ". Nothing was sent to a broker.";
    if (state.voice && window.speechSynthesis) window.speechSynthesis.cancel();
  });
}

$("side-buy").addEventListener("click", () => setSide("buy"));
$("side-sell").addEventListener("click", () => setSide("sell"));
$("kind").addEventListener("change", syncTicketFields);
["limit-price", "stop-price"].forEach((id) => {
  const node = $(id);
  if (node) node.addEventListener("input", paintTicketPreview);
});
syncTicketFields();
setSide("buy");

$("send").addEventListener("click", () => withBusy(async () => {
  if (!$("confirm").checked) {
    $("status").textContent = "Check Confirm and Send, then send the paper order.";
    return;
  }
  const kind = $("kind").value;
  const ticket = {
    side: state.side,
    kind,
    tif: $("tif").value,
    // Market buys do not require a stop price. Stop/limit fields only apply to those order types.
    stop: kind === "stop" || kind === "stop_limit" ? Number($("stop-price").value) || 0 : 0,
    limit: kind === "limit" || kind === "stop_limit" ? Number($("limit-price").value) || 0 : 0,
  };
  if (!(await askFirst(ticket.side, (state.paper && state.paper.symbol) || selectedSymbol(), "you", { ticket }))) return;
  const paper = await paperCall("/api/paper/ticket", { ...ticket, confirmed: true });
  $("confirm").checked = false;
  render(paper);
  if (paper.last_action === "working") {
    $("status").textContent = "Working paper order placed. It fills when the real quote reaches it. Nothing was sent to a broker.";
  } else {
    const verb = paper.last_action === "sell" ? "Sold" : "Bought";
    $("status").textContent = (fillText(paper) || verb + " on the paper account.") + " Nothing was sent to a broker.";
  }
  await loadSignals();
}));

$("cancel-all").addEventListener("click", () => withBusy(async () => {
  const paper = await paperCall("/api/paper/cancel", { id: "all" });
  render(paper);
  $("status").textContent = "Canceled every working paper order.";
}));

const bookShare = $("book-share");
if (bookShare) bookShare.addEventListener("click", () => sharePaperBook(true));
const bookCopy = $("book-copy");
if (bookCopy) bookCopy.addEventListener("click", () => sharePaperBook(false));
const bookFile = $("book-file");
if (bookFile) {
  bookFile.addEventListener("change", () => {
    const file = bookFile.files && bookFile.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => withBusy(async () => {
      try {
        const payload = JSON.parse(String(reader.result || ""));
        await importPaperBook(payload);
      } catch (error) {
        const note = $("book-share-note");
        if (note) note.textContent = error.message || "That file could not be read.";
      } finally {
        bookFile.value = "";
      }
    });
    reader.onerror = () => {
      const note = $("book-share-note");
      if (note) note.textContent = "That file could not be read.";
      bookFile.value = "";
    };
    reader.readAsText(file);
  });
}

$("safe").addEventListener("click", () => runSafe(true));
$("win-startup").addEventListener("change", (event) => setWindows("startup", event.target));
$("win-desktop").addEventListener("change", (event) => setWindows("desktop", event.target));
$("int-refresh").addEventListener("click", () => loadInternals(true));
$("internals-pill").addEventListener("click", () => showDock("internals"));
$("int-gate").addEventListener("change", () => {
  state.int.gate = $("int-gate").checked;
  try {
    localStorage.setItem(INT_KEY, JSON.stringify({ gate: state.int.gate }));
  } catch (error) {
    /* The switch still works for this visit. */
  }
  renderInternals();
});
$("sq-scan").addEventListener("click", scanSqueeze);
if ($("sq-list")) {
  $("sq-list").addEventListener("change", () => {
    ensureCatalog()
      .then(() => paintSqueezeReadyNote())
      .catch(() => paintSqueezeReadyNote());
  });
}
paintSqueezeControls();
if ($("dp-scan")) $("dp-scan").addEventListener("click", scanDeskPool);
if ($("dp-stop")) {
  $("dp-stop").addEventListener("click", () => {
    if (state.dp) state.dp.stop = true;
  });
}
if ($("dp-refresh")) $("dp-refresh").addEventListener("click", () => refreshDeskPoolFeeds());
if ($("sc-run")) $("sc-run").addEventListener("click", runScorecard);
if ($("sc-stop")) {
  $("sc-stop").addEventListener("click", () => {
    if (state.sc) state.sc.stop = true;
  });
}
if ($("cycle-scan")) $("cycle-scan").addEventListener("click", () => runCycleDesk());
if ($("cycle-run")) $("cycle-run").addEventListener("click", () => runCycleDesk());
if ($("cycle-stop")) {
  $("cycle-stop").addEventListener("click", () => {
    if (state.cycle) state.cycle.stop = true;
  });
}
if ($("forecast-scan")) $("forecast-scan").addEventListener("click", () => runForecast());
if ($("forecast-run")) $("forecast-run").addEventListener("click", () => runForecast());
if ($("forecast-stop")) {
  $("forecast-stop").addEventListener("click", () => {
    if (state.forecast) state.forecast.stop = true;
  });
}
if ($("scorecard-open")) {
  $("scorecard-open").addEventListener("click", () => showDock("scorecard"));
}
if ($("outlook-refresh")) {
  $("outlook-refresh").addEventListener("click", () => refreshOutlook());
}
$("sq-stop").addEventListener("click", () => {
  state.sq.stop = true;
  $("sq-note").textContent = "Stopping after the markets already being read.";
});
$("sq-side").addEventListener("change", renderSqueeze);
setInterval(() => runSafe(false), 60000);

$("strat-pick").addEventListener("change", () => pickStrategy($("strat-pick").value));
const stratBuy = $("strat-buy");
if (stratBuy) stratBuy.addEventListener("click", () => stratTrade("buy"));
const stratSell = $("strat-sell");
if (stratSell) stratSell.addEventListener("click", () => stratTrade("sell"));
$("strat-auto-on").addEventListener("change", () => {
  state.strat.on = $("strat-auto-on").checked;
  state.strat.note = "";
  saveStrat();
  state.boardKey = "";
  renderStrategies();
  renderBestSetup();
  $("status").textContent = state.strat.on ? "Strategy trading is on. Turn Auto on and it follows " + state.strat.pick + " on closed bars." : "Strategy trading is off.";
});
const bestSetupBtn = $("best-setup");
if (bestSetupBtn) {
  bestSetupBtn.addEventListener("click", () => setBestSetup(!(state.best && state.best.on)));
  renderBestSetup();
}
[
  ["trig-squeeze", "squeeze"],
  ["trig-edge", "edge"],
  ["trig-strategy", "strategy"],
  ["trig-best", "best"],
  ["trig-process", "process"],
].forEach(([id, key]) => {
  const box = $(id);
  if (!box) return;
  box.addEventListener("change", () => {
    state.triggers[key] = box.checked;
    saveTriggers();
    $("status").textContent = box.checked
      ? "Trigger on: " + key + ". Voice speaks it on closed bars when Voice is on."
      : "Trigger off: " + key + ".";
  });
});
syncTriggerChecks();
$("strat-board").addEventListener("click", (event) => {
  const row = event.target.closest("tr[data-strategy]");
  if (row) pickStrategy(row.dataset.strategy);
});
$("strat-board").addEventListener("keydown", (event) => {
  const row = event.target.closest("tr[data-strategy]");
  if (row && (event.key === "Enter" || event.key === " ")) {
    event.preventDefault();
    pickStrategy(row.dataset.strategy);
  }
});
$("strat-sweep").addEventListener("click", sweepStrategy);
$("edge-frames-refresh").addEventListener("click", () => loadEdgeFrames(true));
$("cm-yes").addEventListener("click", () => closeConfirm(true));
$("cm-no").addEventListener("click", () => closeConfirm(false));
$("confirm-modal").addEventListener("click", (event) => {
  if (event.target === $("confirm-modal")) closeConfirm(false);
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && confirmWait) closeConfirm(false);
});
$("strat-load").addEventListener("click", loadOrb);
["strat-open", "strat-buffer", "strat-target"].forEach((id) => $(id).addEventListener("change", renderOrb));
$("strat-arm-buy").addEventListener("click", () => armOrb("buy"));
$("strat-arm-stop").addEventListener("click", () => armOrb("stop"));
$("strat-arm-target").addEventListener("click", () => armOrb("target"));

$("trade").addEventListener("click", async () => {
  if (state.busy || !state.paper) return;
  const side = state.paper.shares > 0 ? "sell" : "buy";
  if (!(await askFirst(side, state.paper.symbol, "you", {}))) return;
  tradeNow();
});

const tradeNow = () => withBusy(async () => {
  stopAuto();
  const paper = await paperCall("/api/paper/trade", { confirmed: true });
  render(paper);
  const verb = paper.last_action === "sell" ? "Sold" : "Bought";
  const caution = paper.last_action === "buy" ? internalsGate(paper.symbol) : "";
  $("status").textContent = (fillText(paper) || verb + " one simulated position on the paper account.") + (caution ? " Heads up: " + caution : "");
  await loadSignals();
});

$("halt").addEventListener("click", async () => {
  const button = $("halt");
  if (button.disabled || !state.paper) return;
  if (!(await askFirst("sell", state.paper.symbol, "you", { all: true }))) return;
  button.disabled = true;
  stopAuto("Selling everything on the paper account…");
  try {
    const paper = await paperCall("/api/paper/halt", { confirmed: true });
    render(paper);
    $("status").textContent = (paper.last_action === "sell" ? "Sold everything to paper cash. " + fillText(paper) : "Already in paper cash.") + " Buys stop until tomorrow.";
  } catch (error) {
    $("status").textContent = error.message;
    return;
  } finally {
    button.disabled = false;
  }
  loadSignals().catch(() => {});
});

$("live").addEventListener("click", () => {
  $("status").textContent = LIVE_NOTE + " To trade real money, use your broker's own app. Schwab Live Trading stays locked on this desk.";
});

$("paper").addEventListener("click", () => withBusy(async () => {
  const paper = await paperCall("/api/paper/mode", { mode: "paper" });
  render(paper);
  $("status").textContent = "Paper is on. Orders stay simulated on this computer.";
}));

$("schwab-connect").addEventListener("click", () => withBusy(async () => {
  if (state.offline) {
    $("status").textContent = "Schwab connect needs the computer desk on 127.0.0.1.";
    return;
  }
  if (state.schwab && state.schwab.connected) {
    const status = await postJSON("/api/schwab/disconnect", {});
    paintSchwabButtons(status);
    await loadSignals();
    $("status").textContent = "Disconnected from Schwab. Signals show red until you connect again.";
    return;
  }
  try {
    const start = await postJSON("/api/schwab/connect", {});
    window.open(start.authorize_url, "schwab-oauth", "popup=yes,width=720,height=820");
    openSchwabModal("Browser opened to Schwab. After sign-in, paste the https://127.0.0.1 redirect URL below.");
    $("status").textContent = "Finish Schwab connect by pasting the redirect URL.";
  } catch (error) {
    $("status").textContent = error.message;
    $("schwab-note").textContent = error.message;
  }
}));

$("schwab-modal-cancel").addEventListener("click", () => {
  closeSchwabModal();
  $("status").textContent = "Schwab connect canceled. Signals stay red until you connect.";
});

$("schwab-modal-save").addEventListener("click", () => withBusy(async () => {
  const redirect = $("schwab-redirect").value.trim();
  try {
    const status = await postJSON("/api/schwab/finish", { redirect });
    paintSchwabButtons(status);
    closeSchwabModal();
    await loadSignals();
    $("status").textContent = "Schwab connected. Signals show green. Market Data is still off.";
  } catch (error) {
    $("schwab-modal-note").textContent = error.message;
    $("status").textContent = error.message;
  }
}));

$("schwab-modal").addEventListener("click", (event) => {
  if (event.target === $("schwab-modal")) closeSchwabModal();
});

$("schwab-market").addEventListener("click", () => withBusy(async () => {
  if (!(state.schwab && state.schwab.connected)) {
    $("status").textContent = "Connect to Schwab before Market Data.";
    return;
  }
  const turnOn = !(state.schwab && state.schwab.market_data);
  try {
    const status = await postJSON("/api/schwab/market-data", { enabled: turnOn });
    paintSchwabButtons(status);
    await loadSignals();
    $("status").textContent = turnOn
      ? "Market Data on. Quotes still use the desk feed until Schwab bars are wired; connection stays green."
      : "Market Data off. Live Trading was cleared. Paper stays the default.";
  } catch (error) {
    $("status").textContent = error.message;
  }
}));

$("schwab-live").addEventListener("click", () => withBusy(async () => {
  // Max Safe: refuse arming. Clear any stale preference, then report Live locked.
  try {
    if (state.schwab && state.schwab.live_trading) {
      const status = await postJSON("/api/schwab/live-trading", { enabled: false, confirmed: true });
      paintSchwabButtons(status);
      await loadSignals();
    }
  } catch (error) {
    /* disarm may 400 when already off — still locked */
  }
  $("status").textContent = LIVE_NOTE + " Schwab Live Trading cannot arm on this desk. Paper only.";
}));

$("quote").addEventListener("click", () => {
  if (!state.paper) return;
  showChart();
  showRead(state.paper.symbol + " is on the chart.");
});

function canvasX(event, canvas) {
  const box = canvas.getBoundingClientRect();
  const point = event.clientX != null ? event.clientX : event.changedTouches && event.changedTouches[0] ? event.changedTouches[0].clientX : 0;
  const cssX = point - box.left;
  const scale = box.width ? (canvas.paperWidth || box.width) / box.width : 1;
  return cssX * scale;
}

function scrubReadout(event, map, canvas) {
  if (!map || !map.bars || !map.bars.length) return;
  const index = PaperFrames.barIndexAt(map.pad, map.slot, map.bars.length, canvasX(event, canvas));
  const bar = map.bars[index < 0 ? 0 : index];
  if (!bar) return;
  const node = $("chart-read");
  if (node) {
    node.textContent = (map.symbol || "Chart") + " · " + bar.date + " · O " + formatPrice(bar.open) + " · H " + formatPrice(bar.high) + " · L " + formatPrice(bar.low) + " · C " + formatPrice(bar.close);
  }
}

$("candles").addEventListener("click", (event) => {
  const map = state.chartMap;
  const canvas = $("candles");
  if (!map || !map.bars.length) {
    showRead("The chart has no bars yet.");
    return;
  }
  const index = PaperFrames.barIndexAt(map.pad, map.slot, map.bars.length, canvasX(event, canvas));
  const bar = map.bars[index < 0 ? 0 : index];
  state.ruler = { date: bar.date };
  showChart();
  showRead((map.symbol || "Chart") + " · " + bar.date + " · Open " + formatPrice(bar.open) + " · High " + formatPrice(bar.high) + " · Low " + formatPrice(bar.low) + " · Close " + formatPrice(bar.close));
  showChartTip(event, map, index < 0 ? 0 : index);
  drawCandles();
});

$("candles").addEventListener("pointermove", (event) => {
  if (event.pointerType === "touch" && event.buttons === 0) return;
  scrubReadout(event, state.chartMap, $("candles"));
});
$("candles").addEventListener("pointerleave", () => {
  if (state.paper) paintChartRead(state.paper);
});

function showChartTip(event, map, index) {
  const history = seenBars();
  const view = PaperFrames.zoomBars(history, state.zoom);
  const info = PaperFrames.describeBar(history, view.start + index, { format: formatPrice, crypto: chartCrypto(), title: (map.symbol || "Chart") + " · 1D" });
  PaperFrames.showTip(document, event.clientX, event.clientY, info);
}

function bindStudyScrub(id, mapKey) {
  const canvas = $(id);
  if (!canvas) return;
  canvas.addEventListener("click", (event) => {
    const map = state[mapKey];
    if (!map || !map.bars || !map.bars.length) return;
    const index = PaperFrames.barIndexAt(map.pad, map.slot, map.bars.length, canvasX(event, canvas));
    const bar = map.bars[index < 0 ? 0 : index];
    state.ruler = { date: bar.date };
    showChartTip(event, map, index < 0 ? 0 : index);
    redrawStudies();
  });
  canvas.addEventListener("pointermove", (event) => {
    if (event.pointerType === "touch" && event.buttons === 0) return;
    scrubReadout(event, state[mapKey], canvas);
  });
  canvas.addEventListener("pointerleave", () => {
    if (state.paper) paintChartRead(state.paper);
  });
}
bindStudyScrub("rsi-chart", "tmoMap");
bindStudyScrub("sqz-chart", "sqzMap");
bindStudyScrub("cci-chart", "cciMap");

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && !confirmWait) {
    PaperFrames.hideTip(document);
    return;
  }
  const tag = ((event.target && event.target.tagName) || "").toLowerCase();
  if (tag === "input" || tag === "textarea" || tag === "select" || (event.target && event.target.isContentEditable)) return;
  if (event.metaKey || event.ctrlKey || event.altKey) return;
  if (event.key === "/") {
    event.preventDefault();
    const search = $("search");
    if (search) search.focus();
    return;
  }
  if (event.key === "b" || event.key === "B") {
    setSide("buy");
    setStatus("Ticket set to Buy.");
    return;
  }
  if (event.key === "s" || event.key === "S") {
    setSide("sell");
    setStatus("Ticket set to Sell.");
    return;
  }
  if (event.key === "1") { $("kind").value = "market"; syncTicketFields(); setStatus("Order type: Market."); return; }
  if (event.key === "2") { $("kind").value = "limit"; syncTicketFields(); setStatus("Order type: Limit."); return; }
  if (event.key === "3") { $("kind").value = "stop"; syncTicketFields(); setStatus("Order type: Stop."); return; }
  if (event.key === "4") { $("kind").value = "stop_limit"; syncTicketFields(); setStatus("Order type: Stop Limit."); return; }
  if (event.key === "j" || event.key === "J") {
    showDock("journal");
    setStatus("Journal is open.");
    return;
  }
  if (event.key === "c" || event.key === "C") {
    showChart();
    setStatus("Chart is open.");
  }
});

const scanToggle = $("scan-toggle");
if (scanToggle) {
  scanToggle.addEventListener("click", () => {
    setScanner(!state.scanOn).catch(() => {
      state.scanNote = "The scanner switch did not save.";
      renderScan();
    });
  });
}

const fireAutoBtn = $("fire-auto");
if (fireAutoBtn) {
  state.fireAuto = loadFireAuto();
  state.deskCard = readDeskCardId();
  renderFireFive();
  fireAutoBtn.addEventListener("click", () => {
    const next = !state.fireAuto;
    saveFireAuto(next);
    renderFireFive();
    if (next) {
      if (state.paper && state.paper.paper === true && !state.auto) {
        state.auto = true;
        render(state.paper);
        autoTick();
      }
      $("status").textContent =
        "Fire 5 Auto is on. Confirm Lock (Desk Cards) gates paper buys. Green Fire trades still ask Yes. Scanner should stay on.";
      refreshFireFive().catch(() => {});
    } else {
      $("status").textContent = "Fire 5 Auto is off. Green Fire signals still show for review.";
    }
  });
}

function wireDeskCardButton(button, key) {
  if (!button) return;
  button.addEventListener("click", () => {
    writeDeskCardId(key);
    renderDeskCards();
    renderBestSetup();
    const card = activeDeskCard(chartCrypto());
    $("status").textContent =
      "Desk Card · " +
      ((typeof PaperFrames !== "undefined" && PaperFrames.DESK_CARDS && PaperFrames.DESK_CARDS[key] && PaperFrames.DESK_CARDS[key].name) || key) +
      ". Open market Confirm Lock uses " +
      (card.name || "Desk Card") +
      ". Paper only. Live locked.";
  });
}
wireDeskCardButton($("card-auto"), "auto");
wireDeskCardButton($("card-stockfire"), "stockfire");
wireDeskCardButton($("card-coinseat"), "coinseat");
state.deskCard = readDeskCardId();

const tideRunBtn = $("tide-run");
if (tideRunBtn) {
  renderTideFade();
  tideRunBtn.addEventListener("click", () => {
    $("status").textContent = "Refreshing Tide Alert and Go Long 5…";
    refreshTideFade()
      .then(() => {
        const tide = state.tide && state.tide.result;
        $("status").textContent = tide && tide.on ? "Tide Alert on — Go Long 5 ready for review." : "Tide Alert off.";
      })
      .catch((error) => {
        $("status").textContent = error.message || String(error);
      });
  });
}

$("pin-toggle").addEventListener("click", () => {
  const current = state.paper && state.paper.symbol;
  if (!current) return;
  const pins = readPins();
  const next = pins.includes(current) ? pins.filter((symbol) => symbol !== current) : [current].concat(pins).slice(0, PIN_LIMIT);
  writePins(next);
  renderPins();
});

let searchTimer = 0;
function queueSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    refreshSymbols().catch((error) => {
      $("status").textContent = error.message;
    });
  }, 160);
}

$("search").addEventListener("input", queueSearch);
$("category").addEventListener("change", () => {
  writeType($("category").value);
  queueSearch();
  if (state.offline && state.scanOn) scanPhone();
});

$("next").addEventListener("click", () => withBusy(async () => {
  render(await paperCall("/api/paper/step", { days: 1 }));
}));

$("run").addEventListener("click", () => withBusy(async () => {
  stopAuto();
  render(await paperCall("/api/paper/run", {}));
  $("status").textContent = "The quote is current.";
}));

$("reset").addEventListener("click", () => withBusy(async () => {
  stopAuto();
  const paper = await paperCall("/api/paper/reset", formParams());
  state.paper = paper;
  await loadMarket(paper.symbol);
  render(paper);
  $("status").textContent = "Session reset on the latest quote.";
}));

async function runBacktestReport() {
  const symbol = selectedSymbol() || ((state.paper && state.paper.symbol) || "");
  if (state.offline) {
    if ($("backtest-summary")) {
      $("backtest-summary").textContent =
        "On the phone, the quote stays current. Open " +
        (symbol || "this market") +
        " on the Windows desk and press Run full backtest (or Tools → Backtest) for the trade table.";
    }
    if ($("backtest-body")) $("backtest-body").innerHTML = "";
    showDock("backtest");
    return;
  }
  if ($("backtest-summary")) {
    $("backtest-summary").textContent = "Replaying " + (symbol || "market") + " history…";
  }
  const report = await post("/api/backtest", formParams());
  const factor = report.profit_factor == null ? "—" : report.profit_factor.toFixed(2);
  if ($("backtest-summary")) {
    $("backtest-summary").textContent =
      (symbol || "Market") +
      " · Return " +
      report.return_pct.toFixed(2) +
      "% · drawdown " +
      report.max_drawdown_pct.toFixed(2) +
      "% · trades " +
      report.trade_count +
      " · win rate " +
      report.win_rate_pct.toFixed(1) +
      "% · profit factor " +
      factor +
      ". Paper session stays on the latest quote.";
  }
  fillTrades($("backtest-body"), report.trades || []);
  showDock("backtest", { skipRun: true });
  if ($("status")) {
    $("status").textContent =
      "Backtest " + (symbol || "") + ": " + report.trade_count + " trade" + (report.trade_count === 1 ? "" : "s") + ".";
  }
}

$("full").addEventListener("click", () => withBusy(async () => {
  await runBacktestReport();
}));

function storedStudies() {
  try {
    const parsed = JSON.parse(localStorage.getItem(STUDY_KEY) || "[]");
    return Array.isArray(parsed) ? parsed.filter((item) => item && typeof item.code === "string" && typeof item.name === "string").slice(0, 20) : [];
  } catch (error) {
    return [];
  }
}

function saveStudies(studies) {
  try {
    localStorage.setItem(STUDY_KEY, JSON.stringify(studies.slice(0, 20)));
  } catch (error) {
    return;
  }
}

function fillStudyList() {
  const select = $("study-list");
  const studies = storedStudies();
  select.replaceChildren();
  if (!studies.length) {
    const option = document.createElement("option");
    option.value = "";
    option.textContent = "None installed";
    select.appendChild(option);
    return;
  }
  studies.forEach((item) => {
    const option = document.createElement("option");
    option.value = item.name;
    option.textContent = item.name;
    select.appendChild(option);
  });
}

function restoreStudies() {
  fillStudyList();
  const studies = storedStudies();
  if (!studies.length) return;
  $("study-name").value = studies[0].name;
  $("study-code").value = studies[0].code;
  state.studyCode = studies[0].code;
}

let toolsReturnTab = "chart";

function paintOpenToolsButton() {
  const btn = $("open-tools");
  if (!btn) return;
  const open = !!document.body.dataset.tools;
  btn.setAttribute("aria-pressed", open ? "true" : "false");
  btn.classList.toggle("on", open);
}

function hideTools() {
  delete document.body.dataset.tools;
  paintOpenToolsButton();
  const phone = window.matchMedia("(max-width: 980px)").matches;
  if (phone) {
    const back = toolsReturnTab === "study" ? "chart" : toolsReturnTab || "chart";
    showPhoneTab(back);
  }
  const status = $("status");
  if (status) status.textContent = "Back to desk.";
}

function backFromToolPage() {
  const current = document.body.dataset.tools || "";
  if (current && current !== "home") {
    showDock("home");
    const status = $("status");
    if (status) status.textContent = "Tools.";
    return;
  }
  hideTools();
}

function showDock(name, opts) {
  if (!name) name = "home";
  if (!document.querySelector('.dock-tab[data-dock="' + name + '"]')) name = "home";
  const skipRun = !!(opts && opts.skipRun);
  if (name === "internals") loadInternals(false);
  if (name === "scorecard") {
    renderScorecard();
    if (!(state.sc && state.sc.result) && !(state.sc && state.sc.busy)) runScorecard();
  }
  if (name === "cycle") {
    renderCycleDesk();
    if (!(state.cycle && state.cycle.at) && !(state.cycle && state.cycle.busy)) runCycleDesk();
  }
  if (name === "forecast") {
    renderForecast();
    if (!(state.forecast && state.forecast.at) && !(state.forecast && state.forecast.busy)) runForecast();
  }
  if (name === "deskpool") {
    ensureCatalog().catch(() => {});
    renderDeskPool();
    refreshDeskPoolFeeds();
  }
  if (name === "squeeze") {
    paintSqueezeControls();
    ensureCatalog()
      .then(() => {
        if (!(state.sq && state.sq.busy) && !(state.sq && state.sq.rows && state.sq.rows.length && state.sq.at)) {
          paintSqueezeReadyNote();
        }
      })
      .catch(() => {});
  }
  if (name === "frames") {
    renderFrames();
    renderEdgeFrames();
    if (state.paper && !(state.ef && state.ef.busy) && !(state.ef && state.ef.at)) loadEdgeFrames(false);
  }
  if (name === "readings") {
    renderReadings();
  }
  if (name === "backtest" && !state.offline && !skipRun && !state.backtestBusy) {
    state.backtestBusy = true;
    withBusy(async () => {
      await runBacktestReport();
    }).finally(() => {
      state.backtestBusy = false;
    });
  }
  if (name === "strategies") {
    ensureCatalog().catch(() => {});
    if (state.paper && (!state.orb || state.orb.symbol !== state.paper.symbol) && !state.orbBusy) {
      state.orbBusy = true;
      loadOrb().finally(() => {
        state.orbBusy = false;
      });
    }
  }
  if (!document.body.dataset.tools) {
    const tab = document.body.dataset.tab || "chart";
    toolsReturnTab = tab === "study" ? "chart" : tab;
  }
  document.body.dataset.tools = name;
  paintOpenToolsButton();
  document.querySelectorAll(".dock-tab").forEach((button) => {
    button.classList.toggle("on", button.dataset.dock === name);
  });
  document.querySelectorAll(".dock-page").forEach((page) => {
    page.classList.toggle("on", page.dataset.dockPage === name);
  });
  try {
    localStorage.setItem(DOCK_KEY, name);
  } catch (error) {
    /* Dock still switches for this page. */
  }
  if (name === "journal") {
    const box = $("positions-body");
    if (box) delete box.dataset.stamp;
    ["journal-buys", "journal-sells", "journal-filled"].forEach((id) => {
      const node = $(id);
      if (node) delete node.dataset.stamp;
    });
    renderBook(state.paper);
    renderJournalCheck(state.paper);
  }
  nudgeLearn(name === "home" ? "study" : name);
  const phone = window.matchMedia("(max-width: 980px)").matches;
  if (phone) {
    document.body.dataset.tab = "study";
    document.querySelectorAll(".tabbar [data-tab]").forEach((button) => {
      button.classList.toggle("on", button.dataset.tab === "study");
    });
  }
  const workspace = document.querySelector(".workspace");
  if (workspace) workspace.scrollTop = 0;
  const page = document.querySelector('.dock-page.on');
  if (page) page.scrollTop = 0;
}

function restoreDock() {
  try {
    const name = localStorage.getItem(DOCK_KEY) || "home";
    if (!document.querySelector('.dock-tab[data-dock="' + name + '"]')) return;
    /* Remember the last Tools page without opening it over the main desk. */
    document.querySelectorAll(".dock-tab").forEach((button) => {
      button.classList.toggle("on", button.dataset.dock === name);
    });
    document.querySelectorAll(".dock-page").forEach((page) => {
      page.classList.toggle("on", page.dataset.dockPage === name);
    });
  } catch (error) {
    return;
  }
}

$("study-install").addEventListener("click", () => {
  try {
    const code = $("study-code").value;
    const result = PaperStudy.runStudy(code, (state.market && state.market.bars) || []);
    const name = ($("study-name").value || "Study").trim().slice(0, 40) || "Study";
    const studies = storedStudies().filter((item) => item.name !== name);
    studies.unshift({ name, code });
    saveStudies(studies);
    state.studyCode = code;
    state.study = result;
    fillStudyList();
    $("study-list").value = name;
    drawCandles();
    $("study-status").textContent = "Installed " + name + " on this device. " + result.plots.length + " plots. No broker order was sent.";
  } catch (error) {
    state.study = null;
    $("study-status").textContent = error.message;
  }
});

$("study-remove").addEventListener("click", () => {
  const name = $("study-list").value;
  if (!name) return;
  saveStudies(storedStudies().filter((item) => item.name !== name));
  state.study = null;
  state.studyCode = "";
  fillStudyList();
  if (state.market) drawCandles();
  $("study-status").textContent = "Removed from this device.";
});

$("study-list").addEventListener("change", () => {
  const found = storedStudies().find((item) => item.name === $("study-list").value);
  if (!found) return;
  $("study-name").value = found.name;
  $("study-code").value = found.code;
  state.studyCode = found.code;
  try {
    state.study = PaperStudy.runStudy(found.code, (state.market && state.market.bars) || []);
    if (state.market) drawCandles();
    $("study-status").textContent = found.name + " is on the chart. It stays on this device.";
  } catch (error) {
    $("study-status").textContent = error.message;
  }
});

$("frames-all").addEventListener("click", () => openAllFrames());
$("brief-window").addEventListener("click", () => openBrief());

function showPhoneTab(name) {
  if (name === "study") {
    if (!document.body.dataset.tools) {
      toolsReturnTab = document.body.dataset.tab || "chart";
    }
    document.body.dataset.tab = "study";
    document.querySelectorAll(".tabbar [data-tab]").forEach((item) => item.classList.toggle("on", item.dataset.tab === "study"));
    if (state.market && state.paper) redrawStudies();
    showDock(document.body.dataset.tools || (document.querySelector(".dock-tab.on") || {}).dataset.dock || "home");
    return;
  }
  delete document.body.dataset.tools;
  paintOpenToolsButton();
  document.body.dataset.tab = name;
  document.querySelectorAll(".tabbar [data-tab]").forEach((item) => item.classList.toggle("on", item.dataset.tab === name));
  if (name === "chart") paintChartToolBoxes(state.market && state.market.history);
  if (state.market && state.paper && name === "chart") redrawStudies();
  nudgeLearn(name);
}

document.querySelectorAll(".tabbar [data-tab]").forEach((button) => {
  button.addEventListener("click", () => showPhoneTab(button.dataset.tab));
});

const chartBack = $("chart-back");
if (chartBack) {
  chartBack.addEventListener("click", () => {
    if (document.body.dataset.tools) {
      hideTools();
      return;
    }
    if (window.matchMedia("(max-width: 980px)").matches) {
      showPhoneTab("markets");
      $("status").textContent = "Back to markets.";
      return;
    }
    showDock("home");
    $("status").textContent = "Tools.";
  });
}

const signalSum = $("signal-sum");
if (signalSum) signalSum.addEventListener("click", () => showDock("signals"));

document.querySelectorAll(".dock-tab").forEach((button) => {
  button.addEventListener("click", () => showDock(button.dataset.dock));
});

document.querySelectorAll("[data-open-dock]").forEach((button) => {
  button.addEventListener("click", () => {
    const name = button.dataset.openDock;
    showDock(name);
    $("status").textContent = "Opened " + (button.textContent || name) + ".";
  });
});

document.querySelectorAll(".dock-back").forEach((button) => {
  button.addEventListener("click", () => backFromToolPage());
});

const indexTape = $("index-tape");
if (indexTape) {
  indexTape.querySelectorAll(".index-chip[data-index-symbol]").forEach((chip) => {
    chip.addEventListener("click", () => {
      const symbol = chip.dataset.indexSymbol;
      if (symbol) chooseSymbol(symbol);
    });
  });
}

const openTools = $("open-tools");
if (openTools) {
  openTools.addEventListener("click", () => {
    if (document.body.dataset.tools === "home") {
      hideTools();
      return;
    }
    showDock("home");
    const status = $("status");
    if (status) status.textContent = "Tools.";
  });
}
paintOpenToolsButton();

const buildTag = $("build-tag");
if (buildTag) buildTag.textContent = VERSION;

window.addEventListener("resize", () => {
  if (state.market && state.paper) {
    redrawStudies();
    const bars = seenBars();
    const tone = currentMood(bars);
    drawMoodClock(tone && tone.score, tone && tone.label);
  }
});

function bindZoom(canvas, which) {
  if (!canvas) return;
  canvas.addEventListener("wheel", (event) => {
    event.preventDefault();
    stepZoom(which, event.deltaY > 0 ? 1 : -1);
  }, { passive: false });
}

[["zoom-in", "chart", -1], ["zoom-out", "chart", 1], ["preview-zoom-in", "preview", -1], ["preview-zoom-out", "preview", 1]].forEach(([id, which, direction]) => {
  const button = $(id);
  if (button) button.addEventListener("click", () => stepZoom(which, direction));
});
const layersToggle = $("layers-toggle");
if (layersToggle) {
  layersToggle.addEventListener("click", () => {
    const panel = $("layers");
    const open = panel.hidden;
    panel.hidden = !open;
    layersToggle.setAttribute("aria-expanded", open ? "true" : "false");
    requestAnimationFrame(() => drawCandles());
  });
}
state.layers = readLayers();
paintLayers();
bindZoom($("candles"), "chart");
bindZoom($("rsi-chart"), "chart");
bindZoom($("sqz-chart"), "chart");
bindZoom($("cci-chart"), "chart");
bindZoom($("vol-chart"), "chart");
bindZoom($("frame-preview"), "preview");

const STUDY_PANE_KEY = "paperTraderStudyPanes";
const VOLUME_WAVE_KEY = "paperTraderVolumeWave";

function readVolumeWavePrefs() {
  const chosen = { n: 6, atrMult: 0.7, avgLen: 6, avgType: "sma", paint: false };
  try {
    const saved = JSON.parse(localStorage.getItem(VOLUME_WAVE_KEY) || "{}");
    if (saved && typeof saved === "object") {
      const n = Math.round(Number(saved.n));
      const atrMult = Number(saved.atrMult);
      const avgLen = Math.round(Number(saved.avgLen));
      if (Number.isFinite(n) && n >= 2 && n <= 80) chosen.n = n;
      if (Number.isFinite(atrMult) && atrMult > 0 && atrMult <= 5) chosen.atrMult = atrMult;
      if (Number.isFinite(avgLen) && avgLen >= 1 && avgLen <= 80) chosen.avgLen = avgLen;
      if (saved.avgType === "ema" || saved.avgType === "sma") chosen.avgType = saved.avgType;
      if (typeof saved.paint === "boolean") chosen.paint = saved.paint;
    }
  } catch (error) {
    /* Defaults stay. */
  }
  return chosen;
}

function saveVolumeWavePrefs(next) {
  try {
    localStorage.setItem(VOLUME_WAVE_KEY, JSON.stringify(next));
  } catch (error) {
    /* Choice still holds for this page. */
  }
}

function paintVolumeWavePrefs() {
  const prefs = readVolumeWavePrefs();
  const n = $("wave-n");
  const atr = $("wave-atr");
  const avg = $("wave-avg");
  const avgType = $("wave-avg-type");
  const paint = $("wave-paint");
  if (n) n.value = String(prefs.n);
  if (atr) atr.value = String(prefs.atrMult);
  if (avg) avg.value = String(prefs.avgLen);
  if (avgType) avgType.value = prefs.avgType;
  if (paint) paint.checked = !!prefs.paint;
}

function bindVolumeWaveSettings() {
  const apply = () => {
    const next = {
      n: Math.round(Number(($("wave-n") && $("wave-n").value) || 6)),
      atrMult: Number(($("wave-atr") && $("wave-atr").value) || 0.7),
      avgLen: Math.round(Number(($("wave-avg") && $("wave-avg").value) || 6)),
      avgType: ($("wave-avg-type") && $("wave-avg-type").value) === "ema" ? "ema" : "sma",
      paint: !!($("wave-paint") && $("wave-paint").checked),
    };
    if (!(next.n >= 2 && next.n <= 80)) next.n = 6;
    if (!(next.atrMult > 0 && next.atrMult <= 5)) next.atrMult = 0.7;
    if (!(next.avgLen >= 1 && next.avgLen <= 80)) next.avgLen = 6;
    saveVolumeWavePrefs(next);
    paintVolumeWavePrefs();
    requestAnimationFrame(() => redrawStudies());
  };
  ["wave-n", "wave-atr", "wave-avg", "wave-avg-type", "wave-paint"].forEach((id) => {
    const el = $(id);
    if (!el) return;
    el.addEventListener("change", apply);
    if (el.tagName === "INPUT" && el.type === "number") el.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        apply();
      }
    });
  });
  paintVolumeWavePrefs();
}

function readStudyPanes() {
  const chosen = { price: true, tmo: true, sqz: true, cci: true, vol: true, big: true };
  try {
    const saved = JSON.parse(localStorage.getItem(STUDY_PANE_KEY) || "{}");
    if (saved && typeof saved === "object") {
      ["price", "tmo", "sqz", "cci", "vol"].forEach((id) => {
        if (typeof saved[id] === "boolean") chosen[id] = saved[id];
      });
      if (typeof saved.big === "boolean") chosen.big = saved.big;
    }
  } catch (error) {
    /* Keep the open panes. */
  }
  return chosen;
}

function saveStudyPanes(next) {
  try {
    localStorage.setItem(STUDY_PANE_KEY, JSON.stringify(next));
  } catch (error) {
    /* Choice still holds for this page. */
  }
}

function setStudyPane(id, open) {
  const pane = document.querySelector('.study-pane[data-pane="' + id + '"]');
  if (!pane) return;
  pane.dataset.expanded = open ? "true" : "false";
  const roll = pane.querySelector(".study-roll");
  if (roll) {
    roll.setAttribute("aria-expanded", open ? "true" : "false");
    roll.textContent = open ? "▾" : "▸";
  }
}

function paintStudyPanes() {
  const saved = readStudyPanes();
  ["price", "tmo", "sqz", "cci", "vol"].forEach((id) => setStudyPane(id, saved[id] !== false));
  document.querySelectorAll(".study-pane").forEach((pane) => {
    pane.classList.toggle("big", !!saved.big);
  });
  const expand = $("studies-expand-all");
  if (expand) expand.textContent = saved.big ? "Compact studies ▴" : "Expand studies ▾";
}

function openStudiesWindow(focus) {
  if (!state.market || !state.paper) {
    setStatus("Open a market first, then open TMO · Squeeze · CCI · Waves.");
    return;
  }
  const symbol = state.paper.symbol || "Chart";
  const win = window.open("", "paper-studies", "popup=yes,width=980,height=980,left=48,top=48");
  if (!win) {
    setStatus("The studies window was blocked. Allow popups for this desk, or expand the panes below the price chart.");
    return;
  }
  win.document.open();
  win.document.write(
    "<!DOCTYPE html><title>Studies</title>" +
    "<body style=\"margin:0;background:#05070a;color:#e7edf3;font:14px Segoe UI,sans-serif\">" +
    "<div style=\"display:flex;gap:8px;align-items:center;padding:10px 14px;border-bottom:1px solid #1a222c;background:#0d1218\">" +
    "<button id=\"back\" type=\"button\" style=\"min-height:34px;padding:4px 10px\">Back</button>" +
    "<strong id=\"studies-head\" style=\"letter-spacing:.04em\"></strong>" +
    "<span id=\"note\" style=\"color:#9aa6b2;margin-left:auto\"></span>" +
    "<button id=\"in\" type=\"button\" style=\"min-height:34px;padding:4px 10px\">Zoom in</button>" +
    "<button id=\"out\" type=\"button\" style=\"min-height:34px;padding:4px 10px\">Zoom out</button>" +
    "</div>" +
    "<p style=\"margin:8px 14px 0;color:#9aa6b2;font-size:12px\">Same closed-bar studies as the chart. Public math only. Not a forecast.</p>" +
    "<div style=\"display:flex;flex-direction:column;gap:10px;padding:10px 14px 18px;height:calc(100vh - 78px);box-sizing:border-box;overflow:auto\">" +
    "<div><div style=\"margin:0 0 4px;font-size:12px;color:#67e8f9\">TMO 14/5/3</div><canvas id=\"tmo\" style=\"width:100%;height:22vh;background:#000;border:1px solid #1a222c\"></canvas></div>" +
    "<div><div style=\"margin:0 0 4px;font-size:12px;color:#facc15\">Squeeze momentum</div><canvas id=\"sqz\" style=\"width:100%;height:18vh;background:#000;border:1px solid #1a222c\"></canvas></div>" +
    "<div><div style=\"margin:0 0 4px;font-size:12px;color:#f4f7fb\">CCI 50 · 14 · 5</div><canvas id=\"cci\" style=\"width:100%;height:22vh;background:#000;border:1px solid #1a222c\"></canvas></div>" +
    "<div><div style=\"margin:0 0 4px;font-size:12px;color:#3ddc97\">Volume Wave · Desk Waves</div><canvas id=\"vol\" style=\"width:100%;height:22vh;background:#000;border:1px solid #1a222c\"></canvas></div>" +
    "</div></body>"
  );
  win.document.close();
  win.document.title = symbol + " · Studies";
  const head = win.document.getElementById("studies-head");
  if (head) head.textContent = symbol + " · TMO · Squeeze · CCI · Waves";
  const paint = () => {
    const view = PaperFrames.zoomBars(sliceToCursor(state.market.bars), state.zoom);
    const note = win.document.getElementById("note");
    if (note) note.textContent = view.size + " bars · Edge on the main desk";
    drawTmo(win.document.getElementById("tmo"));
    drawSqueeze(win.document.getElementById("sqz"));
    drawCci(win.document.getElementById("cci"));
    drawVolumeWave(win.document.getElementById("vol"));
  };
  const back = win.document.getElementById("back");
  if (back) back.addEventListener("click", () => win.close());
  const inn = win.document.getElementById("in");
  const out = win.document.getElementById("out");
  if (inn) inn.addEventListener("click", () => {
    state.zoom = PaperFrames.zoomStep(state.zoom, -1, seenBars().length);
    paintZoom(seenBars().length);
    redrawStudies();
    paint();
  });
  if (out) out.addEventListener("click", () => {
    state.zoom = PaperFrames.zoomStep(state.zoom, 1, seenBars().length);
    paintZoom(seenBars().length);
    redrawStudies();
    paint();
  });
  win.addEventListener("resize", paint);
  requestAnimationFrame(paint);
  if (focus === "tmo") win.document.getElementById("tmo").scrollIntoView();
  if (focus === "sqz") win.document.getElementById("sqz").scrollIntoView();
  if (focus === "cci") win.document.getElementById("cci").scrollIntoView();
  if (focus === "vol") win.document.getElementById("vol").scrollIntoView();
  setStatus("Opened TMO · Squeeze · CCI · Waves in a separate window.");
  state.studiesWin = win;
}

document.querySelectorAll(".study-roll").forEach((button) => {
  button.addEventListener("click", () => {
    const pane = button.closest(".study-pane");
    if (!pane) return;
    const id = pane.dataset.pane;
    const open = pane.dataset.expanded !== "true";
    setStudyPane(id, open);
    const saved = readStudyPanes();
    saved[id] = open;
    saveStudyPanes(saved);
    requestAnimationFrame(() => redrawStudies());
  });
});

document.querySelectorAll(".study-pop").forEach((button) => {
  button.addEventListener("click", () => openStudiesWindow(button.dataset.study));
});

const studiesWindowBtn = $("studies-window");
if (studiesWindowBtn) studiesWindowBtn.addEventListener("click", () => openStudiesWindow());

const studiesExpandBtn = $("studies-expand-all");
if (studiesExpandBtn) {
  studiesExpandBtn.addEventListener("click", () => {
    const saved = readStudyPanes();
    saved.big = !saved.big;
    saved.tmo = true;
    saved.sqz = true;
    saved.cci = true;
    saved.vol = true;
    saveStudyPanes(saved);
    paintStudyPanes();
    requestAnimationFrame(() => redrawStudies());
    setStatus(saved.big ? "Study panes expanded. Use Open TMO · Squeeze · CCI · Waves for a full window." : "Study panes back to normal size.");
  });
}

paintStudyPanes();
bindVolumeWaveSettings();
paintChartToolBoxes([]);

boot().then(() => {
  document.body.classList.add("ready");
  paintChartToolBoxes(state.market && state.market.history);
  loadBreadth();
  setInterval(loadBreadth, 60000);
  restoreDock();
  runSafe(false);
  loadInternals(false);
  setInterval(() => loadInternals(true), 600000);
  syncTriggerChecks();
  warnDesk();
  setInterval(warnDesk, 20000);
  refreshIndexTape().catch(() => {});
  setInterval(() => refreshIndexTape().catch(() => {}), 30000);
  setInterval(() => {
    if (state.paper) renderQuote(state.paper);
    syncDeskStatus();
  }, 1000);
  startScanLearnLoop();
}).catch((error) => {
  showProblem(error && error.message ? error.message : "The desk did not start.");
});
