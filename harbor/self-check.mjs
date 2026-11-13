import { createHash } from "node:crypto";
import { readFileSync } from "node:fs";
import { pack } from "./pack.mjs";

const HOSTS = new Set([
  "www.mayoclinic.org",
  "www.mayoclinichealthsystem.org",
  "www.niddk.nih.gov",
  "my.clevelandclinic.org",
  "health.clevelandclinic.org",
  "www.pancreasfoundation.org",
  "pancreasfoundation.org",
  "medlineplus.gov",
  "healthy.kaiserpermanente.org",
  "www.clinicalnutritionjournal.com"
]);

const errors = [];
const ids = new Set();
const eat = pack.foods.filter((food) => food.group !== "avoid");
const groups = ["sip", "soft", "plate", "calm", "avoid"];

if (eat.length < 50) errors.push(`expected at least 50 flare foods, found ${eat.length}`);
for (const group of ["sip", "soft", "plate", "calm"]) {
  const count = pack.foods.filter((food) => food.group === group).length;
  if (count < 8) errors.push(`${group} has only ${count}`);
}
if (pack.foods.filter((food) => food.group === "avoid").length < 8) errors.push("skip list is short");
if (eat.filter((food) => food.cook === "none").length < 12) errors.push("not enough no-cook foods");
if (pack.emergency.items.length < 8) errors.push("emergency list is short");
if (pack.flareSteps.length !== 3) errors.push("flare steps must be exactly 3");
if (pack.sources.length < 8) errors.push("sources are short");

for (const id of pack.startHere) {
  if (!pack.foods.some((food) => food.id === id && food.group !== "avoid")) {
    errors.push(`start food missing: ${id}`);
  }
}

for (const food of pack.foods) {
  if (ids.has(food.id)) errors.push(`duplicate id ${food.id}`);
  ids.add(food.id);
  if (!groups.includes(food.group)) errors.push(`bad group ${food.id}`);
  if (!["none", "warm", "stove"].includes(food.cook)) errors.push(`bad cook ${food.id}`);
  if (!food.name || !food.line) errors.push(`unnamed ${food.id}`);
  if (food.group === "avoid") continue;
  if (!Array.isArray(food.steps) || food.steps.length < 3) errors.push(`steps ${food.id}`);
  if (!Array.isArray(food.needs) || food.needs.length < 1) errors.push(`needs ${food.id}`);
  if (!food.portion || !food.fat) errors.push(`portion ${food.id}`);
}

const sourceIds = new Set(pack.sources.map((source) => source.id));
for (const source of pack.sources) {
  let url;
  try { url = new URL(source.url); } catch { errors.push(`bad url ${source.id}`); continue; }
  if (url.protocol !== "https:" || !HOSTS.has(url.hostname)) errors.push(`host not allowed ${source.url}`);
}
for (const guide of pack.guides) {
  if (!guide.body || guide.body.length < 1) errors.push(`empty guide ${guide.id}`);
  for (const sourceId of guide.sourceIds) {
    if (!sourceIds.has(sourceId)) errors.push(`guide ${guide.id} missing source ${sourceId}`);
  }
}

const blob = JSON.stringify(pack);
if (/\d+\s*mg\b/i.test(blob)) errors.push("a drug-style mg dose slipped in");
if (/\b(cure|guaranteed|ibuprofen|acetaminophen|tylenol|oxycodone|morphine)\b/i.test(blob)) {
  errors.push("disallowed medical wording");
}

const file = readFileSync(new URL("./content.json", import.meta.url));
const sealed = readFileSync(new URL("./pack-hash.txt", import.meta.url), "utf8").trim();
const hash = createHash("sha256").update(file).digest("hex");
if (hash !== sealed) errors.push("pack-hash.txt does not match content.json");
const parsed = JSON.parse(file.toString("utf8"));
if (parsed.foods.length !== pack.foods.length) errors.push("content.json drifted from pack.mjs");

if (errors.length) {
  console.error(errors.join("\n"));
  process.exit(1);
}
console.log(`ok ${eat.length} foods seal ${hash.slice(0, 12)}`);
