# Luxe SEO Score Repair 1.4.2

This is the **SEO plugin** for https://luxetrendsetters.com/. WordPress admin menu: **Luxe SEO**. Amazon AI lives here, not in Luxe Theme.

## Install

1. Download `luxe-score-repair.zip` (also named `Luxe-SEO-Score-Repair.zip`).
2. Plugins → Add Plugin → Upload Plugin. Upload **over** 1.4.1 (same folder `luxe-score-repair`).
3. Confirm Plugins shows **Luxe SEO Score Repair 1.4.2**.
4. Open the left menu **Luxe SEO**.
5. Leave **Amazon AI** On.
6. Click **Run process learning now**. Do not expect Save repairs alone to refresh the board.

Do **not** upload this zip over Mission Control, Stay Repair, Product Scout, or Luxe Theme Guard.

## What 1.4.0 does automatically

- Honest loopback: live HTML rows stay yellow when Hostinger blocks the plugin from fetching itself
- Official Amazon Associate identification on every public page
- Amazon AI: one catalog item every 5 minutes (ASIN + `tag=luxetrendse0f-20` audit)
- Optional official Amazon Creators API (PA-API v5 is retired)
- Writes a short `robots.txt` to disk (Hostinger serves the physical file)
- 301s `/blog/` and `/wp-sitemap.xml` on `init` before Rank Math
- 410 Gone for removed Link Guardian plugin files and `/meta.json`
- Read-only duplicate title list (you edit Rank Math by hand)
- Purges LiteSpeed after heals
- Rechecks SEO every 15 minutes
- Blocks copied Instagram video/CDN media
- Refuses thin 37-word ranking pages in favor of unique buyer-guide copy

## Safety

- Never writes `post_content`
- Never changes post status
- Never rewrites Amazon or product permalinks
- Never creates Rank Math redirect rows
- Never auto-hops `/product-category/` or `/product-tag/` URLs
- Never copies Instagram video, audio, frames, or captions
- Never invents Amazon ratings
