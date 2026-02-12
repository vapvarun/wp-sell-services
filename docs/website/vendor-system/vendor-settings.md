# Vendor Settings

Configure how vendors join your marketplace and manage their service creation capabilities.

## Accessing Vendor Settings

Navigate to **WP Sell Services → Settings → Vendor** in the WordPress admin panel.

![Vendor Settings Tab](../images/settings-vendor-tab.png)

## Vendor Registration

Control who can become a vendor on your marketplace.

### Registration Mode

Choose from three registration modes:

| Mode | Description | Use Case |
|------|-------------|----------|
| **Open** | Anyone can register as vendor | Growing marketplaces, open platforms |
| **Requires Approval** | Admin must approve applications | Curated marketplaces, quality control |
| **Closed** | Only admins create vendor accounts | Invite-only, exclusive platforms |

**Setting:** `vendor_registration` (default: `open`)

**Configuration:**
1. Go to **Settings → Vendor**
2. Select **Vendor Registration** mode
3. Choose: Open, Requires Approval, or Closed
4. Save changes

### Open Registration

**Behavior:**
- Users self-register via `[wpss_vendor_registration]` or `[wpss_become_vendor]` shortcode
- Account activated immediately (if auto-approve enabled)
- Vendors can create services right away

**Best For:**
- Community marketplaces
- High-volume platforms
- Quick onboarding

### Requires Approval

**Behavior:**
- Users submit registration form
- Account status: "Pending"
- Admin reviews and approves/rejects
- Email notification sent to applicant

**Approval Process:**
1. New vendor registers
2. Admin receives notification
3. Navigate to **WP Sell Services → Vendors**
4. Filter by Status: Pending
5. Review vendor profile
6. Click Approve or Reject

**Best For:**
- Quality-controlled marketplaces
- Curated vendor lists
- Professional services platforms

### Closed Registration

**Behavior:**
- Registration page shows "Registration Closed" message
- Only admins can create vendor accounts manually
- No public registration form

**Manual Vendor Creation:**
1. Navigate to **Users → Add New**
2. Create WordPress user account
3. Assign `wpss_vendor` role
4. User gains vendor capabilities

**Best For:**
- Invite-only platforms
- Soft launch phase
- Exclusive marketplaces

## Service Limits

Control what vendors can include in their service listings.

### Max Services Per Vendor

**Setting:** `max_services_per_vendor` (default: `20`)

Limit the total number of active services each vendor can create.

**Configuration:**
1. Set **Max Services Per Vendor** value
2. Enter number (0 for unlimited)
3. Save changes

**Example:**
- Set to 10: Vendors can create maximum 10 services
- Set to 0: No limit (unlimited services)

### Gallery Images

**Limit:** 4 images per service (Free version)

**Setting Location:** Not directly configurable in Vendor settings (handled by service creation logic)

**Pro Version:** Unlimited images

**Image Requirements:**
- Minimum: 800×600 pixels
- Formats: JPG, PNG, GIF
- Size limit: Set by server/WordPress config

## Verification Requirements

Control vendor account verification.

### Require Verification

**Setting:** `require_verification` (default: `false`)

When enabled, new vendors start with "Pending" status until identity verified.

**Configuration:**
1. Check **Require Verification**
2. Save changes

**Effect:**
- New vendors registered with `status = 'pending'`
- Vendors cannot create services until verified
- Admin manually verifies vendors

**Verification Process:**
1. Vendor registers
2. Admin reviews submitted information
3. Admin verifies identity (email, documents, etc.)
4. Admin updates status to "Active"
5. Vendor can now create services

## Service Moderation

Control whether services need admin approval before publishing.

### Require Service Moderation

**Setting:** `require_service_moderation` (default: `false`)

When enabled, all new services require admin approval before going live.

**Configuration:**
1. Check **Require Service Moderation**
2. Save changes

**Workflow:**
1. Vendor creates service
2. Service saved as "Pending Review"
3. Admin reviews service
4. Admin approves/rejects
5. If approved, service goes live

**Review Location:**
Navigate to **WP Sell Services → Services** and filter by status.

## Database Options

These settings are stored in WordPress options table as `wpss_vendor` option group:

```php
'wpss_vendor' => [
    'vendor_registration' => 'open', // or 'approval', 'closed'
    'max_services_per_vendor' => 20,
    'require_verification' => false,
    'require_service_moderation' => false
]
```

## Shortcode Reference

### Vendor Registration

Use these shortcodes on your "Become a Vendor" page:

- `[wpss_vendor_registration]` - Registration form
- `[wpss_become_vendor]` - Alternative registration form

Both shortcodes provide the same functionality.

**Example Page Setup:**
1. Create new page: "Become a Vendor"
2. Add shortcode: `[wpss_vendor_registration]`
3. Publish page
4. Update page slug in **Settings → Pages → Become a Vendor**

## Vendor Role Capabilities

When a user becomes a vendor, they receive the `wpss_vendor` role with these capabilities:

- `wpss_vendor` - Vendor marker capability
- `wpss_manage_services` - Create and edit services
- `wpss_manage_orders` - View and manage orders
- `wpss_view_analytics` - Access earnings dashboard
- `wpss_respond_to_requests` - Respond to buyer requests
- `read` - Basic WordPress access
- `upload_files` - Upload attachments
- `edit_posts` - Create content

These capabilities are granted automatically via `Activator::create_roles()`.

## Registration Form Fields

The vendor registration form collects:

| Field | Required | Notes |
|-------|----------|-------|
| **Display Name** | Yes | Public vendor name |
| **Tagline** | Yes | Professional title |
| **Bio** | Yes | About you section |
| **Skills** | Yes | Comma-separated skills |
| **Terms Agreement** | Yes | Checkbox for T&C acceptance |

Additional fields may be added by extensions.

## Admin Vendor Management

### Viewing Vendors

Navigate to **WP Sell Services → Vendors** to see:

- Total vendors count
- Active vendors
- Pending approvals
- Vendor list with statistics

### Vendor Actions

For each vendor, admins can:

- **View Profile**: See complete vendor information
- **Edit**: Modify vendor settings
- **Approve/Reject**: Change account status
- **Suspend**: Temporarily disable vendor
- **Delete**: Remove vendor account

### Custom Commission Rates

Admins can set per-vendor commission rates:

1. Navigate to vendor details
2. Set custom commission percentage
3. Overrides global commission rate
4. Calculated per order

Learn more: [Commission System](../earnings-wallet/commission-system-wpss.md)

## Best Practices

### For Open Registration

- Enable email verification
- Monitor new vendor registrations
- Set reasonable service limits
- Review vendors periodically

### For Approval Mode

- Respond to applications within 24-48 hours
- Provide clear rejection reasons
- Document approval criteria
- Communicate with applicants

### For Closed Registration

- Invite quality vendors personally
- Provide onboarding assistance
- Set higher service limits
- Build vendor relationships

## Troubleshooting

### Registration Form Not Showing

**Check:**
- Registration mode is "Open" or "Approval"
- Page contains correct shortcode
- Plugin is activated
- No JavaScript errors

**Solution:**
- Verify shortcode: `[wpss_vendor_registration]`
- Clear cache
- Test in default WordPress theme

### Vendors Can't Create Services

**Verify:**
- Account status is "Active"
- Max services limit not reached
- Vendor role assigned correctly
- Service moderation settings

**Solution:**
- Check vendor status in admin
- Increase service limit if needed
- Verify `wpss_vendor` role exists

### Service Moderation Not Working

**Confirm:**
- "Require Service Moderation" is checked
- Settings saved successfully
- Cache cleared
- WordPress permissions correct

## Related Documentation

- [Becoming a Vendor](becoming-a-vendor.md) - Vendor registration guide
- [Vendor Dashboard](vendor-dashboard.md) - Vendor interface
- [Seller Levels](seller-levels.md) - Vendor progression system
- [Platform Settings](../platform-settings/general-settings-wpss.md) - General configuration
