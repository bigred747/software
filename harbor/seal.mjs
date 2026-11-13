import { createHash } from "node:crypto";
import { writeFileSync } from "node:fs";
import { pack } from "./pack.mjs";

const pretty = JSON.stringify(pack, null, 2) + "\n";
const hash = createHash("sha256").update(pretty).digest("hex");
writeFileSync(new URL("./content.json", import.meta.url), pretty);
writeFileSync(new URL("./pack-hash.txt", import.meta.url), hash + "\n");
const eat = pack.foods.filter((food) => food.group !== "avoid").length;
console.log(`sealed ${eat} foods ${hash.slice(0, 12)}`);
