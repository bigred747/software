# Rules this plugin follows

The contract board fails closed. A hard miss scores 70, not 95.

## Google

- One HTML document for computer, mobile, and Googlebot. No user-agent switch.
- Affiliate anchors get `rel="sponsored"`. The href is not edited.
- No doorway pages, no hidden keyword blocks, no review markup, no Indexing API calls.
- Viewport is added only when the theme did not print one.
- Rank Math keeps `sitemap_index.xml`. Submit that URL in Search Console.

## Amazon Associates

- The page says: "As an Amazon Associate I earn from qualifying purchases."
- The page says the official price and availability disclaimer for Amazon.com.
- The compare block is labeled "(paid link)" and sits in the page, not a pop-up.
- Shoppers open on-site guides first, then click the Amazon button already on the page.
- Item weight, package weight, and similar attribute archives redirect to a product department on this site, or to `/shop/`.
- The generic “may contain affiliate links” line is replaced with the official Associate sentence. Amazon file-name image labels are replaced with the product name.
- AdSense is not connected, and add-to-cart events are not sent.
- This plugin does not create Amazon sessions by itself, buy impressions, or offer a reward for clicking.

## Sibling plugins

Score Repair keeps titles, robots.txt, schema, and the homepage disclosure. Product Scout keeps scores and related products. Product Integrity keeps taxonomy repairs. WooCommerce keeps the product button.
