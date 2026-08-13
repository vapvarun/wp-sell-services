# REST error codes

How to branch on WP Sell Services REST failures from a client.

Clients should branch on the **HTTP status** first and use the **code** only to
choose the message or the recovery step. The status tells you *what kind* of
failure it is; the code tells you *which one*.

Asserted by `wp wpss rest:contract`, which runs the table below against a live
site as anonymous, buyer, vendor and admin. Run it before shipping a client
release.

## Status meanings

| Status | Meaning | What the client should do |
|---|---|---|
| `401` | Not authenticated, or the token expired | Refresh the token and retry **once**. Never log the user out on a single 401. |
| `403` | Authenticated, but not allowed | Stop. Retrying will not help. Show why, using the code. |
| `404` | No such route, or no such resource | Stop. Do not cache "gone" for a feature flag - check the code first. |
| `409` | Conflict with current state | Refetch the resource and reconcile before retrying. |
| `501` | Feature disabled on this site | Hide the feature. Do not retry. |

The single most important rule: **a 401 and a 403 must never be interchangeable.**
A client that treats 403 as "refresh and retry" loops forever; one that treats
401 as "permission denied" logs people out when a token simply expired.

## Codes

### Authentication

| Code | Status | Meaning |
|---|---|---|
| `rest_not_logged_in` | 401 | No authenticated user. Every protected route answers this when anonymous. |

### Authorisation

| Code | Status | Meaning |
|---|---|---|
| `wpss_not_admin` | 403 | Logged in, but lacks `manage_options`. Moderation, audit log, analytics, and the Pro admin endpoints. |
| `wpss_not_vendor` | 403 | Logged in, but not a vendor. Vendor-only creates and earnings surfaces. |
| `wpss_not_owner` | 403 | Logged in, but does not own **this specific resource** - and is not an admin. Orders, reviews, portfolio items, disputes, media. |

These three are deliberately distinct. `wpss_not_owner` is about one resource;
`wpss_not_admin` and `wpss_not_vendor` are about the caller. Before 1.6.0 the
admin case also answered `wpss_not_owner`, so a client could not tell "this
isn't yours" from "you need admin" without reading the English message.

### Resources

| Code | Status | Meaning |
|---|---|---|
| `rest_no_route` | 404 | Unknown path. Also returned by WordPress core for a **known path with an unsupported method** - see the note below. |
| `wpss_order_not_found` | 404 | The order does not exist, or is not visible to this caller. |

### State

| Code | Status | Meaning |
|---|---|---|
| `wpss_order_not_payable` | 409 | The order is not in a payable state. Refetch it before retrying. |
| `wpss_milestone_locked` | 409 | An earlier milestone phase is not approved yet. Phases pay in lock-step. |
| `wpss_report_already_resolved` | 400 | The report has already been actioned by someone else. |
| `wpss_realtime_disabled` | 501 | Realtime is switched off on this site. Hide the feature. |
| `wpss_missing_service_requirement` | 403 | Checkout blocked: a required service requirement was not supplied (FluentCart rail). |

## Known deviation: wrong HTTP method

A known path called with an unsupported method returns **404 `rest_no_route`**,
not 405.

This is WordPress core's behaviour, not ours - `DELETE /wp/v2/posts` answers the
same. We match core deliberately rather than special-casing our namespace, so
the client can apply one rule to every WordPress API it talks to. If a client
needs to distinguish "wrong method" from "wrong path", check the route against
the schema before dispatching.

## Verifying

```bash
wp wpss rest:contract              # full table, one line per check
wp wpss rest:contract --porcelain  # failure count only, for CI
```

Exits non-zero on the first violated expectation. Add a row to
`RestContractCommand::expectations()` whenever a new permission gate ships.
