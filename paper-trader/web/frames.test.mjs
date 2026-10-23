import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import vm from "node:vm";

const context = {};
context.globalThis = context;
vm.createContext(context);
vm.runInContext(fs.readFileSync(new URL("./frames.js", import.meta.url), "utf8"), context);
const { FRAMES, barsFor, analyze, brief, intradayDay, averages, compression, candlePlace, squeezeName, trendPivots, trueMomentum, zoomBars, zoomStep, mood, lesson, edge, pro, liveBar, closedBars, barIndexAt, hiddenTape, volatility, volumeProfile, rollingVwap, bollinger, macd } = context.PaperFrames;

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
  assert.equal(full.checks, 5);
  const again = edge(bars);
  full.marks.filter((mark) => mark.side === "buy").forEach((mark) => {
    const signal = edge(bars.slice(0, mark.index));
    assert.equal(signal.call, "BUY");
    assert.equal(signal.score, 5);
    assert.ok(Object.values(signal.parts).every(Boolean));
  });
  assert.equal(again.trades.length, full.trades.length);
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

test("volume profile, VWAP, bands, MACD, and ATR size use only real bars", () => {
  const bars = [];
  for (let index = 0; index < 240; index += 1) {
    const close = 100 + Math.sin(index / 8) * 5 + index * 0.05;
    const date = new Date(Date.UTC(2024, 0, 1) + index * 86400000).toISOString().slice(0, 10);
    bars.push({ date, open: close - 0.5, high: close + 1, low: close - 1, close, volume: index % 10 === 0 ? 5000 : 100 });
  }
  const profile = volumeProfile(bars, 24);
  assert.equal(profile.rows.length, 24);
  assert.ok(profile.poc >= profile.low && profile.poc <= profile.high);
  const inside = profile.rows.filter((row) => row.low >= profile.low - 1e-9 && row.high <= profile.high + 1e-9).reduce((sum, row) => sum + row.volume, 0);
  assert.ok(inside >= profile.total * 0.7 - 1e-6);
  const flat = bars.map((bar) => ({ ...bar, volume: 0 }));
  assert.equal(volumeProfile(flat, 24).poc, null);
  assert.equal(rollingVwap(flat, 20)[239], null);
  const vwap = rollingVwap(bars, 20);
  assert.equal(vwap[18], null);
  const window = bars.slice(220, 240);
  const expected = window.reduce((sum, bar) => sum + ((bar.high + bar.low + bar.close) / 3) * bar.volume, 0) / window.reduce((sum, bar) => sum + bar.volume, 0);
  assert.ok(Math.abs(vwap[239] - expected) < 1e-9);
  const bands = bollinger(bars, 20, 2);
  assert.ok(bands.upper[239] > bands.mid[239] && bands.mid[239] > bands.lower[239]);
  const trend = macd(bars);
  assert.ok(Math.abs(trend.hist[239] - (trend.line[239] - trend.signal[239])) < 1e-12);
  const desk = pro(bars, 10000);
  assert.ok(desk.last.rsi >= 0 && desk.last.rsi <= 100);
  assert.ok(Math.abs(desk.risk.cash - 100) < 1e-9);
  assert.ok(Math.abs(desk.risk.units * (desk.close - desk.risk.stop) - 100) < 1e-6 || desk.risk.capped);
  assert.ok(desk.risk.units * desk.close <= 10000 + 1e-6);
});

test("volatility is annualized from closed bars and ranked against the past year", () => {
  const calm = [];
  const wild = [];
  for (let index = 0; index < 300; index += 1) {
    const date = new Date(Date.UTC(2024, 0, 1) + index * 86400000).toISOString().slice(0, 10);
    const step = index % 2 ? 1.01 : 1 / 1.01;
    const swing = index > 280 ? (index % 2 ? 1.05 : 1 / 1.05) : step;
    const priorCalm = calm.length ? calm[calm.length - 1].close : 100;
    const priorWild = wild.length ? wild[wild.length - 1].close : 100;
    calm.push({ date, open: priorCalm, high: priorCalm * 1.02, low: priorCalm * 0.98, close: priorCalm * step, volume: 1 });
    wild.push({ date, open: priorWild, high: priorWild * 1.06, low: priorWild * 0.94, close: priorWild * swing, volume: 1 });
  }
  const quiet = volatility(calm, 20);
  const expected = Math.sqrt(20 / 19) * Math.log(1.01) * Math.sqrt(365);
  assert.ok(Math.abs(quiet.yearly - expected) < 1e-9);
  assert.equal(quiet.year, 365);
  const loud = volatility(wild, 20);
  assert.ok(loud.yearly > quiet.yearly * 3);
  assert.equal(loud.regime, "Extreme");
  assert.ok(loud.rank > 0.9);
  assert.equal(volatility(calm.slice(0, 10), 20).yearly, null);
});

test("today's forming bar never makes an Edge signal and old marks never move", () => {
  const bars = [];
  for (let index = 0; index < 320; index += 1) {
    const wave = Math.sin(index / 7) * 10 + Math.sin(index / 23) * 18;
    const close = 150 + index * 0.2 + wave;
    const open = close - Math.cos(index / 4) * 1.2;
    const date = new Date(Date.UTC(2023, 0, 1) + index * 86400000).toISOString().slice(0, 10);
    bars.push({ date, open, high: Math.max(open, close) + 1.2, low: Math.min(open, close) - 1.2, close, volume: 10 });
  }
  const full = edge(bars, { live: true });
  const closed = edge(bars.slice(0, -1));
  assert.equal(full.score, closed.score);
  assert.deepEqual(JSON.parse(JSON.stringify(full.parts)), JSON.parse(JSON.stringify(closed.parts)));
  assert.equal(full.signalDate, bars[bars.length - 2].date);
  for (const swing of [0.5, 1.5, 3]) {
    const moved = bars.map((bar, index) => (index === bars.length - 1 ? { ...bar, close: bar.open * swing, high: Math.max(bar.high, bar.open * swing), low: Math.min(bar.low, bar.open * swing) } : bar));
    const again = edge(moved, { live: true });
    assert.equal(again.call, full.call);
    assert.equal(again.score, full.score);
    assert.deepEqual(again.marks, full.marks);
  }
  for (let cut = 120; cut <= bars.length; cut += 17) {
    const early = edge(bars.slice(0, cut), { live: true });
    const later = full.marks.filter((mark) => mark.index < cut);
    assert.deepEqual(JSON.parse(JSON.stringify(early.marks)), JSON.parse(JSON.stringify(later)));
  }
});

test("hidden tape uses closed public volume and never invents a dark-pool print", () => {
  const quiet = [];
  for (let index = 0; index < 40; index += 1) {
    const close = 100 + index * 0.1;
    quiet.push({ date: "2024-03-" + String((index % 27) + 1).padStart(2, "0"), open: close - 0.4, high: close + 1, low: close - 1, close, volume: 100 });
  }
  const absorbed = quiet.map((bar, index) => (index === quiet.length - 1 ? { ...bar, high: bar.close + 0.1, low: bar.close - 0.1, volume: 400 } : bar));
  const moved = quiet.map((bar, index) => (index === quiet.length - 1 ? { ...bar, high: bar.close + 6, low: bar.close - 6, volume: 400 } : bar));
  const light = quiet.map((bar, index) => (index === quiet.length - 1 ? { ...bar, volume: 20 } : bar));
  const empty = quiet.map((bar) => ({ ...bar, volume: 0 }));
  const hold = hiddenTape(absorbed);
  assert.equal(hold.absorption, "absorption");
  assert.ok(hold.rvol > 3);
  assert.equal(hiddenTape(moved).absorption, "initiative");
  assert.equal(hiddenTape(light).absorption, "quiet");
  assert.equal(hiddenTape(empty).rvol, null);
  assert.equal(hiddenTape(empty).volume, null);
  const withBook = hiddenTape(absorbed, { bid: 10, ask: 10.02, crypto: true });
  assert.equal(withBook.kind, "crypto");
  assert.match(withBook.note, /public exchange tape/);
  assert.ok(Math.abs(withBook.spread - 0.02 / 10.01) < 1e-9);
  const stock = hiddenTape(absorbed, { kind: "stock", relVol: 1.19, afterVolume: 6248638 });
  assert.match(stock.note, /Dark pool books/);
  assert.equal(stock.feedRelVol, 1.19);
  assert.equal(stock.afterVolume, 6248638);
  const source = fs.readFileSync(new URL("./frames.js", import.meta.url), "utf8");
  assert.equal(/dark pool print|invent.*dark/i.test(source), false);
});

test("a click on the chart maps to the candle under that x", () => {
  assert.equal(barIndexAt(10, 10, 5, 10), 0);
  assert.equal(barIndexAt(10, 10, 5, 19.9), 0);
  assert.equal(barIndexAt(10, 10, 5, 35), 2);
  assert.equal(barIndexAt(10, 10, 5, 59), 4);
  assert.equal(barIndexAt(10, 10, 5, -4), 0);
  assert.equal(barIndexAt(10, 0, 5, 20), -1);
});

test("a bar is forming until its day ends in the market's own clock", () => {
  const noonDenver = new Date("2026-10-22T18:00:00Z");
  const crypto = [{ date: "2026-10-21" }, { date: "2026-10-22" }];
  assert.equal(liveBar(crypto, true, noonDenver), true);
  assert.equal(closedBars(crypto, true, noonDenver).length, 1);
  assert.equal(liveBar(crypto, true, new Date("2026-10-23T00:30:00Z")), false);
  const stock = [{ date: "2026-10-21" }, { date: "2026-10-22" }];
  assert.equal(liveBar(stock, false, new Date("2026-10-23T01:00:00Z")), true);
  assert.equal(liveBar(stock, false, new Date("2026-10-23T13:00:00Z")), false);
});
