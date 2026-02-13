# Vendor Management

View and monitor vendor accounts, statistics, and basic information through the admin panel.

## Overview

The vendor management page provides administrators with a centralized view of all vendors on the platform, including key statistics and account details.

**Page Location:** WordPress Admin → WP Sell Services → Vendors

**Access Required:** `manage_options` capability (administrators only)

![Vendors management page](../images/admin-vendors-dashboard.png)

## Accessing the Vendors Page

1. Log in to WordPress admin
2. Go to **WP Sell Services → Vendors** (submenu of WP Sell Services)
3. Page slug: `wpss-vendors`

## Dashboard Statistics

The vendors page displays 4 statistics cards:

### Total Vendors

- Count of all vendors in `wpss_vendor_profiles` table
- All statuses included in count

### Active

- Count of vendors with `status = 'active'`
- Color: Green highlight

### Pending

- Count of vendors with `status = 'pending'`
- Vendors awaiting approval (if approval required)

### Suspended

- Count of vendors with `status = 'suspended'`
- Temporarily restricted accounts

Additional statistics shown:
- **Average Rating:** Average of `avg_rating` column across all vendors (rounded to 2 decimals)
- **Total Earnings:** Sum of `total_earnings` from all vendor profiles

## Vendor List Table

The main table displays all vendors with sortable columns:

### Columns

| Column | Data Source | Description | Sortable |
|--------|-------------|-------------|----------|
| Vendor | `u.display_name` | Vendor name and email | Yes (by display_name) |
| Services | COUNT of wpss_service posts | Number of published services | No |
| Orders | `vp.total_orders` | Total orders completed | Yes |
| Earnings | `vp.total_earnings` | Total lifetime earnings | Yes (by total_earned) |
| Rating | `vp.avg_rating` | Average customer rating | Yes |
| Level | Calculated | Seller level badge | No |
| Status | `vp.status` | Account status badge | No |
| Joined | `u.user_registered` | Registration date | Yes (by created_at) |

### Vendor Column Details

Shows vendor name linked to detail view:

- Display name (linked to `?action=view&vendor_id=[id]`)
- Email address below name (smaller text)

### Services Column

Count of published services query:

```sql
SELECT COUNT(*) FROM wp_posts p
WHERE p.post_author = vp.user_id
AND p.post_type = 'wpss_service'
AND p.post_status = 'publish'
```

### Sorting

Default sort: `created_at DESC` (newest first)

Available sort columns:
- `created_at` - Registration date
- `display_name` - Vendor name (alphabetical)
- `rating` - Average rating (avg_rating column)
- `total_orders` - Order count
- `total_earned` - Lifetime earnings

URL parameters: `?orderby=[column]&order=[ASC|DESC]`

## Filtering Vendors

### Status Filter

Filter tabs above table:

- **All** - All vendors regardless of status
- **Active** - `status = 'active'` only
- **Pending** - `status = 'pending'` only
- **Suspended** - `status = 'suspended'` only

URL parameter: `?status=[status]`

### Search

Search box searches in:
- Vendor display name (`u.display_name`)
- Email address (`u.user_email`)

URL parameter: `?s=[search_term]`

Uses `LIKE %search%` query with `$wpdb->esc_like()`.

## Vendor Detail View

Click vendor name to view detailed profile.

**URL:** `?action=view&vendor_id=[user_id]`

**Note:** The source code shows vendor detail rendering via `render_vendor_detail()` method, but implementation details require reading additional vendor profile files which weren't fully reviewed. The detail view exists but specific tabs/content need verification from VendorProfileRepository and related classes.

## Vendor Registration Status

### Pending Status

Vendors with `status = 'pending'` are awaiting approval.

**Approval Required Setting:**

Checked in `VendorService::register()`:

```php
$vendor_settings = get_option( 'wpss_vendor', array() );
$require_verification = ! empty( $vendor_settings['require_verification'] );
$default_status = $require_verification ? 'pending' : 'active';
```

If `wpss_vendor['require_verification']` is enabled:
- New vendors start with `status = 'pending'`
- Require admin approval before active

If disabled:
- New vendors start with `status = 'active'`
- Immediately active upon registration

### Approving Vendors

Approval functionality handled through AJAX action: `wpss_update_vendor_status`

Changes vendor status from `pending` to `active`.

## Vendor Statuses

Three main statuses defined:

| Status | Description |
|--------|-------------|
| `active` | Full access, can create services and accept orders |
| `pending` | Awaiting approval, limited access |
| `suspended` | Temporarily restricted, cannot receive new orders |

Status stored in `wpss_vendor_profiles.status` column.

## Vendor Profile Database

### Table: `wpss_vendor_profiles`

Key columns (from VendorProfileRepository):

- `user_id` - Foreign key to wp_users (PRIMARY)
- `display_name` - Vendor display name
- `tagline` - Short tagline
- `bio` - Full biography
- `country` - Country location
- `city` - City location
- `status` - Account status (active/pending/suspended)
- `verification_tier` - Tier: basic/verified/pro
- `avg_rating` - Average rating (calculated)
- `total_orders` - Total completed orders
- `total_earnings` - Lifetime earnings
- `created_at` - Profile creation timestamp
- `updated_at` - Last update timestamp

### Verification Tiers

Three tiers defined in `VendorService`:

```php
public const TIER_BASIC    = 'basic';
public const TIER_VERIFIED = 'verified';
public const TIER_PRO      = 'pro';
```

New vendors start at `TIER_BASIC`.

## Vendor Capabilities

### Vendor Role

Role slug: `wpss_vendor` (defined in `VendorService::ROLE`)

### Adding Vendor Role

When user becomes vendor via `VendorService::register()`:

1. Check user exists
2. Check not already vendor
3. Add `wpss_vendor` role via `$user->add_role()`
4. Verify role added successfully
5. Add vendor capabilities
6. Create vendor profile
7. Set user meta:
   - `_wpss_is_vendor` → `true`
   - `_wpss_vendor_since` → current timestamp

### Checking Vendor Status

Method: `VendorService::is_vendor($user_id)`

Checks:
1. User has `wpss_vendor` role
2. OR `_wpss_is_vendor` user meta is true

Returns boolean.

## AJAX Actions

### Update Vendor Status

**Action:** `wpss_update_vendor_status`

**Nonce:** `wpss_vendors_admin`

Changes vendor status (pending → active, active → suspended, etc.).

### Get Vendor Details

**Action:** `wpss_get_vendor_details`

**Nonce:** `wpss_vendors_admin`

Returns vendor profile details for detail view.

### Update Vendor Commission **[PRO]**

**Action:** `wpss_update_vendor_commission`

**Nonce:** `wpss_vendors_admin`

Override global commission rate for specific vendor.

### Get Tab Content

**Action:** `wpss_vendor_tab_content`

**Nonce:** `wpss_vendors_admin`

Load content for vendor detail tabs (services, orders, reviews, earnings).

### Update Vendor Vacation

**Action:** `wpss_update_vendor_vacation`

**Nonce:** `wpss_vendors_admin`

Toggle vendor vacation mode.

### Update Vendor Availability

**Action:** `wpss_update_vendor_availability`

**Nonce:** `wpss_vendors_admin`

Update vendor availability settings.

## Vendor Query

### Get Vendors Method

Query in `VendorsPage::get_vendors()`:

```sql
SELECT
    vp.*,
    u.display_name,
    u.user_email,
    u.user_registered,
    (SELECT COUNT(*) FROM wp_posts p
     WHERE p.post_author = vp.user_id
     AND p.post_type = 'wpss_service'
     AND p.post_status = 'publish') as services_count
FROM wp_wpss_vendor_profiles vp
LEFT JOIN wp_users u ON vp.user_id = u.ID
WHERE [filters]
ORDER BY [orderby] [order]
LIMIT [per_page] OFFSET [offset]
```

**Pagination:**
- Default: 20 vendors per page
- URL parameter: `paged`
- Total pages: `ceil(total / per_page)`

## JavaScript Localization

The page localizes `wpssVendors` object:

```javascript
{
    ajaxUrl: admin_url('admin-ajax.php'),
    nonce: wp_create_nonce('wpss_vendors_admin'),
    i18n: {
        confirmStatusChange: 'Are you sure you want to change this vendor\'s status?',
        loading: 'Loading...',
        error: 'An error occurred. Please try again.'
    }
}
```

## WordPress Hooks

### Action: Vendor Registered

Fires when new vendor is registered:

```php
/**
 * Fires when a new vendor is registered.
 *
 * @param int   $user_id      User ID.
 * @param array $profile_data Profile data.
 */
do_action( 'wpss_vendor_registered', $user_id, $profile_data );
```

## Technical Details

**Page Hook:** `sell-services_page_wpss-vendors`

**Stylesheets:**
- `wpss-free-admin` - Main admin CSS (`assets/css/admin.css`)

**Scripts:**
- `wpss-free-admin` - Main admin JS (`assets/js/admin.js`)

**Database Tables:**
- `wpss_vendor_profiles` - Vendor profile data
- `wp_posts` - Services (post_type = 'wpss_service')
- `wp_users` - User accounts

## Vendor Statistics Calculation

Statistics query in `get_vendor_stats()`:

```sql
SELECT
    COUNT(*) as total_vendors,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vendors,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_vendors,
    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_vendors,
    AVG(avg_rating) as avg_rating,
    SUM(total_earnings) as total_earnings
FROM wp_wpss_vendor_profiles
```

Returns aggregate counts and averages across all vendors.

## Limitations in Free Version

The free version vendor management is view-only with basic filtering:

- View vendor list and statistics
- Filter by status and search
- Sort by various columns
- View basic vendor details

**Not included in free version:**
- Vendor approval workflow UI (requires manual status updates)
- Commission rate overrides per vendor
- Advanced vendor analytics
- Bulk actions on vendors
- Custom vendor notes
- Vendor suspension with reason tracking
- Detailed vendor performance reports

**For full vendor management features, see the Pro version documentation.**

## Next Steps

- **[Withdrawal Approvals](withdrawal-approvals.md)** - Processing vendor payouts
- **[Commission System](../earnings-wallet/commission-system.md)** - Understanding vendor earnings
- **[Vendor Dashboard](../vendor-system/vendor-dashboard.md)** - Vendor-facing dashboard
- **[Vendor Registration](../vendor-system/vendor-registration.md)** - How vendors sign up
