# Archived audits

Superseded. Kept for history, **not** for planning — several describe code that
no longer exists, and following them will send you down paths already walked.

Active docs live one level up. Start at `../FEATURE_AUDIT.md` for the inventory and `../FLOW-AUDIT.md` for gaps. **Open work lives on the Basecamp board, not in a markdown plan** — that is the whole reason the plans below are here.

| File | Was | Why archived |
|---|---|---|
| `CODE_FLOWS.md` | Flow maps, 2026-05-08 | Pre-1.2.x. Superseded by `../docs/architecture/MONEY-FLOW.md` for money and by the manifest for structure. |
| `FEATURE_AUDIT.md` | Feature inventory, 2026-05-08 | Snapshot of a much older feature set. |
| `ROLE_MATRIX.md` | Role/capability matrix, 2026-05-08 | Predates role-granted vendors and the current capability set. |
| `REFUND-PLAN.md` | Refund plan, 2026-07-21 | **The work shipped in 1.2.3.** The document still says "PLAN — not implemented", which is now the opposite of the truth: `OrderWorkflowManager::settle_refund()` refunds at the gateway and reverses vendor earnings on every refund path. Read the code, not this. |
| `wppqa-baseline-2026-05-08/` | QA baseline | Superseded by `wppqa-baseline-2026-06-10/`. |
| `wppqa-baseline-2026-06-06/` | QA baseline | Superseded by `wppqa-baseline-2026-06-10/`. |
| `TASKS.md` | Master task list, branch 1.2.2 | A closed sprint's list, five releases old — its final item is "before tagging 1.2.2". Its headline open items are stale: the Stripe inline-duplicate was fixed in 1.2.2 and the PayPal payout double-pay in 1.4.0. Anything genuinely still open belongs on the board. |
| `MONEY-FLOW-PLAN.md` | Money-flow audit + plan | Every task marked done, none open. The living version is `../../docs/architecture/MONEY-FLOW.md`. |
| `COMMISSION-ARCHITECTURE.md` | Target architecture | Target reached — commission computes in one place (`CommissionService::compute_breakdown()`) and gateways execute what was persisted. |
| `PAYMENT-ARCHITECTURE-RND.md` | R&D + target design | Target reached — one rail owns payment, `wpss_uses_standalone_payments()` decides, and the pay-order seam shipped. |
| `HANDOFF.md` | Session handoff, 2026-07-23 | Superseded by the 1.6.0 handoffs (also archived) and by the 1.7.0 sweep. Follows `HANDOFF-2026-07-20.md` into here. |
| `AUDIT-VERDICT.md` | 1.2.0 cycle verdict, 2026-06-08 | That cycle shipped. |
| `journey-audit-2026-07-19.md` | Journey audit | Findings were consumed into `REMEDIATION-PLAN.md` → `TASKS.md`. |
| `usability-audit-2026-07-19.md` | Usability / template flow audit | Same. |
| `gapfill-audit-2026-07-19.md` | Disputes / admin / discovery gaps | Same. |
| `REMEDIATION-PLAN.md` | Master remediation plan | Fully consumed into `TASKS.md`; zero open checkboxes of its own. |
| `HANDOFF-2026-07-20.md` | Session handoff | Replaced by `../HANDOFF.md`. |

## Not archived, and why

- **`../TASKS.md`** — still carries **38 open items, including P0s** (notably
  PayPal and Razorpay hitting the same unregistered-gateway-action wall that was
  fixed for Stripe). It must be triaged into `../audit/FLOW-AUDIT.md` before it
  can be archived. Archiving it now would silently bury real work.
- **`../COMMISSION-ARCHITECTURE.md`**, **`../DUPLICATE-FLOWS-money.md`**,
  **`../DUPLICATE-FLOWS-ui.md`** — money and duplication analysis that has NOT
  been reconciled against `../MONEY-FLOW-PLAN.md`. They may contradict it. Read
  the plan first; treat these as unverified until reconciled.
