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

## Verification bar (each finding)

- Same header component, same x-alignment, same width on /services/,
  /service/<slug>/, /dashboard/ (and its sections), at 1280px and 390px
- Zero duplicate component definitions across CSS files (ux-audit clean)
- Banner CTA passes WCAG 2.1 AA contrast
- Behaviour identical on Reign 8.0.0 and twentytwentyfive (theme-agnostic
  check: the compat layer degrades gracefully)
