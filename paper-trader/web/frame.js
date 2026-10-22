const params = new URLSearchParams(location.search);
const symbol = params.get("symbol") || "BTC-USD";
const frameId = params.get("frame") || "1D";
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
  const response = await fetch("/api/bars?symbol=" + encodeURIComponent(symbol) + "&frame=" + encodeURIComponent(frame.id), { cache: "no-store" });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.error || "The quote did not load");
  const bars = payload.bars || [];
  const last = bars.length ? bars[bars.length - 1] : null;
  const previous = bars.length > 1 ? bars[bars.length - 2] : last;
  const change = last && previous && previous.close ? ((last.close - previous.close) / previous.close) * 100 : 0;
  const signed = (change >= 0 ? "+" : "") + change.toFixed(2) + "%";
  const when = payload.as_of ? new Date(payload.as_of * 1000).toLocaleString() : (last ? last.date : "");
  cached = {
    bars,
    note: frame.label + " · " + signed + ". Market quote · " + when + ".",
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
