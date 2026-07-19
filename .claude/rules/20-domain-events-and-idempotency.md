# Rule 20 — Domain Events and Idempotency

## Purpose

Domain events are how bounded contexts stay decoupled, and idempotency is what keeps them correct when
the network misbehaves. In a product that takes money on a patchy connection, "at least once" delivery is
the reality and "exactly once" effect is the requirement. Delivered in Step 1, enforced from Step 3.

Canonical catalogues: `docs/domain/DOMAIN_EVENTS.md` and `docs/domain/COMMANDS_AND_POLICIES.md`.

## Commands versus events

1. A **command** expresses intent, may be rejected, and is named imperatively — `RecordPayment`,
   `AssignCourier`.
2. An **event** states that something already happened, is never rejected, and is named in the past
   tense — `PaymentRecorded`, `CourierAssigned`.
3. A **policy** reacts to an event by issuing a command. Policies are where cross-context behaviour
   lives, and each one is documented with its trigger, its guard, and its resulting command.
4. **An event is never used to ask for something.** An event that means "please do X" is a command
   wearing the wrong name, and it will couple the contexts it was supposed to decouple.

## Event rules

5. **Every event belongs to exactly one source aggregate** in exactly one bounded context. An event with
   no owning aggregate is a modelling defect (Rule 17).
6. **Every event carries tenant context explicitly.** A consumer never infers the tenant from ambient
   state, "the last request", or a shared connection (Rule 02).
7. **Every event carries**: event name, version, event ID, occurrence timestamp, tenant, actor, the
   aggregate identity, and a correlation identifier that survives queue hops.
8. **Events are immutable.** A published event is never edited. A mistake is corrected by publishing a
   compensating event, never by rewriting history (this mirrors Rule 04's reversal-only discipline).
9. **Events are versioned.** A breaking change to an event payload is a new version, not a silent change
   under existing consumers.
10. **Events never carry secrets or unnecessary personal data** — no plaintext tracking token, no OTP, no
    credential, no full address beyond what the consumer genuinely needs (Rule 03).
11. **Server timestamps are authoritative** for ordering. Client clocks are skewed and are treated as
    untrusted metadata (Rule 07).

## Idempotency rules

12. **Idempotency is a server contract, not a client trick.** The server recognises a repeated
    `ClientReference` and returns the original result instead of creating a second record.
13. **`ClientReference` is generated once, persisted with the queued operation, and reused on every
    retry.** Regenerating it on retry defeats the entire mechanism and is the highest-risk bug class in
    the offline design (Rule 07).
14. **Every consumer is idempotent.** Message infrastructure delivers at least once; a consumer that
    cannot tolerate redelivery will eventually double-charge, double-notify, or double-ship.
15. **Deduplication keys are explicit and documented.** Notification dedup is keyed on recipient, event,
    order, and intended send window (Rule 08). Payment dedup is keyed on `ClientReference` (Rule 04).
16. **A duplicate payment or duplicate order caused by a retry is an automatic NO-GO** (Rule 04).
17. **Gateway callbacks are verified and replay-protected** — signature, amount, and status verified
    server-side; a replayed callback is rejected, not reprocessed (Rule 04).
18. **Retries use bounded exponential backoff**, never a tight loop against a struggling service.
19. **A failed event handler is never silently dropped.** It is retried under a bounded policy and then
    made visible for human attention (Rule 08).

## Ordering and dependency

20. **Dependent operations preserve their order.** `CreateOrder` precedes `RecordPayment`; an operation
    whose predecessor failed does not jump ahead (Rule 07).
21. **Consistency across aggregates is eventual and is documented as such.** Where a user could observe
    an intermediate state, the interface says so rather than pretending the update was atomic.
22. **A messaging or provider failure never changes business state** (Rule 08, Rule 19).

## Step 1 note

No event bus, queue, consumer, or idempotency implementation exists. Redis is `ABSENT`; the backend
runtime is `ABSENT`. Step 1 records the catalogue only. Implementation begins at **Step 3** and the
idempotency test suite is mandatory from **Step 5** (Rule 13).

## Violation handling

- **An operation retried with a fresh `ClientReference`** — reject; this is the defining failure mode of
  the offline design (Rule 07).
- **A duplicate payment or duplicate order from a retry** — automatic **NO-GO**; preserve evidence at the
  exact SHA, notify the owner, fix, and add a regression test (Rule 04).
- **A non-idempotent consumer** — reject; redelivery is guaranteed, not hypothetical.
- **An event edited after publication** — reject; publish a compensating event instead.
- **An event with no owning aggregate, or carrying no tenant context** — modelling defect and a potential
  cross-tenant leak (Rule 02).
- **An event carrying a plaintext token, OTP, credential, or unnecessary personal data** — security
  defect (Rule 03); fix before the Step closes.
- **A breaking change made in place to an existing event version** — revert; ship a new version.
- **A gateway callback processed without server-side verification or replay protection** — critical
  financial and security defect (Rule 04).
