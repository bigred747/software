const ALLOWED = new Set([
  "https://paper-trader.apps.grok.bot",
  "http://127.0.0.1:8765",
  "http://localhost:8765",
  "http://127.0.0.1:4311",
  "http://localhost:4311",
]);

export function corsHeaders(request) {
  const origin = request.headers.get("origin") || "";
  const headers = {
    "cache-control": "private, no-store",
    vary: "Origin",
  };
  if (origin && ALLOWED.has(origin)) {
    headers["access-control-allow-origin"] = origin;
    headers["access-control-allow-methods"] = "GET, PUT, POST, DELETE, OPTIONS";
    headers["access-control-allow-headers"] = "content-type, authorization";
  }
  return headers;
}

export function jsonResponse(request, status, body) {
  return Response.json(body, {
    status,
    headers: corsHeaders(request),
  });
}
