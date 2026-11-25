import { randomBytes, timingSafeEqual } from "node:crypto";
import { getIdentity } from "../cursor/identity.js";
import { sql } from "./db.js";
import { fingerprint, signRequest, tokenPepperHash } from "./vault.js";

const hits = new Map();
const nonces = new Map();
const MAX_SKEW_MS = 90_000;
const TOKEN_TTL_DAYS = 90;

export function newDeviceToken() {
  return randomBytes(32).toString("hex");
}

export function hashToken(token) {
  return tokenPepperHash(token);
}

export function rateLimit(bucket, limit = 30, windowMs = 60 * 60 * 1000) {
  const now = Date.now();
  const row = hits.get(bucket) || { count: 0, reset: now + windowMs };
  if (now > row.reset) {
    row.count = 0;
    row.reset = now + windowMs;
  }
  row.count += 1;
  hits.set(bucket, row);
  if (row.count > limit) {
    const error = new Error("Too many sync requests. Try again later.");
    error.status = 429;
    throw error;
  }
}

function header(request, name) {
  if (request.headers?.get) return request.headers.get(name) || "";
  const key = Object.keys(request.headers || {}).find((k) => k.toLowerCase() === name.toLowerCase());
  return key ? String(request.headers[key] || "") : "";
}

function bearerToken(request) {
  const match = String(header(request, "authorization")).match(/^Bearer\s+(\S+)/i);
  return match ? match[1].trim() : "";
}

function safeEqualHex(a, b) {
  const left = Buffer.from(String(a), "utf8");
  const right = Buffer.from(String(b), "utf8");
  if (left.length !== right.length) return false;
  return timingSafeEqual(left, right);
}

function rememberNonce(ownerSub, nonce) {
  if (!nonce || nonce.length < 16 || nonce.length > 128) {
    const error = new Error("Missing or invalid request nonce.");
    error.status = 401;
    throw error;
  }
  const key = `${ownerSub}:${nonce}`;
  const now = Date.now();
  for (const [k, exp] of nonces) {
    if (exp < now) nonces.delete(k);
  }
  if (nonces.has(key)) {
    const error = new Error("Replay blocked.");
    error.status = 401;
    throw error;
  }
  nonces.set(key, now + MAX_SKEW_MS * 2);
}

async function audit(ownerSub, action, ok, detail = "") {
  try {
    await sql(
      "insert into sync_audit (owner_sub, action, ok, detail) values ($1, $2, $3, $4)",
      [ownerSub || null, action, ok, String(detail).slice(0, 200)],
    );
  } catch (_error) {
    /* audit must never block the desk */
  }
}

export async function requireViewer(request, { method, path, bodyText = "" } = {}) {
  // Bearer computer links always take the signed-device path (never silent identity bypass).
  const token = bearerToken(request);
  if (!token) {
    const identity = await getIdentity(request);
    if (identity.verified && identity.kind !== "key") {
      rateLimit(`id:${identity.sub}`, 40);
      await audit(identity.sub, "auth.identity", true, fingerprint(identity.sub));
      return { ownerSub: identity.sub, via: "identity", identity, rawToken: "" };
    }
    await audit(null, "auth.deny", false, "missing_token");
    const error = new Error("Sign in to Cursor, or use a computer link token from this app.");
    error.status = 401;
    throw error;
  }
  if (!/^[a-f0-9]{64}$/i.test(token)) {
    await audit(null, "auth.deny", false, "bad_token_shape");
    const error = new Error("Computer link token format is invalid.");
    error.status = 401;
    throw error;
  }

  const timestamp = Number(header(request, "x-desk-timestamp"));
  const signature = header(request, "x-desk-signature").toLowerCase();
  const nonce = header(request, "x-desk-nonce");
  const now = Date.now();
  if (!Number.isFinite(timestamp) || Math.abs(now - timestamp) > MAX_SKEW_MS) {
    await audit(null, "auth.deny", false, "bad_timestamp");
    const error = new Error("Request clock skew too large. Sync again.");
    error.status = 401;
    throw error;
  }
  if (!signature || signature.length !== 64) {
    await audit(null, "auth.deny", false, "bad_signature");
    const error = new Error("Missing request signature.");
    error.status = 401;
    throw error;
  }
  const expected = signRequest(token, method || "GET", path || "/api/book", String(timestamp), bodyText);
  if (!safeEqualHex(expected, signature)) {
    await audit(null, "auth.deny", false, "sig_mismatch");
    const error = new Error("Request signature invalid.");
    error.status = 401;
    throw error;
  }

  const tokenHash = hashToken(token);
  const rows = await sql(
    `select owner_sub, expires_at, revoked_at
     from device_tokens
     where token_hash = $1`,
    [tokenHash],
  );
  if (!rows.length) {
    await audit(null, "auth.deny", false, "unknown_token");
    const error = new Error("Computer link token is invalid or revoked.");
    error.status = 401;
    throw error;
  }
  const row = rows[0];
  if (row.revoked_at) {
    await audit(row.owner_sub, "auth.deny", false, "revoked");
    const error = new Error("Computer link token was revoked.");
    error.status = 401;
    throw error;
  }
  if (row.expires_at && new Date(row.expires_at).getTime() < now) {
    await audit(row.owner_sub, "auth.deny", false, "expired");
    const error = new Error("Computer link token expired. Create a new one.");
    error.status = 401;
    throw error;
  }

  rememberNonce(row.owner_sub, nonce);
  rateLimit(`tok:${row.owner_sub}`, 30);
  await sql("update device_tokens set last_used_at = now() where token_hash = $1", [tokenHash]);
  await audit(row.owner_sub, "auth.device", true, fingerprint(tokenHash));
  return { ownerSub: row.owner_sub, via: "device", rawToken: token };
}

export function tokenExpiryIso() {
  return new Date(Date.now() + TOKEN_TTL_DAYS * 24 * 60 * 60 * 1000).toISOString();
}

export { audit };
