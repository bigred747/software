# LuxeTrendsetters website software

Admin plugins for [luxetrendsetters.com](https://luxetrendsetters.com/). They do not auto-publish, rewrite stored posts, or run content cron.

## Fix the WordPress / Site Kit Dashboard

Download the pack, unzip it, then upload the two plugin zips in **Plugins → Add Plugin → Upload Plugin**. Do not upload the pack zip itself.

### Pack (one download)

File: `dist/Luxe-Dashboard-Fix-v1.1.0.zip`

SHA-256: `5473aae6c469c11af2051a0b1d9b7debd2a2a442acceb0d0aab875e84f0a5508`

Direct download: https://github.com/bigred747/software/raw/cursor/operator-dashboard-9b7a/dist/Luxe-Dashboard-Fix-v1.1.0.zip

### 1. Luxe Operator Dashboard 1.1.0

File: `dist/Luxe-Operator-Dashboard-v1.1.0.zip`

SHA-256: `fa5c0960a574d9c0c8649d67ca5dba96fc8b43977d3f9f14ab964e70f0b5d789`

Direct download: https://github.com/bigred747/software/raw/cursor/operator-dashboard-9b7a/dist/Luxe-Operator-Dashboard-v1.1.0.zip

Hides YITH news, Rank Math blog marketing, Site Kit Reader Revenue Manager / Ads / AdSense upsells, and repeating nags. Adds one operator card that translates the numbers you pasted.

Keep **Luxe Hard Rescue Admin Cleaner** active.

### 2. Stay Repair 1.9.4

File: `dist/Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.4.zip`

SHA-256: `8c568197e24e34c7899b88458e92fe19d7ae067cfc4446ad5dc366145ac19f86`

Direct download: https://github.com/bigred747/software/raw/cursor/operator-dashboard-9b7a/dist/Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.4.zip

Upload **over** Stay Repair 1.9.2 or 1.9.3. Do **not** replace Mission Control 1.8.1.

Then:

1. LiteSpeed Cache → Guest Mode OFF, Crawler OFF → Purge All
2. Stay Repair → paste Site Kit numbers → Save → Run audit now
3. Open Mission Control → run the bounded full audit

## What the pasted Site Kit numbers mean

| What you see | Meaning |
| --- | --- |
| 19K users, 99.8% Direct, 1s | Crawler / LiteSpeed Guest Mode reloads counted as Direct. Not Google growth. |
| 36 impressions, 0 clicks, 0% CTR | Real search picture. Keywords have 1 impression each. |
| Add to cart 0% / AdSense disconnected / no sales | Amazon affiliate catalog. Do not connect AdSense or Reader Revenue Manager. |
| Reader Revenue Manager / Ads nags | Site Kit upsells. Hidden by Operator Dashboard 1.1.0. |
| Tag pages at 1s | Archive lists plus Guest Mode reload. |
| Engagement 18% | Bot-heavy Direct sessions. |
| TBT 260ms | Lab JS. LCP 1.7s and CLS 0 are already Good. |
| Richard Brummer SEO 1 red | Truncated FAQ JSON-LD. Stay Repair quarantines it. |

## Safety

None of these plugins change post status, Amazon URLs, or Rank Math redirect rules unless you turn on an unsafe Stay Repair checkbox (off by default).
