# Platform Adaptation — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Derived from:** Master Source §5 (Platforms), Rule 05
**Scope:** DOCUMENTATION ONLY. No surface exists. Flutter workspace is ABSENT.

---

## 1. One design language, four adaptations

All four surfaces consume the **same tokens, the same components, and the same content rules**. What
differs is navigation, density, input model, and what each surface is permitted to display.

### The two rules that matter most

> **Never enlarge a mobile layout into a desktop layout.**
> A phone screen scaled up produces enormous touch targets, a single wasteful column, and a navigation
> model that ignores everything a pointer and keyboard make possible. Console Web is designed as a
> desktop application, not as a stretched phone app.

> **Never shrink a desktop table into an Android UI.**
> A data table compressed onto a phone produces unreadable columns, horizontal scroll, and truncated
> money values. Android surfaces use the stacked-card pattern
> (`RESPONSIVE_FOUNDATION.md` §5 Pattern C), which is a **different component**, not a smaller table.

Both failures come from the same mistake: treating a layout as something to resize rather than
something to re-express.

### What is shared, always

| Shared | Never varies by surface |
|---|---|
| Colour semantics | `color.semantic.danger` means danger everywhere |
| Status labels | "Siap Diambil" is identical on all four surfaces |
| Status icons and colours | Identical mapping (`ICONOGRAPHY.md`) |
| Money formatting | `Rp79.000` everywhere |
| Date, time, weight, quantity formatting | Identical (`TYPOGRAPHY.md` §6) |
| Error message pattern | What failed + what to do next |
| Accessibility baseline | WCAG 2.2 AA target on every surface |
| Voice and tone | `CONTENT_DESIGN.md` |
| Privacy masking rules | Per context, per `COLOR_AND_CONTRAST.md` and Master Source §17 |

### What adapts

Navigation model, density, input model, elevation budget, motion budget, table strategy, and the
permitted data set.

---

## 2. Aish Laundry Customer Android

**Stack:** Flutter · **Users:** laundry customers · **Density:** standard

### Navigation

- **Bottom navigation**, 4 destinations: Beranda, Pesanan, Jemput, Akun.
- Bottom navigation persists across top-level destinations and never appears on a detail screen.
- **Hardware back** always works: it pops the navigation stack, closes a sheet, dismisses a dialog, and
  on a top-level destination returns to Beranda before exiting. It is never intercepted to mean
  something custom.
- **Deep links** from a WhatsApp message open the relevant order directly, with a synthesised back stack
  so back leads to Beranda rather than out of the app.

### Layout

- Single column. Primary action in a bottom action bar, within thumb reach.
- **One-hand reach:** primary and frequent actions occupy the lower 40% of the screen. Destructive and
  rare actions sit in the app bar overflow, deliberately out of easy reach — the awkwardness is the
  safety mechanism.
- **Bottom sheets** are the default for supplementary input: choosing an address, picking a time window,
  filtering. They preserve context and stay in thumb reach.
- **Dialogs** are reserved for confirmations and blocking decisions.

### Surface-specific rules

1. Gold accent is permitted here, within the budget in `BRAND_FOUNDATION.md` §3 — loyalty tier and
   milestone markers only.
2. Marketing content never appears inside an operational flow (order detail, payment,
   tracking). `DESIGN_PRINCIPLES.md` P11.
3. Notification preferences are reachable in at most two taps from Akun, and marketing opt-out is a
   single switch that takes effect immediately.
4. The app **does not replace the public tracking portal** (DEC-0014). A tracking link opened by a
   customer who has the app may deep-link into it, but the portal remains fully functional for everyone
   else.
5. Laundry photographs, where shown to the owning customer, load only through signed expiring URLs and
   are never cached to shared device storage.

### Prohibited

- Data tables of any kind.
- Compact density.
- Blocking the tracking flow behind login.
- A rating or review prompt inside a payment or issue flow.

---

## 3. Aish Laundry Ops Android

**Stack:** Flutter · **Users:** kasir, manager outlet, operator produksi, quality control, kurir,
laundry admin · **Density:** standard, **comfortable** for courier, payment, refund, and shift-closing
surfaces

This is the **offline-first** surface (Rule 07) and the hardest environment in the product: bright
light, wet hands, time pressure, cheap hardware, patchy data.

### Navigation

- **Bottom navigation** with destinations that vary by role — a kasir does not see the courier
  destination.
- **Navigation rail** (`size.navrail.width`, 80 dp) at `breakpoint.medium` and above, for counter
  tablets.
- **Hardware back** never discards unsaved work silently. In a form with changes it opens the
  unsaved-changes confirmation (`FORM_AND_VALIDATION_PATTERNS.md` §11).
- **Tenant switcher** in the app bar wherever the user belongs to more than one tenant (Rule 02,
  hard rule 5). Switching tenants **clears all cached tenant data** and returns to the top-level
  destination — it never carries a previous tenant's data into a new context (Rule 07, rule 7).
- **Outlet selector** beneath the tenant switcher where the user has access to multiple outlets.

### Offline and sync presentation

Offline state is visible **at all times**, not only when something fails (Master Source §18.2 rule 5).

| Element | Behaviour |
|---|---|
| Offline banner | Persistent when offline. States the mode and the queue depth: "Mode offline — 3 transaksi menunggu dikirim" |
| Sync indicator | Always present in the app bar; shows the current sync state from the `SYNC_*` set |
| Per-item sync chip | Every order, payment, and proof carries its own sync state chip |
| Conflict panel | Reachable in one tap from the sync indicator; lists every `SYNC_CONFLICT` item |
| Queue view | Always reachable; shows pending, failed, and conflicted operations with their age |

**A financial operation is never removed from the queue by an ordinary UI action** (Rule 07, rule 4).
No "clear queue" button exists for ordinary roles. Removal requires an explicit, permissioned, audited
action, and its confirmation dialog names the amount and the customer.

### Courier surfaces

Rule 09, rule 8. Couriers get the simplest interface in the product.

- **Comfortable density**, always.
- **Minimum 56 dp targets; 64 dp for proof capture.**
- **One job at a time.** The job list shows a suggested visit order; the job screen shows exactly one
  stop.
- **Route language is always "usulan"** — "Usulan urutan kunjungan". Never "optimal", never a
  guaranteed arrival time (Rule 09, rule 1).
- **Proof capture** — OTP, photo, signature, recipient name — is the primary action on the job screen,
  reachable one-handed, and works fully offline with a persisted `client_reference`.
- **Cash collection** shows the expected amount, the amount collected, and the running total for the
  shift. Variance is always visible, never absorbed.
- Copy is short and imperative: "Ambil foto bukti", "Minta tanda tangan".
- **High-contrast boundaries** — `color.semantic.border.strong` (6.80:1) rather than the interactive
  default — because soft borders and shadows vanish in sunlight.

### Prohibited

- Compact density anywhere.
- Horizontally scrolling data tables — stacked cards only.
- A full-screen blocking error on connectivity loss.
- Hiding queued or failed operations.
- Silent conflict resolution on a payment.
- Any cached data surviving a tenant or user switch.
- A refund control adjacent to a print control (`DESIGN_PRINCIPLES.md` P5).

---

## 4. Aish Laundry Console Web

**Stack:** Flutter Web · **Users:** owner, tenant admin, manager, finance, platform admin
**Density:** standard, **compact** in data regions, **comfortable** for financial actions
**Baseline:** 1366 × 768 (`RESPONSIVE_FOUNDATION.md` §4)

### Navigation

- **Persistent side navigation** (`size.sidenav.width`, 264 dp), collapsible to 72 dp. Grouped by
  domain: Operasi, Keuangan, Pelanggan, Master Data, Laporan, Pengaturan.
- **Top bar** (64 dp) carrying the wordmark, the **tenant switcher**, the outlet selector, global
  search, and the account menu.
- **Breadcrumb** below the top bar on any view more than two levels deep.
- **Browser back and forward work correctly.** Every meaningful view has a URL; a filtered table state
  is reflected in the URL so it can be bookmarked and shared within the tenant.
- **Tenant switcher** is always visible and always names the current tenant. Switching reloads the
  workspace and clears client-side tenant data. The current tenant is never ambiguous — an owner
  managing three tenants must never wonder which one they are editing.

### Keyboard

Console Web is a **keyboard-first** application for finance and admin users.

| Requirement | Detail |
|---|---|
| Full keyboard operability | Every action reachable without a pointer (WCAG 2.2 SC 2.1.1) |
| Visible focus | Always (`SHAPE_BORDER_ELEVATION.md` §4) |
| Logical tab order | Follows visual order; no positive `tabindex` equivalents |
| Skip link | "Lewati ke konten utama" as the first focusable element |
| Table navigation | Arrow keys within a grid; Home/End for row extremes (`ACCESSIBILITY.md` §9) |
| Shortcuts | Optional, documented, never single-character without a modifier, always remappable or disableable (WCAG 2.2 SC 2.1.4) |
| Escape | Closes the topmost dismissible layer only (`ACCESSIBILITY.md` §11) |
| Enter in a form | Submits the primary action, unless focus is in a multi-line field |

### Data tables

The Console is where tables belong.

- Column priority defined per table (`RESPONSIVE_FOUNDATION.md` §5 Pattern A).
- Sorting on every meaningful column, with the sort state announced.
- Filtering via a filter bar that collapses to a filter button when vertical space is tight.
- Pagination or virtualised scrolling, with the total count always stated — "Menampilkan 1–25 dari 340".
- Row selection with a bulk action bar that appears only when a selection exists and states the count.
- **Bulk destructive and bulk financial actions are prohibited.** A bulk refund does not exist. Bulk
  operations are limited to non-destructive, non-financial actions such as exporting or assigning a
  follow-up officer.
- Every export carries the same access rules as the underlying records and is tenant-scoped
  (Rule 03).

### Prohibited

- A layout that is a scaled-up phone screen.
- Bottom navigation.
- Horizontal page scroll on a primary workflow.
- A dialog taller than 560 px at the baseline resolution.
- Any view where the current tenant is not identifiable.
- Compact density on a refund, void, subscription change, or shift-closing surface.
- Bulk financial or bulk destructive operations.

---

## 5. Portal Tracking Publik

**Stack:** browser-based; Flutter **not** mandatory (Master Source §5.4)
**Users:** anyone holding a tracking link · **Density:** comfortable in the status region
**Baseline:** 320 px viewport (`RESPONSIVE_FOUNDATION.md` §3)

The most exposed surface in the product and the most constrained.

### Navigation

- **No navigation.** The portal is a single page answering a single question. There is no menu, no
  sign-in, no tabs, no other-orders list.
- **No app install requirement, ever** (DEC-0006, DEC-0014). An install suggestion may exist only as a
  dismissible element below the status content, never as an interstitial, a modal, or a blocker.

### Content

Permitted (Master Source §9.3): order number; brand and outlet identity; service type; current status
and status history; estimated completion; amount due and payment state; available customer actions.

Never shown without OTP verification: full address, full phone number, other orders belonging to the
same customer, internal notes, staff identity beyond operational necessity, and laundry photographs.

**Never shown at all:** the full address, in any circumstance (Master Source §9.2 rule 8). OTP unlocks
a delivery-address *change* flow, not a full-address *display*.

### Layout

- Single column at every viewport. Content capped at `size.maxWidth.reading` (640 dp) on wide screens and
  centred.
- Vertical status timeline, never a horizontal stepper.
- The status, its label, and the amount due are above the fold at 320 px.
- Payment state is stated in words: "Belum Lunas — Sisa Rp45.000".

### Security and privacy presentation

- The page is served `noindex` (Master Source §9.2 rule 6).
- The tracking token never appears in the page body, in a heading, in copyable text, or in any
  analytics or error payload.
- Masked values carry a masking indicator and a plain explanation: "Sebagian data disembunyikan untuk
  keamanan."
- An expired or revoked link shows a specific, non-enumerable message that does not confirm whether the
  order exists (`CONTENT_DESIGN.md` §7).
- Sensitive actions — changing a delivery address, requesting a schedule change — require OTP
  (Master Source §9.2 rule 9).

### Performance

- Elevation budget: `elevation.0` and `elevation.1` only.
- Motion budget: fade and spinner only.
- No webfont. System stack only (`TYPOGRAPHY.md` §1).
- The map preview, if present, loads after the status content and never blocks it.

### Prohibited

- Any navigation chrome.
- Any data table.
- Any app-install interstitial or content-obstructing banner.
- A full address, at any width, under any condition.
- Laundry photographs.
- Horizontal scroll.
- Any element that would enumerate orders or confirm the existence of an order for an invalid token.

---

## 6. The external courier guest link

Not a fifth surface — a **scoped view** of the Ops delivery flow, reached by an external ojek courier
through a secure guest link (Rule 09, rules 6 and 7).

Design constraints:

- **Comfortable density, 56 dp minimum targets**, as with any courier surface.
- Shows **only the assigned job**: pickup or delivery point at the precision the job genuinely
  requires, the recipient's masked contact, the parcel reference, and the proof-capture action.
- Shows **no** customer history, no other orders, no pricing, no tenant data beyond the assignment.
- Shows **no full customer address** beyond what the delivery genuinely requires, and never in a
  shareable or indexable form.
- Carries a visible, honest statement of scope and expiry: "Akses ini hanya untuk satu tugas dan akan
  berakhir otomatis."
- Is **tenant-scoped**. A courier working for two tenants gets two unrelated links, and no element of
  either view can traverse to the other.
- Route language is "usulan", never "optimal".

---

## 7. Adaptation summary

| Dimension | Customer Android | Ops Android | Console Web | Tracking Portal |
|---|---|---|---|---|
| Primary navigation | Bottom nav (4) | Bottom nav (role-based) / rail | Side nav (264 dp) | None |
| Density | Standard | Standard / comfortable | Compact / standard / comfortable | Comfortable |
| Input model | Touch | Touch, one-handed | Keyboard + pointer | Touch or pointer |
| Tables | Prohibited | Stacked cards only | Full tables | Prohibited |
| Bottom sheets | Primary supplementary pattern | Primary supplementary pattern | Rare; prefer dialogs/panels | Rare |
| Hardware back | Full support | Full support, guards unsaved work | Browser back | Browser back |
| Tenant switcher | Not applicable | App bar | Top bar, always visible | Not applicable |
| Offline UX | Basic indicators | **Full offline-first UX** | Connection banner only | Simple retry |
| Gold accent | Permitted (loyalty) | Effectively absent | Rare, reporting only | Absent |
| Elevation budget | 0–4 | 0–1 in production/courier views | 0–4 | 0–1 |
| Motion budget | Standard | Standard | Standard | Fade + spinner |
| Baseline viewport | 360 dp | 360 dp | 1366 × 768 | **320 px** |

---

## 8. Cross-surface prohibitions

| Prohibited | Reason |
|---|---|
| A scaled-up mobile layout on Console Web | §1 |
| A shrunk desktop table on any Android surface | §1 |
| A different status label on different surfaces | §1 shared list |
| A different money format on different surfaces | §1 shared list |
| A full address on the tracking portal or a guest-courier link | Master Source §9.2 rule 8, Rule 09 rule 6 |
| Laundry photographs on any public surface | Master Source §17.2 rule 3 |
| An app-install requirement for tracking | DEC-0006, DEC-0014 |
| Cached tenant data surviving a tenant switch | Rule 02, Rule 07 rule 7 |
| A guest link exposing anything beyond its assignment | Rule 09 rule 6 |
| Route optimization language on any surface | Rule 09 rule 1 |
| "Unlimited WhatsApp" on any surface | Rule 08 rule 10, Rule 14 |
| Silent support impersonation without a persistent indicator | Rule 03 rule 19 |
| A bulk refund or bulk destructive action on any surface | §4 |
