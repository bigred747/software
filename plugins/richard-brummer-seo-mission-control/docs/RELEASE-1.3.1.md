# Richard Brummer SEO Mission Control 1.3.1

## Purpose

This release corrects Amazon evidence classification without expanding the plugin's writing authority. It does not rewrite stored posts, products, buttons, affiliate URLs, theme files, WooCommerce templates, publishing status, redirects, or cache settings.

## Protected behavior

The authority boundaries of the Amazon Associate identification insertion and invalid JSON-LD quarantine are unchanged. The identification detector now preserves visible word boundaries so an existing statement is not duplicated. Cron, Search Console, compatibility ownership, maintenance checks, and technical checks are unchanged.

## Corrected behavior

- Stored-source warnings are separated from final public rendered failures.
- Only a separately fetched published page can create a verified Amazon content RED.
- Source-versus-rendered differences are MANUAL.
- Every Amazon RED now contains inspectable URL, detected tag, expected tag, issue reason, item ID, and cache evidence.
- Shortened links and duplicate-but-equal tag parameters are indeterminate rather than falsely RED or GREEN.
- Conflicting duplicate tags remain RED.
- A static homepage is not penalized twice when the homepage rendered signal already owns the same failure.

## Safe installation

Create a current backup, upload the 1.3.1 ZIP, replace the existing plugin, purge public caches once, load the homepage, and run a bounded full audit. Review exact evidence before changing any companion plugin.
