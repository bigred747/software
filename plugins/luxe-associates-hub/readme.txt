=== Luxe Associates Hub ===
Contributors: luxetrendsetters
Tags: amazon associates, affiliate links, disclosure
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Admin checklist for the Amazon Associates starter guide, wired to the Luxe master set.

== Description ==

Associates Hub walks through the Associates Central starter steps and places strict Amazon Special Links in your content.

The shortcode `[luxe_amazon asin="B0ABCDEF12"]` prints a direct tagged amazon.com link, the required associate disclosure, and `rel="sponsored noopener nofollow"`. It does not cloak the URL, name a price, or say Amazon endorses the site.

It does not log into Amazon. It does not store an Amazon password, bank account, or tax ID. It does not rewrite catalog URLs, publish products, or call Amazon.

The Luxe master set on this screen is:

* Luxe Affiliate Product Scout — read ASINs already on products.
* Luxe Score Repair — homepage associate disclosure.
* Luxe Product Integrity Repair — catalog brand and category repair.

Payment, tax, earnings reports, Creator Connections, bounty links, and Content Partners enrollment stay on Amazon’s own sites.

== Installation ==

1. Upload `dist/Luxe-Associates-Hub-v1.0.0.zip` in Plugins → Add Plugin → Upload Plugin.
2. Activate Luxe Associates Hub.
3. Open **Associates Hub**.
4. Paste the tracking ID from Associates Central. It looks like `storename-20`.
5. Submit payment and tax info on Amazon, then mark that step done here.
6. Build one tagged product link, or copy one from SiteStripe, and share it with the disclosure.

== Changelog ==

= 1.0.0 =
* Starter guide for payment and tax, SiteStripe, earnings, policies, Creator Rewards, tutorials, Creator Connections, subscription bounties, Content Partners, and the Operating Agreement.
* Shortcode `[luxe_amazon asin=""]` prints a direct tagged Amazon link, the required disclosure, and rel="sponsored noopener nofollow".
* Public pages get the sponsored rel on Amazon offer links. Existing hrefs are not rewritten.
* A "use the brand button" sentence is corrected when that page already links to Amazon.
* Read-only status for the Luxe master set.
* No Amazon login, no password storage, no catalog writes.
