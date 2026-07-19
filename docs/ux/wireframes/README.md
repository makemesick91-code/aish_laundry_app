# Wireframes — Step 2 Design System and UX Foundation

## Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Wireframes in this directory | **NOT IMPLEMENTED** |
| Flutter workspace | **ABSENT** |
| Backend runtime | **ABSENT** |
| Application CI | **NOT APPLICABLE** |

These wireframes are **NOT IMPLEMENTED**. They are low-fidelity layout sketches, **not final UI**, not a
design system, not a screen, not a build, and not a test. A drawn box is a documented intention, never an
achievement. No wireframe in this directory has been rendered by any application, because no application
exists.

`GO` is conferred by the repository owner and is never written here.

## What a wireframe is and is not

- **Is**: a low-fidelity indication of layout order, grouping, primary action placement, status
  presentation, and the safety-relevant copy that must be present on a surface.
- **Is not**: a visual design, a colour specification, a component contract, a spacing token, an
  accessibility audit result, or a promise that any of the depicted behaviour exists.

Values shown as example data are **fictional** and recognisably so.

## Naming convention

```
<platform>-<SCREEN-ID>-<short-slug>.svg
```

- `<platform>` is one of `customer-android`, `ops-android`, `console-web`, `tracking-portal`.
- `<SCREEN-ID>` is the stable screen identifier from [`../SCREEN_INVENTORY.md`](../SCREEN_INVENTORY.md)
  (`SCR-CUS-###`, `SCR-OPS-###`, `SCR-CON-###`, `SCR-TRK-###`).
- `<short-slug>` is a lowercase hyphenated description of the screen.

Screen IDs are stable and are never reused for a different screen. A wireframe filename is changed only
when its screen ID changes in the inventory.

## Counts by platform

| Platform | Wireframes |
|---|---|
| Aish Laundry Customer Android | 10 |
| Aish Laundry Ops Android | 12 |
| Aish Laundry Console Web | 6 |
| Portal Tracking Publik | 4 |
| **Total** | **32** |

## Index

| Wireframe file | Screen ID | Platform | What it shows |
|---|---|---|---|
| `customer-android-SCR-CUS-002-phone-entry-otp.svg` | SCR-CUS-002 | Customer Android | Phone entry and OTP verification, code validity, resend backoff, attempt limiting, recovery copy |
| `customer-android-SCR-CUS-004-home.svg` | SCR-CUS-004 | Customer Android | Customer home: active order card, quick actions, running charge, delivery window, latest notification |
| `customer-android-SCR-CUS-005-active-orders.svg` | SCR-CUS-005 | Customer Android | Active order list with canonical status chips (WASHING, READY_FOR_PICKUP, OUT_FOR_DELIVERY, QUALITY_CONTROL, ISSUE) |
| `customer-android-SCR-CUS-006-order-detail.svg` | SCR-CUS-006 | Customer Android | Order detail: service, weight, locked price, private condition evidence, payment state |
| `customer-android-SCR-CUS-007-order-timeline.svg` | SCR-CUS-007 | Customer Android | Order timeline across canonical statuses, with the immutable first-ready note |
| `customer-android-SCR-CUS-008-payment-summary.svg` | SCR-CUS-008 | Customer Android | Payment summary in integer Rupiah, historical price immutability, reversal-only corrections |
| `customer-android-SCR-CUS-009-pickup-request.svg` | SCR-CUS-009 | Customer Android | Pickup request: outlet, masked address line, time window, estimate, REQUESTED state |
| `customer-android-SCR-CUS-011-delivery-schedule.svg` | SCR-CUS-011 | Customer Android | Delivery schedule, cash on delivery, proof methods, failed-delivery handling |
| `customer-android-SCR-CUS-012-order-history.svg` | SCR-CUS-012 | Customer Android | Order history with COMPLETED and CANCELLED entries and tenant-scoped filtering |
| `customer-android-SCR-CUS-016-notifications.svg` | SCR-CUS-016 | Customer Android | Notification list including the H+1 / H+3 / H+7 / H+14 ladder, quiet hours, opt-out preference |
| `ops-android-SCR-OPS-004-tenant-selection.svg` | SCR-OPS-004 | Ops Android | Tenant and membership selection, cache clearing on tenant switch, queue retained per tenant |
| `ops-android-SCR-OPS-006-home.svg` | SCR-OPS-006 | Ops Android | Ops home with context bar, sync chip, production queue, ready count, unclaimed buckets |
| `ops-android-SCR-OPS-007-pos-new-order.svg` | SCR-OPS-007 | Ops Android | POS new order: customer, service, estimated weight, subtotal, `client_reference`, DRAFT state |
| `ops-android-SCR-OPS-011-weight-input.svg` | SCR-OPS-011 | Ops Android | Weight capture with large targets, audited corrections, server-side total calculation |
| `ops-android-SCR-OPS-013-condition-evidence.svg` | SCR-OPS-013 | Ops Android | Condition evidence capture, private file handling, upload queue, RESTRICTED classification |
| `ops-android-SCR-OPS-016-payment.svg` | SCR-OPS-016 | Ops Android | Payment capture in integer Rupiah with the offline "BELUM DIKONFIRMASI SERVER" state and idempotency reference |
| `ops-android-SCR-OPS-020-offline-queue.svg` | SCR-OPS-020 | Ops Android | Offline queue items with `client_reference` labels, per-item state text, ordering and deletion rules |
| `ops-android-SCR-OPS-021-sync-conflict.svg` | SCR-OPS-021 | Ops Android | Sync conflict showing server value versus local value and two explicit resolution buttons, no automatic overwrite |
| `ops-android-SCR-OPS-022-production-queue.svg` | SCR-OPS-022 | Ops Android | Production queue by canonical status with enumerated-transition note |
| `ops-android-SCR-OPS-026-quality-control.svg` | SCR-OPS-026 | Ops Android | Quality control outcomes PENDING / PASSED / FAILED_REWORK_REQUIRED / WAIVED_WITH_AUTHORIZATION |
| `ops-android-SCR-OPS-030-delivery-job.svg` | SCR-OPS-030 | Ops Android | Courier delivery job: masked destination, suggestion-only stop order, cash collected, mandatory proof |
| `ops-android-SCR-OPS-031-proof-capture.svg` | SCR-OPS-031 | Ops Android | Proof capture with OTP, Foto, Tanda Tangan, Nama Penerima, cash recording, offline reference |
| `console-web-SCR-CON-001-portfolio-dashboard.svg` | SCR-CON-001 | Console Web | Owner portfolio dashboard with the MODE PORTOFOLIO badge and tenant-scoped aggregation note |
| `console-web-SCR-CON-003-outlet-dashboard.svg` | SCR-CON-003 | Console Web | Outlet dashboard with the MODE OUTLET badge, order table, canonical statuses, outstanding balance |
| `console-web-SCR-CON-011-pickup-delivery-planning.svg` | SCR-CON-011 | Console Web | Pickup and delivery planning board, zones, time windows, courier assignment, guest-link note |
| `console-web-SCR-CON-013-route-ordering.svg` | SCR-CON-013 | Console Web | Ordered courier stop list labelled USULAN RUTE — BUKAN RUTE OPTIMAL, with no arrival-time guarantee |
| `console-web-SCR-CON-014-unclaimed-laundry.svg` | SCR-CON-014 | Console Web | Unclaimed laundry by H+1 / H+3 / H+7 / H+14 with order count, customer count, outstanding balance, reminder state, follow-up owner |
| `console-web-SCR-CON-017-courier-settlement.svg` | SCR-CON-017 | Console Web | Courier cash settlement with expected versus actual and an explicitly recorded variance |
| `tracking-portal-SCR-TRK-001-valid-tracking.svg` | SCR-TRK-001 | Tracking Portal | Valid tracking view: masked name, order number, status chip, timeline, Hubungi Outlet |
| `tracking-portal-SCR-TRK-003-ready-status.svg` | SCR-TRK-003 | Tracking Portal | Ready-for-pickup tracking view with first-ready note and reminder ladder mention |
| `tracking-portal-SCR-TRK-005-expired-token.svg` | SCR-TRK-005 | Tracking Portal | Expired tracking token with the recovery action "Minta tautan baru" and no order data disclosure |
| `tracking-portal-SCR-TRK-008-rate-limited.svg` | SCR-TRK-008 | Tracking Portal | Rate-limited tracking lookup with the recovery action "Coba lagi dalam 15 menit" and enumeration protection |

## Canvas sizes

| Surface | viewBox |
|---|---|
| Customer Android, Ops Android | `0 0 360 720` |
| Console Web | `0 0 1440 900` |
| Portal Tracking Publik | `0 0 390 780` |

## SVG authoring constraints

Every file in this directory must satisfy all of the following:

1. Valid XML whose first line is exactly `<?xml version="1.0" encoding="UTF-8"?>`.
2. A root `<svg>` element carrying `xmlns`, a mandatory `viewBox`, and matching `width` and `height`.
3. **No `<script>`**, no `<foreignObject>`, no `<image>`, and no `href` or `xlink:href` of any kind.
4. **No external references** of any kind — no `@import`, no remote stylesheet, no remote font. The only
   permitted URL anywhere in a file is the required `xmlns="http://www.w3.org/2000/svg"` value.
5. Fonts are declared as `font-family="sans-serif"` only.
6. Only plain shapes (`<rect>`, `<line>`, `<circle>`, `<path>`, `<polyline>`) and `<text>`; inline `style`
   or presentation attributes only, with no `<style>` element.
7. `&`, `<`, and `>` are escaped or avoided in text content.
8. Every file carries visible `<text>` showing the screen ID, the platform name, and the exact literal
   string `LOW-FIDELITY — NOT IMPLEMENTED`.
9. **No PII.** Every example datum is fictional and recognisably so: `Budi Santoso`, `Siti Rahmawati`,
   the masked `Budi S.`, phone `0812-XXXX-1234`, order `AL-2026-000123`, `Outlet Cempaka`,
   `Laundry Bersih Sejahtera`, money `Rp79.000` / `Rp25.000`, weight `1,5 kg`, time `14:30`.
10. No customer address is ever drawn in full; the destination line reads
    `Jl. Melati (detail dibuka saat tiba)`.

## Safety-relevant content rules reflected in these wireframes

- **Ops Android** carries a persistent context bar (`Laundry Bersih Sejahtera / Outlet Cempaka / Kasir`)
  and a sync chip (`TERSINKRON`, `MENUNGGU SINKRON 3`, `GAGAL SINKRON`).
- **Offline payment** is drawn as `BELUM DIKONFIRMASI SERVER`; amounts are integer Rupiah.
- **Sync conflict** shows the server value beside the local value with two explicit resolution buttons.
  No automatic overwrite is depicted anywhere.
- **Route ordering** is labelled `USULAN RUTE — BUKAN RUTE OPTIMAL` and depicts no guaranteed arrival
  time.
- **Unclaimed laundry** depicts reminding, escalating, and reporting only. No disposal, sale, auction,
  donation, or ownership transfer action appears in any wireframe.
- **Tracking portal** shows a masked name, no full address, and no full phone number, and every failure
  state offers a recovery action.
- Order statuses drawn are taken only from the canonical set: `DRAFT`, `RECEIVED`, `AWAITING_PROCESS`,
  `SORTING`, `WASHING`, `DRYING`, `FINISHING`, `QUALITY_CONTROL`, `REWORK`, `READY_FOR_PICKUP`,
  `SCHEDULED_FOR_DELIVERY`, `OUT_FOR_DELIVERY`, `COMPLETED`, `CANCELLED`, `ISSUE`.

## Accessibility

DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED

Status is never conveyed by colour alone in these wireframes; every status is drawn as text. Contrast,
font scaling, focus order, and tap-target sizing are design intentions recorded here, not measured
results. No accessibility test has been executed, because no runtime exists.

## Related documents

- [`../SCREEN_INVENTORY.md`](../SCREEN_INVENTORY.md)
- [`../UX_STATE_MODEL.md`](../UX_STATE_MODEL.md)
