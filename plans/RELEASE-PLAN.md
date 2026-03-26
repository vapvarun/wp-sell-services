# WP Sell Services - Release Plan

> Single source of truth: what is built, roadmap status, pre-release checklist, security checklist, out of scope.

Last Updated: 2026-03-24

---

## Current State

| Metric | Free | Pro |
|--------|------|-----|
| Version | 1.0.0 | 1.0.0 |
| DB Version | 1.3.9 | 1.0.0 |
| PHP Required | 8.1+ | 8.1+ |
| WP Required | 6.4+ | 6.4+ |
| Custom DB Tables | 17 | 7 |
| REST Endpoints | 80+ | 40+ |
| AJAX Handlers | 70+ | 20 |

---

## What is Built (Complete)

### Free Plugin
- Service marketplace with CPTs (wpss_service, wpss_request)
- Standalone checkout with Offline/Stripe/PayPal gateways
- Full order lifecycle (17 statuses, state machine enforcement)
- Vendor registration, approval, profiles, portfolios
- Buyer requests and vendor proposals
- Order messaging (polling-based, 10s interval)
- Deliveries with revisions tracking
- Disputes with admin mediation
- Reviews with multi-criteria ratings
- Commission system (flat rate, per-vendor overrides)
- Wallet/earnings with withdrawal requests
- Seller levels (auto-recalculated weekly)
- Service wizard (create/edit flow)
- Unified vendor dashboard (all sections)
- 11 cron jobs for automation
- Cascade deletion handler for data integrity
- Mobile-ready REST API with batch endpoint (25 sub-requests)
- Gutenberg blocks and shortcodes
- SEO schema markup
- Rate limiting on all sensitive operations
- Email notification system (11 templates)
- Template override system for themes

### Pro Plugin
- EDD Software Licensing integration
- WooCommerce, EDD, FluentCart, SureCart adapters
- Razorpay payment gateway
- Vendor wallet (Internal, TeraWallet, WooWallet, MyCred)
- Advanced admin analytics with Chart.js
- Vendor frontend analytics dashboard
- Tiered commission rules (priority-based)
- Vendor subscription plans with Stripe billing
- Stripe Connect (Express accounts, split payments)
- Recurring services with Stripe subscriptions
- PayPal Payouts (batch vendor payments)
- White labeling (admin, dashboard, emails)
- Cloud storage (AWS S3, Google Cloud, DigitalOcean Spaces)
- WordPress 6.9+ Abilities API integration

---

## Bug Fixes (2026-03-24 Sprint)

16 Basecamp cards fixed, commented, moved to Ready for Testing.

| # | Fix | Plugin |
|---|-----|--------|
| 1 | Price comparison normalization (monthly equiv) | Pro |
| 2 | Duplicate analytics admin menu removed | Pro |
| 3 | wpColorPicker DOM-ready wrap | Pro |
| 4 | Vendor subscription plan selection + migration | Both |
| 5 | Subscription enforcement on frontend wizard | Free |
| 6 | Profile Views meta key mismatch | Pro |
| 7 | Offline payment auto-cancel cron job | Free |
| 8 | Commission on pre-tax base | Free |
| 9 | Deletion cascade handler | Free |
| 10 | Currency hardcoded USD fix | Free |
| 11 | Dual status update paths unified | Free |
| 12 | Self-purchase blocked in AJAX gateway | Free |
| 13 | Addons in AJAX gateway order creation (7 files) | Free |
| 14 | PayPal webhook signature bypass fix | Free |
| 15 | Stripe HTTP error handling | Free |
| 16 | UI issues verified (order overlap, analytics tab) | Pro |

---

## Security Fixes (2026-03-24 Audit)

| # | Fix | Plugin | Severity |
|---|-----|--------|----------|
| 1 | IDOR on reject_proposal (added ownership check) | Free | CRITICAL |
| 2 | ROLLBACK on failed wallet insert | Free | HIGH |
| 3 | extract() with EXTR_SKIP flag | Free | MEDIUM |
| 4 | Nonce verification order + wp_unslash | Pro | CRITICAL |
| 5 | SSL verification enabled on license API | Pro | CRITICAL |
| 6 | Wallet withdrawal form (actual REST call) | Pro | CRITICAL |
| 7 | Date format validation on analytics ranges | Pro | HIGH |

---

## Pre-Release Checklist

### Code Quality
- [ ] Run composer phpcs on both plugins
- [ ] Run composer phpcbf for auto-fixable issues
- [ ] Run php -l on all PHP files
- [ ] Run npm run build for production assets
- [ ] Verify composer install --no-dev for production

### Security (Verified in Audit)
- [x] All AJAX handlers have nonce checks
- [x] All REST endpoints have permission callbacks
- [x] No eval(), exec(), system() calls
- [x] All DB queries use $wpdb->prepare()
- [x] All output escaped (esc_html, esc_attr, esc_url)
- [x] No hardcoded credentials or API keys
- [x] ABSPATH guard on all PHP files (160 files)
- [x] strict_types=1 on all source files (139 files)
- [ ] Fix remaining: guest vote options table bloat
- [ ] Fix remaining: live_search rate limiting for guests
- [ ] Fix remaining: .html() to .text() in admin JS

### Testing
- [ ] Run PHPUnit test suite (46 Pro + 7 Free test files)
- [ ] Browser test all dashboard sections
- [ ] Test full order lifecycle
- [ ] Test Stripe/PayPal payment flows
- [ ] Test vendor registration to service creation
- [ ] Test cascade deletion
- [ ] Test commission calculation with tax

### Documentation
- [ ] Update version constants
- [ ] Update changelog
- [ ] Publish docs via wbcom-docs MCP tool

---

## Remaining Advisory Items

| Issue | Plugin | Priority |
|-------|--------|----------|
| Guest vote rows bloat wp_options (use transients) | Free | Medium |
| Live search no rate limiting for guests | Free | Medium |
| .html() XSS-ready pattern in admin JS | Pro | Medium |
| Raw IN() clause in SubscriptionSettingsRenderer | Pro | Low |
| Inline style blocks in templates (should be CSS files) | Pro | Low |
| maybe_unserialize() usage (prefer json_decode) | Free | Low |
| Dead code: TemplateLoader::add_rewrite_rules() | Free | Low |
| Dual AJAX + REST handlers for same features | Free | Low |

---

## Out of Scope (v1.0)

- Real-time messaging (WebSocket)
- Multi-currency support
- Multi-vendor checkout
- Mobile app (REST API ready, app not built)
- AI-powered service matching
- Video consultations / live sessions
- Escrow payment holding
