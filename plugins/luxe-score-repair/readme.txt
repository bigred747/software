=== Luxe SEO Score Repair ===
Contributors: richardbrummer
Tags: seo, schema, robots, luxetrendsetters, copyright
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The LuxeTrendsetters SEO plugin. Green-signal board, Amazon AI, unique-copy ranking, and a copyright lock. No copied Instagram video. No thin 37-word pages.

== Description ==

Luxe SEO Score Repair 1.3.0 is the public SEO plugin for luxetrendsetters.com. It lives in the left admin menu as **Luxe SEO**. Amazon AI lives here, not in Luxe Theme.

Automatic heals:

* Amazon AI: one catalog item every 5 minutes (ASIN + Associates tag audit)
* optional official Amazon Creators API (PA-API v5 is retired)
* overwrite bloated physical `robots.txt`
* 301 `/blog/` on `init` before Rank Math
* LiteSpeed purge after each heal
* 15-minute process learning (verify + exact-path hop learning)
* copyright lock against copied Instagram video/CDN media
* unique buyer-guide copy (thin 37-word ranking pages are refused)

It does **not** write `post_content`, change post status, rewrite Amazon URLs, create Rank Math redirect rows, copy Instagram video, or invent Amazon ratings.

== Changelog ==

= 1.3.0 =
* Amazon AI moved into the SEO plugin (not Luxe Theme)
* 5-minute Associates ASIN/tag audit
* Optional official Creators API credentials on Luxe SEO
* Left menu **Luxe SEO → Amazon AI**

= 1.2.0 =
* Visible as **Luxe SEO** in the WordPress admin menu (no longer buried under Tools).
* Plugin name is Luxe SEO Score Repair so it is findable as the SEO plugin.
* Copyright lock signal: no copied Instagram video on public pages.
* Unique-copy signal: real buyer-guide copy required; 37-word ranking trick refused.
* Homepage canonical signal.

= 1.1.0 =
* Green signal board for every process
* Process learning every 15 minutes
* Physical robots.txt heal
* Early /blog/ 301 before Rank Math
* Automatic LiteSpeed purge

= 1.0.0 =
* First public repair pass
