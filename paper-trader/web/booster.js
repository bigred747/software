/* Paper Trader desk booster — pace, lanes, and learn gap.
   Fail closed: never unlocks Live, never invents quotes, never skips Safe.
   Safe · Balanced · Boost · Max · Auto (slows when Auto/busy/soft; speeds when calm).
   1.10.43: snappy defaults — Boost is the everyday pace; Auto prefers Max when calm. */
(function (root) {
  "use strict";

  const KEY = "paper_trader_booster_v1";

  const PROFILES = {
    safe: {
      id: "safe",
      label: "Safe",
      why: "Careful quotes · small learn batch · fewer scan lanes. Use when Auto is heavy or the feed is soft.",
      quoteWait: 8000,
      quoteGap: 220,
      learnGap: 6000,
      batch: 2,
      lanesOffline: 3,
      lanesOnline: 5,
    },
    balanced: {
      id: "balanced",
      label: "Balanced",
      why: "Steady desk pace with room to breathe. Still snappy on closed-bar walks.",
      quoteWait: 4500,
      quoteGap: 100,
      learnGap: 3200,
      batch: 3,
      lanesOffline: 4,
      lanesOnline: 8,
    },
    boost: {
      id: "boost",
      label: "Boost",
      why: "Everyday fast desk. Tight quote gap, quick learn, wide scan lanes. Paper only · Live locked.",
      quoteWait: 2500,
      quoteGap: 50,
      learnGap: 1800,
      batch: 4,
      lanesOffline: 6,
      lanesOnline: 12,
    },
    max: {
      id: "max",
      label: "Max",
      why: "Snappiest closed-bar walks. Use when the feed is healthy. Safe check still gates Auto.",
      quoteWait: 1500,
      quoteGap: 40,
      learnGap: 1200,
      batch: 5,
      lanesOffline: 8,
      lanesOnline: 16,
    },
  };

  function load() {
    try {
      const raw = JSON.parse(localStorage.getItem(KEY) || "{}");
      const mode = raw && typeof raw.mode === "string" ? raw.mode : "boost";
      return {
        mode: mode === "safe" || mode === "balanced" || mode === "boost" || mode === "max" || mode === "auto" ? mode : "boost",
        applied: "",
        at: 0,
        note: "",
      };
    } catch (error) {
      return { mode: "boost", applied: "", at: 0, note: "" };
    }
  }

  function save(book) {
    try {
      localStorage.setItem(KEY, JSON.stringify({ mode: book.mode || "boost", at: book.at || 0 }));
    } catch (error) {
      /* Booster still holds for this page. */
    }
  }

  function profile(id) {
    return PROFILES[id] || PROFILES.boost;
  }

  function pickAuto(input) {
    const source = input || {};
    // Prefer speed. Only step down when the desk is clearly stressed.
    if (source.hidden) return "balanced";
    if (source.softHold && source.busyScanners >= 2) return "safe";
    if (source.softHold) return "balanced";
    if (source.autoOn && source.busyScanners >= 2) return "balanced";
    if (source.autoOn) return "boost";
    if (source.busyScanners >= 2) return "boost";
    if (source.feedWarm) return "max";
    return "boost";
  }

  function applyLive(settings) {
    if (typeof root.PaperLive === "undefined" || !root.PaperLive) return;
    if (typeof root.PaperLive.pace === "function") root.PaperLive.pace(settings.quoteWait);
    if (typeof root.PaperLive.gap === "function") root.PaperLive.gap(settings.quoteGap);
  }

  function resolve(book, context) {
    const mode = (book && book.mode) || "boost";
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
