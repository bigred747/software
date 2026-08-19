=== Luxe SEO Score Repair ===
Contributors: richardbrummer
Tags: seo, schema, robots, luxetrendsetters, copyright
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The LuxeTrendsetters SEO plugin. Green-signal board, Amazon AI, unique-copy ranking, and a copyright lock. No copied Instagram video. No thin 37-word pages.

== Description ==

Luxe SEO Score Repair 1.4.0 is the public SEO plugin for luxetrendsetters.com. It lives in the left admin menu as **Luxe SEO**. Amazon AI lives here, not in Luxe Theme.

Automatic heals:

* Amazon AI: one catalog item every 5 minutes (ASIN + Associates tag audit)
* optional official Amazon Creators API (PA-API v5 is retired)
* overwrite bloated physical `robots.txt`
* 301 `/blog/` and `/wp-sitemap.xml` on `init` before Rank Math
* 410 Gone for removed Link Guardian plugin files and `/meta.json`
* official Amazon Associate identification on public pages
* LiteSpeed purge after each heal
* 15-minute process learning (verify + exact-path hop learning)
* copyright lock against copied Instagram video/CDN media
* unique buyer-guide copy (thin 37-word ranking pages are refused)
* read-only duplicate title list (human Rank Math review)

Loopback skips stay **yellow**. They are not fake-green.

It does **not** write `post_content`, change post status, rewrite Amazon URLs, create Rank Math redirect rows, copy Instagram video, invent Amazon ratings, or auto-hop product / category / tag URLs.

== Changelog ==

= 1.4.0 =
* Honest loopback: unverified live fetches stay yellow
* Official Associates identification on every public page
* Exact 301 `/wp-sitemap.xml` → `/sitemap_index.xml`
* 410 Gone for dead Link Guardian plugin files and `/meta.json`
* robots.txt Disallow those dead paths
* Read-only duplicate title list
* Learning never auto-hops product, category, tag, or Amazon URLs

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
