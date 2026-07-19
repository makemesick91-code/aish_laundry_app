# Rule 05 — Flutter Client Foundation, Design, and UX

## Purpose

To fix the client-side technology and experience foundation for Aish Laundry App so that later steps
build a single coherent product rather than three unrelated apps.

Backed by **DEC-0004 (Flutter Client and Web Console)** and **DEC-0006 (Public Tracking Without App
Installation)**.

## Locked technology

- **Frontend framework: Flutter + Dart.** Locked. Changing it requires a new ADR in a later step.
- The repository is a **monorepo**; client code will live under `apps/` when it is created in later
  steps.

## The three Flutter surfaces

1. **Aish Laundry Customer Android** — Flutter.
   Phone + OTP login; active orders; order history; tracking; pickup request; addresses; invoices;
   loyalty; feedback; notifications.
2. **Aish Laundry Ops Android** — Flutter.
   Roles: kasir, manager outlet, operator produksi, quality control, kurir, laundry admin.
   This is the offline-first surface (see Rule 07).
3. **Aish Laundry Console Web** — Flutter Web.
   Roles: owner, tenant admin, manager, finance, platform admin.

Plus a fourth, deliberately non-Flutter-mandated surface:

4. **Portal Tracking Publik** — browser-based, **no app install required**. Flutter is *not*
   mandatory here; a lighter web stack is permitted if it performs better. This portal is a
   differentiator and must never be degraded into "install the app first".

**The Customer Android app does not replace the public tracking portal** (DEC-0014). Both exist.

## Design foundation (visual)

- Palette: **white; soft blue; dark blue; restrained gold accent**.
- Tone: **professional, light, not futuristic**, relevant to Indonesian UMKM.
- The gold accent is restrained — an accent, not a theme.
- Avoid heavy animation. Motion serves comprehension, never decoration.

## UX foundation

1. **Shortest possible primary actions.** The counter is busy; the common path must be the fastest
   path.
2. **Status is never conveyed by colour alone.** Every status carries text and/or an icon in addition
   to colour.
3. **Destructive actions are visually and spatially separated** from routine actions.
4. **Errors explain recovery steps** — what happened, and what the user should do next. An error code
   alone is not an acceptable error message.
5. **Offline and sync state are visible** to the user at all times in the Ops app (see Rule 07).
6. **Accessibility and device font scaling are supported.** Layouts must survive large system font
   sizes without truncating critical information; tap targets must be adequately sized; contrast must
   meet accessible ratios.
7. **Couriers get a simple interface** — large targets, minimal steps, usable one-handed, on a phone,
   outdoors, in a hurry.

## Language and locale

- Primary language: **Bahasa Indonesia**. UI copy is written in Bahasa Indonesia.
- Currency: **Rupiah**, formatted for Indonesian conventions, backed by integer values (Rule 04).
- Timezone: **Asia/Jakarta** for business-day logic; outlet local time governs quiet hours (Rule 08).

## Step 0 note

**Flutter workspace: ABSENT.** No Flutter or Dart project exists. In Step 0 it is forbidden to run
`flutter create` or `dart create`, or to create `pubspec.yaml`. `apps/` contains only `README` or
`.gitkeep` files. The design system itself is built in Step 2; client experience work lands in
Steps 11 and beyond.

## Violation handling

- **Any Flutter/Dart scaffolding or `pubspec.yaml` created during Step 0** — remove it and report the
  scope breach. It is a Step 0 scope-guard violation.
- **Claiming a screen, app, or design system exists** when only a folder or README exists —
  correct the claim immediately (Rule 01).
- **Status conveyed by colour alone, or an unrecoverable error message** — treat as a Definition of
  Done failure for the step that introduced it, not a cosmetic backlog item.
- **A change that makes public tracking require an app install** — reject; it contradicts DEC-0006
  and DEC-0014 and requires an owner decision record to even consider.
- **Replacing Flutter with another framework** without a new accepted ADR — reject and escalate.
