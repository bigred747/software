import { jsonResponse } from "../../../lib/cors.js";

export async function GET(request) {
  return jsonResponse(request, 200, { ok: true, service: "paper-desk-sync" });
}
