import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import vm from "node:vm";

const context = {};
context.globalThis = context;
vm.createContext(context);
vm.runInContext(fs.readFileSync(new URL("./frames.js", import.meta.url), "utf8"), context);
const { FRAMES, barsFor, analyze, brief, intradayDay } = context.PaperFrames;

function day(date, open, high, low, close) {
  return { date, open, high, low, close, volume: 1000 };
}

test("ten timeframes and a brief", () => {
  const daily = [];
  for (let index = 0; index < 40; index += 1) {
    const close = 100 + index;
    daily.push(day("2024-01-" + String((index % 28) + 1).padStart(2, "0"), close - 1, close + 2, close - 3, close));
  }
  const rows = JSON.parse(JSON.stringify(analyze(daily)));
  assert.equal(rows.length, 10);
  assert.deepEqual(rows.map((row) => row.id), ["1m", "5m", "15m", "30m", "1h", "4h", "1D", "1W", "1M", "1Q"]);
  const note = brief("TEST", rows);
  assert.equal(note.lines.length, 10);
  assert.match(note.lead, /higher timeframes/);
});

test("intraday bars stay inside the daily sample", () => {
  const bar = day("2024-06-03", 10, 14, 8, 13);
  const bars = intradayDay(bar, 60);
  assert.equal(bars.length, 8);
  assert.equal(bars[0].open, 10);
  assert.equal(bars[bars.length - 1].close, 13);
  bars.forEach((child) => {
    assert.ok(child.high <= 14 + 1e-9);
    assert.ok(child.low >= 8 - 1e-9);
    assert.ok(child.high >= Math.max(child.open, child.close) - 1e-9);
    assert.ok(child.low <= Math.min(child.open, child.close) + 1e-9);
  });
});

test("weekly bars join the days", () => {
  const daily = [
    day("2024-06-03", 10, 12, 9, 11),
    day("2024-06-04", 11, 13, 10, 12),
    day("2024-06-05", 12, 14, 11, 10),
    day("2024-06-10", 10, 11, 8, 9),
  ];
  const weeks = barsFor(daily, { kind: "week" });
  assert.equal(weeks.length, 2);
  assert.equal(weeks[0].open, 10);
  assert.equal(weeks[0].high, 14);
  assert.equal(weeks[0].low, 9);
  assert.equal(weeks[0].close, 10);
  assert.equal(weeks[1].open, 10);
  assert.equal(weeks[1].close, 9);
});
