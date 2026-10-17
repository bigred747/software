=== Luxe Google Presence ===
Contributors: richardbrummer
Tags: seo, amazon, mobile, luxetrendsetters
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Computer and mobile crawl readiness, on-site compare links, then the existing Amazon button.

== Description ==

Luxe Google Presence 1.2.0 keeps one public document for computer and mobile, prints the Amazon Associate disclosure and price disclaimer, and adds rel="sponsored" without changing Amazon URLs. It replaces the generic affiliate line, sends weight and size filters to a real department, repairs image labels that are Amazon file names, and drops an empty PHP session on anonymous catalog pages so those pages can be cached.

It does not buy traffic, auto-redirect to Amazon, write posts, or replace Score Repair, Product Scout, Product Integrity, or Rank Math.

95 means the required live checks passed. 100 means those checks passed and the official Associate sentence plus the full price disclaimer are on the page.

== Changelog ==

= 1.2.0 =
* Anonymous catalog pages drop a brand-new empty PHP session so LiteSpeed can cache them
* Cart, checkout, account, add-to-cart, and a shopper who already has session data keep the cookie
* The 1981 Expires and Pragma no-cache headers from that empty session are removed

= 1.1.0 =
* Replace the generic affiliate disclosure with Amazon’s required sentence and price line
* 301 item-weight and similar filters to the shared department, or the shop
* Repair Amazon file-name image labels and show storage or color on truncated cards
* Do not connect AdSense, send add-to-cart events, or publish pages for zero-click queries

= 1.0.0 =
* Green contract board for Google, Amazon, mobile, desktop, and sibling plugins
* On-site compare block before the existing Amazon button
* Live homepage scan for computer and mobile
