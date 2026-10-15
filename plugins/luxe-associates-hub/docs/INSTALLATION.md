# Luxe Associates Hub 1.0.0

Admin companion for the Amazon Associates starter guide on https://luxetrendsetters.com/.

It connects to the Luxe master set already on the site:

- **Luxe Affiliate Product Scout** — reads ASINs that are already stored. It does not rewrite Amazon URLs.
- **Luxe Score Repair** — shows whether the homepage associate disclosure is on.
- **Luxe Product Integrity Repair** — listed so catalog repair stays one click away.

## Install

1. Download `dist/Luxe-Associates-Hub-v1.0.0.zip`.
2. Plugins → Add Plugin → Upload Plugin → Install Now → Activate.
3. Open **Associates Hub** in the left menu.

## What you do on Amazon, in your own browser

1. Sign in at Associates Central and submit payment and tax info.
2. Come back to Associates Hub and check **I already submitted payment and tax info on Amazon**.
3. Paste your tracking ID (`storename-20`). Save.
4. Build a product link from a catalog ASIN, or create one with SiteStripe or Mobile GetLink.
5. Put this disclosure next to the link: `As an Amazon Associate I earn from qualifying purchases.`
6. In a post or page, the strict block is `[luxe_amazon asin="B0ABCDEF12" title="View this on Amazon"]`. The href is the direct Amazon Special Link, the disclosure is in the block, and the anchor uses `rel="sponsored noopener nofollow"`.

## Rules the block follows

- Amazon: tagged `amazon.com` link, required associate sentence, no claim that Amazon endorses the site, no cloaked redirect.
- Google: affiliate links are marked `sponsored`. The block does not invent a star rating or a price-valid-until date.

## What this plugin never does

- It never asks for your Amazon password.
- It never stores a bank account or tax ID.
- It never opens your earnings report.
- It never enrolls you in Creator Connections, Creator Rewards, or Content Partners.
- It never publishes products or edits affiliate URLs in the catalog.

Bounty links for Prime, Amazon Music, and Audible still have to be copied from SiteStripe so Amazon attaches the bounty.
