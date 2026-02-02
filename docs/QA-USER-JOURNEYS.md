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
