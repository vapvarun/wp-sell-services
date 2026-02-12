# Documentation Verification Findings

Total discrepancies found: ~138 across all 67 docs

## Critical Issues by Category

### Service Creation (20 issues)
- Wizard has 6 steps not 7; no SEO step
- Description char limit 5000 not 2000/10000
- Gallery image limit 4 not 5
- Image max size 5MB not 2MB
- Featured image 800x600 not 1280x720
- WebP supported but undocumented
- Addon field types: Radio/Multi-select don't exist; Quantity exists but undocumented
- 4 of 5 documented hooks don't exist
- Requirements field types: docs list 9, wizard offers 4
- Requirement file upload: docs say 6 types, code allows 24
- "Paused" and "Archived" are not registered post statuses
- Pro does NOT unlock unlimited packages (capped at 3)
- Pro does NOT increase tag limit (hardcoded at 5)
- Subcategory exists but not documented

### Order Management (12 issues)
- "Pending Acceptance" status doesn't exist; actual 11th status is `late`
- Status transitions differ significantly from docs
- Order number format wrong (WPSS-RANDOM-TIMESTAMP not WPSS-YYYYMM-NNNN)
- Auto-complete default 3 days not 7
- Requirements reminder at day 1, 3, 5 (not 24h, 48h, 5d)
- Requirements timeout configurable (not hardcoded 7 days), can auto-start
- `wpss_commission_rate` filter has 4 params not 3
- `wpss_order_status_changed` parameter order swapped
- 6 documented hooks don't exist in code
- File upload max 50MB not 10MB

### Messaging + Tipping + Extensions (14 issues)
- `wpss_message_allowed_file_types` hook doesn't exist
- Admin cannot reply to conversations (only view)
- Delivery valid from 3 statuses not just `in_progress`
- SVG removed from allowed types (XSS risk)
- Default max extension 14 days not 30
- 90-day tip window not enforced in code
- No minimum $1 tip enforcement
- Six message types undocumented

### Reviews + Disputes (12 issues)
- Sub-ratings: 3 not 4 (no "experience")
- Review window: single configurable (30 days default), not tiered
- Review moderation: simple boolean, no flagging/keyword detection
- Dispute statuses: 5 not 6
- Resolution type constants conflict between Model and Service
- Reviews CAN be edited via API (docs say cannot)

### Vendor System (12 issues)
- Seller levels: 4 in code, 3 in docs (wrong names and thresholds)
- Vacation mode: simple toggle, no dates/auto-return
- Registration modes: 3 in code, 2 in docs
- Dashboard sections don't match (Reviews/Notifications don't exist)
- Portfolio fields: Category/Completion Date/Client don't exist
- Vendor registration shortcode wrong (`[wpss_register]` vs `[wpss_vendor_registration]`)

### Payments (15 issues)
- WC carrier product name: "Service Order" not "Service Package Carrier"
- Carrier product status: `publish` (hidden) not `draft`
- Cart item meta keys fabricated
- All 3 webhook URLs wrong (use `/wpss-payment/*/callback/` not REST)
- Razorpay webhook secret no `whsec_` prefix
- WPSS statuses "Refunded" and "Failed" don't exist
- Reverse sync only covers 2 statuses, not 7
- Custom gateway base class `WPSS_Payment_Gateway` doesn't exist
- 3 of 5 standalone shortcodes don't exist

### Earnings + Commission (11 issues)
- Commission types: only percentage, no flat/hybrid
- Withdrawal statuses: 4 not 5 (no Processing/Cancelled; "Approved" not "Processing")
- Withdrawal methods: 2 built-in, not 4
- Commission priority: 2-level, not 3 (no tier-based)
- Commission timing: at completion, not creation
- Commission base config (include/exclude add-ons) doesn't exist

### Notifications + Shortcodes + SEO (18 issues)
- `[wpss_dashboard]` shortcode doesn't exist in code
- Almost every shortcode has wrong attributes documented
- `[wpss_services]` columns default 4 not 3
- Many shortcode attributes fabricated (autoplay, show_filters, etc.)
- SEO: 4 undocumented schema types; Review schema never output
- Vendor schema missing documented AggregateOffer

### Settings + Admin + API (12 issues)
- Only 3 required pages, not 4 (no Buyer Requests page)
- Only 10 currencies, not "15+"
- Advanced settings: only 2 fields, not dozens
- REST API: 20 controllers, not 8
- "Request Changes" moderation feature doesn't exist
- Only 3 cron events, not 6

### Buyer Requests + Proposals (12 issues)
- Request statuses: 4-5, not 7 (no Draft/In Progress/Completed)
- Proposal statuses: 4, not 6 (no Under Review/Expired)
- Budget types: 2, not 3 (no Flexible)
- Proposals CAN be edited (docs say cannot)
- Tags field doesn't exist on requests
- Order created immediately on acceptance, not after checkout
