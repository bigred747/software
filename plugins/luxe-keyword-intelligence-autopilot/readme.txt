=== Luxe Keyword Intelligence Autopilot ===
Contributors: richardbrummer
Tags: seo, rank-math, luxetrendsetters
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast attention-first Rank Math phrase repair. Never publishes.

== Description ==

Luxe Keyword Intelligence Autopilot 1.4.0 scans posts, products, and pages in bounded batches. It installs natural Rank Math focus phrases and repairs weak titles, descriptions, and broken featured-image labels.

Published article bodies and excerpts are not rewritten. WordPress status is never changed. item-weight and date-first-available stay noindex.

95 means the repairable keyword fields pass. 100 means those fields pass. The score is not a Google ranking.

== Installation ==

Upload `luxe-keyword-intelligence-autopilot.zip`. Do not unzip it. Activate 1.4.0 and open Keyword Autopilot. Click Repair attention items.

== Changelog ==

= 1.4.0 =
* Replace the one-item cycle with a load-aware batch. At load 32 that is 6 items every 5 minutes, or up to 48 from Repair attention items.
* Daily caps default to 960 scans and 480 repairs so a 411-item attention queue is not stopped at 96 scans.
* Already-green items are skipped. A miss cools down for 6 hours instead of looping.
* Published bodies stay untouched. Scheduled items are scan-only.

= 1.3.0 =
* Attention-first engine, junk taxonomy guard, published body not required for a passing score.
