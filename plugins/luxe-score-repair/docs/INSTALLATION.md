# Luxe Score Repair 1.0.0

Safe public-output plugin for https://luxetrendsetters.com/

## Install

1. Download `dist/Luxe-Score-Repair-v1.0.0.zip`.
2. WordPress → Plugins → Add Plugin → Upload Plugin → Activate.
3. LiteSpeed Cache → **Purge All**.
4. Keep **Guest Mode OFF** and **Crawler OFF**.
5. Open **Tools → Luxe Score Repair**.

Do **not** upload this zip over Mission Control or Stay Repair. This is a new plugin folder: `luxe-score-repair`.

## Safety

- Never writes `post_content`
- Never changes post status
- Never rewrites Amazon or product permalinks
- Never creates Rank Math redirect rows
- No content cron

## What it repairs

| Live error | Repair |
|---|---|
| Title `HOME: Features, Use Cases and Buyer Fit` | Brand title + description |
| Homepage OG image is an Amazon HP laptop | Site icon / logo |
| Truncated FAQ JSON-LD | Quarantine + valid FAQPage |
| `robots.txt` 110KB of duplicate Autopilot comments | Short valid file + `sitemap_index.xml` |
| Test blog / client portal / cart indexable | `noindex, follow` |
| `/blog/` 404 | 301 to `/blogs/` |
| `[toc]` and “expanded automatically” filler | Stripped on output only |
| Concatenated `Rolex … – canon eos r5` titles | Display-only cleanup |
| `Cache-Control: no-store` on public HTML | Public cache 10 minutes |
| Missing image alts | Filled, never overwritten |

## After purge, confirm

- View-source homepage `<title>` is `LuxeTrendsetters | Luxury Tech, Watches & Drones`
- `https://luxetrendsetters.com/robots.txt` is under 1 KB
- `https://luxetrendsetters.com/blog/` 301s to `/blogs/`
- Homepage FAQ JSON-LD parses
