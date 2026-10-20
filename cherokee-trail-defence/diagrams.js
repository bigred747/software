/* Original call-sheet pictures. Not game film. */
var CougarDiagrams = (function () {
  var SPECS = {
    "odd-cover3": spec("Odd Cover 3", "defense",
      "Four down linemen in an odd spacing, three linebackers, one safety in the post.",
      baseOffense(),
      [
        D(22, 52, "E"), D(36, 50, "T"), D(48, 50, "N"), D(64, 50, "E"),
        D(28, 38, "S"), D(48, 34, "M"), D(66, 38, "W"),
        D(8, 46, "C"), D(94, 46, "C"), D(34, 20, "$"), D(62, 16, "FS")
      ],
      [line(48, 34, 48, 56, "fit"), line(66, 38, 74, 56, "fit"), line(62, 16, 48, 28, "drop"), line(8, 46, 8, 30, "drop"), line(94, 46, 94, 30, "drop")]
    ),
    "nickel-quarters": spec("Nickel Cover 4", "defense",
      "Two safeties high, corners and nickel in match quarters. The picture of the 2026 base call.",
      baseOffense(),
      [
        D(24, 52, "E"), D(38, 50, "T"), D(56, 50, "T"), D(72, 52, "E"),
        D(34, 38, "W"), D(60, 38, "M"), D(82, 42, "N"),
        D(8, 40, "C"), D(96, 40, "C"), D(30, 18, "$"), D(72, 18, "FS")
      ],
      [line(30, 18, 18, 28, "drop"), line(72, 18, 84, 28, "drop"), line(8, 40, 8, 26, "drop"), line(96, 40, 96, 26, "drop"), line(34, 38, 40, 56, "fit"), line(60, 38, 52, 56, "fit")]
    ),
    "bear-goalline": spec("Bear goal line", "defense",
      "Five defenders on the line, linebackers in the gaps, one safety on the goal line.",
      goalOffense(),
      [
        D(22, 54, "E"), D(34, 52, "T"), D(46, 52, "N"), D(58, 52, "T"), D(74, 54, "E"),
        D(28, 40, "S"), D(46, 36, "M"), D(64, 40, "W"),
        D(10, 42, "C"), D(90, 42, "C"), D(50, 18, "FS")
      ],
      [line(28, 40, 30, 58, "fit"), line(46, 36, 46, 58, "fit"), line(64, 40, 66, 58, "fit"), line(50, 18, 50, 30, "drop")]
    ),
    "nickel-cover2": spec("Cover 2 sink", "defense",
      "Both safeties sink to the deep halves. Corners jam, then fall into the flats.",
      baseOffense(),
      [
        D(24, 52, "E"), D(38, 50, "T"), D(56, 50, "T"), D(72, 52, "E"),
        D(32, 38, "W"), D(50, 36, "M"), D(68, 38, "S"),
        D(12, 48, "C"), D(88, 48, "C"), D(28, 18, "$"), D(74, 18, "FS")
      ],
      [line(12, 48, 18, 58, "flat"), line(88, 48, 82, 58, "flat"), line(28, 18, 22, 30, "drop"), line(74, 18, 80, 30, "drop"), line(50, 36, 50, 48, "rob")]
    ),
    "sim-robber": spec("Sim pressure, robber", "defense",
      "Four rushers. One linebacker adds late. The other robs the crossing route.",
      baseOffense(),
      [
        D(24, 52, "E"), D(38, 50, "T"), D(56, 50, "T"), D(72, 52, "E"),
        D(30, 36, "W"), D(52, 34, "M"), D(78, 40, "N"),
        D(8, 44, "C"), D(96, 44, "C"), D(36, 18, "$"), D(70, 18, "FS")
      ],
      [line(30, 36, 26, 58, "blitz"), line(52, 34, 62, 48, "rob"), line(70, 18, 50, 28, "drop")]
    ),
    "dime-cushion": spec("Dime cushion", "defense",
      "Six defensive backs. Safeties start deeper. Use it only when the lead is safe.",
      emptyOffense(),
      [
        D(30, 54, "E"), D(42, 52, "T"), D(58, 52, "T"), D(70, 54, "E"),
        D(24, 40, "N"), D(76, 40, "N"),
        D(8, 36, "C"), D(96, 36, "C"), D(28, 14, "$"), D(50, 12, "FS"), D(74, 14, "$")
      ],
      [line(8, 36, 8, 20, "drop"), line(96, 36, 96, 20, "drop"), line(50, 12, 50, 24, "drop")]
    ),
    "sam-wrap": spec("Sam wrap", "defense",
      "The Sam wraps inside the end. The end spills everything outside.",
      baseOffense(),
      [
        D(22, 52, "E"), D(36, 50, "T"), D(50, 50, "N"), D(66, 50, "E"),
        D(16, 36, "S"), D(48, 34, "M"), D(70, 38, "W"),
        D(8, 46, "C"), D(94, 46, "C"), D(34, 18, "$"), D(68, 16, "FS")
      ],
      [path("M 16 36 Q 28 48 40 58", "blitz"), line(22, 52, 14, 60, "spill"), line(48, 34, 58, 56, "fit"), line(68, 16, 48, 26, "drop")]
    ),
    "will-wrap": spec("Will wrap", "defense",
      "The Will wraps from the back side and meets the pulling guard inside.",
      counterOffense(),
      [
        D(24, 52, "E"), D(38, 50, "T"), D(52, 50, "N"), D(68, 52, "E"),
        D(30, 38, "S"), D(48, 34, "M"), D(78, 36, "W"),
        D(8, 46, "C"), D(94, 46, "C"), D(32, 18, "$"), D(70, 16, "FS")
      ],
      [path("M 78 36 Q 64 46 54 58", "blitz"), line(68, 52, 80, 60, "spill"), line(30, 38, 40, 56, "fit")]
    ),
    "bandit-wrap": spec("Bandit wrap", "defense",
      "The safety inserts off the edge. The corner sinks to the flat he left.",
      tripsOffense(),
      [
        D(24, 52, "E"), D(38, 50, "T"), D(54, 50, "T"), D(70, 52, "E"),
        D(32, 38, "W"), D(52, 36, "M"), D(84, 34, "$"),
        D(8, 44, "C"), D(96, 42, "C"), D(40, 16, "FS"), D(78, 18, "N")
      ],
      [path("M 84 34 Q 92 46 98 58", "blitz"), line(96, 42, 86, 56, "flat"), line(40, 16, 55, 26, "drop")]
    ),
    "inside-zone": spec("Inside zone", "offense",
      "Downhill path at the play-side guard. The back cuts off the first color that shows.",
      [],
      genericDefense(),
      [line(46, 84, 52, 58, "run"), line(46, 74, 40, 66, "mesh")]
    , baseOffense()),
    "gt-counter": spec("Guard-tackle counter", "offense",
      "The back-side guard and tackle pull. The back takes the ball the other way.",
      [],
      genericDefense(),
      [line(36, 64, 62, 58, "block"), line(26, 64, 70, 56, "block"), line(46, 84, 72, 56, "run")]
    , baseOffense()),
    "boot": spec("Zone boot", "offense",
      "Fake the zone one way. The quarterback boots away and throws the flat or the crosser.",
      [],
      genericDefense(),
      [line(46, 84, 58, 60, "run"), path("M 46 74 Q 28 70 12 60", "route"), line(6, 62, 6, 48, "route"), line(84, 66, 60, 52, "route")]
    , baseOffense()),
    "mesh": spec("Mesh", "offense",
      "Two receivers cross under the linebackers. The ball comes out on time.",
      [],
      genericDefense(),
      [line(8, 62, 70, 56, "route"), line(94, 62, 28, 56, "route"), line(84, 66, 84, 48, "route")]
    , spreadOffense()),
    "yankee": spec("Yankee shot", "offense",
      "Play-action. One post, one over route from the other side.",
      [],
      genericDefense(),
      [line(46, 84, 56, 62, "run"), line(8, 62, 42, 28, "route"), line(94, 62, 58, 24, "route")]
    , baseOffense()),
    "stick": spec("Stick", "offense",
      "The slot sits at five yards. The outside man clears. Throw the sit if the flat is empty.",
      [],
      genericDefense(),
      [line(94, 62, 94, 40, "route"), line(84, 66, 78, 56, "route"), line(74, 62, 62, 50, "route")]
    , baseOffense()),
    "duo": spec("Duo at the goal line", "offense",
      "Double teams at the point of attack. The back's path is downhill, not sideways.",
      [],
      bearFront(),
      [line(38, 64, 40, 54, "block"), line(48, 64, 46, 54, "block"), line(46, 80, 44, 56, "run")]
    , goalOffense()),
    "screen": spec("Running-back screen", "offense",
      "The ends rush up the field. The back catches it behind them with two linemen out in front.",
      [],
      genericDefense(),
      [line(46, 84, 24, 70, "route"), line(26, 64, 16, 66, "block"), line(36, 64, 20, 62, "block")]
    , baseOffense()),
    "no-huddle": spec("No-huddle zone", "offense",
      "Same inside zone, snapped with the defense still pointing. The picture is the tempo, not a new play.",
      [],
      genericDefense(),
      [line(46, 84, 50, 56, "run"), line(94, 62, 94, 48, "route")]
    , spreadOffense()),
    "fake-punt": spec("Fake punt", "offense",
      "Spread punt. The up-back has the ball. The punter sells the kick.",
      [],
      puntDefense(),
      [line(40, 78, 18, 62, "run"), line(70, 70, 88, 58, "route")]
    , puntOffense()),
    "flea-flicker": spec("Flea flicker", "offense",
      "Handoff, pitch back to the quarterback, throw the vertical the safety left.",
      [],
      genericDefense(),
      [line(46, 74, 46, 80, "run"), line(46, 82, 46, 70, "run"), line(94, 62, 94, 24, "route"), line(6, 62, 30, 22, "route")]
    , baseOffense()),
    "reverse-pass": spec("Reverse pass", "offense",
      "Backward toss to the end around. He throws back across the field before the line.",
      [],
      genericDefense(),
      [line(46, 74, 78, 66, "run"), line(84, 66, 20, 48, "route")]
    , baseOffense()),
    "hook-and-ladder": spec("Hook and ladder", "offense",
      "Short catch moving sideways, backward pitch to the trailer.",
      [],
      genericDefense(),
      [line(94, 62, 94, 42, "route"), line(84, 66, 72, 58, "route"), line(74, 70, 58, 52, "run")]
    , tripsOffense()),
    "statue": spec("Statue of Liberty", "offense",
      "The quarterback cocks the throw. The back takes the ball out of that hand.",
      [],
      genericDefense(),
      [line(46, 74, 78, 50, "route"), line(46, 82, 22, 58, "run")]
    , baseOffense()),
    "fake-field-goal": spec("Fake field goal", "offense",
      "Holder takes the snap. The wing he is looking away from is the throw.",
      [],
      kickDefense(),
      [line(48, 78, 16, 60, "route"), line(78, 64, 86, 52, "route")]
    , kickOffense()),
    "jet-pop-pass": spec("Jet pop pass", "offense",
      "Jet motion takes a backward toss and throws the wheel, still behind the line.",
      [],
      genericDefense(),
      [line(84, 66, 70, 70, "run"), line(94, 62, 96, 28, "route")]
    , tripsOffense()),
    "screen-and-wheel": spec("Screen and wheel", "offense",
      "Pump the tunnel screen. Throw the wheel behind the end who chased it.",
      [],
      genericDefense(),
      [line(46, 84, 22, 68, "route"), path("M 84 66 Q 78 52 86 30", "route")]
    , tripsOffense())
  };

  function spec(title, side, caption, offense, defense, marks, offenseOverride) {
    return {
      title: title,
      side: side,
      caption: caption,
      offense: offenseOverride || offense || [],
      defense: defense || [],
      marks: marks || []
    };
  }

  function O(x, y, lb) { return { t: "O", x: x, y: y, lb: lb }; }
  function D(x, y, lb) { return { t: "D", x: x, y: y, lb: lb }; }
  function line(x1, y1, x2, y2, k) { return { k: k, x1: x1, y1: y1, x2: x2, y2: y2 }; }
  function path(d, k) { return { k: k, d: d }; }

  function baseOffense() {
    return [O(6, 62, "X"), O(26, 64, "T"), O(36, 64, "G"), O(46, 64, "C"), O(56, 64, "G"), O(66, 64, "T"), O(76, 64, "Y"), O(88, 68, "H"), O(96, 62, "Z"), O(46, 74, "Q"), O(46, 84, "R")];
  }
  function spreadOffense() {
    return [O(6, 62, "X"), O(18, 66, "H"), O(30, 64, "T"), O(40, 64, "G"), O(50, 64, "C"), O(60, 64, "G"), O(70, 64, "T"), O(84, 66, "Y"), O(96, 62, "Z"), O(50, 76, "Q"), O(42, 86, "R")];
  }
  function tripsOffense() {
    return [O(8, 62, "X"), O(28, 64, "T"), O(38, 64, "G"), O(48, 64, "C"), O(58, 64, "G"), O(68, 64, "T"), O(80, 66, "H"), O(90, 62, "Y"), O(98, 58, "Z"), O(48, 74, "Q"), O(40, 84, "R")];
  }
  function goalOffense() {
    return [O(18, 64, "T"), O(30, 64, "G"), O(42, 64, "C"), O(54, 64, "G"), O(66, 64, "T"), O(76, 64, "Y"), O(8, 62, "X"), O(92, 62, "Z"), O(42, 74, "Q"), O(42, 84, "R"), O(88, 68, "H")];
  }
  function emptyOffense() {
    return [O(4, 60, "X"), O(16, 64, "H"), O(30, 64, "T"), O(40, 64, "G"), O(50, 64, "C"), O(60, 64, "G"), O(70, 64, "T"), O(82, 64, "Y"), O(92, 60, "Z"), O(50, 76, "Q"), O(98, 68, "R")];
  }
  function counterOffense() {
    return baseOffense();
  }
  function puntOffense() {
    return [O(8, 58, "G"), O(22, 62, "T"), O(34, 64, "G"), O(46, 64, "C"), O(58, 64, "G"), O(70, 62, "T"), O(86, 58, "G"), O(40, 78, "U"), O(58, 86, "P"), O(16, 70, "W"), O(78, 70, "W")];
  }
  function kickOffense() {
    return [O(20, 64, "T"), O(32, 64, "G"), O(44, 64, "C"), O(56, 64, "G"), O(68, 64, "T"), O(14, 60, "W"), O(80, 62, "W"), O(48, 78, "H"), O(48, 92, "K"), O(8, 66, "S"), O(92, 66, "S")];
  }
  function genericDefense() {
    return [D(24, 52, "E"), D(38, 50, "T"), D(56, 50, "T"), D(72, 52, "E"), D(34, 38, "W"), D(58, 38, "M"), D(8, 44, "C"), D(96, 44, "C"), D(30, 22, "$"), D(72, 22, "FS"), D(82, 40, "N")];
  }
  function bearFront() {
    return [D(20, 52, "E"), D(32, 50, "T"), D(44, 50, "N"), D(56, 50, "T"), D(72, 52, "E"), D(28, 38, "S"), D(46, 34, "M"), D(64, 38, "W"), D(8, 44, "C"), D(92, 44, "C"), D(50, 18, "FS")];
  }
  function puntDefense() {
    return [D(18, 52, "E"), D(32, 50, "T"), D(46, 50, "N"), D(60, 50, "T"), D(76, 52, "E"), D(40, 40, "Spy"), D(8, 36, "C"), D(92, 36, "C"), D(28, 16, "$"), D(70, 16, "FS"), D(88, 48, "G")];
  }
  function kickDefense() {
    return [D(16, 54, "E"), D(30, 52, "T"), D(44, 52, "N"), D(58, 52, "T"), D(74, 54, "E"), D(48, 40, "Spy"), D(10, 42, "C"), D(90, 42, "C"), D(32, 20, "$"), D(66, 20, "FS"), D(84, 36, "W")];
  }

  function esc(text) {
    return String(text == null ? "" : text).replace(/[&<>"]/g, function (ch) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;" }[ch];
    });
  }

  var pictureSeq = 0;

  function render(id) {
    var diagram = SPECS[id];
    if (!diagram) return "";
    pictureSeq += 1;
    var marker = "arr" + pictureSeq;
    var marks = diagram.marks.map(function (mark) {
      var color = mark.k === "blitz" ? "#ffb4a8" : mark.k === "route" ? "#d6e6ff" : mark.k === "run" ? "#ffe08a" : mark.k === "block" ? "#f3d2a2" : "#f7fafc";
      var width = mark.k === "blitz" || mark.k === "run" ? 1.7 : 1.15;
      if (mark.d) {
        return '<path d="' + mark.d + '" fill="none" stroke="' + color + '" stroke-width="' + width + '" marker-end="url(#' + marker + ')"/>';
      }
      return '<line x1="' + mark.x1 + '" y1="' + mark.y1 + '" x2="' + mark.x2 + '" y2="' + mark.y2 + '" stroke="' + color + '" stroke-width="' + width + '" marker-end="url(#' + marker + ')"/>';
    }).join("");
    var people = diagram.offense.concat(diagram.defense).map(function (player) {
      if (player.t === "O") {
        return '<circle cx="' + player.x + '" cy="' + player.y + '" r="3.1" fill="#f7fafc" stroke="#10243a" stroke-width="0.6"/>' +
          '<text x="' + player.x + '" y="' + (player.y + 1.1) + '" text-anchor="middle" font-size="2.5" font-family="Segoe UI, sans-serif" fill="#10243a">' + esc(player.lb) + "</text>";
      }
      return '<text x="' + player.x + '" y="' + (player.y + 1.3) + '" text-anchor="middle" font-size="4.2" font-weight="700" font-family="Segoe UI, sans-serif" fill="#ffe08a">' + esc(player.lb) + "</text>";
    }).join("");
    return '<svg viewBox="0 0 104 100" role="img" aria-label="' + esc(diagram.title) + '">'+
      '<rect width="104" height="100" rx="2" fill="#1c6b3c"/>' +
      '<line x1="0" y1="63" x2="104" y2="63" stroke="rgba(255,255,255,.85)" stroke-width="0.8"/>' +
      '<line x1="0" y1="78" x2="104" y2="78" stroke="rgba(255,255,255,.25)" stroke-width="0.4"/>' +
      '<line x1="0" y1="48" x2="104" y2="48" stroke="rgba(255,255,255,.25)" stroke-width="0.4"/>' +
      '<defs><marker id="' + marker + '" markerWidth="4" markerHeight="4" refX="2" refY="2" orient="auto"><path d="M0,0 L4,2 L0,4 Z" fill="#ffe08a"/></marker></defs>' +
      marks + people +
      '<text x="3" y="8" font-size="3.4" font-family="Segoe UI, sans-serif" fill="#f7fafc">' + esc(diagram.title) + "</text>" +
      "</svg>";
  }

  function audit(book, tricks) {
    var missing = [];
    (book.calls || []).forEach(function (call) {
      if (!SPECS[call.id] || SPECS[call.id].side !== "defense") missing.push(call.id);
    });
    (book.offense || []).forEach(function (play) {
      if (!SPECS[play.id] || SPECS[play.id].side !== "offense") missing.push(play.id);
    });
    (tricks || []).forEach(function (trick) {
      if (!SPECS[trick.id]) missing.push(trick.id);
    });
    return { ok: missing.length === 0, missing: missing, count: Object.keys(SPECS).length };
  }

  return { render: render, audit: audit, get: function (id) { return SPECS[id]; }, ids: Object.keys(SPECS) };
})();
