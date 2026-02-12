# Posting a Buyer Request

## Overview

Buyer Requests let you post your project needs and receive competitive proposals from vendors. Instead of searching for services, vendors come to you with pricing and delivery estimates.

**Benefits:**
- Get multiple quotes without searching
- Compare vendor pricing and timelines
- Find specialists for custom projects
- Vendors compete for your business

---

## What Are Buyer Requests?

A reverse marketplace where you describe what you need and vendors submit proposals.

### How It Works

1. **You post a request** with project details and budget
2. **Vendors browse requests** and submit proposals
3. **You review proposals** and compare options
4. **You accept a proposal** which creates an order
5. **Order begins** with standard workflow

![Buyer requests flow](../images/frontend-buyer-requests-flow.png)

---

## Accessing the Request Form

**Dashboard Method:**
1. Log in to your account
2. Go to **Dashboard → Buyer Requests**
3. Click **Post New Request**
4. Form opens

**Shortcode:**
Admins can place the form anywhere with:
```
[wpss_post_request]
```

![Post request button](../images/dashboard-my-requests.png)

---

## Request Form Fields

### Title (Required)

A clear, specific title summarizing your project.

**Character Limit:**
- Minimum: 10 characters (HTML only, not server-enforced)
- Maximum: 100 characters (HTML only, not server-enforced)

**Note:** Previous documentation stated strict server-side validation. The code only has HTML `maxlength="100"` with no server validation. Longer titles can be submitted via API.

**Good Examples:**
```
✓ "WordPress E-commerce Site with Custom Product Configurator"
✓ "Logo Design for Tech Startup - Modern & Minimalist"
✓ "Fix WooCommerce Checkout Payment Gateway Issues"
```

**Bad Examples:**
```
✗ "Need help" (too vague)
✗ "Website" (not descriptive)
✗ "URGENT!!!" (no detail)
```

### Description (Required)

Detailed explanation of your project requirements.

**What to Include:**
- Project overview
- Specific deliverables needed
- Technical requirements
- Design preferences (if applicable)
- Success criteria

**No Character Limits Enforced:**
Previous docs claimed strict character limits. The code has NO server-side validation for description length.

**Good Example:**
```
I need a custom WordPress plugin that adds a product wishlist
feature to my WooCommerce store.

Required Features:
- Add/remove products to wishlist (heart icon)
- Wishlist page showing saved products
- Email reminders for wishlist items
- Share wishlist via unique URL
- Admin dashboard to view wishlist stats

Technical Requirements:
- Compatible with WooCommerce 8.0+
- Works with WordPress 6.0+
- Mobile-responsive design
- Clean, documented code

Deliverables:
- Fully functional plugin
- Installation instructions
- 30 days of bug fixes
```

![Request description field](../images/frontend-request-form.png)

### Category (Optional)

Select the service category that best matches your project.

**Available Categories:**
- Dynamically loaded from `wpss_service_category` taxonomy
- Helps vendors find relevant requests
- Shown in request listings

### Budget

**Two Budget Types:**

**1. Fixed Price** (`fixed`)
- Set one specific amount
- "My budget is $500"
- Best for well-defined projects

**2. Price Range** (`range`)
- Set minimum and maximum
- "My budget is $300-$500"
- Best for flexible projects

**Important:** Previous documentation listed a "Flexible" budget type. Only `fixed` and `range` exist in the code.

**Budget Fields:**
- `budget_type`: `fixed` or `range`
- `budget_min`: Minimum amount (for range) or fixed amount
- `budget_max`: Maximum amount (for range only)

**Examples:**
```
Fixed: budget_type="fixed", budget_min=500
Range: budget_type="range", budget_min=300, budget_max=500
```

### Delivery Days (Optional)

How many days you need the work completed in.

**Field:** `delivery_days` (integer)
**Default:** 7 days (if not specified)

**Purpose:**
- Sets buyer expectations
- Vendors can propose different timelines
- Not enforced as deadline (proposal accepted deadline used)

### Skills Required (Optional)

**Field:** `skills_required` (array of strings)

List specific skills or technologies needed.

**Examples:**
```
["WordPress", "PHP", "JavaScript", "WooCommerce"]
["Photoshop", "Illustrator", "Logo Design"]
["React", "Node.js", "MongoDB"]
```

**Note:** This field exists in the code but was not mentioned in previous documentation.

### Attachments (Optional)

Upload reference files, mockups, or documents.

**Field:** `attachments` (array of WordPress attachment IDs)

**Supported:**
- Images (.jpg, .png, .gif)
- Documents (.pdf, .doc, .docx)
- Design files (.psd, .ai, .sketch)
- Archives (.zip)

**How to Add:**
1. Click **Add Attachment**
2. Upload file to WordPress media library
3. File ID automatically added to array

**Note:** Previous documentation mentioned a "tags" field. This does NOT exist on buyer requests in the code.

![Request attachments](../images/frontend-request-attachments.png)

---

## Request Statuses

Your request will have one of these statuses:

### Model Statuses (BuyerRequest Model)

| Status | Meaning |
|--------|---------|
| `open` | Accepting proposals |
| `closed` | No longer accepting (manually closed) |
| `hired` | Proposal accepted, order created |
| `expired` | Deadline passed without acceptance |

### Service Statuses (BuyerRequestService)

The Service layer uses slightly different statuses:

| Status | Meaning |
|--------|---------|
| `open` | Accepting proposals |
| `in_review` | Buyer reviewing proposals |
| `hired` | Proposal accepted |
| `expired` | Time limit reached |
| `cancelled` | Buyer cancelled request |

**Important:** Note the inconsistency between Model and Service. The code uses both sets depending on context. The Service statuses are more comprehensive.

---

## Submitting Your Request

### Step 1: Review Information

Before submitting:
- ✓ Title is clear and specific
- ✓ Description includes all requirements
- ✓ Budget is realistic for scope
- ✓ Delivery timeline is reasonable
- ✓ Attachments uploaded (if applicable)

### Step 2: Submit

Click **Post Request** button.

**What Happens:**
1. Request created with status `open`
2. Published to requests page
3. Vendors notified of new request
4. Request visible in search

**Expiration:**
Default: 30 days (configurable by admin)

After expiration:
- Status changes to `expired`
- No longer visible to vendors
- Can be reposted with updates

---

## After Posting

### Receiving Proposals

Vendors will submit proposals with:
- Proposed price
- Delivery timeline (days)
- Cover letter explaining their approach
- Link to relevant portfolio/services

**You'll receive notifications:**
- Email for each new proposal
- In-app notification
- Dashboard badge count

### Managing Proposals

**Dashboard → Buyer Requests → [Your Request]**

View all proposals:
- Vendor profile and ratings
- Proposed price and timeline
- Cover letter
- Accept or reject actions

![Received proposals](../images/frontend-proposals-list.png)

---

## Accepting a Proposal

When you find the right vendor:

### Step 1: Review Proposal

Check:
- Vendor rating and reviews
- Proposed price fits budget
- Timeline meets your needs
- Cover letter shows understanding
- Portfolio shows relevant experience

### Step 2: Accept

Click **Accept Proposal** button.

**What Happens Immediately:**
1. Order created with status `pending_payment`
2. Proposal status → `accepted`
3. Other proposals → `rejected`
4. Request status → `hired`
5. Vendor notified
6. You're redirected to checkout

**Order Details:**
- Subtotal: Proposal's proposed price
- Service: Vendor's linked service (if any) or generic "Buyer Request"
- Delivery deadline: Based on proposal's `proposed_days`
- Requirements: Auto-filled from request description

### Step 3: Complete Payment

1. Review order details
2. Enter payment information
3. Confirm purchase
4. Order status → `in_progress`
5. Vendor begins work

**Important:** Previous documentation suggested orders aren't created until after a separate checkout step. The code creates the order IMMEDIATELY when proposal is accepted with `pending_payment` status.

---

## Request Visibility

### Who Can See Your Request?

**Public by default:**
- All verified vendors can see open requests
- Shown in vendor dashboard
- Listed on requests browse page
- Searchable by category/keywords

**Hidden when:**
- Status is `closed`, `hired`, or `expired`
- Admin manually hides it
- You delete the request

---

## Editing Requests

### While Open

You CAN edit before proposals are accepted:

**Editable:**
- Title
- Description
- Budget (within reason)
- Attachments
- Category

**Cannot Edit:**
- After proposal accepted
- After request expired
- Submitted proposals (those are vendor-owned)

**To Edit:**
1. Go to **Dashboard → Buyer Requests**
2. Find your request
3. Click **Edit**
4. Make changes
5. Click **Update**

### After Hiring

Once you accept a proposal, the request cannot be edited. It's locked and associated with the order.

---

## Closing Requests

**Manual Close:**
If you no longer need the work:

1. Go to your request
2. Click **Close Request**
3. Status → `closed`
4. Pending proposals notified
5. No longer visible to vendors

**Reasons to Close:**
- Found vendor outside platform
- Project cancelled
- Requirements changed significantly
- Budget no longer available

**Note:** Closing doesn't affect already-accepted proposals/orders.

---

## Request Expiration

### Default Expiration

**Default:** 30 days after posting

**Configurable:** Admin can change in settings

### What Happens at Expiration

1. Status → `expired`
2. No longer accepting proposals
3. Hidden from vendor search
4. Existing proposals remain visible

### Extending Expiration

If admin allows:
1. Go to request page
2. Click **Extend**
3. Adds another 30 days
4. Status → `open` again

**Note:** The code doesn't automatically implement extension. This requires custom development or admin intervention.

---

## Best Practices

### For Better Proposals

**Be Specific:**
- List exact deliverables
- Provide examples or references
- Specify file formats needed
- Mention must-have vs. nice-to-have

**Set Realistic Budgets:**
- Research market rates
- Consider project complexity
- Allow range for flexibility
- Don't lowball experienced vendors

**Respond Quickly:**
- Check proposals within 24-48 hours
- Ask clarifying questions
- Decline proposals you won't accept
- Keep vendors updated

### Red Flags in Proposals

**Be Cautious of:**
- Prices far below market rate
- Promises that sound too good
- Poor grammar/unprofessional tone
- Generic copy-paste responses
- No relevant portfolio
- Brand new accounts with no reviews

---

## REST API Endpoints

### Create Request

```
POST /wp-json/wpss/v1/buyer-requests
```

**Required:**
```json
{
  "title": "Project title",
  "description": "Detailed requirements",
  "budget_type": "range",
  "budget_min": 300,
  "budget_max": 500
}
```

**Optional:**
```json
{
  "category_id": 5,
  "delivery_days": 14,
  "skills_required": ["WordPress", "PHP"],
  "attachments": [123, 456]
}
```

### Get Open Requests

```
GET /wp-json/wpss/v1/buyer-requests?status=open
```

### Update Request

```
PATCH /wp-json/wpss/v1/buyer-requests/{request_id}
```

---

## Related Documentation

- [Submitting Proposals](./submitting-proposals.md) - How vendors respond
- [Managing Requests](./managing-requests.md) - Track and manage your requests
- [Order Workflow](../order-management/order-workflow.md) - After accepting proposal
- [Service Creation](../service-creation/service-wizard.md) - For vendors

---

## Troubleshooting

**Q: Why can't I post a request?**
- Must have verified account
- Check if buyer requests are enabled
- May require minimum account age

**Q: How many requests can I post?**
- No hard limit in code
- Admin may set rate limits
- Keep active requests reasonable (5-10 max)

**Q: Can I accept multiple proposals?**
- No, only ONE proposal per request
- Accepting one rejects all others
- If you need multiple vendors, post separate requests

**Q: What if I don't get any proposals?**
- Increase budget if too low
- Make requirements clearer
- Check if category is correct
- Wait 3-5 days (vendors may be busy)

**Q: Can I message vendors before accepting?**
- Not directly through requests
- Use proposal comments/questions
- Or ask vendor to contact you

**Q: What happens to my request if I delete my account?**
- Open requests are closed
- Accepted proposals/orders remain active
- Proposal history preserved for vendors

**Q: Do I pay to post requests?**
- No, posting requests is free
- You only pay when you accept a proposal
- Payment is for the actual work, not the request
