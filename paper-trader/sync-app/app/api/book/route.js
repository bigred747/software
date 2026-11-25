import { requireViewer } from "../../../lib/auth.js";
import { jsonResponse, securityHeaders } from "../../../lib/security.js";
import { sql } from "../../../lib/db.js";
import { openBook, sealBook } from "../../../lib/vault.js";

const maxBytes = 750_000;

export async function OPTIONS(request) {
  return new Response(null, { status: 204, headers: securityHeaders(request) });
}

export async function GET(request) {
  try {
    const url = new URL(request.url);
    const viewer = await requireViewer(request, {
      method: "GET",
      path: url.pathname,
      bodyText: "",
    });
    const rows = await sql(
      "select payload, markets, updated_at from paper_books_v2 where owner_sub = $1",
      [viewer.ownerSub],
    );
    if (!rows.length) {
      return jsonResponse(request, 404, {
        ok: false,
        error: "No book saved yet. Push from the other device first.",
      });
    }
    const row = rows[0];
    const book = openBook(viewer.ownerSub, row.payload);
    return jsonResponse(request, 200, {
      ok: true,
      markets: row.markets,
      updated_at: row.updated_at,
      book,
      vault: "sealed",
    });
  } catch (error) {
    return jsonResponse(request, error.status || 500, {
      ok: false,
      error: error.message || "Sync failed.",
    });
  }
}

export async function PUT(request) {
  try {
    const url = new URL(request.url);
    const text = await request.text();
    if (text.length > maxBytes) {
      return jsonResponse(request, 413, { ok: false, error: "Paper book is too large to sync." });
    }
    const viewer = await requireViewer(request, {
      method: "PUT",
      path: url.pathname,
      bodyText: text,
    });
    const body = text ? JSON.parse(text) : {};
    const book = body.book || body;
    const sessions = book && typeof book === "object" ? book.sessions : null;
    if (!sessions || typeof sessions !== "object" || !Object.keys(sessions).length) {
      return jsonResponse(request, 400, {
        ok: false,
        error: "Nothing to sync. Open a paper trade first.",
      });
    }
    if (Object.keys(sessions).length > 200) {
      return jsonResponse(request, 400, { ok: false, error: "Too many markets in one book." });
    }
    const markets = Number(book.markets) || Object.keys(sessions).length;
    const payload = {
      version: 1,
      sessions,
      markets,
      note: "Synced paper book. Nothing goes to a broker.",
    };
    const sealed = sealBook(viewer.ownerSub, payload);
    await sql(
      `insert into paper_books_v2 (owner_sub, payload, markets, updated_at)
       values ($1, $2::jsonb, $3, now())
       on conflict (owner_sub) do update
       set payload = excluded.payload, markets = excluded.markets, updated_at = now()`,
      [viewer.ownerSub, JSON.stringify(sealed), markets],
    );
    return jsonResponse(request, 200, { ok: true, markets, vault: "sealed" });
  } catch (error) {
    return jsonResponse(request, error.status || (error instanceof SyntaxError ? 400 : 500), {
      ok: false,
      error: error.message || "Sync failed.",
    });
  }
}
