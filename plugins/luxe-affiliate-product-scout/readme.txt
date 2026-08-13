=== Luxe Affiliate Product Scout ===
Contributors: luxetrendsetters
Tags: woocommerce, affiliate products, catalog audit, product integrity, rank math
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 5.6.7
License: GPLv2 or later

Safe WooCommerce catalog auditing and repair with honest 95-100 product-integrity scoring, supported-brand repair, taxonomy/SEO cleanup, and related-product integrity.

== Description ==

Version 5.6.7 is admin-only until you click Repair ALL. It does not hook the public site on activate, does not run an immediate cron repair, and cannot take WordPress down with an uncaught error.

Version 5.6.6 scores every product that already has supported brand evidence, ASIN, price, a primary image, and no hard risk flags at 95–100. Taxonomy/brand-attribute leftovers are still repaired, but they no longer cap those products at 94.

A 95–100 score requires those core local facts. Missing ratings, seller, or warranty data is reported as unavailable and is never invented, and it does not lower an otherwise valid product.

Core truth gates:

* Supported local brand evidence.
* Traceable ASIN.
* Real local price.
* Primary product image.
* No used/refurbished/parts hard risk.

Live repair rules:

* Samsung smartwatches cannot remain in Apple Watches.
* Apple smartwatches cannot remain in Samsung Watches.
* Non-watch products lose Apple Watches/Samsung Watches leftovers.
* Non-Apple products and compatibility accessories lose MacBooks/Apple Laptops leftovers.
* Stale manufacturer tags are removed when they disagree with the supported product brand.
* Unsupported public Brand/Manufacturer attributes are removed instead of displayed as fake evidence.
* Supported brands replace stale custom Brand attribute values.
* Unknown brands stay Unknown rather than receiving a synthetic brand.

SEO repair rules:

* Product-level stale brand/internal-machine Rank Math conflicts are repaired when the evidence is deterministic.
* Product Scout does not compete with Keyword Intelligence for ongoing keyword optimization; Keyword Intelligence remains the ongoing Rank Math keyword/title/description owner.
* Mission Control remains the evidence/audit owner.
* A clearly generic homepage SEO shell can be repaired to a neutral LuxeTrendsetters title/description.

Related-product rules:

* WooCommerce related products are filtered to the same real product bucket.
* Audited products below 95 Readiness or Integrity are excluded from related-product output.

Safety rules:

* Never publishes a draft/pending/private item.
* Never changes publication status.
* Never deletes content.
* Never imports products.
* Never creates redirects.
* Never rewrites affiliate URLs.
* Never injects WZone import screens/scripts.
* Never calls external scoring or AI APIs.
* During the manual Repair ALL action only, it may create a clearly labeled About draft for human review if no About page exists; the draft is never published automatically.

== Upgrade Notes ==

1. Back up WordPress files and database.
2. Plugins → Add Plugin → Upload Plugin.
3. When WordPress asks to replace the current plugin, choose Replace. Folder name is `luxe-affiliate-product-scout`.
4. Open **Luxe Product Scout**.
5. Run **Repair ALL Catalog Data + Recalculate**.
6. Run **Run Full Catalog Audit**.
7. Purge LiteSpeed once after the repair.
8. Review any remaining products below 95. They are intentionally held when the brand is unknown, ASIN/price/image is missing, or a real hard-risk condition remains. Taxonomy leftovers no longer keep an otherwise valid product at 94.

== Changelog ==

= 5.6.7 =
* Admin-only by default. No frontend related-product hook and no auto-repair on activate, so WordPress cannot white-screen.
* Activation no longer fires an immediate catalog cron.
* Product cache refresh no longer triggers WooCommerce update hooks / WZone.
* Invalid UTF-8 in Amazon titles cannot throw a PHP error.
* Class/function guards prevent a fatal if an old Product Scout copy is still present.

= 5.6.6 =
* 95–100 now follows the published gates: supported brand + ASIN + price + primary image + no hard risk flags.
* Taxonomy/brand-attribute leftovers are repaired and listed, but they no longer cap an otherwise valid product at 94.
* Missing ratings, seller, or warranty data still never invents evidence and never lowers a valid product.
* Brand matching accepts “Bose Corporation” style manufacturer strings and stored WZone `_brand` metadata.
* Repair ALL processes the full catalog in one pass and writes `_luxe_homepage_ready` for 5.6.4 readers.
* Unknown-brand and renewed/used products stay HOLD below 95.

= 5.6.5 =
* Repair ALL now clears Apple Watches / Samsung Watches / MacBooks leftovers and Brand/Manufacturer mismatches in the same pass as recalculate, so Integrity is no longer stuck at 94 after a clean audit.
* Dashboard shows Catalog Readiness, Integrity, REVIEW, HOLD, unknown brands, and conflict counters.
* Related-product bucket filter excludes items below 95.
* Same 95+ truth gates, Renewed/Used holds, status preservation, no redirects, no imports, no Amazon URL rewrites, no external API calls.

= 5.6.4 =
* Live site build. Public readme still listed 5.6.3 as the stable tag.

= 5.6.3 =
* SEO-ownership coordination with Keyword Intelligence and Mission Control.
* Neutral homepage SEO-shell repair.
* Machine-control tag cleanup.
* Deterministic generated-copy relevance cleanup.
* Manual Repair ALL may create an unpublished About draft.

= 5.6.2 =
* Public taxonomy conflict repair for Apple Watches, Samsung Watches, MacBooks.
* Brand/Manufacturer attribute cleanup.
* Related-product filtering and truthful score caps below 95.

= 5.6.1 =
* Honest 95-100 Product Integrity Score.
* Ratings, seller, and warranty optional unless locally present.
* Hard truth gates preventing fake 95+ scores.
