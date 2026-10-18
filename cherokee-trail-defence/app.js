(function () {
  var E = CougarEngine;
  var D = CougarDiagrams;
  var BASE = COUGAR_BOOK;
  var KEY = "cougar-defense-v1";
  var svgCache = Object.create(null);
  var photoUrls = [];
  var saveTimer = 0;
  var dbPromise = null;

  var PLAYERS = [
    ["learn", "Your room"]
  ];
  var LINE_MENU = [
    ["dline", "D-Line"]
  ];
  var COACH = [
    ["install", "Install"],
    ["diagnose", "Diagnose"],
    ["scout", "Scout"],
    ["rooms", "Rooms"],
    ["blitz", "Blitz"],
    ["alerts", "Alerts"],
    ["pictures", "Pictures"],
    ["film", "After the game"],
    ["oc", "OC sheet"],
    ["checks", "Checks"]
  ];
  var ROOMS = [["dl", "Line"], ["lb", "Backers"], ["s", "Safeties"], ["cb", "Corners"]];

  var state = loadState();
  state.model = state.model || E.emptyModel(BASE);
  state.overlay = state.overlay || { updates: [], added: [] };
  if (state.pack !== "full") {
    state.section = "install";
    state.pack = "full";
  }
  state.section = state.section || "install";
  var roomHash = String(location.hash || "").replace(/^#/, "");
  if (roomHash === "dl" || roomHash === "lb" || roomHash === "s" || roomHash === "cb") {
    state.room = roomHash;
    state.section = "learn";
  }
  if (roomHash === "diagnose") state.section = "diagnose";
  if (roomHash === "dline") state.section = "dline";
  if (roomHash === "film") state.section = "film";
  state.sit = state.sit || { down: 1, distance: 10, zone: "mid", hash: "middle" };
  state.photosOk = true;
  state.photoCount = 0;
  state.lastBeat = Date.now();

  function loadState() {
    try {
      var raw = localStorage.getItem(KEY);
      if (!raw) return {};
      var data = JSON.parse(raw);
      return data && typeof data === "object" ? data : {};
    } catch (err) {
      return {};
    }
  }

  function save() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () {
      try {
        localStorage.setItem(KEY, JSON.stringify({
          section: state.section,
          sit: state.sit,
          theme: state.theme,
          sideline: state.sideline,
          overlay: state.overlay,
          model: state.model,
          opponent: state.opponent,
          room: state.room,
          pack: "full",
          coachId: state.coachId,
          lastGameAdded: state.lastGameAdded || ""
        }));
        state.storageOk = true;
      } catch (err) {
        state.storageOk = false;
      }
    }, 250);
  }

  function book() {
    return E.mergeBook(BASE, state.overlay);
  }

  function today() {
    var d = new Date();
    return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
  }
  function pad(n) { return (n < 10 ? "0" : "") + n; }

  function esc(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (ch) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[ch];
    });
  }

  function picture(id) {
    if (!svgCache[id]) svgCache[id] = D.render(id) || "";
    return svgCache[id];
  }

  function query() {
    return {
      down: state.sit.down,
      distance: state.sit.distance,
      zone: state.sit.zone,
      hash: state.sit.hash
    };
  }

  function openDb() {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise(function (resolve, reject) {
      if (!window.indexedDB) { reject(new Error("no-db")); return; }
      var req = indexedDB.open("cougar-defense", 1);
      req.onupgradeneeded = function () {
        var db = req.result;
        if (!db.objectStoreNames.contains("photos")) {
          var store = db.createObjectStore("photos", { keyPath: "id" });
          store.createIndex("playId", "playId", { unique: false });
        }
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
    return dbPromise;
  }

  function withStore(mode, fn) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction("photos", mode);
        var store = tx.objectStore("photos");
        var result = fn(store);
        tx.oncomplete = function () { resolve(result); };
        tx.onerror = function () { reject(tx.error); };
      });
    });
  }

  function countPhotos() {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction("photos", "readonly");
        var req = tx.objectStore("photos").count();
        req.onsuccess = function () { resolve(req.result); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  function photosFor(playId) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction("photos", "readonly");
        var req = tx.objectStore("photos").index("playId").getAll(playId);
        req.onsuccess = function () { resolve(req.result || []); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  function shrink(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        var max = 1400;
        var scale = Math.min(1, max / Math.max(img.width, img.height));
        var canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(img.width * scale));
        canvas.height = Math.max(1, Math.round(img.height * scale));
        canvas.getContext("2d").drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
          URL.revokeObjectURL(url);
          if (!blob) reject(new Error("photo"));
          else resolve(blob);
        }, "image/jpeg", 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error("photo")); };
      img.src = url;
    });
  }

  function installPhoto(file, playId, side) {
    var okType = file && (file.type === "image/jpeg" || file.type === "image/png" || file.type === "image/webp");
    if (!okType || file.size > 8 * 1024 * 1024) {
      throw new Error("Use a JPG, PNG, or WebP under 8 MB.");
    }
    return shrink(file).then(function (blob) {
      var rec = {
        id: "pic-" + Date.now() + "-" + Math.floor(Math.random() * 1000),
        playId: E.safeId(playId),
        side: side === "defense" ? "defense" : "offense",
        name: E.safeText(file.name, 80),
        created: new Date().toISOString(),
        blob: blob
      };
      return openDb().then(function (db) {
        return new Promise(function (resolve, reject) {
          var tx = db.transaction("photos", "readwrite");
          tx.objectStore("photos").put(rec);
          tx.oncomplete = function () { resolve(rec); };
          tx.onerror = function () { reject(tx.error); };
        });
      });
    });
  }

  function removePhoto(id) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction("photos", "readwrite");
        tx.objectStore("photos").delete(id);
        tx.oncomplete = function () { resolve(); };
        tx.onerror = function () { reject(tx.error); };
      });
    });
  }

  function signals() {
    var pics = D.audit(BASE, E.TRICKS);
    return E.signals(book(), {
      publishedBook: BASE,
      now: Date.now(),
      lastBeat: state.lastBeat,
      storageOk: state.storageOk !== false,
      photosOk: state.photosOk,
      photoCount: state.photoCount,
      picturesOk: pics.ok,
      pictureDetail: pics.ok ? pics.count + " play pictures are stored in this program" : "Missing picture: " + pics.missing[0],
      lastGameAdded: state.lastGameAdded || "",
      updateReady: true
    });
  }

  function paint() {
    document.body.classList.toggle("night", state.theme === "night");
    document.body.classList.toggle("sideline", !!state.sideline);
    var board = signals();
    document.getElementById("nav").innerHTML = navHtml();
    document.getElementById("top").innerHTML = topHtml(board);
    document.getElementById("view").innerHTML = viewHtml(board);
    revokePhotos();
    hydrate();
  }

  function navButtons(items) {
    return items.map(function (item) {
      return '<button type="button" data-go="' + item[0] + '"' + (state.section === item[0] ? ' aria-current="page"' : "") + ">" + item[1] + "</button>";
    }).join("");
  }

  function navHtml() {
    return '<div class="brand"><b>Ashton Torres</b><span>Defense</span></div>' +
      '<div class="nav-label">Players</div>' + navButtons(PLAYERS) +
      '<div class="nav-label">' + esc(E.LINE_COACH) + '</div>' + navButtons(LINE_MENU) +
      '<div class="nav-label">Coach</div>' + navButtons(COACH) +
      '<div class="spacer"></div><div class="quiet">On this computer only. Not a website.</div>';
  }

  function topHtml(board) {
    var bad = board.rows.filter(function (row) { return !row.ok; }).length;
    var pill = bad ? '<span class="pill bad"><i class="dot"></i>' + bad + " to check</span>" : '<span class="pill"><i class="dot"></i>Ready</span>';
    return "<div><h1>" + esc(title()) + "</h1><p>" + esc(subtitle()) + "</p></div>" +
      '<div class="tools">' + pill +
      '<button type="button" data-act="theme">' + (state.theme === "night" ? "Light" : "Film room") + "</button>" +
      '<button type="button" data-act="size">' + (state.sideline ? "Office size" : "Sideline size") + "</button></div>";
  }

  function title() {
    return ({
      learn: "Your job",
      dline: "Defensive line",
      install: "This week's install",
      diagnose: "Diagnose",
      scout: "Scout by opponent",
      rooms: "Position rooms",
      blitz: "Wrap blitzes",
      alerts: "Trick alerts",
      pictures: "Play pictures",
      film: "After the game",
      oc: "Offensive sheet",
      checks: "Program checks"
    })[state.section] || "Ashton Torres";
  }
  function subtitle() {
    if (state.section === "dline") return E.LINE_COACH + ". Line calls only. The other rooms stay off this page.";
    if (state.section === "learn") return state.room ? "This card is only your room. The rest of the program is still in the menu." : "Tap your room. The rest of the program stays in the menu.";
    if (state.section === "diagnose") return "Full read for the coach. The install, scout, blitzes, and pictures are still in the menu.";
    if (state.section === "install") return "Cherokee Trail · Aurora · the call, the four rooms, and the blitz";
    if (state.section === "oc") return "Hand this sheet to the offensive coordinator. It is not the defensive install.";
    if (state.section === "pictures") return "Every defense and every offensive play has a picture. Install a photo of a new look under it.";
    if (state.section === "film") return "Update weekly. The score stays on this computer.";
    return "Standalone program. Film you add stays on this device.";
  }

  function viewHtml() {
    if (state.section === "dline") return dlineHtml();
    if (state.section === "learn") return learnHtml();
    if (state.section === "diagnose") return diagnoseHtml();
    if (state.section === "install") return installHtml();
    if (state.section === "scout") return scoutHtml();
    if (state.section === "rooms") return roomsHtml();
    if (state.section === "blitz") return blitzHtml();
    if (state.section === "alerts") return alertsHtml();
    if (state.section === "pictures") return picturesHtml();
    if (state.section === "film") return filmHtml();
    if (state.section === "oc") return ocHtml();
    return checksHtml();
  }

  function dlineHtml() {
    var live = book();
    var game = E.nextGame(live, today());
    var defense = E.bestDefense(live, { game: game, down: 1, distance: 10, zone: "mid", hash: "middle" }, state.model);
    var plays = E.linePlays();
    var week = plays.filter(function (play) { return play.id === defense.call.id; })[0] || plays[0];
    var rest = plays.filter(function (play) { return play.id !== week.id; });
    state.copyText = [E.LINE_COACH, "DEFENSIVE LINE", week.name, week.simple, "Run. " + week.run, "Pass. " + week.pass, "Do not. " + week.avoid].join("\n");
    return '<section class="dl-banner"><div class="who">Defensive line</div><h2>' + esc(E.LINE_COACH) + '</h2>' +
      '<p>' + esc(when(game)) + '</p><p class="job">' + esc(week.name) + '. ' + esc(week.simple) + '</p>' +
      '<button class="btn" type="button" data-act="copy">Copy this week\'s line card</button></section>' +
      lineCard(week, true) +
      '<h3 class="dl-more">Every other line call</h3>' +
      rest.map(function (play) { return lineCard(play, false); }).join("");
  }

  function lineCard(play, week) {
    var spots = play.spots.map(function (spot) {
      return '<div class="dl-spot"><b>' + esc(spot.spot) + '</b><span>' + esc(spot.line) + '</span></div>';
    }).join("");
    return '<article class="card dl-play">' + (week ? '<div class="kicker">This week, in the room</div>' : '') +
      '<h3>' + esc(play.name) + '</h3><p class="job">' + esc(play.simple) + '</p>' + spots +
      '<p><b>Run.</b> ' + esc(play.run) + '</p><p><b>Pass.</b> ' + esc(play.pass) + '</p>' +
      '<p class="muted"><b>Do not.</b> ' + esc(play.avoid) + '</p></article>';
  }

  function learnHtml() {
    var live = book();
    var game = E.nextGame(live, today());
    var defense = E.bestDefense(live, { game: game, down: 1, distance: 10, zone: "mid", hash: "middle" }, state.model);
    var names = { dl: "Line", lb: "Backers", s: "Safeties", cb: "Corners" };
    var group = names[state.room] ? state.room : "";
    var picks = '<div class="rooms-pick">' + ROOMS.map(function (item) {
      return '<button type="button" data-room="' + item[0] + '"' + (group === item[0] ? ' aria-pressed="true"' : "") + ">" + item[1] + "</button>";
    }).join("") + "</div>";
    var head = '<section class="hero learn"><div class="kicker">' + esc(when(game)) + "</div>" +
      "<p class=\"step\">1. The call</p><h2 class=\"call-name\">" + esc(defense.call.name) + "</h2>" +
      "<p class=\"brief\">" + esc(E.plainLine(defense.call.id, "say") || defense.call.brief) + "</p>";
    if (!group) {
      state.copyText = defense.call.name + "\nTap your room.";
      return head + "<p class=\"step\">2. Your room</p>" + picks + "<p class=\"job\">Tap your room. You will only see your job.</p></section>";
    }
    var mine = defense.call.positions[group];
    var job = E.plainLine(defense.call.id, group);
    var tricks = E.trickPlaysForGame(live, game);
    var goal = E.bestDefense(live, { game: game, down: 1, distance: 3, zone: "goal" }, state.model);
    var goalLine = goal.call.id === defense.call.id ? "" :
      "<p class=\"muted\">Inside the 5, the call is " + esc(goal.call.name) + ". " + esc(E.plainLine(goal.call.id, group)) + "</p>";
    var blitz = defense.wrap
      ? "<p class=\"step\">3. Your blitz</p><p class=\"job\">" + esc(defense.wrap.name) + ". " + esc(E.plainLine(defense.wrap.id, group)) + " We call it once. If the backfield is empty, we check out.</p>"
      : "";
    state.copyText = [
      defense.call.name,
      E.plainLine(defense.call.id, "say"),
      "I play " + names[group] + ".",
      mine.align + ".",
      job,
      defense.wrap ? "Blitz: " + defense.wrap.name + ". " + E.plainLine(defense.wrap.id, group) : "",
      "Watch: " + tricks.map(function (trick) { return trick.name + ". " + E.plainLine(trick.id, group); }).join(" ")
    ].filter(Boolean).join("\n");
    return head +
      "<p class=\"step\">2. Your job · " + esc(names[group]) + "</p><article class=\"room\"><p><b>" + esc(mine.align) + "</b></p><p class=\"job\">" + esc(job) + "</p></article>" +
      goalLine + blitz +
      "<p class=\"step\">4. Watch for this</p>" + tricks.map(function (trick) {
        return "<p><b>" + esc(trick.name) + ".</b> " + esc(E.plainLine(trick.id, group)) + "</p>";
      }).join("") +
      '<div class="chips"><button class="btn primary" type="button" data-act="copy">Copy my card</button></div>' +
      "<p class=\"step\">Change room</p>" + picks + "</section>";
  }

  function diagnoseHtml() {
    var live = book();
    var game = E.nextGame(live, today());
    var season = E.seasonByYear(live, game.season);
    var defense = E.bestDefense(live, Object.assign({ game: game }, query()), state.model);
    var tricks = E.trickPlaysForGame(live, game);
    var tells = defense.top.map(function (row) {
      return esc(row.label) + " " + esc(String(row.value));
    }).join(" · ");
    var ranks = defense.rows.slice(0, 6).map(function (row) {
      return "<li>" + esc(row.name) + " · score " + esc(String(row.score)) + "</li>";
    }).join("");
    var rooms = ["dl", "lb", "s", "cb"].map(function (group) {
      var card = defense.call.positions[group];
      var wrapJob = defense.wrap ? "<p><b>Blitz, " + esc(defense.wrap.name) + ".</b> " + esc(defense.wrap.positions[group].job) + "</p>" : "";
      return "<article class=\"card\"><h3>" + esc(card.title) + " · " + esc(card.align) + "</h3><p>" + esc(card.job) + "</p>" + wrapJob +
        "<p class=\"muted\">Player card: " + esc(E.plainLine(defense.call.id, group)) + "</p></article>";
    }).join("");
    var trickBlocks = tricks.map(function (trick) {
      var roomsText = ["dl", "lb", "s", "cb"].map(function (group) {
        var card = trick.positions[group];
        return "<p><b>" + esc(card.title) + ".</b> " + esc(card.job) + "</p>";
      }).join("");
      return "<article class=\"card\"><h3>" + esc(trick.name) + "</h3><p>" + esc(trick.defense) + "</p>" + roomsText +
        "<p class=\"muted\">" + esc(trick.why) + "</p></article>";
    }).join("");
    return '<section class="hero"><div class="kicker">' + esc(when(game)) + "</div>" +
      "<h2 class=\"call-name\">" + esc(defense.call.name) + "</h2>" +
      "<p>" + esc(defense.confidenceText) + "</p>" + sitChips() +
      "<p><b>Tells.</b> " + tells + "</p>" +
      "<p><b>Weight on this call.</b> " + esc(String(defense.weight)) + (defense.alternate ? ". Next call: " + esc(defense.alternate.call.name) + " · " + esc(String(defense.alternate.score)) : "") + "</p>" +
      "<ol>" + ranks + "</ol>" +
      "<p>" + defense.reasons.map(esc).join("</p><p>") + "</p>" +
      (season ? "<p>" + esc(season.why) + "</p>" : "") +
      "<p class=\"muted\">" + esc(defense.hashNote) + "</p>" +
      (defense.wrap ? "<p><b>" + esc(defense.wrap.name) + " · score " + esc(String(defense.wrapScore)) + ".</b> " + esc(defense.wrapAdvice) + "</p>" : "") +
      "</section>" + rooms + trickBlocks;
  }

  function weekLine(live, game) {
    var row = E.opponents(live).filter(function (item) { return item.name === game.opponent; })[0];
    var played = row ? row.games.filter(function (item) { return !item.upcoming; }) : [];
    if (!played.length) {
      return "First meeting in this book. No snap list was published for " + game.opponent + ". This is the season call, not a filmed game.";
    }
    var last = played[played.length - 1];
    var snaps = 0;
    played.forEach(function (item) {
      snaps += (item.plays || []).filter(function (play) { return play.text; }).length;
    });
    var line = "Series " + row.record.wins + "-" + row.record.losses + ". Last meeting " + last.date + ", " + scoreLine(last) + ".";
    if (!snaps) line += " No offensive snap list was published for those games.";
    return line;
  }

  function installHtml() {
    var live = book();
    var game = E.nextGame(live, today());
    var season = E.seasonByYear(live, game.season);
    var defense = E.bestDefense(live, Object.assign({ game: game }, query()), state.model);
    var tricks = E.trickPlaysForGame(live, game);
    state.copyText = copyText(game, defense, tricks);
    return '<div class="chips"><button type="button" class="chip" data-go="learn">Your room</button><button type="button" class="chip" data-go="dline">' + esc(E.LINE_COACH) + '</button><button type="button" class="chip" data-go="diagnose">Diagnose</button></div>' +
      '<section class="hero"><div class="kicker">' + esc(when(game)) + "</div>" +
      "<h2 class=\"call-name\">" + esc(defense.call.name) + "</h2>" +
      "<p class=\"brief\">" + esc(defense.call.brief) + "</p>" +
      '<p class="muted">' + esc(weekLine(live, game)) + "</p>" +
      "<p class=\"muted\">" + esc(defense.confidenceText) + "</p>" +
      sitChips() +
      (state.updateOpen ? updateForm(live, game) : '<div class="chips"><button class="btn primary update-all" type="button" data-act="open-update">Update weekly</button><button class="btn" type="button" data-act="copy">Copy the install</button></div>') +
      '<div class="grid"><div><div class="picture">' + picture(defense.call.id) + "</div>" +
      "<p class=\"caption\">" + esc((D.get(defense.call.id) || {}).caption || "") + "</p>" +
      photoSlot(defense.call.id, "defense") + "</div><div>" + reasons(defense, season) + wrapCard(defense) + "</div></div>" +
      roomsBlock(defense.call, game.coachId) +
      "<h3>Defend these tricks</h3>" + tricks.map(trickCard).join("") +
      "</section>";
  }

  function when(game) {
    var place = game.site === "away" ? "at " : "vs ";
    var clock = game.kickoff ? " · " + game.kickoff : "";
    var tag = game.upcoming ? "Not played yet" : (game.result === "conflict" ? "Result in dispute" : game.result + " " + game.ctScore + "-" + game.oppScore);
    return game.date + " " + place + game.opponent + clock + " · " + tag;
  }

  function sitChips() {
    var items = [
      ["1st and 10", 1, 10, "mid"],
      ["2nd and 6", 2, 6, "mid"],
      ["3rd and 1", 3, 1, "mid"],
      ["3rd and long", 3, 12, "mid"],
      ["Red zone", 1, 8, "red"],
      ["Goal line", 1, 3, "goal"]
    ];
    return '<div class="chips">' + items.map(function (item) {
      var on = state.sit.down === item[1] && state.sit.distance === item[2] && state.sit.zone === item[3];
      return '<button type="button" class="chip" data-sit="' + item[1] + "," + item[2] + "," + item[3] + '"' + (on ? ' aria-pressed="true"' : "") + ">" + item[0] + "</button>";
    }).join("") + "</div>";
  }

  function reasons(defense, season) {
    return "<div class=\"card\"><h3>Why this call</h3><p>" + defense.reasons.map(esc).join("</p><p>") + "</p>" +
      (season ? "<p>" + esc(season.why) + "</p>" : "") +
      "<p class=\"muted\">" + esc(defense.hashNote) + "</p></div>";
  }

  function wrapCard(defense) {
    if (!defense.wrap) return "";
    return '<div class="card" style="margin-top:10px"><h3>Blitz change-up · ' + esc(defense.wrap.name) + "</h3><p>" + esc(defense.wrapAdvice) + "</p>" +
      '<div class="picture">' + picture(defense.wrap.id) + "</div></div>";
  }

  function roomsBlock(call, coachId) {
    var note = "";
    var rooms = ["dl", "lb", "s", "cb"].map(function (group) {
      var card = E.positionCard(call, group, coachId);
      if (!note && card.coachNote) note = card.coachNote;
      var simple = E.plainLine(call.id, group);
      return '<article class="room"><h3>' + esc(card.title) + "</h3>" +
        (simple ? '<p class="job">' + esc(simple) + "</p>" : "") +
        "<p><b>" + esc(card.align) + "</b></p><p>" + esc(card.job) + "</p></article>";
    }).join("");
    return (note ? '<p class="muted">' + esc(note) + "</p>" : "") + '<div class="rooms">' + rooms + "</div>";
  }

  function trickCard(trick) {
    var label = trick.kind === "published" ? "They ran this" : "Your alert";
    return '<article class="card" style="margin-top:10px"><span class="tag' + (trick.kind === "published" ? " alert" : "") + '">' + label + "</span>" +
      "<h3>" + esc(trick.name) + "</h3><p>" + esc(trick.summary) + "</p><p>" + esc(trick.defense) + "</p>" +
      "<p class=\"muted\">" + esc(trick.why) + "</p><div class=\"picture\">" + picture(trick.id) + "</div>" +
      photoSlot(trick.id, "defense") + "</article>";
  }

  function photoSlot(playId, side) {
    return '<div class="thumbs" data-photos="' + esc(playId) + '"></div>' +
      '<div class="chips"><label class="btn">Install a photo<input type="file" accept="image/jpeg,image/png,image/webp" data-photo="' + esc(playId) + '" data-kind="' + (side === "offense" ? "offense" : "defense") + '" hidden></label></div>';
  }

  function scoutHtml() {
    var live = book();
    var rows = E.opponents(live).filter(function (row) {
      return !state.q || row.name.toLowerCase().indexOf(state.q) !== -1;
    });
    var selected = rows.filter(function (row) { return row.name === state.opponent; })[0] || rows[0];
    return '<input class="search" type="search" placeholder="Find an opponent" value="' + esc(state.q || "") + '" data-q="1">' +
      '<div class="grid"><div class="list">' + rows.map(function (row) {
        var rec = row.record;
        return '<button type="button" class="row" data-opp="' + esc(row.name) + '"><div class="split"><b>' + esc(row.name) + "</b><span>" + rec.wins + "-" + rec.losses + "</span></div><span class=\"muted\">" + row.games.length + " meetings</span></button>";
      }).join("") + "</div><div>" + (selected ? opponentDetail(live, selected) : "") + "</div></div>";
  }

  function opponentDetail(live, row) {
    var defense = E.seriesDefense(live, row.name, query(), state.model);
    var head = '<div class="hero"><div class="kicker">Series call</div><h2 class="call-name">' + esc(defense.call.name) + "</h2><p>" + esc(defense.call.brief) + "</p><p class=\"muted\">" +
      row.record.wins + "-" + row.record.losses + (row.record.disputes ? " · " + row.record.disputes + " result in dispute" : "") + "</p></div>";
    var games = row.games.slice().reverse().map(function (game) {
      var call = E.bestDefense(live, Object.assign({ game: game }, query()), state.model).call.name;
      var tricks = E.trickPlaysForGame(live, game).map(function (trick) { return trick.name; }).join(" · ");
      var plays = (game.plays || []).filter(function (play) { return play.text; });
      return '<article class="card"><div class="split"><b>' + esc(game.date) + "</b><span>" + esc(scoreLine(game)) + "</span></div>" +
        "<p>" + esc(call) + "</p>" +
        (plays.length ? "<ul>" + plays.map(function (play) { return "<li>" + esc(play.text) + "</li>"; }).join("") + "</ul>" : "<p class=\"muted\">No offensive snap list was published for this game.</p>") +
        ((game.special || []).length ? "<p class=\"muted\">Not an offensive play: " + game.special.map(function (play) { return esc(play.text); }).join(" ") + "</p>" : "") +
        "<p class=\"muted\">Tricks: " + esc(tricks) + "</p>" +
        (game.note ? "<p class=\"muted\">" + esc(game.note) + "</p>" : "") +
        "</article>";
    }).join("");
    return head + '<div class="list">' + games + "</div>";
  }

  function scoreLine(game) {
    if (game.upcoming) return "Not played";
    if (game.result === "conflict") return "Disputed";
    return game.result + " " + game.ctScore + "-" + game.oppScore + (game.overtime ? " " + game.overtime : "");
  }

  function roomsHtml() {
    var live = book();
    var coachId = state.coachId || "jajczyk";
    var group = state.room || "dl";
    var season = live.seasons.filter(function (item) { return item.coachId === coachId && !item.live; }).pop() || live.seasons.filter(function (item) { return item.coachId === coachId; }).pop();
    var defense = E.bestDefense(live, Object.assign({ season: season.id }, query()), state.model);
    var calls = ["nickel-quarters", "odd-cover3", "bear-goalline", "sam-wrap", "bandit-wrap"].map(function (id) {
      return live.calls.filter(function (call) { return call.id === id; })[0];
    });
    return '<div class="chips">' + live.coaches.map(function (coach) {
      return '<button type="button" class="chip" data-coach="' + coach.id + '"' + (coach.id === coachId ? ' aria-pressed="true"' : "") + ">" + esc(coach.name) + "</button>";
    }).join("") + "</div>" +
      '<div class="chips">' + [["dl", "Line"], ["lb", "Backers"], ["s", "Safeties"], ["cb", "Corners"]].map(function (item) {
        return '<button type="button" class="chip" data-room="' + item[0] + '"' + (group === item[0] ? ' aria-pressed="true"' : "") + ">" + item[1] + "</button>";
      }).join("") + "</div>" +
      calls.map(function (call) {
        var card = E.positionCard(call, group, coachId);
        return '<article class="card"><h3>' + esc(call.name) + " · " + esc(card.title) + "</h3><p><b>" + esc(card.align) + "</b></p><p>" + esc(card.job) + "</p><p class=\"muted\">" + esc(card.coachNote) + "</p></article>";
      }).join("") +
      '<p class="muted">Base call for the last ' + esc(season.coachName) + " season in this book: " + esc(defense.call.name) + ".</p>";
  }

  function blitzHtml() {
    var live = book();
    return live.calls.filter(function (call) { return call.family === "wrap"; }).map(function (call) {
      return '<article class="card"><h3>' + esc(call.name) + "</h3><p class=\"brief\">" + esc(call.brief) + "</p><div class=\"picture\">" + picture(call.id) + "</div>" +
        roomsBlock(call, "jajczyk") + photoSlot(call.id, "defense") + "</article>";
    }).join("");
  }

  function alertsHtml() {
    var live = book();
    var season = state.alertYear || 2026;
    var games = live.games.filter(function (game) { return game.season === Number(season); });
    return '<div class="chips">' + live.seasons.map(function (item) {
      return '<button type="button" class="chip" data-year="' + item.id + '"' + (item.id === Number(season) ? ' aria-pressed="true"' : "") + ">" + item.id + "</button>";
    }).join("") + "</div>" +
      '<div class="list">' + games.map(function (game) {
        var tricks = E.trickPlaysForGame(live, game);
        return '<article class="row"><div class="split"><b>' + esc(game.opponent) + "</b><span class=\"muted\">" + esc(scoreLine(game)) + "</span></div><p>" +
          tricks.map(function (trick) { return esc((trick.kind === "published" ? "Published: " : "") + trick.name); }).join(" · ") + "</p></article>";
      }).join("") + "</div>";
  }

  function picturesHtml() {
    var live = book();
    var side = state.side || "defense";
    var items = side === "defense" ? live.calls : live.offense;
    return '<div class="chips"><button type="button" class="chip" data-side="defense"' + (side === "defense" ? ' aria-pressed="true"' : "") + '>Defenses</button>' +
      '<button type="button" class="chip" data-side="offense"' + (side === "offense" ? ' aria-pressed="true"' : "") + ">Offense</button></div>" +
      '<div class="gallery">' + items.map(function (item) {
        var info = D.get(item.id) || {};
        return '<article class="card"><h3>' + esc(item.name) + "</h3><div class=\"picture\">" + picture(item.id) + "</div><p class=\"caption\">" + esc(info.caption || item.summary || "") + "</p>" + photoSlot(item.id, side) + "</article>";
      }).join("") + "</div>";
  }

  function updateForm(live, game) {
    var upcoming = live.games.filter(function (item) { return item.upcoming; });
    if (game && !upcoming.some(function (item) { return item.id === game.id; })) upcoming = [game].concat(upcoming);
    var options = upcoming.map(function (item) {
      return '<option value="' + esc(item.id) + '"' + (game && item.id === game.id ? " selected" : "") + ">" + esc(item.date + " " + item.opponent) + "</option>";
    }).join("") + '<option value="new">A different opponent</option>';
    return '<form id="week-form" class="update-form" autocomplete="off"><p>Enter this week\'s final and what they ran. Update weekly writes it into the install, the opponent card, and the trick alerts. Nothing leaves this computer.</p>' +
      '<label>Game</label><select name="game">' + options + "</select>" +
      '<div id="new-opponent" hidden><label>Opponent</label><input name="opponent" type="text" maxlength="40"><label>Date</label><input name="date" type="date"></div>' +
      '<div class="form-row"><div><label>Cherokee Trail score</label><input name="ct" type="number" min="0" max="99" inputmode="numeric"></div>' +
      '<div><label>Opponent score</label><input name="opp" type="number" min="0" max="99" inputmode="numeric"></div></div>' +
      '<label>What they ran</label><textarea name="play" maxlength="240" placeholder="Inside zone to the field, 6 yards"></textarea>' +
      '<label>Play type</label><select name="tag"><option value="run_inside">Inside run</option><option value="run_explosive">Explosive run</option><option value="pass_short">Short pass</option><option value="pass_explosive">Shot play</option><option value="goal_line">Goal line</option></select>' +
      '<label>Yards you allowed on that play</label><input name="yards" type="number" min="0" max="99" inputmode="numeric">' +
      '<div class="drop"><b>Install a photo of the play</b><p class="muted">JPG, PNG, or WebP. It is resized and kept on this device.</p><input name="photo" type="file" accept="image/jpeg,image/png,image/webp"></div>' +
      '<div class="chips"><button class="btn primary update-all" type="submit">Update weekly</button> <button class="btn" type="button" data-act="export">Save a backup file</button></div>' +
      '<label class="btn">Open a backup<input type="file" accept="application/json" data-import="1" hidden></label>' +
      '<p class="muted" id="film-note"></p></form>';
  }

  function filmHtml() {
    var live = book();
    var game = E.nextGame(live, today());
    return '<section class="hero"><div class="kicker">' + esc(when(game)) + "</div>" + updateForm(live, game) + "</section>";
  }

  function ocHtml() {
    var live = book();
    var year = state.ocYear || 2026;
    var situation = state.ocSit || "standard";
    var sheet = E.bestOffense(live, { season: year, situation: situation });
    var sits = [["standard", "Early down"], ["need_score", "Need a score"], ["red", "Red zone"], ["goal", "Goal line"], ["third_long", "3rd and long"]];
    return '<div class="chips">' + live.seasons.map(function (season) {
      return '<button type="button" class="chip" data-oc-year="' + season.id + '"' + (season.id === Number(year) ? ' aria-pressed="true"' : "") + ">" + season.id + "</button>";
    }).join("") + "</div><div class=\"chips\">" + sits.map(function (item) {
      return '<button type="button" class="chip" data-oc-sit="' + item[0] + '"' + (situation === item[0] ? ' aria-pressed="true"' : "") + ">" + item[1] + "</button>";
    }).join("") + "</div>" +
      (sheet.best ? '<article class="hero"><div class="kicker">Best offensive play</div><h2 class="call-name">' + esc(sheet.best.name) + "</h2><p>" + esc(sheet.best.summary) + "</p><p>" + esc(sheet.best.why) + "</p><div class=\"picture\">" + picture(sheet.best.id) + "</div>" + photoSlot(sheet.best.id, "offense") + "</article>" : "") +
      "<p>" + esc(sheet.plan || "") + "</p>" +
      sheet.also.map(function (play) {
        return '<article class="card"><h3>' + esc(play.name) + "</h3><p>" + esc(play.summary) + "</p></article>";
      }).join("");
  }

  function checksHtml() {
    var board = signals();
    return '<div class="checks">' + board.rows.map(function (row) {
      return '<article class="row check" data-signal="' + row.id + '"><i class="dot" style="background:' + (row.ok ? "var(--green)" : "var(--red)") + '"></i><div><b>' + esc(row.name) + "</b><div class=\"detail muted\">" + esc(row.detail) + "</div></div></article>";
    }).join("") + "</div>";
  }

  function copyText(game, defense, tricks) {
    var lines = ["ASHTON TORRES", when(game), weekLine(book(), game), "", defense.call.name, defense.call.brief, ""];
    ["dl", "lb", "s", "cb"].forEach(function (group) {
      var card = defense.call.positions[group];
      lines.push(card.title + ": " + card.align);
      lines.push(card.job);
      lines.push("");
    });
    if (defense.wrap) lines.push("Blitz: " + defense.wrap.name + ". " + defense.wrapAdvice);
    lines.push("Tricks: " + tricks.map(function (trick) { return trick.name; }).join(", "));
    return lines.join("\n");
  }

  function hydrate() {
    var nodes = document.querySelectorAll("[data-photos]");
    Array.prototype.forEach.call(nodes, function (node) {
      photosFor(node.getAttribute("data-photos")).then(function (rows) {
        node.innerHTML = rows.map(function (row) {
          var url = URL.createObjectURL(row.blob);
          photoUrls.push(url);
          return '<figure><button type="button" data-full="' + url + '"><img alt="Installed play photo" src="' + url + '"></button><button type="button" data-del="' + esc(row.id) + '">Remove</button></figure>';
        }).join("");
      }).catch(function () {});
    });
  }

  function revokePhotos() {
    photoUrls.forEach(function (url) { URL.revokeObjectURL(url); });
    photoUrls = [];
  }

  function onClick(event) {
    var go = event.target.closest("[data-go]");
    if (go) { state.section = go.getAttribute("data-go"); save(); paint(); return; }
    var sit = event.target.closest("[data-sit]");
    if (sit) {
      var parts = sit.getAttribute("data-sit").split(",");
      state.sit = { down: Number(parts[0]), distance: Number(parts[1]), zone: parts[2], hash: "middle" };
      save(); paint(); return;
    }
    var opp = event.target.closest("[data-opp]");
    if (opp) { state.opponent = opp.getAttribute("data-opp"); paint(); return; }
    var coach = event.target.closest("[data-coach]");
    if (coach) { state.coachId = coach.getAttribute("data-coach"); save(); paint(); return; }
    var room = event.target.closest("[data-room]");
    if (room) {
      state.room = room.getAttribute("data-room");
      if (state.section === "learn" && location.hash !== "#" + state.room) location.hash = state.room;
      save(); paint(); return;
    }
    var year = event.target.closest("[data-year]");
    if (year) { state.alertYear = Number(year.getAttribute("data-year")); paint(); return; }
    var side = event.target.closest("[data-side]");
    if (side) { state.side = side.getAttribute("data-side"); paint(); return; }
    var ocYear = event.target.closest("[data-oc-year]");
    if (ocYear) { state.ocYear = Number(ocYear.getAttribute("data-oc-year")); paint(); return; }
    var ocSit = event.target.closest("[data-oc-sit]");
    if (ocSit) { state.ocSit = ocSit.getAttribute("data-oc-sit"); paint(); return; }
    var full = event.target.closest("[data-full]");
    if (full) { openLight(full.getAttribute("data-full")); return; }
    var del = event.target.closest("[data-del]");
    if (del) { removePhoto(del.getAttribute("data-del")).then(refreshCount).then(paint); return; }
    var act = event.target.closest("[data-act]");
    if (!act) return;
    var name = act.getAttribute("data-act");
    if (name === "theme") { state.theme = state.theme === "night" ? "day" : "night"; save(); paint(); }
    if (name === "size") { state.sideline = !state.sideline; save(); paint(); }
    if (name === "copy") copyInstall();
    if (name === "export") exportBackup();
    if (name === "open-update") { state.updateOpen = true; state.section = "install"; paint(); }
  }

  function onChange(event) {
    var photo = event.target.closest("[data-photo]");
    if (photo && photo.files && photo.files[0]) {
      installPhoto(photo.files[0], photo.getAttribute("data-photo"), photo.getAttribute("data-kind") || "defense").then(refreshCount).then(paint).catch(function (err) {
        window.alert(err.message || "That photo could not be installed.");
      });
      return;
    }
    var incoming = event.target.closest("[data-import]");
    if (incoming && incoming.files && incoming.files[0]) importBackup(incoming.files[0]);
    if (event.target.name === "game") {
      var extra = document.getElementById("new-opponent");
      if (extra) extra.hidden = event.target.value !== "new";
    }
  }

  function onInput(event) {
    if (!event.target.hasAttribute("data-q")) return;
    state.q = String(event.target.value || "").trim().toLowerCase();
    var start = event.target.selectionStart;
    paint();
    var box = document.querySelector("[data-q]");
    if (box) { box.focus(); box.selectionStart = box.selectionEnd = start; }
  }

  function onSubmit(event) {
    if (!event.target || event.target.id !== "week-form") return;
    event.preventDefault();
    var form = event.target;
    var data = new FormData(form);
    var live = book();
    var gameId = String(data.get("game") || "");
    var ct = data.get("ct");
    var opp = data.get("opp");
    var playText = E.safeText(data.get("play"), 240);
    var yards = data.get("yards");
    var patch = {};
    if (ct !== "" && opp !== "" && ct != null && opp != null) {
      patch.ctScore = Number(ct);
      patch.oppScore = Number(opp);
    }
    var targetId = gameId;
    if (gameId === "new") {
      var opponent = E.safeText(data.get("opponent"), 40);
      var date = E.safeText(data.get("date"), 10);
      if (!opponent || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        note("Enter the opponent and the date.");
        return;
      }
      targetId = E.safeId(date + "-" + opponent);
      var seasonYear = Number(date.slice(0, 4));
      var coach = (E.seasonByYear(BASE, seasonYear) || {}).coachId || "jajczyk";
      if (!state.overlay.added.some(function (game) { return game.id === targetId; })) {
        state.overlay.added.push({
          id: targetId, season: seasonYear, date: date, opponent: opponent, site: "home",
          coachId: coach, plays: [], special: [], upcoming: true, note: "Added on this computer."
        });
      }
    }
    var current = E.gameById(book(), targetId);
    if (playText && current) {
      var playId = E.safeId("film-" + Date.now());
      var tag = String(data.get("tag") || "");
      if (E.TAGS.indexOf(tag) === -1) tag = "run_inside";
      var play = { id: playId, kind: "snap", text: playText, tags: [tag], source: "Your film, " + today(), yours: true };
      patch.plays = (current.plays || []).concat([play]);
      var file = form.photo.files && form.photo.files[0];
      if (file) {
        installPhoto(file, playId, "offense").catch(function (err) { note(err.message || "Photo was not installed."); });
      }
      if (yards !== "" && yards != null) {
        var called = E.bestDefense(book(), { game: current, down: 1, distance: 10, zone: "mid" }, state.model);
        var learned = E.applyLearning(state.model, {
          id: playId, callId: called.call.id, yards: Number(yards), touchdown: Number(yards) >= 20,
          opponent: current.opponent, play: playText, learnedAt: new Date().toISOString()
        });
        state.model = learned.model;
      }
    }
    if (Object.keys(patch).length) {
      var prior = (state.overlay.updates || []).filter(function (item) { return item.id === targetId; })[0];
      var merged = Object.assign({}, prior ? prior.patch : {}, patch);
      state.overlay.updates = (state.overlay.updates || []).filter(function (item) { return item.id !== targetId; });
      state.overlay.updates.push({ id: targetId, patch: merged });
    }
    var named = current ? current.opponent : E.safeText(data.get("opponent"), 40);
    state.lastGameAdded = named + " " + today();
    state.updateOpen = false;
    save();
    refreshCount().then(function () {
      state.section = "install";
      paint();
    });
  }

  function note(text) {
    var el = document.getElementById("film-note");
    if (el) el.textContent = text;
  }

  function copyInstall() {
    var text = state.copyText || "";
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).catch(function () { fallbackCopy(text); });
    } else fallbackCopy(text);
  }
  function fallbackCopy(text) {
    var area = document.createElement("textarea");
    area.value = text;
    document.body.appendChild(area);
    area.select();
    try { document.execCommand("copy"); } catch (err) {}
    document.body.removeChild(area);
  }

  function exportBackup() {
    var blob = new Blob([JSON.stringify({ updates: state.overlay.updates, added: state.overlay.added, model: state.model })], { type: "application/json" });
    var url = URL.createObjectURL(blob);
    var link = document.createElement("a");
    link.href = url;
    link.download = "cougar-defense-backup.json";
    link.click();
    URL.revokeObjectURL(url);
  }

  function importBackup(file) {
    if (file.size > 2 * 1024 * 1024) { note("That backup is too large."); return; }
    var reader = new FileReader();
    reader.onload = function () {
      try {
        var data = JSON.parse(String(reader.result || ""));
        if (!data || typeof data !== "object") return;
        state.overlay.updates = Array.isArray(data.updates) ? data.updates.slice(0, 400) : state.overlay.updates;
        state.overlay.added = Array.isArray(data.added) ? data.added.slice(0, 200) : state.overlay.added;
        if (data.model && data.model.weights) state.model = data.model;
        save();
        paint();
      } catch (err) {
        note("That file is not an Ashton Torres backup.");
      }
    };
    reader.readAsText(file);
  }

  function openLight(url) {
    var box = document.getElementById("light");
    box.textContent = "";
    var img = document.createElement("img");
    img.alt = "Play photo";
    img.src = url;
    box.appendChild(img);
    box.hidden = false;
  }

  function refreshCount() {
    return countPhotos().then(function (count) {
      state.photoCount = count;
      state.photosOk = true;
    }).catch(function () {
      state.photosOk = false;
    });
  }

  document.body.addEventListener("click", onClick);
  document.body.addEventListener("change", onChange);
  document.body.addEventListener("input", onInput);
  document.body.addEventListener("submit", onSubmit);
  document.getElementById("light").addEventListener("click", function () {
    document.getElementById("light").hidden = true;
  });
  window.addEventListener("hashchange", function () {
    var hash = String(location.hash || "").replace(/^#/, "");
    if (hash === "dline") { state.section = "dline"; paint(); return; }
    if (hash === "film") { state.section = "film"; paint(); return; }
    if (hash === "dl" || hash === "lb" || hash === "s" || hash === "cb") {
      state.room = hash;
      state.section = "learn";
      paint();
    }
  });

  setInterval(function () {
    state.model = E.heartbeat(state.model);
    state.lastBeat = Date.now();
    var row = document.querySelector('[data-signal="learn"] .detail');
    if (row) row.textContent = "Last tick just now · " + state.model.ticks + " checks";
    var open = document.querySelector('[data-signal="open"] .detail');
    if (open) open.textContent = "This window is checking the book · " + state.model.ticks + " checks";
    if (state.model.ticks % 8 === 0) save();
  }, 1000);

  paint();
  refreshCount().then(function () {
    if (state.section === "checks" || state.section === "pictures" || state.section === "install") paint();
  });
})();
