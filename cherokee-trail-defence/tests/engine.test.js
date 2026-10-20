const fs = require("fs");
const vm = require("vm");
const assert = require("assert");
const path = require("path");
const root = path.join(__dirname, "..");
const context = {};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(root, "book.js"), "utf8"), context);
vm.runInContext(fs.readFileSync(path.join(root, "engine.js"), "utf8"), context);
vm.runInContext(fs.readFileSync(path.join(root, "diagrams.js"), "utf8"), context);
const E = context.CougarEngine;
const B = context.COUGAR_BOOK;
const D = context.CougarDiagrams;

const audit = E.dataAudit(B);
assert.strictEqual(audit.ok, true, audit.problems.join("; "));
assert.strictEqual(E.trickAudit(B).ok, true);
assert.strictEqual(D.audit(B, E.TRICKS).ok, true);
assert.strictEqual(B.games.length, 119);
assert.strictEqual(E.tenYearSeasons ? null : null, null);
assert.strictEqual(B.seasons.filter(function (s) { return !s.live; }).length, 10);

assert.strictEqual(E.bestDefense(B, { season: 2016, down: 1, distance: 10, zone: "mid" }).call.id, "odd-cover3");
assert.strictEqual(E.bestDefense(B, { season: 2026, down: 1, distance: 10, zone: "mid" }).call.id, "nickel-quarters");
assert.strictEqual(E.bestDefense(B, { season: 2026, down: 3, distance: 12, zone: "mid" }).call.id, "nickel-quarters");
assert.strictEqual(E.bestDefense(B, { season: 2026, down: 3, distance: 1, zone: "mid" }).call.id, "bear-goalline");
assert.strictEqual(E.bestDefense(B, { season: 2021, down: 3, distance: 1, zone: "mid" }).call.id, "bear-goalline");
assert.strictEqual(E.bestDefense(B, { season: 2020, down: 3, distance: 1, zone: "mid" }).call.id, "bear-goalline");
assert.strictEqual(E.bestDefense(B, { season: 2026, down: 1, distance: 8, zone: "red" }).call.id, "nickel-quarters");
var second = E.bestDefense(B, { season: 2026, down: 2, distance: 6, zone: "mid" });
assert.ok(E.cardLine(second).indexOf(second.wrap.name) === 0);
var thirdLong = E.bestDefense(B, { season: 2026, down: 3, distance: 12, zone: "mid" });
assert.ok(E.cardLine(thirdLong).indexOf("Do not blitz") !== -1);
var redZone = E.bestDefense(B, { season: 2026, down: 1, distance: 8, zone: "red" });
assert.ok(E.cardLine(redZone).indexOf("fade") !== -1);
var fridayLines = [[1, 10, "mid"], [2, 6, "mid"], [3, 1, "mid"], [3, 12, "mid"], [1, 8, "red"], [1, 3, "goal"]].map(function (item) {
  return E.cardLine(E.bestDefense(B, { season: 2026, down: item[0], distance: item[1], zone: item[2] }));
});
assert.strictEqual(new Set(fridayLines).size, fridayLines.length);
var repeats = E.repeatAudit(B);
assert.strictEqual(repeats.ok, true, repeats.problems.join(" | "));
assert.strictEqual(E.bestDefense(B, { season: 2019, down: 1, distance: 3, zone: "goal" }).call.id, "bear-goalline");
const poudre = B.games.find(function (g) { return g.id === "2018-11-02-poudre"; });
assert.strictEqual(E.bestDefense(B, { game: poudre, down: 1, distance: 10, zone: "mid" }).call.id, "nickel-cover2");

B.games.forEach(function (game) {
  const tricks = E.trickPlaysForGame(B, game);
  assert.ok(tricks.length >= 1 && tricks.length <= 2, game.id);
});
const east = E.trickPlaysForGame(B, B.games.find(function (g) { return g.id === "2017-09-07-denver-east"; }));
assert.ok(east.some(function (t) { return t.kind === "published" && t.id === "fake-punt" && t.source; }));

assert.strictEqual(E.nextGame(B, "2026-10-17").opponent, "Northfield");
const live = E.mergeBook(B, { updates: [{ id: "2026-10-23-northfield", patch: { ctScore: 21, oppScore: 14 } }] });
assert.strictEqual(E.nextGame(live, "2026-10-17").opponent, "Castle View");
assert.strictEqual(live.seasons.find(function (s) { return s.id === 2026; }).liveRecord, "5-4");
assert.strictEqual(B.seasons.find(function (s) { return s.id === 2026; }).pf, 263);

const model = E.applyLearning(E.emptyModel(B), { id: "a", callId: "nickel-quarters", yards: 1, touchdown: false });
assert.strictEqual(model.model.weights["nickel-quarters"], 1.1);
assert.strictEqual(E.safeText("North\nfield", 20), "Northfield");
assert.strictEqual(E.safeId("../photo"), "photo");
assert.strictEqual(E.opponents(B).some(function (row) { return row.name === "Cherry Creek"; }), true);
assert.strictEqual(E.bestOffense(B, { season: 2026, situation: "standard" }).best.id, "mesh");
const creek = E.opponents(B).find(function (row) { return row.name === "Cherry Creek"; });
assert.strictEqual(creek.record.wins, 0);
var board = E.signals(B, { now: 10000, lastBeat: 9500, publishedBook: B, photosOk: true, picturesOk: true, storageOk: true, updateReady: true });
assert.strictEqual(board.ok, true, board.rows.filter(function (row) { return !row.ok; }).map(function (row) { return row.id + ": " + row.detail; }).join("; "));
assert.ok(board.rows.every(function (row) { return !/15-minute|scheduled task/i.test(row.name + " " + row.detail); }));
var plain = E.plainAudit(B);
assert.strictEqual(plain.ok, true, plain.problems.join("; "));
assert.ok(E.plainLine("nickel-quarters", "lb").indexOf("slot") !== -1);
assert.ok(E.plainLine("nickel-quarters", "dl").indexOf("Rush four") === 0);
["dl", "lb", "s", "cb"].forEach(function (group) {
  assert.ok(E.plainLine("jet-pop-pass", group).length > 20);
});
var line = E.lineAudit();
assert.strictEqual(line.ok, true, line.problems.join("; "));
assert.ok(E.bestDefense(B, { season: 2026, down: 1, distance: 10, zone: "mid", hash: "left" }).hashNote.indexOf("left hash") !== -1);
assert.ok(E.bestDefense(B, { season: 2026, down: 1, distance: 10, zone: "mid", hash: "right" }).hashNote.indexOf("wide side") !== -1);
assert.strictEqual(E.bestDefense(B, { season: 2026, down: 1, distance: 10, zone: "mid", hash: "left", protect: true, needStop: true }).call.id, "nickel-quarters");
assert.strictEqual(E.bestDefense(B, { season: 2026, down: 3, distance: 5, zone: "mid" }).call.id, "nickel-quarters");
assert.strictEqual(E.bestDefense(B, { season: 2026, down: 4, distance: 2, zone: "mid" }).call.id, "bear-goalline");
assert.strictEqual(E.LINE_COACH, "Ashton Torres");
assert.strictEqual(E.linePlays().length, 9);
assert.ok(E.linePlays().some(function (play) { return play.id === "nickel-quarters" && play.spots.length >= 2; }));

var best = E.bestPlays(B);
assert.strictEqual(best.map(function (row) { return row.play.id; }).join(","), "2020-ec-94,2026-aw-77,2023-ch-77,2020-ol-65,2021-col-59");
assert.ok(best[0].play.text.indexOf("94") !== -1);
assert.ok(best.every(function (row) { return row.play.source; }));

var oppNames = E.opponents(B).map(function (row) { return row.name; }).sort();
var teamNames = B.teams.map(function (team) { return team.name; }).sort();
assert.strictEqual(teamNames.join("|"), oppNames.join("|"));
assert.strictEqual(B.teams.length, 33);
B.teams.forEach(function (team) {
  assert.ok(team.city && team.citySource, team.name);
  if (team.league) assert.ok(team.leagueSource, team.name);
  (team.facts || []).forEach(function (item) {
    assert.ok(item.text && item.source, team.name);
  });
});
assert.ok(B.teams.some(function (team) { return team.name === "Northfield" && team.league === "5A Centennial" && team.city === "Denver"; }));
assert.ok(B.teams.some(function (team) { return team.name === "Dakota Ridge" && team.league === "" && team.city === "Littleton"; }));

console.log("engine tests passed");
