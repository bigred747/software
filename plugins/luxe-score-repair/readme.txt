=== Luxe Score Repair ===
Contributors: richardbrummer
Tags: seo, schema, robots, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe 24/7 public-output repair. Holds technical SEO 95–100 and public-output 9.5–10 for luxetrendsetters.com.

== Description ==

Luxe Score Repair 1.2.0 runs always-on process learning:

* heal bloated physical `robots.txt` on public hits
* 5-minute heal cron + 15-minute verify
* visitor HTML snapshots when Hostinger blocks loopback
* strip junk URLs from Rank Math sitemaps
* 301 `/blog/` on `init` before Rank Math
* LiteSpeed purge after file heals

It does **not** write `post_content`, change post status, rewrite Amazon URLs, or create Rank Math redirect rows.

Technical SEO 95–100 and public-output 9.5–10 are this plugin’s job. Editorial Google quality is not.

== Changelog ==

= 1.2.0 =
* 24/7 always-on learning (public-hit heal, 5-minute cron, 15-minute verify)
* Visitor HTML snapshots so scores stay live when loopback is blocked
* Automatic sitemap hygiene (wishlist / portal / test URLs removed)
* X-Robots-Tag on utility URLs; drop leftover Expires/Pragma on public HTML
* Technical SEO 95–100 and public-output 9.5–10 when every process verifies

= 1.1.1 =
* Loopback verification no longer false-reds Public cache + HSTS when the live site already sends them.
* Loopback verification no longer false-reds /blog/ when WordPress already 301s to /blogs/.
* HSTS is also set in wp_headers so loopback and front-end both see it.

= 1.1.0 =
* Green signal board for every process
* Process learning every 15 minutes
* Physical robots.txt heal
* Early /blog/ 301 before Rank Math
* Automatic LiteSpeed purge

= 1.0.0 =
* First public repair pass
