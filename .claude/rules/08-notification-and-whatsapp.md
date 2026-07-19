# Rule 08 — Notifications and WhatsApp

## Purpose

WhatsApp is the primary customer communication channel for Indonesian laundry businesses. It is also
a third-party service with real per-message costs, policy constraints, and delivery failures. This
rule prevents the two classic mistakes: coupling the product to one vendor, and over-promising
messaging that the business cannot afford to deliver.

Backed by **DEC-0008 (H+1 H+3 H+7 Reminder as Core Product)** and **DEC-0011 (Transparent
Third-Party Messaging Costs)**. Delivered in Step 7.

## Hard rules

1. **Provider abstraction is mandatory.** WhatsApp sending sits behind an internal interface. No
   vendor SDK, vendor-specific payload, or vendor identifier leaks into business logic. Swapping
   providers must be a configuration and adapter change, not a product rewrite.
2. **The official provider is the automated path.** Automated, unattended sending goes through an
   official WhatsApp Business API provider.
3. **Manual deep-link is the fallback**, not the primary mechanism. A `wa.me`-style deep link that a
   staff member taps is an acceptable fallback for tenants without a provider — it must never be
   described or sold as automation.
4. **Transactional and marketing messages are separated** — separate categories, separate templates,
   separate consent handling, separate reporting. A marketing message must never be sent through a
   transactional path to evade opt-out.
5. **Opt-out is honoured.** A customer who opts out of marketing receives no marketing message,
   permanently, without exception, across all outlets of the tenant. Opt-out state is respected at
   send time, not only at campaign-build time.
6. **Quiet hours default to 20.00–08.00 outlet local time.** No non-critical message is sent inside
   the quiet window. Messages queued during quiet hours are deferred to the next permitted window,
   not dropped and not silently sent anyway.
7. **Message deduplication is required.** The same notification for the same order, event, and
   recipient is not sent twice — including across retries, queue replays, and scheduler restarts.
   Deduplication is keyed on a stable identity (recipient + event + order + intended send window).
8. **A WhatsApp failure never cancels an order.** Messaging is a side effect. If the provider is
   down, the order proceeds and the message is retried or flagged. Business state never depends on
   message delivery.
9. **Provider costs are transparent.** Tenants can see what messaging is costing them. WhatsApp
   provider fees are billed separately from the subscription plan (Rule 14).
10. **Never promise fake "unlimited WhatsApp."** Not in the app, not in pricing pages, not in
    documentation, not in marketing copy. Message volume has a real third-party cost, and claiming
    otherwise is a false claim under Rule 01.

## Supporting expectations

- Every send is tenant-scoped and recorded with tenant, outlet, order, recipient, template, category,
  status, timestamp, and provider reference.
- Delivery failures are visible to the tenant and retried under a bounded policy — not retried
  forever, and not silently discarded.
- Message content follows the privacy masking rules: no full address, no token, no OTP echoed back,
  no sensitive personal data beyond what the recipient already owns (Rule 03).
- The unclaimed-laundry reminder ladder (H+1 / H+3 / H+7 / H+14, Rule 10) is a consumer of this
  subsystem and inherits every rule here, including quiet hours and deduplication.
- Critical operational messages may have a defined exception path around quiet hours only if the
  Master Source or an accepted decision record explicitly grants it. Absent such a record, quiet
  hours apply.

## Step 0 note

No messaging code, provider integration, template, or scheduler exists. In Step 0 it is forbidden to
create any WhatsApp implementation. This rule records the constraints only.

## Violation handling

- **A message sent inside quiet hours** without an explicitly recorded exception — treat as a
  product defect; fix the scheduler before further messaging work.
- **A duplicate message reaching a customer** — treat as a defect of the same class as a duplicate
  payment: investigate the deduplication key, fix, and add a regression test.
- **A marketing message sent to an opted-out recipient** — stop marketing sends, notify the owner,
  and fix before resuming. This is a trust and compliance failure, not a minor bug.
- **An order cancelled, blocked, or failed because messaging failed** — reject the design; messaging
  must be decoupled from business state.
- **"Unlimited WhatsApp" or equivalent appearing anywhere** — remove immediately as a false claim
  under Rule 01 and a pricing guardrail breach under Rule 14.
- **Vendor specifics leaking into business logic** — refactor behind the abstraction before the step
  can meet its Definition of Done.
