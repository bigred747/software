# Luxe Affiliate Product Scout 5.6.5

Full WordPress plugin for https://luxetrendsetters.com/. Same folder as the live 5.6.4 plugin: `luxe-affiliate-product-scout`.

## Install

1. Download `Luxe-Affiliate-Product-Scout.zip` from the repo root.
2. WordPress → Plugins → Add Plugin → Upload Plugin.
3. When WordPress says the plugin is already installed, choose **Replace current with uploaded**.
4. Open **Luxe Product Scout** in the admin menu.
5. Click **Repair ALL Catalog Data + Recalculate**.
6. Click **Run Full Catalog Audit**.
7. Purge LiteSpeed once.

Do not upload this zip over Score Repair, Mission Control, or Integrity Repair. Those stay in their own folders.

## What 5.6.5 fixes that kept 5.6.4 at 94

- Samsung Galaxy Watch in `Apple Watches`
- Laptop stands / hubs in `MacBooks`
- Brand vs Manufacturer mismatches (example: Apple Watch with Manufacturer Beats)
- Missing Brand attribute on supported products (example: Bose earbuds)
- Stale manufacturer tags

## What stays below 95 on purpose

- Renewed / refurbished / used (HOLD)
- Unknown-brand listings
- Products missing ASIN, price, or primary image
