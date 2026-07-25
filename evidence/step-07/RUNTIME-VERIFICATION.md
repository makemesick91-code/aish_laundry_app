# Step 7 — Customer Tracking and WhatsApp: Runtime Verification Evidence

**Step:** 7 — Customer Tracking and WhatsApp
**Status:** `IN PROGRESS` — runtime built and `TESTED`; **`GO` is NOT conferred and is the owner's to give** (Rule 01)
**Master Source version:** 1.4.13
**Runtime scope opened by:** [DEC-0039](../../docs/decisions/DEC-0039-step-07-runtime-scope-transition.md)
**Branch:** `feature/step-07-runtime-customer-tracking-whatsapp`
**Branch base:** `47356042cce926abd8d75b9f29388b8403af140b` (Step 7 START merge, PR #28)

---

## 0. What this document is, and what it is not

It records verification that was **executed**, with the command, the environment, and the exact
40-character commit SHA it ran against (Rule 01, DEC-0013). It is not a claim that Step 7 is finished.

**Sanitisation.** This repository is `PUBLIC` (AMENDMENT-0001, DEC-0016). Every datum below and in
every fixture it refers to is fictional and recognisably so — phone numbers are all-zero placeholders
that cannot reach a subscriber, names are marked "Fiktif", addresses are invented. No token, OTP,
credential, or provider secret appears anywhere, because none is stored in the first place: only
SHA-256 hashes are persisted (Rule 23, Rule 45).

**No live provider send occurred.** See §6.

---

## 1. Baseline, established before any change

Run on the branch base with a clean working tree, before a single Step 7 runtime file existed.

```
$ bash scripts/verify-step-07.sh
commit : 47356042cce926abd8d75b9f29388b8403af140b
PASS 17   FAIL 0   SKIP 2
skipped gates:
  - Step 7 tracking backend suite (Tracking/Notification modules not yet implemented)
  - Step 7 public portal + operator UI gates (tracking/notification UI not yet implemented)
```

Those two SKIPs were the honest state at the governance transition, and replacing them with real
gates is what this sprint set out to do.

**An earlier attempt at this baseline reported `FAIL 1` on the Step 0–6 regression. That result was
invalid and is recorded here rather than quietly discarded:** files had been created in the working
tree while the run was in flight, and `verify-step-06.sh` gates on a clean tree. The run above is the
re-run on a genuinely clean tree, and it is the one that counts.

## 2. Implementation commits

| SHA | Unit |
|---|---|
| `a4dd02b` | Schema (five tables), Tracking + Notification modules, provider abstraction, RBAC, wiring |
| `cd010f7` | Tracking suites — schema, token lifecycle, projection, public API, OTP (63 tests) |
| `7116811` | Notification suites — outbox, policy, provider, FR-099 decoupling (63 tests) |
| `bc1ed92` | Internal API RBAC and tenant-isolation matrix (17 tests) |
| `c049486` | Operator Flutter surface + 20 widget tests |
| `d33baf1` | Verifier: both SKIPs replaced by mandatory gates; adversarial harness; OQ-014/OQ-018 |
| `27f9d2d` | DEC-0030 residual audit made step-aware; stale STATUS/CLAUDE/Rule 15 claims corrected |
| `bdddd15` | Step 4 forward-boundary route/endpoint gates moved to the Step 8+ band |
| `0eb395c` | The three public portal routes allow-listed, with the reasoning recorded in source |
| `ca3476a` | **Defect fix:** `show()` returned the superseded link after a rotation (see §9) |

## 3. Executed results

All backend evidence is produced against **live PostgreSQL 18.4**, never SQLite (Rule 43).
Environment: Linux, PHP 8.4 + Composer, PostgreSQL 18.4 and Redis 8.2.7 on loopback, Flutter/Dart
from the pinned SDK.

### 3.1 Backend suite

```
$ cd backend && php artisan test
Tests:  790 passed (7557 assertions)
```

Step 7 contributes **165** of those tests across ten files — 97 matched by `--filter=Tracking`
and 68 by `--filter=Notification`, which are the two gates the verifier runs:

| Suite | Tests | What it proves |
|---|---|---|
| `TrackingSchemaTest` | 12 | no plaintext-token or plaintext-OTP column exists anywhere; `tenant_id` on every table; no floating-point column; event tables refuse mutation at the database; a `SENT` intent cannot exist without an acceptance timestamp; a suppressed intent must state its reason; a token cannot reference another tenant's order; a token must carry an expiry; a revoked token must carry a reason code |
| `TrackingTokenTest` | 20 | 256-bit CSPRNG token, 1000 distinct; no six-character run shared with the order number; two orders carrying the SAME order number get unrelated tokens; revocation terminal with no reactivation; rotation supersedes atomically and never inherits the old expiry; completion expiry only ever tightens; stale `expected_version` refused; foreign-tenant token unaddressable; the issued-link object refuses to serialise |
| `PublicProjectionTest` | 16 | an address is saved and no fragment reaches the output; the customer's other order exists and is invisible; internal notes, internal UUIDs, the token and its hash all absent; name masked to "Budi F."; no ETA fabricated; readiness stays true and `first_ready_at` unchanged after the order re-enters `REWORK` |
| `PublicTrackingApiTest` | 15 | unknown, malformed, expired, revoked, superseded, and order-number-as-token produce ONE byte-identical body, and a throttled valid token joins them; `noindex`/`no-store`/`no-referrer`/CSP headers; robots meta tag; tenant markup escaped; no script, no remote asset, no app-install prompt |
| `TrackingOtpTest` | 15 | brute force exhausts the challenge; replay after consumption refused; cross-action and cross-token reuse refused; expiry; resend cooldown; per-token issuance limit; a wrong guess never written to an audit payload; the HTTP surface answers identically for live and dead links |
| `NotificationOutboxTest` | 14 | dedup returns the original intent, survives a replay after a successful send, and is refused at the UNIQUE constraint even from direct SQL; a rejection is permanent immediately while a timeout backs off; retry bounded and visible; terminal intents never re-dispatched; attempt rows carry no personal data; the due-query is tenant-scoped |
| `NotificationPolicyTest` | 18 | marketing with no consent row is BLOCKED; opt-out re-evaluated at dispatch; the category is proved un-passable by reflection; quiet hours exact at 19:59/20:00/07:59/08:00, wrapping midnight to the SAME morning, evaluated in the outlet's zone (Jayapura sends at 09:00 WIT while Jakarta defers at 07:00 WIB); unusable timezone fails closed; **every template the outbox carries defers**, and the outbox refuses an OTP-carrying template outright — so the DEC-0040 exemption is provably not reachable from this path |
| `OtpQuietHoursExemptionTest` | 18 | the DEC-0040 exemption and its fences: a customer request at 19:59 is eligible, and at 20:00, 00:00, and 07:59 is eligible IMMEDIATELY with `deferred_for_quiet_hours` false and `scheduled_for` unmoved; evaluated in the outlet's own zone; an automated origin is REFUSED with `otp_not_customer_initiated` at every hour, never deferred; the outbox refuses OTP templates; marketing and ordinary transactional messages still defer; marketing opt-out is still honoured and does NOT block a customer-requested OTP; per-token rate limit and resend cooldown still refuse inside quiet hours; dedup still applies; the classification is recorded on both the intent and the tracking access event and neither carries the code; the database refuses an exempt row marked deferred and refuses an unrecognised classification; an unavailable or rejecting provider is reported as a failure with no acceptance timestamp |
| `ProviderAbstractionTest` | 22 | no file outside `Providers/` names a vendor or an HTTP client; no other module imports an adapter; `OutboundMessage` carries no internal identifier; the official adapter is unavailable with absent, partial, or disabled credentials and fabricates nothing; exactly three adapters, none a browser-automation client; no template combines an OTP with a tracking link; nothing promises unlimited WhatsApp |
| `MessagingDoesNotGateOrderStateTest` | 10 | the order, its total, and its ledger balance are byte-identical after timeout, 4xx, 5xx, malformed, and unavailable; enqueue never throws into a business caller; a contract-violating provider that throws is absorbed; the ledger and the immutable first-ready anchor untouched; structurally, no business module imports Notification and the module writes to no business table |
| `TrackingApiRbacTest` | 18 | cashier may issue/rotate/revoke; production operator and **courier** hold nothing; finance reads but does not send; suspended membership loses access on the next request; foreign order/link/notification indistinguishable from absent; a client-supplied tenant id is never authorization proof; no list-all or export route; the plaintext is unretrievable after issuance |

### 3.2 Migrations against the authoritative engine (Rule 43)

```
$ php artisan migrate --force
  2026_07_25_200000_create_tracking_tables ....... DONE
  2026_07_25_210000_create_notification_tables ... DONE
$ php artisan migrate:rollback --step=2 --force   # both rolled back
$ php artisan migrate --force                     # both re-applied
```

Fresh apply, rollback, and re-apply all exercised.

### 3.3 Live schema scope

```
$ php scripts/ci/assert-schema-scope.php
  tables present: 54
  forbidden Step 8+ tables: 0
  authorised Step 7 tables present: 5
schema is within Step 7 scope
```

### 3.4 Flutter workspace

```
$ dart format --output=none --set-exit-if-changed .   # clean
$ flutter analyze                                     # No issues found!
$ cd apps/ops_android && flutter test
  154 tests passed   (20 of them the Step 7 tracking surface)
```

### 3.5 Verifier adversarial harness (Rule 47, Rule 33)

```
$ bash scripts/test-verify-step-07.sh
SUMMARY [verify-step-07 adversarial]: 14/14 expectations met, 0 failed

$ bash scripts/test-step-07-validators.sh
SUMMARY [test-step-07-validators]: 57 passed, 0 failed
```

It proves, among other things, that a **missing** Tracking module, a **missing** backend test suite,
or a **missing** operator UI test now **FAILS** rather than skipping — which is the entire value of
replacing the two transitional SKIPs. Its removal tests run in a disposable copy, and it asserts the
canonical repository is unchanged afterwards.

Expectations 10 and 11 were added for this ratification round: that the DEC-0040 and DEC-0041 presence
checks and the portal-stack audit are **mandatory gates** rather than comments or skips, and that the
portal-stack audit actually **discriminates** broken input from clean input.

`test-step-07-validators.sh` §4 drives the DEC-0041 audit's pure functions with synthetic broken markup
— a script tag, an inline handler, a remote asset, a Vite bundle, an inline PHP block, a database call,
an Eloquent query, `localStorage`, a session read, an auth facade call, and five Step 8/9 control
shapes — and asserts each is rejected while legitimate portal markup, including the FR-091 OTP control,
is accepted. It also asserts the comment-stripping narrowing is bounded: a `<script>` **mentioned in a
Blade comment** is not flagged, but a real script tag beside that comment still is. **Nothing is written
to disk**, which is the specific defect that invalidated the superseded Step 3 "31/31 mutations caught"
figure (Rule 49).

## 4. The canonical Step 7 verification

The authoritative result, its exact command, and the 40-character SHA it measured are recorded in
[`VERIFY-STEP-07-FINAL.md`](VERIFY-STEP-07-FINAL.md), captured verbatim from a clean tree. **That file
is the evidence; this section is a pointer to it and is not itself a result** (Rule 01).

The earlier `PASS 28 / FAIL 0 / SKIP 0` figure quoted here was produced at
`ca3476ae31bf718b78523dc982948c54626413aa` and **does not carry over**: the DEC-0040 and DEC-0041
ratification added three mandatory gates to the verifier, so the total moved. Evidence produced at one
SHA is never evidence at another (Rule 01, DEC-0013), and quoting the old number beside a changed gate
set would have been exactly that error.

**SKIP is 0, and that remains the claim that matters.** Both transitional skips are gone; nothing in
this run reports green by not having run.


## 5. Requirement → evidence traceability

| FR | Verified by |
|---|---|
| FR-086 tracking token issuance | `TrackingTokenTest` (entropy, distinctness, hash-only persistence), `TrackingSchemaTest` (no plaintext column) |
| FR-087 independence from the order number | `TrackingTokenTest` (no shared substring; identical order numbers → unrelated tokens), `PublicTrackingApiTest` (the order number resolves to nothing) |
| FR-088 revocation and expiry | `TrackingTokenTest` (terminal, no reactivation, rotation, tightening-only expiry), `PublicTrackingApiTest` (dead links indistinguishable) |
| FR-089 portal content set | `PublicProjectionTest` (key set equals the allow-list, both directions) |
| FR-090 portal exclusions | `PublicProjectionTest` (address, phone, notes, other orders, ids, token all absent from output while present in the database) |
| FR-091 OTP on sensitive actions | `TrackingOtpTest` (15 cases), `PublicTrackingApiTest` (generic responses) |
| FR-092 indexing prevention | `PublicTrackingApiTest` (header + meta tag, on both the valid and the not-available response) |
| FR-093 provider abstraction | `ProviderAbstractionTest` (structural source-tree assertions) |
| FR-094 official provider, fail-closed | `ProviderAbstractionTest` (absent/partial/disabled credentials; no unofficial adapter exists) |
| FR-095 manual deep-link fallback | `ProviderAbstractionTest`, `tracking_test.dart` ("BELUM dikirim"), the `MANUAL_FALLBACK_PREPARED` state |
| FR-096 transactional vs marketing | `NotificationPolicyTest` (category un-passable by reflection; consent default blocked) |
| FR-097 quiet hours | `NotificationPolicyTest` (boundaries, midnight wrap, multi-timezone, fail-closed, re-check at dispatch, every outbox-carried template defers, the outbox refuses an OTP template), `OtpQuietHoursExemptionTest` (the one DEC-0040 exempt class and its fences) |
| FR-098 deduplication | `NotificationOutboxTest` (replay, post-send replay, DB constraint) |
| FR-099 messaging decoupled from order state | `MessagingDoesNotGateOrderStateTest` (all five provider failure modes plus structural absence of any path back) |

## 6. What is NOT claimed

- **No live WhatsApp message was sent, to anyone, at any point.** Official WhatsApp Business
  credentials, an approved sender identity, and approved production templates are not available to
  this repository. The official adapter is therefore **UNVERIFIED AGAINST A LIVE PROVIDER**, and that
  is stated rather than papered over (Rule 01). What IS verified is its fail-closed behaviour, its
  error mapping, and that nothing else depends on it — all testable without credentials.
- **No production webhook or callback has been exercised.**
- **No deployment exists.** Deployment remains `ABSENT` and nothing here authorises it.
- **No UAT has occurred.** UAT remains `NOT STARTED`.
- **No performance measurement has been taken.** The portal is designed to be the lightest surface in
  the product; that is a design property, not a measured one.
- **No accessibility audit has been run.** The operator surface is covered by widget tests at 320 px
  and 1.6× text scale; that is not an assistive-technology audit and is not described as one.
- **`GO` is not claimed.** The step is `IN PROGRESS`; `GO` is conferred by the repository owner after
  merge and is never self-declared by an agent (Rule 01).

## 7. Open questions raised to the owner — BOTH NOW RESOLVED

Neither was closed by invention (Rule 00 hard rule 6, Rule 12). Both were raised to the repository
owner, and the owner decided them on **26 July 2026**.

- **OQ-018 → [DEC-0040](../../docs/decisions/DEC-0040-oq-018-user-initiated-security-transaction-quiet-hours-exemption.md).**
  The question was whether a customer-initiated tracking OTP is "urgent" for quiet-hours purposes.
  Master Source §14.1 rule 6 holds **non-urgent** messages until quiet hours end, and §14.2's catalogue
  carries **no OTP entry at all**, so the canonical text neither classified it nor granted it an
  exception. Step 7 originally took the conservative reading and deferred, which left the FR-091
  sensitive-action flow **unavailable between 20.00 and 08.00 outlet local time** — a five-minute
  challenge deferred to 08.00 is a message that verifies a challenge which already expired.

  **The owner classified it a `USER_INITIATED_SECURITY_TRANSACTION` and exempted it from quiet hours.**
  This is the exception `NOT-022` reserves to a decision record, and it is the first one granted. The
  implementation was changed accordingly and the conservative deferral is superseded. The exemption is
  gated on an explicit customer request and on nothing else; an automated origin is **refused**, not
  deferred; the ordinary outbox refuses OTP-carrying templates outright; and two database CHECK
  constraints close the classification set to one value and forbid a row from carrying the exemption
  together with `deferred_for_quiet_hours`. Rate limits, resend cooldown, expiry, attempt limit,
  single-use consumption, dedup, opt-out, and the account-takeover rule are all unchanged.

- **OQ-014 → [DEC-0041](../../docs/decisions/DEC-0041-oq-014-laravel-blade-as-the-public-tracking-portal-stack.md).**
  The question was which web stack the public portal uses. Step 7 implemented the zero-dependency
  option — server-rendered Blade, no new dependency, no new toolchain, no third-party asset, no script
  on the page — but OQ-014 required the choice to be recorded in a decision record by the step that
  builds it, and an agent does not accept a product decision on the owner's behalf.

  **The owner ratified Blade for this surface only**, with written boundaries: no parallel admin or
  operations application, no business rule duplicated in a view, no persistent browser storage of the
  token, no public authentication session, the transport controls mandatory, and no Step 8 or Step 9
  control on the surface. **No behaviour changed**; what changed is that the choice is now a ratified
  decision with a structural audit behind it (`scripts/validate-dec-0041-portal-stack.py`).

## 8. Residual audit correction made during this sprint

`validate-dec-0030-labels.py` still treated `tracking_token` and `public_tracking` as forbidden
labels, which produced a **false failure** on runtime that DEC-0039 explicitly authorises. DEC-0039
§11 made the DEC-0035 and DEC-0037 residual audits step-aware for exactly this reason and did not
notice that the DEC-0030 audit carried the same tokens. It is now step-aware by the identical
mechanism: forbidden below Step 7, audited by `validate-dec-0039-labels.py` from Step 7.

**This is not a widening.** The labels were already in the permitted band of the canonical guard
(`validate-runtime-scope.py`) by an accepted decision record; what changed is that a stale auditor no
longer contradicts it. Below Step 7 the tokens remain forbidden exactly as before, so the audit still
cannot false-pass in an earlier tree.

## 9. A defect found by the verifier, and fixed

`GET /orders/{order}/tracking-link` chose the order's current link with
`orderByDesc('issued_at')`. Rotation writes the new row and supersedes the old one inside a single
transaction, so both can carry an **identical** `issued_at`, and the tiebreak was then arbitrary —
roughly half of runs returned the **superseded** row.

The consequence was operational, not cosmetic: the operator screen would have shown a dead token as
current, returned `409 CONFLICT` on "Cabut tautan", and hidden the link the customer was actually
holding — so a staff member trying to close an over-shared link would have been told it had already
ended while the live one kept resolving.

It surfaced as an intermittent failure of the Step 7 tracking gate while the suite passed when run
alone. Ordering now puts an `ISSUED` row first (unique per order by the partial index, so the result
is deterministic), with `id` as a final tiebreak so the terminal-only case is stable between reads.
The regression test **forces** the two timestamps equal rather than re-rolling the race, so it pins
the ordering rule instead of passing by luck.

This is the second same-instant tiebreak defect on this branch. The first was a marketing consent
grant and withdrawal recorded in the same second, where the arbitrary winner could mean messaging a
customer who had opted out; that one now breaks toward `WITHDRAWN`. Both were found by tests, and both
are now decided by an explicit rule rather than by chance.
