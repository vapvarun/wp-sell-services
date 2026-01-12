# WP Sell Services - Settings Audit Report

## Critical Issues Found

### 1. Option Name Inconsistency (BROKEN)

**Activator.php sets defaults with these names:**
```php
'wpss_general_settings' => array(...)
'wpss_vendor_settings'  => array(...)
```

**Settings.php registers options with these names:**
```php
'wpss_general' => array(...)
'wpss_vendor'  => array(...)
```

**Result:** Default options from Activator are NEVER used because the option names don't match!

---

### 2. Minimum Withdrawal Setting (BROKEN)

**Setting Registered:**
```php
// Settings.php - Vendor tab
'wpss_vendor' => [
    'min_payout_amount' => 50  // DEFAULT
]
```

**Code Uses:**
```php
// EarningsService.php, VendorDashboard.php, earnings.php template
get_option('wpss_min_withdrawal', 50)  // WRONG OPTION NAME!
```

**Result:** The admin setting `min_payout_amount` is NEVER read. Code always uses the hardcoded default of 50.

**Files Affected:**
- `src/Services/EarningsService.php:251`
- `src/Frontend/VendorDashboard.php:751, 762, 770, 801`
- `templates/dashboard/sections/earnings.php:21`

---

### 3. Commission Rate Confusion

**Currently Defined In:**
| Location | Setting Name | Purpose |
|----------|--------------|---------|
| Settings.php (General) | `platform_fee_percentage` | Global platform commission |
| Activator.php | `default_commission_rate` | Vendor default (NOT USED) |
| Database | `custom_commission_rate` | Per-vendor override |

**Issues:**
- `default_commission_rate` in Activator is never used
- Term "platform_fee" is ambiguous (commission vs gateway fee)

---

### 4. Duplicate Settings Locations

| Setting | Current Location | Should Be |
|---------|------------------|-----------|
| Platform Fee % | General Tab | Commission Tab (new) |
| Min Payout Amount | Vendor Tab | Payouts Tab (new) |
| Commission Rate | General Tab | Commission Tab (new) |

---

## Current Settings Structure

### Tab: General (`wpss_general`)
| Field | Key | Type | Default |
|-------|-----|------|---------|
| Platform Name | `platform_name` | text | Site name |
| Currency | `currency` | select | USD |
| Platform Fee (%) | `platform_fee_percentage` | number | 10 |
| E-Commerce Platform | `ecommerce_platform` | select | auto |

### Tab: Vendor (`wpss_vendor`)
| Field | Key | Type | Default |
|-------|-----|------|---------|
| Vendor Registration | `vendor_registration` | select | open |
| Max Services per Vendor | `max_services_per_vendor` | number | 20 |
| Require Verification | `require_verification` | checkbox | false |
| Minimum Payout Amount | `min_payout_amount` | number | 50 |
| Service Moderation | `require_service_moderation` | checkbox | false |

### Tab: Orders (`wpss_orders`)
| Field | Key | Type | Default |
|-------|-----|------|---------|
| Auto-Complete Days | `auto_complete_days` | number | 3 |
| Default Revision Limit | `revision_limit` | number | 2 |
| Allow Disputes | `allow_disputes` | checkbox | true |
| Dispute Window (Days) | `dispute_window_days` | number | 14 |

### Tab: Notifications (`wpss_notifications`)
| Field | Key | Type | Default |
|-------|-----|------|---------|
| New Order | `notify_new_order` | checkbox | true |
| Order Completed | `notify_order_completed` | checkbox | true |
| Order Cancelled | `notify_order_cancelled` | checkbox | true |
| Delivery Submitted | `notify_delivery_submitted` | checkbox | true |
| Revision Requested | `notify_revision_requested` | checkbox | true |
| New Message | `notify_new_message` | checkbox | true |
| New Review | `notify_new_review` | checkbox | true |
| Dispute Opened | `notify_dispute_opened` | checkbox | true |

### Tab: Pages (`wpss_pages`)
| Field | Key | Type |
|-------|-----|------|
| Services Page | `services_page` | page select |
| Dashboard | `dashboard` | page select |
| Become a Vendor | `become_vendor` | page select |

### Tab: Advanced (`wpss_advanced`)
| Field | Key | Type | Default |
|-------|-----|------|---------|
| Delete Data on Uninstall | `delete_data_on_uninstall` | checkbox | false |
| Debug Mode | `enable_debug_mode` | checkbox | false |

---

## Proposed Settings Structure

### Tab: General (`wpss_general`)
| Field | Key | Type | Default | Notes |
|-------|-----|------|---------|-------|
| Platform Name | `platform_name` | text | Site name | KEEP |
| Currency | `currency` | select | USD | KEEP |
| E-Commerce Platform | `ecommerce_platform` | select | auto | KEEP |

### Tab: Commission (`wpss_commission`) - NEW
| Field | Key | Type | Default | Notes |
|-------|-----|------|---------|-------|
| Platform Commission (%) | `commission_rate` | number | 10 | Renamed from platform_fee_percentage |
| Commission Paid By | `commission_payer` | select | vendor | NEW |
| Enable Per-Vendor Rates | `enable_vendor_rates` | checkbox | true | NEW |

### Tab: Payment Gateways (`wpss_gateways`) - NEW (Pro)
Each gateway as sub-section:
- Stripe: enabled, keys, gateway fee %, fee payer
- PayPal: enabled, credentials, gateway fee %, fee payer
- Razorpay: enabled, credentials, gateway fee %, fee payer

### Tab: Tax (`wpss_tax`) - NEW
| Field | Key | Type | Default | Notes |
|-------|-----|------|---------|-------|
| Enable Tax | `tax_enabled` | checkbox | false | NEW |
| Tax Handling | `tax_handling` | select | buyer | NEW |
| Default Tax Rate | `tax_rate` | number | 0 | NEW |
| Tax Label | `tax_label` | text | Tax | NEW |
| Require Vendor Tax ID | `require_tax_id` | checkbox | false | NEW |

### Tab: Payouts (`wpss_payouts`) - NEW
| Field | Key | Type | Default | Notes |
|-------|-----|------|---------|-------|
| Minimum Withdrawal | `min_withdrawal` | number | 50 | Moved & renamed |
| Withdrawal Methods | `withdrawal_methods` | multi-select | paypal,bank | NEW |
| Clearance Period (Days) | `clearance_days` | number | 14 | NEW |
| Enable Auto-Withdrawal | `auto_withdrawal_enabled` | checkbox | false | NEW |
| Auto-Withdrawal Schedule | `auto_withdrawal_schedule` | select | monthly | NEW |
| High-Earner Threshold | `auto_withdrawal_threshold` | number | 500 | NEW |

### Tab: Vendor (`wpss_vendor`)
| Field | Key | Type | Default | Notes |
|-------|-----|------|---------|-------|
| Vendor Registration | `vendor_registration` | select | open | KEEP |
| Max Services | `max_services_per_vendor` | number | 20 | KEEP |
| Require Verification | `require_verification` | checkbox | false | KEEP |
| Service Moderation | `require_service_moderation` | checkbox | false | KEEP |
| ~~Min Payout Amount~~ | ~~`min_payout_amount`~~ | - | - | MOVED to Payouts |

### Tab: Orders (`wpss_orders`)
UNCHANGED

### Tab: Notifications (`wpss_notifications`)
Add new notification types:
| Field | Key | Type | Default |
|-------|-----|------|---------|
| Withdrawal Requested | `notify_withdrawal_requested` | checkbox | true |
| Withdrawal Processed | `notify_withdrawal_processed` | checkbox | true |
| Auto-Withdrawal Processed | `notify_auto_withdrawal` | checkbox | true |

### Tab: Pages (`wpss_pages`)
UNCHANGED

### Tab: Advanced (`wpss_advanced`)
UNCHANGED

---

## Fixes Required (In Order)

### Phase 0: Fix Broken Settings (Critical)

1. **Fix Activator option names:**
   ```php
   // Change from:
   'wpss_general_settings' => ...
   'wpss_vendor_settings' => ...

   // To:
   'wpss_general' => ...
   'wpss_vendor' => ...
   ```

2. **Fix minimum withdrawal references:**
   ```php
   // Change from:
   get_option('wpss_min_withdrawal', 50)

   // To (temporary fix):
   Settings::get('vendor', 'min_payout_amount', 50)

   // Or (after restructure):
   Settings::get('payouts', 'min_withdrawal', 50)
   ```

### Phase 1: Settings Restructure

1. Add new tabs: Commission, Payouts, Tax, Payment Gateways
2. Move `platform_fee_percentage` → `wpss_commission['commission_rate']`
3. Move `min_payout_amount` → `wpss_payouts['min_withdrawal']`
4. Add migration for existing settings
5. Update all code references

### Phase 2: New Features

1. Add gateway fee tracking
2. Add tax configuration
3. Add automatic withdrawals
4. Add clearance period

---

## Migration Script Needed

```php
/**
 * Migrate settings from old structure to new.
 */
function wpss_migrate_settings_v2() {
    $version = get_option('wpss_settings_version', '1.0');

    if (version_compare($version, '2.0', '>=')) {
        return; // Already migrated
    }

    // 1. Migrate platform_fee_percentage to commission tab
    $general = get_option('wpss_general', []);
    if (isset($general['platform_fee_percentage'])) {
        $commission = get_option('wpss_commission', []);
        $commission['commission_rate'] = $general['platform_fee_percentage'];
        update_option('wpss_commission', $commission);

        unset($general['platform_fee_percentage']);
        update_option('wpss_general', $general);
    }

    // 2. Migrate min_payout_amount to payouts tab
    $vendor = get_option('wpss_vendor', []);
    if (isset($vendor['min_payout_amount'])) {
        $payouts = get_option('wpss_payouts', []);
        $payouts['min_withdrawal'] = $vendor['min_payout_amount'];
        update_option('wpss_payouts', $payouts);

        unset($vendor['min_payout_amount']);
        update_option('wpss_vendor', $vendor);
    }

    // 3. Clean up old options from Activator
    delete_option('wpss_general_settings');
    delete_option('wpss_vendor_settings');

    update_option('wpss_settings_version', '2.0');
}
```

---

## Summary of Issues

| Issue | Severity | Impact |
|-------|----------|--------|
| Option names mismatch (Activator vs Settings) | HIGH | Defaults never applied |
| `wpss_min_withdrawal` not registered | HIGH | Setting change ignored |
| `default_commission_rate` unused | MEDIUM | Dead code |
| Platform fee ambiguity | LOW | Confusing terminology |
| Settings scattered across tabs | LOW | Poor UX |

---

*Audit completed: 2026-01-12*
