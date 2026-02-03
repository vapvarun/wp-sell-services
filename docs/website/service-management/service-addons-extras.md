# Service Add-ons & Extras

Service add-ons (also called extras) let buyers customize their orders with additional features beyond your base packages. This guide shows you how to create effective add-ons that increase your average order value.

## What Are Service Add-ons?

Add-ons are optional extras that buyers can add to any package during checkout. They're displayed after package selection and let buyers customize their order.

**Examples:**
- Rush delivery (+$25)
- Extra revisions (+$15 each)
- Source files (+$30)
- Commercial license (+$50)
- Additional pages (+$20 per page)

![Add-on selection at checkout](../images/frontend-addon-selection.png)

## Add-on Limits

**Free Version**: Maximum **3 add-ons** per service

**Pro Version**: **[PRO]** **Unlimited add-ons**

## Add-on Components

Each add-on includes these fields:

| Field | Description | Required |
|-------|-------------|----------|
| **Title** | Add-on name shown to buyers | Yes |
| **Description** | What the add-on includes | Optional |
| **Field Type** | How buyers select (checkbox, dropdown, etc.) | Yes |
| **Price Type** | How to calculate cost | Yes |
| **Price** | Cost amount | Yes |
| **Apply To** | Which packages can use this add-on | Yes |

## Field Types

Choose the right field type for your add-on:

### Checkbox (Yes/No)

Simple on/off extras that add a fixed cost.

**Use For:**
- Rush delivery
- Source files included
- Commercial license
- Priority support

**Example:**
```
Title: Extra Fast Delivery
Description: Receive your order in 24 hours
Field Type: Checkbox
Price: Flat $25
```

![Checkbox add-on](../images/admin-addon-checkbox.png)

### Dropdown (Select One)

Buyer chooses one option from a list.

**Use For:**
- Quantity selectors (1, 2, 3, 4, 5 extra items)
- Tier upgrades
- Feature variations

**Example:**
```
Title: Additional Pages
Field Type: Dropdown
Options:
  - 1 page - $20
  - 2 pages - $35
  - 3 pages - $50
  - 5 pages - $75
Price Type: Variable per option
```

### Text Input

Buyer enters custom text (name, URL, specifications).

**Use For:**
- Custom domain names
- Personalization details
- Special instructions
- Client branding text

**Example:**
```
Title: Custom Domain Setup
Description: Enter your domain name (e.g., example.com)
Field Type: Text
Price: Flat $15
```

**Note**: Text inputs have a fixed price; buyer input is for information only.

### Radio Buttons

Similar to dropdown but displayed as radio options.

**Use For:**
- Delivery speed tiers
- Format options
- Style variations

**Example:**
```
Title: Delivery Speed
Field Type: Radio
Options:
  - Standard (5 days) - $0
  - Fast (3 days) - $20
  - Express (24 hours) - $50
```

### Multi-Select (Choose Multiple)

Buyer can select multiple options from a list.

**Use For:**
- Multiple feature add-ons
- Plugin installations
- Additional formats
- Platform integrations

**Example:**
```
Title: Additional Integrations
Field Type: Multi-select
Options:
  - Mailchimp Setup - $15
  - Google Analytics - $10
  - Facebook Pixel - $10
  - Stripe Payment - $25
  - PayPal Setup - $15
Price Type: Individual per option
```

![Multi-select add-on](../images/admin-addon-multiselect.png)

## Price Types

Add-ons can be priced in three ways:

### Flat Rate

Fixed price regardless of package or quantity.

**Best For:**
- One-time additions
- Fixed-cost extras
- Binary options

**Example:**
```
Title: Source Files Included
Price Type: Flat Rate
Price: $30
```

**Calculation**: Always adds $30 to order total

### Percentage

Price calculated as percentage of package price.

**Best For:**
- Scaled services
- Proportional upgrades
- Commission-based extras

**Example:**
```
Title: Commercial License
Price Type: Percentage
Price: 50%
```

**Calculation:**
- Basic package ($100) + add-on = $100 + $50 = $150
- Premium package ($500) + add-on = $500 + $250 = $750

### Quantity-Based

Price multiplied by quantity selected.

**Best For:**
- Variable quantities (pages, products, hours)
- Scalable deliverables
- Per-unit additions

**Example:**
```
Title: Extra Product Pages
Price Type: Quantity-Based
Price: $20 per page
Min Quantity: 1
Max Quantity: 10
```

**Calculation**: Buyer selects 3 pages → $20 × 3 = $60 added to order

![Quantity-based pricing](../images/admin-addon-quantity.png)

## Applying Add-ons to Packages

Control which packages can use each add-on:

### All Packages

Add-on available for Basic, Standard, and Premium tiers.

**Use For:**
- Universal extras (rush delivery, source files)
- Options that apply to any tier

### Specific Packages Only

Add-on only available for selected packages.

**Use For:**
- Tier-specific upgrades
- Features that only make sense for certain packages

**Example:**
```
Add-on: E-commerce Integration
Apply To: Standard and Premium only
(Basic package is too limited for e-commerce)
```

![Package restrictions](../images/admin-addon-packages.png)

## Creating Effective Add-ons

### High-Converting Add-on Ideas

**Rush Delivery** (25-30% take rate)
- Field: Checkbox
- Price: 30-50% of package price
- Reduces delivery time by 50%

**Extra Revisions** (15-20% take rate)
- Field: Dropdown (1, 2, 3, 5 revisions)
- Price: $15-30 per revision
- Popular with perfectionists

**Source Files** (40-50% take rate for design)
- Field: Checkbox
- Price: 20-30% of package price
- Essential for designers/developers

**Commercial License** (10-15% take rate)
- Field: Checkbox
- Price: 50-100% of package price
- Required for business use

**Extended Support** (20-25% take rate)
- Field: Dropdown (30, 60, 90 days)
- Price: 10-20% per month
- Builds ongoing relationship

### Pricing Add-ons

**Low-Friction Extras** ($5-20):
- Quick additions that many buyers select
- Keep price low to encourage impulse purchases

**Value Add-ons** ($20-50):
- Significant improvements to deliverable
- Price reflects substantial extra work

**Premium Add-ons** ($50+):
- Complete feature additions
- Major scope expansions

**Pro Tip**: Price add-ons to make package upgrades attractive. If add-ons cost more than upgrading to the next package tier, buyers will upgrade instead.

### Bundle Strategy

Create add-ons that work together:

**Social Media Package:**
- Facebook Integration (+$15)
- Instagram Feed (+$15)
- Twitter Widget (+$10)
- Full Social Suite (+$30, save $10)

This encourages buyers to purchase the bundle add-on.

## Add-on Display

### Frontend Display

Buyers see add-ons after selecting a package:

1. Choose package (Basic/Standard/Premium)
2. Add-ons section appears below
3. Select desired add-ons
4. Total price updates in real-time
5. Proceed to checkout

![Add-on selection interface](../images/frontend-addons-interface.png)

### Price Calculation Display

Show clear breakdown:

```
Standard Package: $250
├─ Extra Fast Delivery: +$25
├─ Source Files Included: +$30
├─ 2 Extra Revisions: +$30 (2 × $15)
└─ Total: $335
```

### Visual Hierarchy

Add-ons are displayed as:
- **Checkbox**: Single checkbox with price
- **Dropdown**: Select menu with options
- **Radio**: List of radio options
- **Multi-select**: Checkbox list

## Managing Add-ons

### Adding New Add-ons

1. Edit your service
2. Go to **Add-ons** tab
3. Click **Add New Add-on**
4. Fill in fields:
   - Title and description
   - Field type
   - Price type and amount
   - Package restrictions
5. Click **Save Add-on**

![Add-on creation form](../images/admin-addon-create.png)

### Editing Existing Add-ons

1. Edit service
2. Go to **Add-ons** tab
3. Click **Edit** on the add-on
4. Update fields
5. Save changes

**Note**: Changes don't affect active orders, only new purchases.

### Reordering Add-ons

Drag and drop to change display order:
1. Edit service
2. Go to **Add-ons** tab
3. Drag add-ons up or down
4. Order saves automatically

**Best Practice**: Put most popular add-ons first (top of the list).

### Deleting Add-ons

1. Edit service
2. Go to **Add-ons** tab
3. Click **Delete** on add-on
4. Confirm deletion

**Warning**: Cannot delete add-ons included in active orders.

## Add-on Analytics

Track add-on performance in your vendor dashboard:

**Metrics Available:**
- **Attachment Rate**: % of orders that include add-on
- **Revenue Generated**: Total from this add-on
- **Average Order Value**: Orders with vs. without add-on
- **Most Popular**: Top-selling add-ons

Use this data to:
- Remove low-performing add-ons
- Create more of what sells
- Adjust pricing
- Test new ideas

![Add-on analytics](../images/admin-addon-analytics.png)

## Advanced Add-on Strategies

### Decoy Pricing

Create an expensive add-on to make others seem reasonable:

```
- Extra Revision: $15 (most buy this)
- 3 Extra Revisions: $40
- Unlimited Revisions: $100 (makes $40 look good)
```

### Deadline-Based Pricing

Variable pricing based on urgency:

```
Standard Delivery (5 days): $0
Fast Delivery (3 days): +$20
Express (24 hours): +$50
Super Express (12 hours): +$100
```

### Package Upgrade Incentive

Price add-ons so upgrading packages is better value:

```
Basic Package: $100
+ Extra features via add-ons: +$75
Total: $175

OR

Standard Package (includes those features): $150
```

Smart buyers upgrade to Standard, increasing your package sales.

## Industry-Specific Add-on Examples

### Web Design/Development
- Mobile app version (+$200)
- Additional pages ($25 each)
- E-commerce integration (+$100)
- SEO optimization (+$50)
- Performance optimization (+$75)

### Content Writing
- Extra revisions ($10 each)
- SEO keyword research (+$30)
- Meta descriptions (+$15)
- Social media posts (+$25)
- Rush delivery (+$20)

### Graphic Design
- Additional concepts ($15 each)
- Source files (+$30)
- Vector files (+$40)
- Commercial license (+$50)
- Social media kit (+$35)

### Video Editing
- Additional minutes ($15/min)
- Color grading (+$50)
- Sound design (+$75)
- Subtitles/captions (+$30)
- Multiple formats (+$25)

## Common Mistakes to Avoid

❌ **Too Many Add-ons**: More than 5-7 overwhelms buyers

❌ **Confusing Field Types**: Use checkbox for simple yes/no, not dropdown

❌ **Vague Descriptions**: "Extra stuff" doesn't sell, be specific

❌ **Overpriced Add-ons**: If add-on costs more than upgrading package, buyers won't choose it

❌ **Irrelevant Add-ons**: Must relate directly to the service

✅ **Best Practices**: 3-5 targeted add-ons, clear value, competitive pricing, simple selection

## Developer Customization

Developers can customize add-ons with hooks:

### Filter Add-on Display

```php
add_filter( 'wpss_service_addons', function( $addons, $service_id ) {
    // Modify add-ons array
    return $addons;
}, 10, 2 );
```

### Custom Price Calculation

```php
add_filter( 'wpss_addon_price', function( $price, $addon, $package ) {
    // Custom pricing logic
    return $price;
}, 10, 3 );
```

### Conditional Add-ons

```php
add_filter( 'wpss_addon_available', function( $available, $addon_id, $package_id ) {
    // Hide/show add-ons based on logic
    return $available;
}, 10, 3 );
```

## Next Steps

- **[Service Packages](service-packages.md)** - Structure your base pricing tiers
- **[Creating a Service](creating-a-service.md)** - Complete service setup
- **[Order Management](../order-management/order-workflow.md)** - How add-ons affect orders

Add-ons are your secret weapon for increasing revenue per order without raising base prices!
