# Archived audits

Superseded. Kept for history, **not** for planning — several describe code that
no longer exists, and following them will send you down paths already walked.

Active docs live one level up. Start at `../HANDOFF.md`.

| File | Was | Why archived |
|---|---|---|
| `CODE_FLOWS.md` | Flow maps, 2026-05-08 | Pre-1.2.x. Superseded by `../docs/architecture/MONEY-FLOW.md` for money and by the manifest for structure. |
| `FEATURE_AUDIT.md` | Feature inventory, 2026-05-08 | Snapshot of a much older feature set. |
| `ROLE_MATRIX.md` | Role/capability matrix, 2026-05-08 | Predates role-granted vendors and the current capability set. |
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
