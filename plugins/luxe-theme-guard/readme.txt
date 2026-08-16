=== Luxe Theme Guard ===
Contributors: luxetrendsetters
Tags: flatsome, theme update, security, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

24/7 process learning, Amazon AI, and official Flatsome auto-update. Never downloads nulled zips.

== Description ==

Luxe Theme Guard 1.3.0 is the Flatsome auto-update and Amazon AI plugin for luxetrendsetters.com. WordPress admin menu: **Luxe Theme** and **Amazon AI**.

It:

* Runs 24/7 process learning: one theme signal every 5 minutes
* Runs Amazon AI: one catalog item per cycle (ASIN + Associates tag audit)
* Optional official Amazon Creators API (PA-API v5 is retired)
* Takes an hourly read-only snapshot of the licensed Flatsome package
* Applies an official Flatsome parent upgrade as soon as WordPress has a licensed package
* Shows GREEN / RED for XSS patch 3.20.6+, recommended 3.20.9+, license, 24/7 cron, and cache purge
* Purges LiteSpeed after a successful theme update
* Never upgrades during a shopper page view (WP-Cron / admin button only)

It does **not** download pirate/nulled Flatsome zips, overwrite plugins, write post content, change post status, or rewrite Amazon URLs.

A ThemeForest purchase code can be pasted on **Luxe Theme** settings (or Flatsome → Theme Registration). WordPress cannot fetch the official package without it.

== Changelog ==

= 1.3.0 =
* Paste ThemeForest purchase code on Luxe Theme to register Flatsome
* Auto-update official Flatsome every 5 minutes when a licensed package is available
* Direct filesystem for cron upgrades on Hostinger

= 1.2.1 =
* Migrates leftover 12-hour WP-Cron timers to the 5-minute 24/7 loop after a zip replace

= 1.2.0 =
* Amazon AI: one catalog item per 5-minute cycle
* Associates tag lock luxetrendse0f-20
* Official Creators API GetItems (optional credentials)
* Never scrapes amazon.com, never rewrites affiliate URLs, never invents ratings

= 1.1.0 =
* 24/7 process learning: one signal every 5 minutes
* Hourly licensed-package snapshot
* Official Flatsome upgrade at most once per 12 hours
* WP-Cron spawn without writing theme files on public HTML

= 1.0.1 =
* Parent-only Flatsome is green (child theme is optional)
* Board shows only license + 3.20.9 as red until ThemeForest registration

= 1.0.0 =
* Flatsome parent auto-update
* Green-signal board
* Official HTTPS packages only
* Visible **Luxe Theme** admin menu
