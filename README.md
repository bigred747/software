# LuxeTrendsetters website software

Admin plugins for [luxetrendsetters.com](https://luxetrendsetters.com/). They do not auto-publish, rewrite stored posts, or run content cron.

## Fix the WordPress Dashboard

Download the pack, unzip it, then upload the two plugin zips in **Plugins → Add Plugin → Upload Plugin**. Do not upload the pack zip itself.

### Pack (one download)

File: `dist/Luxe-Dashboard-Fix-v1.0.0.zip`

SHA-256: `52f4cb69307c148f8c80649cd2983ca63934b1f618e5d667fd81c657431adb98`

Direct download: https://github.com/bigred747/software/raw/cursor/operator-dashboard-9b7a/dist/Luxe-Dashboard-Fix-v1.0.0.zip

### 1. Luxe Operator Dashboard 1.0.0

File: `dist/Luxe-Operator-Dashboard-v1.0.0.zip`

SHA-256: `956d0f1a31f8b756c73dadfb53425aad62adcfd3b9d79b4f8485a2e97bfb3ad4`

Direct download: https://github.com/bigred747/software/raw/cursor/operator-dashboard-9b7a/dist/Luxe-Operator-Dashboard-v1.0.0.zip

Hides YITH news, Rank Math blog marketing, WordPress news, empty reviews, and repeating plugin nags **on the Dashboard screen only**. Adds one operator card that translates the numbers you pasted.

Keep **Luxe Hard Rescue Admin Cleaner** active.

### 2. Stay Repair 1.9.3

File: `dist/Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.3.zip`

SHA-256: `b418b24f32517694cb77971fbc6605c80ccf7a8ff01a970c1d723bdc1f1138c4`

Direct download: https://github.com/bigred747/software/raw/cursor/operator-dashboard-9b7a/dist/Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.3.zip

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
