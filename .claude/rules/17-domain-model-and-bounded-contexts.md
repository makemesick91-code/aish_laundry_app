# Rule 17 — Domain Model and Bounded Contexts

## Purpose

To fix one vocabulary and one set of boundaries for the whole product, so that later Steps extend a
coherent domain instead of accumulating synonyms and overlapping models. Delivered in Step 1.

## The glossary is binding

`docs/domain/DOMAIN_GLOSSARY.md` defines the canonical domain vocabulary.

1. **Use glossary terms exactly.** Code identifiers, table names, API fields, event names, UI copy keys,
   documentation, and commit messages use the glossary term, not a synonym.
2. **A new domain term requires a glossary entry** in the same pull request that introduces it.
3. **One concept, one term.** `order` and `transaksi` are not interchangeable; `courier` and `driver` are
   not interchangeable. Synonyms are how a domain model quietly forks.
4. Bahasa Indonesia remains the user-facing language (Master Source §1.6); the glossary records the
   canonical Indonesian term alongside the technical identifier where they differ.

## The twenty bounded contexts

The canonical bounded contexts are fixed in `docs/domain/BOUNDED_CONTEXTS.md`:

Identity and Access · Tenant and Organization · Subscription and Entitlement · Customer Management ·
Service Catalog and Pricing · Order Intake and POS · Production Operations · Quality Control and Rework ·
Payment and Receivables · Customer Tracking · Pickup and Delivery · Courier Assignment and Settlement ·
Notification and Communication · Unclaimed Laundry Recovery · Loyalty, Membership, and Deposit ·
Reporting and Owner Portfolio · Audit and Compliance · Platform Administration · Offline Synchronization ·
File and Evidence Management.

Rules:

5. **Every aggregate belongs to exactly one bounded context.** An aggregate with no context, or with two,
   is a modelling defect.
6. **Contexts communicate through defined interfaces and domain events**, never by reaching into another
   context's data. Shared table access across a context boundary is what turns a modular monolith into a
   mud ball (Rule 06).
7. **Adding, removing, renaming, or merging a bounded context requires a decision record.** The context
   set is not adjusted to make an implementation convenient.
8. **Each context documents**: purpose, owner, primary actors, aggregates, commands, events, upstream and
   downstream contexts, synchronous and asynchronous dependencies, tenant boundary, sensitive data,
   failure impact, and the roadmap Step that delivers it.
9. **Backend module boundaries mirror the bounded contexts** (Master Source §6.2, Rule 06). The domain
   model and the code structure are the same structure.

## Aggregates

10. **Every business aggregate carries tenant ownership.** No exceptions for "small" or "lookup"
    aggregates (Rule 02, hard rule 7).
11. **Each aggregate documents**: aggregate root, entities, value objects, commands, invariants, allowed
    and forbidden transitions, domain events, tenant ownership, concurrency concerns, idempotency
    concerns, retention, sensitive fields, and its deletion or reversal policy.
12. **An aggregate is the consistency boundary.** Invariants hold inside it synchronously; consistency
    across aggregates is eventual and event-driven, and is documented as such.
13. **Value objects carry meaning, not just a type.** `Money` is integer Rupiah (Rule 04), never a raw
    number; `PhoneNumber` and `MaskedPhoneNumber` are distinct types because their disclosure rules
    differ; `TrackingTokenHash` is never the token.

## The conceptual model is not a schema

14. **Step 1 produces a conceptual domain model, not a database design.** Any entity-relationship diagram
    in Step 1 carries the literal marker `CONCEPTUAL DOMAIN MODEL — NOT DATABASE SCHEMA`.
15. Physical schema, indexes, and migrations arrive in **Step 3 and later**, and are forbidden in Step 1
    (Rule 06, Master Source §24.1).

## Step 1 note

No domain implementation exists. There is no code, no schema, no migration, and no runtime. The domain
model is `NOT IMPLEMENTED`; the backend runtime is `ABSENT`.

## Violation handling

- **A synonym used in place of a glossary term** — correct it and add the mapping to the glossary if the
  synonym is genuinely common in the business.
- **A new domain term introduced without a glossary entry** — the change is incomplete; add the entry.
- **An aggregate with no bounded context, or spanning two** — reject; the boundary must be decided before
  code depends on it.
- **A bounded context added, renamed, or merged without a decision record** — revert and escalate.
- **A context reaching directly into another context's data** — reject the change; route it through the
  owning context's interface or a domain event.
- **An aggregate without tenant ownership** — treat as a tenant-isolation defect (Rule 02), not a
  modelling nitpick.
- **A Step 1 diagram presented as a database schema, or a schema/migration created in Step 1** — remove
  it and report the scope breach.
