import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import test from "node:test";

const context = {};
context.globalThis = context;
vm.createContext(context);
vm.runInContext(fs.readFileSync(new URL("./study.js", import.meta.url), "utf8"), context);
const { runStudy } = context.PaperStudy;

function bars(closes) {
  return closes.map((close, index) => ({
    date: "2024-01-" + String(index + 2).padStart(2, "0"),
    open: close,
    high: close + 1,
    low: Math.max(0.1, close - 1),
    close,
    volume: 100,
  }));
}

test("average and cross markers stay on the sample", () => {
  const source = `
    input length = 3;
    def baseline = Average(close, length);
    plot Baseline = baseline;
    plot Buy = close crosses above baseline;
    plot Sell = close crosses below baseline;
  `;
  const result = JSON.parse(JSON.stringify(runStudy(source, bars([5, 4, 3, 2, 3, 4, 5, 1]))));
  assert.equal(result.plots[0].name, "Baseline");
  assert.equal(result.plots[0].values[2], 4);
  assert.equal(result.plots[1].values[4], 1);
  assert.equal(result.plots[2].values[7], 1);
});

test("order markers do not leave the page and text math is refused", () => {
  const marked = runStudy("AddOrder(OrderType.BUY_AUTO, close > open);", bars([1, 2, 3]));
  assert.equal(marked.plots[0].side, "buy");
  assert.equal(marked.plots[0].values[0], 0);
  assert.throws(() => runStudy("plot X = close; plot Y = \"http://example\";", bars([1])), /text|prices and math/);
  assert.throws(() => runStudy("def x = fold i = 0 to 10 with s = 0 do s + i;", bars([1])), /Fold/);
});

test("compound value can reference the previous bar", () => {
  const result = JSON.parse(JSON.stringify(runStudy("def total = CompoundValue(1, total[1] + close, close); plot Total = total;", bars([2, 3, 4]))));
  assert.deepEqual(result.plots[0].values, [2, 5, 9]);
});
