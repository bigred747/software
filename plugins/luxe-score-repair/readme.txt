=== Luxe Score Repair ===
Contributors: richardbrummer
Tags: seo, schema, robots, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe public-output repair for luxetrendsetters.com. Fixes titles, schema, robots.txt, junk-page indexing, and cache headers without writing posts.

== Description ==

Luxe Score Repair is a companion plugin for LuxeTrendsetters. It repairs the public HTML Google actually fetches.

It does **not**:

* write `post_content`
* change post status
* rewrite Amazon or product permalinks
* create Rank Math redirect rows
* run a content cron

It **does**:

* replace the homepage title `HOME: Features, Use Cases and Buyer Fit`
* replace the generic homepage meta description
* quarantine truncated FAQ JSON-LD and emit a valid FAQ + Organization graph
* replace the 110KB Autopilot `robots.txt` with a short valid file pointing at `sitemap_index.xml`
* noindex cart, checkout, account, wishlist, client portal, and the test blog
* 301 `/blog/` to `/blogs/`
* hide leaked `[toc]` shortcodes and AI filler on output
* fill missing image alts
* send public cache headers on catalog pages

After activate: LiteSpeed → Purge All. Keep Guest Mode OFF and Crawler OFF.

== Installation ==

1. Upload the `luxe-score-repair` folder via Plugins → Add Plugin → Upload Plugin.
2. Activate.
3. LiteSpeed Cache → Purge All.
4. Open Tools → Luxe Score Repair.

== Changelog ==

= 1.0.0 =
* First public repair pass for the live luxetrendsetters.com scan.
