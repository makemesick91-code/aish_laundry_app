# DEC-0003 — Multi-Laundry Owner Model

## ID

DEC-0003

## Title

Multi-Laundry Owner Model

## Status

ACCEPTED

## Date

19 July 2026

## Context

The Indonesian laundry market has a structure that most generic SaaS tenancy models handle badly.

A successful laundry owner rarely stops at one shop. The common progression is: one outlet, then a second
outlet under the same name, then a differently-positioned brand aimed at a different price segment, then
sometimes a completely separate business with a business partner. Meanwhile staff move fluidly — a kasir
may work mornings at one owner's outlet and evenings at another's, and a courier may serve two businesses.

Naive models fail in specific ways:

- **One user, one business** forces staff to maintain multiple accounts and forces owners to log out and
  in to see each business.
- **Automatic merging on matching identity** — the tempting shortcut of treating "same owner name" or
  "same phone number" as "same business" — silently joins the books of two legally and commercially
  separate entities. In a market where a partner may own 40% of one business and none of another, this is
  catastrophic.
- **Flattening brands into tenants** makes billing incoherent: an owner with three brands would receive
  three subscriptions for what is commercially one business.
- **Flattening tenants into brands** breaks isolation between genuinely separate businesses, sometimes
  with different partners.

The product needs to represent all of: one person with many roles across many businesses; one business
with many brands; one brand with many outlets; and one owner deliberately keeping two businesses apart.

## Decision

Aish Laundry App adopts an explicit **multi-laundry owner model** built on the hierarchy in DEC-0002.

1. **A user account is a person**, identified by phone number, and there is exactly one account per
   person across the platform.
2. **A membership links a person to a tenant** and carries the roles and permissions held *within that
   tenant*. A person with three jobs has three memberships and one account.
3. **One owner may own or manage multiple tenants.** Multiple tenants is the correct, supported way to
   represent genuinely separate businesses — different partners, different books, different subscriptions.
4. **One tenant may have multiple brands**, and **one brand may have multiple outlets**. Multi-brand
   growth inside one business does **not** require a second tenant or a second subscription.
5. **A tenant switcher** is present in every authenticated client, so a multi-tenant person moves between
   businesses without logging out.
6. **Data is never merged merely because owner name, email, or phone number match.** Two customer records
   in two tenants with the same phone number are two independent records with independent histories. This
   is correct behaviour, not duplication.
7. **The owner portfolio consolidates within one tenant**, across that tenant's brands and outlets. It
   never consolidates across tenants by relaxing the tenant filter (DEC-0002 rule 13).
8. **Subscription and billing sit at the tenant boundary.** An owner with two tenants has two
   subscriptions, because they are two businesses.

## Consequences

Membership becomes the unit of authorisation throughout the system; no code path authorises against a
bare user account. The tenant switcher becomes a first-class navigation element in Ops Android and
Console Web, and switching clears or partitions client caches. Growth from one outlet to a multi-brand
chain happens inside one tenant and one subscription, which is also the commercially attractive path.
Any future cross-tenant view requires explicit consent, separate authorisation, full audit, and its own
decision record.

## Positive consequences

- Matches how Indonesian laundry businesses actually grow, rather than forcing owners into an artificial
  structure.
- Staff keep one account and one phone number regardless of how many businesses employ them.
- An owner adding a second brand or a fifth outlet expands inside the product instead of outgrowing it,
  which aligns product design with the retention metrics in §29.
- Genuinely separate businesses — different partners, different books — stay genuinely separate, which
  protects the owner legally and commercially.
- Rule 6 removes an entire category of catastrophic data-merge incidents by prohibiting the shortcut
  outright.

## Negative consequences / trade-offs

- **An owner of several tenants cannot see one combined number across them.** This is a real product
  limitation and it will be requested. It is accepted because the alternative weakens hard gate 1.
- Two subscriptions for two tenants may feel like double charging to an owner who thinks of them as "my
  businesses". Sales and documentation must explain the boundary clearly.
- A customer who uses two laundries served by two tenants exists twice in the platform. Support staff must
  understand this is by design.
- The membership indirection adds complexity to every authorisation path, and to onboarding: creating a
  user is not the same as granting access.
- Choosing between "another brand in this tenant" and "another tenant" is a genuine decision the owner
  must make, and making it wrong is expensive to undo. Onboarding must guide it explicitly.

## Verification

- From Step 3: tests asserting that a user with memberships in tenants A and B sees only A's data while
  A is active, and that switching cleanly changes context and clears client state.
- Tests asserting that two customers with the same phone number in different tenants remain separate and
  are never auto-merged.
- Tests asserting that the portfolio dashboard query never crosses a tenant boundary.
- Review checks that no authorisation path resolves permissions from a user account without a membership.
- At the Step 0 baseline: no runtime exists, so these tests are `NOT APPLICABLE`.

## Supersession policy

Superseded only by a decision record that defines the replacement ownership model, specifies how existing
memberships and subscriptions migrate, and demonstrates that tenant isolation remains intact throughout.
Any proposal to introduce cross-tenant consolidation requires its own decision record covering consent,
authorisation, audit, and the effect on hard gate 1. Requires at least a **minor** version bump of
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md), or **major** if a hard rule changes.

## Related Master Source sections

- §4 Multi-tenancy
- §7 Roles
- §12 Owner dashboard and portfolio
- §21 Pricing
- §29 Success metrics
