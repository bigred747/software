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

const VERSION = "1.9.31";

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
}

async function loadPhoneBars(symbol, params, fresh) {
  const row = state.catalog.find((item) => item.symbol === symbol);
  const bars = await PaperLive.daily(symbol, row ? row.category : "");
  if (fresh || !state.session || state.session.symbol !== symbol) {
    state.session = PaperEngine.sessionFromBars(symbol, bars, params || {});
  } else {
    PaperEngine.replaceBars(state.session, bars);
  }
  state.market = PaperEngine.market(state.session);
  stampQuote(bars);
  return PaperEngine.view(state.session);
}

async function paperCall(path, body) {
  if (!state.offline) return post(path, body);
  const params = body || {};
  if (path === "/api/paper/step" || path === "/api/paper/run") {
    return loadPhoneBars(state.session.symbol, state.session.params, false);
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
    return PaperEngine.trade(state.session);
  }
  if (path === "/api/paper/mode") return PaperEngine.view(state.session);
  if (path === "/api/paper/reset") {
    const symbol = params.symbol || selectedSymbol();
    return loadPhoneBars(symbol, { ...formParams(), ...params }, true);
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
  applyHook(next, "Edge", edge, edge ? "This page is in Microsoft Edge" : (state.offline ? "Open this file in the Edge app" : "This window is not Microsoft Edge"));
  if (!next.some((item) => item.name === "Learning")) {
    setSignal(next, "Learning", true, state.offline ? "This symbol, while the page is open" : "Starts when you open the desk, then keeps scoring after that window closes");
  }
  const tone = currentMood(bars);
  if (tone && tone.score != null) setSignal(next, "Fear and greed", true, tone.label + " " + tone.score);
  else if (!next.some((item) => item.name === "Fear and greed")) setSignal(next, "Fear and greed", false, "Waiting on the quote");
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
  note.hidden = !(state.offline && !inMicrosoftEdge());
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

const SCAN_KEY = "paperTraderScan";
const TYPE_KEY = "paperTraderType";
const phoneBarCache = new Map();
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
  let errors = 0;
  state.scanDone = false;
  state.leaders = [];
  state.picks = [];
  state.scanNote = "Scanned 0 of " + symbols.length + ". " + scanKindNote();
  state.pickNote = "";
  renderScan();
  renderToday();
  for (let index = 0; index < symbols.length; index += 1) {
    if (token !== phoneScanToken || !state.scanOn) return;
    while (typeof document !== "undefined" && document.hidden) {
      await new Promise((resolve) => setTimeout(resolve, 1000));
      if (token !== phoneScanToken || !state.scanOn) return;
    }
    const symbol = symbols[index];
    const row = state.catalog.find((item) => item.symbol === symbol);
    const open = (state.paper && state.paper.symbol) || "";
    try {
      const bars = symbol === open && phoneBarCache.has(symbol)
        ? phoneBarCache.get(symbol)
        : await PaperLive.daily(symbol, row ? row.category : "");
      if (symbol !== open) phoneBarCache.delete(symbol);
      const lesson = PaperFrames.lesson(bars);
      const alert = PaperFrames.squeezeAlert ? PaperFrames.squeezeAlert(bars) : { kind: "", side: "" };
      const seen = Number(lesson && lesson.seen) || 0;
      const matched = Number(lesson && lesson.matched) || 0;
      scored.push({
        symbol,
        seen,
        matched,
        rate: seen ? Math.round((matched / seen) * 100) : 0,
        setup: alert.kind === "dots" && alert.side === "buy" ? "buy" : "",
      });
    } catch (error) {
      errors += 1;
    }
    if (token !== phoneScanToken || !state.scanOn) return;
    const leaders = phoneLeaders(scored, false).slice(0, 10);
    const picks = bestPicks(scored);
    state.leaders = leaders;
    state.picks = picks;
    const done = index + 1 === symbols.length;
    const score = leaders.length ? "Highest closed-bar scores. Not a forecast." : "No market cleared the bar yet. Not a forecast.";
    const missed = missNote(errors);
    state.scanNote = "Scanned " + (index + 1) + " of " + symbols.length + ". " + scanKindNote() + " " + score + missed;
    state.pickNote = done
      ? (picks.length
        ? "Scan finished. Green signal on each name. Three to five, and no more. Not a forecast."
        : "No market cleared the bar today.")
      : "Ten names, then three to five. Green signals come on when the scan finishes.";
    state.scanDone = done;
    renderScan();
    renderToday();
    if (!done) await new Promise((resolve) => setTimeout(resolve, 150));
  }
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
  state.session = PaperEngine.sessionFromBars(found.symbol, bars, {});
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
  state.paper = paper;
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
  fillTrades($("journal-body"), paper.trades || []);
  renderResult(paper);
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
  const stamp = (signal ? "go" : "wait") + "|" + current + "|" + (rows || []).map((row) => row.symbol + ":" + row.rate + ":" + (row.setup || "") + ":" + row.matched + ":" + row.seen).join(",");
  if (sameStamp(bar, stamp)) return;
  bar.replaceChildren();
  (rows || []).forEach((row) => {
    if (!row || !row.symbol) return;
    const button = document.createElement("button");
    button.type = "button";
    const ready = row.setup === "buy" ? " buy" : "";
    button.textContent = pinLabel(row.symbol) + " " + row.rate + "%" + ready;
    button.title = row.matched + " of " + row.seen + " closed bars. Not a forecast.";
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
  node.textContent = price ? paper.symbol + " · " + price + " · click a candle" : "Click a ticker or a candle.";
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
    stopAuto();
    rememberSymbol(symbol);
    if (state.offline) {
      const row = state.catalog.find((item) => item.symbol === symbol);
      const bars = await PaperLive.daily(symbol, row ? row.category : "");
      state.session = PaperEngine.sessionFromBars(symbol, bars, formParams());
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
    const paper = await paperCall("/api/paper/reset", { ...formParams(), symbol });
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
    ["TMO", tmoText(bars)],
    ["Lesson", lessonText(bars)],
    ["Fear and greed", moodText(bars)],
  ];
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

function currentLesson(bars) {
  const remote = state.market && state.market.lesson;
  if (remote && remote.rule) return remote;
  const last = bars.length ? bars[bars.length - 1] : null;
  const key = bars.length + ":" + (last ? last.date + ":" + last.close : "");
  if (state.lessonKey === key && state.lesson) return state.lesson;
  state.lesson = PaperFrames.lesson(bars);
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
  const fitted = currentLesson(bars);
  const study = PaperFrames.trueMomentum(bars, 14, fitted.calc, fitted.smooth);
  const last = study.main.length - 1;
  if (study.main[last] == null || study.signal[last] == null) return "—";
  const side = study.main[last] > study.signal[last] ? "Main above signal" : "Main below signal";
  return side + " " + study.main[last].toFixed(1);
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
  const studyLines = PaperFrames.averages(history, fitted.average);
  paintZoom(history.length);
  const highLine = studyLines.high.slice(dropped);
  const midLine = studyLines.close.slice(dropped);
  const lowLine = studyLines.low.slice(dropped);
  const pivots = PaperFrames.trendPivots(history);
  const fast = visible(state.market.fast);
  const slow = visible(state.market.slow);
  const compressed = PaperFrames.compression(history, fitted.squeeze);
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
  [highLine, midLine, lowLine, fast, slow].forEach((series) => {
    series.forEach((value) => {
      if (value == null) return;
      if (value < low) low = value;
      if (value > high) high = value;
    });
  });
  if (pivots) {
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
  drawLine(ctx, fast, y, pad, slot, "#7eb6ff", 1);
  drawLine(ctx, slow, y, pad, slot, "#e2b15a", 1);
  drawLine(ctx, highLine, y, pad, slot, "#3ddc97", 2);
  drawLine(ctx, midLine, y, pad, slot, "#5b9dff", 2);
  drawLine(ctx, lowLine, y, pad, slot, "#ff6b7a", 2);
  if (pivots) {
    PaperFrames.paintPivots(ctx, {
      ...pivots,
      fromHigh: Math.max(0, pivots.fromHigh - dropped),
      fromLow: Math.max(0, pivots.fromLow - dropped),
    }, (index) => pad + index * slot + slot / 2, y, bars.length - 1);
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
  if (width >= 640) {
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
  lessonMarks(fitted, history).forEach((mark) => {
    const index = mark.index - dropped;
    if (index < 0 || index >= bars.length) return;
    const bar = bars[index];
    const x = pad + index * slot + slot / 2;
    const up = mark.side === "buy";
    ctx.fillStyle = up ? "#3ddc97" : "#ff6b7a";
    const point = Math.max(24, Math.min(priceBottom - 12, up ? y(bar.low) + 36 : y(bar.high) - 36));
    drawArrow(ctx, x, point, up);
  });
  const tag = formatPrice(lastBar.close);
  const tagWidth = ctx.measureText(tag).width;
  const tagX = Math.min(plotRight + 8, width - tagWidth - 8);
  const tagY = Math.max(14, Math.min(priceBottom - 8, y(lastBar.close)));
  ctx.setLineDash([4, 4]);
  ctx.strokeStyle = lastBar.close >= lastBar.open ? "#3ddc97" : "#ff6b7a";
  ctx.beginPath();
  ctx.moveTo(pad + last * slot + slot / 2, y(lastBar.close));
  ctx.lineTo(Math.max(pad, tagX - 6), y(lastBar.close));
  ctx.stroke();
  ctx.setLineDash([]);
  ctx.fillStyle = "#07090b";
  ctx.fillRect(tagX - 4, tagY - 12, tagWidth + 8, 16);
  ctx.fillStyle = lastBar.close >= lastBar.open ? "#3ddc97" : "#ff6b7a";
  ctx.fillText(tag, tagX, tagY);
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
  const fitted = currentLesson(history);
  const study = PaperFrames.trueMomentum(history, 14, fitted.calc, fitted.smooth);
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
    if (!mark || main[index] == null) return;
    ctx.fillStyle = "#67e8f9";
    drawArrow(ctx, pad + index * slot + slot / 2, y(main[index]) + 11, true);
  });
  down.forEach((mark, index) => {
    if (!mark || main[index] == null) return;
    ctx.fillStyle = "#ff6b7a";
    drawArrow(ctx, pad + index * slot + slot / 2, y(main[index]) - 11, false);
  });
  ctx.fillStyle = "#d7e2ea";
  ctx.fillText("TMO", pad, 12);
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
        const bars = await PaperLive.daily(symbol, row ? row.category : "");
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
    $("status").textContent = "Auto is on. Refreshing the market quote.";
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

$("trade").addEventListener("click", () => withBusy(async () => {
  stopAuto();
  const paper = await paperCall("/api/paper/trade", {});
  render(paper);
  const verb = paper.last_action === "sell" ? "Sold" : "Bought";
  $("status").textContent = verb + " one simulated position on the paper account.";
  await loadSignals();
}));

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

$("candles").addEventListener("click", (event) => {
  const map = state.chartMap;
  if (!map || !map.bars.length) {
    showRead("The chart has no bars yet.");
    return;
  }
  const index = Math.round((event.offsetX - map.pad) / map.slot - 0.5);
  const bar = map.bars[Math.max(0, Math.min(map.bars.length - 1, index))];
  showChart();
  showRead((map.symbol || "Chart") + " · " + bar.date + " · Open " + formatPrice(bar.open) + " · High " + formatPrice(bar.high) + " · Low " + formatPrice(bar.low) + " · Close " + formatPrice(bar.close));
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
bindZoom($("candles"), "chart");
bindZoom($("rsi-chart"), "chart");
bindZoom($("frame-preview"), "preview");

boot().then(() => {
  warnSqueeze();
  setInterval(warnSqueeze, 20000);
}).catch((error) => {
  showProblem(error && error.message ? error.message : "The desk did not start.");
});
