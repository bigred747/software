import { jsonResponse } from "../../../lib/security.js";

export async function GET(request) {
  return jsonResponse(request, 200, {
    ok: true,
    service: "paper-desk-sync",
    hardening: "fort-knox",
    vault: Boolean(process.env.SYNC_VAULT_KEY?.trim()),
  });
}
