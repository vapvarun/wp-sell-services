# WP Sell Services - Usability Test Cases

> Role-based manual checklist for site owners and end users. Each section covers a specific role with action-by-action verification steps.

Last Updated: 2026-03-24

---

## How to Use This Document

1. Pick a role section below
2. Login as that role using auto-login: `?autologin=username`
3. Walk through each test case, checking the expected result
4. Mark PASS/FAIL in the checkbox

**Test Users:**
| Role | Username | Auto-Login URL |
|------|----------|---------------|
| Site Admin | varundubey | `?autologin=1` |
| Vendor | testuser_vendor | `?autologin=testuser_vendor` |
| Buyer | testbuyer | `?autologin=testbuyer` |
| Guest | (logged out) | Clear cookies |

---

## ROLE 1: Guest (Not Logged In)

### 1.1 Service Browsing
- [ ] Visit /services/ - page loads, services visible
- [ ] Search bar works - type keyword, results filter
- [ ] Category filter works - click category, services filter
- [ ] Pagination works - if 10+ services, page 2 loads
- [ ] Service card shows: thumbnail, title, vendor name, price, rating

### 1.2 Single Service Page
- [ ] Click a service - single page loads
- [ ] Package tabs visible (Basic/Standard/Premium)
- [ ] Switching packages updates price display
- [ ] Addons listed with checkboxes and prices
- [ ] FAQ section expandable/collapsible
- [ ] Reviews section visible with ratings
- [ ] Vendor profile card visible (name, avatar, rating, response time)
- [ ] "Order Now" button visible (redirects to login for guests)

### 1.3 Vendor Profile
- [ ] Click vendor name on service - profile page loads
- [ ] Bio, tagline, stats visible
- [ ] Portfolio items displayed
- [ ] Published services listed
- [ ] Reviews visible

### 1.4 Buyer Requests
- [ ] Visit /requests/ archive - page loads
- [ ] Request cards visible with title, budget, deadline
- [ ] Cannot submit proposal (login required)

### 1.5 Registration
- [ ] Visit /become-vendor/ page - loads
- [ ] Fill form with valid data - account created
- [ ] Fill form with existing email - error shown
- [ ] Fill form with weak password (< 8 chars) - error shown
- [ ] After registration, redirected to dashboard

---

## ROLE 2: Buyer (testbuyer)

### 2.1 Dashboard Navigation
- [ ] Login, visit /dashboard/ - loads without errors
- [ ] Sidebar shows: My Orders, Buyer Requests
- [ ] Sidebar shows: Messages, Profile under Account
- [ ] User avatar and role displayed in sidebar

### 2.2 Purchasing a Service
- [ ] Browse services, select a package
- [ ] Check addon(s) - price updates in real-time
- [ ] Click "Order Now" - redirected to checkout
- [ ] Checkout page shows: service name, package, addons, subtotal, tax, total
- [ ] Select payment method
- [ ] Complete purchase - order confirmation shown
- [ ] Order appears in Dashboard > My Orders

### 2.3 Cannot Buy Own Service (if also vendor)
- [ ] If user is also a vendor, try ordering own service
- [ ] Error: "You cannot purchase your own service"

### 2.4 Order Management (Buyer Side)
- [ ] View order details from My Orders
- [ ] Submit requirements (text fields + file upload)
- [ ] After submission, status changes to "In Progress"
- [ ] Send message in order conversation
- [ ] Receive vendor messages (polling updates)
- [ ] View delivery when vendor delivers
- [ ] Accept delivery - order completes
- [ ] Request revision - vendor notified
- [ ] Request cancellation with reason

### 2.5 Reviews
- [ ] After order completed, leave review option visible
- [ ] Submit review with star rating and text
- [ ] Review appears on service page
- [ ] Cannot review same order twice

### 2.6 Buyer Requests
- [ ] Go to Dashboard > Buyer Requests
- [ ] Post new request (title, description, budget, deadline, category)
- [ ] Request appears in list
- [ ] View incoming proposals
- [ ] Accept a proposal - order created
- [ ] Reject a proposal - vendor notified
- [ ] Edit request details
- [ ] Delete request

### 2.7 Disputes
- [ ] On an active order, click "Open Dispute"
- [ ] Fill reason and attach evidence
- [ ] Dispute created, status shown
- [ ] Add additional evidence/messages
- [ ] Cannot open dispute on completed/cancelled order

### 2.8 Notifications
- [ ] Notification bell shows count
- [ ] Click bell - notification dropdown appears
- [ ] Click notification - navigates to relevant page
- [ ] Mark all as read works

### 2.9 Profile
- [ ] Go to Dashboard > Profile
- [ ] Edit display name, bio
- [ ] Upload avatar
- [ ] Save changes - profile updated

### 2.10 Favorites
- [ ] On service page, click favorite/heart icon
- [ ] Service added to favorites
- [ ] View favorites in dashboard
- [ ] Remove from favorites

---

## ROLE 3: Vendor (testuser_vendor)

### 3.1 Dashboard Navigation
- [ ] Login, visit /dashboard/ - loads
- [ ] Sidebar shows Buying section (My Orders, Buyer Requests)
- [ ] Sidebar shows Selling section (My Services, Sales Orders, Portfolio, Wallet, Analytics)
- [ ] Sidebar shows Account section (Messages, Profile)

### 3.2 Service Management
- [ ] Go to My Services - list of services shown
- [ ] Each service shows: title, status, orders count, price
- [ ] Click "Create Service" - wizard opens
- [ ] Complete all wizard steps (info, pricing, addons, gallery, requirements, FAQ)
- [ ] Save draft works
- [ ] Publish works (or goes to pending if moderation on)
- [ ] Edit existing service - wizard loads with existing data
- [ ] Pause/unpause service
- [ ] Delete service

### 3.3 Service Wizard Validation
- [ ] Title required - error if empty
- [ ] At least 1 package required
- [ ] Package price must be > 0
- [ ] Package delivery days must be > 0
- [ ] Gallery images upload and display
- [ ] Requirements form builder works
- [ ] FAQ add/remove works

### 3.4 Sales Order Management (Vendor Side)
- [ ] Go to Sales Orders - list with status badges
- [ ] View order - see buyer requirements
- [ ] Accept order (if pending acceptance)
- [ ] Start work on order
- [ ] Send message in conversation
- [ ] Upload and submit delivery
- [ ] Re-deliver after revision request
- [ ] Decline order with reason
- [ ] Request deadline extension

### 3.5 Wallet and Earnings
- [ ] Go to Wallet & Earnings
- [ ] Balance displayed correctly
- [ ] Transaction history visible
- [ ] Click "Withdraw" - modal opens
- [ ] Enter amount and method
- [ ] Submit - actual REST API call made (not alert)
- [ ] Withdrawal appears in history as "pending"
- [ ] Cannot withdraw more than balance

### 3.6 Analytics (Pro)
- [ ] Go to Analytics tab
- [ ] Stat cards show: Revenue, Orders, Completion Rate, Profile Views
- [ ] Profile Views shows actual number (not 0)
- [ ] Time period selector works (7d, 30d, 90d, 12m)
- [ ] Revenue chart renders
- [ ] Top services table shows data

### 3.7 Portfolio
- [ ] Go to Portfolio section
- [ ] Add new portfolio item (title, description, images)
- [ ] Reorder items via drag-and-drop
- [ ] Delete portfolio item
- [ ] Portfolio visible on public vendor profile

### 3.8 Proposals
- [ ] Browse buyer requests on frontend
- [ ] Submit proposal (price, delivery time, cover letter)
- [ ] Proposal appears in "My Proposals" or request detail
- [ ] Withdraw proposal
- [ ] When buyer accepts, order created automatically

### 3.9 Subscription Plan (Pro, if enabled)
- [ ] If subscription required, see plan selection page
- [ ] Free plan auto-assigned (if configured)
- [ ] Can upgrade to paid plan
- [ ] Service limit enforced per plan
- [ ] Upgrade/Downgrade labels correct (monthly price comparison)

### 3.10 Vacation Mode
- [ ] Go to Profile > Settings
- [ ] Enable vacation mode with custom message
- [ ] Verify services show "on vacation" badge
- [ ] Disable vacation mode - services active again

---

## ROLE 4: Site Admin (varundubey)

### 4.1 Plugin Dashboard
- [ ] Go to WP Admin > Sell Services > Dashboard
- [ ] Stats cards visible (Total Orders, In Progress, Completed, Revenue)
- [ ] Quick action links work
- [ ] Content overview shows service/request/order counts
- [ ] Recent orders table with clickable links

### 4.2 Service Management
- [ ] Go to All Services - WP list table with services
- [ ] Quick edit works
- [ ] Bulk actions work (publish, trash)
- [ ] Service categories and tags manageable

### 4.3 Service Moderation
- [ ] Go to Moderation page
- [ ] Pending services listed (if moderation enabled)
- [ ] Approve service - status changes to publish
- [ ] Reject service with reason - vendor notified
- [ ] Bulk moderate works

### 4.4 Order Management
- [ ] Go to Orders page - all orders listed
- [ ] Status filter tabs work (all, pending, in_progress, etc.)
- [ ] Click order - detail view loads
- [ ] Admin can change order status
- [ ] Admin can add notes
- [ ] Admin can submit requirements on behalf of buyer
- [ ] Admin can mark offline order as paid

### 4.5 Vendor Management
- [ ] Go to Vendors page - all vendors listed
- [ ] View vendor details (orders, earnings, services)
- [ ] Approve pending vendor
- [ ] Set custom commission rate for vendor
- [ ] Toggle vendor vacation mode
- [ ] Update vendor availability

### 4.6 Withdrawals
- [ ] Go to Withdrawals page - pending requests listed
- [ ] Approve withdrawal
- [ ] Reject withdrawal with note
- [ ] Completed withdrawals shown in history

### 4.7 Disputes
- [ ] Go to Disputes page - open disputes listed
- [ ] View dispute details and evidence from both parties
- [ ] Set resolution (full refund, partial, dismiss)
- [ ] Resolution applied to order

### 4.8 Analytics (Pro)
- [ ] Go to Analytics page - loads without error (no critical error)
- [ ] Single "Analytics" menu item (no duplicate)
- [ ] Period selector works (Today, Week, Month, Year, Custom)
- [ ] Custom date range with valid dates - data loads
- [ ] Custom date range with invalid format - error shown
- [ ] Revenue breakdown visible
- [ ] Orders breakdown with status counts
- [ ] Top services table
- [ ] Top vendors table
- [ ] Export CSV button works
- [ ] Export PDF button works

### 4.9 Settings - General
- [ ] Go to Settings > General
- [ ] Platform name editable
- [ ] Currency selector works
- [ ] E-commerce platform selector works (Standalone/WooCommerce)
- [ ] Save changes persists

### 4.10 Settings - Pages
- [ ] Go to Settings > Pages
- [ ] All 4 pages mapped (Services, Dashboard, Become Vendor, Create Service)
- [ ] "Create Page" button works for missing pages
- [ ] Pages link to correct frontend URLs

### 4.11 Settings - Payments
- [ ] Go to Settings > Payments
- [ ] Commission rate configurable
- [ ] Commission type (percentage/flat) selectable
- [ ] Tiered commission rules section visible (Pro)
- [ ] Add/edit/delete commission rules

### 4.12 Settings - Gateways
- [ ] Go to Settings > Gateways
- [ ] Accordion sections for each gateway
- [ ] Stripe settings (API keys, webhook secret)
- [ ] PayPal settings (client ID, secret, webhook ID)
- [ ] Offline payment settings (auto-cancel hours)
- [ ] Razorpay settings (Pro)

### 4.13 Settings - Vendor
- [ ] Go to Settings > Vendor
- [ ] Auto-approve toggle
- [ ] Max services per vendor
- [ ] Require moderation toggle
- [ ] Subscription plans section (Pro)
- [ ] Create/edit/delete subscription plans
- [ ] Default plan for new vendors
- [ ] Migrate existing vendors tool

### 4.14 Settings - Orders
- [ ] Go to Settings > Orders
- [ ] Auto-complete days configurable
- [ ] Requirements timeout configurable
- [ ] Cancellation timeout configurable

### 4.15 Settings - Emails
- [ ] Go to Settings > Emails
- [ ] Email toggle per type (enable/disable)
- [ ] Test email button works
- [ ] Email template preview

### 4.16 Settings - Branding (Pro)
- [ ] Go to Settings > Branding
- [ ] Brand name field
- [ ] Logo URL field
- [ ] Primary color picker (no JS error)
- [ ] Footer text field
- [ ] Save - changes reflect in admin menu and dashboard

### 4.17 Settings - Advanced
- [ ] Go to Settings > Advanced
- [ ] Debug/developer options
- [ ] Data deletion on uninstall toggle
- [ ] Cache settings

### 4.18 License (Pro)
- [ ] Go to License page
- [ ] License key field
- [ ] Activate/Deactivate buttons
- [ ] License status displayed

### 4.19 Setup Wizard
- [ ] Go to Setup Wizard
- [ ] Multi-step wizard loads
- [ ] Can complete all steps
- [ ] Creates categories, configures settings

---

## ROLE 5: Cross-Role Interactions

### 5.1 Buyer-Vendor Communication
- [ ] Buyer sends message on order
- [ ] Vendor receives message (polling in ~10s)
- [ ] Vendor replies
- [ ] Buyer receives reply
- [ ] File attachments work in messages

### 5.2 Order Transition Notifications
- [ ] When vendor accepts order - buyer notified
- [ ] When vendor delivers - buyer notified
- [ ] When buyer requests revision - vendor notified
- [ ] When buyer completes order - vendor notified (+ commission)
- [ ] When either cancels - other party notified

### 5.3 Proposal Flow
- [ ] Buyer posts request
- [ ] Vendor submits proposal
- [ ] Buyer receives notification of new proposal
- [ ] Buyer accepts - order created, vendor notified
- [ ] Buyer rejects - vendor notified with reason

### 5.4 Dispute Flow
- [ ] Buyer opens dispute - vendor and admin notified
- [ ] Vendor responds - buyer and admin notified
- [ ] Admin resolves - both parties notified

### 5.5 Review Flow
- [ ] Buyer leaves review - vendor notified
- [ ] Vendor can reply to review
- [ ] Review visible on service page and vendor profile
- [ ] Average rating updates

---

## ROLE 6: Edge Cases and Error Handling

### 6.1 Rate Limiting
- [ ] Rapid-fire AJAX calls (click button 10x fast) - rate limited after threshold
- [ ] Rate limit error message shown
- [ ] Recovery after cooldown period

### 6.2 Concurrent Access
- [ ] Two tabs open on same order
- [ ] Submit delivery in tab 1, accept in tab 2
- [ ] No double-processing or DB corruption

### 6.3 File Upload Limits
- [ ] Upload oversized file - error shown
- [ ] Upload invalid file type - rejected
- [ ] Multiple file upload works

### 6.4 Empty States
- [ ] New vendor with 0 services - "Create your first service" prompt
- [ ] Buyer with 0 orders - "Browse services" prompt
- [ ] Empty search results - "No services found" message
- [ ] No proposals on request - appropriate empty state

### 6.5 URL Manipulation
- [ ] Direct URL to order not owned by user - access denied
- [ ] Direct URL to admin page as non-admin - redirected
- [ ] Invalid order_id in URL - 404 or error page, not PHP error

### 6.6 Browser Compatibility
- [ ] Chrome - all features work
- [ ] Firefox - all features work
- [ ] Safari - all features work
- [ ] Mobile responsive - dashboard usable on phone

### 6.7 RTL Support
- [ ] Switch to RTL language
- [ ] Dashboard layout mirrors correctly
- [ ] Service cards layout correct
- [ ] Checkout form aligned properly

---

## Summary Scorecard

| Role | Total Tests | Pass | Fail | Notes |
|------|------------|------|------|-------|
| Guest | 18 | | | |
| Buyer | 35 | | | |
| Vendor | 38 | | | |
| Admin | 55 | | | |
| Cross-Role | 15 | | | |
| Edge Cases | 16 | | | |
| **TOTAL** | **177** | | | |
