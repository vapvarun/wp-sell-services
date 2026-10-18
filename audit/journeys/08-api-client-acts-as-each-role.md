# Journey 08 - An API client acts as each role

**Role:** app client (HTTP), any member
**Rail:** standalone, woo
**Status:** rewritten 2026-10-18; walk pending (see docs/qa/walks.json)

Guards cards 10154918753, 10154919636.

## Why this one matters

This is the only journey here that is **not** walked in a browser, and it must
be walked over **real HTTP**. That is the whole point of it.

`rest_do_request()` does not define `REST_REQUEST` and does not run
`rest_post_dispatch`. So under `wp eval-file`, WordPress's application-password
authenticator never engages and core's `_fields` filtering never applies.
Both of the security findings on card 10154918753 were **verified as safe from
WP-CLI and were not** — the token-minting hole answered 401 internally and 200
over HTTP. A pass from the command line is not evidence here.

## Preconditions

A member with a known account password, and `curl` or equivalent. Do not use
`wp eval` for any step.

## Steps

### 1. Sign in
`POST /wpss/v1/auth/login` with username + **account password**.

**Expect**
- `200`, a `token`.
- `expires` is an **ISO-8601 timestamp**, not `null`. Null means the server is
  enforcing nothing.

### 2. A token cannot mint tokens
`POST /wpss/v1/auth/login` again, passing the **token** from step 1 as the
`password`.

**Expect** `401 wpss_token_cannot_mint`.

A `200` here means whoever steals one token has an unlimited supply and revoking
the original changes nothing. This is the step that was reported passing and was
not.

### 3. The token still works for ordinary calls
`GET /wpss/v1/me` with `Authorization: Basic <token>`.

**Expect** `200`. Step 2 must block minting without breaking authentication.

### 4. Where am I signed in
`GET /wpss/v1/auth/sessions`.

**Expect** a list with `uuid`, `device`, `created`, `last_used`, `expires` and
`is_current` — with `is_current` true for exactly the session making the call.

Note this is **`/auth/sessions`**, not `/auth/devices`. The latter exists and
means something else entirely: push notification tokens. Revoking a push token
must never sign anybody out.

### 5. Revoke one device
`DELETE /wpss/v1/auth/sessions/{uuid}` for a session that is not the current one.

**Expect** `200`, and that session gone from step 4's list. Replay the same
call: `404`. As a **different member**, try the same uuid: `404` — a uuid alone
must not be enough to sign someone else out.

### 6. Expiry is real
Backdate a WPSS token past both limits (30 days idle, 90 days absolute), then
`GET /wpss/v1/me`.

**Expect** `401 wpss_token_expired`.

### 7. Expiry does not lock the member out — the harder half
With that **expired token still in the Authorization header**, call
`POST /wpss/v1/auth/login` with the correct account password.

**Expect** `200` and a fresh token.

WordPress 401s the *entire request* on a failed application password, so without
a carve-out this answers 401 and an app that attaches its stored token to every
request can never sign in again. That is a permanent lockout arriving 30 days
after release — worse than the vulnerability it came from.

Then repeat with a **wrong** body password: **expect `401`**. The dead token
must buy nothing.

### 8. The carve-out is narrow
With the expired token in the header, call `/me`, `/orders`, `/notifications`,
`/auth/sessions`, `/auth/logout`, `/auth/change-password`.

**Expect** `401 wpss_token_expired` on **every one**. Only `/auth/login`,
`/auth/register` and `/auth/forgot-password` are reachable with a dead token.

### 9. A hand-made application password is not ours to expire
Create an application password in the member's WordPress profile (name it
anything without the `WPSS` prefix), backdate it a year, and use it.

**Expect** `200`. We expire the sessions this plugin issued, not whatever script
the member built.

### 10. The payload contract
Run the committed scanner:

```
wp wpss api:shapes
```

**Expect** success, and read the "Not audited" count — those are routes it could
not reach on this dataset, and an unaudited route is where the last three
gaps hid. `--verbose` names them.

## Notes for whoever runs this

- Every step over HTTP. If a result surprises you, check you are not on
  `wp eval` before concluding anything.
- `_fields` is worth confirming here too:
  `GET /reviews?_fields=id,rating,review,created_at` should be roughly 80%
  smaller than the full payload — over HTTP. Internally it returns identical
  bytes and looks broken.


# Part 2 - a change through the API reads the same as one made in the screen (was journey 11)

## Steps

### 1. Moderate a review both ways (card:10399042982)
Approve, reject and unapprove a review in Reviews > Moderation, then with `PUT /reviews/{id}` `{status}`.

**Expect** the service star average and count on the service page change at once in both; the audit log has a row each time; changing to the same status again adds nothing.

### 2. Read a notification both ways (card:10403105565)
Bell > mark one, mark all (screen), then `POST /notifications/{id}/read` and `/read-all`.

**Expect** the badge count drops immediately each time and `read_at` is stamped.

### 3. Filter the audit log by one object (card:10402964352)
`GET /audit-log?object_id=N` and `?object_type=order&object_id=N`.

**Expect** only that object's rows, never the whole log.

### 4. Portfolio from the dashboard and the API (card:10403565741)
Dashboard > Portfolio: add an item with tags "logo, branding", edit it, clear the tags. Then the same with `POST/PUT /portfolio` sending only `{tags}`.

**Expect** tags are kept and shown on the card and in the edit form; a PUT with one field changes only that field; the item limit and featured limit apply on both; a second seller gets 403 on someone else's item. At 390px the modal fits without sideways scroll.

### 5. Pro: subscription plans and commission rules (cards 10408191220, 10407786815, 10407787331, 10407787480)
`PUT` one field at a time to a plan and to a commission rule.

**Expect** the other fields are untouched, `is_active` is in every response, and a category rule with no category is refused.
