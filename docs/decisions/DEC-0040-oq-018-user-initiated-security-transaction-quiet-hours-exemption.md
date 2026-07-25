# DEC-0040 — Customer-Initiated Tracking OTP is a USER_INITIATED_SECURITY_TRANSACTION and is Exempt from Quiet Hours (OQ-018 Ratification)

**ID:** DEC-0040
**Title:** Customer-Initiated Tracking OTP is a USER_INITIATED_SECURITY_TRANSACTION and is Exempt from Quiet Hours (OQ-018 Ratification)
**Status:** ACCEPTED
**Date:** 26 July 2026

---

## Context

Master Source §14.1 rule 6 holds **non-urgent** messages until quiet hours end (20.00–08.00 outlet local
time), and §14.2's notification catalogue contains **no OTP entry at all**. The canonical text therefore
neither classified a one-time verification code nor granted it an exception. `NOT-022` states the only
lawful route to an exception: it exists *"only where the Master Source or an accepted decision record
explicitly grants it. Absent such a record, quiet hours apply."*

FR-091 requires OTP verification before either of the two canonical sensitive portal actions — changing a
delivery address, and requesting a schedule change. A challenge lives five minutes
(`TrackingOtpChallenge::TTL_SECONDS`). Deferring such a message to 08.00 is not a delay; it is a
non-delivery, because the challenge it verifies has already expired.

Step 7 therefore implemented the **conservative reading** and deferred, which made the FR-091 flow
unavailable between 20.00 and 08.00 outlet local time. That consequence was recorded rather than hidden,
as [OQ-018](../product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md), and was deliberately **not** resolved by
inventing an "urgent" bypass: choosing one would have settled an owner question by agent invention
(Rule 00 hard rule 6), and an unnamed "urgent" category becomes the route every future message quietly
takes.

The repository owner has now decided OQ-018.

## Options considered

- **Classify the customer-initiated OTP as a `USER_INITIATED_SECURITY_TRANSACTION`, exempt from quiet
  hours, with the exemption bounded to messages the customer explicitly asked for.** Adopted. It is not
  an outbound notification the business decided to send; it is the second half of an interaction the
  customer started seconds earlier, in the same session, while looking at the screen. The 20.00–08.00
  window exists to stop a business messaging a customer at an unwelcome hour — it was never intended to
  stop the customer from completing something they themselves initiated.
- **A general "urgent message" exception.** Rejected. "Urgent" is a judgement, not a structural property,
  and every future message would be argued into it. The adopted class is defined by an observable fact —
  the customer made an explicit request in this request cycle — not by an assessment of importance.
- **Keep the conservative deferral (status quo).** Rejected by the owner. It leaves FR-091 unavailable for
  twelve hours a day, and it does so in the name of protecting a customer from a message that same
  customer just asked for.
- **Shorten quiet hours, or make them tenant-configurable to work around the OTP case.** Rejected. It
  would weaken the protection for genuine outbound notifications in order to fix an unrelated case.

## Decision

1. **A customer-initiated OTP for a canonical FR-091 sensitive action is classified
   `USER_INITIATED_SECURITY_TRANSACTION`.** It is a transactional security message, never a scheduled
   outbound notification and never marketing.

2. **Quiet hours 20.00–08.00 do not defer a `USER_INITIATED_SECURITY_TRANSACTION`.** This is the
   `NOT-022` exception, granted here, and it is granted **only** for this class. Every other message class
   — including every other transactional template — continues to defer exactly as before. Master Source
   §14.1 rule 6 is unchanged in text and unchanged in effect for non-urgent messages; this record supplies
   the classification that rule always presupposed.

3. **The exemption is gated on an explicit customer request and on nothing else.** An OTP that no
   customer asked for is **rejected**, not sent and not deferred. The dispatch origin is a required,
   typed argument (`OtpDispatchOrigin`) and only `CustomerRequest` is honoured; an automated or
   system-initiated origin is refused with the stated reason `otp_not_customer_initiated`. The generic
   notification outbox additionally **refuses any OTP-carrying template outright**, so the exempt path
   cannot be reached by enqueueing a template.

4. **Marketing opt-out does not block a customer-requested security OTP, and this creates no marketing
   route.** The OTP template is `transactional` by catalogue definition, and category comes from the
   template and never from a caller (FR-096, NOT-024). A marketing message can therefore not acquire this
   exemption by relabelling: `USER_INITIATED_SECURITY_TRANSACTION` is only ever assigned by the OTP
   delivery path, which renders exactly one template and requires a plaintext code it holds for the
   duration of one call.

5. **Every other control remains mandatory and unchanged.** Per-token and per-IP rate limiting, the
   resend cooldown, the five-minute expiry, the attempt limit, single-use consumption (replay
   prevention), and binding of the challenge to its destination, action, token, and order all continue to
   apply in full. This record relaxes the quiet-hours schedule and nothing else. In particular the
   account-takeover rule stands: a message never carries an OTP value and a tracking link together
   (TRK-029, NOT-014, Master Source §14.3).

6. **Provider failure is reported truthfully.** `SENT` continues to mean the provider accepted the
   message and never that a customer received it; an unavailable provider yields `FAILED_PERMANENT` with
   its failure code, and the manual deep-link fallback is still **not** offered for an OTP, because
   handing a staff member a link containing a customer's verification code defeats the code. Enqueueing,
   local acceptance, or challenge issuance is never rendered as provider delivery (Rule 01).

7. **The classification is recorded in the audit trail.** The notification intent carries
   `security_classification = 'USER_INITIATED_SECURITY_TRANSACTION'`, and the tracking access event for a
   challenge issue carries the same classification. A database CHECK constraint makes the exemption
   structural: a row classified `USER_INITIATED_SECURITY_TRANSACTION` can never also be marked
   `deferred_for_quiet_hours`.

8. **This record authorises no new capability.** No Step 8 pickup or delivery behaviour and no Step 9
   reminder behaviour is introduced. A verified OTP continues to record an **accepted request** and
   continues not to reschedule a delivery or rewrite an address, exactly as DEC-0039 §5 bounds it.

## Consequences

OQ-018 is closed. The FR-091 sensitive-action flow works at every hour of the day, and the quiet-hours
protection that governs outbound notification is unchanged for every message a customer did not ask for.

### Positive consequences

- A customer who needs to correct a delivery address at 22.30 can complete the flow, instead of being
  told to return after 08.00 — the case that made the conservative reading a real usability failure.
- The exception is defined by a structural fact (an explicit customer request in this request cycle)
  rather than by an "urgency" judgement, so it cannot be argued outward to cover a future message.
- The bypass cannot be reached by the notification outbox at all, because the outbox now refuses
  OTP-carrying templates; there is one exempt path and it is the one that requires a live plaintext code.
- The exemption is enforced by a database CHECK rather than by convention, so a future code path cannot
  claim the classification and defer at the same time.

### Negative consequences / trade-offs

- A customer whose phone number has been entered into another person's order could be sent a verification
  code at 03.00. The resend cooldown, per-token limit (3/hour), and per-IP limit (12/hour) bound this to a
  small number of messages, but they do not reduce it to zero. This is the accepted cost of the decision
  and it is stated rather than minimised.
- The message class is a genuine widening of what may be sent inside quiet hours. It is narrow, named, and
  structurally gated, but it is the first such exception in the product, and every future request for one
  must be judged on its own record rather than by pointing at this one.
- `security_classification` adds a column to `notification_intents`, so the Step 7 notification schema
  carries one more field than it did before this decision.

## Verification

Verified on `feature/step-07-runtime-customer-tracking-whatsapp`, bound to the exact commit SHA recorded
in the Step 7 evidence pack (Rule 01, DEC-0013). This record quotes no result it did not produce; the
executed output, its command, and its SHA live in
[`evidence/step-07/`](../../evidence/step-07/), not in this file.

Covered by `backend/tests/Feature/Notification/OtpQuietHoursExemptionTest.php`:

- a customer request at 19.59 outlet local is eligible;
- a customer request at 20.00, at 00.00, and at 07.59 outlet local is eligible **immediately** — no
  deferral, `deferred_for_quiet_hours` false, `scheduled_for` unmoved;
- an OTP with a non-customer origin is rejected with `otp_not_customer_initiated` and nothing is sent;
- the generic outbox refuses an OTP-carrying template;
- a marketing notification inside quiet hours is still deferred;
- a withdrawn marketing consent still blocks marketing, and does **not** block a customer-requested OTP;
- the per-token rate limit and the resend cooldown still refuse a challenge;
- the notification intent and the tracking access event both record
  `USER_INITIATED_SECURITY_TRANSACTION`;
- an exempt intent marked `deferred_for_quiet_hours` is refused by the database CHECK.

`backend/tests/Feature/Notification/NotificationPolicyTest.php` continues to prove that every template the
outbox carries defers inside quiet hours, so the exception is demonstrably confined to the one class this
record names.

## Requirement references

FR-091 (OTP-gated sensitive portal actions), FR-096 (transactional/marketing separation and consent),
FR-097 (quiet hours), FR-098 (deduplication), FR-099 (messaging never gates order state), NOT-003,
NOT-004, NOT-014, NOT-016, NOT-021, NOT-022, NOT-024, TRK-029, SEC-042 … SEC-045. **No requirement is
created, changed, or withdrawn.** This record supplies the classification `NOT-022` requires before an
exception may exist, and resolves OQ-018.

## Rule references

- Rule 08 — notification and WhatsApp; quiet hours, dedup, opt-out, and the rule that a messaging failure
  never changes order state.
- Rule 00 hard rule 6 — an open question is closed by the owner, never by invention.
- Rule 01 — status vocabulary; `SENT` means provider-accepted and never customer-received.
- Rule 03 hard rule 20 / Rule 46 — an OTP value is never written to a log, an event, or a row.
- Rule 12 — an agent does not accept an owner decision on the owner's behalf; this record is the owner's.
- Rule 32 hard rule 21 — a notification payload carries the minimum, and never an OTP together with a
  tracking link.

## Supersession policy

This record supersedes nothing. It resolves OQ-018 and grants the single, named quiet-hours exception that
`NOT-022` reserves to a decision record. It does not amend Master Source §14.1 rule 6, which continues to
govern every non-urgent message unchanged.

Widening the exemption to any further message class, or removing the explicit-customer-request gate,
requires a **new** accepted decision record naming this one. Editing `QuietHours`, `OtpMessenger`, or
`OtpDispatchOrigin` to broaden the exemption without such a record is a governance breach (Rule 00,
Rule 08).

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §9 — public tracking portal.
- §14 — notifications and WhatsApp; §14.1 rule 6, §14.2 catalogue, §14.3 content rules.
- §15 — security.
- §24 — Roadmap; Step 7.
- §31 — Decision records.
- §32 — Changelog.
