# Tiered Commission Rules [PRO]

Replace a single flat commission rate with **rules** that resolve automatically:
by service category, by seller level, or by sales volume. The right rate is
picked at checkout without you touching anything.

Free already gives you a global rate and per-vendor overrides. Tiered rules are
for when "which rate applies" is a question about the *sale*, not about a
specific vendor you hand-picked.

## The three rule types

| Type | Matches on | Typical use |
|------|-----------|-------------|
| **Category** | The service's category | Design pays 15%, Writing pays 20% |
| **Seller level** | The vendor's level (New, Rising, Top Rated) | Reward Top Rated sellers with 10% instead of 20% |
| **Volume** | The vendor's sales volume | Vendors over $10k/month drop to 12% |

## Creating rules

1. Go to **Sell Services > Settings > Commission & Tax**.
2. Find the **Tiered Commission** card.
3. Add a rule: pick the type, what it matches, and the rate.
4. Set a **priority** number.
5. Save. Rules can be deactivated without deleting them.

## How a rate is chosen

Rules are evaluated in **ascending priority order -- lowest number first** -- and
the **first rule that matches wins**. Evaluation stops there; later rules are not
consulted, and rates are never averaged or stacked.

The default priority is 10, so give your most specific rules a lower number.

```
priority 5   Seller level = Top Rated       ->  10%
priority 10  Category = Design              ->  15%
priority 20  Volume over $10,000/month      ->  12%
```

A Top Rated designer in the example above pays **10%**, because the seller-level
rule is evaluated first. If you wanted category to win for that vendor, give the
category rule the lower number.

If no rule matches, resolution falls back through:

1. A matching tiered rule (this page)
2. The vendor's per-vendor custom rate, if set (free)
3. The global commission rate (free)

A vendor subscription plan can also carry a commission override, applied when
present -- see [Vendor Subscription Plans](../vendor-system/vendor-subscription-plans.md).

## Preview before you commit

The commission-rules REST endpoint exposes a `preview` route that resolves which
rule *would* apply to a given vendor, category, and amount without saving
anything. Use it to sanity-check a rule set, or to explain a rate inside your own
UI. See [REST API Controllers](../developer-guide/rest-api-controllers.md).

## How the rate is recorded

The resolved rate computes the platform fee and vendor earnings **once, at
payment time**, and both are stored on the order. Earnings, the vendor ledger,
payouts, analytics, refunds, and the Stripe Connect application fee all read that
stored figure.

This means **changing a rule does not rewrite history**. Orders already paid keep
the rate they settled at, and a refund reverses proportionally at the original
rate rather than today's.

## Worked examples

- Top Rated sellers pay 10 percent instead of 20.
- The Design category pays 15 percent.
- Vendors over $10k in monthly sales drop to 12 percent.
- A specific enterprise vendor pays a flat 5 percent -- use a **per-vendor rate** (free), not a tiered rule. Tiered rules are for classes of sale, not individuals.

## For developers

The tiered manager hooks the free plugin's `wpss_commission_rate` filter at
priority 20 -- deliberately later than the default 10 -- so per-vendor overrides
set by other code are not clobbered silently. Flat (non-percentage) fees go
through `wpss_commission_fee` instead.

See [Pro Extension Points](../developer-guide/pro-extension-points.md).

## Related

- [Commission System](commission-system.md) -- global and per-vendor rates (free)
- [Paying Your Vendors](vendor-payouts.md)
- [Seller Levels](../vendor-system/seller-levels.md)
