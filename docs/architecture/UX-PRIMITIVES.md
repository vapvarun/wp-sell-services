# UX Primitives

> **Stable, reusable building blocks for every form and page in the plugin.** Use these instead of writing one-off error toasts, status pills, or page wrappers. One pattern, one bug surface, one set of accessibility guarantees.

**Introduced:** 1.1.0 — see `docs/audits/baseline-2026-04-25.md` for the friction findings that motivated each primitive.

**Three primitives:**

1. [`wpss-form-error`](#1-wpss-form-error) — inline + summary error rendering with ARIA
2. [`wpss-autosave`](#2-wpss-autosave) — status pill (idle / saving / saved / error)
3. [`wpss-app-shell`](#3-wpss-app-shell) — page wrapper that prevents host-theme layout bleed

**Asset wiring:**

- CSS: appended to `assets/css/design-system.css` (sections 14, 15, 16)
- JS: `assets/js/components/wpss-ux-primitives.js`, registered as `wpss-ux-primitives` script handle, depended on by `wpss-frontend`
- Translations: `wpssData.i18n.autosave*` + `wpssData.i18n.formErrorSummaryTitle`

---

## 1. `wpss-form-error`

Standardised field-level and form-level validation error rendering. Replaces every ad-hoc red text label and inline error span across the plugin.

### Why it exists

`vendor-flow-07` baseline screenshot caught it: clicking Continue with a missing required field on the wizard did **nothing visible** — no toast, no inline error. Vendors abandoned the flow thinking the button was broken. Every form in the plugin needs the same fix; doing it once here means we don't reinvent it per-form.

### DOM contract

#### Field-level error

```html
<div class="wpss-field">
    <label for="title">Title <span aria-hidden="true">*</span></label>
    <input id="title" name="title" aria-describedby="title-error" />
    <p id="title-error" class="wpss-form-error" hidden></p>
</div>
```

The error element MUST:
- Carry `id="<input-id>-error"` (the JS finds it by convention) OR live as a sibling `<p class="wpss-form-error">` inside the same parent
- Start with `hidden` attribute set
- Be referenced from the input's `aria-describedby`

#### Form-level summary

```html
<div class="wpss-form-error-summary" hidden>
    <p class="wpss-form-error-summary__title">Please fix the following:</p>
    <ul class="wpss-form-error-summary__list"></ul>
</div>
```

Place this above the first field of the form (or above the wizard step body). It will be revealed and populated by `WpssFormError.summary()`.

### JavaScript API

```js
// Show one error.
WpssFormError.show( 'title', 'Title must be at least 10 characters.' );

// Clear one error.
WpssFormError.clear( 'title' );

// Render a summary of all errors at once (typically after a failed submit).
WpssFormError.summary( document.querySelector( '.wpss-wizard__step' ), [
    'Title must be at least 10 characters.',
    'Please choose a category.',
    'Description must be at least 120 characters.'
] );

// Pass an empty array to hide the summary.
WpssFormError.summary( container, [] );

// After rendering errors, scroll to and focus the first invalid field.
WpssFormError.scrollToFirst( container );
```

All four methods accept either an element id (string, no `#`) or a DOM node.

### Accessibility behaviour

- `aria-invalid="true"` is set on the input when an error is shown.
- The error element gets `role="alert"` so screen readers announce it without stealing focus.
- `scrollToFirst()` focuses the first invalid field — keyboard users can fix-and-continue without a mouse.

### What this primitive does NOT do

- It does not validate. Your code decides what counts as an error; the primitive only renders.
- It does not replace native HTML5 validation (`required`, `pattern`, etc.). You can use both — call the primitive from your own validation handler.
- It does not handle async server-side errors specifically. Treat them the same as client-side: call `summary()` with the messages the server returned.

---

## 2. `wpss-autosave`

A small status pill that tells the user their work is safe.

### Why it exists

The wizard auto-saves drafts on every keystroke (debounced) but there was zero visible feedback that it had happened. Vendors had no way to know if leaving the tab would lose their draft. Same need exists in the profile editor and any future form with auto-save.

### DOM contract

```html
<span class="wpss-autosave"
      data-state="idle"
      role="status"
      aria-live="polite">
    <span class="wpss-autosave__icon" aria-hidden="true"></span>
    <span class="wpss-autosave__label"></span>
</span>
```

- `data-state` is the source of truth. CSS reads it; JS sets it.
- `aria-live="polite"` ensures screen readers announce state changes without interrupting.
- `idle` is invisible (`opacity: 0`) so the pill takes its space but doesn't distract until the first save fires.

### States

| State    | Triggered by                          | Visible label  | Visual                              |
|----------|---------------------------------------|----------------|-------------------------------------|
| `idle`   | Initial render, never saved yet       | (empty)        | Hidden (opacity 0)                  |
| `saving` | Auto-save AJAX request in flight      | "Saving…"      | Grey pill + spinning icon           |
| `saved`  | Auto-save succeeded                   | "Saved"        | Green pill + checkmark; auto-fades after 2s |
| `error`  | Auto-save failed (network, 500, etc.) | "Save failed"  | Red pill + warning icon             |

### JavaScript API

```js
// Set state.
WpssAutosave.set( '.my-form .wpss-autosave', 'saving' );
WpssAutosave.set( '.my-form .wpss-autosave', 'saved' );
WpssAutosave.set( '.my-form .wpss-autosave', 'error' );

// Pass a label override if you want custom text.
WpssAutosave.set( indicator, 'error', 'Network unreachable. Will retry.' );
```

Pass either a selector string or a DOM node as the first argument.

### Integration with Alpine.js

```js
// Inside an Alpine component:
methods: {
    async saveDraft() {
        WpssAutosave.set( this.$refs.autosave, 'saving' );
        try {
            await fetch( '/wp-admin/admin-ajax.php', { method: 'POST', body: this.serialize() } );
            WpssAutosave.set( this.$refs.autosave, 'saved' );
        } catch ( e ) {
            WpssAutosave.set( this.$refs.autosave, 'error' );
        }
    }
}
```

In the template, mark the indicator with `x-ref="autosave"` so Alpine exposes it on `this.$refs`.

---

## 3. `wpss-app-shell`

A standardised page wrapper that prevents host-theme stickies from bleeding into our UI.

### Why it exists

`vendor-flow-05` and following baseline screenshots caught a serious rendering bug: the WP admin bar + the host theme's site header appeared **stuck mid-page** on every wizard screen. Root cause: the BuddyX theme's sticky header used a `position: fixed` strategy with no stacking context isolation; our wizard wrapper used `position: relative` without `isolation: isolate`, so the theme's z-indices could escape into our layout.

The fix is one CSS rule per page wrapper. This primitive bottles it so every section-level template can opt in by replacing whatever ad-hoc wrapper class it currently uses.

### DOM contract

```html
<div class="wpss-app-shell">
    <div class="wpss-app-shell__container">
        <!-- everything else: sidebar, main, etc. -->
    </div>
</div>
```

### What the CSS does

```css
.wpss-app-shell {
    position: relative;
    isolation: isolate;   /* ← creates new stacking context, blocks theme z-indices */
    contain: layout;      /* ← isolates layout calculations from the page */
    width: 100%;
    max-width: 100%;
}

.wpss-app-shell__container {
    max-width: var( --wpss-container-max, 1200px );
    margin: 0 auto;
    padding: var( --wpss-space-6 ) var( --wpss-space-4 );
}
```

The two critical lines are `isolation: isolate` (stops any stacked element from the host theme rendering inside our wrapper) and `contain: layout` (stops layout reflow propagation across the boundary).

### Where to apply it

Wrap every plugin frontend section template's outermost `<div>` in `wpss-app-shell` + `wpss-app-shell__container`:

- `templates/dashboard/main.php`
- `templates/wizard/main.php`
- `templates/profile/edit.php`
- `templates/single-service/main.php`
- `templates/archive/main.php`
- `templates/vendor-profile/main.php`
- `templates/buyer-request/single.php`

Do NOT apply it to inner partials (sidebar templates, package tab templates) — only the outermost wrapper. Doing it twice creates nested stacking contexts that are wasteful but harmless.

### What this primitive does NOT do

- It does not style the inner content. Use the existing layout utilities (`wpss-grid`, `wpss-layout--sidebar-right`, etc.) for that.
- It does not eliminate the WordPress admin bar. That bar is part of WP itself; the primitive only ensures our content does not get visually polluted by it.
- It does not auto-fix existing wrappers. Each template needs to be updated to use the new class.

---

## Adding new primitives

Before reaching for a new primitive, check:

1. **Could the existing CSS/JS handle this if I just used it consistently?** Most of the time, yes — and that's better than another component.
2. **Will this be used in 3+ places?** If not, it doesn't earn its keep as a primitive. Inline it in the one place that needs it.
3. **Is the friction I'm solving actually felt by users, or is this a code-architecture concern?** Primitives are for user-facing patterns. Refactor architecture in a different file.

If the answer is yes to all three, follow the pattern:

- CSS goes in a new section of `assets/css/design-system.css` with a numbered comment block matching the existing style
- JS goes in `assets/js/components/wpss-ux-primitives.js` (extend the existing module rather than starting a new one)
- Document here with: why it exists, DOM contract, JS API, accessibility behaviour, what it does NOT do
- Add any user-visible strings to `wpssData.i18n` in `Frontend.php` so they're translatable

---

## Anti-patterns to avoid

- ❌ Inline error rendering: `<p style="color:red">Error!</p>` — use `wpss-form-error` instead
- ❌ Custom toast libraries — we deliberately don't use them; field-level errors with summary cover the same need with better accessibility
- ❌ Per-page CSS files for layout wrappers — use `wpss-app-shell` for any new section template
- ❌ `wpss-frontend.css` as a dumping ground for styles that could live in `design-system.css` — section-specific styles are fine in their own file, but anything that could be reused belongs in the design system
