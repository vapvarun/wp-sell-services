# Archived Plans (1.0.0 era)

These documents were the QA + planning artifacts for the **1.0.0** release. They are kept here for historical reference only.

**For the active 1.1.0 QA cycle, use `docs/qa/1.1.0-qa-checklist.md` — that is the single source of truth for the QA team.**

| File | Purpose at the time | Why archived |
|---|---|---|
| `QA-CHECKLIST-FREE.md` | 416-test full regression of the free plugin at 1.0.0 launch | Superseded by the focused 1.1.0 checklist; full regression is no longer the workflow |
| `QA-CHECKLIST-PRO.md` | 309-test full regression of Pro at 1.0.0 launch | Same — Pro 1.1.0 changes are covered in §17 + §18 of the new checklist |
| `QA-SUITE.md` | High-level QA process narrative | Outdated — process is now per-release |
| `MANUAL-TESTING-FLOWS.md` | Manual smoke flows during 1.0.0 development | Replaced by the 1.1.0 setup section |
| `USABILITY-TEST-CASES.md` | Usability scenarios for 1.0.0 | Pre-dates the unified admin UX; needs rewrite if revived |
| `PRODUCTION-READY-TEST-PLAN.md` | Pre-launch readiness gate for 1.0.0 | Single-use document; release-plan now lives in `RELEASE-PLAN.md` |
| `PREMIUM-UX-AUDIT.md` | UX audit before 1.0.0 polish pass | Findings folded into 1.1.0 admin UX delta |
| `POST-LAUNCH-ACTION-AUDIT-CI.md` | Post-launch CI hardening notes | Implementation-tracking doc, no longer actionable |

If you need to revive any of these for a future regression sweep, copy the relevant items into a new release-tagged checklist under `docs/qa/` rather than editing in place.
