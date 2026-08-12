# Installation and Safe Upgrade

Copyright © 2026 Richard Brummer.

## Before installation

1. Create a complete file and database backup.
2. Confirm WordPress 6.5 or newer and PHP 7.4 or newer.
3. Record the current active-plugin list and any custom theme/WooCommerce template changes.
4. Do not delete companion plugins merely because Mission Control reports overlap; inspect evidence first.

## Install version 1.6.0

1. Open **Plugins → Add Plugin → Upload Plugin**.
2. Select `Richard-Brummer-SEO-Mission-Control-v1.6.0.zip`.
3. Choose **Replace current with uploaded** when WordPress detects the older version.
4. Activate the plugin if WordPress did not preserve activation.
5. Purge LiteSpeed/QUIC.cloud full-page cache once.
6. Load the public homepage once.
7. Open **Richard Brummer SEO → 24/7 Engine**.
8. Click **Run heartbeat now** and confirm homepage plus REST API probes succeed.
9. Click **Run full learning now** and confirm the cycle finishes without a verified red error.

## Reliable 24/7 scheduling

WordPress WP-Cron wakes when the site receives requests. For dependable all-day execution, add the exact host-cron command shown under **Richard Brummer SEO → 24/7 Engine**. The suggested command wakes WordPress every five minutes; the plugin still limits the heartbeat to its selected 15-minute, 30-minute, or hourly schedule and limits the full audit to hourly or every six hours.

After adding host cron, verify that both **Last heartbeat** and **Last full learning cycle** continue advancing.

## Default continuous settings

- Continuous engine: enabled.
- Heartbeat: every 15 minutes.
- Full evidence and learning cycle: every hour.
- Update inventory refresh: every six hours.
- Blind plugin/theme update installation: disabled and not available.

Adjust these under **Settings → 24/7 evidence engine** only after the default cycle runs correctly.

## Traffic Quality setup

1. Open **Traffic Quality**.
2. Use one date range for every field, normally 28 days.
3. Enter Analytics users, Direct percentage, Organic Search percentage, engagement rate, average session seconds, key events, Search Console impressions, and Search Console clicks.
4. Save and review the diagnostic evidence.
5. Treat extreme Direct traffic or a large Analytics/Search Console mismatch as unverified until server/CDN logs, hostnames, bot filtering, attribution, and tag behavior are checked.

## Content Lab test

1. Open **Content Lab**.
2. Enter one narrow buyer question or choose a real Search Console query.
3. Answer all ten questions with your own experience, testing, research notes, or authorized material.
4. Confirm the rights/ownership statement.
5. Create the draft.
6. Open the draft and fact-check every current model, specification, price, seller, policy, quotation, image, and link.
7. Publish only after human review.

## Search Console connection

Follow `docs/SEARCH-CONSOLE-WORKFLOW.md`. Store the service-account JSON key outside the public web directory and define `RBSMC_GSC_KEY_FILE` plus the exact property in `wp-config.php`.

## Verify Social Growth

1. Open **Richard Brummer SEO → Social Growth**.
2. Select one published page.
3. Enter an exact audience and at least 80 characters of original firsthand evidence.
4. Keep the destination on the same site.
5. Complete the rights attestation and create one package.
6. Confirm five hooks, a timed script, shot list, caption, tracked URL, and platform checklist appear.
7. Export the package as TXT.
8. Create an idea queue from one page and confirm exactly three idea-only angles are added.
9. Record a test performance snapshot and confirm the local learning summary updates.
10. Delete the test campaign records if they are not part of the real editorial queue.

Do not paste another creator's script, caption, prompt, video, music, images, or distinctive presentation into the workflow.

## Rollback

If an unexpected issue appears, deactivate 1.6.0, restore the prior plugin ZIP, purge cache, and compare the evidence export and WordPress debug log. Continuous evidence history remains in WordPress unless the plugin is uninstalled with the explicit delete-data constant. Content Lab drafts remain ordinary WordPress drafts and are never published automatically.
