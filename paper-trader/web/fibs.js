/* Fibs Lab — Tools page only (never paints on the main Chart). */
(function (root) {
  "use strict";

  const TOS_FIB_RETRACE = [
    { id: "r0", pct: 0, label: "0%", color: "#f5f7fa", weight: 2.5, kind: "ret" },
    { id: "r236", pct: 23.6, label: "23.6%", color: "#2dd4bf", weight: 2, kind: "ret" },
    { id: "r382", pct: 38.2, label: "38.2%", color: "#84cc16", weight: 2, kind: "ret" },
    { id: "r50", pct: 50, label: "50%", color: "#f43f5e", weight: 2, kind: "ret" },
    { id: "r618", pct: 61.8, label: "61.8%", color: "#fbbf24", weight: 3, kind: "ret" },
    { id: "r786", pct: 78.6, label: "78.6%", color: "#c084fc", weight: 2, kind: "ret" },
    { id: "r100", pct: 100, label: "100%", color: "#94a3b8", weight: 2.5, kind: "ret" },
  ];

  const TOS_FIB_EXT = [
    { id: "e127p", mult: 1.272, side: "+", label: "127.2+", color: "#38bdf8", weight: 2, kind: "ext" },
    { id: "e162p", mult: 1.618, side: "+", label: "161.8+", color: "#60a5fa", weight: 2, kind: "ext" },
    { id: "e200p", mult: 2.0, side: "+", label: "200+", color: "#818cf8", weight: 2, kind: "ext" },
    { id: "e262p", mult: 2.618, side: "+", label: "261.8+", color: "#a78bfa", weight: 2, kind: "ext" },
    { id: "e127m", mult: 1.272, side: "-", label: "127.2−", color: "#fb7185", weight: 2, kind: "ext" },
    { id: "e162m", mult: 1.618, side: "-", label: "161.8−", color: "#f472b6", weight: 2, kind: "ext" },
    { id: "e200m", mult: 2.0, side: "-", label: "200−", color: "#e879f9", weight: 2, kind: "ext" },
    { id: "e262m", mult: 2.618, side: "-", label: "261.8−", color: "#d946ef", weight: 2, kind: "ext" },
  ];

  const FIB_PREF_KEY = "paperTraderFibPrefsLab63";
  const fibState = { frame: "1D", on: {} };

  function $(id) {
    return root.document.getElementById(id);
  }

  function defaultFibToggles() {
    const on = {};
    TOS_FIB_RETRACE.forEach((row) => {
      on[row.id] = true;
    });
    TOS_FIB_EXT.forEach((row) => {
      on[row.id] = row.mult === 1.272;
    });
    return on;
  }

  function readFibPrefs() {
    const on = defaultFibToggles();
    try {
      const saved = JSON.parse(localStorage.getItem(FIB_PREF_KEY) || "{}");
      if (saved && typeof saved === "object") {
        if (saved.frame) fibState.frame = String(saved.frame);
        if (saved.on && typeof saved.on === "object") {
          Object.keys(on).forEach((id) => {
            if (typeof saved.on[id] === "boolean") on[id] = saved.on[id];
          });
        }
      }
    } catch (error) {
      /* defaults */
    }
    fibState.on = on;
    return fibState;
  }

  function saveFibPrefs() {
    try {
      localStorage.setItem(FIB_PREF_KEY, JSON.stringify({ frame: fibState.frame, on: fibState.on }));
    } catch (error) {
      /* ignore */
    }
  }

  function fibSwingRead(bars) {
    const list = (bars || []).slice();
    if (list.length < 20) return null;
    const window = list.slice(-120);
    let hi = -Infinity;
    let lo = Infinity;
    let hiAt = 0;
    let loAt = 0;
    window.forEach((bar, index) => {
      const high = Number(bar.high);
      const low = Number(bar.low);
      if (!(high > 0) || !(low > 0)) return;
      if (high >= hi) {
        hi = high;
        hiAt = index;
      }
      if (low <= lo) {
        lo = low;
        loAt = index;
      }
    });
    if (!(hi > lo) || !Number.isFinite(hi) || !Number.isFinite(lo)) return null;
    const upswing = loAt <= hiAt;
    const startIndex = list.length - window.length;
    return {
      upswing,
      low: lo,
      high: hi,
      from: startIndex + Math.min(loAt, hiAt),
      to: startIndex + Math.max(loAt, hiAt),
      close: Number(list[list.length - 1].close) || hi,
      span: hi - lo,
    };
  }

  function fibPriceAt(swing, pct) {
    return swing.high - swing.span * (pct / 100);
  }

  function fibExtPrice(swing, row) {
    if (row.side === "+") return swing.low + swing.span * row.mult;
    return swing.high - swing.span * row.mult;
  }

  function fibRole(row, upswing) {
    if (row.kind === "ext") {
      return row.side === "+"
        ? { role: "Extension up", use: "Upside target", klass: "buy" }
        : { role: "Extension down", use: "Downside target", klass: "sell" };
    }
    const pct = row.pct;
    if (pct === 0) return { role: "Swing high", use: "Sell / take-profit zone", klass: "sell" };
    if (pct === 100) return { role: "Swing low", use: "Fail / invalidate", klass: "sell" };
    if (pct === 23.6) return { role: "Shallow", use: upswing ? "Light buy" : "Early sell", klass: upswing ? "buy" : "sell" };
    if (pct === 38.2 || pct === 50) return { role: "Standard", use: upswing ? "Buy zone" : "Sell zone", klass: upswing ? "buy" : "sell" };
    if (pct === 61.8) return { role: "Golden", use: upswing ? "Best buy" : "Best sell", klass: "key" };
    if (pct === 78.6) return { role: "Deep", use: upswing ? "Last buy / risk" : "Last sell / risk", klass: upswing ? "buy" : "sell" };
    return { role: "Level", use: "Watch", klass: "" };
  }

  function visibleFibRows() {
    readFibPrefs();
    return TOS_FIB_RETRACE.concat(TOS_FIB_EXT).filter((row) => fibState.on[row.id]);
  }

  function paintFibToggles() {
    readFibPrefs();
    const fill = (box, list) => {
      if (!box) return;
      box.replaceChildren();
      list.forEach((row) => {
        const label = document.createElement("label");
        const input = document.createElement("input");
        input.type = "checkbox";
        input.checked = !!fibState.on[row.id];
        const mark = document.createElement("span");
        mark.className = "fibs-dot";
        mark.style.background = row.color;
        label.append(input, mark, document.createTextNode(" " + row.label));
        input.addEventListener("change", () => {
          fibState.on[row.id] = !!input.checked;
          saveFibPrefs();
          runFibsPage({ quiet: true });
        });
        box.appendChild(label);
      });
    };
    fill($("fibs-ret-toggles"), TOS_FIB_RETRACE);
    fill($("fibs-ext-toggles"), TOS_FIB_EXT);
    const frames = $("fibs-frames");
    if (frames) {
      frames.querySelectorAll("button[data-fib-frame]").forEach((btn) => {
        btn.classList.toggle("on", btn.dataset.fibFrame === fibState.frame);
      });
    }
  }

  function paintFibChart(canvas, bars, swing, symbol, frameId) {
    if (!canvas || !bars || !bars.length || !swing) return;
    const rows = visibleFibRows();
    const box = canvas.parentElement;
    const cssW = Math.max(320, (box && box.clientWidth) || canvas.clientWidth || 640);
    const cssH = 380;
    canvas.style.width = cssW + "px";
    canvas.style.height = cssH + "px";
    const ratio = Math.max(1, root.devicePixelRatio || 1);
    canvas.width = Math.floor(cssW * ratio);
    canvas.height = Math.floor(cssH * ratio);
    const ctx = canvas.getContext("2d");
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    const width = cssW;
    const height = cssH;
    ctx.clearRect(0, 0, width, height);
    const view = bars.slice(-120);
    const dropped = bars.length - view.length;
    let low = Math.min(...view.map((b) => Number(b.low)).filter((n) => Number.isFinite(n)));
    let high = Math.max(...view.map((b) => Number(b.high)).filter((n) => Number.isFinite(n)));
    if (!(high > low)) {
      low = swing.low;
      high = swing.high;
    }
    rows.forEach((row) => {
      const price = row.kind === "ext" ? fibExtPrice(swing, row) : fibPriceAt(swing, row.pct);
      if (!Number.isFinite(price)) return;
      if (price < low) low = price;
      if (price > high) high = price;
    });
    const span = high - low || 1;
    low -= span * 0.08;
    high += span * 0.08;
    const padLeft = 54;
    const padRight = 92;
    const padTop = 28;
    const padBottom = 18;
    const y = (price) => padTop + ((high - price) / (high - low)) * (height - padTop - padBottom);
    const slot = (width - padLeft - padRight) / Math.max(1, view.length);
    const xAt = (index) => padLeft + index * slot + slot / 2;
    ctx.fillStyle = "#070b10";
    ctx.fillRect(0, 0, width, height);
    const retOn = rows.filter((r) => r.kind === "ret");
    for (let i = 0; i < retOn.length - 1; i += 1) {
      const a = fibPriceAt(swing, retOn[i].pct);
      const b = fibPriceAt(swing, retOn[i + 1].pct);
      const top = Math.min(y(a), y(b));
      const bot = Math.max(y(a), y(b));
      ctx.fillStyle = retOn[i].color + "22";
      ctx.fillRect(padLeft, top, width - padLeft - padRight, Math.max(2, bot - top));
    }
    ctx.strokeStyle = "#243040";
    ctx.fillStyle = "#8a97a6";
    ctx.lineWidth = 1;
    ctx.font = "11px Segoe UI, sans-serif";
    for (let g = 0; g < 5; g += 1) {
      const price = low + ((high - low) * g) / 4;
      const line = y(price);
      ctx.beginPath();
      ctx.moveTo(padLeft, line);
      ctx.lineTo(width - padRight, line);
      ctx.stroke();
      ctx.fillText(price.toFixed(price >= 100 ? 2 : 3), 4, line + 3);
    }
    view.forEach((bar, index) => {
      const x = xAt(index);
      const up = Number(bar.close) >= Number(bar.open);
      ctx.strokeStyle = up ? "#3ddc97" : "#ff6b7a";
      ctx.fillStyle = up ? "rgba(61,220,151,0.45)" : "rgba(255,107,122,0.45)";
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(x, y(Number(bar.high)));
      ctx.lineTo(x, y(Number(bar.low)));
      ctx.stroke();
      const top = y(Math.max(Number(bar.open), Number(bar.close)));
      const bot = y(Math.min(Number(bar.open), Number(bar.close)));
      ctx.fillRect(x - Math.max(1.2, slot * 0.28), top, Math.max(2.4, slot * 0.56), Math.max(1, bot - top));
    });
    const a = Math.max(0, Math.min(view.length - 1, swing.from - dropped));
    const b = Math.max(0, Math.min(view.length - 1, swing.to - dropped));
    ctx.strokeStyle = "rgba(232,238,245,0.55)";
    ctx.lineWidth = 1.5;
    ctx.setLineDash([5, 4]);
    ctx.beginPath();
    ctx.moveTo(xAt(a), y(swing.upswing ? swing.low : swing.high));
    ctx.lineTo(xAt(b), y(swing.upswing ? swing.high : swing.low));
    ctx.stroke();
    ctx.setLineDash([]);
    rows.forEach((row) => {
      const price = row.kind === "ext" ? fibExtPrice(swing, row) : fibPriceAt(swing, row.pct);
      if (!Number.isFinite(price)) return;
      const yy = y(price);
      ctx.strokeStyle = row.color;
      ctx.lineWidth = row.weight || 2;
      ctx.setLineDash(row.kind === "ext" ? [6, 4] : []);
      ctx.beginPath();
      ctx.moveTo(padLeft, yy);
      ctx.lineTo(width - padRight, yy);
      ctx.stroke();
      ctx.setLineDash([]);
      const tag = row.label + "  " + price.toFixed(price >= 100 ? 2 : 3);
      ctx.font = "700 12px Segoe UI, sans-serif";
      const tw = ctx.measureText(tag).width + 10;
      ctx.fillStyle = "rgba(5,8,12,0.9)";
      ctx.fillRect(width - padRight + 2, yy - 9, tw, 16);
      ctx.fillStyle = row.color;
      ctx.fillText(tag, width - padRight + 6, yy + 3);
    });
    ctx.fillStyle = "#e8eef5";
    ctx.font = "700 15px Segoe UI, sans-serif";
    ctx.fillText((symbol || "AMD") + " · " + (frameId || "1D") + " · TOS Fibs", padLeft, 18);
  }

  function renderFibLevels(swing) {
    const body = $("fibs-levels");
    if (!body) return;
    if (!swing) {
      body.innerHTML = "";
      return;
    }
    body.innerHTML = visibleFibRows()
      .map((row) => {
        const price = row.kind === "ext" ? fibExtPrice(swing, row) : fibPriceAt(swing, row.pct);
        const meta = fibRole(row, swing.upswing);
        return (
          "<tr><td style=\"color:" +
          row.color +
          "\">" +
          row.label +
          "</td><td>" +
          (Number.isFinite(price) ? price.toFixed(price >= 100 ? 2 : 3) : "—") +
          "</td><td>" +
          meta.role +
          "</td><td class=\"" +
          meta.klass +
          "\">" +
          meta.use +
          "</td></tr>"
        );
      })
      .join("");
  }

  async function barsForFibs(symbol, frameId) {
    const frame = frameId || fibState.frame || "1D";
    if (typeof root.fetchFrameBars === "function") {
      try {
        const bars = await root.fetchFrameBars(symbol, frame);
        if (bars && bars.length) return bars;
      } catch (error) {
        /* fall through */
      }
    }
    if (typeof root.barsForLearn === "function" && frame === "1D") {
      try {
        return (await root.barsForLearn(symbol)) || [];
      } catch (error) {
        return [];
      }
    }
    return [];
  }

  async function runFibsPage(opts) {
    const quiet = !!(opts && opts.quiet);
    readFibPrefs();
    paintFibToggles();
    const input = $("fibs-symbol");
    const note = $("fibs-note");
    const banner = $("fibs-banner");
    const symbol = ((input && input.value) || "AMD").trim().toUpperCase() || "AMD";
    if (input) input.value = symbol;
    const frame = fibState.frame || "1D";
    if (!quiet) {
      if (note) note.textContent = "Reading " + symbol + " · " + frame + "…";
      if (banner) banner.textContent = "Loading " + symbol + " · " + frame + "…";
    }
    const bars = await barsForFibs(symbol, frame);
    if (!bars || bars.length < 20) {
      if (note) note.textContent = "Could not load enough " + frame + " bars for " + symbol + ".";
      if (banner) banner.textContent = "Need more history for Fibs.";
      renderFibLevels(null);
      return;
    }
    const settled =
      root.PaperFrames && PaperFrames.closedBars
        ? PaperFrames.closedBars(bars, false)
        : bars.slice(0, -1);
    const use = settled && settled.length >= 20 ? settled : bars;
    const swing = fibSwingRead(use);
    if (!swing) {
      if (note) note.textContent = "No clear swing for Fibs yet.";
      if (banner) banner.textContent = "Waiting for a clean swing.";
      renderFibLevels(null);
      return;
    }
    const paint = () => {
      paintFibChart($("fibs-chart"), use, swing, symbol, frame);
      renderFibLevels(swing);
    };
    requestAnimationFrame(() => requestAnimationFrame(paint));
    const best = fibPriceAt(swing, 61.8);
    const e127p = fibExtPrice(swing, TOS_FIB_EXT[0]);
    const e127m = fibExtPrice(swing, TOS_FIB_EXT[4]);
    if (banner) {
      banner.textContent =
        symbol +
        " · " +
        frame +
        (swing.upswing ? " upswing" : " downswing") +
        " · 61.8 " +
        best.toFixed(2) +
        " · 127.2+ " +
        e127p.toFixed(2) +
        " · 127.2− " +
        e127m.toFixed(2);
    }
    if (note) {
      note.textContent =
        "Fibs Lab · " + symbol + " · " + frame + " · toggles above. Not on the main Chart. Paper only.";
    }
  }

  function bindFibsUi() {
    if ($("fibs-run")) $("fibs-run").addEventListener("click", () => runFibsPage());
    if ($("fibs-symbol")) {
      $("fibs-symbol").addEventListener("keydown", (event) => {
        if (event.key === "Enter") runFibsPage();
      });
    }
    const frames = $("fibs-frames");
    if (frames) {
      frames.querySelectorAll("button[data-fib-frame]").forEach((btn) => {
        btn.addEventListener("click", () => {
          fibState.frame = btn.dataset.fibFrame || "1D";
          saveFibPrefs();
          paintFibToggles();
          runFibsPage();
        });
      });
    }
    paintFibToggles();
  }

  root.PaperFibs = { runFibsPage, bindFibsUi, readFibPrefs };
  if (root.document.readyState === "loading") {
    root.document.addEventListener("DOMContentLoaded", bindFibsUi);
  } else {
    bindFibsUi();
  }
})(typeof window !== "undefined" ? window : globalThis);
