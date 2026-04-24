# WP Sell Services Pro -- QA Checklist

**Version:** 1.0.0
**Last Updated:** 2026-04-01
**Total Tests:** 309
**Site URL:** `http://wss.local`
**Admin Login:** `http://wss.local/?autologin=1`
**Prerequisite:** Free plugin must be active. Complete Free QA Phases 1-5 first.

---

## Agent Assignment Map

Pro phases are independent and can be run by **parallel agents** after Free setup is done.

| Agent | Phases | Focus | Tests | Type |
|-------|--------|-------|-------|------|
| **Agent P1: License & Activation** | 1 | Pro activation, license, DB tables, dependency check | ~25 | Backend + DB |
| **Agent P2: WooCommerce** | 2 (WC section) | Product sync, cart, checkout, order sync, reverse sync, refunds | ~40 | Browser + DB |
| **Agent P3: EDD/FluentCart/SureCart** | 2 (other adapters) | Adapter switching, product sync, checkout | ~30 | Browser + DB |
| **Agent P4: Payment Gateways** | 3 | Razorpay, Stripe, PayPal, Offline with all modes | ~30 | Browser (Frontend) |
| **Agent P5: Wallet & Earnings** | 4 | Internal wallet, balance, transactions, admin adjust, withdrawal | ~25 | Browser + DB |
| **Agent P6: Analytics** | 5 | Admin dashboard, vendor dashboard, charts, export | ~20 | Browser (Admin) |
| **Agent P7: Pro Feature Modules** | 7 | Tiered commission, white-label, PayPal payouts, Stripe Connect, subscriptions, recurring | ~60 | Browser + API |
| **Agent P8: Storage & Limits** | 6, 8 | S3/GCS/DO upload, expanded service limits | ~25 | Browser + API |
| **Agent P9: Pro Templates & REST** | 9, 10 | Dashboard tabs, all 10 REST controllers | ~35 | Browser + API |
| **Agent P10: Integration & Cleanup** | 11, 12, 13, 14 | Multi-gateway, platform switching, responsive, uninstall | ~30 | Mixed |

**All Pro agents can run in parallel** — they are independent. Only Agent P1 must run first.

---

## How to Use This Checklist

1. The Free plugin must be installed and activated before testing Pro.
2. Assign agents as shown above, or work through phases sequentially.
3. Each item has a checkbox, step description, expected result, and test type.
4. Some phases require third-party plugins (WooCommerce, EDD, etc.) — skip if not in your environment.
5. Mark items with `[x]` as you complete them. Add notes for failures.

**Test type legend:**
- **M** = Manual browser test
- **A** = Automated (Playwright MCP)
- **DB** = Database verification (WP-CLI or phpMyAdmin)
- **API** = REST API call (curl/Postman/Playwright)

---

## Phase 1: Activation & License

### 1.1 Activation Prerequisites

- [ ] **1.1.1** Verify WP Sell Services (free) is installed and activated. **(A)**
- [ ] **1.1.2** Activate WP Sell Services Pro. **Expected:** Plugin activates without errors, no PHP warnings. **(A)**
- [ ] **1.1.3** Verify Pro hooks into the free plugin via `wpss_loaded` action. Check that Pro admin menu items appear. **(A)**

### 1.2 Activation Without Free Plugin

- [ ] **1.2.1** Deactivate the free plugin. Keep Pro active. **(M)**
- [ ] **1.2.2** Verify Pro displays an admin notice: "WP Sell Services (free) is required." **(A)**
- [ ] **1.2.3** Verify Pro features are disabled gracefully (no PHP fatals). **(A)**
- [ ] **1.2.4** Reactivate the free plugin. Verify Pro features resume working. **(M)**

### 1.3 License Activation

- [ ] **1.3.1** Navigate to **WP Sell Services > License**. Verify license form loads. **(A)**
- [ ] **1.3.2** Enter a valid license key. Click "Activate License". **Expected:** "License activated successfully" message. **(M)**
- [ ] **1.3.3** Verify license status shows "Active" with expiry date. **(A)**
- [ ] **1.3.4** Enter an invalid/expired license key. Click "Activate". **Expected:** Error message. **(M)**

### 1.4 License Deactivation

- [ ] **1.4.1** Click "Deactivate License". **Expected:** License status changes to "Inactive". **(M)**
- [ ] **1.4.2** Verify Pro features still work (feature gating is on expiry, not deactivation). **(A)**

### 1.5 Feature Gating

- [ ] **1.5.1** With license deactivated/expired, verify Pro settings tabs still render but show license notice. **(A)**
- [ ] **1.5.2** Verify update mechanism is disabled when license is not valid. **(M)**

### 1.6 Pro Database Tables

Verify all 7 Pro tables are created on activation:

- [ ] **1.6.1** `wpss_pro_connect_accounts` -- Stripe Connect vendor accounts **(DB)**
- [ ] **1.6.2** `wpss_pro_subscription_plans` -- Vendor subscription plans **(DB)**
- [ ] **1.6.3** `wpss_pro_vendor_subscriptions` -- Active vendor subscriptions **(DB)**
- [ ] **1.6.4** `wpss_pro_commission_rules` -- Tiered commission rules **(DB)**
- [ ] **1.6.5** `wpss_pro_recurring_subscriptions` -- Customer recurring subscriptions **(DB)**
- [ ] **1.6.6** `wpss_pro_paypal_payout_batches` -- PayPal batch payout records **(DB)**
- [ ] **1.6.7** `wpss_pro_paypal_payout_items` -- Individual payout items **(DB)**

---

## Phase 2: E-Commerce Adapters

### 2.1 WooCommerce Adapter

**Prerequisite:** WooCommerce installed and activated.

#### 2.1.1 Setup

- [ ] **2.1.1.1** Set e-commerce platform to "WooCommerce" in **Settings > General**. **(M)**
- [ ] **2.1.1.2** Verify WooCommerce adapter initializes (no errors in debug.log). **(A)**

#### 2.1.2 Product Sync (WPSS --> WC)

- [ ] **2.1.2.1** Create a new service in WP Sell Services. Verify a corresponding WC product is auto-created. **(DB)**
- [ ] **2.1.2.2** Verify WC product has correct price (from selected package). **(A)**
- [ ] **2.1.2.3** Update service title in WPSS. Verify WC product title syncs. **(M)**
- [ ] **2.1.2.4** Delete the WPSS service. Verify WC product is trashed. **(DB)**

#### 2.1.3 Cart & Checkout

- [ ] **2.1.3.1** Add a service to cart via the WPSS service page. Verify item appears in WC cart. **(A)**
- [ ] **2.1.3.2** Proceed to WC checkout. Complete payment (use a test gateway like COD). **(M)**
- [ ] **2.1.3.3** Verify a WPSS order is created with correct service, buyer, vendor, and amount. **(DB)**
- [ ] **2.1.3.4** Verify WC order status syncs to WPSS order status. **(DB)**

#### 2.1.4 Order Sync (WC --> WPSS)

- [ ] **2.1.4.1** Mark WC order as "Completed" from WC admin. Verify WPSS order status updates accordingly. **(M)**
- [ ] **2.1.4.2** Cancel the WC order. Verify WPSS order reflects cancellation. **(M)**
- [ ] **2.1.4.3** Refund the WC order. Verify WPSS order is cancelled and vendor earnings adjusted. **(M)**

#### 2.1.5 Reverse Sync (WPSS --> WC)

- [ ] **2.1.5.1** Change WPSS order status to `completed`. Verify WC order status syncs. **(M)**
- [ ] **2.1.5.2** Cancel WPSS order. Verify WC order is cancelled. **(M)**

#### 2.1.6 Multi-Service Cart

- [ ] **2.1.6.1** Add 2 services from different vendors to cart. Complete checkout. **(M)**
- [ ] **2.1.6.2** Verify 2 separate WPSS orders are created (one per vendor). **(DB)**
- [ ] **2.1.6.3** Verify 1 WC order is created with 2 line items. **(DB)**

#### 2.1.7 HPOS Compatibility

- [ ] **2.1.7.1** Enable HPOS (High-Performance Order Storage) in WooCommerce. **(M)**
- [ ] **2.1.7.2** Complete an order flow. Verify no errors and WPSS orders are correctly linked. **(M)**

### 2.2 Easy Digital Downloads Adapter

**Prerequisite:** EDD installed and activated.

- [ ] **2.2.1** Set e-commerce platform to "Easy Digital Downloads" in settings. **(M)**
- [ ] **2.2.2** Create a service in WPSS. Verify a corresponding EDD download is created. **(DB)**
- [ ] **2.2.3** Add service to cart via WPSS. Complete EDD checkout. Verify WPSS order is created. **(M)**
- [ ] **2.2.4** Verify EDD payment status syncs to WPSS order status. **(M)**
- [ ] **2.2.5** Refund the EDD payment. Verify WPSS order is cancelled. **(M)**

### 2.3 FluentCart Adapter

**Prerequisite:** FluentCart installed and activated.

- [ ] **2.3.1** Set e-commerce platform to "FluentCart" in settings. **(M)**
- [ ] **2.3.2** Create a service. Verify FluentCart product is created. **(DB)**
- [ ] **2.3.3** Complete FluentCart checkout. Verify WPSS order is created. **(M)**
- [ ] **2.3.4** Verify order status sync between FluentCart and WPSS. **(M)**

### 2.4 SureCart Adapter

**Prerequisite:** SureCart installed and activated.

- [ ] **2.4.1** Set e-commerce platform to "SureCart" in settings. **(M)**
- [ ] **2.4.2** Create a service. Verify SureCart product is created. **(DB)**
- [ ] **2.4.3** Complete SureCart checkout. Verify WPSS order is created. **(M)**
- [ ] **2.4.4** Verify order status sync between SureCart and WPSS. **(M)**

---

## Phase 3: Payment Gateways

### 3.1 Razorpay Gateway

**Prerequisite:** Razorpay API keys configured in settings.

- [ ] **3.1.1** Navigate to **Settings > Gateways > Razorpay**. Enter API key and secret (sandbox). **(M)**
- [ ] **3.1.2** Enable Razorpay gateway. Save settings. **(M)**
- [ ] **3.1.3** On checkout, select Razorpay as payment method. **(M)**
- [ ] **3.1.4** Click "Pay". Verify Razorpay modal opens with correct amount. **(M)**
- [ ] **3.1.5** Complete payment in Razorpay sandbox. Verify redirect to order confirmation. **(M)**
- [ ] **3.1.6** Verify WPSS order status changes from `pending_payment` to `pending_requirements`. **(DB)**
- [ ] **3.1.7** Verify Razorpay payment ID is stored in order meta. **(DB)**
- [ ] **3.1.8** Test Razorpay webhook: simulate a payment capture webhook. Verify order updates. **(API)**
- [ ] **3.1.9** Test failed payment. Verify order remains in `pending_payment` with error message. **(M)**

### 3.2 Stripe Gateway (Free Plugin -- Verify with Pro Active)

**Prerequisite:** Stripe test keys configured in settings.

- [ ] **3.2.1** Navigate to **Settings > Gateways > Stripe**. Verify test keys are configured. **(A)**
- [ ] **3.2.2** On checkout, select Stripe. Enter test card `4242 4242 4242 4242`. **(M)**
- [ ] **3.2.3** Submit payment. Verify Stripe Payment Intent is created. **(M)**
- [ ] **3.2.4** Verify order moves to `pending_requirements` on successful charge. **(DB)**
- [ ] **3.2.5** Test 3D Secure card `4000 0025 0000 3155`. Verify 3DS modal appears and completes. **(M)**
- [ ] **3.2.6** Test declined card `4000 0000 0000 0002`. Verify error message displayed. **(M)**
- [ ] **3.2.7** Verify Stripe webhook `payment_intent.succeeded` updates order status. **(API)**

### 3.3 PayPal Gateway (Free Plugin -- Verify with Pro Active)

**Prerequisite:** PayPal sandbox credentials configured.

- [ ] **3.3.1** Navigate to **Settings > Gateways > PayPal**. Verify sandbox credentials. **(A)**
- [ ] **3.3.2** On checkout, select PayPal. Click "Pay with PayPal". **(M)**
- [ ] **3.3.3** Verify redirect to PayPal sandbox login. Log in and approve. **(M)**
- [ ] **3.3.4** Verify redirect back to site with order confirmed. **(M)**
- [ ] **3.3.5** Verify PayPal capture ID is stored in order meta. **(DB)**
- [ ] **3.3.6** Verify PayPal webhook (IPN) processes correctly. **(API)**

### 3.4 Offline Gateway (Free Plugin -- Verify with Pro Active)

- [ ] **3.4.1** On checkout, select "Offline Payment". **(M)**
- [ ] **3.4.2** Submit order. Verify order is created with `pending_payment` status and bank details displayed. **(A)**
- [ ] **3.4.3** As admin, mark order as paid. Verify status transitions to `pending_requirements`. **(M)**

---

## Phase 4: Wallet System

### 4.1 Internal Wallet

- [ ] **4.1.1** Set wallet provider to "Internal Wallet" in **Settings > Earnings > Wallet Provider**. **(M)**
- [ ] **4.1.2** Complete an order. Verify vendor wallet balance increases by (order total - commission). **(DB)**
- [ ] **4.1.3** As vendor, navigate to Dashboard > Wallet tab. Verify balance displays correctly. **(A)**
- [ ] **4.1.4** Verify transaction history lists the earning with order reference. **(A)**

### 4.2 Wallet Transactions

- [ ] **4.2.1** Complete 3 orders for the same vendor. Verify 3 credit transactions appear. **(A)**
- [ ] **4.2.2** Request a withdrawal. Verify a debit transaction appears in wallet history. **(A)**
- [ ] **4.2.3** Admin rejects the withdrawal. Verify balance is restored and a credit-back transaction appears. **(DB)**

### 4.3 Admin Wallet Adjustments

- [ ] **4.3.1** As admin, navigate to vendor's profile in **WP Sell Services > Vendors**. **(A)**
- [ ] **4.3.2** Credit $50 to vendor's wallet with a note. Verify balance increases. **(M)**
- [ ] **4.3.3** Debit $20 from vendor's wallet with a note. Verify balance decreases. **(M)**
- [ ] **4.3.4** Verify both adjustments appear in the vendor's transaction history with admin notes. **(A)**

### 4.4 Wallet Withdrawal via Wallet

- [ ] **4.4.1** As vendor, from Dashboard > Wallet, click "Withdraw". Enter amount. Submit. **(M)**
- [ ] **4.4.2** Verify withdrawal request appears in admin **Withdrawals** page. **(A)**
- [ ] **4.4.3** Admin approves. Verify vendor wallet balance is reduced. **(DB)**

### 4.5 TeraWallet Integration

**Prerequisite:** TeraWallet for WooCommerce installed.

- [ ] **4.5.1** Set wallet provider to "TeraWallet". **(M)**
- [ ] **4.5.2** Complete an order. Verify vendor's TeraWallet balance increases. **(DB)**
- [ ] **4.5.3** Verify transaction appears in TeraWallet's transaction log. **(A)**

### 4.6 WooWallet Integration

**Prerequisite:** WooWallet installed.

- [ ] **4.6.1** Set wallet provider to "WooWallet". **(M)**
- [ ] **4.6.2** Complete an order. Verify vendor's WooWallet balance increases. **(DB)**

### 4.7 MyCred Integration

**Prerequisite:** MyCred installed.

- [ ] **4.7.1** Set wallet provider to "MyCred". **(M)**
- [ ] **4.7.2** Complete an order. Verify vendor's MyCred points increase. **(DB)**

---

## Phase 5: Analytics

### 5.1 Admin Analytics Dashboard

- [ ] **5.1.1** Navigate to **WP Sell Services > Analytics**. Dashboard loads without errors. **(A)**
- [ ] **5.1.2** Verify revenue chart renders (Chart.js canvas element present and populated). **(A)**
- [ ] **5.1.3** Toggle time range: 7 days, 30 days, 90 days, 12 months. Verify chart updates. **(M)**
- [ ] **5.1.4** Verify order analytics widget shows status distribution (bar/pie chart). **(A)**
- [ ] **5.1.5** Verify "Top Services" widget lists services ranked by revenue. **(A)**
- [ ] **5.1.6** Verify "Top Vendors" widget lists vendors ranked by earnings. **(A)**

### 5.2 Data Export

- [ ] **5.2.1** Click "Export CSV" on the analytics dashboard. Verify CSV file downloads. **(M)**
- [ ] **5.2.2** Open the CSV. Verify it contains revenue data with correct columns (date, amount, orders). **(M)**
- [ ] **5.2.3** Click "Export Excel" (if available). Verify XLSX downloads. **(M)**

### 5.3 Vendor Analytics

- [ ] **5.3.1** As vendor, navigate to Dashboard > Analytics tab. **(A)**
- [ ] **5.3.2** Verify vendor-specific revenue chart renders with their earnings data. **(A)**
- [ ] **5.3.3** Verify order count and average order value display correctly. **(A)**
- [ ] **5.3.4** Verify top-performing services list shows vendor's own services only. **(A)**

---

## Phase 6: Cloud Storage

### 6.1 Amazon S3

**Prerequisite:** AWS S3 credentials configured in **Settings > Advanced > Cloud Storage**.

- [ ] **6.1.1** Set storage provider to "Amazon S3". Enter access key, secret key, bucket, region. Save. **(M)**
- [ ] **6.1.2** As vendor, deliver an order with a file attachment. **(M)**
- [ ] **6.1.3** Verify the file is uploaded to S3 bucket (check S3 console or use AWS CLI). **(M)**
- [ ] **6.1.4** As buyer, download the delivery file. Verify it downloads from S3 (pre-signed URL). **(M)**
- [ ] **6.1.5** Delete the delivery from WPSS. Verify file is removed from S3. **(M)**

### 6.2 Google Cloud Storage

**Prerequisite:** GCS service account JSON configured.

- [ ] **6.2.1** Set storage provider to "Google Cloud Storage". Upload service account JSON. Set bucket. Save. **(M)**
- [ ] **6.2.2** Deliver an order with a file. Verify upload to GCS bucket. **(M)**
- [ ] **6.2.3** Download the file as buyer. Verify pre-signed URL download from GCS. **(M)**

### 6.3 DigitalOcean Spaces

**Prerequisite:** DO Spaces credentials configured.

- [ ] **6.3.1** Set storage provider to "DigitalOcean Spaces". Enter key, secret, space, region. Save. **(M)**
- [ ] **6.3.2** Deliver an order with a file. Verify upload to Spaces. **(M)**
- [ ] **6.3.3** Download the file. Verify it serves from Spaces. **(M)**

### 6.4 Fallback to Local

- [ ] **6.4.1** Set storage provider to "None" / "Local" (default). **(M)**
- [ ] **6.4.2** Deliver a file. Verify it is stored in WordPress uploads directory. **(M)**
- [ ] **6.4.3** Misconfigure S3 credentials (wrong key). Attempt file upload. Verify graceful error, file falls back to local storage. **(M)**

---

## Phase 7: Pro Feature Modules

### 7.1 Tiered Commission

#### 7.1.1 Rule CRUD

- [ ] **7.1.1.1** Navigate to **WP Sell Services > Settings > Commission** (or dedicated Tiered Commission tab). **(A)**
- [ ] **7.1.1.2** Create a Category Commission Rule: Category = "Design", Rate = 20%. Save. **(M)**
- [ ] **7.1.1.3** Verify rule is stored in `wpss_pro_commission_rules`. **(DB)**
- [ ] **7.1.1.4** Create a Volume Commission Rule: Orders > 10 = 12% rate. Save. **(M)**
- [ ] **7.1.1.5** Create a Seller Level Commission Rule: Top Rated = 8%. Save. **(M)**
- [ ] **7.1.1.6** Edit the category rule. Change rate to 18%. Save. Verify update persists. **(M)**
- [ ] **7.1.1.7** Delete the volume rule. Verify it is removed from the table. **(M)**

#### 7.1.2 Rule Application

- [ ] **7.1.2.1** Complete an order for a "Design" category service. Verify 18% commission is applied (not global rate). **(DB)**
- [ ] **7.1.2.2** Complete orders until volume threshold is met. Verify volume rule kicks in for subsequent orders. **(DB)**
- [ ] **7.1.2.3** Promote vendor to "Top Rated" level. Complete an order. Verify 8% commission applies. **(DB)**
- [ ] **7.1.2.4** Verify rule priority: most specific rule wins (seller level > volume > category > global). **(DB)**

### 7.2 White Label

- [ ] **7.2.1** Navigate to **WP Sell Services > Settings > White Label** tab. **(A)**
- [ ] **7.2.2** Change platform name to "MyMarket". Save. **(M)**
- [ ] **7.2.3** Verify admin menu label changes to "MyMarket". **(A)**
- [ ] **7.2.4** Verify frontend dashboard header shows "MyMarket" instead of "WP Sell Services". **(A)**
- [ ] **7.2.5** Upload a custom logo. Save. Verify logo replaces default in admin and frontend. **(M)**
- [ ] **7.2.6** Change primary brand color. Save. Verify color applies to frontend buttons/links. **(A)**
- [ ] **7.2.7** Verify email templates use the white-label name and logo. **(M)**
- [ ] **7.2.8** Reset to defaults. Verify original branding is restored. **(M)**

### 7.3 PayPal Payouts

**Prerequisite:** PayPal Business API credentials configured.

#### 7.3.1 Setup

- [ ] **7.3.1.1** Navigate to **Settings > PayPal Payouts** tab. Enter client ID and secret (sandbox). **(M)**
- [ ] **7.3.1.2** Enable PayPal Payouts. Save. **(M)**

#### 7.3.2 Vendor PayPal Profile

- [ ] **7.3.2.1** As vendor, navigate to Dashboard > Payouts (or Profile > Payout Settings). **(A)**
- [ ] **7.3.2.2** Enter PayPal email for payouts. Save. Verify email is stored. **(M)**

#### 7.3.3 Create Payout Batch

- [ ] **7.3.3.1** As admin, navigate to **WP Sell Services > Payouts > PayPal**. **(A)**
- [ ] **7.3.3.2** Select vendors with available earnings. Click "Create Payout Batch". **(M)**
- [ ] **7.3.3.3** Verify `wpss_pro_paypal_payout_batches` has a new row with `pending` status. **(DB)**
- [ ] **7.3.3.4** Verify `wpss_pro_paypal_payout_items` has individual items per vendor. **(DB)**
- [ ] **7.3.3.5** Verify PayPal sandbox shows the batch payout. **(M)**

#### 7.3.4 Batch Sync

- [ ] **7.3.4.1** After PayPal processes the batch (sandbox), trigger sync. **(M)**
- [ ] **7.3.4.2** Verify batch status updates to `success` or `processed`. **(DB)**
- [ ] **7.3.4.3** Verify individual payout items have updated statuses. **(DB)**
- [ ] **7.3.4.4** Verify vendor wallet balances are adjusted (earnings deducted as paid out). **(DB)**

### 7.4 Stripe Connect

**Prerequisite:** Stripe Connect platform keys configured.

#### 7.4.1 Setup

- [ ] **7.4.1.1** Navigate to **Settings > Stripe Connect** tab. Enter platform account ID and keys. **(M)**
- [ ] **7.4.1.2** Enable Stripe Connect. Save. **(M)**

#### 7.4.2 Vendor Onboarding

- [ ] **7.4.2.1** As vendor, navigate to Dashboard > Stripe Connect tab. **(A)**
- [ ] **7.4.2.2** Click "Connect with Stripe". Verify redirect to Stripe Express onboarding flow. **(M)**
- [ ] **7.4.2.3** Complete Stripe onboarding (use test data). Verify redirect back to dashboard. **(M)**
- [ ] **7.4.2.4** Verify `wpss_pro_connect_accounts` has a row with vendor's Stripe account ID and `active` status. **(DB)**
- [ ] **7.4.2.5** Verify dashboard shows "Connected" status with account summary. **(A)**

#### 7.4.3 Direct Payments

- [ ] **7.4.3.1** Buyer places an order for a Stripe-connected vendor. **(M)**
- [ ] **7.4.3.2** Verify Stripe charge includes `transfer_data` to the connected vendor account. **(M)**
- [ ] **7.4.3.3** Verify platform commission is retained as application fee. **(DB)**

#### 7.4.4 Disconnect

- [ ] **7.4.4.1** As vendor, click "Disconnect from Stripe". Confirm. **(M)**
- [ ] **7.4.4.2** Verify `wpss_pro_connect_accounts` row is updated (status = disconnected or deleted). **(DB)**
- [ ] **7.4.4.3** Verify subsequent orders fall back to normal payout flow. **(M)**

### 7.5 Vendor Subscriptions

#### 7.5.1 Plan CRUD (Admin)

- [ ] **7.5.1.1** Navigate to **WP Sell Services > Subscriptions > Plans**. **(A)**
- [ ] **7.5.1.2** Create a "Basic Vendor" plan: $9.99/month, 5 max services, 3 max active orders. Save. **(M)**
- [ ] **7.5.1.3** Create a "Pro Vendor" plan: $29.99/month, unlimited services, unlimited orders. Save. **(M)**
- [ ] **7.5.1.4** Verify `wpss_pro_subscription_plans` has 2 rows. **(DB)**
- [ ] **7.5.1.5** Edit "Basic Vendor" plan price to $12.99. Save. Verify update persists. **(M)**
- [ ] **7.5.1.6** Delete a plan. Verify it is removed. **(M)**

#### 7.5.2 Vendor Subscribe

- [ ] **7.5.2.1** As vendor, navigate to Dashboard > Subscription tab. **(A)**
- [ ] **7.5.2.2** View available plans. Select "Basic Vendor". Click "Subscribe". **(M)**
- [ ] **7.5.2.3** Complete payment via Stripe. Verify subscription is active. **(M)**
- [ ] **7.5.2.4** Verify `wpss_pro_vendor_subscriptions` has a row with `active` status. **(DB)**

#### 7.5.3 Limit Enforcement

- [ ] **7.5.3.1** With "Basic Vendor" plan (5 max services), create 5 services. **(M)**
- [ ] **7.5.3.2** Attempt to create a 6th service. **Expected:** Blocked with "Upgrade your plan" message. **(M)**
- [ ] **7.5.3.3** Upgrade to "Pro Vendor" plan. Verify unlimited services are now allowed. **(M)**

#### 7.5.4 Subscription Lifecycle

- [ ] **7.5.4.1** Cancel subscription as vendor. Verify status changes to `cancelled` (remains active until period end). **(M)**
- [ ] **7.5.4.2** Verify enforcement begins after subscription period ends. **(DB)**
- [ ] **7.5.4.3** Test Stripe webhook for subscription renewal. Verify auto-renewal extends period. **(API)**
- [ ] **7.5.4.4** Test Stripe webhook for payment failure. Verify subscription becomes `past_due`. **(API)**

### 7.6 Recurring Services

#### 7.6.1 Setup

- [ ] **7.6.1.1** Navigate to **Settings > Recurring Services** tab. Enable recurring services. Save. **(M)**

#### 7.6.2 Enable on Service

- [ ] **7.6.2.1** As vendor, edit a service. In the wizard, toggle "Enable Recurring" on a package. **(M)**
- [ ] **7.6.2.2** Set billing cycle: monthly. Save. **(M)**
- [ ] **7.6.2.3** Verify service single page shows "Monthly subscription" label on the package. **(A)**

#### 7.6.3 Buyer Subscribes

- [ ] **7.6.3.1** As buyer, select the recurring package. Complete checkout via Stripe. **(M)**
- [ ] **7.6.3.2** Verify `wpss_pro_recurring_subscriptions` has a row with `active` status. **(DB)**
- [ ] **7.6.3.3** Verify a WPSS order is created for the first billing period. **(DB)**

#### 7.6.4 Recurring Billing

- [ ] **7.6.4.1** Simulate Stripe `invoice.payment_succeeded` webhook for renewal. **(API)**
- [ ] **7.6.4.2** Verify a new WPSS order is auto-created for the new billing period. **(DB)**
- [ ] **7.6.4.3** Verify vendor earnings are credited for the renewal. **(DB)**

#### 7.6.5 Cancel & Pause

- [ ] **7.6.5.1** As buyer, cancel the recurring subscription from dashboard. **(M)**
- [ ] **7.6.5.2** Verify status changes to `cancelled`. No further billing occurs. **(DB)**
- [ ] **7.6.5.3** Subscribe again. Then pause the subscription. Verify status = `paused`. **(M)**
- [ ] **7.6.5.4** Resume the paused subscription. Verify it becomes `active` again. **(M)**

---

## Phase 8: Expanded Service Limits

### 8.1 Gallery Images

- [ ] **8.1.1** As vendor (with Pro active), create a service. Upload 5+ gallery images. **(M)**
- [ ] **8.1.2** Verify no limit is enforced (free limit of 4 is lifted). **(M)**
- [ ] **8.1.3** Upload 10 images. Verify all display in gallery. **(A)**

### 8.2 Video Embeds

- [ ] **8.2.1** Add 2 video embed URLs (free limit is 1). Verify both are accepted. **(M)**
- [ ] **8.2.2** Add a 3rd video. Verify Pro limit of 3 max. **(M)**

### 8.3 Add-ons

- [ ] **8.3.1** Add 4+ add-ons to a service (free limit is 3). Verify no limit with Pro. **(M)**

### 8.4 FAQs

- [ ] **8.4.1** Add 6+ FAQ items (free limit is 5). Verify no limit with Pro. **(M)**

### 8.5 Requirements

- [ ] **8.5.1** Add 6+ requirement fields (free limit is 5). Verify no limit with Pro. **(M)**

### 8.6 Wizard Enhancements

- [ ] **8.6.1** In service wizard Step 1, verify AI title suggestion feature is available. **(A)**
- [ ] **8.6.2** Verify service templates dropdown appears (Pro feature). **(A)**
- [ ] **8.6.3** In media step, verify bulk image upload is available. **(M)**
- [ ] **8.6.4** In publish step, verify "Schedule for later" option is available. Select a future date. Save. Verify service gets `future` status. **(M)**

---

## Phase 9: Pro Templates & Dashboard Tabs

### 9.1 Analytics Dashboard Tab

- [ ] **9.1.1** As vendor, navigate to Dashboard. Verify "Analytics" tab appears (Pro only). **(A)**
- [ ] **9.1.2** Click Analytics tab. Verify charts and data load. **(A)**

### 9.2 Stripe Connect Tab

- [ ] **9.2.1** As vendor, verify "Stripe Connect" tab appears in dashboard (when Stripe Connect is enabled). **(A)**
- [ ] **9.2.2** Click tab. Verify connect/disconnect UI loads. **(A)**

### 9.3 Subscription Tab

- [ ] **9.3.1** Verify "Subscription" tab appears in vendor dashboard (when vendor subscriptions are enabled). **(A)**
- [ ] **9.3.2** Click tab. Verify plan selection and current subscription status display. **(A)**

### 9.4 Wallet Tab

- [ ] **9.4.1** Verify "Wallet" tab appears in vendor dashboard (when a wallet provider is configured). **(A)**
- [ ] **9.4.2** Click tab. Verify balance, transaction list, and withdraw button display. **(A)**

---

## Phase 10: Pro REST API

**Base URL:** `http://wss.local/wp-json/wpss/v1`

### 10.1 Wallet Controller (`/wallet`)

- [ ] **10.1.1** `GET /wallet/balance` as vendor. **Expected:** 200 + balance object. **(API)**
- [ ] **10.1.2** `GET /wallet/transactions` as vendor. **Expected:** 200 + paginated transactions. **(API)**
- [ ] **10.1.3** `POST /wallet/withdraw` as vendor. **Expected:** 200 + withdrawal created. **(API)**
- [ ] **10.1.4** `GET /wallet/providers`. **Expected:** 200 + list of configured providers. **(API)**
- [ ] **10.1.5** `GET /wallet/balance` as non-vendor. **Expected:** 403 forbidden. **(API)**

### 10.2 Payment Controller (`/payments`)

- [ ] **10.2.1** `POST /payments/stripe/create-intent` with order data. **Expected:** 200 + client_secret. **(API)**
- [ ] **10.2.2** `POST /payments/paypal/create-order` with order data. **Expected:** 200 + PayPal order ID. **(API)**
- [ ] **10.2.3** `POST /payments/razorpay/create-order`. **Expected:** 200 + Razorpay order ID. **(API)**
- [ ] **10.2.4** `GET /payments/status/{order_id}`. **Expected:** 200 + payment status. **(API)**
- [ ] **10.2.5** `POST /payments/offline/submit` with proof. **Expected:** 200. **(API)**

### 10.3 Analytics Controller (`/analytics`)

- [ ] **10.3.1** `GET /analytics/overview` as admin. **Expected:** 200 + summary stats. **(API)**
- [ ] **10.3.2** `GET /analytics/revenue?period=30d` as admin. **Expected:** 200 + revenue data. **(API)**
- [ ] **10.3.3** `GET /analytics/orders?period=30d`. **Expected:** 200 + order stats. **(API)**
- [ ] **10.3.4** `GET /analytics/services`. **Expected:** 200 + service performance data. **(API)**
- [ ] **10.3.5** `GET /analytics/export?format=csv`. **Expected:** 200 + CSV download. **(API)**
- [ ] **10.3.6** `GET /analytics/overview` as non-admin. **Expected:** 403 forbidden. **(API)**

### 10.4 Vendor Analytics Controller (`/analytics/vendor`)

- [ ] **10.4.1** `GET /analytics/vendor` as vendor. **Expected:** 200 + vendor-specific analytics. **(API)**
- [ ] **10.4.2** `GET /analytics/vendor` as buyer. **Expected:** 403 forbidden. **(API)**

### 10.5 Storage Controller (`/storage`)

- [ ] **10.5.1** `POST /storage/upload` with file. **Expected:** 200 + file URL. **(API)**
- [ ] **10.5.2** `GET /storage/download-url/{file_id}`. **Expected:** 200 + pre-signed URL. **(API)**
- [ ] **10.5.3** `DELETE /storage/{file_id}`. **Expected:** 200 success. **(API)**
- [ ] **10.5.4** `GET /storage/providers`. **Expected:** 200 + configured providers. **(API)**

### 10.6 Stripe Connect Controller (`/stripe-connect`)

- [ ] **10.6.1** `POST /stripe-connect/onboard` as vendor. **Expected:** 200 + onboarding URL. **(API)**
- [ ] **10.6.2** `GET /stripe-connect/status` as vendor. **Expected:** 200 + connection status. **(API)**
- [ ] **10.6.3** `GET /stripe-connect/accounts` as admin. **Expected:** 200 + all connected accounts. **(API)**
- [ ] **10.6.4** `DELETE /stripe-connect/disconnect` as vendor. **Expected:** 200 success. **(API)**

### 10.7 PayPal Payouts Controller (`/paypal-payouts`)

- [ ] **10.7.1** `POST /paypal-payouts/create-batch` as admin. **Expected:** 200 + batch ID. **(API)**
- [ ] **10.7.2** `GET /paypal-payouts/batches` as admin. **Expected:** 200 + batch list. **(API)**
- [ ] **10.7.3** `GET /paypal-payouts/pending` as admin. **Expected:** 200 + pending payout vendors. **(API)**
- [ ] **10.7.4** `POST /paypal-payouts/profiles` as vendor. **Expected:** 200 + profile saved. **(API)**

### 10.8 Subscription Plans Controller (`/subscription-plans`)

- [ ] **10.8.1** `GET /subscription-plans`. **Expected:** 200 + plan list. **(API)**
- [ ] **10.8.2** `POST /subscription-plans` as admin. **Expected:** 201 + plan created. **(API)**
- [ ] **10.8.3** `PUT /subscription-plans/{id}` as admin. **Expected:** 200 + plan updated. **(API)**
- [ ] **10.8.4** `DELETE /subscription-plans/{id}` as admin. **Expected:** 200 + plan deleted. **(API)**
- [ ] **10.8.5** `POST /subscription-plans/{id}/subscribe` as vendor. **Expected:** 200 + subscription created. **(API)**
- [ ] **10.8.6** `POST /subscription-plans/cancel` as vendor. **Expected:** 200 + subscription cancelled. **(API)**

### 10.9 Recurring Services Controller (`/recurring-services`)

- [ ] **10.9.1** `GET /recurring-services` as buyer. **Expected:** 200 + active subscriptions. **(API)**
- [ ] **10.9.2** `POST /recurring-services/{id}/cancel` as buyer. **Expected:** 200. **(API)**
- [ ] **10.9.3** `POST /recurring-services/{id}/pause` as buyer. **Expected:** 200. **(API)**
- [ ] **10.9.4** `POST /recurring-services/{id}/resume` as buyer. **Expected:** 200. **(API)**

### 10.10 Commission Rules Controller (`/commission-rules`)

- [ ] **10.10.1** `GET /commission-rules` as admin. **Expected:** 200 + rules list. **(API)**
- [ ] **10.10.2** `POST /commission-rules` as admin with rule data. **Expected:** 201 + rule created. **(API)**
- [ ] **10.10.3** `PUT /commission-rules/{id}` as admin. **Expected:** 200 + rule updated. **(API)**
- [ ] **10.10.4** `DELETE /commission-rules/{id}` as admin. **Expected:** 200 + rule deleted. **(API)**
- [ ] **10.10.5** `POST /commission-rules/preview` with order data. **Expected:** 200 + calculated commission. **(API)**
- [ ] **10.10.6** `POST /commission-rules` as non-admin. **Expected:** 403 forbidden. **(API)**

### 10.11 White Label Controller (`/white-label`)

- [ ] **10.11.1** `GET /white-label` as admin. **Expected:** 200 + current settings. **(API)**
- [ ] **10.11.2** `PUT /white-label` as admin with updated settings. **Expected:** 200 + settings saved. **(API)**
- [ ] **10.11.3** `GET /white-label` as non-admin. **Expected:** 403 forbidden. **(API)**

### 10.12 Permission Checks (Cross-Cutting)

- [ ] **10.12.1** Hit all admin-only endpoints as a vendor. **Expected:** 403 on every one. **(API)**
- [ ] **10.12.2** Hit all vendor-only endpoints as a buyer. **Expected:** 403 on every one. **(API)**
- [ ] **10.12.3** Hit all authenticated endpoints without auth. **Expected:** 401 on every one. **(API)**

---

## Phase 11: Integration Smoke Tests

### 11.1 Pro + Free Coexistence

- [ ] **11.1.1** Verify no duplicate admin menu items when both plugins are active. **(A)**
- [ ] **11.1.2** Verify settings from both plugins merge correctly (no overwriting). **(A)**
- [ ] **11.1.3** Verify Pro tabs appear alongside Free tabs in Settings. **(A)**
- [ ] **11.1.4** Deactivate Pro. Verify Free plugin still works independently, Pro tabs disappear. **(M)**
- [ ] **11.1.5** Reactivate Pro. Verify all Pro features resume without data loss. **(M)**

### 11.2 Multiple Gateways Active

- [ ] **11.2.1** Enable Stripe, PayPal, Razorpay, and Offline gateways simultaneously. **(M)**
- [ ] **11.2.2** On checkout, verify all 4 gateway options appear. **(A)**
- [ ] **11.2.3** Complete a checkout with each gateway. Verify orders are created correctly for each. **(M)**

### 11.3 Platform Switching

- [ ] **11.3.1** Switch e-commerce platform from Standalone to WooCommerce. Verify existing orders are preserved. **(M)**
- [ ] **11.3.2** Switch from WooCommerce to EDD. Verify services and orders remain intact. **(M)**
- [ ] **11.3.3** Switch back to Standalone. Verify standalone checkout works. **(M)**

---

## Phase 12: Edge Cases & Error Handling

### 12.1 Cloud Storage Failures

- [ ] **12.1.1** Set S3 credentials to invalid values. Upload a file. **Expected:** Graceful error, fallback to local. **(M)**
- [ ] **12.1.2** Set S3 bucket to non-existent bucket. Upload a file. **Expected:** Error message. **(M)**

### 12.2 Payment Gateway Failures

- [ ] **12.2.1** Stripe: Use expired API keys. Attempt checkout. **Expected:** Clear error message. **(M)**
- [ ] **12.2.2** PayPal: Use invalid credentials. Attempt checkout. **Expected:** Clear error message. **(M)**
- [ ] **12.2.3** Razorpay: Use invalid API key. Attempt checkout. **Expected:** Clear error message. **(M)**

### 12.3 Subscription Edge Cases

- [ ] **12.3.1** Vendor with active subscription: admin deletes the plan. **Expected:** Existing subscription continues until end of period. **(M)**
- [ ] **12.3.2** Vendor attempts to subscribe to 2 plans simultaneously. **Expected:** Only 1 active subscription allowed. **(M)**

### 12.4 Webhook Replay

- [ ] **12.4.1** Replay a Stripe `payment_intent.succeeded` webhook with a previously processed event. **Expected:** Idempotent (no duplicate orders). **(API)**
- [ ] **12.4.2** Replay a PayPal IPN for an already-captured payment. **Expected:** Idempotent. **(API)**

---

## Phase 13: Responsive -- Mobile (390px)

- [ ] **13.1** Analytics dashboard at 390px. Charts resize, no horizontal overflow. **(A)**
- [ ] **13.2** Vendor wallet tab at 390px. Balance and transactions readable. **(A)**
- [ ] **13.3** Subscription plan selection at 390px. Plan cards stack vertically. **(A)**
- [ ] **13.4** Stripe Connect onboarding return page at 390px. Status displays correctly. **(A)**
- [ ] **13.5** Razorpay payment modal at 390px. Modal is full-width and usable. **(A)**

---

## Phase 14: Deactivation & Uninstall

### 14.1 Pro Deactivation

- [ ] **14.1.1** Deactivate Pro plugin. Verify no PHP fatal errors. **(A)**
- [ ] **14.1.2** Verify Pro database tables are PRESERVED. **(DB)**
- [ ] **14.1.3** Verify Free plugin continues to work without Pro. **(A)**
- [ ] **14.1.4** Reactivate Pro. Verify all Pro data and settings are intact. **(A)**

### 14.2 Pro Uninstall

- [ ] **14.2.1** Deactivate and delete Pro plugin. **(M)**
- [ ] **14.2.2** Verify Free plugin remains functional. **(A)**
- [ ] **14.2.3** Verify Pro tables are cleaned up if "delete data on uninstall" is enabled. **(DB)**
- [ ] **14.2.4** Verify Free plugin's tables and data are untouched. **(DB)**

---

## Summary

| Phase | Items | Priority |
|-------|-------|----------|
| 1. Activation & License | 20 | Critical |
| 2. E-Commerce Adapters | 25 | Critical |
| 3. Payment Gateways | 23 | Critical |
| 4. Wallet System | 16 | High |
| 5. Analytics | 12 | High |
| 6. Cloud Storage | 11 | High |
| 7. Pro Feature Modules | 52 | High |
| 8. Expanded Limits | 10 | Medium |
| 9. Pro Templates | 8 | Medium |
| 10. Pro REST API | 46 | Medium |
| 11. Integration Smoke Tests | 9 | Medium |
| 12. Edge Cases | 8 | Medium |
| 13. Responsive | 5 | Medium |
| 14. Deactivation | 6 | Low |

**Total: ~251 test items**
