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

const notes = {
  BUY: "The next sample day would open a simulated long at that day's open.",
  SELL: "The next sample day would close the simulated long at that day's open.",
  "HOLD LONG": "A simulated long is open. No exit signal on this close.",
  HOLD: "Flat. No new simulated order on this close.",
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

function formParams() {
  return {
    symbol: $("symbol").value,
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

async function paperCall(path, body) {
  if (!state.offline) return post(path, body);
  const params = body || {};
  if (path === "/api/paper/step") return PaperEngine.step(state.session, params.days || 1);
  if (path === "/api/paper/run") return PaperEngine.run(state.session);
  if (path === "/api/paper/trade") return PaperEngine.trade(state.session);
  if (path === "/api/paper/mode") return PaperEngine.view(state.session);
  if (path === "/api/paper/reset") {
    state.session = PaperEngine.reset(state.catalog, { ...formParams(), ...params });
    return PaperEngine.view(state.session);
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
  const select = $("symbol");
  const current = (state.paper && state.paper.symbol) || select.value;
  const rows = listed.symbols.slice();
  if (current && !rows.some((item) => item.symbol === current)) {
    rows.unshift({ symbol: current, name: "Current sample", category: "", decimals: state.decimals });
  }
  select.replaceChildren();
  rows.forEach((item) => {
    const option = document.createElement("option");
    option.value = item.symbol;
    option.dataset.decimals = String(item.decimals);
    const kind = item.category ? " · " + item.category : "";
    option.textContent = item.symbol + " · " + item.name + kind;
    select.appendChild(option);
  });
  if (current) select.value = current;
  const chosen = [...select.options].find((item) => item.value === select.value);
  if (chosen) state.decimals = Number(chosen.dataset.decimals || 2);
  const total = Number(listed.total || 0).toLocaleString();
  $("market-count").textContent = select.options.length + " listed · " + total + " sample markets";
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

function renderLocalSignals() {
  const items = [
    ["This device", true, state.offline ? "Phone copy" : "127.0.0.1"],
    ["Paper", true, "On"],
    ["Auto", true, "Ready"],
    ["Trade", true, "Ready"],
    ["Markets", state.catalog.length >= 7500, state.catalog.length + " samples"],
    ["Squeeze", true, "Ready"],
    ["Arrows", true, "Ready"],
    ["Study", true, "On this device"],
    ["Timeframes", true, "10 windows"],
    ["Broker", true, "Not connected"],
    ["Version", true, "1.7.0"],
  ];
  const list = $("signals");
  list.replaceChildren();
  items.forEach(([name, ok, detail]) => {
    const row = document.createElement("li");
    const dot = document.createElement("span");
    dot.className = ok ? "dot on" : "dot";
    const text = document.createElement("span");
    text.textContent = name + " · " + detail;
    row.append(dot, text);
    list.appendChild(row);
  });
}

async function bootOffline() {
  const text = globalThis.PAPER_CATALOG || await (await fetch("/catalog.txt")).text();
  state.catalog = PaperEngine.parseCatalog(text);
  state.session = PaperEngine.open(state.catalog, "BTC-USD", {});
  state.paper = PaperEngine.view(state.session);
  await refreshSymbols();
  fillForm(state.paper.params);
  state.market = PaperEngine.market(state.session);
  render(state.paper);
  renderLocalSignals();
  if (state.paper.paper === true && !state.paper.finished) {
    state.auto = true;
    render(state.paper);
    $("status").textContent = "Auto is on. Simulated sample days only.";
    autoTick();
    return;
  }
  $("status").textContent = "Phone copy ready. Sample prices stay on this device.";
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
  await loadMarket();
  render(paper);
  await loadSignals();
  if (paper.paper === true && !paper.finished) {
    state.auto = true;
    render(paper);
    $("status").textContent = "Auto is on. Simulated sample days only.";
    autoTick();
    return;
  }
  $("status").textContent = "Sample session ready.";
}

async function loadSignals() {
  const report = await getJSON("/api/checks");
  const list = $("signals");
  list.replaceChildren();
  report.items.forEach((item) => {
    const row = document.createElement("li");
    const dot = document.createElement("span");
    dot.className = item.ok ? "dot on" : "dot";
    const text = document.createElement("span");
    text.textContent = item.name + " · " + item.detail;
    row.append(dot, text);
    list.appendChild(row);
  });
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

async function loadMarket() {
  if (state.offline) {
    state.market = PaperEngine.market(state.session);
    rerunStudy();
    return;
  }
  const params = new URLSearchParams(formParams());
  state.market = await getJSON("/api/market?" + params.toString());
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
  $("chart-title").textContent = (paper.symbol || "Sample") + " · Daily session";
  renderQuote(paper);
  $("next").disabled = state.auto || paper.finished;
  $("run").disabled = state.auto || paper.finished;
  $("trade").disabled = Boolean(paper.finished);
  $("auto").disabled = Boolean(paper.finished);
  $("auto").setAttribute("aria-pressed", state.auto ? "true" : "false");
  $("paper").disabled = false;
  $("paper").setAttribute("aria-pressed", paper.paper === true && paper.mode === "paper" ? "true" : "false");
  fillTrades($("journal-body"), paper.trades || []);
  renderReadings();
  renderFrames();
  drawCandles();
  drawRsi();
}

function renderQuote(paper) {
  const bars = seenBars();
  const last = bars.length ? bars[bars.length - 1] : null;
  const previous = bars.length > 1 ? bars[bars.length - 2] : null;
  const quote = $("quote");
  if (!quote) return;
  $("quote-symbol").textContent = paper.symbol || "Sample";
  if (!last) {
    $("quote-last").textContent = "—";
    $("quote-change").textContent = "—";
    $("quote-asof").textContent = "Sample session";
    quote.className = "quote";
    return;
  }
  const change = previous ? last.close - previous.close : 0;
  const percent = previous && previous.close ? (change / previous.close) * 100 : 0;
  const sign = change > 0 ? "+" : "";
  $("quote-last").textContent = formatPrice(last.close);
  $("quote-change").textContent = sign + formatPrice(change) + "  " + sign + percent.toFixed(2) + "%";
  $("quote-asof").textContent = "Through " + (last.date || paper.last_date || "the sample") + " · not a live quote";
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
    caption.textContent = selected.label + " preview · " + selected.bias + " · " + change + " · " + selected.count + " sample bars";
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
  win.document.write("<!DOCTYPE html><title>" + heading + "</title><body style=\"margin:0;background:#101318;color:#e7edf3;font:16px Segoe UI,sans-serif\"><p style=\"padding:12px 16px\">" + heading + " is " + (row ? row.bias : "flat") + ". Sample bars, not a live quote.</p><canvas id=\"c\" style=\"width:100%;height:78vh\"></canvas><script>const bars = " + JSON.stringify(bars) + ";const canvas=document.getElementById('c');function paint(){const width=canvas.clientWidth||640;const height=canvas.clientHeight||360;canvas.width=width;canvas.height=height;const ctx=canvas.getContext('2d');if(!bars.length)return;const pad=28;let low=Math.min(...bars.map(b=>b.low));let high=Math.max(...bars.map(b=>b.high));const span=high-low||1;low-=span*0.08;high+=span*0.08;const y=p=>pad+((high-p)/(high-low))*(height-pad*2);const slot=(width-pad*2)/bars.length;bars.forEach((bar,index)=>{const x=pad+index*slot+slot/2;const up=bar.close>=bar.open;ctx.strokeStyle=up?'#3ddc97':'#ff6b7a';ctx.fillStyle=ctx.strokeStyle;ctx.beginPath();ctx.moveTo(x,y(bar.high));ctx.lineTo(x,y(bar.low));ctx.stroke();const top=y(Math.max(bar.open,bar.close));const bottom=y(Math.min(bar.open,bar.close));ctx.fillRect(x-2,top,4,Math.max(1,bottom-top));});}paint();addEventListener('resize',paint);<" + "/script></body>");
  win.document.close();
}

function openFrame(frameId) {
  state.frameId = frameId;
  renderFrames();
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
  PaperFrames.FRAMES.forEach((frame) => {
    const win = openFrameWindow(frame.id);
    if (win) opened += 1;
  });
  showDock("frames");
  $("status").textContent = opened
    ? "Opened " + opened + " of 10 windows. A blocked window stays listed in this panel."
    : "The browser blocked the windows. Each timeframe is listed in this panel.";
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
  win.document.write("<!DOCTYPE html><title>" + note.title + "</title><body style=\"margin:0;background:#101318;color:#e7edf3;font:18px/1.5 Segoe UI,sans-serif\"><article style=\"max-width:640px;padding:28px\"><p style=\"letter-spacing:.08em;text-transform:uppercase;color:#8fd0c4;font-size:12px\">Brief</p><h1 style=\"font-size:28px\">" + note.title + "</h1>" + body + "<p style=\"color:#93a0ad\">Sample prices are not live quotes and are not a forecast.</p></article></body>");
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

function drawCandles() {
  const canvas = $("candles");
  const bars = sliceToCursor(state.market.bars);
  const fast = sliceToCursor(state.market.fast);
  const slow = sliceToCursor(state.market.slow);
  const squeeze = sliceToCursor(state.market.squeeze);
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
    const values = sliceToCursor(plot.values);
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
  const values = sliceToCursor(state.market.rsi);
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

async function withBusy(action) {
  if (state.busy) return;
  state.busy = true;
  $("status").textContent = "";
  try {
    await action();
  } catch (error) {
    $("status").textContent = error.message;
  } finally {
    state.busy = false;
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
  try {
    const paper = await paperCall("/api/paper/step", { days: 1 });
    render(paper);
    if (paper.finished || paper.paper !== true) {
      state.auto = false;
      render(paper);
      $("status").textContent = paper.finished
        ? "Auto stopped. The sample is finished."
        : "Auto stopped. Paper is off.";
      return;
    }
    $("status").textContent = "Auto is on. This step is a simulated sample day.";
  } catch (error) {
    state.auto = false;
    if (state.paper) render(state.paper);
    $("status").textContent = error.message;
    return;
  } finally {
    state.busy = false;
  }
  if (state.auto) state.autoTimer = setTimeout(autoTick, 200);
}

$("auto").addEventListener("click", () => {
  if (!state.paper || state.paper.paper !== true) {
    $("status").textContent = "Turn Paper on first. Auto only replays the sample.";
    return;
  }
  if (state.paper.finished) return;
  state.auto = !state.auto;
  render(state.paper);
  if (!state.auto) {
    if (state.autoTimer) clearTimeout(state.autoTimer);
    state.autoTimer = 0;
    $("status").textContent = "Auto is off.";
    return;
  }
  $("status").textContent = "Auto is on. Simulated sample days only.";
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

$("symbol").addEventListener("change", () => withBusy(async () => {
  stopAuto();
  const chosen = $("symbol").selectedOptions[0];
  if (chosen) state.decimals = Number(chosen.dataset.decimals || 2);
  const paper = await paperCall("/api/paper/reset", formParams());
  state.paper = paper;
  await refreshSymbols();
  await loadMarket();
  render(paper);
  $("status").textContent = "Started a new sample session for " + paper.symbol + ".";
}));

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
  $("status").textContent = "Sample session finished.";
}));

$("reset").addEventListener("click", () => withBusy(async () => {
  stopAuto();
  const paper = await paperCall("/api/paper/reset", formParams());
  await loadMarket();
  render(paper);
  $("status").textContent = "Session reset with the settings above.";
}));

$("full").addEventListener("click", () => withBusy(async () => {
  if (state.offline) {
    $("backtest-summary").textContent = "On the phone, Run remaining days replays the sample. The separate full-sample report is on the computer copy.";
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
