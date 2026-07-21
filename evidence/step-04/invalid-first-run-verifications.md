# Step 4 — Invalid verification attempts, preserved

**Status:** RECORDED — these are FAILED verification attempts, kept deliberately.
**Runtime anchor:** `6673efd117bebfbdbb0290da824c1dfa1c1dcfa6`

Three assertions in the first run of
`apps/ops_android/integration_test/master_data_runtime_test.dart` reported green
while proving nothing. The suite said `All tests passed!` in every case.

They are recorded here rather than silently replaced, because a corrected result
with the failed attempt deleted is indistinguishable from a result that was
right the first time — and the reason each one was wrong is more useful than the
final green line.

Each entry gives the original output, why it was invalid, the correction, the
final valid result, and the mechanism that prevents recurrence.

---

## 1. Catalogue — an empty collection reported as success

**Original output**

```text
OPS-FLOW: catalogue=ok services=0 packages=0 addons=0 price-lists=0
```

**Why it was invalid.** The assertion was `expect(services.isOk, isTrue)`. The
development seeder creates no catalogue data, so the endpoint returned an empty
list and the assertion passed. It proved that the route answers and that the
caller is authorised. It proved nothing about serialisation, about the
tenant-scoped query, or about whole-Rupiah price handling — which is what the
requirement actually needs.

**Correction.** The service is now CREATED through the repository and read back,
so the collection contains a known record that must survive a round trip.

**Final valid result**

```text
OPS-FLOW: catalogue-write=ok
OPS-FLOW: catalogue=ok services=2 packages=0 addons=0 price-lists=0
```

**Recurrence prevention.** `requireFixture(services.valueOrNull!.isNotEmpty, …)`
fails the suite when the catalogue is empty. `packages` and `addons` remain `0`
and are NOT claimed as verified — see residual risk below.

---

## 2. Cross-tenant customer — a mandatory isolation assertion that skipped

**Original output**

```text
AUTHZ: foreign tenant has no customer to target — skipped
00:03 +4: All tests passed!
```

**Why it was invalid.** This is the most serious of the three. The test tried to
read a customer belonging to another tenant, found none to target, and returned
early. The suite reported a pass. The single most important isolation assertion
in the file — the one covering the product's central safety property (Rule 02) —
**did not execute at all**, and nothing in the output said so except one line
that read like an informational note.

**Correction.** The target is now created in the foreign tenant by a genuinely
authenticated member of that tenant, so a real cross-tenant identifier exists to
attempt.

**Final valid result**

```text
AUTHZ: cross-tenant-customer refused code=NOT_FOUND
```

`NOT_FOUND` rather than `FORBIDDEN` is correct: across a tenant boundary, denial
and absence must be indistinguishable (Rule 32, hard rule 2).

**Recurrence prevention.** The early return is gone. `requireFixture(...)` fails
the suite if the foreign customer cannot be created, because a mandatory
adversarial scenario that cannot run is a verification failure, not a skip.

---

## 3. Role escalation — an actor who was allowed to do it

**Original output**

```text
AUTHZ: role-escalation outcome=ALLOWED-for-this-actor
00:04 +5: All tests passed!
```

**Why it was invalid.** The test asked the server to grant `tenantOwner`, and the
server allowed it. That was the correct server behaviour: the acting identity was
`owner.kenanga@contoh.invalid`, a **tenant owner**, for whom granting
`tenantOwner` is within authority. The test was therefore not an escalation test.
It asserted only `escalate.isOk || escalate.isErr`, which is a tautology.

This is the subtlest of the three, because both the server and the assertion
behaved exactly as written. The defect was in the CHOICE OF ACTOR.

**Correction.** The scenario now runs as `lintas.tenant@contoh.invalid`, whose
membership in the target tenant is `outlet_manager`, and asserts refusal.

**Final valid result**

```text
AUTHZ: escalation-actor-roles=[outlet_manager]
AUTHZ: role-escalation refused code=FORBIDDEN
AUTHZ: session-survives-refusal=true
```

**Recurrence prevention.** The guard is on the ACTOR, not just the outcome. The
test reads the live membership roles and fails if the identity holds
`tenant_owner` or `tenant_admin`, so a change to the seeded roles breaks the test
loudly instead of quietly making it vacuous again. The observed roles are printed
with the result, so a reader can check the premise rather than trusting it.

---

## The common failure mode

All three share one shape: **an assertion that can pass for a reason unrelated to
what it claims to prove.** An empty collection, an unexecuted branch, and a
tautological expectation each produce a green line.

This is the same family as the defect recorded in DEC-0032 and threat T-51, where
a test-only provider override masked a missing production dependency. In both
cases the suite was green and the property was unproven.

The countermeasure adopted here is `requireFixture()`: a mandatory adversarial
scenario whose precondition cannot be constructed **fails the suite**. It never
degrades to a skip, because a skipped adversarial assertion reads as a pass.

## Residual risk — not closed by these corrections

- `packages=0` and `addons=0`. Service packages and add-ons are still read
  against empty collections. Their READ path is exercised; their round trip is
  not. This is stated rather than counted as verified, and it is the same class
  of weakness as entry 1 above.
- `price-lists=0`. Price-list integrity is covered by the backend suite
  (`PriceListIntegrityTest`, including overlap and exact-Rupiah behaviour), but
  not by the on-device runtime path.
- The corrections were verified on an Android 34 x86_64 emulator. No physical
  device was used.
