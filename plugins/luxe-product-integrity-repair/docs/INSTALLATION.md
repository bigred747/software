# Luxe Product Integrity Repair 1.0.0

Companion to **Luxe Affiliate Product Scout 5.6.4** on https://luxetrendsetters.com/.

The original Product Scout zip was not in this repo. This plugin repairs the WooCommerce data Product Scout uses for 95–100. It does not overwrite Scout’s score fields.

## Install

1. Download `dist/Luxe-Product-Integrity-Repair-v1.0.0.zip`.
2. Plugins → Add Plugin → Upload Plugin. This is a **new folder** (`luxe-product-integrity-repair`). Do not upload over Product Scout, Score Repair, or Mission Control.
3. Activate it. Keep Product Scout active.
4. Open **Tools → Luxe Product Integrity**.
5. Click **Repair ALL catalog conflicts**.
6. Open Product Scout and click **Run Full Catalog Audit**.

## What it fixes

- Samsung Galaxy Watch in `Apple Watches`
- Laptop stands / hubs in `MacBooks`
- Brand vs Manufacturer mismatches (example: Apple Watch with Manufacturer Beats)
- Stale manufacturer tags

## What stays below 95 on purpose

- Renewed / refurbished / used (HOLD)
- Unknown-brand listings
- Products missing ASIN, price, or primary image
