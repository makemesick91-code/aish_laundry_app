# Step 7 — Customer Tracking and WhatsApp: Requirement Matrix and Acceptance Criteria

**Step:** 7 — Customer Tracking and WhatsApp
**Status:** `IN PROGRESS` — runtime scope opened by DEC-0039; no runtime feature verified until exact-SHA evidence exists
**Authorized by:** the canonical roadmap (Master Source §24; [`ROADMAP.md`](../ROADMAP.md))
**Runtime scope opened by:** [DEC-0039](../decisions/DEC-0039-step-07-runtime-scope-transition.md)
**Master Source version:** 1.4.13
**Baseline SHA:** `cfa7cf7399cd9769b522dc31d03edae09349a823` (post-Step-6 canonical `main`)
**Depends on (Step 6, delivered):** production lifecycle, the immutable first `READY_FOR_PICKUP`
timestamp (FR-076/FR-077), quality control and rework. Depends on (Step 5): orders, the order number,
server-authoritative pricing and payment state, the append-only ledger. Depends on (Step 4): outlets
(operating hours, timezone), customer master data, customer consent/opt-out state (FR-027/FR-028),
quiet-hours configuration (FR-047). Depends on (Step 3): phone + OTP authentication primitives
(FR-001/FR-003), tenancy, RBAC.

---

## 1. How to read this document

This is the Phase 0/1 requirement matrix for Step 7. It maps every canonical Step 7 requirement to the
mechanism that will satisfy it, the verification that will prove it, and the evidence that must exist
before the claim may be made.

**Nothing in this document is evidence.** A row saying a requirement is `TESTED` is a claim that points
AT evidence; it is not itself the evidence. Only captured output bound to an exact 40-character commit
SHA proves anything (Rule 01, DEC-0013). The authoritative requirement → evidence traceability lives in
[`evidence/step-07/README.md`](../../evidence/step-07/), bound to the candidate SHA, and it — not this
table — is what a reader should check.

**`TESTED` is not `GO`.** Every row below now reads `TESTED` because the runtime exists and its
verification was executed and captured. Step 7 itself remains `IN PROGRESS`: `GO` is conferred by the
repository owner after merge and is never self-declared by an agent (Rule 01).

**Both Step 7 open questions are now resolved by the repository owner**, and neither was closed by
invention (Rule 00 hard rule 6):

- **OQ-018** → [DEC-0040](../decisions/DEC-0040-oq-018-user-initiated-security-transaction-quiet-hours-exemption.md).
  A **customer-initiated** FR-091 OTP is a `USER_INITIATED_SECURITY_TRANSACTION` and is **exempt from
  quiet hours**. This supersedes the conservative deferral originally shipped, under which the FR-091
  sensitive-action flow was unavailable 20.00–08.00 outlet local time. The exemption is gated on an
  explicit customer request; an automated origin is refused, not deferred, and the ordinary outbox
  refuses OTP-carrying templates outright. FR-091 therefore no longer depends on an open question.
- **OQ-014** → [DEC-0041](../decisions/DEC-0041-oq-014-laravel-blade-as-the-public-tracking-portal-stack.md).
  Server-rendered **Laravel Blade** is the canonical portal stack, with written boundaries and a
  structural audit (`scripts/validate-dec-0041-portal-stack.py`). FR-089 … FR-092 are unchanged in
  behaviour; the record ratifies what was built and fences what it may become.

**No requirement is invented here.** Step 7's requirement set is **FR-086 … FR-099**, fixed in
[`PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md). Step 7 also carries the runtime
realisation of the canonical public tracking portal (Master Source §9) and the notification/WhatsApp
subsystem (Master Source §14), the tracking-access lifecycle
([`TRACKING_ACCESS_LIFECYCLE.md`](../state-machines/TRACKING_ACCESS_LIFECYCLE.md)), and the tracking and
notification domain models ([`TRACKING_DOMAIN.md`](../domain/TRACKING_DOMAIN.md),
[`NOTIFICATION_DOMAIN.md`](../domain/NOTIFICATION_DOMAIN.md)).

## 2. Hard gates that do not relax for Step 7

- **Tenant isolation (Rule 02/39/48).** Every tracking and notification table carries `tenant_id` from
  its introducing migration; every query is tenant-scoped server-side; negative tests prove a member of
  tenant A cannot reach a tenant B tracking token, notification, or consent record by direct ID, list,
  filter, search, export, or file URL. A public token resolves only its own order, in its own tenant.
  Evidence is produced against PostgreSQL (Rule 43).
- **Financial integrity (Rule 04/18).** Step 7 records no money of its own and **must not mutate** the
  order ledger, payment state, or historical prices. The portal *reads* amount-due and payment state; a
  messaging or portal path that could change a payment is a defect. No money column is introduced.
- **Immutable readiness anchor (Rule 10, FR-076/FR-077).** The customer-visible status projection reads
  the first `READY_FOR_PICKUP` fact from Step 6; it never writes, restarts, or re-derives it. Rework
  does not produce a deceptive "not yet ready" after a genuine readiness event.
- **Messaging never gates order state (Rule 08/19, FR-099).** A WhatsApp/provider failure never cancels,
  blocks, reverses, or alters an order. Notification is a side effect.
- **Server-side authorization (Rule 03/40).** A client-supplied tenant, role, token, or OTP claim is
  never authorization proof. The plaintext tracking token is a `SECRET` (Rule 21); only its hash is
  stored; it never appears in a log, event, analytics field, page title, or committed file.
- **Public-repository safety (Rule 23/45).** Every seed, fixture, template example, phone number, name,
  and address is fictional and recognisably so. Deletion is not remediation.
- **Account-takeover guard (Master Source §14.3).** An OTP value and a tracking link are never combined
  in a single message.

## 3. Non-goals (forbidden in Step 7 — remain NOT IMPLEMENTED)

Pickup request/scheduling, delivery scheduling, courier assignment, route ordering/optimisation, proof
of pickup/delivery, courier OTP/photo/signature, external ojek guest links, courier cash reconciliation
(**Step 8**, FR-100 … FR-111); unclaimed-laundry aging, the H+1/H+3/H+7/H+14 reminder ladder, follow-up
tasks, the unclaimed dashboard (**Step 9**, FR-112 … FR-117); finance/owner reports, shift closing
(**Step 10**); subscription billing (**Step 12**); the Customer Android tracking experience and loyalty
(**Step 11**, FR-118 … FR-120). The runtime-scope guard keeps every one of these structurally forbidden
after DEC-0039; DEC-0039 moves **only** the tracking + notification labels into the permitted band.

Also forbidden: unofficial WhatsApp automation, WhatsApp Web/browser automation, live external sends
without real credentials and owner authorization, and any deployment.

## 4. Requirement matrix — FR-086 … FR-099

Requirement statements are reproduced verbatim from `PRODUCT_REQUIREMENTS.md` (Rule 16). "Verified by"
names the intended mechanism; "Status" is the honest current state.

### 4.1 Public tracking portal (Master Source §9) — FR-086 … FR-092

| FR | Requirement (verbatim) | Mechanism | Verified by | Status |
|---|---|---|---|---|
| FR-086 | Tracking token issuance — issue a high-entropy tracking token for an order from a cryptographically secure random source, stored hashed server-side. | `Tracking` module: CSPRNG token (≥256-bit), stored as a hash only; plaintext returned once at issuance. | Entropy + hash-only-persistence tests; "no plaintext column" schema assertion. | TESTED — evidence/step-07 at the recorded SHA |
| FR-087 | Token independence from order number — the token shall not be the order number and shall not be derivable from it. | Token independent of order number, customer id, phone, tenant id, timestamp, sequence. | Test: token uncorrelated with order number; order-number guessing yields the generic invalid response. | TESTED — evidence/step-07 at the recorded SHA |
| FR-088 | Token revocation and expiry — revocable by the customer or the outlet, and shall expire. | Lifecycle fields (issued/expires/revoked); rotation invalidates the prior token. | Tests: expired, revoked, rotated → indistinguishable invalid response; revocation immediate. | TESTED — evidence/step-07 at the recorded SHA |
| FR-089 | Portal content set — order number, brand/outlet identity, service type, current status and history, estimated completion, amount due, payment state, available actions. | Allow-list projection assembled server-side from Step 5/6 state. | Tests: projection field set equals the canonical safe set; status/history correct. | TESTED — evidence/step-07 at the recorded SHA |
| FR-090 | Portal exclusions — never a full address, full phone, other orders of the same customer, internal notes, or laundry photographs without OTP. | Allow-list projection: excluded fields are never assembled, not merely hidden. | Tests: no full address/phone/notes/other-orders/photos in the public response. | TESTED — evidence/step-07 at the recorded SHA |
| FR-091 | Portal sensitive actions — changing a delivery address and requesting a schedule change shall require OTP verification. | OTP-gated sensitive actions bound to token + order + action. Delivery of the code is a `USER_INITIATED_SECURITY_TRANSACTION` (DEC-0040), exempt from quiet hours and gated on an explicit customer request. | Tests: sensitive action without valid OTP is refused; OTP replay/expiry/attempt limits; DEC-0040 exemption at 19:59/20:00/00:00/07:59; automated origin refused. | TESTED — evidence/step-07 at the recorded SHA |
| FR-092 | Portal indexing prevention — served with `noindex` so tracking pages never enter search engines. | `X-Robots-Tag: noindex`, `<meta noindex>`, `Cache-Control: no-store`, `Referrer-Policy: no-referrer`. | Header/markup tests on the portal response. | TESTED — evidence/step-07 at the recorded SHA |

### 4.2 Notification and WhatsApp (Master Source §14) — FR-093 … FR-099

| FR | Requirement (verbatim) | Mechanism | Verified by | Status |
|---|---|---|---|---|
| FR-093 | Provider abstraction — WhatsApp sending sits behind an internal notification interface; no vendor SDK, payload, or identifier leaks into business logic. | `Notification` module: `NotificationProvider` interface; adapters isolated. | Tests: business logic references the interface only; adapter swap is config-only. | TESTED — evidence/step-07 at the recorded SHA |
| FR-094 | Official provider as automated path — automated sending goes through an official WhatsApp Business API provider. | Official adapter, **fail-closed** without credentials; no unofficial fallback. | Tests: adapter disabled without credentials; never falls back to browser automation. | TESTED — evidence/step-07 at the recorded SHA |
| FR-095 | Manual deep-link fallback — a prepared deep link a staff member sends manually, explicit and visible, never presented or sold as automation. | `wa.me`-style deep link builder; records only "prepared", never "delivered". | Tests: deep link encodes safe content; no delivery claim; consent/classification respected. | TESTED — evidence/step-07 at the recorded SHA |
| FR-096 | Transactional and marketing separation — separate categories, templates, consent, reporting; marketing never routed through a transactional path. | Message category on every intent; opt-out evaluated per category. | Tests: marketing relabelled transactional is rejected; opted-out marketing blocked. | TESTED — evidence/step-07 at the recorded SHA |
| FR-097 | Quiet hours enforcement — non-critical messages not sent inside quiet hours (default 20.00–08.00 outlet local time); due-inside messages deferred to the next window, not dropped, not sent anyway. | Outlet-timezone quiet-hours evaluation with midnight crossing; next-eligible-window computation. Exactly ONE exempt class, `USER_INITIATED_SECURITY_TRANSACTION`, granted by DEC-0040 under NOT-022 and enforced by two database CHECK constraints. | Tests: timezone/midnight/boundary; deferral not drop; every outbox-carried template still defers; the exemption is unreachable without an explicit customer request. | TESTED — evidence/step-07 at the recorded SHA |
| FR-098 | Message deduplication — the same notification for the same recipient, event, order, and intended send window is sent exactly once across retries, replays, and scheduler restarts. | Structural dedup key (recipient + event + order + window); outbox idempotency. | Tests: duplicate event/replay produces one message. | TESTED — evidence/step-07 at the recorded SHA |
| FR-099 | Messaging decoupled from order state — a messaging failure never cancels, blocks, or alters an order; failures are visible and retried under a bounded policy. | Notification intent created outside the order transaction; bounded retry; visible failure. | Tests: order succeeds under provider timeout/4xx/5xx/malformed/credentials-absent/queue-down. | TESTED — evidence/step-07 at the recorded SHA |

## 5. Acceptance criteria (Given / When / Then, negative-path first)

- **AC-07-01 (FR-086/FR-087)** Given an issued tracking token, When its stored representation is
  inspected, Then no column holds the plaintext token and the token is not equal to nor derivable from
  the order number.
- **AC-07-02 (FR-088)** Given a revoked or expired token, When the portal is opened with it, Then the
  response is byte-identical to an unknown-token response (no existence oracle) and revocation takes
  effect on the next request.
- **AC-07-03 (FR-089/FR-090)** Given a valid token, When the portal renders, Then it shows exactly the
  safe-by-default set and never a full address, full phone, other orders, internal notes, or photographs.
- **AC-07-04 (FR-091)** Given a valid token but no verified OTP, When a sensitive action is attempted,
  Then it is refused; and OTP replay, expiry, and attempt-limit are enforced.
- **AC-07-05 (FR-092)** Given any portal response, When headers/markup are inspected, Then `noindex` and
  `no-store` are present.
- **AC-07-06 (FR-093/FR-094/FR-095)** Given no provider credentials, When an automated send is
  attempted, Then the official adapter fails closed, no unofficial channel is used, and the manual
  deep-link fallback remains available without claiming delivery.
- **AC-07-07 (FR-096)** Given a customer opted out of marketing, When a marketing message is queued,
  Then it is blocked; and a marketing message cannot be routed through the transactional path.
- **AC-07-08 (FR-097)** Given the current outlet-local time is inside 20.00–08.00, When a non-critical
  message is due, Then it is deferred to the next permitted window and not dropped.
- **AC-07-08a (FR-097, FR-091 · DEC-0040)** Given the current outlet-local time is inside 20.00–08.00,
  When a customer **explicitly requests** an OTP for a canonical FR-091 sensitive action, Then it is
  classified `USER_INITIATED_SECURITY_TRANSACTION`, sent immediately, not marked deferred, and the
  classification is recorded on both the notification intent and the tracking access event.
- **AC-07-08b (FR-097 · DEC-0040 fence)** Given an OTP send whose origin is **not** an explicit customer
  request, When it is attempted at any hour, Then it is **refused** with `otp_not_customer_initiated` —
  not deferred and not sent — nothing reaches the provider, and the ordinary outbox separately refuses
  any OTP-carrying template. Marketing and every other transactional template still defer inside quiet
  hours, and marketing opt-out is still honoured.
- **AC-07-09 (FR-098)** Given the same (recipient, event, order, window), When the notification is
  triggered twice (retry/replay/restart), Then exactly one message results.
- **AC-07-10 (FR-099)** Given the provider times out / returns 4xx / 5xx / malformed / has no
  credentials, When an order-triggered notification is dispatched, Then the order's state is unchanged
  and the failure is recorded and retried under a bounded policy.
- **AC-07-11 (tenant isolation)** Given a member of tenant A, When any tracking/notification access path
  is exercised against a tenant B record (direct ID, list, filter, search, export, file URL), Then
  access is denied indistinguishably from non-existence.

## 6. Evidence obligations

Evidence for every row above is captured under [`evidence/step-07/`](../../evidence/step-07/), bound to
the candidate SHA, sanitised, and stating that sanitisation occurred. Until that evidence exists, every
Status column reads `NOT IMPLEMENTED` and no stronger claim may be made (Rule 01).
