import { createHash, randomBytes, timingSafeEqual } from "node:crypto";
import { getIdentity } from "../cursor/identity.js";
import { sql } from "./db.js";

const hits = new Map();

export function hashToken(token) {
  return createHash("sha256").update(String(token), "utf8").digest("hex");
}

export function newDeviceToken() {
  return randomBytes(32).toString("hex");
}

export function rateLimit(ownerSub, limit = 60, windowMs = 60 * 60 * 1000) {
  const now = Date.now();
  const row = hits.get(ownerSub) || { count: 0, reset: now + windowMs };
  if (now > row.reset) {
    row.count = 0;
    row.reset = now + windowMs;
  }
  row.count += 1;
  hits.set(ownerSub, row);
  if (row.count > limit) {
    const error = new Error("Too many sync requests. Try again later.");
    error.status = 429;
    throw error;
  }
}

function bearerToken(request) {
  const header =
    request.headers?.get?.("authorization") ||
    request.headers?.authorization ||
    request.headers?.Authorization ||
    "";
  const match = String(header).match(/^Bearer\s+(\S+)/i);
  return match ? match[1].trim() : "";
}

export async function requireViewer(request) {
  const identity = await getIdentity(request);
  if (identity.verified) {
    rateLimit(identity.sub);
    return { ownerSub: identity.sub, via: "identity", identity };
  }

  const token = bearerToken(request);
  if (!token || token.length < 48) {
    const error = new Error("Sign in to Cursor, or use a computer link token from this app.");
    error.status = 401;
    throw error;
  }

  const tokenHash = hashToken(token);
  const rows = await sql(
    "select owner_sub from device_tokens where token_hash = $1",
    [tokenHash],
  );
  if (!rows.length) {
    const error = new Error("Computer link token is invalid or revoked.");
    error.status = 401;
    throw error;
  }
  const ownerSub = rows[0].owner_sub;
  rateLimit(ownerSub);
  await sql("update device_tokens set last_used_at = now() where token_hash = $1", [tokenHash]);
  return { ownerSub, via: "device" };
}

export function tokensEqual(a, b) {
  const left = Buffer.from(String(a));
  const right = Buffer.from(String(b));
  if (left.length !== right.length) return false;
  return timingSafeEqual(left, right);
}
