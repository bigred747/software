import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import vm from "node:vm";

const context = {};
context.globalThis = context;
vm.createContext(context);
vm.runInContext(fs.readFileSync(new URL("./frames.js", import.meta.url), "utf8"), context);
const { FRAMES, barsFor, analyze, brief, intradayDay, averages, stackAverages, compression, candlePlace, squeezeName, trendPivots, trueMomentum, cci, cciPane, volumeWave, hull, wma, deskClearRead, big4Indicator, zoomBars, zoomStep, mood, lesson, edge, pro, liveBar, closedBars, barIndexAt, hiddenTape, volatility, volumeProfile, rollingVwap, bollinger, macd, strategies, openingRange, squeezeRead, squeezeScore, internals, deskInternals, INTERNAL_SYMBOLS, describeBar, edgeLine, strengthRead, isAPlusSqueeze, dayBias, tideRead, fadeRead, bestFades, marketScorecard, marketOutlook, cycleMapRead, marketTankRead, defensiveShieldScan, riskReboundScan, marketForecastRead, forecastBoard, seatTrailRead, seatTrailMult, rangeSeat, SCORECARD_INDEXES, SCORECARD_SECTORS } = context.PaperFrames;

function day(date, open, high, low, close) {
  return { date, open, high, low, close, volume: 1000 };
}

function fallingVix(bars) {
  // Synthetic always-falling VIX so chart-stack Fire tests stay focused on TMO/Squeeze/CCI.
  return (bars || []).map((bar, index) => ({ date: String(bar.date).slice(0, 10), close: 40 - index * 0.01 }));
}

function edgeWithVix(bars, options) {
  return edge(bars, Object.assign({ vixBars: fallingVix(bars) }, options || {}));
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

test("desk clear read exposes VWAP chips and a non-empty bias", () => {
  const bars = [];
  for (let index = 0; index < 80; index += 1) {
    const close = 100 + index * 0.4 + Math.sin(index / 5) * 2;
    bars.push({
      date: "2024-06-" + String((index % 27) + 1).padStart(2, "0"),
      open: close - 0.5,
      high: close + 1,
      low: close - 1,
      close,
      volume: 1000 + index * 10,
    });
  }
  const read = deskClearRead(bars);
  assert.ok(read);
  assert.ok(["Bullish", "Bearish", "Mixed", "Waiting"].includes(read.bias));
  assert.ok(read.pctB == null || Number.isFinite(read.pctB));
  assert.ok(["Acceleration", "Deceleration", "Steady"].includes(read.stage));
});

test("cci pane builds three lengths and ±100 cross arrows", () => {
  const bars = [];
  for (let index = 0; index < 80; index += 1) {
    const close = index < 40 ? 100 + index * 2 : 180 - (index - 40) * 3;
    bars.push(day("2024-05-" + String((index % 27) + 1).padStart(2, "0"), close - 1, close + 2, close - 2, close));
  }
  const pane = cciPane(bars);
  assert.equal(pane.slow.length, 80);
  assert.equal(pane.mid.length, 80);
  assert.equal(pane.fast.length, 80);
  assert.equal(cci(bars, 50)[79], pane.slow[79]);
  assert.ok(pane.high > 100);
  assert.ok(pane.low < -100);
  assert.equal(pane.levels[0], -100);
  assert.equal(pane.levels[1], 0);
  assert.equal(pane.levels[2], 100);
  assert.equal(pane.up.length, 80);
  assert.equal(pane.down.length, 80);
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
  const stack = stackAverages(bars);
  assert.equal(stack.length, 6);
  assert.equal(stack.map((row) => row.length).join(","), "5,9,21,50,100,200");
  assert.equal(stack[0].values[4] != null, true);
  assert.equal(stack[5].values[39], null);
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
  const full = edgeWithVix(bars);
  assert.ok(full.ready);
  assert.ok(full.stages);
  assert.ok(full.big4);
  assert.equal(big4Indicator(bars).name, "big4indicator");
  assert.ok(full.trades.length > 0);
  assert.ok(["BUY", "SELL", "HOLD", "WAIT"].includes(full.call));
  assert.ok(full.score >= 0 && full.score <= 7);
  full.marks.forEach((mark) => {
    assert.equal(mark.price, bars[mark.index].open);
    assert.ok(mark.index >= 51);
    assert.equal(mark.stage, "fire");
  });
  full.trades.forEach((trade) => assert.ok(trade.exitDate > trade.entryDate));
  assert.ok(full.record.worst <= 0);
  assert.ok(full.record.winRate >= 0 && full.record.winRate <= 1);
  const cut = edgeWithVix(bars.slice(0, 240));
  const closedBefore = cut.trades.filter((trade) => trade.exitDate < bars[239].date);
  const sameInFull = full.trades.slice(0, closedBefore.length);
  assert.deepEqual(JSON.parse(JSON.stringify(sameInFull)), JSON.parse(JSON.stringify(closedBefore)));
  assert.equal(full.checks, 7);
  const sampleParts = full.allParts.find((row) => row);
  assert.ok(sampleParts);
  assert.deepEqual(Object.keys(sampleParts).sort(), ["above", "cci", "dots", "tmo", "tmoOs", "vix", "wave"]);
  assert.equal("band" in sampleParts, false);
  assert.equal("rising" in sampleParts, false);
  const again = edgeWithVix(bars);
  full.marks.filter((mark) => mark.side === "buy").forEach((mark) => {
    const signal = edgeWithVix(bars.slice(0, mark.index));
    assert.equal(signal.call, "BUY");
    assert.equal(signal.score, 7);
    assert.equal(signal.stage, "fire");
    assert.ok(signal.big4);
    assert.ok(Object.values(signal.parts).every(Boolean));
    assert.equal(signal.parts.cci, true);
    assert.equal(signal.parts.tmo, true);
    assert.equal(signal.parts.wave, true);
    assert.equal(signal.parts.vix, true);
  });
  assert.equal(again.trades.length, full.trades.length);
  const short = edgeWithVix(bars.slice(0, 30));
  assert.equal(short.ready, false);
  assert.equal(short.trades.length, 0);
  const noVix = edge(bars);
  assert.equal(noVix.vixReady, false);
  assert.ok((noVix.allParts.find((row) => row) || {}).vix === false);
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
  const full = edgeWithVix(bars, { live: true });
  const closed = edgeWithVix(bars.slice(0, -1));
  assert.equal(full.score, closed.score);
  assert.deepEqual(JSON.parse(JSON.stringify(full.parts)), JSON.parse(JSON.stringify(closed.parts)));
  assert.equal(full.signalDate, bars[bars.length - 2].date);
  for (const swing of [0.5, 1.5, 3]) {
    const moved = bars.map((bar, index) => (index === bars.length - 1 ? { ...bar, close: bar.open * swing, high: Math.max(bar.high, bar.open * swing), low: Math.min(bar.low, bar.open * swing) } : bar));
    const again = edgeWithVix(moved, { live: true });
    assert.equal(again.call, full.call);
    assert.equal(again.score, full.score);
    assert.deepEqual(again.marks, full.marks);
  }
  for (let cut = 120; cut <= bars.length; cut += 17) {
    const early = edgeWithVix(bars.slice(0, cut), { live: true });
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

function walk(count, seed, drift) {
  let state = seed;
  const next = () => {
    state = (state * 16807) % 2147483647;
    return state / 2147483647;
  };
  const bars = [];
  let price = 100;
  for (let index = 0; index < count; index += 1) {
    const open = price;
    price = Math.max(5, price * (1 + drift + (next() - 0.5) * 0.03));
    bars.push({ date: new Date(Date.UTC(2023, 0, 1 + index)).toISOString().slice(0, 10), open, high: Math.max(open, price) * 1.004, low: Math.min(open, price) * 0.996, close: price, volume: 1000 });
  }
  return bars;
}

test("strategies report classic public rows plus the four-study combo from closed bars only", () => {
  const bars = walk(600, 7, 0.0008);
  const board = JSON.parse(JSON.stringify(strategies(bars, { live: false })));
  assert.deepEqual(board.rows.map((row) => row.id), ["edge", "squeeze", "quad", "ccizoom", "ccisnap", "donchian", "rsi2", "golden", "dualema", "macdx", "bollrev", "atrtrail", "seatrail", "weeklow"]);
  const seatrail = board.rows.find((row) => row.id === "seatrail");
  assert.ok(seatrail);
  assert.equal(seatrail.name, "Seat Trail");
  const quad = board.rows.find((row) => row.id === "quad");
  assert.ok(quad);
  assert.equal(quad.name, "Squeeze · TMO · big4indicator · stack · MTF");
  const zoom = board.rows.find((row) => row.id === "ccizoom");
  const snap = board.rows.find((row) => row.id === "ccisnap");
  assert.ok(zoom && snap);
  assert.equal(zoom.name, "CCI zoom zone");
  assert.equal(snap.name, "CCI snap-back");
  board.rows.forEach((row) => {
    assert.ok(["BUY", "SELL", "HOLD", "WAIT"].includes(row.call));
    assert.ok(Number.isFinite(row.total));
  });
  const withLive = JSON.parse(JSON.stringify(strategies(bars.concat([{ ...bars[bars.length - 1], date: "2030-01-01", close: bars[bars.length - 1].close * 3, high: bars[bars.length - 1].close * 3 }]), { live: true })));
  assert.deepEqual(withLive.rows.map((row) => row.total), board.rows.map((row) => row.total));
});

test("each strategy lists its own past trades with dates", () => {
  const bars = walk(700, 5, 0.0009);
  const board = JSON.parse(JSON.stringify(strategies(bars, { live: false })));
  board.rows.filter((row) => row.ready).forEach((row) => {
    assert.ok(Array.isArray(row.log) && row.log.length <= 12);
    assert.ok(row.log.length === Math.min(12, row.trades));
    row.log.forEach((trade) => {
      assert.ok(trade.entryDate && trade.exitDate && trade.entryDate <= trade.exitDate);
      assert.ok(Number.isFinite(trade.pct) && trade.entry > 0 && trade.exit > 0);
    });
  });
});

test("a strategy never changes its past when new bars arrive", () => {
  const bars = walk(620, 11, 0.0005);
  const early = strategies(bars.slice(0, 560), { live: false });
  const later = strategies(bars, { live: false });
  early.rows.forEach((row, index) => {
    assert.ok(later.rows[index].trades >= row.trades);
  });
});

test("opening range reads the first bar, then the break", () => {
  const stamp = (day, hour, minute) => day + " " + String(hour).padStart(2, "0") + ":" + String(minute).padStart(2, "0");
  const bars = [
    { date: stamp("2026-11-10", 14, 30), open: 100, high: 102, low: 100, close: 101, volume: 1 },
    { date: stamp("2026-11-10", 14, 45), open: 101, high: 103, low: 101, close: 103, volume: 1 },
    { date: stamp("2026-11-10", 15, 0), open: 103, high: 105, low: 103, close: 104.5, volume: 1 },
    { date: stamp("2026-11-11", 14, 30), open: 100, high: 102, low: 100, close: 101, volume: 1 },
    { date: stamp("2026-11-11", 14, 45), open: 101, high: 101.5, low: 99, close: 99.5, volume: 1 },
    { date: stamp("2026-11-12", 14, 30), open: 100, high: 102, low: 100, close: 101, volume: 1 },
    { date: stamp("2026-11-12", 14, 45), open: 101, high: 101.8, low: 100.5, close: 101.2, volume: 1 },
  ];
  const result = JSON.parse(JSON.stringify(openingRange(bars, { utcLabels: true })));
  assert.equal(result.state, "ARMED");
  assert.equal(result.latest.trigger, 102);
  assert.equal(result.latest.floor, 100);
  assert.equal(result.record.wins, 1);
  assert.equal(result.record.skipped, 1);
  assert.equal(openingRange([], {}).ready, false);
});

test("squeeze score is bounded and ranks a stacked clean squeeze above a mixed one", () => {
  const strong = squeezeScore({ ready: true, state: "ON", dots: 9, level: 3, direction: "bull", stack: "positive", clean: true, rising: true, weekly: true }, { sector: true, index: true });
  const weak = squeezeScore({ ready: true, state: "ON", dots: 2, level: 2, direction: "bull", stack: "mixed", clean: false, rising: false, weekly: false }, {});
  assert.ok(strong.score <= 100 && strong.score > weak.score);
  assert.equal(strong.triple, true);
  assert.equal(squeezeScore({ ready: true, state: "OFF" }, {}).score, 0);
  assert.equal(squeezeName(3), "High");
  assert.equal(squeezeName(2), "Mid");
  assert.equal(squeezeName(1), "Low");
  assert.equal(squeezeName(0), "Off");
  const read = squeezeRead(walk(300, 5, 0.0003), { live: false });
  assert.equal(read.ready, true);
  assert.ok(["ON", "FIRED", "OFF"].includes(read.state));
  assert.equal(squeezeRead(walk(30, 5, 0), {}).ready, false);
});

test("internals roll real gauges into a label and never guess a missing one", () => {
  const data = {};
  INTERNAL_SYMBOLS.forEach((symbol, index) => {
    data[symbol] = walk(300, 20 + index, symbol === "^VIX" ? -0.001 : 0.001);
  });
  const full = JSON.parse(JSON.stringify(internals(data)));
  assert.equal(full.ready, true);
  assert.ok(["Supportive", "Mixed", "Defensive"].includes(full.label));
  assert.equal(full.coverage, 100);
  assert.equal(full.missing.length, 0);
  const partial = JSON.parse(JSON.stringify(internals({ SPY: data.SPY, QQQ: data.QQQ })));
  assert.equal(partial.ready, false);
  assert.equal(partial.label, "Unknown");
  assert.ok(partial.missing.includes("Fear gauge (VIX)"));
  assert.equal(internals({}).ready, false);
});

test("deskInternals folds Squeeze · TMO · big4 · stack · MTF into the internals path", () => {
  const short = deskInternals(walk(40, 12, 0.001), { live: false });
  assert.equal(short.ready, false);
  assert.ok(short.missing.includes("Squeeze"));
  const desk = JSON.parse(JSON.stringify(deskInternals(walk(320, 18, 0.0012), { live: false })));
  assert.equal(desk.ready, true);
  assert.deepEqual(
    desk.parts.map((part) => part.id),
    ["squeeze", "tmo", "big4", "stack", "week", "month"]
  );
  assert.equal(typeof desk.squeezeOk, "boolean");
  assert.equal(typeof desk.tmoOk, "boolean");
  assert.equal(typeof desk.big4Ok, "boolean");
  assert.equal(typeof desk.stackOk, "boolean");
  assert.equal(typeof desk.mtfOk, "boolean");
  assert.ok(desk.score >= 0 && desk.score <= 100);
});

function intraday(count, seed, minutes) {
  const daily = walk(count, seed, 0.0004);
  return daily.map((bar, index) => {
    const at = new Date(Date.UTC(2026, 9, 1, 13, 30) + index * minutes * 60000);
    const label = at.toISOString().slice(0, 16).replace("T", " ");
    return { ...bar, date: label };
  });
}

test("Edge reads every timeframe's own bars, daily or intraday", () => {
  ["1m", "5m", "15m", "30m", "1h", "4h"].forEach((id, index) => {
    const bars = intraday(400, 3 + index, [1, 5, 15, 30, 60, 240][index]);
    const read = JSON.parse(JSON.stringify(edgeWithVix(bars, { live: false })));
    assert.equal(read.ready, true, id);
    assert.ok(["BUY", "SELL", "HOLD", "WAIT"].includes(read.call), id);
    assert.equal(read.scores.length, bars.length);
    assert.match(edgeLine(bars, false), /^Edge (BUY|SELL|HOLD|WAIT)/);
  });
  const few = intraday(30, 9, 5);
  assert.match(edgeLine(few, false), /needs at least/);
});

test("the price box lists open, high, low, close, change and the Edge checks for that bar", () => {
  const bars = intraday(300, 21, 15);
  const info = describeBar(bars, 200, { crypto: true });
  assert.equal(info.rows.slice(0, 4).map((row) => row[0]).join(","), "Open,High,Low,Close");
  assert.ok(info.rows.some((row) => row[0] === "Change"));
  assert.equal(info.title, bars[200].date);
  assert.match(info.edgeNote, /^Edge on this bar: [0-7] of 7/);
  assert.equal(describeBar(bars, -1, {}), null);
  assert.equal(describeBar(bars, 300, {}), null);
  assert.equal(describeBar(bars, 0, { crypto: true }).rows.some((row) => row[0] === "Change"), false);
});

test("Big 3 Market Scorecard ranks strength and day bias", () => {
  assert.ok(SCORECARD_INDEXES.includes("SPY"));
  assert.ok(SCORECARD_SECTORS.includes("XLK"));
  const up = [];
  for (let index = 0; index < 80; index += 1) {
    const close = 100 + index * 0.8;
    up.push(day("2024-03-" + String((index % 28) + 1).padStart(2, "0"), close - 1, close + 1, close - 2, close));
  }
  const down = [];
  for (let index = 0; index < 80; index += 1) {
    const close = 180 - index * 0.7;
    down.push(day("2024-03-" + String((index % 28) + 1).padStart(2, "0"), close + 1, close + 2, close - 1, close));
  }
  const strong = strengthRead(up);
  const weak = strengthRead(down);
  assert.equal(strong.ready, true);
  assert.ok(strong.score > weak.score);
  assert.equal(strong.stacked, true);
  const flat = dayBias({ ready: true, label: "Defensive", score: 30 }, [{ symbol: "SPY", stacked: false, score: 20 }]);
  assert.equal(flat.bias, "Stay flat");
  const swing = dayBias({ ready: true, label: "Supportive", score: 72 }, [
    { symbol: "SPY", stacked: true, score: 80 },
    { symbol: "QQQ", stacked: true, score: 78 },
  ]);
  assert.equal(swing.bias, "Swing trades");
  const card = marketScorecard({
    bars: { SPY: up, QQQ: up, XLK: up, XLU: down },
    internals: { ready: true, label: "Supportive", score: 70 },
    squeezes: [],
    indexSymbols: ["SPY", "QQQ"],
    sectorSymbols: ["XLK", "XLU"],
  });
  assert.deepEqual(card.indexes.map((row) => row.symbol).sort(), ["QQQ", "SPY"]);
  assert.equal(card.sectors[0].symbol, "XLK");
  assert.ok(card.sectors[0].score > card.sectors[1].score);
  assert.equal(card.day.bias, "Swing trades");
  assert.equal(isAPlusSqueeze({ ready: true, direction: "bear", rising: true, stack: "positive", level: 3, dots: 6, clean: true }, { score: 90 }), false);
  assert.equal(isAPlusSqueeze({ ready: true, direction: "bull", rising: true, stack: "positive", level: 3, dots: 6, clean: true }, { score: 90 }), true);
});

test("Tide Alert drains soft sectors and Go Long 5 ranks pressure names", () => {
  const soft = [];
  for (let index = 0; index < 80; index += 1) {
    const close = 160 - index * 0.6;
    soft.push(day("2024-04-" + String((index % 28) + 1).padStart(2, "0"), close + 1, close + 2, close - 1, close));
  }
  const indexes = ["SPY", "QQQ", "IWM"].map((symbol) => Object.assign({ symbol }, strengthRead(soft)));
  const sectors = ["XLK", "SMH", "XLF", "XLE", "XLV", "XLI", "XLY", "XLP", "XLU", "XLB"].map((symbol) =>
    Object.assign({ symbol }, strengthRead(soft))
  );
  const unread = tideRead({ indexes: [], sectors: [], spyBars: soft });
  assert.equal(unread.on, false);
  assert.match(unread.why, /fail closed|not ready/i);
  const tide = tideRead({ indexes, sectors, spyBars: soft });
  assert.equal(tide.ready, true);
  assert.equal(tide.drain, true);
  assert.equal(tide.indexSoft, true);
  assert.equal(tide.on, true);
  assert.match(tide.bias, /Tide Alert/);
  const card = marketScorecard({
    bars: Object.fromEntries(indexes.concat(sectors).map((row) => [row.symbol, soft])),
    internals: { ready: true, label: "Defensive", score: 20 },
    squeezes: [],
    indexSymbols: indexes.map((row) => row.symbol),
    sectorSymbols: sectors.map((row) => row.symbol),
  });
  assert.equal(card.tide.on, true);
  assert.match(card.day.bias, /Tide Alert/);
  const fade = fadeRead(soft, { live: false, vixBars: [] });
  assert.equal(fade.ready, true);
  assert.ok(fade.score >= 45);
  const ranked = bestFades([
    { symbol: "AAA", ready: true, score: 70, edgeFire: false },
    { symbol: "BBB", ready: true, score: 40, edgeFire: false },
    { symbol: "CCC", ready: true, score: 90, edgeFire: true },
    { symbol: "DDD", ready: true, score: 80, edgeFire: false },
  ]);
  assert.deepEqual(
    ranked.map((row) => row.symbol),
    ["DDD", "AAA"]
  );
});

test("Market Outlook scores Day Week Month from SPY closed bars", () => {
  function sequential(startClose, step, count) {
    const rows = [];
    const start = Date.UTC(2022, 0, 3);
    for (let index = 0; index < count; index += 1) {
      const stamp = new Date(start + index * 86400000);
      const iso =
        stamp.getUTCFullYear() +
        "-" +
        String(stamp.getUTCMonth() + 1).padStart(2, "0") +
        "-" +
        String(stamp.getUTCDate()).padStart(2, "0");
      const close = startClose + index * step;
      rows.push(day(iso, close - 1, close + 2, close - 2, close));
    }
    return rows;
  }
  const up = sequential(100, 0.35, 420);
  const down = sequential(220, -0.35, 420);
  const bull = marketOutlook({ spyBars: up });
  assert.equal(bull.ready, true);
  assert.ok(bull.day.score >= 7);
  assert.equal(bull.day.color, "green");
  assert.equal(bull.day.stance, "Go long");
  assert.ok(bull.week.ready);
  assert.ok(bull.month.ready);
  assert.equal(bull.call, "Go long");
  assert.ok(bull.board >= 7);
  const soft = marketOutlook({ spyBars: down });
  assert.equal(soft.ready, true);
  assert.ok(soft.day.score <= 4);
  assert.equal(soft.day.color, "red");
  assert.equal(soft.day.stance, "Short bias");
  assert.match(soft.call, /Short bias|Stay flat/);
  const tide = marketOutlook({ spyBars: up, tide: { on: true, why: "Tide Alert on." } });
  assert.equal(tide.call, "Stay flat · Tide");
  assert.equal(tide.color, "amber");
});

test("cycle desk reads stages and tank modes with own names", () => {
  function sequential(startClose, step, count) {
    const rows = [];
    const stamp = new Date(Date.UTC(2020, 0, 1));
    for (let index = 0; index < count; index += 1) {
      stamp.setUTCDate(stamp.getUTCDate() + 1);
      const iso =
        stamp.getUTCFullYear() +
        "-" +
        String(stamp.getUTCMonth() + 1).padStart(2, "0") +
        "-" +
        String(stamp.getUTCDate()).padStart(2, "0");
      const close = startClose + index * step;
      rows.push(day(iso, close - 1, close + 2, close - 2, close));
    }
    return rows;
  }
  const unread = cycleMapRead(sequential(100, 0.1, 40));
  assert.equal(unread.ready, false);
  const climb = sequential(100, 0.4, 220);
  const cycle = cycleMapRead(climb);
  assert.equal(cycle.ready, true);
  assert.ok(cycle.label);
  assert.ok(Array.isArray(cycle.maps));
  assert.equal(cycle.why.includes("Own desk"), true);
  const tank = marketTankRead(sequential(200, -0.5, 220));
  assert.equal(tank.ready, true);
  assert.ok(tank.mode === "tank" || tank.mode === "soft");
  const shield = defensiveShieldScan({
    market: tank,
    bars: { XLU: climb, XLP: climb },
    symbols: ["XLU", "XLP"],
  });
  assert.equal(shield.ready, true);
  const rebound = riskReboundScan({
    market: { ready: true, mode: "calm", label: "Market calm" },
    bars: { QQQ: climb },
    symbols: ["QQQ"],
  });
  assert.equal(rebound.active, false);
  assert.equal(rebound.rows.length, 0);
});

test("marketForecastRead builds base bull bear and desk score", () => {
  function sequential(startClose, step, count) {
    const rows = [];
    const stamp = new Date(Date.UTC(2020, 0, 1));
    for (let index = 0; index < count; index += 1) {
      stamp.setUTCDate(stamp.getUTCDate() + 1);
      const iso =
        stamp.getUTCFullYear() +
        "-" +
        String(stamp.getUTCMonth() + 1).padStart(2, "0") +
        "-" +
        String(stamp.getUTCDate()).padStart(2, "0");
      const close = startClose + index * step;
      rows.push(day(iso, close - 1, close + 2, close - 2, close));
    }
    return rows;
  }
  const unread = marketForecastRead(sequential(100, 0.1, 40), { symbol: "TEST" });
  assert.equal(unread.ready, false);
  const climb = sequential(100, 0.4, 280);
  const spy = sequential(400, 0.2, 280);
  const read = marketForecastRead(climb, { symbol: "TEST", spyBars: spy, cash: 10000 });
  assert.equal(read.ready, true);
  assert.ok(read.near && read.near.base > 0 && read.near.bull > read.near.base && read.near.bear < read.near.base);
  assert.ok(read.far && read.far.base > 0);
  assert.ok(read.deskScore >= 1 && read.deskScore <= 100);
  assert.ok(Array.isArray(read.assumptions) && read.assumptions.length >= 3);
  assert.ok(read.invalidation);
  assert.ok(read.size && read.size.note);
  assert.equal(read.why.includes("crystal ball"), true);
  assert.ok(read.vsSpy && read.vsSpy.ready);
  const board = forecastBoard({
    symbols: ["TEST", "WEAK"],
    bars: { TEST: climb, WEAK: sequential(100, -0.2, 280) },
    spyBars: spy,
    cash: 10000,
  });
  assert.equal(board.ready, true);
  assert.ok(board.rows.length >= 1);
  assert.equal(board.rows[0].symbol, "TEST");
});


test("Desk Waves fail closed without volume and build Hull ATR waves with volume", () => {
  const blank = [];
  for (let index = 0; index < 40; index += 1) {
    const close = 100 + ((index % 7) - 3) * 1.4;
    blank.push({
      date: "2024-03-" + String((index % 27) + 1).padStart(2, "0"),
      open: close - 0.5,
      high: close + 1.5,
      low: close - 1.5,
      close,
      volume: 0,
    });
  }
  const empty = volumeWave(blank, { n: 6, atrMult: 0.7 });
  assert.equal(empty.hasVolume, false);
  assert.equal(empty.ready, false);
  assert.ok(empty.up.every((value) => value == null));

  const bars = blank.map((bar, index) => ({ ...bar, volume: 1000 + index * 10 }));
  const study = volumeWave(bars, { n: 6, atrMult: 0.7, avgLen: 6, avgType: "sma" });
  assert.equal(study.hasVolume, true);
  assert.equal(study.ready, true);
  assert.equal(study.n, 6);
  assert.equal(study.atrMult, 0.7);
  assert.ok(study.up.some((value) => value != null && value > 0) || study.dn.some((value) => value != null && value < 0));
  assert.ok(study.avg.some((value) => value != null));
  assert.ok(study.paint.some((value) => value === "bull" || value === "bear"));
  const gate = hull([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12], 6);
  assert.ok(gate.some((value) => value != null));
  assert.ok(wma([1, 2, 3, 4, 5], 3)[4] != null);
});

test("Edge Fire requires Desk Waves green / up when volume is present", () => {
  const bars = [];
  for (let index = 0; index < 260; index += 1) {
    const close = 100 + index * 0.4 + Math.sin(index / 7) * 3;
    bars.push({
      date: new Date(Date.UTC(2024, 0, 1) + index * 86400000).toISOString().slice(0, 10),
      open: close - 0.5,
      high: close + 1.2,
      low: close - 1.2,
      close,
      volume: 1000 + index * 5,
    });
  }
  const withVol = edgeWithVix(bars);
  assert.equal(withVol.checks, 7);
  const blank = bars.map((bar) => ({ ...bar, volume: 0 }));
  const noVol = edgeWithVix(blank);
  const sample = noVol.allParts.find((row) => row);
  assert.ok(sample);
  assert.equal(sample.wave, false);
  withVol.marks.filter((mark) => mark.side === "buy").forEach((mark) => {
    assert.equal(withVol.allParts[mark.index - 1].wave, true);
  });
});

test("Seat Trail seat-aware trail mult and fail-closed without volume", () => {
  assert.equal(seatTrailMult("high"), 3);
  assert.equal(seatTrailMult("mid"), 2.2);
  assert.equal(seatTrailMult("low"), 1.6);
  const blank = [];
  let price = 50;
  for (let index = 0; index < 120; index += 1) {
    price = price * 1.004;
    blank.push({
      date: "2024-04-" + String((index % 27) + 1).padStart(2, "0"),
      open: price - 0.4,
      high: price + 0.8,
      low: price - 0.8,
      close: price,
      volume: 0,
    });
  }
  const noVol = seatTrailRead(blank, { live: false });
  assert.equal(noVol.ready, true);
  assert.equal(noVol.armed, false);
  assert.equal(noVol.hasVolume, false);
  assert.match(noVol.why, /no volume/i);

  const bars = blank.map((bar, index) => {
    const surge = index === blank.length - 1;
    return {
      ...bar,
      high: surge ? bar.close * 1.08 : bar.high,
      close: surge ? bar.close * 1.06 : bar.close,
      volume: surge ? 50000 : 1000 + index,
    };
  });
  const read = seatTrailRead(bars, { live: false });
  assert.equal(read.ready, true);
  assert.equal(read.hasVolume, true);
  assert.ok(read.trailMult >= 1.6);
  const seat = rangeSeat(bars);
  assert.ok(seat && seat.ready);
  const board = strategies(bars, { live: false });
  const row = board.rows.find((item) => item.id === "seatrail");
  assert.ok(row && row.ready);
  assert.ok(["BUY", "SELL", "HOLD", "WAIT"].includes(row.call));
});
