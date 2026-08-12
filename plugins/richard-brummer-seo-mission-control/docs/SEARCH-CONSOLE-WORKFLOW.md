# Search Console Workflow

Copyright © 2026 Richard Brummer.

## Connection states

The Search Console tab distinguishes four different states:

1. **Not connected:** one or more required credential components are missing.
2. **Credentials present:** property, service-account email, and private key are available, but Google access has not yet been tested.
3. **Connection tested:** Google returned property permission and sitemap information.
4. **Snapshot stored:** Search Analytics rows were returned and saved for reporting.

No report tables are displayed while a real snapshot is absent.

## Secure credential choices

Recommended:

```php
define( 'RBSMC_GSC_PROPERTY', 'sc-domain:example.com' );
define( 'RBSMC_GSC_KEY_FILE', '/secure/non-public/path/service-account.json' );
```

Supported alternatives are explicit `RBSMC_GSC_CLIENT_EMAIL` and `RBSMC_GSC_PRIVATE_KEY` constants, or a `RBSMC_GSC_SERVICE_ACCOUNT_JSON` constant. A non-public key file is easier to rotate and avoids storing a large secret directly in `wp-config.php`.

## Automated through the read-only API

- Property access and permission level.
- Submitted sitemap status.
- Query and page clicks, impressions, CTR, and average position.
- Current versus previous 28-day period comparisons.
- Positions 2–20.
- High-impression, low-CTR opportunities.
- Rising and falling queries/pages.
- Device, country, search type, and search appearance data.
- Branded/non-branded local grouping using configured terms.
- Possible query cannibalization based on multiple returned pages.
- Indexed-version URL Inspection when explicitly enabled.

## Manual-only checks

Some Search Console reports and account controls are not available through the public API. The plugin labels them manual rather than inventing a result, including Manual Actions, Security Issues, aggregate Page indexing reasons, Core Web Vitals issue groups, live URL testing, and interface-only reports or controls.

## Zero-row result

A successful authenticated request can legitimately return no query or page rows. The plugin reports that state separately and suggests checking:

- the exact property identifier;
- whether the property has search traffic in the selected final-data period;
- whether a country filter is too narrow;
- whether the service account was added to the same property.
