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

## 3. Vendor Journey: Receiving & Fulfilling Orders

### 3.1 New Order Notification
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Buyer places order | Vendor receives email notification | [ ] |
| Vendor dashboard shows new order | Order visible in orders list | [ ] |
| Notification bell shows count | Unread notification count updated | [ ] |

### 3.2 Viewing Order Details
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click order | Order detail page loads | [ ] |
| See buyer info | Name, avatar visible | [ ] |
| See service ordered | Service name, package, addons | [ ] |
| See requirements | Buyer's submitted requirements visible | [ ] |
| See order timeline | Status history shown | [ ] |
| See deadline | Due date and countdown visible | [ ] |

### 3.3 Working on Order
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Send message to buyer | Message delivered and visible to both | [ ] |
| Receive message from buyer | Notification and message visible | [ ] |
| Work on order | Status remains `in_progress` | [ ] |
| Deadline approaching | Warning shown if near due date | [ ] |

### 3.4 Delivering Order
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click "Deliver" button | Delivery form/modal opens | [ ] |
| Add delivery message | Text field for description | [ ] |
| Attach files | File upload works | [ ] |
| Submit delivery | Delivery recorded | [ ] |
| Status changes | Status = `delivered` or `pending_approval` | [ ] |
| Buyer notified | Email sent to buyer | [ ] |

---

## 4. Buyer Journey: Receiving Delivery

### 4.1 Delivery Notification
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Vendor delivers | Buyer receives email notification | [ ] |
| Dashboard shows update | Order status updated | [ ] |

### 4.2 Reviewing Delivery
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| View order | Delivery details visible | [ ] |
| Download files | All attached files downloadable | [ ] |
| Read vendor message | Delivery description shown | [ ] |

### 4.3 Accepting Delivery
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click "Accept" | Confirmation prompt | [ ] |
| Confirm acceptance | Order status = `completed` | [ ] |
| Review prompt shown | Option to leave review | [ ] |
| Vendor notified | Email sent to vendor | [ ] |
| Vendor gets paid | Earnings added to vendor balance | [ ] |

### 4.4 Requesting Revision
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Click "Request Revision" | Revision form opens | [ ] |
| Enter revision details | Text field for explanation | [ ] |
| Submit revision request | Status = `revision_requested` | [ ] |
| Vendor notified | Email and notification sent | [ ] |
| Revision count tracked | Revisions remaining shown | [ ] |

---

## 5. Messaging Flow

### 5.1 Buyer Messaging
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Open order conversation | Message history loaded | [ ] |
| Send text message | Message appears immediately | [ ] |
| Attach file | File uploads and shows | [ ] |
| Vendor receives | Real-time or polling update | [ ] |

### 5.2 Vendor Messaging
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Open order conversation | Message history loaded | [ ] |
| Send text message | Message appears immediately | [ ] |
| Attach file | File uploads and shows | [ ] |
| Buyer receives | Real-time or polling update | [ ] |

### 5.3 Message Availability by Status
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

## 6. Review Flow

### 6.1 Leaving a Review
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Order completed | Review CTA shown to buyer | [ ] |
| Click "Write Review" | Review form opens | [ ] |
| Select rating (1-5 stars) | Stars highlight on selection | [ ] |
| Write review text | Text area accepts input | [ ] |
| Submit review | Review saved | [ ] |
| Review shows on service | Visible on service page | [ ] |
| Vendor sees review | Visible in vendor dashboard | [ ] |

### 6.2 Review Restrictions
| Condition | Expected Behavior | Test Status |
|-----------|------------------|-------------|
| Order not completed | Review button not shown | [ ] |
| Already reviewed | "Already reviewed" message | [ ] |
| Not the buyer | Review button not shown | [ ] |

---

## 7. Dispute Flow

### 7.1 Opening a Dispute
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Eligible order status | Dispute button visible | [ ] |
| Click "Open Dispute" | Dispute form opens | [ ] |
| Select reason | Dropdown with reasons | [ ] |
| Describe issue | Text area for details | [ ] |
| Submit dispute | Dispute created | [ ] |
| Order status changes | Status = `disputed` | [ ] |
| Admin notified | Admin sees new dispute | [ ] |

### 7.2 Dispute Resolution
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Admin views dispute | Full details visible | [ ] |
| Admin resolves for buyer | Buyer gets refund | [ ] |
| Admin resolves for vendor | Vendor gets payment | [ ] |
| Both parties notified | Email notifications sent | [ ] |

---

## 8. Cancellation Flow

### 8.1 Buyer Cancellation
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Eligible status | Cancel button visible | [ ] |
| Click "Cancel Order" | Confirmation + reason prompt | [ ] |
| Enter reason | Text input for reason | [ ] |
| Confirm cancellation | Order status = `cancelled` | [ ] |
| Refund processed | Buyer refunded (if applicable) | [ ] |
| Vendor notified | Email sent to vendor | [ ] |

### 8.2 Vendor Cancellation
| Step | Expected Behavior | Test Status |
|------|------------------|-------------|
| Eligible status | Cancel button visible | [ ] |
| Click "Cancel Order" | Confirmation + reason prompt | [ ] |
| Enter reason | Text input for reason | [ ] |
| Confirm cancellation | Order status = `cancelled` | [ ] |
| Buyer refunded | Automatic refund processed | [ ] |
| Buyer notified | Email sent to buyer | [ ] |

---

## 9. Vendor Dashboard

### 9.1 Dashboard Overview
| Element | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Stats cards | Earnings, orders, rating shown | [ ] |
| Active orders list | Current orders visible | [ ] |
| Recent messages | Latest messages shown | [ ] |
| Earnings chart | Graphical earnings display | [ ] |

### 9.2 Orders Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List all orders | Orders with status, buyer, amount | [ ] |
| Filter by status | Filtering works | [ ] |
| Search orders | Search by ID, buyer name | [ ] |
| Pagination | Pages work correctly | [ ] |
| Click order | Goes to order detail | [ ] |

### 9.3 Services Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List my services | All vendor's services shown | [ ] |
| See status | Published, draft, pending shown | [ ] |
| Edit service | Edit link works | [ ] |
| Create new | Create button works | [ ] |
| Delete service | Delete with confirmation | [ ] |

### 9.4 Earnings Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Available balance | Current withdrawable amount | [ ] |
| Pending balance | Amount awaiting clearance | [ ] |
| Earnings history | List of completed orders | [ ] |
| Request withdrawal | Withdrawal form works | [ ] |

### 9.5 Settings Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Profile info | Name, bio, avatar editable | [ ] |
| Payment methods | Payment details configurable | [ ] |
| Notification preferences | Email toggles work | [ ] |
| Save settings | Changes persist | [ ] |

---

## 10. Buyer Dashboard

### 10.1 Dashboard Overview
| Element | Expected Behavior | Test Status |
|---------|------------------|-------------|
| Active orders | Current orders visible | [ ] |
| Order stats | Total orders, completed count | [ ] |
| Recent messages | Latest conversations shown | [ ] |

### 10.2 Orders Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List all orders | Orders with status, vendor, amount | [ ] |
| Filter by status | Filtering works | [ ] |
| Click order | Goes to order detail | [ ] |

### 10.3 Messages Tab
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| List conversations | All order conversations | [ ] |
| Unread indicator | Unread count shown | [ ] |
| Click conversation | Opens message view | [ ] |

---

## 11. Admin Workflows

### 11.1 Manual Order Creation
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

### 11.2 Order Management
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| View order | Full order details visible | [ ] |
| Change status | Status update works | [ ] |
| Add admin note | Note saved and visible | [ ] |
| View messages | Conversation visible | [ ] |
| View requirements | Submitted requirements visible | [ ] |
| Enter requirements | Admin can fill requirements form | [ ] |

### 11.3 Service Moderation
| Feature | Expected Behavior | Test Status |
|---------|------------------|-------------|
| View pending services | List shows pending | [ ] |
| Approve service | Status changes to published | [ ] |
| Reject service | Status changes, reason saved | [ ] |
| Vendor notified | Email sent on approval/rejection | [ ] |

---

## 12. Email Notifications

### 12.1 Order Emails
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| Order placed | Buyer + Vendor | [ ] |
| Requirements submitted | Vendor | [ ] |
| Order started | Buyer | [ ] |
| Delivery submitted | Buyer | [ ] |
| Revision requested | Vendor | [ ] |
| Order completed | Buyer + Vendor | [ ] |
| Order cancelled | Buyer + Vendor | [ ] |

### 12.2 Message Emails
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| New message | Other party | [ ] |

### 12.3 Review Emails
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| New review | Vendor | [ ] |

### 12.4 Dispute Emails
| Event | Recipient | Test Status |
|-------|-----------|-------------|
| Dispute opened | Admin + other party | [ ] |
| Dispute resolved | Buyer + Vendor | [ ] |

---

## Testing Priority

### Critical Path (Must Work)
1. Service discovery and viewing
2. Purchase and checkout
3. Requirements submission
4. Messaging
5. Delivery and acceptance
6. Review submission

### High Priority
1. Revision flow
2. Cancellation flow
3. Vendor dashboard
4. Admin order management

### Medium Priority
1. Dispute flow
2. Withdrawal flow
3. Service moderation
4. Email notifications

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
