# WP Sell Services Free — Functionality Flow Checklist

**Purpose:** Step-by-step guide for site owners to set up and verify every feature in the Free plugin.
**Created:** 2026-02-20
**How to use:** Follow each phase in order. Check off items as you verify them on a fresh install.

---

## Phase 1: Admin Setup

### Flow 1: Plugin Activation
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Install and activate plugin | No PHP errors | [ ] |
| 2 | Database tables created | All custom tables exist (wpss_orders, wpss_deliveries, wpss_conversations, wpss_reviews, wpss_disputes, wpss_service_packages, wpss_order_requirements, wpss_earnings, wpss_withdrawals, etc.) | [ ] |
| 3 | Vendor role created | `wpss_vendor` role with correct capabilities (wpss_vendor, upload_files, edit_posts) | [ ] |
| 4 | Default options set | All `wpss_*` options created with defaults | [ ] |
| 5 | Cron events scheduled | `wpss_auto_complete_orders`, `wpss_cleanup_expired_requests`, `wpss_update_vendor_stats` | [ ] |
| 6 | Rewrite rules flushed | Service/request permalinks work | [ ] |
| 7 | Redirect to Setup Wizard | First-time activation redirects to wizard page | [ ] |

### Flow 2: Setup Wizard (6 Steps)
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Step 1 — Platform Basics | Marketplace name (pre-filled from site name), currency dropdown, commission rate | [ ] |
| 2 | Step 2 — Payment Gateway | Radio: Stripe / PayPal / Offline. Each shows relevant fields (API keys, sandbox toggle) | [ ] |
| 3 | Step 3 — Create Pages | 4 pages: Services, Dashboard, Vendor Registration, Checkout. "Create All" button works | [ ] |
| 4 | Step 4 — Service Categories | 10 preset chips (Web Dev, Design, Writing, etc.) + custom input. Creates taxonomy terms | [ ] |
| 5 | Step 5 — Vendor Settings | Registration mode (Open/Approval), max services, moderation toggle, verification toggle | [ ] |
| 6 | Step 6 — Done | Success message, "Create First Service" / "Import Demo" / "Go to Settings" cards. `wpss_setup_wizard_completed` option set | [ ] |
| 7 | Skip buttons | Each step can be skipped, defaults remain | [ ] |
| 8 | Re-run wizard | Settings > Advanced > "Re-run Setup Wizard" opens wizard with current values | [ ] |

### Flow 3: Settings Configuration
| # | Tab | Key Settings | Status |
|---|-----|-------------|--------|
| 1 | General | Platform name, currency (10 options), e-commerce platform (auto-detect/standalone) | [ ] |
| 2 | Pages | Services page, Dashboard page, Vendor Registration page, Checkout page — dropdown assignment or "Create Page" | [ ] |
| 3 | Payments (accordion) | Commission rate (0-50%), per-vendor rates toggle, tax settings, payout settings (min withdrawal, clearance period, auto-withdrawal) | [ ] |
| 4 | Gateways (accordion) | Stripe (test/live keys, 3D Secure), PayPal (sandbox/live, client ID/secret), Offline (title, instructions, proof upload) | [ ] |
| 5 | Vendor (accordion) | Registration mode (Open/Approval/Closed), max services, require verification, service moderation | [ ] |
| 6 | Orders (accordion) | Auto-complete days, default revisions, allow disputes, dispute window, requirements timeout, auto-start on timeout | [ ] |
| 7 | Emails (accordion) | 8 notification types (New Order, Completed, Cancelled, Delivery, Revision, Message, Review, Dispute) — each toggleable | [ ] |
| 8 | Advanced (accordion) | Delete data on uninstall, debug mode, demo content import/delete, re-run setup wizard | [ ] |
| 9 | Tab navigation | All tabs load correctly, active tab highlighted, accordion sections expand/collapse | [ ] |
| 10 | Save & reload | Settings persist after page reload | [ ] |

---

## Phase 2: Vendor Onboarding

### Flow 4: Vendor Registration
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Visit "Become a Vendor" page | Registration form visible (requires logged-in user) | [ ] |
| 2 | Logged-out user | Sees login/register prompt | [ ] |
| 3 | Fill registration form | Tagline, bio, skills, profile photo, payment details, terms checkbox | [ ] |
| 4 | Submit application | Success message shown | [ ] |
| 5 | Open registration mode | User immediately gets `wpss_vendor` role, can create services | [ ] |
| 6 | Approval mode | User sees "pending approval" status, cannot create services yet | [ ] |
| 7 | "Start Selling" dashboard button | Alternative path — one-click vendor conversion from unified dashboard | [ ] |
| 8 | Already a vendor | Shows "you're already a vendor" notice | [ ] |

### Flow 5: Vendor Approval (if moderation enabled)
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Admin sees pending applications | Listed in admin panel | [ ] |
| 2 | Admin approves vendor | Vendor role assigned, vendor notified via email | [ ] |
| 3 | Admin rejects vendor | Application rejected, rejection reason sent via email | [ ] |
| 4 | Approved vendor accesses dashboard | Full vendor dashboard available, can create services | [ ] |
| 5 | Rejected vendor can reapply | Option to submit new application | [ ] |

### Flow 6: Vendor Profile Setup
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Go to Dashboard > Settings | Profile settings form visible | [ ] |
| 2 | Edit display name, bio, tagline | Fields accept input and save | [ ] |
| 3 | Upload/change avatar | Image upload works (requires upload_files cap) | [ ] |
| 4 | Set payment method | PayPal email or bank details saved | [ ] |
| 5 | Set notification preferences | Email toggle for each notification type | [ ] |
| 6 | Save settings | Changes persist on reload | [ ] |
| 7 | Public vendor profile | Profile visible to buyers on vendor page | [ ] |

---

## Phase 3: Service Creation

### Flow 7: Service Creation Wizard (6 Steps)
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Dashboard > "Create Service" | Wizard opens with step indicators | [ ] |
| 2 | Step 1 — Basic Info | Title (required), category dropdown, description (rich editor), tags | [ ] |
| 3 | Step 2 — Media | Featured image (required), gallery (up to 4 images), video URL (1 max), reorder/delete | [ ] |
| 4 | Step 3 — Packages | 3 tabs (Basic/Standard/Premium), each with: name, description, price, delivery days, revisions, features list. Enable/disable per package | [ ] |
| 5 | Step 4 — Requirements | Add questions (text, textarea, select, file), mark required/optional, add choices for select fields, reorder, delete (up to 5) | [ ] |
| 6 | Step 5 — FAQs | Add FAQ items with question + answer, reorder, delete (up to 5) | [ ] |
| 7 | Step 6 — Addons | Add extras with name, description, price, delivery time, delete (up to 3) | [ ] |
| 8 | Publish / Submit for Review | If moderation off: published immediately. If on: pending review | [ ] |
| 9 | Save as Draft | Can save and continue later | [ ] |
| 10 | Limit messages | When limits reached (4 gallery, 3 addons, 5 FAQs, 5 requirements, 1 video), shows "upgrade to Pro" message | [ ] |

### Flow 8: Service Moderation (if enabled)
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Vendor submits service | Status = pending review | [ ] |
| 2 | Admin sees pending services | Listed in admin panel | [ ] |
| 3 | Admin approves | Service published, vendor notified | [ ] |
| 4 | Admin rejects | Service stays draft, rejection reason sent | [ ] |

### Flow 9: Service Management
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Dashboard > Services tab | All vendor's services listed with status indicators | [ ] |
| 2 | Edit service | Opens wizard with existing data, all fields editable | [ ] |
| 3 | Pause service | Service hidden from frontend, existing orders unaffected | [ ] |
| 4 | Unpause service | Service visible again | [ ] |
| 5 | Delete service | Confirmation prompt, service trashed, active orders remain accessible | [ ] |
| 6 | Service stats | Orders count, earnings, rating shown per service | [ ] |

---

## Phase 4: Buyer Experience

### Flow 10: Service Discovery
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Visit Services page | Published services displayed in grid with images, prices, ratings | [ ] |
| 2 | Filter by category | Only matching services shown | [ ] |
| 3 | Sort by price/rating/date | Correct ordering | [ ] |
| 4 | Search by keyword | Relevant results appear | [ ] |
| 5 | Pagination | Pages work correctly | [ ] |
| 6 | Empty state | "No services found" message when no results | [ ] |
| 7 | Gutenberg blocks | Service Grid, Featured Services, Categories, Service Search, Seller Card, Buyer Requests blocks render | [ ] |

### Flow 11: Service Detail Page
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Click a service | Single service page loads | [ ] |
| 2 | Service info | Title, description, gallery, video visible | [ ] |
| 3 | Package selector | Switch between Basic/Standard/Premium, price and features update | [ ] |
| 4 | Addon selection | Addons listed with prices, total updates when selected | [ ] |
| 5 | Vendor info sidebar | Name, avatar, rating, response time, level badge | [ ] |
| 6 | Reviews section | Real reviews with star ratings, pagination, "helpful" votes | [ ] |
| 7 | FAQs section | Accordion-style FAQ items | [ ] |
| 8 | Contact Seller button | Opens message modal or contact flow | [ ] |
| 9 | "Order Now" button | Proceeds to checkout with selected package + addons | [ ] |
| 10 | SEO | Meta description, Open Graph tags, schema markup on service pages | [ ] |

### Flow 12: Cart & Standalone Checkout
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Add to cart | Service + package + addons added, cart count updates | [ ] |
| 2 | View cart | Order summary with service, package, addons, total | [ ] |
| 3 | Remove from cart | Item removed, total updates | [ ] |
| 4 | Proceed to checkout | Checkout page loads with order summary | [ ] |
| 5 | Select payment gateway | Available gateways shown (Stripe, PayPal, Offline) | [ ] |
| 6 | Stripe payment | Card form appears, test card (4242...) works, 3D Secure if needed | [ ] |
| 7 | PayPal payment | Redirects to PayPal, completes payment, returns to site | [ ] |
| 8 | Offline payment | Instructions shown, order created as `pending_payment` | [ ] |
| 9 | Order confirmation | Success page with order number, next steps | [ ] |
| 10 | Confirmation email | Both buyer and vendor receive email | [ ] |

---

## Phase 5: Order Lifecycle

### Flow 13: Requirements Collection
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Service WITH requirements | After payment, order status = `pending_requirements` | [ ] |
| 2 | Requirements form visible | All field types render (text, textarea, select, file) | [ ] |
| 3 | Required fields enforced | Cannot submit without filling required fields | [ ] |
| 4 | Submit requirements | Form submits, status changes to `in_progress` | [ ] |
| 5 | Requirements shown read-only | After submission, buyer sees submitted answers | [ ] |
| 6 | Vendor sees requirements | Same data visible on vendor's order view | [ ] |
| 7 | Service WITHOUT requirements | After payment, order auto-advances to `in_progress` (skips pending_requirements) | [ ] |
| 8 | Admin can enter requirements | Admin metabox allows filling requirements on behalf of buyer | [ ] |

### Flow 14: Order In Progress
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Order status = `in_progress` | Both parties see active order | [ ] |
| 2 | Send message (buyer) | Message delivered, visible to both parties | [ ] |
| 3 | Send message (vendor) | Message delivered, visible to both parties | [ ] |
| 4 | Attach file in conversation | File uploads, downloadable by recipient | [ ] |
| 5 | Message polling | New messages appear without page refresh | [ ] |
| 6 | Deadline tracking | Due date and countdown visible | [ ] |
| 7 | Deadline approaching | Warning shown when near due date | [ ] |

### Flow 15: Delivery & Approval
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Vendor clicks "Deliver" | Delivery form opens with message + file upload | [ ] |
| 2 | Submit delivery | Delivery recorded, status = `pending_approval` | [ ] |
| 3 | Buyer notified | Email + in-app notification sent | [ ] |
| 4 | Buyer views delivery | Delivery message + files visible, files downloadable | [ ] |
| 5 | Buyer accepts delivery | Status = `completed`, vendor notified, earnings credited | [ ] |
| 6 | Buyer requests revision | Revision form with explanation, status = `revision_requested`, vendor notified | [ ] |
| 7 | Revision count tracked | "X revisions remaining" shown | [ ] |
| 8 | Vendor redelivers | New delivery submitted, status back to `pending_approval` | [ ] |

### Flow 16: Order Completion
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Buyer accepts final delivery | Order status = `completed` | [ ] |
| 2 | Auto-complete timer | If buyer doesn't respond within configured days, order auto-completes | [ ] |
| 3 | Review prompt | Buyer prompted to leave review after completion | [ ] |
| 4 | Vendor earnings credited | Earnings added to vendor balance (subject to clearance period) | [ ] |
| 5 | Commission deducted | Platform fee taken from order total | [ ] |
| 6 | Completion emails | Both parties receive "order completed" email | [ ] |

---

## Phase 6: Post-Order Features

### Flow 17: Reviews & Ratings
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Leave review (buyer) | 5-star rating + text review, submit works | [ ] |
| 2 | Review visible on service | Shows on service detail page | [ ] |
| 3 | Vendor sees review | Visible in vendor dashboard | [ ] |
| 4 | "Helpful" votes | Other users can mark reviews as helpful | [ ] |
| 5 | Review restrictions | Can't review if: not buyer, not completed, already reviewed | [ ] |
| 6 | Vendor rating updates | Average rating recalculated | [ ] |

### Flow 18: Disputes
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Open dispute (buyer) | Dispute form with reason dropdown + description | [ ] |
| 2 | Order status changes | Status = `disputed` | [ ] |
| 3 | Admin + vendor notified | Email + notification to both | [ ] |
| 4 | Admin views dispute | Full details visible in admin panel | [ ] |
| 5 | Admin resolves for buyer | Buyer refunded, both notified | [ ] |
| 6 | Admin resolves for vendor | Vendor paid, both notified | [ ] |
| 7 | Dispute window | Can only open dispute within configured days after completion | [ ] |

### Flow 19: Earnings & Withdrawals
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Earnings dashboard | Available balance, pending balance, total earnings, this month | [ ] |
| 2 | Earnings history | List of completed orders with amounts, commission shown | [ ] |
| 3 | Clearance period | Earnings locked for configured days after order completion | [ ] |
| 4 | Request withdrawal | Form with amount input + payment method selection | [ ] |
| 5 | Below minimum | Error if amount below minimum withdrawal threshold | [ ] |
| 6 | Insufficient balance | Error if amount exceeds available balance | [ ] |
| 7 | No payment method | Prompt to configure payment method first | [ ] |
| 8 | Withdrawal submitted | Balance reduced, request appears as "Pending" | [ ] |
| 9 | Admin approves withdrawal | Status updated, vendor notified | [ ] |
| 10 | Admin rejects withdrawal | Balance restored, rejection reason sent | [ ] |
| 11 | Auto-withdrawal (if enabled) | Processes automatically per schedule when threshold met | [ ] |

---

## Phase 7: Additional Features

### Flow 20: Buyer Requests (Job Board)
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Buyer posts request | Title, description, budget range, category, deadline, attachments | [ ] |
| 2 | Request visible on listing | Shows in buyer requests archive | [ ] |
| 3 | Vendor submits proposal | Price, delivery time, description | [ ] |
| 4 | Buyer views proposals | All proposals listed with vendor info | [ ] |
| 5 | Buyer accepts proposal | Order created from proposal | [ ] |
| 6 | Buyer rejects proposal | Vendor notified | [ ] |
| 7 | Vendor withdraws proposal | Proposal removed | [ ] |

### Flow 21: Portfolio
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Dashboard > Portfolio | Portfolio section visible | [ ] |
| 2 | Add portfolio item | Title, description, images, project URL | [ ] |
| 3 | Reorder items | Drag to reorder works | [ ] |
| 4 | Edit/delete items | Edit form, delete with confirmation | [ ] |
| 5 | Public display | Portfolio visible on vendor profile page | [ ] |

### Flow 22: Favorites / Wishlist
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Heart icon on service card | Clickable favorite icon | [ ] |
| 2 | Toggle favorite (logged in) | Adds/removes from favorites, icon state changes | [ ] |
| 3 | Logged out click | Redirect to login | [ ] |
| 4 | View favorites list | All favorited services shown in dashboard | [ ] |
| 5 | Favorited service deleted | Removed from list gracefully | [ ] |

### Flow 23: Seller Levels
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | New vendor starts at "New Seller" | Default level assigned | [ ] |
| 2 | Level progress visible | Dashboard shows progress toward next level | [ ] |
| 3 | Auto-promotion | When thresholds met (orders, rating, reviews, response rate, days active), level upgrades | [ ] |
| 4 | Promotion notification | Email sent on level up | [ ] |
| 5 | Level badge displayed | Visible on service page + vendor profile | [ ] |
| 6 | 4 levels | New Seller → Level 1 → Level 2 → Top Rated | [ ] |

### Flow 24: Tipping
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | "Send Tip" on completed order | Tip button visible | [ ] |
| 2 | Enter tip amount | Amount field accepts input | [ ] |
| 3 | Confirm tip | Payment processed | [ ] |
| 4 | Tip recorded | Shows in order timeline | [ ] |
| 5 | Vendor receives tip | Added to earnings (tips are commission-free) | [ ] |
| 6 | Not on cancelled orders | Tip button hidden | [ ] |

### Flow 25: Milestones
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Vendor creates milestones | Title, description, amount for each phase | [ ] |
| 2 | Vendor submits milestone | Milestone marked as submitted | [ ] |
| 3 | Buyer reviews milestone | Details visible | [ ] |
| 4 | Buyer approves milestone | Payment released for that milestone | [ ] |
| 5 | Buyer requests revision | Milestone sent back to vendor | [ ] |
| 6 | All milestones complete | Order marked complete | [ ] |

### Flow 26: Extension Requests
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Vendor requests extension | Form with reason + new deadline | [ ] |
| 2 | Buyer notified | Notification + email sent | [ ] |
| 3 | Buyer approves | Deadline updated | [ ] |
| 4 | Buyer rejects | Original deadline remains | [ ] |

### Flow 27: Vacation Mode
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Enable vacation mode | Toggle in vendor settings | [ ] |
| 2 | Services hidden | Not discoverable on frontend | [ ] |
| 3 | Existing orders continue | Active orders unaffected | [ ] |
| 4 | Disable vacation mode | Services visible again | [ ] |

### Flow 28: Email Notifications (8 Types)
| # | Email Type | Recipient | Status |
|---|-----------|-----------|--------|
| 1 | New Order | Vendor | [ ] |
| 2 | Order Completed | Both | [ ] |
| 3 | Order Cancelled | Both | [ ] |
| 4 | Delivery Submitted | Buyer | [ ] |
| 5 | Revision Requested | Vendor | [ ] |
| 6 | New Message | Recipient | [ ] |
| 7 | New Review | Vendor | [ ] |
| 8 | Dispute Opened | Both + Admin | [ ] |

### Flow 29: In-App Notifications
| # | Step | Expected Result | Status |
|---|------|----------------|--------|
| 1 | Bell icon in dashboard | Shows unread count badge | [ ] |
| 2 | Click bell | Notification dropdown opens | [ ] |
| 3 | Click notification | Navigates to relevant page (order, message) | [ ] |
| 4 | Mark as read | Count decreases | [ ] |
| 5 | Mark all read | All cleared | [ ] |
| 6 | Empty state | "No notifications" message | [ ] |

### Flow 30: REST API (20 Controllers)
| # | Endpoint Group | Key Operations | Status |
|---|---------------|---------------|--------|
| 1 | Auth | Login, register, logout | [ ] |
| 2 | Services | CRUD, search, featured | [ ] |
| 3 | Orders | CRUD, status transitions | [ ] |
| 4 | Reviews | CRUD, vendor reviews | [ ] |
| 5 | Vendors | List, profile, stats | [ ] |
| 6 | Conversations | Messages, attachments | [ ] |
| 7 | Disputes | Create, respond, resolve | [ ] |
| 8 | Buyer Requests | CRUD, proposals | [ ] |
| 9 | Proposals | CRUD, accept/reject | [ ] |
| 10 | Notifications | List, read, delete | [ ] |
| 11 | Portfolio | CRUD, reorder | [ ] |
| 12 | Earnings | Summary, history, withdrawals | [ ] |
| 13 | Extensions | Create, approve, reject | [ ] |
| 14 | Milestones | CRUD, submit, approve | [ ] |
| 15 | Tipping | Send, list | [ ] |
| 16 | Seller Levels | Definitions, progress | [ ] |
| 17 | Moderation | Approve, reject, history | [ ] |
| 18 | Favorites | Add, remove, list | [ ] |
| 19 | Media | Upload, info, delete | [ ] |
| 20 | Cart | Add, get, remove, checkout | [ ] |
| 21 | Batch | POST /wpss/v1/batch (up to 25 sub-requests) | [ ] |
| 22 | Generic | Categories, tags, settings, me, dashboard, search | [ ] |

---

## Quick Reference: Order Status Flow

```
pending_payment → pending_requirements → in_progress → pending_approval → completed
                                              ↓                ↓
                                         extension_requested   revision_requested
                                              ↓                ↓
                                         (approved/rejected)   (vendor redelivers)

Branches at any active status:
  → cancelled (buyer/vendor/admin cancels)
  → disputed (buyer opens dispute after delivery)
  → late (deadline passed)
  → on_hold (admin action)
```

---

## Free Plugin Limits (vs Pro)

| Feature | Free Limit | Pro |
|---------|-----------|-----|
| Gallery images per service | 4 | Unlimited |
| Videos per service | 1 | 3 |
| Add-ons per service | 3 | Unlimited |
| FAQs per service | 5 | Unlimited |
| Requirements per service | 5 | Unlimited |
| Packages per service | 3 | 3 (same) |
| Active services per vendor | Configurable (default 20) | Same |
| Payment gateways | Stripe, PayPal, Offline | +Razorpay |
| E-commerce platform | Standalone only | +WooCommerce, EDD, FluentCart, SureCart |

---

## Changelog

| Date | Changes |
|------|---------|
| 2026-02-20 | Initial creation — 30 flows across 7 phases |
