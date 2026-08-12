# Stay Repair 1.9.4

Install the ZIP through **Plugins → Add Plugin → Upload Plugin**.

Do **not** upload this over `richard-brummer-seo-mission-control/`. This is a companion plugin.

If 1.9.0–1.9.3 is already active, upload 1.9.4 over the same Stay Repair folder.

After activate:

1. LiteSpeed Cache → Cache → Guest Mode → OFF
2. LiteSpeed Cache → Crawler → OFF
3. Purge all caches (LiteSpeed + QUIC.cloud if used)
4. WordPress → Stay Repair → paste Site Kit numbers (19K users, 99.8% Direct, 1s, 36 impressions, 0 clicks) → Save → Run audit now
5. Load the homepage once as a logged-out visitor
6. Open **Richard Brummer SEO** (Mission Control) and run the bounded full audit

## Site Kit 19K / 99.8% Direct / 1s

That is crawler and Guest Mode reload traffic counted as Direct. Search Console (36 impressions, 0 clicks) is the real search picture. Do not connect AdSense or Reader Revenue Manager.

## Dashboard red

Live homepage HTML contains three `application/ld+json` blocks. Rank Math and the related-paths ItemList parse. The third block is a truncated FAQPage. Stay Repair deletes only that script from rendered HTML.
