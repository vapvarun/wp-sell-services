# Manual Order Creation

Create orders manually through the WordPress admin panel for phone orders, offline sales, system migrations, or special customer requests.

## Overview

The manual order creation tool lets administrators create service orders outside the normal checkout flow. This is useful for processing phone orders, importing legacy data, handling offline payments, or accommodating special customer situations.

**Page Location:** WordPress Admin → WP Sell Services → Orders → Create Order

**Access Required:** `manage_options` capability (administrators only)

![Manual order creation page](../images/admin-create-manual-order.png)

## When to Use Manual Orders

Create manual orders for:

- **Phone Orders:** Customer places order over the phone
- **Offline Payments:** Payment received via bank transfer, cash, or check
- **Data Migration:** Importing orders from another system
- **Special Arrangements:** Custom pricing or package arrangements
- **Testing:** Creating test orders for development

Manual orders function identically to regular orders once created.

## Accessing the Create Order Page

1. Go to **WP Sell Services → Orders** in WordPress admin
2. Click **Create Order** button
3. Manual order form opens (page slug: `wpss-create-order`)

**Note:** This is a hidden submenu page - not visible in the WordPress admin menu. Only accessible via the Create Order button.

## Creating a Manual Order

### Step 1: Select Service and Package

**Service Selection:**

- Choose from all published services
- Dropdown shows: Service Title (Vendor Name) - Starting Price
- Service must have `publish` post status

**Package Selection:**

- After selecting service, available packages load
- Packages auto-fill:
  - Price (subtotal)
  - Delivery days
  - Revisions included
- Leave empty to use service starting price

### Step 2: Select Addons

**Loading Addons:**

Addons load automatically when you select a service via AJAX action: `wpss_get_service_addons`

**Addon Information Shown:**

- Title and description
- Price and price type
- Min/max quantity
- Delivery days added
- Required status

**Addon Selection:**

- Check addon to include
- Set quantity (for quantity-based addons)
- Price calculates automatically

### Step 3: Select Customer and Vendor

**Customer (Buyer):**

- Required field
- Select from all WordPress users
- Shows display name and email

**Vendor Override:**

- Optional field
- Defaults to service author if left empty
- Useful for reassigning orders to different vendors
- Cannot be same as customer

### Step 4: Configure Pricing

**Pricing Summary Table:**

- **Subtotal:** Package base price
- **Addons Total:** Sum of selected addon prices
- **Order Total:** Subtotal + Addons Total (or manual override)
- **Commission Rate:** Platform fee percentage (default from global settings)
- **Platform Fee:** Calculated from total × commission rate
- **Vendor Earnings:** Total - Platform Fee

**Manual Total Override:**

- Check "Override total manually" to set custom total
- Input field enables when checked
- Useful for discounts or custom pricing

**Commission Rate:**

- Defaults to global commission rate from `CommissionService::get_global_commission_rate()`
- Can be adjusted per-order (0-100%)
- Affects platform fee and vendor earnings calculations

**Currency:**

- Select from 25 supported currencies
- Defaults to global currency from `wpss_get_currency()`
- Includes: USD, EUR, GBP, INR, AUD, CAD, JPY, and 18 more

### Step 5: Set Status and Payment Details

**Order Status:**

Available initial statuses:

| Status | Description |
|--------|-------------|
| `pending_payment` | Payment not yet received |
| `pending_requirements` | Payment complete, awaiting buyer requirements |
| `in_progress` | Skip requirements, vendor starts immediately |
| `delivered` | Create order with delivery already submitted |
| `completed` | Create already-completed order |

**Smart Status Logic:**

If you select `pending_requirements` but the service has no requirements defined, the order automatically transitions to `in_progress` status. You'll see a notification: "Note: This service has no requirements defined. Order was set to 'In Progress' automatically."

**Payment Status:**

| Status | Description |
|--------|-------------|
| `pending` | Payment not received |
| `paid` | Payment received (default) |
| `failed` | Payment failed |
| `refunded` | Payment refunded |

**Payment Method:**

| Method | Use For |
|--------|---------|
| `manual` | Default for admin-created orders |
| `bank_transfer` | Bank transfer payments |
| `cash` | Cash payments |
| `other` | Other payment methods |

**Transaction ID:**

- Optional reference number
- Enter external payment reference (e.g., bank transaction ID)
- Stored in `transaction_id` field

**Delivery Days:**

- Number of days until delivery deadline
- Auto-fills from selected package
- Can be manually adjusted
- Used to calculate `delivery_deadline` if order status is `in_progress`

**Revisions Included:**

- Number of revisions buyer can request
- Auto-fills from selected package
- Can be manually adjusted
- Default: 2 revisions

### Step 6: Add Admin Notes

**Internal Notes:**

- Textarea for internal notes about the order
- Not visible to customer or vendor
- Stored as system message in order conversation
- Format: `[Admin Note] Your note text here`
- Uses `ConversationService::add_system_message()`

**Example Notes:**

- "Phone order from customer on 2024-01-15"
- "Migrated from old system - Order #OLD-123"
- "Custom pricing approved by manager"
- "Special rush delivery requested"

## Order Creation Process

When you click **Create Order**, the following happens:

### 1. Data Validation

- Service ID and Customer ID required
- Service must exist and be type `wpss_service`
- Customer cannot be same as vendor
- Amount must be greater than zero (minimum $10.00 fallback)

### 2. Addon Processing

- Selected addons validated via `ServiceAddonService::get()`
- Addon prices calculated using `ServiceAddonService::calculate_price()`
- Addon delivery days added to total delivery time

### 3. Commission Calculation

- Commission rate validated (0-100%)
- Platform fee = Total × (Commission Rate / 100)
- Vendor earnings = Total - Platform Fee

### 4. Deadline Calculation

- If status is `in_progress` and delivery_days > 0:
  - `delivery_deadline` = current time + delivery_days
  - `original_deadline` = same as delivery_deadline
- Otherwise, deadline fields remain null

### 5. Order Number Generation

- Format: `WPSS-` + 8 random uppercase characters
- Example: `WPSS-A7K9M2X4`
- Generated using `wp_generate_password(8, false)`

### 6. Database Insert

Order record inserted into `wpss_orders` table with fields:

```php
array(
    'order_number'       => 'WPSS-A7K9M2X4',
    'customer_id'        => (int),
    'vendor_id'          => (int),
    'service_id'         => (int),
    'package_id'         => (int|null),
    'platform'           => 'manual',
    'platform_order_id'  => null,
    'transaction_id'     => (string|null),
    'addons'             => JSON,
    'subtotal'           => (float),
    'addons_total'       => (float),
    'total'              => (float),
    'currency'           => (string),
    'status'             => (string),
    'payment_method'     => (string),
    'payment_status'     => (string),
    'commission_rate'    => (float),
    'platform_fee'       => (float),
    'vendor_earnings'    => (float),
    'revisions_included' => (int),
    'revisions_used'     => 0,
    'delivery_deadline'  => (datetime|null),
    'original_deadline'  => (datetime|null),
    'started_at'         => (datetime|null),
    'paid_at'            => (datetime|null),
    'completed_at'       => (datetime|null),
    'created_at'         => (datetime),
    'updated_at'         => (datetime),
)
```

### 7. Conversation Creation

- Order conversation created via `ConversationService::create_for_order()`
- Admin notes added as system message if provided

### 8. WordPress Hooks Fired

```php
do_action( 'wpss_order_created', $order_id, $status );
do_action( 'wpss_order_status_changed', $order_id, $status, '' );
do_action( "wpss_order_status_{$status}", $order_id, '' );
```

### 9. Success Response

Returns JSON with:

- `order_id` - Database ID of created order
- `order_number` - Generated order number
- `status` - Order status
- `view_url` - URL to view order (via `wpss_get_order_url()`)
- `requirements_url` - URL to requirements form
- `requirements_skipped` - Boolean if requirements auto-skipped
- `has_requirements` - Boolean if service has requirements

## After Order Creation

**Success Screen Shows:**

- Order created confirmation message
- Order number and ID
- **View Order** button - Opens order detail page
- **Submit Requirements** button - Shows if service has requirements and status is `pending_requirements`
- **Create Another Order** button - Resets form

**Next Steps:**

1. If status is `pending_requirements`:
   - Customer or admin submits requirements
   - Order transitions to `in_progress`

2. If status is `in_progress`:
   - Vendor receives notification
   - Delivery deadline starts counting
   - Vendor works on order

3. If status is `delivered` or `completed`:
   - Order already in final stage
   - No further action required

## Manual Order Fields Reference

### Required Fields

- Service
- Customer (Buyer)

### Auto-Calculated Fields

- Order Number (generated)
- Subtotal (from package or service)
- Platform Fee (from commission rate)
- Vendor Earnings (total - platform fee)
- Delivery Deadline (from delivery days if in_progress)

### Optional Override Fields

- Vendor (defaults to service author)
- Total (calculated unless manually overridden)
- Commission Rate (defaults to global rate)
- Currency (defaults to global currency)
- Payment Method (defaults to 'manual')
- Transaction ID
- Delivery Days (defaults from package)
- Revisions (defaults from package)
- Admin Notes

## Common Use Cases

### Phone Order

1. Customer calls with order request
2. Admin creates manual order
3. Status: `pending_payment` or `paid` depending on payment
4. Payment Method: Bank transfer or other
5. Add admin note: "Phone order from [customer name] on [date]"

### Offline Payment

1. Payment received outside platform (bank transfer, cash)
2. Create order with status: `paid`
3. Payment Status: `paid`
4. Transaction ID: Bank reference number
5. Admin note: Payment method details

### Data Migration

1. Import orders from old system
2. Create multiple manual orders
3. Status: `completed` for historical orders
4. Admin notes: "Migrated from [old system] - Original order ID: [old_id]"

### Custom Pricing

1. Negotiate special price with customer
2. Create order with package
3. Enable "Override total manually"
4. Set custom total
5. Adjust commission rate if needed
6. Admin note: "Custom pricing approved by [manager name]"

## Limitations

- Cannot bulk create orders (one at a time)
- No draft/save functionality (must complete creation)
- Cannot edit orders after creation (use order edit page)
- No customer notification sent automatically (manual orders are platform='manual')

## Technical Details

**AJAX Actions:**

- `wpss_create_manual_order` - Creates the order
- `wpss_get_service_addons` - Loads addons for selected service

**JavaScript Object:**

`wpssManualOrder` localized with:
- `ajaxUrl` - AJAX endpoint
- `nonce` - Security nonce
- `defaultCommissionRate` - Global commission rate
- `currencyFormat` - Currency display format
- `i18n` - Translated strings

**Nonce Action:**

- Action: `wpss_create_manual_order`
- Verified with `check_ajax_referer()`

**Database Tables:**

- `wpss_orders` - Order record
- `wpss_conversations` - Order conversation

**Minimum Order Total:**

If calculated total is 0 or negative, falls back to $10.00 minimum.

## Next Steps

- **[Order Management](../order-management/order-lifecycle.md)** - Managing orders after creation
- **[Vendor Management](vendor-management.md)** - Managing vendor accounts
- **[Commission System](../earnings-wallet/commission-system.md)** - Understanding platform fees
