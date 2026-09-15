# WP Sell Services — Core Paths (the 60–70%)

> **What nearly every owner uses, ranked.** QA walks these first, every cycle,
> as the named role, on a clean install. Bug priority follows this list (see
> `owner-questions.md` → Triage): a defect on a core path outranks one on an edge
> regardless of who reported it.
>
> **Seeded from evidence, confirmed by a human once.** Evidence: what activation
> creates, what the settings screen shows first, what the readme leads with, what
> the Basecamp ledger shows people report on, and the free/pro split (free is the
> core by definition). Re-confirm at each major release; the ledger will tell you
> when the ranking has drifted (`C-4`).

Last confirmed: **NOT YET CONFIRMED — seeded 2026-09-15 by QA, needs one human pass**

## How this draft was ranked

Three evidence sources, none of them opinion:

1. **What activation creates** — 13 pages inserted, one role (`wpss_vendor`),
   34 DB tables, 152 REST routes. The inserted pages are the owner's marketplace:
   services, dashboard, become-a-vendor, cart, checkout, vendors, registration.
2. **The 21 cards in this cycle's Ready for Testing column.** What people
   actually reported against is the strongest available signal for what people
   actually use. Clusters, most-reported first: service creation (3 cards),
   dark-mode/theme fit across surfaces (3), catalog browsing (2), vendor
   approval + its emails (2), checkout (2), disputes (1), orders/requirements (1),
   earnings (1), buyer requests (1).
3. **The free/pro split.** Everything below is Free unless marked, so it is core
   by definition — a Pro-only flow cannot be in the 60–70%.

| # | Flow (owner's words) | Role | Surface | Why it is core (evidence) | Journey | Free/Pro |
|---|---|---|---|---|---|---|
| 1 | "Someone can browse what I sell and open a service" | visitor | `/services/`, `/service/<slug>/`, `/service-category/<slug>/` | The catalog is what a marketplace *is*. Activation inserts the services page; 2 cards this cycle (archive rate, showcase video) | | Free |
| 2 | "A buyer can buy something and pay" | buyer | `/service-checkout/<id>/`, `/cart/` | The money path. Activation inserts cart + checkout pages; 2 cards this cycle | | Free |
| 3 | "A seller can list a service and it goes live" | vendor | `/dashboard/create/` (wizard) **and** wp-admin service editor | Two authoring surfaces for one outcome — the most-reported area this cycle (3 cards), and the one where the two surfaces historically disagreed | | Free |
| 4 | "I can approve who sells on my site" | site owner | Sell Services → Vendors | Gatekeeping. `wpss_vendor` is the only role activation adds; 2 cards this cycle (approve button, decision emails) | | Free |
| 5 | "I can hold a service back before it goes live" | site owner | Sell Services → Moderation | `wpss_vendor[require_service_moderation]`; 1 card this cycle | | Free |
| 6 | "A buyer tells the seller what they need, then the seller delivers" | buyer → vendor | `/dashboard/orders/<id>/` requirements form, order states | The fulfilment loop. Requirement types were a card this cycle | | Free |
| 7 | "Both sides can talk about an order" | buyer ↔ vendor | `/dashboard/messages/` | Every marketplace order needs a conversation | | Free |
| 8 | "When it goes wrong, someone can raise a dispute and I can settle it" | buyer/vendor → owner | `/dashboard/disputes/`, Sell Services → Disputes | The path owners discover during their worst week; 1 card this cycle | | Free |
| 9 | "A seller can see what they earned and take it out" | vendor | `/dashboard/earnings/`, Withdrawals | Money leaving the system; 1 card this cycle | | Free |
| 10 | "A buyer can ask for something nobody is selling yet" | buyer | `/buyer-request/`, `/dashboard/create-request/` | Activation inserts the page; 1 card this cycle | | Free |
| 11 | "Someone can find a seller rather than a service" | visitor | `/vendors/`, vendor profile | Activation inserts the vendors page | | Free |
| 12 | "I can become a seller on this site" | buyer → vendor | `/become-a-vendor/` | Activation inserts the page; the on-ramp for flow 3 | | Free |

## Edge (walk after core — welcome findings, never the starting point)

| Flow | Role | Surface | Why it is edge |
|---|---|---|---|
| Portfolio items on a vendor profile | vendor | single service / vendor profile | Optional content; a service sells without one |
| Service add-ons | vendor/buyer | service page, order modal | Optional per service — no service on the QA site had any until this cycle seeded them |
| Showcase video in the gallery | vendor/buyer | single service | Optional; images alone are a complete gallery |
| Tips | buyer | order view | Post-completion, optional |
| Milestones | buyer/vendor | order view | Pro-adjacent, not on the default order path |
| WooCommerce / EDD / FluentCart rails | owner | Settings → `ecommerce_platform` | Only loads when the rail is selected; `standalone` is the default |
| Multi-category buyer requests | buyer | buyer request card/single | Works with one category, which is the common case |

## Zero-config check (C-2)

The first thing a new owner would try, with **no settings touched**:

**Activate the plugin → open `/services/` → open a service → reach checkout.**

On a fresh activate this must complete without the owner configuring anything.
Two things make this the right zero-config probe: `ecommerce_platform` defaults
to `auto`/`standalone`, so no payment rail has been chosen yet; and the catalog
is the only surface that is populated the moment the plugin is on.

**Known zero-config gap, found this cycle:** a fresh install has **no Terms page
mapped**, and the admin says so on every screen — *"Buyers are paying on this
site, but no Terms page is mapped."* The checkout still completes, so this is a
warning rather than a block, but any zero-config walk will meet that notice
first and should not treat it as a defect.

## Open questions for the human confirming this

1. **Is flow 3 really above flow 2?** Cards cluster on service creation, but a
   marketplace with no checkout is worth less than one with no wizard. Ranked
   checkout higher on consequence; the reporting data says the opposite.
2. **Should vendor approval (4) sit above service moderation (5)?** Both are
   owner gatekeeping. Ranked approval higher because `vendor_registration` is
   `open` by default while `require_service_moderation` is a setting.
3. **Is the buyer-request flow (10) core or edge?** It has its own inserted page
   and CPT, which argues core; but a marketplace functions with nobody ever
   posting a request.
