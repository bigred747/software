import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import vm from "node:vm";

const context = {};
context.globalThis = context;
vm.createContext(context);
vm.runInContext(fs.readFileSync(new URL("./frames.js", import.meta.url), "utf8"), context);
const { FRAMES, barsFor, analyze, brief, intradayDay, averages, compression, candlePlace, squeezeName, trendPivots, trueMomentum, zoomBars, zoomStep, mood, lesson, edge } = context.PaperFrames;

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

test("averages and squeeze stay with green or red candles", () => {
  const source = fs.readFileSync(new URL("./frames.js", import.meta.url), "utf8");
  assert.equal(source.includes("hollow"), false);
  assert.equal(source.includes("#67e8f9"), false);
  const bars = [];
  for (let index = 0; index < 40; index += 1) {
    const close = 100 + index;
    bars.push(day("2024-02-" + String((index % 27) + 1).padStart(2, "0"), close - 1, close + 2, close - 2, close));
  }
  const lines = averages(bars);
  assert.equal(lines.high.length, 40);
  assert.ok(lines.close[39] > lines.close[20]);
  assert.equal(candlePlace(bars[39], lines.high[39], lines.low[39]), "Above the high average");
  const study = compression(bars);
  assert.equal(study.level.length, 40);
  assert.equal(study.momentum[19] == null, false);
  study.level.forEach((level) => assert.ok(level >= 0 && level <= 3));
  assert.equal(squeezeName(3), "High");
  assert.equal(squeezeName(0), "Off");
});

test("pivot resistance follows the rising high and support stays under it", () => {
  const bars = [];
  for (let index = 0; index < 30; index += 1) {
    const close = 100 + index;
    bars.push(day("2024-03-" + String((index % 27) + 1).padStart(2, "0"), close - 1, close + 1, close - 2, close));
  }
  const pivots = trendPivots(bars, 5, 0.7);
  assert.equal(pivots.resistance, bars[29].high);
  assert.ok(pivots.confirmHigh <= pivots.resistance);
  assert.ok(pivots.riskOffHigh <= pivots.confirmHigh);
  assert.ok(pivots.support <= bars[0].low);
  assert.ok(pivots.confirmLow >= pivots.support);
  assert.ok(pivots.riskOffLow >= pivots.confirmLow);
  assert.equal(pivots.fromHigh, 29);
});

test("true momentum stays inside the length and confirms arrows on a closed bar", () => {
  const bars = [];
  for (let index = 0; index < 40; index += 1) {
    const close = index < 25 ? 100 + index : 130 - (index - 25) * 4;
    const open = index < 25 ? close - 2 : close + 2;
    bars.push(day("2024-04-" + String((index % 27) + 1).padStart(2, "0"), open, Math.max(open, close) + 1, Math.min(open, close) - 1, close));
  }
  const study = trueMomentum(bars, 14, 5, 3);
  assert.equal(study.main.length, 40);
  assert.equal(study.ob, 10);
  assert.equal(study.os, -10);
  study.main.forEach((value) => {
    assert.ok(value <= 14.01 && value >= -14.01);
  });
  assert.equal(study.up[39], false);
  assert.equal(study.down[39], false);
  assert.equal(study.main[18], 14);
  assert.equal(study.down[25], true);
});

test("edge fills at the next open and later bars do not move closed trades", () => {
  const bars = [];
  for (let index = 0; index < 320; index += 1) {
    const wave = Math.sin(index / 9) * 12 + Math.sin(index / 31) * 20;
    const close = 200 + index * 0.25 + wave;
    const open = close - Math.cos(index / 5) * 1.5;
    const date = new Date(Date.UTC(2023, 0, 1) + index * 86400000).toISOString().slice(0, 10);
    bars.push(day(date, open, Math.max(open, close) + 1.5, Math.min(open, close) - 1.5, close));
  }
  const full = edge(bars);
  assert.ok(full.ready);
  assert.ok(full.trades.length > 0);
  assert.ok(["BUY", "SELL", "HOLD", "WAIT"].includes(full.call));
  assert.ok(full.score >= 0 && full.score <= 5);
  full.marks.forEach((mark) => {
    assert.equal(mark.price, bars[mark.index].open);
    assert.ok(mark.index >= 51);
  });
  full.trades.forEach((trade) => assert.ok(trade.exitDate > trade.entryDate));
  assert.ok(full.record.worst <= 0);
  assert.ok(full.record.winRate >= 0 && full.record.winRate <= 1);
  const cut = edge(bars.slice(0, 240));
  const closedBefore = cut.trades.filter((trade) => trade.exitDate < bars[239].date);
  const sameInFull = full.trades.slice(0, closedBefore.length);
  assert.deepEqual(JSON.parse(JSON.stringify(sameInFull)), JSON.parse(JSON.stringify(closedBefore)));
  const short = edge(bars.slice(0, 30));
  assert.equal(short.ready, false);
  assert.equal(short.trades.length, 0);
});

test("zoom keeps the latest bar and fear or greed follows the range", () => {
  const bars = [];
  for (let index = 0; index < 200; index += 1) {
    const close = 100 + index;
    bars.push(day("2024-05-" + String((index % 27) + 1).padStart(2, "0"), close - 1, close + 1, close - 2, close));
  }
  const view = zoomBars(bars, 4);
  assert.equal(view.size, 180);
  assert.equal(view.bars[view.bars.length - 1].close, bars[199].close);
  assert.equal(zoomStep(4, -1, 200), 3);
  assert.equal(zoomStep(0, -1, 200), 0);
  assert.equal(zoomStep(4, 1, 50), 4);
  const greed = mood(bars);
  assert.equal(greed.label, "Greed");
  assert.ok(greed.score >= 65);
  const falling = bars.map((bar, index) => day(bar.date, 300 - index + 1, 300 - index + 2, 300 - index - 1, 300 - index));
  assert.equal(mood(falling).label, "Fear");
  const fitted = lesson(bars);
  assert.equal(fitted.arrows.some((mark) => mark.index === bars.length - 1), false);
  assert.ok(fitted.note.includes("Not a forecast"));
  const nudged = bars.slice(0, -1).concat([day(bars[199].date, bars[199].open, bars[199].high, bars[199].low, bars[199].close * 0.5)]);
  const again = lesson(nudged);
  assert.deepEqual(JSON.parse(JSON.stringify(fitted.arrows)), JSON.parse(JSON.stringify(again.arrows)));
});
