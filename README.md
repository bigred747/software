# software

WordPress plugins for [luxetrendsetters.com](https://luxetrendsetters.com/).

## 1. Luxe SEO Score Repair 1.3.0 — THIS IS THE SEO PLUGIN + Amazon AI

Visible in WordPress as **Luxe SEO** (left admin menu). Plugin list name: **Luxe SEO Score Repair**. Amazon AI lives here, not in Luxe Theme.

Windows 11 / WordPress zip. Size **145KB**.

**Upload this file from Windows 11 into WordPress:**

[luxe-score-repair.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/seo-amazon-ai-0935/luxe-score-repair.zip)

SHA-256 `a862ee4a6d8b986e7d05a7c00be000e432d877666d5772edc8c9d5e31775af9d`

Also named [Luxe-SEO-Score-Repair.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/seo-amazon-ai-0935/Luxe-SEO-Score-Repair.zip)

Plugins → Add Plugin → Upload Plugin → Install Now → Replace current with uploaded → Activate.

Then open the left menu **Luxe SEO**. Leave **Amazon AI** On. Click **Run process learning now**.

What it does:

- Amazon AI: one catalog item every 5 minutes (ASIN + tag=luxetrendse0f-20). Never rewrites URLs.
- Homepage title, description, Open Graph, JSON-LD, robots.txt, noindex junk URLs
- Unique buyer-guide copy (thin 37-word ranking pages are refused)
- Copyright lock (no copied Instagram video)
- 15-minute SEO process learning and LiteSpeed purge
- Never writes post content, never rewrites Amazon URLs

## 2. Luxe Affiliate Product Scout 5.7.0 — catalog + Instagram scan

24/7 catalog learning. Copyright-safe Instagram video scanning. One product per 5-minute cycle, hourly read-only snapshot, honest 95–100 scores. Instagram video is **never** downloaded or republished. Windows 11 / WordPress PclZip package. Size **145KB**.

**Upload this file from Windows 11 into WordPress:**

[luxe-affiliate-product-scout.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/instagram-video-scan-0935/luxe-affiliate-product-scout.zip)

SHA-256 `8b58cdc12095a5506cbd1e10eed727cfef88368ef8d2b4590007c4664e181e32`

If the last upload caused a critical error: in WordPress delete Product Scout (and any leftover `luxe-affiliate-product-scout*` folders in `wp-content/plugins/`), then upload this zip. Do not unzip it on the PC.

Plugins → Add Plugin → Upload Plugin → Install Now → Replace current with uploaded → Activate.

### After you activate 5.7.0

1. Confirm **Plugins** shows **Version 5.7.0**.
2. Open **Luxe Product Scout**.
3. Confirm **24/7 learning is ON**.
4. Click **Repair ALL Catalog Data + Recalculate** once so the **full catalog** scoreboard is stored (233 products, not 25).
5. Click **Run Full Catalog Audit** or **Run learning snapshot**. The board must show the full catalog, not a 25-product batch.
6. Paste public Instagram reel URLs. Click **Scan all Instagram videos**.
7. Leave the site running. WP-Cron learns **one product every 5 minutes** and scans one queued Instagram permalink without copying video.

### 24/7 learning

| Cycle | What it does |
|---|---|
| Every 5 minutes | Repair + rescore **one** product. Log it. Never publishes. Scan one Instagram permalink if queued. Never downloads video. |
| Every hour | Read-only snapshot of stored scores for the **full catalog**. Updates the board averages. Does not import. |
| Repair next batch | Still 25 products for a manual catch-up. **Does not overwrite** the 233-product board. |
| Repair ALL / Full Audit | Only these (and the hourly snapshot) write the full catalog scoreboard. |
| Scan all Instagram videos | Records operator-pasted permalinks and Instagram URLs already on the site. Matches the local catalog. Copies nothing. |

WP-Cron is kept alive on `init` / `shutdown` **without running repair on public page views**. If Hostinger has a real cron job, point it at `wp-cron.php` every 5 minutes.

### What scores 95–100

A product is 95–100 when it has **supported brand + ASIN + price + primary image** and is **not HOLD**. Missing ratings, seller, or warranty do **not** invent evidence and do **not** drop a valid product below 95.

- **Integrity 100** when a model signal is present.
- **Integrity 95** when the rest of the gate is met.
- Taxonomy leftovers are repaired. They **do not cap** a valid product at 94.
- Unknown brand, renewed, used, refurbished stay **HOLD** and stay below 95.

### Copyright lock

Product Scout and Luxe SEO never copy Instagram video, audio, frames, captions, or third-party prompt packs. Original LuxeTrendsetters reel-studio copy is included for you to film yourself.
