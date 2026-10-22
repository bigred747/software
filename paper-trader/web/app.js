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
};

const STUDY_KEY = "paperTraderStudies";
const PIN_KEY = "paperTraderPins";
const SYMBOL_KEY = "paperTraderSymbol";
const DEFAULT_PINS = ["BTC-USD", "ETH-USD", "XRP-USD", "XLM-USD", "SOL-USD", "SPY"];

const notes = {
  BUY: "The paper account would open a long on the next bar.",
  SELL: "The paper account would close the long on the next bar.",
  "HOLD LONG": "A paper long is open. No exit signal on this bar.",
  HOLD: "Flat. No new paper order on this bar.",
};

function $(id) {
  return document.getElementById(id);
}

async function getJSON(url, options) {
  const response = await fetch(url, options);
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payload.error || "Request failed");
  }
  return payload;
}

const VERSION = "1.9.3";

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

function stampQuote(bars) {
  if (!state.market) return;
  state.market.as_of = bars && bars.asOf ? bars.asOf : Math.floor(Date.now() / 1000);
  state.market.price = bars && bars.length ? bars[bars.length - 1].close : state.market.price;
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
  let listed;
  if (state.offline) {
    listed = PaperEngine.search(state.catalog, $("search").value.trim(), $("category").value, 40);
  } else {
  const params = new URLSearchParams({
    q: $("search").value.trim(),
    category: $("category").value,
    limit: "40",
  });
  listed = await getJSON("/api/symbols?" + params.toString());
  }
  const list = $("symbol");
  const current = (state.paper && state.paper.symbol) || list.dataset.value || "";
  const rows = listed.symbols.slice();
  if (current && !rows.some((item) => item.symbol === current)) {
    const decimals = /-USD$/.test(current) ? 4 : 2;
    rows.unshift({ symbol: current, name: "Current market", category: "", decimals });
  }
  list.replaceChildren();
  rows.forEach((item) => {
    const button = document.createElement("button");
    button.type = "button";
    button.dataset.symbol = item.symbol;
    button.dataset.decimals = String(item.decimals);
    const kind = item.category ? " · " + item.category : "";
    button.textContent = item.symbol + " · " + item.name + kind;
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
  const total = Number(listed.total || 0).toLocaleString();
  $("market-count").textContent = list.querySelectorAll("button").length + " listed · " + total + " markets";
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
  return next;
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
  ["signal-strip", "signals"].forEach((id) => {
    const list = $(id);
    if (!list) return;
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
  paintSignals(withHooks([
    { name: "This device", ok: true, detail: "Mobile copy" },
    { name: "Paper", ok: true, detail: "On" },
    { name: "Auto", ok: true, detail: "Ready" },
    { name: "Trade", ok: true, detail: "Ready" },
    { name: "Markets", ok: state.catalog.length >= 7500, detail: state.catalog.length + " markets" },
    { name: "Quotes", ok: !!(state.market && state.market.price), detail: state.market && state.market.price ? "Live crypto" : "Waiting on the quote" },
    { name: "Squeeze", ok: !!(state.market && (state.market.squeeze || []).length), detail: "Ready" },
    { name: "Arrows", ok: true, detail: "Ready" },
    { name: "Study", ok: typeof PaperStudy !== "undefined", detail: "On this device" },
    { name: "Timeframes", ok: typeof PaperFrames !== "undefined" && PaperFrames.FRAMES.length === 10, detail: "10 windows" },
    { name: "Broker", ok: true, detail: "Not connected" },
    { name: "Version", ok: true, detail: VERSION },
  ]));
}

async function bootOffline() {
  const text = globalThis.PAPER_CATALOG || await (await fetch("/catalog.txt")).text();
  state.catalog = PaperEngine.parseCatalog(text);
  const saved = savedSymbol();
  let symbol = state.catalog.some((row) => row.symbol === saved) ? saved : "BTC-USD";
  let row = state.catalog.find((item) => item.symbol === symbol);
  if (!phoneCrypto(symbol, row)) {
    symbol = "BTC-USD";
    row = state.catalog.find((item) => item.symbol === symbol);
  }
  const bars = await PaperLive.daily(symbol, row ? row.category : "");
  state.session = PaperEngine.sessionFromBars(symbol, bars, {});
  state.paper = PaperEngine.view(state.session);
  await refreshSymbols();
  fillForm(state.paper.params);
  state.market = PaperEngine.market(state.session);
  stampQuote(bars);
  render(state.paper);
  renderLocalSignals();
  rememberSymbol(state.paper.symbol);
  noteEdge();
  if (state.paper.paper === true) {
    state.auto = true;
    render(state.paper);
    $("status").textContent = "Auto is on. Refreshing the market quote.";
    autoTick();
    return;
  }
  $("status").textContent = inMicrosoftEdge()
    ? "Opened in Microsoft Edge. Crypto quotes are live."
    : "Mobile copy ready. Crypto quotes are live.";
}

async function boot() {
  restoreStudies();
  state.offline = !(await probeDesk());
  if (state.offline) {
    await bootOffline();
    return;
  }
  const paper = await getJSON("/api/paper");
  state.paper = paper;
  await refreshSymbols();
  fillForm(paper.params);
  await loadMarket(paper.symbol);
  render(paper);
  await loadSignals();
  rememberSymbol(paper.symbol);
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
  renderReadings();
  renderFrames();
  drawCandles();
  drawRsi();
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
    if (!Array.isArray(parsed)) return DEFAULT_PINS.slice();
    return parsed.filter((item) => typeof item === "string" && item.length > 0 && item.length < 24).slice(0, 12);
  } catch (error) {
    return DEFAULT_PINS.slice();
  }
}

function writePins(pins) {
  try {
    localStorage.setItem(PIN_KEY, JSON.stringify(pins.slice(0, 12)));
  } catch (error) {
    return;
  }
}

function pinLabel(symbol) {
  return symbol.endsWith("-USD") ? symbol.slice(0, -4) : symbol;
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
  const pins = readPins();
  bar.replaceChildren();
  pins.forEach((symbol) => {
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = pinLabel(symbol);
    button.title = symbol;
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
      drawRsi();
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
      showChart();
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
    quote.className = "quote";
    return;
  }
  const change = previous ? last.close - previous.close : 0;
  const percent = previous && previous.close ? (change / previous.close) * 100 : 0;
  const sign = change > 0 ? "+" : "";
  const sameMarket = state.market && state.market.symbol === paper.symbol;
  const livePrice = sameMarket && state.market.price != null ? state.market.price : last.close;
  $("quote-last").textContent = formatPrice(livePrice);
  $("quote-change").textContent = sign + formatPrice(change) + "  " + sign + percent.toFixed(2) + "%";
  const asOf = sameMarket && state.market.as_of;
  $("quote-asof").textContent = asOf
    ? "Market quote · " + new Date(asOf * 1000).toLocaleString()
    : "Market quote · " + (last.date || "");
  quote.className = "quote " + (change > 0 ? "up" : change < 0 ? "down" : "flat");
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
  bar.replaceChildren();
  const selectedId = state.frameId || "1D";
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
  PaperFrames.draw($("frame-preview"), selected.bars);
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
  win.document.open();
  const quoteLine = bars.length ? "Market quote." : "Loading the market quote.";
  win.document.write("<!DOCTYPE html><title>" + heading + "</title><body style=\"margin:0;background:#101318;color:#e7edf3;font:16px Segoe UI,sans-serif\"><p id=\"note\" style=\"padding:12px 16px\">" + heading + " is " + (row ? row.bias : "flat") + ". " + quoteLine + "</p><canvas id=\"c\" style=\"width:100%;height:78vh\"></canvas><script>const bars = " + JSON.stringify(bars) + ";const canvas=document.getElementById('c');function paint(){const width=canvas.clientWidth||640;const height=canvas.clientHeight||360;canvas.width=width;canvas.height=height;const ctx=canvas.getContext('2d');if(!bars.length)return;const pad=28;let low=Math.min(...bars.map(b=>b.low));let high=Math.max(...bars.map(b=>b.high));const span=high-low||1;low-=span*0.08;high+=span*0.08;const y=p=>pad+((high-p)/(high-low))*(height-pad*2);const slot=(width-pad*2)/bars.length;bars.forEach((bar,index)=>{const x=pad+index*slot+slot/2;const up=bar.close>=bar.open;ctx.strokeStyle=up?'#3ddc97':'#ff6b7a';ctx.fillStyle=ctx.strokeStyle;ctx.beginPath();ctx.moveTo(x,y(bar.high));ctx.lineTo(x,y(bar.low));ctx.stroke();const top=y(Math.max(bar.open,bar.close));const bottom=y(Math.min(bar.open,bar.close));ctx.fillRect(x-2,top,4,Math.max(1,bottom-top));});}paint();addEventListener('resize',paint);<" + "/script></body>");
  win.document.close();
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
  const level = (market.squeeze || [])[index] || 0;
  const momentum = (market.momentum || [])[index];
  const rsiValue = (market.rsi || [])[index];
  const fast = (market.fast || [])[index];
  const slow = (market.slow || [])[index];
  const squeezeName = level >= 3 ? "Tight" : level === 2 ? "Normal" : level === 1 ? "Wide" : "Off";
  const momentumName = momentum == null ? "—" : momentum >= 0 ? "Up " + Number(momentum).toFixed(2) : "Down " + Number(momentum).toFixed(2);
  const rsiName = rsiValue == null ? "—" : Number(rsiValue).toFixed(1);
  const averageName = fast == null || slow == null ? "—" : fast >= slow ? "Fast above slow" : "Fast below slow";
  const list = $("readings");
  list.replaceChildren();
  [
    ["Squeeze", squeezeName],
    ["Momentum", momentumName],
    ["RSI", rsiName],
    ["Averages", averageName],
  ].forEach(([name, value]) => {
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

function sliceToCursor(values) {
  const cursor = state.paper ? state.paper.cursor : 0;
  return (values || []).slice(0, cursor);
}

function visible(values) {
  const sliced = sliceToCursor(values);
  return sliced.length > 180 ? sliced.slice(sliced.length - 180) : sliced;
}

function drawCandles(retry) {
  const canvas = $("candles");
  if (!state.market) return;
  if ((canvas.clientWidth < 40 || canvas.clientHeight < 40) && !retry) {
    requestAnimationFrame(() => drawCandles(true));
    return;
  }
  const bars = visible(state.market.bars);
  const fast = visible(state.market.fast);
  const slow = visible(state.market.slow);
  const squeeze = visible(state.market.squeeze);
  const ctx = prepare(canvas);
  if (!bars.length) return;
  const width = canvas.clientWidth;
  const height = canvas.clientHeight;
  const pad = 64;
  let low = Math.min(...bars.map((bar) => bar.low));
  let high = Math.max(...bars.map((bar) => bar.high));
  const span = high - low || 1;
  low -= span * 0.14;
  high += span * 0.14;
  const y = (price) => pad + ((high - price) / (high - low)) * (height - pad * 2);
  const slot = (width - pad * 2) / bars.length;
  state.chartMap = { bars, pad, slot, symbol: state.paper && state.paper.symbol };
  ctx.strokeStyle = "#2d3640";
  ctx.fillStyle = "#9aa6b2";
  ctx.font = "12px Segoe UI, sans-serif";
  for (let grid = 0; grid < 4; grid += 1) {
    const price = low + ((high - low) * grid) / 3;
    const line = y(price);
    ctx.beginPath();
    ctx.moveTo(pad, line);
    ctx.lineTo(width - 8, line);
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
  drawLine(ctx, fast, y, pad, slot, "#7eb6ff");
  drawLine(ctx, slow, y, pad, slot, "#e2b15a");
  drawStudy(ctx, bars, y, pad, slot);
  squeeze.forEach((level, index) => {
    if (!level) return;
    const x = pad + index * slot + slot / 2;
    ctx.fillStyle = level >= 3 ? "#7eb6ff" : level === 2 ? "#e2b15a" : "#9aa6b2";
    ctx.fillRect(x - 2.5, height - 12, 5, 5);
  });
  const byDate = new Map(bars.map((bar, index) => [bar.date, index]));
  (state.paper.markers || []).forEach((marker) => {
    const index = byDate.get(marker.date);
    if (index == null) return;
    const bar = bars[index];
    const x = pad + index * slot + slot / 2;
    const up = marker.side === "buy";
    ctx.fillStyle = up ? "#3ddc97" : "#ff6b7a";
    const point = up ? y(bar.low) + 16 : y(bar.high) - 16;
    drawArrow(ctx, x, point, up);
  });
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

function drawRsi() {
  const canvas = $("rsi-chart");
  const values = visible(state.market.rsi);
  const ctx = prepare(canvas);
  const width = canvas.clientWidth;
  const height = canvas.clientHeight;
  const pad = 28;
  const y = (value) => pad / 2 + ((100 - value) / 100) * (height - pad);
  ctx.strokeStyle = "#2d3640";
  ctx.fillStyle = "#9aa6b2";
  ctx.font = "12px Segoe UI, sans-serif";
  [30, 70].forEach((level) => {
    ctx.beginPath();
    ctx.moveTo(pad, y(level));
    ctx.lineTo(width - 8, y(level));
    ctx.stroke();
    ctx.fillText(String(level), 4, y(level) + 4);
  });
  drawLine(ctx, values, y, pad, (width - pad * 2) / Math.max(1, values.length), "#c9d4e3");
}

function drawLine(ctx, values, y, pad, slot, color) {
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
  ctx.lineWidth = 1.5;
  ctx.stroke();
  ctx.lineWidth = 1;
}

function prepare(canvas) {
  const ratio = window.devicePixelRatio || 1;
  const width = canvas.clientWidth || 300;
  const height = canvas.clientHeight || 100;
  canvas.width = Math.floor(width * ratio);
  canvas.height = Math.floor(height * ratio);
  const ctx = canvas.getContext("2d");
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  ctx.clearRect(0, 0, width, height);
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

async function autoTick() {
  if (!state.auto || state.busy) return;
  state.busy = true;
  let delay = 15000;
  try {
    if (state.offline && state.session) {
      const row = state.catalog.find((item) => item.symbol === state.session.symbol);
      const bars = await PaperLive.daily(state.session.symbol, row ? row.category : "");
      const paper = PaperEngine.replaceBars(state.session, bars);
      state.market = PaperEngine.market(state.session);
      stampQuote(bars);
      render(paper);
    } else {
      const paper = await paperCall("/api/paper/step", { days: 1 });
      state.paper = paper;
      await loadMarket(paper.symbol);
      render(paper);
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

$("pin-toggle").addEventListener("click", () => {
  const current = state.paper && state.paper.symbol;
  if (!current) return;
  const pins = readPins();
  const next = pins.includes(current) ? pins.filter((symbol) => symbol !== current) : [current].concat(pins).slice(0, 12);
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
$("category").addEventListener("change", queueSearch);

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
      drawRsi();
    }
  });
});

document.querySelectorAll(".dock-tab").forEach((button) => {
  button.addEventListener("click", () => showDock(button.dataset.dock));
});

window.addEventListener("resize", () => {
  if (state.market && state.paper) {
    drawCandles();
    drawRsi();
  }
});

boot().catch((error) => {
  $("status").textContent = error.message;
});
