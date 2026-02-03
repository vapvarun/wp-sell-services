# Service Packages

Service packages are pricing tiers that let you offer different levels of service at different price points. This guide covers how to create effective package structures that maximize sales.

## What Are Service Packages?

Service packages (also called tiers or pricing plans) give buyers options when purchasing your service. Instead of one fixed price, you offer multiple tiers with increasing value.

**Typical Structure:**
- **Basic**: Entry-level offering at lowest price
- **Standard**: Mid-tier with more value (most popular)
- **Premium**: Complete solution at highest price

## Package Components

Each package includes these fields:

| Field | Description | Required |
|-------|-------------|----------|
| **Package Name** | Tier label (Basic, Standard, Premium) | Yes |
| **Price** | Cost in your currency | Yes |
| **Delivery Days** | Days to complete the work | Yes |
| **Revisions** | Number of revision rounds | Yes |
| **Description** | What's included in this tier | Yes |

![Package configuration](../images/admin-package-setup.png)

## Package Limits

**Free Version**: Maximum **3 packages** per service

**Pro Version**: **[PRO]** **Unlimited packages** - create as many tiers as needed

## Creating Effective Packages

### Basic Package Strategy

The Basic package should:
- Deliver core value at an attractive entry price
- Include the minimum viable deliverable
- Have shorter delivery time
- Limit revisions (1-2)
- Remove premium features

**Example: WordPress Website Design**

| Item | Basic Package |
|------|---------------|
| Price | $100 |
| Delivery | 3 days |
| Revisions | 1 |
| Includes | • 3 pages<br>• Mobile responsive<br>• Contact form<br>• Basic SEO |

### Standard Package Strategy

The Standard package should:
- Be your "best value" option (most buyers choose this)
- Include 50-70% more value than Basic
- Price 2-3x the Basic package
- Offer moderate delivery time
- Include more revisions (2-3)

**Example: WordPress Website Design**

| Item | Standard Package |
|------|------------------|
| Price | $250 |
| Delivery | 5 days |
| Revisions | 3 |
| Includes | • 7 pages<br>• Mobile responsive<br>• Contact form<br>• Advanced SEO<br>• Social media integration<br>• 30-day support |

### Premium Package Strategy

The Premium package should:
- Be the complete, comprehensive solution
- Include everything from Standard + premium extras
- Price 3-5x the Basic package
- Offer extended delivery time
- Provide unlimited or high revision count
- Include VIP treatment

**Example: WordPress Website Design**

| Item | Premium Package |
|------|-----------------|
| Price | $500 |
| Delivery | 7 days |
| Revisions | Unlimited |
| Includes | • Unlimited pages<br>• Mobile responsive<br>• Advanced forms<br>• Premium SEO optimization<br>• Social media integration<br>• E-commerce setup<br>• 90-day priority support<br>• Performance optimization<br>• Security hardening |

![Package comparison display](../images/frontend-package-comparison.png)

## Pricing Your Packages

### Pricing Psychology

**Price Anchoring**: The Premium package makes the Standard package look like a better deal.

**Sweet Spot Pricing**: Most buyers choose the middle option.

**Value Perception**: Each tier should feel like a significant upgrade.

### Pricing Formula

**Basic Package**: Base your hourly rate + minimal scope
- Example: $50/hour × 2 hours = $100

**Standard Package**: Basic × 2.5 to 3
- Example: $100 × 2.5 = $250

**Premium Package**: Standard × 2 to 2.5
- Example: $250 × 2 = $500

### Competitive Pricing

Research similar services on your marketplace:

1. Find 5-10 comparable services
2. Note their package prices
3. Price competitively based on your experience:
   - **New vendor**: 10-20% below average
   - **Experienced vendor**: At average prices
   - **Top-rated vendor**: 10-30% above average

## Delivery Time Strategy

### Time-Based Differentiation

Don't just scale features—use delivery time as a differentiator:

**Fast Basic**: Quick turnaround for small scope
- Basic: 2-3 days
- Standard: 5-7 days
- Premium: 10-14 days

**Why This Works:**
- Buyers who need it fast pay for Basic
- Buyers who want more value wait longer for Standard
- Premium buyers get comprehensive work and don't mind waiting

### Setting Realistic Delivery Times

**Calculate Your Time:**
1. Estimate actual work hours
2. Add buffer for revisions (20-30%)
3. Add buffer for delays (10-20%)
4. Convert to business days

**Example:**
- Work time: 8 hours
- Revision buffer: +2 hours
- Delay buffer: +1 hour
- Total: 11 hours = ~2 business days
- **Set delivery**: 3 days (safe margin)

**Pro Tip**: Under-promise and over-deliver. If you set 5 days but deliver in 3, buyers are thrilled.

## Revision Strategy

### Revision Allocation

**Basic**: 1-2 revisions
- Keeps workload manageable
- Encourages clear initial requirements

**Standard**: 2-4 revisions
- Balances quality and efficiency
- Most buyers need 2-3 rounds

**Premium**: Unlimited or 6+ revisions
- VIP treatment
- Justifies premium price
- Very few buyers use more than 4 revisions anyway

### What Counts as a Revision?

Define this in your service description:

**One Revision Round Includes:**
- Minor text changes
- Color adjustments
- Layout tweaks
- Small feature modifications

**Not Included in Revisions (counts as new work):**
- Complete redesigns
- New pages/sections beyond original scope
- Major functionality changes
- Feature additions

![Revision counter in order](../images/frontend-order-revisions.png)

## Package Descriptions

### Writing Compelling Descriptions

Use bullet points to list exactly what's included:

**Good Description:**
```
Standard Package - $250
• 7 responsive WordPress pages
• Custom theme customization
• Contact form setup
• SEO optimization (Yoast)
• Social media integration
• Google Analytics setup
• 3 rounds of revisions
• 30-day post-delivery support
• 5-day delivery
```

**Bad Description:**
```
Standard Package - $250
I will make you a great website with several pages and some extras.
Delivery in about a week.
```

### Highlighting Differences

Make it easy to compare packages:

| Feature | Basic | Standard | Premium |
|---------|-------|----------|---------|
| Pages | 3 | 7 | Unlimited |
| Revisions | 1 | 3 | Unlimited |
| Support | 14 days | 30 days | 90 days |
| SEO | Basic | Advanced | Premium |
| E-commerce | ❌ | ❌ | ✅ |
| Performance Optimization | ❌ | ❌ | ✅ |

## Package Display on Frontend

Buyers see packages on your service page:

### Package Card Layout

Each package displays:
- Package name
- Price (prominently)
- Delivery time
- Revision count
- Feature list (bullets)
- **Select** button

![Package selection interface](../images/frontend-package-cards.png)

### Highlighting Best Value

Mark your recommended package with a badge:

- **Most Popular** badge on Standard package
- Highlighted with different color
- Draws buyer attention to best value

This is configurable in service settings.

## Advanced Package Strategies

### Industry-Specific Examples

**Content Writing:**
- Basic: 1 article (500 words)
- Standard: 3 articles (500 words each)
- Premium: 5 articles (1000 words each) + SEO optimization

**Logo Design:**
- Basic: 2 concepts, 1 revision, files
- Standard: 5 concepts, 3 revisions, files + source files
- Premium: 10 concepts, unlimited revisions, files + full branding kit

**WordPress Development:**
- Basic: Single feature/fix
- Standard: Multiple features + testing
- Premium: Complex feature + testing + documentation + support

### Subscription-Based Packages

For ongoing services, structure as recurring work:

**Basic**: $500/month
- 10 hours of work
- 5-day turnaround
- Basic support

**Standard**: $1200/month
- 25 hours of work
- 3-day turnaround
- Priority support

**Premium**: $2500/month
- 50 hours of work
- Same-day turnaround
- Dedicated support

## Managing Packages

### Editing Existing Packages

1. Go to **Vendor Dashboard → Services**
2. Click **Edit** on your service
3. Navigate to **Packages** tab
4. Click **Edit** on the package
5. Update fields
6. Click **Save Changes**

**Note**: Changes to packages don't affect active orders, only new purchases.

### Removing Packages

To remove a package:
1. Edit the service
2. Click **Delete** on the package
3. Confirm deletion

**Warning**: You cannot delete a package if there are active orders using it.

### Reordering Packages

Drag and drop packages to reorder:
1. Edit service
2. Drag package cards up or down
3. Changes save automatically

**Display Order**: Typically Basic → Standard → Premium (left to right)

## Package Analytics

Track which packages perform best:

1. Go to **Vendor Dashboard → Analytics**
2. View **Package Performance** report
3. See metrics:
   - Orders per package
   - Revenue per package
   - Average order value
   - Conversion rate by package

Use this data to optimize pricing and features.

## Common Mistakes to Avoid

❌ **Too Many Packages**: More than 3 packages overwhelms buyers

❌ **Similar Pricing**: If packages are too close in price ($50, $60, $70), buyers won't see value in upgrading

❌ **Unclear Differences**: Vague descriptions make it hard to compare

❌ **Underpricing Premium**: If Premium is only 20% more than Standard, everyone chooses Premium (hurting your margins)

❌ **Overcomplicating Basic**: Don't make Basic so robust that nobody needs Standard

✅ **Best Practices**: 3 clear tiers, 2-3x price jumps, obvious value increases, specific deliverables

## Package Templates by Service Type

### Web Design/Development
- Basic: Landing page
- Standard: Multi-page website
- Premium: Full website + extras

### Writing Services
- Basic: 1 piece
- Standard: 3-5 pieces
- Premium: 10+ pieces + strategy

### Graphic Design
- Basic: 2-3 concepts
- Standard: 5 concepts + revisions
- Premium: 10 concepts + full package

### Consulting/Strategy
- Basic: 1-hour session
- Standard: 3-hour deep dive + report
- Premium: Full strategy + implementation plan

## Next Steps

- **[Service Add-ons](service-addons-extras.md)** - Upsell with extras beyond packages
- **[Creating a Service](creating-a-service.md)** - Complete service creation guide
- **[Managing Services](managing-services.md)** - Edit and optimize published services

Well-structured packages are the key to maximizing your service revenue!
