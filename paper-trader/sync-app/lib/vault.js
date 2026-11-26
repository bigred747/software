import { createHash, createHmac, randomBytes, createCipheriv, createDecipheriv } from "node:crypto";

function vaultSecret() {
  const key = process.env.SYNC_VAULT_KEY?.trim() || "";
  if (key.length >= 32) return key;
  if (process.env.CURSOR_APP_ID || process.env.VERCEL) {
    const error = new Error("Vault locked: SYNC_VAULT_KEY is not set.");
    error.status = 503;
    throw error;
  }
  // Local sample only — never used when the app is published.
  return "local-dev-sample-vault-key-not-for-production-use";
}

function keyBytes(ownerSub) {
  return createHmac("sha256", vaultSecret()).update(`paper-desk-vault:v1:${ownerSub}`).digest();
}

export function tokenPepperHash(token) {
  return createHmac("sha256", vaultSecret()).update(`desk-token:v1:${token}`).digest("hex");
}

export function sealBook(ownerSub, book) {
  const iv = randomBytes(12);
  const key = keyBytes(ownerSub);
  const cipher = createCipheriv("aes-256-gcm", key, iv);
  const plain = Buffer.from(JSON.stringify(book), "utf8");
  const ct = Buffer.concat([cipher.update(plain), cipher.final()]);
  const tag = cipher.getAuthTag();
  return {
    v: 1,
    alg: "aes-256-gcm",
    iv: iv.toString("base64"),
    tag: tag.toString("base64"),
    ct: ct.toString("base64"),
  };
}

export function openBook(ownerSub, sealed) {
  if (!sealed || typeof sealed !== "object") {
    throw Object.assign(new Error("Vault payload missing."), { status: 500 });
  }
  if (sealed.v === 1 && sealed.ct) {
    const key = keyBytes(ownerSub);
    const iv = Buffer.from(String(sealed.iv), "base64");
    const tag = Buffer.from(String(sealed.tag), "base64");
    const ct = Buffer.from(String(sealed.ct), "base64");
    const decipher = createDecipheriv("aes-256-gcm", key, iv);
    decipher.setAuthTag(tag);
    const plain = Buffer.concat([decipher.update(ct), decipher.final()]);
    return JSON.parse(plain.toString("utf8"));
  }
  // Legacy plaintext rows (pre-vault) — read once, then re-seal on next write.
  if (sealed.sessions) return sealed;
  throw Object.assign(new Error("Vault payload unreadable."), { status: 500 });
}

export function signRequest(token, method, path, timestamp, bodyText) {
  return createHmac("sha256", token)
    .update(`${method.toUpperCase()}\n${path}\n${timestamp}\n${bodyText || ""}`)
    .digest("hex");
}

export function fingerprint(value) {
  return createHash("sha256").update(String(value)).digest("hex").slice(0, 16);
}
