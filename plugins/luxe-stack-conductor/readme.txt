=== Luxe Stack Conductor ===
Contributors: richardbrummer
Tags: cron, woocommerce, luxetrendsetters
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One queue for every 24/7 learner, with a real green, yellow, or red signal on each process.

== Description ==

Luxe Stack Conductor sits beside the plugins already running on LuxeTrendsetters. It does not replace them and it does not deactivate them.

On each WP-Cron hit it keeps one heavy learner per lane, up to a learned cap of 1 to 3 jobs. The rest move 75 seconds forward. WooCommerce, WZone, Rank Math, LiteSpeed, and Wordfence are never postponed. The public homepage filters stay on the request, which is what keeps the site looking the way it looks.

The signal board is inside this plugin. A process is green only when its own check passed: the plugin is active, its cron is not stalled, and any stored evidence it already published still adds up. A 48/49 report stays yellow. An overdue learner stays yellow or red. Mission Control keeps its own evidence dashboard.

Every 5 minutes the conductor stores a read-only snapshot and learns which hooks are late. Three cron hits under 8 seconds raise the cap by one. A hit over 12 seconds drops it back to one.

== Installation ==

1. Upload the zip in Plugins → Add Plugin → Upload Plugin.
2. Activate Luxe Stack Conductor. Leave every other plugin active.
3. Open Luxe Conductor in the admin menu.
4. Point Hostinger cron at wp-cron.php every 5 minutes so learning does not wait for a visitor.

== Changelog ==

= 1.0.1 =
* A stalled cron hook gets a real future run before the board is read. Green means that next run is stored, or the check passed. An overdue hook with no future run stays yellow or red.

= 1.0.0 =
* Lane queue for the live plugin stack.
* Honest per-process signals.
* 5-minute read-only learning snapshot and a self-tuning slot cap.
