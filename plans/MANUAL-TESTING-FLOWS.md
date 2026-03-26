# WP Sell Services - Manual Functionality Testing Flows

> Step-by-step manual testing flows for QA team. Each flow tests end-to-end user journeys.

Last Updated: 2026-03-24

---

## Test Environment

- Site: https://wss.local
- Admin: ?autologin=1
- Vendor (Sarah Mitchell): ?autologin=testuser_vendor
- Buyer (James Cooper): ?autologin=testbuyer
- Dashboard: /dashboard/
- Services: /services/

---

## Flow 1: Vendor Registration and Profile Setup

**Role:** New user (logged out)

1. Go to /become-vendor/ page
2. Fill registration form (username, email, password)
3. Verify account created with `wpss_vendor` role
4. Verify vendor profile row created in `wpss_vendor_profiles`
5. If auto-approve is OFF: verify status = `pending`, vendor sees "pending approval" message
6. If auto-approve is ON: verify status = `active`, vendor can access dashboard
7. Go to Dashboard > Profile section
8. Update display name, tagline, bio, avatar
9. Verify changes saved and visible on vendor public profile
10. Go to Dashboard > Portfolio
11. Add a portfolio item with images
12. Verify portfolio visible on vendor profile page

**Expected Data:**
- `wpss_vendor_profiles` row with correct user_id
- User meta `_wpss_is_vendor` = 1
- Notification sent to admin (if moderation enabled)

---

## Flow 2: Service Creation via Wizard

**Role:** Vendor (testuser_vendor)

1. Login as vendor, go to Dashboard > My Services
2. Click "Create Service" button
3. Step 1 - Basic Info: Enter title, description, category
4. Step 2 - Pricing: Add 3 packages (Basic/Standard/Premium) with different prices and delivery days
5. Step 3 - Addons: Add 2 addons (e.g., "Express Delivery +$20", "Source Files +$15")
6. Step 4 - Gallery: Upload 3 images
7. Step 5 - Requirements: Add 2 requirement fields
8. Step 6 - FAQs: Add 2 FAQ items
9. Click "Save Draft" - verify service saved as draft
10. Click "Publish" - verify service published (or pending moderation)
11. Go to /services/ archive - verify service appears
12. Click service - verify single service page renders with all data

**Expected Data:**
- `wpss_service` CPT with post_status = publish
- 3 rows in `wpss_service_packages`
- 2 rows in `wpss_service_addons`
- Gallery images in post meta

**Verify These Fixes:**
- Service limit enforcement works (card 5 fix)
- If Pro subscription required, blocked without plan

---

## Flow 3: Buyer Purchase (Standalone Checkout)

**Role:** Buyer (testbuyer)

1. Browse /services/ page
2. Click a service owned by testuser_vendor
3. Select "Standard" package
4. Check an addon (e.g., "Express Delivery")
5. Click "Order Now" or "Add to Cart"
6. Verify checkout page loads at /service-checkout/
7. Verify subtotal = package price, addons shown separately
8. Verify tax calculated correctly (if enabled)
9. Verify total = subtotal + addons + tax
10. Select payment method (Offline for testing)
11. Complete checkout
12. Verify order confirmation page
13. Verify order created in `wpss_orders`

**Expected Data:**
- Order with correct subtotal, addons_total, total
- Commission calculated on pre-tax base (subtotal + addons, NOT total)
- Status = pending_payment (offline) or pending_requirements (paid)
- Conversation created in `wpss_conversations`
- Notification sent to vendor

**Verify These Fixes:**
- Addons included in order (card 13 fix)
- Commission is pre-tax (card 8 fix)
- Self-purchase blocked (card 12 fix)

---

## Flow 4: Self-Purchase Prevention

**Role:** Vendor (testuser_vendor)

1. Login as testuser_vendor
2. Navigate to a service owned by testuser_vendor
3. Try to click "Order Now" or add to cart
4. Verify error: "You cannot purchase your own service"
5. Test via REST API: POST /wpss/v1/cart/add with own service_id
6. Verify 403 error response

**Expected:** Blocked in both AJAX and REST paths

---

## Flow 5: Order Lifecycle (Happy Path)

**Role:** Buyer starts, Vendor completes

1. **Buyer:** Complete checkout (pending_payment if offline)
2. **Admin:** Mark as paid (if offline) - status moves to pending_requirements
3. **Buyer:** Go to Dashboard > My Orders > View order
4. Submit requirements form with text and file attachment
5. Verify status = in_progress, deadline set
6. **Vendor:** Go to Dashboard > Sales Orders > Manage order
7. Send a message in conversation
8. **Buyer:** Verify message received (check polling)
9. **Vendor:** Click "Deliver" - upload delivery files, add message
10. Verify status = pending_approval
11. **Buyer:** Review delivery
12. If revision needed: click "Request Revision" - verify status = revision_requested
13. **Vendor:** Re-deliver (repeat step 9)
14. **Buyer:** Accept delivery - verify status = completed
15. Verify commission recorded in `wpss_wallet_transactions`
16. Verify vendor earnings updated in `wpss_vendor_profiles`
17. **Buyer:** Leave a review (rating + text)
18. Verify review in `wpss_reviews`, vendor avg_rating updated

**Status Transitions to Verify:**
```
pending_payment -> pending_requirements -> in_progress ->
pending_approval -> revision_requested -> in_progress ->
pending_approval -> completed
```

**Verify These Fixes:**
- Status changes logged in conversation (card 11 fix)
- Commission on pre-tax base (card 8 fix)
- Profile Views increment (card 6 fix)

---

## Flow 6: Order Cancellation

**Role:** Buyer and Vendor

1. Create a new order (in_progress status)
2. **Buyer:** Request cancellation with reason
3. Verify status = cancellation_requested
4. **Vendor:** Accept cancellation
5. Verify status = cancelled
6. Verify no commission recorded
7. Verify notification sent to both parties

**Alternative:** Vendor rejects cancellation
- Status returns to in_progress

---

## Flow 7: Dispute Resolution

**Role:** Buyer, Vendor, Admin

1. Create order in pending_approval status
2. **Buyer:** Open dispute with reason and evidence
3. Verify status = disputed, row in `wpss_disputes`
4. **Vendor:** Add response with evidence
5. Verify dispute messages in `wpss_dispute_messages`
6. **Admin:** Go to WP Admin > Sell Services > Disputes
7. Review dispute, set resolution (refund/partial/dismiss)
8. Verify dispute status updated, order status updated
9. If refund: verify wallet transaction

---

## Flow 8: Buyer Request and Proposals

**Role:** Buyer posts, Vendor responds

1. **Buyer:** Go to Dashboard > Buyer Requests
2. Click "Post a Request"
3. Fill title, description, budget, deadline
4. Verify request created in `wpss_request` CPT
5. **Vendor:** Browse /requests/ archive
6. Click request, submit proposal (price, delivery time, cover letter)
7. Verify proposal in `wpss_proposals`
8. **Buyer:** Go to Dashboard > Buyer Requests > View request
9. See proposals listed
10. Accept one proposal
11. Verify order created from accepted proposal
12. Reject another proposal
13. Verify rejection recorded (card 1 IDOR fix ensures only request owner can reject)

**Verify This Fix:**
- reject_proposal ownership check (security fix)

---

## Flow 9: Vendor Wallet and Withdrawals

**Role:** Vendor with completed orders

1. **Vendor:** Go to Dashboard > Wallet & Earnings
2. Verify balance shows (from completed order commissions)
3. Click "Withdraw"
4. Fill withdrawal amount and method
5. Submit withdrawal request
6. Verify REST API call to /wpss/v1/wallet/withdraw (NOT setTimeout stub)
7. Verify withdrawal row in `wpss_withdrawals` with status = pending
8. **Admin:** Go to WP Admin > Sell Services > Withdrawals
9. Approve withdrawal
10. Verify status = completed, wallet balance updated

**Verify This Fix:**
- Wallet withdrawal form makes actual AJAX call (Pro security fix)
- ROLLBACK on failed wallet insert (Free security fix)

---

## Flow 10: Offline Payment Auto-Cancel

**Role:** Admin configures, System executes

1. **Admin:** Go to Settings > Gateways > Offline Payment
2. Set "Auto-Cancel Hours" to 1
3. **Buyer:** Create an offline payment order (status = pending_payment)
4. Wait 1 hour (or trigger manually):
   ```bash
   wp cron event run wpss_process_offline_auto_cancel --url=wss.local
   ```
5. Verify order status = cancelled
6. Verify notification sent to buyer and vendor

**Verify This Fix:** Card 7 - offline auto-cancel cron

---

## Flow 11: Vendor Subscription Plans (Pro)

**Role:** Admin creates plans, Vendor subscribes

1. **Admin:** Go to Settings > Vendor tab > Subscription Plans section
2. Create a free plan (name: "Starter", price: 0, max_services: 3)
3. Create a paid plan (name: "Pro", price: 19.99/month, max_services: unlimited)
4. Enable "Require Subscription to Sell"
5. **New Vendor:** Register as vendor
6. Verify auto-redirect to subscription page
7. Verify free plan auto-assigned (if default set)
8. Create services up to plan limit (3)
9. Try to create 4th service - verify blocked with upgrade message
10. Switch to Pro plan
11. Verify unlimited services allowed

**Verify These Fixes:**
- Card 4: subscription plan selection + migration
- Card 5: enforcement on frontend wizard
- Card 1: price comparison normalization

---

## Flow 12: Admin Analytics Dashboard (Pro)

**Role:** Admin

1. Go to WP Admin > Sell Services > Analytics
2. Verify dashboard loads (no critical error)
3. Verify period selector (Today, This Week, This Month, This Year, Custom Range)
4. Select "Custom Range" with start/end dates
5. Verify data loads (revenue, orders, vendors, services)
6. Click "Export CSV" - verify download
7. Check browser console - no JS errors

**Verify These Fixes:**
- Card 2: no duplicate menu
- Card 3: wpColorPicker no JS error
- Pro security: date validation on custom ranges

---

## Flow 13: Vendor Frontend Analytics (Pro)

**Role:** Vendor

1. Login as vendor, go to Dashboard > Analytics
2. Verify stat cards: Revenue, Orders, Completion Rate, Profile Views
3. Verify Profile Views > 0 (meta key fix)
4. Switch time periods (7 Days, 30 Days, 90 Days, 12 Months)
5. Verify Revenue Over Time chart
6. Verify Top Performing Services table
7. No JS errors in console

**Verify This Fix:** Card 6 - Profile Views meta key

---

## Flow 14: Service Deletion Cascade

**Role:** Admin

1. Note a service ID that has orders, conversations, reviews
2. Count related records:
   ```bash
   wp eval 'global $wpdb; $sid=251;
   echo "Orders: " . $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders WHERE service_id=%d",$sid)) . "\n";
   echo "Packages: " . $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wpss_service_packages WHERE service_id=%d",$sid)) . "\n";
   echo "Addons: " . $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wpss_service_addons WHERE service_id=%d",$sid)) . "\n";
   ' --url=wss.local
   ```
3. Permanently delete the service (trash then delete permanently)
4. Verify all related records deleted from custom tables
5. Verify no orphaned conversations, messages, deliveries

**Verify This Fix:** Card 9 - deletion cascade

---

## Flow 15: Payment Gateway Error Handling

**Role:** Developer/QA

### Stripe Error Handling
1. Configure Stripe with test keys
2. Use Stripe test card 4000 0000 0000 0002 (decline)
3. Attempt checkout
4. Verify error message shown to user (not silent failure)
5. Verify no phantom order created

### PayPal Webhook Security
1. Send forged POST to webhook URL without signature headers
2. Verify rejected (401/500), not processed
3. With valid webhook_id configured, send properly signed webhook
4. Verify processed correctly

**Verify These Fixes:** Cards 14, 15

---

## Flow 16: White Label Branding (Pro)

**Role:** Admin

1. Go to Settings > Branding tab
2. Set custom brand name, logo URL, primary color
3. Save settings
4. Verify admin menu shows custom brand name
5. Verify vendor dashboard shows custom logo and colors
6. Verify emails use custom branding

---

## QA Checklist Summary

| Flow | Description | Priority | Status |
|------|-------------|----------|--------|
| 1 | Vendor registration | P0 | [ ] |
| 2 | Service creation wizard | P0 | [ ] |
| 3 | Buyer purchase checkout | P0 | [ ] |
| 4 | Self-purchase prevention | P1 | [ ] |
| 5 | Order lifecycle (happy path) | P0 | [ ] |
| 6 | Order cancellation | P1 | [ ] |
| 7 | Dispute resolution | P1 | [ ] |
| 8 | Buyer requests + proposals | P1 | [ ] |
| 9 | Wallet + withdrawals | P0 | [ ] |
| 10 | Offline auto-cancel | P2 | [ ] |
| 11 | Vendor subscription plans | P1 | [ ] |
| 12 | Admin analytics | P1 | [ ] |
| 13 | Vendor analytics | P1 | [ ] |
| 14 | Deletion cascade | P2 | [ ] |
| 15 | Gateway error handling | P1 | [ ] |
| 16 | White label branding | P2 | [ ] |
