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
  leaders: [],
  scanNote: "",
  scanDone: false,
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

async function getJSON(url, options) {
  const response = await fetch(url, options);
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payload.error || "Request failed");
  }
  return payload;
}

const VERSION = "1.9.40";

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
    return PaperEngine.cancel(state.session, params.id);
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
  if (!next.some((item) => item.name === "Learning")) {
    setSignal(next, "Learning", true, state.offline ? "This symbol, while the page is open" : "Starts when you open the desk, then keeps scoring after that window closes");
  }
  const tone = currentMood(bars);
  if (tone && tone.score != null) setSignal(next, "Fear and greed", true, tone.label + " " + tone.score);
  else if (!next.some((item) => item.name === "Fear and greed")) setSignal(next, "Fear and greed", false, "Waiting on the quote");
  const breaker = breakerState();
  setSignal(next, "Breaker", !breaker.tripped, breaker.tripped ? "Tripped. Buys stop until tomorrow" : "Armed at " + Number(breaker.drop_pct || 5) + "% a day");
  setSignal(next, "Live", true, "Locked. Paper only");
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
  dot.className = item.ok ? "dot on" : "dot";
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
    { name: "Arrows", ok: true, detail: "Ready" },
    { name: "Study", ok: typeof PaperStudy !== "undefined", detail: "On this device" },
    { name: "Learning", ok: true, detail: "This symbol, while the page is open" },
    { name: "Fear and greed", ok: !!(state.market && state.market.bars && state.market.bars.length >= 20), detail: phoneMood() },
    { name: "Zoom", ok: !!($("zoom-in") && $("zoom-out")), detail: "In and out" },
    { name: "Timeframes", ok: typeof PaperFrames !== "undefined" && PaperFrames.FRAMES.length === 10, detail: "10 windows" },
    { name: "Broker", ok: true, detail: "Not connected" },
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

function phoneBuyReason(symbol) {
  if (breakerState().tripped) return BREAKER_STOP;
  if (!state.scanOn && !state.scanDone) return "Turn the scanner on to fill today's best.";
  if (!state.scanDone) return "The scan is still walking. Green signals come on when it finishes.";
  const bars = state.session && state.session.bars ? state.session.bars : [];
  const day = bars.length ? bars[bars.length - 1].date : "";
  const bought = (state.session.dayBuys || []).filter((key) => key.indexOf(day + "|") === 0);
  const names = new Set(bought.map((key) => key.slice(day.length + 1)));
  if (names.size >= 5) return "Today already has five paper buys.";
  if (!(state.picks || []).some((row) => row.symbol === symbol)) return "This market is not in today's best.";
  if (names.has(symbol)) return "This market already has a paper buy today.";
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
const SCAN_READERS = 3;
const SCAN_GAP = 150;
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
  state.scanNote = "Scanned 0 of " + total + ". " + scanKindNote();
  state.pickNote = "";
  renderScan();
  renderToday();
  const live = () => token === phoneScanToken && state.scanOn;
  const publish = () => {
    const leaders = phoneLeaders(scored, false).slice(0, 10);
    const picks = bestPicks(scored);
    state.leaders = leaders;
    state.picks = picks;
    const done = finished === total;
    const score = leaders.length ? "Highest closed-bar scores. Not a forecast." : "No market cleared the bar yet. Not a forecast.";
    state.scanNote = "Scanned " + finished + " of " + total + ". " + scanKindNote() + " " + score + missNote(errors);
    state.pickNote = done
      ? (picks.length
        ? "Scan finished. Green signal on each name. Three to five, and no more. Not a forecast."
        : "No market cleared the bar today.")
      : "Ten names, then three to five. Green signals come on when the scan finishes.";
    state.scanDone = done;
    renderScan();
    renderToday();
  };
  const reader = async () => {
    while (live() && next < total) {
      while (typeof document !== "undefined" && document.hidden) {
        await new Promise((resolve) => setTimeout(resolve, 1000));
        if (!live()) return;
      }
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
        const timing = PaperFrames.edge ? PaperFrames.edge(bars, { live: PaperFrames.liveBar(bars, crypto) }) : null;
        scored.push({
          symbol,
          seen,
          matched,
          rate: seen ? Math.round((matched / seen) * 100) : 0,
          setup: alert.kind === "dots" && alert.side === "buy" ? "buy" : "",
          edge: timing && timing.ready ? timing.call : "",
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
  const paper = await getJSON("/api/paper");
  state.paper = paper;
  await refreshSymbols();
  fillForm(paper.params);
  await loadMarket(paper.symbol);
  render(paper);
  await loadSignals();
  rememberSymbol(paper.symbol);
  deskLine("Desk is working.");
  if (paper.paper === true) {
    state.auto = true;
    render(paper);
    $("status").textContent = "Auto is on. Refreshing the market quote.";
    autoTick();
    return;
  }
  $("status").textContent = "Market quote ready.";
}

async function loadSignals() {
  const report = await getJSON("/api/checks");
  paintSignals(withHooks(report.items));
  requestAnimationFrame(() => paintSignals(withHooks(report.items)));
  try {
    await postJSON("/api/learn/watch", { symbols: readPins().concat([state.paper && state.paper.symbol].filter(Boolean)) });
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
    renderPins();
    renderToday();
    renderScan();
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
  drawCandles();
  drawTmo();
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
  if (paper.fill && paper.fill.price > 0 && paper.symbol) state.lastFill = { symbol: paper.symbol, text: fillText(paper) };
  const fillNode = $("last-fill");
  if (fillNode && state.lastFill && state.lastFill.symbol === paper.symbol && fillNode.textContent !== "Last fill: " + state.lastFill.text) {
    fillNode.textContent = "Last fill: " + state.lastFill.text;
    fillNode.classList.add("on");
  }
  fillTrades($("journal-body"), paper.trades || []);
  renderResult(paper);
  renderEdge();
  renderBook(paper);
  renderStrategies();
  renderPins();
  renderToday();
  renderScan();
  renderReadings();
  renderFrames();
  drawCandles();
  drawTmo();
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
  const stamp = (signal ? "go" : "wait") + "|" + current + "|" + (rows || []).map((row) => row.symbol + ":" + row.rate + ":" + (row.setup || "") + ":" + (row.edge || "") + ":" + row.matched + ":" + row.seen).join(",");
  if (sameStamp(bar, stamp)) return;
  bar.replaceChildren();
  (rows || []).forEach((row) => {
    if (!row || !row.symbol) return;
    const button = document.createElement("button");
    button.type = "button";
    const ready = row.setup === "buy" ? " buy" : "";
    const timing = row.edge ? " · " + row.edge : "";
    button.textContent = pinLabel(row.symbol) + " " + row.rate + "%" + ready + timing;
    button.title = row.matched + " of " + row.seen + " closed bars." + (row.edge ? " Edge " + row.edge + "." : "") + " Not a forecast.";
    if (signal) button.classList.add("go");
    if (row.symbol === current) {
      button.classList.add("on");
      button.setAttribute("aria-pressed", "true");
    }
    button.addEventListener("click", () => chooseSymbol(row.symbol));
    bar.appendChild(button);
  });
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
  node.textContent = price ? paper.symbol + " · " + price + call + " · tap a candle" : "Click a ticker or a candle.";
}

function showRead(text) {
  state.chartRead = text;
  if (state.paper) paintChartRead(state.paper);
  $("status").textContent = text;
}

function showChart() {
  const narrow = window.matchMedia("(max-width: 980px)").matches;
  if (narrow) {
    document.body.dataset.tab = "chart";
    document.querySelectorAll(".tabbar [data-tab]").forEach((button) => {
      button.classList.toggle("on", button.dataset.tab === "chart");
    });
  }
  requestAnimationFrame(() => {
    if (state.market && state.paper) {
      drawCandles();
      drawTmo();
    }
  });
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
  $("quote-asof").textContent = asOf
    ? "Market quote · " + new Date(asOf * 1000).toLocaleString()
    : "Market quote · " + (last.date || "");
  quote.className = "quote " + (change > 0 ? "up" : change < 0 ? "down" : "flat");
  const tone = currentMood(bars);
  const badge = $("quote-mood");
  if (badge) {
    badge.textContent = tone && tone.score != null ? tone.label + " " + tone.score : "—";
    badge.className = "mood " + (tone && tone.label ? tone.label.toLowerCase() : "");
  }
  drawMoodClock(tone && tone.score, tone && tone.label);
  if (paper.symbol && tone && tone.score != null) state.moods[paper.symbol] = tone;
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
  PaperFrames.draw($("frame-preview"), previewRow ? previewRow.bars : [], state.previewZoom);
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
  const tone = bars.length ? PaperFrames.mood(bars) : null;
  const moodLine = tone && tone.score != null ? " " + tone.label + " " + tone.score + "." : "";
  win.document.open();
  const quoteLine = bars.length ? "Market quote." : "Loading the market quote.";
  win.document.write("<!DOCTYPE html><title>" + heading + "</title><body style=\"margin:0;background:#101318;color:#e7edf3;font:16px Segoe UI,sans-serif\"><p id=\"note\" style=\"padding:12px 16px 0\">" + heading + " is " + (row ? row.bias : "flat") + ". " + quoteLine + moodLine + "</p><p style=\"padding:8px 16px\"><button id=\"in\" type=\"button\">Zoom in</button> <button id=\"out\" type=\"button\">Zoom out</button> <span id=\"label\"></span></p><canvas id=\"c\" style=\"width:100%;height:72vh\"></canvas></body>");
  win.document.close();
  let zoomStop = PaperFrames.DEFAULT_ZOOM;
  const paint = () => {
    const canvas = win.document.getElementById("c");
    if (canvas) PaperFrames.draw(canvas, bars, zoomStop);
    const view = PaperFrames.zoomBars(bars, zoomStop);
    const label = win.document.getElementById("label");
    const inn = win.document.getElementById("in");
    const out = win.document.getElementById("out");
    if (label) label.textContent = view.size + " bars";
    if (inn) inn.disabled = view.atMin;
    if (out) out.disabled = view.atMax;
  };
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

function openAllFrames() {
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
    openedWindows.forEach(({ id, win }) => {
      paintPhoneFrame(win, id);
    });
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
    if (note) note.textContent = state.paper.symbol + " · " + PaperFrames.frameById(frameId).label + ". Market quote · " + bars.length + " bars.";
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
  const body = [note.lead].concat(note.lines).map((line) => "<p>" + line.replace(/[&<>]/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;" }[ch])) + "</p>").join("");
  win.document.open();
  win.document.write("<!DOCTYPE html><title>" + note.title + "</title><body style=\"margin:0;background:#101318;color:#e7edf3;font:18px/1.5 Segoe UI,sans-serif\"><article style=\"max-width:640px;padding:28px\"><p style=\"letter-spacing:.08em;text-transform:uppercase;color:#8fd0c4;font-size:12px\">Brief</p><h1 style=\"font-size:28px\">" + note.title + "</h1>" + body + "<p style=\"color:#93a0ad\">Market quotes are not a forecast. Paper orders stay on this device.</p></article></body>");
  win.document.close();
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
  ["dots", "5+ squeeze dots"],
  ["above", "Squeeze momentum above 0"],
  ["rising", "Squeeze momentum rising"],
  ["tmo", "True momentum above signal"],
  ["band", "Under the upper Bollinger band"],
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

function currentEdge(bars) {
  if (!PaperFrames.edge) return null;
  const last = bars.length ? bars[bars.length - 1] : null;
  const live = PaperFrames.liveBar(bars, chartCrypto());
  const key = bars.length + ":" + live + ":" + (last ? last.date + ":" + last.open + ":" + last.close : "");
  if (state.edgeKey === key && state.edge) return state.edge;
  state.edge = PaperFrames.edge(bars, { live });
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
  if (result.call === "BUY") return "Buy setup on the " + result.signalDate + " close. A paper buy fills at the next open. Starting stop " + stop + risk + "." + size + today;
  if (result.call === "SELL") return "Sell on the " + result.signalDate + " close: " + (result.reason || "exit") + ". A paper sell fills at the next open." + today;
  if (result.call === "HOLD") return "In since " + formatPrice(result.entry) + " · " + signedPercent(result.openPct) + " · trailing stop " + stop + risk + "." + today;
  return "No buy yet. Edge waits for all five checks to turn on together on a closed bar." + today;
}


function denverStamp(seconds) {
  if (!(seconds > 0)) return "—";
  return new Date(seconds * 1000).toLocaleString("en-US", { timeZone: "America/Denver", month: "short", day: "numeric", hour: "numeric", minute: "2-digit", second: "2-digit" });
}

function phoneBook() {
  const saved = readBook();
  const live = state.book || {};
  const out = { positions: [], fills: [], markets: 0, total_pnl: 0 };
  new Set(Object.keys(saved).concat(Object.keys(live))).forEach((symbol) => {
    const session = live[symbol];
    const snap = session ? PaperEngine.snapshot(session) : saved[symbol];
    if (!snap || !(snap.shares > 0 || (snap.fills && snap.fills.length))) return;
    out.markets += 1;
    const last = session && session.bars.length ? session.bars[session.bars.length - 1].close : snap.last;
    const start = Number(snap.params && snap.params.cash) || 0;
    const equity = session ? PaperEngine.view(session).equity : snap.cash + (snap.shares > 0 && last ? snap.shares * last : 0);
    out.total_pnl += equity - start;
    if (snap.shares > 0) {
      out.positions.push({ symbol, shares: snap.shares, entry: snap.entryPrice, last, pnl: last && snap.entryPrice ? snap.shares * (last - snap.entryPrice) : null, current: !!session, opened: snap.entryDate, source: "fill" });
    }
    (snap.fills || []).forEach((item) => out.fills.push(item));
  });
  out.fills.sort((a, b) => (b.at || 0) - (a.at || 0));
  out.fills = out.fills.slice(0, 300);
  return out;
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
  if (!positions || !fills) return;
  const book = state.offline ? phoneBook() : (paper && paper.book) || null;
  if (!book) return;
  const stamp = JSON.stringify([book.positions, book.fills.length, book.fills[0] || null, book.total_pnl, state.offline]);
  if (sameStamp(positions, stamp)) return;
  positions.replaceChildren();
  fills.replaceChildren();
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
  book.fills.forEach((item) => {
    const row = document.createElement("tr");
    const tone = item.side === "sell" ? (item.pnl > 0 ? "up" : item.pnl < 0 ? "down" : "") : "";
    addCells(row, [
      denverStamp(item.at),
      item.symbol,
      { text: item.side === "buy" ? "Buy" : "Sell", cls: item.side === "buy" ? "up" : "down" },
      Number(item.qty).toFixed(4),
      formatPrice(item.price),
      item.reason || "trade",
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
      ? "Across " + book.markets + " market" + (book.markets === 1 ? "" : "s") + ": " + moneyText(book.total_pnl) + " paper profit or loss so far. Each market has its own paper account." + (state.offline ? " Saved in this browser only." : " Saved on this computer.")
      : "Nothing traded yet.";
  }
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
  const blocked = plan.checks.some((check) => check.block && !check.ok);
  $("cm-yes").disabled = blocked;
  $("cm-warn").textContent = blocked
    ? "This cannot go ahead until the STOP line is fixed. Nothing was traded."
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
  add("Paper only", state.paper && state.paper.paper === true, "No broker. Nothing real is bought or sold", true);
  const safe = state.safe;
  add("Safe check", !safe || safe.safe, safe ? (safe.safe ? "Every safety check passed" : "A safety check failed. Press Safe check to see which") : "Not run yet", !!(safe && !safe.safe));
  if (side === "buy") {
    const tripped = breakerState().tripped;
    add("Circuit breaker", !tripped, tripped ? "Tripped. Buys stop until tomorrow" : "Armed, buys allowed", true);
    const held = internalsGate(symbol);
    const read = state.int && state.int.result;
    add("Internals", !held, held || (read && read.ready ? read.label + " (" + read.score + ")" : "Not read yet"), false);
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
  rows.push(["Started by", source === "auto" ? "AUTO SIGNAL · " + (extra.row ? extra.row.name : "strategy") + " on the closed bar " + (extra.closed || "") + ", seen " + new Date().toLocaleTimeString("en-US", { timeZone: "America/Denver", timeZoneName: "short" }) : source === "strategy" ? "Armed from the opening-bar panel" : "You pressed it"]);
  return {
    side,
    title: (source === "auto" ? "Auto signal: " : "Confirm: ") + (extra && extra.all ? "SELL ALL" : side.toUpperCase()) + " " + symbol,
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

function loadStrat() {
  const base = { pick: "edge", on: false, acted: {}, note: "" };
  try {
    const raw = JSON.parse(localStorage.getItem(STRAT_KEY) || "{}");
    return { ...base, pick: ["edge", "squeeze", "donchian", "rsi2"].includes(raw.pick) ? raw.pick : "edge", on: raw.on === true, acted: raw.acted && typeof raw.acted === "object" ? raw.acted : {} };
  } catch (error) {
    return base;
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

state.strat = loadStrat();

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
    pill.className = "call-pill " + row.call.toLowerCase();
    pill.textContent = row.call;
    addCells(tr, [
      row.name + " · " + row.who,
      "",
      row.ready ? String(row.trades) : "—",
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
  renderAutoNote();
  renderStratDetail(board);
}

function pickStrategy(id) {
  if (!["edge", "squeeze", "donchian", "rsi2"].includes(id)) return;
  state.strat.pick = id;
  state.strat.note = "";
  saveStrat();
  state.boardKey = "";
  state.sweep = { busy: false, id: "", rows: [], at: 0 };
  renderStrategies();
  const row = state.board && state.board.rows.find((item) => item.id === id);
  $("status").textContent = "Testing " + (row ? row.name : id) + " on " + ((state.paper && state.paper.symbol) || "this market") + ". Auto follows it only if strategy trading is on.";
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
    await Promise.all([worker(), worker(), worker(), worker()]);
  } finally {
    fresh.busy = false;
    fresh.at = Date.now();
    $("strat-sweep").disabled = false;
  }
  if (state.sweep === fresh) renderSweep();
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
    ? "On. While Auto runs, a fresh " + name + " buy or sell on a closed bar opens the second warning. Nothing trades until you press Yes. It follows the scanner rules and stops at the circuit breaker. " + internalsLine()
    : "Off. Auto only refreshes the quote until you turn this on. Every trade is paper, follows the scanner rules, and stops at the circuit breaker.");
}

async function autoTrade() {
  if (!state.strat.on || !state.paper || state.paper.paper !== true) return "";
  if (breakerState().tripped) return "";
  const bars = seenBars();
  const board = currentBoard(bars);
  const row = board.rows.find((item) => item.id === state.strat.pick);
  if (!row || !row.ready) return "";
  const held = state.paper.shares > 0;
  const want = row.call === "BUY" && !held ? "buy" : row.call === "SELL" && held ? "sell" : "";
  if (!want) return "";
  const key = state.paper.symbol + "|" + row.id + "|" + want + "|" + closedDate(bars);
  if (state.strat.acted[key]) return "";
  if (want === "buy") {
    const held = internalsGate(state.paper.symbol);
    if (held) {
      state.strat.note = row.name + " wanted a buy. Skipped: " + held;
      renderAutoNote();
      return state.strat.note;
    }
  }
  if (confirmWait) return "Waiting for your answer on the open warning.";
  state.strat.acted[key] = Date.now();
  saveStrat();
  state.strat.note = row.name + " signal: paper " + want + ". Waiting for your Yes.";
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
  add("Live locked", locked, "No broker or bank link. Every order is paper");
  add("Paper only", !state.paper || state.paper.paper === true, "Paper mode is on");
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
  add("Learning", true, "Runs only while this page is open", "health");
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
    const bad = report.items.filter((item) => item.kind === "safety" && !item.ok);
    const warn = report.items.filter((item) => item.kind !== "safety" && !item.ok);
    button.className = "safe-btn " + (bad.length ? "bad" : warn.length ? "warn" : "ok");
    button.textContent = bad.length ? "Not safe" : warn.length ? "Safe · " + warn.length + " to check" : "Safe";
    const stamp = new Date().toLocaleTimeString("en-US", { timeZone: "America/Denver" });
    $("safe-note").textContent = bad.length
      ? "Fix first: " + bad.map((item) => item.name).join(", ") + ". Auto is stopped."
      : "All " + report.items.filter((item) => item.kind === "safety").length + " safety checks passed at " + stamp + " Denver." + (warn.length ? " Check: " + warn.map((item) => item.name).join(", ") + "." : "");
    const list = $("safe-list");
    list.replaceChildren();
    report.items.forEach((item) => {
      const row = document.createElement("li");
      row.textContent = item.name + " · " + item.detail;
      if (!item.ok) row.className = item.kind === "safety" ? "bad" : "warn";
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


const SQ_LISTS = {
  starter: ["BTC-USD", "ETH-USD", "XRP-USD", "ES", "NQ", "YM", "RTY", "GC", "CL", "ZN", "SPY", "QQQ", "IWM", "DIA", "XLK", "XLI", "XLF", "XLE", "XLV", "XLY", "XLP", "XLU", "XLB", "SMH", "NVDA", "MSFT", "AAPL", "AMZN", "GOOGL", "META", "TSLA", "AVGO", "AMD", "JPM", "HOOD", "DASH", "CRCL", "GE", "CAT", "XOM", "LLY", "WMT", "COST"],
  sectors: ["SPY", "QQQ", "XLK", "XLI", "XLF", "XLE", "XLV", "XLY", "XLP", "XLU", "XLB", "SMH", "NVDA", "MSFT", "AAPL", "AVGO", "AMD", "AMZN", "TSLA", "DASH", "JPM", "HOOD", "V", "MA", "CRCL", "GE", "CAT", "HON", "BA", "LMT", "XOM", "CVX", "LLY", "WMT", "COST"],
  crypto: ["BTC-USD", "ETH-USD", "XRP-USD", "SOL-USD", "XLM-USD", "ADA-USD", "DOGE-USD", "LTC-USD", "LINK-USD", "AVAX-USD", "DOT-USD", "BCH-USD"],
  futures: ["ES", "NQ", "YM", "RTY", "CL", "GC", "SI", "NG", "ZN", "ZB", "6E", "ZC", "ZS", "ZW"],
};

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

function sqSymbols() {
  const kind = $("sq-list").value;
  let list;
  if (kind === "custom") {
    list = ($("sq-custom").value || "").toUpperCase().split(/[\s,;]+/).filter((item) => /^[A-Z0-9.^=-]{1,12}$/.test(item));
  } else {
    list = SQ_LISTS[kind] || SQ_LISTS.starter;
  }
  const seen = new Set();
  const out = [];
  list.forEach((symbol) => {
    if (!seen.has(symbol)) {
      seen.add(symbol);
      out.push(symbol);
    }
  });
  return out.slice(0, 80);
}

async function sqDaily(symbol) {
  if (state.offline) {
    const row = state.catalog.find((item) => item.symbol === symbol);
    return PaperLive.daily(symbol, row ? row.category : "");
  }
  return (await getJSON("/api/bars?symbol=" + encodeURIComponent(symbol) + "&frame=1D")).bars;
}

async function scanSqueeze() {
  const sq = state.sq;
  if (sq.busy) return;
  const symbols = sqSymbols();
  if (!symbols.length) {
    $("sq-note").textContent = "Type at least one symbol for your own list.";
    return;
  }
  sq.busy = true;
  sq.stop = false;
  const reads = {};
  const skipped = [];
  let next = 0;
  let done = 0;
  const known = state.catalog && state.catalog.length ? new Set(state.catalog.map((item) => item.symbol)) : null;
  const worker = async () => {
    while (!sq.stop && next < symbols.length) {
      const symbol = symbols[next];
      next += 1;
      if (known && !known.has(symbol)) {
        skipped.push(symbol + " (not in this bot's market list)");
        done += 1;
        continue;
      }
      try {
        const bars = await sqDaily(symbol);
        const read = PaperFrames.squeezeRead(bars, { live: PaperFrames.liveBar(bars, phoneCrypto(symbol, state.catalog.find((item) => item.symbol === symbol))) });
        if (read.ready) reads[symbol] = read;
        else skipped.push(symbol + " (" + read.reason + ")");
      } catch (error) {
        skipped.push(symbol + " (" + error.message + ")");
      }
      done += 1;
      $("sq-note").textContent = "Reading daily bars: " + done + " of " + symbols.length + "…";
    }
  };
  try {
    await Promise.all([worker(), worker(), worker(), worker()]);
  } finally {
    sq.busy = false;
  }
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
    const scored = PaperFrames.squeezeScore(read, flags);
    rows.push({ symbol, read, ...scored });
  });
  rows.sort((a, b) => b.score - a.score || b.read.dots - a.read.dots);
  sq.rows = rows;
  sq.skipped = skipped;
  sq.scanned = Object.keys(reads).length;
  sq.at = Date.now();
  sq.listed = symbols.length;
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
    open.addEventListener("click", () => chooseSymbol(row.symbol));
    addCells(tr, [
      String(index + 1),
      row.symbol,
      read.state === "ON" ? "On" : "Fired " + (read.firedAgo === 0 ? "now" : read.firedAgo + " bar" + (read.firedAgo === 1 ? "" : "s") + " ago"),
      String(read.dots),
      read.stack === "positive" ? "Stacked up" : read.stack === "negative" ? "Stacked down" : "Mixed",
      (read.direction === "bull" ? "Above zero" : "Below zero") + (read.clean ? ", clean" : "") + (read.rising ? ", building" : ""),
      read.weekly ? "Yes" : "No",
      row.triple ? "Yes" : "—",
      { text: String(row.score), cls: row.score >= 70 ? "up" : "" },
      "",
    ]);
    tr.lastChild.appendChild(open);
    body.appendChild(tr);
  });
  if (!shown.length) {
    const tr = document.createElement("tr");
    addCells(tr, ["", "No squeezes on this side right now. A quiet list is a normal answer.", "", "", "", "", "", "", "", ""]);
    body.appendChild(tr);
  }
  const when = new Date(sq.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" });
  $("sq-note").textContent = "Read " + sq.scanned + " of " + sq.listed + " markets at " + when + " Denver. " + sq.rows.length + " in a squeeze or just fired. Closed daily bars only. " + internalsLine();
  const top = shown[0];
  $("sq-why").textContent = top ? "Top score " + top.score + " for " + top.symbol + ": " + top.parts.map((item) => item[0] + " +" + item[1]).join(", ") + "." : "";
  $("sq-skip").textContent = sq.skipped.length ? "Not read: " + sq.skipped.join("; ") + "." : "";
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

state.int = { gate: loadIntGate(), result: null, at: 0, busy: false, error: "" };

async function internalBars(symbol) {
  if (state.offline && INT_GAUGES.includes(symbol)) return PaperLive.gauge(symbol);
  return sqDaily(symbol);
}

async function loadInternals(force) {
  const box = state.int;
  if (box.busy || (!force && box.at && Date.now() - box.at < 600000)) return;
  box.busy = true;
  const symbols = PaperFrames.INTERNAL_SYMBOLS;
  const data = {};
  let next = 0;
  const worker = async () => {
    while (next < symbols.length) {
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
    await Promise.all([worker(), worker(), worker(), worker()]);
    box.result = PaperFrames.internals(data);
    box.at = Date.now();
    box.error = "";
  } catch (error) {
    box.error = error.message;
  } finally {
    box.busy = false;
  }
  renderInternals();
  runSafe(false);
}

function internalsLine() {
  const result = state.int.result;
  if (!result) return "Internals not read yet.";
  if (!result.ready) return "Internals unknown: only " + result.coverage + " of 100 points could be read.";
  return "Internals " + result.label + " " + result.score + " as of the " + result.asOf + " close.";
}

function renderInternals() {
  const pill = $("internals-pill");
  if (!pill) return;
  const result = state.int.result;
  pill.className = "internals-pill " + (result && result.ready ? result.label.toLowerCase() : "");
  pill.textContent = result ? (result.ready ? "Internals · " + result.label + " " + result.score : "Internals · unknown") : state.int.error ? "Internals · could not read" : "Internals · reading…";
  const body = $("int-body");
  if (!body) return;
  body.replaceChildren();
  $("int-gate").checked = state.int.gate;
  if (!result) {
    $("int-head").textContent = state.int.error || "Press Refresh internals.";
    return;
  }
  result.parts.forEach((part) => {
    const row = document.createElement("tr");
    addCells(row, [part.name, { text: part.says === "good" ? "Good" : part.says === "bad" ? "Bad" : "Neutral", cls: part.says === "good" ? "up" : part.says === "bad" ? "down" : "" }, part.points + " of " + part.weight, part.text]);
    body.appendChild(row);
  });
  const when = new Date(state.int.at).toLocaleTimeString("en-US", { timeZone: "America/Denver" });
  $("int-head").textContent = internalsLine() + " Read at " + when + " Denver." + (result.ready && result.label === "Defensive" ? " Auto skips new stock, ETF, and futures buys while this holds." : "");
  $("int-miss").textContent = result.missing.length ? "Not read: " + result.missing.join(", ") + ". The score uses only what was read." : "";
  renderAutoNote();
}

function internalsGate(symbol) {
  const result = state.int.result;
  if (!state.int.gate || !result || !result.ready || result.label !== "Defensive") return "";
  const row = state.catalog.find((item) => item.symbol === symbol);
  if (phoneCrypto(symbol, row)) return "";
  return "Internals are Defensive (" + result.score + "), so new buys are held back.";
}

function renderEdge() {
  const box = $("edge");
  if (!box) return;
  const bars = seenBars();
  const result = currentEdge(bars);
  if (!result) return;
  const call = result.ready ? result.call : "WAIT";
  const stamp = [call, result.score, result.stop, result.openPct, result.record.trades, result.record.total, bars.length].join("|");
  if (sameStamp(box, stamp)) return;
  box.className = "edge " + call.toLowerCase();
  $("edge-call").textContent = call;
  $("edge-score").textContent = result.score == null ? "—" : result.score + " of " + (result.checks || 4);
  const checks = $("edge-checks");
  checks.replaceChildren();
  EDGE_CHECKS.forEach(([id, name]) => {
    const item = document.createElement("li");
    item.textContent = name;
    if (result.parts && result.parts[id]) item.className = "on";
    checks.appendChild(item);
  });
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
  if (canvas.clientHeight < 40) canvas.style.minHeight = "240px";
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
  const priceBottom = Math.min(height - 36, Math.max(72, Math.round(height * 0.72)));
  let low = Math.min(...bars.map((bar) => bar.low));
  let high = Math.max(...bars.map((bar) => bar.high));
  [layers.averages ? highLine : [], layers.averages ? midLine : [], layers.averages ? lowLine : [], fast, slow].forEach((series) => {
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
  const y = (price) => 16 + ((high - price) / (high - low)) * (priceBottom - 28);
  const slot = (width - pad - rightGap) / bars.length;
  const plotRight = width - rightGap;
  state.chartMap = { bars, pad, slot, symbol: state.paper && state.paper.symbol };
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
  bars.forEach((bar, index) => {
    const x = pad + index * slot + slot / 2;
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
  drawPro(ctx, history, dropped, bars.length, y, pad, slot, plotRight, priceBottom);
  drawLine(ctx, fast, y, pad, slot, "#7eb6ff", 1);
  drawLine(ctx, slow, y, pad, slot, "#e2b15a", 1);
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
    edgeResult.marks.forEach((mark) => {
      const index = mark.index - dropped;
      if (index < 0 || index >= bars.length) return;
      const x = pad + index * slot + slot / 2;
      ctx.fillStyle = mark.side === "buy" ? "#3ddc97" : "#ff6b7a";
      ctx.strokeStyle = "#07090b";
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.arc(x, y(mark.price), Math.max(3, Math.min(4.5, slot * 0.6)), 0, Math.PI * 2);
      ctx.fill();
      ctx.stroke();
    });
    ctx.restore();
  }
  drawStudy(ctx, bars, y, pad, slot);
  const histoTop = priceBottom + 8;
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
    const room = Math.max(4, (histoBottom - histoTop) / 2 - 6);
    const barHeight = (Math.abs(value) / scale) * room;
    ctx.fillStyle = tone;
    ctx.fillRect(x - Math.max(1, slot * 0.28), value >= 0 ? zero - barHeight : zero, Math.max(2, slot * 0.56), Math.max(1, barHeight));
    ctx.fillStyle = PaperFrames.squeezeTone(squeeze[index] || 0);
    ctx.beginPath();
    ctx.arc(x, zero, Math.max(2, Math.min(3.5, slot * 0.18)), 0, Math.PI * 2);
    ctx.fill();
  });
  const last = bars.length - 1;
  const lastBar = bars[last];
  const lastMomentum = momentum[last];
  const priorMomentum = last === 0 ? allMomentum[dropped - 1] : momentum[last - 1];
  const lastRising = lastMomentum != null && (priorMomentum == null ? lastMomentum >= 0 : lastMomentum > priorMomentum);
  if (width >= 640 && layers.key) {
    const key = [
      [lastBar.close >= lastBar.open ? "Candle green" : "Candle red", lastBar.close >= lastBar.open ? "#3ddc97" : "#ff6b7a"],
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
  const byDate = new Map(bars.map((bar, index) => [bar.date, index]));
  (state.paper.markers || []).forEach((marker) => {
    const index = byDate.get(marker.date);
    if (index == null) return;
    const bar = bars[index];
    const x = pad + index * slot + slot / 2;
    const up = marker.side === "buy";
    ctx.fillStyle = up ? "#3ddc97" : "#ff6b7a";
    const point = Math.max(24, Math.min(priceBottom - 12, up ? y(bar.low) + 16 : y(bar.high) - 16));
    drawArrow(ctx, x, point, up);
  });
  (layers.lesson ? lessonMarks(fitted, history) : []).forEach((mark) => {
    const index = mark.index - dropped;
    if (index < 0 || index >= bars.length) return;
    const bar = bars[index];
    const x = pad + index * slot + slot / 2;
    const up = mark.side === "buy";
    ctx.fillStyle = up ? "#3ddc97" : "#ff6b7a";
    const point = Math.max(24, Math.min(priceBottom - 12, up ? y(bar.low) + 36 : y(bar.high) - 36));
    drawArrow(ctx, x, point, up);
  });
  const lastTone = lastBar.close >= lastBar.open ? "#3ddc97" : "#ff6b7a";
  drawPriceTag(ctx, formatPrice(lastBar.close), plotRight, width, y(lastBar.close), priceBottom, lastTone, pad + last * slot + slot / 2);
  drawPriceLine(ctx, bars, pad, slot, y, plotRight, priceBottom, height, width);
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
    drawLine(ctx, desk.vwap.slice(dropped, dropped + count), y, pad, slot, "#3fd0e0", 1.5);
  }
  ctx.setLineDash([]);
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
  ["edge", "Edge stop", "edgestop", true],
  ["ema200", "200 EMA", "ema200", true],
  ["profile", "Volume + POC", "poc", true],
  ["vwap", "VWAP", "vwap", false],
  ["bands", "Bands", "band", false],
  ["averages", "High · Close · Low", "close", false],
  ["fastslow", "Fast · Slow", "fast", false],
  ["pivots", "Pivots", "high", false],
  ["lesson", "Lesson arrows", "low", false],
  ["key", "Chart notes", "key", false],
];
const LAYERS_KEY = "paperTraderLayers";

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

function drawArrow(ctx, x, y, up) {
  const size = 10;
  ctx.beginPath();
  if (up) {
    ctx.moveTo(x, y - size);
    ctx.lineTo(x - size * 0.82, y + size * 0.62);
    ctx.lineTo(x + size * 0.82, y + size * 0.62);
  } else {
    ctx.moveTo(x, y + size);
    ctx.lineTo(x - size * 0.82, y - size * 0.62);
    ctx.lineTo(x + size * 0.82, y - size * 0.62);
  }
  ctx.closePath();
  ctx.fill();
  ctx.lineWidth = 1.5;
  ctx.strokeStyle = "#121417";
  ctx.stroke();
}

function drawTmo() {
  const canvas = $("rsi-chart");
  if (!state.market || !canvas) return;
  const history = sliceToCursor(state.market.bars);
  if (!history.length) return;
  const study = PaperFrames.trueMomentum(history, 14, 5, 3);
  const view = PaperFrames.zoomBars(history, state.zoom);
  const count = view.size;
  const dropped = view.start;
  const main = study.main.slice(dropped);
  const signal = study.signal.slice(dropped);
  const up = study.up.slice(dropped);
  const down = study.down.slice(dropped);
  const ctx = prepare(canvas);
  const width = canvas.clientWidth;
  const height = canvas.clientHeight;
  const pad = 28;
  const rightGap = Math.max(120, Math.round(width * 0.16));
  const limit = study.length;
  const y = (value) => 10 + ((limit - value) / (limit * 2)) * (height - 18);
  const slot = (width - pad - rightGap) / Math.max(1, main.length);
  const plotRight = width - rightGap;
  ctx.fillStyle = "rgba(255, 107, 122, 0.16)";
  ctx.fillRect(pad, y(limit), plotRight - pad, Math.max(1, y(study.ob) - y(limit)));
  ctx.fillStyle = "rgba(61, 220, 151, 0.16)";
  ctx.fillRect(pad, y(study.os), plotRight - pad, Math.max(1, y(-limit) - y(study.os)));
  ctx.font = "11px Segoe UI, sans-serif";
  ctx.setLineDash([3, 3]);
  ctx.strokeStyle = "#8b98a5";
  [study.ob, 0, study.os].forEach((level) => {
    ctx.beginPath();
    ctx.moveTo(pad, y(level));
    ctx.lineTo(plotRight, y(level));
    ctx.stroke();
    ctx.fillStyle = "#9aa6b2";
    ctx.fillText(String(level), 4, y(level) + 4);
  });
  ctx.setLineDash([]);
  for (let index = 1; index < main.length; index += 1) {
    if (main[index] == null || signal[index] == null) continue;
    const left = pad + (index - 1) * slot + slot / 2;
    const right = pad + index * slot + slot / 2;
    const top = Math.min(y(main[index]), y(signal[index]));
    const bottom = Math.max(y(main[index]), y(signal[index]));
    ctx.fillStyle = main[index] >= signal[index] ? "rgba(18, 92, 58, 0.55)" : "rgba(122, 28, 40, 0.55)";
    ctx.fillRect(left, top, Math.max(1, right - left), Math.max(1, bottom - top));
  }
  strokeOscillator(ctx, main, main, signal, y, pad, slot);
  strokeOscillator(ctx, signal, main, signal, y, pad, slot);
  up.forEach((mark, index) => {
    if (!mark || main[index] == null || main[index] > 0) return;
    ctx.fillStyle = "#67e8f9";
    drawArrow(ctx, pad + index * slot + slot / 2, y(main[index]) + 11, true);
  });
  down.forEach((mark, index) => {
    if (!mark || main[index] == null || main[index] < 0) return;
    ctx.fillStyle = "#ff6b7a";
    drawArrow(ctx, pad + index * slot + slot / 2, y(main[index]) - 11, false);
  });
  ctx.fillStyle = "#d7e2ea";
  ctx.fillText("Momentum", pad, 12);
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
  $("status").textContent = "";
  try {
    await action();
  } catch (error) {
    $("status").textContent = error.message;
    if (state.paper) $("symbol").dataset.value = state.paper.symbol;
  } finally {
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
  if (!state.voice || !item || (item.side !== "buy" && item.side !== "sell") || !item.detail) return;
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
    sessionStorage.setItem("paperTraderHeard", JSON.stringify(heard));
  } catch (error) {
    /* The warning still shows on the desk. */
  }
  $("status").textContent = item.detail;
  if (!window.speechSynthesis) return;
  const message = new SpeechSynthesisUtterance(item.detail);
  message.lang = "en-US";
  window.speechSynthesis.speak(message);
}

async function warnSqueeze() {
  if (!state.voice) return;
  const symbols = readPins();
  const current = state.paper && state.paper.symbol;
  if (current && symbols.indexOf(current) < 0) symbols.push(current);
  if (!symbols.length) return;
  try {
    if (state.offline) {
      for (let index = 0; index < symbols.length; index += 1) {
        const symbol = symbols[index];
        const row = state.catalog.find((item) => item.symbol === symbol);
        if (typeof PaperLive === "undefined" || !PaperFrames.squeezeAlert) continue;
        const bars = PaperFrames.closedBars(await PaperLive.daily(symbol, row ? row.category : ""), phoneCrypto(symbol, row));
        const alert = PaperFrames.squeezeAlert(bars);
        if (!alert.side) continue;
        const name = pinLabel(symbol);
        const detail = alert.kind === "steam"
          ? "Sell. " + name + ". The daily squeeze ran out of steam."
          : (alert.side === "buy" ? "Buy warning. " : "Sell warning. ") + name + ". Five squeeze dots on the daily.";
        speakWarning({ symbol, date: bars[alert.index] ? bars[alert.index].date : "", kind: alert.kind, side: alert.side, detail });
      }
      return;
    }
    const report = await getJSON("/api/voice?symbols=" + encodeURIComponent(symbols.join(",")));
    (report.items || []).forEach(speakWarning);
  } catch (error) {
    return;
  }
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
    $("status").textContent = traded || "Auto is on. Refreshing the market quote.";
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
    $("status").textContent = state.voice ? "Voice is on. Daily squeeze warnings." : "Voice is off.";
    if (state.voice) warnSqueeze();
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
}

function syncTicketFields() {
  const kind = $("kind").value;
  $("stop-field").hidden = !(kind === "stop" || kind === "stop_limit");
  $("limit-field").hidden = !(kind === "limit" || kind === "stop_limit");
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
syncTicketFields();
setSide("buy");

$("send").addEventListener("click", () => withBusy(async () => {
  if (!$("confirm").checked) {
    $("status").textContent = "Check Confirm and Send, then send the paper order.";
    return;
  }
  const ticket = {
    side: state.side,
    kind: $("kind").value,
    tif: $("tif").value,
    stop: Number($("stop-price").value) || 0,
    limit: Number($("limit-price").value) || 0,
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
$("sq-stop").addEventListener("click", () => {
  state.sq.stop = true;
  $("sq-note").textContent = "Stopping after the markets already being read.";
});
$("sq-side").addEventListener("change", renderSqueeze);
setInterval(() => runSafe(false), 60000);

$("strat-pick").addEventListener("change", () => pickStrategy($("strat-pick").value));
$("strat-auto-on").addEventListener("change", () => {
  state.strat.on = $("strat-auto-on").checked;
  state.strat.note = "";
  saveStrat();
  state.boardKey = "";
  renderStrategies();
  $("status").textContent = state.strat.on ? "Strategy trading is on. Turn Auto on and it follows " + state.strat.pick + " on closed bars." : "Strategy trading is off.";
});
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
  $("status").textContent = LIVE_NOTE + " To trade real money, use your broker's own app.";
});

$("paper").addEventListener("click", () => withBusy(async () => {
  const paper = await paperCall("/api/paper/mode", { mode: "paper" });
  render(paper);
  $("status").textContent = "Paper is on. Orders stay simulated on this computer.";
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
  drawCandles();
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

$("full").addEventListener("click", () => withBusy(async () => {
  if (state.offline) {
    $("backtest-summary").textContent = "On the phone, the quote stays current. The full history report is on the computer copy.";
    showDock("backtest");
    return;
  }
  const report = await post("/api/backtest", formParams());
  const factor = report.profit_factor == null ? "—" : report.profit_factor.toFixed(2);
  $("backtest-summary").textContent =
    "Return " + report.return_pct.toFixed(2) + "% · drawdown " +
    report.max_drawdown_pct.toFixed(2) + "% · trades " + report.trade_count +
    " · win rate " + report.win_rate_pct.toFixed(1) + "% · profit factor " + factor;
  fillTrades($("backtest-body"), report.trades || []);
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

function showDock(name) {
  if (name === "internals") loadInternals(false);
  if (name === "strategies" && state.paper && (!state.orb || state.orb.symbol !== state.paper.symbol) && !state.orbBusy) {
    state.orbBusy = true;
    loadOrb().finally(() => {
      state.orbBusy = false;
    });
  }
  document.querySelectorAll(".dock-tab").forEach((button) => {
    button.classList.toggle("on", button.dataset.dock === name);
  });
  document.querySelectorAll(".dock-page").forEach((page) => {
    page.classList.toggle("on", page.dataset.dockPage === name);
  });
  if (window.matchMedia("(max-width: 980px)").matches) {
    document.body.dataset.tab = "study";
    document.querySelectorAll(".tabbar [data-tab]").forEach((button) => {
      button.classList.toggle("on", button.dataset.tab === "study");
    });
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

document.querySelectorAll(".tabbar [data-tab]").forEach((button) => {
  button.addEventListener("click", () => {
    document.body.dataset.tab = button.dataset.tab;
    document.querySelectorAll(".tabbar [data-tab]").forEach((item) => item.classList.toggle("on", item === button));
    if (state.market && state.paper) {
      drawCandles();
      drawTmo();
    }
  });
});

const signalSum = $("signal-sum");
if (signalSum) signalSum.addEventListener("click", () => showDock("signals"));

document.querySelectorAll(".dock-tab").forEach((button) => {
  button.addEventListener("click", () => showDock(button.dataset.dock));
});

window.addEventListener("resize", () => {
  if (state.market && state.paper) {
    drawCandles();
    drawTmo();
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
bindZoom($("frame-preview"), "preview");

boot().then(() => {
  runSafe(false);
  loadInternals(false);
  setInterval(() => loadInternals(true), 600000);
  warnSqueeze();
  setInterval(warnSqueeze, 20000);
}).catch((error) => {
  showProblem(error && error.message ? error.message : "The desk did not start.");
});
