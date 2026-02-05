# WP Sell Services - REST API Mapping for Mobile App

**Purpose**: Comprehensive list of all user-facing features and data operations that require REST API endpoints for mobile app functionality.

**Last Updated**: 2026-02-03

---

## Table of Contents
1. [Service Management (CRUD)](#service-management-crud)
2. [Order Lifecycle Management](#order-lifecycle-management)
3. [Messaging & Conversations](#messaging--conversations)
4. [Reviews & Ratings](#reviews--ratings)
5. [Disputes & Resolution](#disputes--resolution)
6. [Buyer Requests & Proposals](#buyer-requests--proposals)
7. [Deliveries & Files](#deliveries--files)
8. [Vendor Management](#vendor-management)
9. [Portfolio & Work Samples](#portfolio--work-samples)
10. [Earnings & Payouts](#earnings--payouts)
11. [Commissions & Revenue](#commissions--revenue)
12. [Notifications](#notifications)
13. [Search & Discovery](#search--discovery)
14. [Moderation & Approvals](#moderation--approvals)
15. [Extensions & Milestones](#extensions--milestones)
16. [Tipping](#tipping)

---

## SERVICE MANAGEMENT (CRUD)

**Service**: `ServiceManager`  
**Purpose**: Create, read, update, delete services and manage service metadata

### Endpoints Needed

#### Service Creation
- **POST** `/services`
  - Method: `ServiceManager->create()`
  - Required: title, description, category, package data
  - Returns: Service object with ID
  - Related: saves packages, requirements, FAQs

#### Service Retrieval
- **GET** `/services/{id}`
  - Method: `ServiceManager->get()`
  - Returns: Single service with all metadata
  
- **GET** `/services`
  - Method: `ServiceManager->search()`
  - Query params: search_term, category, vendor_id, status, limit, offset
  - Returns: Service list with pagination
  
- **GET** `/vendors/{vendor_id}/services`
  - Method: `ServiceManager->get_by_vendor()`
  - Returns: All services by specific vendor

#### Service Updates
- **PUT** `/services/{id}`
  - Method: `ServiceManager->update()`
  - Can update: title, description, category, tags, status, etc.
  
- **PUT** `/services/{id}/packages`
  - Method: `ServiceManager->save_packages()`
  - Updates: pricing tiers with delivery days and features
  
- **PUT** `/services/{id}/requirements`
  - Method: `ServiceManager->save_requirements()`
  - Updates: questionnaire/form fields buyers must complete
  
- **PUT** `/services/{id}/faqs`
  - Method: `ServiceManager->save_faqs()`
  - Updates: FAQ items for service

#### Service Deletion
- **DELETE** `/services/{id}`
  - Method: `ServiceManager->delete()`

#### Service Ratings
- **GET** `/services/{id}/rating`
  - Method: `ServiceManager->get_rating()`
  - Returns: Average rating and review count
  
- **PATCH** `/services/{id}/rating`
  - Method: `ServiceManager->update_rating()`
  - Used internally when reviews are added/updated

#### Service Statistics
- **GET** `/services/{id}/stats`
  - Method: `ServiceManager->get_stats()`
  - Returns: order_count, review_count, rating, completion rate, etc.
  
- **PATCH** `/services/{id}/increment-orders`
  - Method: `ServiceManager->increment_order_count()`
  - Used when order is completed

#### Service Addons (if exists)
- **GET** `/services/{id}/addons`
  - Returns: extra features/rush delivery options

---

## ORDER LIFECYCLE MANAGEMENT

**Service**: `OrderService`  
**Purpose**: Create orders, manage status transitions, handle order modifications

### Endpoints Needed

#### Order Retrieval
- **GET** `/orders/{id}`
  - Method: `OrderService->get()`
  - Returns: Full order details
  
- **GET** `/orders`
  - Query param: `order_number` (alternative lookup)
  - Method: `OrderService->get_by_number()`
  
- **GET** `/me/orders`
  - Method: `OrderService->get_customer_orders()`
  - Query params: status, limit, offset, order_by, order
  - Returns: Customer's orders (paginated)
  - Auth: Current user must be customer
  
- **GET** `/vendors/{vendor_id}/orders`
  - Method: `OrderService->get_vendor_orders()`
  - Query params: status, limit, offset, order_by, order
  - Returns: Vendor's orders (paginated)
  - Auth: Must be vendor or admin

#### Order Status Transitions
- **PATCH** `/orders/{id}/status`
  - Method: `OrderService->update_status()`
  - Body: `{ "status": "in_progress", "note": "optional reason" }`
  - Validates: `OrderService->can_transition()`
  
  **Valid Status Transitions**:
  - `pending_payment` → `pending_requirements`, `cancelled`
  - `pending_requirements` → `in_progress`, `cancelled`, `on_hold`
  - `in_progress` → `pending_approval`, `on_hold`, `cancelled`, `late`
  - `pending_approval` → `completed`, `revision_requested`, `disputed`
  - `revision_requested` → `in_progress`, `cancelled`, `disputed`
  - `late` → `pending_approval`, `cancelled`, `disputed`
  - `on_hold` → `in_progress`, `cancelled`
  - `disputed` → `completed`, `cancelled`

#### Order Actions
- **POST** `/orders/{id}/start-work`
  - Method: `OrderService->start_work()`
  - Action: Moves to `in_progress`, sets delivery deadline
  - Side effect: Calculates deadline from package delivery_days + addons

- **POST** `/orders/{id}/request-revision`
  - Method: `OrderService->request_revision()`
  - Body: `{ "reason": "what needs revision" }`
  - Updates: revision count, status → `revision_requested`

- **POST** `/orders/{id}/cancel`
  - Method: `OrderService->cancel()`
  - Body: `{ "reason": "cancellation reason", "user_id": current_user }`
  - Returns: `{ "success": bool, "message": string }`

- **POST** `/orders/{id}/extend-deadline`
  - Method: `OrderService->extend_deadline()`
  - Body: `{ "extra_days": number }`
  - Action: Extends delivery_deadline by N days

---

## MESSAGING & CONVERSATIONS

**Service**: `ConversationService`  
**Purpose**: Real-time messaging between buyers and sellers, order discussion thread

### Endpoints Needed

#### Conversation Retrieval
- **GET** `/conversations/{id}`
  - Method: `ConversationService->get()`
  - Returns: Conversation object with metadata
  - Auth: User must be participant

- **GET** `/orders/{order_id}/conversation`
  - Method: `ConversationService->get_by_order()`
  - Returns: The main conversation for this order
  
- **GET** `/me/conversations`
  - Method: `ConversationService->get_by_user()`
  - Query params: limit, offset
  - Returns: All conversations for current user (paginated)

#### Message Operations
- **GET** `/conversations/{id}/messages`
  - Method: `ConversationService->get_messages()`
  - Query params: limit, offset
  - Returns: Messages paginated (newest first or oldest first)
  
- **POST** `/conversations/{id}/messages`
  - Method: `ConversationService->send_message()`
  - Body: `{ "message": "content", "attachments": [...] }`
  - Returns: Created message object
  - Triggers: Notification to other participant

- **POST** `/conversations/{id}/system-message`
  - Method: `ConversationService->add_system_message()`
  - Body: `{ "message": "status changed from X to Y" }`
  - Action: Creates automated/admin message (order status change, etc.)
  - Auth: System/admin only

- **GET** `/conversations/{id}/last-message`
  - Method: `ConversationService->get_last_message()`
  - Returns: Most recent message (for preview)

#### Read Status
- **PATCH** `/conversations/{id}/mark-as-read`
  - Method: `ConversationService->mark_as_read()`
  - Action: Marks all messages in conversation as read

- **PATCH** `/me/conversations/mark-all-as-read`
  - Method: `ConversationService->mark_all_as_read()`
  - Action: Marks all user's unread conversations as read

- **GET** `/me/conversations/unread-count`
  - Method: `ConversationService->get_total_unread_count()`
  - Returns: `{ "count": number }`

- **GET** `/conversations/{id}/unread-count`
  - Method: `ConversationService->get_unread_count()`
  - Returns: `{ "count": number }`

#### Conversation Management
- **POST** `/conversations`
  - Method: `ConversationService->create_for_order()`
  - Body: `{ "order_id": number, "subject": "string" }`
  - Returns: Conversation object

- **POST** `/conversations/{id}/close`
  - Method: `ConversationService->close()`
  - Action: Marks conversation as closed (no more messages)

- **GET** `/conversations/stats`
  - Method: `ConversationService->count_by_user()`
  - Returns: Conversation count per user

---

## REVIEWS & RATINGS

**Service**: `ReviewService`  
**Purpose**: Submit and manage service and vendor reviews/ratings

### Endpoints Needed

#### Review Creation
- **POST** `/orders/{order_id}/review`
  - Method: `ReviewService->create()`
  - Body: 
    ```json
    {
      "rating": 1-5,
      "rating_communication": 1-5,
      "rating_quality": 1-5,
      "rating_value": 1-5,
      "content": "review text"
    }
    ```
  - Auth: Must be order customer
  - Validation:
    - Order must be completed
    - No existing review for this order
    - Within review time window (default 30 days)
    - Rating 1-5 only
  - Side effect: Updates service and vendor ratings

#### Review Retrieval
- **GET** `/reviews/{id}`
  - Method: `ReviewService->get()`
  - Returns: Full review object

- **GET** `/orders/{order_id}/review`
  - Method: `ReviewService->get_by_order()`
  - Returns: Review for this order (or null)

- **GET** `/services/{service_id}/reviews`
  - Method: `ReviewService->get_service_reviews()`
  - Query params: limit, offset
  - Returns: Approved reviews for service (paginated)

- **GET** `/vendors/{vendor_id}/reviews`
  - Method: `ReviewService->get_vendor_reviews()`
  - Query params: limit, offset
  - Returns: Approved reviews for vendor (paginated)

#### Vendor Response
- **POST** `/reviews/{id}/response`
  - Method: `ReviewService->add_response()`
  - Body: `{ "response": "vendor reply text" }`
  - Auth: Must be reviewed vendor
  - Validation: No existing response

#### Review Metadata
- **GET** `/services/{service_id}/rating`
  - Returns: Average rating, review count, rating distribution
  - Uses: `ReviewService->update_service_rating()`

- **GET** `/vendors/{vendor_id}/rating`
  - Returns: Average rating, review count
  - Uses: `ReviewService->update_vendor_rating()`

#### Review Window Info
- **GET** `/orders/{order_id}/review-window`
  - Method: `ReviewService->get_remaining_review_days()`
  - Returns: `{ "can_review": bool, "days_remaining": number | null }`
  
- **GET** `/orders/{order_id}/can-review`
  - Method: `ReviewService->can_review()`
  - Returns: 
    ```json
    {
      "can_review": bool,
      "reason": "Order must be completed..." or ""
    }
    ```

---

## DISPUTES & RESOLUTION

**Service**: `DisputeService`  
**Purpose**: Open, manage, and resolve order disputes

### Endpoints Needed

#### Dispute Creation
- **POST** `/orders/{order_id}/dispute`
  - Method: `DisputeService->open()`
  - Body:
    ```json
    {
      "reason": "quality issues, missed deadline, etc.",
      "evidence": ["file_ids"],
      "amount": number
    }
    ```
  - Returns: Dispute object
  - Notification: Sends to vendor and support team

#### Dispute Retrieval
- **GET** `/disputes/{id}`
  - Method: `DisputeService->get()`
  - Returns: Full dispute with evidence

- **GET** `/orders/{order_id}/dispute`
  - Method: `DisputeService->get_by_order()`
  - Returns: Dispute for this order (or null)

- **GET** `/me/disputes`
  - Method: `DisputeService->get_by_user()`
  - Query params: status, limit, offset
  - Returns: Disputes for current user
  - Status filter: open, pending, resolved, escalated, closed

- **GET** `/disputes`
  - Method: `DisputeService->get_all()`
  - Auth: Admin only
  - Returns: All disputes with filters

#### Dispute Evidence
- **POST** `/disputes/{id}/evidence`
  - Method: `DisputeService->add_evidence()`
  - Body: `{ "description": "string", "file_ids": [...] }`
  - Returns: Evidence object

- **GET** `/disputes/{id}/evidence`
  - Method: `DisputeService->get_evidence()`
  - Returns: All evidence for dispute

#### Dispute Status & Resolution
- **PATCH** `/disputes/{id}/status`
  - Method: `DisputeService->update_status()`
  - Body: `{ "status": "pending|resolved|escalated|closed" }`
  - Auth: Admin only

- **POST** `/disputes/{id}/resolve`
  - Method: `DisputeService->resolve()`
  - Body:
    ```json
    {
      "resolution_type": "refund|partial_refund|favor_vendor|favor_buyer|mutual",
      "refund_amount": number,
      "notes": "resolution notes"
    }
    ```
  - Returns: Updated dispute
  - Side effects:
    - Triggers refund if applicable
    - Updates order status
    - Sends notifications

#### Dispute Statistics
- **GET** `/disputes/stats`
  - Method: `DisputeService->count_by_status()`
  - Returns: `{ "open": N, "pending": N, "resolved": N, ... }`

**Status Flow**:
- `open` → `pending` (admin review started)
- `pending` → `resolved` (decision made)
- `resolved` → `closed` (refund processed)
- Any → `escalated` (needs higher review)

**Resolution Types**:
- `refund` - Full refund to buyer
- `partial_refund` - Partial refund
- `favor_vendor` - Vendor was right, no refund
- `favor_buyer` - Buyer was right, full refund
- `mutual` - Both parties compromise

---

## BUYER REQUESTS & PROPOSALS

**Service**: `BuyerRequestService`, `ProposalService`  
**Purpose**: Manage buyer job posts and vendor proposals

### Buyer Requests - Endpoints Needed

#### Request Creation
- **POST** `/buyer-requests`
  - Method: `BuyerRequestService->create()`
  - Body:
    ```json
    {
      "title": "I need...",
      "description": "Details...",
      "category": "category_slug",
      "budget_type": "fixed|range",
      "budget_min": number,
      "budget_max": number,
      "deadline": "YYYY-MM-DD",
      "attachments": ["file_ids"],
      "tags": ["tag1", "tag2"]
    }
    ```
  - Returns: Request object
  - Auth: Buyer/customer only

#### Request Retrieval
- **GET** `/buyer-requests/{id}`
  - Method: `BuyerRequestService->get()`
  - Returns: Full request with proposal count

- **GET** `/buyer-requests`
  - Method: `BuyerRequestService->search()`
  - Query params: status, category, min_budget, max_budget, search, limit, offset
  - Returns: Open requests (paginated)
  - Status filter: open, in_review, hired, expired, cancelled

- **GET** `/me/buyer-requests`
  - Method: `BuyerRequestService->get_by_buyer()`
  - Query params: limit, offset
  - Returns: Current user's requests

- **GET** `/buyer-requests/trending`
  - Method: `BuyerRequestService->get_open()` + sorting
  - Returns: Most recent open requests

#### Request Updates
- **PUT** `/buyer-requests/{id}`
  - Method: `BuyerRequestService->update()`
  - Can update: title, description, deadline, budget

- **PATCH** `/buyer-requests/{id}/status`
  - Method: `BuyerRequestService->update_status()`
  - Body: `{ "status": "open|in_review|hired|expired|cancelled" }`

- **POST** `/buyer-requests/{id}/hire`
  - Method: `BuyerRequestService->mark_hired()`
  - Body: `{ "vendor_id": number, "proposal_id": number }`
  - Returns: Order object
  - Side effect: Converts request to order, rejects other proposals

#### Request Metadata
- **GET** `/buyer-requests/{id}/proposal-count`
  - Method: `BuyerRequestService->get_proposal_count()`
  - Returns: Number of proposals received

---

### Proposals - Endpoints Needed

#### Proposal Submission
- **POST** `/buyer-requests/{request_id}/proposals`
  - Method: `ProposalService->submit()`
  - Body:
    ```json
    {
      "price": number,
      "delivery_days": number,
      "description": "pitch text",
      "attachments": ["file_ids"]
    }
    ```
  - Auth: Vendor only
  - Validation: Vendor can only propose once per request
  - Returns: Proposal object

#### Proposal Retrieval
- **GET** `/proposals/{id}`
  - Method: `ProposalService->get()`
  - Returns: Full proposal

- **GET** `/buyer-requests/{request_id}/proposals`
  - Method: `ProposalService->get_by_request()`
  - Query params: limit, offset
  - Returns: All proposals for request
  - Auth: Must be request owner or admin

- **GET** `/vendors/{vendor_id}/proposals`
  - Method: `ProposalService->get_by_vendor()`
  - Query params: status, limit, offset
  - Returns: All proposals by vendor

#### Proposal Actions
- **PATCH** `/proposals/{id}/status`
  - Method: `ProposalService->update_status()`
  - Body: `{ "status": "pending|accepted|rejected|withdrawn" }`
  - Validation: Only request owner can reject; only vendor can withdraw

- **POST** `/proposals/{id}/accept`
  - Method: `ProposalService->accept()`
  - Side effects:
    - Creates order from request
    - Rejects all other proposals for this request
    - Deletes request

- **POST** `/proposals/{id}/reject`
  - Method: `ProposalService->reject()`
  - Auth: Request owner only
  - Side effect: Notifies vendor

- **POST** `/proposals/{id}/withdraw`
  - Method: `ProposalService->withdraw()`
  - Auth: Proposal vendor only

#### Proposal Metadata
- **GET** `/vendors/{vendor_id}/proposals/has-proposed`
  - Method: `ProposalService->vendor_has_proposed()`
  - Query param: `request_id`
  - Returns: `{ "has_proposed": bool, "proposal_id": number }`

---

## DELIVERIES & FILES

**Service**: `DeliveryService`  
**Purpose**: Submit order deliverables and manage file uploads

### Endpoints Needed

#### Delivery Submission
- **POST** `/orders/{order_id}/deliveries`
  - Method: `DeliveryService->submit()`
  - Body:
    ```json
    {
      "description": "what's being delivered",
      "files": ["file_ids"],
      "note": "optional note to buyer"
    }
    ```
  - Returns: Delivery object
  - Auth: Vendor only
  - Notification: Buyer notified of delivery

#### Delivery Retrieval
- **GET** `/orders/{order_id}/deliveries`
  - Method: `DeliveryService->get_order_deliveries()`
  - Returns: All deliveries for order (chronological)

#### Delivery Actions
- **POST** `/orders/{order_id}/deliveries/{delivery_id}/accept`
  - Method: `DeliveryService->accept()`
  - Returns: Updated delivery
  - Side effect: Marks delivery accepted, may complete order

- **POST** `/orders/{order_id}/deliveries/{delivery_id}/request-revision`
  - Method: `DeliveryService->request_revision()`
  - Body: `{ "reason": "what's wrong" }`
  - Returns: Updated delivery
  - Side effect: Order goes back to in_progress

#### File Operations
- **POST** `/deliveries/process-file`
  - Method: `DeliveryService->process_file()`
  - Multipart form: `file` + optional `file_type`
  - Returns: File object with ID, URL, type
  - Validation: Checks file type against allowed types

- **GET** `/deliveries/allowed-file-types`
  - Method: `DeliveryService->get_allowed_file_types()`
  - Returns:
    ```json
    {
      "images": ["jpg", "png", "gif", "webp"],
      "documents": ["pdf", "doc", "docx", "txt"],
      "video": ["mp4", "avi", "mov"],
      "audio": ["mp3", "wav", "flac"],
      "archives": ["zip", "rar", "7z"],
      "code": ["zip", "rar", "tar.gz"]
    }
    ```

---

## VENDOR MANAGEMENT

**Service**: `VendorService`  
**Purpose**: Vendor registration, profile management, and capability control

### Endpoints Needed

#### Vendor Registration
- **POST** `/vendors/register`
  - Method: `VendorService->register_vendor()`
  - Body:
    ```json
    {
      "display_name": "vendor name",
      "tagline": "short bio",
      "bio": "longer description",
      "country": "country name",
      "city": "city name"
    }
    ```
  - Returns: `{ "success": bool, "message": string }`
  - Side effects:
    - Adds `wpss_vendor` role
    - Creates vendor profile
    - Sets metadata: `_wpss_is_vendor`, `_wpss_vendor_since`
    - Triggers action: `wpss_vendor_registered`

- **GET** `/me/is-vendor`
  - Method: `VendorService->is_vendor()`
  - Returns: `{ "is_vendor": bool }`

#### Vendor Profile
- **GET** `/vendors/{vendor_id}/profile`
  - Method: `VendorService->get_profile()`
  - Returns: Profile object with stats

- **PUT** `/vendors/{vendor_id}/profile`
  - Method: `VendorService->update_profile()`
  - Body:
    ```json
    {
      "display_name": "string",
      "tagline": "string",
      "bio": "string",
      "avatar_id": "media_id",
      "cover_image_id": "media_id",
      "country": "string",
      "city": "string",
      "timezone": "string",
      "website": "url",
      "social_links": { "twitter": "url", "instagram": "url" }
    }
    ```
  - Allowed fields only (whitelist enforced)

#### Vendor Statistics
- **GET** `/vendors/{vendor_id}/stats`
  - Method: `VendorService->get_stats()`
  - Returns:
    ```json
    {
      "total_orders": number,
      "completed_orders": number,
      "active_orders": number,
      "review_count": number,
      "avg_rating": number,
      "response_time": "1 hour|2 days|etc",
      "repeat_customer_rate": number
    }
    ```

- **PATCH** `/vendors/{vendor_id}/stats`
  - Method: `VendorService->update_stats()`
  - Action: Recalculates all vendor stats
  - Auth: Admin or self

#### Vendor Availability
- **PATCH** `/vendors/{vendor_id}/vacation-mode`
  - Method: `VendorService->set_vacation_mode()`
  - Body: `{ "enabled": bool, "message": "back in 2 weeks" }`
  - Side effect: Pauses service orders

- **PATCH** `/vendors/{vendor_id}/availability`
  - Method: `VendorService->set_availability()`
  - Body: `{ "available": bool }`

#### Vendor Verification Tier
- **GET** `/vendors/{vendor_id}/tier`
  - Returns: Current tier (basic|verified|pro)

- **PATCH** `/vendors/{vendor_id}/tier`
  - Method: `VendorService->update_verification_tier()`
  - Body: `{ "tier": "basic|verified|pro" }`
  - Auth: Admin only
  - Side effect: Triggers action: `wpss_vendor_tier_changed`

#### Vendor Activity
- **POST** `/vendors/{vendor_id}/activity`
  - Method: `VendorService->update_last_active()`
  - Action: Updates `_wpss_last_active` timestamp
  - Called on each page view

- **GET** `/vendors/{vendor_id}/is-online`
  - Method: `VendorService->is_online()`
  - Query param: `minutes` (default 5)
  - Returns: `{ "online": bool }`

#### Vendor Discovery
- **GET** `/vendors/top-rated`
  - Method: `VendorService->get_top_vendors()`
  - Query param: `limit` (default 10)
  - Returns: Top-rated vendors

- **GET** `/vendors/search`
  - Method: `VendorService->search()`
  - Query params: `search`, `limit`, `offset`
  - Returns: Vendors matching search

- **GET** `/vendors/by-country`
  - Method: `VendorService->get_by_country()`
  - Query params: `country`, `limit`, `offset`
  - Returns: Vendors in country

- **GET** `/vendors/countries`
  - Method: `VendorService->get_countries()`
  - Returns: `["USA", "Canada", "UK", ...]`

#### Vendor Tiers Metadata
- **GET** `/vendors/tiers`
  - Method: `VendorService->get_tiers()`
  - Returns: `{ "basic": "Basic", "verified": "Verified", "pro": "Pro" }`

---

## PORTFOLIO & WORK SAMPLES

**Service**: `PortfolioService`  
**Purpose**: Display vendor's previous work and portfolio items

### Endpoints Needed

#### Portfolio Item CRUD
- **GET** `/vendors/{vendor_id}/portfolio`
  - Method: `PortfolioService->get_by_vendor()`
  - Query params: limit, offset
  - Returns: Vendor's portfolio items (paginated)

- **GET** `/portfolio/{id}`
  - Method: `PortfolioService->get()`
  - Returns: Single portfolio item

- **POST** `/vendors/{vendor_id}/portfolio`
  - Method: `PortfolioService->create()`
  - Body:
    ```json
    {
      "title": "project name",
      "description": "what was done",
      "images": ["media_ids"],
      "link": "project url",
      "featured": bool
    }
    ```
  - Auth: Must be vendor
  - Returns: Portfolio item object

- **PUT** `/portfolio/{id}`
  - Method: `PortfolioService->update()`
  - Can update: title, description, images, link

- **DELETE** `/portfolio/{id}`
  - Method: `PortfolioService->delete()`
  - Auth: Vendor or admin

#### Portfolio Management
- **GET** `/vendors/{vendor_id}/portfolio/featured`
  - Method: `PortfolioService->get_featured()`
  - Returns: Featured portfolio items only

- **PATCH** `/portfolio/{id}/toggle-featured`
  - Method: `PortfolioService->toggle_featured()`
  - Returns: Updated portfolio item

- **POST** `/vendors/{vendor_id}/portfolio/reorder`
  - Method: `PortfolioService->reorder()`
  - Body: `{ "order": [id1, id2, id3, ...] }`
  - Action: Updates display order

#### Portfolio Statistics
- **GET** `/vendors/{vendor_id}/portfolio/count`
  - Method: `PortfolioService->get_count()`
  - Returns: `{ "total": number, "featured": number }`

- **GET** `/services/{service_id}/portfolio`
  - Method: `PortfolioService->get_by_service()`
  - Returns: Portfolio items linked to service

---

## EARNINGS & PAYOUTS

**Service**: `EarningsService`  
**Purpose**: Track vendor earnings and manage withdrawal requests

### Endpoints Needed

#### Earnings Summary
- **GET** `/vendors/{vendor_id}/earnings/summary`
  - Method: `EarningsService->get_summary()`
  - Returns:
    ```json
    {
      "total_earned": number,
      "pending": number,
      "available": number,
      "withdrawn": number,
      "on_hold": number
    }
    ```

- **GET** `/vendors/{vendor_id}/earnings/history`
  - Method: `EarningsService->get_history()`
  - Query params: limit, offset
  - Returns: Chronological earning transactions

- **GET** `/vendors/{vendor_id}/earnings/by-period`
  - Method: `EarningsService->get_by_period()`
  - Query params: `start_date`, `end_date`, `period` (day|week|month|year)
  - Returns: Earnings grouped by period

#### Withdrawal System
- **POST** `/vendors/{vendor_id}/withdrawals`
  - Method: `EarningsService->request_withdrawal()`
  - Body:
    ```json
    {
      "amount": number,
      "method": "bank_transfer|paypal|etc",
      "notes": "optional"
    }
    ```
  - Validation:
    - Amount >= minimum withdrawal (default $100)
    - Amount <= available balance
  - Returns: Withdrawal request object
  - Status: `pending` → `approved` → `completed`

- **GET** `/vendors/{vendor_id}/withdrawals`
  - Method: `EarningsService->get_withdrawals()`
  - Query params: status, limit, offset
  - Returns: Vendor's withdrawal history

- **GET** `/withdrawals`
  - Method: `EarningsService->get_all_withdrawals()`
  - Auth: Admin only
  - Returns: All withdrawal requests

#### Withdrawal Administration
- **PATCH** `/withdrawals/{id}/status`
  - Method: `EarningsService->process_withdrawal()`
  - Body: `{ "status": "approved|completed|rejected" }`
  - Auth: Admin only

#### Withdrawal Methods & Configuration
- **GET** `/withdrawals/methods`
  - Method: `EarningsService->get_withdrawal_methods()`
  - Returns: `["bank_transfer", "paypal", ...]`

- **GET** `/withdrawals/statuses`
  - Method: `EarningsService->get_withdrawal_statuses()`
  - Returns: `["pending", "approved", "completed", "rejected"]`

- **GET** `/withdrawals/min-amount`
  - Method: `EarningsService->get_min_withdrawal_amount()`
  - Returns: `{ "amount": number, "currency": "USD" }`

#### Auto-Withdrawal System
- **GET** `/vendors/{vendor_id}/auto-withdrawal`
  - Methods:
    - `EarningsService->is_auto_withdrawal_enabled()`
    - `EarningsService->get_auto_withdrawal_threshold()`
    - `EarningsService->get_auto_withdrawal_schedule()`
  - Returns:
    ```json
    {
      "enabled": bool,
      "threshold": number,
      "schedule": "weekly|monthly|etc"
    }
    ```

- **PATCH** `/vendors/{vendor_id}/auto-withdrawal`
  - Methods: `EarningsService->create_auto_withdrawal()`, `schedule_auto_withdrawal_cron()`
  - Body:
    ```json
    {
      "enabled": bool,
      "threshold": number,
      "schedule": "weekly|monthly"
    }
    ```

---

## COMMISSIONS & REVENUE

**Service**: `CommissionService`  
**Purpose**: Calculate and track commission splits, revenue distribution

### Endpoints Needed

#### Commission Rates
- **GET** `/commissions/rates`
  - Methods:
    - `CommissionService->get_global_commission_rate()`
    - `CommissionService->get_vendor_commission_rate()`
  - Query param: `vendor_id` (optional, for vendor-specific rate)
  - Returns:
    ```json
    {
      "global_rate": 20,
      "vendor_rate": 15,
      "effective_rate": 15
    }
    ```

- **PATCH** `/commissions/rates/{vendor_id}`
  - Method: `CommissionService->set_vendor_commission_rate()`
  - Body: `{ "rate": number }`
  - Auth: Admin only
  - Returns: Updated rate

#### Commission Calculations
- **POST** `/commissions/calculate`
  - Method: `CommissionService->calculate()`
  - Body: `{ "order_id": number }`
  - Returns:
    ```json
    {
      "order_total": 500,
      "commission_amount": 100,
      "vendor_payout": 400,
      "rate": 20
    }
    ```

- **GET** `/orders/{order_id}/commission`
  - Method: `CommissionService->get_order_commission()`
  - Returns: Commission details for this order

#### Commission Recording
- **POST** `/commissions/record`
  - Method: `CommissionService->record()`
  - Body: 
    ```json
    {
      "order_id": number,
      "commission_amount": number
    }
    ```
  - Auth: System/admin only
  - Side effects: Creates earnings transaction

#### Vendor Commission Summary
- **GET** `/vendors/{vendor_id}/commission-summary`
  - Method: `CommissionService->get_vendor_summary()`
  - Returns:
    ```json
    {
      "total_orders": number,
      "total_revenue": number,
      "total_commissions": number,
      "total_payouts": number,
      "active_commissions": number
    }
    ```

#### Platform Commission Summary
- **GET** `/commissions/summary`
  - Method: `CommissionService->get_platform_totals()`
  - Auth: Admin only
  - Returns:
    ```json
    {
      "total_orders": number,
      "total_revenue": number,
      "total_commissions": number,
      "pending_payouts": number
    }
    ```

#### Commission History
- **GET** `/commissions/history`
  - Body: `{ "order_id": number }`
  - Method: `CommissionService->create_earnings_transaction()`
  - Returns: All earnings transactions for period

---

## NOTIFICATIONS

**Service**: `NotificationService`  
**Purpose**: Send and manage in-app and email notifications

### Endpoints Needed

#### Notification Retrieval
- **GET** `/me/notifications`
  - Method: `NotificationService->get_user_notifications()`
  - Query params: limit, offset, status (read|unread|all)
  - Returns: User's notifications (paginated)

- **GET** `/me/notifications/unread-count`
  - Method: `NotificationService->get_unread_count()`
  - Returns: `{ "count": number }`

- **GET** `/notifications/{id}`
  - Returns: Single notification details

#### Notification Actions
- **PATCH** `/notifications/{id}/read`
  - Method: `NotificationService->mark_as_read()`
  - Returns: Updated notification

- **PATCH** `/me/notifications/read-all`
  - Method: `NotificationService->mark_all_as_read()`
  - Returns: Count of marked notifications

- **DELETE** `/notifications/{id}`
  - Method: `NotificationService->delete()`

#### Notification Types

The system triggers these notifications automatically:

| Type | Trigger | Method |
|------|---------|--------|
| `order_created` | New order placed | `notify_order_created()` |
| `order_status` | Order status changes | `notify_order_status()` |
| `new_message` | Message received | `notify_new_message()` |
| `delivery_submitted` | Vendor submits delivery | Auto-triggered |
| `delivery_accepted` | Buyer accepts delivery | Auto-triggered |
| `revision_requested` | Buyer requests revision | Auto-triggered |
| `review_received` | Review posted on service | `notify_review_received()` |
| `dispute_opened` | Dispute created | Auto-triggered |
| `dispute_resolved` | Dispute resolved | `notify_dispute_resolved()` |
| `deadline_warning` | Delivery deadline approaching | Auto-triggered |
| `vendor_registered` | New vendor registered | `notify_vendor_registered()` |

**Creating Notifications (System Use)**:
- **POST** `/notifications` (system endpoint, not user-facing)
  - Method: `NotificationService->create()`
  - Body:
    ```json
    {
      "user_id": number,
      "type": "order_created|order_status|...",
      "title": "Order Received",
      "message": "New order #123",
      "meta": { "order_id": 123, "service_id": 456 }
    }
    ```

---

## SEARCH & DISCOVERY

**Service**: `SearchService`  
**Purpose**: Unified search across services, vendors, and buyer requests

### Endpoints Needed

#### Unified Search
- **GET** `/search`
  - Method: `SearchService->search()`
  - Query params:
    - `q` (search query)
    - `type` (service|vendor|request|all, default: all)
    - `category` (optional)
    - `min_price`, `max_price` (for services)
    - `sort` (relevance|newest|popular|rating|price_low|price_high)
    - `limit`, `offset`
  - Returns:
    ```json
    {
      "services": [...],
      "vendors": [...],
      "requests": [...]
    }
    ```

#### Service Search
- **GET** `/services/search`
  - Method: `SearchService->search_services()`
  - Query params: `q`, `category`, `min_price`, `max_price`, `sort`, `limit`, `offset`
  - Returns: Service results (paginated)

- **GET** `/services/count`
  - Method: `SearchService->count_services()`
  - Query param: `q`
  - Returns: `{ "count": number }`

#### Vendor Search
- **GET** `/vendors/search`
  - Method: `SearchService->search_vendors()`
  - Query params: `q`, `country`, `limit`, `offset`
  - Returns: Vendor results (paginated)

- **GET** `/vendors/count`
  - Method: `SearchService->count_vendors()`
  - Query param: `q`
  - Returns: `{ "count": number }`

#### Request Search
- **GET** `/buyer-requests/search`
  - Method: `SearchService->search_requests()`
  - Query params: `q`, `category`, `min_budget`, `max_budget`, `limit`, `offset`
  - Returns: Request results (paginated)

- **GET** `/buyer-requests/count`
  - Method: `SearchService->count_requests()`
  - Query param: `q`
  - Returns: `{ "count": number }`

#### Search Discovery
- **GET** `/search/suggestions`
  - Method: `SearchService->get_suggestions()`
  - Query param: `q` (partial query)
  - Returns: `["autocomplete", "suggestions", ...]`

- **GET** `/search/popular`
  - Method: `SearchService->get_popular_searches()`
  - Returns: Trending search terms

- **GET** `/services/{id}/related`
  - Method: `SearchService->get_related_services()`
  - Returns: Similar services

#### Search Analytics
- **POST** `/search/track`
  - Method: `SearchService->track_search()`
  - Body: `{ "query": string, "type": "service|vendor|request" }`
  - Action: Logs search for analytics

---

## MODERATION & APPROVALS

**Service**: `ModerationService`  
**Purpose**: Admin service approval workflow

### Endpoints Needed

#### Moderation Status
- **GET** `/admin/moderation/status`
  - Method: `ModerationService->is_enabled()`
  - Returns: `{ "enabled": bool }`

#### Pending Services
- **GET** `/admin/moderation/services`
  - Method: `ModerationService->get_pending_services()`
  - Query params: limit, offset
  - Returns: Services awaiting approval (paginated)

- **GET** `/admin/moderation/services/count`
  - Method: `ModerationService->get_pending_count()`
  - Returns: `{ "count": number }`

- **GET** `/admin/moderation/services/{id}`
  - Method: `ModerationService->get_moderation_data()`
  - Returns: Service with moderation details

#### Moderation Actions
- **POST** `/admin/moderation/services/{id}/approve`
  - Method: `ModerationService->approve()`
  - Returns: Updated service (status: approved)
  - Notification: Vendor notified

- **POST** `/admin/moderation/services/{id}/reject`
  - Method: `ModerationService->reject()`
  - Body: `{ "reason": "why rejected" }`
  - Returns: Updated service (status: rejected)
  - Notification: Vendor notified with reason

#### Service Status Control
- **PATCH** `/admin/services/{id}/moderation-status`
  - Method: `ModerationService->set_pending()`
  - Body: `{ "pending": bool }`
  - Auth: Admin only

---

## EXTENSIONS & MILESTONES

**Service**: `ExtensionRequestService`, `MilestoneService`  
**Purpose**: Manage deadline extensions and milestone-based delivery

### Extension Requests - Endpoints Needed

#### Extension Request CRUD
- **POST** `/orders/{order_id}/extension`
  - Method: `ExtensionRequestService->create()`
  - Body:
    ```json
    {
      "days": number,
      "reason": "why extension needed"
    }
    ```
  - Returns: Extension request object
  - Auth: Vendor only

- **GET** `/orders/{order_id}/extension`
  - Method: `ExtensionRequestService->get_by_order()`
  - Returns: Pending extension request (or null)

- **GET** `/extensions/pending`
  - Method: `ExtensionRequestService->get_pending()`
  - Returns: All pending extension requests
  - Auth: Admin/buyer

#### Extension Actions
- **POST** `/extensions/{id}/approve`
  - Method: `ExtensionRequestService->approve()`
  - Returns: Updated extension (approved)
  - Side effect: Updates order deadline

- **POST** `/extensions/{id}/reject`
  - Method: `ExtensionRequestService->reject()`
  - Returns: Updated extension (rejected)

**Extension Statuses**:
- `pending` - Awaiting approval
- `approved` - Deadline extended
- `rejected` - Denied

---

### Milestones - Endpoints Needed

#### Milestone Creation
- **POST** `/orders/{order_id}/milestones`
  - Method: `MilestoneService->create()`
  - Body:
    ```json
    {
      "title": "Milestone name",
      "description": "what's included",
      "amount": number,
      "delivery_days": number,
      "order": number
    }
    ```
  - Returns: Milestone object
  - Auth: Admin/order creator

#### Milestone Retrieval
- **GET** `/orders/{order_id}/milestones`
  - Method: `MilestoneService->get_order_milestones()`
  - Returns: All milestones for order (in order)

- **GET** `/milestones/{id}`
  - Method: `MilestoneService->get()`
  - Returns: Single milestone

#### Milestone Updates
- **PUT** `/milestones/{id}`
  - Method: `MilestoneService->update()`
  - Can update: title, description, amount, delivery_days

- **DELETE** `/milestones/{id}`
  - Method: `MilestoneService->delete()`
  - Auth: Admin only

#### Milestone Workflow
- **POST** `/milestones/{id}/submit`
  - Method: `MilestoneService->submit()`
  - Body: `{ "deliverables": "description", "files": ["file_ids"] }`
  - Auth: Vendor
  - Status: `pending` → `submitted`

- **POST** `/milestones/{id}/approve`
  - Method: `MilestoneService->approve()`
  - Returns: Updated milestone
  - Status: `submitted` → `approved`
  - Triggers: Release of milestone payment

- **POST** `/milestones/{id}/reject`
  - Method: `MilestoneService->reject()`
  - Body: `{ "reason": "why rejected" }`
  - Status: `submitted` → `rejected`
  - Triggers: Vendor can re-submit

- **GET** `/milestones/{id}/progress`
  - Method: `MilestoneService->get_progress()`
  - Returns: Progress percentage and status

**Milestone Statuses**:
- `pending` - Not yet started
- `in_progress` - Vendor working
- `submitted` - Waiting for approval
- `approved` - Buyer accepted
- `rejected` - Needs revision

---

## TIPPING

**Service**: `TippingService`  
**Purpose**: Allow customers to tip vendors after order completion

### Endpoints Needed

#### Tip Submission
- **POST** `/orders/{order_id}/tip`
  - Method: `TippingService->tip()`
  - Body:
    ```json
    {
      "amount": number,
      "message": "optional thank you message"
    }
    ```
  - Auth: Buyer only
  - Validation: Order must be completed
  - Returns: Tip object

#### Tip Retrieval
- **GET** `/orders/{order_id}/tip`
  - Method: `TippingService->get_order_tip()`
  - Returns: Tip details (or null)

- **GET** `/vendors/{vendor_id}/tips`
  - Method: `TippingService->get_vendor_tips()`
  - Query params: limit, offset
  - Returns: All tips for vendor

- **GET** `/vendors/{vendor_id}/tips/total`
  - Method: `TippingService->get_vendor_tips_total()`
  - Returns: `{ "total_tips": number, "tip_count": number }`

#### Tip Metadata
- **GET** `/orders/{order_id}/can-tip`
  - Method: `TippingService->has_tipped()`
  - Returns: `{ "has_tipped": bool }`

**Tip Statuses**:
- `pending` - Awaiting processing
- `completed` - Sent to vendor
- `refunded` - Refund issued (if applicable)

---

## Summary: API Endpoint Categories

| Category | Count | Key Endpoints |
|----------|-------|--------------|
| Services | 12 | CRUD, packages, FAQs, ratings |
| Orders | 7 | Status transitions, revisions, cancellation |
| Messages | 8 | Conversations, messages, read status |
| Reviews | 7 | Submit, respond, retrieve, ratings |
| Disputes | 7 | Open, evidence, resolution, statistics |
| Buyer Requests | 6 | Request CRUD, proposals, hiring |
| Deliveries | 5 | Submit, accept, revision, files |
| Vendors | 13 | Register, profile, stats, discovery |
| Portfolio | 6 | CRUD, featured, reorder |
| Earnings | 8 | Summary, history, withdrawals, auto-pay |
| Commissions | 6 | Rates, calculations, summaries |
| Notifications | 5 | Retrieve, mark read, types |
| Search | 7 | Unified search, suggestions, analytics |
| Moderation | 5 | Pending queue, approve/reject |
| Extensions | 3 | Request, approve/reject |
| Milestones | 8 | CRUD, workflow, progress |
| Tipping | 4 | Submit, retrieve, statistics |

**Total: ~127 REST API endpoints** needed to fully support a mobile application

---

## Notes for API Implementation

### Authentication
- All endpoints require user authentication (JWT or OAuth2 recommended)
- Some endpoints admin-only (marked with "Auth: Admin only")
- Some endpoints role-specific (vendor-only, buyer-only)

### Authorization
- Users can only access their own data by default
- Vendors can access vendor-specific endpoints
- Buyers can access buyer-specific endpoints
- Admins can access all endpoints

### Pagination
- Use `limit` and `offset` for list endpoints
- Recommended default: `limit=20`
- Maximum recommended: `limit=100`

### Response Format
- All responses should use JSON
- Success responses: `{ "data": {...}, "meta": {...} }`
- Error responses: `{ "error": "message", "code": "ERROR_CODE" }`

### Real-time Considerations
- Notifications should support WebSocket or Server-Sent Events
- Messages in conversations need real-time delivery
- Order status changes should be pushed to clients

### Rate Limiting
- Recommend rate limiting per user (e.g., 100 requests/minute)
- Search endpoints may need higher limits
- File upload endpoints may need special handling

---

## Pro Plugin Extensions

The pro plugin adds these additional services/features:

- **WalletManager**: Digital wallet, prepaid credits, balance management
- **AnalyticsManager**: Advanced analytics, custom reports, export
- **DataExporter**: Export user data, orders, earnings
- **Storage Providers**: Cloud storage integration (S3, GCS)
- **Payment Gateways**: Stripe, PayPal, Razorpay, direct payment processing
- **Advanced Notifications**: Scheduled notifications, templates, marketing

These would require additional API endpoints following similar patterns.

---

## Implementation Priority

**Phase 1 (Core Mobile App)**:
1. Services (CRUD, search)
2. Orders (lifecycle, status)
3. Vendors (profile, discovery)
4. Conversations (messaging)
5. Authentication

**Phase 2 (Enhanced Experience)**:
6. Deliveries (file submission)
7. Reviews (ratings)
8. Proposals (for requests)
9. Notifications (in-app, real-time)
10. Search (advanced search)

**Phase 3 (Complete Platform)**:
11. Disputes (resolution)
12. Earnings (withdrawals)
13. Extensions (deadline requests)
14. Milestones (project-based delivery)
15. Tipping (rewards)

---
