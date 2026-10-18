/* Cougar Defense call engine. Pure logic: no page elements. */
var CougarEngine = (function () {
  var TAGS = [
    "run_inside",
    "run_edge",
    "run_explosive",
    "pass_short",
    "pass_explosive",
    "play_action",
    "goal_line",
    "third_long"
  ];

  var TAG_LABELS = {
    run_inside: "inside run",
    run_edge: "edge run",
    run_explosive: "explosive run",
    pass_short: "short pass",
    pass_explosive: "explosive pass",
    play_action: "play-action",
    goal_line: "goal-line run",
    third_long: "third and long"
  };

  function emptyTags() {
    var out = {};
    TAGS.forEach(function (tag) { out[tag] = 0; });
    return out;
  }

  function clamp(n, lo, hi) {
    return Math.max(lo, Math.min(hi, n));
  }

  function round1(n) {
    return Math.round(n * 10) / 10;
  }

  function byId(list, id) {
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === id) return list[i];
    }
    return null;
  }

  function seasonByYear(book, year) {
    return byId(book.seasons, year) || byId(book.seasons, Number(year));
  }

  function gameById(book, id) {
    return byId(book.games, id);
  }

  function coachById(book, id) {
    return byId(book.coaches, id);
  }

  function playedGames(book) {
    return book.games.filter(function (game) { return !game.upcoming; });
  }

  function tenYearSeasons(book) {
    return book.seasons.filter(function (season) { return !season.live; });
  }

  function tagsFromPlays(plays) {
    var acc = emptyTags();
    var weight = 0;
    (plays || []).forEach(function (play) {
      if (!play.tags || !play.tags.length) return;
      var share = play.kind === "highlight" ? 0.5 : 1;
      weight += share;
      play.tags.forEach(function (tag) {
        if (acc[tag] != null) acc[tag] += share;
      });
    });
    if (!weight) return null;
    TAGS.forEach(function (tag) {
      acc[tag] = (acc[tag] / weight) * 10;
    });
    return acc;
  }

  function blend(a, b, aShare) {
    var out = emptyTags();
    TAGS.forEach(function (tag) {
      out[tag] = a[tag] * aShare + b[tag] * (1 - aShare);
    });
    return out;
  }

  function profileFor(book, query) {
    var season = query.season || (query.game && seasonByYear(book, query.game.season));
    var base = (query.game && query.game.statTags) || (season && season.prior) || emptyTags();
    var fromPlays = query.game ? tagsFromPlays(query.game.plays) : null;
    var profile = fromPlays ? blend(fromPlays, base, 0.62) : copyTags(base);
    return {
      tags: profile,
      season: season,
      game: query.game || null,
      confidence: confidenceFor(query.game, fromPlays)
    };
  }

  function copyTags(tags) {
    var out = emptyTags();
    TAGS.forEach(function (tag) { out[tag] = Number(tags[tag] || 0); });
    return out;
  }

  function confidenceFor(game, fromPlays) {
    if (!game) return "season";
    if (game.upcoming) return "upcoming";
    if (fromPlays && game.plays.some(function (play) { return play.kind === "snap"; })) return "snaps";
    if (fromPlays) return "highlights";
    if (game.statTags) return "yardage";
    return "score";
  }

  var CONFIDENCE_TEXT = {
    snaps: "Built from the published plays in this game.",
    highlights: "Built from published highlights. A full snap list was not published.",
    yardage: "Built from the published yardage line. A snap list was not published.",
    score: "No snap list was published for this game. This is the season call, not a filmed play.",
    upcoming: "This game has not been played. This is the current season call.",
    season: "This is the season call.",
    series: "Built from every meeting in this book, using each game's own evidence.",
    film: "Your weekly film is included with the published record."
  };

  function topTags(tags, count) {
    return TAGS.map(function (tag) {
      return { tag: tag, label: TAG_LABELS[tag], value: tags[tag] };
    }).sort(function (a, b) { return b.value - a.value; }).slice(0, count || 3);
  }

  function situationOf(query) {
    var down = Number(query.down || 1);
    var distance = Number(query.distance == null ? 10 : query.distance);
    var zone = query.zone || "mid";
    return {
      down: down,
      distance: distance,
      zone: zone,
      hash: query.hash || "middle",
      protect: !!query.protect,
      needStop: !!query.needStop
    };
  }

  function dot(fits, tags) {
    var total = 0;
    TAGS.forEach(function (tag) {
      total += Number(fits[tag] || 0) * Number(tags[tag] || 0);
    });
    return total;
  }

  function situationBonus(call, situation, tags) {
    var bonus = 0;
    var zone = situation.zone;
    var down = situation.down;
    var distance = situation.distance;
    if (zone === "goal" || (distance <= 3 && zone === "red")) {
      if (call.family === "goal") bonus += 240;
      if (call.family === "wrap") bonus -= 30;
      if (call.family === "prevent") bonus -= 40;
      if (call.id === "nickel-quarters") bonus -= 40;
    } else if (call.family === "goal") {
      bonus -= 35;
    }
    if (zone === "red" && distance > 3 && distance <= 8 && call.id === "nickel-cover2") bonus += 28;
    if (down >= 3 && distance >= 8) {
      if (call.id === "nickel-quarters") bonus += 36;
      if (call.family === "prevent") bonus += 18;
      if (call.family === "wrap") bonus -= 30;
      if (call.family === "goal") bonus -= 45;
    }
    if (down >= 3 && distance <= 2 && zone !== "goal") {
      if (call.family === "goal") bonus += 200;
      if (call.id === "odd-cover3") bonus += 16;
    }
    if (down === 3 && distance >= 4 && distance <= 7 && call.family === "pressure") bonus += 18;
    if (situation.needStop && Number(tags.pass_explosive) >= 7 && call.id === "nickel-quarters") bonus += 24;
    if (situation.needStop && call.family === "wrap") bonus -= 16;
    if (situation.protect && call.family === "prevent") bonus += 22;
    if (situation.protect && call.id === "nickel-quarters") bonus += 14;
    var explosive = Number(tags.pass_explosive) >= 8 && Number(tags.pass_explosive) >= Number(tags.run_inside);
    if (explosive && call.family === "wrap") bonus -= 22;
    if (!explosive && Number(tags.run_inside) >= 7 && call.id === "sam-wrap" && down === 1 && situation.zone === "mid") {
      bonus += 8;
    }
    return bonus;
  }

  function weightOf(model, callId) {
    if (!model || !model.weights || model.weights[callId] == null) return 1;
    return Number(model.weights[callId]);
  }

  function scoreCall(call, tags, situation, model) {
    var raw = dot(call.fits, tags) + situationBonus(call, situation, tags);
    var weight = weightOf(model, call.id);
    return {
      call: call,
      raw: raw,
      weight: weight,
      score: raw * weight
    };
  }

  function rank(book, tags, situation, model, filter) {
    return book.calls.map(function (call) {
      return scoreCall(call, tags, situation, model);
    }).filter(function (row) {
      return filter ? filter(row.call) : true;
    }).sort(function (a, b) {
      return b.score - a.score;
    });
  }

  function hashNote(hash) {
    if (hash === "left" || hash === "right") {
      var boundary = hash === "right" ? "right" : "left";
      var field = hash === "right" ? "left" : "right";
      return "Ball is on the " + boundary + " hash. The " + boundary + " corner plays inside leverage. The safety help is to the " + field + ", the wide side of the field. This is your pre-snap input. Cherokee Trail hash tendencies were not published.";
    }
    return "Ball is in the middle of the field. Play the call balanced. Hash tendencies were not published.";
  }

  function wrapAdvice(wrap, tags) {
    var explosive = Number(tags.pass_explosive) >= 8 && Number(tags.pass_explosive) > Number(tags.run_inside);
    if (!wrap) return "";
    if (explosive) {
      return "Change-up only. Call " + wrap.name + " once a half, on 2nd and medium, with the coverage listed behind it. If the backfield is empty or the slot shows a quick screen to the wrap side, check out of it.";
    }
    return "This is the run blitz that fits the profile. Use it on early downs when a back is in the box. Check out of it when the backfield is empty.";
  }

  function explain(best, profile, situation) {
    var lines = [];
    lines.push(best.call.why);
    lines.push(CONFIDENCE_TEXT[profile.confidence] || "");
    var leaders = topTags(profile.tags, 2).filter(function (row) { return row.value >= 4; });
    if (leaders.length) {
      lines.push("The strongest tells in this card are " + leaders.map(function (row) {
        return row.label + " (" + round1(row.value) + ")";
      }).join(" and ") + ".");
    }
    if (situation.zone === "goal") {
      lines.push("Inside the 5, the call has to be gap-sound. A wrap that leaves a lane is the wrong answer here.");
    }
    if (situation.down >= 3 && situation.distance >= 8) {
      lines.push("On 3rd and long, keep a safety over the top. Do not trade a sack for a free touchdown.");
    }
    return lines.filter(Boolean);
  }

  function bestDefense(book, query, model) {
    var situation = situationOf(query || {});
    var game = query && query.gameId ? gameById(book, query.gameId) : (query && query.game) || null;
    var season = game ? seasonByYear(book, game.season) : seasonByYear(book, query && query.season);
    var built = profileFor(book, { game: game, season: season });
    if (query && query.profile) built.tags = copyTags(query.profile);
    if (query && query.confidence) built.confidence = query.confidence;
    var rows = rank(book, built.tags, situation, model, function (call) {
      return call.family !== "wrap";
    });
    var wraps = rank(book, built.tags, situation, model, function (call) {
      return call.family === "wrap";
    });
    var best = rows[0];
    var alternate = rows[1] || null;
    var wrap = wraps[0] || null;
    return {
      call: best.call,
      score: round1(best.score),
      raw: round1(best.raw),
      weight: best.weight,
      alternate: alternate ? { call: alternate.call, score: round1(alternate.score) } : null,
      wrap: wrap ? wrap.call : null,
      wrapScore: wrap ? round1(wrap.score) : 0,
      wrapAdvice: wrapAdvice(wrap ? wrap.call : null, built.tags),
      profile: built.tags,
      top: topTags(built.tags, 3),
      confidence: built.confidence,
      confidenceText: CONFIDENCE_TEXT[built.confidence] || "",
      reasons: explain(best, built, situation),
      hashNote: hashNote(situation.hash),
      situation: situation,
      season: season,
      game: game,
      rows: rows.map(function (row) {
        return { id: row.call.id, name: row.call.name, score: round1(row.score) };
      })
    };
  }

  function averageProfiles(profiles) {
    var out = emptyTags();
    if (!profiles.length) return out;
    profiles.forEach(function (tags) {
      TAGS.forEach(function (tag) { out[tag] += Number(tags[tag] || 0); });
    });
    TAGS.forEach(function (tag) { out[tag] = out[tag] / profiles.length; });
    return out;
  }

  function seriesDefense(book, opponent, query, model) {
    var games = book.games.filter(function (game) {
      return game.opponent === opponent && !game.upcoming;
    });
    var profiles = games.map(function (game) {
      return profileFor(book, { game: game }).tags;
    });
    var blended = averageProfiles(profiles);
    var result = bestDefense(book, {
      season: games.length ? games[games.length - 1].season : (query && query.season),
      profile: blended,
      confidence: "series",
      down: query && query.down,
      distance: query && query.distance,
      zone: query && query.zone,
      hash: query && query.hash,
      protect: query && query.protect,
      needStop: query && query.needStop
    }, model);
    result.games = games;
    result.confidence = "series";
    result.confidenceText = CONFIDENCE_TEXT.series;
    return result;
  }

  function seriesRecord(games) {
    var wins = 0;
    var losses = 0;
    var disputes = 0;
    var upcoming = 0;
    games.forEach(function (game) {
      if (game.upcoming) upcoming += 1;
      else if (game.result === "W") wins += 1;
      else if (game.result === "L") losses += 1;
      else disputes += 1;
    });
    return { wins: wins, losses: losses, disputes: disputes, upcoming: upcoming, played: wins + losses + disputes };
  }

  function opponents(book) {
    var map = {};
    book.games.forEach(function (game) {
      if (!map[game.opponent]) map[game.opponent] = [];
      map[game.opponent].push(game);
    });
    return Object.keys(map).map(function (name) {
      var games = map[name].slice().sort(function (a, b) {
        return a.date < b.date ? -1 : 1;
      });
      return {
        name: name,
        games: games,
        record: seriesRecord(games)
      };
    }).sort(function (a, b) {
      return b.games.length - a.games.length || a.name.localeCompare(b.name);
    });
  }

  function nextGame(book, today) {
    var day = today || "2026-10-17";
    var ordered = book.games.slice().sort(function (a, b) { return a.date < b.date ? -1 : 1; });
    var i;
    for (i = 0; i < ordered.length; i++) {
      if (ordered[i].upcoming && ordered[i].date >= day) return ordered[i];
    }
    for (i = 0; i < ordered.length; i++) {
      if (ordered[i].upcoming) return ordered[i];
    }
    return ordered[ordered.length - 1];
  }

  function pointAudit(book) {
    var problems = [];
    book.seasons.forEach(function (season) {
      var games = book.games.filter(function (game) {
        return game.season === season.id && game.countInPoints !== false && !game.upcoming;
      });
      var pf = 0;
      var pa = 0;
      games.forEach(function (game) {
        pf += Number(game.ctScore);
        pa += Number(game.oppScore);
      });
      if (pf !== season.pf || pa !== season.pa) {
        problems.push(season.id + " adds to " + pf + "-" + pa + " but the published total is " + season.pf + "-" + season.pa);
      }
    });
    return { ok: problems.length === 0, problems: problems };
  }

  function scoreAudit(book) {
    var problems = [];
    book.games.forEach(function (game) {
      if (game.upcoming || game.result === "conflict") return;
      var expected = game.ctScore > game.oppScore ? "W" : "L";
      if (game.result !== expected) {
        problems.push(game.id + " is marked " + game.result + " with score " + game.ctScore + "-" + game.oppScore);
      }
    });
    return { ok: problems.length === 0, problems: problems };
  }

  function dataAudit(book) {
    var problems = [];
    var years = tenYearSeasons(book).map(function (season) { return season.id; });
    if (years.length !== 10) problems.push("Ten-year book has " + years.length + " seasons.");
    for (var year = 2016; year <= 2025; year++) {
      if (years.indexOf(year) === -1) problems.push("Missing " + year + ".");
    }
    if (!book.seasons.some(function (season) { return season.live && season.id === 2026; })) {
      problems.push("2026 live season is missing.");
    }
    var coachIds = book.coaches.map(function (coach) { return coach.id; });
    ["mangnall", "johnson", "jajczyk"].forEach(function (id) {
      if (coachIds.indexOf(id) === -1) problems.push("Missing coach " + id + ".");
    });
    book.games.forEach(function (game) {
      if (!game.opponent || !game.date || !game.coachId) problems.push("Game " + game.id + " is missing an opponent, date, or coach.");
      var season = seasonByYear(book, game.season);
      if (!season) problems.push("Game " + game.id + " has no season.");
      else if (season.coachId !== game.coachId) problems.push("Game " + game.id + " coach does not match the season.");
      (game.plays || []).forEach(function (play) {
        if (!play.source) problems.push("Play " + play.id + " has no source.");
        (play.tags || []).forEach(function (tag) {
          if (TAGS.indexOf(tag) === -1) problems.push("Play " + play.id + " has unknown tag " + tag + ".");
        });
      });
      (game.special || []).forEach(function (play) {
        if (!play.source) problems.push("Special play " + play.id + " has no source.");
      });
    });
    book.calls.forEach(function (call) {
      ["dl", "lb", "s", "cb"].forEach(function (group) {
        if (!call.positions || !call.positions[group] || !call.positions[group].job) {
          problems.push(call.id + " is missing the " + group + " job.");
        }
      });
      if (!call.coachNotes || !call.coachNotes.mangnall || !call.coachNotes.johnson || !call.coachNotes.jajczyk) {
        problems.push(call.id + " is missing a coach note.");
      }
    });
    var wraps = book.calls.filter(function (call) { return call.family === "wrap"; });
    if (wraps.length < 3) problems.push("Wrap package has " + wraps.length + " blitzes.");
    book.coaches.forEach(function (coach) {
      var plays = book.offense.filter(function (play) { return play.coaches.indexOf(coach.id) !== -1; });
      if (!plays.length) problems.push("No offensive call for " + coach.name + ".");
    });
    var points = pointAudit(book);
    var scores = scoreAudit(book);
    problems = problems.concat(points.problems).concat(scores.problems);
    return { ok: problems.length === 0, problems: problems, points: points, scores: scores, wraps: wraps.length };
  }

  function emptyModel(book) {
    var weights = {};
    book.calls.forEach(function (call) { weights[call.id] = 1; });
    return {
      version: 1,
      ticks: 0,
      processedIds: [],
      weights: weights,
      log: [],
      updated: null
    };
  }

  function applyLearning(model, entry) {
    var next = {
      version: 1,
      ticks: model.ticks || 0,
      processedIds: (model.processedIds || []).slice(),
      weights: Object.assign({}, model.weights || {}),
      log: (model.log || []).slice(),
      updated: model.updated || null
    };
    if (!entry || entry.sample) {
      return { model: next, changed: false, reason: "sample" };
    }
    if (!entry.id || next.processedIds.indexOf(entry.id) !== -1) {
      return { model: next, changed: false, reason: "already-learned" };
    }
    if (!entry.callId) {
      return { model: next, changed: false, reason: "no-call" };
    }
    var current = Number(next.weights[entry.callId] == null ? 1 : next.weights[entry.callId]);
    var yards = Number(entry.yards);
    var delta = 0;
    if (entry.touchdown) delta = -0.25;
    else if (yards <= 2) delta = 0.1;
    else if (yards <= 5) delta = 0.04;
    else if (yards >= 15) delta = -0.15;
    else if (yards >= 8) delta = -0.06;
    var updated = clamp(Math.round((current + delta) * 100) / 100, 0.4, 2);
    next.weights[entry.callId] = updated;
    next.processedIds.push(entry.id);
    next.updated = entry.learnedAt || next.updated;
    next.log.unshift({
      id: entry.id,
      callId: entry.callId,
      from: current,
      to: updated,
      yards: yards,
      touchdown: !!entry.touchdown,
      opponent: entry.opponent || "",
      play: entry.play || "",
      at: entry.learnedAt || ""
    });
    next.log = next.log.slice(0, 40);
    return { model: next, changed: delta !== 0 || updated !== current, delta: delta, from: current, to: updated };
  }

  function heartbeat(model) {
    var next = {
      version: model.version || 1,
      ticks: (model.ticks || 0) + 1,
      processedIds: model.processedIds || [],
      weights: model.weights || {},
      log: model.log || [],
      updated: model.updated || null
    };
    return next;
  }

  function bestOffense(book, query) {
    var year = Number(query.season || (query.game && query.game.season) || 2026);
    var situation = query.situation || "standard";
    var season = seasonByYear(book, year);
    var ranked = book.offense.map(function (play) {
      var score = Number((play.years && play.years[year]) || 0);
      score += Number((play.situations && play.situations[situation]) || 0);
      return { play: play, score: score };
    }).sort(function (a, b) { return b.score - a.score; });
    var plan = (season && season.offensePlan) || "";
    return {
      season: season,
      situation: situation,
      best: ranked[0] ? ranked[0].play : null,
      also: ranked.slice(1, 3).map(function (row) { return row.play; }),
      plan: plan,
      ranked: ranked.map(function (row) { return { id: row.play.id, score: row.score }; })
    };
  }

  var TRICKS = [
    {
      id: "fake-punt",
      name: "Fake punt, up-back throw or run",
      fits: { run_inside: 4, run_edge: 5, run_explosive: 2, pass_short: 6, pass_explosive: 3, play_action: 4, goal_line: 1, third_long: 7 },
      callOnce: "Call it once in a game, on a true punt down. A second one is a giveaway.",
      summary: "Spread punt. The up-back is the thrower or the runner. The personal protector sells the block, then releases to the flat.",
      steps: [
        "Line up in spread punt, not a scrimmage formation you only use for the fake.",
        "Snap to the up-back. The punter steps through as if he has the ball.",
        "If the edge chases the punter, the up-back runs the open lane. If the edge sits, he throws the flat behind that man.",
        "The ball has to leave the up-back's hand from behind the line if he throws."
      ],
      offense: "Best when the other team is rushing nine and the game is one score. Do not call it on 4th and short. Just go for it.",
      defense: "Rush eight, not ten. One linebacker is a spy on the up-back and does not rush. Safeties stay deep until the ball is punted.",
      positions: {
        dl: { title: "Defensive line", align: "Punt rush, contain", job: "Rush the punter's side but stay in your lane. The end to the up-back squeezes inside-out and does not run past the ball. If you are deeper than the punter when the ball is still in the backfield, you are the man the fake is drawn for." },
        lb: { title: "Linebackers", align: "One spy, the rest in lanes", job: "The Mike is the spy. He does not rush. He watches the up-back's hands. If the up-back pulls the ball, the Mike fits him inside. The other backers stay in rush lanes and retrace if the ball stays in the backfield." },
        s: { title: "Safeties", align: "Deep, do not creep", job: "Stay at punt-return depth until you see a kick. A fake punt touchdown is usually a 30-yard throw or a run through the alley a safety left early. Come up only after the kick is away, or after the spy yells 'live'." },
        cb: { title: "Corners", align: "Jam the gunners, find the ball", job: "Jam the gunner, then look back. If the ball is still in the punter's hands, abandon the gunner and fit the alley. Do not run 40 yards with a gunner on a ball that never left the backfield." }
      }
    },
    {
      id: "flea-flicker",
      name: "Flea flicker",
      fits: { run_inside: 9, run_edge: 6, run_explosive: 5, pass_short: 3, pass_explosive: 7, play_action: 9, goal_line: 2, third_long: 4 },
      callOnce: "Call it once, on first down, while the safeties are still fitting the run.",
      summary: "Handoff to the back, the back pitches back to the quarterback, and the quarterback throws the go ball.",
      steps: [
        "11 personnel, two-by-two, the back lined up so the handoff looks like inside zone.",
        "Handoff. The back takes two steps downhill, then pitches back to the quarterback. The pitch is backward.",
        "Both receivers on one side run vertical. The quarterback throws the one the safety left.",
        "The line blocks inside zone for two counts, then shows pass. Do not hold."
      ],
      offense: "Use it when a defense has been fitting a featured back and both safeties have been coming down. One flicker. Then go back to the run that set it up.",
      defense: "The free safety does not come down on the first step of the back. The backside end stays on the quarterback instead of chasing the fake.",
      positions: {
        dl: { title: "Defensive line", align: "Play the run, backside end stays home", job: "Fit the run on the front side. The backside end does not chase the back past the tackle's outside shoulder. When the ball comes back to the quarterback, that end is the hit. Chase the fake and the throw goes over your head." },
        lb: { title: "Linebackers", align: "Fit it, then retrace", job: "Take the handoff as a run until the ball comes back. Do not tackle the back eight yards downfield. The moment the pitch goes back to the quarterback, get depth and find the crosser. The Mike carries the inside throw." },
        s: { title: "Safeties", align: "Free safety stays deep", job: "One safety can fit the alley. The free safety stays in the middle of the field. He is the player the flicker is built to move. If he comes down, the go ball is open. He comes down only after the ball is clearly a run past the line." },
        cb: { title: "Corners", align: "On top of the vertical", job: "Do not jump the run fake and squat on a hitch. Stay on top of the outside receiver. The flicker is a go ball. Eyes on the quarterback after the pitch comes back. Play the ball in the air." }
      }
    },
    {
      id: "reverse-pass",
      name: "Reverse, then a throw",
      fits: { run_inside: 4, run_edge: 8, run_explosive: 6, pass_short: 4, pass_explosive: 8, play_action: 7, goal_line: 2, third_long: 5 },
      callOnce: "Call it once, to the wide side, with the reverse man still behind the line when he throws.",
      summary: "End around one way. Before he crosses the line, he throws back across the field to a receiver who stalled his route.",
      steps: [
        "Jet or end-around path. The quarterback tosses the ball backward to him.",
        "The reverse man runs at the edge, stops his feet behind the line, and throws back to the other side.",
        "The receiver on the other side runs five yards and sits, then goes vertical when the reverse man sets.",
        "If the reverse man crosses the line of scrimmage, the throw is illegal. He has to get rid of it behind the line, or tuck it and run."
      ],
      offense: "Use it when the ends have been crashing inside and the corners have been jumping the jet. The throw back punishes both.",
      defense: "The end plays the reverse with outside leverage and does not crash inside. The backside corner stays with his man instead of chasing the reverse.",
      positions: {
        dl: { title: "Defensive line", align: "Outside leverage on the reverse", job: "The end to the reverse squeezes with his outside arm free. He makes the ball carrier cut inside or throw from a crowded pocket. He does not dive inside and open the edge. The backside end stays home for the throwback." },
        lb: { title: "Linebackers", align: "Slow to the reverse, eyes on the ball", job: "Do not run yourself out of the play. Shuffle to the reverse until the ball is pitched. If the reverse man sets to throw, the Will on the backside finds the sit route. One backer has to be late to the reverse on purpose." },
        s: { title: "Safeties", align: "Backside safety stays on his hash", job: "The safety to the reverse can fit the alley late. The backside safety does not cross the formation. The throwback is aimed at the grass he would leave. He robs the sit, then carries the vertical." },
        cb: { title: "Corners", align: "Backside corner stays home", job: "The corner to the reverse plays the pitch man as a runner until a throw starts. The backside corner does not chase the reverse across the field. His man is the throwback. Stay on top of him." }
      }
    },
    {
      id: "hook-and-ladder",
      name: "Hook and ladder",
      fits: { run_inside: 3, run_edge: 6, run_explosive: 7, pass_short: 8, pass_explosive: 6, play_action: 4, goal_line: 3, third_long: 5 },
      callOnce: "Call it once in space, not on the goal line. The pitch has to be backward.",
      summary: "Short completion to the slot. He catches it going sideways and pitches backward to a trailer who is already at full speed.",
      steps: [
        "Trips to the field. The outside man runs a clear-out. The middle man runs a five-yard hook.",
        "Throw the hook on time, on the outside shoulder, so the catcher is moving toward the sideline.",
        "The inside man loops behind the catch and takes a backward pitch.",
        "A forward pitch is an illegal pass. Drill the backward angle before you call it."
      ],
      offense: "Use it when a defense is rallying downhill to short completions and leaving the alley behind the hook. One call, in the open field.",
      defense: "Rally to the catch, but the alley player does not tackle the catcher and ignore the trailer. Two hats to the ball.",
      positions: {
        dl: { title: "Defensive line", align: "Pass rush, then find the screen", job: "Rush, then get your head around. This trick lives after the catch. If you are on the ground, you are out of it. The backside end retraces to the middle in case the pitch comes back inside." },
        lb: { title: "Linebackers", align: "Rally in pairs", job: "The first backer fits the catcher. The second backer fits the trailer, not the catcher. Call 'second man' on the way to the ball. Do not both tackle the receiver who is about to pitch." },
        s: { title: "Safeties", align: "Alley, inside-out", job: "Come downhill inside-out so the trailer has to go sideways, not north. Break on the throw, then find the second ball. If you only see the catch, you will run past the pitch." },
        cb: { title: "Corners", align: "Force the catch toward your help", job: "The corner on the hook squeezes the catcher toward the linebacker. He does not dive at the feet. Stay on his feet until the pitch is dead. The outside shoulder of the catcher is the landmark." }
      }
    },
    {
      id: "statue",
      name: "Statue of Liberty",
      fits: { run_inside: 6, run_edge: 7, run_explosive: 4, pass_short: 4, pass_explosive: 5, play_action: 8, goal_line: 4, third_long: 2 },
      callOnce: "Call it once, on a down they think is a throw, with the back coming from the quarterback's throwing side.",
      summary: "The quarterback cocks to throw. The back takes the ball out of that cocked hand and runs the other way.",
      steps: [
        "Shotgun, the back offset to the quarterback's throwing hand.",
        "The quarterback drops his eyes to the far receiver and cocks the ball. He does not swing through.",
        "The back takes the ball from the cocked hand on the second step and bends the opposite edge.",
        "The line blocks outside zone to the back's path. The quarterback sells the throw for one more count, then gets out of the way."
      ],
      offense: "Use it when the ends are rushing upfield at a throwing quarterback and leaving the back side empty.",
      defense: "The end to the back's path rushes with a wide shoulder so a handoff out of the cocked throw still hits a body. Somebody watches the back, not the quarterback's eyes.",
      positions: {
        dl: { title: "Defensive line", align: "Wide rush, don't disappear", job: "Rush, but the end opposite the quarterback's eyes stays wide. The trick goes to the side the quarterback is not looking. If both ends chase the fake throw up the field, the back walks in." },
        lb: { title: "Linebackers", align: "One backer on the back", job: "The backer to the offset back is responsible for him even while the quarterback cocks. He does not drop into a hook and leave the back. Fit the back's path the moment the ball leaves the quarterback's hand backward." },
        s: { title: "Safeties", align: "Normal pass read, one eye on the back", job: "Play your pass responsibility. The free safety does not break on the cocked arm until the ball is actually in the air. If the arm never comes through, he fits the run alley the back is entering." },
        cb: { title: "Corners", align: "Do not bail off the fake arm", job: "Stay on your man. Do not turn and run because the quarterback cocked the ball. If you bail early, the back has the edge and your man is irrelevant. Find the ball, then carry the route." }
      }
    },
    {
      id: "fake-field-goal",
      name: "Fake field goal",
      fits: { run_inside: 3, run_edge: 4, run_explosive: 2, pass_short: 7, pass_explosive: 4, play_action: 5, goal_line: 6, third_long: 3 },
      callOnce: "Call it once, inside the 15, when a field goal is a believable call. Not on fourth and 18.",
      summary: "Field-goal formation. The holder takes the snap and throws the flat or runs the edge the defense just left.",
      steps: [
        "Normal field-goal personnel so the look is honest. The holder is a quarterback.",
        "Snap to the holder at seven yards. The kicker steps through.",
        "The wings release. One runs the flat, one blocks the edge.",
        "The holder throws the flat or keeps it if the wing is covered. He is behind the line, so the throw is legal."
      ],
      offense: "Use it in a one-score game when the other team is sending both edges at the kicker. A three-point try that becomes seven is the whole call.",
      defense: "Do not rush ten. The end to the wide side contains. One safety is a spy on the holder. The rest play the kick.",
      positions: {
        dl: { title: "Defensive line", align: "Contain the holder", job: "The two ends rush, but the wide-side end stays outside the wing. He is not a free runner at the kicker. If the holder pulls the ball, that end makes him go inside, where the spy is." },
        lb: { title: "Linebackers", align: "Block the inside gaps, one spy", job: "Most of the front plays the kick block. One backer spies the holder and does not jump. He fits a keep and robs the short throw. Jumping with the wings is how the fake scores." },
        s: { title: "Safeties", align: "One on the holder, one on the kick", job: "The short safety watches the holder's hands. The deep safety plays the kick until the ball is a throw, then he is the last man on the wing who released. Do not both come up." },
        cb: { title: "Corners", align: "Wings, then the ball", job: "Align on the wings. When a wing releases, carry him. Do not run at the kicker and leave the wing alone in the flat. That flat is the throw." }
      }
    },
    {
      id: "jet-pop-pass",
      name: "Jet toss, pop pass",
      fits: { run_inside: 3, run_edge: 7, run_explosive: 6, pass_short: 6, pass_explosive: 8, play_action: 6, goal_line: 2, third_long: 6 },
      callOnce: "Call it once to the field. The jet man throws from behind the line, or he tucks it.",
      summary: "Fast jet motion. He takes a backward toss, plants behind the line, and throws the wheel that the corner left to chase the jet.",
      steps: [
        "The slot starts in jet motion on the snap. He does not need a signal the defense can time.",
        "The quarterback tosses backward to the jet. The line slides as if it is jet sweep.",
        "The outside receiver runs a wheel. The jet plants behind the line and throws it, or tucks the ball if the corner stayed.",
        "If the jet's foot is over the line when he throws, the play is illegal. Plant early."
      ],
      offense: "Use it when corners are jumping jet motion and leaving the outside man. One call. If the corner stays, the jet runs the sweep and you still have a play.",
      defense: "The corner does not chase motion until the ball is in the jet's hands. The end contains the jet so he cannot set his feet.",
      positions: {
        dl: { title: "Defensive line", align: "Contain the jet", job: "The end to the motion squeezes the jet and makes him run or throw flat-footed. Do not chase the motion inside. A jet who can set his feet will throw the wheel over the corner who just chased him." },
        lb: { title: "Linebackers", align: "Do not run with the motion", job: "The backer to the jet shuffles, he does not sprint. He fits the jet as a runner and walls a throw back inside. The backside backer stays in the middle for the quarterback, who still has a give." },
        s: { title: "Safeties", align: "Motion does not move the free safety", job: "The free safety stays in the middle. Jet motion is not his trigger. He picks up the wheel if the corner bails with the jet. That is the throw." },
        cb: { title: "Corners", align: "Outside man stays on the outside man", job: "The corner on the outside receiver does not follow the jet. His man is about to wheel. The nickel can widen with the motion, but he does it at a shuffle, with his eyes on the ball." }
      }
    },
    {
      id: "screen-and-wheel",
      name: "Tunnel screen, wheel behind it",
      fits: { run_inside: 3, run_edge: 5, run_explosive: 4, pass_short: 7, pass_explosive: 9, play_action: 5, goal_line: 1, third_long: 8 },
      callOnce: "Call it once on a passing down, when the ends have been chasing screens.",
      summary: "The backfield sells a tunnel screen to the flat. The slot, who looked like he was blocking, wheels up the numbers.",
      steps: [
        "Trips. The inside receiver takes one step as if he is cracking on the end.",
        "The back slides to the flat and the quarterback pumps the screen.",
        "The inside receiver wheels. The quarterback resets and throws the wheel behind the end who chased the screen.",
        "If the end does not chase the screen, throw the screen. The trick only has to be a threat."
      ],
      offense: "Use it on second and medium or third and medium, when the rush has been leaving early for screens. The screen is the alert. The wheel is the shot.",
      defense: "The end chases the screen only to the width of the tackle. The safety on that side carries the slot who 'blocked' and then left.",
      positions: {
        dl: { title: "Defensive line", align: "Rush, don't chase the screen past the tackle", job: "Get up the field, then stop if you see screen action. Do not run to the flat. The wheel is thrown into the space you leave when you chase a back through the flat. The backside end stays in a rush lane." },
        lb: { title: "Linebackers", align: "Rob the screen, carry the wheel", job: "The play-side backer jumps the tunnel screen and tackles it for a loss if it is thrown. The backside backer does not follow him. Somebody has to be under the wheel. That is the backer who did not bite." },
        s: { title: "Safeties", align: "Carry the slot who sells the block", job: "The safety to the trips watches the inside receiver, not the back. When that receiver stops blocking and wheels, the safety carries him. He is not a spectator to the screen." },
        cb: { title: "Corners", align: "Outside leverage, don't help on the screen", job: "The outside corner stays on the outside man. He does not come down to tackle the screen. The nickel can help on the screen only if the safety has the wheel. Call that before the snap." }
      }
    }
  ];

  function gameShape(game) {
    if (!game) return "this game";
    if (game.upcoming || game.ctScore == null || game.oppScore == null) {
      return "the " + game.date + " game against " + game.opponent + ", which is not in the book as a final";
    }
    if (game.result === "conflict") {
      return "the " + game.date + " game against " + game.opponent + ", where the published result is in dispute";
    }
    var where = game.site === "away" ? "at " : "against ";
    return where + game.opponent + " on " + game.date + ", final " + game.ctScore + "-" + game.oppScore;
  }

  function rankTricks(prior, game) {
    var margin = game.ctScore == null || game.oppScore == null ? null : Math.abs(Number(game.ctScore) - Number(game.oppScore));
    var close = margin != null && margin <= 8;
    var track = margin != null && margin >= 28;
    return TRICKS.map(function (trick) {
      var score = dot(trick.fits, prior || emptyTags());
      if (close && (trick.id === "fake-field-goal" || trick.id === "fake-punt")) score += 48;
      if (track && trick.id === "fake-field-goal") score -= 24;
      if (game.upcoming && trick.id === "jet-pop-pass") score += 14;
      if (Number(prior.run_inside) >= 7 && trick.id === "flea-flicker") score += 26;
      if (Number(prior.pass_explosive) >= 8 && (trick.id === "reverse-pass" || trick.id === "screen-and-wheel")) score += 18;
      if (Number(prior.goal_line) >= 8 && trick.id === "statue") score += 10;
      return { trick: trick, score: score };
    }).sort(function (a, b) {
      if (b.score !== a.score) return b.score - a.score;
      return a.trick.id < b.trick.id ? -1 : 1;
    }).map(function (row) { return row.trick; });
  }

  function decorateTrick(trick, game, season, kind, sourcePlay) {
    var coach = season && season.coachName ? season.coachName : "this staff";
    var why;
    if (kind === "published") {
      why = "Published trick in this game. " + (sourcePlay.text || trick.name) + " Source: " + (sourcePlay.source || "the game story") + ". Defend it with the rules on this card. It is not a down-and-distance play from their base offense.";
    } else {
      why = "Your trick for " + gameShape(game) + ". " + coach + " is the coach of record for this season, and this call fits that season's published profile. " + trick.callOnce + " This is your play. It is not a play Cherokee Trail was reported to have run in this game.";
    }
    return {
      id: trick.id,
      name: trick.name,
      kind: kind,
      summary: trick.summary,
      steps: trick.steps,
      offense: trick.offense,
      defense: trick.defense,
      positions: trick.positions,
      callOnce: trick.callOnce,
      why: why,
      source: sourcePlay ? sourcePlay.source : "",
      legal: "Legal high-school call. No fumblerooski, no sleeper, and no route by a lineman numbered 50 through 79."
    };
  }

  function trickPlaysForGame(book, game) {
    if (!game) return [];
    var season = seasonByYear(book, game.season) || {};
    var prior = game.statTags || season.prior || emptyTags();
    var fromPlays = tagsFromPlays(game.plays || []);
    if (fromPlays) prior = blend(fromPlays, prior, 0.62);
    var chosen = [];
    var used = {};
    (game.special || []).forEach(function (play) {
      if (!play.trick || !play.trickId || used[play.trickId]) return;
      var trick = byId(TRICKS, play.trickId);
      if (!trick) return;
      used[trick.id] = true;
      chosen.push(decorateTrick(trick, game, season, "published", play));
    });
    var ranked = rankTricks(prior, game);
    for (var i = 0; i < ranked.length && chosen.length < 2; i++) {
      if (used[ranked[i].id]) continue;
      used[ranked[i].id] = true;
      chosen.push(decorateTrick(ranked[i], game, season, "yours", null));
    }
    return chosen.slice(0, 2);
  }

  function cloneBook(book) {
    return JSON.parse(JSON.stringify(book));
  }

  function applyPatch(game, patch) {
    Object.keys(patch || {}).forEach(function (key) {
      game[key] = patch[key];
    });
    if (game.result === "conflict") return;
    if (game.ctScore != null && game.oppScore != null && game.ctScore !== "" && game.oppScore !== "") {
      game.upcoming = false;
      game.result = Number(game.ctScore) > Number(game.oppScore) ? "W" : "L";
      game.countInPoints = patch && patch.countInPoints === false ? false : true;
      game.updatedFromNewGame = true;
    }
  }

  function mergeBook(book, overlay) {
    var next = cloneBook(book);
    var index = {};
    next.games.forEach(function (game) { index[game.id] = game; });
    ((overlay && overlay.added) || []).forEach(function (game) {
      if (!game || !game.id || index[game.id]) return;
      if (game.ctScore != null && game.oppScore != null && game.result !== "conflict") {
        game.upcoming = false;
        game.result = Number(game.ctScore) > Number(game.oppScore) ? "W" : "L";
      }
      game.updatedFromNewGame = true;
      next.games.push(game);
      index[game.id] = game;
    });
    ((overlay && overlay.updates) || []).forEach(function (update) {
      if (!update || !index[update.id]) return;
      applyPatch(index[update.id], update.patch || {});
    });
    next.seasons.forEach(function (season) {
      var games = next.games.filter(function (game) {
        return game.season === season.id && !game.upcoming && game.countInPoints !== false && game.result !== "conflict";
      });
      var pf = 0;
      var pa = 0;
      var wins = 0;
      var losses = 0;
      games.forEach(function (game) {
        pf += Number(game.ctScore);
        pa += Number(game.oppScore);
        if (game.result === "W") wins += 1;
        else if (game.result === "L") losses += 1;
      });
      season.livePf = pf;
      season.livePa = pa;
      season.liveWins = wins;
      season.liveLosses = losses;
      season.liveRecord = wins + "-" + losses;
      season.liveGames = games.length;
    });
    return next;
  }

  function positionCard(call, group, coachId) {
    var pos = call.positions[group];
    return {
      group: group,
      title: pos.title,
      align: pos.align,
      job: pos.job,
      coachNote: (call.coachNotes && call.coachNotes[coachId]) || ""
    };
  }

  function trickAudit(book) {
    var problems = [];
    (book.games || []).forEach(function (game) {
      var tricks = trickPlaysForGame(book, game);
      if (tricks.length < 1 || tricks.length > 2) {
        problems.push(game.id + " has " + tricks.length + " trick plays");
      }
      tricks.forEach(function (trick) {
        ["dl", "lb", "s", "cb"].forEach(function (group) {
          if (!trick.positions || !trick.positions[group] || !trick.positions[group].job) {
            problems.push(trick.id + " is missing the " + group + " job");
          }
        });
        if (trick.kind === "published" && !trick.source) problems.push(game.id + " published trick has no source");
      });
    });
    return { ok: problems.length === 0, problems: problems };
  }

  var PLAIN = {
    "odd-cover3": {
      say: "Odd front. One safety deep in the middle. Fit the run.",
      dl: "Play the center and the tackles. The end on the back's side stays home. Do not chase a boot.",
      lb: "Mike hits the middle. Will hits the gap away from the play. Sam plays outside and makes the run cut back inside.",
      s: "One safety stays deep in the middle. Come down only when it is a run. Do not jump the fake throw.",
      cb: "Play deep. The short-side corner lines up inside his man. Do not jump a short throw."
    },
    "nickel-quarters": {
      say: "Nickel. Both safeties start deep. Fit the run after you see it.",
      dl: "Rush four. Stay in your lane. Do not chase a screen past the tackle.",
      lb: "Fit the run from the inside out. If you are the nickel, stay on the slot.",
      s: "Both of you start deep. Stay deep on a fake run. Come up only when the ball is past the line.",
      cb: "Press your man and run with him if he goes deep. A safety is behind you. Do not sit on a short throw."
    },
    "bear-goalline": {
      say: "Bear. Man coverage. One safety on the goal line. No blitz.",
      dl: "Five across. Own the gap inside you. Do not leave it.",
      lb: "Step forward on the snap. Stay in your gap until the ball goes outside.",
      s: "Stand on the goal line in the middle. You are the last man.",
      cb: "Press your man. Stay inside him. Do not let him behind you."
    },
    "nickel-cover2": {
      say: "Cover 2. Corners take the flats. Safeties take the deep halves.",
      dl: "Rush four and stay tight. You have the boot, not the short throw.",
      lb: "Drop, then run to the short catch and make the tackle.",
      s: "Both of you go deep. The flat belongs to the corner.",
      cb: "Bump your man, then drop to the flat and tackle."
    },
    "sim-robber": {
      say: "Four rush. One backer blitzes late. The other jumps the crosser.",
      dl: "Rush four. Stay on the quarterback. Do not run past a boot.",
      lb: "One of you blitzes late. The other sits in the middle for a crosser. Do not both blitz.",
      s: "Stay deep. The blitz works only if the deep ball is covered.",
      cb: "Stay inside your man and run deep with him. A backer has the crosser."
    },
    "dime-cushion": {
      say: "Dime. Play deeper. Still fit a draw. This is not a soft prevent.",
      dl: "Rush, but do not crash inside. A draw or a quarterback keep can end the game.",
      lb: "Line up off the ball. Read run first.",
      s: "Start deeper than normal. Do not give up a deep ball to make a short tackle.",
      cb: "Line up off and outside. Protect the sideline. Tackle the short throw."
    },
    "sam-wrap": {
      say: "Sam wraps inside the end. The end forces the run outside.",
      dl: "On the blitz side, force the run outside. Do not crash inside. The blitzer is in that gap.",
      lb: "Sam runs inside the tackle. Mike comes over the top for the cutback. Will stays on the back side.",
      s: "Stay back until the ball is handed off. Then the safety to the blitz can come down.",
      cb: "Play your coverage. A quick screen to the blitz side means we check out."
    },
    "will-wrap": {
      say: "Will wraps from the back side and meets the pulling lineman.",
      dl: "The back-side end stays wide. Do not chase the pulling lineman.",
      lb: "Will meets the puller inside the back-side tackle. Sam plays the front. Mike plays the cutback.",
      s: "Do not run across the field. Stay for a quarterback keep.",
      cb: "Stay home. Do not chase the fake. If they throw it, it is to the man you left."
    },
    "bandit-wrap": {
      say: "One safety blitzes off the edge. The corner on that side drops to the flat.",
      dl: "The end away from the blitz keeps the quarterback inside. The other end lets the safety through.",
      lb: "Do not blitz with the safety. Mike plays the middle. Will plays the cutback.",
      s: "One safety blitzes off the edge. The other stays deep and does not come down.",
      cb: "If the blitz is on your side, drop to the flat right away. That quick throw is yours."
    },
    "fake-punt": {
      dl: "Rush your lane. Do not run past the punter. If you get deeper than the ball, the fake is drawn for you.",
      lb: "One of you spies the up-back and does not rush. Everyone else stays in a lane.",
      s: "Stay deep until the ball is kicked. Come up after the kick, or when the spy yells live.",
      cb: "Jam the gunner, then look back. If the ball was not kicked, come fit the run."
    },
    "flea-flicker": {
      dl: "Play the run. The back-side end stays home for the quarterback.",
      lb: "Play the handoff as a run until the ball comes back. Then find the throw.",
      s: "One safety can fit the run. The free safety stays deep. This trick is a throw.",
      cb: "Stay on top of your man. This is a deep throw after a fake run. Do not jump a short route."
    },
    "reverse-pass": {
      dl: "The end on the reverse stays outside. The back-side end stays home for the throwback.",
      lb: "Shuffle to the reverse. Do not sprint. One backer stays for the throw back.",
      s: "The back-side safety does not cross the field. The throwback is his.",
      cb: "The back-side corner stays home. His man is the throwback."
    },
    "hook-and-ladder": {
      dl: "Rush, then find the ball. This trick happens after the catch.",
      lb: "Two of you go to the ball. One hits the catcher. One hits the trailer.",
      s: "Come downhill, then find the second man. Do not run past the pitch.",
      cb: "Stay on your feet and squeeze the catcher inside. Do not dive at his feet."
    },
    "statue": {
      dl: "Rush wide. The run goes to the side the quarterback is not looking.",
      lb: "The backer near the back stays on the back, even while the quarterback fakes the throw.",
      s: "Do not break until the ball is really thrown. If he never throws it, fit the run.",
      cb: "Stay on your man. Do not turn and run just because he cocked his arm."
    },
    "fake-field-goal": {
      dl: "The wide-side end stays outside the holder. Do not all hit the kicker.",
      lb: "One of you watches the holder and does not jump. The rest play the kick.",
      s: "One safety watches the holder's hands. One stays deep for the kick.",
      cb: "Stay with the wings. The throw is to a wing, not to the kicker."
    },
    "jet-pop-pass": {
      dl: "Squeeze the jet so he cannot set his feet. Do not chase him inside.",
      lb: "Shuffle with the jet. Do not sprint. The other backer stays in the middle.",
      s: "The free safety stays in the middle. If a corner chases the jet, you have the wheel.",
      cb: "Stay with the outside man. He is about to wheel. Do not follow the jet."
    },
    "screen-and-wheel": {
      dl: "If you see a screen, stop. Do not chase the back past the tackle.",
      lb: "One of you jumps the screen. The other stays under the wheel.",
      s: "Watch the slot who sells a block and then wheels. Do not watch only the screen.",
      cb: "Stay on the outside man. Do not come down to tackle the screen."
    }
  };

  function plainLine(id, group) {
    var row = PLAIN[id];
    if (!row || !row[group]) return "";
    return row[group];
  }

  function plainAudit(book) {
    var problems = [];
    var groups = ["dl", "lb", "s", "cb"];
    (book.calls || []).forEach(function (call) {
      if (!PLAIN[call.id] || !PLAIN[call.id].say) problems.push(call.id + " has no player call line");
      groups.forEach(function (group) {
        var line = plainLine(call.id, group);
        if (line.length < 20 || line.length > 180) problems.push(call.id + " " + group + " player line is " + line.length);
      });
    });
    TRICKS.forEach(function (trick) {
      groups.forEach(function (group) {
        var line = plainLine(trick.id, group);
        if (line.length < 20 || line.length > 180) problems.push(trick.id + " " + group + " player line is " + line.length);
      });
    });
    return { ok: problems.length === 0, problems: problems };
  }

  function signals(book, runtime) {
    runtime = runtime || {};
    var published = runtime.publishedBook || book;
    var live = book;
    var audit = dataAudit(published);
    var tricks = trickAudit(live);
    var plain = plainAudit(published);
    var beatAge = runtime.now && runtime.lastBeat ? runtime.now - runtime.lastBeat : null;
    var updateDetail = runtime.lastGameAdded
      ? "Last game folded in: " + runtime.lastGameAdded
      : "Update weekly on the front page writes the final into the card, the call, and the tricks";
    var rows = [
      check("book", "Book loaded", !!published && published.games && published.games.length > 100, published.games.length + " games in the ten-year book"),
      check("ten", "Ten-year check, 2016 through 2025", tenYearSeasons(published).length === 10, "10 seasons, plus the 2026 live slate"),
      check("points", "Published point totals", audit.points.ok, audit.points.ok ? "Every season adds up to the MaxPreps total" : audit.points.problems[0]),
      check("scores", "Win and loss check", audit.scores.ok, audit.scores.ok ? "Every final matches its score" : audit.scores.problems[0]),
      check("opponents", "Split by opponent", opponents(live).length >= 20, opponents(live).length + " opponents"),
      check("coaches", "Coach cards", published.coaches.length === 3, "Mangnall, Johnson, and Jajczyk"),
      check("dl", "Defensive line jobs", jobsPresent(published, "dl"), "A line job is written on every call"),
      check("dline", "Aston Torres line room", lineAudit().ok, lineAudit().ok ? "Nine line plays. The other rooms are not written on this card." : lineAudit().problems[0]),
      check("lb", "Linebacker jobs", jobsPresent(published, "lb"), "A linebacker job is written on every call"),
      check("s", "Safety jobs", jobsPresent(published, "s"), "A safety job is written on every call"),
      check("cb", "Corner jobs", jobsPresent(published, "cb"), "A corner job is written on every call"),
      check("plain", "Player room cards", plain.ok, plain.ok ? "Each room has a short card. The full diagnosis stays on the coach page." : plain.problems[0]),
      check("wrap", "Wrap-around blitzes", published.calls.filter(function (call) { return call.family === "wrap"; }).length >= 3, "Sam wrap, Will wrap, and Bandit wrap"),
      check("tricks", "Trick plays, one or two a game", tricks.ok, tricks.ok ? "Every game has a trick card" : tricks.problems[0]),
      check("offense", "Your offense card", published.offense.length >= 6, published.offense.length + " calls to run against them"),
      check("update", "New game update", runtime.updateReady !== false, updateDetail),
      check("photos", "Play photos", runtime.photosOk !== false, runtime.photosOk === false ? "Photo storage is blocked. Open with the Windows launcher." : (runtime.photoCount ? runtime.photoCount + " photos installed on this device" : "Ready. Install a photo when you have a new play.")),
      check("pictures", "Defense and offense pictures", runtime.picturesOk !== false, runtime.pictureDetail || "A picture is drawn for every defensive call and every offensive play"),
      check("sources", "Every published play has a source", audit.ok, audit.ok ? "No unsourced play is in the book" : audit.problems[0]),
      check("learn", "Learning loop", beatAge != null && beatAge < 3000, beatAge == null ? "Waiting for the first tick" : "Last tick " + Math.max(0, Math.round(beatAge / 100) / 10) + "s ago"),
      check("open", "Learns while this window is open", beatAge != null && beatAge < 3000, beatAge == null ? "Open the program and this check turns green" : "This window keeps checking the book. Close it and learning pauses."),
      check("save", "Weekly film save", runtime.storageOk !== false, runtime.storageOk === false ? "This browser is blocking saved film" : "Film, new games, and weights save on this computer")
    ];
    var master = rows.every(function (row) { return row.ok; }) && audit.ok && tricks.ok;
    return { ok: master, rows: rows, problems: audit.problems.concat(tricks.problems, plain.problems) };
  }

  function jobsPresent(book, group) {
    return book.calls.every(function (call) {
      return call.positions && call.positions[group] && call.positions[group].job;
    });
  }

  function check(id, name, ok, detail) {
    return { id: id, name: name, ok: !!ok, detail: detail || "" };
  }

  function safeText(value, max) {
    return String(value == null ? "" : value).replace(/[\u0000-\u001F]/g, "").slice(0, max || 240);
  }

  function safeId(value) {
    var cleaned = String(value == null ? "" : value).toLowerCase().replace(/[^a-z0-9-]/g, "").slice(0, 48);
    return cleaned || "play";
  }

  var LINE_COACH = "Aston Torres";

  var LINE_PLAYS = [
    {
      id: "nickel-quarters",
      name: "Nickel Cover 4",
      simple: "Rush four. Stay in your lane. Do not chase a screen past the tackle.",
      spots: [
        { spot: "End", line: "Outside shoulder of the tackle. Outside hand on his chest. Your outside arm stays free so he cannot hook you." },
        { spot: "Tackle", line: "Outside shoulder of the guard. First step is up the field. You own the gap on your inside." }
      ],
      run: "Squeeze your gap and make the back cut to a backer. Do not cross the center on the first step. If the tackle reaches you, long-arm him and stay square.",
      pass: "Rush the quarterback's near shoulder and get his eyes down. If the tackle opens a gate, stay in that gate. Running past him leaves a free lane.",
      avoid: "Stop a screen at the tackle. Do not chase it to the flat. The end away from the boot stays home."
    },
    {
      id: "odd-cover3",
      name: "Odd Cover 3",
      simple: "Nose on the center. Ends on the tackles. The end to the back stays home.",
      spots: [
        { spot: "Nose", line: "Shade the center to the call. Strike him, then find the back. The gap to the call is yours." },
        { spot: "End", line: "Outside shoulder of the tackle. Long arm if he reaches. Squeeze. Do not fly up the field on the first step." }
      ],
      run: "The end on the back's side squeezes and spills the run inside. The other end stays for the cutback and a quarterback keep. Low pads. Do not stand up and look.",
      pass: "Rush from where you lined up. The nose does not loop. The end's outside shoulder is the contain.",
      avoid: "Do not chase a boot. That is why the back-side end never left."
    },
    {
      id: "bear-goalline",
      name: "Bear",
      simple: "Five across. Own the gap inside you. Do not leave it.",
      spots: [
        { spot: "Nose", line: "Head up on the center. Strike and stay square. He cannot block you and a guard." },
        { spot: "Tackle", line: "Head up on the guard. The gap inside you is yours. Do not slant away from it." },
        { spot: "End", line: "Wider than the tackle. The gap inside you is yours. You are the last man on the line if the ball spills." }
      ],
      run: "Step forward on the snap and fit low. If you slant out of your gap, the back walks through the hole you left.",
      pass: "Hands up. The throw is short. Do not rush past the ball and open the sneak.",
      avoid: "No one loops. No one wraps. Stay in the gap you lined up in."
    },
    {
      id: "nickel-cover2",
      name: "Nickel Cover 2",
      simple: "Rush four and stay tight. The boot is yours. The short throw is not.",
      spots: [
        { spot: "End", line: "Tight on the tackle. Squeeze. Your job is the boot lane, not a five-yard throw in the flat." },
        { spot: "Tackle", line: "Tight on the guard. Rush the inside shoulder of the quarterback so he cannot step up." }
      ],
      run: "Play it as a four-man run fit. Do not widen on the first step. A zone play wants you to run yourself out of the gap.",
      pass: "Four rush, shoulders square. The ends squeeze so the quarterback has to throw from the pocket.",
      avoid: "Do not peel off for a short throw. Stay on the boot until the ball is out."
    },
    {
      id: "sim-robber",
      name: "Sim pressure",
      simple: "Rush four. Stay on the quarterback. Do not run past a boot.",
      spots: [
        { spot: "End", line: "Contain rush. Your inside foot aims at the quarterback's near shoulder. Do not cross his face." },
        { spot: "Tackle", line: "Win the inside gap. You are not looping with a backer. The extra rusher is not a lineman." }
      ],
      run: "Fit the run in your gap first. If it is a run, you still own the gap you lined up in. Do not chase the back out of your fit.",
      pass: "Rush to affect the throw. Keep the pocket small. The end who loses contain loses the play.",
      avoid: "Do not run past the quarterback on a boot. Turn back to the football."
    },
    {
      id: "dime-cushion",
      name: "Dime",
      simple: "Rush, but do not crash inside. A draw or a keep can end the game.",
      spots: [
        { spot: "End", line: "Rush with a wide shoulder. You are the contain. Crashing inside is the lane they want." },
        { spot: "Tackle", line: "Rush the guard, then find a draw. If the back comes straight downhill, shed and fit him." }
      ],
      run: "Treat a draw like a run in your gap. Do not keep rushing up the field after the back has the ball.",
      pass: "Get a hand up, then settle. A late draw is the play they check to when the ends disappear.",
      avoid: "Do not pin your ears. Contain first, then rush."
    },
    {
      id: "sam-wrap",
      name: "Sam wrap",
      simple: "On the blitz side, force the run outside. Do not crash inside.",
      spots: [
        { spot: "End", line: "Long-arm the tackle and spill every run outside you. The blitzer is filling the gap you leave. You do not fill it." },
        { spot: "Tackle", line: "Stay in your gap. Do not follow the end outside. You are the cutback on the inside." }
      ],
      run: "Spill, do not penetrate. If you crash, you and the blitzer are in the same gap and the back cuts behind both of you.",
      pass: "After you spill, rush the quarterback only if the ball is still in his hands. Do not chase a quick throw to the flat.",
      avoid: "A quick screen to your side means the blitz is off. Play your normal rush."
    },
    {
      id: "will-wrap",
      name: "Will wrap",
      simple: "The back-side end stays wide. Do not chase a pulling lineman.",
      spots: [
        { spot: "End", line: "Stay wide of the tackle. If a guard pulls across your face, let him go. Your gap is the boot and the keep behind him." },
        { spot: "Tackle", line: "Squeeze the guard who does not pull. Do not run down the line after the puller." }
      ],
      run: "Spill the counter. The pulling guard is not your man to chase. Meet the ball if it tries to cut back under you.",
      pass: "The boot comes back to the side the line pulled away from. That end has to be there.",
      avoid: "Do not follow the puller inside. That is the lane."
    },
    {
      id: "bandit-wrap",
      name: "Bandit wrap",
      simple: "One end keeps the quarterback inside. The other end lets the blitzer through.",
      spots: [
        { spot: "End away", line: "Contain the quarterback. He cannot escape to your side. Four hands on the offensive line, not a free run at him from the far side." },
        { spot: "End to the blitz", line: "Spill the run and leave the edge lane. The blitzer is coming there. Do not occupy that lane." }
      ],
      run: "Play your gap. Two people in the blitz lane means the draw scores inside.",
      pass: "The contain end rushes after he has the quarterback walled. The spill end does not chase a quick throw.",
      avoid: "Do not both rush up the field. One of you is the wall."
    }
  ];

  function linePlays() {
    return LINE_PLAYS;
  }

  function lineAudit() {
    var problems = [];
    if (LINE_COACH !== "Aston Torres") problems.push("line coach name");
    if (LINE_PLAYS.length !== 9) problems.push("expected 9 line plays");
    var banned = /son-in-law|free safety|the corner|linebacker drops|cover 2|cover 3|cover 4/i;
    LINE_PLAYS.forEach(function (play) {
      if (!play.simple || play.simple.length < 20) problems.push(play.id + " simple");
      if (!play.run || !play.pass || !play.avoid) problems.push(play.id + " rules");
      if (!play.spots || play.spots.length < 2) problems.push(play.id + " spots");
      var text = [play.simple, play.run, play.pass, play.avoid].concat((play.spots || []).map(function (spot) {
        return spot.spot + " " + spot.line;
      })).join(" ");
      if (banned.test(text)) problems.push(play.id + " teaches another room");
    });
    return { ok: problems.length === 0, coach: LINE_COACH, problems: problems };
  }

  return {
    TAGS: TAGS,
    TAG_LABELS: TAG_LABELS,
    CONFIDENCE_TEXT: CONFIDENCE_TEXT,
    emptyTags: emptyTags,
    seasonByYear: seasonByYear,
    gameById: gameById,
    coachById: coachById,
    opponents: opponents,
    nextGame: nextGame,
    bestDefense: bestDefense,
    seriesDefense: seriesDefense,
    seriesRecord: seriesRecord,
    bestOffense: bestOffense,
    trickPlaysForGame: trickPlaysForGame,
    TRICKS: TRICKS,
    mergeBook: mergeBook,
    positionCard: positionCard,
    dataAudit: dataAudit,
    trickAudit: trickAudit,
    plainLine: plainLine,
    LINE_COACH: LINE_COACH,
    linePlays: linePlays,
    lineAudit: lineAudit,
    plainAudit: plainAudit,
    pointAudit: pointAudit,
    emptyModel: emptyModel,
    applyLearning: applyLearning,
    heartbeat: heartbeat,
    signals: signals,
    topTags: topTags,
    safeText: safeText,
    safeId: safeId
  };
})();
