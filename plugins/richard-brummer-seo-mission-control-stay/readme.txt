=== Richard Brummer SEO Mission Control Stay Repair ===
Contributors: Richard Brummer
Tags: seo, dwell time, litespeed, 404, amazon associates, json-ld, wordpress
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.9.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion to Mission Control 1.8.1. Fixes 1-second time-on-page and quarantines invalid rendered JSON-LD without back-button hijacking.

== Description ==

Install this ZIP **beside** Richard Brummer SEO Mission Control. Do not replace the 1.8.1 folder.

Stay Repair 1.9.5:

* Puts the Guest Mode skip flag first in `<head>` so the 1-second reload cannot run.
* Adds a five-minute compare hub on product tag, category, brand, and shop archives (the URLs Site Kit shows at 1s).
* Skips LiteSpeed Guest Mode's first-visit reload that records 1-second sessions.
* Labels Site Kit 99.8% Direct + 1s + 0 search clicks as crawler/Guest Mode traffic, not Google growth.
* Removes only rendered `application/ld+json` blocks that fail strict JSON parsing (the truncated homepage FAQ schema). Valid Rank Math schema stays.
* Hides leaked [toc] shortcodes and utility-page AI filler on output only.
* Adds related published guides and a reading progress bar so people can stay 5–15 minutes for real.
* Drops plugin CSS/JS when the file is missing on disk (Link Guardian 404s).
* Answers `/meta.json` and `/.well-known/agents.js` instead of 404.
* 301s a short exact-path list of deleted posts. Does not auto-create Rank Math redirects.
* Does not auto-publish, rewrite stored posts, or trap the back button.

== Installation ==

1. Backup the site.
2. In WordPress go to Plugins → Add Plugin → Upload Plugin.
3. Upload `Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.5.zip`.
4. Activate **Richard Brummer SEO Mission Control Stay Repair**.
5. Keep Mission Control 1.8.1 active.
6. Open Stay Repair, save, click Run audit now.
7. LiteSpeed Cache → Cache → turn Guest Mode OFF, Crawler OFF, then purge all caches.
8. Open Mission Control and run the bounded full audit so the dashboard red can clear.

== Changelog ==

= 1.9.5 =
* Inject Guest Mode skip flag as the first script in head.
* Compare hub on tag/category/brand archives so people can actually stay about five minutes.
* Does not fake Google Analytics time and does not trap the back button.

= 1.9.4 =
* Classify Site Kit Direct ≥90% with 1s sessions and 0 search clicks as crawler/Guest Mode, not Google growth.
* Snapshot fields for Direct % and engagement rate.
* Do not treat AdSense, Reader Revenue Manager, or WooCommerce add-to-cart as required.

= 1.9.3 =
* Quarantine only invalid rendered JSON-LD (truncated FAQPage unterminated string).
* Audit the homepage JSON-LD blocks and report SHA-256 + parser error.
* Leave valid Rank Math and ItemList schema untouched.
* Does not rewrite stored post_content or change publish status.

= 1.9.2 =
* Dequeue plugin CSS/JS when the file is missing on disk.
* Serve `/meta.json` and `/.well-known/agents.js` so bot probes stop 404ing.
* 301 a short exact-path list of deleted posts to live catalog pages.
* Audit flags leftover Link Guardian asset URLs after cache.

= 1.9.1 =
* Guest Mode red is green when Stay Repair is already skipping the reload.
* Administrator-entered traffic snapshot is INFO, not a verified red.
* Audit fetches a cache-busted homepage.

= 1.9.0 =
* First Stay Repair module for the 1-second Site Kit dwell collapse, 404 recovery, and Search Console zero diagnostics.
