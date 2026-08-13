# Scoring

Catalog Readiness and Integrity are catalog-data scores, not Google or Lighthouse scores.

## 95–100 requires all of

1. Supported local brand evidence (title-leading brand or stored product brand metadata)
2. Traceable ASIN (SKU, Amazon URL, or stored ASIN meta)
3. Real WooCommerce price greater than 0
4. Primary product image
5. No used / refurbished / open-box / for-parts hard risk

Missing ratings, seller, or warranty data is reported as unavailable. Those fields are never invented and never lower an otherwise valid product.

## Integrity 95 vs 100

- 95: core gates pass, model string is not clear
- 100: core gates pass and a local model/series signal is present

## Caps

- Unknown brand or hard-risk condition: cap 70 Integrity / 94 Readiness, status HOLD
- Missing ASIN, price, or primary image: cap 94, status REVIEW
- Remaining taxonomy, attribute, SEO, or marker leftovers are repaired and listed, but they do **not** cap a product that already meets the 95–100 gates

## Averages

The dashboard averages Readiness and Integrity across the products scanned in the last Repair ALL or Full Catalog Audit. HOLD rows stay visible on purpose.
