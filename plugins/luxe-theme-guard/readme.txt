=== Luxe Theme Guard ===
Contributors: luxetrendsetters
Tags: flatsome, theme update, security, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically updates official Flatsome and shows a green-signal board. Never downloads nulled zips.

== Description ==

Luxe Theme Guard 1.0.1 is the Flatsome auto-update plugin for luxetrendsetters.com. WordPress admin menu: **Luxe Theme**.

It:

* Enables WordPress auto-updates for the Flatsome **parent** theme only
* Checks every 12 hours and applies an official licensed package when WordPress has one
* Shows GREEN / RED for XSS patch 3.20.6+, recommended 3.20.9+, license, cron, and cache purge
* Purges LiteSpeed after a successful theme update

It does **not** download pirate/nulled Flatsome zips, overwrite plugins, write post content, change post status, or rewrite Amazon URLs.

A ThemeForest purchase code must already be registered under **Flatsome → Theme Registration** or WordPress cannot fetch the official package.

== Changelog ==

= 1.0.1 =
* Parent-only Flatsome is green (child theme is optional)
* Board shows only license + 3.20.9 as red until ThemeForest registration

= 1.0.0 =
* Flatsome parent auto-update
* Green-signal board
* Official HTTPS packages only
* Visible **Luxe Theme** admin menu
