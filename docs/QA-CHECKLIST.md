# WP Sell Services -- Free Plugin QA Checklist

**Version:** 1.0.0
**Last Updated:** 2026-04-01
**Site URL:** `http://wss.local`
**Admin Login:** `http://wss.local/?autologin=1`

---

## How to Use This Checklist

1. Work through each phase sequentially -- they mirror the real user journey.
2. Each item has a checkbox, step description, expected result, and test type.
3. **Manual** = perform in browser. **Automated** = run via Playwright MCP browser tools.
4. Mark items with `[x]` as you complete them. Add notes for failures.
5. After completing all phases, file bugs for any failures with the phase and item number.

**Test type legend:**
- **M** = Manual browser test
- **A** = Automated (Playwright MCP)
- **DB** = Database verification (WP-CLI or phpMyAdmin)
- **API** = REST API call (curl/Postman/Playwright)

---

## Phase 1: Fresh Install & Activation

### 1.1 Plugin Activation

- [ ] **1.1.1** Activate WP Sell Services from Plugins page. **Expected:** Plugin activates without errors, no PHP warnings in debug.log. **(A)**
- [ ] **1.1.2** Check that the `wpss_vendor` role is created with correct capabilities (`wpss_vendor`, `wpss_manage_services`, `wpss_manage_orders`, `wpss_view_analytics`, `wpss_respond_to_requests`, `upload_files`, `edit_posts`). **(DB)**
- [ ] **1.1.3** Check admin role has `wpss_manage_settings`, `wpss_manage_disputes`, `wpss_manage_vendors` capabilities. **(DB)**
- [ ] **1.1.4** Verify activation redirect to Setup Wizard fires on first activation. **(A)**

### 1.2 Setup Wizard

- [ ] **1.2.1** Navigate to **WP Sell Services > Setup Wizard**. Wizard loads with Step 1 (Welcome). **(A)**
- [ ] **1.2.2** Complete Step 1: Select currency (e.g., USD). Click Next. **(M)**
- [ ] **1.2.3** Complete Step 2: Set commission rate (e.g., 10%). Click Next. **(M)**
- [ ] **1.2.4** Complete Step 3: Configure vendor registration mode (Open). Click Next. **(M)**
- [ ] **1.2.5** Complete Step 4: Review summary and click Finish. **(M)**
- [ ] **1.2.6** Verify `wpss_setup_wizard_completed` option is set to `true`. **(DB)**

### 1.3 Pages Created

- [ ] **1.3.1** Verify "Services" page exists with `[wpss_services]` shortcode. **(DB)**
- [ ] **1.3.2** Verify "Dashboard" page exists with `[wpss_dashboard]` shortcode. **(DB)**
- [ ] **1.3.3** Verify "Become a Vendor" page exists with `[wpss_vendor_registration]` shortcode. **(DB)**
- [ ] **1.3.4** Verify "Checkout" page exists with `[wpss_checkout]` shortcode. **(DB)**
- [ ] **1.3.5** Verify "Cart" page exists with `[wpss_cart]` shortcode. **(DB)**
- [ ] **1.3.6** Verify `wpss_pages` option maps all 5 page slugs to valid post IDs. **(DB)**

### 1.4 Database Tables Created

Verify all 17 custom tables exist with correct schema:

- [ ] **1.4.1** `wpss_service_packages` -- service pricing tiers **(DB)**
- [ ] **1.4.2** `wpss_service_addons` -- service extras **(DB)**
- [ ] **1.4.3** `wpss_orders` -- service orders **(DB)**
- [ ] **1.4.4** `wpss_order_requirements` -- buyer requirement submissions **(DB)**
- [ ] **1.4.5** `wpss_conversations` -- order conversation threads **(DB)**
- [ ] **1.4.6** `wpss_messages` -- individual messages within conversations **(DB)**
- [ ] **1.4.7** `wpss_deliveries` -- final file deliveries **(DB)**
- [ ] **1.4.8** `wpss_extension_requests` -- deadline extension requests **(DB)**
- [ ] **1.4.9** `wpss_reviews` -- ratings and reviews **(DB)**
- [ ] **1.4.10** `wpss_disputes` -- dispute cases **(DB)**
- [ ] **1.4.11** `wpss_dispute_messages` -- dispute conversation messages **(DB)**
- [ ] **1.4.12** `wpss_proposals` -- vendor proposals on buyer requests **(DB)**
- [ ] **1.4.13** `wpss_vendor_profiles` -- vendor profile data **(DB)**
- [ ] **1.4.14** `wpss_portfolio_items` -- vendor portfolio pieces **(DB)**
- [ ] **1.4.15** `wpss_notifications` -- in-app notifications **(DB)**
- [ ] **1.4.16** `wpss_wallet_transactions` -- earnings ledger **(DB)**
- [ ] **1.4.17** `wpss_withdrawals` -- withdrawal requests **(DB)**

### 1.5 Default Settings Populated

- [ ] **1.5.1** `wpss_general` option exists with `platform_name`, `currency`, `ecommerce_platform` keys. **(DB)**
- [ ] **1.5.2** `wpss_commission` option has `commission_rate` and `enable_vendor_rates` keys. **(DB)**
- [ ] **1.5.3** `wpss_payouts` option has `min_withdrawal`, `clearance_days` keys. **(DB)**
- [ ] **1.5.4** `wpss_tax` option has `enable_tax`, `tax_rate` keys. **(DB)**
- [ ] **1.5.5** `wpss_vendor` option has `vendor_registration`, `max_services_per_vendor` keys. **(DB)**
- [ ] **1.5.6** `wpss_orders` option has `auto_complete_days`, `revision_limit`, `dispute_window_days` keys. **(DB)**
- [ ] **1.5.7** `wpss_notifications` option has all `notify_*` keys. **(DB)**
- [ ] **1.5.8** `wpss_advanced` option has `delete_data_on_uninstall` key. **(DB)**

### 1.6 Demo Content

- [ ] **1.6.1** Navigate to **WP Sell Services > Tools > Demo Content**. Click "Import Demo Content". **(M)**
- [ ] **1.6.2** Verify demo services are created (check Services > All Services in admin). **(A)**
- [ ] **1.6.3** Verify demo categories are created. **(A)**
- [ ] **1.6.4** Click "Delete Demo Content". Verify demo services and categories are removed. **(M)**

---

## Phase 2: Vendor Registration & Profile

### 2.1 Vendor Registration -- Open Mode

- [ ] **2.1.1** Set vendor registration to "Open" in **Settings > Vendor**. **(M)**
- [ ] **2.1.2** Log out. Register a new WordPress user (e.g., `vendor1`). **(A)**
- [ ] **2.1.3** Visit the "Become a Vendor" page as `vendor1`. Fill form and submit. **(A)**
- [ ] **2.1.4** Verify user is immediately assigned the `wpss_vendor` role. **(DB)**
- [ ] **2.1.5** Verify vendor profile row is created in `wpss_vendor_profiles`. **(DB)**

### 2.2 Vendor Registration -- Approval Mode

- [ ] **2.2.1** Set vendor registration to "Approval Required" in settings. **(M)**
- [ ] **2.2.2** Register a new user (`vendor2`). Submit vendor application. **(A)**
- [ ] **2.2.3** Verify user does NOT get `wpss_vendor` role immediately. **(DB)**
- [ ] **2.2.4** As admin, go to **WP Sell Services > Vendors**. Find `vendor2`. Click "Approve". **(M)**
- [ ] **2.2.5** Verify `vendor2` now has `wpss_vendor` role. **(DB)**

### 2.3 Vendor Registration -- Closed Mode

- [ ] **2.3.1** Set vendor registration to "Closed" in settings. **(M)**
- [ ] **2.3.2** Visit "Become a Vendor" page as a logged-in non-vendor user. **(A)**
- [ ] **2.3.3** Verify registration form is hidden and a "registration closed" message is shown. **(A)**

### 2.4 Vendor Dashboard

- [ ] **2.4.1** Log in as `vendor1` (`?autologin=vendor1`). Visit the Dashboard page. **(A)**
- [ ] **2.4.2** Verify dashboard tabs load: Overview, Services, Orders, Earnings, Messages, Profile. **(A)**
- [ ] **2.4.3** Verify earnings summary shows $0.00 (no orders yet). **(A)**

### 2.5 Vendor Profile Update

- [ ] **2.5.1** Navigate to Dashboard > Profile tab. **(A)**
- [ ] **2.5.2** Update bio text. Click Save. Verify bio persists on reload. **(M)**
- [ ] **2.5.3** Update tagline. Click Save. Verify tagline persists. **(M)**
- [ ] **2.5.4** Upload avatar image. Click Save. Verify avatar displays. **(M)**
- [ ] **2.5.5** Add social links (website, Twitter, LinkedIn). Click Save. Verify they persist. **(M)**

### 2.6 Vacation Mode

- [ ] **2.6.1** On Dashboard > Profile, toggle "Vacation Mode" ON. Enter a vacation message. Save. **(M)**
- [ ] **2.6.2** Visit vendor's public profile page. Verify vacation message is displayed. **(A)**
- [ ] **2.6.3** Verify vendor's services show as paused / not purchasable during vacation. **(A)**
- [ ] **2.6.4** Toggle vacation mode OFF. Verify services are purchasable again. **(M)**

### 2.7 Portfolio CRUD

- [ ] **2.7.1** Navigate to Dashboard > Portfolio tab. Click "Add Portfolio Item". **(M)**
- [ ] **2.7.2** Fill title, description, upload image. Save. Verify item appears in portfolio list. **(M)**
- [ ] **2.7.3** Edit the portfolio item. Change title. Save. Verify title updates. **(M)**
- [ ] **2.7.4** Delete the portfolio item. Verify it is removed from the list. **(M)**
- [ ] **2.7.5** Add 3 items. Verify they display on vendor's public profile page. **(A)**

---

## Phase 3: Service Creation

### 3.1 Service Wizard -- Basic Info (Step 1)

- [ ] **3.1.1** As vendor, navigate to Dashboard > Services > "Create New Service". Wizard opens at Step 1. **(A)**
- [ ] **3.1.2** Enter service title (e.g., "Logo Design"). **(M)**
- [ ] **3.1.3** Enter service description (rich text). **(M)**
- [ ] **3.1.4** Select a category from dropdown. **(M)**
- [ ] **3.1.5** Add tags. **(M)**
- [ ] **3.1.6** Click "Next". Wizard advances to Step 2. **(M)**

### 3.2 Service Wizard -- Packages (Step 2)

- [ ] **3.2.1** Fill Basic package: name, description, price ($50), delivery days (3), revisions (1), features. **(M)**
- [ ] **3.2.2** Fill Standard package: price ($100), delivery days (5), revisions (2). **(M)**
- [ ] **3.2.3** Fill Premium package: price ($200), delivery days (7), revisions (5). **(M)**
- [ ] **3.2.4** Verify all 3 packages display correctly in the live preview panel. **(A)**
- [ ] **3.2.5** Click "Next". Wizard advances to Step 3. **(M)**

### 3.3 Service Wizard -- Add-ons (Step 3)

- [ ] **3.3.1** Add a checkbox add-on (e.g., "Express Delivery +$20"). Set price. **(M)**
- [ ] **3.3.2** Add a dropdown add-on (e.g., "File Format" with options PNG, SVG, AI). **(M)**
- [ ] **3.3.3** Add a text add-on (e.g., "Special Instructions"). **(M)**
- [ ] **3.3.4** Verify free version limits add-ons to 3 max. Fourth add-on shows upgrade prompt. **(M)**
- [ ] **3.3.5** Click "Next". **(M)**

### 3.4 Service Wizard -- Media (Step 4)

- [ ] **3.4.1** Upload 1 gallery image. Verify thumbnail preview appears. **(M)**
- [ ] **3.4.2** Upload 3 more images (total 4). Verify all 4 display. **(M)**
- [ ] **3.4.3** Attempt to upload a 5th image. Verify free version blocks it (limit: 4). **(M)**
- [ ] **3.4.4** Remove 1 image. Verify it disappears from gallery. **(M)**
- [ ] **3.4.5** Add a video embed URL (YouTube or Vimeo). Verify preview renders. **(M)**
- [ ] **3.4.6** Attempt to add a 2nd video. Verify free version limits to 1. **(M)**
- [ ] **3.4.7** Click "Next". **(M)**

### 3.5 Service Wizard -- FAQs (Step 5)

- [ ] **3.5.1** Add FAQ item: question + answer. **(M)**
- [ ] **3.5.2** Add up to 5 FAQ items. Verify free version limits to 5 max. **(M)**
- [ ] **3.5.3** Click "Next". **(M)**

### 3.6 Service Wizard -- Requirements (Step 6)

- [ ] **3.6.1** Add a required text field (e.g., "Brand Name"). **(M)**
- [ ] **3.6.2** Add an optional file upload field (e.g., "Reference Files"). **(M)**
- [ ] **3.6.3** Verify free version limits requirements to 5 max. **(M)**
- [ ] **3.6.4** Click "Next". **(M)**

### 3.7 Service Wizard -- Publish (Step 7)

- [ ] **3.7.1** Review the complete service preview. Verify title, packages, gallery, FAQs render. **(A)**
- [ ] **3.7.2** Click "Publish". Verify service is created as `wpss_service` CPT with `publish` status. **(M)**
- [ ] **3.7.3** Verify `wpss_service_packages` table has 3 rows for this service. **(DB)**
- [ ] **3.7.4** Verify service appears on the Services archive page. **(A)**

### 3.8 Service Moderation (If Enabled)

- [ ] **3.8.1** Enable "Require Service Moderation" in **Settings > Vendor**. **(M)**
- [ ] **3.8.2** Create a new service as vendor. Verify it gets `pending` status instead of `publish`. **(M)**
- [ ] **3.8.3** As admin, go to **WP Sell Services > Moderation**. Find the service. Click "Approve". **(M)**
- [ ] **3.8.4** Verify service status changes to `publish`. **(A)**
- [ ] **3.8.5** Create another service. Reject it from moderation. Verify it gets `rejected` status. **(M)**

### 3.9 Service Management

- [ ] **3.9.1** As vendor, navigate to Dashboard > Services. Verify the service is listed. **(A)**
- [ ] **3.9.2** Click "Edit" on the service. Modify the title. Save. Verify title updates on the frontend. **(M)**
- [ ] **3.9.3** Toggle service status to "Paused". Verify service is not purchasable on frontend. **(A)**
- [ ] **3.9.4** Toggle service status back to "Active". Verify it is purchasable again. **(A)**
- [ ] **3.9.5** Click "Delete" on a service. Confirm deletion. Verify service is removed from listings. **(M)**

---

## Phase 4: Buyer Flow

### 4.1 Browse & Search Services

- [ ] **4.1.1** Visit the Services archive page (logged out). Verify services display in a grid. **(A)**
- [ ] **4.1.2** Filter by category. Verify only services in that category appear. **(A)**
- [ ] **4.1.3** Filter by tag. Verify only tagged services appear. **(A)**
- [ ] **4.1.4** Use the search bar. Type a keyword. Verify matching services appear. **(A)**
- [ ] **4.1.5** Verify price sorting works (low to high, high to low). **(A)**

### 4.2 Single Service Page

- [ ] **4.2.1** Click on a service. Single service page loads. **(A)**
- [ ] **4.2.2** Verify gallery images display with carousel/lightbox functionality. **(M)**
- [ ] **4.2.3** Verify video embed plays correctly. **(M)**
- [ ] **4.2.4** Verify 3 package tabs display (Basic, Standard, Premium) with correct prices and features. **(A)**
- [ ] **4.2.5** Verify FAQs section renders as accordion. **(A)**
- [ ] **4.2.6** Verify vendor card shows name, avatar, rating, level, response time. **(A)**
- [ ] **4.2.7** Verify "Reviews" section shows reviews or "No reviews yet" placeholder. **(A)**
- [ ] **4.2.8** Verify add-ons display and are checkable/selectable. **(M)**

### 4.3 Add to Cart & Checkout (Standalone + Offline)

- [ ] **4.3.1** Register/log in as a buyer user (`buyer1`). **(A)**
- [ ] **4.3.2** On service page, select "Standard" package. Check the "Express Delivery" add-on. **(M)**
- [ ] **4.3.3** Click "Add to Cart". Verify cart count updates. **(A)**
- [ ] **4.3.4** Navigate to Cart page. Verify line items, add-on, subtotal are correct. **(A)**
- [ ] **4.3.5** Click "Proceed to Checkout". Checkout page loads. **(A)**
- [ ] **4.3.6** Select "Offline Payment" gateway. **(M)**
- [ ] **4.3.7** Click "Place Order". Verify order confirmation page loads with order number. **(A)**
- [ ] **4.3.8** Verify `wpss_orders` table has a new row with `pending_payment` status. **(DB)**
- [ ] **4.3.9** Verify `wpss_conversations` table has a new conversation for this order. **(DB)**

---

## Phase 5: Order Lifecycle

### 5.1 Status: Pending Payment --> Pending Requirements

- [ ] **5.1.1** As admin, navigate to **WP Sell Services > Orders**. Find the new order. **(A)**
- [ ] **5.1.2** Click "Mark as Paid" (offline gateway admin confirmation). **(M)**
- [ ] **5.1.3** Verify order status changes to `pending_requirements`. **(A)**
- [ ] **5.1.4** Verify buyer receives "New Order" email notification. **(DB -- check wp_mail log or MailHog)**

### 5.2 Requirements Submission

- [ ] **5.2.1** Log in as `buyer1`. Navigate to Dashboard > Orders > click the order. **(A)**
- [ ] **5.2.2** Verify requirements form displays the fields defined in the service. **(A)**
- [ ] **5.2.3** Fill in "Brand Name" text field. Upload a file for "Reference Files". **(M)**
- [ ] **5.2.4** Click "Submit Requirements". Verify order status changes to `in_progress`. **(A)**
- [ ] **5.2.5** Verify `wpss_order_requirements` table has the submitted data. **(DB)**

### 5.3 Skip Requirements

- [ ] **5.3.1** Create another order that reaches `pending_requirements` status. **(M)**
- [ ] **5.3.2** As buyer, click "Skip Requirements" on that order. **(M)**
- [ ] **5.3.3** Verify order status jumps to `in_progress` without requirement data. **(A)**

### 5.4 Order Messaging

- [ ] **5.4.1** As `buyer1`, on the order detail page, type a message in the conversation box. Click Send. **(M)**
- [ ] **5.4.2** Verify message appears in the conversation thread. **(A)**
- [ ] **5.4.3** Log in as `vendor1`. Open the same order. Verify buyer's message is visible. **(A)**
- [ ] **5.4.4** As vendor, reply with a message. Verify it appears in thread. **(M)**
- [ ] **5.4.5** As vendor, send a message with a file attachment. Verify file is downloadable by buyer. **(M)**

### 5.5 File Delivery

- [ ] **5.5.1** As `vendor1`, on the order page, click "Deliver Order". **(M)**
- [ ] **5.5.2** Upload a delivery file. Add a delivery note. Click "Submit Delivery". **(M)**
- [ ] **5.5.3** Verify order status changes to `pending_approval`. **(A)**
- [ ] **5.5.4** Verify `wpss_deliveries` table has the delivery record. **(DB)**
- [ ] **5.5.5** As `buyer1`, open the order. Verify delivery file and note are visible. **(A)**

### 5.6 Buyer Approves Delivery

- [ ] **5.6.1** As `buyer1`, click "Accept Delivery". **(M)**
- [ ] **5.6.2** Verify order status changes to `completed`. **(A)**
- [ ] **5.6.3** Verify vendor's earnings are credited in `wpss_wallet_transactions` (order total minus commission). **(DB)**
- [ ] **5.6.4** Verify order completion email is sent. **(M)**

### 5.7 Revision Request

- [ ] **5.7.1** Create a new order that reaches `pending_approval` (delivery submitted). **(M)**
- [ ] **5.7.2** As buyer, click "Request Revision". Enter revision notes. Submit. **(M)**
- [ ] **5.7.3** Verify order status changes to `revision_requested`. **(A)**
- [ ] **5.7.4** As vendor, open the order. Verify revision notes are visible. **(A)**
- [ ] **5.7.5** Vendor submits a new delivery. Verify status returns to `pending_approval`. **(M)**
- [ ] **5.7.6** Verify revision count increments. Test revision limit enforcement (if configured). **(DB)**

### 5.8 Deadline Extension Request

- [ ] **5.8.1** As vendor, on an `in_progress` order, click "Request Extension". Enter days and reason. **(M)**
- [ ] **5.8.2** Verify `wpss_extension_requests` table has the request. **(DB)**
- [ ] **5.8.3** As buyer, view the order. Verify extension request notification appears. **(A)**
- [ ] **5.8.4** Accept the extension. Verify order deadline is extended. **(M)**
- [ ] **5.8.5** Create another extension request and reject it. Verify original deadline remains. **(M)**

### 5.9 Cancellation Flow

- [ ] **5.9.1** As buyer, on an `in_progress` order, click "Request Cancellation". Enter reason. **(M)**
- [ ] **5.9.2** Verify order shows cancellation pending indicator. **(A)**
- [ ] **5.9.3** As vendor, view the order. Verify cancellation request is shown. **(A)**
- [ ] **5.9.4** Vendor accepts the cancellation. Verify order status changes to `cancelled`. **(M)**
- [ ] **5.9.5** Create another cancellation request. Vendor rejects it. Verify order remains `in_progress`. **(M)**

### 5.10 Auto-Complete

- [ ] **5.10.1** Set auto-complete days to 1 in **Settings > Orders**. **(M)**
- [ ] **5.10.2** Create an order that reaches `pending_approval`. Wait for cron (or trigger WP-Cron manually). **(M)**
- [ ] **5.10.3** Verify the order auto-completes after the configured days. **(DB)**

---

## Phase 6: Reviews & Disputes

### 6.1 Submit Review

- [ ] **6.1.1** As buyer, navigate to a `completed` order. Click "Leave Review". **(M)**
- [ ] **6.1.2** Select star rating (e.g., 4 stars). **(M)**
- [ ] **6.1.3** Fill multi-criteria ratings: Communication (5), Quality (4), Delivery (3). **(M)**
- [ ] **6.1.4** Write review text. Click Submit. **(M)**
- [ ] **6.1.5** Verify `wpss_reviews` table has the review record with correct ratings. **(DB)**
- [ ] **6.1.6** Verify review appears on the service single page. **(A)**

### 6.2 Review Moderation

- [ ] **6.2.1** Enable review moderation in settings (if applicable). **(M)**
- [ ] **6.2.2** Submit a new review. Verify it does NOT appear publicly until approved. **(A)**
- [ ] **6.2.3** As admin, approve the review from the moderation queue. Verify it now displays. **(M)**

### 6.3 Vendor Reply to Review

- [ ] **6.3.1** As vendor, navigate to the service page or dashboard reviews section. **(A)**
- [ ] **6.3.2** Click "Reply" on a review. Write a reply. Submit. **(M)**
- [ ] **6.3.3** Verify vendor reply appears beneath the buyer review on the service page. **(A)**

### 6.4 Mark Review Helpful

- [ ] **6.4.1** As any logged-in user (not the reviewer), view a review on the service page. **(A)**
- [ ] **6.4.2** Click "Helpful" button. Verify helpful count increments by 1. **(M)**
- [ ] **6.4.3** Click "Helpful" again. Verify it toggles off (or prevents double-counting). **(M)**

### 6.5 Open Dispute

- [ ] **6.5.1** As buyer, on an `in_progress` or `pending_approval` order, click "Open Dispute". **(M)**
- [ ] **6.5.2** Select a dispute reason. Write description. Click Submit. **(M)**
- [ ] **6.5.3** Verify order status changes to `disputed`. **(A)**
- [ ] **6.5.4** Verify `wpss_disputes` table has the dispute record. **(DB)**

### 6.6 Add Evidence

- [ ] **6.6.1** As buyer, on the dispute page, upload evidence file(s). Add notes. Submit. **(M)**
- [ ] **6.6.2** Verify evidence appears in `wpss_dispute_messages`. **(DB)**
- [ ] **6.6.3** As vendor, add counter-evidence. Verify it appears in the dispute thread. **(M)**

### 6.7 Admin Mediation

- [ ] **6.7.1** As admin, navigate to **WP Sell Services > Disputes**. Find the dispute. **(A)**
- [ ] **6.7.2** View dispute details: order info, both parties' evidence, messages. **(A)**
- [ ] **6.7.3** Post an admin message in the dispute thread. Verify both parties can see it. **(M)**

### 6.8 Resolution Types

- [ ] **6.8.1** Resolve a dispute as "Full Refund". Verify order is cancelled and buyer refund is noted. **(M)**
- [ ] **6.8.2** Open another dispute. Resolve as "Partial Refund". Verify partial amount logged. **(M)**
- [ ] **6.8.3** Open another dispute. Resolve as "Complete Order" (in vendor's favor). Verify order completes. **(M)**
- [ ] **6.8.4** Open another dispute. Resolve as "Mutual Agreement". Verify status updates. **(M)**

---

## Phase 7: Earnings & Withdrawals

### 7.1 Commission Calculation

- [ ] **7.1.1** Set global commission to 15% in **Settings > Commission**. **(M)**
- [ ] **7.1.2** Complete an order for $100. Verify vendor receives $85 and platform receives $15 in `wpss_wallet_transactions`. **(DB)**
- [ ] **7.1.3** Set a per-vendor commission of 10% for `vendor1` in admin. **(M)**
- [ ] **7.1.4** Complete another $100 order. Verify vendor receives $90 (per-vendor rate overrides global). **(DB)**

### 7.2 Earnings Dashboard

- [ ] **7.2.1** As vendor, navigate to Dashboard > Earnings tab. **(A)**
- [ ] **7.2.2** Verify "Available Balance", "Pending Clearance", "Total Earned" display correct amounts. **(A)**
- [ ] **7.2.3** Verify transaction history table lists all earnings with dates and order references. **(A)**

### 7.3 Withdrawal Request

- [ ] **7.3.1** Set minimum withdrawal to $50 in **Settings > Payouts**. **(M)**
- [ ] **7.3.2** As vendor (with $85+ available), click "Request Withdrawal". Enter amount $85. **(M)**
- [ ] **7.3.3** Submit the request. Verify `wpss_withdrawals` table has the request with `pending` status. **(DB)**
- [ ] **7.3.4** Verify vendor's available balance decreases by the withdrawal amount. **(A)**

### 7.4 Admin Approve/Reject Withdrawal

- [ ] **7.4.1** As admin, go to **WP Sell Services > Withdrawals**. Find the pending request. **(A)**
- [ ] **7.4.2** Click "Approve". Verify status changes to `approved` in `wpss_withdrawals`. **(DB)**
- [ ] **7.4.3** Create another withdrawal request. Reject it. Verify status = `rejected` and balance is restored. **(M)**

### 7.5 Auto-Withdrawal

- [ ] **7.5.1** Enable auto-withdrawal in **Settings > Payouts**. Set frequency to "Weekly". **(M)**
- [ ] **7.5.2** Trigger WP-Cron or wait for next scheduled run. **(M)**
- [ ] **7.5.3** Verify auto-withdrawal creates a `wpss_withdrawals` record for eligible vendors. **(DB)**

---

## Phase 8: Buyer Requests

### 8.1 Post Buyer Request

- [ ] **8.1.1** As buyer, navigate to the Buyer Requests page (or Dashboard > "Post a Request"). **(A)**
- [ ] **8.1.2** Fill in: title, description, budget range, deadline, category. **(M)**
- [ ] **8.1.3** Click "Post Request". Verify `wpss_request` CPT is created. **(DB)**
- [ ] **8.1.4** Verify the request appears on the public Buyer Requests listing page. **(A)**

### 8.2 Vendor Submit Proposal

- [ ] **8.2.1** As vendor, browse the Buyer Requests page. Find the request. **(A)**
- [ ] **8.2.2** Click "Submit Proposal". Fill in: cover letter, price, delivery days. **(M)**
- [ ] **8.2.3** Submit. Verify `wpss_proposals` table has the proposal. **(DB)**
- [ ] **8.2.4** Verify the request shows proposal count (e.g., "1 Proposal"). **(A)**

### 8.3 Accept/Reject Proposal

- [ ] **8.3.1** As buyer, view the request detail page. See vendor's proposal. **(A)**
- [ ] **8.3.2** Click "Accept Proposal". Verify an order is created from the proposal. **(M)**
- [ ] **8.3.3** Verify proposal status updates to `accepted` in `wpss_proposals`. **(DB)**
- [ ] **8.3.4** Create another request with proposals. Reject a proposal. Verify status = `rejected`. **(M)**

### 8.4 Request Management

- [ ] **8.4.1** As buyer, edit the request from dashboard. Change description. Save. Verify update persists. **(M)**
- [ ] **8.4.2** Close the request. Verify it is no longer visible to vendors. **(M)**
- [ ] **8.4.3** Reopen the request. Verify it is visible again. **(M)**
- [ ] **8.4.4** Delete the request. Verify it is removed from listings. **(M)**

---

## Phase 9: Admin Operations

### 9.1 Orders Management

- [ ] **9.1.1** Navigate to **WP Sell Services > Orders**. Verify orders list loads with filters (status, date). **(A)**
- [ ] **9.1.2** Click on an order. Verify order detail page shows all info (service, buyer, vendor, timeline). **(A)**
- [ ] **9.1.3** Manually change order status from the admin (e.g., `in_progress` to `completed`). Verify it saves. **(M)**
- [ ] **9.1.4** Add an admin note to the order. Verify it is saved in the `meta` field. **(M)**

### 9.2 Vendors Management

- [ ] **9.2.1** Navigate to **WP Sell Services > Vendors**. Verify vendors list with status, services count, earnings. **(A)**
- [ ] **9.2.2** Click on a vendor. View vendor detail page. **(A)**
- [ ] **9.2.3** Change vendor's custom commission rate. Save. Verify it persists. **(M)**
- [ ] **9.2.4** Suspend a vendor. Verify their services become unpurchasable. **(M)**
- [ ] **9.2.5** Reactivate the vendor. Verify services are purchasable again. **(M)**

### 9.3 Service Moderation Page

- [ ] **9.3.1** Navigate to **WP Sell Services > Moderation**. Verify pending services are listed. **(A)**
- [ ] **9.3.2** Approve a pending service. Verify it publishes. **(M)**
- [ ] **9.3.3** Reject a pending service with feedback. Verify vendor can see rejection reason. **(M)**

### 9.4 Withdrawals Page

- [ ] **9.4.1** Navigate to **WP Sell Services > Withdrawals**. Verify list with pending/approved/rejected filters. **(A)**
- [ ] **9.4.2** Bulk approve multiple withdrawals. Verify all change to `approved`. **(M)**

### 9.5 Disputes Page

- [ ] **9.5.1** Navigate to **WP Sell Services > Disputes**. Verify list with status filters. **(A)**
- [ ] **9.5.2** Click on a dispute. Verify admin mediation panel loads. **(A)**

### 9.6 Settings -- All Tabs

- [ ] **9.6.1** Navigate to **WP Sell Services > Settings**. General tab loads. **(A)**
- [ ] **9.6.2** Test each settings tab loads without errors: General, Commission, Payouts, Tax, Vendor, Orders, Notifications, Advanced. **(A)**
- [ ] **9.6.3** Change a setting in each tab. Save. Reload. Verify the setting persists. **(M)**
- [ ] **9.6.4** Test tax configuration: enable tax, set rate, set label. Verify tax appears on checkout. **(M)**

### 9.7 Manual Order Creation

- [ ] **9.7.1** Navigate to **WP Sell Services > Orders > Add New** (if available). **(M)**
- [ ] **9.7.2** Select buyer, vendor, service, package. Set status. Save. **(M)**
- [ ] **9.7.3** Verify order appears in the orders list and is accessible by both buyer and vendor. **(A)**

---

## Phase 10: Frontend Display

### 10.1 Service Archive Page

- [ ] **10.1.1** Visit the Services page. Verify grid layout with service cards (image, title, price, vendor, rating). **(A)**
- [ ] **10.1.2** Verify pagination works when there are more than 12 services. **(A)**
- [ ] **10.1.3** Verify empty state message when no services exist. **(A)**

### 10.2 Category & Tag Filtering

- [ ] **10.2.1** Click a category link. Verify URL changes and only that category's services display. **(A)**
- [ ] **10.2.2** Click a tag link. Verify filtered results. **(A)**
- [ ] **10.2.3** Verify breadcrumb navigation shows correct category hierarchy. **(A)**

### 10.3 Search with Autocomplete

- [ ] **10.3.1** Type in the service search bar. Verify autocomplete suggestions appear after 2+ characters. **(A)**
- [ ] **10.3.2** Click a suggestion. Verify navigation to the correct service page. **(M)**
- [ ] **10.3.3** Submit a search query. Verify search results page displays matching services. **(A)**

### 10.4 Vendor Profile Page

- [ ] **10.4.1** Visit a vendor's public profile URL. **(A)**
- [ ] **10.4.2** Verify profile shows: avatar, name, tagline, bio, seller level badge, rating, social links. **(A)**
- [ ] **10.4.3** Verify vendor's active services are listed on the profile. **(A)**
- [ ] **10.4.4** Verify portfolio items display. **(A)**

### 10.5 Vendor Directory

- [ ] **10.5.1** Visit the vendor directory page (if exists via shortcode `[wpss_vendors]`). **(A)**
- [ ] **10.5.2** Verify vendor cards display with name, avatar, rating, services count. **(A)**
- [ ] **10.5.3** Verify clicking a vendor card navigates to their profile. **(A)**

### 10.6 SEO Schema Markup

- [ ] **10.6.1** View page source of a single service page. Verify JSON-LD `Product` or `Service` schema is present. **(A)**
- [ ] **10.6.2** Verify schema includes `name`, `description`, `offers` (price), `aggregateRating`. **(A)**
- [ ] **10.6.3** Validate schema using Google Rich Results Test or Schema.org validator. **(M)**

### 10.7 Responsive -- Mobile (390px)

- [ ] **10.7.1** Resize browser to 390px width. Visit Services archive. Verify single-column layout, no horizontal overflow. **(A)**
- [ ] **10.7.2** Visit single service page at 390px. Verify packages stack vertically, gallery is swipeable. **(A)**
- [ ] **10.7.3** Visit vendor dashboard at 390px. Verify tabs are accessible (horizontal scroll or hamburger). **(A)**
- [ ] **10.7.4** Visit checkout page at 390px. Verify form fields are full-width and buttons are tappable. **(A)**
- [ ] **10.7.5** Complete a service wizard flow at 390px. Verify all steps are usable. **(M)**

---

## Phase 11: Notifications

### 11.1 Email Notifications

Verify each email type fires correctly when triggered. Check `wp_mail` log, MailHog, or debug.log.

- [ ] **11.1.1** `new_order` -- Fires when a new order is placed. Goes to vendor. **(M)**
- [ ] **11.1.2** `requirements_submitted` -- Fires when buyer submits requirements. Goes to vendor. **(M)**
- [ ] **11.1.3** `order_in_progress` -- Fires when order starts. Goes to buyer. **(M)**
- [ ] **11.1.4** `delivery_ready` -- Fires when vendor submits delivery. Goes to buyer. **(M)**
- [ ] **11.1.5** `order_completed` -- Fires when order completes. Goes to both parties. **(M)**
- [ ] **11.1.6** `revision_requested` -- Fires when buyer requests revision. Goes to vendor. **(M)**
- [ ] **11.1.7** `new_message` -- Fires when a new conversation message is sent. Goes to recipient. **(M)**
- [ ] **11.1.8** `order_cancelled` -- Fires when order is cancelled. Goes to both parties. **(M)**
- [ ] **11.1.9** `dispute_opened` -- Fires when a dispute is opened. Goes to other party + admin. **(M)**
- [ ] **11.1.10** `cancellation_requested` -- Fires when buyer requests cancellation. Goes to vendor. **(M)**
- [ ] **11.1.11** `requirements_reminder` -- Fires as a reminder for pending requirements. Goes to buyer. **(M)**
- [ ] **11.1.12** `seller_level_promotion` -- Fires when vendor levels up. Goes to vendor. **(M)**
- [ ] **11.1.13** `withdrawal_requested` -- Fires when vendor requests withdrawal. Goes to admin. **(M)**
- [ ] **11.1.14** `withdrawal_approved` -- Fires when admin approves withdrawal. Goes to vendor. **(M)**
- [ ] **11.1.15** `withdrawal_rejected` -- Fires when admin rejects withdrawal. Goes to vendor. **(M)**
- [ ] **11.1.16** `proposal_submitted` -- Fires when vendor submits a proposal. Goes to buyer. **(M)**
- [ ] **11.1.17** `proposal_accepted` -- Fires when buyer accepts a proposal. Goes to vendor. **(M)**
- [ ] **11.1.18** `vendor_contact` -- Fires when buyer contacts vendor. Goes to vendor. **(M)**
- [ ] **11.1.19** `moderation_approved` -- Fires when service is approved. Goes to vendor. **(M)**
- [ ] **11.1.20** `moderation_rejected` -- Fires when service is rejected. Goes to vendor. **(M)**
- [ ] **11.1.21** `test_email` -- Admin test email from Settings > Notifications. Goes to admin. **(M)**

### 11.2 In-App Notification Center

- [ ] **11.2.1** Trigger an event (e.g., new order). Log in as the recipient. **(M)**
- [ ] **11.2.2** Verify notification bell/icon shows unread count. **(A)**
- [ ] **11.2.3** Click the bell. Verify notification list drops down with unread notifications. **(A)**
- [ ] **11.2.4** Click a notification. Verify it navigates to the relevant page (e.g., order detail). **(M)**
- [ ] **11.2.5** Click "Mark as Read" on a single notification. Verify unread count decrements. **(M)**
- [ ] **11.2.6** Click "Mark All as Read". Verify unread count goes to 0. **(M)**

---

## Phase 12: Blocks & Shortcodes

### 12.1 Gutenberg Blocks

Test each block by inserting it into a page via the Block Editor.

- [ ] **12.1.1** **Service Grid** block -- Insert. Verify services grid renders on frontend. **(A)**
- [ ] **12.1.2** **Service Search** block -- Insert. Verify search bar renders on frontend. **(A)**
- [ ] **12.1.3** **Service Categories** block -- Insert. Verify category list/grid renders. **(A)**
- [ ] **12.1.4** **Featured Services** block -- Insert. Verify featured services display. **(A)**
- [ ] **12.1.5** **Seller Card** block -- Insert. Configure vendor. Verify vendor card renders. **(A)**
- [ ] **12.1.6** **Buyer Requests** block -- Insert. Verify buyer requests list renders. **(A)**

### 12.2 Shortcodes

Test each shortcode by adding it to a page and viewing the frontend.

- [ ] **12.2.1** `[wpss_services]` -- Services grid page. **(A)**
- [ ] **12.2.2** `[wpss_dashboard]` -- Unified vendor/buyer dashboard. **(A)**
- [ ] **12.2.3** `[wpss_service_search]` -- Search bar widget. **(A)**
- [ ] **12.2.4** `[wpss_featured_services]` -- Featured services list. **(A)**
- [ ] **12.2.5** `[wpss_service_categories]` -- Category listing. **(A)**
- [ ] **12.2.6** `[wpss_vendors]` -- Vendor directory grid. **(A)**
- [ ] **12.2.7** `[wpss_vendor_profile]` -- Single vendor profile. **(A)**
- [ ] **12.2.8** `[wpss_top_vendors]` -- Top-rated vendors. **(A)**
- [ ] **12.2.9** `[wpss_buyer_requests]` -- Buyer requests listing. **(A)**
- [ ] **12.2.10** `[wpss_post_request]` -- Post request form. **(A)**
- [ ] **12.2.11** `[wpss_my_orders]` -- Orders listing for current user. **(A)**
- [ ] **12.2.12** `[wpss_order_details]` -- Single order detail. **(A)**
- [ ] **12.2.13** `[wpss_vendor_registration]` -- Vendor registration form. **(A)**
- [ ] **12.2.14** `[wpss_login]` -- Login form. **(A)**
- [ ] **12.2.15** `[wpss_register]` -- Registration form. **(A)**
- [ ] **12.2.16** `[wpss_cart]` -- Cart page. **(A)**
- [ ] **12.2.17** `[wpss_checkout]` -- Checkout page (Standalone adapter). **(A)**
- [ ] **12.2.18** `[wpss_account]` -- Account page (Standalone adapter). **(A)**
- [ ] **12.2.19** `[wpss_service_wizard]` -- Service creation wizard (vendor only). **(A)**

---

## Phase 13: REST API Smoke Tests

Use `curl` or Playwright to hit each endpoint group. Test auth, success, and error cases.

**Base URL:** `http://wss.local/wp-json/wpss/v1`

### 13.1 Auth Endpoints

- [ ] **13.1.1** `POST /auth/login` with valid credentials. **Expected:** 200 + user data + token. **(API)**
- [ ] **13.1.2** `POST /auth/login` with invalid credentials. **Expected:** 401 error. **(API)**
- [ ] **13.1.3** `POST /auth/register` with valid data. **Expected:** 201 + new user. **(API)**
- [ ] **13.1.4** `POST /auth/register` with duplicate email. **Expected:** 400 error. **(API)**
- [ ] **13.1.5** `POST /auth/logout`. **Expected:** 200 success. **(API)**

### 13.2 Services Endpoints

- [ ] **13.2.1** `GET /services` (unauthenticated). **Expected:** 200 + paginated service list. **(API)**
- [ ] **13.2.2** `GET /services/{id}` for existing service. **Expected:** 200 + service detail. **(API)**
- [ ] **13.2.3** `POST /services` as vendor (with auth). **Expected:** 201 + created service. **(API)**
- [ ] **13.2.4** `PUT /services/{id}` as service owner. **Expected:** 200 + updated service. **(API)**
- [ ] **13.2.5** `DELETE /services/{id}` as service owner. **Expected:** 200 success. **(API)**
- [ ] **13.2.6** `GET /services` with search param `?search=logo`. **Expected:** filtered results. **(API)**
- [ ] **13.2.7** `POST /services` as non-vendor. **Expected:** 403 forbidden. **(API)**

### 13.3 Orders Endpoints

- [ ] **13.3.1** `GET /orders` as vendor. **Expected:** 200 + vendor's orders. **(API)**
- [ ] **13.3.2** `GET /orders/{id}` as order participant. **Expected:** 200 + order detail. **(API)**
- [ ] **13.3.3** `GET /orders/{id}` as non-participant. **Expected:** 403 forbidden. **(API)**
- [ ] **13.3.4** `POST /orders/{id}/status` to transition status. **Expected:** 200 + updated status. **(API)**
- [ ] **13.3.5** `POST /orders/{id}/requirements` with requirement data. **Expected:** 200. **(API)**
- [ ] **13.3.6** `POST /orders/{id}/cancel` as buyer. **Expected:** 200. **(API)**

### 13.4 Cart & Checkout

- [ ] **13.4.1** `POST /cart` with service_id and package_id. **Expected:** 200 + cart contents. **(API)**
- [ ] **13.4.2** `GET /cart`. **Expected:** 200 + current cart items. **(API)**
- [ ] **13.4.3** `DELETE /cart/{item_id}`. **Expected:** 200 + updated cart. **(API)**
- [ ] **13.4.4** `POST /cart/checkout` with payment method. **Expected:** 200 + order created. **(API)**

### 13.5 Reviews Endpoints

- [ ] **13.5.1** `POST /reviews` on a completed order. **Expected:** 201 + review. **(API)**
- [ ] **13.5.2** `GET /reviews?service_id={id}`. **Expected:** 200 + reviews for service. **(API)**
- [ ] **13.5.3** `POST /reviews` on non-completed order. **Expected:** 400 error. **(API)**

### 13.6 Conversations Endpoints

- [ ] **13.6.1** `GET /conversations/{order_id}`. **Expected:** 200 + messages. **(API)**
- [ ] **13.6.2** `POST /conversations/{order_id}` with message text. **Expected:** 201. **(API)**
- [ ] **13.6.3** `POST /conversations/{order_id}` with file attachment. **Expected:** 201. **(API)**

### 13.7 Disputes Endpoints

- [ ] **13.7.1** `POST /disputes` with order_id and reason. **Expected:** 201 + dispute. **(API)**
- [ ] **13.7.2** `POST /disputes/{id}/respond` with message. **Expected:** 200. **(API)**
- [ ] **13.7.3** `POST /disputes/{id}/resolve` as admin. **Expected:** 200. **(API)**

### 13.8 Buyer Requests & Proposals

- [ ] **13.8.1** `POST /buyer-requests` as buyer. **Expected:** 201 + request. **(API)**
- [ ] **13.8.2** `GET /buyer-requests`. **Expected:** 200 + list. **(API)**
- [ ] **13.8.3** `POST /proposals` as vendor on a request. **Expected:** 201 + proposal. **(API)**
- [ ] **13.8.4** `POST /proposals/{id}/accept` as request owner. **Expected:** 200. **(API)**

### 13.9 Other Endpoints

- [ ] **13.9.1** `GET /vendors` (public). **Expected:** 200 + vendor list. **(API)**
- [ ] **13.9.2** `GET /notifications` (authenticated). **Expected:** 200 + notification list. **(API)**
- [ ] **13.9.3** `GET /earnings` as vendor. **Expected:** 200 + earnings summary. **(API)**
- [ ] **13.9.4** `GET /favorites` as buyer. **Expected:** 200 + favorites list. **(API)**
- [ ] **13.9.5** `POST /favorites` add a service. **Expected:** 201. **(API)**
- [ ] **13.9.6** `GET /categories`. **Expected:** 200 + category list. **(API)**
- [ ] **13.9.7** `GET /tags`. **Expected:** 200 + tag list. **(API)**
- [ ] **13.9.8** `GET /settings` (public settings). **Expected:** 200. **(API)**
- [ ] **13.9.9** `GET /me` (authenticated). **Expected:** 200 + current user. **(API)**
- [ ] **13.9.10** `GET /dashboard` (authenticated). **Expected:** 200 + dashboard stats. **(API)**
- [ ] **13.9.11** `GET /search?q=keyword`. **Expected:** 200 + search results. **(API)**

### 13.10 Batch Endpoint

- [ ] **13.10.1** `POST /batch` with 3 sub-requests. **Expected:** 200 + array of 3 responses. **(API)**
- [ ] **13.10.2** `POST /batch` with 26 sub-requests. **Expected:** 400 error (limit is 25). **(API)**
- [ ] **13.10.3** `POST /batch` mixing public and auth-required endpoints. **Expected:** mixed 200/403 responses. **(API)**

---

## Phase 14: Edge Cases & Error Handling

### 14.1 Permission Checks

- [ ] **14.1.1** Non-vendor tries to create a service. **Expected:** Access denied message. **(A)**
- [ ] **14.1.2** Buyer tries to access vendor-only dashboard tabs. **Expected:** Restricted. **(A)**
- [ ] **14.1.3** Logged-out user tries to place an order. **Expected:** Redirect to login. **(A)**
- [ ] **14.1.4** Vendor tries to review their own service. **Expected:** Blocked. **(API)**
- [ ] **14.1.5** Non-participant tries to view order details. **Expected:** Access denied. **(API)**

### 14.2 Validation

- [ ] **14.2.1** Submit service wizard with empty title. **Expected:** Validation error. **(M)**
- [ ] **14.2.2** Submit package with negative price. **Expected:** Validation error. **(M)**
- [ ] **14.2.3** Submit withdrawal exceeding available balance. **Expected:** Error message. **(M)**
- [ ] **14.2.4** Submit withdrawal below minimum amount. **Expected:** Error message. **(M)**
- [ ] **14.2.5** Submit review without star rating. **Expected:** Validation error. **(M)**

### 14.3 Concurrent Operations

- [ ] **14.3.1** Two browsers: buyer accepts delivery while vendor sends revision in the same order. **Expected:** One succeeds, other gets stale-state error. **(M)**
- [ ] **14.3.2** Submit two withdrawal requests simultaneously. **Expected:** Only one succeeds if balance insufficient for both. **(M)**

---

## Phase 15: Deactivation & Uninstall

### 15.1 Deactivation

- [ ] **15.1.1** Deactivate the plugin. Verify no PHP fatal errors. **(A)**
- [ ] **15.1.2** Verify database tables are PRESERVED after deactivation. **(DB)**
- [ ] **15.1.3** Reactivate the plugin. Verify all data is intact. **(A)**

### 15.2 Uninstall (with Delete Data ON)

- [ ] **15.2.1** Enable "Delete data on uninstall" in **Settings > Advanced**. **(M)**
- [ ] **15.2.2** Deactivate and delete the plugin. **(M)**
- [ ] **15.2.3** Verify all 17 custom tables are dropped. **(DB)**
- [ ] **15.2.4** Verify all `wpss_*` options are removed from `wp_options`. **(DB)**
- [ ] **15.2.5** Verify `wpss_vendor` role is removed. **(DB)**

---

## Summary

| Phase | Items | Priority |
|-------|-------|----------|
| 1. Fresh Install | 28 | Critical |
| 2. Vendor Registration | 19 | Critical |
| 3. Service Creation | 29 | Critical |
| 4. Buyer Flow | 9 | Critical |
| 5. Order Lifecycle | 27 | Critical |
| 6. Reviews & Disputes | 18 | High |
| 7. Earnings & Withdrawals | 13 | High |
| 8. Buyer Requests | 12 | High |
| 9. Admin Operations | 17 | High |
| 10. Frontend Display | 17 | Medium |
| 11. Notifications | 27 | Medium |
| 12. Blocks & Shortcodes | 25 | Medium |
| 13. REST API | 37 | Medium |
| 14. Edge Cases | 12 | Medium |
| 15. Deactivation | 7 | Low |

**Total: ~296 test items**
