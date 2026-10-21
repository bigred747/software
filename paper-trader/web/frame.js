const params = new URLSearchParams(location.search);
const symbol = params.get("symbol") || "BTC-USD";
const frameId = params.get("frame") || "1D";
const cursor = Number(params.get("cursor") || "0");
let cached = null;

function title(text) {
  document.title = text;
  const node = document.getElementById("frame-title");
  if (node) node.textContent = text;
}

function paint() {
  if (!cached) return;
  const note = document.getElementById("frame-note");
  if (note) note.textContent = cached.note;
  PaperFrames.draw(document.getElementById("frame-chart"), cached.bars);
}

async function main() {
  const frame = PaperFrames.frameById(frameId);
  title(symbol + " · " + frame.label);
  const response = await fetch("/api/market?symbol=" + encodeURIComponent(symbol), { cache: "no-store" });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.error || "The sample did not load");
  const daily = (payload.bars || []).slice(0, cursor > 0 ? cursor : undefined);
  const bars = PaperFrames.barsFor(daily, frame, 160);
  const row = PaperFrames.analyze(daily).find((item) => item.id === frame.id);
  const change = row ? ((row.change >= 0 ? "+" : "") + row.change.toFixed(2) + "%") : "";
  cached = {
    bars,
    note: frame.label + (change ? " · " + change : "") + " · " + (row ? row.bias : "flat")
      + ". Built from the daily sample through " + ((row && row.asOf) || "the session") + ". Not a live quote.",
  };
  paint();
  requestAnimationFrame(paint);
}

main().catch((error) => {
  title(symbol);
  const note = document.getElementById("frame-note");
  if (note) note.textContent = error.message;
});

window.addEventListener("resize", paint);
