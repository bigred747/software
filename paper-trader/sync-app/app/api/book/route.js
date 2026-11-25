import { requireViewer } from "../../../lib/auth.js";
import { corsHeaders, jsonResponse } from "../../../lib/cors.js";
import { sql } from "../../../lib/db.js";

const maxBytes = 1_500_000;

export async function OPTIONS(request) {
  return new Response(null, { status: 204, headers: corsHeaders(request) });
}

export async function GET(request) {
  try {
    const viewer = await requireViewer(request);
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
    return jsonResponse(request, 200, {
      ok: true,
      markets: row.markets,
      updated_at: row.updated_at,
      book: row.payload,
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
    const viewer = await requireViewer(request);
    const text = await request.text();
    if (text.length > maxBytes) {
      return jsonResponse(request, 413, { ok: false, error: "Paper book is too large to sync." });
    }
    const body = text ? JSON.parse(text) : {};
    const book = body.book || body;
    const sessions = book && typeof book === "object" ? book.sessions : null;
    if (!sessions || typeof sessions !== "object" || !Object.keys(sessions).length) {
      return jsonResponse(request, 400, {
        ok: false,
        error: "Nothing to sync. Open a paper trade first.",
      });
    }
    const markets = Number(book.markets) || Object.keys(sessions).length;
    const payload = {
      version: 1,
      sessions,
      markets,
      note: book.note || "Synced paper book. Nothing goes to a broker.",
    };
    await sql(
      `insert into paper_books_v2 (owner_sub, payload, markets, updated_at)
       values ($1, $2::jsonb, $3, now())
       on conflict (owner_sub) do update
       set payload = excluded.payload, markets = excluded.markets, updated_at = now()`,
      [viewer.ownerSub, JSON.stringify(payload), markets],
    );
    return jsonResponse(request, 200, { ok: true, markets });
  } catch (error) {
    return jsonResponse(request, error.status || (error instanceof SyntaxError ? 400 : 500), {
      ok: false,
      error: error.message || "Sync failed.",
    });
  }
}
