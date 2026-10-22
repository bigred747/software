const params = new URLSearchParams(location.search);
const symbol = params.get("symbol") || "BTC-USD";
const frameId = params.get("frame") || "1D";
let cached = null;
let zoomStop = PaperFrames.DEFAULT_ZOOM;

function title(text) {
  document.title = text;
  const node = document.getElementById("frame-title");
  if (node) node.textContent = text;
}

function paint() {
  if (!cached) return;
  const note = document.getElementById("frame-note");
  const tone = PaperFrames.mood(cached.bars);
  const fitted = PaperFrames.lesson(cached.bars);
  const moodLine = tone.score == null ? "" : " " + tone.label + " " + tone.score + ".";
  if (note) note.textContent = cached.note + moodLine + " Lesson: " + fitted.rule + ".";
  PaperFrames.draw(document.getElementById("frame-chart"), cached.bars, zoomStop);
  const view = PaperFrames.zoomBars(cached.bars, zoomStop);
  const label = document.getElementById("zoom-label");
  const inn = document.getElementById("zoom-in");
  const out = document.getElementById("zoom-out");
  if (label) label.textContent = view.size + " bars";
  if (inn) inn.disabled = view.atMin;
  if (out) out.disabled = view.atMax;
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

document.getElementById("zoom-in").addEventListener("click", () => {
  zoomStop = PaperFrames.zoomStep(zoomStop, -1, cached ? cached.bars.length : 0);
  paint();
});
document.getElementById("zoom-out").addEventListener("click", () => {
  zoomStop = PaperFrames.zoomStep(zoomStop, 1, cached ? cached.bars.length : 0);
  paint();
});
document.getElementById("frame-chart").addEventListener("wheel", (event) => {
  event.preventDefault();
  zoomStop = PaperFrames.zoomStep(zoomStop, event.deltaY > 0 ? 1 : -1, cached ? cached.bars.length : 0);
  paint();
}, { passive: false });

window.addEventListener("resize", paint);
