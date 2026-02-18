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
| Active subscriptions table shows | Table rendered | [ ] |

### Workflow

| Step | Expected | Status |
|------|----------|--------|
| Vendor enables recurring on service (meta field) | Toggle visible in service editor | [ ] |
| Buyer purchases recurring service | Order + subscription created | [ ] |
| Subscription status "Active" | Shown in admin table | [ ] |
| Renewal charge succeeds (webhook) | New order created | [ ] |
| Renewal charge fails | Status "Past Due", no order | [ ] |
| Customer cancels subscription | Status "Cancelled", no future charges | [ ] |
| Vendor deletes service | Existing subscriptions continue | [ ] |
| Recurring disabled globally | Existing subscriptions continue, new blocked | [ ] |
| Deactivate Pro | No recurring meta fields shown, no errors | [ ] |

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
