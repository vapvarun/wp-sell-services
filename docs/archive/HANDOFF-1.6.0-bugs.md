# Handoff — WPSS 1.6.0 release (2026-08-17, second session)

**Bugs column is EMPTY.** Everything below is committed and pushed to branch
`1.6.0` in both repos. Nothing is uncommitted.

The gate the owner set — "clear every card in Bugs" — is met. 12 cards moved to
**Ready for Testing** (`9381846126`), 2 moved out to Ready For Development by
owner decision. Every card carries a comment with root cause, files changed, and
step-by-step verification, so QA can verify without re-deriving anything.

## What shipped this session

| Commit | Card(s) | What it actually was |
|---|---|---|
| `7deda27` | 10208094640, 10208199238 | `get_post( 0 )` returns the **global post**, so proposal orders resolved their "service" to whatever page was rendering. Plus offline pay-order never recorded `payment_method`, which made the order **impossible to confirm by anyone** |
| `4caaf6e` | — | PHPStan (7 errors) and `composer test` were **red at HEAD**, contrary to the previous handoff |
| `52d4281` | 10208211608, 10208142348 | A guard hid a CTA and nothing replaced it, twice: the admin order never mentioned its dispute; the order page never showed the review the buyer wrote |
| `57d3cc2` + Pro `c97e8c0` | 10208211769 | Settings deep links used the pre-1.3.0 `?tab=` arg nothing reads — and **5 of 13 also pointed at the wrong section**, which the dead arg had been hiding |
| `19b08e1`, `386790e` | 10208142467 | Become a Vendor read the legacy `_wpss_is_vendor` meta. All six vendors hold the role and **none** holds the meta, so every vendor was told to sign up |
| `ee4f31e` | 10208075268 | The unread count was **correct and invisible** — `text-indent: -999999px` rendered it as a bare dot. Plus no pagination while the banner counted every conversation |
| `019ff5b` | 10208199338 | `/create-service/` was a leftover published page for a **virtual route**; now redirects to the wizard, de-indexed |
| `c5c1b5b` | 10207973462 | `--wpss-sticky-top` assumed admin-bar-only; measured 47px of the package sidebar behind BuddyX's sticky header |
| `3dd1968`, `c9d6413` | — | Owner decision: losing bidders get in-app only, no email burst |
| `d9a66c5` + Pro `eec19c7` | 10138985537 | i18n was largely done; the gap was **no gate**. New `js-fallback` check; Pro's CI never ran the i18n gate it shipped |
| Pro `c6c943a`, `ea846d7` | 10154920673 | Push notification **sending** built in Pro (owner decision). Device tokens had been stored since 1.1.0 and never read |
| `dfece70` | 10208511245 | Dual H1 on cart and dashboard — filed by QA mid-session |

## Owner decisions taken this session

1. **Big non-bug cards → Ready For Development.** 10154919636 (normalise API) and
   10163575694 (guest purchase) left Bugs. Neither is a defect; both are multi-day
   contract work. Comments record "not started" plus starting points.
2. **Push notifications → Pro.** Built. Off by default.
3. **Losing bidders → in-app only.** Implemented; explicit rejections still email.
4. **Cart-link default → stays off.** No code change needed.

## Gate state — read this before tagging

Corrected from the previous handoff, which claimed all gates green.

| Gate | Free | Pro |
|---|---|---|
| phpcs | **0 errors** | **0 errors in touched files**; 59 pre-existing errors across 52 other files |
| phpstan | **0** | **runs at all now** (see below); 22 pre-existing EDD/FluentCart stub errors |
| phpunit `--testsuite unit` (what CI runs) | **7/7 pass** | pass |
| `composer test` (all suites) | **7 pre-existing failures** | — |
| i18n / version drift | **pass** | **pass**, and now runs in CI |
| docs audit | pass | — |
| app parity | pass | — |

Three things were broken before this session and need your decision:

- **Pro's PHPStan could not run at all.** `phpstan.neon` and its baseline still
  referenced `src/Integrations/SureCart/*`, deleted in `cfe766e`, so the tool
  exited on a config error rather than analysing. Pro has had no static analysis
  since that commit. Fixed in `c6c943a`.
- **`composer test` died instantly** on a testsuite pointing at `./tests/API`,
  which does not exist. Fixed in `4caaf6e`. Running it now exposes **7 pre-existing
  failures** in the integration + rest suites that CI never ran — confirmed
  pre-existing by re-running with this session's changes stashed. One is a
  genuinely wrong test: `MilestonesUpworkFlowTest` fires `wpss_order_paid` with 1
  arg where production fires 2.
- **Pro's phpcs has 59 pre-existing errors** in files nobody touched.

**Decision needed:** fix those before tagging, or ship with CI's current gates
(which are green) and file them. They are unrelated to the bug sweep.

## Not verified, and cannot be from this sandbox

- **Push notifications** reaching a real handset. Everything the plugin controls is
  verified with the HTTP layer intercepted — the OAuth JWT, the FCM endpoint, the
  message body, dead-token pruning, token caching. What Google does with a
  valid-looking request is untested. **Recommend leaving it off by default in 1.6.0
  and doing a real-device pass before advertising it.**
- **EDD / FluentCart purchase flows.** Still never purchase-tested, as the previous
  handoff noted. SureCart was removed in `cfe766e`.

## Release checklist (Bugs is empty, so this is the remaining work)

1. Decide the gate question above.
2. `wp wpss preflight` — clean (ignore the third-party deprecation count on this
   sandbox).
3. Deactivate → reactivate both plugins; confirm zero fatals. **This caught a real
   activation fatal previously — do not skip it.**
4. Assets are already rebuilt (`npm run rtl && npm run build:min`) for every CSS/JS
   change this session. Re-run if you touch anything.
5. **`readme.txt` changelog for 1.6.0 — still needs this session's 13 commits
   folded in.** WooCommerce-style action prefixes, no em-dashes, no emoji. This is
   the biggest remaining task.
6. Update `CLAUDE.md` "Recent Changes".
7. Verify version consistency (all four read 1.6.0; the i18n gate enforces this).
8. Tag. Latest tag is still `v1.4.0`, so the three fatals fixed in `631e711` —
   including the customer-reported one — stay live until this ships.

## Cross-cutting things learned this session

- **`get_post( 0 )` returns the global `$post`.** Any `get_post( $maybe_zero )` is a
  latent version of the 10208094640 bug. Guard the id first.
- **A store with a reader and no writer, or a writer and no reader, is the
  recurring shape.** This session: `payment_method` on offline pay-order (writer
  missing), push tokens (reader missing), the unread badge count (written, then
  hidden by CSS).
- **Check the sandbox's real data before trusting a code read.** It contradicted the
  code three more times: `wpss_disputes.reason` holds free text, not enum keys;
  every vendor holds the role and no legacy meta; the "inflated" unread counts were
  arithmetically correct.
- **A page joining `ShellHeader`'s shell list must render its own `<h1>` in every
  branch** — logged out, empty, and rail-redirected included. Two headingless pages
  were created and fixed while doing exactly that.
- **`in_the_loop()` is not where themes render their entry title.** BuddyX does it
  outside the loop.
- **QA cards are entry points.** Every card this session was bigger than filed, and
  two had a fix gate that would have introduced a regression if followed literally
  (10208511245's page list, and 10208075268's premise that the count was wrong).

## Sandbox state (local only, not product changes)

Left from the previous session:
- 17 orphan `cart-N` pages → **Trash** (recoverable).
- QA repro page "WPSS RFT Bug Repro Vendors" deleted.
- `wpss_general['use_marketplace_cart_link']` left **on**.

Added and then cleaned up this session — all removed, verified:
- Order 3128 carried through to `pending_requirements` / `payment_status = paid`
  (proving the offline chain). **Orders 3129 and 3130 left unpaid as fresh repros.**
- Review 16 now has a seller response (kept deliberately — QA needs it).
- Requests 550-554, proposals 43-52, orders 3169-3170, 2 conversations and
  notifications 851-863: **deleted**.
- Fake push service account, 2 fake devices, notifications 862-863: **deleted**.
- de_DE `.mo` files, the `wp-content/languages` directory, the locale-forcing
  mu-plugin, the title-probe mu-plugin, the messages-per-page mu-plugin: **deleted**.
- `wpss_orders[review_window_days]`, `wpss_pages[create_service]` and BuddyX's
  `site_sticky_header` theme mod: **restored to their exact prior state** (all
  absent).
