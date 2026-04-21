# Milestone / Phase Terminology

**Status:** Accepted  
**Date:** 2026-04-21  
**Scope:** All 1.1.0 surfaces (milestone contracts, proposal modal, order view, emails, notifications)

## Decision

We adopt a **two-noun split**:

- **Milestone** — the feature name and the system-level container / grouping noun.
- **Phase** — the unit noun: the individual item inside a milestone plan that has its own title, amount, delivery, approval, and sub-order row.

The split matches the Upwork convention (which the plugin's 1.1.0 milestone contract model is deliberately patterned on) and keeps the field-level, row-level, and button-level UX in a single consistent voice while preserving "Milestone" as the admin-facing / searchable feature name.

## Where each noun is used

### "Milestone" (container / system)

- Feature name in admin settings, documentation, and changelogs.
- Section headings that group the phases:
  - `Milestones (N)` header on the order view.
  - `Milestone: <phase title>` badges in sales/orders list rows.
- Sub-order badge in dashboard lists (`Milestone`).
- Email **subjects** and in-app **notification titles** (searchable inbox tokens):
  - `[Site] Milestone proposed: <phase>`
  - `[Site] Milestone paid — start work: <phase>`
  - `[Site] Milestone delivered: <phase>`
  - `[Site] Milestone approved: <phase>`
- Sub-order `platform` marker (`milestone`) and internal service name (`MilestoneService`).

### "Phase" (user-facing unit noun)

- Body copy inside emails and notifications (the thing the buyer pays / seller delivers).
- Form field labels in the proposal modal (`Phase title`, `+ Add another phase`).
- Inline button labels that act on a single phase (`Submit delivery`, `Approve delivery`, `Review & approve`, `Decline`, `Cancel proposal`).
- Receipt / detail-view titles when the page is about one item (`Paid — seller working`, `Delivery ready for your review`).
- Helper text (`This phase unlocks once the earlier phase is approved…`).
- `View phase breakdown` details summary on the proposal review card.

## Rationale

1. **Pattern-match to known UX.** Buyers and sellers familiar with Upwork already read "Milestones" as the container and the numbered rows as "phases" / "milestones of the plan". Splitting the noun matches the model they already have.
2. **"Phase" is friendlier in body copy.** "Pay the first phase" reads cleanly; "Pay the first milestone" scans as jargon.
3. **"Milestone" is a better inbox search token.** When a buyer searches Gmail for "milestone", they should find every sub-order email related to this feature. Using "Milestone" in every subject line guarantees that.
4. **Avoids pairing the two nouns in the same string.** Before this decision, the UI shipped strings like "3 phase milestone plan" (ungrammatical, mixes both nouns). After, breakdown says "%d-phase plan" and the email subject says "Milestone proposed".

## Consequences

- **Button "+ Propose Milestone"** on the order view → `+ Propose a phase`.
- **Modal title "Propose a Milestone"** → `Propose a phase`.
- **"View milestone breakdown" details summary** → `View phase breakdown`.
- **Proposal badge "%d phase milestone plan"** → `%d-phase plan`.
- **Milestone-view page titles** (vendor/buyer receipts) already use "Milestone" as the noun for the item — this is fine because the section heading is the "Milestone" container. Individual inline copy inside the card uses "phase" when referencing the thing being paid / delivered.
- **Email subjects stay "Milestone …"** (inbox token rule above).
- **Email bodies** say "phase" when referring to the thing the buyer is paying / approving.
- **Milestone-based radio** on the proposal form stays as "Milestone-based" (legacy value the server expects). The helper text uses "phased plan".

## Non-scope

- The database column `contract_type` value (`milestone`) and the service class name `MilestoneService` are system-internal and are not changed — only user-facing copy.
- Admin settings pages are not in the 1.1.0 language sweep.
