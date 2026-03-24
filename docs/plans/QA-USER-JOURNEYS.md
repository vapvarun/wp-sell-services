# WP Sell Services - User Journey QA Checklist

**Created:** 2026-02-02
**Purpose:** End-to-end workflow testing from buyer/seller perspective
**Complements:** QA-AUDIT-ISSUES.md (code-level), audit-logic-flows.md (technical)

---

## Why This Document Exists

Previous QA audits focused on:
- Code-level bugs (missing methods, wrong parameters)
- Security vulnerabilities (XSS, SQL injection)
- Technical implementation (table schemas, API endpoints)

This document focuses on **user expectations** - what buyers and sellers expect to happen at each step, and verifying the plugin delivers that experience.

---

## 1. Buyer Journey: Discovering & Purchasing a Service

### 1.1 Service Discovery
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Browse services archive | See published services with images, prices, ratings | [ ] |
| Filter by category | Only matching services shown | [ ] |
| Sort by price/rating/date | Correct ordering | [ ] |
| Search by keyword | Relevant results appear | [ ] |
| Click service | Goes to single service page | [ ] |

### 1.2 Service Page
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View service details | Title, description, gallery, FAQs visible | [ ] |
| See package options | All packages with features and prices | [ ] |
| Switch packages | Price and features update | [ ] |
| See vendor info | Name, avatar, rating, response time | [ ] |
| Read reviews | Real reviews with ratings displayed | [ ] |
| Contact seller button | Opens message modal or goes to contact | [ ] |

### 1.3 Purchase Flow
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Select package | Package highlighted | [ ] |
| Add addons (if any) | Price updates with addon costs | [ ] |
| Click "Order Now" | Goes to checkout/cart | [ ] |
| See order summary | Correct service, package, price | [ ] |
| Complete payment | Payment processed | [ ] |
| See confirmation | Order created, next steps shown | [ ] |
| Receive email | Order confirmation email received | [ ] |

---

## 2. Buyer Journey: Order Requirements

### 2.1 Service WITH Requirements
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| After payment | Order status = `pending_requirements` | [ ] |
| View order page | Requirements form visible | [ ] |
| Form shows all fields | Text, textarea, select, file as defined by vendor | [ ] |
| Required fields marked | Asterisk or "required" label | [ ] |
| Submit without required | Error shown, form not submitted | [ ] |
| Submit with all fields | Form submits successfully | [ ] |
| After submission | Status changes to `in_progress` | [ ] |
| View order after submit | Requirements shown as read-only | [ ] |
| Vendor sees requirements | Same data visible to vendor | [ ] |

### 2.2 Service WITHOUT Requirements
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| After payment | Order status = `in_progress` (skips pending_requirements) | [ ] |
| View order page | "No requirements for this service" message | [ ] |
| No form shown | Requirements form not displayed | [ ] |
| Work starts immediately | Vendor notified to start work | [ ] |

### 2.3 Late Requirements Submission (if enabled)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Order in `in_progress` without requirements | Form shown if setting enabled | [ ] |
| Submit late requirements | Success message, vendor notified | [ ] |
| Order stays `in_progress` | Status doesn't change | [ ] |

### 2.4 Admin Requirements Entry
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Admin views order without requirements | Form shown in metabox | [ ] |
| Admin fills form | All field types work | [ ] |
| Admin submits | Requirements saved | [ ] |
| Order status updates | Transitions if needed | [ ] |
| Buyer sees requirements | Shows as submitted | [ ] |

---

## 3. Vendor Journey: Registration & Onboarding

### 3.1 Becoming a Vendor
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Find registration page | Link visible in menu/dashboard | [ ] |
| View registration form | Form with required fields shown | [ ] |
| Fill profile info | Name, bio, skills fields | [ ] |
| Upload profile photo | Image upload works | [ ] |
| Add payment details | Payment method fields | [ ] |
| Accept terms | Terms checkbox required | [ ] |
| Submit application | Form submits successfully | [ ] |
| See confirmation | "Application submitted" message | [ ] |
| Receive email | Confirmation email received | [ ] |

### 3.2 Vendor Approval (if moderation enabled)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Application pending | Vendor sees "pending approval" status | [ ] |
| Admin approves | Vendor role assigned | [ ] |
| Vendor notified | Approval email received | [ ] |
| Dashboard access | Vendor dashboard now accessible | [ ] |
| Can create services | Service creation enabled | [ ] |

### 3.3 Vendor Rejection
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Admin rejects | Application marked rejected | [ ] |
| Vendor notified | Rejection email with reason | [ ] |
| Can reapply | Option to submit new application | [ ] |

---

## 4. Vendor Journey: Service Creation

### 4.1 Starting Service Creation
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click "Create Service" | Wizard/form opens | [ ] |
| See wizard steps | Step indicators visible | [ ] |
| Progress saved | Can continue later (draft) | [ ] |

### 4.2 Step 1: Basic Info
| Field | Expected Behavior | Test Status |
|-------|------------------|-------------|
| Service title | Text input, required | [ ] |
| Category selection | Dropdown/select with categories | [ ] |
| Service description | Rich text editor | [ ] |
| Tags/keywords | Tag input field | [ ] |
| Validation | Required fields enforced | [ ] |
| Save & Continue | Moves to next step | [ ] |

### 4.3 Step 2: Gallery/Media
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Featured image | Single image upload, required | [ ] |
| Gallery images | Multiple image upload | [ ] |
| Image preview | Thumbnails shown after upload | [ ] |
| Reorder images | Drag to reorder | [ ] |
| Delete image | Remove uploaded image | [ ] |
| Video URL (optional) | YouTube/Vimeo URL input | [ ] |

### 4.4 Step 3: Pricing & Packages
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Package tabs | Basic/Standard/Premium toggles | [ ] |
| Package name | Editable package title | [ ] |
| Package description | What's included text | [ ] |
| Price input | Number field with currency | [ ] |
| Delivery time | Days dropdown/input | [ ] |
| Revisions included | Number input | [ ] |
| Features list | Add/remove feature items | [ ] |
| Enable/disable packages | Toggle individual packages | [ ] |

### 4.5 Step 4: Requirements (Critical)
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Add requirement | "Add Question" button works | [ ] |
| Question text | Text input for the question | [ ] |
| Question type | Dropdown: text, textarea, select, file, etc. | [ ] |
| Required toggle | Mark as required/optional | [ ] |
| Choices (for select/radio) | Add options for choice fields | [ ] |
| Reorder questions | Drag to reorder | [ ] |
| Delete question | Remove requirement | [ ] |
| Preview | See how buyer will see form | [ ] |
| No requirements | Can skip/leave empty | [ ] |

### 4.6 Step 5: FAQs
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Add FAQ | "Add FAQ" button works | [ ] |
| Question field | FAQ question text | [ ] |
| Answer field | FAQ answer text | [ ] |
| Reorder FAQs | Drag to reorder | [ ] |
| Delete FAQ | Remove FAQ item | [ ] |

### 4.7 Step 6: Addons (Optional)
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Add addon | "Add Addon" button works | [ ] |
| Addon name | Text input | [ ] |
| Addon description | What addon provides | [ ] |
| Addon price | Price input | [ ] |
| Addon delivery time | Additional days | [ ] |
| Delete addon | Remove addon | [ ] |

### 4.8 Publishing Service
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Review all steps | Summary of service shown | [ ] |
| Submit for review | If moderation enabled | [ ] |
| Publish directly | If no moderation | [ ] |
| See success message | "Service created" confirmation | [ ] |
| Service in dashboard | Appears in services list | [ ] |
| Service live (if approved) | Visible on frontend | [ ] |

---

## 5. Vendor Journey: Service Management

### 5.1 Viewing My Services
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Services list | All vendor's services shown | [ ] |
| Status indicators | Published, Draft, Pending, Paused | [ ] |
| Stats per service | Orders, earnings, rating | [ ] |
| Quick actions | Edit, Pause, Delete buttons | [ ] |

### 5.2 Editing a Service
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Click Edit | Edit form/wizard opens | [ ] |
| All fields editable | Can change any field | [ ] |
| Change requirements | Add/edit/remove requirements | [ ] |
| Change packages | Modify pricing/features | [ ] |
| Save changes | Updates saved | [ ] |
| Existing orders unaffected | Old orders keep old requirements | [ ] |

### 5.3 Pausing a Service
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click Pause | Confirmation prompt | [ ] |
| Confirm pause | Service status = paused | [ ] |
| Service hidden | Not visible on frontend | [ ] |
| Existing orders continue | Active orders unaffected | [ ] |
| Unpause service | Service visible again | [ ] |

### 5.4 Deleting a Service
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click Delete | Confirmation prompt | [ ] |
| Warning about orders | Shows if active orders exist | [ ] |
| Confirm delete | Service removed/trashed | [ ] |
| Active orders | Remain accessible | [ ] |

---

## 6. Vendor Journey: Receiving & Fulfilling Orders

### 6.1 New Order Notification
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Buyer places order | Vendor receives email notification | [ ] |
| Vendor dashboard shows new order | Order visible in orders list | [ ] |
| Notification bell shows count | Unread notification count updated | [ ] |

### 6.2 Viewing Order Details
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click order | Order detail page loads | [ ] |
| See buyer info | Name, avatar visible | [ ] |
| See service ordered | Service name, package, addons | [ ] |
| See requirements | Buyer's submitted requirements visible | [ ] |
| See order timeline | Status history shown | [ ] |
| See deadline | Due date and countdown visible | [ ] |

### 6.3 Working on Order
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Send message to buyer | Message delivered and visible to both | [ ] |
| Receive message from buyer | Notification and message visible | [ ] |
| Work on order | Status remains `in_progress` | [ ] |
| Deadline approaching | Warning shown if near due date | [ ] |

### 6.4 Delivering Order
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click "Deliver" button | Delivery form/modal opens | [ ] |
| Add delivery message | Text field for description | [ ] |
| Attach files | File upload works | [ ] |
| Submit delivery | Delivery recorded | [ ] |
| Status changes | Status = `delivered` or `pending_approval` | [ ] |
| Buyer notified | Email sent to buyer | [ ] |

---

## 7. Buyer Journey: Receiving Delivery

### 7.1 Delivery Notification
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Vendor delivers | Buyer receives email notification | [ ] |
| Dashboard shows update | Order status updated | [ ] |

### 7.2 Reviewing Delivery
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View order | Delivery details visible | [ ] |
| Download files | All attached files downloadable | [ ] |
| Read vendor message | Delivery description shown | [ ] |

### 7.3 Accepting Delivery
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click "Accept" | Confirmation prompt | [ ] |
| Confirm acceptance | Order status = `completed` | [ ] |
| Review prompt shown | Option to leave review | [ ] |
| Vendor notified | Email sent to vendor | [ ] |
| Vendor gets paid | Earnings added to vendor balance | [ ] |

### 7.4 Requesting Revision
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click "Request Revision" | Revision form opens | [ ] |
| Enter revision details | Text field for explanation | [ ] |
| Submit revision request | Status = `revision_requested` | [ ] |
| Vendor notified | Email and notification sent | [ ] |
| Revision count tracked | Revisions remaining shown | [ ] |

---

## 8. Messaging Flow

### 8.1 Buyer Messaging
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Open order conversation | Message history loaded | [ ] |
| Send text message | Message appears immediately | [ ] |
| Attach file | File uploads and shows | [ ] |
| Vendor receives | Real-time or polling update | [ ] |

### 8.2 Vendor Messaging
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Open order conversation | Message history loaded | [ ] |
| Send text message | Message appears immediately | [ ] |
| Attach file | File uploads and shows | [ ] |
| Buyer receives | Real-time or polling update | [ ] |

### 8.3 Message Availability by Status
| Order Status | Can Buyer Message? | Can Vendor Message? | Test Status |
|--------------|-------------------|--------------------| ------------|
| `pending_payment` | No | No | [ ] |
| `pending_requirements` | Yes | Yes | [ ] |
| `in_progress` | Yes | Yes | [ ] |
| `delivered` | Yes | Yes | [ ] |
| `revision_requested` | Yes | Yes | [ ] |
| `completed` | No | No | [ ] |
| `cancelled` | No | No | [ ] |
| `disputed` | Yes (via dispute) | Yes (via dispute) | [ ] |

---

## 9. Review Flow

### 9.1 Leaving a Review
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Order completed | Review CTA shown to buyer | [ ] |
| Click "Write Review" | Review form opens | [ ] |
| Select rating (1-5 stars) | Stars highlight on selection | [ ] |
| Write review text | Text area accepts input | [ ] |
| Submit review | Review saved | [ ] |
| Review shows on service | Visible on service page | [ ] |
| Vendor sees review | Visible in vendor dashboard | [ ] |

### 9.2 Review Restrictions
| Condition | Expected Behavior | Test Status |
|-----------|------------------|-------------|
| Order not completed | Review button not shown | [ ] |
| Already reviewed | "Already reviewed" message | [ ] |
| Not the buyer | Review button not shown | [ ] |

---

## 10. Dispute Flow

### 10.1 Opening a Dispute
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Eligible order status | Dispute button visible | [ ] |
| Click "Open Dispute" | Dispute form opens | [ ] |
| Select reason | Dropdown with reasons | [ ] |
| Describe issue | Text area for details | [ ] |
| Submit dispute | Dispute created | [ ] |
| Order status changes | Status = `disputed` | [ ] |
| Admin notified | Admin sees new dispute | [ ] |

### 10.2 Dispute Resolution
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Admin views dispute | Full details visible | [ ] |
| Admin resolves for buyer | Buyer gets refund | [ ] |
| Admin resolves for vendor | Vendor gets payment | [ ] |
| Both parties notified | Email notifications sent | [ ] |

---

## 11. Cancellation Flow

### 11.1 Buyer Cancellation
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Eligible status | Cancel button visible | [ ] |
| Click "Cancel Order" | Confirmation + reason prompt | [ ] |
| Enter reason | Text input for reason | [ ] |
| Confirm cancellation | Order status = `cancelled` | [ ] |
| Refund processed | Buyer refunded (if applicable) | [ ] |
| Vendor notified | Email sent to vendor | [ ] |

### 11.2 Vendor Cancellation
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Eligible status | Cancel button visible | [ ] |
| Click "Cancel Order" | Confirmation + reason prompt | [ ] |
| Enter reason | Text input for reason | [ ] |
| Confirm cancellation | Order status = `cancelled` | [ ] |
| Buyer refunded | Automatic refund processed | [ ] |
| Buyer notified | Email sent to buyer | [ ] |

---

## 12. Vendor Dashboard

### 12.1 Dashboard Overview
| Element | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Stats cards | Earnings, orders, rating shown | [ ] |
| Active orders list | Current orders visible | [ ] |
| Recent messages | Latest messages shown | [ ] |
| Earnings chart | Graphical earnings display | [ ] |

### 12.2 Orders Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List all orders | Orders with status, buyer, amount | [ ] |
| Filter by status | Filtering works | [ ] |
| Search orders | Search by ID, buyer name | [ ] |
| Pagination | Pages work correctly | [ ] |
| Click order | Goes to order detail | [ ] |

### 12.3 Services Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List my services | All vendor's services shown | [ ] |
| See status | Published, draft, pending shown | [ ] |
| Edit service | Edit link works | [ ] |
| Create new | Create button works | [ ] |
| Delete service | Delete with confirmation | [ ] |

### 12.4 Earnings Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Available balance | Current withdrawable amount | [ ] |
| Pending balance | Amount awaiting clearance | [ ] |
| Earnings history | List of completed orders | [ ] |
| Request withdrawal | Withdrawal form works | [ ] |

### 12.5 Settings Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Profile info | Name, bio, avatar editable | [ ] |
| Payment methods | Payment details configurable | [ ] |
| Notification preferences | Email toggles work | [ ] |
| Save settings | Changes persist | [ ] |

---

## 13. Vendor Journey: Earnings & Withdrawals

### 13.1 Earnings Overview
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Total earnings | Lifetime earnings shown | [ ] |
| Available balance | Withdrawable amount shown | [ ] |
| Pending balance | Amount awaiting clearance | [ ] |
| This month earnings | Current month total | [ ] |
| Earnings chart | Visual graph of earnings | [ ] |

### 13.2 Earnings History
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List of completed orders | Shows order ID, amount, date | [ ] |
| Filter by date range | Date filter works | [ ] |
| See commission deducted | Platform fee shown | [ ] |
| Net amount received | After commission amount | [ ] |
| Export earnings | CSV/PDF export option | [ ] |

### 13.3 Setting Up Payment Methods
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Go to settings | Payment methods section visible | [ ] |
| Add PayPal email | Email field saves correctly | [ ] |
| Add bank details | Bank account fields save | [ ] |
| Set default method | Default payment method marked | [ ] |
| Validate input | Invalid data rejected | [ ] |
| Save payment info | Data persists | [ ] |

### 13.4 Requesting a Withdrawal
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Have available balance | Balance > 0 shown | [ ] |
| Click "Request Withdrawal" | Form opens | [ ] |
| Enter withdrawal amount | Amount field accepts input | [ ] |
| Amount exceeds balance | Error: "Insufficient balance" | [ ] |
| Amount below minimum | Error: "Below minimum" | [ ] |
| Valid amount entered | No error | [ ] |
| Select payment method | Dropdown with configured methods | [ ] |
| No payment method configured | Prompt to add one first | [ ] |
| Submit request | Success message shown | [ ] |
| Balance updated | Available balance reduced | [ ] |
| Request appears in list | Shows as "Pending" | [ ] |
| Email confirmation | Withdrawal request email sent | [ ] |

### 13.5 Tracking Withdrawal Status
| Status | What Vendor Sees | Test Status |
|--------|-----------------|-------------|
| Pending | "Awaiting admin approval" | [ ] |
| Approved | "Approved - Processing payment" | [ ] |
| Completed | "Paid on [date]" | [ ] |
| Rejected | "Rejected: [reason]" | [ ] |
| Rejected balance | Balance restored to available | [ ] |

### 13.6 Admin Withdrawal Management
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| View pending withdrawals | List in admin panel | [ ] |
| See vendor details | Name, email, payment method | [ ] |
| See amount requested | Withdrawal amount shown | [ ] |
| Approve withdrawal | Status changes to approved | [ ] |
| Mark as paid | Status changes to completed | [ ] |
| Reject withdrawal | Rejection form opens | [ ] |
| Enter rejection reason | Required field | [ ] |
| Confirm rejection | Status = rejected, balance restored | [ ] |
| Vendor notification | Email sent on status change | [ ] |

---

## 14. Buyer Dashboard

### 14.1 Dashboard Overview
| Element | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Active orders | Current orders visible | [ ] |
| Order stats | Total orders, completed count | [ ] |
| Recent messages | Latest conversations shown | [ ] |

### 14.2 Orders Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List all orders | Orders with status, vendor, amount | [ ] |
| Filter by status | Filtering works | [ ] |
| Click order | Goes to order detail | [ ] |

### 14.3 Messages Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List conversations | All order conversations | [ ] |
| Unread indicator | Unread count shown | [ ] |
| Click conversation | Opens message view | [ ] |

---

## 15. Admin Workflows

### 15.1 Manual Order Creation
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Go to manual order page | Form loads | [ ] |
| Select service | Service dropdown works | [ ] |
| Select buyer | User dropdown works | [ ] |
| Select package | Package options load | [ ] |
| Set status | Status dropdown works | [ ] |
| Create order | Order created successfully | [ ] |
| Service without requirements | Status auto-upgrades to `in_progress` | [ ] |
| Service with requirements | Status stays `pending_requirements` | [ ] |

### 15.2 Order Management
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| View order | Full order details visible | [ ] |
| Change status | Status update works | [ ] |
| Add admin note | Note saved and visible | [ ] |
| View messages | Conversation visible | [ ] |
| View requirements | Submitted requirements visible | [ ] |
| Enter requirements | Admin can fill requirements form | [ ] |

### 15.3 Service Moderation
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| View pending services | List shows pending | [ ] |
| Approve service | Status changes to published | [ ] |
| Reject service | Status changes, reason saved | [ ] |
| Vendor notified | Email sent on approval/rejection | [ ] |

---

## 16. Email Notifications

### 16.1 Order Emails
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| Order placed | Buyer + Vendor | [ ] |
| Requirements submitted | Vendor | [ ] |
| Order started | Buyer | [ ] |
| Delivery submitted | Buyer | [ ] |
| Revision requested | Vendor | [ ] |
| Order completed | Buyer + Vendor | [ ] |
| Order cancelled | Buyer + Vendor | [ ] |

### 16.2 Message Emails
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| New message | Other party | [ ] |

### 16.3 Review Emails
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| New review | Vendor | [ ] |

### 16.4 Dispute Emails
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| Dispute opened | Admin + other party | [ ] |
| Dispute resolved | Buyer + Vendor | [ ] |

---

## Testing Priority

### Critical Path (Must Work)
1. **Buyer**: Service discovery and viewing (Section 1)
2. **Buyer**: Purchase and checkout (Section 1.3)
3. **Buyer**: Requirements submission (Section 2)
4. **Vendor**: Service creation with requirements (Section 4)
5. **Vendor**: Receiving and fulfilling orders (Section 6)
6. **Both**: Messaging during order (Section 8)
7. **Both**: Delivery and acceptance (Section 7)
8. **Buyer**: Review submission (Section 9)

### High Priority
1. **Vendor**: Registration and onboarding (Section 3)
2. **Vendor**: Service management (Section 5)
3. **Buyer**: Revision flow (Section 7.4)
4. **Both**: Cancellation flow (Section 11)
5. **Vendor**: Dashboard overview (Section 12)
6. **Buyer**: Dashboard (Section 14)
7. **Admin**: Order management (Section 15)

### Medium Priority
1. **Both**: Dispute flow (Section 10)
2. **Vendor**: Earnings and withdrawal (Section 13)
3. **Admin**: Service moderation (Section 15.3)
4. **All**: Email notifications (Section 16)
5. **Admin**: Manual order creation (Section 15.1)
6. **Admin**: Settings pages - all tabs and accordions (Section 17)
7. **Buyer/Vendor**: Buyer requests and proposals (Section 18)

### Lower Priority (but required before launch)
1. **Admin**: Setup Wizard (Section 19)
2. **All**: Gutenberg Blocks rendering (Section 20)
3. **All**: Shortcodes output (Section 21)
4. **Vendor**: Seller Levels progression (Section 22)
5. **Buyer**: Favorites/Wishlist (Section 23)
6. **Vendor**: Portfolio management (Section 24)
7. **Buyer**: Tipping (Section 25)
8. **Both**: Milestones (Section 26)
9. **Both**: Extension Requests (Section 27)
10. **Dev**: REST API endpoints (Section 28)
11. **Buyer**: Cart & Standalone Checkout (Section 29)
12. **All**: Payment Gateways (Section 30)
13. **Dev**: WP-CLI Commands (Section 31)
14. **Dev**: Cron Jobs (Section 32)
15. **All**: In-App Notifications (Section 33)
16. **All**: Media Uploads (Section 34)
17. **Admin**: Activation & Database (Section 35)
18. **Dev**: Template Overrides (Section 36)
19. **All**: Search & Filtering (Section 37)

---

## 17. Admin Settings Pages

### 17.1 Settings Navigation & Tabs
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Navigate to Sell Services > Settings | Settings page loads with tab groups | [ ] |
| Tab groups visible | Setup, Business, Operations, System shown | [ ] |
| Click each tab | Tab content loads, active tab highlighted | [ ] |
| Pro tabs in separate group | Pro separator with Pro tabs (if Pro active) | [ ] |
| Unknown/custom tab | Rendered via `wpss_settings_tab_{id}` action | [ ] |

### 17.2 General Tab
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Platform name field | Text input, saves correctly | [ ] |
| Currency selection | Dropdown with currencies, saves | [ ] |
| E-commerce platform | Auto-detect or manual selection | [ ] |
| Save changes | Settings persisted on reload | [ ] |

### 17.3 Pages Tab
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Page assignment dropdowns | Dropdown for each required page | [ ] |
| "Create Page" button | Creates page via AJAX, assigns to dropdown | [ ] |
| Page already exists | Dropdown pre-selects existing page | [ ] |
| Created page links | Edit link appears after page creation | [ ] |
| Save page assignments | All page IDs persisted | [ ] |

### 17.4 Payments Tab (Accordion)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Tab has accordion sections | Commission, Tax, Payouts sections visible | [ ] |
| Click section header | Section toggles open/closed | [ ] |
| Commission section | Commission rate, type fields | [ ] |
| Tax section | Tax settings fields | [ ] |
| Payouts section | Payout settings fields | [ ] |
| Save within section | Settings form saves independently | [ ] |
| Pro sections visible (if Pro active) | Commission Rules, PayPal Payouts sections with Pro badge | [ ] |
| `wpss_settings_sections_payments` hook fires | Pro sections rendered after free sections | [ ] |

### 17.5 Gateways Tab (Accordion)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Gateway accordions shown | Stripe, PayPal, Offline listed | [ ] |
| Each gateway has enabled/disabled badge | Status badge next to title | [ ] |
| Enable a gateway | Toggle saves, badge updates | [ ] |
| Configure gateway fields | API keys, settings save | [ ] |
| Pro gateways visible (if Pro active) | Stripe Connect, Razorpay sections | [ ] |
| `wpss_gateway_accordion_sections` fires | Legacy hook for Pro gateways | [ ] |
| `wpss_settings_sections_gateways` fires | Unified hook also fires | [ ] |

### 17.6 Vendor Tab (Accordion)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Tab has accordion format | Vendor Settings section visible | [ ] |
| Click section header | Section toggles open/closed | [ ] |
| Vendor registration settings | Registration toggle, approval mode | [ ] |
| Vendor capabilities settings | Permissions and limits | [ ] |
| Save changes | Settings persisted | [ ] |
| Pro section visible (if Pro active) | Vendor Subscriptions section with Pro badge | [ ] |
| `wpss_settings_sections_vendor` hook fires | Pro section rendered after free section | [ ] |

### 17.7 Orders Tab (Accordion)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Tab has accordion format | Order Settings section visible | [ ] |
| Click section header | Section toggles open/closed | [ ] |
| Order settings fields | Deadline, auto-complete, etc. | [ ] |
| Save changes | Settings persisted | [ ] |
| Pro section visible (if Pro active) | Recurring Services section with Pro badge | [ ] |
| `wpss_settings_sections_orders` hook fires | Pro section rendered after free section | [ ] |

### 17.8 Emails Tab (Accordion)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Tab has accordion format | Email Notifications section visible | [ ] |
| Click section header | Section toggles open/closed | [ ] |
| Email template settings | Enable/disable individual emails | [ ] |
| Save changes | Settings persisted | [ ] |
| `wpss_settings_sections_emails` hook fires | Hook fires for extensibility | [ ] |

### 17.9 Advanced Tab (Accordion)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Tab has accordion sections | System Settings, Demo Content visible | [ ] |
| System settings | Debug, reset, system info | [ ] |
| Demo content import | "Import Demo" button creates sample data | [ ] |
| Demo content delete | "Delete Demo" button removes sample data | [ ] |
| Only 2 sections (no Pro clutter) | No Pro features dumped here | [ ] |
| `wpss_settings_sections_advanced` hook fires | Hook fires after core sections | [ ] |
| `wpss_advanced_settings_sections` backward-compat | Legacy hook still fires for third-party | [ ] |

### 17.10 Accordion UI Behavior
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Unified CSS class | All accordions use `.wpss-settings-section` | [ ] |
| Click header to expand | Section content slides open | [ ] |
| Click header to collapse | Section content slides closed | [ ] |
| Multiple sections open | Can have multiple sections open simultaneously | [ ] |
| Pro badge styling | `.wpss-pro-badge` shown on Pro sections | [ ] |
| Collapsed by default | `collapsed` parameter respected | [ ] |
| Form submission within section | Each section saves its own option group | [ ] |
| Page reload | Saved values persist in correct sections | [ ] |

---

## 18. Buyer Requests (Job Board)

### 18.1 Posting a Buyer Request
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Navigate to post request page | Request form loads | [ ] |
| Fill title and description | Text fields accept input | [ ] |
| Set budget range | Min/max budget fields | [ ] |
| Select category | Category dropdown works | [ ] |
| Set deadline | Date picker works | [ ] |
| Attach files (optional) | File upload works | [ ] |
| Submit request | Request created, confirmation shown | [ ] |
| Request visible on listing page | Shows in buyer requests archive | [ ] |

### 18.2 Vendor Submitting a Proposal
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View buyer request | Request details visible | [ ] |
| Click "Submit Proposal" | Proposal form opens | [ ] |
| Enter proposal details | Price, delivery time, description | [ ] |
| Submit proposal | Proposal created, buyer notified | [ ] |
| Proposal visible to buyer | Shows in buyer's proposals list | [ ] |

### 18.3 Buyer Managing Proposals
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View proposals on request | All proposals listed | [ ] |
| Compare proposals | Price, delivery, vendor info visible | [ ] |
| Accept proposal | Order created from proposal | [ ] |
| Reject proposal | Proposal marked rejected, vendor notified | [ ] |

---

## 19. Setup Wizard (Post-Activation)

### 19.1 Activation Redirect
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Activate plugin (first time) | Redirects to Setup Wizard page | [ ] |
| Activate plugin (wizard already completed) | No redirect, goes to normal admin | [ ] |
| Bulk-activate multiple plugins | No redirect (bulk activation skipped) | [ ] |
| Non-admin user activates | No redirect (capability check) | [ ] |

### 19.2 Step 1 — Platform Basics
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Marketplace Name pre-filled | Uses site name from settings | [ ] |
| Currency dropdown | Shows all currencies, saves selection | [ ] |
| Commission Rate field | Number input, saves percentage | [ ] |
| Save & Continue | Saves to `wpss_general` + `wpss_commission`, advances to step 2 | [ ] |
| Skip | Defaults remain (site name, USD, 10%), advances to step 2 | [ ] |

### 19.3 Step 2 — Payment Gateway
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Radio selection (Stripe/PayPal/Offline) | Shows relevant panel | [ ] |
| Stripe panel | Test mode toggle, API keys fields | [ ] |
| PayPal panel | Sandbox toggle, client ID/secret fields | [ ] |
| Offline panel | Title, description fields | [ ] |
| Save & Continue | Saves to gateway-specific option | [ ] |
| Skip | No gateway enabled, can configure later | [ ] |

### 19.4 Step 3 — Create Pages
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| 4 page rows shown | Services, Dashboard, Vendor Registration, Checkout | [ ] |
| "Create" button per page | Creates page via AJAX, shows success badge | [ ] |
| "Create All Pages" button | Creates all 4 pages at once | [ ] |
| Page already exists | Shows "Created" badge, no duplicate | [ ] |
| Skip | No pages created | [ ] |

### 19.5 Step 4 — Service Categories
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Preset category chips shown | 10 suggestions (Web Dev, Design, Writing, etc.) | [ ] |
| Click chip to select/deselect | Visual toggle, no AJAX yet | [ ] |
| Custom category input | "Add your own" text field works | [ ] |
| Save & Continue | Creates selected categories via AJAX | [ ] |
| Duplicate category | Skipped gracefully, no error | [ ] |
| Skip | No categories created | [ ] |

### 19.6 Step 5 — Vendor Settings
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Registration mode | Open / Requires Approval radio | [ ] |
| Max services per vendor | Number input, default 20 | [ ] |
| Require moderation checkbox | Saves correctly | [ ] |
| Require verification checkbox | Saves correctly | [ ] |
| Save & Continue | Saves to `wpss_vendor` | [ ] |
| Skip | Defaults remain (open, 20, no moderation) | [ ] |

### 19.7 Step 6 — Done
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Success message shown | Congratulations message | [ ] |
| "Create Your First Service" card | Links to service creation | [ ] |
| "Import Demo Content" card | Triggers demo import AJAX | [ ] |
| "Go to Settings" card | Links to settings page | [ ] |
| Wizard marked complete | `wpss_setup_wizard_completed` option set | [ ] |

### 19.8 Re-Run Wizard
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Settings > Advanced > Setup Wizard | Accordion section visible | [ ] |
| Click "Re-run Setup Wizard" | Opens wizard with current values pre-filled | [ ] |
| Complete re-run | Settings updated, no duplicates | [ ] |
| Completion date shown | Shows when wizard was last completed | [ ] |

---

## 20. Gutenberg Blocks

### 20.1 Block Registration
| Block | Expected Behavior | Test Status |
|-------|------------------|-------------|
| Service Grid | Registered in block inserter under "WP Sell Services" | [ ] |
| Service Search | Registered in block inserter | [ ] |
| Service Categories | Registered in block inserter | [ ] |
| Featured Services | Registered in block inserter | [ ] |
| Seller Card | Registered in block inserter | [ ] |
| Buyer Requests | Registered in block inserter | [ ] |

### 20.2 Block Editor
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Insert any block | Block renders in editor | [ ] |
| Block options panel | Settings sidebar shows block options | [ ] |
| Category filter (Service Grid) | Category dropdown populated | [ ] |
| Columns setting (Service Grid) | Number input changes layout | [ ] |
| Save page with blocks | Page saves without errors | [ ] |
| Preview page | Blocks render on frontend | [ ] |

### 20.3 Block Frontend Rendering
| Block | Expected Behavior | Test Status |
|-------|------------------|-------------|
| Service Grid | Displays services in grid layout with images, prices | [ ] |
| Service Search | Shows search form, results update | [ ] |
| Service Categories | Shows category list/grid with counts | [ ] |
| Featured Services | Shows only featured services | [ ] |
| Seller Card | Shows vendor profile card | [ ] |
| Buyer Requests | Shows buyer request listings | [ ] |

---

## 21. Shortcodes

### 21.1 Service Shortcodes
| Shortcode | Expected Behavior | Test Status |
|-----------|------------------|-------------|
| `[wpss_services]` | Displays services grid/list | [ ] |
| `[wpss_service_search]` | Shows search form with filters | [ ] |
| `[wpss_featured_services]` | Shows featured services only | [ ] |
| `[wpss_service_categories]` | Displays category listing | [ ] |

### 21.2 Vendor Shortcodes
| Shortcode | Expected Behavior | Test Status |
|-----------|------------------|-------------|
| `[wpss_vendors]` | Lists all vendors | [ ] |
| `[wpss_vendor_profile]` | Shows single vendor profile | [ ] |
| `[wpss_top_vendors]` | Shows top-rated vendors | [ ] |

### 21.3 Buyer Request Shortcodes
| Shortcode | Expected Behavior | Test Status |
|-----------|------------------|-------------|
| `[wpss_buyer_requests]` | Lists buyer requests | [ ] |
| `[wpss_post_request]` | Shows request submission form | [ ] |

### 21.4 Dashboard/Order Shortcodes
| Shortcode | Expected Behavior | Test Status |
|-----------|------------------|-------------|
| `[wpss_my_orders]` | Lists user's orders | [ ] |
| `[wpss_order_details]` | Shows single order detail | [ ] |

### 21.5 Account Shortcodes
| Shortcode | Expected Behavior | Test Status |
|-----------|------------------|-------------|
| `[wpss_login]` | Shows login form | [ ] |
| `[wpss_register]` | Shows registration form | [ ] |

### 21.6 Shortcode Attributes
| Test | Expected Behavior | Test Status |
|------|------------------|-------------|
| `[wpss_services per_page="6"]` | Respects per_page attribute | [ ] |
| `[wpss_services category="web-dev"]` | Filters by category | [ ] |
| Invalid shortcode attribute | Graceful fallback, no error | [ ] |
| Shortcode in widget area | Renders correctly | [ ] |

---

## 22. Seller Levels

### 22.1 Level Definitions (Admin)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View seller levels | Level definitions listed | [ ] |
| Default levels exist | New Seller, Level 1, Level 2, Top Rated | [ ] |
| Edit level requirements | Orders, rating, earnings thresholds | [ ] |
| Level badges/icons | Visual indicator per level | [ ] |

### 22.2 Level Progress (Vendor)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Vendor sees current level | Level badge in dashboard | [ ] |
| Progress toward next level | Progress bar/metrics shown | [ ] |
| Level promotion | Auto-promoted when thresholds met | [ ] |
| Promotion email | Notification sent on level up | [ ] |
| Level shown on service page | Badge visible to buyers | [ ] |
| Level shown on vendor profile | Badge visible publicly | [ ] |

### 22.3 Level Benefits
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Higher level = lower commission | Commission rate adjusted per level | [ ] |
| Level-specific features | Unlocked based on level | [ ] |
| Demotion (if applicable) | Level drops if metrics decline | [ ] |

---

## 23. Favorites / Wishlist

### 23.1 Adding Favorites
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Heart/favorite icon on service card | Clickable icon visible | [ ] |
| Click favorite (logged in) | Service added to favorites | [ ] |
| Click favorite (logged out) | Redirect to login or prompt | [ ] |
| Icon state changes | Filled heart when favorited | [ ] |
| Favorite from service detail page | Works from single service page | [ ] |

### 23.2 Managing Favorites
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View favorites list | All favorited services shown | [ ] |
| Remove from favorites | Service removed, icon updates | [ ] |
| Favorited service deleted by vendor | Removed from list gracefully | [ ] |

---

## 24. Portfolio

### 24.1 Creating Portfolio Items
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Navigate to portfolio section | Portfolio tab in dashboard | [ ] |
| Click "Add Portfolio Item" | Form opens | [ ] |
| Enter title and description | Text fields work | [ ] |
| Upload images | Image upload works | [ ] |
| Add project URL (optional) | URL field saves | [ ] |
| Save portfolio item | Item created successfully | [ ] |

### 24.2 Managing Portfolio
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View all portfolio items | Grid/list of items | [ ] |
| Edit portfolio item | All fields editable | [ ] |
| Reorder items | Drag to reorder works | [ ] |
| Delete portfolio item | Removed after confirmation | [ ] |
| Portfolio visible on vendor profile | Public display works | [ ] |

---

## 25. Tipping

### 25.1 Sending a Tip
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Tip option on completed order | "Send Tip" button visible | [ ] |
| Enter tip amount | Amount field accepts input | [ ] |
| Confirm tip | Payment processed | [ ] |
| Tip recorded | Shows in order timeline | [ ] |
| Vendor receives tip | Added to vendor earnings | [ ] |
| Tip not available on cancelled orders | Button hidden | [ ] |

### 25.2 Tip History
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Buyer sees tips sent | In order history | [ ] |
| Vendor sees tips received | In earnings breakdown | [ ] |

---

## 26. Milestones

### 26.1 Creating Milestones (Vendor)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View order with milestones enabled | Milestones section visible | [ ] |
| Add milestone | Title, description, amount fields | [ ] |
| Set milestone order | Sequential numbering | [ ] |
| Save milestones | Milestones created | [ ] |

### 26.2 Milestone Workflow
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Vendor submits milestone | Status = submitted | [ ] |
| Buyer reviews milestone | Details visible | [ ] |
| Buyer approves milestone | Payment released for milestone | [ ] |
| Buyer requests revision | Milestone sent back to vendor | [ ] |
| All milestones complete | Order marked complete | [ ] |

---

## 27. Extension Requests

### 27.1 Requesting Extension (Vendor)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Order near deadline | Extension request button visible | [ ] |
| Request extension | Form with reason and new deadline | [ ] |
| Submit request | Buyer notified | [ ] |

### 27.2 Responding to Extension (Buyer)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View extension request | Details and new deadline shown | [ ] |
| Approve extension | Deadline updated | [ ] |
| Reject extension | Original deadline remains | [ ] |

---

## 28. REST API

### 28.1 Authentication
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| `POST /wpss/v1/auth/login` | Returns token on valid credentials | [ ] |
| Login with wrong password | 401 error returned | [ ] |
| `POST /wpss/v1/auth/register` | Creates new user account | [ ] |
| `POST /wpss/v1/auth/logout` | Invalidates session | [ ] |
| Unauthenticated request to protected endpoint | 401 returned | [ ] |
| Application Password auth | Works for API requests | [ ] |

### 28.2 Core Endpoints
| Endpoint | Method | Expected | Test Status |
|----------|--------|----------|-------------|
| `/wpss/v1/services` | GET | List services with pagination | [ ] |
| `/wpss/v1/services/{id}` | GET | Single service details | [ ] |
| `/wpss/v1/orders` | GET | User's orders | [ ] |
| `/wpss/v1/orders/{id}` | GET | Order detail | [ ] |
| `/wpss/v1/orders` | POST | Create order | [ ] |
| `/wpss/v1/conversations/{id}` | GET | Order messages | [ ] |
| `/wpss/v1/conversations/{id}` | POST | Send message | [ ] |
| `/wpss/v1/reviews` | GET | Service reviews | [ ] |
| `/wpss/v1/reviews` | POST | Submit review | [ ] |
| `/wpss/v1/vendors` | GET | List vendors | [ ] |
| `/wpss/v1/me` | GET | Current user info | [ ] |
| `/wpss/v1/dashboard` | GET | Dashboard stats | [ ] |
| `/wpss/v1/categories` | GET | Service categories | [ ] |
| `/wpss/v1/search` | GET | Search services/vendors | [ ] |

### 28.3 Batch Endpoint
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| `POST /wpss/v1/batch` with 5 sub-requests | All 5 responses returned | [ ] |
| Batch with 25 sub-requests | Max limit works | [ ] |
| Batch with 26+ sub-requests | Error: exceeds limit | [ ] |
| Mixed success/failure in batch | Each sub-request has own status | [ ] |

### 28.4 Permission Checks
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Vendor accesses own orders | 200 OK | [ ] |
| Vendor accesses other's orders | 403 forbidden | [ ] |
| Buyer creates service | 403 forbidden | [ ] |
| Admin accesses any resource | 200 OK | [ ] |
| Non-vendor accesses vendor endpoints | 403 forbidden | [ ] |

---

## 29. Cart & Standalone Checkout

### 29.1 Cart Operations
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Add service to cart | Service added, cart count updates | [ ] |
| Add service with addons | Addons reflected in cart | [ ] |
| View cart | Service, package, addons, total shown | [ ] |
| Remove from cart | Item removed, total updates | [ ] |
| Change package in cart | Price updates | [ ] |
| Empty cart message | "Your cart is empty" shown | [ ] |

### 29.2 Standalone Checkout
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Proceed to checkout | Checkout page loads | [ ] |
| Order summary visible | Service, price, addons correct | [ ] |
| Select payment gateway | Available gateways shown | [ ] |
| Complete checkout | Order created | [ ] |
| Redirect to confirmation | Order confirmation page shown | [ ] |
| Guest checkout (if enabled) | Works without account | [ ] |

---

## 30. Payment Gateways

### 30.1 Stripe Gateway
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Enable Stripe in Settings > Gateways | Toggle saves | [ ] |
| Enter test API keys | Keys saved (secret masked) | [ ] |
| Test mode toggle | Switches between test/live keys | [ ] |
| Stripe appears at checkout | Payment form shown | [ ] |
| Enter test card (4242...) | Payment succeeds | [ ] |
| Declined card (4000000000000002) | Error shown to buyer | [ ] |
| 3D Secure card | Authentication modal appears | [ ] |
| Webhook received | Order status updated | [ ] |
| Refund via admin | Stripe refund processed | [ ] |

### 30.2 PayPal Gateway
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Enable PayPal in Settings > Gateways | Toggle saves | [ ] |
| Enter sandbox credentials | Client ID/secret saved | [ ] |
| Sandbox mode toggle | Switches between sandbox/live | [ ] |
| PayPal appears at checkout | PayPal button shown | [ ] |
| Complete PayPal payment | Redirects to PayPal, back to site | [ ] |
| Cancel PayPal payment | Returns to checkout, order pending | [ ] |
| IPN/webhook received | Order status updated | [ ] |

### 30.3 Offline Gateway
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Enable Offline in Settings > Gateways | Toggle saves | [ ] |
| Set custom title and instructions | Fields save | [ ] |
| Offline appears at checkout | Instructions shown | [ ] |
| Place offline order | Order created as `pending_payment` | [ ] |
| Admin marks payment received | Order advances to next status | [ ] |

---

## 31. WP-CLI Commands

### 31.1 Demo Content
| Command | Expected Behavior | Test Status |
|---------|------------------|-------------|
| `wp wpss demo create` | Creates demo services | [ ] |
| `wp wpss demo create --count=5` | Creates 5 demo services | [ ] |
| `wp wpss demo delete --yes` | Deletes all demo services | [ ] |
| `wp wpss service list` | Lists all services with stats | [ ] |

---

## 32. Cron Jobs & Background Tasks

### 32.1 Scheduled Events
| Cron Event | Expected Behavior | Test Status |
|-----------|------------------|-------------|
| `wpss_auto_complete_orders` (hourly) | Auto-completes orders past deadline | [ ] |
| `wpss_cleanup_expired_requests` (daily) | Removes expired buyer requests | [ ] |
| `wpss_update_vendor_stats` (twice daily) | Updates vendor statistics cache | [ ] |
| Auto-withdrawal cron | Processes auto-withdrawals per schedule | [ ] |

### 32.2 Cron Verification
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Check scheduled events | All 3+ events registered | [ ] |
| Manually trigger auto-complete | Orders past deadline completed | [ ] |
| Deactivate plugin | Cron events cleared | [ ] |
| Reactivate plugin | Cron events re-registered | [ ] |

---

## 33. In-App Notifications

### 33.1 Notification Delivery
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| New order placed | Vendor gets notification | [ ] |
| Order delivered | Buyer gets notification | [ ] |
| New message received | Other party notified | [ ] |
| Revision requested | Vendor notified | [ ] |
| Dispute opened | Admin + other party notified | [ ] |
| Review received | Vendor notified | [ ] |

### 33.2 Notification UI
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Bell icon in dashboard | Shows unread count badge | [ ] |
| Click bell | Notification dropdown opens | [ ] |
| Click notification | Goes to relevant page (order, message) | [ ] |
| Mark as read | Notification marked, count decreases | [ ] |
| Mark all read | All notifications cleared | [ ] |
| Empty state | "No notifications" message | [ ] |

---

## 34. Media Uploads & Attachments

### 34.1 Service Media
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Upload featured image | Image uploads via WP media library | [ ] |
| Upload gallery images | Multiple images upload | [ ] |
| Vendor has upload_files cap | Media library accessible | [ ] |
| File size limit | Large files rejected with message | [ ] |
| Allowed file types | Only images for gallery | [ ] |

### 34.2 Conversation Attachments
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Attach file in conversation | File uploads and attaches | [ ] |
| Download attachment | File downloads correctly | [ ] |
| Multiple attachments | All files attach | [ ] |
| File type restrictions | Non-allowed types rejected | [ ] |

### 34.3 Delivery Files
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Attach delivery files | Files upload with delivery | [ ] |
| Buyer downloads delivery | All files downloadable | [ ] |
| Large file delivery | Handles within WP upload limits | [ ] |

---

## 35. Activation & Database

### 35.1 Fresh Installation
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Activate plugin | No PHP errors | [ ] |
| Database tables created | All 20 tables exist | [ ] |
| Vendor role created | `wpss_vendor` role with correct caps | [ ] |
| Default options set | All `wpss_*` options created | [ ] |
| Cron events scheduled | All events registered | [ ] |
| Rewrite rules flushed | Permalinks work | [ ] |

### 35.2 Deactivation
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Deactivate plugin | No errors | [ ] |
| Cron events removed | Scheduled events cleared | [ ] |
| Data preserved | Options and tables intact | [ ] |
| Reactivate | Everything works without re-setup | [ ] |

### 35.3 Uninstall (Delete Data enabled)
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Enable "Delete data on uninstall" | Setting saved | [ ] |
| Delete plugin | All tables dropped | [ ] |
| Options removed | All `wpss_*` options deleted | [ ] |
| Roles removed | `wpss_vendor` role deleted | [ ] |

---

## 36. Template Overrides

### 36.1 Theme Override System
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Copy template to `theme/wp-sell-services/` | Override detected | [ ] |
| Modified template renders | Theme version used instead of plugin | [ ] |
| Delete theme override | Falls back to plugin template | [ ] |
| Partial override | Only overridden templates affected | [ ] |

---

## 37. Search & Filtering

### 37.1 Service Search
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Search by keyword | Relevant services returned | [ ] |
| Filter by category | Only matching category shown | [ ] |
| Filter by price range | Services within range | [ ] |
| Filter by rating | Min rating filter works | [ ] |
| Sort by price ascending | Correct order | [ ] |
| Sort by price descending | Correct order | [ ] |
| Sort by rating | Highest rated first | [ ] |
| Sort by newest | Most recent first | [ ] |
| No results | "No services found" message | [ ] |
| Pagination | Multiple pages work | [ ] |

### 37.2 Vendor Search
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Search vendors by name | Matching vendors shown | [ ] |
| Filter by category | Vendors in that category | [ ] |
| Filter by rating | Min rating filter | [ ] |
| Sort by rating/orders | Correct ordering | [ ] |

---

## How to Use This Checklist

1. **Before Release**: Complete all Critical Path tests
2. **Weekly**: Run through High Priority tests
3. **Monthly**: Full checklist review
4. **After Changes**: Re-test affected flows

Mark each test with:
- `[x]` = Passed
- `[!]` = Failed (create bug ticket)
- `[-]` = Not applicable
- `[ ]` = Not tested

---

## Changelog

| Date | Changes |
|------|---------|
| 2026-02-02 | Initial creation - comprehensive user journey checklist |
| 2026-02-02 | Added complete vendor journeys: registration (3), service creation (4), service management (5), earnings & withdrawals (13) |
| 2026-02-18 | Added Section 17: Admin Settings Pages (unified accordion architecture, all tabs) |
| 2026-02-18 | Added Section 18: Buyer Requests (job board, proposals) |
| 2026-02-20 | Added Sections 19-37: Setup Wizard, Gutenberg Blocks, Shortcodes, Seller Levels, Favorites, Portfolio, Tipping, Milestones, Extension Requests, REST API, Cart & Checkout, Payment Gateways, WP-CLI, Cron Jobs, Notifications, Media, Activation, Templates, Search |
