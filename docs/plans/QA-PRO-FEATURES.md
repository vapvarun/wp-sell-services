# QA Checklist: Pro Features

Manual QA checklists for all 6 WP Sell Services Pro features. Mark `[x]` when verified.

---

## Feature 1: Tiered Commission

### Settings (Payments > Commission Rules)

| Step | Expected | Status |
|------|----------|--------|
| Navigate to Payments tab | Commission Rules section visible with Pro badge | [ ] |
| Add Category rule: name, rate 15%, category ID, priority 5 | Rule saved, shown in table | [ ] |
| Add Volume rule: min earnings $1000, rate 8% | Rule saved | [ ] |
| Add Seller Level rule: level "Top Rated", rate 5% | Rule saved | [ ] |
| Edit rule -- click Edit, change rate, save | Updated in table | [ ] |
| Delete rule -- confirm dialog | Removed from table | [ ] |
| Set rule inactive via status toggle | Shows "Inactive" | [ ] |
| Save with empty required fields | Validation error shown | [ ] |

### Workflow

| Step | Expected | Status |
|------|----------|--------|
| Order for service in rule's category | Tiered rate applied, not global | [ ] |
| Order for service NOT in any rule | Global commission rate used | [ ] |
| Multiple rules match -- priority 1 beats priority 10 | Lowest priority number wins | [ ] |
| Vendor above volume threshold gets order | Volume rule rate applied | [ ] |
| Inactive rule matches | Skipped, next rule evaluated | [ ] |
| Deactivate Pro | Orders use global rate, no errors | [ ] |
| Reactivate Pro | Rules reappear with data intact | [ ] |

### REST API

| Endpoint | Method | Expected | Status |
|----------|--------|----------|--------|
| `/wpss/v1/commission-rules` | GET | List rules (admin) | [ ] |
| `/wpss/v1/commission-rules` | POST | Create rule (admin) | [ ] |
| `/wpss/v1/commission-rules/{id}` | PUT | Update rule | [ ] |
| `/wpss/v1/commission-rules/{id}` | DELETE | Delete rule | [ ] |
| Unauthenticated | any | 401 error | [ ] |
| Non-admin | POST | 403 forbidden | [ ] |

---

## Feature 2: Vendor Subscriptions

### Settings (Vendor > Vendor Subscriptions)

| Step | Expected | Status |
|------|----------|--------|
| Navigate to Vendor tab | Vendor Subscriptions section visible with Pro badge | [ ] |
| Enable feature toggle | Saves | [ ] |
| Enable "Require subscription to sell" | Saves | [ ] |
| Add plan: "Starter", $29/mo, 5 max services | Plan created | [ ] |
| Add plan: "Pro", $99/mo, unlimited, 5% commission override | Plan created | [ ] |
| Edit plan -- change price | Updated | [ ] |
| Delete plan | Removed after confirmation | [ ] |
| Duplicate slug | Error shown | [ ] |

### Workflow

| Step | Expected | Status |
|------|----------|--------|
| Vendor subscribes to Starter plan | Subscription active | [ ] |
| Vendor creates 5 services | All succeed | [ ] |
| Vendor tries 6th service | Blocked by PlanEnforcer | [ ] |
| Vendor upgrades to Pro | New subscription, old cancelled | [ ] |
| Pro plan commission override applies | 5% rate on orders | [ ] |
| Subscription expires | New service creation blocked (if required) | [ ] |
| "Require" disabled | All vendors can sell | [ ] |
| Deactivate Pro | All vendors can create services, no errors | [ ] |

### REST API

| Endpoint | Method | Expected | Status |
|----------|--------|----------|--------|
| `/wpss/v1/subscription-plans` | GET | List plans | [ ] |
| `/wpss/v1/subscription-plans` | POST | Create (admin) | [ ] |
| `/wpss/v1/subscription-plans/{id}` | PUT/DELETE | Update/delete | [ ] |
| `/wpss/v1/me/subscription` | GET | Vendor's active plan | [ ] |

---

## Feature 3: White Label

### Settings (Branding tab)

| Step | Expected | Status |
|------|----------|--------|
| Branding tab visible in Pro separator group | Tab renders | [ ] |
| Enable white label toggle | Saves | [ ] |
| Enter brand name "MyMarket" | Saves | [ ] |
| Upload logo via media chooser | Preview appears, URL saved | [ ] |
| Set primary color via picker | Hex saved | [ ] |
| Set email from name | Saves | [ ] |
| Set footer text | Saves with newlines | [ ] |
| Reload page | All values persist | [ ] |

### Workflow

| Step | Expected | Status |
|------|----------|--------|
| Admin menu label shows "MyMarket" | Menu text changed | [ ] |
| Admin page titles use brand name | Titles updated | [ ] |
| Send order email | From name = configured name | [ ] |
| Email header shows logo | Image rendered | [ ] |
| Email footer shows custom text | Text appears | [ ] |
| Frontend dashboard shows brand | Name replaced | [ ] |
| Disable white label | All branding reverts to defaults | [ ] |
| XSS in brand name | Sanitized | [ ] |
| Deactivate Pro | "WP Sell Services" label restored, no errors | [ ] |

### REST API

| Endpoint | Method | Expected | Status |
|----------|--------|----------|--------|
| `/wpss/v1/white-label` | GET | Current settings (admin) | [ ] |
| `/wpss/v1/white-label` | PUT | Update settings (admin) | [ ] |

---

## Feature 4: Recurring Services

### Settings (Orders > Recurring Services)

| Step | Expected | Status |
|------|----------|--------|
| Navigate to Orders tab | Recurring Services section visible with Pro badge | [ ] |
| Enable feature toggle | Saves | [ ] |
| Set default interval to Monthly | Saves | [ ] |
| Enable auto-renew | Saves | [ ] |
| "View All Subscriptions" link visible when enabled | Links to Subscriptions admin page | [ ] |
| "View All Subscriptions" link hidden when disabled | Not rendered | [ ] |

### Subscriptions Admin Page (Sell Services > Subscriptions)

| Step | Expected | Status |
|------|----------|--------|
| Menu item visible when recurring enabled | "Subscriptions" submenu under Sell Services | [ ] |
| Menu item hidden when recurring disabled | No submenu entry | [ ] |
| Page renders with status filter links | All/Active/Past Due/Paused/Cancelled/Pending with counts | [ ] |
| Filter by "Active" status | Only active subscriptions shown, count matches | [ ] |
| Filter by "Cancelled" status | Only cancelled subscriptions shown | [ ] |
| Click "All" filter | All subscriptions shown | [ ] |
| Table columns correct | ID, Customer, Vendor, Service, Amount, Interval, Status, Next Billing, Created | [ ] |
| Customer/Vendor display names shown | Names not just IDs | [ ] |
| Service links to edit page | Clickable service title | [ ] |
| Amount formatted correctly | Currency symbol and format | [ ] |
| Interval labels correct | Weekly/Monthly/Quarterly/Yearly | [ ] |
| Status badges color-coded | Green=Active, Yellow=Past Due, Red=Cancelled, Grey=Paused, Blue=Pending | [ ] |
| Pagination works (>20 items) | Page navigation, correct item counts | [ ] |
| Empty state message | "No recurring subscriptions found." when table empty | [ ] |

### Workflow

| Step | Expected | Status |
|------|----------|--------|
| Vendor enables recurring on service (meta field) | Toggle visible in service editor | [ ] |
| Buyer purchases recurring service | Order + subscription created | [ ] |
| Subscription status "Active" | Shown in Subscriptions admin page | [ ] |
| Renewal charge succeeds (webhook) | New order created | [ ] |
| Renewal charge fails | Status "Past Due", no order | [ ] |
| Customer cancels subscription | Status "Cancelled", no future charges | [ ] |
| Vendor deletes service | Existing subscriptions continue | [ ] |
| Recurring disabled globally | Existing subscriptions continue, new blocked, menu hidden | [ ] |
| Deactivate Pro | No recurring meta fields shown, Subscriptions menu hidden, no errors | [ ] |

### REST API

| Endpoint | Method | Expected | Status |
|----------|--------|----------|--------|
| `/wpss/v1/recurring/subscriptions` | GET | List subscriptions | [ ] |
| `/wpss/v1/recurring/subscriptions/{id}` | GET | Detail | [ ] |
| `/wpss/v1/recurring/subscriptions/{id}/cancel` | POST | Cancel | [ ] |

---

## Feature 5: Stripe Connect

### Settings (Gateways > Stripe Connect)

| Step | Expected | Status |
|------|----------|--------|
| Stripe Connect accordion in Gateways tab | Visible with Pro badge | [ ] |
| Enable Stripe Connect | Saves | [ ] |
| Set platform fee 15% | Saves | [ ] |
| Connected accounts table shows | Empty or with accounts | [ ] |

### Vendor Onboarding

| Step | Expected | Status |
|------|----------|--------|
| Vendor sees "Connect with Stripe" button | Visible in dashboard | [ ] |
| Vendor clicks connect | Redirected to Stripe Express onboarding | [ ] |
| Vendor completes onboarding | Redirected back, account saved | [ ] |
| Admin sees vendor in accounts table | Status, charges, payouts flags | [ ] |
| Account pending verification | Status "Pending" | [ ] |

### Payment Split

| Step | Expected | Status |
|------|----------|--------|
| Buyer orders from connected vendor | PaymentIntent has transfer_data | [ ] |
| Platform fee deducted correctly | 15% to platform, 85% to vendor | [ ] |
| Webhook account.updated received | Account status updated | [ ] |
| Vendor NOT connected, buyer orders | Normal payment flow (no transfer_data) | [ ] |
| Deactivate Pro | Stripe payments work without transfer_data | [ ] |

### REST API

| Endpoint | Method | Expected | Status |
|----------|--------|----------|--------|
| `/wpss/v1/stripe-connect/onboard` | POST | Start flow | [ ] |
| `/wpss/v1/stripe-connect/status` | GET | Vendor status | [ ] |
| `/wpss/v1/stripe-connect/accounts` | GET | All accounts (admin) | [ ] |

---

## Feature 6: PayPal Payouts

### Settings (Payments > PayPal Payouts)

| Step | Expected | Status |
|------|----------|--------|
| Navigate to Payments tab | PayPal Payouts section visible with Pro badge | [ ] |
| Enable feature toggle | Saves | [ ] |
| Enter Client ID and Secret | Saves (secret masked) | [ ] |
| Enable Sandbox Mode | Saves | [ ] |
| Set minimum payout $25 | Saves | [ ] |

### Workflow

| Step | Expected | Status |
|------|----------|--------|
| Vendor saves PayPal email in profile | Field visible via profile hook | [ ] |
| Vendor has $50 pending (above min) | Shown in payouts table, selectable | [ ] |
| Vendor has $10 pending (below min) | Shown but disabled | [ ] |
| Select vendors, click "Send Payouts" | Confirmation dialog | [ ] |
| Confirm batch | API call, batch created, success message | [ ] |
| View recent batches | Batch with status shown | [ ] |
| Invalid API credentials | Clear error message | [ ] |
| No vendors with PayPal email | "No vendors" message | [ ] |
| Deactivate Pro | PayPal email field hidden, no errors | [ ] |

### REST API

| Endpoint | Method | Expected | Status |
|----------|--------|----------|--------|
| `/wpss/v1/paypal-payouts/batches` | GET | List batches (admin) | [ ] |
| `/wpss/v1/paypal-payouts/batches` | POST | Create batch (admin) | [ ] |
| `/wpss/v1/paypal-payouts/batches/{id}` | GET | Batch detail | [ ] |

---

## Feature 7: WooCommerce Adapter

### Settings & Activation
| Step | Expected | Status |
|------|----------|--------|
| Activate Pro with WooCommerce active | WC adapter auto-detected | [ ] |
| Settings > General > E-commerce Platform | "WooCommerce" shown or auto-selected | [ ] |
| No WooCommerce installed | Adapter not loaded, no errors | [ ] |

### Service as WC Product
| Step | Expected | Status |
|------|----------|--------|
| Create service | Carrier product created in WC | [ ] |
| Service packages map to WC pricing | Prices match | [ ] |
| Service addons reflected | Additional costs correct | [ ] |
| Edit service price | WC product updated | [ ] |
| Delete service | WC product cleaned up | [ ] |

### WC Checkout Flow
| Step | Expected | Status |
|------|----------|--------|
| Add service to WC cart | Service appears in cart | [ ] |
| WC checkout page | Service order details shown | [ ] |
| Complete WC payment | WPSS order created from WC order | [ ] |
| WC order status "completed" | WPSS order status advances | [ ] |
| WC order status "cancelled" | WPSS order cancelled | [ ] |
| WC order status "refunded" | WPSS order refund processed | [ ] |
| Multi-service WC order | Multiple WPSS orders created | [ ] |
| HPOS compatibility | Works with High Performance Order Storage | [ ] |

### WC Account Integration
| Step | Expected | Status |
|------|----------|--------|
| WC My Account > Vendor Dashboard | Dashboard tab visible | [ ] |
| Vendor orders in WC account | WPSS orders linked correctly | [ ] |

---

## Feature 8: EDD Adapter

### Settings & Activation
| Step | Expected | Status |
|------|----------|--------|
| Activate Pro with EDD active | EDD adapter auto-detected | [ ] |
| No EDD installed | Adapter not loaded, no errors | [ ] |

### EDD Integration
| Step | Expected | Status |
|------|----------|--------|
| Create service | EDD download product created | [ ] |
| EDD checkout flow | Service order details in cart | [ ] |
| Complete EDD payment | WPSS order created | [ ] |
| EDD order status sync | WPSS status follows EDD status | [ ] |
| Refund via EDD | WPSS order updated | [ ] |

---

## Feature 9: FluentCart Adapter

### Settings & Activation
| Step | Expected | Status |
|------|----------|--------|
| Activate Pro with FluentCart active | FC adapter auto-detected | [ ] |
| No FluentCart installed | Adapter not loaded, no errors | [ ] |

### FluentCart Integration
| Step | Expected | Status |
|------|----------|--------|
| Create service | FC product type registered | [ ] |
| Service editor tab in FC | Service fields visible | [ ] |
| FC checkout flow | Service in cart, checkout works | [ ] |
| Complete FC payment | WPSS order created | [ ] |
| Order status mapping | FC status maps to WPSS status | [ ] |
| Dashboard tab integration | Vendor dashboard accessible | [ ] |
| Auto-create order setting | Option in settings works | [ ] |

---

## Feature 10: SureCart Adapter

### Settings & Activation
| Step | Expected | Status |
|------|----------|--------|
| Activate Pro with SureCart active | SC adapter auto-detected | [ ] |
| No SureCart installed | Adapter not loaded, no errors | [ ] |

### SureCart Integration
| Step | Expected | Status |
|------|----------|--------|
| Create service | SC product integration works | [ ] |
| SC checkout flow | Service order processed | [ ] |
| Complete SC payment | WPSS order created | [ ] |
| Order status sync | SC status maps correctly | [ ] |

---

## Feature 11: Razorpay Gateway

### Settings (Gateways > Razorpay)
| Step | Expected | Status |
|------|----------|--------|
| Razorpay accordion in Gateways tab | Visible with Pro badge | [ ] |
| Enable Razorpay | Toggle saves | [ ] |
| Enter Key ID and Key Secret | Saves (secret masked) | [ ] |
| Enable Test Mode | Saves | [ ] |

### Checkout Flow
| Step | Expected | Status |
|------|----------|--------|
| Razorpay appears at checkout | Razorpay payment button shown | [ ] |
| Click pay | Razorpay modal opens | [ ] |
| Complete test payment | Order created, status updated | [ ] |
| Failed payment | Error shown, order stays pending | [ ] |
| Webhook received | Order status confirmed | [ ] |
| INR currency | Rupee symbol, correct format | [ ] |
| USD currency | Dollar amount converted | [ ] |
| Invalid API credentials | Clear error message in settings | [ ] |
| Deactivate Pro | Razorpay option hidden, no errors | [ ] |

### Supported Currencies
| Currency | Expected | Status |
|----------|----------|--------|
| INR | Works natively | [ ] |
| USD | Supported | [ ] |
| EUR | Supported | [ ] |
| GBP | Supported | [ ] |

---

## Feature 12: Cloud Storage (S3 / GCS / DigitalOcean)

### S3 Storage
| Step | Expected | Status |
|------|----------|--------|
| Enable S3 in storage settings | Toggle saves | [ ] |
| Enter AWS Access Key, Secret, Region, Bucket | Saves (secret masked) | [ ] |
| Upload delivery file | File stored on S3 | [ ] |
| Download delivery file | Signed URL generated, file downloads | [ ] |
| Delete file | Removed from S3 | [ ] |
| Invalid credentials | Clear error message | [ ] |

### Google Cloud Storage
| Step | Expected | Status |
|------|----------|--------|
| Enable GCS | Toggle saves | [ ] |
| Enter service account credentials | Saves | [ ] |
| Upload file | Stored on GCS | [ ] |
| Download file | Signed URL works | [ ] |
| Delete file | Removed from GCS | [ ] |

### DigitalOcean Spaces
| Step | Expected | Status |
|------|----------|--------|
| Enable DO Spaces | Toggle saves | [ ] |
| Enter Space name, region, keys | Saves | [ ] |
| Upload file | Stored on DO Spaces | [ ] |
| Download file | Signed URL works | [ ] |
| Delete file | Removed from Spaces | [ ] |

### Cross-Storage Tests
| Step | Expected | Status |
|------|----------|--------|
| Switch storage provider | New uploads go to new provider | [ ] |
| Old files still accessible | Previously uploaded files still download | [ ] |
| Deactivate Pro | Falls back to local WP storage | [ ] |
| Large file upload | Handles within provider limits | [ ] |

---

## Feature 13: Wallet Integrations

### Internal Wallet
| Step | Expected | Status |
|------|----------|--------|
| Internal wallet auto-detected (no third-party) | Wallet active | [ ] |
| Vendor sees wallet balance | Amount shown in dashboard | [ ] |
| Earnings credited to wallet | Balance increases on order complete | [ ] |
| Withdrawal from wallet | Balance decreases | [ ] |
| Wallet transaction history | Credits/debits listed | [ ] |

### TeraWallet Integration
| Step | Expected | Status |
|------|----------|--------|
| TeraWallet plugin active | Auto-detected as wallet provider | [ ] |
| Earnings credited to TeraWallet | Balance in TW increases | [ ] |
| Withdrawal via TeraWallet | TW handles payout | [ ] |
| TW deactivated | Falls back to internal wallet | [ ] |

### WooWallet Integration
| Step | Expected | Status |
|------|----------|--------|
| WooWallet plugin active | Auto-detected as wallet provider | [ ] |
| Earnings credited to WooWallet | Balance increases | [ ] |
| Withdrawal via WooWallet | WW handles payout | [ ] |

### MyCred Integration
| Step | Expected | Status |
|------|----------|--------|
| MyCred plugin active | Auto-detected as wallet provider | [ ] |
| Earnings as MyCred points | Points credited | [ ] |
| Withdrawal via MyCred | Points deducted | [ ] |

### Wallet REST API
| Endpoint | Method | Expected | Status |
|----------|--------|----------|--------|
| `/wpss/v1/wallet/balance` | GET | Current balance | [ ] |
| `/wpss/v1/wallet/transactions` | GET | Transaction history | [ ] |

---

## Feature 14: Advanced Analytics

### Admin Analytics Dashboard
| Step | Expected | Status |
|------|----------|--------|
| Navigate to Sell Services > Analytics | Dashboard page loads | [ ] |
| Revenue chart | Chart.js graph renders | [ ] |
| Period selector (7d / 30d / 90d / 12mo) | Chart updates per period | [ ] |
| Revenue widget | Total revenue, growth % | [ ] |
| Orders widget | Order count, completion rate | [ ] |
| Top Vendors widget | Ranked by earnings | [ ] |
| Top Services widget | Ranked by orders | [ ] |

### Data Export
| Step | Expected | Status |
|------|----------|--------|
| Export as CSV | CSV file downloads | [ ] |
| Export as JSON | JSON file downloads | [ ] |
| Date range filter | Exported data matches range | [ ] |

### Vendor Analytics
| Step | Expected | Status |
|------|----------|--------|
| Vendor dashboard analytics section | Chart visible | [ ] |
| Revenue breakdown | Vendor's own data only | [ ] |
| Vendor visibility toggle in admin | Can hide analytics from vendors | [ ] |

### Data Retention
| Step | Expected | Status |
|------|----------|--------|
| Set retention period in settings | Saves | [ ] |
| Old data purged | Data beyond retention removed | [ ] |

---

## Feature 15: License Management

### License Activation
| Step | Expected | Status |
|------|----------|--------|
| Navigate to Sell Services > License | License page loads | [ ] |
| Enter valid license key | Activates, shows "Active" badge | [ ] |
| Enter invalid license key | Error message shown | [ ] |
| Enter expired license key | "Expired" message, update prompt | [ ] |
| Deactivate license | License deactivated, Pro features gated | [ ] |

### License Enforcement
| Step | Expected | Status |
|------|----------|--------|
| Valid license | All Pro features enabled | [ ] |
| No license | Pro features disabled, upgrade prompts shown | [ ] |
| Expired license | Grace period or feature gating | [ ] |
| License check on admin load | Status verified periodically | [ ] |

---

## Cross-Feature & Safety Checks

| Step | Expected | Status |
|------|----------|--------|
| Activate Pro with all 6 features enabled | No PHP errors in debug.log | [ ] |
| Deactivate Pro | Free plugin works standalone, no errors | [ ] |
| Reactivate Pro | All data intact, features resume | [ ] |
| Uninstall Pro (delete) | Free plugin unaffected, Pro tables dropped | [ ] |
| All admin settings tabs render without JS errors | Console clean | [ ] |
| Recurring + Stripe Connect: recurring with connected vendor | Payment split on each renewal | [ ] |
| Tiered Commission + Vendor Subscriptions: plan override vs tiered rule | Plan override takes precedence | [ ] |
| PayPal Payouts: batch amounts match tiered commission earnings | Correct after commission deductions | [ ] |
| WooCommerce + Stripe Connect: WC order with connected vendor | Transfer_data on PaymentIntent | [ ] |
| WooCommerce + Recurring: recurring WC order | WC subscription + WPSS subscription created | [ ] |
| Cloud Storage + Delivery: upload delivery via S3 | File on S3, buyer can download | [ ] |
| Wallet + PayPal Payouts: earnings in wallet, payout via PayPal | Wallet deducted, PayPal batch sent | [ ] |
| Analytics + Tiered Commission: tiered rate reflected in revenue | Correct earnings after tiered rate | [ ] |
| White Label + Email: branded email with custom logo | Email shows custom branding | [ ] |
| All 4 e-commerce adapters: only one active at a time | No conflicts between WC/EDD/FC/SC | [ ] |
| Razorpay + Standalone: standalone checkout with Razorpay | Payment modal works | [ ] |
| License expired + all features | All Pro features gracefully disabled | [ ] |
| PHP 8.1 compatibility | No deprecation warnings | [ ] |
| WordPress 6.4+ compatibility | No compatibility errors | [ ] |
