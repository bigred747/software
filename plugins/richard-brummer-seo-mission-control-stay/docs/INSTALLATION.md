# Stay Repair 1.9.3

Install the ZIP through **Plugins → Add Plugin → Upload Plugin**.

Do **not** upload this over `richard-brummer-seo-mission-control/`. This is a companion plugin.

If 1.9.0, 1.9.1, or 1.9.2 is already active, upload 1.9.3 over the same Stay Repair folder.

After activate:

1. LiteSpeed Cache → Cache → Guest Mode → OFF
2. LiteSpeed Cache → Crawler → OFF
3. Purge all caches (LiteSpeed + QUIC.cloud if used)
4. WordPress → Stay Repair → Save → Run audit now
5. Load the homepage once as a logged-out visitor
6. Open **Richard Brummer SEO** (Mission Control) and run the bounded full audit

## Dashboard red this version fixes

Live homepage HTML contains three `application/ld+json` blocks. Rank Math and the related-paths ItemList parse. The third block is a truncated FAQPage that ends at:

`Are prices and availability final?` → `"text":"`

That unterminated string is the Mission Control **1 verified red**. 1.9.3 deletes only that script from rendered HTML. It does not edit the post in the database.
