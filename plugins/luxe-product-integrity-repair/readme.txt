=== Luxe Product Integrity Repair ===
Contributors: richardbrummer
Tags: woocommerce, product integrity, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Companion to Luxe Affiliate Product Scout. Repairs taxonomy/brand conflicts that cap honest scores at 94.

== Description ==

Product Scout 5.6.4 keeps products at 94 when Apple/Samsung watch categories or Brand/Manufacturer attributes conflict. This plugin repairs those live WooCommerce fields, then you recalculate in Product Scout.

It does **not** write fake 95–100 scores. Renewed/used items and unknown brands stay held.

Safety:

* never publishes
* never deletes
* never rewrites Amazon URLs
* never invents ratings, seller, warranty, or brands

== Changelog ==

= 1.1.0 =
* Repair ALL ignores a stale lock so the full 233-product catalog always runs
* Stronger Apple Watches / Samsung Watches / MacBooks matching
* Flushes WooCommerce and LiteSpeed caches after repair so Product Scout sees live data

= 1.0.0 =
* Repair Apple Watches / Samsung Watches / MacBooks leftovers
* Align Brand and Manufacturer attributes to the title-leading supported brand
* Remove stale manufacturer tags
* 6-hour bounded batches
* Repair ALL covers the 233-product catalog in one pass
