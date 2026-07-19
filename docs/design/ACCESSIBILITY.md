# Accessibility — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY

---

## Accessibility statement

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

That sentence is used verbatim wherever the product's accessibility posture is stated. It is not
softened, not shortened, and not upgraded.

What it means precisely:

- The specifications in this design system are **written against** WCAG 2.2 Level AA.
- **No conformance has been tested, measured, audited, or certified**, because no runtime exists.
- Contrast ratios in [`COLOR_AND_CONTRAST.md`](COLOR_AND_CONTRAST.md) are **computed from hex values**,
  not measured on a device.
- The product is **not** WCAG certified, **not** WCAG compliant, and **not** accessibility tested. Any
  claim otherwise is a false claim under Rule 01.

Runtime verification — automated scanning, screen-reader walkthroughs, keyboard traversal, device
testing at 200% scaling — is owed by each Step that builds a surface, and is a Definition-of-Done gate
for those Steps.

---

## 1. Contrast

Governed by [`COLOR_AND_CONTRAST.md`](COLOR_AND_CONTRAST.md). Summary of obligations:

| Content | Minimum |
|---|---|
| Normal text | 4.5:1 |
| Large text (18.66 px bold / 24 px regular and above) | 3:1 |
| Interactive component boundary | 3:1 |
| Focus indicator against adjacent colours | 3:1 |
| Meaningful graphical object | 3:1 |

Prohibited combinations are enumerated in `COLOR_AND_CONTRAST.md` §4. The three that catch people out:
`color.blue.500` is not a text colour (3.87:1), `color.neutral.500` is not a text colour (4.36:1), and
`color.semantic.border` is not an interactive boundary (1.29:1).

Disabled states are a documented, WCAG-permitted exception with compensating obligations
(`COLOR_AND_CONTRAST.md` §6).

---

## 2. Colour independence

**No information is conveyed by colour alone** (WCAG 2.2 SC 1.4.1; Master Source §18.2 rule 2).

Every status carries **text + icon + colour**, in that order of importance. Removing colour must leave
the interface fully usable.

| Context | Non-colour carrier |
|---|---|
| Order status | Indonesian label + distinct icon silhouette |
| Payment state | Label ("Belum Lunas") + amount |
| Sync state | Label + icon |
| Form error | Icon + message text + programmatic association |
| Required field | The word "wajib" or an asterisk with a legend — never colour |
| Chart series | Direct label, pattern, or shape (`DATA_VISUALIZATION.md`) |
| Table row emphasis | A status column, never a coloured row alone |
| Aging band | The age as text ("H+9") |
| Link in prose | Underline, not colour alone |
| Selected item | Checkmark or selected-state icon, not tint alone |

**Verification tests** every design must survive: greyscale rendering; deuteranopia and protanopia
simulation; a low-quality display in bright light.

---

## 3. Focus

- **Every interactive element has a visible focus indicator.** Specification in
  [`SHAPE_BORDER_ELEVATION.md`](SHAPE_BORDER_ELEVATION.md) §4: a 3 dp `color.semantic.focus` ring with a
  2 dp surface offset, measuring 7.86:1 on white.
- **The focus indicator can never be removed.** Not for aesthetics, not per component, not per surface,
  not temporarily. There is no authority in this design system to remove it.
- **Focus is never obscured** by a sticky header, a bottom action bar, a snackbar, or a floating action
  button (WCAG 2.2 SC 2.4.11 Focus Not Obscured). A focused element scrolls into view with sufficient
  margin to clear any overlay.
- **Focus order follows visual order** (SC 1.3.2, SC 2.4.3).
- **Focus is never trapped** except in a modal, where trapping is required (§11).
- **Focus moves predictably** on navigation: to the new view's heading, or to the first interactive
  element. It is never left on a control that no longer exists.
- **Focus returns** when a layer closes — to the control that opened it.
- **A focus change never occurs on input alone** (SC 3.2.1, SC 3.2.2). Typing in a field does not move
  focus; selecting a dropdown value does not submit a form. The one permitted exception is an OTP field
  advancing between digit boxes (§13).

---

## 4. Keyboard

**Everything is operable by keyboard** (SC 2.1.1). This is a Console Web requirement in the strong
sense and a correctness requirement everywhere, because Android supports external keyboards and
switch access.

| Key | Behaviour |
|---|---|
| Tab / Shift+Tab | Move between focusable elements in visual order |
| Enter | Activate a button or link; submit a form from a single-line field |
| Space | Activate a button; toggle a checkbox or switch |
| Arrow keys | Move within a composite: radio group, tabs, menu, data grid, segmented control |
| Home / End | First / last item in a composite or row |
| Page Up / Page Down | Page within a scrollable region |
| Escape | Close the topmost dismissible layer (§11) |

Rules:

1. **No keyboard trap** (SC 2.1.2), except an intentional modal focus trap.
2. **A skip link** ("Lewati ke konten utama") is the first focusable element on Console Web and the
   tracking portal.
3. **Composite widgets have a single tab stop.** A data grid is one Tab stop; arrow keys move inside it.
4. **Character-key shortcuts** are avoided; where present they require a modifier and are remappable or
   disableable (SC 2.1.4).
5. **Drag-only interactions are prohibited.** Every drag has a keyboard and single-pointer equivalent
   (SC 2.5.7 Dragging Movements). Reordering a courier's suggested stop order must be possible with
   "Pindah ke atas" / "Pindah ke bawah" controls.
6. **Custom controls expose correct roles, names, and states.** A `div` that behaves like a button but
   does not announce as one is a defect.

---

## 5. Structure, headings, and reading order

- **One `h1` per view**, naming the view. Levels descend without skipping.
- **Headings are never truncated** (`TYPOGRAPHY.md` §3.2) — they are the primary screen-reader
  navigation mechanism.
- **Visual order matches DOM/semantic order** (SC 1.3.2). A control positioned visually before another
  is also before it in the reading order.
- **Landmark regions** are declared: banner, navigation, main, complementary, contentinfo. Each view has
  exactly one main.
- **Lists are marked as lists**, tables as tables, with a caption or accessible name.
- **A visual grouping is a semantic grouping.** A boxed set of fields is a fieldset with a legend, not a
  styled container.
- **Page title** identifies the view and the context: "Pesanan LDY-2026-000481 — Aish Laundry App".

---

## 6. Screen reader

Target technologies: **TalkBack** (Android) and **NVDA / VoiceOver** (Web).

| Element | Requirement |
|---|---|
| Icon-only button | Accessible name in Indonesian describing the action ("Hapus lampiran"), never the glyph name |
| Decorative icon | Hidden from assistive technology |
| Status badge | Announced as its label, never as its colour |
| Money value | Announced completely; `Rp1.240.000` is announced as a full amount, never as digits with truncation |
| Image | Alternative text, or marked decorative |
| Proof photograph | Alternative text stating what it is and when it was captured — never the customer's personal details |
| Chart | Text alternative and an accessible data table (`DATA_VISUALIZATION.md`) |
| Loading state | Announced politely: "Memuat" |
| Progress | Value announced at meaningful intervals, not on every tick |
| Empty state | The explanation is read, not skipped |
| Masked value | Announced as masked: "Nomor telepon sebagian disembunyikan" |

Rules:

1. **The accessible name matches the visible label.** If a button reads "Simpan", its accessible name
   begins with "Simpan" (SC 2.5.3 Label in Name), so voice control works.
2. **State is announced, not implied.** Selected, expanded, checked, disabled, invalid, busy, current.
3. **Nothing important is announced only visually**, and nothing important is announced only aurally.
4. **No raw identifiers are announced.** `READY_FOR_PICKUP` is never spoken; "Siap Diambil" is
   (`DESIGN_PRINCIPLES.md` P7).
5. **No token, OTP, or credential is ever placed in an accessible name, label, or live region**
   (Rule 03).

---

## 7. Status announcements

Live regions are how a screen-reader user learns something changed without moving focus.

| Change | Politeness | Announcement |
|---|---|---|
| Form validation error on submit | assertive | Error count and the first error, plus focus moved to it |
| Inline field error on blur | polite | The field name and the error |
| Save succeeded | polite | "Perubahan tersimpan" |
| Order status changed | polite | The new label |
| Payment recorded | polite | The amount and the resulting state |
| Connectivity lost | polite | "Mode offline. Perubahan akan dikirim saat koneksi kembali." |
| Sync completed | polite | "Sinkronisasi selesai" |
| **Sync conflict detected** | **assertive** | "Ada data yang perlu diperiksa" — it needs a human decision |
| Search results updated | polite | The result count |
| Bulk selection changed | polite | The selected count |
| Session about to expire | assertive | §12 |
| Support impersonation active | assertive, on entry | The tenant name and that the session is audited |

Rules:

1. **Assertive is reserved** for conditions that need immediate attention: errors, conflicts, expiry
   warnings, security context. Overusing it makes the product unusable with a screen reader.
2. **A live region exists before the content changes.** Injecting the region and the message at once is
   not reliably announced.
3. **Announcements are complete sentences in Indonesian**, not fragments.
4. **No announcement contains a token, an OTP, a credential, or an unmasked personal identifier.**

---

## 8. Forms

Detail in [`FORM_AND_VALIDATION_PATTERNS.md`](FORM_AND_VALIDATION_PATTERNS.md). Accessibility
obligations:

1. **Every field has a persistent visible label.** A placeholder is never a label — it disappears on
   input and typically fails contrast.
2. **The label is programmatically associated** with its control.
3. **Helper text and error text are programmatically associated** with the field, so they are announced
   when the field receives focus.
4. **Errors are identified in text** (SC 3.3.1), describe the problem, and state the recovery (SC 3.3.3).
5. **Required fields are indicated in text**, not by colour, and the convention is explained once per
   form.
6. **Related fields are grouped** in a fieldset with a legend — a time window's start and end, an
   address's parts.
7. **Autocomplete hints** are provided for personal data fields (SC 1.3.5): name, phone, address.
8. **Input purpose is signalled by keyboard type** — numeric for weight and quantity, telephone for
   phone, numeric for OTP.
9. **Error summary on submit** lists every error, each linking to its field, and receives focus.
10. **No timeout destroys entered data** (§12).
11. **Financial and consequential submissions are reversible, confirmed, or checked** (SC 3.3.4). A
    refund is confirmed; a payment is checked before submission; a cancellation is confirmed.
12. **Accessible authentication** (SC 3.3.8): no cognitive-function test. OTP is supported by paste and
    by platform autofill; there is no puzzle, no arithmetic, and no memory challenge.

---

## 9. Tables

1. Column headers are marked as headers and associated with their cells.
2. Every table has an accessible name describing its content.
3. **One tab stop for the grid**; arrow keys move between cells, Home/End to row extremes.
4. Sortable column headers announce their sort state, and a sort change is announced politely with the
   new order and the result count.
5. **A row is never identified by colour alone** — the status column carries the meaning.
6. Numeric columns are right-aligned with tabular figures; screen-reader output is unaffected by
   alignment but the visual scan depends on it.
7. When a table adopts a responsive pattern (`RESPONSIVE_FOUNDATION.md` §5), the accessible structure
   changes with it: stacked cards are a list, and each card's labels are real labels.
8. A horizontally scrollable table region is keyboard-scrollable and exposed as a named scrollable
   region.
9. Pagination announces the current range and the total: "Menampilkan 1–25 dari 340".

---

## 10. Touch targets and pointer

- **48 × 48 dp minimum** on every surface (`SPACING_SIZING_DENSITY.md` §4), exceeding SC 2.5.8's 24 × 24
  minimum deliberately.
- **56 dp on courier surfaces; 64 dp for proof capture.**
- **Minimum 8 dp between adjacent targets.**
- **No hover-only information.** Anything revealed on hover is reachable by keyboard focus and by touch
  (SC 1.4.13). A tooltip is dismissible, hoverable, and persistent.
- **No path-based gesture is required** (SC 2.5.1). Every swipe has a button equivalent.
- **No drag-only interaction** (SC 2.5.7).
- **Motion actuation is never required** (SC 2.5.4). Nothing is triggered by shaking or tilting.
- **Activation on release, not on press** (SC 2.5.2), so a mis-touch can be dragged away and cancelled.

---

## 11. Modals, sheets, dialogs, and Escape

### Focus trap

While a modal is open:

1. **Focus moves into the modal** on open — to its heading or first interactive element.
2. **Focus is trapped inside it.** Tab cycles within; nothing behind is reachable by keyboard, pointer,
   or touch.
3. **Content behind is inert** and hidden from assistive technology.
4. **Focus returns** to the triggering control on close.
5. The modal has an accessible name from its heading.

### Escape behaviour

| Layer | Escape |
|---|---|
| Tooltip | Dismisses |
| Menu / dropdown | Closes, focus returns to the trigger |
| Autocomplete suggestions | Closes the list, keeps the field focused and its text intact |
| Bottom sheet | Closes if dismissible |
| Dialog | Closes if dismissible |
| **Confirmation dialog for a destructive action** | **Closes and cancels the action** — never confirms it |
| **Blocking dialog** (unsaved changes, conflict resolution, expired session) | **Does not close.** The decision must be made |
| Drawer | Closes |
| Full-screen dialog | Closes, with unsaved-changes confirmation if applicable |

**Escape never confirms, never submits, and never performs a destructive action.** It only ever
cancels or dismisses.

Only the **topmost** dismissible layer responds to Escape. A dialog containing an open dropdown closes
the dropdown first.

### Bottom sheets

- Draggable to dismiss, **and** dismissible by a visible close control and by Escape — never
  drag-only (SC 2.5.7).
- Content inside scrolls independently of the drag gesture.
- A non-dismissible sheet is a modal and traps focus.
- At 200% text scaling a sheet may occupy the full screen; it never clips its content.
- The sheet's handle is decorative and hidden from assistive technology; the close control is real.

---

## 12. Timeouts

WCAG 2.2 SC 2.2.1 and SC 2.2.6.

1. **No data is lost to a timeout.** Form input, queued operations, and drafts survive.
2. **A warning appears at least 60 seconds before a session expires**, announced assertively, with an
   obvious "Lanjutkan" control.
3. **Extension is one interaction.**
4. **The warning is not itself on a short timer** the user could miss.
5. **A timeout never occurs mid-financial-operation.** A payment being recorded is completed or
   explicitly failed; it is never abandoned by an expiring session.
6. **The tracking portal has no session timeout.** The token's own expiry governs, and its expiry
   message is specific and non-enumerable (§15).
7. **Offline queue operations never expire from the client** (Rule 07, rule 4).
8. **An OTP's validity period is stated in the interface** and a resend path exists with a visible
   cooldown.

---

## 13. OTP accessibility

OTP appears in customer login, proof of delivery, and sensitive tracking-portal actions. It is a
frequent accessibility failure point.

1. **The OTP field accepts paste.** Blocking paste is prohibited (SC 3.3.8).
2. **Platform autofill from SMS is supported**, with the correct autocomplete hint.
3. **A single-field input is preferred.** Where separate digit boxes are used, they behave as one
   logical field: one accessible name, paste distributing across boxes, backspace moving back, and the
   whole group announced as one input.
4. **Advancing focus between digit boxes is the one permitted automatic focus change** (§3).
5. **The field is labelled with its purpose and its length**: "Kode OTP (6 digit)".
6. **The validity period is stated**, with a live countdown that is announced at meaningful intervals —
   not every second.
7. **Resend is available** with a visible cooldown and an announced state.
8. **An error states what to do**: "Kode salah atau kedaluwarsa. Minta kode baru." — never merely
   "Invalid".
9. **The OTP value is never announced back**, never logged, never placed in an error message, never put
   in a live region, and never included in any telemetry (Rule 03, rule 20).
10. **No CAPTCHA or cognitive test** accompanies OTP entry (SC 3.3.8).
11. **Attempt limiting is communicated honestly** — the user is told they are locked out and when they
    may retry, without revealing whether the number exists.

---

## 14. Orientation and reflow

- **Content works in both orientations** (SC 1.3.4). Orientation is never locked except where a task
  genuinely requires it, and even then the alternative orientation remains functional.
- **Rotating never loses data**, never resets a form, and never closes a sheet.
- **Reflow** (SC 1.4.10): content is usable at a 320 px-equivalent width without two-dimensional
  scrolling. The only exception is a large data table under a documented responsive pattern
  (`RESPONSIVE_FOUNDATION.md` §5).
- **Text spacing** (SC 1.4.12): the layout survives increased line height, paragraph spacing, letter
  spacing, and word spacing without loss of content.
- **Text scaling to 200%** (SC 1.4.4): `TYPOGRAPHY.md` §5.

---

## 15. Charts and non-text content

Detail in [`DATA_VISUALIZATION.md`](DATA_VISUALIZATION.md).

1. Every chart has a **text alternative** stating what it shows and its key finding.
2. Every chart has an **accessible data table** presenting the same values.
3. **Series are distinguishable without colour** — direct labels, patterns, or shapes.
4. Chart interactions are keyboard operable, and tooltip content is also reachable in the table.
5. **No chart is the sole source of a value a user must act on.** An amount due is text.

---

## 16. Privacy and accessibility together

Accessibility must not become a disclosure channel.

1. **A masked value is masked in its accessible name too.** Screen-reader output never reveals what the
   screen hides.
2. **A tracking token is never in an accessible name, label, live region, page title, or error text.**
3. **An OTP is never announced back.**
4. **A proof photograph's alternative text describes the artefact, not the customer** — "Foto bukti
   pengiriman, 19 Juli 2026 14:30", never an address or a name.
5. **An error never enumerates.** "Nomor tidak ditemukan" is announced identically whether the record
   exists or not, so assistive output cannot be used to probe (`CONTENT_DESIGN.md` §7).
6. **The support-impersonation indicator is announced on entry and remains discoverable** — silent
   access is prohibited (Rule 03, rule 19).

---

## 17. Verification owed

**No item below has been performed.** Each is an obligation on the Step that builds the surface.

| Verification | Owed by |
|---|---|
| Automated accessibility scan in CI | Step that creates `packages/design_system` |
| Runtime contrast verification on a physical low-end device | Each surface-building Step |
| TalkBack walkthrough of every primary flow | Steps 11, 5, 6, 8 |
| NVDA / VoiceOver walkthrough of Console primary flows | Step 10, Step 12 |
| Full keyboard traversal of Console Web | Step 10 |
| 200% text scaling review of every screen | Each surface-building Step |
| Colour-blindness simulation of all status sets | Step 2 review, before Step 11 |
| Reduced-motion verification on all four surfaces | Each surface-building Step |
| Focus-order audit | Each surface-building Step |
| Tracking portal audit at 320 px | Step 7 |
| Courier surface sunlight and one-handed field check | Step 8 |
| Screen-reader privacy audit (§16) | Step 13 |
| Independent accessibility audit | Not scheduled — recorded as `DEBT-009` |
