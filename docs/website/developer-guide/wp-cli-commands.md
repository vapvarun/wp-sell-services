# WP-CLI Commands

WP Sell Services ships a `wpss` command namespace for site operators and
developers. Run `wp wpss` to list everything, or add `--help` to any command for
its full options.

```
wp wpss demo         Manage demo services
wp wpss preflight    Run release-readiness preflight checks
wp wpss scale        Seed, benchmark and teardown a production-shape dataset
wp wpss service      Manage services (alias of `demo` -- same subcommands)
wp wpss test:flow    Run end-to-end data flow tests
wp wpss validate     Validate models and schema
```

All commands respect the current site in multisite (`--url=`).

## Demo content

`wp wpss demo` and `wp wpss service` are the **same command** registered under
two names, so every subcommand below works with either prefix.

```bash
wp wpss demo create --count=20 --featured=5   # seed 20 services, 5 featured
wp wpss demo marketplace                      # seed a FULL demo marketplace
wp wpss demo list                             # list services with stats
wp wpss demo stats                            # marketplace statistics summary
wp wpss demo regenerate-meta                  # rebuild computed service meta
wp wpss demo delete --yes                     # remove all demo/test content
```

**`create` and `marketplace` are not the same thing.** `create` seeds service
listings only. `marketplace` builds a working marketplace: multiple vendors with
profiles and portfolios, buyers, orders across the whole lifecycle, reviews,
buyer requests with proposals, and conversations. Use `marketplace` for staging
sites, theme testing, and client demos; use `create` when you just need catalog
volume.

Demo content is flagged internally, so `demo delete` never touches real customer
data. `delete` prompts for confirmation -- pass `--yes` in scripts.

## Health checks

```bash
wp wpss preflight    # verify database tables, settings defaults, page mappings
wp wpss validate     # validate marketplace data integrity
```

Run `preflight` after installation, after a migration, and before a release.
Every check prints PASS or FAIL with the reason.

## Testing and gap detection

```bash
wp wpss test:flow      # end-to-end data flow tests
```

> Earlier documentation listed `wp wpss test run`, `test tables`, `test gaps`,
> `test seed` and `test clean`. **No bare `wpss test` command is registered** --
> those examples fail with "not a registered subcommand". The only command in
> this group is `test:flow`. For seeding and cleanup use `wp wpss demo`, and for
> release checks `wp wpss preflight`.

`test:flow` walks a complete order lifecycle through the data layer and reports
where a flow breaks. It is the fastest way to confirm a fresh install actually
works end to end.

## Scale benchmarking

For verifying the marketplace holds up at production shape -- 10k vendors with
orders and wallet transactions.

```bash
wp wpss scale seed                    # seed a production-shape dataset
wp wpss scale bench                   # time every hot-path query vs its budget
wp wpss scale bench --seed --teardown # seed, bench, teardown in one shot (CI gate)
wp wpss scale teardown --yes          # remove all benchmark data
```

Every seeded row carries a sentinel (a `description` / `vendor_notes` prefix plus
a high user-id offset), so `teardown` removes exactly what `seed` created and
never touches real marketplace data.

`bench` measures each hot-path query against a per-query budget, so it fails
loudly when a change makes a list view unusable at scale, rather than leaving you
to discover it on a customer's site.

## Related

- [REST API Overview](rest-api-overview.md)
- [Action Scheduler Integration](action-scheduler.md)
- [Building Custom Integrations](custom-integrations.md)
