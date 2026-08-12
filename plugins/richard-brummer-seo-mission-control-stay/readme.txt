=== Richard Brummer SEO Mission Control Stay Repair ===
Contributors: Richard Brummer
Tags: seo, dwell time, litespeed, 404, amazon associates, wordpress
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion to Mission Control 1.8.1. Fixes 1-second time-on-page without back-button hijacking.

== Description ==

Install this ZIP **beside** Richard Brummer SEO Mission Control. Do not replace the 1.8.1 folder.

Stay Repair 1.9.0:

* Skips LiteSpeed Guest Mode's first-visit reload that records 1-second sessions.
* Hides leaked [toc] shortcodes and utility-page AI filler on output only.
* Adds related published guides and a reading progress bar so people can stay 5–15 minutes for real.
* Logs 404s and shows a helpful 404 page. Does not auto-create Rank Math redirects.
* Does not auto-publish, rewrite stored posts, or trap the back button.

== Installation ==

1. Backup the site.
2. In WordPress go to Plugins → Add Plugin → Upload Plugin.
3. Upload `Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.0.zip`.
4. Activate **Richard Brummer SEO Mission Control Stay Repair**.
5. Keep Mission Control 1.8.1 active.
6. Open Stay Repair, save, click Run audit now.
7. LiteSpeed Cache → Cache → turn Guest Mode OFF, then purge all caches.

== Changelog ==

= 1.9.0 =
* First Stay Repair module for the 1-second Site Kit dwell collapse, 404 recovery, and Search Console zero diagnostics.
