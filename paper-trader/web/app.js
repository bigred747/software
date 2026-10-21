const state = { market: null, paper: null, busy: false, auto: false, autoTimer: 0, decimals: 2 };

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
  if (value == null || Number.isNaN(Number(value))) return "—";
  return Number(value).toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function post(url, body) {
  return getJSON(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {}),
  });
}

async function refreshSymbols() {
  const params = new URLSearchParams({
    q: $("search").value.trim(),
    category: $("category").value,
    limit: "40",
  });
  const listed = await getJSON("/api/symbols?" + params.toString());
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

async function boot() {
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

async function loadMarket() {
  const params = new URLSearchParams(formParams());
  state.market = await getJSON("/api/market?" + params.toString());
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
    ? paper.shares.toFixed(4) + " @ " + formatPrice(paper.entry_price)
    : "Flat";
  $("through").textContent = paper.cursor + " / " + paper.total_bars;
  $("chart-title").textContent = (paper.last_date || "Sample") + " · " + paper.symbol;
  $("next").disabled = state.auto || paper.finished;
  $("run").disabled = state.auto || paper.finished;
  $("trade").disabled = Boolean(paper.finished);
  $("auto").disabled = Boolean(paper.finished);
  $("auto").setAttribute("aria-pressed", state.auto ? "true" : "false");
  $("paper").disabled = false;
  $("paper").setAttribute("aria-pressed", paper.paper === true && paper.mode === "paper" ? "true" : "false");
  fillTrades($("journal-body"), paper.trades || []);
  renderReadings();
  drawCandles();
  drawRsi();
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
  const pad = 36;
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
    const paper = await post("/api/paper/step", { days: 1 });
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
  const paper = await post("/api/paper/trade", {});
  render(paper);
  const verb = paper.last_action === "sell" ? "Sold" : "Bought";
  $("status").textContent = verb + " one simulated position on the paper account.";
  await loadSignals();
}));

$("paper").addEventListener("click", () => withBusy(async () => {
  const paper = await post("/api/paper/mode", { mode: "paper" });
  render(paper);
  $("status").textContent = "Paper is on. Orders stay simulated on this computer.";
}));

$("symbol").addEventListener("change", () => withBusy(async () => {
  stopAuto();
  const chosen = $("symbol").selectedOptions[0];
  if (chosen) state.decimals = Number(chosen.dataset.decimals || 2);
  const paper = await post("/api/paper/reset", formParams());
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
  render(await post("/api/paper/step", { days: 1 }));
}));

$("run").addEventListener("click", () => withBusy(async () => {
  stopAuto();
  render(await post("/api/paper/run", {}));
  $("status").textContent = "Sample session finished.";
}));

$("reset").addEventListener("click", () => withBusy(async () => {
  stopAuto();
  const paper = await post("/api/paper/reset", formParams());
  await loadMarket();
  render(paper);
  $("status").textContent = "Session reset with the settings above.";
}));

$("full").addEventListener("click", () => withBusy(async () => {
  const report = await post("/api/backtest", formParams());
  const factor = report.profit_factor == null ? "—" : report.profit_factor.toFixed(2);
  $("backtest-summary").textContent =
    "Return " + report.return_pct.toFixed(2) + "% · drawdown " +
    report.max_drawdown_pct.toFixed(2) + "% · trades " + report.trade_count +
    " · win rate " + report.win_rate_pct.toFixed(1) + "% · profit factor " + factor;
  fillTrades($("backtest-body"), report.trades || []);
}));

window.addEventListener("resize", () => {
  if (state.market && state.paper) {
    drawCandles();
    drawRsi();
  }
});

boot().catch((error) => {
  $("status").textContent = error.message;
});
