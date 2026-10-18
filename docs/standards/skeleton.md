# Plugin skeleton: where does X go

One answer per question. If you cannot find the answer here, add the row in the same PR.

Direction: WP Sell Services is the commerce core for selling services, built so that other product types
(physical goods later) and third-party integrations plug in without a core edit. See
`integration-seams.md` for how a provider is added.

## PHP classes (`src/`, PSR-4, namespace `WPSellServices\<Dir>`)

| I am adding... | It goes in | Rule |
|---|---|---|
| A table, query, CRUD | `Database/Repositories/` (rows) or `Models/` (one record) | New SQL goes here. About 90 existing `$wpdb` uses still sit in `Services/`, `API/` and `functions/`; they move to a repository when their file is next changed, never copied. |
| Business logic (orders, earnings, disputes) | `Services/` | No HTML, no `$_POST`. |
| A REST route | `API/<Thing>Controller.php` | Extends the base controller, has a `permission_callback`. |
| An admin screen | `Admin/` (page) + `assets/css/admin-*.css` + `assets/js/admin-*.js` | Same slug on all three. |
| A frontend screen | `Frontend/` + `templates/` | Markup in a template, never in a class. |
| A third-party integration | `Integrations/<Name>/` (Free) or Pro `src/Integrations/<Name>/` | One class extending the seam base; registered through the seam's filter. No core edit. |
| A gutenberg block | `Blocks/<name>/` | |
| A WP-CLI command | `CLI/` | |
| An interface a provider implements | `Integrations/Contracts/` | |

## Global functions (`src/functions/<domain>.php`, always `wpss_` prefix)

A helper goes in the file for its domain. Never create `misc`, `utils` or `helpers`.

| File | Holds |
|---|---|
| `core.php` | platform name, plugin instance, logging, generic sanitizers, admin/enqueue/form helpers, `wpss_validate_registry()` |
| `security.php` | secrets, app tokens, login lockout, client IP |
| `settings.php` | option readers |
| `money.php` | formatting, currency registry, minor units, tax, order limits |
| `ledger.php` | ledger rows, refunds, commission base, wallet balance |
| `billing.php`, `payments.php` | invoices, billing fields, gateway lookups |
| `orders.php` | order lookup, URLs, snapshots |
| `order-status.php` | status and payment labels, groups, priority |
| `order-queries.php` | order lists, counts, revenue aggregates |
| `requirements.php` | service requirement fields and answers |
| `services.php` | service lookup, status, rating, limits, publishing |
| `packages.php` | packages, add-ons, express delivery, line pricing |
| `catalog.php` | galleries, sorting, category terms, services grid |
| `cart.php` | the member cart |
| `demo.php` | demo content |
| `vendors.php` | vendor lookup and standing |
| `files.php` | uploads, storage provider, file directories |
| `order-files.php` | order files: store, locate, stream, legacy migration |
| `request-files.php` | buyer-request attachments and their guards |
| `notifications.php`, `moderation.php` | notices, reports |
| `templates.php`, `urls.php`, `rest.php` | rendering, routing, REST helpers |
| `time.php` | the only place that formats or converts time |

A file stays under about 1,000 lines. When one outgrows that, split it by sub-domain and add the row here.

Logic that needs state, or is more than a few helpers, is a class in `Services/`.

## CSS (`assets/css/`)

| Layer | File | Rule |
|---|---|---|
| Tokens and shared primitives (button, table, badge, form field) | `design-system.css` | The only place a primitive's base is declared. `bin/check-ux-conformance.php` fails on a button copy and `tests/test-primitive-single-source.php` on a table copy. |
| One screen family | `<surface>.css` (`single-service`, `vendor-dashboard`, `unified-dashboard`, `messaging`, `order-view`...) | Only scoped deltas under the surface's wrapper class. |
| wp-admin | `admin.css`, `admin-<screen>.css` | Exempt from the primitive rule. |
| Block editor | `blocks-editor.css` | |

`-rtl.css` and `.min.css` are generated (`npm run rtl`, `node scripts/build-min.js`); never edit them.
Colours, spacing and radii are tokens (`var(--wpss-*)`), never raw values.

## JavaScript (`assets/js/`)

| I am adding... | It goes in |
|---|---|
| Behaviour for one screen | `<surface>.js`, same name as its stylesheet |
| A widget used by several screens | `components/<name>.js` |
| An admin screen | `admin-<screen>.js` |
| A third-party library | `vendor/` (never edited) |

No new inline `<script>`; enqueue a file or use `wp_add_inline_script`. `bin/check-ux-conformance.php` counts the existing ones (baselined) and fails if the count grows.

## Hooks

Prefix `wpss_`. A new extension point is documented at the call site and listed in
`docs/website/developer-guide/hooks-filters.md`. Provider registries follow `integration-seams.md`.
