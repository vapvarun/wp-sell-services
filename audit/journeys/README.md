# Journeys

A journey is one end-to-end path through the product, walked in a real browser
as a real role. Not a unit test — the point is the seams *between* correct
units, which is where this plugin's defects have actually lived.

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

## Roles (verified 2026-08-17 against the live site)

| Role | Slug | WPSS capabilities |
|---|---|---|
| Administrator | `administrator` | `wpss_manage_disputes`, `wpss_manage_orders`, `wpss_manage_services`, `wpss_manage_settings`, `wpss_manage_vendors`, `wpss_respond_to_requests`, `wpss_vendor`, `wpss_view_analytics` |
| Vendor | `wpss_vendor` | `wpss_manage_orders`, `wpss_manage_services`, `wpss_respond_to_requests`, `wpss_vendor`, `wpss_view_analytics` |
| Buyer | `subscriber` | none — a buyer is a plain WordPress subscriber |

Note the buyer has **no** WPSS capability. Any gate written as "buyer can X"
must therefore be an ownership check (is this their order?), never a capability
check. `wpss_user_can_view_order()` is the correct participant check; there are
~20 hand-rolled variants elsewhere that are not.

## The journeys

| File | Covers | Status |
|---|---|---|
| `01-buyer-hires-seller.md` | request → proposal → hire → pay | **step 7 fails** (card 10208094640) |
| `02-dispute.md` | open → both parties reply → admin reads → back to order | passes |
| `03-install-and-upgrade.md` | activation, pages, settings, preflight | passes |
| `04-vendor-discovery.md` | directory → profile → service | passes |

## Not yet written

Worth adding as the remaining cards are cleared: messages/unread, review
submission and display, withdrawal + payout, milestone contracts, extensions,
and the three non-standalone rails (WooCommerce, EDD, FluentCart).
