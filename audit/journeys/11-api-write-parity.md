# Journey 11 - A change made through the API reads the same as one made in the screen

**Roles:** admin, seller (`wpss_vendor`), buyer, an API/mobile client acting as each
**Rail:** any
**Status:** written 2026-10-18; walk pending

The recurring shape behind these cards: a REST route, an AJAX handler and a dashboard screen each wrote the same data their own way, and the copies drifted. For every step below do it in the screen, then through the route, and compare the result.

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
