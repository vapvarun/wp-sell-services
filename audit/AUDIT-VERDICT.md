# Audit Verdict: wp-sell-services (FREE) — 1.2.0 Cycle

**Generated:** 2026-06-08  
**Branch:** autovap/wp-sell-services/wave-7-audit-7.1  
**Auditor direction:** 7.1 (product-readiness, full cycle)

---

## Shippable? NO / BLOCKED

Two functional blockers prevent shipping to a 100k-site install base:

1. **ReviewModerationPage and NotificationsPage are never wired into Admin.php** — the classes were written by wave-6.4b.3b but Admin.php constructor does not instantiate or init() them. Both admin pages are unreachable. This is a dead-code regression: shipping 994 lines of new admin-page code that customers cannot access.
2. **Emoji icons in templates/myaccount/notifications.php** — 8 emoji code points (📦 🔄 💬 📤 ✅ 🔁 ⚠️ 📣) are hard-coded as notification type icons. The zero-UI-emoji gate in direction 7.1 is explicit. The payout-banner emoji were fixed (F9) but this file was not touched. Also: SingleServiceView.php:306 📦 and AjaxHandlers.php:1362 👍 remain.

All other known bugs are resolved. The remaining open items are documented known gaps (KG-1 through KG-7 in the ajax-rest-map), not new bugs.

---

## Sellable? NO (same two blockers; premium quality otherwise)

The baseline UX is now premium-grade: token-contrast primary buttons, clean confirm modal, resubmit CTA, payout-banner Lucide icons, token-colored notices. A paying customer using the visible surfaces would be satisfied. However an admin navigating to the review moderation queue would find the menu item absent, and every user sees emoji notification icons. These two blockers land before "paid" level.

---

## Findings (ranked: Blocker → Major → Minor → Polish)

| # | Severity | Lens | Finding | File:line | Fix Journey |
|---|----------|------|---------|-----------|-------------|
| 1 | **BLOCKER** | QA / Wiring | `ReviewModerationPage` is NEVER instantiated — the class (656 lines, src/Admin/Pages/ReviewModerationPage.php) was added by wave-6.4b.3b but Admin.php constructor does not include a `new ReviewModerationPage()` + `init()` call. Admin menu item is absent; the page cannot be reached. Same applies to `NotificationsPage` (338 lines, src/Admin/Pages/NotificationsPage.php). Both were added in commit aedcf28 which modified ONLY the two new PHP files; Admin.php was not touched. | `src/Admin/Admin.php` (constructor + init_pages) | fix: add `use` declarations + `$this->review_moderation_page = new ReviewModerationPage()` and `$this->notifications_page = new NotificationsPage()` in constructor; call `->init()` in `init_pages()`. Five-line change. |
| 2 | **BLOCKER** | UX / No-emoji | Emoji code points in user-facing templates: `templates/myaccount/notifications.php` lines 15-22, 56 (8 emoji: 📦🔄💬📤✅🔁⚠️📣); `src/Frontend/SingleServiceView.php:306` (📦 package icon); `src/Frontend/AjaxHandlers.php:1362` (👍 helpful icon). Direction 7.1 gate: "zero UI emoji". The F9 payout banner emoji were fixed; these were not. | `templates/myaccount/notifications.php:15-22,56`; `src/Frontend/SingleServiceView.php:306`; `src/Frontend/AjaxHandlers.php:1362` | improve: replace emoji with `Services\Icon::render()` Lucide equivalents (package, refresh-cw, message-circle, upload, check-circle, rotate-ccw, alert-triangle, megaphone, thumbs-up). |
| 3 | **MAJOR** | CSS / Dedup | `.wpss-btn--primary` is defined as a live rule block in TWO source-cascade contexts that can co-load: `design-system.css` (frontend owner, #4f46e5) AND `admin.css` (admin cascade, --wpss-admin-accent). The css-duplicates.json documents this as "out of scope for 2.7.1a — different cascade." The distinction is sound (admin pages never load design-system.css), but the audit/cleanup/css-inventory.json shows 55 identical-body cross-file selectors and 235 override conflicts across source files, with the consolidation decision marked ADVISORY ONLY and not auto-applied. No browser regression evidence exists that all 55 identical-body instances are truly safe in practice. This is NOT the token-poisoning bug (that was fixed), it is the documentation gap: the "safe to ship" determination rests on static analysis only, no live-site verification of the advisory duplicates. | `audit/cleanup/css-inventory.json` (55 identical-body, 235 override findings, status ADVISORY ONLY) | improve (wave-2.6 follow-through): browser-verify a representative set of the 55 identical-body selector redundancies on Playwright, confirm zero visual regression, then delete the evidenced-redundant rule blocks. |
| 4 | **MAJOR** | AJAX/REST | KG-4: `conversation.js::send_message` still routes to the legacy `wpss_send_message` AJAX handler. The map documents a pre-existing contract inconsistency (handler returns `{message: string, html}` but JS reads `response.data.message` as a structured object), making a silent double-render or blank-message risk on every order thread. This is documented as a known gap in `audit/ajax-rest-map.json` but it means admin-ajax.php is still the hot path for every order message on a 100k-site install (admin-ajax boots full admin context per hit, bypasses object-cache). | `assets/js/conversation.js` / `src/Frontend/AjaxHandlers.php` | fix (KG-4): resolve the JS<->PHP contract inconsistency first (verify the actual running behavior), then migrate to REST ConversationsController. |
| 5 | **MAJOR** | AJAX/REST | KG-5: `unified-dashboard.js` profile form (including intro video) still routes to AJAX `wpss_update_vendor_profile`. REST twin `VendorsController::update_current_vendor` lacks `intro_video_url`, `country`, `city`, `website`, `vacation_*`, and `cover_image_id`, and writes to `user_meta` rather than the `wpss_vendor_profiles` custom table. Migrating as-is is a data-loss regression. Vendor profile completeness is a primary selling feature. | `src/API/VendorsController.php::update_current_vendor`; `src/Frontend/AjaxHandlers.php::update_vendor_profile` | fix (KG-5): bring `update_current_vendor` to full field+storage parity with the AJAX handler, then migrate the JS and browser-verify the intro-video round-trip. |
| 6 | **MAJOR** | Performance / Scale | `AjaxHandlers.php::load_services` (KG-3) and `AjaxHandlers.php::load_reviews` (KG-1) still return server-rendered HTML via admin-ajax.php — both are on hot paths (blocks-frontend.js services pagination; single-service reviews load-more). At 100k sites, admin-ajax context = full WP boot + full admin load every paginated card/review page flip. The migration is blocked by the HTML-vs-JSON shape mismatch (documented in ajax-rest-map.json KG-1 and KG-3) but the scale risk is real. | `assets/js/blocks-frontend.js`; `assets/js/single-service.js`; `src/Frontend/AjaxHandlers.php` | improve: implement a client-side service-card renderer + review-card renderer to unblock KG-1 and KG-3 REST migration; browser-verify at 1280px+390px. |
| 7 | **MAJOR** | AJAX/REST | KG-6: Single-service favorites toggle (`single-service.js`) still routes to AJAX `wpss_favorite_service` / `wpss_unfavorite_service`. The fix is a one-line localization (`apiUrl` + `restNonce` onto the `wpssService` object in `SingleServiceView.php`), but it was scoped out of direction 6.3.1. FavoritesController already has the `count` field needed. | `src/Frontend/SingleServiceView.php::enqueue_assets()`; `assets/js/single-service.js` | fix (KG-6): add `apiUrl` and `restNonce` to the `wpssService` localized object in SingleServiceView, update single-service.js to use them. |
| 8 | **MINOR** | WPCS | `$wpdb->prepare()` called with an array-of-params (`$params`) in multiple services (EarningsService, DisputeService, ProposalService, OrderService, VendorPage, etc.) triggers `WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber` (WPCS 3.x does not understand splat-array param). Total: ~18 occurrences. These are false positives (the pattern is valid PHP), but the WPCS gate exits with code 2 (warnings treated as errors by the ruleset). 0 errors, but the non-zero exit means "FAILED" in CI. | Multiple files (see WPCS output) | fix: add `// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber` on each `$params` array prepare call, or switch to variadic spread `...$params`. |
| 9 | **MINOR** | Security | `src/Blocks/ServiceSearch.php:124-125` and `src/PostTypes/ServicePostType.php:243-244` / `BuyerRequestPostType.php:137-138` process `$_GET`/`$_REQUEST` form data without nonce verification (WPCS `WordPress.Security.NonceVerification.Recommended`). These are search/archive query params (read-only, no writes) so not a critical vulnerability, but the pattern is flagged by WPCS. | `src/Blocks/ServiceSearch.php:124-125`; `src/PostTypes/ServicePostType.php:243-244`; `src/PostTypes/BuyerRequestPostType.php:137-138` | fix: add `phpcs:ignore` with documented rationale (read-only search query params do not require nonces per WP core convention), or add a nonce if form allows write-state changes. |
| 10 | **MINOR** | Security | `src/CLI/ValidateCommand.php:151` uses `file_get_contents()` for remote URL fetch (WPCS `WordPress.WP.AlternativeFunctions.file_get_contents`). Should use `wp_remote_get()`. CLI-only context but still flagged. | `src/CLI/ValidateCommand.php:151` | fix: replace `file_get_contents()` with `wp_remote_get()`. |
| 11 | **MINOR** | CSS | Safe CSS consolidation candidate (css-inventory.json): `.wpss-btn:hover`, `.wpss-btn:active:not(:disabled)`, `.wpss-table`, `.wpss-modal.wpss-modal-open` are defined in both `design-system.css` and `frontend.css` with identical bodies. `wpss-frontend` declares `wpss-design-system` as a dependency so the frontend.css definitions are redundant. Not a visual bug, but increases parse overhead. | `assets/css/frontend.css` (4 selectors with identical bodies to design-system.css) | improve: delete the 4 redundant rule blocks from frontend.css. |
| 12 | **MINOR** | Flow | KG-7: `frontend.js::WPSS.submitDelivery` (file-upload via `wpss_deliver_order` multipart) remains on AJAX. The REST twin is `POST /orders/{id}/deliverables` (file-upload semantics, separate from the button-driven `/orders/{id}/deliver` action). Not migrated (documented in ajax-rest-map.json). The current flow works but is the only remaining multipart-file AJAX path. | `assets/js/frontend.js` (submitDelivery) | improve: migrate multipart delivery uploads to REST deliverables endpoint; browser-verify with a real file attachment. |
| 13 | **POLISH** | WPCS | Commented-out code warnings: `src/functions.php:954-992` (4 hits), `src/Services/DeliveryService.php:367,394` (2 hits), `src/API/ProposalsController.php:460` (1 hit). Not blocking but WPCS flags them. | See file list | polish: remove commented-out code blocks or extract to a `@todo` comment. |
| 14 | **POLISH** | Readme | `readme.txt` Stable tag still reads `1.1.1`; plugin header version is `1.2.0`. These must match for WordPress.org submission. | `readme.txt:7` | polish: bump Stable tag to 1.2.0 + add 1.2.0 changelog block. |

---

## Basecamp Card Status

| Card | Title | Status |
|------|-------|--------|
| 9966680633 | Services homepage shows user-scoped data | **FIXED** — `ServiceArchiveView::modify_archive_query()` now detects front-page context and removes `author` scoping; pagination follow-up (BC code: `6173363`, `2a051a7`, `8293d88`). Safe to close. |
| 9958056390 | Services archive/single UI broken (borders, button bg, category-label overlay, sidebar filters, transparent popups) | **FIXED** — card borders, category overlay (BC reference in `frontend.css:542`), button backgrounds (token-poison fix), transparent popup messaging.css rewrite (wave-2b.1), sidebar restyle (wave-2.5.2). Safe to close. |
| 9959210393 | Services single UI broken (colours/transparency, button backgrounds) | **FIXED** — same token-poison root cause as 9958056390, resolved by wave-2.7.1 / css-duplicates.json. Safe to close. |
| 9826303369 | Dashboard pretty permalinks | **FIXED** — `add_rewrite_rule` anchored to live dashboard page path, `wpss_section` query var, `wpss_append_dashboard_section()` helper, 301 fallback for `?section=` legacy URLs, activation/upgrade flush. Wave-6.1 commits `5740f38`, `f869d73`, `3b06aaf`. Safe to close. |

---

## Wave Completion Status

| Wave | Title | Status |
|------|-------|--------|
| 0 | Model-site demo seeder | DONE — `MarketplaceSeeder.php` + `ServiceCommands::marketplace()` seeder |
| 1 | Homepage user-scoped data (BC 9966680633) | DONE |
| 2 | Archive/single UI repair (BC 9958056390, 9959210393) | DONE |
| 2b | Transparent popups + favorites surface | DONE |
| 2.4 | Frontend shell unification | DONE |
| 2.5 | Frontend usability uplift (all surfaces) | DONE |
| 2.6 | CSS inventory + dead-file cleanup | DONE (advisory: 55 identical-body duplicates deferred, see finding #3) |
| 2.7 (informal) | Interactive component UX polish (F8/F9/F10) | DONE — confirm modal clean, payout banner token, rejected notice stacked |
| 3 | Security verification (e3f2d21, 9f640cb) | DONE — capability checks wired; AjaxHandlers ownership patterns documented |
| 4 | Input-handling hardening | DONE — superglobal iteration eliminated (3 sites), enum-consistency 7-drift fix |
| 5 | Admin UX (alert/confirm → toast, tap targets) | DONE — 5 alert/confirm replaced with wpssAdminConfirm / WPSS.showNotification |
| 6.1 | Dashboard pretty permalinks (BC 9826303369) | DONE |
| 6.2 | Template extension hooks (31 wppqa advisories) | DONE |
| 6.3 | AJAX→REST migration (4 of 10 migrated, 6 documented KGs) | PARTIAL — 4 migrated, KG-1 through KG-7 documented. Blockers #4-#7 above. |
| 6.4a | Flow completeness user-facing (buyer requests, wallet, vendor email) | DONE |
| 6.4b | Flow completeness admin (audit log, review queue, seller level, suborders, portfolio mod, vacation, gateways) | PARTIAL — **ReviewModerationPage + NotificationsPage written but NOT WIRED (Blocker #1)** |
| 6.5 | Scale benchmark (`wpss scale`) | DONE — benchmark CLI with per-query budgets + additive indexes |

---

## wppqa Baseline Item Resolution

| Baseline Item | Status |
|---|---|
| 5 `alert()`/`confirm()` calls (admin-settings-demo.js:21,56; admin-settings-pages.js:53; unified-dashboard.js:116,120) | **RESOLVED** — all 5 replaced with `wpssAdminConfirm` / `WPSS.showNotification` modal/toast system |
| 3 superglobal-iteration patterns (BuyerRequestMetabox.php:327; ServiceMetabox.php:1065; AjaxHandlers.php:638) | **RESOLVED** — refactored to explicit-key reads in wave-4.1/4.2 |
| Nonce-no-cap in Admin.php:1266,1319 (bulk order/dispute processors) | **RESOLVED** — `current_user_can('manage_options')` guards added (commit e3f2d21) |
| Nonce-no-cap in ServiceModerationPage.php:1211 | **RESOLVED** — capability check present in current source (verified) |
| AjaxHandlers nonce-no-cap (many) | **RESOLVED AS ACCEPTED** — commit 9f640cb documents the four object-ownership patterns (A-D); comment-only change; accepted pattern |
| Tap target < 40px (65 hits in admin CSS) | **RESOLVED** — wave-5.2 set 40px floor on admin buttons, two-block 640/1024 responsive scale |
| Enum-consistency drift (7 high-severity drifts) | **RESOLVED** — wave-4.3 unified to canonical Models constants |

---

## AJAX/REST Map Completeness (KG status)

| KG | Flow | Status |
|---|---|---|
| KG-1 | `single-service.js::load_reviews` | OPEN — HTML/JSON shape mismatch; needs client-side review renderer |
| KG-2 | `single-service.js::mark_review_helpful` | OPEN — deferred with KG-1 |
| KG-3 | `blocks-frontend.js::load_services` | OPEN — HTML/JSON shape mismatch; needs client-side card renderer |
| KG-4 | `conversation.js::send_message` | OPEN — pre-existing JS<->PHP contract inconsistency; needs contract resolution + REST migration |
| KG-5 | `unified-dashboard.js::update_vendor_profile` (incl. intro video) | OPEN — REST twin lacks field+storage parity; migration = data-loss regression |
| KG-6 | `single-service.js::favorites toggle` | OPEN — one-line localization gap in SingleServiceView.php |
| KG-7 | `frontend.js::submitDelivery` (multipart file upload) | OPEN — deferred; REST twin is /deliverables (file semantics); needs browser-verify |

---

## Scores

| Lens | Grade | Notes |
|------|-------|-------|
| Security | A- | Nonce+capability wired everywhere; superglobal iteration eliminated; IDOR on proposals fixed. Minor: 3 non-mutating GET handlers without nonces (read-only, acceptable). |
| Performance | B | Hot-path archive query paginated (12/page). Scale CLI with benchmarks added. Known: KG-1/KG-3/KG-4 flows still on admin-ajax hot path (server-rendered HTML). MigrationManager has 4 unbounded queries but these are one-time migration-only paths, not hot-path. |
| UX / Design System | B+ | Premium interactive components (modal, notices, banners). Token system clean after poison removal. 55 identical-body CSS duplicates remain as advisory. **Emoji blocker in notifications.php** breaks the A grade. |
| QA | C+ | 14/14 flow-completeness findings addressed in code; **ReviewModerationPage + NotificationsPage not wired** = 2 features DOA. 7 AJAX/REST known gaps documented. |
| Standards (WPCS) | B+ | ZERO WPCS errors. 159 warnings total (src/ directory). False-positive prepare($params) pattern generates ~18 WPCS warnings that exit code 2 in CI; none are real vulnerabilities. PHP syntax clean. |
| Triage / Health | A- | 4 Basecamp cards all fixed. wppqa baseline items all resolved or accepted. Stable tag mismatch (1.1.1 vs 1.2.0) needs bump. |

---

## Fix Plan (shortest path to ship)

**Blocker 1 (30 min):** Add to `src/Admin/Admin.php`:
- `use` declarations for `ReviewModerationPage` and `NotificationsPage`
- Two instantiations in `__construct()`
- Two `->init()` calls in `init_pages()`

**Blocker 2 (2 hrs):** Replace 10 emoji occurrences in three files with Lucide icon via `Services\Icon::render()`. Run `grunt min` after.

**After those two fixes:** The plugin is shippable for 1.2.0 with the 7 AJAX/REST known gaps **documented** in `audit/ajax-rest-map.json` (they are not new bugs; the legacy handlers remain registered and byte-compatible). The documented gaps become the 1.3.0 migration sprint.

**readme.txt stable tag:** One-line bump to `1.2.0` with changelog block.

