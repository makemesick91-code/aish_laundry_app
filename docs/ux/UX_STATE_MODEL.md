# UX State Model

**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED
**Backend runtime:** ABSENT · **Flutter workspace:** ABSENT

> **Documentation is not implementation.** No state described here has ever been rendered. This is a
> taxonomy of obligations, not a report of behaviour.

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

---

## 1. Purpose and the one rule

This document is the **authoritative taxonomy** of non-happy-path states across all four surfaces. A
screen that invents its own state vocabulary is a defect; a screen that leaves a state undefined is
an incomplete design.

> **EVERY STATE HAS A RECOVERY PATH.** There is no terminal dead end anywhere in this product. A user
> who reaches any state below can always see what happened, what it means, and what to do next.

A state with no recovery path is not a state; it is a trap, and it blocks the Definition of Done.

## 2. Cross-cutting rules

1. **Status is never conveyed by colour alone.** Every state carries text, and where a chip is used,
   an icon as well.
2. **Errors explain recovery steps.** What happened, what it means, what to do. An error code alone
   is not an acceptable message. Codes may accompany the explanation for support purposes.
3. **Copy is Bahasa Indonesia** on every user-facing surface.
4. **Never blame the user** and never blame "the system" vaguely. Name the operation.
5. **Never inflate.** A state is never softened to read better; `GAGAL SINKRON` is not renamed to
   "menunggu" because it looks calmer.
6. **Money is never shown as settled unless the server said so.** Financial states are the strictest
   in this taxonomy.
7. **A messaging or provider failure never changes business state.**
8. States are announced to assistive technology using a live region; a change from Syncing to Failed
   Sync is announced, not merely repainted.

---

## 3. State index

| State ID | Name | Class | Blocks work? |
|---|---|---|---|
| `UXS-001` | Loading | Transient | Partially |
| `UXS-002` | Empty | Informational | No |
| `UXS-003` | Error | Failure | Yes, for the failed operation |
| `UXS-004` | Offline | Environmental | No on Ops Android; yes for server-dependent actions |
| `UXS-005` | Pending Sync | Sync | No |
| `UXS-006` | Syncing | Sync | No |
| `UXS-007` | Synced | Sync | No |
| `UXS-008` | Failed Sync | Sync failure | Yes, for that operation |
| `UXS-009` | Conflict | Sync failure | Yes, requires a decision |
| `UXS-010` | Permission Denied | Authorisation | Yes |
| `UXS-011` | Session Expired | Authentication | Yes |
| `UXS-012` | Device Revoked | Authentication | Yes |
| `UXS-013` | Tenant Unavailable | Context | Yes, for that tenant |
| `UXS-014` | Outlet Inactive | Context | Partially |
| `UXS-015` | Subscription Limited | Entitlement | Partially |
| `UXS-016` | Provider Degraded | External dependency | No |
| `UXS-017` | Rate Limited | Abuse resistance | Yes, temporarily |
| `UXS-018` | Maintenance | Platform | Yes, temporarily |
| `UXS-019` | Partial Data | Data integrity | No, but qualifies the data |
| `UXS-020` | Stale Data | Data integrity | No, but qualifies the data |

---

## 4. The states

### UXS-001 — Loading

| Field | Definition |
|---|---|
| **Trigger** | A request has been issued and no response has arrived |
| **Message** | "Memuat…" with the specific object where known ("Memuat antrean produksi…") |
| **Visual pattern** | Skeleton matching the final layout. Never a spinner over a blank screen. The shell, context bar, and navigation render immediately |
| **Allowed actions** | Cancel where the operation is cancellable; navigate away; open the context sheet |
| **Prohibited actions** | Submitting the same write twice; showing a partially loaded total as if complete |
| **Recovery** | On timeout, transitions to `UXS-003 Error` with retry. Loading never persists indefinitely without becoming an error |
| **Accessibility** | `aria-busy` equivalent on the region; announced once as "Memuat", not repeatedly |
| **Audit intent** | None |
| **Analytics intent** | Time-to-first-content per screen; timeout rate |

### UXS-002 — Empty

| Field | Definition |
|---|---|
| **Trigger** | A successful response containing zero records |
| **Message** | States what would appear here and, where a filter caused it, which filter |
| **Visual pattern** | Explanatory block with a single suggested next action. Never an unexplained blank |
| **Allowed actions** | The suggested next action; clear or widen the filter within the tenant |
| **Prohibited actions** | Presenting Empty when the request actually failed — that is `UXS-003` |
| **Recovery** | Create the first record, or adjust the filter |
| **Accessibility** | The explanation is real text, not an image; announced on arrival |
| **Audit intent** | None |
| **Analytics intent** | Zero-result rate by screen and filter |

### UXS-003 — Error

| Field | Definition |
|---|---|
| **Trigger** | A request failed, or a validation failed server-side |
| **Message** | What failed, what it means, what to do — in that order. "Pesanan belum tersimpan. Jaringan terputus saat mengirim. Coba kirim ulang." |
| **Visual pattern** | Inline where the failure is local to a field or panel; blocking only where the user cannot safely proceed |
| **Allowed actions** | Retry; correct the input; contact support; navigate away without losing captured work |
| **Prohibited actions** | Showing a bare error code; discarding user input; retrying a financial write with a **new** `client_reference` |
| **Recovery** | Retry with the original `client_reference`, correct and resubmit, or escalate to a manager |
| **Accessibility** | Announced assertively; focus moves to the error; the message is associated with the field it concerns |
| **Audit intent** | Server-side failures of financial operations are recorded with actor, tenant, outlet, timestamp, and reason |
| **Analytics intent** | Error class frequency by screen; never the error payload contents |

### UXS-004 — Offline

| Field | Definition |
|---|---|
| **Trigger** | No usable connectivity |
| **Message** | "Mode offline. Pekerjaan disimpan di perangkat dan dikirim saat jaringan kembali." |
| **Visual pattern** | Persistent chip in the context bar with text and icon; affected actions are disabled with an inline reason, never silently missing |
| **Allowed actions** | Ops Android: order intake, payment capture, production transitions, proof capture, courier cash. All surfaces: read cached data with a freshness label |
| **Prohibited actions** | OTP request and verification; discount approval requiring server grant; presenting cached money as current; queueing a Console Web write |
| **Recovery** | Automatic on reconnect; the queue drains under bounded exponential backoff |
| **Accessibility** | The chip is focusable and describes the count of pending items |
| **Audit intent** | None on entering offline; queued operations carry their own audit on the server when applied |
| **Analytics intent** | Offline duration; operations captured while offline; reconnect success rate |

### UXS-005 — Pending Sync

| Field | Definition |
|---|---|
| **Trigger** | An operation has been captured locally and has not yet been sent |
| **Message** | "Menunggu sinkron" with the exact count and the operation type |
| **Visual pattern** | Amber chip with text and icon on the item and a count badge on Antrean |
| **Allowed actions** | Open the queue; view the item; continue working |
| **Prohibited actions** | Presenting the operation as complete; printing a receipt that implies server confirmation; deleting the item without permission, reason, and audit |
| **Recovery** | Automatic send on reconnect; manual "Kirim sekarang" is available |
| **Accessibility** | The count is in the accessible name of the queue destination |
| **Audit intent** | The eventual server application is audited; local queueing is not a server event |
| **Analytics intent** | Queue depth distribution; time spent pending |

### UXS-006 — Syncing

| Field | Definition |
|---|---|
| **Trigger** | The queue is actively sending |
| **Message** | "Menyinkronkan 3 dari 5" — exact, never approximate |
| **Visual pattern** | Determinate progress on the queue screen; animated chip in the context bar |
| **Allowed actions** | Continue working; open the queue; capture new operations |
| **Prohibited actions** | Blocking the whole app behind sync; reordering dependent operations so a payment precedes its order |
| **Recovery** | On failure of an item, that item moves to `UXS-008`; the rest of the queue continues |
| **Accessibility** | Progress announced at meaningful milestones, not on every tick |
| **Audit intent** | None client-side |
| **Analytics intent** | Sync throughput; per-item latency |

### UXS-007 — Synced

| Field | Definition |
|---|---|
| **Trigger** | The server acknowledged the operation and returned its authoritative result |
| **Message** | "Tersinkron" plus the server timestamp in outlet local time |
| **Visual pattern** | Neutral chip with text and icon; the item leaves the pending list |
| **Allowed actions** | Print or reprint a confirmed receipt; proceed with dependent operations |
| **Prohibited actions** | Showing Synced on the strength of a local write; showing Synced when only some of a dependent chain succeeded |
| **Recovery** | Not applicable — this is a success state, and it is the **only** state in which money is presented as confirmed |
| **Accessibility** | Announced politely once |
| **Audit intent** | The server records actor, tenant, outlet, timestamp, and amounts |
| **Analytics intent** | End-to-end capture-to-acknowledgement time |

### UXS-008 — Failed Sync

| Field | Definition |
|---|---|
| **Trigger** | The server rejected the operation, or retries were exhausted under the bounded policy |
| **Message** | "Gagal sinkron" plus the server's stated reason in plain Bahasa Indonesia |
| **Visual pattern** | Red chip with text and icon; the item stays in Antrean at the top |
| **Allowed actions** | Retry with the **original** `client_reference`; correct and resubmit; escalate to the outlet manager |
| **Prohibited actions** | **Silently dropping the item.** Retrying with a new `client_reference`. Hiding the failure behind a generic offline chip |
| **Recovery** | Manual retry, correction, or a permissioned and audited removal with a recorded reason. The item never disappears on its own |
| **Accessibility** | Announced assertively; the queue badge distinguishes failed from pending |
| **Audit intent** | A removed queue item records actor, timestamp, reason, and the operation's contents |
| **Analytics intent** | Failure reason class; retry success rate. **No silent sync failure is permitted anywhere in this product** |

### UXS-009 — Conflict

| Field | Definition |
|---|---|
| **Trigger** | Local state and server state disagree about the same record |
| **Message** | Names the record, shows the **server value** and the **local value** side by side with their timestamps |
| **Visual pattern** | Blocking panel; two explicit, clearly labelled resolution choices; no default is preselected |
| **Allowed actions** | Accept the server value; submit the local value as a new, correctly sequenced operation; escalate to a manager |
| **Prohibited actions** | **Silent overwrite in either direction.** Auto-resolving a money conflict. Dismissing the panel by navigating back |
| **Recovery** | An explicit human decision. **The server is the final source of truth**; where the user believes the server is wrong, the correction is a new audited entry, never an edit of history |
| **Accessibility** | Both values are readable text with labels; the two actions are distinct in wording, not only in position |
| **Audit intent** | The resolution is recorded with actor, timestamp, chosen value, and reason |
| **Analytics intent** | Conflict rate by operation type; resolution latency |

### UXS-010 — Permission Denied

| Field | Definition |
|---|---|
| **Trigger** | The backend refused the operation for this membership |
| **Message** | States that this role cannot perform the action and names who in the tenant can |
| **Visual pattern** | Inline panel replacing the action, or a blocking page for a whole destination |
| **Allowed actions** | Request approval from a manager; navigate to a permitted area; contact the tenant admin |
| **Prohibited actions** | Revealing whether the record exists in another tenant; revealing the record's contents; implying the denial is a temporary fault |
| **Recovery** | A role change by the tenant admin, or a manager performing the action. **Client-side visibility is not authorization** — this state is produced by the server, not by the client hiding a button |
| **Accessibility** | Announced assertively; focus moves to the explanation |
| **Audit intent** | Denied attempts on financial and tenant-configuration operations are recorded |
| **Analytics intent** | Denial rate by role and destination — a high rate suggests the navigation model is wrong |

### UXS-011 — Session Expired

| Field | Definition |
|---|---|
| **Trigger** | The session was revoked server-side, or its lifetime elapsed |
| **Message** | "Sesi berakhir. Masuk kembali untuk melanjutkan." plus, on Ops Android, the unsynced count |
| **Visual pattern** | Blocking screen; on Ops Android the queue summary is visible on this screen |
| **Allowed actions** | Re-authenticate; view the unsynced queue summary |
| **Prohibited actions** | **Clearing the queue.** Discarding a POS draft. Silently switching tenant on re-authentication |
| **Recovery** | Re-authentication returns the user to the intended destination with the queue and drafts intact |
| **Accessibility** | Focus moves to the sign-in action; the unsynced count is in the announcement |
| **Audit intent** | Session termination is recorded server-side |
| **Analytics intent** | Expiry frequency; work lost (target: zero) |

### UXS-012 — Device Revoked

| Field | Definition |
|---|---|
| **Trigger** | A tenant admin or owner revoked this specific device |
| **Message** | States that this device's access was withdrawn and that unsynced work must be handed over |
| **Visual pattern** | Blocking screen listing unsynced operations by reference and amount |
| **Allowed actions** | View the unsynced list; contact the outlet manager; request re-activation |
| **Prohibited actions** | **Silently discarding unsynced work.** Continuing to serve cached tenant data. Syncing under a revoked device credential |
| **Recovery** | Re-activation by an authorised user, or handover of the unsynced list to the outlet manager for manual re-entry under an audited process. Revoking one device never forces every other device to re-authenticate |
| **Accessibility** | The unsynced list is readable text; the handover instruction is explicit |
| **Audit intent** | Revocation records actor, device, tenant, timestamp, and reason |
| **Analytics intent** | Revocation frequency; unsynced work at revocation |

### UXS-013 — Tenant Unavailable

| Field | Definition |
|---|---|
| **Trigger** | The tenant is suspended or the membership was revoked |
| **Message** | Names the tenant, states that it cannot be opened, and says what still works |
| **Visual pattern** | Blocking panel with the list of remaining memberships |
| **Allowed actions** | Switch to another membership; contact the tenant owner; view the preserved queue for that tenant |
| **Prohibited actions** | Silently switching tenant; serving cached data from the unavailable tenant; leaving it in a portfolio aggregate |
| **Recovery** | Choose another membership, or wait. **A suspended tenant retains its business data and its export right** (`TEN-003`, `TEN-028`) |
| **Accessibility** | The tenant name is stated, not implied by position |
| **Audit intent** | Suspension and membership revocation are audited server-side |
| **Analytics intent** | Occurrence rate; whether the user had unsynced work |

### UXS-014 — Outlet Inactive

| Field | Definition |
|---|---|
| **Trigger** | The outlet was deactivated by the tenant |
| **Message** | "Outlet Cempaka tidak aktif. Pesanan baru tidak dapat dibuat." |
| **Visual pattern** | Banner on the operational home; new-order actions disabled with the reason inline |
| **Allowed actions** | Complete and sync work already captured; read history; switch outlet |
| **Prohibited actions** | Starting a new order; deleting queued work belonging to that outlet |
| **Recovery** | Switch to an active outlet, or reactivate the outlet if permitted |
| **Accessibility** | The banner is announced on arrival and is not dismissible into invisibility |
| **Audit intent** | Outlet deactivation is a tenant-configuration change and is audited |
| **Analytics intent** | Occurrence rate; work stranded at deactivation |

### UXS-015 — Subscription Limited

| Field | Definition |
|---|---|
| **Trigger** | A plan limit is reached — outlets, staff, or monthly order volume |
| **Message** | Names the limit, the current plan, and who can resolve it. Starter's order volume is described as **fair-use**, never as a hard cutoff |
| **Visual pattern** | Banner plus an inline explanation on the blocked action |
| **Allowed actions** | Continue reading; export the tenant's own data; contact the tenant owner; open Subscription if permitted |
| **Prohibited actions** | **Blocking export of the tenant's own data.** Placing a security control, tenant isolation, or backup behind the paywall. Inventing a plan, discount, or limit |
| **Recovery** | The tenant owner reviews the plan. **Tenant data remains exportable per policy when a subscription lapses** (`TEN-028`) |
| **Accessibility** | Plan names and figures are text, not images |
| **Analytics intent** | Which limit is hit, how often, on which plan |
| **Audit intent** | Plan changes are audited |

### UXS-016 — Provider Degraded

| Field | Definition |
|---|---|
| **Trigger** | A third-party provider — WhatsApp, payment gateway, map — is failing or slow |
| **Message** | Names the affected capability and states plainly that the order is unaffected |
| **Visual pattern** | Non-blocking banner scoped to the affected area |
| **Allowed actions** | Continue all business operations; use the manual WhatsApp deep-link fallback where configured; retry later |
| **Prohibited actions** | **Cancelling, blocking, or reversing an order because messaging failed.** Marking an order paid on an unverified provider response. Describing the deep-link fallback as automation |
| **Recovery** | Automatic retry under a bounded policy; failures are made visible for human attention, never silently discarded |
| **Accessibility** | The banner explains impact in plain language |
| **Audit intent** | Provider failures affecting financial callbacks are recorded |
| **Analytics intent** | Provider failure rate and duration; deferred message volume |

### UXS-017 — Rate Limited

| Field | Definition |
|---|---|
| **Trigger** | Too many attempts — OTP requests, tracking-token lookups, sign-in attempts, searches |
| **Message** | States that too many attempts were made and when to retry, in plain language |
| **Visual pattern** | Blocking panel on the affected action only |
| **Allowed actions** | Wait; contact the outlet through a human channel |
| **Prohibited actions** | Revealing whether the attempted identifier exists; offering an unlimited retry; leaking the remaining budget in a way that assists an attacker |
| **Recovery** | Wait out the window, or use the human path. **There is always a human path** |
| **Accessibility** | The wait time is text; a countdown is supplementary, not the only information |
| **Audit intent** | Repeated limiting on authentication and token lookup is recorded as a security signal |
| **Analytics intent** | Limiting frequency by endpoint. **Never the attempted token or OTP value** |

### UXS-018 — Maintenance

| Field | Definition |
|---|---|
| **Trigger** | A planned or unplanned platform maintenance window |
| **Message** | States the window in outlet local time and what remains available |
| **Visual pattern** | Blocking page on Console Web; banner on Ops Android, which continues to capture work offline |
| **Allowed actions** | Ops Android: continue capturing work into the queue. All surfaces: read cached data with a freshness label |
| **Prohibited actions** | Discarding queued work; presenting maintenance as a user error |
| **Recovery** | Automatic on service restoration; the queue drains |
| **Accessibility** | Times are written out, not only shown as a countdown |
| **Audit intent** | None user-facing |
| **Analytics intent** | Maintenance impact duration by surface |

### UXS-019 — Partial Data

| Field | Definition |
|---|---|
| **Trigger** | Some panels or pages of a composite view failed while others succeeded |
| **Message** | Names which parts are missing — "Ringkasan piutang belum dapat dimuat" |
| **Visual pattern** | The failed panel shows its own error; totals that depend on it are **suppressed**, not estimated |
| **Allowed actions** | Retry the failed panel; use the parts that loaded |
| **Prohibited actions** | **Showing an incomplete total as if it were complete.** Silently omitting a data source from a figure |
| **Recovery** | Per-panel retry; a narrower date range for reports |
| **Accessibility** | The missing part is announced; the suppressed total states why it is absent |
| **Audit intent** | None |
| **Analytics intent** | Panel failure rate; which composite views degrade most |

### UXS-020 — Stale Data

| Field | Definition |
|---|---|
| **Trigger** | Displayed data came from cache rather than a fresh server response |
| **Message** | "Data per 14:30, 19 Juli 2026" in outlet local time |
| **Visual pattern** | Freshness label attached to the data, not to the page chrome |
| **Allowed actions** | Refresh; act on non-financial information with the staleness understood |
| **Prohibited actions** | **Showing a money figure from cache without a freshness marker.** Implying a cached balance is settled. Using a cached loyalty or deposit balance to imply spendable credit |
| **Recovery** | Refresh on demand; automatic refresh on reconnect |
| **Accessibility** | The freshness label is part of the accessible name of the figure it qualifies |
| **Audit intent** | None |
| **Analytics intent** | Cache-served ratio; age of served data |

---

## 5. State coverage obligation

Every screen in [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md) must state its behaviour for, at
minimum: `UXS-001`, `UXS-002`, `UXS-003`, `UXS-004`, and `UXS-010`. Operational Ops Android screens
must additionally cover `UXS-005` through `UXS-009`, `UXS-011`, and `UXS-012`. Tracking portal
screens must cover `UXS-017`.

A screen that omits a required state is an incomplete design and does not meet the Step 2 Definition
of Done.

## 6. Related documents

- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./OFFLINE_AND_SYNC_UX.md`](./OFFLINE_AND_SYNC_UX.md)
- [`./CRITICAL_JOURNEYS.md`](./CRITICAL_JOURNEYS.md)
- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)
- [`./information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md`](./information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md)

## 7. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| UX states | **NOT IMPLEMENTED** |
| State rendering | **NOT IMPLEMENTED** |
| Accessibility runtime testing | **NOT STARTED** |

`GO` is conferred by the repository owner and is never self-declared.
