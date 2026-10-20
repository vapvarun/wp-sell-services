# QA handoff for WP Sell Services 1.9.1 (Free and Pro)

Written 2026-10-20. For the agents verifying the cards in **Ready for Testing**. A fix author never verifies their own work; the verdict goes on each card. The process is the same as `QA-HANDOFF-1.9.0.md` section 2; the differences for 1.9.1 are below.

## 1. What to test and where

| | |
|---|---|
| Branch | `1.9.1` in both repos. Test the branch, not `main` |
| Free HEAD | `4b2ef9f0` |
| Pro HEAD | `bf1a1f4` |
| Version under test | 1.9.1 (Free and Pro lockstep; Pro requires Free 1.9.1) |
| Site | http://wp-sell-services.local (dev DB is a playground; it has already been upgraded to 1.9.1) |
| Log in | `?autologin=<user>` only when logged out; admin is `?autologin=1` |
| Users | `wpss_vendor_sofia` (vendor), `wpss_buyer_ella`, `wpss_buyer_mia`, admin |
| Themes | Reign (default), BuddyX, BuddyX Pro, Twenty Twenty-Five; put Reign back when done |
| Rail | `wpss_general[ecommerce_platform]` is `auto` (WooCommerce active). Standalone-only checks need it set to `standalone`; restore to `auto` after |

Traps: do not run `wp eval-file` tests while someone is walking the browser (concurrent writes contaminate the walk); outbound HTTP from a web request can 502 locally, so gateway tests use a `php -S` side server; prefix scratch data `QA-RFT` and delete it; screenshots go to `app/qa-artifacts/`, never the site root. EDD is not installed locally.

## 2. Test an upgraded site, not only this one

Two fixes live in the upgrade itself, so test the path from 1.9.0:

1. Set the schema back and reload any page:
   `wp eval 'global $wpdb; $wpdb->query("ALTER TABLE {$wpdb->prefix}wpss_reviews MODIFY order_id bigint(20) unsigned NOT NULL"); update_option("wpss_db_version","1.9.0");'`
   then open the site once. Expected: `wpss_reviews.order_id` is nullable again and `wpss_db_version` reads `1.9.1`.
2. Insert an old-style imported review with `order_id = 0` (reviewer name set), upgrade as above. Expected: the row survives, `order_id` becomes NULL, and it shows the Imported review label.
3. Pro: a site holding a plaintext S3 / PayPal Payouts / push secret in its option encrypts it on the first load of 1.9.1 (value starts with `enc:`) and the settings screen still shows a masked field.

## 3. Cards in Ready for Testing

Each row: card id, fix commit, what to prove. "Test" is the repo test that fails on the old code and passes now; run it as a smoke check, not as your verdict. Run all of them with `wp eval-file tests/<file>` from the Free plugin directory.

### Reviews (five cards)

| Card | Fix | Test |
|---|---|---|
| `10421729953` Imported reviews | Free `0cc1b2f3`, `928f26c9`; Pro `223488f` | Free `test-imported-reviews.php`, Pro `test-review-import-route.php` |
| `10421649579` Updating deletes reviews migrated from Woo Sell Services | Free `da4bfed6`, `38a8e809` | `test-review-dedupe-guard.php` |
| `10422047565` Vendor profile uses its own review markup | Free `f692392d` | n/a (browser) |
| `10422086065` Page 2+ of "View all reviews" built in JS | Free `b7e19716` | `test-load-reviews-vendor.php` |
| (no card) Ratings read through one query | Free `f692392d` | `test-rating-single-source.php` |

Steps:

- **Import Reviews (admin, Pro):** Sell Services > Import Reviews. Add one review (vendor search, service select, name, 1-5 stars, text, optional date). Add the same again: refused as already imported. Download the CSV template, build a file with 2 good rows, 1 repeat of a good row, 1 row with rating 9. Choose Check file: expect Ready 2, Already imported 1, Needs fixing 1, with a plain reason per row, and nothing saved. Import: expect Imported 2 and the run button disappears. Check the file again: all already imported. Also check that a file over 500 rows is refused with a plain message. Check at 390: no horizontal scroll, preview table readable.
- **Who can use it:** only an admin. A vendor or buyer gets nothing (`POST /wpss/v1/reviews/import` answers 401 or 403; the menu item is absent). The menu item appears only with a valid license.
- **Display:** imported reviews show an **Imported review** label, no Verified Purchase badge and on the service page, the vendor profile and the vendor dashboard Reviews list (its service link goes to the service, not an order). The vendor rating, service rating and seller level move; no email goes out (check Mailpit).
- **Vendor profile** (`/vendors/?vendor=wpss_vendor_sofia`): reviews look like the service page (avatar, name, stars, date), with the service named on its own line under the header and no Helpful button.
- **Load more:** with more than 10 reviews for one vendor, click View all reviews: reviews 11 and up appear with the label and service line, once only (no duplicate rows), and the button disappears at the end. Repeat on a service page with more than 10 reviews: one request, Helpful buttons present on the new rows, button disappears at the end.
- **Old data:** a review that came from another marketplace (order 0 before the upgrade) is still there after the upgrade (see section 2).
- **Ratings agree:** vendor rating on the profile, the seller card block, `GET /wpss/v1/vendors/{id}` stats and the vendor dashboard match for the same vendor.

### Money and offline payments (six cards)

| Card | Fix | Test |
|---|---|---|
| `10421776346` Admin "Completed" on an unpaid offline milestone credits the seller's wallet | Free `2a2438f4` | `test-unpaid-suborder-no-credit.php` |
| `10421776732` Admin order screen shows 0.00 / 0% for milestone projects | Free `99ac695c` | `test-milestone-contract-totals.php` |
| `10421776409` Offline milestones and tips have no bank instructions or receipt upload | Free `99ac695c`, `03c6c6c2` | n/a (browser) |
| `10421776430` Checkout prints unfilled `{total}` `{currency}` and every offline method's bank details | Free `99ac695c` | n/a (browser) |
| `10421776535` Custom-price manual orders show the package name and price | Free `0412ecb2` | `test-manual-order-custom-price.php` |
| `10418526157` REST `/payments/offline/submit` drops package_id and payment_info | Pro `cc26f67` | Pro `test-offline-submit-delegates.php` |

Steps:

- **Unpaid sub-order:** admin sets an unpaid offline milestone, tip or extension order to Completed. Expected: the status changes if the owner allows it, but the seller's wallet and ledger get no credit and no money event fires. Then confirm a paid one still credits once.
- **Milestone totals:** admin order screen for a milestone project shows the contract total, paid so far and the platform cut, not 0.00 and 0%.
- **Offline milestone and tip pages:** the pay page shows the bank instructions and the receipt upload once (a single waiting notice, not two) for the buyer; the seller sees the waiting state. At 390 the tip and Extra Work cards have inset spacing, not edge to edge.
- **Checkout:** the offline method text shows real totals; only the chosen method's bank details are printed.
- **Custom price:** Create Order with a typed price (or total override). The order reads "Custom order", the invoice and emails show the typed amount and no package name or package price. Normal package orders are unchanged.
- **Offline REST route:** an app posting to the offline submit route gets an order at the chosen package price with its payment details saved; same result as the web checkout. (Standalone rail only; set the rail to `standalone`, then restore.)

### Emails and sign-up (two cards)

| Card | Fix | Test |
|---|---|---|
| `10421776492` Buyer email preferences do not say which emails always send | Free `0412ecb2` | `test-always-sent-emails.php` |
| `10418526208` REST `/auth/register` with role=vendor does not create a vendor | Free `a89b59f0` | `test-signup-vendor-intent.php` |

Steps:

- **Email preferences:** Dashboard > Profile > email preferences, as buyer and as vendor. The screen says that payment, proposal, milestone and extension emails are always sent because they carry an amount or a deadline, and those categories have no off switch. Turning off a category that does have a switch still stops that email (check Mailpit).
- **Vendor register:** `POST /wpss/v1/auth/register` with role=vendor as a logged-out client: in open mode the account becomes a vendor; in application mode it is pending; in closed mode it is refused and no account is created. The web sign-up form behaves the same way in each mode.

### Pro secrets, FluentCart, Woo, My Services (eight cards)

| Card | Fix | Test |
|---|---|---|
| `10418526128` Storage secrets plaintext and echoed | Pro `bfc6db1` | Pro `test-secrets-at-rest.php` |
| `10418526164` PayPal Payouts client_secret plaintext and rendered | Pro `bfc6db1` | same |
| `10418526204` FCM service-account JSON plaintext | Pro `bfc6db1` | same |
| `10418526176` Wallet withdrawal details returned as ciphertext | Pro `bfc6db1` | same |
| `10418526183` FluentCart rail charges 100x on zero-decimal currencies | Pro `bfc6db1` | n/a (needs FluentCart) |
| `10418526213` Woo provider: duplicate order from a doubled completion hook | Pro `6bfd2c7` | n/a (needs Woo rail) |
| `10422089720` WooCommerce My Services loads every service | Pro `4fd5262`, Free `b7e19716` | Free `test-wc-my-services-paged.php` |
| `10422089787` EDD My Services loads every download to count them | Pro `4fd5262` | none (EDD not installed) |

Steps:

- **Secrets:** Settings pages for S3, DigitalOcean Spaces, GCS, PayPal Payouts and push notifications show a masked field. View the page source: neither the secret nor its ciphertext appears. Save the form with the field left blank: the stored secret is kept. Save a new value: it is stored encrypted (`enc:` prefix in the option) and the integration still works. A withdrawal's payout details read as text to the admin, not as `enc:` ciphertext.
- **FluentCart (needs FluentCart):** a JPY order of 1000 is charged 1000 on the rail, not 100000; a USD order is unchanged; KWD or BHD use three decimals.
- **Woo duplicate order (Woo rail):** complete a paid Woo order; fire the completion hook twice (e.g. a second status save). Exactly one WPSS order exists for the line.
- **My Services, WooCommerce rail:** a vendor with 26 or more services sees 24 cards and a page 2 link; fewer than 24 shows no pagination. Check 1440 and 390.
- **My Services, EDD (needs EDD):** the Active Services number equals the vendor's published service downloads. This was lint and phpcs only; a real walk is needed.

### One UI card

| Card | Fix | Test |
|---|---|---|
| `10417579719` Missing left and right margins on the Extra Work pending card | Free `c623c989` | n/a (browser) |

Steps: an order with a pending extension, as buyer and as vendor. The card is inset at 1440, 1024 and 390, in light and dark, and in RTL.

## 4. Also changed, with no card

- **Ratings come from one query:** `ReviewRepository::rating_stats()` is the only average and count. Check that a vendor's rating on the card, profile, REST stats and seller levels all agree (see Reviews).
- **Vendor service limit** (Pro subscription plans) counts services in the database. A vendor at their plan limit is still blocked from publishing one more; a vendor under the limit is not.
- **Dead code removed (about 1,370 lines):** `FAQService`, three repositories and the admin-viewer data classes. Nothing should change; a fatal anywhere means something still used them. Watch `debug.log` through the whole walk.
- **Docs:** `docs/website/reviews-ratings/review-system.md` has an "Importing Reviews" section; check it reads true against the screen.

## 5. What has and has not been checked

Checked by the author: PHPStan, REST reachability (178 routes, none unguarded), contract audit (0 errors), docs audit, i18n gate, phpcs on changed files, and each repo test above on both old and new code. Browser: the Import Reviews flow at 1440 and 390, imported labels on the service page and vendor profile at 1440 and 390, page 2 load-more on the vendor profile and a service page at 1440.

Not checked, so please cover them:

- Dark mode and RTL on the Import Reviews screen and on review page 2.
- Page 2 load-more at 390; a Helpful vote on a page-2 row.
- The EDD and FluentCart rails (not installed locally); the Woo duplicate-order lock under two truly simultaneous requests.
- The standalone offline checkout and receipt upload, end to end.
- The admin order summary at 390 and some custom-order emails.
- The importer on a real 500-row file; the duplicate check is not race-proof (two admins importing the same file at the same moment could both add a row).
- The deployed upgrade from a real 1.9.0 zip (section 2 simulates it).

## 6. Known limits (not bugs)

- Imported reviews: 500 rows per file, split larger files by hand; there are no WP-CLI, WooCommerce or Fiverr importers.
- The 7-day offline auto-cancel default from the earlier decision was not applied; offline orders are not auto-cancelled while a receipt awaits confirmation.
- The gateway-secret card `10421889741` is parked in Not now by owner decision (store them as WooCommerce does).
- `wppqa_run_checks` reports no matching checks and many dangling guards for this plugin; it has never replayed `audit/escapes.json` here, so a green run of it is not evidence either way.

## 6b. Smoke walk

A Sonnet combo smoke walk against these commits was running when this was written. Its report lands in `.qa-results/last-smoke-pass.json`; read it before you start, and treat anything in its `failures` or `untested` lists as extra scope.

## 7. Verdicts

Per card: PASS (move to Done), BOUNCE (back to Bugs with the failing step, role, viewport and theme), or BLOCKED (say what you need). For every bounce add the check that would have caught it to the journey that owns the behaviour, tagged `card:<id>`, with a row in `audit/escapes.json`.
