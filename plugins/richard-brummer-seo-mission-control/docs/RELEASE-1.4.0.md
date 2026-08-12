# Richard Brummer SEO Mission Control 1.4.0

## Purpose

This release adds original traffic-quality and first-party content processes while preserving the plugin's evidence rules and bounded front-end repairs.

## Traffic Quality

The new tab accepts one matching-period Analytics or Site Kit snapshot and compares it with Search Console figures. It flags:

- unusually concentrated Direct traffic;
- large user totals unsupported by Search Console impressions or clicks;
- extremely short, low-engagement sessions;
- zero configured key events;
- missing Site Kit or rendered analytics markers.

Administrator-entered metrics are always labeled as such and use `scored=false`, so they cannot change the verified-check score.

## Content Lab

The new Content Lab:

1. accepts a specific search question or a Search Console opportunity;
2. asks ten independently written, one-at-a-time questions about experience, testing, buyer intent, measurements, failures, strengths, alternatives, steps, misleading assumptions, and the final recommendation;
3. stores plain-text answers;
4. requires confirmation that the material is owned, licensed, public-domain, or otherwise authorized;
5. builds a review-only WordPress draft;
6. compares it with recent local content using five-word-shingle Jaccard similarity;
7. blocks draft creation at 70% or greater local similarity;
8. never auto-publishes.

The workflow does not scrape keyword services, third-party articles, social-media prompts, or product pages.

## Public-site performance inventory

The rendered homepage audit now records HTML bytes, script count, stylesheet count, possible blocking head scripts, lazy-loaded images, and images missing dimensions. These are local heuristics and are explicitly not presented as PageSpeed Insights, CrUX, or Core Web Vitals measurements.

## Protected behavior

The Amazon identification insertion and invalid JSON-LD quarantine are unchanged. Existing post/product content, affiliate links, statuses, themes, templates, redirects, and cache settings are not rewritten.

## Safe installation

Create a fresh backup, upload the 1.4.0 ZIP, replace the older plugin, purge full-page cache once, load the homepage, and run the bounded full audit. Then enter one same-period traffic snapshot and test one Content Lab interview through draft creation.
