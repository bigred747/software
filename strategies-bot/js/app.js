import { MARKETS, getMarket } from "./markets.js";
import { rankStrategies, STRATEGY_DEFS } from "./strategies.js";

const state = {
  symbol: "ZB",
  selectedId: "edge-five",
  ranking: null,
  pendingSide: null,
  paper: JSON.parse(localStorage.getItem("strategies-bot-paper") || "[]"),
};

const els = {
  marketSelect: document.getElementById("marketSelect"),
  strategyBody: document.getElementById("strategyBody"),
  footerNote: document.getElementById("footerNote"),
  pickedText: document.getElementById("pickedText"),
  tradesBody: document.getElementById("tradesBody"),
  tradesSub: document.getElementById("tradesSub"),
  marketsList: document.getElementById("marketsList"),
  buyBtn: document.getElementById("buyBtn"),
  sellBtn: document.getElementById("sellBtn"),
  modal: document.getElementById("confirmModal"),
  modalText: document.getElementById("modalText"),
  modalConfirm: document.getElementById("modalConfirm"),
  modalCancel: document.getElementById("modalCancel"),
  toast: document.getElementById("toast"),
  copyThinkscript: document.getElementById("copyThinkscript"),
};

function fmtPct(n, digits = 1) {
  const v = Number(n);
  const sign = v > 0 ? "+" : "";
  return `${sign}${v.toFixed(digits)}%`;
}

function pctClass(n) {
  return n > 0 ? "pos" : n < 0 ? "neg" : "";
}

function nowClass(now) {
  const n = String(now || "WAIT").toUpperCase();
  if (n === "HOLD") return "hold";
  if (n === "BUY") return "buy";
  if (n === "SELL") return "sell";
  return "wait";
}

function selectedRow() {
  return state.ranking?.rows.find((r) => r.id === state.selectedId) || state.ranking?.rows[0];
}

function renderMarkets() {
  els.marketSelect.innerHTML = MARKETS.map(
    (m) => `<option value="${m.symbol}" ${m.symbol === state.symbol ? "selected" : ""}>${m.symbol} · ${m.name}</option>`
  ).join("");

  els.marketsList.innerHTML = MARKETS.map((m) => {
    const rank = rankStrategies(m.bars);
    const best = rank.best;
    return `<li data-symbol="${m.symbol}" class="${m.symbol === state.symbol ? "active" : ""}">
      <div>
        <div class="sym">${m.symbol}</div>
        <div class="meta">${m.name}</div>
      </div>
      <div class="meta num ${pctClass(best.result)}">${best.name.split(" · ")[0]} · ${fmtPct(best.result)}</div>
    </li>`;
  }).join("");
}

function renderTable() {
  const { rows, best } = state.ranking;
  els.strategyBody.innerHTML = rows
    .map((r) => {
      const isEdge = r.id === "edge-five";
      return `<tr data-id="${r.id}" class="${r.id === state.selectedId ? "selected" : ""}">
        <td class="name-cell">
          <div class="title">${r.name}${isEdge ? '<span class="tag">This bot</span>' : ""}</div>
          <div class="blurb">${r.blurb}</div>
        </td>
        <td><span class="now ${nowClass(r.now)}">${r.now}</span></td>
        <td class="num">${r.trades}</td>
        <td class="num">${Math.round(r.winRate)}%</td>
        <td class="num ${pctClass(r.result)}">${fmtPct(r.result)}</td>
        <td class="num ${pctClass(r.hold)}">${fmtPct(r.hold)}</td>
        <td class="num ${pctClass(r.worst)}">${fmtPct(r.worst)}</td>
      </tr>`;
    })
    .join("");

  els.footerNote.innerHTML = `Best on this market's history so far: <strong>${best.name.split(" · ")[0]}</strong>, ${fmtPct(best.result)} over ${best.trades} trades, against ${fmtPct(best.hold)} for simply holding. Picking the winner after the fact flatters it. Past closed bars, not a forecast.`;
}

function renderPicked() {
  const row = selectedRow();
  const best = state.ranking.best;
  els.pickedText.innerHTML = `Picked: <strong>${row.name}</strong> (${row.now}). Best on this history so far: <strong>${best.name.split(" · ")[0]}</strong>. Paper Buy or Sell on <strong>${state.symbol}</strong>. Second warning still asks. Not a forecast.
    <span class="warn">Sample OHLC data — swap for live bars in your bot feed.</span>`;
}

function renderTrades() {
  const row = selectedRow();
  els.tradesSub.textContent = `${row.name} on ${state.symbol} · closed-bar paper history`;
  const list = [...row.tradeList].reverse();
  if (!list.length) {
    els.tradesBody.innerHTML = `<tr><td colspan="5" class="blurb">No trades on this sample history yet.</td></tr>`;
    return;
  }
  els.tradesBody.innerHTML = list
    .map((t) => {
      return `<tr>
        <td>${t.side}${t.open ? " · open" : ""}</td>
        <td class="num">${t.entryDate}</td>
        <td class="num">${t.exitDate}</td>
        <td class="num">${t.entry.toFixed(2)} → ${t.exit.toFixed(2)}</td>
        <td class="num ${pctClass(t.ret)}">${fmtPct(t.ret)}</td>
      </tr>`;
    })
    .join("");
}

function refresh() {
  const market = getMarket(state.symbol);
  state.ranking = rankStrategies(market.bars);
  if (!state.ranking.rows.some((r) => r.id === state.selectedId)) {
    state.selectedId = "edge-five";
  }
  renderMarkets();
  renderTable();
  renderPicked();
  renderTrades();
}

function showToast(msg) {
  els.toast.textContent = msg;
  els.toast.classList.add("show");
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => els.toast.classList.remove("show"), 2800);
}

function openConfirm(side) {
  state.pendingSide = side;
  const row = selectedRow();
  els.modalText.textContent = `Paper ${side} ${state.symbol} using ${row.name}. This is not a live order and not a forecast. Continue?`;
  els.modalConfirm.textContent = `Confirm ${side}`;
  els.modalConfirm.className = side === "Buy" ? "confirm-buy" : "confirm-sell";
  els.modal.classList.add("open");
}

function closeConfirm() {
  state.pendingSide = null;
  els.modal.classList.remove("open");
}

function placePaper(side) {
  const market = getMarket(state.symbol);
  const px = market.bars[market.bars.length - 1].c;
  const row = selectedRow();
  const ticket = {
    id: `${Date.now()}`,
    side,
    symbol: state.symbol,
    strategy: row.name,
    price: px,
    at: new Date().toISOString(),
  };
  state.paper.unshift(ticket);
  localStorage.setItem("strategies-bot-paper", JSON.stringify(state.paper.slice(0, 50)));
  showToast(`Paper ${side} ${state.symbol} @ ${px.toFixed(2)} · ${row.name}`);
  closeConfirm();
}

els.marketSelect.addEventListener("change", (e) => {
  state.symbol = e.target.value;
  refresh();
});

els.strategyBody.addEventListener("click", (e) => {
  const tr = e.target.closest("tr[data-id]");
  if (!tr) return;
  state.selectedId = tr.dataset.id;
  renderTable();
  renderPicked();
  renderTrades();
});

els.marketsList.addEventListener("click", (e) => {
  const li = e.target.closest("li[data-symbol]");
  if (!li) return;
  state.symbol = li.dataset.symbol;
  refresh();
});

els.buyBtn.addEventListener("click", () => openConfirm("Buy"));
els.sellBtn.addEventListener("click", () => openConfirm("Sell"));
els.modalCancel.addEventListener("click", closeConfirm);
els.modalConfirm.addEventListener("click", () => {
  if (state.pendingSide) placePaper(state.pendingSide);
});
els.modal.addEventListener("click", (e) => {
  if (e.target === els.modal) closeConfirm();
});

els.copyThinkscript.addEventListener("click", async () => {
  try {
    const res = await fetch("./thinkscript/Edge_Five_Checks_Strategy.ts");
    const text = await res.text();
    await navigator.clipboard.writeText(text);
    showToast("ThinkScript copied — paste into Strategies tab in Thinkorswim");
  } catch {
    showToast("Open thinkscript/Edge_Five_Checks_Strategy.ts and copy manually");
  }
});

refresh();

// Expose for debugging / bot hooks
window.StrategiesBot = {
  getState: () => ({ ...state, strategies: STRATEGY_DEFS.map((s) => s.id) }),
  rank: () => state.ranking,
};
