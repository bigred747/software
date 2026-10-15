=== Luxe Mission Score Repair ===
Contributors: richardbrummer
Tags: seo, woocommerce, amazon, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real repairs for the Mission Control checks that are still yellow or red. Does not publish and does not rewrite Amazon retail URLs.

== Description ==

Mission Control 1.8.0 scored 93/100 from 38 verified greens, 2 yellows, and 2 reds. Manual and info rows are already excluded. This plugin changes the evidence those four scored rows measure.

* Homepage output: removes the Wordfence login-screen translation catalog, Thickbox, Contact Form 7, and block-library CSS that the homepage markup does not use. A saved homepage fell from 260,204 bytes and 44 scripts to 197,719 bytes and 39 scripts. Stylesheet links stay at 2. jQuery, JSON-LD, the storefront CSS, and the 16 affiliate links stay.
* Amazon final output: Pinterest and other non-Amazon share links drop embedded Amazon image URLs. Product-gallery anchors to the Amazon image CDN stop using that URL as the href. Zoom keeps `data-large_image`. amazon.com and amzn.to URLs are not rewritten, and `rel="sponsored"` is not added to anything that is not an affiliate link.
* WooCommerce templates: merges the five WooCommerce 11.2 behavior changes into the theme overrides (`cart.php`, `mini-cart.php`, `cart-shipping.php`, `thankyou.php`, `single-product-reviews.php`). `@version 11.2.0` is written only after that behavior is in the file. A divergent override is left unchanged. Backups are stored under uploads.

The dashboard worker is red while verified red or yellow checks remain. It is not forced green.

== Installation ==

1. Upload the zip in Plugins → Add Plugin → Upload Plugin.
2. Activate Luxe Mission Score Repair.
3. Open Tools → Luxe Mission Score and confirm the five template rows.
4. Load the homepage once so LiteSpeed stores the trimmed HTML.
5. Run the Mission Control audit.

== Changelog ==

= 1.0.0 =
* Homepage asset trim for assets the front page does not use.
* Share-link and image-CDN anchor repair that leaves Amazon retail URLs alone.
* WooCommerce 11.2 template merge with an evidence gate on @version.
