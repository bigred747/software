=== Luxe Score Repair ===
Contributors: richardbrummer
Tags: seo, schema, robots, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
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

= 1.1.0 =
* Green signal board for every process
* Process learning every 15 minutes
* Physical robots.txt heal
* Early /blog/ 301 before Rank Math
* Automatic LiteSpeed purge

= 1.0.0 =
* First public repair pass
