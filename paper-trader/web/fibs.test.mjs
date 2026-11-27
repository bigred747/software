import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import vm from "node:vm";

const dir = dirname(fileURLToPath(import.meta.url));
const source = readFileSync(join(dir, "fibs.js"), "utf8");

function loadFibs() {
  const root = { document: { readyState: "complete", addEventListener() {}, getElementById() { return null; } }, devicePixelRatio: 1 };
  vm.runInNewContext(source, { window: root, globalThis: root, console });
  return root.PaperFibs;
}

const Fibs = loadFibs();

function bar(date, o, h, l, c) {
  return { date, open: o, high: h, low: l, close: c, volume: 1e6 };
}

// Build a clear upswing then pullback.
const bars = [];
let px = 100;
for (let i = 0; i < 40; i += 1) {
  const c = px + 2;
  bars.push(bar(`2025-01-${String(i + 1).padStart(2, "0")}`, px, c + 1, px - 1, c));
  px = c;
}
const peak = px;
for (let i = 0; i < 20; i += 1) {
  const c = px - 1.5;
  bars.push(bar(`2025-02-${String(i + 1).padStart(2, "0")}`, px, px + 0.5, c - 0.5, c));
  px = c;
}

const swing = Fibs.fibSwingRead(bars);
assert.ok(swing, "swing exists");
assert.ok(swing.high >= peak - 5, "captures the rise");
assert.ok(swing.span > 10, "meaningful span");

const p618 = Fibs.fibPriceAt(swing, 61.8);
const expected = swing.high - swing.span * 0.618;
assert.ok(Math.abs(p618 - expected) < 1e-9, "61.8 math");

const ext = Fibs.fibExtPrice(swing, Fibs.TOS_FIB_EXT[0]);
assert.ok(ext > swing.high, "127.2+ above high");
const extDown = Fibs.fibExtPrice(swing, Fibs.TOS_FIB_EXT[4]);
assert.ok(extDown < swing.low, "127.2− below low");

const manual = Fibs.swingFromAnchors(
  { index: 10, price: 100 },
  { index: 50, price: 200 }
);
assert.equal(manual.low, 100);
assert.equal(manual.high, 200);
assert.equal(manual.upswing, true);

const nearBars = bars.slice();
nearBars[nearBars.length - 1] = {
  ...nearBars[nearBars.length - 1],
  close: p618,
  high: p618 + 1,
  low: p618 - 1,
};
const conf = Fibs.fibConfluence(swing, nearBars, Fibs.TOS_FIB_RETRACE);
assert.ok(conf && conf.hits.some((h) => h.label === "61.8%"), "confluence marks 61.8");

console.log("fibs.test.mjs ok");
