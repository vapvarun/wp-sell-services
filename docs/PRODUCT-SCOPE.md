# WP Sell Services: product scope

The one page that decides whether work belongs in this plugin. Read it before adding a feature, a setting, a table, a screen or a REST route, and before moving a card out of Not now.

Owner direction (2026-11-26): "make sure we have a functional flow instead of over cooking features which are not getting used by most of people", and "we are a plugin: what WordPress core, the theme or another popular plugin already does, we do not reinvent".

## 1. What the product is

A Fiverr-style services marketplace that a site owner adds to their own WordPress site, on a theme and plugin stack we do not control. Free is the marketplace. Pro adds money automation and analytics on top of it. The mobile app is a front end over the same settings and REST API; it has no settings of its own.

## 2. Core flows: must always work

These are the flows nearly every owner uses (detail and evidence: [`docs/qa/CORE_PATHS.md`](qa/CORE_PATHS.md)). A defect here outranks every other card. Every release keeps them working on Reign, BuddyX and one default theme, at desktop and phone width, in light and dark mode.

| # | Flow | Role |
|---|---|---|
| 1 | Browse services and open one (catalog, category, single service) | visitor |
| 2 | Buy and pay (cart, checkout, Standalone gateways: Stripe, PayPal, Offline) | buyer |
| 3 | List a service and get it live (wizard and admin editor) | vendor |
| 4 | Approve who sells (vendor approve, reject, suspend) | owner |
| 5 | Hold a service back before it goes live (service moderation) | owner |
| 6 | Requirements, delivery, revisions, completion, review | buyer and vendor |
| 7 | Talk about an order (order messages) | buyer and vendor |
| 8 | Raise and settle a dispute | buyer, vendor, owner |
| 9 | See earnings and withdraw | vendor, owner |
| 10 | Post a buyer request and receive proposals | buyer and vendor |
| 11 | Find a vendor (vendors page, profile) | visitor |
| 12 | Become a vendor | member |

Plus the owner's ground rules: install, upgrade and uninstall leave a clean site; emails reach the right person with the right words; money is never charged, credited or refunded wrongly.

## 3. Shipped supporting features: keep working, no expansion without a report

Maintained and tested, but new work on them needs a real owner or customer report, not an audit idea.

- Free: packages, add-ons, gallery with one video, FAQs, service extras; tips, milestones, extensions, express delivery; order again; favorites; notifications centre; portfolio; reviews (single star rating, vendor reply, moderation); audit log; member reports; blocks and shortcodes (service grid, featured services, buyer requests, vendor card, categories, search); WooCommerce rail; billing fields; tax and commission; payouts schedule; demo import.
- Pro: licence; vendor and marketplace analytics; commission rules per vendor or category; Stripe Connect and PayPal Payouts; Razorpay; vendor subscription plans; cloud storage for deliveries; wallet providers and admin wallet adjust; white label; display currency hint; push notifications.

## 4. Frozen: keeps working, no new work

Decided by the owner (card WPSS-6b). Code stays, nothing new is built, bugs are fixed only if a paying customer hits them.

- Pro recurring services (off by default, `wpss_pro_recurring_enabled` filter).
- EDD and FluentCart rails (only with `wpss_pro_beta_rails_enabled()`).

## 5. Approved later (the roadmap, in order)

| When | Item | Card |
|---|---|---|
| 1.10 | Vendor-plan subscriber list for the owner (admin, REST, frontend) on the existing `wpss_pro_vendor_subscriptions` table | 10505027356 |
| 1.10 | Emails and notifications in the recipient's language (core `switch_to_user_locale` around each email) | 10504311205 |
| 1.10 | Hand SEO output to SEO plugins: robots via core `wp_robots`; schema off when an SEO plugin is active; detect SEOPress, The SEO Framework, Slim SEO | 10504310967, 10504310831, 10504311089 |
| 1.10 | Block CSS/JS only where a block renders (handles on `register_block_type`) | 10545808637 |
| 1.10 | REST service schema completeness, after replaying the app's real payloads | 10545978378 |
| 1.10 | Lean pass: dead code, duplicate helpers, inline template CSS, own-table transients to `wp_cache` | Not now column |
| 2.0 | Custom offers inside conversations | 9981906066 |
| 2.0 | Remove unused interface methods (wallet and product provider write halves) | 10244795655, 10504413152 |

Anything not in this table is not planned. Adding a row needs the owner's yes.

## 6. Not ours: use WordPress core, the theme or a popular plugin

We integrate with these; we never build our own version.

| Need | Who owns it | What we do |
|---|---|---|
| Login security, lockouts, 2FA, CAPTCHA | Security plugins | Nothing (our lockout was removed in 1.9.3) |
| Lost-password and reset pages | Core `wp-login.php`; WooCommerce, Theme My Login via `lostpassword_url` | Link with `wp_lostpassword_url()` |
| Roles and capability editing | Core caps; User Role Editor, Members | Declare clear capabilities; no role-editing screen |
| SEO titles, meta, robots, sitemaps, schema graph | Core `wp_robots`; Yoast, Rank Math, SEOPress, TSF, Slim SEO | Feed core filters; step aside when an SEO plugin is active |
| Page caching | Cache plugins and hosts | Set `DONOTCACHEPAGE` on per-visitor pages |
| Object caching | Core `wp_cache_*` with `last_changed` | No transient caches of our own table rows |
| Email delivery, SMTP, From address | Core `wp_mail`; SMTP plugins | Use `wp_mail`; filters for From |
| Translation and multilingual | Core i18n; WPML, Polylang, Loco | Translatable strings; `switch_to_user_locale` for mail |
| Privacy export, erase, policy text | Core privacy tools | Register exporters, erasers and `wp_add_privacy_policy_content` |
| Look, colours, fonts, dark mode | The theme | Use our tokens that follow the theme; no hard-coded colours |
| Site-wide analytics, forms, memberships, chat apps, CRM | Their own plugins | Hooks so they can connect |
| Background jobs | Action Scheduler, falling back to WP-Cron | One scheduler path |

## 7. Decided no (do not re-open without new evidence)

- Review sub-ratings (single star rating stays, 2026-11-01).
- Buyers editing a posted review (reviews are final, 2026-11-26).
- A review character minimum (only non-empty, 2026-11-26).
- A separate moderator capability (moderation is admin-only, 2026-11-26).
- Our own login lockout (removed in 1.9.3).
- A branded password-reset page.
- Success toasts or confirm dialogs where the page already reloads or the action is frequent (UX preference, not a bug).
- Merging the dashboard and order message threads (refactor with no user cost).

## 8. The gate for any new feature, setting or card

Answer every line before writing code or moving a card to Bugs. One "no" means Not now, with the reason on the card.

- [ ] Which core flow (section 2) does it fix or complete? None means it is not a 1.x patch.
- [ ] Is it a defect (wrong result, broken flow, data or money loss, silent failure, docs wrong), not a preference?
- [ ] Does core, the theme or a popular plugin already do it (section 6)? Then integrate, do not build.
- [ ] Did a real owner or customer report it, or is it an audit idea? Audit ideas wait in Not now.
- [ ] Is it already in section 7 (decided no)?
- [ ] Does it need a new table, option, setting or capability? That needs the owner's yes first.
- [ ] Free or Pro? Pro reuses Free's tables, helpers and hooks.
- [ ] Does it reach all three entry points it needs (member front end, owner admin, REST) and both themes?
- [ ] Is the smallest complete change chosen (delete or reuse before adding)?
- [ ] Is there a test that fails on the current code?

## 9. What a release contains

A patch release (1.9.x) ships only: defects on core flows, money or data-loss fixes, security fixes, and docs that are wrong. A minor (1.10) adds items from section 5. A major (2.0) is where breaking changes and section 5's 2.0 rows go.

Keep this page current: when the owner decides a scope question, add it to section 5, 6 or 7 in the same change.
