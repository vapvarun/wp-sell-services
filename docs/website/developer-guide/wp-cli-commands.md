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
wp wpss demo delete --yes                     # remove demo content only
wp wpss demo delete --all --yes               # remove EVERY service (always confirmed)
```

**`create` and `marketplace` are not the same thing.** `create` seeds service
listings only. `marketplace` builds a working marketplace: multiple vendors with
profiles and portfolios, buyers, orders across the whole lifecycle, reviews,
buyer requests with proposals, and conversations. Use `marketplace` for staging
sites, theme testing, and client demos; use `create` when you just need catalog
volume.

`demo delete` and **Settings > Advanced > Delete Demo Content** run the same
routine (`wpss_delete_demo_content()`): flagged services, buyer requests and
media, the demo vendor accounts with their profile rows, and any category the
demo created that is now empty. Whether demo content is installed is decided
from the content itself (`wpss_has_demo_content()`), so the wizard can import
again straight after a delete.

Demo content is flagged internally (`_wpss_demo_content`), so `demo delete` never
touches real customer data unless you pass `--all`, which also requires `--yes`
and still confirms the site-wide count. Every command that writes rows (`create`,
`marketplace`, `regenerate-meta`, `delete`, `scale seed`, `scale teardown`,
`test:flow`) prompts with the count -- pass `--yes` in scripts -- and refuses
outright when `wp_get_environment_type()` is `production` unless you pass
`--force`.
`marketplace` no longer changes the homepage; it prints the `wp option update`
commands to do that yourself.

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

## API payload contract

```bash
wp wpss api:shapes                        # audit every GET route
wp wpss api:shapes --verbose              # also list routes it could not reach
wp wpss api:shapes --route=conversations  # narrow to one area
wp wpss api:shapes --user=diego           # audit as a vendor, not an admin
```

Walks every registered GET route in both namespaces, fills parameterised routes
from real rows in the database, and inspects each response for the two
conventions a client depends on: dates are ISO-8601 with an offset, and any
object describing a person carries `deleted`. Exits non-zero on a breach, so it
can gate a build.

Run it after adding or changing an endpoint. It exists because the same class of
inconsistency was reported three times, and twice the fix was verified against a
hand-picked sample of endpoints and reported as if it covered the API.

Read the **"Not audited"** count as part of the result. Those are routes with no
matching row on the current dataset, or not readable as the chosen user -- an
unaudited route is exactly where the last gaps hid, so the command names them
rather than passing over them silently. Seeding the missing data, or re-running
with `--user`, shrinks the list.

## Repairs

```bash
wp wpss utc-migrate --dry-run   # rows per column and three "old -> new" samples
wp wpss utc-migrate             # convert now instead of waiting for the background job
```

1.8.0 stores every datetime in UTC. On upgrade, rows written before it are
converted once in the background (Action Scheduler hook
`wpss_utc_migrate_batch`). This command runs the same conversion in the
foreground. A site on UTC whose database server is also on UTC has nothing to
convert. Rows are converted once only, so running it again changes nothing.

`updated_at` is decided row by row, because two things wrote it before 1.8.0:
PHP (site time) when a writer set it, MySQL (the database server's clock) when
a writer left it out. A value stamped in the same second as another date on the
row (created, paid, started, completed) is treated as site time. Otherwise it is
treated as the database server's clock, unless that reading would put the update
before the row was created. On a site whose WordPress time zone and database
time zone match, both readings are identical. Always read the `--dry-run`
output first on a site where they differ: it lists `updated_at` separately for
each table and prints nothing for a value that would not change.

The conversion is safe to interrupt. Each batch and its progress marker are
saved together, so a batch that dies is redone from its start, and only one
batch runs at a time. If the background job stalls, the next visit to wp-admin
by an administrator queues it again; you can also just run the command.

```bash
wp wpss repair:stripe-tax           # dry run: lists what would change, writes nothing
wp wpss repair:stripe-tax --apply   # writes the changes the dry run listed
```

Before 1.8.0, when Stripe's payment webhook reached the site before the buyer's
browser, the order was recorded with tax counted twice: a $23.60 charge could be
stored as a $27.85 order, and the vendor's earning was sized on the inflated
amount. This affects only sites that charge tax and use Stripe.

The command reads each taxed Stripe order's payment from Stripe once and changes
only orders with that exact shape: the stored subtotal equals what Stripe
captured and the total is higher. It sets the subtotal, tax and total back to
what was charged and re-splits the fee and earning at the order's own
commission rate. If the vendor was already credited, it adds one **Order
Correction** row to their earnings for the difference. Stripe Connect orders
get the order row only, because Stripe paid the vendor from the real charge.

Any other mismatch, and any affected order with a refund, is listed for review
and left unchanged. Run the dry run first and read both tables. Running
`--apply` twice changes nothing the second time. Every corrected order is
written to the audit log as `order.repair`.

## Scale benchmarking

For verifying the marketplace holds up at production shape -- 10k vendors with
orders and wallet transactions.

```bash
wp wpss scale seed                    # seed a production-shape dataset
wp wpss scale bench                   # time every hot-path query vs its budget
wp wpss scale bench --seed --teardown --yes # seed, bench, teardown on a disposable CI site
wp wpss scale teardown                # remove all benchmark data (count confirmed first)
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
