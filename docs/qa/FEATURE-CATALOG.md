# Feature catalog

Every area a member or the site owner can reach, the journey that walks it, and the guards that protect it. QA works from this table: an area with no journey is a gap, and a gap is a task, not a pass.

Counts from the 2026-10-18 browser walk, Free and Pro active. Guards are scripts in `tests/` run with `wp eval-file`. Pro rows are marked.

## Admin: Sell Services menu (17 items)

| Area | Journey | Guards | Gap |
|---|---|---|---|
| Dashboard, All Services, All Requests, Categories, Tags | 12 | `test-menu-no-dead-ends`, `test-render-matrix` | |
| Moderation (services, vendors) | 13 | `test-moderation-state-contract` | |
| Review Moderation | 13, 14 | `test-notification-matrix` | |
| Disputes | 02, 13 | `test-dispute-state-machine`, `test-dispute-closes-on-rail-refund-and-cancel` | |
| Vendors (approve, suspend, hold) | 13 | `test-account-standing`, `test-ajax-standing-gate` | |
| Orders (status, hold and resume, refund) | 13 | `test-order-action-gates`, `test-refund-cascade-contract`, `test-hold-and-revision-clock`, `test-refund-cart-sibling-contract`, `test-gateway-refund-seam`, `test-refund-dedupe-contract` | Refund waiting for a gig (notice and assign); platform-paid order shows the refund pointer; live gateway echo (money, held) |
| Withdrawals | 10 | `test-auto-withdrawal-schedule`, `test-payout-integrity` (Pro) | Paid email (money, held) |
| Analytics (Pro) | 15 | `test-pro-perf-and-options` (Pro) | admin at 390 |
| Audit Log | 13 | `test-audit-log-coverage`, `test-audit-log-object-filter` | |
| My Notifications | 11 | `test-notification-types-registry`, `test-notification-center-retention`, `test-notification-read-at` | |
| License (Pro) | 12 | `test-license-gate-and-storage` | |
| Member Reports | 13 | `test-report-link-contract` | |

## Admin: Settings (12 tabs)

| Tab | Journey | Guards | Gap |
|---|---|---|---|
| General, Pages, Branding, Integrations, System | 12 | `test-settings-heading-outline`, `test-settings-card-title-case`, `bin/settings-contract.py` | |
| Checkout (account at checkout, billing fields) | 06, 12 | `test-billing-prefill-contract`, `test-guest-checkout-contract` | |
| Payment Gateways | 06, 12 | `test-payment-gateway-seam`, `test-gateway-isolation-contract` | Stripe and PayPal live walk (money, held) |
| Commission & Tax | 12 | `test-checkout-tax-contract`, `test-multi-checkout-tax-label`, `test-money-rules-gateway-independent` | Tax cards (money, held) |
| Payouts | 10, 12 | `test-payout-method-registry` | schedule card (money, held) |
| Vendor Settings (limits, moderation, Review Moderation) | 05, 12 | `bin/docs-audit.py` (settings paths in docs) | |
| Orders & Disputes | 02, 12 | `test-requirements-clock-from-payment` | |
| Emails | 11, 12 | `test-notification-toggle-contract` | Mailpit read of each template |

## Member: vendor dashboard (14 sections)

| Section | Journey | Guards | Gap |
|---|---|---|---|
| My Services, create and edit wizard | 05 | `test-package-ids-on-save`, `test-addon-pricing-contract`, `test-form-state-and-feedback` | wizard image remove cross |
| Sales Orders, deliveries, revisions | 01, 02 | `test-order-write-integrity`, `test-accept-needs-delivery` | |
| Buyer Requests, Proposals | 01 | `test-proposal-count-contract`, `test-request-deadline-utc` | |
| Reviews | 14 | `test-review-status-rest`, `test-review-window-and-reply` | |
| Earnings & Payouts | 10, 15 | `test-ledger-balance-contract`, `test-negative-balance-contract` | |
| Portfolio | 15 | `test-portfolio-rest-service` | |
| Analytics | 15 | | |
| Messages | 14 | `test-conversation-states-contract` | |
| Notifications, Disputes, Favorites | 11, 02, 14 | `test-notification-list-style` | |
| Profile | 15 | `test-vendor-profile-contract` | |
| Milestones, extensions, tips | 16 | `test-milestone-revision-contract`, `test-milestone-parent-total-contract`, `test-express-delivery` | |

## Member: buyer dashboard (7 sections)

My Orders, Favorites, Buyer Requests, Messages, Notifications, Disputes, Profile. Journeys 01, 02, 11, 14. Guards: `test-dashboard-nav-and-sales-filter`, `test-dashboard-section-registry`.

## Public

| Page | Journey | Guards | Gap |
|---|---|---|---|
| `/services/`, single service, category | 04 | `test-service-sort-direction`, `test-gallery-lightbox`, `test-cart-pricing-contract` | block theme walk at 390 |
| `/vendors/`, vendor profile | 04 | `test-vendor-profile-contract` | |
| `/buyer-request/`, single request | 01 | | |
| Cart, checkout (standalone) | 06 | `test-cart-link-carrier`, `test-post-payment-redirect-contract` | sticky sidebar on BuddyX Pro |
| Cart, checkout (WooCommerce) | 07 | `test-cart-unavailable-contract` | |
| `/become-a-vendor/`, `/login/` | 05 | `test-registration-page-contract`, `test-login-page-contract`, `test-login-lockout` | |

## Pro features (all QA and docs for Pro live here, in Free)

Everything below is off by default except the licence screen and vendor analytics. Walk Pro on top of the Free core paths. Rail first: Pro owns the WooCommerce, EDD and FluentCart adapters, and an adapter loads only when `wpss_general[ecommerce_platform]` names its rail, so a step on another rail is `skipped`, never `fail`.

| Flow (owner's words) | Role and surface | Journey | Guards |
|---|---|---|---|
| "My licence is active and Pro features are on" | admin, Sell Services > License | 12 | `test-license-gate-and-storage` |
| "Sellers can see how they are doing" | vendor, `/dashboard/analytics/` | 15 | `test-pro-perf-and-options` |
| "I can see how the marketplace is doing" | admin, Sell Services > Analytics | 13 | same |
| "I take a different cut per seller or category" | admin, Settings > Commission & Tax | 12 | `test-rest-partial-update` |
| "Sellers get paid out automatically" | Stripe Connect, PayPal Payouts | 10 | `test-payout-integrity`, `test-connect-refund-observation` |
| "Sellers pay me a plan to sell" | vendor `/dashboard/subscription/`, admin Subscriptions | 15 | `test-seams-subscriptions-carrier` |
| "Deliveries go to my cloud storage" | admin, Settings > Integrations | none | `test-license-gate-and-storage` |
| "Buyers can pay with site credit" | buyer, checkout and wallet | none | |
| "The dashboard carries my brand" | admin, White label | 12 | |
| Razorpay | buyer, checkout | none | `test-razorpay-settle-path` |
| WooCommerce rail pay-order, fee-only lines | buyer | 07 | `test-fee-only-pay-order-contract`, `test-woo-line-money-contract` |
| "I refund a Woo, EDD or FluentCart order in that platform and it shows here" | admin, the platform's order screen, then Sell Services > Orders | none | `test-woo-line-money-contract`, `test-refund-dedupe-contract` |

Pro checks to add to every Pro walk:
- **Licence:** with a valid licence Pro features load; with it cleared they stop and the Free marketplace keeps working, no fatal. With Free deactivated, Pro shows an admin notice, not a fatal. Pro loads no second copy of a library Free ships.
- **Zero config:** activate Pro on a working Free site with no Pro setting touched. The Free path (`/services/`, a service, checkout) behaves as before and the vendor dashboard gains Analytics. Anything else appearing is a finding.
- **Money rails:** on the Woo rail a tip, paid extension and milestone phase each resolve to a real order-pay URL and charge the right amount. A Woo refund on the parent reverses its tip, extension and phase children. Switching rails never rewrites a past order. EDD and FluentCart are `skipped` unless installed and set as the rail.
- **Payouts and wallet:** Stripe Connect and PayPal payout screens load. The active wallet provider balance equals `wpss_get_ledger_balance()` for the same vendor; a configured but inactive provider falls back to `internal`. `WalletManager` exposes no way to move money.
- **Cloud storage:** settings save and the connection test passes; with a provider active a delivery upload reaches it, the local copy goes, and the download is a short-lived signed URL that refuses an unrelated member. A failed upload keeps the file on local disk.
- **Analytics:** every widget renders and CSV export writes a real file. Top Services excludes `service_id = 0`.
- **Display currency:** with rates set, the switcher converts prices carrying `data-wbcom-amount`; earnings, admin screens and emails stay in base currency; with rates unavailable the hint does not render.

## Cross-cutting (gate parameters, not journeys)

| Concern | Where | Guards |
|---|---|---|
| Modals | every flow that opens one | `test-modal-portal`, `bin/check-ux-conformance.php` |
| Buttons, stat cards, form controls | every screen | `test-primitive-single-source`, `test-layout-surface-css`, `test-design-token-integrity` |
| Dark mode | every frontend flow | `test-dark-mode-and-icon-hooks` |
| Focus and contrast | every screen | `test-focus-indicator-css` |
| Time zones | any date | `bin/check-time-formatter.php`, `test-request-deadline-utc` |
| REST | journey 08 | `test-money-route-gates`, `test-no-defaults-on-update-routes`, `tests/audit/rest-reachability.php` |
| Install and upgrade | journey 03 | `test-orders-schema-contract`, `wp wpss preflight` |
| Docs match code | every release | `bin/docs-audit.py`, `bin/generate-hook-reference.py --check` |

## Known gaps (no journey yet)

Wallet providers (TeraWallet, myCred), Razorpay, Stripe Connect and PayPal payouts, EDD and FluentCart rails (beta), vendor subscription plan purchase, seller levels, the blocks and shortcodes, recurring services (switched off). Hook coverage: 103 hooks have no contract test.

## Keeping this current

A new feature adds its row here and its journey in the same change. A fixed bug adds a check line to the journey that owns it and a guard row in `audit/escapes.json`. `wp eval-file bin/coverage-map.php check` fails when coverage drops below the baseline.
