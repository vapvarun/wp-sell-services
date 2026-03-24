# WP Sell Services — Enterprise Feature & Capability Audit

**Date:** 2026-03-17 | **Free + Pro Combined | Based on Direct Codebase Analysis**

---

## 1. Service Marketplace Core

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Service CPT + Taxonomies | `wpss_service` CPT with hierarchical categories (icons/images) and flat tags | All | Free |
| Multi-Step Service Wizard | Guided vendor service creation with Pro field injection via `wpss_service_meta_fields` | Vendor | Free |
| Tiered Packages | Up to 3 pricing tiers (Basic/Standard/Premium) per service with price, delivery time, revisions, extras | Vendor | Free |
| Service Add-ons | Optional purchasable extras on top of packages | Buyer | Free |
| FAQ System | Per-service FAQ sections stored as JSON, rendered on detail page and via REST | Vendor | Free |
| Requirements System | Custom field definitions (text, file upload, checkbox, select, etc.) buyer must fill before work starts | Both | Free |
| Gallery & Media | Main thumbnail + gallery of images/videos per service | Vendor | Free |
| Service Moderation | Admin toggle to require approval before services go live | Admin | Free |

---

## 2. Order Management

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Full Order Lifecycle | `pending` → `accepted` → `in_progress` → `delivered` → `completed` with side branches to cancelled/disputed/refunded | All | Free |
| Delivery Workflow | Formal delivery submission with attachments, buyer approve/reject within revision limits | Both | Free |
| Revision System | Per-package revision count, tracked per order, escalation when exhausted | Both | Free |
| Milestone-Based Projects | Break orders into named milestones with own state machine, amounts, due dates, progress % | Both | Free |
| Deadline Extensions | Either party can request deadline extension, counterparty approves/declines | Both | Free |
| Cancellation Policies | Configurable cancellation rules, auto-cancel after 48h vendor non-response | Admin | Free |
| Tipping | Buyers send tips on completed orders, recorded in earnings | Buyer | Free |
| Manual Orders | Admin creates orders on behalf of any buyer/vendor pair (phone/email sales) | Admin | Free |

---

## 3. Payment Infrastructure

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Standalone Checkout | WooCommerce-independent cart & checkout via REST API | Buyer | Free |
| Stripe Gateway | PaymentIntent flow, webhook verification, replay protection | Buyer | Free |
| PayPal Gateway | Standard PayPal with order creation, capture, webhooks | Buyer | Free |
| Offline Gateway | Manual bank transfer / cash on delivery, admin marks paid | Admin | Free |
| WooCommerce Adapter | Creates WC products from packages, delegates checkout to WC, syncs orders | All | Pro |
| EDD Adapter | Full Easy Digital Downloads integration | All | Pro |
| FluentCart Adapter | Full FluentCart integration | All | Pro |
| SureCart Adapter | Full SureCart integration | All | Pro |
| Razorpay Gateway | Indian payment infrastructure | Buyer | Pro |
| Currency Config | Currency, symbol, position, decimals, min/max order amounts | Admin | Free |

---

## 4. Vendor Ecosystem

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Registration Modes | Open / Approval Required / Closed | Admin | Free |
| Vendor Dashboard | Single-page frontend dashboard with extensible sections via filter | Vendor | Free |
| Vendor Profile | Display name, tagline, bio, location, language, website, response rate, avatar, cover image | Vendor | Free |
| Portfolio | Showcase items (images/links) with title, description, reorder support | Vendor | Free |
| Seller Levels | 4-tier reputation: New Seller → Level 1 → Level 2 → Top Rated (data-driven criteria, weekly cron recalculation) | Vendor | Free |
| Vacation Mode | Pause listings and show away badge | Vendor | Free |
| Vendor Search | Search by name/tagline/bio with rating and country filters, autocomplete | Buyer | Free |
| Stripe Connect Onboarding | Full Express/Standard Stripe Connect flow with account status tracking | Vendor | Pro |

---

## 5. Buyer Experience

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Service Discovery | Category/tag archives, search, filtering by price/rating/category, sorting | Buyer | Free |
| Unified Search | Cross-entity search (services + vendors) with autocomplete suggestions | Buyer | Free |
| Popular Search Tracking | Top 100 trending terms tracked and exposed for UI widgets | Buyer | Free |
| Related Services | Tag-first, category-supplemented recommendations on every detail page | Buyer | Free |
| Buyer Requests / Job Board | Buyers post project needs with budget range and deadline, vendors submit proposals | Both | Free |
| Proposals | Vendors bid on buyer requests; accepted proposals auto-create orders | Both | Free |
| Favorites / Wishlist | Save services for later | Buyer | Free |
| Reviews & Ratings | Star ratings with moderation option, vendor rating aggregation | Buyer | Free |
| Contact Me / Direct Messages | Pre-order conversations with vendors | Buyer | Free |

---

## 6. Communication System

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Order Messaging | Per-order conversation threads with file attachments and read receipts | Both | Free |
| In-App Notifications | Bell icon inbox with mark read/delete via REST | All | Free |
| Email Notifications | 11+ email types with per-type toggles and customizable subject/body | All | Free |
| Email Branding | Custom logo, colors, footer in email templates | Admin | Pro |

---

## 7. Commission & Revenue

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Platform Commission | Flat % deducted on order completion | Admin | Free |
| Tiered Commission Rules | Category-based, seller-level-based, and volume-based rules with priority ordering | Admin | Pro |
| Vendor Subscription Plans | Plans with service limits, commission overrides, Stripe billing | Admin/Vendor | Pro |
| Earnings Dashboard | Total earned, pending, available balance, payout history via REST | Vendor | Free |
| Withdrawal Management | Vendors request, admins approve and process | Both | Free |
| PayPal Payouts | Batch mass-payout to all eligible vendors via PayPal Payouts API | Admin | Pro |
| Wallet Integrations | Internal wallet + TeraWallet + WooWallet + MyCred providers | Vendor | Pro |

---

## 8. Dispute Resolution

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Dispute Opening | Either party opens dispute, order transitions to `disputed` | Both | Free |
| Evidence Submission | Text, image, file, link evidence types stored as JSON | Both | Free |
| Formal Response | Both parties submit responses with attachments | Both | Free |
| Escalation | Escalation to admin mediation with reason | Both | Free |
| Admin Resolution | Assign to admin, resolve with refund amount, full audit timeline | Admin | Free |

---

## 9. Analytics & Reporting

| Feature | Description | Who | Tier |
|---------|-------------|-----|------|
| Vendor Analytics | Personal overview, revenue time series, orders breakdown, top services | Vendor | Pro |
| Admin Analytics Dashboard | Chart.js charts, 4 collectors, period presets, extensible widget system | Admin | Pro |
| Data Export | CSV and PDF export of analytics data | Admin | Pro |

---

## 10. Enterprise Pro Features

| Feature | Description | Tier |
|---------|-------------|------|
| Stripe Connect Split Payments | Auto-transfer vendor share to connected Stripe account on every payment | Pro |
| Recurring Services | Subscription billing (weekly/monthly/quarterly/yearly) via Stripe Subscriptions | Pro |
| White Label Branding | Custom brand name, colors, admin menu label, email branding, hide attribution | Pro |
| Cloud Storage | S3, Google Cloud Storage, DigitalOcean Spaces for delivery files | Pro |

---

## 11. Admin Controls

| Feature | Tier |
|---------|------|
| 8+ Settings Tabs (General, Pages, Payments, Gateways, Vendor, Orders, Emails, Advanced) | Free |
| Setup Wizard (guided first-run config) | Free |
| Service Moderation Queue | Free |
| Vendor Management (approve/suspend/reset) | Free |
| Withdrawals Management | Free |
| Orders Admin Table with filtering | Free |
| Disputes Admin Table | Free |
| Pro tabs: License, Analytics, Integrations, Stripe Connect, Subscriptions, Tiered Commission, Recurring, White Label, PayPal Payouts | Pro |

---

## 12. Developer Extensibility

| Feature | Details | Tier |
|---------|---------|------|
| REST API | 20 free + 10 Pro controllers under `wpss/v1`, mobile-ready | Free/Pro |
| Batch API | Up to 25 sub-requests per call (mobile optimization) | Free |
| CORS Support | Configurable allowed origins | Free |
| WP-CLI | `wp wpss validate schema/models/all` | Free |
| Template Overrides | Theme-overridable PHP templates | Free |
| Gutenberg Blocks | 6 blocks (ServiceGrid, FeaturedServices, SellerCard, BuyerRequests, ServiceSearch, ServiceCategories) | Free |
| Shortcodes | 13 shortcodes with attribute support | Free |
| Custom Fields System | 8 field types with FieldManager/Renderer/Validator pipeline | Free |
| 30+ Action/Filter Hooks | Full extension architecture for Pro and third-party plugins | Free |

---

## 13. SEO

| Feature | Tier |
|---------|------|
| SEO-friendly URLs for services, categories, tags, vendor profiles | Free |
| Schema markup on service pages | Free |
| Yoast / RankMath compatible (show_in_rest CPTs) | Free |
| Popular search tracking (trending searches widget data) | Free |

---

## 14. Security

| Feature | Tier |
|---------|------|
| WordPress Capabilities & Roles (wpss_manage_services, manage_options gating) | Free |
| Nonce Verification on all AJAX/REST operations | Free |
| Input Sanitization (sanitize_text_field, wp_kses_post, etc.) | Free |
| Webhook Signature Verification (Stripe HMAC-SHA256) | Free |
| REST Schema Validation (type, enum, required, validate_callback) | Free |
| Rate Limiting on auth endpoints (5 login/5min, 3 register/10min) | Free |
| EDD Software Licensing for Pro | Pro |

---

**Summary:** 70+ distinct features across 14 enterprise categories. Free covers core marketplace end-to-end. Pro adds enterprise revenue infrastructure (Stripe Connect, tiered commissions, recurring billing, vendor subscriptions, batch PayPal payouts, cloud storage, white label, analytics).
