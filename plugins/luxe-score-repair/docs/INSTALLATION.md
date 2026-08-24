# Luxe Score Repair 1.2.0

Safe public-output plugin for https://luxetrendsetters.com/. Search focus points Google at products and published buyer guides instead of thin product-tag pages.

This cannot invent Search Console clicks. Direct 1-second Analytics sessions are not search traffic.

## Install

1. Download [luxe-score-repair.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/score-repair-search-focus-e8f3/luxe-score-repair.zip).
2. Plugins → Add Plugin → Upload Plugin. Upload **over** 1.1.0 (same folder `luxe-score-repair`).
3. Open **Tools → Luxe Score Repair**.
4. Confirm **Search focus: products and buyer guides** is on.
5. Click **Run process learning now**.

Do **not** upload this zip over Mission Control, Stay Repair, or Product Scout.

## What 1.2.0 does

- Noindex product tags, WooCommerce attribute archives, and filtered shop URLs (`noindex, follow`)
- Keep homepage, products, published posts, product categories, and the shop page indexable
- Drop thin tags and utility pages (wishlist, cart, client portal) from sitemaps
- Serve `/luxe-search-sitemap.xml` with homepage, `/blogs/`, published guides, and product categories
- Add that sitemap to `robots.txt` (does **not** Disallow `/product-tag/` so Google can recrawl noindex)
- Homepage JSON-LD ItemList of published buyer guides
- Exact 301 `/date-first-available` → `/blogs/`
- Still heals `robots.txt`, 301s `/blog/`, purges LiteSpeed, and rechecks every 15 minutes

## Safety

- Never writes `post_content`
- Never changes post status
- Never rewrites Amazon or product permalinks
- Never creates Rank Math redirect rows
- Never fakes Analytics or Search Console traffic
