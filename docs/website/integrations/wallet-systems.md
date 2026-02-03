# Wallet Systems

**[PRO]** Integrate wallet systems for vendor earnings management and flexible payout options.

## Wallet Integration Overview

Wallet systems allow vendors to accumulate earnings in a virtual wallet before withdrawing to their bank account.

### Why Use Wallets?

**Benefits:**
- Vendors accumulate earnings before withdrawal
- Reduce payment processing fees (batch withdrawals)
- Flexible payout schedules
- Instant balance visibility
- Bonus and credit systems
- Multi-currency support

**Use Cases:**
- Marketplaces with frequent small transactions
- Platforms wanting to reduce payout frequency
- Sites offering vendor bonuses/credits
- Markets with delayed payouts

## Supported Wallet Systems

| Wallet System | Type | Best For |
|---------------|------|----------|
| **Internal Wallet** **[PRO]** | Built-in | Complete control, no dependencies |
| **TeraWallet** **[PRO]** | WooCommerce extension | WooCommerce-based marketplaces |
| **WooWallet** **[PRO]** | WooCommerce wallet | WooCommerce integration |
| **MyCred** **[PRO]** | Points system | Gamification, loyalty programs |

---

## Internal Wallet System (Pro)

**[PRO]** Built-in wallet system with no external dependencies.

### Internal Wallet Features

**Vendor Wallet:**
- Automatic earnings deposit
- Real-time balance display
- Withdrawal requests
- Transaction history
- Multiple withdrawal methods

**Admin Controls:**
- Approve/reject withdrawals
- Add/subtract credits manually
- Set minimum withdrawal amount
- Configure payout schedules
- View all transactions

### Setup Internal Wallet

**Enable Wallet:**

1. Go to **WP Sell Services → Settings → Wallets**
2. Select **Wallet System:** Internal Wallet
3. Configure settings:
   - Minimum withdrawal amount
   - Maximum withdrawal amount
   - Withdrawal methods
   - Auto-approval threshold
4. Save changes

**Configuration Options:**

**Minimum Withdrawal:**
- Set minimum amount vendors can withdraw
- Prevents excessive small withdrawals
- Typical: $50-100

**Withdrawal Methods:**
- PayPal (email address)
- Bank transfer (account details)
- Stripe (automatic)
- Wire transfer
- Other custom methods

**Processing Time:**
- Instant (auto-approved below threshold)
- Manual review (requires admin approval)
- Scheduled (batch processing on specific days)

### Vendor Wallet Usage

**Earning Accumulation:**

1. Vendor completes order
2. Buyer accepts delivery
3. Earnings credited to wallet automatically
4. Commission deducted before crediting
5. Vendor sees updated balance

**Example:**
```
Order Total: $100
Commission (15%): $15
Credited to Wallet: $85
Previous Balance: $200
New Balance: $285
```

**Withdrawal Process:**

1. Vendor goes to **Dashboard → Wallet**
2. Clicks **Request Withdrawal**
3. Enters withdrawal amount
4. Selects payment method
5. Provides payment details (PayPal email, bank account, etc.)
6. Submits request
7. Admin reviews and approves/rejects
8. Vendor receives payment
9. Balance updated

**Transaction History:**

Vendors see:
- Order earnings (credit)
- Withdrawals (debit)
- Bonuses/credits (credit)
- Refund adjustments (debit)
- Current balance
- Pending balance (orders in progress)

### Admin Wallet Management

**Withdrawal Requests:**

1. Go to **WP Sell Services → Withdrawals**
2. View pending requests
3. Click request to review
4. Verify vendor details
5. Check withdrawal amount
6. Approve or reject with reason
7. Process payment externally
8. Mark as paid in system

**Manual Adjustments:**

Add/remove credits manually:

**Use Cases:**
- Award vendor bonus
- Promotional credits
- Adjust for errors
- Platform-specific incentives

**Process:**
1. Go to vendor profile
2. Navigate to **Wallet** section
3. Click **Add Credit** or **Deduct Credit**
4. Enter amount and reason
5. Save adjustment
6. Vendor notified via email

### Internal Wallet Reports

**Admin Analytics:**

View wallet statistics:
- Total wallet balance (all vendors)
- Pending withdrawals
- Processed withdrawals (by period)
- Average withdrawal amount
- Withdrawal frequency
- Vendors by balance

**Export Reports:**
- CSV export of transactions
- Withdrawal history
- Vendor balances
- Tax reporting data

---

## TeraWallet Integration (Pro)

**[PRO]** Popular WooCommerce wallet extension integration.

### Why TeraWallet?

**Advantages:**
- Mature WooCommerce wallet plugin
- Partial payment support
- Cashback features
- Transfer between users
- Strong admin controls

**Best For:**
- WooCommerce-based marketplaces
- Platforms wanting advanced wallet features
- Sites with buyer wallets too (not just vendors)

### TeraWallet Setup

**Requirements:**
- WooCommerce
- TeraWallet plugin (purchase separately)
- WP Sell Services Pro

**Installation:**

1. Purchase TeraWallet from WooCommerce.com
2. Install and activate TeraWallet plugin
3. Go to **WooCommerce → TeraWallet → Settings**
4. Configure TeraWallet options
5. Go to **WP Sell Services → Settings → Wallets**
6. Select **Wallet System:** TeraWallet
7. Save changes

**TeraWallet Configuration:**

**Wallet Settings:**
- Enable wallet for all users or specific roles
- Set minimum wallet balance
- Configure transaction fees (if any)
- Enable/disable user-to-user transfers

**Withdrawal Settings:**
- Minimum withdrawal amount
- Maximum withdrawal per period
- Withdrawal methods
- Auto-approval rules

### TeraWallet Features

**Vendor Wallets:**
- Earnings deposited to TeraWallet balance
- Withdraw via configured methods
- View detailed transaction logs
- Receive email notifications

**Buyer Wallets:**
- Buyers can also have wallets
- Pay for services using wallet balance
- Receive refunds to wallet
- Top up wallet balance

**Advanced Features:**
- Cashback on purchases
- Wallet recharge bonuses
- Scheduled payouts
- Bulk wallet actions

---

## WooWallet Integration (Pro)

**[PRO]** Alternative WooCommerce wallet plugin.

### Why WooWallet?

**Advantages:**
- Lightweight
- Free (basic version)
- Simple setup
- QR code payments
- Referral bonuses

**Best For:**
- Budget-conscious marketplaces
- Simple wallet needs
- WooCommerce stores

### WooWallet Setup

**Installation:**

1. Install WooWallet plugin (free or pro)
2. Activate WooWallet
3. Go to **WooCommerce → WooWallet → Settings**
4. Configure wallet options
5. Go to **WP Sell Services → Settings → Wallets**
6. Select **Wallet System:** WooWallet
7. Save changes

**WooWallet Features:**
- User wallet balances
- Add funds to wallet
- Partial payments
- Email credits
- Cashback rules
- Referral system

---

## MyCred Integration (Pro)

**[PRO]** Points-based system integration for gamified marketplaces.

### Why MyCred?

**Advantages:**
- Points instead of currency
- Gamification features
- Badges and ranks
- Multiple point types
- Extensive add-ons

**Best For:**
- Community-driven marketplaces
- Platforms with gamification
- Sites rewarding engagement
- Educational/skill-based platforms

### MyCred Setup

**Requirements:**
- MyCred plugin (free)
- MyCred Pro (for some features)
- WP Sell Services Pro

**Installation:**

1. Install MyCred plugin
2. Activate MyCred
3. Go to **MyCred → Settings**
4. Create point type (e.g., "Credits")
5. Go to **WP Sell Services → Settings → Wallets**
6. Select **Wallet System:** MyCred
7. Choose point type for vendor earnings
8. Save changes

### MyCred Point Types

**Example Point Systems:**

**Vendor Points:**
- 100 points = $1
- Vendors earn points for completed orders
- Redeem points for cash
- Bonus points for high ratings

**Buyer Points:**
- Earn points for purchases
- Spend points on services
- Loyalty rewards
- Referral bonuses

### MyCred Features

**Earning Points:**
- Order completion
- First sale bonus
- High rating bonus
- Milestone achievements
- Referral rewards

**Spending Points:**
- Convert to cash withdrawals
- Upgrade services
- Promotional features
- Platform perks

**Gamification:**
- Vendor levels/ranks
- Achievement badges
- Leaderboards
- Progress tracking

---

## Wallet vs Direct Payout Comparison

### Wallet System

**Pros:**
- Vendors accumulate earnings
- Reduced payout frequency = lower fees
- Flexibility in withdrawal timing
- Platform retains funds longer
- Bonuses and credits possible

**Cons:**
- Vendors wait for withdrawals
- Manual withdrawal processing (unless automated)
- Platform liability (holding funds)
- More complex accounting

**Best For:**
- Marketplaces with many small orders
- Platforms wanting to reduce fees
- Sites offering bonuses/credits

### Direct Payout

**Pros:**
- Vendors receive money immediately
- No withdrawal requests
- Simple for vendors
- No platform liability

**Cons:**
- Higher payment processing fees
- No earning accumulation
- Limited bonus/credit options
- Each payout incurs fee

**Best For:**
- Transparency-focused platforms
- High-value service marketplaces
- Vendors preferring instant payment

---

## Configuring Wallet Settings

### Minimum Withdrawal Amount

**Purpose:**
- Prevent excessive small withdrawals
- Reduce payment processing fees
- Batch vendor payouts

**Recommended Amounts:**
- Freelance marketplace: $50-100
- Digital products: $25-50
- High-value services: $200-500

**Configuration:**
```
Go to Settings → Wallets
Set "Minimum Withdrawal": $50
Vendors must reach $50 before withdrawing
```

### Withdrawal Schedules

**Options:**

**On Demand:**
- Vendors request withdrawal anytime
- Admin processes when requested
- Flexible but high admin workload

**Scheduled (Weekly/Monthly):**
- Fixed payout dates (e.g., every Friday)
- Batch process all requests
- Predictable for vendors and admin

**Automatic:**
- Withdrawals auto-process above threshold
- Requires payment gateway automation (Stripe Connect)
- Minimal admin intervention

**Example Schedule:**
```
Weekly Payouts: Every Friday
Process all withdrawal requests submitted by Thursday
Minimum: $50
Maximum: $5,000 per week
```

### Withdrawal Methods

**PayPal:**
- Vendor provides PayPal email
- Admin sends via PayPal
- Instant to vendor account
- Fee: ~2-3%

**Bank Transfer:**
- Vendor provides bank details
- Admin initiates wire transfer
- 1-5 business days
- Fee: $0-30 depending on bank

**Stripe Connect:** **[PRO]**
- Automatic via Stripe
- Instant transfer
- Vendor must connect Stripe
- Fee: Stripe standard rates

**Check/Wire:**
- Manual payment
- Slower (5-10 days)
- Good for large amounts
- Higher security

---

## Wallet Transaction Types

### Credit Transactions

**Order Earnings:**
- Vendor completes order
- Earnings credited to wallet
- Amount = order total - commission

**Bonuses:**
- Admin awards bonus
- Promotional credits
- Milestone rewards

**Refund Reversals:**
- Refunded order later re-paid
- Credit restored to wallet

### Debit Transactions

**Withdrawals:**
- Vendor requests payout
- Amount debited from wallet
- Transferred to vendor

**Refund Adjustments:**
- Order refunded
- Vendor's commission reversed
- Deducted from wallet balance

**Platform Fees:**
- Additional fees (if any)
- Listing fees
- Premium features

---

## Wallet Security

### Protecting Vendor Funds

**Security Measures:**
- Two-factor authentication for withdrawals
- Withdrawal confirmation emails
- IP address logging
- Suspicious activity detection
- Admin approval for large amounts

**Best Practices:**
- Regular security audits
- Encrypt sensitive payment details
- Monitor for fraud patterns
- Verify bank account ownership
- Limit daily withdrawal amounts

---

## Troubleshooting

### Wallet Balance Not Updating

**Check:**
1. Order status is "Completed"
2. Commission calculated correctly
3. No errors in transaction log
4. Cache cleared
5. Cron jobs running

### Withdrawal Request Failed

**Verify:**
1. Vendor has sufficient balance
2. Withdrawal amount meets minimum
3. Payment method configured
4. No account holds/restrictions
5. Admin approval granted (if manual)

### Payment Method Not Working

**Troubleshoot:**
1. Payment gateway API keys correct
2. Vendor payment details valid
3. No gateway account issues
4. Sufficient platform balance (for some gateways)
5. Test with small amount first

---

## Related Documentation

- [Payment Gateways](payment-gateways.md) - Payment processing
- [Payment Settings](../admin-settings/payment-settings.md) - Commission configuration
- [WooCommerce Setup](woocommerce-setup.md) - WooCommerce integration
- [Alternative E-commerce](alternative-ecommerce.md) - Platform options

---

## Next Steps

1. Choose wallet system for your marketplace
2. Configure wallet settings
3. Set minimum withdrawal amount
4. Configure payout methods
5. Test wallet and withdrawal flow
6. Educate vendors on wallet usage
