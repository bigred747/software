# Safety

Product Scout 5.6.8 will not:

- publish a draft, pending, or private item
- change publication status
- delete products, posts, or pages
- import products
- create redirects or Rank Math redirect rows
- rewrite Amazon or affiliate URLs
- invent ratings, seller, warranty, or unknown brands
- call external scoring or AI APIs
- inject WZone import screens
- run catalog repair on a public page view
- let a 25-product batch replace the full-catalog scoreboard

It may:

- repair and rescore one product every 5 minutes via WP-Cron
- take an hourly read-only snapshot of stored scores
- remove conflicting product categories and stale manufacturer tags
- set Brand / Manufacturer attributes to the title-leading supported brand
- strip known internal machine-control tags and LUXE-PUBLIC-VERIFY markers
- repair deterministic Rank Math brand conflicts
- create an unpublished About draft during manual Repair ALL if no About page exists
- filter WooCommerce related products to the same bucket when that setting is on

Keyword Intelligence remains the owner of ongoing Rank Math keyword/title/description optimization. Mission Control remains the evidence/audit owner.
