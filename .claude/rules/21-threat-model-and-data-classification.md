# Rule 21 — Threat Model and Data Classification

## Purpose

To keep security reasoning attached to the product as it changes, rather than performed once and
forgotten. A threat model that is not updated when a trust boundary moves is a document, not a control.
Delivered in Step 1, hardened in Step 13.

Canonical artefacts: `docs/security/INITIAL_THREAT_MODEL.md`, `docs/security/ABUSE_CASES.md`,
`docs/security/DATA_CLASSIFICATION.md`, `docs/security/TRUST_BOUNDARIES.md`.

## Threat model rules

1. **The method is STRIDE**, stated explicitly. A different method may be adopted only if it is named and
   its coverage is documented.
2. **Every threat records**: threat ID, category, actor, asset, precondition, scenario, impact,
   likelihood, severity, prevention, detection, response, residual risk, and the roadmap Step that
   implements the mitigation.
3. **Every `CRITICAL` and `HIGH` threat has at least one explicit mitigation.** A `CRITICAL` or `HIGH`
   threat with no mitigation blocks the Step's Definition of Done. This is a validator-enforced gate.
4. **Severity is argued, not asserted.** Impact and likelihood are stated so that a reader can disagree
   with the rating on the evidence rather than on authority.
5. **Residual risk is recorded honestly**, including when it is accepted. An accepted risk is a decision
   with an owner, not an omission.
6. **The threat model is updated whenever a trust boundary changes** — a new client surface, a new
   provider, a new integration, a new export path, a new public endpoint, or a change in who may
   impersonate whom. Adding a trust boundary without updating the model is a governance defect.
7. **Threat IDs are permanent.** A closed threat keeps its ID and gains a closure note.

## Trust boundaries

The canonical boundaries are: the public tracking portal; Customer Android; Ops Android; Console Web; the
backend API; **the tenant boundary**; Redis; PostgreSQL; object storage; the WhatsApp provider; the
payment provider; the map provider; the external courier guest link; platform support access; and offline
device storage.

8. **The tenant boundary is a trust boundary**, not an application-level convenience. It is modelled and
   tested as one (Rule 02).
9. **Every third-party provider is untrusted input.** Callbacks, webhooks, and provider responses are
   verified server-side before they influence business or financial state (Rule 04).
10. **The public tracking portal is the most exposed surface** and is modelled accordingly: high-entropy
    hashed tokens, rate limiting, enumeration protection, `noindex`, masked personal data, no full
    address, and OTP for sensitive actions (Master Source §9).

## Abuse cases

11. **Abuse cases are written from the attacker's point of view**, describing what an adversary wants,
    not merely which control exists.
12. The catalogue covers at minimum: tenant enumeration; order-number enumeration; tracking token brute
    force; price manipulation; payment replay; duplicate offline order; duplicate payment; fake delivery
    completion; courier viewing unrelated addresses; notification spam; consent bypass; support
    impersonation abuse; malicious upload; cross-tenant export; leaked public evidence; stale membership;
    role escalation; refund abuse; cash settlement fraud; storage-fee abuse; unlawful disposal of
    unclaimed laundry; customer account takeover; OTP brute force.
13. **Each abuse case maps to at least one mitigation and at least one acceptance criterion** (Rule 22).

## Data classification

14. Classes are: `PUBLIC`, `INTERNAL`, `CONFIDENTIAL`, `RESTRICTED`, `SECRET`.
15. **Every data element handled by the product carries a class**, and its class determines its storage,
    transport, masking, retention, logging, and export rules.
16. Canonical anchors: marketing pricing is `PUBLIC`; internal operational metrics are `INTERNAL`;
    customer phone is `CONFIDENTIAL`; customer address is `RESTRICTED`; laundry photographs are
    `RESTRICTED`; tracking token, OTP, provider credential, and private key are `SECRET`; an audit record
    is `CONFIDENTIAL` or `RESTRICTED` depending on its contents.
17. **Classification drives masking.** The tracking portal never shows a full address; masking level
    depends on who is looking and where (Rule 03, Master Source §17).
18. **A `SECRET` value is never logged, never emitted in an event, never placed in telemetry, and never
    committed** (Rule 03, Rule 20).

## Public repository constraint

19. Because the repository is **PUBLIC** (Rule 23, DEC-0016), **only `PUBLIC` and sanitised `INTERNAL`
    material is committed.** `CONFIDENTIAL`, `RESTRICTED`, and `SECRET` classes may be *described* and
    *modelled*, but never *instantiated* with real values.
20. **Every example datum in every security document is fictional** and recognisably so.

## Step 1 note

No security control is implemented. There is no authentication, authorisation, rate limiting, token
issuance, encryption, or audit trail, because there is no runtime. Step 1 records the model only.
Controls are delivered across Steps 3, 7, 8, and 12 and hardened in **Step 13**.

## Violation handling

- **A `CRITICAL` or `HIGH` threat with no mitigation** — the Step is not done; close the gap or record an
  owner-accepted residual risk with reasoning.
- **A trust boundary changed without a threat-model update** — the change is incomplete; update the model
  in the same pull request.
- **A data element handled with no classification** — assign one before code depends on it; when in
  doubt, classify upward, never downward.
- **A `SECRET` value found in a log, event, telemetry payload, or commit** — treat as compromised. Rotate
  first, then remove (Rule 03). Removal alone is not remediation on a public repository.
- **A real phone number, address, name, or any personal data committed** — automatic **NO-GO** under
  Rule 03; remove, disclose, and treat as exposed.
- **A severity downgraded to make a report read cleanly** — treat as status inflation (Rule 01) and
  restore the original rating.
