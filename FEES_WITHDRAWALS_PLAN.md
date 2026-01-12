# WP Sell Services - Fees, Taxes & Withdrawals Architecture Plan

## Executive Summary

This document outlines the complete architecture for:
1. **Fee Structure** - Separating gateway fees from platform commission
2. **Tax Configuration** - Vendor tax settings and reporting
3. **Withdrawal System** - Automatic/threshold-based withdrawals

---

## Current State Analysis

### What Exists Now

| Component | Status | Issue |
|-----------|--------|-------|
| Platform Fee | ✅ Exists | Incorrectly labeled in gateway tabs, should be "Commission" |
| Gateway Fee (Stripe/PayPal) | ❌ Missing | Not tracked or deducted |
| Vendor Tax Settings | ❌ Missing | No tax configuration |
| Manual Withdrawals | ✅ Works | Requires admin approval |
| Auto Withdrawals | ❌ Missing | No automatic processing |
| Threshold Withdrawals | ❌ Missing | No high-earner auto-payout |

### Terminology Confusion (Current)

The term "Platform Fee" is overloaded:
- In General Settings: Means marketplace commission (correct usage)
- In Gateway tabs: Should be "Gateway Fee" (processor's cut)

---

## Proposed Fee Architecture

### Fee Types (Clear Separation)

```
┌─────────────────────────────────────────────────────────────────┐
│                     ORDER TOTAL ($100)                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. GATEWAY FEE (Payment Processor)                             │
│     └── Stripe: 2.9% + $0.30 = $3.20                           │
│     └── PayPal: 2.9% + $0.30 = $3.20                           │
│     └── Razorpay: 2% = $2.00                                   │
│                                                                 │
│  2. PLATFORM COMMISSION (Marketplace Cut)                       │
│     └── Global Default: 10% = $10.00                           │
│     └── OR Vendor-specific rate                                │
│                                                                 │
│  3. TAX (If applicable)                                         │
│     └── Collected from buyer OR                                │
│     └── Deducted from vendor earnings (configurable)           │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│  VENDOR RECEIVES:                                               │
│  $100 - $3.20 (gateway) - $10 (commission) = $86.80            │
│                                                                 │
│  PLATFORM RECEIVES:                                             │
│  $10.00 (commission) - gateway fees on commission portion      │
└─────────────────────────────────────────────────────────────────┘
```

### Who Pays What? (Configuration Options)

| Fee Type | Option 1 | Option 2 | Option 3 |
|----------|----------|----------|----------|
| Gateway Fee | Platform absorbs | Vendor pays | Split 50/50 |
| Platform Commission | Always from vendor earnings | - | - |
| Tax | Added to buyer | Included in price | Vendor responsible |

---

## Settings Structure (Proposed)

### Tab: General Settings
```
Platform Name: [text]
Currency: [select]
```

### Tab: Commission (NEW - Renamed from scattered locations)
```
╔══════════════════════════════════════════════════════════════╗
║ MARKETPLACE COMMISSION                                        ║
╠══════════════════════════════════════════════════════════════╣
║ Global Commission Rate: [10] %                                ║
║   └── Description: Percentage the marketplace keeps           ║
║                                                               ║
║ Commission Paid By: [Vendor ▼]                                ║
║   └── Options: Vendor | Added to Buyer                        ║
║                                                               ║
║ ☐ Enable per-vendor custom rates                              ║
║   └── Set individual rates in Vendors page                    ║
╚══════════════════════════════════════════════════════════════╝
```

### Tab: Payment Gateways (Each gateway as sub-section)

#### Stripe Section
```
╔══════════════════════════════════════════════════════════════╗
║ STRIPE SETTINGS                                               ║
╠══════════════════════════════════════════════════════════════╣
║ ☐ Enable Stripe                                               ║
║                                                               ║
║ API Keys:                                                     ║
║   Publishable Key: [sk_live_...]                              ║
║   Secret Key: [••••••••••••]                                  ║
║                                                               ║
║ Gateway Fee Configuration:                                    ║
║   Stripe charges approximately:                               ║
║   • Percentage: [2.9] %                                       ║
║   • Fixed Fee: [0.30] per transaction                         ║
║                                                               ║
║ Gateway Fee Paid By: [Vendor ▼]                               ║
║   └── Options: Platform Absorbs | Vendor | Split 50/50       ║
║                                                               ║
║ Webhook URL: [auto-generated, copy button]                    ║
╚══════════════════════════════════════════════════════════════╝
```

#### PayPal Section
```
╔══════════════════════════════════════════════════════════════╗
║ PAYPAL SETTINGS                                               ║
╠══════════════════════════════════════════════════════════════╣
║ ☐ Enable PayPal                                               ║
║                                                               ║
║ API Credentials:                                              ║
║   Client ID: [...]                                            ║
║   Secret: [••••••••••••]                                      ║
║   Mode: [Sandbox ▼] / Live                                    ║
║                                                               ║
║ Gateway Fee Configuration:                                    ║
║   PayPal charges approximately:                               ║
║   • Percentage: [2.9] %                                       ║
║   • Fixed Fee: [0.30] per transaction                         ║
║                                                               ║
║ Gateway Fee Paid By: [Vendor ▼]                               ║
╚══════════════════════════════════════════════════════════════╝
```

#### Razorpay Section
```
╔══════════════════════════════════════════════════════════════╗
║ RAZORPAY SETTINGS                                             ║
╠══════════════════════════════════════════════════════════════╣
║ ☐ Enable Razorpay                                             ║
║                                                               ║
║ API Credentials:                                              ║
║   Key ID: [...]                                               ║
║   Key Secret: [••••••••••••]                                  ║
║                                                               ║
║ Gateway Fee Configuration:                                    ║
║   Razorpay charges approximately:                             ║
║   • Percentage: [2.0] %                                       ║
║   • Fixed Fee: [0] per transaction                            ║
║                                                               ║
║ Gateway Fee Paid By: [Vendor ▼]                               ║
╚══════════════════════════════════════════════════════════════╝
```

### Tab: Tax Settings (NEW)
```
╔══════════════════════════════════════════════════════════════╗
║ TAX CONFIGURATION                                             ║
╠══════════════════════════════════════════════════════════════╣
║ ☐ Enable Tax Collection                                       ║
║                                                               ║
║ Tax Handling: [Added to Order Total ▼]                        ║
║   └── Options:                                                ║
║       • Added to Order Total (buyer pays)                     ║
║       • Included in Price (vendor responsible)                ║
║       • Platform handles (marketplace responsibility)         ║
║                                                               ║
║ Default Tax Rate: [0] %                                       ║
║   └── Can be overridden per service or vendor location        ║
║                                                               ║
║ Tax Label: [Tax ▼]                                            ║
║   └── Options: Tax | VAT | GST | Sales Tax | Custom           ║
║                                                               ║
║ ☐ Require vendors to provide Tax ID                           ║
║   └── Make tax ID mandatory for vendor registration           ║
║                                                               ║
║ ☐ Show tax breakdown in invoices                              ║
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║ TAX REPORTING                                                 ║
╠══════════════════════════════════════════════════════════════╣
║ Export Tax Report: [Select Year ▼] [Export CSV]               ║
║                                                               ║
║ Report includes:                                              ║
║   • Vendor name and Tax ID                                    ║
║   • Total earnings                                            ║
║   • Tax collected/withheld                                    ║
║   • Platform commission                                       ║
╚══════════════════════════════════════════════════════════════╝
```

### Tab: Payouts/Withdrawals (Enhanced)
```
╔══════════════════════════════════════════════════════════════╗
║ WITHDRAWAL SETTINGS                                           ║
╠══════════════════════════════════════════════════════════════╣
║ Minimum Withdrawal Amount: [$50]                              ║
║                                                               ║
║ Withdrawal Methods:                                           ║
║   ☑ PayPal                                                    ║
║   ☑ Bank Transfer                                             ║
║   ☐ Stripe Connect (Pro)                                      ║
║   ☐ Payoneer (Pro)                                            ║
║                                                               ║
║ Withdrawal Fee: [0] % + [$0] fixed                            ║
║   └── Platform fee for processing withdrawal                  ║
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║ AUTOMATIC WITHDRAWALS                                         ║
╠══════════════════════════════════════════════════════════════╣
║ ☐ Enable Automatic Withdrawals                                ║
║                                                               ║
║ Auto-Withdrawal Trigger: [Monthly ▼]                          ║
║   └── Options:                                                ║
║       • Monthly (1st of each month)                           ║
║       • Bi-weekly (1st & 15th)                                ║
║       • Weekly (every Monday)                                 ║
║       • Threshold-based only                                  ║
║                                                               ║
║ High-Earner Threshold: [$500]                                 ║
║   └── Auto-withdrawal when balance exceeds this amount        ║
║   └── Set to 0 to disable threshold-based                     ║
║                                                               ║
║ ☐ Require vendor to set preferred payment method              ║
║   └── Block auto-withdrawal if no method configured           ║
║                                                               ║
║ Auto-Withdrawal Status: [Approved ▼]                          ║
║   └── Options:                                                ║
║       • Approved (skip admin review)                          ║
║       • Pending (still requires admin approval)               ║
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║ EARNING CLEARANCE                                             ║
╠══════════════════════════════════════════════════════════════╣
║ Clearance Period: [14] days                                   ║
║   └── Days after order completion before earnings available   ║
║   └── Protects against chargebacks/disputes                   ║
║                                                               ║
║ ☐ Instant clearance for verified vendors                      ║
╚══════════════════════════════════════════════════════════════╝
```

---

## Database Schema Changes

### New Table: `wp_wpss_vendor_payment_methods`
```sql
CREATE TABLE wp_wpss_vendor_payment_methods (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    vendor_id bigint(20) NOT NULL,
    method varchar(50) NOT NULL,           -- paypal, bank_transfer, stripe_connect
    is_default tinyint(1) DEFAULT 0,
    details longtext,                      -- Encrypted JSON
    verified tinyint(1) DEFAULT 0,
    verified_at datetime,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_vendor (vendor_id),
    KEY idx_vendor_default (vendor_id, is_default)
);
```

### Update Table: `wp_wpss_orders`
Add columns:
```sql
ALTER TABLE wp_wpss_orders ADD COLUMN gateway_fee decimal(10,2) DEFAULT 0;
ALTER TABLE wp_wpss_orders ADD COLUMN gateway_fee_payer varchar(20) DEFAULT 'vendor';
ALTER TABLE wp_wpss_orders ADD COLUMN tax_amount decimal(10,2) DEFAULT 0;
ALTER TABLE wp_wpss_orders ADD COLUMN tax_rate decimal(5,2) DEFAULT 0;
```

### Update Table: `wp_wpss_vendor_profiles`
Add columns:
```sql
ALTER TABLE wp_wpss_vendor_profiles ADD COLUMN tax_id varchar(100) DEFAULT NULL;
ALTER TABLE wp_wpss_vendor_profiles ADD COLUMN tax_id_verified tinyint(1) DEFAULT 0;
ALTER TABLE wp_wpss_vendor_profiles ADD COLUMN preferred_payout_method varchar(50) DEFAULT NULL;
ALTER TABLE wp_wpss_vendor_profiles ADD COLUMN auto_withdrawal_enabled tinyint(1) DEFAULT 1;
```

### New Table: `wp_wpss_auto_withdrawal_log`
```sql
CREATE TABLE wp_wpss_auto_withdrawal_log (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    vendor_id bigint(20) NOT NULL,
    withdrawal_id bigint(20),
    trigger_type varchar(50),              -- monthly, threshold, manual
    amount decimal(10,2),
    status varchar(50),                    -- success, failed, skipped
    failure_reason text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    KEY idx_vendor (vendor_id),
    KEY idx_status (status)
);
```

---

## Updated Commission Calculation

### New Formula
```php
public function calculate( int $order_id ): array {
    $order = wpss_get_order( $order_id );
    $order_total = (float) $order->total;

    // 1. Calculate Gateway Fee
    $gateway_fee = $this->calculate_gateway_fee( $order );
    $gateway_fee_payer = $this->get_gateway_fee_payer();

    // 2. Calculate Tax (if applicable)
    $tax = $this->calculate_tax( $order );

    // 3. Calculate Platform Commission
    $commission_rate = $this->get_commission_rate( $order );
    $base_for_commission = $order_total; // Commission on full amount
    $platform_commission = round( $base_for_commission * ( $commission_rate / 100 ), 2 );

    // 4. Calculate Vendor Earnings
    $vendor_earnings = $order_total - $platform_commission;

    // Deduct gateway fee from vendor if configured
    if ( 'vendor' === $gateway_fee_payer ) {
        $vendor_earnings -= $gateway_fee;
    } elseif ( 'split' === $gateway_fee_payer ) {
        $vendor_earnings -= ( $gateway_fee / 2 );
    }

    return array(
        'order_total'         => $order_total,
        'gateway_fee'         => $gateway_fee,
        'gateway_fee_payer'   => $gateway_fee_payer,
        'tax_amount'          => $tax,
        'commission_rate'     => $commission_rate,
        'platform_commission' => $platform_commission,
        'vendor_earnings'     => max( 0, $vendor_earnings ),
    );
}
```

---

## Automatic Withdrawal System

### Cron Jobs to Add

```php
// In Activator.php
if ( ! wp_next_scheduled( 'wpss_process_auto_withdrawals' ) ) {
    wp_schedule_event( time(), 'daily', 'wpss_process_auto_withdrawals' );
}

if ( ! wp_next_scheduled( 'wpss_check_threshold_withdrawals' ) ) {
    wp_schedule_event( time(), 'hourly', 'wpss_check_threshold_withdrawals' );
}
```

### Auto Withdrawal Service (New Class)

```php
class AutoWithdrawalService {

    /**
     * Process scheduled withdrawals (monthly/weekly/bi-weekly)
     */
    public function process_scheduled_withdrawals(): void {
        $settings = get_option( 'wpss_withdrawals', array() );

        if ( empty( $settings['auto_withdrawal_enabled'] ) ) {
            return;
        }

        $schedule = $settings['auto_withdrawal_schedule'] ?? 'monthly';

        if ( ! $this->is_scheduled_day( $schedule ) ) {
            return;
        }

        $vendors = $this->get_eligible_vendors();

        foreach ( $vendors as $vendor ) {
            $this->create_auto_withdrawal( $vendor, 'scheduled' );
        }
    }

    /**
     * Process threshold-based withdrawals
     */
    public function process_threshold_withdrawals(): void {
        $settings = get_option( 'wpss_withdrawals', array() );
        $threshold = (float) ( $settings['threshold_amount'] ?? 500 );

        if ( $threshold <= 0 ) {
            return;
        }

        $vendors = $this->get_vendors_above_threshold( $threshold );

        foreach ( $vendors as $vendor ) {
            $this->create_auto_withdrawal( $vendor, 'threshold' );
        }
    }

    /**
     * Get vendors eligible for auto-withdrawal
     */
    private function get_eligible_vendors(): array {
        global $wpdb;

        $min = (float) get_option( 'wpss_min_withdrawal', 50 );

        // Get vendors with:
        // 1. Auto-withdrawal enabled
        // 2. Preferred payment method set
        // 3. Available balance >= minimum

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT vp.*,
                    (vp.net_earnings - COALESCE(w.withdrawn, 0) - COALESCE(w.pending, 0)) as available
             FROM {$wpdb->prefix}wpss_vendor_profiles vp
             LEFT JOIN (
                 SELECT vendor_id,
                        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as withdrawn,
                        SUM(CASE WHEN status IN ('pending', 'approved') THEN amount ELSE 0 END) as pending
                 FROM {$wpdb->prefix}wpss_withdrawals
                 GROUP BY vendor_id
             ) w ON vp.user_id = w.vendor_id
             WHERE vp.auto_withdrawal_enabled = 1
               AND vp.preferred_payout_method IS NOT NULL
               AND (vp.net_earnings - COALESCE(w.withdrawn, 0) - COALESCE(w.pending, 0)) >= %f",
            $min
        ) );
    }
}
```

---

## Implementation Order

### Phase 1: Settings Restructure (Foundation)
1. Create new "Commission" settings tab
2. Move platform_fee_percentage to Commission tab
3. Rename gateway "Platform Fee" → "Gateway Fee"
4. Add gateway fee configuration fields
5. Add "Gateway Fee Paid By" option

### Phase 2: Tax System
1. Create Tax settings tab
2. Add tax_amount, tax_rate columns to orders table
3. Add tax_id fields to vendor profiles
4. Update commission calculation to include tax
5. Add tax report export

### Phase 3: Withdrawal Enhancements
1. Create vendor payment methods table
2. Add vendor payment method management UI
3. Add preferred_payout_method to vendor profiles
4. Create Payouts/Withdrawals settings tab
5. Add clearance period setting

### Phase 4: Automatic Withdrawals
1. Create AutoWithdrawalService class
2. Add auto-withdrawal settings UI
3. Register cron jobs
4. Create auto_withdrawal_log table
5. Add threshold checking
6. Add vendor auto-withdrawal preferences

### Phase 5: Testing & Polish
1. Test all fee calculations
2. Test automatic withdrawals
3. Update earnings dashboard display
4. Add admin notifications for auto-withdrawals
5. Documentation

---

## User Interface Clarity

### Admin Dashboard - At a Glance
```
┌─────────────────────────────────────────────────────────────┐
│ WP SELL SERVICES - Today's Overview                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Orders Today: 15        Revenue: $1,500                    │
│                                                             │
│  Fee Breakdown:                                             │
│  ├── Gateway Fees Collected: $43.50                         │
│  ├── Platform Commission: $150.00                           │
│  └── Tax Collected: $120.00                                 │
│                                                             │
│  Vendor Payouts:                                            │
│  ├── Pending Approval: 5 ($750)                             │
│  ├── Auto-Withdrawals Today: 3 ($1,200)                     │
│  └── High-Earner Threshold Triggers: 2                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Vendor Dashboard - Earnings Breakdown
```
┌─────────────────────────────────────────────────────────────┐
│ YOUR EARNINGS                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Order Total:           $100.00                             │
│  ├── Gateway Fee:       - $3.20  (Stripe 2.9% + $0.30)     │
│  ├── Platform Fee:      - $10.00 (10% commission)           │
│  └── Your Earnings:     = $86.80                            │
│                                                             │
│  ──────────────────────────────────────────────────────     │
│                                                             │
│  Available for Withdrawal: $486.80                          │
│  Pending Clearance:        $200.00 (available in 14 days)  │
│  Pending Withdrawal:       $0.00                            │
│                                                             │
│  [Request Withdrawal] [Setup Auto-Withdrawal]               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Terminology Guide (For Site Owners)

| Term | What It Means | Who Pays |
|------|---------------|----------|
| **Gateway Fee** | Fee charged by Stripe/PayPal/Razorpay for processing payment | Configurable |
| **Platform Commission** | Marketplace's cut for hosting the service | Always Vendor |
| **Tax** | Government-required tax on services | Buyer or Vendor |
| **Withdrawal Fee** | Optional fee for processing vendor payouts | Vendor |
| **Clearance Period** | Days before earnings become available | N/A |

---

## Approval Required

Before implementation, please confirm:

1. [ ] Fee structure and who pays what
2. [ ] Tax handling approach (buyer vs vendor responsibility)
3. [ ] Automatic withdrawal schedule options
4. [ ] High-earner threshold default amount
5. [ ] Settings tab organization

---

*Plan created: 2026-01-12*
*Plugin: WP Sell Services v1.1.0*
