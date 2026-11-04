# Per-surface walk checklist

The release walk, one surface at a time. Each surface below is a key in [`smoke-surfaces.json`](smoke-surfaces.json), so a change to a mapped file re-walks that surface only. A file that maps to no surface (design-system CSS, `frontend.js`, `src/Core`, `src/Models`, `src/Database`, `src/Services`, `src/functions`) re-walks everything.

Run it with Playwright MCP, one browser, one surface at a time. Each row is a `browser_navigate` plus one `browser_evaluate` that returns a measured value. A screenshot is evidence for a human, not the assertion.

## How a row is run

| Step | Rule |
|---|---|
| Role | `?autologin=<login>` only when logged out; log out via `/wp-login.php?action=logout` first |
| Widths | 1280 and 390 for any row that renders UI |
| Overflow | `document.documentElement.scrollWidth <= innerWidth` |
| Console | `browser_console_messages` level error: a first-party error fails the row |
| Mode | combo (Free + Pro) first, then Free only; mark Pro-only rows `Pro` |
| Money surfaces | cart, checkout, earnings, analytics-and-ledger are walked last, after the rest is green |
| Untested | a row the walker could not run (denied action, missing fixture) is `UNTESTED` with the reason, never skipped silently |

Status per row: OPEN, FOUND (card), FIXED (commit), VERIFIED (by someone other than the author), ACCEPTED (reason + card).

## Surfaces

### cart, checkout (money, last)
| id | role | check | assertion | status |
|---|---|---|---|---|
| CART-1 | buyer | add a service with an add-on, open the cart | line total = package + add-on; tax row present when tax is on | OPEN |
| CART-2 | banned buyer | add to cart | refused with a visible notice, cart unchanged | OPEN |
| CHK-1 | buyer | standalone checkout, then Woo block checkout | order created with revisions, deadline, tax meta | OPEN |
| CHK-2 | buyer | pay twice (double click, replay) | one order, one ledger row | OPEN |

### publish, moderation, admin-service-list
| id | role | check | assertion | status |
|---|---|---|---|---|
| PUB-1 | vendor | create via wizard, then via dashboard, then via REST | the same limit refusal on all three | OPEN |
| PUB-2 | vendor | edit a live approved service | goes back to review when moderation is on | OPEN |
| PUB-3 | vendor | pause a service | listed state matches the label (see card: pause control) | OPEN |
| MOD-1 | admin | open the moderation queue with moderation on | pending services listed, approve and reject change status once | UNTESTED (disabled on the dev site) |
| ASL-1 | admin | service list table | pagination, status filter, no overflow at 390 | OPEN |

### dashboard-sections, vendor-doors, notifications
| id | role | check | assertion | status |
|---|---|---|---|---|
| DASH-1 | vendor | open every section (services, sales, earnings, portfolio, messages, profile, create, orders, requests, reviews) | HTTP 200, no PHP notice, no overflow at 390 | PASS 2026-10-23 at c8748e76, combo, Maya, 390 (one `evaluate`, 10 sections, 0 console errors); not yet verified by a second person |
| DASH-2 | vendor | delete a service that has a paid order | refused with a message, row stays | OPEN |
| DASH-3 | suspended vendor | open the dashboard and the portfolio | standing message shown, new-activity buttons absent | OPEN |
| BUY-1 | buyer | dashboard orders, requests, favorites, messages, notifications, disputes and the orders page at 390 | HTTP 200, no PHP notice, no overflow | PASS 2026-10-23, combo, Ella, 8 of 8 |
| NOT-1 | buyer | open notifications, mark one read | unread count drops by one, state survives reload | OPEN |

### order-view, order-titles-and-tax
| id | role | check | assertion | status |
|---|---|---|---|---|
| ORD-1 | buyer, vendor | open an order, a request order, a milestone and an extension | title is never empty or "No service linked" where a title exists | OPEN |
| ORD-2 | buyer | order with tax on top and tax included | totals and the VAT line agree with the order meta | OPEN |
| ORD-3 | buyer | open a dispute, add evidence after it is resolved | evidence refused | OPEN |

### owner-decisions, admin-screens
| id | role | check | assertion | status |
|---|---|---|---|---|
| OWN-1 | admin | resolve a report twice, assign a dispute to the same admin twice | one audit row, no second mail | OPEN |
| OWN-2 | admin | ban a member, then try to buy as them | purchase refused with the standing message | OPEN |
| ADM-1 | admin | open every admin screen (vendors, orders, disputes, withdrawals, review moderation, reports, audit log, settings, setup wizard, moderation, create order, service and request lists) | HTTP 200, no PHP notice, no overflow at 1280 | PASS 2026-10-23, combo, 14 of 14 (`upgrade` is registered only without Pro: Free-only pass) |
| ADM-2 | admin | audit log filter and pagination | filter narrows rows, page 2 loads | OPEN |

### rest-api, blocks, reviews-and-service-page
| id | role | check | assertion | status |
|---|---|---|---|---|
| API-1 | each role | the REST routes of journey 08 | 401 or 403 where the role must not act, tax present in the order payload | OPEN |
| BLK-1 | admin | insert each block in the editor, then view the page | block renders, no console error | OPEN |
| SVC-1 | visitor | home, vendors, buyer requests and cart at 390 | HTTP 200, no PHP notice, no overflow | PASS 2026-10-23, combo (cart is `/cart/` on this site; `/service-cart/` is 404). Single service page still OPEN |
| SVC-2 | visitor | a suspended seller's service | not listed, page says unavailable | OPEN |

### earnings, analytics-and-ledger (money, last)
| id | role | check | assertion | status |
|---|---|---|---|---|
| ERN-1 | vendor | earnings page, withdrawal request | balance = ledger sum, request creates one row | OPEN |
| ERN-2 | admin | approve the same withdrawal twice | second approve is a no-op | OPEN |
| ANL-1 | admin (Pro) | analytics and ledger export | totals equal the ledger, export downloads | OPEN |

## Findings register

Fixed in 1.9.1 (tests in `tests/`, guards in `qa-config.json`): service limit on every insert door, gallery saver, standing on new-activity doors, same-state repeat is a no-op, order title and tax shape, tip commission excludes tax, settle result checked, EDD and FluentCart start orders with revisions and deadline, evidence after resolution, dispute transition scoped to status, dead AJAX doors removed.

Open, filed in Basecamp Bugs for the next release: pause control, audit log listener migration, owner notification on new report or dispute (decision), disputed completed order re-fire (unverified), moderation cleanup. Decision card: REST create or update returns 400 after saving when a limit truncates.

## Not walked this cycle

The full journey re-walk, Free-only mode, dark mode, RTL and the theme matrix, EDD and FluentCart rails, a fresh install of each zip, the 1.9.0 to 1.9.1 upgrade path. Rows above stay OPEN until a real run changes them.
