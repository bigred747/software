import { getIdentity } from "../../../cursor/identity.js";
import { hashToken, newDeviceToken, rateLimit } from "../../../lib/auth.js";
import { corsHeaders, jsonResponse } from "../../../lib/cors.js";
import { sql } from "../../../lib/db.js";

export async function OPTIONS(request) {
  return new Response(null, { status: 204, headers: corsHeaders(request) });
}

export async function POST(request) {
  try {
    const identity = await getIdentity(request);
    if (!identity.verified) {
      return jsonResponse(request, 401, {
        ok: false,
        error: "Sign in to Cursor to create a computer link.",
      });
    }
    rateLimit(identity.sub, 10, 60 * 60 * 1000);
    const existing = await sql(
      "select count(*)::int as n from device_tokens where owner_sub = $1",
      [identity.sub],
    );
    if ((existing[0]?.n || 0) >= 3) {
      return jsonResponse(request, 400, {
        ok: false,
        error: "You already have 3 computer links. Revoke one first.",
      });
    }
    const token = newDeviceToken();
    await sql(
      `insert into device_tokens (token_hash, owner_sub, label, created_at)
       values ($1, $2, $3, now())`,
      [hashToken(token), identity.sub, "computer"],
    );
    return jsonResponse(request, 200, {
      ok: true,
      token,
      note: "Copy this once into Documents\\Paper Trader\\.env as PAPER_SYNC_TOKEN=... Keep it private.",
    });
  } catch (error) {
    return jsonResponse(request, error.status || 500, {
      ok: false,
      error: error.message || "Could not create computer link.",
    });
  }
}

export async function DELETE(request) {
  try {
    const identity = await getIdentity(request);
    if (!identity.verified) {
      return jsonResponse(request, 401, {
        ok: false,
        error: "Sign in to Cursor to revoke computer links.",
      });
    }
    rateLimit(identity.sub, 10, 60 * 60 * 1000);
    await sql("delete from device_tokens where owner_sub = $1", [identity.sub]);
    return jsonResponse(request, 200, { ok: true, revoked: true });
  } catch (error) {
    return jsonResponse(request, error.status || 500, {
      ok: false,
      error: error.message || "Could not revoke computer links.",
    });
  }
}
