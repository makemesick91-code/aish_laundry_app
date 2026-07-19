# Console Web UX

**Surface:** Aish Laundry Console Web (Flutter Web)
**Roadmap steps:** Step 10 (finance, reports, portfolio), Step 12 (subscription and platform administration)
**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED · **Flutter workspace:** ABSENT

> **Documentation is not implementation.** No console, dashboard, or report exists.

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

---

## 1. Design intent

The console is where a laundry business is **understood, configured, and reconciled**. Its users have
a keyboard, a larger screen, and — critically — time to think. It is not a second point of sale, and
pulling counter workflows into it would produce a worse counter and a worse console.

Design bias: **comprehension over speed, and reversibility over convenience.**

---

## 2. The three modes

The mode is the most important orientation cue on this surface. It is never ambiguous, and it is
never signalled by colour alone.

| | **Portfolio Mode** | **Tenant Mode** | **Outlet Mode** |
|---|---|---|---|
| Badge | `MODE PORTOFOLIO` | `MODE TENANT` | `MODE OUTLET` |
| App bar | Dark blue | White with soft-blue accent | Soft blue |
| Breadcrumb | Tenant-agnostic | `Laundry Bersih Sejahtera` | `Laundry Bersih Sejahtera › Bersih Express › Outlet Cempaka` |
| Scope | Every tenant the user holds an active membership in | One tenant | One outlet |
| Granularity | **Aggregates only** | Individual records | Individual records, narrowed |
| Customer records | **Never shown** | Shown, tenant-scoped | Shown, outlet-scoped |
| Write actions | **None — structurally absent** | Subject to role and server-side authorisation | Same |
| Who | Tenant Owner with two or more tenants | Tenant Owner, Tenant Admin, Finance | Outlet Manager |

Three reinforcements:

1. **Colour, badge text, and breadcrumb together.** A monochrome screen still reads correctly.
2. Entering a tenant from Portfolio Mode is an **explicit act** with a confirmation naming the tenant,
   and it re-scopes every subsequent query.
3. Portfolio Mode has **no create, edit, or delete affordance anywhere** — the absence is structural,
   not a disabled state that could be re-enabled by a bug.

**Portfolio aggregation never widens the query surface**, and it never merges records. Two tenants
owned by the same person remain two unrelated data sets.

---

## 3. Dashboards

### Portfolio Dashboard — `SCR-CON-001`

Per-tenant rows with: revenue, order count, orders in production, orders `READY_FOR_PICKUP`, aging
distribution across H+1 / H+3 / H+7 / H+14, outstanding receivable, and subscription plan. All money
in integer Rupiah.

If one tenant's figures fail to load, the row shows its own error and **the portfolio total is
suppressed rather than estimated** (`UXS-019 Partial Data`). An incomplete total presented as complete
is worse than no total.

### Tenant Dashboard — `SCR-CON-002` and Outlet Dashboard — `SCR-CON-003`

Today's orders by status, revenue, cash position, unsynced device count, unclaimed-laundry pressure,
receivable ageing, and delivery jobs outstanding. Every figure reads from the authoritative financial
records; nothing is recomputed independently on this surface.

---

## 4. Operational screens

### Orders — `SCR-CON-004`

Filterable by status, outlet, date range, and payment state. Status labels come from the canonical
fifteen. `ISSUE` is a real, filterable state with a reason and an owner, not a rubbish bin.

### Production Board — `SCR-CON-005`

Columns for the production statuses. Moving a card **requests** a transition; the server decides
whether it is legal and whether this actor may perform it. A refused move snaps back and states why.
There is no generic "set status" control.

### Pickup and Delivery Planning, Courier Assignment, Route Ordering — `SCR-CON-011`, `012`, `013`

- Planning shows requests, schedules, zones, and time windows. Windows are **preferences**, described
  as such.
- Assignment covers internal couriers and external ojek lokal. Assigning an external courier issues a
  scoped, expiring, revocable guest link — never a tenant login.
- Route ordering is labelled **USULAN RUTE — BUKAN RUTE OPTIMAL** on the screen itself, in exports,
  and in any printed sheet. No arrival guarantee is offered anywhere.
- A guest link can be revoked from this screen, and revocation takes effect immediately.

### Unclaimed Laundry — `SCR-CON-014`

See [`./UNCLAIMED_LAUNDRY_UX.md`](./UNCLAIMED_LAUNDRY_UX.md) for the full specification.

---

## 5. Money screens

### Receivables — `SCR-CON-015`

Outstanding balances by customer and order, ageing, disputes. Integer Rupiah throughout. Figures read
from the authoritative financial records — this screen never recomputes money independently.

### Cashier Shifts — `SCR-CON-016`

Expected versus actual cash per shift, with the **variance shown explicitly** and its acknowledgement
recorded. A variance is never masked, auto-rounded away, or suppressed from a report.

### Courier Settlement — `SCR-CON-017`

Cash collected per courier per shift, from collection through handover, with variance recorded and
acknowledged. There is no delete action; corrections are reversal or adjustment entries.

### Reports — `SCR-CON-018`

Sales, production, delivery, receivables, and unclaimed laundry. Exports carry the tenant scope of the
view that produced them and are named accordingly. An export is subject to the same access rules as
the underlying records.

---

## 6. Configuration screens

### Pricing — `SCR-CON-008`

The dialog before saving says it plainly:

> Perubahan daftar harga **tidak** mengubah pesanan, faktur, atau cetak ulang yang sudah ada.

An order captures the price in force when it was created. Historical orders are immune to price-list
changes. Nobody should discover this by being surprised by it later.

Pricing figures reproduced anywhere on this surface match the canonical commercial table exactly —
never rounded, reformatted, or restated from memory.

### Employees and Roles — `SCR-CON-009`, `010`

Role edits show a **diff before saving**: who gains what, who loses what. Revoking a membership shows
what the user currently has open and whether they hold unsynced work on a device.

### Subscription — `SCR-CON-019`

Plan, limits, usage, and billing history. Two things are stated honestly:

- Starter's monthly order volume is **fair-use**, described as fair-use — never as a hard cutoff.
- **Tenant data remains exportable per policy when a subscription lapses** (`TEN-028`). Export is
  never blocked by a billing state.

Security controls, tenant isolation, and encrypted backup are **baseline on every plan**, and are
never presented as tier upgrades. There is no lifetime cloud plan and no per-nota fee on normal plans.

### Audit — `SCR-CON-020` and Support Access — `SCR-CON-021`

- Audit entries carry tenant context and are append-only (`TEN-022`).
- Support Access shows every platform support session against this tenant: who, when, for how long,
  and why. **There is no silent platform access to tenant data.**

---

## 7. Bulk and destructive actions

Any action affecting more than one record, or any action that removes access or changes money,
presents a confirmation panel showing **all six** of:

| Element | Example |
|---|---|
| **Item count** | "23 pesanan akan diperbarui" |
| **Scope** | "Laundry Bersih Sejahtera › Outlet Cempaka › filter: READY_FOR_PICKUP, 1–19 Juli 2026" |
| **Confirmation** | An explicit step that cannot be satisfied by one stray click — typing the outlet name for high-impact actions |
| **Permission** | The permission being exercised, and whether this user holds it |
| **Reason** | A reason code **plus** free text, both mandatory |
| **Audit effect** | "Tercatat dengan aktor, waktu, dan nilai sebelum/sesudah" |

Additional rules:

1. **Financial records are never hard-deleted from this surface.** The panel states whether it will
   create a **reversal** or an **adjustment** entry, by name.
2. Destructive actions are **visually and spatially separated** from routine actions.
3. Bulk selection is explicit. "Select all" selects the visible page and says so; extending to the
   whole filtered set is a second, separate confirmation that restates the count.
4. There is **no bulk action anywhere on this surface** that discards, sells, auctions, donates, or
   transfers ownership of customer laundry. That capability does not exist and will not be built.

---

## 8. Accessibility

- Full keyboard operation: every action reachable by keyboard, visible focus indicators throughout,
  and a logical tab order that follows the visual order.
- Tables have proper headers and scope; sorting state is announced, not merely arrow-shaped.
- Mode is conveyed by badge text and breadcrumb as well as colour.
- Status is never colour alone anywhere, including in charts, where series carry labels.
- Contrast meets accessible ratios in both the light surface and the dark Portfolio app bar.
- Wide content — tables, boards, diagrams — scrolls inside its own container. **The page body never
  scrolls horizontally.**
- Layouts survive large browser zoom and large system font sizes.

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

---

## 9. Responsive behaviour

| Breakpoint | Layout |
|---|---|
| compact `<600px` | Single column; navigation collapses to a drawer; the mode badge stays in the app bar; tables become stacked cards |
| medium `600–1023px` | Two columns; rail with icons; detail opens as a full-height sheet |
| expanded `1024–1439px` | Rail plus list plus detail panel side by side; the list never disappears when a detail opens |
| wide `>=1440px` | Wider detail panel, additional dashboard columns; content width-capped so lines stay readable |

---

## 10. What this surface must never do

1. Show any record from a tenant the user has no membership in.
2. Search or export across tenants.
3. Present an incomplete total as complete.
4. Hard-delete a financial record.
5. Claim route optimisation or a guaranteed arrival time.
6. Offer disposal, sale, auction, donation, or ownership transfer of customer laundry.
7. Block export of a tenant's own data because a subscription lapsed.
8. Place a security control, tenant isolation, or backup behind a paid tier.
9. Restate a pricing figure inaccurately.
10. Grant silent platform support access to tenant data.

---

## 11. Related documents

- [`./information-architecture/CONSOLE_WEB_IA.md`](./information-architecture/CONSOLE_WEB_IA.md)
- [`./information-architecture/GLOBAL_SEARCH_MODEL.md`](./information-architecture/GLOBAL_SEARCH_MODEL.md)
- [`./TENANT_AND_OUTLET_CONTEXT_UX.md`](./TENANT_AND_OUTLET_CONTEXT_UX.md)
- [`./UNCLAIMED_LAUNDRY_UX.md`](./UNCLAIMED_LAUNDRY_UX.md)
- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)

## 12. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Console Web | **NOT IMPLEMENTED** |
| Reports and exports | **NOT IMPLEMENTED** |
| Flutter workspace | **ABSENT** |
| Accessibility runtime testing | **NOT STARTED** |

`GO` is conferred by the repository owner and is never self-declared.
