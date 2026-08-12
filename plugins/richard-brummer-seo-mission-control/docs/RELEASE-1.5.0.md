# Richard Brummer SEO Mission Control 1.5.0

## Continuous evidence engine

Version 1.5.0 adds a bounded 24/7 monitoring and local-learning system. It is designed to keep evidence fresh without turning the plugin into an uncontrolled crawler, publisher, updater, or self-modifying program.

### Default schedules

- Lightweight heartbeat: every 15 minutes.
- Full evidence and learning cycle: every hour.
- Update inventory refresh: every six hours.
- Search Console background evidence refresh: every six hours; a manual Search Console run forces fresh API requests.

The schedules are configurable in **Richard Brummer SEO → Settings**. A shared job lock prevents the heartbeat and full audit from running at the same time.

### Heartbeat process

The heartbeat checks the rendered homepage and WordPress REST API. Every fourth heartbeat also samples robots.txt and one standard XML sitemap endpoint. It records response codes, response duration, byte count, and a SHA-256 homepage fingerprint. A fingerprint change is inventory evidence and is not automatically treated as an SEO failure.

A failed homepage or REST probe creates a critical event and requests a priority full audit. No post, product, theme file, plugin file, URL, or publishing status is changed.

### Local learning process

After each bounded full audit, the engine learns only from locally recorded evidence:

- signal status streaks;
- red, yellow, green, and manual occurrences;
- regressions from green;
- recoveries to green;
- recurring priority weights;
- verified-score trend, average, best, and worst values;
- common content-audit gaps from recent stored audit results.

This is transparent statistical memory, not remote model training. It does not scrape competing sites or import copyrighted material.

### Search Console pacing

The hourly full cycle reuses a recent Search Console evidence snapshot for six hours by default. This reduces unnecessary API calls while still keeping local and availability checks hourly. The administrator can set a one-to-24-hour interval and can force a fresh Search Console run from its tab.

### Update monitoring

The engine periodically refreshes WordPress plugin and theme update metadata and reports pending counts plus existing WordPress auto-update coverage. It intentionally does not install updates. WordPress's own per-plugin and per-theme auto-update settings remain authoritative.

### Reliable 24/7 execution

WP-Cron is request-driven. For reliable all-day scheduling, add the host cron command shown in the **24/7 Engine** tab. The suggested server cron wakes WordPress every five minutes while the plugin still enforces its configured 15-minute heartbeat and hourly deep-cycle intervals.

## Safety boundaries

Version 1.5.0 does not:

- auto-publish or change post status;
- rewrite existing stored content;
- rewrite its own PHP files;
- install plugin, theme, or core updates blindly;
- scrape competing websites or social platforms;
- claim guaranteed rankings or guaranteed human traffic;
- bypass the existing compatibility ownership boundaries.

The two existing optional final-output repairs remain unchanged: deduplicated Amazon Associate identification and strict quarantine of syntactically invalid JSON-LD.
