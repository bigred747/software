=== Luxe Blog Review Desk ===
Contributors: luxetrendsetters
Tags: woocommerce, buyer guides, review, publish
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later

View live buyer guides. Publish only drafts that already pass Command Center 95-100 / Approval Ready gates.

== Description ==

Command Center never publishes. This desk is the human View + Publish step.

* View opens the live page or the draft preview.
* Publish appears only for draft/pending posts with a real product and Approval Ready or a 95-100 score.
* Product #0 stays blocked.
* Master #10833 is read-only.
* No cron. No auto-publish. No deletes. No Amazon URL rewrites.

== Installation ==

1. Upload the zip in Plugins → Add Plugin → Upload Plugin.
2. Activate **Luxe Blog Review Desk**.
3. Open **Luxe Review Desk** in the admin menu.
4. Click View on a live guide. Click Publish only when a row is in Ready to publish.

== Changelog ==

= 1.0.1 =
* Read Command Center product / score / Approval Ready keys (`_luxe_bmc_*`).
* Skip a stored product 0 so it cannot hide a real locked product.
* Load Keep Live + drafts from Command Center audit memory instead of only the last 80 modified posts.
* Published rows with no stored score show live, matching Command Center.

= 1.0.0 =
* First review desk with View and gated Publish.
