# Vendor Reputation & Review Moderation

## Overview

Vendor reputation is built through verified reviews from completed orders. This guide explains how reputation is calculated, displayed, and managed through the review moderation system.

**Key Topics:**
- How reputation scores are calculated
- Rating impact on vendor visibility
- Review moderation settings
- Admin review management

---

## Reputation Calculation

### How Vendor Ratings Work

Vendor ratings are calculated as a simple average of all approved reviews across all of the vendor's services.

**Formula:**
```
Vendor Rating = Sum of all review ratings ÷ Total review count

Example:
Review 1: 5 stars
Review 2: 4 stars
Review 3: 5 stars
Review 4: 4 stars
Review 5: 5 stars

Vendor Rating = (5+4+5+4+5) ÷ 5 = 4.6 stars
```

**Important:** The old documentation described complex weighted calculations with recent reviews weighted higher. This does NOT exist in the code. All approved reviews are weighted equally.

### Service-Level Ratings

Each service has its own rating separate from the vendor's overall rating.

**Service Rating Formula:**
```
Service Rating = Sum of service's reviews ÷ Service review count

Example Vendor with 3 Services:
├─ WordPress Plugin: 4.8 stars (20 reviews)
├─ Theme Setup: 4.5 stars (10 reviews)
└─ Site Migration: 4.9 stars (8 reviews)

Vendor Overall: (4.8×20 + 4.5×10 + 4.9×8) ÷ 38 = 4.71 stars
```

**Display Locations:**
- Service cards show service-specific rating
- Vendor profile shows overall rating
- Each service page shows that service's rating


---

## Rating Components

### Overall Rating Only

Each review has ONE primary rating from 1-5 stars.

**Sub-Ratings Are Optional:**
- Communication (1-5 stars)
- Quality (1-5 stars)
- Value (1-5 stars)

**Important:** Sub-ratings do NOT affect the overall vendor or service rating. They are display-only for buyer information.

**Calculation:**
```
Service/Vendor Rating = Average of overall ratings only
Sub-ratings = Displayed separately, not included in average
```

**Note:** The old documentation described sub-ratings as weighted components of the overall score. This is incorrect - only the overall rating counts.

---

## Review Count Requirements

### Minimum Reviews for Display

**Service Ratings:**
- Displayed immediately after first review
- Shows "Based on 1 review" until more accumulate
- No minimum required

**Vendor Profile Rating:**
- Displays after first approved review
- Combined from all services
- Updates in real-time when reviews approved

### Seller Level Requirements

**Rating + Review Count Required:**

| Seller Level | Min Rating | Min Reviews | Additional Requirements |
|--------------|------------|-------------|------------------------|
| **New Seller** | None | None | Account verified |
| **Level 1** | 4.0+ | 10+ | 90% completion rate |
| **Level 2** | 4.5+ | 50+ | 95% completion rate |
| **Top Seller** | 4.8+ | 100+ | <2% cancellation rate |

**Note:** Exact thresholds may vary by marketplace configuration.

---

## Review Moderation System

### Moderation Setting

**Location:** WP Admin → Settings → General → "Moderate Reviews"

**Options:**
- **Disabled** (Default) - Reviews publish immediately
- **Enabled** - Reviews require admin approval

**When to Enable Moderation:**
- New marketplace (quality control)
- History of spam reviews
- Legal/compliance requirements
- Manual quality assurance needed

**When to Disable:**
- Established marketplace
- Trust your user base
- Want instant feedback
- Faster review velocity

**Important:** The old documentation described automated keyword detection, flagging systems, and complex moderation rules. These do NOT exist. Moderation is a simple boolean on/off setting.

### How Moderation Works

**With Moderation Disabled (Default):**
1. Buyer submits review
2. Status: `approved`
3. Review appears immediately
4. Vendor notified instantly
5. Ratings update in real-time

**With Moderation Enabled:**
1. Buyer submits review
2. Status: `pending`
3. Review hidden from public
4. Admin notified of pending review
5. Admin approves/rejects manually
6. If approved → Status: `approved`, visible publicly
7. If rejected → Status: `rejected`, hidden permanently


---

## Admin Review Management

### Viewing Reviews

**Navigate to:**
WP Admin → WP Sell Services → Reviews

**Filter Options:**
- All reviews
- Pending (awaiting approval)
- Approved (publicly visible)
- Rejected (hidden)

**Sort Options:**
- Date (newest/oldest)
- Rating (high/low)
- Service
- Vendor

### Approving Reviews

**Steps:**
1. Go to **Reviews** page
2. Find pending review
3. Read review content
4. Click **Approve**
5. Review published instantly
6. Vendor notified
7. Rating updates applied

**Bulk Approve:**
1. Check multiple reviews
2. Select **Bulk Actions → Approve**
3. Click **Apply**


### Rejecting Reviews

**When to Reject:**
- Spam or meaningless content
- Personal attacks or harassment
- Contains prohibited content
- Violates marketplace policies
- Not related to the actual service

**Steps:**
1. Find the review
2. Click **Reject**
3. Optional: Add admin note (visible to admins only)
4. Review status: `rejected`
5. Review hidden from all public views

**Customer Notification:**
- Customers are NOT automatically notified of rejection
- Send manual email if explanation needed
- Rejection is permanent (customer cannot resubmit)

### Editing Reviews

Admins can edit review content if needed.

**Editable Fields:**
- Rating (1-5 stars)
- Review text
- Status (pending/approved/rejected)

**Cannot Edit:**
- Order ID
- Reviewer
- Vendor
- Submission date

**When to Edit:**
- Fix typos in constructive reviews
- Remove personally identifiable info
- Clean up formatting issues
- Rarely - editing should be exceptional

**Note:** The code allows editing via API. Frontend admin UI may vary by theme.

---

## Rating Impact

### On Search Visibility

**Service Search Ranking Factors:**
- Higher ratings rank higher
- Review count matters
- Recency of reviews considered

**Visibility Thresholds:**
- **4.7+** - Excellent visibility, featured placement
- **4.0-4.6** - Normal search visibility
- **3.5-3.9** - Reduced visibility
- **Below 3.5** - May be hidden from default search

### On Vendor Status

**Automatic Actions Based on Rating:**
- **4.8+** - Eligible for Top Seller badge
- **4.0-4.7** - Good standing
- **3.5-3.9** - Warning email sent
- **Below 3.5** - Account review triggered

**Admin Review Process:**
1. System flags vendors below threshold
2. Admin reviews recent orders
3. Vendor contacted for improvement plan
4. Account suspended if no improvement

**Note:** Specific thresholds are configurable by admin.

---

## Reputation Dashboard

### Vendor View

**Dashboard → My Reputation**

Vendors see:
- Overall rating (all services)
- Total review count
- Rating breakdown by service
- Recent reviews (last 10)
- Unanswered reviews count

**Available Actions:**
- Reply to reviews
- View detailed review history
- Filter by service
- Export review data


### Service-Specific Stats

**Dashboard → Services → [Service Name] → Reviews**

For each service:
- Service rating
- Review count for this service
- Star breakdown (5★, 4★, 3★, 2★, 1★)
- Individual reviews
- Reply to reviews option

---

## Character Limits

### Review Content

**No Server-Side Character Limits Enforced**

The code does NOT enforce any character limits on review text. HTML form may include client-side limits, but these can be bypassed.

**Best Practices (Recommended):**
- Minimum: 10-20 characters (meaningful feedback)
- Maximum: 2000 characters (readability)
- These are suggestions, not enforced

**Note:** The old documentation claimed strict 50-character minimum and 2000-character maximum. These limits do NOT exist in the ReviewService code.

### Vendor Replies

**No Character Limits**

Vendor replies also have no enforced character limits.

**Best Practices:**
- Keep replies concise (200-300 characters)
- Address specific points raised
- Professional tone

---

## Reputation Recovery

### Improving Low Ratings

**Steps to Recover:**
1. Identify issues from negative reviews
2. Contact affected customers
3. Offer solutions or refunds
4. Deliver exceptional service on new orders
5. Accumulate positive reviews over time

**How Long It Takes:**

```
Example: Vendor at 3.8 stars (20 reviews)
Goal: Reach 4.5 stars

Needed: ~30 consecutive 5-star reviews
Timeline: 3-6 months of consistent excellent service
```

**Rating Math:**
```
Current: 3.8 × 20 = 76 total stars
Add 30 reviews at 5.0: 76 + 150 = 226
New average: 226 ÷ 50 = 4.52 stars ✓
```

### Cannot Remove Old Reviews

**Important:**
- Past reviews remain permanently
- Cannot delete negative reviews (except policy violations)
- Only option: Dilute with new positive reviews
- Focus on improvement, not removal

---

## Review Guidelines

### What Reviews Should Include

**Constructive Reviews:**
- Specific examples of quality
- Timeliness feedback
- Communication experience
- Value assessment
- Honest pros and cons

**Example Good Review:**
```
The WordPress plugin works perfectly. John delivered 2 days
early and responded to questions within hours. Code is clean
and well-documented. Price was fair for the quality. Would
hire again for similar projects.

Rating: 5 stars
```

### Prohibited Review Content

Reviews violating these are rejected:

**Not Allowed:**
- Personal attacks or harassment
- Profanity or hate speech
- Spam or promotional content
- Unrelated to the service delivered
- Extortion attempts
- False statements
- Contact information or external links
- Competitor promotion

**Admin Action:**
- Review rejected immediately
- Buyer warned if severe
- Repeat violations = account review

---

## REST API Access

### Get Vendor Rating Summary

```
GET /wp-json/wpss/v1/vendors/{vendor_id}/reviews/summary
```

**Response:**
```json
{
  "vendor_id": 123,
  "total_reviews": 45,
  "average_rating": 4.7,
  "completed_orders": 50,
  "response_rate": 90,
  "breakdown": {
    "5": 30,
    "4": 10,
    "3": 3,
    "2": 1,
    "1": 1
  }
}
```

### Moderate Reviews via API

**Update Review Status:**
```
PATCH /wp-json/wpss/v1/reviews/{review_id}
```

**Admin can set:**
```json
{
  "status": "approved" // or "pending" or "rejected"
}
```

**Permissions:** Requires `manage_options` capability

---

## Notifications

### Vendor Notifications

**New Review:**
- Email: "You received a new review"
- In-app notification
- Includes rating and first 100 characters

**Review Approved (if moderation enabled):**
- Email: "Your review was approved"
- Sent to reviewer, not vendor

### Admin Notifications

**Pending Review (if moderation enabled):**
- Dashboard badge count
- Optional email digest (daily)
- Shows review content preview

---

## Best Practices

### For Admins

**Moderation:**
- Review pending reviews within 24 hours
- Be consistent with approval criteria
- Document rejection reasons internally
- Don't reject just because rating is negative

**Monitoring:**
- Check vendor reputation weekly
- Watch for sudden rating drops
- Investigate review patterns
- Address vendor concerns promptly

### For Vendors

**Maintaining High Ratings:**
- Deliver quality work consistently
- Communicate proactively
- Meet deadlines
- Reply to all reviews professionally
- Use negative feedback to improve

**Responding to Negative Reviews:**
- Reply within 48 hours
- Acknowledge the issue
- Explain what happened
- Offer solutions
- Stay professional and calm

---

## Related Documentation

- [Review System](./review-system.md) - How to leave and manage reviews
- [Order Workflow](../order-management/order-workflow.md) - Complete orders to enable reviews
- [Vendor Levels](../vendor-system/seller-levels.md) - Rating requirements for advancement
- [Admin Tools](../admin-tools/moderation-queue.md) - Content moderation

---

## Troubleshooting

**Q: Why isn't a review showing after approval?**
- Check review status is `approved`
- Clear site cache
- Check if service/vendor is still active
- Verify review wasn't accidentally deleted

**Q: Can I change a review's rating after it's published?**
- Yes, admins can edit ratings via API
- Customers can edit their own reviews
- Changes update vendor/service averages immediately

**Q: How do I enable review moderation?**
- Go to Settings → General
- Find "Moderate Reviews" checkbox
- Enable it
- All new reviews will be pending

**Q: Why do some reviews have sub-ratings and others don't?**
- Sub-ratings (communication, quality, value) are optional
- Buyers can skip them
- They don't affect the overall vendor rating
- They're informational only

**Q: Can I weight recent reviews higher?**
- No, this feature doesn't exist in the current code
- All reviews weighted equally
- Contact developer if you need custom weighting

**Q: How do I disable reviews entirely?**
- This requires custom development
- Reviews are core to the marketplace
- Consider disabling public display instead
- Not recommended for trust/transparency
