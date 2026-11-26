# Luxe Theme Guard 1.4.6

24/7 process learning, Amazon AI, and official Flatsome auto-update. WordPress admin menu: **Luxe Theme**.

## Install

1. Download `luxe-theme-guard.zip` (Windows 11 / WordPress zip).
2. Plugins → Add Plugin → Upload Plugin → Install Now → Activate (replace 1.4.5 if asked).
3. Open the left menu **Luxe Theme**.
4. Paste your ThemeForest purchase code and click **Save settings**.
5. Leave **Auto-update Flatsome** On. Guard registers the site with api.uxthemes.com (Flatsome 3.14+ `purchaseCode` method), watches the official release, and installs it when UX Themes returns the package. It does not stop at 3.20.9.

Do **not** upload this zip over Flatsome, Product Scout, Luxe SEO, Mission Control, or Stay Repair.

## Registration

Guard must write Flatsome’s `flatsome_registration` option with **purchaseCode** (camelCase) and a license **id** from UX Themes. Never write `flatsome_wup_purchase_code` — that leftover makes Flatsome show “unsupported or outdated method.”

If the purchase code is already registered on another domain:

1. Open [account.uxthemes.com](https://account.uxthemes.com) with the Envato purchaser account
2. Trash the old domain registration
3. Paste the code on **Luxe Theme** and Save again

Guide: https://docs.uxthemes.com/article/417-the-purchase-code-is-already-registered-on-another-site

## 24/7 learning

| Cycle | What it does |
|---|---|
| Every 5 minutes | Learn **one** theme signal and **one** Amazon item. If Flatsome is behind the watched official release, refresh and upgrade. |
| Every hour | Re-read the official Flatsome version. A newer release becomes the new target. |
| Shopper page view | Only pings `wp-cron.php` in the background. Never writes theme files on the public request. |

## License

Flatsome is a paid ThemeForest theme. Paste the purchase code from ThemeForest → Downloads → License certificate on **Luxe Theme**, or register under **Flatsome → Theme Registration**. Guard never downloads a nulled zip.

## Amazon AI

Stays on **Luxe Theme**. One catalog item per 5-minute cycle. Audit-only. Never rewrites Amazon URLs.

## Safety

- Official Flatsome parent only
- Child theme is never replaced
- Plugins are never overwritten
- Never publishes content
- Never rewrites Amazon URLs
