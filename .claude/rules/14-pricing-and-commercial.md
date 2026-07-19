# Rule 14 — Pricing and Commercial Guardrails

## Purpose

Pricing is a **locked commercial decision** of the repository owner, not an engineering variable. This
rule reproduces the pricing exactly and fixes the guardrails that prevent the product from being
quietly repositioned by implementation choices.

Backed by **DEC-0009 (Initial Commercial Pricing)**, **DEC-0010 (No Lifetime Cloud Subscription)**,
and **DEC-0011 (Transparent Third-Party Messaging Costs)**.

Note: this repository is **PUBLIC** by deliberate owner decision (AMENDMENT-0001). Commercial pricing
recorded here is publicly visible. That was an accepted consequence, but it means pricing text must be
accurate at all times.

## Pricing (reproduce exactly)

```
Trial: 14 hari gratis
Starter: Rp79.000/bulan — 1 outlet, 5 staff, hingga 1.000 order/bulan fair-use
Growth: Rp199.000/bulan — hingga 3 outlet, 20 staff, hingga 5.000 order/bulan
Scale: Rp399.000/bulan — hingga 10 outlet, 75 staff, hingga 20.000 order/bulan
Enterprise: mulai Rp999.000/bulan
Annual: Starter Rp790.000/tahun; Growth Rp1.990.000/tahun; Scale Rp3.990.000/tahun
```

These figures are canonical. Do not round them, reformat the amounts, convert them, "simplify" the
limits, translate the plan names, or restate them from memory. When pricing appears anywhere — UI,
documentation, marketing copy, tests, fixtures — it must match the Master Source character for
character.

## Guardrails

1. **No lifetime cloud plan.** Ever. A cloud service carries perpetual cost; a one-time fee for
   perpetual service is structurally unsound (DEC-0010).
2. **No per-nota fee on normal plans.** Standard plans are not charged per receipt/order document.
3. **Transparent provider costs.** Third-party costs are disclosed, not buried.
4. **The security baseline is not locked behind expensive plans.** Authentication, authorization,
   secure storage, rate limiting, and audit logging are available on every plan including Starter.
   Security is not an upsell.
5. **Tenant isolation is not an add-on.** It is the architecture (Rule 02). It is never a paid
   feature, a premium tier, or an option.
6. **Backup is not a premium security add-on.** Encrypted backup is baseline, not a tier.
7. **Pricing changes require a decision record.** A new or amended decision record under
   `docs/decisions/`, plus a Master Source version bump and checksum refresh (Rule 00).
8. **WhatsApp provider fees are billed separately** from the subscription plan (Rule 08). And never
   promise fake "unlimited WhatsApp".
9. **Tenant data remains exportable per policy when a subscription lapses.** A lapsed subscription
   does not hold a tenant's own business data hostage.

## Implementation consequences

- Plan limits (outlets, staff, orders per month) are **enforced server-side**, tenant-scoped, and
  presented honestly to the tenant. Starter's order limit is explicitly **fair-use**, and must be
  described as fair-use — not as a hard cutoff — unless a decision record changes that.
- Subscription and billing operate at the **tenant boundary** (Rule 02, hard rule 6).
- Money in billing is **integer Rupiah** and follows every rule in Rule 04.
- Pricing displayed to a user is read from a single canonical configuration derived from the Master
  Source, never hard-coded in scattered UI strings.
- Downgrade, lapse, and grace behaviour must be defined before billing ships, and must honour
  guardrail 9.
- No plan may be constructed that violates guardrails 1–9 as a "custom Enterprise deal" without an
  owner decision record. Enterprise starts at Rp999.000/bulan; it is not a guardrail exemption.

## Step 0 note

No subscription, billing, metering, or plan-limit implementation exists. Subscription and platform
administration arrive in Step 12. Step 0 records the commercial decision only.

## Violation handling

- **Pricing figures altered, rounded, reformatted, or restated inaccurately anywhere in the
  repository** — correct them to match the Master Source exactly and report the discrepancy. Pricing
  drift on a public repository is a commercial risk, not a typo.
- **A pricing change made without a decision record and Master Source version bump** — revert it and
  escalate to the owner (Rule 00).
- **A security control, tenant isolation, or backup placed behind a paid tier** — reject the change
  outright; it breaches guardrails 4, 5, and 6.
- **A lifetime plan or per-nota fee introduced on a normal plan** — reject and escalate.
- **"Unlimited WhatsApp" or equivalent** — remove immediately as a false claim (Rules 01 and 08).
- **Export blocked for a lapsed tenant** — reject; it breaches guardrail 9.
- An agent must **never** invent a plan, discount, promotion, trial extension, or limit that is not in
  the table above. Pricing is owner territory (Rule 12).
