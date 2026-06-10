# Frontend Shell Findings — input to the 1.2.0 ux-uplift waves

Owner-diagnosed on 2026-06-07 against the live model site (Reign 8.0.0,
1280px + 390px). These are ARCHITECTURE findings, not patch requests: the
shell-unification direction implements one coherent system; the per-surface
uplift directions then build on it. No finding here may be fixed with a
page-specific override.

## F1 - Two competing page-header systems (the "disconnected headers")

| Page | Header rendered by | Result |
|---|---|---|
| /services/ (converted archive) | PLUGIN `.wpss-archive-header` (title + tagline + divider + filter bar) | Plugin design language |
| /dashboard/ (page + shortcode) | THEME `h1.entry-title` ("Dashboard", Reign typography) + plugin panel below with its own section header | Theme design language, different size/weight/x-position |

Decision to implement: **the plugin app shell owns the page header on every
plugin surface** (archive, single, dashboard, account, vendor, requests,
cart/order). One `wpss-page-header` component: title, optional subtitle,
optional actions slot. The theme's entry-title is suppressed on plugin-shell
pages through a theme-agnostic compat layer (documented filter, no raw CSS
`display:none` hacks against specific theme selectors; Reign verified, other
themes get the documented filter). This is the WooCommerce-grade approach:
plugin surfaces feel like one product regardless of theme.

## F2 - Triple-nested width containers

Current chain on archive pages:

```
.container (Reign, max-width 1170px, own gutters)
  └─ .wpss-app-shell__container (max-width 1200px, padding space-6/space-4)
       └─ .wpss-services-archive
            └─ .wpss-container (max-width 1200px OR 1400px, padding 20px)
```

Three max-widths + three gutter layers. Inner 1200/1400px caps are
unreachable inside the theme's 1170px box; content loses ~100px to stacked
padding and alignment shifts page-to-page (the services H1 and dashboard H1
start at different x positions).

Decision: containers are defined ONCE in design-system.css with tokens
(`--wpss-container-max`). When a wpss container is nested inside the app
shell (or any wpss container), the inner one collapses to fluid width and
zero horizontal padding. The shell defers to the theme's content width when
the theme provides a constrained container.

## F3 - Duplicate .wpss-container definitions (design-system drift)

- design-system.css:434 -> max-width 1200px, padding `var(--wpss-space-5)`
- frontend.css:92      -> max-width 1400px, padding 20px (raw px)

Loaded in that order, so frontend.css silently wins. Single source of truth:
design-system.css; the frontend.css block is deleted, not overridden.

## F4 - Profile-banner CTA contrast failure (WCAG)

Dashboard "Complete your profile" banner: the "Complete profile" button
renders white text on light lavender - unreadable (see owner screenshot,
2026-06-07). Cause: button uses a light primary tint background with
on-primary text. Must use token pairs (`--wpss-primary` bg + on-primary
text, or outline style with primary text). Applies to ALL banner/CTA
variants, not just this one instance.

## F5 - Vertical rhythm after sub-header removal

`.wpss-services-archive` top padding stacked with theme content padding
(Reign `.site-content` 40px). Already reduced to 0.75rem (commit f378eb8) -
the shell-unification direction should make this systematic: the app shell
owns ONE top-spacing token used by every surface, instead of each surface
adding its own.

## F6 - Apply Filters form drops the selected category (wiring gap)

Wave-0 gate evidence (2026-06-07): on /services/, sidebar taxonomy LINKS
filter correctly, and the toolbar category combobox navigates on change -
but the "Apply Filters" form submits only min_price/max_price/delivery.
The selected category is not carried into the submission URL, so applying
filters silently resets the category (count stays 32). Pre-existing
(ServiceArchiveView.php form markup + frontend.js). Fix: the filter form
must include the active category (and any other active filters) as hidden
inputs so combined filtering works; verify category + price together
narrows results.

## F7 - Naked sections: shared components styled in surface-scoped CSS

Owner screenshot 2026-06-07: "Related Services" on the single service page
renders bare cards (no border, no background, floating text). Full audit of
every shared-component render vs. the CSS each surface actually enqueues:

| Component | Rendered by | Styled in | Loaded there? |
|---|---|---|---|
| content-service-card.php | templates/archive-service.php | archive-service.css | YES (ServiceArchiveView guard) |
| content-service-card.php | SingleServiceView render_related_services() | archive-service.css | **NO - naked (the screenshot)** |
| content-service-card.php | templates/vendor/profile.php | archive-service.css | **NO - naked** |
| content-service-card.php | AjaxHandlers.php (cards injected via AJAX) | archive-service.css | **NO on non-archive pages - naked** |
| .wpss-service-card--dashboard | templates/dashboard/sections/services.php | own duplicate definitions in unified-dashboard.css | partial - duplicate implementation |
| .wpss-favorites__card | templates/dashboard/sections/favorites.php | third custom card implementation | duplicate implementation |
| content-request-card.php | templates/archive-request.php | buyer-request.css | YES (BuyerRequestArchiveView guard incl. is_singular wpss_request) |

Two root causes, one fix:
1. **Shared components live in surface-scoped files.** archive-service.css is
   enqueued only on archive/tax/services-page requests, but the service card
   renders on single, vendor profile, and via AJAX into arbitrary pages.
2. **Three parallel card implementations** (archive card, dashboard card,
   favorites card) - the duplicate-component drift ux-audit exists to kill.

Decision to implement (wave-2.4 shell + wave-2.5 surfaces): a **shared
component layer** - card, grid, badge, price, avatar-row styles move into the
design-system/components layer that the app shell loads on EVERY plugin
surface. Surface CSS files keep only surface-specific layout. The dashboard
and favorites cards become modifiers of the one card component
(.wpss-service-card--dashboard etc.), not re-implementations. AJAX-injected
markup is automatically styled because the component layer is always present.

Verification: service cards render identically (border, bg, hover, badge,
price row) on archive, single related-services, vendor profile, dashboard
services, dashboard favorites, and after any AJAX refresh - at 1280px + 390px.

- Same header component, same x-alignment, same width on /services/,
  /service/<slug>/, /dashboard/ (and its sections), at 1280px and 390px
- Zero duplicate component definitions across CSS files (ux-audit clean)
- Banner CTA passes WCAG 2.1 AA contrast
- Behaviour identical on Reign 8.0.0 and twentytwentyfive (theme-agnostic
  check: the compat layer degrades gracefully)

## Interactive-component polish (owner screenshots 2026-06-08)

The CSS-only uplift (wave 2.5) made static surfaces premium but missed
JS-rendered + structurally-broken interactive components. "Premium UX
uniformly" means these too. THREE findings, likely ONE shared root cause
(primary-button background not rendering -> white text on transparent =
invisible CTA). Must be diagnosed via LIVE computed styles, not grep.

### F8 - Delete confirmation modal: confirm button invisible + double-box
WPSS.showConfirm (frontend.js:1702) renders BOTH .wpss-confirm-cancel and
.wpss-confirm-ok, yet the screenshot shows only "Cancel" - the .wpss-btn--primary
confirm button is present in the DOM but renders invisible (white text, no
background). A delete you cannot confirm. Also the modal shows a broken
double-card (outer gradient/shadow box + inner white box) instead of one clean
surface. Fix: one premium modal surface (single card, title + message + a proper
action row with a visible danger/primary confirm + outline cancel), focus trap,
ESC-to-close, design-system tokens. Diagnose why .wpss-btn--primary background
is empty here via computed styles and fix at the root so EVERY primary button
renders.

### F9 - Payout/earnings banner: odd colors, invisible CTA, emoji
The "earnings ready for withdrawal" banner is yellow with brown text and a
"Set Up Payouts" button whose white text sits on no background (invisible -
same root cause as F8). Uses a raw money-bag emoji (violates no-emoji rule).
Fix: design-system notice/banner tokens (proper bg + AA-contrast text), a
visible token-styled CTA, a Lucide icon instead of emoji, correct internal
spacing.

### F10 - Rejected-service notice: broken 3-column text layout
The "This service was not approved / Reviewer feedback / Edit your service..."
notice renders as three awkward vertical text columns (flex children each
wrapping into narrow ribbons). Should be one readable block: warning icon +
heading + reviewer-feedback paragraph + the resubmit guidance, stacked, with
the Resubmit CTA. Pink box should use the design-system danger-soft notice
token, not an ad-hoc pink.

### Verification bar
- Every .wpss-btn--primary CTA renders with a visible background + readable
  label on EVERY surface incl. body-appended modals and banners (computed
  background-color is non-transparent)
- Confirm modal: single surface, visible danger confirm + cancel, focus trap,
  ESC closes, verified by actually completing a delete at 390px + 1280px
- Payout banner + rejected notice: token colors, AA contrast, Lucide icons
  (no emoji), correct spacing, verified at both viewports
