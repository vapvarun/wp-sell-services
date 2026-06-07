# Flow Completeness Audit - 2026-06-07

Four parallel read-only agents traced every feature across the three entry
points (frontend UX / admin UI / REST) per the three-entry-point rule.
Evidence file:line in the agent transcripts; this is the synthesis.

## Fully cooked (no action)

Disputes (all three FULL incl. admin resolve/refund) - Withdrawals/Earnings
(full chain incl. admin processing) - Cart/Checkout (login-required by
design) - Setup Wizard - Guided Tour (filter-stubbed steps are by design) -
Reviews submit/display - Order messaging + pre-sale contact - Moderation
admin side - Vendor registration approval chain - Portfolio (vendor+public).

## Half-cooked, ranked by customer impact

### P1 - end-user flow breaks or invisible feature
| # | Feature | Gap | Fix shape |
|---|---|---|---|
| 1 | Buyer Requests | Buyers CANNOT post a request from the frontend: REST + archive + vendor side exist, but no create-form wiring on [wpss_post_request] | Wire the post-request form template to POST /buyer-requests (template + JS, no new API) |
| 2 | Wallet transactions | Money flows (tips/milestones/commissions) write wpss_wallet_transactions but vendors have NO transaction history UI, admin sees balance only, NO REST read | New /wallet REST read + dashboard "Transactions" list + admin vendor-drawer tab |
| 3 | Moderation resubmit UX | Rejected vendors get email+badge but no "Resubmit for review" button; re-queue on republish is implicit and unexplained | Resubmit CTA + helper text on rejected services (template + existing service) |
| 4 | Requirements auto-transition | pending_requirements -> in_progress transition not visibly wired after buyer submits | Verify + wire status advance in RequirementsService submit path |

### P2 - admin can't manage what users do
| # | Feature | Gap | Fix shape |
|---|---|---|---|
| 5 | Audit log | Table + REST only; the in-admin viewer promised in AuditLogController.php:8 never shipped | Admin page: filterable list reading the existing REST |
| 6 | Review moderation | Approve/reject buried in VendorsPage drawer; no queue like ServiceModerationPage | Dedicated moderation queue tab reusing ServiceModerationPage pattern |
| 7 | Seller levels | Auto-progression works; admin cannot override/assign a tier | Override control on VendorsPage + meta + REST PATCH |
| 8 | Suborder admin (tips/milestones/extensions) | Visible as labeled rows only; no aggregate management/decline UI | Filters + row actions on OrdersListTable for suborder types |
| 9 | Notifications admin | No admin visibility into user notifications | Low priority: read-only viewer (or accept as user-private, document) |
| 10 | Portfolio admin | No admin moderation/feature control over portfolio items | Row actions in VendorsPage drawer |

### P3 - consistency / API completeness
| # | Feature | Gap | Fix shape |
|---|---|---|---|
| 11 | Vacation mode | No REST endpoint, no admin override | PATCH /vendors/{id}/vacation + admin toggle |
| 12 | Profile updates (incl. intro video) | Legacy AJAX only; no /vendors/me PATCH | Covered by wave-6.3 AJAX->REST migration (verify profile fields included) |
| 13 | Vendor approval email | wpss_vendor_registered fires but no approval/rejection email template found (35 templates, none vendor-approval) | Two templates + NotificationService wiring |
| 14 | Gateway settings UX | Stripe/PayPal config split between wizard step 2 and settings; webhook secret UX unsafe | Consolidate into Payments settings tab |

## Placement decision (owner)

Option A - fold all 14 into the running 1.2.0 cycle as waves 6.4a/6.4b
(P1+P13 first, then P2/P3). Adds roughly 1.5-2x the remaining runtime.

Option B - 1.2.0 ships the current 15-wave scope (all known BUGS fixed,
shell unified, scale proven); these 14 feature-completion items become the
pre-planned 1.3.0 cycle manifest, drafted now from this doc.

Either way: items 12 (already in wave-6.3) and 4 (a bug by the zero-bug
policy - fold into 1.2.0 regardless).
