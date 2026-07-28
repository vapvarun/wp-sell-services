# Documentation Coverage Matrix

Internal QA artifact -- **not published to customers**. It is the answer to
"is every shipped feature documented, for the right audience?"

- **Last verified:** 2026-07-29 against plugin 1.3.0 / Pro 1.3.0 (99 pages)
- **Docs root:** `wp-sell-services/docs/website/` (single source of truth; Pro tree retired)
- **Automated gate:** `python3 bin/docs-audit.py`

Audience key: **B** buyer, **V** vendor, **O** site owner/admin, **D** developer.

## Free -- marketplace

| Feature | Doc | Audience | Status |
|---------|-----|----------|--------|
| Introduction / what it is | `getting-started/intro.md` | B V O | OK |
| Installation & requirements | `getting-started/installation.md` | O | OK |
| Quick setup | `getting-started/initial-setup.md` | O | OK -- defaults corrected 1.3.0 |
| Free vs Pro matrix | `getting-started/free-vs-pro.md` | B V O | OK |
| Dashboard tour | `getting-started/dashboard-tour.md` | B V | OK |
| What's new | `getting-started/whats-new-1-3-0.md` | B V O | OK |
| Browse & purchase | `buyer-guide/browsing-and-purchasing.md` | B | OK |
| Choosing a package | `buyer-guide/choosing-the-right-package.md` | B | OK |
| Buyer dashboard | `buyer-guide/buyer-dashboard.md` | B | OK |
| Order tracking | `buyer-guide/buyer-order-tracking.md` | B | OK |
| Buyer best practices | `buyer-guide/buyer-tips-best-practices.md` | B | OK |
| Favorites | `buyer-guide/favorites-saved-services.md` | B | **Expanded** |
| Service wizard | `service-creation/service-wizard.md` | V | OK |
| Pricing packages | `service-creation/pricing-packages.md` | V | OK |
| Add-ons (free cap 3) | `service-creation/service-addons.md` | V | OK |
| Gallery & media (free cap 4) | `service-creation/service-media.md` | V | OK |
| Requirements & FAQs (free cap 5/5) | `service-creation/service-requirements-faqs.md` | V | OK |
| Publishing & moderation | `service-creation/publishing-moderation.md` | V O | OK |
| Edit / pause / delete | `service-creation/editing-pausing-services.md` | V | OK |
| Post a buyer request | `buyer-requests/posting-request.md` | B | OK |
| Submit proposals | `buyer-requests/submitting-proposals.md` | V | OK |
| Manage requests | `buyer-requests/managing-requests.md` | B | OK |
| Fixed vs milestone contracts | `buyer-requests/proposal-contracts-wpss.md` | B V | OK -- screenshots restored |
| Order lifecycle (11 statuses) | `order-management/order-lifecycle.md` | B V O | OK |
| Requirements collection | `order-management/requirements-collection.md` | B V | OK |
| Messaging & files | `order-management/order-messaging.md` | B V | OK |
| Deliveries & revisions | `order-management/deliveries-revisions.md` | B V | OK |
| Milestones | `order-management/milestones-wpss.md` | B V | OK -- timeline screenshot restored |
| Paid extensions | `order-management/extensions-wpss.md` | B V | OK -- screenshot restored |
| Tipping & deadline extensions | `order-management/tipping-extensions.md` | B V | OK |
| Order settings | `order-management/order-settings.md` | O | OK |
| Vendor registration | `vendor-system/becoming-a-vendor.md` | V O | OK |
| Vendor dashboard | `vendor-system/vendor-dashboard.md` | V | OK |
| Profile & portfolio | `vendor-system/vendor-profile-portfolio.md` | V | OK |
| Seller levels | `vendor-system/seller-levels.md` | V O | OK |
| Vacation mode | `vendor-system/vacation-mode.md` | V | OK |
| Vendor settings | `vendor-system/vendor-settings.md` | O | OK |
| Reviews & ratings | `reviews-ratings/review-system.md` | B V | OK |
| Reputation & moderation | `reviews-ratings/reputation-moderation.md` | O | OK |
| Open a dispute | `disputes-resolution/opening-a-dispute.md` | B | OK |
| Dispute process | `disputes-resolution/dispute-process.md` | B V | OK |
| Admin mediation | `disputes-resolution/admin-dispute-mediation.md` | O | OK |

## Free -- money

| Feature | Doc | Audience | Status |
|---------|-----|----------|--------|
| Standalone checkout | `payments-checkout/standalone-mode.md` | O | OK |
| Stripe (direct) | `payments-checkout/stripe-payments.md` | O | OK |
| PayPal + Offline | `payments-checkout/other-gateways.md` | O | OK -- nav `[PRO]` removed (only Razorpay is Pro) |
| Currency & tax | `payments-checkout/currency-tax-config.md` | O | OK |
| Commission (global + per-vendor) | `earnings-wallet/commission-system.md` | O | OK -- per-vendor confirmed free |
| Paying your vendors (all rails) | `earnings-wallet/vendor-payouts.md` | O | **New 1.3.0** |
| Earnings dashboard | `earnings-wallet/earnings-dashboard.md` | V | OK -- clearance default corrected |
| Withdrawals | `earnings-wallet/withdrawals.md` | V O | OK |
| Scheduled auto-withdrawals | `earnings-wallet/automated-payouts.md` | O | OK -- **relabelled free** |
| Withdrawal approvals | `admin-tools/withdrawal-approvals.md` | O | OK |

## Free -- platform, display, admin

| Feature | Doc | Audience | Status |
|---------|-----|----------|--------|
| 19 shortcodes + attributes | `marketplace-display/shortcodes-reference.md` | O D | **Rewritten -- tags + attributes now listed** |
| 6 blocks + attributes | `marketplace-display/gutenberg-blocks.md` | O D | OK -- attribute reference added |
| Search & filters | `marketplace-display/search-filters.md` | O | OK |
| Template overrides + 137 template hooks | `marketplace-display/template-overrides.md` | D | **Rewritten -- all 47 frontend templates listed, 110 hooks + 24 filters documented** |
| SEO & schema | `marketplace-display/seo-schema.md` | O | OK |
| Email types (23 switchable) | `notifications-emails/email-types.md` | O | OK -- count corrected |
| Email configuration | `notifications-emails/email-configuration.md` | O | OK |
| In-app notifications | `notifications-emails/in-app-notifications.md` | B V | OK |
| Realtime (Pusher/Soketi) | `notifications-emails/realtime-updates.md` | O D | OK |
| Service moderation queue | `admin-tools/service-moderation.md` | O | OK |
| Vendor management | `admin-tools/vendor-management.md` | O | OK |
| Manual order creation | `admin-tools/manual-orders.md` | O | OK |
| Guided admin tour | `admin-tools/guided-tour.md` | O | OK |
| General settings | `platform-settings/general-settings.md` | O | OK |
| Pages setup (4 pages) | `platform-settings/pages-setup.md` | O | OK |
| Advanced settings | `platform-settings/advanced-settings.md` | O | OK |
| FAQ & troubleshooting (owner/admin) | `faq/faq-troubleshooting.md` | B V O | OK -- commission answer corrected |
| Buyer FAQ | `buyer-guide/buyer-faq.md` | B | **New** |
| Launch checklist (install -> live) | `getting-started/launch-checklist.md` | O | **New** |

## Free -- developer

| Feature | Doc | Audience | Status |
|---------|-----|----------|--------|
| REST overview (auth, errors, paging) | `developer-guide/rest-api-overview.md` | D | **78 error codes documented by status; 3 fabricated codes removed** |
| 23 free + 10 Pro controllers | `developer-guide/rest-api-controllers.md` | D | **Rewritten from source** |
| Hooks & filters (behavioural) | `developer-guide/hooks-filters.md` | D | **Phantom hooks removed; signatures corrected; template hooks split out** |
| Custom integrations | `developer-guide/custom-integrations.md` | D | OK |
| Theme integration | `developer-guide/theme-integration.md` | D | OK |
| Email customization | `developer-guide/email-customization.md` | D | OK |
| Action Scheduler (17 jobs) | `developer-guide/action-scheduler.md` | D | OK -- `wpss_cleanup_review_votes` added |
| WP-CLI (7 commands) | `developer-guide/wp-cli-commands.md` | O D | **Rewritten -- `demo marketplace`, `test`, `test:flow`, `scale` added** |
| Pro extension seams | `developer-guide/pro-extension-points.md` | D | **New -- ported from Pro tree** |
| Database schema (25 tables) | `developer-guide/database-schema.md` | D | **New** |
| Capabilities & roles | `developer-guide/capabilities.md` | D | **New** |
| Abilities API (15 abilities) | `developer-guide/abilities-api.md` | D | **New** |

## Pro

Every Pro feature is documented **in the free tree**, marked `[PRO]`.

| Pro module (source) | Doc | Audience | Status |
|---------------------|-----|----------|--------|
| WooCommerce adapter | `payments-checkout/woocommerce-checkout.md` | O | OK -- nav now marked `[PRO]` |
| EDD / FluentCart / SureCart | `payments-checkout/alternative-platforms.md` | O | OK |
| Razorpay gateway | `payments-checkout/other-gateways.md` (Razorpay section) | O | OK |
| Stripe Connect | `payments-checkout/stripe-connect.md` | O V | **Expanded -- fee model + account states** |
| `Currency/` display currency | `payments-checkout/display-currency.md` | B O | **New -- was undocumented in free tree** |
| `TieredCommission/` | `earnings-wallet/tiered-commission.md` | O | **Expanded -- rule types + precedence** |
| `PayPalPayouts/` | `earnings-wallet/vendor-payouts.md` | O | OK |
| `Integrations/Wallets/` (4 providers) | `earnings-wallet/wallet-system.md` | O V | OK |
| `Services/LedgerExporter` | `earnings-wallet/ledger-csv-wpss.md` | V | OK |
| `VendorSubscriptions/` | `vendor-system/vendor-subscription-plans.md` | O V | **Expanded -- enforcement matrix** |
| `RecurringServices/` | `order-management/recurring-services.md` | O V | **Flagged: feature-gated off in 1.3.0** |
| `Storage/` (S3, GCS, DO) | `cloud-storage/cloud-overview.md`, `cloud-setup.md` | O | OK |
| `Analytics/` admin | `analytics-reporting/admin-analytics.md` | O | OK |
| `Analytics/` vendor | `analytics-reporting/vendor-analytics.md` | V | OK |
| `Analytics/DataExporter` | `analytics-reporting/data-export.md` | O | OK |
| `WhiteLabel/` | `platform-settings/white-label.md` | O | **Expanded -- all 6 fields + boundaries** |
| `License/Manager` | `getting-started/pro-license.md` | O | **Expanded -- corrected expiry behaviour** |
| `Features/WizardEnhancer` (raised caps) | `service-creation/*` free-vs-Pro tables | V | OK |
| 10 Pro REST controllers | `developer-guide/rest-api-controllers.md` | D | **New coverage** |
| Pro hooks / seams | `developer-guide/pro-extension-points.md`, `hooks-filters.md` | D | **New coverage** |

## Corrections that changed customer-facing claims

Where the docs asserted the opposite of what the code does. Each was verified in
source before the doc was changed.

| Claim in the docs | What the code does | Where |
|-------------------|--------------------|-------|
| (undocumented) Activation grants vendor capabilities to the **`author`** role | Every existing WordPress author silently becomes a vendor on activation | `capabilities.md` |
| Tips are commission-free, vendor keeps 100% | Tips are commissioned at the **regular rate** by default; `tip_commission_rate` can be set to `0` | 4 pages |
| An expired Pro license only stops updates | Expired/invalid license means **Pro features stop loading entirely** | 2 pages |
| Pro adds AI titles, templates, bulk upload, video upload, custom fields, scheduled publishing | **Deferred to a future release**, not enabled in 1.3.0 | `free-vs-pro.md`, `readme.txt` |
| Pro raises the video limit 1 -> 3 | Deferred with the above; still 1 | `free-vs-pro.md` |
| Clearance period defaults to 14 days | Defaults to **0** | 5 pages |
| Per-vendor commission is Pro | Free | `faq-troubleshooting.md` |
| Automated payouts are Pro | Scheduled auto-withdrawals are free | `automated-payouts.md` |
| Minimum withdrawal $50 | $25 | `initial-setup.md` |
| 3 required pages | 4 | `initial-setup.md` |
| A global "revision limit" setting | Revisions are per-package only | `initial-setup.md` |
| 11 / 25 / 27 email types | **23** switchable types | 4 places |
| Recurring services are available | Behind a **default-off** flag; UI hidden | 3 pages |

## Known gaps

Tracked, not silently dropped.

| Gap | Impact | Priority |
|-----|--------|----------|
| **No screenshots on Pro setup pages.** Blocked: Pro loads nothing without a valid license key, so its screens cannot be captured on a dev site | Pro setup is text-only | P1 |
| ~30 free pages still have no screenshot | Setup is harder to follow | P2 |
| 77 of 454 hooks remain undocumented (declared internal) | Low -- template and behavioural surfaces are both covered | P3 |
| REST overview lists generic endpoints; per-endpoint request/response examples are thin | Clients infer payload shapes | P3 |



| `MONEY-FLOW.md` / `SUB_ORDER_PATTERN.md` linked from some dev pages, not all | Minor | P3 |
| Nav ordering still puts Platform Settings / Admin Tools late; the new Launch Checklist partly compensates | Owner path is discoverable but not front-loaded | P3 |

### Closed since the first pass

Thin Pro stubs expanded (`stripe-connect`, `white-label`, `pro-license`,
`favorites-saved-services`); buyer FAQ written; owner launch checklist written;
UI label drift fixed and now gated.

## Regression gate

`bin/docs-audit.py` fails the build on any of:

1. `docs_config.json` not 1:1 with disk
2. Broken image reference
3. Broken internal `.md` link
4. A hook in a reference table that the source never fires
5. A `Settings > X` path naming a tab that does not exist
6. The Pro docs manifest publishing anything

All six pass as of 2026-07-29. Each check has been verified to fail on a
deliberately introduced defect, so a pass is meaningful rather than vacuous.
