# Journeys

A journey is one end-to-end path through the product, walked in a real browser
as a real role. Not a unit test — the point is the seams *between* correct
units, which is where this plugin's defects have actually lived.

**Framing:** [`../../docs/qa/TESTING-GUIDELINE.md`](../../docs/qa/TESTING-GUIDELINE.md)
covers *how to decide what to check* — the expectation-first approach, the
code-flow contract questions, and reading the documentation as part of the flow.
Read it once, then work from the journeys here.

## Why these exist

Every journey here was written **after** a defect it would have caught. They are
not speculative coverage. The 2026-08-17 sweep found, among others:

- a notification type declared since 1.0.0 that nothing ever wrote
- a dispute conversation split across two stores, so members and admins each saw
  half of it
- an order that went silent about its own open dispute
- a "View Dispute" link the theme rendered as borderless text

None of those fail a unit test. All of them fail a journey.

## How to run one

1. Log in as the stated role. Use `?autologin=<login>` on the local sandbox
   (see the mu-plugin in the root `CLAUDE.md`); never hand-fill login forms.
2. Walk the steps **in order**, in the browser.
3. Check each **Expect** line as you go, not at the end.
4. Where a step says *computed style*, actually read it — themes override
   `<a>` styling and the DOM looks fine while the page looks broken.
5. Check 390px for any step that renders UI.

A journey passes only if every Expect holds. Record failures as Basecamp cards
with the step number.

## What a journey is now

One role walking one flow that exists in the product, from the screens an admin or member actually has
(the Sell Services menu, the vendor and buyer dashboards, the public pages). A journey names its rail
(`standalone`, `woo`), seeds its own data, and never says `any`. Bugs become check lines inside the flow
they belong to, tagged `card:<id>`. The conditions every flow is walked under (theme, dark mode, 390px, RTL,
upgraded site, time zone) live in [`../gate-parameters.md`](../gate-parameters.md); they are parameters,
not journeys.

## Roles

| Role | Slug | Notes |
|---|---|---|
| Visitor | (logged out) | public catalog, vendors, requests, sign-up |
| Buyer | `subscriber` | holds no `wpss_*` capability |
| Seller | `wpss_vendor` | an ACTIVE row in `wpss_vendor_profiles` is what makes a vendor |
| Admin | `administrator` | the site owner |

Users and data are created by the walk (or `bin/qa-fixtures.sh` for users). Do not rely on any named account.

## The journeys

| Journey | Role and flow | Rail |
|---|---|---|
| `01-buyer-hires-seller.md` | buyer posts a request, sellers propose, buyer accepts and pays | standalone |
| `02-dispute.md` | buyer and seller dispute an order, admin decides | standalone, woo |
| `03-install-and-upgrade.md` | admin installs, upgrades from the previous release, first-run setup | standalone |
| `04-visitor-browses-and-views-service.md` | visitor finds vendors and services, opens a service with a video | standalone, woo |
| `05-vendor-creates-a-service.md` | seller creates and publishes a service; account standing limits new work | standalone, woo |
| `06-standalone-checkout.md` | buyer pays on the standalone rail | standalone |
| `07-buyer-pays-woo-rail.md` | guest add-to-cart, carrier product, plan limits on the Woo rail | woo |
| `08-api-client-acts-as-each-role.md` | app token session and API writes match the screens | standalone, woo |
| `09-request-files-to-order.md` | request files follow into the order | standalone |
| `10-vendor-withdrawal-payout.md` | seller requests a payout, admin approves and marks paid | standalone |
| `11-member-notifications.md` | member finds, filters and reads notifications | standalone, woo |
| `12-admin-configures-the-plugin.md` | every admin screen and Settings tab | standalone, woo |
| `13-admin-moderates-and-manages.md` | moderation, reviews, vendors, reports, orders, audit log | standalone, woo |
| `14-member-reviews-favorites-messages.md` | reviews and replies, favourites, messages | standalone, woo |
| `15-seller-runs-the-business.md` | profile, portfolio, earnings, analytics, plans | standalone, woo |
| `16-milestones-extensions-and-tips.md` | milestone contracts, paid and deadline extensions, tips | standalone, woo |

Walk dates and results are recorded in `docs/qa/walks.json`, not in these files.

## Not yet written

Wallet providers (TeraWallet, myCred), Razorpay, Stripe Connect and PayPal payouts, EDD and FluentCart rails
(beta), vendor subscription plan purchase, seller levels, the blocks and shortcodes, recurring services (switched off).

## Two things these journeys have taught, the hard way

**A browser is not the only environment that lies.** Journey 08 is walked over
HTTP rather than through `wp eval` because `rest_do_request()` neither defines
`REST_REQUEST` nor runs `rest_post_dispatch` — so application-password
authentication and `_fields` filtering both behave differently there. Two
security findings were verified as "safe" from WP-CLI and were not.

**Demo data is test coverage.** Journey 05 could not fail on a default install
until the seeder built a category hierarchy, because every branch it exercises
only runs when a child term exists. Before writing a journey, check the seeded
dataset can actually reach the state it describes — otherwise it passes by
walking past the code.
