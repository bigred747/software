=== Luxe Score Repair ===
Contributors: richardbrummer
Tags: seo, schema, robots, luxetrendsetters
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe public-output repair with search focus, process learning, and a green-signal board for luxetrendsetters.com.

== Description ==

Luxe Score Repair 1.2.1 heals public HTML and points Google at products and published buyer guides instead of thin product-tag archives.

It does **not** write `post_content`, change post status, rewrite Amazon URLs, create Rank Math redirect rows, or fake Analytics / Search Console traffic.

== Changelog ==

= 1.2.3 =
* Physical robots.txt is green when Hostinger blocks ABSPATH write but the served file still allows crawling and lists a sitemap
* Exact 301 `/wp-sitemap.xml` → `/sitemap_index.xml` (Stay Repair 404 log). No Rank Math redirect rows

= 1.2.2 =
* Stop false reds when learning actually fetches the homepage (schema id, loopback HSTS, HEAD /blog/)
* Count valid JSON-LD as green even if the script id is stripped by cache
* Verify /blog/ with GET instead of HEAD so Hostinger returns Location

= 1.2.1 =
* Top-level Luxe Score Repair menu so the board is not buried on Tools
* Search-focus status card (setting, robots.txt sitemap line, guide count, sitemap link)
* Zip-replace upgrade heals robots.txt and drops the leftover 19/22 board
* Public watchdog rewrites robots.txt when the search sitemap line is missing

= 1.2.0 =
* Search focus: noindex product tags, attribute archives, and filtered shop URLs
* Drop those URLs from Rank Math / core sitemaps; keep products, posts, and product categories
* `/luxe-search-sitemap.xml` lists homepage, published guides, and product categories
* Homepage JSON-LD ItemList of published buyer guides
* Exact 301 `/date-first-available` → `/blogs/`
* Does not Disallow `/product-tag/` in robots.txt so Google can recrawl and see noindex

= 1.1.0 =
* Green signal board for every process
* Process learning every 15 minutes
* Physical robots.txt heal
* Early /blog/ 301 before Rank Math
* Automatic LiteSpeed purge

= 1.0.0 =
* First public repair pass
