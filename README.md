# LuxeTrendsetters website software

Admin plugins for [luxetrendsetters.com](https://luxetrendsetters.com/). They do not auto-publish, rewrite stored posts, or run content cron.

## Fix the WordPress Dashboard

The live Dashboard is noisy, not broken. Upload these two ZIPs in **Plugins → Add Plugin → Upload Plugin**.

### 1. Luxe Operator Dashboard 1.0.0

File: `dist/Luxe-Operator-Dashboard-v1.0.0.zip`

SHA-256: `375f7412c9977e523e65f1cdc67240ef1a8d630405368e2742a9578f1032495c`

Hides YITH news, Rank Math blog marketing, WordPress news, empty reviews, and repeating plugin nags **on the Dashboard screen only**. Adds one operator card that translates the numbers you pasted.

Keep **Luxe Hard Rescue Admin Cleaner** active.

### 2. Stay Repair 1.9.3

File: `dist/Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.3.zip`

SHA-256: `8a7822328e69a8e492af78fb6c9a9a5971152c88d95209d480b2408305a47cec`

Upload **over** Stay Repair 1.9.2. Do **not** replace Mission Control 1.8.1.

This version removes the truncated homepage FAQ JSON-LD that causes **Richard Brummer SEO: 1 verified red**. Valid Rank Math schema stays.

Then:

1. LiteSpeed Cache → Guest Mode OFF, Crawler OFF → Purge All
2. Dashboard → Stay Repair → Save → Run audit now
3. Open Mission Control → run the bounded full audit

## What the pasted Dashboard numbers mean

| What you see | Meaning |
| --- | --- |
| Luxe Hard Rescue / Reader-Love / Keyword Autopilot nags | Status banners. Hard Rescue stays. The other two are scanners; Operator Dashboard hides the nags. |
| Wordfence 9.0.0 passkeys | Optional. Enable on Login Security if you want them. |
| Richard Brummer SEO 1 red | Truncated FAQ JSON-LD on the homepage. Stay Repair 1.9.3 quarantines it. |
| Rank Math 0 impressions / 0 clicks | Rank Math is not connected to Search Console. Site Kit already has 36 impressions. |
| Site Kit 19K users, 1s avg | 28-day lag from LiteSpeed Guest Mode reloads. Trust Stay Repair dwell samples. |
| WooCommerce $0 | Amazon affiliate catalog. Expected. |
| 404 Monitor 75 / 184 | Bot probes and missing plugin assets. Stay Repair already answers `/meta.json`. |
| YITH Latest Updates / YITH Blog | Vendor marketing. Removed from Dashboard. |
| Site Health “No information yet” | WordPress has not run the check. Open Site Health once. |
| Failed login `richard` | Not an existing user. Do not whitelist blocked IPs blindly. |

## Safety

None of these plugins change post status, Amazon URLs, or Rank Math redirect rules unless you turn on an unsafe Stay Repair checkbox (off by default).
