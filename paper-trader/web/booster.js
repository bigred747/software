/* Paper Trader desk booster — pace, lanes, and learn gap.
   Fail closed: never unlocks Live, never invents quotes, never skips Safe.
   Safe · Balanced · Boost · Max · Auto (slows when Auto/busy/soft; speeds when calm). */
(function (root) {
  "use strict";

  const KEY = "paper_trader_booster_v1";

  const PROFILES = {
    safe: {
      id: "safe",
      label: "Safe",
      why: "Slowest quotes · one learn name · few scan lanes. Best when Auto is on or the desk is stressed.",
      quoteWait: 16000,
      quoteGap: 520,
      learnGap: 14000,
      batch: 1,
      lanesOffline: 2,
      lanesOnline: 3,
    },
    balanced: {
      id: "balanced",
      label: "Balanced",
      why: "Default desk pace. Steady quotes and hourly learn without slamming the feed.",
      quoteWait: 12000,
      quoteGap: 300,
      learnGap: 10000,
      batch: 2,
      lanesOffline: 3,
      lanesOnline: 6,
    },
    boost: {
      id: "boost",
      label: "Boost",
      why: "Faster scans and tighter quote gap when the desk is calm. Still paper-only · Live locked.",
      quoteWait: 7000,
      quoteGap: 180,
      learnGap: 6000,
      batch: 3,
      lanesOffline: 4,
      lanesOnline: 8,
    },
    max: {
      id: "max",
      label: "Max",
      why: "Fastest closed-bar walks. Use only when the feed is healthy. Safe check still gates Auto.",
      quoteWait: 4500,
      quoteGap: 110,
      learnGap: 4000,
      batch: 4,
      lanesOffline: 5,
      lanesOnline: 10,
    },
  };

  function load() {
    try {
      const raw = JSON.parse(localStorage.getItem(KEY) || "{}");
      const mode = raw && typeof raw.mode === "string" ? raw.mode : "auto";
      return {
        mode: mode === "safe" || mode === "balanced" || mode === "boost" || mode === "max" || mode === "auto" ? mode : "auto",
        applied: "",
        at: 0,
        note: "",
      };
    } catch (error) {
      return { mode: "auto", applied: "", at: 0, note: "" };
    }
  }

  function save(book) {
    try {
      localStorage.setItem(KEY, JSON.stringify({ mode: book.mode || "auto", at: book.at || 0 }));
    } catch (error) {
      /* Booster still holds for this page. */
    }
  }

  function profile(id) {
    return PROFILES[id] || PROFILES.balanced;
  }

  function pickAuto(input) {
    const source = input || {};
    if (source.hidden) return "safe";
    if (source.autoOn) return "safe";
    if (source.softHold) return "safe";
    if (source.busyScanners >= 2) return "safe";
    if (source.busyScanners >= 1) return "balanced";
    if (source.feedWarm) return "boost";
    return "balanced";
  }

  function applyLive(settings) {
    if (typeof root.PaperLive === "undefined" || !root.PaperLive) return;
    if (typeof root.PaperLive.pace === "function") root.PaperLive.pace(settings.quoteWait);
    if (typeof root.PaperLive.gap === "function") root.PaperLive.gap(settings.quoteGap);
  }

  function resolve(book, context) {
    const mode = (book && book.mode) || "auto";
    const applied = mode === "auto" ? pickAuto(context) : mode;
    const settings = Object.assign({}, profile(applied));
    settings.mode = mode;
    settings.applied = applied;
    settings.auto = mode === "auto";
    return settings;
  }

  root.PaperBooster = {
    KEY,
    PROFILES,
    load,
    save,
    profile,
    pickAuto,
    applyLive,
    resolve,
  };
})(typeof globalThis !== "undefined" ? globalThis : window);
