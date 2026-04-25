# WP Sell Services 1.1.0 — Feature Specs

> **What this doc is:** the source of truth for what each plugin feature MUST do. Drives the completeness audit (`plans/1.1.0-COMPLETENESS-AUDIT.md`) — every audit cell is scored against the SPEC + PASS CRITERIA defined here.
>
> **What this doc is NOT:** a wish-list. Anything in here is something the plugin already claims to do. Missing capabilities go to `plans/future-features/`.

**Versioning:** This doc evolves with each release. When a feature changes behaviour, update the SPEC here in the same PR — the spec drift is the bug.

---

## Cross-cutting checks

These 10 checks apply to **every** feature × persona cell. Audit walks score each cell on these in addition to the feature-specific journey.

| # | Check | Why it matters |
|---|---|---|
| 1 | **No silent failures** — every action triggers visible response (toast, inline message, state change, navigation) | Buyers + vendors abandon when they don't know if a click worked |
| 2 | **Mobile (390px)** — the surface tested also works on phone-width viewport | Marketplace traffic is majority-mobile in 2025+ |
| 3 | **i18n** — every visible string passes through `__()` / `esc_html__()` etc. with `wp-sell-services` text-domain (grep-checkable) | Translatable shipping = international markets |
| 4 | **Permission boundaries** — every action capability-checked + nonce-verified | Owasp top-10 + WordPress security baseline |
| 5 | **Audit log** — every meaningful state change writes to `wpss_audit_log` (or has a justified reason it doesn't) | Dispute evidence + admin forensics |
| 6 | **REST + AJAX parity** — every action available via both endpoints | Future mobile app + headless integrations don't have to be retrofitted |
| 7 | **Empty states** — every list has a designed empty state (icon + headline + CTA), never a bare "0 results" sentence | Empty state is the FIRST experience for many users |
| 8 | **Pro plugin compat** — every action hook + filter Pro depends on still fires with the same signature | Pro plugin must keep working without modification |
| 9 | **Debug log clean** — running the journey produces zero PHP warnings/notices in `debug.log` | Hidden bugs surface as logs first |
| 10 | **Accessibility (WCAG 2.1 AA basics)** — keyboard-navigable, ARIA labels on interactive elements, sufficient contrast | Legal + 15%+ of users have an accessibility need |

---

## How to read each feature spec

```
SPEC: 3-7 bullets describing what the feature MUST do
JOURNEY TEST: numbered steps to verify the SPEC end-to-end
PER-PERSONA EXPECTATIONS: per-row what each user type should see/do
PASS CRITERIA: timing + cleanliness threshold
```

A cell is ✅ when JOURNEY TEST runs clean against the SPEC, no cross-cutting check fails. Anything else gets the appropriate severity emoji.

---

# Vendor lifecycle

## Feature 1 — Vendor signup

**SPEC:**
- Logged-out visitor can become a vendor in one form on `/become-a-vendor/` (no wp-login bounce)
- Logged-in non-vendor can promote to vendor in one click from the same page
- Open mode: vendor instantly active + can post services
- Approval mode: vendor in pending state, admin sees approval queue with one-click approve/reject
- Closed mode: form replaced with "registration closed" message
- After signup: `wpss_vendor_profiles` row created with status, `_wpss_is_vendor` user meta = true (when active), role granted

**JOURNEY TEST:**
1. As logged-out visitor, navigate `/become-a-vendor/` → styled signup form (NOT wp-login)
2. Submit name + email + password → user created + auto-signed in + redirected to dashboard
3. Verify DB row in `wp_wpss_vendor_profiles`, role assignment
4. (Approval mode) Switch admin setting to approval, repeat steps 1-2 → user created with status=pending, sees "Pending Approval" badge
5. As admin, navigate Sell Services → Vendors → Pending tab → see new application
6. Click Approve → vendor's status flips to active, role granted, vendor is notified

**PER-PERSONA EXPECTATIONS:**
- admin: Vendors list shows Pending count + filter tab; Approve/Reject in row actions; receives email when new pending application created
- vendor (active): Page shows "You're already a vendor" card with Go to Dashboard CTA
- vendor (pending): Sees pending status badge in dashboard sidebar + footer note "Your vendor application is pending admin approval"
- buyer (with orders): Page shows full signup card with "Register as Vendor" button (1-click promote since already logged in)
- buyer (fresh): As above

**PASS CRITERIA:** All 6 steps complete in <3 minutes. Zero debug.log entries. Email + in-app notif fire on every status transition.

---

## Feature 2 — Vendor profile

**SPEC:**
- Vendor can edit: avatar, cover image, display name, tagline, bio, country, city, website, intro video (YouTube/Vimeo)
- Vendor can set vacation mode (auto-pauses new orders + shows badge on profile)
- Profile changes save instantly via REST/AJAX with visible feedback
- Profile completeness banner appears on dashboard until tagline + bio + country set (CB7 from earlier audit)

**JOURNEY TEST:**
1. As vendor, navigate dashboard → Profile section
2. Edit each field one at a time, save, reload, verify persisted
3. Toggle vacation mode → verify badge appears on public profile
4. Verify intro video URL accepts YouTube + Vimeo formats; rejects raw .mp4 URLs
5. Profile completion banner verification: empty profile → banner shows; fill all 3 fields → banner gone

**PER-PERSONA EXPECTATIONS:**
- admin: Edit User screen shows the same fields plus admin-only ones (verification, suspension); Sell Services → Vendors → row actions → Edit User opens admin user editor with vendor sections
- vendor (active): Full edit access to own profile
- vendor (pending): Can pre-fill profile while waiting for approval — visible but not yet on marketplace
- buyer: N/A (cannot edit vendor profile)

**PASS CRITERIA:** Each field saves in <1 second with green confirmation. Vacation mode reflects on public profile within 1 page reload.

---

## Feature 3 — Vendor public profile page

**SPEC:**
- `/vendor/<username>/` (or via `[wpss_vendor_profile id=N]` shortcode) renders public profile
- Shows: avatar, cover, name, tagline, bio, country, intro video (if set), seller level badge, total reviews, average rating
- Lists vendor's published services in a grid below profile header
- Lists vendor's portfolio items
- Has "Contact" / "View services" CTA

**JOURNEY TEST:**
1. Visit vendor public URL as logged-out user → all profile fields render
2. Visit as logged-in buyer → contact CTA enabled
3. Visit as a different vendor → contact CTA hidden (vendors don't message vendors)
4. Visit as vendor's own user → shows "Edit profile" inline link
5. Vendor with 0 services → empty state in services grid, NOT a bare "no services"

**PER-PERSONA EXPECTATIONS:**
- admin: Sees "Edit User" admin link in profile header (when admin bar is visible)
- vendor (active, viewing own profile): Sees "Edit profile" inline CTA
- vendor (pending): Profile page returns 404 OR "vendor not yet active" message — they should NOT be public yet
- buyer: Standard profile view + Contact CTA enabled
- buyer fresh: Same as buyer with orders

**PASS CRITERIA:** Profile renders in <2 seconds. All clickable links go somewhere (no 404s). Mobile layout works at 390px.

---

## Feature 4 — Seller levels + tier promotion

**SPEC:**
- 4 tiers: New / Rising / Top Rated / Pro
- Auto-promotion based on completed orders + average rating + on-time delivery (rules in `SellerLevelService`)
- Vendor sees current tier + progress to next on dashboard
- Tier badge appears on service cards + vendor profile
- Admin can manually grant tier override

**JOURNEY TEST:**
1. As vendor, visit dashboard → see current tier prominently
2. Verify next-tier requirements visible ("Need 5 more completed orders + 4.8 rating to reach Top Rated")
3. Manually trigger tier recalc cron via WP-CLI → verify ladder works
4. As admin, override a vendor's tier → verify badge updates immediately
5. Tier badge color-coded on service cards: New=grey OR "New seller" badge if profile incomplete (per F7b)

**PER-PERSONA EXPECTATIONS:**
- admin: Has "Override Tier" action on vendor row; sees tier history per vendor
- vendor (active): Sees current tier + next-tier requirements + actions needed
- vendor (pending): N/A — pending vendors don't have a tier yet
- buyer: Sees tier badge on service cards; helps them filter by trust level
- buyer fresh: Same as buyer

**PASS CRITERIA:** Tier promotion logic correct (cron run produces expected results). Badge displays consistently across marketplace, single service, vendor profile, and order pages.

---

## Feature 5 — Vendor dashboard sections (Selling group)

**SPEC:**
- Sidebar Selling group: My Services / Sales Orders / Portfolio / Wallet & Earnings / Analytics
- Each section loads in <1 second, has a designed empty state, has bulk actions on lists where applicable
- Stats card strip at top of each section reflects accurate numbers
- Mobile: sidebar collapses to drawer, sections stack readably

**JOURNEY TEST:**
1. As vendor, click each Selling section in turn → loads correctly
2. Verify stats numbers match DB (e.g. Sales Orders shows total_orders count from get_vendor_stats)
3. Verify empty states render properly (filter to a period with no data)
4. Mobile (390px): sidebar collapses, all sections accessible

**PER-PERSONA EXPECTATIONS:**
- admin: Has all sections visible + can also impersonate (View Dashboard As) — wait, do we have impersonate? Check + add if missing
- vendor (active): Full access to all 5 sections
- vendor (pending): Selling group hidden in sidebar; only Buying + Account visible
- buyer (with orders): Selling group hidden
- buyer fresh: Selling group hidden

**PASS CRITERIA:** All 5 sections load + render in <1 second. No console errors. Empty states all use designed pattern.

---

# Service lifecycle

## Feature 6 — Service creation wizard

**SPEC:**
- 6-step wizard: Basic Info / Pricing / Gallery / Requirements / Extras & FAQs / Review
- Auto-saves drafts on every change (debounced) with visible "Saved" confirmation (per F5)
- Single Basic tier required; Standard + Premium opt-in via add-tier buttons (per F1)
- Inline + summary form errors with auto-scroll on validation failure (per B3)
- Stepper steps clickable to jump back to completed steps only (per B4)
- Publish blocked until: title ≥10 chars + category set + description ≥120 chars + Basic price ≥$5 + main image uploaded

**JOURNEY TEST:**
1. As vendor, click "Create Service" → wizard opens at Basic Info
2. Try Continue empty → form-level summary + inline errors appear
3. Fill Basic Info → Continue → Pricing step
4. Verify only Basic tab visible by default; click "+ Add Standard tier"
5. Set price + delivery → Continue → Gallery
6. Upload main image; expand "+ Add more media" optional disclosure
7. Continue → Requirements (skip), Extras & FAQs (skip), Review
8. Click Publish → service created in DB, redirected to live service page (or pending-approval message if moderation enabled)
9. Open new draft, edit, verify auto-save indicator transitions: Saving → Saved → fades

**PER-PERSONA EXPECTATIONS:**
- admin: Same as vendor (admin is auto-vendor); also sees admin-only fields in Edit Service screen
- vendor (active): Full wizard access
- vendor (pending): Should be BLOCKED from creating services (status check in `can_create_services`); friendly redirect to dashboard with explanation
- buyer: Should be redirected to "Become a vendor first" page
- buyer fresh: Same as buyer with orders

**PASS CRITERIA:** 6-step wizard complete in <5 minutes for a tester following the test plan. Zero silent failures. Auto-save fires within 1 second of last keystroke.

---

## Feature 7 — Service packages (Basic / Standard / Premium tiers)

**SPEC:**
- Each service has 1-3 pricing tiers
- Per tier: name, price (≥$5), delivery time (1-30 days), revisions (0-5 or unlimited), feature bullets
- Buyer selects tier on service detail page (default = Basic)
- Selected tier carries through to checkout + order detail

**JOURNEY TEST:**
1. As vendor, edit a service with all 3 tiers configured
2. As buyer, view service detail → tier comparison table renders
3. Click Standard → price + delivery + revisions update
4. Continue to checkout → confirms Standard tier price
5. Complete order → order detail shows Standard tier features

**PER-PERSONA EXPECTATIONS:**
- admin: Sees all tiers in Edit Service metabox; can edit
- vendor: Edits via wizard or post metabox
- buyer: Compares tiers + selects via radio
- buyer fresh: Same — needs Sign in or Sign up redirect when clicking Buy

**PASS CRITERIA:** Tier price math correct end-to-end (selected tier price === checkout total === order total === wallet credit basis).

---

## Feature 8 — Service add-ons (extras)

**SPEC:**
- Service can have 0-N optional add-ons
- Per add-on: title, description, price, extra delivery days
- Buyer can select add-ons on service detail or during checkout
- Add-on price + days roll into order total + delivery deadline

**JOURNEY TEST:**
1. As vendor, edit service → add 2 add-ons (each $10 + 1 day)
2. As buyer, view service → see add-on checkboxes
3. Select 1 add-on → checkout total reflects $10 + service base
4. Order detail shows selected add-ons in summary

**PER-PERSONA EXPECTATIONS:**
- admin: Edits via metabox
- vendor: Edits via wizard step 5 or post metabox
- buyer: Selects on service detail, sees in checkout + order
- buyer fresh: Same

**PASS CRITERIA:** Add-on price + days sum into order correctly. Vendor wallet credit reflects add-on revenue.

---

## Feature 9 — Service FAQs

**SPEC:**
- Service can have 0-N FAQ items
- Per FAQ: question + answer
- Display on service detail page in collapsible accordion

**JOURNEY TEST:**
1. As vendor, add 3 FAQs to a service via wizard
2. As buyer, view service → see FAQ section with collapse/expand
3. Verify default state (all collapsed)
4. Verify mobile rendering

**PER-PERSONA EXPECTATIONS:**
- All personas: read-only on service detail; only vendor can edit

**PASS CRITERIA:** Collapse/expand works. Mobile readable.

---

## Feature 10 — Service requirements

**SPEC:**
- Service can define custom required questions for buyers
- Per requirement: question, answer type (text/textarea/file/select), required toggle
- After buyer pays, they're prompted to fill requirements before vendor starts work
- Vendor can see submitted answers on order detail
- Progress bar shows X of N required answered (per CB2)

**JOURNEY TEST:**
1. As vendor, define 3 requirements (1 text required, 1 textarea optional, 1 file required)
2. As buyer, complete checkout → land on requirements form
3. Verify progress bar starts at 0 of 2
4. Fill text → progress 1 of 2; upload file → progress 2 of 2
5. Submit → order transitions to in_progress
6. As vendor, view order → see all 3 answers including file link

**PER-PERSONA EXPECTATIONS:**
- admin: Read-only access to requirements via order detail
- vendor: Sees submitted answers on order detail
- buyer (with orders): Fills requirements form when prompted post-payment
- buyer fresh: Same path on first order

**PASS CRITERIA:** Form blocks submit until required answered. File uploads complete + linked correctly. Progress bar updates live.

---

## Feature 11 — Service moderation

**SPEC:**
- Admin can enable moderation in settings (off by default)
- When on: new/edited services go to "pending" status, shown to admin for review
- Admin approves → service published; rejects → vendor notified with reason
- Vendor sees "pending review" status on their service in My Services

**JOURNEY TEST:**
1. As admin, enable moderation in Sell Services → Settings → Orders
2. As vendor, publish a new service → expected status=pending (NOT publish)
3. As admin, navigate Sell Services → Moderation → see pending service
4. Click Approve → service status flips to publish, vendor notified
5. Reject another → service stays pending with rejection reason; vendor sees + can revise

**PER-PERSONA EXPECTATIONS:**
- admin: Has full moderation queue access; bulk approve/reject
- vendor (active): Sees pending status badge on services in My Services tab
- vendor (pending): Can publish but services moderated like everyone else
- buyer: Doesn't see pending services on marketplace
- buyer fresh: Same

**PASS CRITERIA:** Moderation toggle works. Pending services hidden from marketplace. Approve/reject + reason emails fire.

---

## Feature 12 — Marketplace listing + search

**SPEC:**
- `/services/` archive lists all published services in a grid
- Filter sidebar: category, price range, seller rating, delivery time
- Sort options: recommended (default), date, rating, sales, price asc/desc
- Search box filters by title + description
- Pagination at bottom
- Each card: thumbnail, vendor avatar+name, tier badge, title, starting price, rating

**JOURNEY TEST:**
1. As any persona, visit `/services/` → grid renders
2. Filter by category → list narrows
3. Sort by Rating → order changes
4. Search "logo" → matches narrow
5. Click a card → service detail page loads
6. Mobile: filters collapse to drawer

**PER-PERSONA EXPECTATIONS:**
- admin: Sees all services including drafts? Probably no — admin views drafts in admin only. Verify.
- vendor: Sees own + others' published services
- vendor (pending): Same as logged-out — sees published only
- buyer: Standard browse experience
- buyer fresh: Same

**PASS CRITERIA:** Filters return correct results. Search returns relevant matches. Pagination works.

---

# Order lifecycle

## Feature 13 — Standalone checkout + payment gateways

**SPEC:**
- `[wpss_checkout]` shortcode renders the checkout page
- Shows: service summary, price breakdown, payment method radio, vendor card, trust signals, "What happens next" stepper
- Supports Test, Offline, Stripe (Pro), PayPal (Pro), Razorpay (Pro)
- Standalone is the FREE plugin's default; Pro adapters take over only when WC/EDD/etc. plugin active + admin selects

**JOURNEY TEST:**
1. Set adapter to Standalone in admin
2. As buyer, click Buy on a service → checkout page renders with all sections
3. Select Test Gateway → submit → order created
4. Verify order status, redirect to order page

**PER-PERSONA EXPECTATIONS:**
- admin: Test Gateway visible (only when WP_DEBUG)
- vendor: Can buy other vendors' services as a buyer
- vendor (pending): Same
- buyer (with orders): Standard checkout
- buyer fresh: Standard checkout — guest support? Or login required? Verify

**PASS CRITERIA:** Checkout completes in <30 seconds. Payment processed. Order created. Redirect works.

---

## Feature 14 — Order workflow (11 statuses)

**SPEC:**
- Order moves through: pending_payment → pending_requirements → in_progress → pending_approval → completed (happy path)
- Side branches: cancellation_requested, cancelled, revision_requested, late, on_hold, disputed, refunded
- Each transition fires `wpss_order_status_changed` action + status-specific actions (e.g. `wpss_order_status_completed`)
- All transitions logged in audit log
- Illegal transitions (e.g. cancelled → completed) rejected at OrderService layer

**JOURNEY TEST:**
1. Create order via checkout → status=pending_payment
2. Pay (Test Gateway) → status=pending_requirements (or in_progress if no requirements)
3. Buyer submits requirements → status=in_progress
4. Vendor delivers → status=pending_approval
5. Buyer requests revision → status=revision_requested
6. Vendor re-delivers → status=pending_approval
7. Buyer approves → status=completed
8. Verify wallet credit, audit log entries, emails at every step
9. Edge: try `OrderService::update_status($id, 'completed')` while status=pending_payment → should reject

**PER-PERSONA EXPECTATIONS:**
- admin: Can manually transition any order via admin Orders page; sees full audit log per order
- vendor: Sees correct action button per status (Submit Delivery / Mark Complete / etc.)
- vendor (pending): Cannot fulfil orders — should see "complete vendor application first" if they land on one
- buyer: Sees status timeline; correct action button (Approve / Request Revision / Open Dispute)
- buyer fresh: N/A (no orders)

**PASS CRITERIA:** Full happy path completes in <5 minutes. Money math correct at completion. Zero illegal transitions accepted. Zero debug.log warnings.

---

## Feature 15 — Requirements collection from buyer

**SPEC:**
- After payment, if service has requirements, buyer is routed to requirements form
- Form supports text/textarea/file/select fields
- Required fields enforced
- Submitted answers visible to vendor on order detail
- Late requirements: filter `wpss_allow_late_requirements_submission` enables buyer to submit after deadline

**JOURNEY TEST:**
- Same as Feature 10 SPEC + journey, plus verify late submission filter works

**PER-PERSONA EXPECTATIONS:** Same as Feature 10.

**PASS CRITERIA:** Same as Feature 10.

---

## Feature 16 — Conversation / messaging

**SPEC:**
- Each order has a conversation thread between buyer + vendor
- Messages support text + file attachments
- Date dividers group messages (Today / Yesterday / weekday / full date — per CB7)
- Polling refreshes new messages without reload
- System messages note status transitions inline
- Email + in-app notification on new message (subject to per-vendor preferences — VS11)

**JOURNEY TEST:**
1. As vendor + buyer, exchange 5 messages on an order
2. Refresh page → all messages persist with correct date dividers
3. Attach a file → recipient sees download link
4. Long thread spanning 3 days → dividers correct (Today / Yesterday / weekday)
5. Verify new-message email + in-app notif fire

**PER-PERSONA EXPECTATIONS:**
- admin: Read-only access to all conversations via admin Orders → order detail
- vendor: Full access to own orders' threads
- vendor (pending): N/A
- buyer (with orders): Full access to own orders' threads
- buyer fresh: N/A

**PASS CRITERIA:** Messages send + receive in <2 seconds. Date dividers always correct. Attachments accessible by both parties only.

---

## Feature 17 — Delivery submission + revision request

**SPEC:**
- Vendor can submit delivery on orders in `in_progress`, `revision_requested`, or `late` status
- Delivery includes: description, file attachments
- Status transitions to pending_approval
- Buyer can: Approve (→ completed) or Request Revision (→ revision_requested)
- Revision count tracked + visible badge per CB3+VS3
- Final delivery accessible to buyer permanently

**JOURNEY TEST:**
1. As vendor, navigate active order → click Submit Delivery
2. Fill description + attach 2 files → submit → status=pending_approval
3. As buyer, see delivery panel with files
4. Click Request Revision → enter notes → status=revision_requested + revision_count incremented
5. Vendor re-delivers → buyer approves → status=completed
6. Verify badge shows "1 of 3 revisions used" then "All 3 used" if exhausted

**PER-PERSONA EXPECTATIONS:**
- admin: Read-only; can intervene if dispute opened
- vendor: Submits delivery, sees revision count
- buyer: Approves or requests revision
- buyer fresh: N/A

**PASS CRITERIA:** Files upload + download work. Revision count enforced (no infinite). Status transitions correct.

---

## Feature 18 — Cancellation request

**SPEC:**
- Either buyer or vendor can request cancellation while order is in_progress / pending_approval
- Other party has 48 hours to respond before auto-cancel cron fires (CB5+VS7)
- Counterpart sees deadline countdown ("Auto-cancels in 15h 59m if no response")
- Refund processed via original payment gateway on cancellation

**JOURNEY TEST:**
1. As buyer, open active order → click Cancel Order → reason dropdown + note
2. Submit → status=cancellation_requested + countdown banner appears for both parties
3. As vendor, accept → order cancelled, refund processed; OR reject → order resumes
4. Test auto-cancel: set requested_at to -49h → run cron → status=cancelled

**PER-PERSONA EXPECTATIONS:**
- admin: Sees cancellation requests in admin Orders; can override
- vendor: Sees buyer's request + Accept/Reject buttons
- vendor (pending): N/A
- buyer: Initiates request; sees their own request awaiting vendor response
- buyer fresh: N/A

**PASS CRITERIA:** Refund hits buyer's payment method correctly. Vendor wallet untouched (no credit on cancelled). Audit log captures who initiated + responded.

---

## Feature 19 — Disputes (open, evidence, resolve, escalate)

**SPEC:**
- Buyer can open dispute on completed/in-progress order
- Both parties add evidence (text + files) over a 7-day window
- After window: dispute auto-escalates to admin if unresolved
- Admin reviews evidence, resolves with outcome (refund full / partial / vendor wins) + writes resolution notes
- Status explainer card per state: Open / Pending / Escalated / Resolved / Closed (per VS6)

**JOURNEY TEST:**
1. As buyer, open dispute on order → status explainer card shows "Open"
2. Add evidence → vendor notified
3. Vendor adds counter-evidence → status=pending_review
4. Skip 7 days (manually trigger cron) → status=escalated → admin notified
5. As admin, review evidence → click Resolve → choose outcome → enter notes
6. Refund processed if applicable; status=resolved

**PER-PERSONA EXPECTATIONS:**
- admin: Disputes admin page lists all disputes by status; can resolve with outcome dropdown
- vendor: Receives dispute-opened email + can add evidence
- buyer: Initiates + can add evidence
- buyer fresh: N/A

**PASS CRITERIA:** Refund math correct on resolution. Both parties see resolution outcome. Audit log complete.

---

## Feature 20 — Reviews

**SPEC:**
- Buyer can leave 1-star to 5-star review with optional written comment after order completion
- Review appears on service detail + vendor profile within 1 page reload
- Vendor receives email with rating + comment + link to service page (per CB4)
- Admin can hide/delete reviews if abusive
- Average rating displayed on service cards + vendor profile

**JOURNEY TEST:**
1. As buyer, complete order → see "Leave a Review" CTA
2. Click → modal opens with stars + comment field
3. Submit 5-star + comment → confirmation + redirected back to order
4. Verify review appears on service detail page
5. Vendor receives email + in-app notif

**PER-PERSONA EXPECTATIONS:**
- admin: Can moderate reviews via admin
- vendor: Receives review email; can read on service detail
- vendor (pending): N/A
- buyer (with orders): Can leave review on each completed order once
- buyer fresh: N/A

**PASS CRITERIA:** Review submission instant. Email delivers within 1 minute. Average rating recalculates correctly.

---

# Sub-order patterns

## Feature 21 — Tipping

**SPEC:**
- Buyer can send tip to vendor on completed orders
- Tip creates a sub-order with `platform=tip` + `platform_order_id` linking to parent
- Tip amount: preset $5/$10/$25 or custom
- Payment via same gateway as original order
- Vendor gets email + in-app notif (per VS11 prefs)
- Tip ledger entry separate from regular earnings

**JOURNEY TEST:**
1. As buyer, complete an order → "Send a Tip" CTA on completed order
2. Click → modal with preset amounts + custom + optional message
3. Submit + pay → tip sub-order created + paid → vendor wallet credited NET
4. Verify breadcrumb on tip view: "↳ Tip on order #..." links to parent (per CB6)
5. Vendor receives "tip received" email

**PER-PERSONA EXPECTATIONS:**
- admin: Sees tip in admin Orders list with TIP badge
- vendor: Receives notification; sees tip in earnings + sales list with TIP badge
- buyer: Sends tip on completed orders only
- buyer fresh: N/A

**PASS CRITERIA:** Money math correct (commission applies; vendor gets NET). Tip never counts toward review prompt.

---

## Feature 22 — Paid extensions (catalog orders)

**SPEC:**
- Vendor can quote extension on catalog orders (NOT request orders, NOT milestones)
- Extension: extra amount + extra days + reason
- Buyer accepts + pays → vendor wallet credited; parent deadline pushed by extra_days
- Extension confirmation email shows OLD → NEW deadline (per VS5)
- Mutual exclusion: catalog orders can't have milestones; request orders can't have extensions

**JOURNEY TEST:**
1. As vendor on active catalog order, click "Buyer asked for extra work?" CTA
2. Quote $50 + 3 days + reason → extension sub-order created
3. Buyer accepts + pays → status=completed, parent deadline +3 days
4. Vendor email shows "Was: April 21 | Now: April 24"
5. Try extension on a request order → should be blocked at service layer

**PER-PERSONA EXPECTATIONS:**
- admin: Read-only via admin Orders
- vendor: Initiates extension on catalog orders only
- buyer: Accepts/declines + pays
- buyer fresh: N/A

**PASS CRITERIA:** Mutual exclusion enforced. Deadline math correct. Email shows both old + new dates.

---

## Feature 23 — Milestones (Upwork-style proposal flow)

**SPEC:**
- On a buyer-request order, vendor can submit a proposal with multiple milestone phases
- Each phase: title, description, amount, days, deliverables
- Buyer accepts proposal → all phases pre-created as sub-orders
- Lock-step payment: phase N can only be paid AFTER phase N-1 is approved
- Auto-complete parent on final phase approval
- Cascade-cancel pending phases on parent cancellation

**JOURNEY TEST:**
1. As buyer, post a request → vendor sees in proposal queue
2. Vendor submits Milestone-based proposal (3 phases)
3. Buyer accepts → 3 sub-orders created, all in pending_payment
4. Pay phase 1 → status=in_progress for phase 1; phase 2 still locked
5. Vendor delivers phase 1 → buyer approves → phase 2 unlocks
6. Repeat for phase 2 + 3
7. After phase 3 approved → parent status=completed automatically

**PER-PERSONA EXPECTATIONS:**
- admin: Read-only via admin Orders
- vendor: Submits + delivers per phase
- buyer: Accepts proposal, pays each phase, approves each delivery
- buyer fresh: Could be brand new — verify they can post a buyer request without ever having ordered

**PASS CRITERIA:** Lock-step enforced (URL tampering rejected). Auto-complete parent works. Cascade-cancel works.

---

# Buyer requests + proposals

## Feature 24 — Buyer requests + proposals

**SPEC:**
- Buyer can post a request describing what they need (no listed service required)
- Request includes: title, description, budget, deadline, attachments, category
- Vendors see open requests in dashboard → can submit proposals
- Proposal: cover letter, fixed price OR milestone breakdown, delivery time
- Buyer reviews proposals → accepts one → order created from accepted proposal

**JOURNEY TEST:**
1. As buyer fresh (never ordered), post a request via `[wpss_post_request]`
2. Verify form requires: title, description, category, budget
3. Submit → request appears in vendor's dashboard
4. Vendor browses requests → submits proposal (Fixed type)
5. Buyer reviews proposals → accepts → order created
6. Verify all OTHER proposals on the request are auto-rejected
7. Verify request status flips to "hired"

**PER-PERSONA EXPECTATIONS:**
- admin: Read-only; can moderate inappropriate requests
- vendor (active): Browses + submits proposals
- vendor (pending): Can browse but cannot submit (not yet approved)
- buyer (with orders): Can post requests
- buyer fresh: Can post requests as their first action — no order required

**PASS CRITERIA:** Request post → vendor proposal → accept → order = end-to-end completes in <5 min. Auto-rejection of competing proposals works.

---

# Earnings + payouts

## Feature 25 — Earnings dashboard + withdrawals

**SPEC:**
- Vendor sees earnings summary: Available Balance / In Clearance / Pending Withdrawal / Total Withdrawn
- Earnings ledger lists every transaction with type (Earning/Tip/Extension/Milestone/Withdrawal/Refund)
- Withdrawal request: amount + payout method
- Admin approves/rejects withdrawal requests; can configure auto-approval threshold
- Clearance window configurable (default 14 days, per VS1)
- CSV export of ledger (Pro only — verify free vs Pro behaviour)

**JOURNEY TEST:**
1. As vendor with completed orders, navigate Wallet & Earnings
2. Verify 4 stat cards (Available / In Clearance / Pending / Withdrawn) sum correctly
3. Click Request Withdrawal → enter amount → submit
4. Admin notified; vendor sees pending withdrawal in Pending bucket
5. As admin, navigate Sell Services → Withdrawals → approve
6. Vendor's Pending bucket → 0; Withdrawn bucket → +amount

**PER-PERSONA EXPECTATIONS:**
- admin: Withdrawals queue with approve/reject; can edit auto-payout settings
- vendor (active): Full earnings + withdrawal access
- vendor (pending): Sees blank earnings (no completed orders); can't withdraw
- buyer: N/A
- buyer fresh: N/A

**PASS CRITERIA:** All money math reconciles to the cent. Withdrawal cycle completes (request → admin approve → vendor sees Withdrawn). Auto-payout cron works if enabled.

---

## After audit — what to do with findings

Each cell in `plans/1.1.0-COMPLETENESS-AUDIT.md` matrix gets a severity emoji + 1-line note. After full walk:

1. Group findings by severity (🔴 / 🟠 / 🟡)
2. Estimate fix effort per item
3. Open `feat/1.1.0-completeness` branch
4. Ship one commit per fix with browser-verify
5. Re-walk fixed cells to confirm green
6. Tag `v1.1.0` once 100% ✅
