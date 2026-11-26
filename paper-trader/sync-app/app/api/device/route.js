import { getIdentity } from "../../../cursor/identity.js";
import { audit, hashToken, newDeviceToken, rateLimit, tokenExpiryIso } from "../../../lib/auth.js";
import { jsonResponse, securityHeaders } from "../../../lib/security.js";
import { sql } from "../../../lib/db.js";
import { fingerprint } from "../../../lib/vault.js";

export async function OPTIONS(request) {
  return new Response(null, { status: 204, headers: securityHeaders(request) });
}

export async function POST(request) {
  try {
    const identity = await getIdentity(request);
    if (!identity.verified || identity.kind === "key") {
      await audit(null, "device.create.deny", false, "no_person");
      return jsonResponse(request, 401, {
        ok: false,
        error: "Sign in to Cursor to create a computer link.",
      });
    }
    rateLimit(`device:${identity.sub}`, 5, 60 * 60 * 1000);
    const existing = await sql(
      "select count(*)::int as n from device_tokens where owner_sub = $1 and revoked_at is null",
      [identity.sub],
    );
    if ((existing[0]?.n || 0) >= 2) {
      return jsonResponse(request, 400, {
        ok: false,
        error: "You already have 2 active computer links. Revoke first.",
      });
    }
    const token = newDeviceToken();
    await sql(
      `insert into device_tokens (token_hash, owner_sub, label, created_at, expires_at)
       values ($1, $2, $3, now(), $4::timestamptz)`,
      [hashToken(token), identity.sub, "computer", tokenExpiryIso()],
    );
    await audit(identity.sub, "device.create", true, fingerprint(hashToken(token)));
    return jsonResponse(request, 200, {
      ok: true,
      token,
      expires_at: tokenExpiryIso(),
      note: "Copy once into Documents\\Paper Trader\\.env as PAPER_SYNC_TOKEN=... Keep it private. Expires in 90 days.",
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
    if (!identity.verified || identity.kind === "key") {
      return jsonResponse(request, 401, {
        ok: false,
        error: "Sign in to Cursor to revoke computer links.",
      });
    }
    rateLimit(`device:${identity.sub}`, 5, 60 * 60 * 1000);
    await sql(
      "update device_tokens set revoked_at = now() where owner_sub = $1 and revoked_at is null",
      [identity.sub],
    );
    await audit(identity.sub, "device.revoke", true, "");
    return jsonResponse(request, 200, { ok: true, revoked: true });
  } catch (error) {
    return jsonResponse(request, error.status || 500, {
      ok: false,
      error: error.message || "Could not revoke computer links.",
    });
  }
}
