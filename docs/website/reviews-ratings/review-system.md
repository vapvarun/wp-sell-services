# Review System & Star Ratings

## Overview

The review system creates marketplace trust by allowing buyers to rate completed orders. Reviews help buyers make informed decisions and vendors build their reputation.

**Key Facts:**
- Only buyers can leave reviews (customer-to-vendor only)
- Reviews require a completed, verified order
- One review per order
- Overall rating (1-5 stars) plus 3 sub-ratings
- Vendors can reply to reviews
- Reviews can be edited via API

---

## Rating System Structure

### Overall Rating

Every review includes an overall rating from 1 to 5 stars.

**Star Scale:**
- **5 stars** - Excellent, exceeded expectations
- **4 stars** - Good, met expectations
- **3 stars** - Satisfactory, acceptable quality
- **2 stars** - Below expectations, issues present
- **1 star** - Poor, major problems

### Sub-Ratings

In addition to the overall rating, buyers can provide 3 optional sub-ratings (1-5 stars each):

| Sub-Rating | What It Measures |
|------------|------------------|
| **Communication** | Response time, clarity, professionalism |
| **Quality** | Work quality, attention to detail, craftsmanship |
| **Value** | Price vs. quality received, worth the cost |

**Note:** The old documentation incorrectly listed 4 sub-ratings including "experience". Only these 3 exist in the code.

![Review form with sub-ratings](../images/frontend-review-form.png)

---

## Review Eligibility

### Who Can Leave Reviews

**Requirements:**
- ✓ Order status is "Completed"
- ✓ You are the customer on the order
- ✓ Order has not been reviewed yet
- ✓ Within the review time window (see below)

**Cannot Review:**
- ✗ Cancelled orders
- ✗ Orders you've already reviewed
- ✗ Orders outside the review window
- ✗ Orders where you are the vendor

### Review Time Window

Reviews must be submitted within a configurable time period after order completion.

**Default:** 30 days after order completion

**Administrators can configure this in:**
- Settings → General → Review Window Days
- Set to `0` for unlimited time to review

**How It Works:**
1. Order marked "Completed" on January 1
2. Review window: 30 days (default)
3. Deadline: January 31
4. After January 31: Cannot leave review

**Important:** Previous documentation incorrectly stated different time windows for different user levels. There is only ONE configurable window that applies to all users equally.

---

## Leaving a Review

### Step 1: Access Review Form

**From Order Page:**
1. Go to **Dashboard → My Orders**
2. Click the completed order
3. Click **Leave Review** button
4. Review form opens

**From Email Reminder:**
1. Check your email for "Review Your Order" notification
2. Click **Leave Review** link
3. Log in if prompted
4. Review form opens

### Step 2: Complete the Review

**Overall Rating (Required):**
- Select 1-5 stars
- This is your primary rating

**Sub-Ratings (Optional):**
- Communication: How well vendor communicated
- Quality: Work quality and completeness
- Value: Was it worth the price?

**Written Review (Required):**
- Minimum: 10 characters
- No maximum character limit enforced
- Be specific and constructive
- Focus on facts, not emotions

**Good Review Example:**
```
The WordPress plugin works exactly as specified. John delivered
2 days early and responded to messages within hours. Code is
clean and well-documented. Very satisfied with the quality for
the price paid.
```

**Screenshot Reference:** `../images/frontend-review-form-complete.png`

### Step 3: Submit Review

Click **Submit Review** button.

**What Happens Next:**

If **auto-approval enabled** (default):
- Review published immediately
- Vendor notified
- Rating updates vendor/service averages

If **moderation required**:
- Review status: "Pending"
- Admin must approve
- Vendor notified after approval

---

## Editing Reviews

**Important:** The old documentation incorrectly stated reviews cannot be edited. They CAN be edited via the REST API.

### Via REST API

Customers can update their reviews (rating and text) using:

```
PATCH /wp-json/wpss/v1/reviews/{review_id}
```

**Allowed Updates:**
- Rating (1-5)
- Review text content

**Not Allowed:**
- Changing order ID
- Changing vendor
- Status changes (admins only)

**Frontend Implementation:**
Most themes don't include an edit UI, but developers can add it using the API endpoint.

---

## Vendor Replies

Vendors can respond to reviews on their services.

### How to Reply

1. Go to **Dashboard → Reviews**
2. Find the review
3. Click **Reply**
4. Type response
5. Click **Submit Reply**

**Reply Rules:**
- One reply per review
- Cannot edit once submitted
- Cannot delete (admins can)
- Public and visible to all buyers

**Reply Best Practices:**
- Thank the buyer
- Address any concerns raised
- Be professional and courteous
- Keep it brief and genuine

**Good Reply Example:**
```
Thank you for the kind words! It was a pleasure working on your
WordPress project. I'm glad the plugin meets your needs. Feel
free to reach out anytime if you need future updates.
```

![Vendor reply interface](../images/frontend-vendor-reply.png)

---

## Review Moderation

Admins can optionally enable review moderation.

### Moderation Setting

**Location:** Settings → General → Review Moderation

**Options:**
- **Auto-Approve** (Default) - Reviews publish immediately
- **Require Moderation** - Reviews need admin approval

**Note:** The old documentation described a complex keyword detection and flagging system. This does NOT exist in the code. Moderation is a simple on/off toggle.

### Admin Review Management

**Review Status Options:**
- `pending` - Awaiting approval
- `approved` - Visible publicly
- `rejected` - Hidden from public

**Admin Actions:**
1. Go to **WP Admin → WP Sell Services → Reviews**
2. View pending reviews
3. Click **Approve** or **Reject**
4. Optional: Add admin note

**Screenshot Reference:** `../images/admin-reviews-list.png`

---

## How Ratings Are Calculated

### Service Rating

Average of all approved reviews for that service.

**Formula:**
```
Service Rating = Sum of all ratings ÷ Number of reviews

Example Service (5 reviews):
5 + 4 + 5 + 5 + 4 = 23
23 ÷ 5 = 4.6 stars
```

**Display:**
- Service card in search results
- Service detail page
- Vendor profile (per-service breakdown)

### Vendor Rating

Average of all approved reviews across ALL the vendor's services.

**Formula:**
```
Vendor Rating = Sum of all ratings across all services ÷ Total reviews

Example Vendor (3 services):
Service A: 4.8 stars (10 reviews)
Service B: 4.6 stars (5 reviews)
Service C: 4.9 stars (3 reviews)

Total: (48 + 23 + 14.7) ÷ 18 = 4.76 stars
```

**Display:**
- Vendor profile page
- Vendor card in search
- Proposal submissions

### Rating Updates

Ratings recalculate automatically when:
- New review is approved
- Review is edited
- Review is deleted
- Review status changes to approved/rejected

---

## Verified Purchase Badge

All reviews in WP Sell Services are verified purchases by default.

**Badge Appears When:**
- Review is from a completed order
- Payment was processed
- Delivery was confirmed

**Badge Display:**
- Next to reviewer name
- "Verified Purchase" or checkmark icon

**Note:** The `is_verified` field in the database actually maps to `is_public` in the code. All reviews from the system are inherently verified since they require a completed order.

---

## Review Display

### Where Reviews Appear

**Service Page:**
- All approved reviews for that service
- Sorted by most recent first
- Pagination: 10 reviews per page

**Vendor Profile:**
- All approved reviews for vendor
- Combined from all services
- Sortable by date/rating

**Search Results:**
- Star rating and review count on service cards
- "Based on X reviews" text

### Review Sorting Options

**Available Sort Orders:**
- Most Recent (default)
- Highest Rated First
- Lowest Rated First
- Most Helpful (if helpful votes enabled)

**Screenshot Reference:** `../images/frontend-service-reviews.png`

---

## Review Impact

### On Vendors

**Positive Reviews:**
- Increased visibility in search
- Higher conversion rates
- Qualify for seller level advancement
- Build marketplace reputation

**Negative Reviews:**
- Lower search ranking
- May impact seller level
- Require improvement action
- Can be addressed with replies

**Seller Level Requirements:**
- New Seller: No requirement
- Level 1: 4.0+ average, 10+ reviews
- Level 2: 4.5+ average, 50+ reviews
- Top Seller: 4.8+ average, 100+ reviews

### On Services

**Rating Thresholds:**
- **4.5+** - Featured in search results
- **4.0-4.4** - Normal ranking
- **Below 4.0** - Lower visibility
- **Below 3.5** - May be hidden from search

---

## REST API Endpoints

### Get Reviews

```
GET /wp-json/wpss/v1/reviews
```

**Query Parameters:**
- `service_id` - Filter by service
- `vendor_id` - Filter by vendor
- `rating` - Filter by star rating (1-5)
- `page` - Page number
- `per_page` - Items per page (max 100)

### Create Review

```
POST /wp-json/wpss/v1/orders/{order_id}/review
```

**Required Fields:**
- `rating` - Overall rating (1-5)
- `review` - Review text

**Optional Fields:**
- `rating_communication` - Communication sub-rating
- `rating_quality` - Quality sub-rating
- `rating_value` - Value sub-rating

### Update Review

```
PATCH /wp-json/wpss/v1/reviews/{review_id}
```

**Allowed Updates:**
- `rating` - New overall rating
- `review` - New review text

**Permissions:**
- Customer can edit their own review
- Admin can edit any review and change status

### Add Vendor Reply

```
POST /wp-json/wpss/v1/reviews/{review_id}/reply
```

**Required:**
- `reply` - Reply text

**Permissions:**
- Only vendor who received the review can reply
- One reply per review

### Get Review Summary

**Service Summary:**
```
GET /wp-json/wpss/v1/services/{service_id}/reviews/summary
```

**Vendor Summary:**
```
GET /wp-json/wpss/v1/vendors/{vendor_id}/reviews/summary
```

**Response Includes:**
- Total review count
- Average rating
- Rating breakdown by stars (5, 4, 3, 2, 1)
- Percentages for each star level

---

## Notifications

### Buyer Notifications

**Review Reminder Email:**
- Sent 3 days after order completion
- Includes direct review link
- Sent only if no review submitted

**Review Status Updates:**
- "Your review was published" (if moderation enabled)

### Vendor Notifications

**New Review Received:**
- Email notification
- In-app notification
- Includes order details and rating

**Review Reply Notification:**
- Buyer notified when vendor replies
- Email + in-app notification

---

## Best Practices

### For Buyers

**Do:**
- Leave honest, constructive feedback
- Review within the time window
- Be specific about what you liked/disliked
- Update review if vendor fixes issues

**Don't:**
- Leave reviews just for revenge
- Include personal information in text
- Violate marketplace policies in review
- Demand extra work through review

### For Vendors

**Do:**
- Reply to all reviews professionally
- Address negative feedback constructively
- Thank buyers for positive reviews
- Learn from review patterns

**Don't:**
- Ask buyers to remove negative reviews
- Offer refunds for review changes
- Reply defensively or emotionally
- Report reviews just because they're negative

---

## Related Documentation

- [Reputation & Moderation](./reputation-moderation.md) - How reputation is calculated
- [Order Workflow](../order-management/order-workflow.md) - Complete order to enable reviews
- [Vendor Dashboard](../vendor-system/vendor-dashboard.md) - Managing reviews
- [Developer Guide](../developer-guide/rest-api.md) - Reviews API integration

---

## Troubleshooting

**Q: Why can't I leave a review?**
- Order must be completed (not "Delivered" - fully completed)
- Must be within review time window (default 30 days)
- Cannot review your own services

**Q: Can I edit my review after submitting?**
- Yes, via the REST API (`PATCH /reviews/{id}`)
- Frontend UI depends on theme implementation
- Contact support if you need help

**Q: Why is my review pending?**
- Admin enabled review moderation
- All new reviews require approval
- Check back in 24-48 hours

**Q: Can I delete my review?**
- No, reviews cannot be deleted by customers
- You can edit to clarify
- Admins can delete reviews if needed

**Q: Why don't I see sub-ratings on some reviews?**
- Sub-ratings (communication, quality, value) are optional
- Buyers can skip them
- Only overall rating is required
