import { readdir, readFile } from "node:fs/promises";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { PGlite } from "@electric-sql/pglite";
import pg from "pg";

const databaseUrl = process.env.DATABASE_URL?.trim() ?? "";
let pool;
let local;

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

export async function sql(text, params = []) {
  if (databaseUrl !== "") {
    pool ??= new pg.Pool({ connectionString: databaseUrl, max: 5 });
    return (await pool.query(text, params)).rows;
  }
  local ??= startLocal().catch((error) => {
    local = undefined;
    throw error;
  });
  return (await (await local).query(text, params)).rows;
}

async function startLocal() {
  const db = new PGlite();
  const files = (await readdir(join(root, "migrations")).catch(() => []))
    .filter((name) => name.endsWith(".sql"))
    .sort();
  for (const file of files) {
    await db.exec(await readFile(join(root, "migrations", file), "utf8"));
  }
  return db;
}
