# Member Reports

Members can report a service or a seller they think breaks your marketplace's rules. Every report lands in one queue in wp-admin, where you decide what to do about it.

## How Members Report

Two places on the website carry a Report link:

- **Service page:** "Report this service", at the bottom of the sidebar.
- **Vendor profile:** "Report this seller", at the bottom of the profile sidebar.

Clicking it opens a short form. The member picks a reason and can add details:

| Reason | Use it for |
|--------|-----------|
| Spam or advertising | Listings that exist to advertise something else |
| Offensive or abusive | Abusive wording or imagery |
| Harassment or bullying | A member targeting another member |
| Scam or fraud | Taking money with no intention to deliver |
| Trying to take payment off the platform | Asking buyers to pay outside your checkout |
| Misleading or inaccurate | A listing that does not match what is delivered |
| Copyright or trademark violation | Using work or brands the seller does not own |
| Adult or explicit content | Content your marketplace does not allow |
| Something else | Anything not covered above |

A few rules keep the queue useful:

- The member must be logged in. A visitor who clicks the link is sent to sign in and brought back.
- Nobody sees a Report link on their own service or profile.
- A member can report the same service or seller once. A second attempt tells them the team is already reviewing it.
- The person who is reported is never told who sent the report.

Reporting from reviews and messages is planned for a later release. The mobile app can already report all four through `POST wpss/v1/reports`.

## Reviewing Reports

Go to **Sell Services > Member Reports**. The menu shows a count while reports are waiting.

Three tabs filter the list:

| Tab | Shows |
|-----|-------|
| **Needs review** | Open reports, newest first |
| **Dealt with** | Reports you have already closed |
| **All** | Everything |

Each row shows who was reported, the reason, what was reported (with its ID), the details the member typed, who sent it, and when.

## Closing a Report

Each open report has two buttons:

- **Uphold** closes the report and records that you agreed with it.
- **Dismiss** closes the report and records that you did not.

Both only record your decision. Neither does anything to the reported member or their listing. If two admins work the queue at once and one has already closed a report, the other is told "Someone else already dealt with that report."

## Acting on a Member's Account

Open the **Account** menu on a report to change the reported member's standing:

| Action | What it does |
|--------|--------------|
| **Suspend member** | The member cannot list, bid, buy, or start conversations. Orders already paid for can still be completed. |
| **Close account** | The same restrictions, and it reads to the member as permanent. Orders already paid for can still be completed. |
| **Restore member** | Puts a suspended or closed account back in good standing. |

Suspending and closing both ask you to confirm first. The confirmation opens with **Cancel** selected and the action button in red. Administrators cannot be suspended from this screen.

Account standing applies to the person, not only to their selling: a suspended member is stopped as a buyer too. It is separate from vendor status (pending, active, rejected), which you manage under [Vendor Management](vendor-management.md).

## Tips

- Read the details and open the reported service before deciding. One report is a lead, not a verdict.
- Several reports against the same seller for the same reason are worth acting on quickly.
- Use **Suspend member** while you investigate; it can be undone with **Restore member**.
- To deal with a single bad listing rather than the person, use [Service Moderation](service-moderation.md).

## Related Docs

- [Service Moderation & Approval Queue](service-moderation.md)
- [Vendor Management](vendor-management.md)
- [Opening a Dispute](../disputes-resolution/opening-a-dispute.md)
- [REST API Controllers](../developer-guide/rest-api-controllers.md)
