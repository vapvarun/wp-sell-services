# QA handoff for WP Sell Services 1.9.0 (Free and Pro)

Written 2026-10-18. For the agents verifying the cards in **Ready for Testing**. A fix author never verifies their own work; the verdict goes on each card.

## 1. What to test and where

| | |
|---|---|
| Branch | `1.9.0` in both repos. Test the branch, not `main` |
| Free HEAD | `2b25e0c8` |
| Pro HEAD | `f3c58de` |
| Site | http://wp-sell-services.local (dev DB is a playground, already at 1.9.0) |
| Plugins | `wp-content/plugins/wp-sell-services` and `wp-sell-services-pro` |
| Log in | `?autologin=<user>` only when logged out. To switch: `/wp-login.php?action=logout`, confirm, then autologin |
| Users | `wpss_vendor_sofia` (vendor, id 7836), `wpss_buyer_ella`, `wpss_buyer_mia`, admin (`?autologin=1`) |
| Orders | 120 (sofia vendor, late), 114 (mia, pending approval), 116 (completed), 118 (disputed) |
| Service with packages | /service/i-will-build-a-custom-wordpress-plugin-to-spec-2/ (author sofia) |
| Themes | Reign, BuddyX, BuddyX Pro, Twenty Twenty-Five (`wp theme activate <slug>`); put Reign back when done |

Environment traps: outbound HTTP from a web request can 502 on this machine, so gateway tests use a `php -S` side server; do not run `wp eval-file` tests while someone else is walking the browser (concurrent writes contaminate the walk); restore any setting you change; scratch data is prefixed `QA-RFT`. Screenshots go to `app/qa-artifacts/`, never the site root.

## 2. How to verify (every card)

- Reproduce the original defect first on the 1.8.0 behaviour if you can (the card text is the acceptance bar). No failure seen means no PASS.
- Roles, in this order: Subscriber-role member or buyer, vendor, then admin last. A fix proven only as admin is not proven.
- Surfaces: every place the behaviour lives, not only the one the card names: frontend, wp-admin, REST, AJAX, emails, blocks, Free and Pro together.
- Viewports 1440, 1024 and 390. No horizontal page scroll at 390.
- Themes: Reign, BuddyX, BuddyX Pro and one Twenty-series theme, each in light and dark. The plugin follows the theme dark signal, not the OS setting.
- Direction: one RTL pass on any screen with changed CSS (`document.dir = rtl` or an RTL locale).
- Old data: also check content created before 1.9.0 (orders, notifications, audit rows), not only new records.
- Outgoing mail: read it in Mailpit (`wp eval 'echo ini_get("sendmail_path");'` shows the port). A log row saying Sent is not proof.
- Result per card: PASS (move to Done), BOUNCE (back to Bugs with the failing step, role, viewport and theme), or BLOCKED (say what is needed). Add the question that would have caught any miss.

## 3. Fix groups (cards in Ready for Testing)

Each card was matched to its fix by card id in commit messages, tests or code. "Proof so far" is what already exists; it is not a substitute for your walk.

### G1. Settings headings and contrast

- Fix: Free `234a5232`
- Regression guard: `tests/test-settings-heading-outline.php`
- Live walk so far: Not walked live.

Cards:

- `10412306674` [Settings] Emails tab has no tab-level heading (leads with a 13px card head)
- `10412156185` [Settings] Missing and skipped heading levels across Settings tabs
- `10412156063` [Settings] Payments tab uses an unstyled one-off heading block (structural divergence)
- `10412155855` [Settings] Page description fails WCAG AA contrast (4.24:1)
- `10412155759` [Settings] Sidebar group labels fail WCAG AA contrast (2.43:1)
- `10384448877` [WP Sell Services - UI/CSS Bug] - "Add-on available" indicator lacks proper styling and vertical alignment on the service page

Steps and surfaces:

- wp-admin > WP Sell Services > Settings, every tab (General, Payment Gateways, Emails, Payouts, Integrations, Advanced and the rest).
- Each tab: one h2 for the tab, card titles are headings, sub-sections h3, no skipped level (axe or the browser accessibility tree).
- Page description and sidebar group labels: contrast 4.5:1 or better in the default admin scheme and in a dark admin colour scheme.
- Add-on available indicator (frontend service page) is styled and visible.
- Viewports 1440 and 390; admin only, so no theme matrix.

### G2. Layout per surface

- Fix: Free `9269d78b`
- Regression guard: `tests/test-layout-surface-css.php`
- Live walk so far: Partly walked live: breadcrumb, modal header offset, report modal.

Cards:

- `10411948180` [Checkout] Sticky Order Summary hides "About the Seller" and "What happens next?" cards while scrolling
- `10398652904` [Report Modal] Report popup flashes visible for ~2s on service details page load
- `10384895523` [WP Sell Services - Backend/CSS Bug] - "Vacation Mode" form fields are misaligned and lack proper layout structure
- `10384766482` [WP Sell Services - Backend/CSS Bug] - Search input placeholder text is truncated on the Review Moderation page
- `10384469084` [WP Sell Services - UI/CSS Bug] - Breadcrumb navigation on Buyer Request page stacks vertically instead of inline
- `10384248570` [WP Sell Services - UI/CSS Bug] - "Submit Delivery" modal lacks top margin and collides with the site header
- `10380369422` [WP Sell Services - UI/CSS Bug] - "Or jump into a category:" section is not center-aligned in the empty state
- `10379783717` [WP Sell Services - UI/CSS Bug] - "Days" input field overflows container and "+ Add another phase" button collides with border

Steps and surfaces:

- Checkout: tall sticky Order Summary no longer hides About the Seller and What happens next (buyer, 1440 and 390).
- Service page: Report modal is not visible during load (reload 5 times, throttle CPU).
- Vendor dashboard Vacation Mode fields aligned; Requests search placeholder not truncated; buyer-request breadcrumb stays on one line.
- Order view as vendor: Submit Delivery modal clears the sticky header (also see Modals below).
- Home empty-state categories centred; service wizard Days input does not overflow.
- Themes: Reign, BuddyX, BuddyX Pro, Twenty Twenty-Five. Light and dark.

### G3. Forms, state and feedback

- Fix: Free `8f7723b8`, Pro `cc36be8`
- Regression guard: `tests/test-form-state-and-feedback.php` (Free), `tests/test-form-state-js.php` (Pro)
- Live walk so far: Not walked live.

Cards:

- `10411862427` [Profile Picture] Not Updating in Admin Profile
- `10408271874` [Push Notifications] Save Push Settings throws "ReferenceError: ajaxUrl is not defined" — settings never save
- `10407616413` [COMMISSION RULES] Active Checkbox Is Automatically Checked When Editing a Rule Saved as Inactive
- `10403821351` [Duplicate] “Edit Request” Headings on the Request Editing Page
- `10403187415` [Portfolio] No Option to Remove Uploaded Image
- `10399369371` [Orders] Admin status update saves with no success/error feedback — looks like nothing happened
- `10399020562` [Validation Message] Incorrect Message When Delivery Time Is Not Selected
- `10398958589` [Disputes] Decision note under Resolution is saved but never shown — looks like nothing happens
- `10398686846` [Add New Service] Add-on repeater creates duplicate element ids after remove + add
- `10398584633` [Buyer Requirements] Empty Question is not validated — vendor can continue and the requirement is silently dropped
- `10384898745` [WP Sell Services - Backend/Functional Bug] - "Save" and "Reset" commission buttons are broken on the Earnings tab due to redundant UI

Steps and surfaces:

- wp-admin order edit: change status, a success or error notice appears.
- Dispute resolution: the decision note is shown on the thread to buyer and vendor.
- Service wizard: missing delivery time shows the correct message; empty requirement question is refused; remove then add an add-on gives unique ids (inspect DOM).
- Request edit page: one Edit Request heading.
- Portfolio: an uploaded image can be removed (vendor dashboard > Portfolio) and the removal persists after reload.
- Earnings: Save and Reset commission buttons work (admin).
- Admin profile: changing the avatar shows the new one (Gravatar lookup by email).
- Pro: editing a commission rule keeps its Active state; Push settings save has no ajaxUrl error in the console.

### G4. Dark mode and icons

- Fix: Free `6422881b`
- Regression guard: `tests/test-dark-mode-and-icon-hooks.php`
- Live walk so far: Dark modal fields walked; the rest not.

Cards:

- `10411858243` Dark Mode UI Issues – Reign, BuddyX, and BuddyX Pro
- `10408125397` [Subscription] Unnecessary Horizontal Scrollbar Appears in the Cancel Subscription Popup
- `10399252183` [Notification] Next Button Text Becomes Invisible or Hard to Read on Hover
- `10384321636` [WP Sell Services - UI/CSS Bug] - "Remove from Favorites" 'X' icon renders as a blank white circle in Dark Mode until hovered
- `10384209485` [WP Sell Services - UI/CSS Bug] - Cancel attachment "X" icon is invisible/blank in the messaging interface

Steps and surfaces:

- Reign, BuddyX, BuddyX Pro in their dark mode (data-theme or data-bx-mode dark): Favorites remove X, message attachment cancel X, notification Next button hover, pager hover, native form chrome.
- Pro Cancel Subscription popup: no horizontal scrollbar (needs a vendor with an active subscription).
- Check both the dark toggle and OS dark preference (the plugin follows the theme signal, not the OS).

### G5. WooCommerce rail, cart link, subscriptions, carrier product

- Fix: Free `5c906b9b`, Pro `17e1111`
- Regression guard: `tests/test-woo-rail-guest-and-cart-link.php` (Free), `tests/test-seams-subscriptions-carrier.php` (Pro)
- Live walk so far: Not walked live.

Cards:

- `10407649116` [WooCommerce Integration] UI Issues in the Requirement Submission Section
- `10407576283` [Settings] "Site cart link" override is ignored when WooCommerce owns the checkout rail
- `10407576066` [Checkout] Logged-out "Add to cart" is discarded — guest lands on an empty WooCommerce checkout after login
- `10407726798` [WooCommerce] Carrier product direct URL returns 404 instead of redirecting to homepage
- `10408191922` [Vendor Subscriptions] Documentation mismatches: max_featured never enforced + subscribe() docblock claims ON DUPLICATE KEY UPDATE
- `10408190581` [Vendor Subscriptions] Service-limit message loses its Upgrade link on modern installs

Steps and surfaces:

- With the WooCommerce rail on: logged-out Add to cart returns the guest to the service page after login; the buyer chooses the package again (nothing is carried into a Woo cart for a guest).
- Settings: Site cart link override is honoured, and hidden where WooCommerce owns the checkout.
- Direct URL of a carrier product redirects to the homepage, not 404.
- Requirement submission panel on the Woo order page uses the host theme colours, light and dark.
- Vendor subscription plan max_featured limit is enforced when featuring; subscribe twice is idempotent; the service-limit message links to the subscription page.
- Run on Reign and BuddyX; Free and Pro active together.

### G6. Service moderation

- Fix: Free `22999ff9`
- Regression guard: `tests/test-service-moderation-admin-publish.php`
- Live walk so far: Not walked live.

Cards:

- `10398959570` [Service Moderation] admin.css hides the native Quick Edit Status control on Services AND Buyer Requests
- `10398958911` [Service Moderation] Editor "Publish" leaves a published service stuck in moderation state "pending" (hidden)
- `10398957993` [Service Moderation] Quick Edit overwrites moderation state and unpublishes approved services
- `10398686546` [Add New Service] "Skip to full editor" publishes a complete service instead of saving a draft

Steps and surfaces:

- As vendor: create a service, use Skip to full editor: it saves a draft, it does not publish.
- As admin: Publish from the editor on a pending service approves it; the service is not left in moderation state.
- Quick Edit on Services list (and the status control is visible): changing title or category does not unpublish an approved service.
- Bulk edit as admin and as a non-admin editor.

### G7. REST partial updates, portfolio, reviews, audit filter

- Fix: Free `1e2023fa`, `0fe6abf8`, `473166b9`; Pro `031e9e8`
- Regression guard: Free `test-audit-log-object-filter`, `test-no-defaults-on-update-routes`, `test-notification-read-at`, `test-portfolio-rest-service`, `test-review-status-rest`; Pro `test-rest-partial-update`
- Live walk so far: Not walked live; this is the group the PUT-each-field probe is still owed for.

Cards:

- `10408191220` [Vendor Subscriptions] PUT /subscription-plans/{id} rejects single-field (partial) updates with 400 wpss_missing_name
- `10407787480` [Commission Rules] Category rule can be saved with no conditions and then silently never matches
- `10407787331` [Commission Rules] REST response omits is_active — clients cannot read a rule's active state
- `10407786815` [Commission Rules] REST PUT /commission-rules/{id} rejects partial updates with 400 wpss_invalid_rule_type
- `10403565741` [Vendor Portfolio] Tags are never saved or returned on the dashboard portfolio flow (REST path ignores tags)
- `10403105565` [My Notifications] REST mark-as-read never sets read_at, unlike the AJAX path (inconsistent data)
- `10402964352` [REST/Audit Log] object_id filter silently ignored when object_type is omitted — returns the entire log
- `10399042982` [Reviews] REST status change leaves service/vendor ratings stale

Steps and surfaces:

- PUT /commission-rules/{id} and /subscription-plans/{id} with a single field: 200 and only that field changes (try every field of each).
- Commission rule response includes is_active; a category rule with no conditions is refused.
- Portfolio REST create and update store and return tags; compare with the dashboard flow.
- Notification mark-as-read over REST sets read_at (compare with the AJAX path).
- Review status change over REST updates the service and vendor rating immediately.
- Audit log REST: object_id alone filters correctly (no object_type needed).
- Auth: each route as subscriber, vendor, admin and logged out (expect 401 or 403 where not allowed).

### G8. Notifications and audit retention

- Fix: Free `7ab04f72`
- Regression guard: `tests/test-notification-center-retention.php`
- Live walk so far: Not walked live; 1000+ rows with EXPLAIN is still owed.

Cards:

- `10403105211` [My Notifications] Notification list pagination has no deterministic ORDER BY tie-breaker
- `10403104873` [My Notifications] Documented 90-day automatic cleanup of read notifications is not implemented
- `10403104468` [My Notifications] Documented unread / type filter controls are missing from the notification center
- `10403104232` [My Notifications] Documented unread-count badge on the notification bell is not implemented
- `10402964236` [Audit Log] Missing created_at index — full table scan + filesort on every list load and the daily retention scan
- `10402964139` [Audit Log] Retention cleanup compares UTC timestamps to server-local NOW(), purging rows early

Steps and surfaces:

- Notification center: unread and type filters work; the bell shows an unread badge that clears on read.
- Pagination is stable (no duplicate or missing rows across pages with identical timestamps).
- Retention: read notifications older than 90 days are removed by the daily job (run `wp cron event run`); unread are kept.
- Audit log list uses the created_at index (EXPLAIN) and retention uses UTC cutoffs.
- Seed 1000+ notifications for one user (WP-CLI) and confirm list, filter and badge stay fast.

### G9. Standing and access

- Fix: Free `a309ec49`
- Regression guard: `tests/test-account-standing.php`, `tests/test-walk-regressions.php`
- Live walk so far: Walked live: hold and resume over REST as vendor (400) and admin (200).

Cards:

- `10399370449` [Orders] Vendor Hold/Resume is advertised by REST but has no UI, and docs say On Hold is admin-only
- `10384354109` "Suspend Member" action fails to block the member from selling, bidding, or messaging
- `10379867128` [WP Sell Services - Backend/Permissions Bug] - Vendors see "Sell Services" in wp-admin but encounter a 403 Forbidden error

Steps and surfaces:

- Hold and Resume: admin only. Vendor gets 400 on REST and has no button. Admin can hold and resume from the order screen.
- A suspended seller can finish orders already paid, cannot publish a new service, cannot bid and cannot start a new conversation.
- Subscriber-role member sees no Sell Services menu in wp-admin; vendor and admin see what they should (needs manage_options for the plugin menu).
- Pro WCAccountProvider (My Account) is still owed.

### G10. Documentation

- Fix: Free `5abb8616`
- Regression guard: docs-audit and the docs lint
- Live walk so far: n/a

Cards:

- `10411923530` [Display Currency] Docs advertise [wpss_currency_switcher] picker, but settings UI and JS say there is deliberately no picker
- `10408376355` [Display Currency] Three documentation mismatches: settings tab location, order-confirmation surfaces, switcher picker
- `10407788033` [Commission Rules] Volume rule is all-time but docs describe a monthly threshold
- `10407727864` [WooCommerce] Documentation mismatches: order status mapping, refund status, package_id format, carrier redirect
- `10403937289` [Real-time] Docs point to the wrong Settings tab for the Real-time card (Advanced vs Integrations)
- `10403453238` [Emails Settings] Docs say 24 notification types; the screen has 29
- `10403452847` [Emails Settings] Docs claim the message-email skip setting has no screen; the screen has one (and the delay setting is undocumented)
- `10402964464` [Audit Log] EVENT_TYPES constant is dead and stale; its docblock is false
- `10399464603` [Docs] Supported-currency list wrong/self-contradictory; Pro display-currency feature overclaimed (location detection)
- `10399043338` [Reviews] reputation-moderation.md documents Review Moderation features that do not exist
- `10398960460` [Docs] Service Moderation doc says shop managers bypass moderation, but only administrators do

Steps and surfaces:

- Read each doc against the live screen: Settings tab names, display currency, Woo status map, message-email settings (29 types), review moderation, volume rule, supported currencies, service moderation (shop managers are not administrators).
- Every named tab, field and hook in the page exists as written.
- 10398960460: the service-moderation doc already says shop managers go through the queue; confirm and close.

### G11. Emails settings validation

- Fix: Free `55a12d14`
- Regression guard: `tests/test-settings-no-negative-numbers.php`
- Live walk so far: Not walked live.

Cards:

- `10403454753` [Emails Settings] Negative 'Hold message emails for' value is stored as a positive delay (absint) instead of clamped to 0

Steps and surfaces:

- Emails settings: a negative Hold message emails for value is refused or stored negative as entered, never turned positive.


Total cards in these groups: 69.

## 4. Structural changes in 1.9.0 to re-check on every surface

These shipped as enhancements, not cards. They touch many screens, so check them as surfaces, not as single bugs.

### Modals (one markup, one opener `window.wpssModal`)

Open and close every modal: order (deliver, dispute, extension, revision, tip, review, cancel), single service (order, contact, comparison, report), vendor profile contact, buyer request proposal, portfolio, confirm and prompt dialogs, Pro subscription cancel and Stripe disconnect. For each: no padding on the dialog, header, body and footer aligned, focus lands on the first field, Tab stays inside, Esc, backdrop, the cross and Cancel all close and release the page scroll lock, footer reachable at 390 (body scrolls, header and footer stay), dark mode text and field colours. Known gaps: comparison modal has no trigger on the site; BuddyX adds 15px side padding to order and contact modals on mobile (cosmetic).

### Stat cards and stats grid

Vendor dashboard Earnings, Analytics (Pro), Proposals, Reviews, Orders, Sales, Services; account page vendor stats; Pro admin analytics page. Same card geometry, no clipped figures or labels at 390 (two columns), equal row heights, highlight card keeps its accent on every theme, negative balance shows a red figure. wp-admin pages (Reports, Withdrawals, Vendors, Audit log, Service and Review moderation, admin dashboard) use the renamed `wpss-admin-stat-card` and must look as before. Still owed: dark mode, RTL, BuddyX and Twenty Twenty-Five.

### Buttons (BEM classes)

Primary, secondary, outline, ghost, danger, success, small, large, block and icon buttons on dashboards, checkout, order view, messages, wizard. Hover, focus and visited states under each theme. Vendor ghost lost its border and dashboard secondary now follows the design system; both are intended.

### Payment gateways seam

Settings > Payment Gateways: one card per gateway (Stripe, PayPal, Offline, Test, Razorpay in Pro) in the right order, each saves its own fields. Checkout renders only enabled gateways. REST create and confirm accept product_id and product_type; service_id still works; another product type is refused with `wpss_unsupported_product_type`. Money walks (Stripe test mode, PayPal sandbox) are NOT part of this pass.

### Payout methods registry

Vendor withdrawal form lists the same methods and fields as before; admin withdrawals page unchanged.

### Orders table product_type and product_id

Every existing order has product_type `service` and product_id equal to service_id (spot-check 20 including old ones). Order lists, filters, earnings history and exports unchanged. Earnings history prints the service title; a deleted service prints Deleted Service.

### Product-type registry

`wpss()->get_product_types()` holds `service`. A test plugin adding a type through `wpss_product_types` is listed; an invalid entry is dropped with a developer notice. No visible UI change.

### Time handling

Deadlines print their zone; site timezone is the default and `wpss_display_timezone` overrides it. Walk order, request and wallet screens with the site zone set to Asia/Kolkata, America/New_York, UTC and a UTC+5:30 offset. Known open card: dashboard wallet rows print the visitor zone (not fixed).

### Hold, suspension, menu

Covered in G9. Also check the plugin menu for each role.

### Upgrade

Install 1.8.0 on a populated copy, upgrade to 1.9.0 and check the order backfill, notifications index, settings tab names and that nothing is lost. Not yet done by the author.

## 5. Held back on purpose (not in Ready for Testing)

Money paths are tested last, after the Stripe test and PayPal sandbox keys are wired.

- `10403479953` Payouts: auto-withdrawal schedule: money path; tests last, after sandbox
- `10402904790` Withdrawals: Paid email: money path; Mailpit read and payout walk still owed
- `10403205865` Checkout tax line ignores wpss_checkout_tax_rate: money; test with tax and gateway together
- `10403205035` Empty Tax Label saves blank: money; same walk as the tax line

## 6. Round 2: fixes added after the first handoff

Same rules as sections 1-4: test branch `1.9.0`, every role, 1440 and 390, Reign, BuddyX, BuddyX Pro and Twenty Twenty-Five in light and dark, data created before 1.9.0.

| Card | What to check |
|---|---|
| `10415811081` Reason prompt and confirm | Any reason prompt (reject, cancel, dispute) and any confirm: one dialog, reason field focused, empty submit stays open with an inline alert, Tab trapped, Esc closes and returns focus. |
| `10415811064` Dead dispute statistics | Disputes admin list and dashboard load. |
| `10415811135` Wizard button | Review step says Publish Service on a draft and Update Service on a live or pending service. |
| `10415811119` Wallet and analytics dates | Ledger rows and Pro chart labels with the browser zone different from the site zone. |
| `10407787720` Seller-level rule (Pro) | A "new" level rule matches a new-tier vendor and not a buyer or deleted vendor. |
| `10415811101` Template overrides | Copy `order/tip-view.php` into `yourtheme/wp-sell-services/order/`, change a string, see it on the dashboard order view; same for the notifications list and Woo My Account pages. |
| `10415816659` Block-theme chrome | On Twenty Twenty-Five: archive, single, category, vendor profile, requests, checkout, cart: the theme's header and footer, no `header.php` deprecation in debug.log. |
| `10415816650` Leave-site prompt (Pro) | Create a PayPal batch and run the vendor migration, the page reloads without a browser prompt. |
| `10415816716` Service Grid block | Editor lists Rating, Popularity, Price; category names show `&` not `&amp;`; the front end sorts by each and keeps every service. |
| `10415811057` UtcMigration DST | Upgrade a site whose MySQL global zone is a named zone; package timestamps are right in winter and summer. |
| `10415811038` Request deadlines | Site west of UTC: legacy typed deadline shows the typed day on the edit form and request page; REST create with a date or an offset value stores UTC; a bad value returns 400. |
| `10415811050` Dispute on refund and cancel | A Stripe refund closes the dispute naming stripe; cancelling a disputed paid order closes it naming cancel. |
| `10380420186` Demo import | Wizard Import Demo Content on a clean site: services published, images, 5 sample reviews each; Delete Demo Content removes them. |
| `10403940320` Real-time docs | `realtime-updates.md` matches what a Pusher or Soketi test server produces: a toast, and the unread count on the dashboard menu. |

### Round 3: QA bounces of 2026-10-18, fixed

- **Notifications type filter** (10403104468). One registry, `wpss_notification_types()`, gives label and icon for every type written. The dropdown lists the member's own types. The model constants, the template icon map and the AJAX list's own query are gone. Pro's EDD and FluentCart account pages now render the same partial. Check: as a user with `order_late`, `refund_pending` and `order_started` rows, every type appears, selecting it returns only those rows, and no row shows the generic bell. Guard: `tests/test-notification-types-registry.php`.
- **Suspend Member** (10384354109). Dashboard publish, bulk publish, portfolio add and update, and the wizard draft and publish now refuse a suspended seller with the same message as REST. Publish also checks the plan limit and the publish bar. Pause and delete still work. Profile save stays open (billing and display name are not new work). Guard: `tests/test-ajax-standing-gate.php`.
- **Settings headings** (10412156185). Stripe, PayPal and Offline sub-headings are h3 under their card. The guard now renders the Payment Gateways tab on the standalone rail. Check on Standalone, not Woo.

## 7. Not fixed yet (still in Bugs)

Only the money cards remain: 4 held back above, plus `10415810962` (Stripe 1.7.x payments refused), `10415810929` (REST pay-order refusal after capture) and `10415811011` (cart refund hits sibling orders). They wait for the Stripe test and PayPal sandbox keys. Do not verify them.

## 8. Known failures that are not regressions

- `tests/test-cli-guards.php`, `tests/test-demo-delete-contract.php`: this site has demo content, the tests refuse or mis-scope on it.
- `tests/test-vendor-profile-contract.php`: display-name assertion on the dev data.
