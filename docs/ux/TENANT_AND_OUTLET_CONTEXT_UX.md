# Tenant and Outlet Context UX

**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED · **Backend runtime:** ABSENT

> **Documentation is not implementation.** No context bar, switcher, or guard exists.

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

The structural model lives in
[`./information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md`](./information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md).
This document covers the **experience**: what it looks like, what it says, and what it refuses to do.

---

## 1. The design problem

A tenant owner may operate `Laundry Bersih Sejahtera` and, separately, `Laundry Melati Wangi`. Those
are two businesses. They may compete. The same owner, the same phone number, the same laptop — and a
hard boundary between them that the interface must make impossible to forget.

Meanwhile a cashier at `Outlet Cempaka` never thinks about tenants at all. For them, context is one
line of text they should never have to check twice.

Both users are served by the same principle: **context is always shown, never inferred.**

---

## 2. The context bar

### 2.1 Anatomy

```
┌──────────────────────────────────────────────────────────────┐
│ Laundry Bersih Sejahtera  ›  Bersih Express  ›  Outlet Cempaka │
│ Kasir                                    ⟳ MENUNGGU SINKRON 3  │
└──────────────────────────────────────────────────────────────┘
```

| Element | Behaviour |
|---|---|
| Tenant | Always shown. Truncates from the middle, never the start; full value on focus and long-press |
| Brand | Shown when the tenant has more than one brand |
| Outlet | Always shown on Ops Android and in Console Outlet Mode |
| Role | The role in force for the active membership, in Bahasa Indonesia (`Kasir`, `Operator Produksi`, `Kurir`) |
| Sync chip | Ops Android only. Text plus icon. Exact count, never "9+" |

### 2.2 Rules

1. The bar is persistent on every operational screen. It does not scroll away.
2. **Never colour alone.** Every element carries text.
3. It survives large system font sizes without truncating the tenant name or the sync count. At the
   largest supported scale the bar wraps to two lines rather than dropping information.
4. Tapping it opens the context sheet.
5. On **financial and custody screens** — Payment, Partial Payment, Refund, Shift Close, Proof
   Capture, Courier Cash — the tenant and outlet are repeated **inside the confirmation dialog**. A
   user must never confirm Rp79.000 without seeing which outlet it belongs to.

### 2.3 Accessible name

The bar exposes a single accessible name that reads as a sentence, not as five disconnected labels:
"Tenant Laundry Bersih Sejahtera, brand Bersih Express, outlet Outlet Cempaka, peran Kasir, 3 operasi
menunggu sinkron."

---

## 3. The context sheet

Opened from the bar. Four sections:

| Section | Content |
|---|---|
| **Tenant** | The active tenant, and every other tenant the user holds an active membership in, each with its role and outlet count |
| **Outlet** | Outlets of the active tenant available to this membership |
| **Peran** | The role in force and, in plain language, what it permits — with the explicit note that the backend decides, not the menu |
| **Antrean** | Unsynced count by type and total value in integer Rupiah |

The sheet never shows a tenant the user has no membership in, never shows an outlet count for a
tenant the user cannot enter, and never shows aggregated figures across tenants.

---

## 4. Switching tenant — the experience

### 4.1 The happy case

1. The user opens the sheet and taps another tenant.
2. A confirmation names the target tenant explicitly: "Pindah ke Laundry Melati Wangi?"
3. On confirm, the screen shows a **clean transition** — no data is rendered during the switch. There
   is never a frame in which one tenant's data sits under another tenant's label.
4. The new tenant's outlet picker appears (or is skipped for a single-outlet membership).
5. Operational home renders, fully re-scoped.

### 4.2 The guarded case

When a critical queue exists the confirmation becomes a blocking warning:

> **3 operasi belum tersinkron di Laundry Bersih Sejahtera**
> AL-2026-000123 — Budi Santoso — Rp79.000 — Pembayaran
> AL-2026-000124 — Siti Rahmawati — Rp25.000 — Pembayaran
> AL-2026-000125 — Dewi Anggraini — Pesanan baru
>
> Antrean ini milik tenant saat ini dan tidak ikut berpindah. Antrean tidak dihapus.

Options: *Sinkronkan sekarang*, *Batal*. Switching without draining requires a permissioned, audited
override with a recorded reason — it is not a third button of equal weight.

### 4.3 What never happens

| Forbidden | Why |
|---|---|
| A silent switch triggered by a deep link | The link states its tenant and asks |
| A silent switch triggered by a notification tap | Same |
| A switch because the current tenant became unavailable | The user chooses where to go |
| A switch as a side effect of background refresh or session renewal | Context is never a side effect |
| Inferring tenant from a client-supplied hint | **A client-supplied tenant ID is never authorization proof** |
| Previous tenant data visible after the switch | Cache is partitioned per tenant and per user and is discarded on switch |

---

## 5. Switching outlet — the experience

Simpler, same discipline. The picker lists outlets available to the membership with their brand.
Selecting one shows a brief confirmation naming the outlet, applies the same unsynced-work guard, and
re-scopes.

A membership scoped to one outlet shows no picker — but still shows the outlet in the bar. An implicit
blank is never acceptable; a user must always be able to read where they are.

---

## 6. Portfolio Mode versus operational mode — the experience

| | Portfolio Mode | Tenant Mode | Outlet Mode |
|---|---|---|---|
| Badge | `MODE PORTOFOLIO` | `MODE TENANT` | `MODE OUTLET` |
| App bar | Dark blue | White with soft-blue accent | Soft blue |
| Breadcrumb | Tenant-agnostic | Tenant | Tenant › Brand › Outlet |
| What you see | Aggregates: revenue, order counts, aging distribution, receivable totals — per tenant, side by side | Orders, customers, payments, configuration for one tenant | The same, narrowed to one outlet |
| What you cannot see | **Any individual customer record** | Any other tenant | Any other outlet without leaving the mode |
| Writes | **None** | Subject to role and server-side authorisation | Same |

Three reinforcements so the modes are never confused:

1. **Colour, badge text, and breadcrumb together** — a colour-blind user reads the badge; a user with
   a monochrome screen reads the breadcrumb.
2. Entering a tenant from Portfolio Mode is an explicit act with a confirmation naming the tenant.
3. Portfolio Mode has no create, edit, or delete affordance anywhere. Its absence is structural, not
   a disabled state.

**Aggregation is not merging.** Two tenants with identical owner details remain two data sets.
Nothing is deduplicated or cross-referenced because a name, email, or phone matches.

---

## 7. Context failure states — the copy

| State | Copy (Bahasa Indonesia) | Primary recovery action |
|---|---|---|
| `UXS-013` Tenant Unavailable | "Laundry Melati Wangi tidak dapat dibuka saat ini. Data bisnis dan hak ekspornya tetap tersimpan." | *Pilih tenant lain* |
| Membership revoked | "Akses Anda ke Laundry Melati Wangi telah berakhir." | *Lihat tenant lain* / *Serahkan pekerjaan belum tersinkron* |
| `UXS-014` Outlet Inactive | "Outlet Cempaka tidak aktif. Pesanan baru tidak dapat dibuat. Pekerjaan yang sudah tercatat masih bisa diselesaikan." | *Pilih outlet lain* |
| `UXS-015` Subscription Limited | "Batas paket Starter tercapai untuk outlet. Volume order Starter bersifat fair-use." | *Hubungi pemilik tenant* |
| Switching (transitional) | "Berpindah ke Laundry Melati Wangi…" | *Batal* |
| Tenant loading failure | "Konteks tenant belum dapat dipastikan. Tidak ada data yang ditampilkan sampai konteks terkonfirmasi." | *Coba lagi* / *Keluar* |

The last one is the important one: on any doubt about context, the interface **fails closed** and
shows nothing rather than falling back to a cached context and proceeding as though it were
confirmed.

---

## 8. Related documents

- [`./information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md`](./information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md)
- [`./information-architecture/ROLE_NAVIGATION_MATRIX.md`](./information-architecture/ROLE_NAVIGATION_MATRIX.md)
- [`./OFFLINE_AND_SYNC_UX.md`](./OFFLINE_AND_SYNC_UX.md)
- [`./CONSOLE_WEB_UX.md`](./CONSOLE_WEB_UX.md)
- [`./UX_STATE_MODEL.md`](./UX_STATE_MODEL.md)

## 9. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Context bar and switcher | **NOT IMPLEMENTED** |
| Tenant isolation enforcement | **NOT IMPLEMENTED** (delivered in Step 3) |
| Backend runtime | **ABSENT** |

`GO` is conferred by the repository owner and is never self-declared.
