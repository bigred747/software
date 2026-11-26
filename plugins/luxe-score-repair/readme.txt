=== Luxe Score Repair ===
Contributors: richardbrummer
Tags: seo, schema, robots, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe public-output repair with process learning and a green-signal board for luxetrendsetters.com.

== Description ==

Luxe Score Repair 1.1.0 heals public HTML and shows a green/red signal for every process.

Automatic heals:

* overwrite bloated physical `robots.txt`
* 301 `/blog/` on `init` before Rank Math
* LiteSpeed purge after each heal
* 15-minute process learning (verify + exact-path hop learning)

It does **not** write `post_content`, change post status, rewrite Amazon URLs, or create Rank Math redirect rows.

== Changelog ==

= 1.3.0 =
* Associates Hub now lives in this plugin. One upload. No separate Associates plugin.
* Shortcode `[luxe_amazon asin=""]` prints a direct tagged Amazon link, the required disclosure, and rel="sponsored noopener nofollow".
* Amazon offer links on the public site get the sponsored rel. Existing Amazon URLs are not rewritten.

= 1.2.0 =
* Structured data no longer claims an Amazon price is valid until a future date.
* Product review and aggregateRating markup is removed. This site does not publish those reviews.
* Privacy, terms, contact, and about pages are not marked up as blog posts.
* Amazon URLs are still never rewritten.

= 1.1.0 =
* Green signal board for every process
* Process learning every 15 minutes
* Physical robots.txt heal
* Early /blog/ 301 before Rank Math
* Automatic LiteSpeed purge

= 1.0.0 =
* First public repair pass
