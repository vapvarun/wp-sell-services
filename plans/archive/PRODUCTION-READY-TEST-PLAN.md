# WP Sell Services — Production-Ready UX & Functional Test Plan

## Context
Reusable test plan for verifying WP Sell Services Free + Pro before every release. Covers every user-facing action, UX quality, hover effects, notices, and premium polish. Run this after any development cycle.

**Site:** `http://wss.local`
**Auto-login:** `?autologin=1` (admin), `?autologin=username` (specific user)
**Tools:** Playwright MCP tools only (never write test scripts)

---

## Phase 0: Code-Level Flow Verification (Before Browser)

Run these checks at the code level first — faster than browser testing and catches wiring issues early.

### 0.1 Browser Alert Audit
```bash
# In both plugin directories, check for browser alert/confirm/prompt
grep -rn "alert\s*(" assets/js/ --include="*.js" | grep -v "//" | grep -v "role=\"alert\""
grep -rn "[^a-zA-Z]confirm\s*(" assets/js/ --include="*.js" | grep -v "//" | grep -v "proConfirm"
grep -rn "prompt\s*(" assets/js/ --include="*.js" | grep -v "//"
```
Expected: ZERO matches. All user dialogs must use custom modals.

### 0.2 AJAX Action Wiring Check
```bash
# Verify every wp_ajax_ registration has a matching handler method
grep -rn "wp_ajax_wpss_" src/ --include="*.php" | grep "add_action"
# Cross-check: every AJAX action name in JS has a PHP handler
grep -rn "action.*wpss_" assets/js/ --include="*.js" | grep -v "//"
```

### 0.3 Nonce Consistency Check
```bash
# Find all wp_create_nonce calls and their names
grep -rn "wp_create_nonce" src/ --include="*.php"
# Find all check_ajax_referer calls and verify names match
grep -rn "check_ajax_referer" src/ --include="*.php"
```

### 0.4 Localization Completeness
```bash
# Check for remaining hardcoded English in JS (user-facing, not comments)
grep -n "'\(Please\|Error\|Failed\|Success\|Loading\|Processing\|Submitting\)" assets/js/*.js | grep -v "i18n\." | grep -v "//"
```

### 0.5 Template → JS → PHP Action Wiring
For each interactive template, trace the full chain:
- Template button/form → `data-action` or `action` hidden input
- JS event handler → AJAX action name
- PHP `wp_ajax_` handler → response format
- JS success/error callback → notification method

Key flows to trace:
1. Add to cart: `single-service.js` → `wpss_add_to_cart` → `AjaxHandlers::add_to_cart()`
2. Submit delivery: `frontend.js` → `wpss_submit_delivery` → `AjaxHandlers::submit_delivery()`
3. Request revision: `frontend.js` → `wpss_request_revision` → `AjaxHandlers::request_revision()`
4. Open dispute: `frontend.js` → `wpss_open_dispute` → `AjaxHandlers::open_dispute()`
5. Send message: `frontend.js` → `wpss_send_message` → `AjaxHandlers::send_message()`
6. Register vendor: `unified-dashboard.js` → `wpss_register_vendor` → `AjaxHandlers::register_vendor()`
7. Request withdrawal: `dashboard.js` (Pro) → REST `POST /wallet/withdraw` → `WalletController`
8. Favorite service: `single-service.js` → `wpss_favorite_service` → `AjaxHandlers::favorite_service()`

### 0.6 CSS Hover Audit
```bash
# Count :hover rules per CSS file
for f in assets/css/*.css; do echo "$f: $(grep -c ':hover' "$f") hover rules"; done
# Check interactive elements have transitions
grep -n "transition" assets/css/design-system.css | head -10
```

### 0.7 Empty State Check
```bash
# Find all empty state renders in templates
grep -rn "wpss-empty\|no.*yet\|No.*found" templates/ --include="*.php"
```

### 0.8 Modal Accessibility Check
```bash
# Verify all modals have role="dialog" and aria-modal
grep -rn "role=\"dialog\"\|aria-modal\|aria-labelledby" templates/ --include="*.php"
# Check focus trap in JS
grep -n "focusable\|focus()\|_lastFocused\|trapFocus" assets/js/*.js
```

### 0.9 RTL Loading Check
```bash
# Verify wp_style_add_data RTL for every registered style
grep -rn "wp_style_add_data.*rtl" src/ --include="*.php"
# Count: should match number of CSS registrations
grep -rn "wp_enqueue_style.*WPSS_PLUGIN_URL.*css" src/ --include="*.php" | wc -l
```

### 0.10 PHP Syntax + Error Log
```bash
# Syntax check all PHP files
find src/ -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
# Check debug.log for wpss errors
grep -i "wpss\|wp.sell" /path/to/wp-content/debug.log | tail -20
```

---

## Phase 1: Service Discovery (Visitor/Buyer)

### 1.1 Services Archive (`/services/`)
- [ ] Page loads without PHP errors or console errors
- [ ] Category sidebar renders with counts
- [ ] Price Range filter (min/max spinbuttons) present
- [ ] Seller Rating radio buttons work
- [ ] Delivery Time radio filters work
- [ ] "Apply Filters" button has hover effect (color shift + shadow)
- [ ] "Clear All" link resets all filters
- [ ] Sort dropdown works (Recommended, Newest, Best Rated, Price Low→High, Price High→Low)
- [ ] Category dropdown filter works
- [ ] Service cards show: category badge, vendor avatar+name, title, rating+count, price
- [ ] Service card hover: shadow deepens, slight lift transform
- [ ] Pagination renders and navigates correctly
- [ ] "X services found" count updates with filters
- [ ] Screenshot at 1440px and 390px viewports

### 1.2 Single Service Page (`/service/{slug}/`)
- [ ] Breadcrumbs render (Home / Services / Category / Title)
- [ ] Title, vendor link, rating, order count display
- [ ] Gallery/image loads (placeholder OK for demo)
- [ ] "About This Service" section with description
- [ ] Requirements section lists what seller needs
- [ ] "About The Seller" section with avatar, bio, stats
- [ ] FAQ accordion: click expands, click again collapses, arrow rotates
- [ ] FAQ item hover: background shade change
- [ ] Reviews section loads (or "No reviews yet" empty state)
- [ ] Related Services section at bottom with cards

### 1.3 Package Sidebar (Single Service)
- [ ] Three tabs: Basic / Standard / Premium — all clickable
- [ ] Tab switch updates: package name, price, description, delivery time, revisions
- [ ] Tab hover: background color change
- [ ] Active tab: underline/highlight indicator
- [ ] "Continue ($XX.00)" button — proper hover effect
- [ ] **BUG CHECK**: Switch tab while modal is open → modal updates (our fix)
- [ ] "Contact Seller" link scrolls to contact section or opens modal

### 1.4 Vendor Profile (`/vendor/{username}/`)
- [ ] Cover image area renders
- [ ] Avatar, name, badge (New Seller/Level), tagline
- [ ] Stats: orders completed, country, member since
- [ ] "Contact Me" button has hover effect
- [ ] About section with bio
- [ ] Services grid shows vendor's services
- [ ] Sidebar stats: Active Services count, Orders Completed count

---

## Phase 2: Add to Cart & Checkout Flow

### 2.1 Add to Cart (Single Service Page)
- [ ] Click "Continue ($XX)" → Order Options modal opens
- [ ] Modal shows: Package name, Delivery time, Total price
- [ ] Modal close: X button, click outside, Escape key — all work
- [ ] "Continue to Checkout" button → shows "Added to cart!" with green checkmark
- [ ] Two buttons appear: "View Cart" and "Checkout"
- [ ] Cart count badge on floating mini-cart updates
- [ ] Mini-cart icon visible in bottom-right corner with count
- [ ] NO browser alert() or confirm() — all inline notices

### 2.2 Standalone Checkout (`/checkout/` and `/service-checkout/{id}/`)
- [ ] `/service-checkout/{id}/` loads (not 404) — rewrite rule test
- [ ] `/checkout/` page loads with service details
- [ ] "Back to service" link works
- [ ] "Secure Checkout" badge visible
- [ ] Service Details card: image, title, vendor, delivery, revisions
- [ ] Payment Method section: Offline Payment radio
- [ ] Clicking radio shows payment description
- [ ] Order Summary: package name, price, total
- [ ] "Pay $XX.00" button — hover effect, disabled state during submission
- [ ] Click Pay → redirects to dashboard order view
- [ ] Order status: "Pending Payment"
- [ ] "What happens next?" stepper (Pay → Requirements → Seller Works → Review → Complete)
- [ ] Multi-item cart notice: "You have X more items in your cart"

### 2.3 Checkout with Stripe (Pro)
- [ ] Stripe radio option appears when configured
- [ ] Stripe Payment Element mounts in #wpss-stripe-payment-element
- [ ] Card input works, validation errors show inline (not alert)
- [ ] "Processing..." loading state on submit button
- [ ] Success redirects to order requirements page
- [ ] Error shows in #wpss-stripe-error (inline, not alert)

### 2.4 Checkout with PayPal (Pro)
- [ ] PayPal radio option appears when configured
- [ ] PayPal button renders in container
- [ ] Click opens PayPal popup
- [ ] After approval, captures payment via AJAX
- [ ] Cart cleared after successful payment
- [ ] pay_order flow works for proposal acceptance
- [ ] Success redirects to order requirements

---

## Phase 3: Order Lifecycle (Buyer + Vendor)

### 3.1 Order View — Pending Payment
- [ ] Order number, status badge "PENDING PAYMENT"
- [ ] "Pay $XX.00" button (green) and "Cancel Order" button
- [ ] Order Summary: number, date, total amount
- [ ] Service Details: image, title, price, seller name
- [ ] Order Timeline: "Order Placed" entry
- [ ] Conversation section: "Messaging is not available for this order status"

### 3.2 Order View — Pending Requirements
- [ ] Status badge: "PENDING REQUIREMENTS"
- [ ] Requirements form renders with fields (text, textarea, file upload, select)
- [ ] File upload drag-and-drop works
- [ ] "Submit Requirements" button — loading state, success notice
- [ ] "Skip Requirements" link — confirmation dialog (inline, NOT alert)
- [ ] After submit → status changes to "In Progress"

### 3.3 Order View — In Progress (Vendor Side)
- [ ] Status badge: "IN PROGRESS"
- [ ] "Submit Delivery" button opens delivery modal
- [ ] Delivery modal: textarea for message, file upload
- [ ] Submit → loading state → success notice → status changes to "Pending Approval"
- [ ] Cancel modal via X, Escape, backdrop click

### 3.4 Order View — Pending Approval (Buyer Side)
- [ ] "Accept Delivery" button → inline confirmation (not alert)
- [ ] "Request Revision" button → revision modal with textarea
- [ ] Revision modal: describe changes needed, submit
- [ ] All confirmations use custom styled dialogs, NOT browser confirm()

### 3.5 Order View — Completed
- [ ] Status badge: "COMPLETED" (green)
- [ ] "Leave Review" option available
- [ ] Star rating interactive (hover highlights stars)
- [ ] Review textarea and submit
- [ ] Review appears after submission

### 3.6 Order Conversation
- [ ] Messages display with sender name, timestamp
- [ ] Read receipts: checkmark + "Read" for read messages
- [ ] Send message: type → click Send → message appears instantly
- [ ] File attachment upload works
- [ ] Empty state: "No messages yet. Start the conversation!"
- [ ] Date separators between different days

---

## Phase 4: Vendor Dashboard

### 4.1 Dashboard Navigation
- [ ] Sidebar renders: avatar, name, "Seller" badge
- [ ] Buying section: My Orders, Buyer Requests
- [ ] Selling section: My Services, Sales Orders, Portfolio, Wallet & Earnings, Analytics
- [ ] Account section: Messages, Profile
- [ ] Active section highlighted with accent color + left border
- [ ] All nav links have hover effect

### 4.2 My Services (`?section=services`)
- [ ] Stats bar: Active / Drafts / Pending Review counts
- [ ] "Create Service" button (top right) — hover effect
- [ ] Service cards: image, title, views+orders, price, status badge
- [ ] Edit / View / Delete buttons on each card
- [ ] Delete button: red, confirmation before delete (NOT alert)
- [ ] Empty state if no services: icon + "Create Your First Service" CTA

### 4.3 Create Service (`?section=create`)
- [ ] 6-step wizard: Basic Info → Pricing → Gallery → Requirements → Extras & FAQs → Review
- [ ] Step indicators with icons, active step highlighted
- [ ] Basic Info: title (with char count), category dropdown, subcategory, description, tags
- [ ] "Continue" and "Save Draft" buttons — both work
- [ ] Step navigation: clicking completed steps goes back
- [ ] Media uploader for gallery uses wp.media (localized title, not hardcoded English)

### 4.4 Wallet & Earnings (`?section=wallet`)
- [ ] 4 stat cards: Available Balance, Pending Withdrawal, Total Withdrawn, Total Earned
- [ ] "Request Withdrawal" button — disabled when balance < minimum
- [ ] Minimum withdrawal amount displayed
- [ ] Recent Transactions section (or empty state)
- [ ] Withdrawal History section (or empty state)
- [ ] **Pro**: Withdrawal modal opens, method selection works, PayPal email field toggles
- [ ] Withdrawal form validates amount and method
- [ ] Submit → "Processing..." → page reloads on success
- [ ] Error shows inline in modal (NOT alert)

### 4.5 Analytics (`?section=analytics`)
- [ ] Time period filters: 7 Days, 30 Days, 90 Days, 12 Months
- [ ] 4 stat cards: Revenue, Orders, Completion Rate, Service Impressions
- [ ] Revenue Over Time chart (or "No data" empty state)
- [ ] Top Performing Services (or "No completed orders" empty state)
- [ ] Period filter click updates data without page reload

### 4.6 Messages (`?section=messages`)
- [ ] Conversation list with service name and order number
- [ ] Click conversation → navigates to order view with messages
- [ ] Empty state if no conversations

### 4.7 Profile (`?section=profile`)
- [ ] Profile form with editable fields
- [ ] Avatar upload via media uploader
- [ ] Cover image upload
- [ ] "Save Changes" → AJAX submit → success notice (inline)
- [ ] Form validation errors show inline

### 4.8 Portfolio (`?section=portfolio`)
- [ ] Portfolio items grid
- [ ] Add/Edit portfolio item modal
- [ ] Image upload via media uploader (localized strings)
- [ ] Delete with confirmation
- [ ] Featured toggle
- [ ] Empty state with CTA

### 4.9 Buyer Requests (`?section=requests`)
- [ ] List of posted requests
- [ ] Create Request form
- [ ] Edit/Delete existing requests
- [ ] View proposals on a request

---

## Phase 5: Vendor Registration

### 5.1 Registration Page (`/vendor-registration/`)
- [ ] If already a vendor: "You're already a vendor" + "Go to Dashboard" link
- [ ] If not a vendor: registration form renders
- [ ] Form fields: bio, skills, etc.
- [ ] Submit → inline success/error notification (NOT alert)
- [ ] Redirect to dashboard after success

---

## Phase 6: Admin Pages

### 6.1 Settings (`wp-admin/admin.php?page=wpss-settings`)
- [ ] All 9 tabs present: General, Pages, Payments, Gateways, Vendor, Orders, Emails, Branding, Advanced
- [ ] Each tab loads its content
- [ ] "Save Changes" button works — success notice at top
- [ ] E-Commerce Platform dropdown shows Standalone + WooCommerce (when Pro active)
- [ ] "Currently Active:" label shows correct adapter

### 6.2 Admin Menu
- [ ] "Sell Services" menu with all sub-items: Dashboard, All Services, Moderation, Categories, Tags, All Requests, Orders, Vendors, Withdrawals, Disputes, Analytics, Settings, License

### 6.3 Orders Admin (`wp-admin/admin.php?page=wpss-orders`)
- [ ] Orders list table loads
- [ ] Status filter tabs work
- [ ] Order detail view loads

---

## Phase 7: Pro-Specific Features

### 7.1 Stripe Connect Dashboard Section
- [ ] Three states: Not Connected / Pending / Active
- [ ] Connect button starts onboarding flow
- [ ] Disconnect button opens confirmation modal (focus trap, Escape, backdrop)
- [ ] Account details grid when connected

### 7.2 Admin Analytics (Pro)
- [ ] Chart.js charts render (Revenue line, Orders bar)
- [ ] Period selector dropdown works
- [ ] Export buttons (CSV) trigger download
- [ ] Stat cards update on filter change
- [ ] Loading state during data fetch

### 7.3 Admin License
- [ ] License key input field
- [ ] Activate button → AJAX → success/error notice
- [ ] Deactivate button → custom confirm modal (NOT browser confirm) → AJAX

### 7.4 Admin Wallet Management
- [ ] Add/Deduct funds form
- [ ] Amount validation
- [ ] Success shows "New balance: $XX.XX" (localized string)

---

## Phase 8: Cross-Cutting UX Checks

### 8.1 No Browser Alerts
- [ ] Search ALL JS files for `alert(`, `confirm(`, `prompt(` — NONE should exist
- [ ] All confirmations use custom styled modals
- [ ] All error messages use inline notices or toast notifications
- [ ] All prompts use custom modal dialogs with textarea

### 8.2 Hover Effects Consistency
- [ ] All buttons: background shift + shadow on hover
- [ ] All cards (service, vendor, order): shadow + slight lift
- [ ] All links: color change on hover
- [ ] All form inputs: border color change on focus
- [ ] Package tabs: background change + underline on active
- [ ] FAQ items: background shade on hover
- [ ] Mini-cart icon: slight scale on hover
- [ ] Modal close button: opacity/color change on hover

### 8.3 Loading States
- [ ] All AJAX buttons show loading text ("Processing...", "Submitting...", "Sending...")
- [ ] Buttons disabled during AJAX requests
- [ ] Buttons re-enable after success/error
- [ ] No double-submit possible

### 8.4 Empty States
- [ ] Orders: "No orders yet" + "Browse Services" CTA
- [ ] Services: "No services yet" + "Create Your First Service" CTA
- [ ] Messages: "No messages yet" with helpful text
- [ ] Conversations: contextual empty message
- [ ] Reviews: "No reviews yet"
- [ ] Portfolio: empty state with add CTA
- [ ] Analytics: "No data for this period"
- [ ] Wallet transactions: "No transactions yet"
- [ ] Withdrawal history: "No withdrawal requests yet"

### 8.5 Mobile Responsiveness (390px viewport)
- [ ] Services archive: single column cards, sidebar collapses
- [ ] Single service: packages sidebar stacks below content
- [ ] Dashboard: sidebar becomes top nav or hamburger
- [ ] Checkout: stacks to single column
- [ ] Modals: full-width on mobile
- [ ] Mini-cart: properly positioned

### 8.6 Keyboard Navigation
- [ ] Tab through all interactive elements in order
- [ ] Modals trap focus (Tab/Shift+Tab stays within modal)
- [ ] Escape closes modals
- [ ] Enter activates buttons
- [ ] Focus-visible outline on all focusable elements

### 8.7 Notices & Feedback
- [ ] Success: green toast/notice, auto-dismisses
- [ ] Error: red notice, stays until dismissed or retried
- [ ] Warning: yellow notice
- [ ] Form validation: red text below specific field
- [ ] All notices have dismiss (X) button or auto-dismiss

---

## Phase 9: Favorite & Social Features

### 9.1 Favorite Button
- [ ] Heart icon on service cards/pages
- [ ] Click toggles favorited state (heart fills/empties)
- [ ] Count updates after favorite AND unfavorite
- [ ] **Guest check**: clicking as guest redirects to login (our fix)
- [ ] No silent AJAX failure for guests

### 9.2 Share Button
- [ ] Copy link works with "Copied!" feedback (localized)
- [ ] Clipboard fallback for non-HTTPS

---

## Execution Instructions

### How to Run This Plan
1. Login: Navigate to `http://wss.local/?autologin=1`
2. For each phase, use Playwright MCP tools:
   - `browser_navigate` to go to pages
   - `browser_snapshot` to check DOM structure
   - `browser_click` to test interactions
   - `browser_take_screenshot` for visual evidence
   - `browser_console_messages` for JS errors
3. Check each item, screenshot failures
4. Report: PASS/FAIL per phase with issue details

### Quick Smoke Test (5 minutes)
Run just these critical paths:
1. `/services/` → click a service → click "Continue" → "Continue to Checkout" → `/checkout/` → Pay
2. `/dashboard/?section=services` → "Create Service" → fill basic info → Save Draft
3. `/dashboard/?section=wallet` → check stats render
4. Admin: `/wp-admin/admin.php?page=wpss-settings` → verify all tabs
5. Console errors check on each page

### Browser Alert Audit Command
```bash
grep -rn "alert\s*(" assets/js/ --include="*.js" | grep -v "// " | grep -v "role=\"alert\""
grep -rn "confirm\s*(" assets/js/ --include="*.js" | grep -v "// " | grep -v "proConfirm"
grep -rn "prompt\s*(" assets/js/ --include="*.js" | grep -v "// "
```
