import { createServer } from "node:http";
import { readFile } from "node:fs/promises";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { sql } from "./lib/db.js";

const root = dirname(fileURLToPath(import.meta.url));
const port = Number(process.env.PORT || 4310);
const maxBytes = 1_500_000;

function cors(res) {
  res.setHeader("access-control-allow-origin", "*");
  res.setHeader("access-control-allow-methods", "GET, PUT, OPTIONS");
  res.setHeader("access-control-allow-headers", "content-type, x-desk-code");
  res.setHeader("cache-control", "private, no-store");
}

function cleanCode(raw) {
  return String(raw || "")
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9-]/g, "")
    .slice(0, 32);
}

function json(res, status, body) {
  cors(res);
  const text = JSON.stringify(body);
  res.writeHead(status, { "content-type": "application/json; charset=utf-8" });
  res.end(text);
}

async function readBody(req) {
  const chunks = [];
  let size = 0;
  for await (const chunk of req) {
    size += chunk.length;
    if (size > maxBytes) {
      const error = new Error("Paper book is too large to sync.");
      error.status = 413;
      throw error;
    }
    chunks.push(chunk);
  }
  if (!chunks.length) return {};
  return JSON.parse(Buffer.concat(chunks).toString("utf8"));
}

async function handleBook(req, res, url) {
  const code = cleanCode(req.headers["x-desk-code"] || url.searchParams.get("code"));
  if (code.length < 6) {
    return json(res, 400, { ok: false, error: "Enter the same desk sync code on phone and computer (6+ characters)." });
  }
  if (req.method === "GET") {
    const rows = await sql(
      "select payload, markets, updated_at from paper_books where desk_code = $1",
      [code],
    );
    if (!rows.length) {
      return json(res, 404, { ok: false, error: "No book for that sync code yet. Push from the other device first." });
    }
    const row = rows[0];
    return json(res, 200, {
      ok: true,
      markets: row.markets,
      updated_at: row.updated_at,
      book: row.payload,
    });
  }
  if (req.method === "PUT") {
    const body = await readBody(req);
    const book = body.book || body;
    const sessions = book && typeof book === "object" ? book.sessions : null;
    if (!sessions || typeof sessions !== "object" || !Object.keys(sessions).length) {
      return json(res, 400, { ok: false, error: "Nothing to sync. Open a paper trade first." });
    }
    const markets = Number(book.markets) || Object.keys(sessions).length;
    const payload = {
      version: 1,
      sessions,
      markets,
      note: book.note || "Synced paper book. Nothing goes to a broker.",
    };
    await sql(
      `insert into paper_books (desk_code, payload, markets, updated_at)
       values ($1, $2::jsonb, $3, now())
       on conflict (desk_code) do update
       set payload = excluded.payload, markets = excluded.markets, updated_at = now()`,
      [code, JSON.stringify(payload), markets],
    );
    return json(res, 200, { ok: true, markets, desk_code: code });
  }
  return json(res, 405, { ok: false, error: "Use GET or PUT." });
}

const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url || "/", `http://${req.headers.host || "127.0.0.1"}`);
    if (req.method === "OPTIONS") {
      cors(res);
      res.writeHead(204);
      return res.end();
    }
    if (url.pathname === "/api/book") {
      return await handleBook(req, res, url);
    }
    if (url.pathname === "/api/health") {
      return json(res, 200, { ok: true, service: "paper-desk-sync" });
    }
    if (url.pathname === "/" || url.pathname === "/index.html") {
      const html = await readFile(join(root, "public", "index.html"), "utf8");
      cors(res);
      res.writeHead(200, { "content-type": "text/html; charset=utf-8" });
      return res.end(html);
    }
    json(res, 404, { ok: false, error: "Not found" });
  } catch (error) {
    const status = error.status || (error instanceof SyntaxError ? 400 : 500);
    json(res, status, { ok: false, error: error.message || "Sync failed." });
  }
});

server.listen(port, "0.0.0.0", () => {
  console.log(`paper-desk-sync on http://127.0.0.1:${port}`);
});
