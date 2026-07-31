# REST API mapping — retired

**Do not build a client from this file. It has been replaced.**

## Where the real API reference lives

**[`docs/website/developer-guide/rest-api-controllers.md`](docs/website/developer-guide/rest-api-controllers.md)** — the routes that actually exist, verified against the running plugin.

For conventions, auth, pagination and error codes, see
[`docs/website/developer-guide/rest-api-overview.md`](docs/website/developer-guide/rest-api-overview.md).

## Why this file was emptied

What used to be here was a **planning wishlist written in February 2026**, not a
description of the API. Every section was headed "Endpoints Needed" — but nothing
above the fold said so, and the file sat at the repository root, which is the
first place a client developer looks.

Of the 144 endpoints it listed, **100 did not exist**. Not "were renamed" — did
not exist, and several never had. Whole subsystems were fictional:

| It listed | Reality |
|---|---|
| `/admin/moderation/services/*` | the real prefix is `/moderation/*` |
| `/commissions/*` | the real prefix is `/commission-rules/*` (Pro) |
| `/me/orders`, `/me/notifications`, `/me/conversations`, `/me/disputes` | none exist |
| `/vendors/{id}/earnings/*`, `/vendors/{id}/withdrawals`, `/vendors/{id}/tier`, `/vendors/top-rated`, `/vendors/search` | none exist |
| `/orders/{id}/start-work`, `/cancel`, `/request-revision`, `/deliveries/*` | transitions all go through the single `/orders/{id}/{action}` route |
| `/search/suggestions`, `/search/popular`, `/services/{id}/related`, `/services/count` | none exist |
| `/milestones/{id}/reject` | the terminal action is `decline` |
| `/proposals/{id}/accept`, `/reject` | live under `/buyer-requests/{id}/proposals/{pid}/accept` and `/reject` |

Being excluded from the distributed zip did not help: the harm was to developers
reading the repository, which is exactly where a client team looks first.

The original content remains in git history (`git log --follow REST_API_MAPPING.md`)
as a record of what was once planned. It is not a specification of what is built.

## Two namespaces, so this does not catch you out

- `wpss/v1` — everything, including all ten Pro REST controllers. On a stock
  Free + Pro install this is the only namespace present (179 routes).
- `wpss-pro/v1` — four cart-adapter routes only, and only when that integration
  is active: `/surecart/sync-products` and `/surecart/orders` register from the
  SureCart adapter, `/fluentcart/products` and `/fluentcart/orders` from the
  FluentCart adapter. With neither plugin active the namespace does not exist
  at all.

Pro endpoints are **not** under `wpss-pro/v1`. Calling
`/wp-json/wpss-pro/v1/wallet/balance` returns 404.
