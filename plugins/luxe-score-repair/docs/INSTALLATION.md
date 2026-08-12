# Luxe Score Repair 1.2.0

Safe 24/7 public-output plugin for https://luxetrendsetters.com/.

## Install

1. Download `dist/Luxe-Score-Repair-v1.2.0.zip`.
2. Plugins → Add Plugin → Upload Plugin. Upload **over** 1.1.0 (same folder `luxe-score-repair`).
3. Open **Tools → Luxe Score Repair**.
4. Click **Run 24/7 learning now**, then load the public homepage once (logged out) so the visitor snapshot can verify live HTML.

Do **not** upload this zip over Mission Control or Stay Repair.

## What 1.2.0 does automatically

- 24/7: heals bloated `robots.txt` on public hits
- 5-minute heal cron + 15-minute verify (and wakes itself if Hostinger cron sleeps)
- Strips wishlist / portal / test URLs from Rank Math sitemaps
- 301s `/blog/` on `init` before Rank Math
- Purges LiteSpeed after file heals
- Stores a homepage HTML snapshot so scores do not go green on skipped loopback
- Shows technical SEO 95–100 and public-output 9.5–10 when every process verifies

## Safety

- Never writes `post_content`
- Never changes post status
- Never rewrites Amazon or product permalinks
- Never creates Rank Math redirect rows

Technical SEO 95–100 is public-output (titles, robots, schema, headers, noindex, sitemaps). This plugin cannot write buyer guides, so it does not claim Google’s editorial quality score.
