# Step 7 — Customer Tracking and WhatsApp: Runtime Design and Contracts

**Step:** 7 — Customer Tracking and WhatsApp
**Status:** `IN PROGRESS` — this document is a DESIGN CONTRACT, not evidence (Rule 01, DEC-0013)
**Authorized by:** the canonical roadmap (Master Source §24); runtime scope opened by
[DEC-0039](../decisions/DEC-0039-step-07-runtime-scope-transition.md)
**Requirements:** FR-086 … FR-099 (verbatim in
[`STEP_07_REQUIREMENT_MATRIX.md`](../quality/STEP_07_REQUIREMENT_MATRIX.md))
**Canonical inputs:** Master Source §9 (public tracking portal) and §14 (notifications/WhatsApp);
[`TRACKING_DOMAIN.md`](../domain/TRACKING_DOMAIN.md),
[`NOTIFICATION_DOMAIN.md`](../domain/NOTIFICATION_DOMAIN.md),
[`TRACKING_ACCESS_LIFECYCLE.md`](../state-machines/TRACKING_ACCESS_LIFECYCLE.md)

**Nothing here is a result.** Every row below states an obligation. Only captured output bound to an
exact 40-character commit SHA proves anything.

---

## 0. What Step 7 is, and the two things it must never become

Step 7 gives a customer a link that shows their own order, and gives a tenant a way to tell that
customer something over WhatsApp. That is all.

It must never become **Step 8** (asking for a pickup, scheduling a delivery, assigning a courier,
capturing proof, settling courier cash) and it must never become **Step 9** (aging, the
H+1/H+3/H+7/H+14 ladder, follow-up tasks, the unclaimed dashboard). DEC-0039 §4 and §5 draw both
lines: *reading* readiness is Step 7; *acting on it with a courier* is Step 8. *Sending* a message is
Step 7; the *ladder that decides when to chase* is Step 9.

Two consequences follow structurally, not by convention:

- No table, route segment, model class, or module directory in this step carries a Step 8+ token.
  `validate-dec-0039-labels.py` re-asserts that on every run.
- The notification subsystem has **no scheduler that invents send times from an aging clock**. It
  defers a message that is already due into the next permitted window (FR-097) and does nothing else
  with time.

## 1. Module boundaries

Two new backend modules, mirroring the two bounded contexts (Rule 06 hard rule 5, Rule 17 hard
rule 9):

| Module | Owns | Never owns |
|---|---|---|
| `App\Modules\Tracking` | tracking-token lifecycle, the public projection, portal resolution, throttling, OTP challenge/verification | the order, any money, any personal data of record |
| `App\Modules\Notification` | notification intents, send-policy evaluation, dedup, quiet-hours deferral, dispatch, attempt history, the manual deep-link fallback | any business state, ever |

Both read Step 5/6 state **through the owning module's interface or its Eloquent model scoped by
tenant**, and neither writes an order, a payment, or a production row. `Notification` does not depend
on `Tracking`; the tracking link that appears inside a message body is passed **in** as already-built
text by the caller that has the plaintext, because the plaintext token exists nowhere else (§3.2).

## 2. Persistence plan

Five new tables. Every one carries `tenant_id` from its introducing migration and is composite-FK
bound so a row can never reference a parent in another tenant (Rule 02, Rule 39, Rule 48). No money
column is introduced anywhere in Step 7 (Rule 04).

| Table | Purpose | Key constraints |
|---|---|---|
| `tracking_tokens` | the `TrackingAccess` aggregate | `UNIQUE(token_hash)`; `state ∈ {ISSUED, REVOKED, EXPIRED, SUPERSEDED}` CHECK; composite FK to `orders(tenant_id, id)`; partial `UNIQUE(tenant_id, order_id) WHERE state='ISSUED'`; optimistic `version` |
| `tracking_access_events` | immutable lifecycle audit | append-only triggers (refuse UPDATE/DELETE/TRUNCATE); composite FK to `tracking_tokens(tenant_id, id)`; **no plaintext token, no OTP** |
| `tracking_otp_challenges` | OTP for FR-091 sensitive actions | `code_hash` only; `UNIQUE(tenant_id, id)`; composite FK to `tracking_tokens`; attempt counter; `consumed_at` |
| `notification_intents` | the transactional outbox | `UNIQUE(tenant_id, dedup_key)`; `state` CHECK; composite FK to `orders`; `scheduled_for` |
| `notification_attempts` | append-only attempt history | append-only triggers; composite FK to `notification_intents(tenant_id, id)`; **no credential, no OTP, no token** |

**There is no plaintext-token column and no plaintext-OTP column anywhere.** `TrackingSchemaTest`
asserts that structurally by inspecting `information_schema.columns`, so the guarantee survives a
future migration that adds a column with a plausible name.

**Naming is deliberate.** `notification_attempts`, never `notification_deliveries`: `deliveries` is a
Step 8 token and the label audit would reject it — correctly, because the guard cannot distinguish a
message-delivery record from a laundry-delivery record by name alone. Nothing here is named
`reminder_*`, `pickup_*`, or `schedule_*`.

**Guard twins move together.** `scripts/ci/assert-schema-scope.php` is the database-level twin of
`validate-runtime-scope.py`. Its Step 7 set moves in the same change, exactly as the Step 4/5/6 sets
moved under DEC-0030/DEC-0035/DEC-0037. The Step 8+ forbidden set is unchanged.

## 3. Tracking-token lifecycle (FR-086, FR-087, FR-088)

### 3.1 Generation

- 32 bytes from `random_bytes()` (CSPRNG), base64url-encoded without padding → 256 bits of entropy,
  43 URL-safe characters.
- Stored as `hash('sha256', $plaintext)` — hex, 64 chars. **The plaintext is returned exactly once**,
  from `issue()` and from `rotate()`, and never again by any endpoint (`TRK-019`).
- SHA-256 rather than a password hash is correct here and the reason matters: the token is
  high-entropy random material, not a low-entropy human secret, so there is nothing for a slow hash
  to defend against, and a deterministic hash is what makes the `UNIQUE(token_hash)` index and the
  O(1) lookup possible. A bcrypt-style hash would force a table scan on every public lookup — an
  availability defect on the most exposed surface in the product.
- The token derives from **nothing**: not the order number, not the order id, not the customer, not
  the tenant, not a timestamp, not a sequence (FR-087). `TrackingTokenTest` asserts the generated
  token shares no substring of length ≥ 6 with the order number and that 1 000 tokens are distinct.

### 3.2 States and transitions

Exactly the lifecycle in `TRACKING_ACCESS_LIFECYCLE.md` §1–§3. `ISSUED` is the only live state;
`REVOKED`, `EXPIRED`, and `SUPERSEDED` are terminal and **there is no reactivation path**.

- Resolution does **not** change state (K-02). It updates `last_viewed_at` and increments
  `view_count`, so a forwarded link keeps working for the family member collecting.
- `THROTTLED` is a **rate-limiting condition, not a stored state**: it lives in the Redis limiter
  keyed on the hashed presented token and the hashed client IP. Persisting it on the aggregate would
  let an attacker lock a victim's link out by hammering it — the throttle would become the attack.
  The lifecycle's `THROTTLED` state is realised as this transient condition, and a throttled request
  returns the same generic body as an unknown token.
- Expiry is **evaluated at read time against server time** and is also swept to `EXPIRED` when
  observed. A client clock never extends an access. `expires_at` is never extended in place.
- Rotation (`K-10`) mints a new row and marks the old `SUPERSEDED` **in one transaction under a row
  lock**, so the old plaintext stops resolving at that instant.

### 3.3 Expiry default

Canonical default per `TRK-005`: **30 days after order completion**. Because "completion" is not
recorded until the order reaches a terminal state, issuance sets a bounded `expires_at` of
`now() + 60 days` and the completion-anchored rule tightens it: when an order reaches `COMPLETED` or
`CANCELLED`, `TrackingTokenService::applyCompletionExpiry()` sets `expires_at` to
`min(current, completed_at + 30 days)`. The model carries **no path to an unbounded token**: the
column is `NOT NULL`, and every write path computes it.

### 3.4 Concurrency

Revocation and rotation take `lockForUpdate()` on the token row inside a transaction and carry the
`expected_version` the caller read. Two staff revoking simultaneously: the second fails with
`CONFLICT`. The access is revoked once, with one recorded actor and one reason. Reason code plus free
text is **mandatory** on both revoke and rotate (`TRACKING_ACCESS_LIFECYCLE.md` §9).

## 4. The public projection (FR-089, FR-090)

**An allow-list read model, assembled server-side, masked at build time** (`TRK-008`, `TRK-018`,
`TRK-028`). `PublicTrackingProjection::build()` returns an array whose keys are a closed set; a field
not enumerated is never assembled, so it cannot leak through a template bug.

Served set (FR-089, exactly):

`order_number` · `brand` `{name}` · `outlet` `{name}` · `service_types[]` · `status`
`{code, label}` · `status_history[] {code, label, occurred_at}` · `estimated_completion`
(nullable, labelled an estimate) · `amount_due_rupiah` (integer) · `payment_state`
`{code, label}` · `customer` `{masked_name, masked_phone}` · `available_actions[]` ·
`is_ready_for_pickup` · `first_ready_at` (nullable) · `generated_at`

Never assembled, with or without OTP (FR-090, `TRK-010`, `TRK-015`, `TRK-016`, `TRK-017`): full or
partial street address in any form · full phone · internal notes / `special_instructions` · any other
order of the same customer · laundry or QC photographs and their object keys · staff identity ·
cost, margin, discount internals · internal UUIDs (`order_id`, `customer_id`, `tenant_id`,
`outlet_id`) · the token or its hash · provider metadata · any debug field.

**Masking is applied at build time.** `masked_name` is given name plus initial (`"Budi S."`);
`masked_phone` is country code plus last four (`"+62 ···· 0001"`). The projection never holds an
unmasked value, so there is nothing for a renderer to leak.

### 4.1 Status derivation — the honest part

The fifteen canonical internal statuses are **not** exposed. They map to a small customer-facing set,
in Bahasa Indonesia, through one table in `CustomerVisibleStatus`:

| Internal | Customer code | Label |
|---|---|---|
| `DRAFT` | *(not resolvable — a draft order has no token)* | — |
| `RECEIVED`, `AWAITING_PROCESS` | `DITERIMA` | Pesanan diterima |
| `SORTING`, `WASHING`, `DRYING`, `FINISHING` | `DIPROSES` | Sedang dikerjakan |
| `QUALITY_CONTROL`, `REWORK` | `PEMERIKSAAN` | Pemeriksaan mutu |
| `READY_FOR_PICKUP` | `SIAP_DIAMBIL` | Siap diambil |
| `SCHEDULED_FOR_DELIVERY`, `OUT_FOR_DELIVERY` | `DIANTAR` | Dalam pengantaran |
| `COMPLETED` | `SELESAI` | Selesai |
| `CANCELLED` | `DIBATALKAN` | Dibatalkan |
| `ISSUE` | `PERLU_TINDAKAN` | Perlu tindakan — hubungi outlet |

`REWORK` maps to `PEMERIKSAAN`, **not** back to `DIPROSES`, and critically: once
`production_ready_events` holds the first-ready fact, `is_ready_for_pickup` stays **true** and
`first_ready_at` keeps its original value even if the order later re-enters `REWORK` and returns.
The projection **reads** that immutable Step 6 anchor and never writes, re-derives, or restarts it
(Rule 10, FR-076/FR-077). A customer told "siap diambil" is never told "not ready yet" afterwards —
that would be the deceptive rework claim the requirement matrix §2 forbids.

`estimated_completion` is nullable and is rendered with explicit estimate wording. There is no ETA
computation in Step 7 and none is claimed (Rule 01, Rule 09 hard rule 1).

`amount_due_rupiah` is an **integer** read from the Step 5 payment state. Step 7 never computes,
rounds, or mutates it.

## 5. Public API and portal transport (FR-092)

| Method | Path | Auth | Notes |
|---|---|---|---|
| `GET` | `/lacak/{token}` | none | server-rendered portal page (Bahasa Indonesia) |
| `GET` | `/api/v1/public/tracking/{token}` | none | the JSON projection |
| `POST` | `/api/v1/public/tracking/{token}/otp` | none | request an OTP challenge for a sensitive action |
| `POST` | `/api/v1/public/tracking/{token}/otp/verify` | none | verify the OTP |

There is **no public write path** other than the two OTP endpoints, because FR-086 … FR-099 define no
other customer-initiated portal write. Changing a delivery address and requesting a schedule change
are the sensitive actions FR-091 names; both *act on* Step 8 workflow that does not exist, so Step 7
implements the **OTP gate and the acceptance record** and stops there — the accepted action is
recorded as an OTP-verified request that a later step consumes. Implementing the pickup/delivery
effect now would be the Step 8 leak §0 forbids.

Every response on both surfaces carries, applied by one middleware so it cannot drift per handler:

```
Cache-Control: no-store, no-cache, must-revalidate, private
Pragma: no-cache
X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex
Referrer-Policy: no-referrer
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self' data:;
                         base-uri 'none'; form-action 'self'; frame-ancestors 'none'
Permissions-Policy: geolocation=(), camera=(), microphone=()
```

`Referrer-Policy: no-referrer` is load-bearing, not hygiene: the token is **in the URL path**, so any
outbound referrer would hand a third party a working credential. The CSP forbids every remote origin,
which is what makes "no remote font, no analytics, no pixel, no third-party embed" (Rule 31 hard rule
10, Rule 32 hard rule 26) structural rather than a promise. The portal page carries `<meta
name="robots" content="noindex, nofollow">` as well, because a header can be stripped by a proxy.

### 5.1 Enumeration and the generic response

Unknown, malformed, expired, revoked, superseded, and throttled tokens all produce **one identical
response**: HTTP `404` with error code `TRACKING_LINK_NOT_AVAILABLE` and the same Bahasa message
naming the recovery step ("minta tautan baru dari outlet"). No timing branch, no distinct code, no
distinct length. `PublicTrackingApiTest` asserts byte-equality of the response bodies across all six
cases — that is the no-existence-oracle guarantee (`TRK-007`, AC-07-02, Rule 48 hard rule 5).

Rate limiting: 20 lookups per token-hash per 5 minutes, and 60 per client IP per 5 minutes, with the
throttled response identical to the not-found response. Keys are `g:throttle_public_tracking:…` —
global, holding only two SHA-256 digests, never tenant data. OTP request is 3 per token per hour;
OTP verify is 5 attempts per challenge then the challenge is dead.

## 6. OTP boundary (FR-091)

Scope discipline first: OTP exists in Step 7 **only** for the two sensitive actions FR-091 names. No
other customer action is invented.

- 6 digits from `random_int()` (CSPRNG). Stored as `hash('sha256', $code)` — **never plaintext**.
- Bound to `(tracking_token_id, order_id, action)`. A challenge issued for `change_delivery_address`
  can never verify `request_schedule_change`, and a challenge for one token never verifies another.
- TTL 5 minutes. Max 5 attempts. Resend cooldown 60 seconds. `consumed_at` set on success and
  checked, so a verified code **cannot be replayed**.
- Every failure — wrong code, expired, consumed, wrong action, attempts exhausted — returns the same
  generic `422` body. The only distinguishable outcome is success.
- Delivery of the OTP goes through the notification subsystem as a **transactional** message that
  **never contains a tracking link** (`TRK-029`, `NOT-014`, requirement matrix §2 account-takeover
  guard). `NotificationContentTest` asserts that structurally: a body containing an OTP and a body
  containing a tracking URL are mutually exclusive by template, and `OtpAndLinkNeverCombinedTest`
  fails the build if any template violates it.
- The plaintext OTP is never logged, never returned by an API, never written to an audit row, and
  never placed in `notification_attempts`. Only the **hash** is persisted and only the fact of a send
  is recorded.

## 7. Notification domain and outbox (FR-098, FR-099)

### 7.1 The structural guarantee

FR-099 is enforced by construction, not by care:

- `NotificationIntentService::enqueue()` is called **after** the business transaction commits, via
  `DB::afterCommit()`. A provider, a queue, or the notification tables being unavailable therefore
  cannot roll back an order, a payment, or a production transition.
- `enqueue()` catches `Throwable`, records the failure, and returns `null`. **It never rethrows into
  a business caller.** `MessagingDoesNotGateOrderStateTest` proves the order is unchanged under
  provider timeout, 4xx, 5xx, malformed response, absent credentials, and a dead queue.
- No business aggregate subscribes to a notification event. There is no code path from
  `NotificationFailed` to an order (`NOT-001`, `NOT-027`, `NOT-029`).

### 7.2 Intent states

`PENDING` → `DEFERRED` (quiet hours) → `SENDING` → `SENT` | `FAILED_RETRYABLE` → `FAILED_PERMANENT` |
`SUPPRESSED` (consent/opt-out/dedup) | `MANUAL_FALLBACK_PREPARED`.

`SENT` means **the provider accepted the message**, and the API surface and UI both say exactly that.
It is never rendered as "delivered to the customer": we do not have a delivery receipt, and claiming
one would be a false claim (Rule 01). `SUPPRESSED` carries a `suppression_reason` so a tenant can see
*why* nothing was sent rather than wondering.

Retry is bounded: 5 attempts, exponential backoff (1m, 5m, 15m, 1h, 4h), then `FAILED_PERMANENT` and
visible. Never retried forever; never silently discarded (`NOT-017`, `NOT-018`).

### 7.3 Deduplication (FR-098)

The dedup key is a SHA-256 over the stable identity `NOT-002` names:

```
recipient_normalized | event_type | order_id | send_window
```

`send_window` is the intended send window — the UTC date-hour the message is due in **outlet local
time** — so a retry, a queue replay, a scheduler restart, and a double-triggered domain event all
compute the same key. `UNIQUE(tenant_id, dedup_key)` makes exactly-once **structural at the
database**, not a check-then-insert race: `insertOrIgnore` returns 0 on a duplicate and the caller
returns the original intent.

### 7.4 Consent, classification, and opt-out (FR-096)

- Every intent carries `category ∈ {transactional, marketing}` set from the **template**, never from
  a caller-supplied string. `NotificationTemplate::categoryFor()` is the only source, so a marketing
  template cannot be routed through a transactional path (`NOT-024`) — the attempt is rejected with
  `NOTIFICATION_CATEGORY_MISMATCH`.
- Marketing requires an explicit **granted** `marketing_whatsapp` consent, read at **send time** from
  the append-only Step 4 `customer_consents` table (FR-027/FR-028, `NOT-005`, `NOT-011`). Absence of
  a refusal is not consent: the default with no consent row is **blocked**.
- Opt-out applies across every outlet of the tenant and only that tenant.
- Transactional messages about the customer's own order do not require marketing consent, per
  `NOTIFICATION_DOMAIN.md` §6.

Step 7 introduces **no second consent store**. It reads the one Step 4 built.

### 7.5 Quiet hours (FR-097)

- Default **20.00–08.00 outlet local time**, read from `outlets.timezone` — never server time, never
  device time (`NOT-003`, `NOT-004`).
- A non-critical message due inside the window is **`DEFERRED` with a computed
  `scheduled_for` = next 08.00 outlet-local**, converted to UTC for storage. It is never dropped and
  never sent anyway (`NOT-021`).
- Midnight crossing is handled by evaluating the window as `hour >= 20 || hour < 8`, so 23.30 and
  01.30 are both inside it and both defer to the **same** next 08.00.
- Boundary semantics are fixed and tested: 19:59 sends, **20:00 defers**, 07:59 defers, **08:00
  sends** — for every message class except the one DEC-0040 exempts, immediately below.
- **There is exactly one quiet-hours exception, and it is named** — granted by
  [DEC-0040](../decisions/DEC-0040-oq-018-user-initiated-security-transaction-quiet-hours-exemption.md),
  which resolved OQ-018. The class `USER_INITIATED_SECURITY_TRANSACTION` — a verification code the
  **customer explicitly requested** for a canonical FR-091 sensitive action — is not deferred, at any
  hour. `NOT-022` permits an exception only where the Master Source or an accepted decision record
  grants one; this is that record, and it is the only one.

  **This supersedes the conservative deferral originally shipped in Step 7**, under which a customer
  requesting a code at 02.00 was told to return at 08.00. A challenge lives five minutes, so that
  deferral made the FR-091 flow unavailable twelve hours a day.

  The exemption is gated on the **origin**, not on urgency: `OtpDispatchOrigin` is a required, typed
  argument with no permissive default, an automated origin is **refused** with
  `otp_not_customer_initiated` rather than deferred, and the ordinary outbox refuses any OTP-carrying
  template outright — so the exempt path is reachable only from the single caller that holds a live
  plaintext code. Two database CHECK constraints make the boundary structural: the classification set
  is closed to one value, and no row can carry the exemption together with `deferred_for_quiet_hours`.

  Everything else defers exactly as before, including every transactional order notification. Rate
  limits, resend cooldown, expiry, attempt limit, single-use consumption, dedup, opt-out, and the
  account-takeover rule are all unchanged (DEC-0040 decision item 5).
- An invalid or missing outlet timezone **fails closed**: the message defers rather than sending, and
  the misconfiguration is recorded. Sending at an unknown local hour is the failure mode quiet hours
  exist to prevent.

## 8. Provider abstraction and the manual fallback (FR-093, FR-094, FR-095)

```php
interface NotificationProvider {
    public function key(): string;
    public function isAvailable(): bool;
    public function send(OutboundMessage $message): ProviderResult;
}
```

`OutboundMessage` and `ProviderResult` are **first-party value objects**. No vendor SDK type, payload
shape, error code, or identifier crosses this boundary (`NOT-009`, FR-093).
`ProviderAbstractionTest` asserts structurally that no file under `app/Modules/Notification` outside
`Providers/` mentions a vendor identifier, and that no file outside the `Notification` module
references a provider class at all.

Three adapters:

| Adapter | Behaviour |
|---|---|
| `OfficialWhatsAppBusinessProvider` | **Fail-closed.** `isAvailable()` is false unless every credential is present in configuration. With credentials absent it never attempts a request and never fabricates a result. |
| `NullNotificationProvider` | The configured default. Records `provider_unavailable`; never claims a send. |
| `FakeNotificationProvider` | Deterministic, test-only, wired only in the testing environment. Never registered in any other environment — `ProviderRegistryTest` asserts that. |

Explicitly forbidden and structurally absent: WhatsApp Web automation, browser automation, any
reverse-engineered or unofficial client, and any fabricated provider success. No live external send
occurs in this step; no production callback is claimed.

**Manual deep-link fallback (FR-095).** `ManualWhatsAppLinkBuilder` returns a
`https://wa.me/<E.164 digits>?text=<urlencoded>` link. It:

- records the intent as `MANUAL_FALLBACK_PREPARED` — **the word "prepared", never "sent" and never
  "delivered"**, and no provider success row is written;
- is still subject to category, consent, opt-out, and quiet hours — a fallback that bypassed opt-out
  would be opt-out theatre;
- excludes, by construction, the raw tracking-token hash, internal ids, internal notes, any full
  address, and any credential;
- is presented in the operator UI with copy that says a staff member must send it. It is never
  labelled automation (`NOT-007`).

## 9. Internal API surface and RBAC

Four new permissions, extending the one canonical registry (never a parallel one):

| Permission | Grants |
|---|---|
| `tracking.view` | read tracking-link metadata (state, issued/expiry, view count) — **never the token** |
| `tracking.manage` | issue, rotate, revoke a tracking link |
| `notification.view` | read notification history and attempts for the tenant |
| `notification.send` | trigger a safe resend, prepare the manual fallback |

Role grants (least privilege):

| Role | tracking.view | tracking.manage | notification.view | notification.send |
|---|---|---|---|---|
| tenant_owner | ✅ | ✅ | ✅ | ✅ |
| tenant_admin | ✅ | ✅ | ✅ | ✅ |
| outlet_manager | ✅ | ✅ | ✅ | ✅ |
| cashier | ✅ | ✅ | ✅ | ✅ |
| production_operator | ❌ | ❌ | ❌ | ❌ |
| quality_control | ❌ | ❌ | ❌ | ❌ |
| **courier** | ❌ | ❌ | ❌ | ❌ |
| finance | ✅ | ❌ | ✅ | ❌ |
| customer | ❌ | ❌ | ❌ | ❌ |
| platform roles | ❌ | ❌ | ❌ | ❌ |

The cashier holds `tracking.manage` because handing a customer their link at the counter is the
kasir's job in `TRACKING_ACCESS_LIFECYCLE.md` K-01/K-08/K-10. **The courier holds nothing**: Rule 32
hard rule 11 gives the courier surface one assignment and no traversal path, and a courier able to
read a tenant's notification history would be exactly that traversal.

Internal routes (all under `auth.api` + `tenant.context`):

```
GET    /api/v1/orders/{order}/tracking-link          tracking.view
POST   /api/v1/orders/{order}/tracking-link          tracking.manage   (issue; plaintext ONCE)
POST   /api/v1/tracking-links/{token}/rotate         tracking.manage   (plaintext ONCE)
POST   /api/v1/tracking-links/{token}/revoke         tracking.manage   (reason mandatory)
GET    /api/v1/orders/{order}/notifications          notification.view
GET    /api/v1/notifications/{intent}                notification.view
POST   /api/v1/notifications/{intent}/retry          notification.send
POST   /api/v1/notifications/{intent}/manual-link    notification.send
GET    /api/v1/notifications/provider-state          notification.view
GET    /api/v1/customers/{customer}/notification-consent   customer.view
```

Every write is idempotent on `client_reference` and optimistic on `expected_version` where an
aggregate has one, matching the Step 5/6 contract. A foreign-tenant `{order}`, `{token}`, or
`{intent}` returns `404` indistinguishably from an absent one (Rule 48 hard rule 5). There is **no
list-all-tokens route** and **no export route** — both would be enumeration surfaces, and their
absence is asserted by test rather than assumed.

## 10. Surfaces

**Public tracking portal** — server-rendered Blade under `backend/resources/views/tracking/`, served
at `/lacak/{token}`. **Ratified as the canonical Step 7 portal stack by
[DEC-0041](../decisions/DEC-0041-oq-014-laravel-blade-as-the-public-tracking-portal-stack.md)**, which
resolved OQ-014. Rule 05 and `TRACKING_DOMAIN.md` §9 explicitly permit a lighter web stack here and
Flutter is not mandatory; the portal is the most performance-critical surface in the product, opened
once on an unknown low-end device over an unknown network. Blade ships with the Laravel runtime
DEC-0024 already authorised, so the choice adds no dependency and no toolchain. It is self-contained:
first-party inline CSS, no remote font, no script, no image request, no analytics. Accessible
semantics, keyboard reachable, survives large text scaling, readable at 320 px. States: valid ·
not-available (the one generic state covering unknown/expired/revoked/superseded/throttled) · server
error. No app-install prompt, ever (DEC-0006, DEC-0014, `TRK-025`).

DEC-0041's boundaries are binding and are audited structurally by
`scripts/validate-dec-0041-portal-stack.py`: **Blade is for the public tracking portal only** and
never a parallel admin or operations application; token validation, tenant isolation, the
customer-visible projection, masking, consent, and notification rules stay in canonical backend
services and are **never duplicated in a view**; there is **no persistent browser storage** of the
token and **no public authentication session**; and the transport controls (`no-store`, `noindex`,
`Referrer-Policy: no-referrer`, `default-src 'none'` CSP, anti-framing, contextual escaping, rate
limiting, one generic invalid-link response) remain mandatory. **No Step 8 pickup/delivery control and
no Step 9 reminder control may be added to this surface.**

**Operator surface** — `apps/ops_android/lib/src/tracking/`, following the existing production/order
UI conventions: issue link, one-time plaintext display with copy/share and an explicit "this is shown
once" warning, rotate, revoke with mandatory reason, expiry state, notification timeline with attempt
history, provider-disabled state, manual fallback with honest copy, quiet-hours/opt-out/dedup
suppression reasons shown as text, and a safe retry. No dead control and no fabricated delivery
state.

## 11. Threat model (STRIDE deltas for Step 7)

| ID | Threat | Sev | Mitigation | Test |
|---|---|---|---|---|
| T7-01 | Token brute force / enumeration | CRITICAL | 256-bit token; per-token and per-IP rate limit; identical generic response | `PublicTrackingSecurityTest` |
| T7-02 | Order-number guessing used as a credential | CRITICAL | token independent of order number (FR-087); order number never resolves | `TrackingTokenTest` |
| T7-03 | Existence oracle via distinguishable responses | HIGH | byte-identical body across all six invalid cases | `PublicTrackingApiTest` |
| T7-04 | Token leak via referrer / cache / index / log | HIGH | `no-referrer`, `no-store`, `noindex` header + meta, log redaction, no plaintext persisted | `PortalHeaderTest`, `LogRedactionTest` |
| T7-05 | Cross-tenant read through a public token | CRITICAL | tenant derived server-side from the stored row; never from the request | `TrackingIsolationTest` |
| T7-06 | Over-disclosure through the projection | CRITICAL | allow-list build, masking at build time, no address ever | `PublicProjectionTest` |
| T7-07 | OTP brute force / replay / cross-action reuse | HIGH | hash-only, 5-min TTL, 5 attempts, `consumed_at`, action+token binding | `TrackingOtpTest` |
| T7-08 | One-message account takeover (OTP + link together) | HIGH | template-level mutual exclusion | `OtpAndLinkNeverCombinedTest` |
| T7-09 | Consent / opt-out bypass | HIGH | category from template only; consent read at send time; default blocked | `NotificationConsentTest` |
| T7-10 | Quiet-hours bypass | HIGH | outlet-local evaluation, defer-not-drop, fail closed on bad tz; exactly ONE exempt class (DEC-0040), closed by a database CHECK | `NotificationPolicyTest`, `OtpQuietHoursExemptionTest` |
| T7-18 | The DEC-0040 exemption used to message a number the requester does not own | HIGH | exemption gated on an explicit customer request (`OtpDispatchOrigin`, no default); automated origin refused not deferred; outbox refuses OTP templates; per-token 3/hour, per-IP 12/hour, resend cooldown | `OtpQuietHoursExemptionTest` |
| T7-19 | Marketing relabelled to acquire the quiet-hours exemption | HIGH | category comes from the template only; the classification is assigned solely by the synchronous OTP path; DB CHECK closes the value set to one | `OtpQuietHoursExemptionTest`, `NotificationPolicyTest` |
| T7-20 | Blade portal growing into a parallel admin surface (DEC-0041 boundary) | MEDIUM | one web route; views confined to `tracking/`; no business rule in a view; no browser storage, no session; structural audit | `validate-dec-0041-portal-stack.py` |
| T7-11 | Duplicate messages | HIGH | DB-unique dedup key over recipient+event+order+window | `NotificationDedupTest` |
| T7-12 | Messaging failure corrupting order/payment state | CRITICAL | after-commit enqueue, never rethrows, no subscriber path | `MessagingDoesNotGateOrderStateTest` |
| T7-13 | Vendor coupling | MEDIUM | first-party interface + value objects; structural assertion | `ProviderAbstractionTest` |
| T7-14 | Fabricated delivery claim | HIGH | `SENT` = provider accepted; manual fallback = `PREPARED` only | `ManualFallbackTest` |
| T7-15 | Template injection / XSS on the portal | HIGH | Blade auto-escaping; CSP `default-src 'none'`; no raw output | `PortalXssTest` |
| T7-16 | Deceptive readiness after rework | MEDIUM | first-ready anchor read-only and sticky | `PublicProjectionTest` |
| T7-17 | Step 8/9 scope leak into tracking or notification | MEDIUM | label audit + schema-scope twin + route-absence tests | `validate-dec-0039-labels.py` |

## 12. Evidence and test plan

Backend suites: `Tracking` (schema, token lifecycle, projection, public API, OTP, isolation, RBAC,
security) and `Notification` (outbox, dedup, consent, quiet hours, provider abstraction, manual
fallback, order-state decoupling, content rules). Every one runs against **live PostgreSQL** (Rule
43), never SQLite. Flutter widget tests cover the operator surface; portal behaviour is covered by
backend HTTP tests because the portal is server-rendered.

`scripts/verify-step-07.sh`'s two SKIPs become **mandatory gates** once these surfaces exist, and
`scripts/test-verify-step-07.sh` proves adversarially that the upgraded verifier cannot report PASS
when a gate is missing or failing.

Evidence lands under `evidence/step-07/`, sanitised, bound to the candidate SHA, and stating that
sanitisation occurred. Every fixture phone number, name, and message body in this step is fictional
and recognisably so (Rule 23, Rule 45).
