# Rule 32 — Security and Privacy UX

## Purpose

A security control the user cannot see, understand, or act on is not a control. Tenant isolation, masking,
consent, and financial integrity all reach the user through an interface, and an interface that misstates
them defeats them regardless of how correct the server is. This rule fixes the security and privacy
interaction patterns. Delivered in Step 2, enforced at every Step that builds a surface.

Canonical artefacts: [`../../docs/ux/SECURITY_AND_PRIVACY_UX.md`](../../docs/ux/SECURITY_AND_PRIVACY_UX.md)
and [`../../docs/security/DESIGN_AND_UX_THREAT_REVIEW.md`](../../docs/security/DESIGN_AND_UX_THREAT_REVIEW.md).

## Hard rules

### Context and isolation

1. **Tenant and outlet context must be visible** on every authenticated screen, as text, in the primary
   chrome, and restated inside the confirmation block of any action that writes data (Rule 28, Rule 02).
2. **A denial never confirms the existence of another tenant's record.** Denial and absence are
   indistinguishable across a tenant boundary.
3. **Client-side hiding is never an access control.** A hidden control is a user-experience affordance;
   authorisation is server-side on every request (Rule 03).

### Masking

4. **Customer data must be masked** by default, at a level determined by who is looking and where. Phone is
   masked to country code plus last four digits. Address is never rendered in a list row and is shown at
   full precision only to roles with a pickup or delivery reason. Name is masked to given name plus initial
   on any unauthenticated or guest surface.
5. **Unmasking is a deliberate, per-record, permissioned, recorded action.** It is never a hover, never
   bulk, and never sticky across navigation.
6. **Aggregation raises class.** A screen or export that would render personal data for a large set requires
   a filter and a stated count rather than defaulting to the whole set.

### The public tracking projection

7. **The public tracking projection is separate.** It is an allow-list projection, not a filtered view of
   the internal order — a field absent from the allow-list is never assembled and therefore cannot leak.
8. The portal **never** renders: a full phone number; a full address; an internal note; margin; cost price;
   employee data beyond given name and role; any audit record; a sensitive photograph; or the tracking
   token value in any analytics field, path, parameter, event, or telemetry payload.
9. The portal carries `noindex`, uses first-party assets only, carries no third-party embed or analytics,
   and requires **no app installation** (DEC-0006, DEC-0014).
10. **The plaintext tracking token is `SECRET`.** It never appears in a page title, heading, breadcrumb,
    visible label, error message, support form, log, event, telemetry payload, or committed file. Ops
    surfaces show tracking *state* and a revoke control, never the token.

### External courier

11. **The external courier sees minimum data only.** The guest surface renders one assignment and nothing
    else: no navigation chrome, no search, no history, no customer profile, no pricing, no order value, no
    path to any other record. Address is shown at delivery-necessary precision, non-copyable and
    non-shareable; phone is masked with call-through and message-through actions.
12. The guest link is tenant-scoped, expiring, and revocable, and displays its own expiry. A revoked or
    expired link renders a neutral state disclosing nothing about whether the assignment existed
    (Rule 09).

### Financial interaction

13. **Payment success is never claimed from client state.** Success wording, colour, and iconography are
    reserved for server confirmation; a pending payment is rendered as pending and the order remains
    visibly unpaid (Rule 04, Rule 29).
14. **Destructive action requires confirmation.** Cancel, void, refund, revoke, delete-scoped, and remove
    are spatially separated from routine actions, never in the primary action position, never the visual
    default, never conveyed by colour alone, and always confirmed with the specific object and effect
    restated. Confirmation defaults focus to the safe choice.
15. **Confirmation strength scales with consequence.** Routine reversible actions need none. Financial and
    irreversible actions restate amount, order, customer, outlet, and tenant, and require a distinct
    deliberate gesture or step-up authentication — never merely a second tap in the same position.
16. **Financial action requires a reason where relevant.** For refund, void, quality-control waiver, cash
    variance, storage-fee adjustment, impersonation, and forced status correction, the reason field is
    mandatory, empty by default, rejects whitespace-only input, is never pre-filled or pre-selected, and is
    restated in the confirmation before commit (Rule 04).
17. **There is no delete-payment control** in any ordinary role's interface. Corrections are reversals or
    adjustments, and the interface says so.
18. **Refund is never a dark pattern.** It is discoverable, at the same prominence as payment, with no
    pre-selected amount and no discouraging friction.

### Sessions, notifications, consent

19. **Support impersonation is unmistakable**: a persistent, non-dismissible, text-and-icon banner on every
    screen naming the actor, the reason recorded at session start, and the remaining time, with an exit
    action always one step away. A reason is mandatory before the session begins (Rule 03).
20. **Session expiry never destroys work.** Expiry is warned in advance; re-authentication is step-up, does
    not leave the screen, does not clear the offline queue, and does not silently discard a queued financial
    operation.
21. **Push notifications carry the minimum.** Never an OTP, a tracking token, a full address, a full phone
    number, a payment amount, a customer's full name, an audit reason, or any credential. Sensitive detail
    is retrieved after authentication (Rule 08).
22. **Consent is unbundled, opt-in, positively worded, and defaulted to off**, and withdrawal is at least as
    easy as granting. Transactional messages are visibly distinguished from marketing messages and are never
    used to evade opt-out (Rule 08).
23. **Copy and clipboard affordances never carry secrets.** No copy control for an OTP, a session token, or
    a raw tracking token; nothing is placed on the clipboard automatically; copying a sensitive value warns
    that the clipboard is shared with other applications.

### Untrusted content

24. **User-supplied content is rendered as plain text.** Automatic link detection is off in customer notes,
    courier remarks, and any field an untrusted party can populate. An actionable link shows its full
    destination host, is marked external and user-supplied, and is followed through an interstitial.
25. **Tenant-uploaded assets are untrusted.** SVG is not inlined from user upload; raster formats are
    preferred for tenant brand assets, or SVG is sanitised server-side and rendered in a context that cannot
    execute script. Iconography is a curated first-party set (Rule 25).
26. **No remote embedded content on a token-bearing surface.** No remote font, remote icon set, analytics
    script, marketing pixel, session recorder, or third-party embed. Outbound links carry `noreferrer`.

### Prohibited outright

27. **No automatic disposal path.** No screen, control, bulk action, menu item, feature flag, tooltip, or
    backlog placeholder offers automated disposal, sale, auction, donation, write-off, or transfer of
    ownership of customer laundry. The terminal action is escalation to a named human plus a recorded
    reason (Rule 10).

## Step 2 note

**No security or privacy control is implemented.** There is no authentication, authorisation, masking,
rate limiting, token issuance, encryption, or audit trail, because there is no runtime. The backend is
`ABSENT`, the Flutter workspace is `ABSENT`, and the database is `ABSENT`. Step 2 records the interaction
patterns only. Controls are delivered across **Steps 3, 7, 8, and 12** and hardened in **Step 13**.

## Violation handling

- **A screen without visible tenant context** — tenant-isolation design defect (Rule 02).
- **A denial that reveals another tenant's record exists** — cross-tenant disclosure path; escalate
  (Rule 02).
- **Unmasked personal data rendered by default, or bulk unmasking offered** — privacy defect; fix before
  the Step closes.
- **A field on the public tracking projection outside the allow-list** — remove it; a full address or a
  sensitive photograph reaching the portal is an automatic `NO-GO` under Rule 03.
- **The tracking token value in an analytics field, log, event, or committed file** — treat as compromised:
  rotate first, then remove (Rule 03, Rule 23).
- **Navigation, search, history, pricing, or another record reachable from the external courier surface** —
  reject outright (Rule 09).
- **Payment rendered as successful before server confirmation** — financial-integrity design defect
  (Rule 04).
- **A destructive or financial action without confirmation, or a financial action without a mandatory
  reason** — reject the specification.
- **A delete-payment control in an ordinary role's interface** — remove it and replace with reversal or
  adjustment (Rule 04).
- **Impersonation rendered indistinguishably from a normal session** — silent platform access by interface
  design; automatic `NO-GO` under Rule 12.
- **An OTP, token, address, or amount in a push payload** — privacy defect; fix before the Step closes.
- **Pre-ticked, bundled, or hard-to-withdraw consent** — reject; consent obtained that way is not consent.
- **User-supplied content rendered with automatic link detection, or an unsanitised SVG inlined** —
  security defect; reject.
- **Any disposal, sale, or ownership-transfer affordance for customer laundry** — refuse outright and
  escalate to the repository owner. Do not implement it behind a flag, do not prototype it, do not leave it
  as a TODO (Rule 10).
