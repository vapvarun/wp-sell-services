# Ready for Testing — 1.7.0 verification checklist

**45 distinct cards** in Ready for Testing as of 2026-08-26. Two more are
duplicates marked on the board and must not be tested twice (see the bottom of
this page).

Read [`TESTING-GUIDELINE.md`](TESTING-GUIDELINE.md) first. This page is the
coverage list — it says *what* to walk. The guideline says *how* to judge what
you see, and the walks themselves are in [`../../audit/journeys/`](../../audit/journeys/).

**Each line below is the expectation, not the defect.** If the line is true when
you finish, the card passes. Card numbers are Basecamp ids.

---

## Group A — Money and payments

Test these first. Everything here can move real money or expose a money route.

| # | Card | Passes when |
|---|---|---|
| A1 | 10236008519 | **P0.** Following the dispute documentation start to finish refunds the buyer **once**. The docs no longer tell you to refund by hand in the gateway. |
| A2 | 10236330376 | **P1.** A logged-in buyer who is not a vendor is refused on wallet and withdrawal GETs. Not answered with zeros — refused. |
| A3 | 10236330517 | **P1.** `GET /payments/methods` requires auth regardless of plugin load order. Test with Pro active and with Pro inactive. |
| A4 | 10236330943 | Every vendor-only money route refuses a buyer the same way. No route is softer than its neighbours. |
| A5 | 10236330775 | Free and Pro return the same shape from the withdrawal list. A client written against Free does not break under Pro. |
| A6 | 10236330640 | A vendor-denied response carries the same error code on every related endpoint. |
| A7 | 10203245612 | An owner can define several named offline methods, and the name the buyer chose is still readable on the order after the method is renamed or removed. |
| A8 | 10235851403 | Auto-withdrawal copy says what Free actually does — creates a request. It does not say vendors are paid automatically. |
| A9 | 10235851532 | Wallet copy does not imply Free has no wallet. Free has earnings and withdrawals; the screen says so. |

**Suspended vendor**: walk A2–A6 again as a suspended vendor. That role kept full
access to every money route and no card had caught it.

## Group B — Cart and checkout

| # | Card | Passes when |
|---|---|---|
| B1 | 10235851469 | With Woo active and Standalone as the rail, the buyer never sees an empty cart while a badge says 1. One cart answers for the service. |
| B2 | 10236389213 | The site cart link points at whichever rail owns checkout, without the owner being asked to choose. **Still unverified on a licensed install where the Woo adapter genuinely owns checkout — that configuration is the point of the card.** |
| B3 | 10235853234 | Settings → Pages dropdowns make it impossible to map the wrong Checkout/Cart/Dashboard. Foreign pages are labelled with who owns them. |

## Group C — First install and setup

Use a genuinely clean install. Three of four first-install cards this cycle did
not reproduce on clean — they were found on a site carrying legacy data.

| # | Card | Passes when |
|---|---|---|
| C1 | 10235849730 | The wizard creates every page Settings expects. No page is required later that setup did not make. |
| C2 | 10235849842 | The advertised become-vendor URL resolves. No 404 on the registration path a member would guess or a doc would print. |
| C3 | 10235853527 | An owner looking for vendors cannot open the wrong screen. Two destinations 40px apart do not share a name. |

## Group D — Orders, extensions and requests

| # | Card | Passes when |
|---|---|---|
| D1 | 10213142722 | One route creates an extension. |
| D2 | 10213142125 | A quote raised on the website can be paid. It is not a dead end for the client. |
| D3 | 10213198691 | A seller queue can ask for "every status except these" without guessing client-side. |
| D4 | 10236358969 | What the buyer's request says on screen and what the database holds agree. |
| D5 | 10236358753 | A disputed order links to its dispute from the order itself. The buyer does not have to find the Disputes nav. |
| D6 | 10236358587 | For someone who both buys and sells, **My Orders** shows what they bought. It does not say "No sales yet" to a person with purchases. |
| D7 | 10236358463 | A buyer browsing a service page can see that add-ons exist, without clicking through to find out. |
| D8 | 10213148295 | Submitting a proposal raises no console error. Check the console, not just the outcome. |

## Group E — Email and notifications

For every toggle here run the **toggle test**: turn it off, do the thing it
suppresses, confirm the thing did not happen. Not that the box stayed unticked.

| # | Card | Passes when |
|---|---|---|
| E1 | 10236010913 | **Unticking "New Review" stops the review email.** Leave an actual review to check. |
| E2 | 10236012161 | Moderation email has a control that writes it, and unticking it stops the email. |
| E3 | 10199168939 | The delayed message email arrives only if the message is still unread when the delay expires. |

**Trap**: a 5-minute per-recipient cooldown swallows repeat message emails. Use a
different recipient or clear the transient, or a working email looks broken.

## Group F — Files, privacy and storage

| # | Card | Passes when |
|---|---|---|
| F1 | 10236009182 | **P1/Pro.** A delivery file actually arrives in the configured bucket. Settings saving and the connection test going green is not the check. |
| F2 | 10236010457 | Order-requirement uploads are not reachable by URL to someone who is not party to the order. The docs promise buyers these are private. |
| F3 | 10236009758 | A WordPress personal-data export returns this plugin's data, and an erasure removes it. Cross-check the export against row counts in the database — a missing table returns empty with no error. |
| F4 | 10236011401 | Analytics **Export PDF** returns a file, not HTTP 400. |

## Group G — Pro features and licensing

Pro registers **nothing** without a valid licence, and its settings still save
and report success while unlicensed. Every card in this group needs a licensed
install or it will pass hollowly.

| # | Card | Passes when |
|---|---|---|
| G1 | 10134362181 | Every Pro wizard capability the UI offers is reachable, or the flag is gone. Nothing is advertised that has no consumer. |
| G2 | 10236313127 | The License screen says what stops working **before** you press Deactivate. |
| G3 | 10236312970 | Vendor Analytics offers the export its charts imply, or the docs stop promising it. |
| G4 | 10235851326 | Seller Level commission rule labels match the badges members actually see. A rule built here applies to a real vendor. |

## Group H — Documentation

**Follow each page literally on a real install.** A wrong instruction is a
product defect — file it as one. This level found the P0 in Group A.

| # | Card | Passes when |
|---|---|---|
| H1 | 10235849910 | The Become a Vendor page promises only what the buyer's tier delivers. |
| H2 | 10235853369 | The service-per-vendor limit in the catalog matches the shipped default. |
| H3 | 10235853446 | `marketing/comparison/free-vs-pro.md` matches 1.6.0 — SureCart, AI wizard, licence expiry, tips. |
| H4 | 10236015292 | Recurring services and REST rate limiting are not promised in the README or elsewhere. |
| H5 | 10236014483 | The `theme-integration.md` snippet **moves** the vendor card. Paste it and confirm you get one card, not two. |
| H6 | 10236013836 | `wpss_extension_approved` is documented as live, with its real arguments. |
| H7 | 10236013288 | The developer guide documents the email filter that exists, and not one that does not. |
| H8 | 10236012656 | Menu Visibility instructions match the checkbox. Follow them to hide something and confirm it hides. |

## Group I — Performance, CLI and parity

| # | Card | Passes when |
|---|---|---|
| I1 | 10154918426 | Neither launch scan is unbounded. Seed 1000+ services and 500+ users before judging this one. |
| I2 | 10212618689 | `wp wpss demo marketplace --no-images` is accepted, or it is not documented. |
| I3 | 10184068444 | Parity round 2 — plugin PR #4 and app PR #2 verified together. |

---

## Do not test these — duplicates

Both are marked DUPLICATE on the card and need deleting from the Basecamp UI;
the trash API accepts the call and leaves them active.

| Duplicate | Test this instead |
|---|---|
| 10236298979 (`ISS-002`) | **10235849842** — C2, same become-vendor 404 |
| 10236299217 (`ISS-004`) | **10235851326** — G4, same Seller Level labels |

Both pairs were filed twice on 25 Aug: once from a Playwright journey at 11:27,
then again from the audit document at 13:33. Reconcile against the board before
filing from an audit, or finished work is regenerated as new cards.

---

## Coverage note

45 distinct cards. Groups A, F and G carry the P0/P1 work — start there. Groups
H and I can run in parallel with a second tester since they need no shared state.

Anything that renders gets checked at 390px and in dark mode. Every async
surface has empty, loading and error states, and you see the happy one by
default — ask for the other two.
