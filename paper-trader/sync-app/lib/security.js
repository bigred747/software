const ALLOWED = new Set([
  "https://paper-trader.apps.grok.bot",
  "http://127.0.0.1:8765",
  "http://localhost:8765",
  "http://127.0.0.1:4311",
  "http://localhost:4311",
]);

export function securityHeaders(request) {
  const origin = request.headers.get("origin") || "";
  const headers = {
    "cache-control": "private, no-store, no-cache, must-revalidate",
    "x-content-type-options": "nosniff",
    "x-frame-options": "DENY",
    "referrer-policy": "no-referrer",
    "permissions-policy": "geolocation=(), microphone=(), camera=()",
    "content-security-policy":
      "default-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline'",
    vary: "Origin",
  };
  if (process.env.CURSOR_APP_ID || process.env.VERCEL) {
    headers["strict-transport-security"] = "max-age=31536000; includeSubDomains; preload";
  }
  if (origin && ALLOWED.has(origin)) {
    headers["access-control-allow-origin"] = origin;
    headers["access-control-allow-methods"] = "GET, PUT, POST, DELETE, OPTIONS";
    headers["access-control-allow-headers"] =
      "content-type, authorization, x-desk-timestamp, x-desk-signature, x-desk-nonce";
    headers["access-control-max-age"] = "600";
  }
  return headers;
}

export function jsonResponse(request, status, body) {
  return Response.json(body, {
    status,
    headers: securityHeaders(request),
  });
}
