# Luxe SEO Score Repair 1.3.0

This is the **SEO plugin** for https://luxetrendsetters.com/. WordPress admin menu: **Luxe SEO**. Amazon AI lives here, not in Luxe Theme.

## Install

1. Download `luxe-score-repair.zip` (also named `Luxe-SEO-Score-Repair.zip`).
2. Plugins → Add Plugin → Upload Plugin. Upload **over** 1.2.0 (same folder `luxe-score-repair`).
3. Confirm Plugins shows **Luxe SEO Score Repair 1.3.0**.
4. Open the left menu **Luxe SEO**.
5. Leave **Amazon AI** On. Optional: paste Creators API credentials.
6. Click **Run process learning now** and **Run 1 Amazon AI cycle**.

Do **not** upload this zip over Mission Control, Stay Repair, Product Scout, or Luxe Theme Guard.

## What 1.3.0 does automatically

- Amazon AI: one catalog item every 5 minutes (ASIN + `tag=luxetrendse0f-20` audit)
- Optional official Amazon Creators API (PA-API v5 is retired)
- Writes a short `robots.txt` to disk (Hostinger serves the physical file)
- 301s `/blog/` on `init` before Rank Math
- Purges LiteSpeed after heals
- Rechecks SEO every 15 minutes
- Shows GREEN / RED for each process
- Blocks copied Instagram video/CDN media
- Refuses thin 37-word ranking pages in favor of unique buyer-guide copy

## Safety

- Never writes `post_content`
- Never changes post status
- Never rewrites Amazon or product permalinks
- Never creates Rank Math redirect rows
- Never copies Instagram video, audio, frames, or captions
- Never invents Amazon ratings
