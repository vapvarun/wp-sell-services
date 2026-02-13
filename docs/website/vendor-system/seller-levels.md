# Seller Levels

WP Sell Services uses a four-tier seller level system that rewards vendor performance and drives marketplace quality.

## The Four Seller Levels

The marketplace features four distinct seller levels representing different stages of vendor achievement:

1. **New Seller** (`new`) - Starting point for all vendors
2. **Level 1 Seller** (`level_1`) - Proven track record
3. **Level 2 Seller** (`level_2`) - Consistent excellence
4. **Top Rated Seller** (`top_rated`) - Elite marketplace performers

## Level Criteria

### New Seller

**Entry Requirements:**
- Newly registered vendor
- Profile completed
- Account in good standing

**Criteria:**
- Completed Orders: 0+
- Minimum Rating: 0
- Minimum Reviews: 0
- Response Rate: 0%
- Delivery Rate: 0%
- Days Active: 0

All vendors start at this level automatically.

### Level 1 Seller

**Requirements (ALL must be met):**

| Criterion | Threshold | How Measured |
|-----------|-----------|--------------|
| **Completed Orders** | 5+ | Successfully delivered orders |
| **Average Rating** | 4.0+ stars | Average of all reviews |
| **Total Reviews** | 3+ | Number of reviews received |
| **Response Rate** | 80%+ | Message reply rate |
| **Delivery Rate** | 80%+ | On-time delivery percentage |
| **Days Active** | 30+ days | Since vendor registration |

**Note:** "Cancellation Rate" is NOT a criterion for seller levels.

### Level 2 Seller

**Requirements (ALL must be met):**

| Criterion | Threshold | How Measured |
|-----------|-----------|--------------|
| **Completed Orders** | 25+ | Successfully delivered orders |
| **Average Rating** | 4.5+ stars | Average of all reviews |
| **Total Reviews** | 10+ | Number of reviews received |
| **Response Rate** | 90%+ | Message reply rate |
| **Delivery Rate** | 90%+ | On-time delivery percentage |
| **Days Active** | 90+ days | Since vendor registration |

### Top Rated Seller

**Requirements (ALL must be met):**

| Criterion | Threshold | How Measured |
|-----------|-----------|--------------|
| **Completed Orders** | 100+ | Successfully delivered orders |
| **Average Rating** | 4.8+ stars | Average of all reviews |
| **Total Reviews** | 50+ | Number of reviews received |
| **Response Rate** | 95%+ | Message reply rate |
| **Delivery Rate** | 95%+ | On-time delivery percentage |
| **Days Active** | 180+ days | Since vendor registration |

## How Criteria Are Calculated

### Completed Orders

Orders with status `completed` counted from `wpss_orders` table.

### Average Rating

Average of all approved reviews from `wpss_reviews` table where `status = 'approved'`.

### Total Reviews

Count of approved reviews for the vendor.

### Response Rate

Calculated from message response patterns (tracked in vendor profile as `response_rate`). Defaults to 100 if not tracked.

### Delivery Rate

Percentage of orders delivered by deadline:
```
(on_time_orders / completed_orders) * 100
```

Calculated by comparing `completed_at` to `delivery_deadline` in orders table.

### Days Active

Days since vendor profile creation (`created_at` in `wpss_vendor_profiles` table).

## Automatic Level Assessment

The system automatically calculates vendor levels:

- **Daily Check**: Runs daily to assess all vendors
- **Real-Time Updates**: Metrics update with each order event
- **Automatic Promotion**: Level upgrades happen automatically when criteria met
- **Grace Period**: No immediate downgrade protection

Levels are stored in `verification_tier` column of `wpss_vendor_profiles` table.

## Level Benefits

### New Seller

- Create and publish services
- Receive orders
- Access vendor dashboard
- Basic marketplace visibility

### Level 1 Seller

- "Level 1 Seller" badge
- Improved search ranking
- Increased buyer trust
- Standard marketplace features

### Level 2 Seller

- "Level 2 Seller" badge
- Higher search placement
- Enhanced visibility
- Priority consideration

### Top Rated Seller

- "Top Rated Seller" badge
- Maximum search visibility
- Premium marketplace placement
- Elite status recognition

**[PRO]** Additional benefits may include:
- Lower commission rates
- Priority support
- Featured placement
- Advanced analytics access

## Progress Tracking

### Dashboard View

Access your seller level progress:

1. Navigate to **Dashboard → Seller Level**
2. View current level and criteria
3. Track progress to next level

### Progress Display

For each criterion:
- Current value
- Required threshold
- Progress percentage
- Status (Met/Not Met)

**Example:**
```
Completed Orders: 35/100 (35%) ✗
Average Rating: 4.85/4.80 (100%) ✓
Total Reviews: 55/50 (100%) ✓
Response Rate: 96%/95% (100%) ✓
Delivery Rate: 93%/95% (98%) ✗
Days Active: 200/180 (100%) ✓
```

## Leveling Up

When you meet ALL criteria for the next level:

1. System detects criteria met (daily check)
2. Level automatically upgraded
3. `verification_tier` field updated
4. Badge updates site-wide
5. Email notification sent

**No manual action required** - the system handles everything automatically.

## Maintaining Your Level

To keep your current level:

- Continue meeting ALL criteria
- Deliver quality work consistently
- Maintain rating above threshold
- Keep delivery rate high
- Respond to messages promptly

**Downgrade Note:** While the code calculates levels, there's no explicit downgrade protection period in the current implementation. Level changes as soon as criteria change.

## Tips for Advancement

### Reaching Level 1 (2-4 weeks)

1. **Complete 5 Orders**: Focus on quality over quantity
2. **Earn 4.0+ Rating**: Deliver excellent work
3. **Get 3 Reviews**: Request reviews from satisfied buyers
4. **Respond Quickly**: 80%+ response rate
5. **Deliver On-Time**: 80%+ on-time rate
6. **Stay Active**: 30 days as vendor

### Reaching Level 2 (2-4 months)

1. **Scale to 25 Orders**: Maintain quality at higher volume
2. **Achieve 4.5+ Rating**: Nearly every buyer satisfied
3. **Collect 10 Reviews**: Build review history
4. **90% Response Rate**: Check messages multiple times daily
5. **90% Delivery Rate**: Meet deadlines consistently
6. **90 Days Active**: Three months of activity

### Reaching Top Rated (4-6 months)

1. **Complete 100 Orders**: Demonstrate sustained performance
2. **Maintain 4.8+ Rating**: Exceptional quality every time
3. **Earn 50 Reviews**: Substantial review base
4. **95% Response Rate**: Nearly instant responses
5. **95% Delivery Rate**: Reliable deadline performance
6. **180 Days Active**: Six months proven track record

## Level Labels

The system displays these labels:

```php
'new' => 'New Seller'
'level_1' => 'Level 1 Seller'
'level_2' => 'Level 2 Seller'
'top_rated' => 'Top Rated Seller'
```

Badges appear:
- On vendor profile
- Next to vendor name on services
- In search results
- In vendor listings

## Admin Level Management

Admins can manually set seller levels if needed:

1. Edit vendor profile in admin
2. Update `verification_tier` field
3. Choose from: new, level_1, level_2, top_rated
4. Save changes

Manual changes persist until next automatic assessment.

## Related Resources

- [Vendor Profile](vendor-profile-portfolio.md) - View your stats
- [Vendor Dashboard](vendor-dashboard.md) - Access level progress
- [Review System](../reviews-ratings/review-system-wpss.md) - Impact on ratings
