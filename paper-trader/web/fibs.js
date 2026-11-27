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

  const FIB_PREF_KEY = "paperTraderFibPrefsLab71";
  const fibState = {
    frame: "1D",
    on: {},
    mode: "auto", // auto | manual
    manual: null, // { high, low, from, to, upswing }
    pick: null, // first click while setting manual anchors
    last: null, // { bars, swing, symbol, frame }
    map: null, // last paint hit-test map
  };

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
        if (saved.mode === "auto" || saved.mode === "manual") fibState.mode = saved.mode;
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
      localStorage.setItem(
        FIB_PREF_KEY,
        JSON.stringify({ frame: fibState.frame, on: fibState.on, mode: fibState.mode })
      );
    } catch (error) {
      /* ignore */
    }
  }

  /** Fractal pivots: high/low greater/less than `left` bars on each side. */
  function fibPivots(bars, left) {
    const width = Math.max(2, left || 3);
    const highs = [];
    const lows = [];
    const list = bars || [];
    for (let i = width; i < list.length - width; i += 1) {
      const high = Number(list[i].high);
      const low = Number(list[i].low);
      if (!(high > 0) || !(low > 0)) continue;
      let isHigh = true;
      let isLow = true;
      for (let j = i - width; j <= i + width; j += 1) {
        if (j === i) continue;
        if (Number(list[j].high) >= high) isHigh = false;
        if (Number(list[j].low) <= low) isLow = false;
      }
      if (isHigh) highs.push({ index: i, price: high });
      if (isLow) lows.push({ index: i, price: low });
    }
    return { highs, lows };
  }

  /**
   * Thinkorswim-style swing: most recent significant pivot high + pivot low.
   * Prefers the latest completed swing (low→high or high→low) with enough range.
   */
  function fibSwingRead(bars) {
    const list = (bars || []).slice();
    if (list.length < 30) return null;
    const window = list.slice(-160);
    const base = list.length - window.length;
    const piv = fibPivots(window, 3);
    let hi = null;
    let lo = null;
    if (piv.highs.length && piv.lows.length) {
      // Take the latest high and the latest low that form a clean swing.
      const lastHigh = piv.highs[piv.highs.length - 1];
      const lastLow = piv.lows[piv.lows.length - 1];
      // Pair each with the best opposite pivot before/after for a real move.
      if (lastLow.index < lastHigh.index) {
        // upswing candidate: low then high
        let bestLow = lastLow;
        for (let i = piv.lows.length - 1; i >= 0; i -= 1) {
          if (piv.lows[i].index < lastHigh.index && piv.lows[i].price <= bestLow.price) bestLow = piv.lows[i];
        }
        hi = lastHigh;
        lo = bestLow;
      } else {
        // downswing candidate: high then low
        let bestHigh = lastHigh;
        for (let i = piv.highs.length - 1; i >= 0; i -= 1) {
          if (piv.highs[i].index < lastLow.index && piv.highs[i].price >= bestHigh.price) bestHigh = piv.highs[i];
        }
        hi = bestHigh;
        lo = lastLow;
      }
    }
    // Fallback: window extreme high/low (old behavior) if pivots are thin.
    if (!hi || !lo) {
      let max = -Infinity;
      let min = Infinity;
      let hiAt = 0;
      let loAt = 0;
      window.forEach((bar, index) => {
        const high = Number(bar.high);
        const low = Number(bar.low);
        if (!(high > 0) || !(low > 0)) return;
        if (high >= max) {
          max = high;
          hiAt = index;
        }
        if (low <= min) {
          min = low;
          loAt = index;
        }
      });
      if (!(max > min)) return null;
      hi = { index: hiAt, price: max };
      lo = { index: loAt, price: min };
    }
    if (!(hi.price > lo.price)) return null;
    const upswing = lo.index <= hi.index;
    return {
      upswing,
      low: lo.price,
      high: hi.price,
      from: base + Math.min(lo.index, hi.index),
      to: base + Math.max(lo.index, hi.index),
      lowAt: base + lo.index,
      highAt: base + hi.index,
      close: Number(list[list.length - 1].close) || hi.price,
      span: hi.price - lo.price,
      method: piv.highs.length && piv.lows.length ? "pivot" : "range",
    };
  }

  function swingFromAnchors(a, b) {
    if (!a || !b) return null;
    const high = Math.max(a.price, b.price);
    const low = Math.min(a.price, b.price);
    if (!(high > low)) return null;
    const highAt = a.price >= b.price ? a.index : b.index;
    const lowAt = a.price >= b.price ? b.index : a.index;
    return {
      upswing: lowAt <= highAt,
      low,
      high,
      from: Math.min(a.index, b.index),
      to: Math.max(a.index, b.index),
      lowAt,
      highAt,
      close: b.price,
      span: high - low,
      method: "manual",
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

  /** How close last close is to a level (ATR-normalized when possible). */
  function fibConfluence(swing, bars, rows) {
    if (!swing || !bars || !bars.length || !rows || !rows.length) return null;
    const close = Number(bars[bars.length - 1].close);
    if (!(close > 0)) return null;
    let atr = swing.span / 14;
    if (bars.length > 20) {
      let sum = 0;
      let n = 0;
      for (let i = bars.length - 14; i < bars.length; i += 1) {
        if (i < 1) continue;
        const tr = Math.max(
          Number(bars[i].high) - Number(bars[i].low),
          Math.abs(Number(bars[i].high) - Number(bars[i - 1].close)),
          Math.abs(Number(bars[i].low) - Number(bars[i - 1].close))
        );
        if (tr > 0) {
          sum += tr;
          n += 1;
        }
      }
      if (n) atr = sum / n;
    }
    const band = Math.max(atr * 0.35, swing.span * 0.004);
    const hits = [];
    rows.forEach((row) => {
      const price = row.kind === "ext" ? fibExtPrice(swing, row) : fibPriceAt(swing, row.pct);
      if (!Number.isFinite(price)) return;
      const dist = Math.abs(close - price);
      if (dist <= band) {
        hits.push({
          label: row.label,
          price,
          dist,
          side: close >= price ? "above" : "below",
          color: row.color,
        });
      }
    });
    hits.sort((a, b) => a.dist - b.dist);
    return { close, atr, band, hits };
  }

  function visibleFibRows() {
    readFibPrefs();
    return TOS_FIB_RETRACE.concat(TOS_FIB_EXT).filter((row) => fibState.on[row.id]);
  }

  function setToggleGroup(kind, value) {
    readFibPrefs();
    const list = kind === "ret" ? TOS_FIB_RETRACE : TOS_FIB_EXT;
    list.forEach((row) => {
      fibState.on[row.id] = !!value;
    });
    saveFibPrefs();
    paintFibToggles();
    runFibsPage({ quiet: true });
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
    const autoBtn = $("fibs-mode-auto");
    const manBtn = $("fibs-mode-manual");
    if (autoBtn) autoBtn.classList.toggle("on", fibState.mode === "auto");
    if (manBtn) manBtn.classList.toggle("on", fibState.mode === "manual");
  }

  function paintFibChart(canvas, bars, swing, symbol, frameId) {
    if (!canvas || !bars || !bars.length || !swing) return;
    const rows = visibleFibRows();
    const box = canvas.parentElement;
    const cssW = Math.max(320, (box && box.clientWidth) || canvas.clientWidth || 640);
    const cssH = 400;
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
    const padRight = 96;
    const padTop = 30;
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

    // Mark swing anchors
    const mark = (index, price, color, tag) => {
      const vi = Math.max(0, Math.min(view.length - 1, index - dropped));
      const x = xAt(vi);
      const yy = y(price);
      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.arc(x, yy, 4.5, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = "#e8eef5";
      ctx.font = "700 11px Segoe UI, sans-serif";
      ctx.fillText(tag, x + 6, yy - 6);
    };
    mark(swing.highAt != null ? swing.highAt : swing.to, swing.high, "#f5f7fa", "H");
    mark(swing.lowAt != null ? swing.lowAt : swing.from, swing.low, "#94a3b8", "L");

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

    const conf = fibConfluence(swing, bars, rows);
    rows.forEach((row) => {
      const price = row.kind === "ext" ? fibExtPrice(swing, row) : fibPriceAt(swing, row.pct);
      if (!Number.isFinite(price)) return;
      const yy = y(price);
      const near = conf && conf.hits.some((hit) => hit.label === row.label);
      ctx.strokeStyle = row.color;
      ctx.lineWidth = near ? (row.weight || 2) + 1.2 : row.weight || 2;
      ctx.setLineDash(row.kind === "ext" ? [6, 4] : []);
      ctx.beginPath();
      ctx.moveTo(padLeft, yy);
      ctx.lineTo(width - padRight, yy);
      ctx.stroke();
      ctx.setLineDash([]);
      const tag = row.label + "  " + price.toFixed(price >= 100 ? 2 : 3) + (near ? " ★" : "");
      ctx.font = "700 12px Segoe UI, sans-serif";
      const tw = ctx.measureText(tag).width + 10;
      ctx.fillStyle = "rgba(5,8,12,0.9)";
      ctx.fillRect(width - padRight + 2, yy - 9, tw, 16);
      ctx.fillStyle = row.color;
      ctx.fillText(tag, width - padRight + 6, yy + 3);
    });

    ctx.fillStyle = "#e8eef5";
    ctx.font = "700 15px Segoe UI, sans-serif";
    const method = swing.method === "manual" ? "manual anchors" : swing.method === "pivot" ? "pivot swing" : "range swing";
    ctx.fillText((symbol || "AMD") + " · " + (frameId || "1D") + " · " + method, padLeft, 18);

    if (fibState.mode === "manual" && fibState.pick) {
      ctx.fillStyle = "#fbbf24";
      ctx.font = "12px Segoe UI, sans-serif";
      ctx.fillText("Click a second candle to finish the Fib swing…", padLeft, height - 6);
    }

    fibState.map = { bars: view, dropped, padLeft, padRight, slot, width, height, y, low, high };
  }

  function renderFibLevels(swing, bars) {
    const body = $("fibs-levels");
    if (!body) return;
    if (!swing) {
      body.innerHTML = "";
      return;
    }
    const rows = visibleFibRows();
    const conf = fibConfluence(swing, bars, rows);
    body.innerHTML = rows
      .map((row) => {
        const price = row.kind === "ext" ? fibExtPrice(swing, row) : fibPriceAt(swing, row.pct);
        const meta = fibRole(row, swing.upswing);
        const near = conf && conf.hits.some((hit) => hit.label === row.label);
        return (
          "<tr><td style=\"color:" +
          row.color +
          "\">" +
          row.label +
          (near ? " ★" : "") +
          "</td><td>" +
          (Number.isFinite(price) ? price.toFixed(price >= 100 ? 2 : 3) : "—") +
          "</td><td>" +
          meta.role +
          "</td><td class=\"" +
          meta.klass +
          "\">" +
          meta.use +
          (near ? " · near close" : "") +
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

  function activeSwing(bars) {
    if (fibState.mode === "manual" && fibState.manual) {
      const swing = Object.assign({}, fibState.manual);
      swing.close = Number(bars[bars.length - 1].close) || swing.high;
      return swing;
    }
    return fibSwingRead(bars);
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
      root.PaperFrames && PaperFrames.closedBars ? PaperFrames.closedBars(bars, false) : bars.slice(0, -1);
    const use = settled && settled.length >= 20 ? settled : bars;
    const swing = activeSwing(use);
    if (!swing) {
      if (note) note.textContent = "No clear swing for Fibs yet. Try Manual and click two candles.";
      if (banner) banner.textContent = "Waiting for a clean swing.";
      renderFibLevels(null);
      return;
    }
    fibState.last = { bars: use, swing, symbol, frame };
    const paint = () => {
      paintFibChart($("fibs-chart"), use, swing, symbol, frame);
      renderFibLevels(swing, use);
    };
    requestAnimationFrame(() => requestAnimationFrame(paint));

    const best = fibPriceAt(swing, 61.8);
    const e127p = fibExtPrice(swing, TOS_FIB_EXT[0]);
    const e127m = fibExtPrice(swing, TOS_FIB_EXT[4]);
    const conf = fibConfluence(swing, use, visibleFibRows());
    const near =
      conf && conf.hits.length
        ? " · Near " + conf.hits.slice(0, 2).map((h) => h.label).join(" · ")
        : "";
    if (banner) {
      banner.textContent =
        symbol +
        " · " +
        frame +
        (swing.upswing ? " upswing" : " downswing") +
        " · " +
        (swing.method || "swing") +
        " · 61.8 " +
        best.toFixed(2) +
        " · 127.2+ " +
        e127p.toFixed(2) +
        " · 127.2− " +
        e127m.toFixed(2) +
        near;
    }
    if (note) {
      note.textContent =
        fibState.mode === "manual"
          ? "Manual Fibs · click two candles (high then low or low then high). Toggle levels above. Paper only."
          : "Auto pivot Fibs · " +
            symbol +
            " · " +
            frame +
            ". Toggle levels · switch to Manual to set your own swing. Paper only.";
    }
  }

  function pickBarFromEvent(event) {
    const map = fibState.map;
    const canvas = $("fibs-chart");
    if (!map || !canvas || !map.bars || !map.bars.length) return null;
    const rect = canvas.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / Math.max(1, rect.width)) * map.width;
    const index = Math.max(0, Math.min(map.bars.length - 1, Math.floor((x - map.padLeft) / map.slot)));
    const bar = map.bars[index];
    if (!bar) return null;
    const absIndex = map.dropped + index;
    const mid = (Number(bar.high) + Number(bar.low)) / 2;
    // Prefer extreme nearer the click Y for anchor price.
    const yRatio = (event.clientY - rect.top) / Math.max(1, rect.height);
    const priceGuess = map.high - yRatio * (map.high - map.low);
    const price =
      Math.abs(priceGuess - Number(bar.high)) <= Math.abs(priceGuess - Number(bar.low))
        ? Number(bar.high)
        : Number(bar.low);
    return { index: absIndex, price: Number.isFinite(price) ? price : mid, bar };
  }

  function onFibChartClick(event) {
    if (fibState.mode !== "manual") return;
    const pick = pickBarFromEvent(event);
    if (!pick) return;
    if (!fibState.pick) {
      fibState.pick = pick;
      const note = $("fibs-note");
      if (note) note.textContent = "First anchor set at " + pick.price.toFixed(2) + ". Click the second candle…";
      runFibsPage({ quiet: true });
      return;
    }
    const swing = swingFromAnchors(fibState.pick, pick);
    fibState.pick = null;
    if (!swing) {
      const note = $("fibs-note");
      if (note) note.textContent = "Need two different prices for a Fib swing. Try again.";
      return;
    }
    fibState.manual = swing;
    saveFibPrefs();
    runFibsPage();
  }

  function useOpenMarket() {
    const symbol =
      (root.state && root.state.paper && root.state.paper.symbol) ||
      (root.state && root.state.market && root.state.market.symbol) ||
      "";
    const input = $("fibs-symbol");
    if (input && symbol) {
      input.value = String(symbol).toUpperCase();
      runFibsPage();
    }
  }

  function bindFibsUi() {
    if ($("fibs-run")) $("fibs-run").addEventListener("click", () => runFibsPage());
    if ($("fibs-symbol")) {
      $("fibs-symbol").addEventListener("keydown", (event) => {
        if (event.key === "Enter") runFibsPage();
      });
    }
    if ($("fibs-use-market")) $("fibs-use-market").addEventListener("click", useOpenMarket);
    if ($("fibs-ret-all")) $("fibs-ret-all").addEventListener("click", () => setToggleGroup("ret", true));
    if ($("fibs-ret-none")) $("fibs-ret-none").addEventListener("click", () => setToggleGroup("ret", false));
    if ($("fibs-ext-all")) $("fibs-ext-all").addEventListener("click", () => setToggleGroup("ext", true));
    if ($("fibs-ext-none")) $("fibs-ext-none").addEventListener("click", () => setToggleGroup("ext", false));
    if ($("fibs-mode-auto")) {
      $("fibs-mode-auto").addEventListener("click", () => {
        fibState.mode = "auto";
        fibState.pick = null;
        saveFibPrefs();
        paintFibToggles();
        runFibsPage();
      });
    }
    if ($("fibs-mode-manual")) {
      $("fibs-mode-manual").addEventListener("click", () => {
        fibState.mode = "manual";
        fibState.pick = null;
        saveFibPrefs();
        paintFibToggles();
        const note = $("fibs-note");
        if (note) note.textContent = "Manual mode · click two candles to set the Fib swing.";
        runFibsPage({ quiet: true });
      });
    }
    const canvas = $("fibs-chart");
    if (canvas && !canvas.dataset.fibClickBound) {
      canvas.dataset.fibClickBound = "1";
      canvas.style.cursor = "crosshair";
      canvas.addEventListener("click", onFibChartClick);
    }
    const frames = $("fibs-frames");
    if (frames) {
      frames.querySelectorAll("button[data-fib-frame]").forEach((btn) => {
        btn.addEventListener("click", () => {
          fibState.frame = btn.dataset.fibFrame || "1D";
          fibState.manual = null;
          fibState.pick = null;
          saveFibPrefs();
          paintFibToggles();
          runFibsPage();
        });
      });
    }
    paintFibToggles();
  }

  // Exported for unit tests / app.js
  root.PaperFibs = {
    runFibsPage,
    bindFibsUi,
    readFibPrefs,
    fibSwingRead,
    fibPriceAt,
    fibExtPrice,
    fibConfluence,
    fibPivots,
    swingFromAnchors,
    TOS_FIB_RETRACE,
    TOS_FIB_EXT,
  };
  if (root.document.readyState === "loading") {
    root.document.addEventListener("DOMContentLoaded", bindFibsUi);
  } else {
    bindFibsUi();
  }
})(typeof window !== "undefined" ? window : globalThis);
