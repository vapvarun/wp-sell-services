# Submitting Proposals to Buyer Requests

## Overview

As a vendor, you can submit proposals to buyer requests to win new projects. Proposals let you pitch your services, quote your price, and explain why you're the right fit for the job.

**Key Facts:**
- One proposal per request per vendor
- Proposals can be edited while `pending`
- No character length limits enforced
- Order created immediately when accepted

---

## Finding Buyer Requests

### Dashboard Access

**Navigate to:**
Dashboard → Buyer Requests

**View Options:**
- All open requests
- Filter by category
- Search by keywords
- Sort by budget/date

![Buyer requests list](../images/frontend-buyer-requests-browse.png)

### Request Details

Click any request to view:
- Full project description
- Budget range or fixed price
- Desired delivery timeline
- Category and skills needed
- Attachments (if any)
- Buyer's profile and history
- Number of proposals already submitted

---

## Proposal Requirements

### Eligibility

**You can submit proposals if:**
- ✓ You're a verified vendor
- ✓ Request status is `open`
- ✓ You haven't submitted a proposal yet (one per request)
- ✓ Request hasn't expired

**You cannot submit if:**
- ✗ You already proposed to this request
- ✗ Request is `closed`, `hired`, or `expired`
- ✗ You're the buyer (can't propose to your own request)
- ✗ Your vendor account is suspended

### One Proposal Per Request

**Important:** The code enforces ONE proposal per vendor per request.

If you try to submit again:
- Error: "You have already submitted a proposal"
- Edit your existing proposal instead
- Withdrawn proposals don't count (you can submit a new one)

---

## Proposal Form Fields

### Cover Letter (Required)

Explain your approach and why you're the best choice.

**Field:** `cover_letter` (text)
**No Character Limit:** Previous documentation claimed strict limits. The code has NO server-side validation for length.

**What to Include:**
- Understanding of the project
- Your relevant experience
- Approach/methodology
- Why you're a good fit
- Examples of similar work

**Good Example:**
```
Hi [Buyer Name],

I've reviewed your WordPress WooCommerce wishlist plugin requirements
and I'm confident I can deliver exactly what you need.

Relevant Experience:
- Built 15+ custom WooCommerce plugins
- 5 years WordPress development
- Expertise in wishlist/favorites features

My Approach:
1. Database schema for wishlist storage (days 1-2)
2. Frontend wishlist UI with AJAX (days 3-5)
3. Email reminder system (days 6-7)
4. Share functionality (day 8)
5. Admin dashboard (days 9-10)
6. Testing & documentation (days 11-12)

I'll provide clean, documented code with 30 days of support.
Portfolio: [link to similar projects]

Looking forward to working with you!

Best regards,
[Your Name]
```

**Bad Example:**
```
I can do this. Please hire me. Good price.
```

### Price (Required)

Your proposed price for the project.

**Field:** `proposed_price` (float)

**Pricing Strategy:**
- Review buyer's budget
- Calculate your costs + profit
- Consider project complexity
- Factor in timeline
- Include revisions/support

**Budget Matching:**
- Buyer budget: $300-$500
- Your proposal: $450 (within range)
- Too low: $200 (suspiciously cheap)
- Too high: $800 (outside budget)

### Delivery Days (Required)

How many days you need to complete the work.

**Field:** `proposed_days` (integer)

**Setting Timeline:**
- Be realistic (don't over-promise)
- Add buffer for revisions (10-20%)
- Consider your current workload
- Match or beat buyer's preferred timeline

**Example:**
- Buyer wants: 7 days
- Your estimate: 10 days
- Your proposal: 12 days (with buffer)

**Note:** The old docs incorrectly called this "delivery_days". The actual field name is `proposed_days` in the database and `delivery_days` in API parameters.

### Attachments (Optional)

**Field:** `attachments` (JSON array)

Link to:
- Portfolio samples
- Similar past projects
- Mockups or previews
- Relevant certifications

**Format:** Stored as JSON string in database
```json
{
  "attachments": [
    {"type": "link", "url": "https://..."},
    {"type": "file", "id": 123}
  ]
}
```

### Service Link (Optional)

**Field:** `service_id` (integer)

If you have an existing service that matches the request, you can link it.

**Benefits:**
- Buyer sees your service details
- Reviews and ratings displayed
- Shows professionalism
- Not required, but helpful

---

## Proposal Statuses

Your proposal will have one of these statuses:

| Status | Meaning |
|--------|---------|
| **pending** | Submitted, awaiting buyer decision |
| **accepted** | Buyer accepted, order created |
| **rejected** | Buyer declined your proposal |
| **withdrawn** | You withdrew your proposal |

**Important:** Previous documentation listed 7+ statuses including "Submitted", "Under Review", and "Expired". Only these 4 exist in the code.

![Proposal status indicator](../images/frontend-proposal-status.png)

---

## Submitting Your Proposal

### Step 1: Complete Form

1. Go to buyer request detail page
2. Click **Submit Proposal**
3. Fill in cover letter
4. Set your price
5. Set delivery days
6. Add attachments (optional)
7. Link service (optional)

### Step 2: Review

Before submitting:
- ✓ Cover letter is professional and specific
- ✓ Price is competitive and fair
- ✓ Timeline is realistic
- ✓ No typos or errors

### Step 3: Submit

Click **Submit Proposal** button.

**What Happens:**
1. Proposal created with status `pending`
2. Stored in `wpss_proposals` table
3. Buyer notified via email
4. Buyer can view in their request dashboard
5. You receive confirmation

**Notifications:**
- **To Buyer:** "New proposal on your request"
- **To You:** "Your proposal was submitted"

---

## Editing Proposals

### While Pending

**You CAN edit your proposal while status is `pending`.**

**Editable Fields:**
- `cover_letter` - Update your pitch
- `proposed_price` - Change your price (use carefully)
- `proposed_days` - Adjust timeline

**Cannot Edit:**
- Request ID
- Vendor ID (you)
- Submission date

**Important:** Previous documentation incorrectly stated proposals cannot be edited. The code allows editing via `ProposalService->update()` and REST API.

### How to Edit

**Via API:**
```
PATCH /wp-json/wpss/v1/proposals/{proposal_id}
```

**Permissions:**
- Only vendor who submitted can edit
- Only while status is `pending`
- After acceptance/rejection: Cannot edit

**Why Edit:**
- Buyer asked for clarification
- You want to adjust price
- Found error in timeline
- Want to improve cover letter

### After Acceptance

Once accepted:
- **Cannot edit** - Proposal is locked
- Order created with proposal terms
- Changes must go through order modifications

---

## Proposal Acceptance

### When Buyer Accepts

**What Happens Immediately:**

1. **Proposal Status** → `accepted`
2. **Order Created** with status `pending_payment`
3. **Other Proposals** → `rejected`
4. **Request Status** → `hired`
5. **You're Notified** via email + in-app

**Order Details Auto-Populated:**
- Subtotal: Your `proposed_price`
- Delivery deadline: Current date + your `proposed_days`
- Requirements: Copied from buyer request description
- Proposal details: Stored in order meta

**Important:** Previous documentation suggested a separate checkout step before order creation. The code creates the order IMMEDIATELY when proposal is accepted with `pending_payment` status (line 609-633 in BuyerRequestService).

### After Acceptance

**Buyer Actions:**
1. Review order details
2. Complete payment
3. Order status → `in_progress`

**Your Actions:**
1. Check order dashboard
2. Wait for payment confirmation
3. Begin work after payment

**Timeline:**
- Acceptance: Immediate
- Payment: Usually within 24 hours
- Start work: After payment confirmation

---

## Withdrawing Proposals

### When to Withdraw

**Valid Reasons:**
- You're now too busy
- Project scope isn't clear
- Budget is too low
- Found better opportunities
- Miscalculated timeline

**How to Withdraw:**
1. Go to **Dashboard → My Proposals**
2. Find the proposal
3. Click **Withdraw**
4. Optionally add reason
5. Confirm withdrawal

**What Happens:**
- Status → `withdrawn`
- No longer visible to buyer
- You can submit a new proposal if request is still open
- Buyer notified

**Note:** Withdrawing doesn't harm your reputation unless done excessively.

---

## Proposal Best Practices

### Writing Great Proposals

**Do:**
- Personalize each proposal (no copy-paste)
- Show you understand the project
- Provide relevant examples
- Be professional and friendly
- Proofread for errors
- Set competitive pricing
- Be realistic with timelines

**Don't:**
- Send generic templates
- Undercut significantly just to win
- Promise unrealistic timelines
- Ignore project requirements
- Include irrelevant information
- Use aggressive sales tactics

### Pricing Strategy

**Competitive Pricing:**
- Research similar projects
- Check buyer's budget
- See competitor proposals (if possible)
- Price for quality, not just to win

**Pricing Tiers:**
- Budget-friendly: Within buyer's minimum
- Mid-range: Middle of buyer's range
- Premium: Top of buyer's range (justify with value)

**When to Quote High:**
- Complex technical requirements
- Tight timeline
- Extensive revisions included
- Your expertise is rare

**When to Quote Low:**
- Simple, quick projects
- Building portfolio
- New to platform
- Want testimonials

![Proposal pricing strategy](../images/frontend-proposal-pricing.png)

---

## After Proposal Submission

### Tracking Status

**Dashboard → My Proposals**

View:
- All your submitted proposals
- Current status of each
- Buyer activity (viewed, accepted, etc.)
- Messages from buyer

### Responding to Buyer Questions

If buyer messages you:
1. Respond within 24 hours
2. Answer questions clearly
3. Provide additional details
4. Stay professional

**Good Response:**
```
Hi [Buyer],

Thanks for your question about the email reminder feature.

I'll use WordPress cron jobs to send daily reminders for wishlist
items. Users can set their reminder preferences in their account
settings. Emails will be sent using wp_mail() with customizable
templates you can edit in the plugin settings.

Let me know if you need any other clarifications!
```

### If Proposal is Rejected

**Don't take it personally.**

**Common Reasons:**
- Buyer found better fit
- Budget constraints
- Timeline mismatch
- Chose vendor they've worked with before

**What to Do:**
- Move on to next request
- Learn from feedback (if provided)
- Improve future proposals
- Keep submitting

---

## Proposal Limits

### Quantity Limits

**Per Request:**
- ONE proposal per request per vendor
- Can withdraw and resubmit (if still open)

**Active Proposals:**
- No hard limit in code
- Keep it reasonable (10-20 active max)
- Focus on quality over quantity

### Time Limits

**Submission Window:**
- No time limit while request is `open`
- Best to submit within 2-3 days of posting
- Early submissions get more attention

**Edit Window:**
- Edit anytime while `pending`
- After acceptance: Cannot edit

---

## REST API Endpoints

### Submit Proposal

```
POST /wp-json/wpss/v1/buyer-requests/{request_id}/proposals
```

**Required:**
```json
{
  "description": "Your cover letter",
  "price": 450.00,
  "delivery_days": 12
}
```

**Optional:**
```json
{
  "service_id": 789,
  "attachments": [...]
}
```

### Get Your Proposals

```
GET /wp-json/wpss/v1/proposals?status=pending
```

### Update Proposal

```
PATCH /wp-json/wpss/v1/proposals/{proposal_id}
```

**Allowed while `pending`:**
```json
{
  "cover_letter": "Updated pitch",
  "price": 475.00,
  "delivery_days": 10
}
```

### Withdraw Proposal

```
POST /wp-json/wpss/v1/proposals/{proposal_id}/withdraw
```

---

## Related Documentation

- [Posting Requests](./posting-request.md) - How buyers create requests
- [Managing Requests](./managing-requests.md) - Buyer perspective
- [Order Workflow](../order-management/order-workflow.md) - After acceptance
- [Vendor Dashboard](../vendor-system/vendor-dashboard.md) - Managing proposals

---

## Troubleshooting

**Q: Why can't I submit a proposal?**
- Check if you already submitted one
- Verify request is still `open`
- Ensure you're a verified vendor
- Request may have expired

**Q: Can I submit multiple proposals to one request?**
- No, only ONE proposal per request
- Edit your existing proposal if needed
- Or withdraw and resubmit new one

**Q: Can I change my price after submitting?**
- Yes, while status is `pending`
- Use the edit/update function
- Be careful - buyer may have already seen original price

**Q: What if buyer doesn't respond?**
- Wait 5-7 days
- Buyer receives notifications
- Some buyers post and forget
- Move on to other requests if no response

**Q: Why was my proposal rejected?**
- Buyer chose another vendor
- Price/timeline didn't fit
- Cover letter wasn't convincing
- Buyer's requirements changed
- Not always communicated

**Q: Do I get notifications when proposal is viewed?**
- No, this feature doesn't exist in code
- You only get notified of acceptance/rejection
- Check dashboard for status changes

**Q: Can I see other proposals on the request?**
- No, proposals are private
- Only buyer sees all proposals
- You only see your own

**Q: What happens if I don't complete work after acceptance?**
- Order cancellation
- Negative impact on reputation
- Buyer may dispute
- May affect seller level
- Don't accept if you can't deliver
