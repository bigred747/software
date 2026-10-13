=== Luxe Theme Guard ===
Contributors: luxetrendsetters
Tags: flatsome, theme update, security, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

24/7 process learning, Amazon AI, and official Flatsome auto-update. Watches the current official release. Never downloads nulled zips.

== Description ==

Luxe Theme Guard 1.4.3 is the Flatsome auto-update plugin for luxetrendsetters.com. WordPress admin menu: **Luxe Theme**. Amazon AI stays on this screen.

It:

* Runs 24/7 process learning: one theme signal and one Amazon catalog item every 5 minutes
* Watches the official Flatsome demo stylesheet for the current release, so the target moves past 3.20.9
* Applies an official Flatsome parent upgrade as soon as WordPress has a licensed package
* Shows GREEN / RED for XSS patch 3.20.6+, the watched official release, license, 24/7 cron, and cache purge
* Purges LiteSpeed after a successful theme update
* Never upgrades during a shopper page view (WP-Cron / admin button only)

It does **not** download pirate/nulled Flatsome zips, overwrite plugins, write post content, change post status, or rewrite Amazon URLs.

A ThemeForest purchase code can be pasted on **Luxe Theme** settings (or Flatsome → Theme Registration). WordPress cannot fetch the official package without it.

== Changelog ==

= 1.4.3 =
* Register the saved purchase code with the UX Themes API (api.uxthemes.com) and install the official package
* The retired wupdates product id is no longer shown as an unreadable response
* Show the API result when a code is unknown, already registered, or over the site limit

= 1.4.2 =
* Ask Flatsome's official updater for the package after the purchase code is saved
* Show the official server result when no download comes back
* Purge cache only after a successful theme update

= 1.4.1 =
* Accept a ThemeForest purchase code pasted with the license certificate text, line breaks, or unicode dashes
* A rejected code no longer shows a success notice

= 1.4.0 =
* Watch the official Flatsome release instead of stopping at 3.20.9
* Auto-install that watched release when WordPress has a licensed package
* Amazon AI stays on Luxe Theme

= 1.3.1 =
* Amazon AI moved to Luxe SEO. Theme Guard is Flatsome auto-update only.

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
