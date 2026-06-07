# WP-CLI Commands

WP Sell Services ships a `wpss` command namespace for site operators and developers. Run any command with `--help` for full options.

## Demo data

```bash
wp wpss demo create --count=20 --featured=5   # seed demo services
wp wpss demo create                            # full model marketplace: vendors, buyers, orders, reviews, requests, conversations
wp wpss demo delete                            # remove all demo/test content
wp wpss demo list                              # list services with stats
wp wpss demo stats                             # marketplace statistics summary
wp wpss demo regenerate-meta                   # rebuild computed service meta
```

The full seeder builds a working marketplace: multiple vendors with profiles and portfolios, buyers, orders across the whole lifecycle, reviews, buyer requests with proposals, and conversations. Ideal for staging sites, theme testing, and client demos.

## Health checks

```bash
wp wpss preflight    # verify database tables, settings defaults, and page mappings
wp wpss validate     # validate marketplace data integrity
```

Run `preflight` after installation or migration. Every check prints PASS or FAIL with the reason.

## Service management

```bash
wp wpss service      # service management subcommands (list, inspect)
```

## Notes

- All commands respect the current site in multisite (`--url=`).
- Demo content is flagged internally so `demo delete` never touches real customer data.
