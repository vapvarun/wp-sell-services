# Premium UX Audit + Docs Update Plan

## Scope
Full visual/UX audit of every frontend and backend page in WP Sell Services (free + pro). Fix UX issues and update docs simultaneously.

## Frontend Pages to Audit

### Public Pages
1. **Services Archive** (`/services/`) — grid layout, filters, search, pagination, empty state
2. **Single Service** — gallery, packages tabs, extras, reviews, FAQ, vendor card, portfolio section, related services
3. **Vendor Profile** (`/provider/{username}/`) — header, about, services grid, portfolio section, reviews, sidebar stats
4. **Buyer Requests Archive** — request cards, filters, pagination
5. **Single Buyer Request** — proposal form, existing proposals, request details

### Checkout Flow
6. **Cart Page** (standalone `[wpss_cart]`) — item list, remove, totals, proceed button
7. **Single Service Checkout** (standalone) — order summary, payment methods, gateway forms
8. **Multi-Service Checkout** (standalone) — multi-item summary, single payment
9. **WC Cart** — carrier product display, service data via filters
10. **WC Checkout** — Blocks checkout with service items in order summary

### Dashboard (Buyer)
11. **My Orders** — order list, status badges, pagination
12. **Order View** — conversation, delivery, requirements, dispute, actions
13. **Buyer Requests** — request list, create/edit request
14. **Messages** — conversation list, message thread
15. **Notifications** — notification list, read/unread
16. **Profile** — profile form, avatar

### Dashboard (Vendor)
17. **Sales Orders** — vendor order list, status filters
18. **My Services** — service list, status toggle, delete
19. **Create/Edit Service** (wizard) — all 6 steps
20. **Portfolio** — portfolio grid, add/edit modal
21. **Earnings** — stats, withdrawal form, transaction history
22. **Analytics** (Pro) — charts, date pickers, export

### Auth Pages
23. **Login** — form, validation, redirect
24. **Register** — form, vendor registration
25. **Become a Vendor** — registration flow

## Backend Pages to Audit

### Admin Pages
26. **Orders List** — table, filters, bulk actions, status badges
27. **Order Detail** — metabox, status dropdown, notes, requirements
28. **Services List** — CPT list, columns
29. **Service Moderation** — pending services, approve/reject
30. **Vendors Page** — vendor list, tabs (services/orders/earnings/reviews)
31. **Withdrawals** — pending/approved list, actions
32. **Disputes** — dispute list, view detail
33. **Analytics** (Pro) — admin dashboard, widgets
34. **Categories/Tags** — taxonomy management

### Settings Pages
35. **General Settings** — platform name, currency, pages
36. **Gateways Tab** — Stripe, PayPal, Razorpay, Offline accordion
37. **Orders Tab** — commission, auto-complete, disputes
38. **Payments Tab** — withdrawals, clearance, auto-payout
39. **Tax Tab** — enable, rate, label, inclusive
40. **Notifications Tab** — email toggles per type
41. **Advanced Tab** — delete data, custom fields
42. **License** (Pro) — activation, status

## UX Checklist Per Page

For each page, check:
- [ ] Responsive (desktop, tablet, mobile)
- [ ] Loading states (spinners, skeleton screens)
- [ ] Empty states (no data message, CTA)
- [ ] Error states (validation, failed actions)
- [ ] Consistent typography (design system tokens)
- [ ] Consistent spacing (--wpss-space-* variables)
- [ ] Consistent colors (--wpss-primary, status colors)
- [ ] Button hierarchy (primary, secondary, danger, outline)
- [ ] Status badges (consistent colors across all pages)
- [ ] Accessibility (focus states, aria labels, keyboard nav)
- [ ] RTL support (layout mirrors correctly)
- [ ] Hover/focus states on interactive elements
- [ ] Toast/notification feedback for actions
- [ ] No broken images or placeholder gaps
- [ ] Consistent header/footer across all pages

## Docs to Update Alongside

### Update existing docs
- `getting-started/initial-setup.md` — add screenshots of each settings tab
- `vendor-system/vendor-dashboard.md` — screenshot each dashboard tab
- `order-management/order-lifecycle.md` — screenshot order view states
- `payments-checkout/standalone-mode.md` — screenshot checkout flow
- `marketplace-display/shortcodes-reference.md` — screenshot each shortcode output

### Create new docs if missing
- Cart page documentation (new `[wpss_cart]` shortcode)
- Multi-service checkout flow documentation
- Portfolio public display documentation
- Test Gateway usage guide

## Execution Strategy

1. **Browser walkthrough** — open each page, take screenshots, flag issues
2. **CSS fixes** — use design system variables, consistent spacing/colors
3. **Template fixes** — empty states, loading states, error handling
4. **Screenshot capture** — save to `docs/website/images/` for doc updates
5. **Docs update** — embed screenshots, update descriptions

## Priority Order

1. Checkout flow (buyer-facing, revenue-critical)
2. Dashboard (daily-use by buyers + vendors)
3. Service pages (first impression)
4. Admin pages (admin daily-use)
5. Settings pages (one-time setup)
