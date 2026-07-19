# Component Catalog — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY

---

## How to read this catalog

Every component carries a permanent identifier `CMP-###`. **Identifiers are never reused.** A withdrawn
component keeps its ID and gains a withdrawal note.

**No component in this catalog is implemented.** `packages/design_system` is `ABSENT`. Every entry
describes an obligation on a later Step, never an achievement.

Each entry states: purpose · anatomy · variants · sizes · states · tokens · behaviour · keyboard ·
screen reader · validation · loading · disabled · error · privacy · platform · prohibited ·
requirements.

Fields that do not apply are marked **n/a** with a reason, never left blank.

### Universal obligations

Every component in this catalog inherits, without restatement:

1. **Focus indicator per `SHAPE_BORDER_ELEVATION.md` §4 — never removable.**
2. **48 × 48 dp minimum touch target** (`SPACING_SIZING_DENSITY.md` §4); 56 dp on courier surfaces.
3. **Semantic tokens only.** No component references a primitive token directly.
4. **Status never by colour alone** (`COLOR_AND_CONTRAST.md` §7, NFR-026).
5. **Text survives 200% scaling** without truncating critical information (`TYPOGRAPHY.md` §5,
   NFR-025).
6. **Indonesian labels from `UX_COPY_GLOSSARY.md`.** No raw canonical identifier is ever rendered.
7. **Money is integer Rupiah, formatted `Rp79.000`, tabular, never truncated, never abbreviated.**
8. **Reduced motion honoured** (`MOTION_AND_REDUCED_MOTION.md` §5).
9. **No secret, token, OTP, or credential** in any label, accessible name, live region, or error.

### Requirement reference key

References cite the Product Requirements Document (`docs/product/PRODUCT_REQUIREMENTS.md`), the
Non-Functional Requirements register (`docs/quality/NON_FUNCTIONAL_REQUIREMENTS.md`), Master Source
sections, and governance rules. Every component references at least NFR-025, NFR-026, and NFR-027;
those are not repeated per entry unless the component has a specific obligation under them.

---

# Actions

## Contracts every component carries

Before the individual entries, four contracts apply to **every** component in
this catalog without exception. A component that cannot satisfy them is not
ready to be specified.

1. **A state contract.** Every component resolves every state in
   [`COMPONENT_STATE_MATRIX.md`](COMPONENT_STATE_MATRIX.md) as either
   `APPLICABLE` or `NOT APPLICABLE`. There are no blank cells and no undecided
   states.

2. **A keyboard contract.** Every component that can be reached states how it is
   reached and operated — Tab, Enter, Space, arrow keys, Escape.

3. **A screen-reader contract.** Every component states its role, its accessible
   name, and what is announced when its state changes. Accessible names are in
   Bahasa Indonesia.

4. **A focus contract. The focus indicator is never removed.** Not for
   aesthetics, not per component, not per surface, not because a design reads
   more cleanly without it. Every focusable component renders the
   `component.focusRing.color` ring at `component.focusRing.width` with
   `component.focusRing.offset`, meeting 3:1 against its adjacent background.
   A component that is genuinely not focusable says so explicitly and explains
   why — silence is not an answer.

**Status is never conveyed by colour alone.** Wherever a component below shows a
status, it carries a semantic colour, a semantic icon, and a Bahasa Indonesia
text label together — three redundant signals, so that a status survives bright
shop lighting, a cheap screen, and colour vision deficiency.

**No component names a literal colour.** Components name tokens. A hex value in a
component specification is a governance defect under
[Rule 26](../../.claude/rules/26-design-token-governance.md).

---

## CMP-001 — Button

- **Purpose:** Trigger an action. The primary action affordance of the product.
- **Anatomy:** container · optional leading icon (`size.icon.md`) · label (`font.size.label.lg`) ·
  optional trailing icon · optional loading indicator replacing the leading icon.
- **Variants:** `filled` (primary action, one per view) · `outlined` (secondary) · `text` (tertiary,
  low emphasis) · `danger-filled` · `danger-outlined`.
- **Sizes:** small `size.control.sm` (40) · medium `size.control.md` (48, default) · large
  `size.control.lg` (56, Android primary and all courier surfaces).
- **States:** default, hover, focus, pressed, disabled, loading, error (as a result state, via banner
  or snackbar — never as a persistent button style).
- **Tokens:** filled bg `color.semantic.primary` (white label 5.79:1) · hover
  `color.semantic.primary.hover` · pressed `color.semantic.primary.pressed` · outlined border
  `color.semantic.border.interactive` (3.37:1) · text label `color.semantic.primary` · danger
  `color.semantic.danger` (white label 6.54:1) · disabled `color.semantic.disabled` with
  `color.semantic.text.disabled` · radius `radius.md` · padding `space.4` horizontal · icon gap
  `space.3`.
- **Behaviour:** activates on release, not press. Idempotent — a double tap produces one action.
  During `loading` it is non-interactive and retains its width so the layout does not jump. Never
  auto-focused except as the safe default in a confirmation dialog.
- **Keyboard:** Tab to focus · Enter or Space activates · Enter submits the form when it is the form's
  primary action.
- **Screen reader:** role button · accessible name equals the visible label (SC 2.5.3) · `disabled`
  announced with its reason · `loading` announced as busy.
- **Validation:** n/a — buttons do not validate; the form does (CMP-005, `FORM_AND_VALIDATION_PATTERNS.md`).
- **Loading:** spinner replaces the leading icon; label persists or changes to a progress verb
  ("Menyimpan…"). The spinner appears only after 400 ms.
- **Disabled:** `color.semantic.disabled` fill, `color.semantic.text.disabled` label. **Never the only
  route to an action**, and always accompanied by an accessible reason
  (`COLOR_AND_CONTRAST.md` §6).
- **Error:** the button does not hold an error state. Failure is reported by a snackbar (CMP-048),
  banner (CMP-047), or inline error, and the button returns to `default` so retry is possible.
- **Privacy:** the label never contains a customer name, an amount tied to an identity, or any masked
  value.
- **Platform:** Android — bottom action bar, thumb reach, `size.control.lg`. Console Web — inline or
  sticky action bar, `size.control.md`, keyboard-first. Portal — at most one primary action.
- **Prohibited:** more than one `filled` button per view · `radius.full` (pill) buttons · truncated
  labels · "OK"/"Ya" alone on a consequential dialog · a destructive button as the default focus · a
  destructive button within `space.8` of a routine button · removing the focus ring.
- **Requirements:** NFR-025, NFR-026, NFR-027, NFR-028; Master Source §18.2 rules 1, 3;
  `DESIGN_PRINCIPLES.md` P4, P5.

## CMP-002 — Icon Button

- **Purpose:** A compact action affordance where the icon alone is universally understood.
- **Anatomy:** 48 × 48 dp target · centred icon `size.icon.md` · no visible label · tooltip on pointer.
- **Variants:** `standard` · `filled` · `danger` (rare; see prohibited) · `toggle` (pressed state
  persists).
- **Sizes:** target 48 dp always; 56 dp on courier surfaces. Icon 20–24 dp.
- **States:** default, hover, focus, pressed, selected (toggle), disabled, loading.
- **Tokens:** icon `color.semantic.text.primary` or `color.semantic.primary` · hover `overlay.hover` ·
  pressed `overlay.pressed` · selected `color.semantic.selected` background with
  `color.semantic.primary` icon · radius `radius.full` for the ripple bounds, `radius.md` for a filled
  variant.
- **Behaviour:** activates on release. Toggle variants announce their pressed state.
- **Keyboard:** Tab · Enter or Space.
- **Screen reader:** role button · **accessible name is mandatory** and is a real Indonesian action
  label ("Hapus lampiran"), never a glyph name · toggle exposes its pressed state.
- **Validation:** n/a.
- **Loading:** spinner replaces the icon; target size unchanged.
- **Disabled:** reduced-emphasis icon, plus an accessible reason.
- **Error:** n/a — reported elsewhere, as CMP-001.
- **Privacy:** the accessible name never includes customer data.
- **Platform:** Android — app bar and card actions. Console — table row actions, toolbars. Portal —
  avoided; the portal has no chrome.
- **Prohibited:** **any destructive, financial, or permissioned action as an icon-only control**
  (cancel order, refund, void, revoke, delete attachment, approve discount) · missing accessible name ·
  target below 48 dp · tooltip as the only source of the name.
- **Requirements:** NFR-026, NFR-027; `ICONOGRAPHY.md` §3 rule 3.

## CMP-003 — Floating Action Button

- **Purpose:** The single most important creative action on an Android screen, kept in thumb reach.
- **Anatomy:** 56 dp circle · icon `size.icon.md` · optional label in the extended variant.
- **Variants:** `regular` (56 dp, icon only) · `extended` (icon + label, preferred — it names the
  action).
- **Sizes:** 56 dp regular; extended height 56 dp, width fits the label.
- **States:** default, hover, focus, pressed, disabled (rare — prefer hiding), loading.
- **Tokens:** bg `color.semantic.primary` · icon/label `color.semantic.text.inverse` (5.79:1) · radius
  `radius.full` · elevation `elevation.3`.
- **Behaviour:** one FAB per screen. Positioned bottom-trailing with `space.4` margin plus safe-area
  inset. May hide on scroll-down and reappear on scroll-up. **Never obscures the focused element or
  the last list item** — the list reserves bottom padding equal to the FAB height plus `space.4`
  (SC 2.4.11).
- **Keyboard:** Tab, in visual order · Enter or Space.
- **Screen reader:** role button · accessible name naming the action ("Buat pesanan").
- **Validation:** n/a. **Loading:** spinner replaces the icon.
- **Disabled:** prefer hiding over disabling; a disabled FAB is a large, prominent dead control.
- **Error:** n/a. **Privacy:** no customer data in the label.
- **Platform:** Android only. **Not used on Console Web** (side navigation and inline actions serve
  better) and **not used on the tracking portal**.
- **Prohibited:** more than one per screen · a destructive action · obscuring content or focus · use on
  Console Web or the portal · a FAB that scrolls away permanently.
- **Requirements:** NFR-027; `DESIGN_PRINCIPLES.md` P4.

## CMP-004 — Link

- **Purpose:** Navigate to another view or an external resource.
- **Anatomy:** inline text · underline · optional trailing external-indicator icon.
- **Variants:** `inline` (within prose) · `standalone` · `external`.
- **Sizes:** inherits its surrounding text style.
- **States:** default, hover, focus, pressed, visited (Web only), disabled (prefer plain text).
- **Tokens:** `color.semantic.primary` (5.79:1) · **underline always present** · focus ring per
  `SHAPE_BORDER_ELEVATION.md` §4.
- **Behaviour:** navigates; never performs an action, never mutates state. An external link opens in a
  new context and says so.
- **Keyboard:** Tab · Enter.
- **Screen reader:** role link · the link text describes the destination. "Klik di sini" and
  "Selengkapnya" as bare link text are prohibited (SC 2.4.4).
- **Validation / Loading / Disabled / Error:** n/a — a link that cannot navigate is rendered as plain
  text.
- **Privacy:** a link URL never contains a tracking token, an OTP, or unmasked personal data in a
  visible or copyable position.
- **Platform:** all surfaces. On Android, prefer a button for anything that is not navigation.
- **Prohibited:** underline removed (colour alone would carry it — NFR-026) · a link used to perform an
  action · non-descriptive link text · an inline link as the only route to an important action (a
  48 dp target cannot be guaranteed inside prose).
- **Requirements:** NFR-026, NFR-027.

---

# Input

## CMP-005 — Text Field

- **Purpose:** Single-line free-text entry. The base for CMP-006 to CMP-012.
- **Anatomy:** persistent label above · container · optional leading icon · input area · optional
  trailing icon or clear control · helper text below · error text replacing helper text · optional
  character counter.
- **Variants:** `outlined` (default) · `read-only` · `with prefix` · `with suffix`.
- **Sizes:** `size.control.md` (48) default · `size.control.lg` (56) on courier surfaces.
- **States:** default, hover, focus, filled, disabled, read-only, error, warning, loading, success,
  offline, syncing, conflict, permission denied.
- **Tokens:** border `color.semantic.border.interactive` (3.37:1) at `border.width.hairline` · focus
  `color.semantic.focus` at `border.width.thick` · error `color.semantic.danger` at
  `border.width.thick` · label `font.size.label.md` / `color.semantic.text.secondary` · value
  `font.size.body.md` / `color.semantic.text.primary` · helper `font.size.caption` /
  `color.semantic.text.secondary` · radius `radius.sm`.
- **Behaviour:** the label is persistent and never becomes a placeholder. A placeholder, if present, is
  an example, not a label. Clearing is available via a trailing control when the field is non-empty.
- **Keyboard:** Tab · standard text editing · Escape does not clear the field.
- **Screen reader:** label programmatically associated · helper and error associated via a description
  relationship · `invalid` state exposed · `required` exposed.
- **Validation:** on blur for format; on submit for everything; **never on every keystroke** except to
  clear an existing error once resolved (`FORM_AND_VALIDATION_PATTERNS.md` §3).
- **Loading:** for async validation or lookup, a trailing spinner; the field stays editable.
- **Disabled:** per `COLOR_AND_CONTRAST.md` §6 with an accessible reason.
- **Error:** border `color.semantic.danger` **plus** an error icon **plus** error text stating the
  problem and the recovery. Never colour alone (NFR-026).
- **Privacy:** a field holding CONFIDENTIAL or RESTRICTED data is never logged, never echoed into an
  error message, and never included in telemetry (Rule 03, Rule 21).
- **Platform:** Android — appropriate keyboard type, autofill hints. Console — full keyboard support.
  Portal — minimal; only where a customer action genuinely requires input.
- **Prohibited:** placeholder as label · error indicated by colour alone · validation on keystroke ·
  blocking paste · a fixed-height container that clips at 200% scaling · an unlabelled field.
- **Requirements:** NFR-025, NFR-026, NFR-027; SC 1.3.5, 3.3.1, 3.3.2, 3.3.3.

## CMP-006 — Search Field

- **Purpose:** Find a record by text. The primary discovery mechanism for orders and customers.
- **Anatomy:** leading magnifier icon · input · clear control when non-empty · optional scope selector
  · result count announcement region.
- **Variants:** `inline` (in an app bar) · `standalone` · `with scope`.
- **Sizes:** `size.control.md` (48).
- **States:** default, hover, focus, filled, loading, empty results, error, disabled, offline.
- **Tokens:** as CMP-005, with `color.semantic.surface.sunken` fill in the inline variant.
- **Behaviour:** debounced query (recommended 300 ms). Results are **always tenant-scoped and bounded**
  — there is no unbounded result set (FR-057). Result count is stated and announced. Offline search
  operates over locally cached, tenant-scoped data and **says so explicitly**: "Mencari data offline".
- **Keyboard:** Tab · Escape clears the query, then blurs on a second press · Down Arrow moves into a
  results list if present · Enter runs the search.
- **Screen reader:** role searchbox · result count announced politely on change · loading announced as
  busy.
- **Validation:** n/a — no query is invalid; an empty result set is an empty state (CMP-051).
- **Loading:** trailing spinner after 400 ms; previous results remain visible until replaced.
- **Disabled:** rare; prefer showing an empty state explaining why search is unavailable.
- **Error:** inline message with a retry, per `CONTENT_DESIGN.md` §4.
- **Privacy:** search **never crosses the tenant boundary** (Rule 02, hard rules 8 and 12). Queries are
  never logged with personal data. Results respect masking rules for the viewer's context. Search
  never becomes an enumeration oracle: a no-result response is identical whether the record does not
  exist or is out of the viewer's scope.
- **Platform:** Android — app bar search opening a full-screen results view. Console — persistent
  global search plus per-table filtering. Portal — **no search**; the portal is reached by token only.
- **Prohibited:** unbounded results · cross-tenant results · search on the public portal · results that
  reveal the existence of out-of-scope records · unannounced result changes.
- **Requirements:** FR-057; Rule 02 hard rules 8, 12; NFR-026.

## CMP-007 — Phone Field

- **Purpose:** Capture or display an Indonesian mobile number.
- **Anatomy:** label · optional country context · input · helper showing the expected format · error
  text · optional masking indicator in display mode.
- **Variants:** `entry` · `display-masked` · `display-full` (authorised contexts only).
- **Sizes:** `size.control.md` (48).
- **States:** default, hover, focus, filled, error, disabled, read-only, masked, permission denied.
- **Tokens:** as CMP-005; masking indicator uses the eye-slash icon at `color.semantic.text.secondary`.
- **Behaviour:** telephone keyboard on Android. Accepts and normalises common Indonesian forms
  (`08…`, `+628…`, `628…`). Formatting applied on blur, not per keystroke. Autofill hint provided.
- **Keyboard:** Tab · numeric entry · paste permitted.
- **Screen reader:** label "Nomor telepon" · format stated in the associated helper text · **a masked
  value is announced as masked**, and the accessible name never reveals what the screen hides
  (`ACCESSIBILITY.md` §16).
- **Validation:** length and prefix checked on blur. Error names the rule and gives a fictional
  example: "Nomor telepon harus 10–13 digit. Contoh: 081234567890."
- **Loading:** optional trailing spinner during an async duplicate check.
- **Disabled / Read-only:** read-only uses full-contrast text; it is not disabled styling.
- **Error:** per CMP-005.
- **Privacy:** phone is **CONFIDENTIAL** (Rule 21, anchor 16). Masked by default outside authorised
  contexts. The public tracking portal shows `08•• •••• ••21` and never the full number without OTP
  (Master Source §9.2 rule 7, §9.3). **The same phone number in two tenants is two unrelated
  profiles** — the field never suggests, merges, or cross-references across the tenant boundary
  (Rule 02 hard rule 11, Rule 18 invariant 9).
- **Platform:** all. Portal shows masked display only.
- **Prohibited:** cross-tenant duplicate suggestion · unmasked display on the portal · phone in a log,
  error message, or telemetry payload · blocking paste.
- **Requirements:** Rule 02 hard rule 11; Rule 18 invariants 8, 9; Master Source §9.2, §17.2.

## CMP-008 — Money Field

- **Purpose:** Enter a Rupiah amount. The highest-risk input in the product.
- **Anatomy:** label · `Rp` prefix inside the container · right-aligned numeric input · helper text ·
  error text.
- **Variants:** `entry` · `display` (read-only, full contrast) · `entry-with-max`.
- **Sizes:** `size.control.md` (48) · `size.control.lg` (56) on payment and courier surfaces.
- **States:** default, hover, focus, filled, error, warning, disabled, read-only, loading, offline,
  syncing, conflict.
- **Tokens:** as CMP-005 · value in numeric-emphasis (`TYPOGRAPHY.md` §3.7), tabular, right-aligned ·
  prefix `color.semantic.text.secondary`.
- **Behaviour:** **integer Rupiah only.** No decimal separator is accepted or displayed. Thousands
  separators are applied as the user types, using `.`. The stored value is an integer; the displayed
  string is a formatting of it, never the source of truth (Rule 04, rules 1 and 2). Rounding does not
  occur in the field, because there is nothing to round.
- **Keyboard:** numeric keyboard on Android · digits, backspace, and paste of a numeric string ·
  separators are inserted by the field, not typed by the user.
- **Screen reader:** the label states the currency ("Jumlah pembayaran (Rupiah)") · the value is
  announced as a complete amount · errors state the permitted range.
- **Validation:** non-negative; within any stated maximum; not exceeding an outstanding balance where
  that constraint applies. Validated on blur and again on submit. **The server is authoritative**; any
  client-side total is a preview and is labelled "Perkiraan"
  (`FORM_AND_VALIDATION_PATTERNS.md` §7).
- **Loading:** during a server total recalculation the field is read-only with a spinner, and the
  previous value stays visible.
- **Disabled / Read-only:** a displayed invoice total is **read-only at full contrast**, never
  disabled styling.
- **Error:** border, icon, and text. Range errors state the bound with a real figure.
- **Privacy:** amounts are tenant-scoped INTERNAL data; never rendered on the public portal beyond the
  permitted "amount due and payment state" set (Master Source §9.3).
- **Platform:** Ops Android (comfortable density on payment screens) · Console Web · **not** an input
  on the portal.
- **Prohibited:** **floating point anywhere in the path** · decimals · abbreviated display (`Rp1,2jt`)
  · truncation · a client-computed total presented as final · negative entry (a reversal is a separate,
  permissioned operation, not a negative amount) · a "delete payment" affordance.
- **Requirements:** Rule 04 rules 1, 2, 5, 7, 8; Rule 18 invariants 10–14; FR-063.

## CMP-009 — Weight Field

- **Purpose:** Enter laundry weight in kilograms.
- **Anatomy:** label · numeric input · `kg` suffix · helper stating the range · error text.
- **Variants:** `entry` · `display` · `entry-with-scale-integration` (future; NOT IMPLEMENTED).
- **Sizes:** `size.control.md` (48) · `size.control.lg` (56) at an intake counter.
- **States:** default, hover, focus, filled, error, disabled, read-only, offline, syncing.
- **Tokens:** as CMP-005; value tabular, right-aligned; suffix `color.semantic.text.secondary`.
- **Behaviour:** **one decimal place, decimal comma** — `1,5 kg`. Always displays the decimal even when
  whole: `12,0 kg`. Decimal keyboard on Android.
- **Keyboard:** decimal numeric keyboard · comma as the decimal separator, with `.` accepted and
  normalised.
- **Screen reader:** label "Berat (kg)" · value announced with its unit · range stated in helper text.
- **Validation:** greater than zero; within the tenant's configured maximum. Error names the bound:
  "Berat maksimum 50,0 kg per pesanan. Pisahkan menjadi beberapa pesanan."
- **Loading:** a spinner while a dependent price preview recalculates; the weight value stays editable.
- **Disabled / Read-only:** read-only at full contrast.
- **Error:** border, icon, text.
- **Privacy:** n/a — weight is INTERNAL operational data carrying no personal identifier alone.
- **Platform:** Ops Android primarily; Console for correction with permission and an audit entry.
- **Prohibited:** **conflating weight with quantity** — they are distinct value objects with distinct
  units and distinct validation · integer-only entry (0,5 kg must be expressible) · omitting the unit
  · a weight change silently altering a historical order's price (Rule 04 rule 9).
- **Requirements:** Rule 04 rule 9; Rule 17 rule 13.

## CMP-010 — Quantity Field

- **Purpose:** Enter a count of items.
- **Anatomy:** label · optional decrement control · numeric input · optional increment control · `pcs`
  suffix · helper · error text.
- **Variants:** `stepper` (with +/− controls) · `plain input`.
- **Sizes:** `size.control.md` (48); stepper controls are each 48 × 48 dp.
- **States:** default, hover, focus, filled, error, disabled, read-only, at-minimum, at-maximum.
- **Tokens:** as CMP-005; stepper controls per CMP-002.
- **Behaviour:** **integers only.** No decimal is accepted. Increment and decrement respect the bounds
  and disable at them with an accessible reason. Direct entry is always available — a stepper is never
  the only route.
- **Keyboard:** numeric keyboard · Up/Down arrows increment and decrement when focus is in the input.
- **Screen reader:** role spinbutton with min, max, and current value exposed · stepper controls carry
  names "Tambah jumlah" / "Kurangi jumlah".
- **Validation:** integer, within bounds, on change and on submit.
- **Loading:** spinner while a dependent price preview recalculates.
- **Disabled / Read-only:** read-only at full contrast.
- **Error:** border, icon, text naming the bound.
- **Privacy:** n/a. **Platform:** Ops Android and Console.
- **Prohibited:** decimals · conflating quantity with weight · a stepper without a direct-entry route ·
  a stepper control below 48 dp · unbounded increment.
- **Requirements:** Rule 17 rule 13; NFR-027.

## CMP-011 — OTP Field

- **Purpose:** Enter a one-time code for authentication, proof of delivery, or a sensitive portal
  action.
- **Anatomy:** label stating the length · single input **or** a group of digit boxes behaving as one
  logical field · validity countdown · resend control with cooldown · error text.
- **Variants:** `single-input` (preferred) · `digit-boxes`.
- **Sizes:** `size.control.lg` (56) — this is a high-stakes, often one-handed input.
- **States:** default, focus, filled, error, expired, loading, locked-out, disabled.
- **Tokens:** as CMP-005 with numeric-emphasis value; countdown in `font.size.caption` /
  `color.semantic.text.secondary`; expired state uses `color.semantic.warning`.
- **Behaviour:** numeric keyboard · **paste is always permitted** · platform SMS autofill supported ·
  in the digit-boxes variant, paste distributes across boxes, backspace moves back, and focus advances
  automatically (the single permitted automatic focus change, `ACCESSIBILITY.md` §3) · submits
  automatically on completion only where the action is non-destructive and reversible.
- **Keyboard:** Tab enters the group once · digits · paste · Enter submits.
- **Screen reader:** one accessible name for the whole group ("Kode OTP (6 digit)") · the countdown is
  announced at meaningful intervals, not every second · resend availability is announced.
- **Validation:** length and numeric locally; correctness only server-side. Error copy: "Kode salah
  atau kedaluwarsa. Minta kode baru."
- **Loading:** the field becomes read-only with a spinner during verification.
- **Disabled:** only during an active cooldown or lockout, with the remaining time stated.
- **Error:** border, icon, text. **Attempt limiting is stated honestly** — the user is told they are
  locked out and when they may retry, **without revealing whether the number exists**.
- **Privacy:** **the OTP value is never announced back, never logged, never placed in an error message,
  never put in a live region, and never included in telemetry** (Rule 03 rule 20, Rule 21 rule 18).
  The code is SECRET class.
- **Platform:** Customer Android (login) · Ops Android (proof of delivery) · Portal (sensitive actions,
  Master Source §9.2 rule 9).
- **Prohibited:** blocking paste (SC 3.3.8) · CAPTCHA or any cognitive test alongside OTP · the code
  echoed anywhere · an error revealing account existence · a countdown announced every second ·
  digit boxes that are separate accessible fields.
- **Requirements:** Master Source §9.2 rule 9; Rule 03 rules 15, 16, 20; SC 3.3.8.

## CMP-012 — Text Area

- **Purpose:** Multi-line free text — notes, reasons, variance explanations.
- **Anatomy:** label · multi-line container · optional character counter · helper · error text.
- **Variants:** `fixed-rows` · `auto-grow` (default) · `read-only`.
- **Sizes:** minimum 3 rows; grows with content to a stated maximum, then scrolls internally.
- **States:** default, hover, focus, filled, error, disabled, read-only, loading, offline, syncing.
- **Tokens:** as CMP-005 with `font.size.body.md` and `radius.sm`.
- **Behaviour:** auto-grows rather than scrolling at a fixed small height. Enter inserts a newline and
  does **not** submit the form. The character counter appears only when a limit exists and becomes an
  error state at the limit.
- **Keyboard:** Tab moves out of the field (does not insert a tab) · standard editing.
- **Screen reader:** label and helper associated · the character limit stated in the helper text, not
  only in the counter.
- **Validation:** length on blur and submit. Required-reason fields (cancellation, refund, waiver,
  variance) validate on submit and cannot be satisfied by whitespace.
- **Loading:** read-only with a spinner during save.
- **Disabled / Read-only:** read-only at full contrast — a recorded reason is real information.
- **Error:** border, icon, text.
- **Privacy:** free text can contain anything. Internal notes are **never** shown on the public portal
  (Master Source §9.3). The interface states who can see the note.
- **Platform:** all except the portal.
- **Prohibited:** Enter submitting the form · a fixed height that clips at 200% scaling · an internal
  note surfaced publicly · a required reason satisfiable by whitespace.
- **Requirements:** Master Source §9.3; Rule 04 rule 6; Rule 19 rule 3; NFR-025.

## CMP-013 — Dropdown

- **Purpose:** Choose one option from a known, bounded set.
- **Anatomy:** label · closed trigger showing the current value · chevron · open list · options with
  optional leading icon and selected indicator · helper · error text.
- **Variants:** `single-select` · `multi-select` (with checkboxes) · `grouped`.
- **Sizes:** trigger `size.control.md` (48); options 48 dp minimum.
- **States:** default, hover, focus, open, selected, disabled, read-only, error, loading, empty.
- **Tokens:** trigger as CMP-005 · menu `color.semantic.surface.raised`, `radius.md`, `elevation.2` ·
  selected option `color.semantic.selected` with a check icon.
- **Behaviour:** opens on activation; closes on selection, Escape, or outside interaction. Selection
  **never auto-submits a form** (SC 3.2.2). Above roughly 10 options, use Autocomplete (CMP-014)
  instead.
- **Keyboard:** Enter or Space opens · Arrow keys move · Enter selects · Escape closes and returns
  focus to the trigger · typing a character jumps to a matching option.
- **Screen reader:** role combobox with an expanded state · options exposed with their selected state ·
  the selected value announced on change.
- **Validation:** required-selection validated on submit.
- **Loading:** the trigger shows a spinner and is non-interactive while options load.
- **Disabled:** with an accessible reason. **Read-only:** rendered as full-contrast text, not a dead
  control.
- **Error:** border, icon, text.
- **Privacy:** option lists are tenant-scoped. An outlet or staff list never includes another tenant's
  records (Rule 02).
- **Platform:** Android — opens as a bottom sheet at compact width. Console — an anchored menu.
- **Prohibited:** selection auto-submitting · a dropdown for more than ~10 options · an empty dropdown
  with no explanation · cross-tenant options · removing the focus ring on options.
- **Requirements:** Rule 02 hard rule 8; SC 3.2.2.

## CMP-014 — Autocomplete

- **Purpose:** Find and select from a large set — customers, services, addresses.
- **Anatomy:** text input · suggestion list · optional "create new" affordance · result count · empty
  state.
- **Variants:** `single-select` · `multi-select` (with chips) · `with-create`.
- **Sizes:** input `size.control.md` (48); suggestions 48 dp minimum.
- **States:** default, focus, typing, loading, suggestions-open, selected, no-results, error, disabled,
  offline.
- **Tokens:** as CMP-005; list as CMP-013's menu.
- **Behaviour:** debounced query. **Results are always tenant-scoped and bounded** (FR-057). Matching
  text is emphasised by weight, not by colour alone. Offline mode searches locally cached tenant data
  and says so.
- **Keyboard:** typing filters · Down/Up move through suggestions · Enter selects · Escape closes the
  list and **keeps the typed text** · Tab moves on, committing nothing implicitly.
- **Screen reader:** combobox with a listbox popup · result count announced politely · the active
  option announced as focus moves.
- **Validation:** if a selection is required, free text that matches nothing is an error on submit:
  "Pilih pelanggan dari daftar atau buat pelanggan baru."
- **Loading:** trailing spinner; previous suggestions remain until replaced.
- **Disabled:** with a reason. **Error:** border, icon, text with a retry.
- **Privacy:** **customer lookup is tenant-scoped, always.** A customer profile is tenant-scoped, and
  the same phone number in two tenants is two unrelated profiles — the component never merges,
  deduplicates, or suggests across tenants (Rule 02 hard rule 11, Rule 18 invariants 8 and 9). Results
  respect the viewer's masking context. A no-result response is identical whether the record does not
  exist or is out of scope.
- **Platform:** Ops Android and Console. Not on the portal.
- **Prohibited:** cross-tenant suggestions · unbounded results · Escape discarding typed text · a
  suggestion revealing the existence of an out-of-scope record · matching highlighted by colour alone.
- **Requirements:** FR-057; Rule 02 hard rules 8, 11, 12; Rule 18 invariants 8, 9.

## CMP-015 — Date Picker

- **Purpose:** Select a date or a date range.
- **Anatomy:** label · text input accepting typed entry · calendar trigger · calendar surface with
  month navigation · helper · error text.
- **Variants:** `single-date` · `date-range` · `month-only`.
- **Sizes:** input `size.control.md` (48); calendar day cells 48 × 48 dp.
- **States:** default, focus, open, selected, in-range, today, disabled-date, out-of-bounds, error,
  disabled, read-only.
- **Tokens:** selected `color.semantic.primary` with `text.inverse` (5.79:1) · in-range
  `color.semantic.selected` · today marked by an outline **and** an accessible "hari ini" label —
  never colour alone · disabled dates per `COLOR_AND_CONTRAST.md` §6.
- **Behaviour:** **typed entry is always available** — a calendar is never the only route (an important
  keyboard and screen-reader consideration). Displays `19 Juli 2026`; accepts `19/07/2026`. Indonesian
  month and day names (`UX_COPY_GLOSSARY.md` §10). Week starts Monday.
- **Keyboard:** Arrow keys move by day · Page Up/Down by month · Home/End to week extremes · Enter
  selects · Escape closes and returns focus to the trigger.
- **Screen reader:** the calendar is a grid with row and column semantics · each cell announces its
  full date and state · the selected range is announced as a range.
- **Validation:** within bounds; for a range, end is not before start. Errors name the bound.
- **Loading:** rare; a spinner if availability is fetched.
- **Disabled / Read-only:** read-only at full contrast.
- **Error:** border, icon, text.
- **Privacy:** n/a.
- **Platform:** Android — a bottom sheet or full-screen calendar. Console — an anchored popover.
- **Prohibited:** calendar-only entry with no typed route · English month names · today indicated by
  colour alone · day cells below 48 dp · a range whose end may precede its start.
- **Requirements:** NFR-027, NFR-045; SC 1.3.1, 2.1.1.

## CMP-016 — Time Window Picker

- **Purpose:** Select a pickup or delivery time window.
- **Anatomy:** label · list of selectable windows, or a start/end pair · availability indicator per
  window · helper stating the timezone · error text.
- **Variants:** `preset-windows` (preferred) · `custom-range`.
- **Sizes:** window options 56 dp (comfortable density — this is a courier-adjacent decision).
- **States:** default, focus, selected, unavailable, full, past, disabled, error, loading, offline.
- **Tokens:** selected `color.semantic.selected` with `color.semantic.primary` border and a check icon
  · unavailable per `COLOR_AND_CONTRAST.md` §6 with the reason stated in text.
- **Behaviour:** windows render as `08:00–10:00` (24-hour, en dash). **Times are in the outlet's local
  time and the timezone is stated.** Availability is server-authoritative; a client cache is a hint and
  is revalidated on submit. Start and end are a fieldset with a legend when the custom variant is used.
- **Keyboard:** Arrow keys move between windows · Enter or Space selects.
- **Screen reader:** the group has an accessible name · each option announces its window, its
  availability, and its selected state · unavailability announces its reason.
- **Validation:** the window is available at submit time; end after start; not in the past. A window
  that became unavailable between selection and submit produces a specific error and a re-selection
  prompt.
- **Loading:** skeleton options while availability loads.
- **Disabled:** individual windows disable with a stated reason.
- **Error:** border, icon, text.
- **Privacy:** window availability never reveals another customer's booking.
- **Platform:** Customer Android (pickup request) · Ops Android (scheduling) · Console (planning) ·
  Portal (schedule-change request, **OTP-gated**, Master Source §9.2 rule 9).
- **Prohibited:** 12-hour time · a window presented as a guaranteed arrival time · any delivery
  guarantee wording (Rule 09 rule 1) · availability implied by colour alone · a stale client cache
  treated as authoritative.
- **Requirements:** Master Source §9.2 rule 9; Rule 09 rule 1; NFR-045.

## CMP-017 — Checkbox

- **Purpose:** Toggle an independent boolean, or select items in a set.
- **Anatomy:** 48 × 48 dp target · 20 dp box · check or indeterminate glyph · label · optional helper.
- **Variants:** `standalone` · `in-list` · `with-indeterminate` (parent of a partial selection).
- **Sizes:** box 20 dp; target 48 dp.
- **States:** unchecked, checked, indeterminate, hover, focus, pressed, disabled, read-only, error.
- **Tokens:** unchecked border `color.semantic.border.interactive` at `border.width.thick` · checked
  fill `color.semantic.primary` with `text.inverse` glyph (5.79:1) · error border
  `color.semantic.danger` · radius `radius.xs`.
- **Behaviour:** the **label is part of the target**. Never auto-submits.
- **Keyboard:** Tab · Space toggles.
- **Screen reader:** role checkbox with checked / unchecked / mixed state · label associated.
- **Validation:** a required checkbox (a consent) validates on submit with a specific message.
- **Loading:** for an async toggle, a spinner replaces the glyph and the control is non-interactive.
- **Disabled:** with a reason. **Read-only:** shown as a static state with a text equivalent.
- **Error:** border, icon, and message text.
- **Privacy:** **a marketing-consent checkbox is never pre-ticked** and is never bundled with
  transactional consent (`CONTENT_DESIGN.md` §13, Rule 08 rules 4, 5).
- **Platform:** all.
- **Prohibited:** pre-ticked consent · bundled consent · auto-submit on toggle · label outside the
  target · a box smaller than 20 dp · state by colour alone (the glyph carries it).
- **Requirements:** Rule 08 rules 4, 5; Master Source §17.4; NFR-026.

## CMP-018 — Radio

- **Purpose:** Choose exactly one option from a small, visible set.
- **Anatomy:** 48 dp target · 20 dp circle · inner dot when selected · label · optional helper.
- **Variants:** `vertical-list` (default) · `inline` (2–3 short options).
- **Sizes:** circle 20 dp; target 48 dp.
- **States:** unselected, selected, hover, focus, pressed, disabled, read-only, error.
- **Tokens:** as CMP-017 with `radius.full`.
- **Behaviour:** grouped in a fieldset with a legend. **Selection never auto-submits.** A radio group
  has no "none" state after a selection is made; if clearing must be possible, a "Tidak ada" option is
  provided explicitly.
- **Keyboard:** Tab enters the group once, landing on the selected option (or the first if none) ·
  Arrow keys move **and select** · Space selects.
- **Screen reader:** role radiogroup with the legend as its name · each radio announces its position
  ("2 dari 4") and state.
- **Validation:** required-selection on submit.
- **Loading:** the group becomes non-interactive with a spinner.
- **Disabled:** individual options disable with a reason. **Read-only:** static text equivalent.
- **Error:** message associated with the group, not with a single radio.
- **Privacy:** n/a. **Platform:** all; use a dropdown above ~6 options.
- **Prohibited:** auto-submit on selection · a radio group with one option · more than one tab stop
  for the group · use for a boolean (use a checkbox or switch).
- **Requirements:** NFR-026, NFR-027; SC 3.2.2.

## CMP-019 — Switch

- **Purpose:** Toggle a setting that takes effect immediately.
- **Anatomy:** 48 dp target · track 52 × 32 dp · thumb · label · optional helper stating the effect.
- **Variants:** `standalone` · `in-list-row`.
- **Sizes:** track 52 × 32 dp; target 48 dp.
- **States:** off, on, hover, focus, pressed, disabled, read-only, loading, error.
- **Tokens:** off track `color.semantic.disabled` with `color.semantic.border.interactive` outline · on
  track `color.semantic.primary` · thumb `color.semantic.surface` · radius `radius.full`.
- **Behaviour:** **applies immediately** — there is no Save. A failed application reverts the switch
  and shows an error explaining what happened. The on/off state is conveyed by thumb position **and**
  the associated text, never by track colour alone.
- **Keyboard:** Tab · Space toggles.
- **Screen reader:** role switch with on/off state · the label states what the switch controls, not its
  current state.
- **Validation:** n/a.
- **Loading:** the thumb shows a spinner and the control is non-interactive until the server confirms.
- **Disabled:** with a reason. **Read-only:** static text state.
- **Error:** the switch reverts, and a snackbar or inline message explains.
- **Privacy:** **the marketing opt-out switch is a single tap and takes effect immediately**, with a
  plain confirmation and no retention interstitial (`CONTENT_DESIGN.md` §13). Opt-out applies across
  all outlets of the tenant (Rule 08 rule 5).
- **Platform:** all except the portal.
- **Prohibited:** a switch requiring a separate Save · an opt-out flow longer than the opt-in flow · a
  retention interstitial on opt-out · state by colour alone · a switch for a destructive action (use a
  confirmed button).
- **Requirements:** Rule 08 rules 4, 5; NFR-026.

## CMP-020 — Segmented Control

- **Purpose:** Switch between 2–4 mutually exclusive views of the same content.
- **Anatomy:** container · 2–4 equal segments · label per segment · optional icon.
- **Variants:** `text-only` · `icon-and-text`.
- **Sizes:** height `size.control.md` (48); each segment at least 48 dp wide.
- **States:** default, hover, focus, selected, disabled, read-only.
- **Tokens:** container `color.semantic.surface.sunken` · selected `color.semantic.surface` with
  `border.width.hairline` and `color.semantic.text.primary` · unselected
  `color.semantic.text.secondary` (6.45:1) · radius `radius.sm`.
- **Behaviour:** changes the view immediately; it is a view switch, not a form input. Above 4 segments,
  use Tabs (CMP-035).
- **Keyboard:** Tab enters once · Arrow keys move and select.
- **Screen reader:** role radiogroup or tablist with the selected state announced.
- **Validation:** n/a. **Loading:** the content region shows a skeleton; the control stays interactive.
- **Disabled:** individual segments disable with a reason.
- **Error:** the content region carries the error, not the control.
- **Privacy:** segments never reveal counts from outside the viewer's scope.
- **Platform:** Android and Console. Not on the portal.
- **Prohibited:** more than 4 segments · use as a form input · selection by colour alone (the container
  and border carry it) · segments narrower than 48 dp.
- **Requirements:** NFR-026, NFR-027.

---

# Display

## CMP-021 — Chip

- **Purpose:** A compact, interactive token representing a filter, a selection, or an attribute.
- **Anatomy:** container · optional leading icon · label · optional trailing remove control.
- **Variants:** `filter` (toggleable) · `input` (removable, from an autocomplete) · `assist` (triggers
  an action) · `static` (non-interactive attribute).
- **Sizes:** height 32 dp visual with a 48 dp target; large 40 dp visual on courier surfaces.
- **States:** default, hover, focus, selected, disabled, read-only.
- **Tokens:** unselected `color.semantic.surface` with `color.semantic.border.interactive` · selected
  `color.semantic.selected` with `color.semantic.primary` border and a check icon · radius
  `radius.xs`.
- **Behaviour:** the remove control on an input chip is its **own** 48 dp target, separate from the
  chip body.
- **Keyboard:** Tab to the chip · Space toggles · Delete or Backspace removes an input chip.
- **Screen reader:** filter chips announce their selected state · the remove control has its own name
  ("Hapus filter [nama]").
- **Validation:** n/a. **Loading:** a spinner replaces the leading icon.
- **Disabled:** with a reason. **Error:** n/a — errors belong to the surrounding control.
- **Privacy:** a chip label showing a customer name respects the viewer's masking context.
- **Platform:** all except the portal.
- **Prohibited:** selection by colour alone · a remove control sharing the chip's target · a chip as a
  primary action · truncated labels on filter chips (the user must know what is filtered).
- **Requirements:** NFR-026, NFR-027.

## CMP-022 — Status Badge

- **Purpose:** Display a canonical status. **The single most important display component in the
  product.**
- **Anatomy:** container · icon (`size.icon.sm`) · label · optional secondary detail.
- **Variants:** `subtle` (tinted background, coloured text) · `filled` (solid background, inverse text)
  · `outline` (border + coloured text).
- **Sizes:** small (`font.size.label.sm`) · medium (`font.size.label.md`, default) · large
  (`font.size.label.lg`, tracking portal hero).
- **States:** static display. It has no interactive states unless it is also a filter chip, in which
  case CMP-021 governs.
- **Tokens:** per status, per `COLOR_AND_CONTRAST.md` §8. `subtle` uses the `.subtle` background with
  the darker text token; `filled` uses the semantic colour with `color.semantic.text.inverse`. Radius
  `radius.full`. Padding `space.2` horizontal, `space.1` vertical.
- **Behaviour:** **always renders text + icon + colour** (NFR-026, Master Source §18.2 rule 2). The
  label comes from `UX_COPY_GLOSSARY.md` and is identical on all four surfaces. **The badge wraps at
  200% scaling; it never truncates.**
- **Keyboard:** not focusable unless interactive.
- **Screen reader:** announced as its **label text**, never as a colour. The icon is decorative and
  hidden. A status change is announced politely via a live region (`ACCESSIBILITY.md` §7).
- **Validation:** n/a.
- **Loading:** a skeleton pill of the same dimensions.
- **Disabled:** n/a — a status is a fact, never disabled.
- **Error:** `ISSUE` is a status, not a component error state.
- **Privacy:** the badge shows status only. It never carries a reason containing personal data on a
  public surface. On the portal it shows only the permitted content set (Master Source §9.3).
- **Platform:** all four surfaces, identical labels and colours.
- **Prohibited:** **a status rendered as a coloured dot with no label** · a raw canonical identifier
  rendered · truncation · a tenant-defined status · a status outside the canonical sets (FR-071) ·
  colour reassigned per surface · `WAIVED_WITH_AUTHORIZATION` rendered without its authorising actor
  and reason.
- **Requirements:** FR-071; NFR-026; Master Source §18.2 rule 2, §19; Rule 19 rules 1, 2.

## CMP-023 — Avatar

- **Purpose:** Represent a person or an outlet compactly.
- **Anatomy:** circular container · initials **or** an icon. **No photographs of people.**
- **Variants:** `initials` (default) · `icon` (role or outlet) · `placeholder`.
- **Sizes:** `size.avatar.sm` (32) · `size.avatar.md` (40) · `size.avatar.lg` (56).
- **States:** default, with a status indicator (rare), disabled (n/a — an avatar is not interactive
  unless it is a button).
- **Tokens:** background `color.semantic.neutral.subtle` · initials `color.semantic.text.primary`
  (12.37:1) · radius `radius.full`.
- **Behaviour:** initials derive from the display name the viewer is permitted to see — if the name is
  masked, the initials are derived from the masked form.
- **Keyboard:** focusable only when it is a button.
- **Screen reader:** **decorative and hidden when a name is displayed beside it.** Where the avatar
  stands alone, it carries the person's name as its accessible name.
- **Validation / Loading / Disabled / Error:** n/a; a skeleton circle is used while loading.
- **Privacy:** **no customer photographs, ever.** Avatars are initials or icons. A masked name produces
  masked initials. On the portal, staff identity is limited to operational necessity
  (Master Source §9.3).
- **Platform:** Android and Console. Not on the portal beyond an outlet mark.
- **Prohibited:** customer photographs · deriving initials from data the viewer may not see · an avatar
  as the sole identifier of a person · announcing the avatar when the name is already present.
- **Requirements:** Master Source §9.3, §17.2; Rule 21.

## CMP-024 — Customer Card

- **Purpose:** Summarise a customer for selection or review.
- **Anatomy:** avatar (CMP-023) · name · masked phone (CMP-007 display) · optional loyalty chip ·
  optional order count · optional last-order date · optional outstanding-balance summary.
- **Variants:** `compact` (selection list) · `standard` · `detailed` (header of a customer view).
- **Sizes:** compact 64 dp · standard 88 dp · detailed variable.
- **States:** default, hover, focus, selected, disabled, loading, error, read-only, permission denied.
- **Tokens:** container `color.semantic.surface`, `radius.lg`, hairline border, `elevation.0` ·
  balance in numeric-emphasis where non-zero, with `color.semantic.warning`.
- **Behaviour:** activating it navigates to the customer view. Outstanding balance, where shown, reads
  from the authoritative financial record, never a recomputation.
- **Keyboard:** Tab · Enter activates.
- **Screen reader:** a single accessible label combining name, masked contact, and any outstanding
  balance — not a stream of disconnected fragments.
- **Validation:** n/a. **Loading:** skeleton. **Disabled:** rare.
- **Error:** inline message with retry.
- **Privacy:** **the card is tenant-scoped.** A customer profile belongs to exactly one tenant, and the
  same phone number in another tenant is an unrelated profile — the card never aggregates, merges, or
  cross-references across tenants (Rule 02 hard rule 11, Rule 18 invariants 8, 9). The phone is masked
  by default per the viewer's context. **No card is rendered on the public portal.**
- **Platform:** Ops Android and Console.
- **Prohibited:** cross-tenant aggregation · unmasked phone outside an authorised context · a customer
  photograph · rendering on the portal · a balance figure computed client-side.
- **Requirements:** Rule 02 hard rules 8, 11, 12; Rule 18 invariants 8, 9; Rule 04.

## CMP-025 — Order Card

- **Purpose:** Summarise an order in a list. The Android replacement for a data table row.
- **Anatomy:** order number · customer name (masked per context) · status badge (CMP-022) · service
  type · total amount · payment state badge · optional age indicator · optional sync chip · optional
  action row.
- **Variants:** `compact` · `standard` · `with-actions` · `courier` (see CMP-027).
- **Sizes:** compact 88 dp · standard 120 dp · with-actions variable.
- **States:** default, hover, focus, selected, disabled, loading, error, offline, syncing, conflict,
  read-only, permission denied.
- **Tokens:** container `color.semantic.surface`, `radius.lg`, hairline, `elevation.0` · total in
  numeric-emphasis · conflict state adds a `color.semantic.conflict` left border at
  `border.width.heavy` plus the "Perlu Diperiksa" badge.
- **Behaviour:** activating it opens the order. **Every card shows status and payment state**, because
  those are the two facts staff scan for. The age indicator appears from H+1 and is anchored to the
  **first** `READY_FOR_PICKUP` timestamp, which never restarts (Rule 10, Rule 18 invariant 17).
- **Keyboard:** Tab · Enter opens · actions are separate tab stops.
- **Screen reader:** one accessible label combining order number, status label, and payment state; then
  actions as separate controls.
- **Validation:** n/a. **Loading:** skeleton card.
- **Disabled:** n/a. **Error:** inline with retry.
- **Offline / syncing / conflict:** the card carries its own sync chip
  (`UX_COPY_GLOSSARY.md` §6). A conflicted card links to the conflict panel (CMP-057).
- **Privacy:** tenant-scoped; customer name masked per context; no internal notes on any customer-facing
  surface.
- **Platform:** Ops Android and Customer Android. Console uses a data table (CMP-033). **The card is a
  different component from a table row — a table is never shrunk into a card list**
  (`PLATFORM_ADAPTATION.md` §1).
- **Prohibited:** status without a label · a truncated total · an age indicator that restarts on rework
  · cross-tenant orders · internal notes on a customer surface.
- **Requirements:** FR-057, FR-071; Rule 10; Rule 18 invariant 17; NFR-026, NFR-030.

## CMP-026 — Production Job Card

- **Purpose:** Present one production job to an operator.
- **Anatomy:** order number · item count and weight · current production status badge · target stage ·
  special-handling flags · optional photograph indicator · stage action button.
- **Variants:** `queue` (waiting) · `active` (in progress) · `rework`.
- **Sizes:** 120 dp standard; 140 dp with flags.
- **States:** default, focus, selected, loading, error, offline, syncing, conflict, read-only,
  permission denied, disabled.
- **Tokens:** container as CMP-025 · rework variant adds a `color.semantic.warning` left border at
  `border.width.heavy` and the "Diproses Ulang" badge.
- **Behaviour:** the primary action advances the job to the next **enumerated** stage. **There is no
  free status selection** — only transitions the machine permits are offered (FR-072, Rule 19 rule 2).
  Works fully offline with a persisted `client_reference` reused on retry (FR-079, Rule 07 rule 1).
- **Keyboard:** Tab · Enter opens · the stage action is a separate tab stop.
- **Screen reader:** announces the order, the current stage label, and the next available stage.
- **Validation:** the server decides whether a transition is legal; a rejected transition changes
  nothing and reports why (Rule 19 rules 4, 5).
- **Loading:** the action shows a spinner; the card stays readable.
- **Disabled:** an unavailable transition is not rendered rather than rendered disabled.
- **Error:** inline, with the reason and a retry.
- **Offline:** the transition queues and the card shows "Menunggu Sinkronisasi". **A queued transition
  is never presented as applied.**
- **Privacy:** production data is INTERNAL; laundry photographs are RESTRICTED and load only via signed
  expiring URLs.
- **Platform:** Ops Android. Console for supervision, read-mostly.
- **Prohibited:** a free-text or arbitrary status control · a transition not in the machine · a queued
  transition shown as complete · a photograph served from a public URL · high elevation (sunlight
  legibility — `SHAPE_BORDER_ELEVATION.md` §5).
- **Requirements:** FR-071, FR-072, FR-078, FR-079; Rule 19 rules 2, 4, 5; Rule 07 rule 1.

## CMP-027 — Courier Job Card

- **Purpose:** Present one pickup or delivery job to a courier. **The simplest card in the product.**
- **Anatomy:** job type (Jemput / Antar) · sequence position · recipient name (masked) · destination at
  the precision the job requires · time window · masked contact with a call affordance · cash-to-collect
  amount if any · proof requirement indicator · single primary action.
- **Variants:** `pickup` · `delivery` · `failed` · `guest` (external ojek, reduced content).
- **Sizes:** comfortable density, minimum 140 dp; actions 56 dp, proof actions 64 dp.
- **States:** default, focus, active, completed, failed, loading, error, offline, syncing, conflict,
  expired (guest link), revoked (guest link), read-only, permission denied, disabled.
- **Tokens:** container as CMP-025 with `space.5` padding · borders
  `color.semantic.border.strong` (6.80:1) for sunlight legibility · cash amount in numeric-emphasis ·
  elevation capped at `elevation.1`.
- **Behaviour:** **one job at a time.** The primary action is the next physical step. **Proof capture is
  mandatory before completion** (Rule 09 rule 2). Works fully offline with a persisted
  `client_reference` (FR-107). A failed delivery is a **first-class outcome**: a reason is recorded, the
  laundry returns to the outlet, and the order returns to a defined status (FR-106, Rule 19 rule 8).
- **Keyboard:** operable, though touch is the primary model.
- **Screen reader:** announces job type, sequence, masked recipient, window, and cash amount as one
  coherent label.
- **Validation:** completion is blocked until proof exists. The block is explained, never silent.
- **Loading:** the action shows a spinner; the card stays readable.
- **Disabled:** an unavailable action is explained, not silently greyed.
- **Error:** inline with the reason and a retry. **Offline:** queues; the chip shows the sync state.
- **Privacy:** **no full customer address** beyond what the delivery genuinely requires, and never in a
  shareable or indexable form (Rule 09 rule 6). Contact is masked with a call affordance that does not
  reveal the number. **The guest variant is tenant-scoped and shows only the assigned job** — no
  customer history, no other orders, no pricing, no other tenant data (Rule 09 rules 6, 7).
- **Platform:** Ops Android and the external guest link.
- **Prohibited:** **"rute optimal", "rute tercepat", or any optimisation or arrival guarantee**
  (Rule 09 rule 1, `CONTENT_DESIGN.md` §10) · completion without proof · a full address · a guest view
  exposing anything beyond its assignment · compact density · targets below 56 dp · shadow-based
  separation (invisible in sunlight).
- **Requirements:** FR-106, FR-107; Rule 09 rules 1, 2, 6, 7, 8; NFR-028; Rule 07.

## CMP-028 — Tracking Summary Card

- **Purpose:** The public tracking portal's primary content — answering "sudah selesai belum?"
- **Anatomy:** brand and outlet identity · order number · large status badge · plain-language status
  explanation · estimated completion · amount due and payment state · available customer actions ·
  masking notice.
- **Variants:** `portal` (public, most restricted) · `customer-app` (authenticated customer).
- **Sizes:** comfortable density; full width at 320 px with `space.4` margins.
- **States:** default, loading, error, expired, revoked, not-found.
- **Tokens:** container `color.semantic.surface`, `radius.lg`, hairline, **`elevation.0` or
  `elevation.1` only** (portal performance budget) · status badge at large size · amount in
  numeric-emphasis.
- **Behaviour:** the status, its label, and the amount due are **above the fold at 320 px**
  (`RESPONSIVE_FOUNDATION.md` §3). The status timeline (CMP-058) is vertical, never a horizontal
  stepper.
- **Keyboard:** fully operable; actions are real buttons.
- **Screen reader:** an `h1` naming the order · the status announced by label · the amount announced in
  full.
- **Validation:** n/a. **Loading:** skeleton preserving layout, no shimmer beyond the permitted budget.
- **Disabled:** n/a.
- **Error / expired / revoked / not-found:** **all four render the same non-enumerable message** —
  "Tautan ini sudah tidak berlaku. Minta tautan baru dari outlet." Distinguishing them would create an
  enumeration oracle (`CONTENT_DESIGN.md` §7, Rule 21).
- **Privacy:** shows only the permitted set: order number, brand and outlet identity, service type,
  current status and history, estimated completion, amount due, payment state, available actions
  (FR-089, Master Source §9.3). **Never the full address, under any condition** (Master Source §9.2
  rule 8). Name and phone partially masked. **No laundry photographs.** No internal notes, no other
  orders, no cost or margin. The page is `noindex`. **The tracking token never appears in the body, a
  heading, copyable text, an accessible name, or any telemetry.** Sensitive actions require OTP
  (CMP-011, Master Source §9.2 rule 9).
- **Platform:** the public tracking portal, primarily; a reduced form in Customer Android.
- **Prohibited:** a full address · photographs · an app-install interstitial or content-obstructing
  banner (DEC-0006, DEC-0014) · horizontal scroll · a data table · a chart · a distinct error for an
  invalid vs. non-existent token · a delivery guarantee · elevation above `elevation.1`.
- **Requirements:** FR-089; Master Source §9.1–§9.4; DEC-0006, DEC-0014; Rule 21.

## CMP-029 — Payment Summary

- **Purpose:** Show what an order costs and what has been paid.
- **Anatomy:** line items (service, weight or quantity, unit price, line total) · subtotal · discounts
  with their authorising context · total · amount paid · **amount due** · payment state badge ·
  payment history.
- **Variants:** `compact` (card) · `full` (detail view) · `receipt-style` (see CMP-031).
- **Sizes:** variable; the total row is always the most prominent.
- **States:** default, loading, error, read-only, offline, syncing, conflict, permission denied.
- **Tokens:** amounts in numeric-emphasis, tabular, right-aligned · total at
  `font.size.headline.md`, weight 600 · amount due in `color.semantic.warning` when non-zero, and
  `color.semantic.success` when settled.
- **Behaviour:** **every figure reads from the authoritative server-side financial record.** A
  client-side computation appears only as a labelled preview: "Perkiraan total: Rp75.000. Total final
  dihitung saat pesanan disimpan." **The order captures the price that applied when it was created; a
  later price-list change never alters it** (Rule 04 rule 9, Rule 18 invariant 11).
- **Keyboard:** the history region is scrollable and keyboard-reachable.
- **Screen reader:** presented as a table with headers · the total and amount due announced as complete
  amounts.
- **Validation:** n/a — a display component.
- **Loading:** skeleton preserving the row structure; **the previous total is never shown as current
  while a new one loads**.
- **Disabled:** n/a. **Read-only:** full contrast — this is real information.
- **Error:** an explicit message. **A figure that cannot be loaded shows "—" with an explanation,
  never "Rp0"** — unavailable is not zero (RPT-003).
- **Offline / conflict:** an amount differing between device and server surfaces as a conflict with
  both values shown and no default winner (Rule 07 rule 5).
- **Privacy:** amounts are tenant-scoped. Cost and margin are **never** shown to a customer or on the
  portal (Master Source §9.3).
- **Platform:** Ops Android, Console, Customer Android (own orders), Portal (amount due and payment
  state only).
- **Prohibited:** floating point · decimals · abbreviated amounts · truncation · a client figure
  presented as final · "Rp0" standing in for unknown · a historical total changed by a price-list edit
  · a delete-payment affordance · cost or margin on a customer surface.
- **Requirements:** Rule 04 rules 1, 2, 5, 7, 8, 9; Rule 18 invariants 10–14; RPT-003; FR-089.

## CMP-030 — Receivable Summary

- **Purpose:** Show outstanding money across orders — the cashflow-recovery view.
- **Anatomy:** total outstanding · order count · customer count · held invoices · aging breakdown by
  band · oldest item · optional follow-up officer · optional drill-down.
- **Variants:** `outlet` · `tenant` · `portfolio` (across tenants the user legitimately belongs to).
- **Sizes:** KPI-led; typically 4 KPI cards (CMP-067) plus a table (CMP-033).
- **States:** default, loading, empty, error, read-only, permission denied.
- **Tokens:** amounts use `component.field.money.fontFeature` and `font.size.title.md` · surface `component.surface.card.background` at `component.surface.card.elevation` · aging bands use `color.semantic.warning` and `color.semantic.danger`, each paired with an icon and a Bahasa Indonesia label so the band never depends on colour alone · outstanding totals in `color.semantic.text.primary`.
- **Behaviour:** **all figures read from the authoritative financial records; nothing is recomputed
  independently** (Rule 10). Aging is anchored to the **first** `READY_FOR_PICKUP` timestamp and never
  restarts (Rule 18 invariant 17). The unclaimed-laundry dashboard exposes at minimum all nine required
  fields: order count, customer count, held invoices, unpaid balance, order age, outlet, last reminder,
  follow-up officer, reason not collected (Rule 10).
- **Keyboard:** fully operable; drill-down is a real link or button.
- **Screen reader:** KPI values announced completely; the aging breakdown is a real table.
- **Validation:** n/a.
- **Loading:** skeleton KPIs. **Empty:** "Tidak ada cucian menumpuk. Semua pesanan siap sudah diambil."
- **Error:** explicit; unavailable shows "—", never zero (RPT-003).
- **Privacy:** tenant-scoped. **The portfolio variant aggregates only across tenants the user
  legitimately belongs to, and names which tenants are included. It never widens the query surface**
  (Rule 02 hard rule 13).
- **Platform:** Console Web primarily; a reduced form in Ops Android.
- **Prohibited:** figures computed outside the financial records · an aging clock that restarts on
  rework · missing any of the nine required fields · cross-tenant aggregation beyond legitimate
  membership · **any affordance suggesting automatic disposal, sale, auction, donation, or transfer of
  customer laundry** (Rule 10, absolute prohibition).
- **Requirements:** Rule 10; Rule 04; Rule 02 hard rule 13; Rule 18 invariants 17, 18; RPT-003,
  RPT-009, RPT-011, RPT-012.

## CMP-031 — Receipt Preview

- **Purpose:** Show the nota exactly as it will print.
- **Anatomy:** tenant brand header · outlet identity · order number · date and time · customer name ·
  line items · subtotal · discount · total · amount paid · amount due · payment method · optional
  tracking link or QR · footer.
- **Variants:** `58mm` · `80mm` · `A4 invoice`.
- **Sizes:** fixed to the target paper width; the preview scales to fit, and text remains selectable.
- **States:** default, loading, error, read-only, offline.
- **Tokens:** receipt-numeric style (`TYPOGRAPHY.md` §3.8), tabular, amounts right-aligned · container
  `radius.none` with a hairline border · `elevation.0`.
- **Behaviour:** a faithful preview of the printed artefact. **Amounts never wrap and never truncate;
  columns align.** Misaligned digits on a nota are the most common cause of a disputed total. Printing
  works offline against locally queued data, and the preview states when a figure is not yet
  server-confirmed.
- **Keyboard:** the preview is scrollable and focusable; print and share are separate controls.
- **Screen reader:** presented as a structured table, not an image. **A receipt is never rendered as an
  image with no text alternative.**
- **Validation:** n/a. **Loading:** skeleton preserving the row structure.
- **Disabled:** n/a. **Read-only:** always — a receipt preview is never editable.
- **Error:** explicit, with a retry that does not risk a duplicate print record.
- **Privacy:** the printed nota carries the customer's name and order details. **The tracking link on a
  nota is a token-bearing URL: it is printed, but the token never appears in the app's UI text, an
  accessible name, a log, or telemetry** (Rule 03, Rule 21). The order number is printed and is
  guessable — it therefore **never grants access** (Master Source §9.2 rule 3).
- **Platform:** Ops Android (thermal printing) and Console (A4 invoice).
- **Prohibited:** the order number used as a tracking credential · abbreviated or truncated amounts ·
  proportional figures · a receipt rendered as an image without a text equivalent · a historical
  receipt whose figures change after a price-list edit (Rule 04 rule 9).
- **Requirements:** Rule 04 rules 8, 9; Master Source §9.2 rule 3; Rule 18 invariant 11.

---

# Collections

## CMP-032 — List

- **Purpose:** Present a sequence of records.
- **Anatomy:** optional header · list items (leading element · primary text · secondary text · trailing
  element) · separators · optional footer.
- **Variants:** `single-line` · `two-line` · `three-line` · `card-list` (of CMP-025 etc.) ·
  `selectable`.
- **Sizes:** single-line 48 dp · two-line 64 dp · three-line 88 dp; +8 dp at comfortable density.
- **States:** default, hover, focus, selected, disabled, loading, empty, error, read-only, offline,
  permission denied.
- **Tokens:** separator `color.semantic.border` (decorative) · hover `overlay.hover` · selected
  `color.semantic.selected` with a check icon · `elevation.0` always.
- **Behaviour:** the whole row is the target. Results are **always tenant-scoped and bounded** —
  pagination or virtualised loading, never an unbounded set (FR-057). The total count is stated.
- **Keyboard:** Tab enters the list · Arrow keys move within it · Enter activates · Space toggles
  selection in a selectable list.
- **Screen reader:** a real list with an accessible name and item count · each row is one coherent
  label · selection state announced.
- **Validation:** n/a. **Loading:** skeleton rows, then appended rows on paging.
- **Empty:** CMP-051, always with the reason and a next action.
- **Disabled:** individual rows explain why. **Error:** inline with retry; loaded rows remain visible.
- **Offline:** shows locally cached tenant data and says so.
- **Privacy:** tenant-scoped; masking per the viewer's context; a row never reveals a record outside
  scope.
- **Platform:** all. Android's replacement for a data table.
- **Prohibited:** unbounded loading · cross-tenant rows · elevation on rows · row emphasis by colour
  alone (a status column or badge carries it) · an empty state with no explanation.
- **Requirements:** FR-057; Rule 02 hard rule 8; NFR-026.

## CMP-033 — Data Table

- **Purpose:** Present many records across many attributes. **Console Web only.**
- **Anatomy:** optional toolbar · header row with sort controls · body rows · optional selection
  column · optional row actions · footer with pagination and count.
- **Variants:** `standard` · `selectable` · `with-row-actions` · `expandable-rows`.
- **Sizes:** rows `size.row.compact` (40) / `standard` (48) / `comfortable` (56), by context.
- **States:** default, hover, focus, selected, sorted, filtered, loading, empty, error, read-only,
  offline, permission denied, disabled.
- **Tokens:** header `color.semantic.surface.inverse` with `text.inverse` (14.33:1), or
  `surface.sunken` with `text.primary` · header rule `color.semantic.border.strong` (6.80:1) · row
  separator `color.semantic.border` · zebra `color.semantic.surface.sunken` · `elevation.0` ·
  `radius.none`.
- **Behaviour:** text columns left-aligned, **numeric columns right-aligned with tabular figures**,
  dates left-aligned, status columns carry a badge. Results are tenant-scoped and bounded. Where the
  table exceeds its width, exactly one documented responsive pattern applies
  (`RESPONSIVE_FOUNDATION.md` §5); **a money column and a status column are never hidden**.
- **Keyboard:** **one tab stop for the grid** · Arrow keys move between cells · Home/End to row extremes
  · Enter activates the row · Space toggles selection · sort controls are reachable in the header.
- **Screen reader:** a real table with an accessible name, associated headers, sort state announced,
  and pagination announced as "Menampilkan 1–25 dari 340".
- **Validation:** n/a. **Loading:** skeleton rows preserving column widths.
- **Empty:** CMP-051, distinguishing "no records" from "no results for this filter", offering to clear
  the filter.
- **Error:** an inline row-region message with a retry.
- **Offline:** the Console shows a connection banner; tables are not the offline-first surface.
- **Privacy:** tenant-scoped. **Exports carry the same access rules as the underlying records**
  (Rule 03). Masking applies per the viewer's context.
- **Platform:** **Console Web only.** On Android, the stacked-card pattern is used — which is a
  different component (CMP-025), not a shrunken table (`PLATFORM_ADAPTATION.md` §1).
- **Prohibited:** **use on any Android surface** · horizontal page scroll (only the table region may
  scroll, and only under Pattern B) · hiding a money or status column · proportional figures in a
  numeric column · more than one tab stop for the grid · **bulk financial or bulk destructive
  operations** (`PLATFORM_ADAPTATION.md` §4) · unbounded result sets.
- **Requirements:** FR-057; Rule 02 hard rule 8; Rule 03; NFR-025, NFR-026, NFR-027.

## CMP-034 — Pagination

- **Purpose:** Move through a bounded result set and state its size.
- **Anatomy:** current range · total count · previous / next controls · optional page numbers ·
  optional page-size selector.
- **Variants:** `simple` (prev/next + range) · `numbered` · `load-more` (Android).
- **Sizes:** controls `size.control.md` (48).
- **States:** default, hover, focus, disabled (at bounds), loading, error.
- **Tokens:** current page `color.semantic.selected` with `color.semantic.primary` text · range text
  `color.semantic.text.secondary`.
- **Behaviour:** **the total count is always stated** — "Menampilkan 1–25 dari 340". A user must know
  the size of what they are looking at, particularly for receivables. Page changes preserve sort,
  filter, and selection, and are reflected in the URL on Console Web.
- **Keyboard:** Tab · Enter activates.
- **Screen reader:** the range and total are announced politely on change; controls carry names
  ("Halaman berikutnya").
- **Validation:** n/a. **Loading:** controls disable with a spinner; existing rows remain.
- **Disabled:** at the first and last page, with the state exposed.
- **Error:** an inline message with retry.
- **Privacy:** the total count is tenant-scoped and never reveals out-of-scope records.
- **Platform:** Console Web (numbered) · Android (load-more).
- **Prohibited:** an unstated total · infinite scroll as the only mechanism in a financial or
  receivables context (a user needs to know the size) · losing selection on page change · a count that
  crosses the tenant boundary.
- **Requirements:** FR-057; Rule 02 hard rule 8.

---

# Navigation

## CMP-035 — Tabs

- **Purpose:** Switch between peer views within one screen.
- **Anatomy:** tab bar · tab labels with optional icons and counts · selection indicator · panel.
- **Variants:** `fixed` (2–5) · `scrollable` (6+) · `with-counts`.
- **Sizes:** height 48 dp; each tab at least 48 dp wide.
- **States:** default, hover, focus, selected, disabled, loading, error.
- **Tokens:** selected label `color.semantic.primary` with a 3 dp `color.semantic.primary` indicator ·
  unselected `color.semantic.text.secondary` (6.45:1).
- **Behaviour:** switching a tab does not lose unsaved input in another tab's panel without warning
  (`FORM_AND_VALIDATION_PATTERNS.md` §11). Selection **is not by colour alone** — the indicator bar and
  the announced state carry it.
- **Keyboard:** Tab enters the bar once · Arrow keys move between tabs · Tab moves into the panel.
- **Screen reader:** role tablist / tab / tabpanel with the selected state announced; the panel is
  associated with its tab.
- **Validation:** n/a. **Loading:** the panel shows a skeleton; the bar stays interactive.
- **Disabled:** individual tabs disable with a reason. **Error:** the panel carries it.
- **Privacy:** tab counts are tenant-scoped.
- **Platform:** Android and Console. Not on the portal.
- **Prohibited:** more than 5 fixed tabs · tabs for a sequential process (use CMP-059) · selection by
  colour alone · silent loss of unsaved input on tab change · nested tabs.
- **Requirements:** NFR-026, NFR-027.

## CMP-036 — Bottom Navigation

- **Purpose:** Top-level destination switching on Android.
- **Anatomy:** 3–5 destinations · icon · label · optional badge · selection indicator.
- **Variants:** `3-destination` · `4-destination` · `5-destination`.
- **Sizes:** height `size.bottomnav.height` (64 dp) plus safe-area inset; each destination at least
  48 dp wide.
- **States:** default, focus, selected, disabled (rare — prefer hiding), with-badge.
- **Tokens:** selected icon and label `color.semantic.primary` with a filled icon variant · unselected
  `color.semantic.text.secondary` · surface `color.semantic.surface` with a hairline top border.
- **Behaviour:** persists across top-level destinations; **absent on detail screens**. Each destination
  keeps its own navigation stack. Selecting the current destination scrolls its content to the top.
  **Destinations vary by role** on Ops Android — a kasir does not see the courier destination.
- **Keyboard:** operable via external keyboard; Arrow keys move between destinations.
- **Screen reader:** a navigation landmark; each destination announces its selected state and any badge
  count.
- **Validation:** n/a. **Loading:** the bar never shows a loading state.
- **Disabled:** prefer omitting a destination the role cannot use over disabling it.
- **Error:** n/a.
- **Privacy:** badge counts are tenant- and outlet-scoped and never reveal out-of-scope records.
- **Platform:** **Android only.** Console uses side navigation (CMP-038); the portal has none.
- **Prohibited:** more than 5 destinations · **icon-only destinations without labels** (labels may hide
  only at extreme text scaling, with the accessible name retained — `TYPOGRAPHY.md` §5) · use on
  Console Web · appearing on detail screens · selection by colour alone.
- **Requirements:** NFR-025, NFR-026, NFR-027; Rule 02 hard rule 8.

## CMP-037 — Navigation Rail

- **Purpose:** Top-level destination switching on a tablet-width Android surface.
- **Anatomy:** vertical rail · 3–7 destinations · icon `size.icon.lg` · label · optional leading FAB ·
  selection indicator.
- **Variants:** `icon-and-label` (default) · `icon-only` (with mandatory tooltips and accessible
  names).
- **Sizes:** width `size.navrail.width` (80 dp); destinations at least 56 dp tall.
- **States:** default, hover, focus, selected, disabled, with-badge.
- **Tokens:** as CMP-036, with the selection indicator as a pill behind the icon.
- **Behaviour:** replaces bottom navigation at `breakpoint.medium` and above on Android. Same
  destinations, same role filtering, same stacks.
- **Keyboard:** Arrow keys move; Enter selects.
- **Screen reader:** a navigation landmark with selected states announced.
- **Validation / Loading / Error:** n/a. **Disabled:** prefer omission.
- **Privacy:** as CMP-036.
- **Platform:** Android tablet. Not on phones, not on Console Web, not on the portal.
- **Prohibited:** icon-only without accessible names and tooltips · more than 7 destinations ·
  coexisting with bottom navigation · use on Console Web.
- **Requirements:** NFR-026, NFR-027.

## CMP-038 — Side Navigation

- **Purpose:** Primary navigation for Console Web.
- **Anatomy:** wordmark · destination groups with headings · destinations with icon and label · optional
  counts · collapse control · optional footer.
- **Variants:** `expanded` (264 dp) · `collapsed` (72 dp, icon-only with tooltips) · `overlay` (below
  `breakpoint.expanded`).
- **Sizes:** `size.sidenav.width` (264) / `size.sidenav.width.collapsed` (72); rows 48 dp.
- **States:** default, hover, focus, selected, disabled, expanded, collapsed, with-count.
- **Tokens:** surface `color.semantic.surface.inverse` (`color.blue.900`) with `text.inverse` labels
  (14.33:1) · selected row `color.blue.800` with a leading `color.semantic.accent` or
  `color.blue.400` indicator bar plus a filled icon · group headings in `font.size.label.sm`.
- **Behaviour:** persists at `breakpoint.expanded` and above; becomes an overlay drawer below. Groups
  reflect the product's domains: Operasi, Keuangan, Pelanggan, Master Data, Laporan, Pengaturan.
  **Destinations are filtered by role and by subscription entitlement**, and an unavailable destination
  is either absent or explained — never silently greyed.
- **Keyboard:** Tab · Arrow keys move within a group · Enter navigates · the collapse control is
  focusable and announces its state.
- **Screen reader:** a navigation landmark with a name · groups exposed as labelled groups · selected
  state announced · the collapsed variant retains full accessible names.
- **Validation:** n/a. **Loading:** counts show a skeleton; navigation stays usable.
- **Disabled:** with a reason, or omitted.
- **Error:** n/a.
- **Privacy:** counts are tenant-scoped; the navigation never reveals features or data belonging to
  another tenant.
- **Platform:** **Console Web only.**
- **Prohibited:** use on Android · a collapsed variant without accessible names · selection by colour
  alone · a destination greyed with no explanation · counts crossing the tenant boundary.
- **Requirements:** Rule 02 hard rule 8; NFR-026, NFR-027; SUB-004.

## CMP-039 — Breadcrumb

- **Purpose:** Show the user's position in a hierarchy and let them move up it.
- **Anatomy:** ordered ancestor links · separators · current page as non-link text.
- **Variants:** `full` · `truncated` (with a middle overflow control).
- **Sizes:** height 32 dp; each link a 48 dp target.
- **States:** default, hover, focus, current, truncated.
- **Tokens:** links `color.semantic.primary` underlined · current `color.semantic.text.primary`,
  not a link · separator `color.semantic.text.secondary`.
- **Behaviour:** appears on Console views more than two levels deep. Truncation collapses the middle,
  never the first or the current item.
- **Keyboard:** Tab · Enter navigates.
- **Screen reader:** a navigation landmark named "Breadcrumb", marked up as an ordered list, with the
  current page exposed as current.
- **Validation / Loading / Disabled / Error:** n/a.
- **Privacy:** breadcrumb labels never contain unmasked personal data — an order breadcrumb shows the
  order number, not the customer's name.
- **Platform:** **Console Web only.** Android uses hardware back and the app bar.
- **Prohibited:** use on Android · the current page as a link · truncating the first or current item ·
  a customer name in a breadcrumb.
- **Requirements:** NFR-026; Master Source §17.2.

## CMP-040 — Tenant Switcher

- **Purpose:** Switch the active tenant. **A tenant-isolation-critical component.**
- **Anatomy:** current tenant name (always visible) · switch control · tenant list with names and roles
  · optional search when the list is long · confirmation of the switch.
- **Variants:** `app-bar` (Android) · `top-bar` (Console) · `single-tenant` (renders as a static
  label, non-interactive).
- **Sizes:** control `size.control.md` (48); list items 56 dp.
- **States:** default, hover, focus, open, switching, error, single-tenant, permission denied.
- **Tokens:** current tenant `font.size.title.md` · list items with a building icon · selected
  `color.semantic.selected`.
- **Behaviour:** **the current tenant is always visible and never ambiguous.** A tenant switcher must
  exist wherever a user can belong to more than one tenant (Rule 02 hard rule 5). Switching:
  1. clears **all** cached tenant data, in memory and on device (Rule 02, Rule 07 rule 7);
  2. returns to the top-level destination — it never carries a previous tenant's view forward;
  3. re-derives authorisation from Membership, never from the client's assertion (Rule 02 hard
     rules 9, 10);
  4. shows a switching state that cannot be interrupted mid-way.
- **Keyboard:** Enter opens · Arrow keys move · Enter selects · Escape closes.
- **Screen reader:** the accessible name states the **current** tenant ("Bisnis aktif: [nama]") · each
  option announces its name and the user's role · the switch is announced on completion.
- **Validation:** n/a. **Loading:** a switching state blocking further interaction.
- **Disabled:** a single-tenant user sees a static label, never a dead control.
- **Error:** an explicit message; **on failure the previous tenant remains active and its data remains
  cleared until a successful load** — failing closed, never into an ambiguous state.
- **Privacy:** **the list contains only tenants the user is a member of.** A client-supplied tenant
  identifier is never authorisation proof (Rule 02 hard rule 9, Rule 18 invariant 7). **No cached data
  survives the switch** (Rule 07 rule 7). **Any cross-tenant leak here is an automatic NO-GO**
  (Rule 02 hard rule 12).
- **Platform:** Ops Android and Console Web. Not on Customer Android, not on the portal.
- **Prohibited:** an ambiguous or hidden current tenant · cached data surviving a switch · a tenant not
  derived from Membership · authorisation from a client-supplied tenant ID · switching without
  clearing the view · a switcher that lists tenants for discovery.
- **Requirements:** Rule 02 hard rules 5, 9, 10, 11, 12; Rule 07 rule 7; Rule 18 invariants 6, 7.

## CMP-041 — Outlet Selector

- **Purpose:** Choose the active outlet within the current tenant.
- **Anatomy:** current outlet name · switch control · outlet list grouped by brand · optional
  "Semua outlet" option where the role permits it.
- **Variants:** `single-outlet` (static label) · `multi-outlet` · `with-all-option`.
- **Sizes:** control `size.control.md` (48); list items 48 dp.
- **States:** default, hover, focus, open, selected, disabled, permission denied.
- **Tokens:** as CMP-013, with brand names as group headings.
- **Behaviour:** scoped **within** the current tenant, always. Changing the outlet re-scopes the view
  and is reflected in the URL on Console Web. "Semua outlet" is available only to roles permitted to
  see across outlets, and is a real permission check on the server.
- **Keyboard:** as CMP-013. **Screen reader:** the accessible name states the current outlet.
- **Validation:** n/a. **Loading:** a spinner while re-scoping.
- **Disabled:** a single-outlet user sees a static label.
- **Error:** explicit, with the previous scope retained.
- **Privacy:** **lists only outlets within the current tenant that the user may access.** The hierarchy
  is `Tenant → Laundry Brand → Outlet`, and the selector never crosses the tenant boundary
  (Rule 02).
- **Platform:** Ops Android and Console Web.
- **Prohibited:** outlets from another tenant · an "all outlets" option without a server-side permission
  check · outlet scope inferred from the last request rather than the explicit selection.
- **Requirements:** Rule 02 hard rules 3, 4, 8, 10.

## CMP-042 — App Bar

- **Purpose:** Identify the current view and host its top-level actions.
- **Anatomy:** optional leading navigation control (back or menu) · title · optional subtitle · up to
  2 actions · overflow menu · optional tenant switcher · optional sync indicator.
- **Variants:** `standard` · `with-back` · `search` · `contextual` (during a selection) ·
  `large` (a prominent title that collapses on scroll).
- **Sizes:** `size.appbar.height` (56 dp) Android · `size.appbar.height.web` (64 dp) Console.
- **States:** default, scrolled (gains `elevation.1` or a bottom hairline), contextual, loading.
- **Tokens:** surface `color.semantic.surface` with `text.primary`, or
  `color.semantic.surface.inverse` with `text.inverse` (14.33:1) · title `font.size.title.lg`.
- **Behaviour:** the title names the view. **At most two icon actions**; the rest go to overflow.
  **Destructive actions live in overflow, never as a bar icon** — the distance is the safety mechanism
  (`DESIGN_PRINCIPLES.md` P5). On Ops Android the bar carries the sync indicator (CMP-056) and the
  tenant switcher (CMP-040).
- **Keyboard:** actions are tab stops in visual order.
- **Screen reader:** a banner landmark · the title is the view's heading · each action carries an
  accessible name.
- **Validation:** n/a. **Loading:** a determinate or indeterminate bar may sit beneath the app bar.
- **Disabled:** actions disable with a reason. **Error:** carried by a banner, not the app bar.
- **Privacy:** **the title never contains unmasked personal data.** An order view title is the order
  number, not the customer name.
- **Platform:** Android and Console. The portal has a minimal header, not an app bar.
- **Prohibited:** more than 2 icon actions · a destructive action as a bar icon · a customer name in
  the title · an ambiguous tenant context on a multi-tenant surface · obscuring focused content
  (SC 2.4.11).
- **Requirements:** NFR-026, NFR-027; Master Source §17.2, §18.2 rule 3.

---

# Overlays

## CMP-043 — Bottom Sheet

- **Purpose:** Present supplementary content or input without leaving the screen. The primary
  supplementary pattern on Android.
- **Anatomy:** drag handle (decorative) · optional title · content · optional action row · scrim when
  modal.
- **Variants:** `modal` (with scrim, focus trapped) · `standard` (no scrim) · `expanding`.
- **Sizes:** full width; height fits content up to 90% of the screen; capped at
  `size.bottomsheet.max` (640 dp) when centred on a large screen.
- **States:** closed, opening, open, expanded, closing, loading, error.
- **Tokens:** surface `color.semantic.surface` · `radius.xl` **top corners only** · `elevation.3` ·
  scrim `overlay.scrim` · handle `color.semantic.border`.
- **Behaviour:** enters over `duration.slow` with `easing.decelerate`; exits over `duration.normal`
  with `easing.accelerate`; **cross-fades under reduced motion.** Content scrolls independently of the
  drag gesture. At 200% scaling it may occupy the full screen; it never clips.
- **Keyboard:** **focus moves into the sheet on open and is trapped while modal** · Escape closes a
  dismissible sheet · focus returns to the trigger on close.
- **Screen reader:** role dialog with an accessible name from its title · content behind is inert and
  hidden · the drag handle is decorative and hidden.
- **Validation:** delegated to the contained form.
- **Loading:** a skeleton inside; the sheet does not resize once open unless the user expands it.
- **Disabled:** n/a. **Error:** carried inside the sheet, near the failing element.
- **Privacy:** content inherits the surface's masking rules.
- **Platform:** Android primary. Console prefers a dialog or a side panel. Portal uses sheets rarely and
  never for core status content.
- **Prohibited:** **drag as the only dismissal** — a visible close control and Escape are mandatory
  (SC 2.5.7) · a non-dismissible sheet that does not trap focus · content behind reachable while modal
  · clipping content at 200% scaling · a sheet obscuring the focused element.
- **Requirements:** SC 2.1.2, 2.5.7, 2.4.11; NFR-025; `ACCESSIBILITY.md` §11.

## CMP-044 — Dialog

- **Purpose:** Interrupt the user for a decision or a focused task.
- **Anatomy:** title · body · optional content · action row (secondary then primary, trailing-aligned)
  · optional close control · scrim.
- **Variants:** `standard` · `form` · `full-screen` (Android, for a complex task) · `blocking`
  (non-dismissible).
- **Sizes:** `size.dialog.width` (560) · `size.dialog.width.sm` (400) · full-screen on Android.
- **States:** closed, opening, open, closing, loading, error, blocking.
- **Tokens:** surface `color.semantic.surface` · `radius.lg` · `elevation.4` · scrim `overlay.scrim` ·
  title `font.size.title.lg`.
- **Behaviour:** **at 1366 × 768 a dialog never exceeds 560 px in height**; longer content scrolls
  inside while the title and action row stay fixed (`RESPONSIVE_FOUNDATION.md` §4).
- **Keyboard:** **focus moves in on open and is trapped** · Escape closes a dismissible dialog · a
  blocking dialog does not respond to Escape · focus returns to the trigger on close.
- **Screen reader:** role dialog, modal, named by its title · content behind inert and hidden.
- **Validation:** delegated to the contained form; errors are announced assertively.
- **Loading:** the primary action shows a spinner; the dialog does not resize.
- **Disabled:** the primary action may disable until the form is valid, with the reason available.
- **Error:** shown inside the dialog, never as a separate dialog stacked on top.
- **Privacy:** inherits masking rules; never displays a token, an OTP, or a credential.
- **Platform:** all except the portal, where a dialog is never required to see core status.
- **Prohibited:** stacked dialogs · a dialog taller than 560 px at the baseline resolution · Escape
  confirming or submitting anything · a blocking dialog with no route forward · content behind
  reachable while open.
- **Requirements:** SC 2.1.2, 2.4.11, 3.3.4; `ACCESSIBILITY.md` §11; `RESPONSIVE_FOUNDATION.md` §4.

## CMP-045 — Confirmation Dialog

- **Purpose:** Confirm a consequential or destructive action. **A safety component.**
- **Anatomy:** title naming the action · body naming the **consequence** · optional required reason
  field · cancel control · confirm control.
- **Variants:** `destructive` · `financial` · `standard`.
- **Sizes:** `size.dialog.width.sm` (400); comfortable density.
- **States:** open, loading, error, blocking.
- **Tokens:** confirm in the `danger-filled` button variant for destructive actions
  (`color.semantic.danger`, white label 6.54:1) · cancel in the `outlined` variant · **separated by at
  least `space.8`** (`SPACING_SIZING_DENSITY.md` §4 rule 6).
- **Behaviour:**
  1. **The body names what will be lost or changed**, with the specific record and amount:
     "Kembalikan Rp45.000 ke pelanggan? Tindakan ini dicatat dan tidak bisa dihapus."
  2. **Buttons name their outcomes** — "Batalkan pesanan" / "Kembali", never "Ya" / "Tidak".
  3. **The default focus is the safe option**, never the destructive one.
  4. **A reason is required** where governance requires one — cancellation (FR-058), refund and void
     (Rule 04 rule 6), a QC waiver (Rule 19), a queued-financial-operation removal (Rule 07 rule 4).
     Whitespace does not satisfy it.
  5. **Escape cancels; it never confirms.**
- **Keyboard:** focus trapped · Escape cancels · Tab cycles · Enter activates the focused control,
  which is the safe one by default.
- **Screen reader:** role alertdialog · the body announced on open · the required reason field
  associated and marked required.
- **Validation:** the reason field validates on confirm.
- **Loading:** the confirm control shows a spinner; both controls become non-interactive.
- **Disabled:** confirm stays disabled until a required reason is entered, with the reason for
  disablement available.
- **Error:** shown inside; the action is not performed; the dialog stays open so the user can retry.
- **Privacy:** the body may name a customer and an amount to the authorised operator; it never contains
  a token or an OTP.
- **Platform:** all except the portal.
- **Prohibited:** "Ya"/"Tidak"/"OK" as the labels · the destructive option focused by default · adjacent
  or unseparated buttons · a body that does not name the consequence · Escape confirming · a
  destructive action with **no** confirmation · **any bulk destructive or bulk financial confirmation**
  (`PLATFORM_ADAPTATION.md` §4).
- **Requirements:** FR-058; Rule 04 rules 6, 7, 8; Rule 07 rule 4; Rule 19; Master Source §18.2 rule 3;
  SC 3.3.4.

## CMP-046 — Drawer

- **Purpose:** Host navigation or filters in an overlay panel.
- **Anatomy:** optional header · content · optional footer · scrim · close control.
- **Variants:** `navigation` (leading edge) · `filter` (trailing edge) · `detail` (trailing edge,
  Console).
- **Sizes:** width 280–360 dp on Android; up to 480 dp on Console.
- **States:** closed, opening, open, closing, loading, error.
- **Tokens:** surface `color.semantic.surface` · `radius.none` · `elevation.3` · scrim
  `overlay.scrim`.
- **Behaviour:** slides from its edge; cross-fades under reduced motion. Modal by default on Android.
- **Keyboard:** focus moves in and is trapped while modal · Escape closes · focus returns to the
  trigger.
- **Screen reader:** role dialog when modal, or a navigation landmark when persistent; named.
- **Validation:** delegated. **Loading:** skeleton inside.
- **Disabled:** n/a. **Error:** inside the drawer.
- **Privacy:** filter values never reveal out-of-scope data.
- **Platform:** Android (navigation below `breakpoint.medium`) · Console (filters and detail panels).
- **Prohibited:** swipe as the only open or close mechanism · content behind reachable while modal ·
  a drawer as the only route to a primary action.
- **Requirements:** SC 2.1.2, 2.5.7.

---

# Feedback

## CMP-047 — Banner

- **Purpose:** A persistent, in-page message about a condition that affects the current view.
- **Anatomy:** icon · message · optional action · optional dismiss control.
- **Variants:** `information` · `success` · `warning` · `danger` · `offline` (CMP-055) ·
  `conflict`.
- **Sizes:** full width of its region; height fits content.
- **States:** default, dismissed, loading (action in progress).
- **Tokens:** background `color.semantic.<state>.subtle` · text and icon the darker paired token —
  measured pairs in `COLOR_AND_CONTRAST.md` §3.1 (danger 10.45:1, warning 8.18:1, success 8.63:1,
  information 13.16:1, conflict 6.93:1) · a hairline border in the semantic colour · `radius.md` ·
  `elevation.0`.
- **Behaviour:** persistent — it does not auto-dismiss. Placed at the top of the region it concerns,
  not floating. **A dismissed banner stays dismissed** for that condition and session
  (`CONTENT_DESIGN.md` §13, no nagging).
- **Keyboard:** actions are tab stops; the dismiss control is focusable and named.
- **Screen reader:** role status for informational banners, role alert for danger and conflict; the
  message is announced.
- **Validation:** n/a. **Loading:** the action shows a spinner.
- **Disabled:** n/a. **Error:** a banner *is* the error presentation.
- **Privacy:** never contains a token, an OTP, a credential, or unmasked personal data.
- **Platform:** all four surfaces.
- **Prohibited:** state by colour alone (icon and text carry it) · auto-dismissal · a banner used for a
  transient confirmation (use a snackbar) · re-showing a dismissed banner for the same condition · a
  banner obscuring the focused element.
- **Requirements:** NFR-026; SC 2.4.11; `CONTENT_DESIGN.md` §4, §13.

## CMP-048 — Snackbar

- **Purpose:** Confirm a completed action briefly, with an optional undo.
- **Anatomy:** message · optional single action · optional dismiss.
- **Variants:** `standard` · `with-action` · `error`.
- **Sizes:** full width at compact; up to 480 dp at medium and above.
- **States:** entering, visible, exiting, dismissed.
- **Tokens:** surface `color.semantic.surface.inverse` with `text.inverse` (14.33:1) · `radius.md` ·
  `elevation.3`.
- **Behaviour:** appears above the bottom navigation and the FAB, never covering them. Duration 4–6
  seconds, **and longer when it carries an action** (SC 2.2.1). One at a time; a new one replaces the
  current. **A snackbar is never the only notification of a failure** — a failure that needs action
  gets a banner or an inline error.
- **Keyboard:** the action is focusable; Escape dismisses.
- **Screen reader:** role status announced politely, or alert when it carries an error.
- **Validation:** n/a. **Loading:** n/a.
- **Disabled:** n/a. **Error:** the error variant is permitted only for transient, non-actionable
  failures.
- **Privacy:** never contains a token, an OTP, or unmasked personal data.
- **Platform:** Android and Console.
- **Prohibited:** more than one action · use for a message the user must act on · covering the FAB,
  bottom navigation, or the focused element · a critical financial failure reported only by snackbar ·
  a duration too short to read the action (SC 2.2.1).
- **Requirements:** SC 2.2.1, 2.4.11; `CONTENT_DESIGN.md` §5.

## CMP-049 — Toast

- **Purpose:** A brief, non-interactive, purely informational notice.
- **Anatomy:** message only. No action, no dismiss control.
- **Variants:** `standard`.
- **Sizes:** as CMP-048.
- **States:** entering, visible, exiting.
- **Tokens:** surface `color.semantic.surface.inverse` · label `color.semantic.text.inverse` · radius `radius.md` · elevation `elevation.3` · duration `motion.duration.slow`, replaced by `motion.reduced.duration` when reduced motion is requested — the toast then appears without animation rather than disappearing entirely, because removing the motion must never remove the message.
- **Behaviour:** 2–4 seconds, then disappears. **Distinct from a snackbar in that it carries no
  action.** Because it cannot be acted on, it is used only for information that is genuinely
  disposable.
- **Keyboard:** not focusable — there is nothing to focus.
- **Screen reader:** announced politely via a live region.
- **Validation / Loading / Disabled / Error:** n/a — **a toast is never used to report an error.**
- **Privacy:** never contains personal data, a token, or an OTP.
- **Platform:** Android primarily.
- **Prohibited:** **any error, warning, or financial message** · any message the user might need to act
  on · any message the user might need to re-read · stacking multiple toasts.
- **Requirements:** SC 2.2.1; `CONTENT_DESIGN.md` §4.

## CMP-050 — Tooltip

- **Purpose:** Supply the name or a brief explanation of a control on pointer and keyboard focus.
- **Anatomy:** small surface · short text. No interactive content.
- **Variants:** `label` (names an icon-only control) · `description` (brief explanation).
- **Sizes:** maximum 320 dp wide; content is short by definition.
- **States:** hidden, showing, dismissed.
- **Tokens:** surface `color.semantic.surface.inverse` with `text.inverse` (14.33:1) · `radius.md` ·
  `elevation.2` · `font.size.body.sm`.
- **Behaviour:** appears on hover after ~500 ms and **immediately on keyboard focus**. Per SC 1.4.13 it
  is **dismissible** (Escape), **hoverable** (the pointer may move into it), and **persistent** (it does
  not vanish on a timer while hovered or focused).
- **Keyboard:** shows on focus; Escape dismisses without moving focus.
- **Screen reader:** the tooltip text is the control's accessible description, or its name where the
  control is icon-only. It is not announced twice.
- **Validation / Loading / Disabled / Error:** n/a.
- **Privacy:** never contains personal data.
- **Platform:** **Console Web primarily.** On touch surfaces a tooltip is not reachable, so **a tooltip
  is never the only source of essential information** — icon-only controls carry a real accessible name
  regardless.
- **Prohibited:** **a tooltip as the only carrier of essential information** · interactive content
  inside · use on a touch-only surface as the sole affordance · a tooltip that vanishes on a timer
  while hovered · a tooltip obscuring the control it describes.
- **Requirements:** SC 1.4.13, 3.3.2; `ICONOGRAPHY.md` §3.

## CMP-051 — Empty State

- **Purpose:** Explain that a region has no content, why, and what to do.
- **Anatomy:** icon (`size.icon.xl`) · heading · explanation · optional primary action · optional
  secondary action.
- **Variants:** `no-data-yet` · `no-results` (filter or search) · `all-clear` (positive) ·
  `permission-denied` · `error-adjacent`.
- **Sizes:** centred in its region, `space.20` top offset in a full-screen context.
- **States:** static.
- **Tokens:** icon `color.semantic.text.secondary` · heading `font.size.title.md` · explanation
  `font.size.body.md` / `color.semantic.text.secondary` (6.45:1).
- **Behaviour:** always carries **three parts** — what is empty, why, what to do
  (`CONTENT_DESIGN.md` §12). A filter-induced empty state always offers to clear the filter. An
  "all-clear" state is framed positively: "Tidak ada cucian menumpuk. Semua pesanan siap sudah
  diambil."
- **Keyboard:** actions are focusable; focus moves to the region when it replaces loaded content.
- **Screen reader:** the heading is a real heading; the explanation is read; actions are named.
- **Validation:** n/a. **Loading:** an empty state never shows while loading — a skeleton does.
- **Disabled:** n/a.
- **Error:** an error is CMP-054, not an empty state. The two are distinct: empty means "nothing here";
  error means "we could not find out".
- **Privacy:** an empty state never reveals that a record exists but is out of scope. "Tidak ada hasil"
  is identical in both cases (`CONTENT_DESIGN.md` §7).
- **Platform:** all.
- **Prohibited:** a bare "Tidak ada data" · humour · an illustration implying user error · animated
  illustration (`MOTION_AND_REDUCED_MOTION.md` §6) · showing an empty state where a skeleton belongs ·
  revealing out-of-scope existence.
- **Requirements:** `CONTENT_DESIGN.md` §12; Rule 21.

## CMP-052 — Loading State

- **Purpose:** Indicate that content or an operation is in progress.
- **Anatomy:** spinner or progress indicator · optional message · optional cancel control.
- **Variants:** `inline` (within a control) · `region` (within a content area) · `blocking` (rare, and
  never in the Ops app where the work can queue).
- **Sizes:** inline 20 dp · region 32 dp · blocking 48 dp.
- **States:** loading, succeeded, failed.
- **Tokens:** spinner `color.semantic.primary` · message `font.size.body.sm` /
  `color.semantic.text.secondary`.
- **Behaviour:** **appears only after 400 ms of waiting.** Prefer a skeleton (CMP-053) for content and
  a spinner for actions. **Long operations state what is happening**, not merely that something is.
- **Keyboard:** a cancel control, where present, is focusable.
- **Screen reader:** announced politely as busy; completion announced; **not announced repeatedly**.
- **Validation:** n/a. **Disabled:** n/a.
- **Error:** on failure the loading state is replaced by an error state (CMP-054), never left
  spinning.
- **Privacy:** the message never reveals scope or record existence.
- **Platform:** all. **On Ops Android a blocking loader is prohibited where the operation can queue
  offline** — the user continues and the work syncs (Rule 07).
- **Prohibited:** a spinner before 400 ms · an indefinite spinner with no timeout and no error path ·
  a blocking loader on the Ops app for a queueable operation · **a loading state that hides a queued
  offline operation** · rotation under reduced motion (`MOTION_AND_REDUCED_MOTION.md` §5).
- **Requirements:** Rule 07; NFR-030; SC 2.2.1.

## CMP-053 — Skeleton

- **Purpose:** Preserve layout while content loads, avoiding a jump on arrival.
- **Anatomy:** grey blocks matching the shape and position of the content they stand in for.
- **Variants:** `text-line` · `block` · `circle` · `card` · `table-row`.
- **Sizes:** matches the target content exactly.
- **States:** shimmering, static (reduced motion), replaced.
- **Tokens:** `color.neutral.100` blocks · shimmer 1400 ms, `easing.linear`, opacity 0.5–1.0 ·
  matching radius.
- **Behaviour:** shown after 400 ms. **The layout must not shift when real content arrives** — the
  skeleton reserves the true dimensions. Never shown for more than a few seconds without falling back
  to an error state.
- **Keyboard:** not focusable.
- **Screen reader:** hidden; the region announces "Memuat" once via a live region rather than exposing
  the skeleton shapes.
- **Validation / Disabled:** n/a.
- **Error:** replaced by CMP-054 on failure.
- **Privacy:** a skeleton never hints at the shape or count of data the viewer may not see.
- **Platform:** all. On the tracking portal the shimmer is within budget only as an opacity pulse; no
  moving gradient sweep.
- **Prohibited:** **shimmer under reduced motion** (it becomes static) · a skeleton whose dimensions
  differ from the real content · a skeleton on a money value that then changes (implies uncertainty
  about a definite figure) · a skeleton persisting indefinitely.
- **Requirements:** `MOTION_AND_REDUCED_MOTION.md` §4.3, §5; NFR-025.

## CMP-054 — Error State

- **Purpose:** Explain that something failed and what to do about it.
- **Anatomy:** icon (`size.icon.xl`) · heading naming what failed · explanation of the recovery ·
  primary action (usually retry) · optional secondary action · optional reference code in caption
  style.
- **Variants:** `region` · `full-screen` · `inline` · `permission-denied` · `offline`.
- **Sizes:** centred in its region.
- **States:** static; the retry action has its own loading state.
- **Tokens:** icon `color.semantic.danger` · heading `font.size.title.md` · explanation
  `font.size.body.md` / `color.semantic.text.secondary`.
- **Behaviour:** **every error names what failed and what to do next.** "Terjadi kesalahan" alone is a
  defect (Master Source §18.2 rule 4). "Coba lagi" appears only where retrying can actually help. A
  reference code may accompany the message but is never the message.
- **Keyboard:** actions are focusable; focus moves to the error region when it replaces content.
- **Screen reader:** role alert; the heading is a real heading; announced assertively.
- **Validation:** n/a. **Loading:** the retry action shows a spinner.
- **Disabled:** n/a.
- **Privacy:** **never contains a stack trace, an exception name, an HTTP status, a database error, a
  token, an OTP, a credential, or unmasked personal data.** **It never reveals whether a record
  exists** — the non-enumeration rule (`CONTENT_DESIGN.md` §7).
- **Platform:** all four surfaces.
- **Prohibited:** a bare "Terjadi kesalahan" · a raw error code as the message · a stack trace · a
  retry that cannot help · an error that reveals record existence · an error blaming the user · a
  truncated error message · **an order cancelled, blocked, or failed because a notification failed**
  (Rule 08 rule 8, Rule 19 rule 11).
- **Requirements:** Master Source §18.2 rule 4; Rule 03; Rule 08 rule 8; Rule 21; SC 3.3.1, 3.3.3.

---

# Offline and sync

## CMP-055 — Offline Banner

- **Purpose:** Make offline mode and the pending queue visible at all times in the Ops app.
- **Anatomy:** cloud-slash icon · mode statement · **queue depth when non-zero** · optional link to the
  queue view.
- **Variants:** `offline` · `offline-with-queue` · `reconnecting`.
- **Sizes:** full width; 48 dp minimum, growing with text.
- **States:** hidden (online, empty queue), offline, offline-with-queue, reconnecting, syncing.
- **Tokens:** background `color.semantic.neutral.subtle` · text and icon `color.semantic.offline`
  (6.80:1) · a hairline bottom border.
- **Behaviour:** **persistent while offline — it is not dismissible**, because offline state must be
  visible at all times (Master Source §18.2 rule 5, NFR-030). Copy: "Mode offline — 3 transaksi
  menunggu dikirim". **Offline is presented as a mode, never as an error.** The queue depth is always
  stated when non-zero. **It never promises a sync time.**
- **Keyboard:** the queue link is focusable.
- **Screen reader:** role status, announced politely on entering offline mode and when the queue depth
  changes materially.
- **Validation:** n/a. **Loading:** the reconnecting state shows a static indicator under reduced
  motion.
- **Disabled:** n/a.
- **Error:** a failed sync is reported here and in the sync indicator (CMP-056), and never silently
  dropped.
- **Privacy:** the queue depth is a count only; it never names customers or amounts in the banner.
- **Platform:** **Ops Android primarily** (the offline-first surface). Customer Android shows a
  simplified indicator. Console shows a connection banner. The portal shows a simple retry.
- **Prohibited:** dismissal while offline · offline framed as an error · a full-screen blocking error on
  connectivity loss · hiding the queue depth · promising a sync time · a queued financial operation
  described as complete.
- **Requirements:** NFR-030, NFR-029; Master Source §18.2 rule 5; Rule 07 rules 2, 4.

## CMP-056 — Sync Indicator

- **Purpose:** Show the current synchronisation state and provide the route to the queue.
- **Anatomy:** state icon · optional count badge · tappable target opening the queue view.
- **Variants:** `app-bar` (compact) · `inline` (per item, as a chip).
- **Sizes:** 48 dp target in the app bar; chip 32 dp visual with a 48 dp target.
- **States:** synced, pending, in-progress, failed, conflict, offline.
- **Tokens:** per `UX_COPY_GLOSSARY.md` §6 and `COLOR_AND_CONTRAST.md` §8.4 — `SYNC_PENDING` and
  `SYNC_IN_PROGRESS` `color.semantic.syncing` (7.86:1) · `SYNC_FAILED` `color.semantic.danger` ·
  `SYNC_CONFLICT` `color.semantic.conflict` (7.31:1) · `OFFLINE` `color.semantic.offline`.
- **Behaviour:** **always present in the Ops app app bar.** Rotates only in `SYNC_IN_PROGRESS`, and is
  **static under reduced motion** with the text "Sedang Disinkronkan". Activating it opens the queue
  view. Each state carries its Indonesian label — the icon never stands alone.
- **Keyboard:** focusable; Enter opens the queue.
- **Screen reader:** the accessible name states the current state and count: "Sinkronisasi: 3 transaksi
  menunggu". A move to `SYNC_CONFLICT` is announced **assertively** — it needs a human decision.
- **Validation:** n/a. **Loading:** the in-progress state is itself the loading state.
- **Disabled:** n/a — the indicator is always meaningful.
- **Error:** `SYNC_FAILED` shows the count and routes to the queue; **a failed operation is never
  silently dropped** (Rule 07).
- **Privacy:** counts only; no customer or amount in the indicator itself.
- **Platform:** **Ops Android primarily.** Customer Android simplified. Not on Console or the portal.
- **Prohibited:** hiding the indicator · an icon with no label anywhere in its presentation · rotation
  under reduced motion · a failed operation dropped without a visible trace · a conflict announced
  politely rather than assertively.
- **Requirements:** NFR-030; Rule 07 rules 2, 3, 5, 9; Rule 20 rule 19.

## CMP-057 — Conflict Panel

- **Purpose:** Present a synchronisation conflict and let a human resolve it. **A financial-integrity
  safety component.**
- **Anatomy:** conflict summary · **device value** and **server value** shown side by side with equal
  visual weight · the field in conflict · timestamps for both · resolution controls · optional reason
  field · audit note.
- **Variants:** `financial` (payment amount — the highest-stakes case) · `non-financial` ·
  `bulk-list` (multiple conflicts).
- **Sizes:** comfortable density; full-screen on Android.
- **States:** default, focus, resolving, resolved, error, permission denied.
- **Tokens:** container `color.semantic.conflict.subtle` with a `color.semantic.conflict` border
  (6.93:1 for text on subtle) · both values in numeric-emphasis, tabular · the "Perlu Diperiksa" badge.
- **Behaviour:**
  1. **Neither value is pre-selected.** There is no default winner, no highlighted recommendation, and
     no auto-resolution (Rule 07 rule 5).
  2. **Both values are shown in full**, with their timestamps and origins, at equal weight.
  3. **The server is the final source of truth**, and the panel states that resolution reconciles to
     the server's record (Rule 07 rule 6) — but the human decides what the correct value *is*.
  4. **A financial conflict requires a recorded reason** before resolution.
  5. Resolution is an audited action recording actor, timestamp, both values, and the choice.
  6. **The panel cannot be dismissed without a decision** — it is a blocking layer for financial
     conflicts.
- **Keyboard:** focus trapped for financial conflicts · Tab cycles · **Escape does not resolve
  anything** and does not close a financial conflict panel.
- **Screen reader:** role alertdialog · both values announced in full as complete amounts · **announced
  assertively** on appearance.
- **Validation:** a required reason validates before resolution.
- **Loading:** resolution controls show a spinner and become non-interactive.
- **Disabled:** resolution stays disabled until a choice and any required reason exist.
- **Error:** shown inside; **the conflict remains unresolved** rather than resolving into an ambiguous
  state.
- **Privacy:** shows amounts and the order to an authorised operator; never a token or an OTP.
- **Platform:** **Ops Android primarily.** Console for supervisory review.
- **Prohibited:** **silent or automatic resolution of a payment conflict** · a pre-selected or
  recommended value · dismissal without a decision on a financial conflict · resolution without an
  audit entry · either value truncated or abbreviated · Escape resolving.
- **Requirements:** Rule 07 rules 5, 6; Rule 04 rules 3, 8, 12; Rule 18 invariants 12, 25; Rule 20
  rules 12, 16.

---

# Process and evidence

## CMP-058 — Timeline

- **Purpose:** Show the ordered history of an order's status changes.
- **Anatomy:** vertical sequence of entries · each with status icon, label, timestamp, and optional
  actor or note · connectors · a distinct marker for the current status.
- **Variants:** `full` (all entries) · `compact` (recent entries plus an expander) · `public`
  (portal, reduced).
- **Sizes:** entries 56 dp minimum; comfortable density on the portal.
- **States:** default, loading, empty, error, expanded, collapsed.
- **Tokens:** icons and colours per status (`COLOR_AND_CONTRAST.md` §8, `ICONOGRAPHY.md` §4) ·
  connector `color.semantic.border` · current entry emphasised by weight and a marker, **not by colour
  alone**.
- **Behaviour:** **vertical, always** — never a horizontal stepper, which cannot hold fifteen statuses
  at 320 px (`RESPONSIVE_FOUNDATION.md` §3). Newest first or oldest first is fixed per surface and
  stated. Timestamps display in the **outlet's local time**, with the timezone stated where ambiguous.
  **A return to `REWORK` and a second `READY_FOR_PICKUP` both appear as distinct entries, and the
  original first-ready timestamp remains visible and unchanged** (Rule 10, Rule 18 invariant 17).
- **Keyboard:** the expander is focusable; entries are focusable only if actionable.
- **Screen reader:** a real ordered list; each entry announced as label plus timestamp; the current
  status identified as current.
- **Validation:** n/a. **Loading:** skeleton entries.
- **Empty:** rare; a `DRAFT` order shows a single entry.
- **Error:** inline with retry.
- **Privacy:** **the public variant shows status and timestamps only.** No actor identity beyond
  operational necessity, no internal notes, no address, no photographs (Master Source §9.3, FR-089).
- **Platform:** all four surfaces; the portal uses the reduced public variant.
- **Prohibited:** a horizontal stepper · the first-ready timestamp mutated or hidden after a rework ·
  internal notes or staff identity on a public surface · current status by colour alone · raw canonical
  identifiers rendered.
- **Requirements:** FR-071, FR-089; Rule 10; Rule 18 invariant 17; Master Source §9.3; NFR-026.

## CMP-059 — Stepper

- **Purpose:** Guide a user through a bounded, sequential task.
- **Anatomy:** step indicators with numbers and labels · current-step content · back and next controls
  · optional progress summary.
- **Variants:** `horizontal` (≤ 4 steps, medium and above) · `vertical` (Android) ·
  `progress-only` (compact — "Langkah 2 dari 4").
- **Sizes:** indicators 32 dp with 48 dp targets when navigable.
- **States:** upcoming, current, completed, error, disabled, loading.
- **Tokens:** completed `color.semantic.success` with a check icon · current
  `color.semantic.primary` with a filled indicator · upcoming `color.semantic.neutral` · error
  `color.semantic.danger` with an alert icon.
- **Behaviour:** **entered data persists when moving back and forward.** A step with an error is marked
  and reachable. State is never lost to a rotation or a background/foreground cycle.
- **Keyboard:** Tab through the current step's content; back and next are focusable; completed steps are
  reachable where revisiting is permitted.
- **Screen reader:** the position announced ("Langkah 2 dari 4") · each step's state announced ·
  moving to a new step moves focus to its heading.
- **Validation:** the current step validates before advancing; errors are announced and focused.
- **Loading:** the next control shows a spinner.
- **Disabled:** next disables until the step is valid, with the reason available.
- **Error:** the step indicator carries an error state **and** the field-level errors are shown.
- **Privacy:** partial data entered in a stepper is protected as the final record would be.
- **Platform:** Android (vertical) and Console (horizontal). **Not on the portal.**
- **Prohibited:** more than 4 steps in the horizontal variant at compact width · losing entered data on
  back navigation or rotation · step state by colour alone · a stepper for a non-sequential task (use
  tabs) · a stepper on the portal.
- **Requirements:** NFR-025, NFR-026; SC 1.3.4, 3.3.1.

## CMP-060 — Progress Indicator

- **Purpose:** Show progress toward a known end.
- **Anatomy:** track · fill · optional percentage or count label.
- **Variants:** `linear-determinate` · `linear-indeterminate` · `circular-determinate` ·
  `circular-indeterminate`.
- **Sizes:** linear 4 dp track height; circular 20 / 32 / 48 dp.
- **States:** determinate, indeterminate, complete, error, paused.
- **Tokens:** track `color.semantic.neutral.subtle` · fill `color.semantic.primary` · error fill
  `color.semantic.danger` · radius `radius.xs` on linear ends.
- **Behaviour:** **determinate whenever a total is known.** Indeterminate is used only when it genuinely
  is not. Width transitions at `duration.normal`; under reduced motion the value updates in discrete
  steps with no tween, and indeterminate rotation stops.
- **Keyboard:** not focusable unless it carries a cancel control.
- **Screen reader:** role progressbar with min, max, and current value · announced at meaningful
  intervals, **not on every tick**.
- **Validation:** n/a.
- **Disabled:** n/a. **Error:** the fill turns to the danger token **and** an error message appears —
  never colour alone.
- **Privacy:** a progress label never reveals record counts outside the viewer's scope.
- **Platform:** all.
- **Prohibited:** indeterminate where a total is known · progress announced on every tick · error by
  colour alone · rotation under reduced motion · a progress bar implying a completion time the system
  does not know.
- **Requirements:** NFR-026; `MOTION_AND_REDUCED_MOTION.md` §5.

## CMP-061 — Attachment Uploader

- **Purpose:** Attach a file or image to a record.
- **Anatomy:** drop zone or picker control · file-type and size guidance · selected-file list with
  per-file progress · per-file remove control · error region.
- **Variants:** `single` · `multiple` · `image-only` · `camera-capture` (see CMP-062).
- **Sizes:** drop zone minimum 120 dp tall; file rows 56 dp.
- **States:** empty, dragging-over, selected, uploading, succeeded, failed, disabled, read-only,
  offline, queued, permission denied.
- **Tokens:** drop zone `border.width.thin` dashed `color.semantic.border.interactive` · progress per
  CMP-060 · success and error per the semantic set.
- **Behaviour:** **a click or tap picker is always available — drag is never the only route**
  (SC 2.5.7). Type and size limits are stated **before** selection. Each file shows its own progress
  and its own outcome. **Offline, an attachment queues with a persisted `client_reference` and its
  state is visible** (Rule 07).
- **Keyboard:** the picker is fully keyboard operable; each remove control is a separate tab stop.
- **Screen reader:** the constraints are in the associated description · each file announces its name,
  size, and state · completion and failure are announced.
- **Validation:** **type, size, and content are validated server-side; the client check is a
  convenience, never the control** (Rule 03 rule 12). A client-declared content type is untrusted.
- **Loading:** per-file progress; the form remains usable.
- **Disabled:** with a reason. **Read-only:** existing attachments viewable, no add or remove.
- **Error:** per file, stating the reason and the recovery. A failed file remains listed with a retry —
  never silently dropped.
- **Privacy:** **uploaded files are private data stored in private object storage and served only
  through signed, expiring URLs. Object keys are tenant-scoped and unguessable** (Rule 03 rule 13,
  Rule 06 rules 16, 17). **No file is ever publicly readable or listable.**
- **Platform:** Ops Android and Console. Not on the portal.
- **Prohibited:** drag as the only route · client-side validation treated as the control · a public or
  guessable object URL · a predictable or sequential object key · a failed upload dropped silently ·
  removal of a queued file without an explicit action.
- **Requirements:** Rule 03 rules 12, 13, 14; Rule 06 rules 15, 16, 17; Rule 07; SC 2.5.7.

## CMP-062 — Photo Evidence

- **Purpose:** Capture and display a photograph as operational evidence — proof of pickup or delivery,
  a laundry condition record, an issue report.
- **Anatomy:** capture control (64 dp on courier surfaces) · preview thumbnail · full view · capture
  timestamp · optional caption · retake control · privacy notice.
- **Variants:** `capture` · `gallery` · `single-view` · `proof` (custody transfer).
- **Sizes:** capture control 64 dp · thumbnail 88 dp · full view fits the screen.
- **States:** empty, capturing, captured, uploading, queued, uploaded, failed, read-only, expired-url,
  permission denied, offline.
- **Tokens:** thumbnail `radius.sm` with a hairline border · a lock icon indicating private storage ·
  progress per CMP-060.
- **Behaviour:** **capture works fully offline**, queues with a persisted `client_reference`, and
  synchronises without duplication (FR-107, Rule 07 rules 1, 9). The capture timestamp is recorded;
  **server timestamps are authoritative for ordering** (Rule 20 rule 11). For a custody transfer, **proof
  is mandatory before completion** (Rule 09 rule 2).
- **Keyboard:** capture, retake, and view are focusable and named.
- **Screen reader:** the alternative text describes **the artefact and its time** — "Foto bukti
  pengiriman, 19 Juli 2026 14:30" — and **never the customer's address, name, or belongings**
  (`ACCESSIBILITY.md` §16 rule 4).
- **Validation:** presence validated before a custody transfer completes; the block is explained.
- **Loading:** per-image upload progress; the queued state is visible.
- **Disabled:** with a reason. **Read-only:** view only.
- **Error:** stated with a retry; a queued image is never dropped.
- **Privacy:** **laundry photographs are RESTRICTED data** (Rule 21 anchor 16). They may show the
  inside of a customer's home or their personal garments. Therefore:
  1. stored in **private object storage**, never publicly readable or listable;
  2. served **only through signed, expiring URLs** (Rule 03 rule 13, Rule 09 rule 3);
  3. **tenant-scoped with unguessable object keys** (Rule 06 rule 17);
  4. **never shown on the public tracking portal** (Master Source §17.2 rule 3);
  5. **never used for marketing**;
  6. **never cached to shared or public device storage**;
  7. an expired URL produces a re-fetch, never a broken image and never a fallback to a public URL.
- **Platform:** Ops Android (courier and production) · Console (review) · Customer Android (own orders,
  signed URL) · **never the public portal**.
- **Prohibited:** **a photograph on any public surface** · a public or guessable object URL · caching to
  shared device storage · use in marketing · a custody transfer completed without required proof · alt
  text containing personal data · a queued image silently dropped.
- **Requirements:** FR-107; Rule 03 rules 12, 13, 14; Rule 09 rules 2, 3; Rule 06 rules 16, 17;
  Rule 21 anchor 16; Master Source §17.2 rule 3.

## CMP-063 — Signature Capture Specification

- **Purpose:** Capture a recipient's signature as proof of a custody transfer.
- **Anatomy:** signature canvas · guide line · clear control · recipient-name field · confirm control ·
  privacy notice.
- **Variants:** `pickup-proof` · `delivery-proof`.
- **Sizes:** canvas minimum 240 dp tall, full width; controls 64 dp.
- **States:** empty, drawing, drawn, cleared, saving, queued, saved, failed, read-only, offline.
- **Tokens:** canvas `color.semantic.surface` with a `color.semantic.border.strong` boundary (6.80:1,
  sunlight legibility) · guide line `color.semantic.border` · stroke `color.semantic.text.primary`.
- **Behaviour:** captured as a vector or raster artefact with the recipient's name and a timestamp.
  **The recipient name is captured alongside the signature and is never optional** — a signature alone
  does not identify the recipient. Works fully offline and queues with a persisted `client_reference`.
  Landscape may be requested but **portrait must remain functional** (SC 1.3.4).
- **Keyboard:** **a signature cannot be drawn by keyboard.** Therefore an **alternative proof method is
  always available** — OTP (CMP-064), photograph (CMP-062), or recorded recipient name — per the
  tenant's configured policy. Signature is never the only permitted proof method for a user who cannot
  draw (SC 2.1.1, SC 2.5.7).
- **Screen reader:** the canvas is described; the alternative proof route is announced; the recipient
  name field is a normal labelled field.
- **Validation:** a non-empty signature **and** a recipient name are required before confirmation.
- **Loading:** the confirm control shows a spinner; the artefact queues offline.
- **Disabled:** with a reason. **Read-only:** a captured signature is displayed, never re-editable.
- **Error:** stated with a retry; a queued signature is never dropped.
- **Privacy:** **a signature is personal data and RESTRICTED.** It is stored in private object storage,
  served only via signed expiring URLs, tenant-scoped with unguessable keys, and **never shown on the
  public tracking portal** (Rule 09 rule 3). Legal retention periods for proof artefacts are **outside
  Step 2's authority** (`DESIGN_DECISION_LOG.md` §4) and are recorded as an open question.
- **Platform:** Ops Android and the external courier guest link.
- **Prohibited:** signature as the only available proof method · a signature without a recipient name ·
  a captured signature that remains editable · a signature on a public surface · a public or guessable
  URL · orientation locked with no portrait alternative.
- **Requirements:** Rule 09 rules 2, 3, 9; Rule 03 rule 13; Rule 07; SC 1.3.4, 2.1.1, 2.5.7.

## CMP-064 — OTP Proof Specification

- **Purpose:** Verify a custody transfer by a one-time code the recipient supplies.
- **Anatomy:** instruction · OTP field (CMP-011) · resend control with cooldown · alternative-proof
  route · error region.
- **Variants:** `delivery-proof` · `pickup-proof`.
- **Sizes:** comfortable density; field `size.control.lg` (56).
- **States:** awaiting-send, sent, entering, verifying, verified, failed, expired, locked-out, offline,
  disabled.
- **Tokens:** as CMP-011.
- **Behaviour:** the code is sent to the recipient's registered contact and entered by the courier or
  the recipient. **Verification is server-side, always** — a client never decides that a code is
  correct (Rule 03 rule 2). **Offline, OTP verification cannot complete**; the interface says so
  plainly and offers the configured alternative proof method rather than failing the delivery.
- **Keyboard:** as CMP-011.
- **Screen reader:** as CMP-011; the alternative route is announced.
- **Validation:** server-side only.
- **Loading:** the field becomes read-only with a spinner during verification.
- **Disabled:** during cooldown or lockout, with the remaining time stated.
- **Error:** "Kode salah atau kedaluwarsa. Minta kode baru." **Attempt limiting is communicated without
  revealing whether the contact exists.**
- **Privacy:** **the OTP is SECRET class. It is never logged, never echoed, never announced back, never
  placed in an error message or a live region, never included in telemetry, and never committed
  anywhere** (Rule 03 rule 20, Rule 21 rule 18). Rate limiting and brute-force protection apply
  (Rule 03 rules 15, 16).
- **Platform:** Ops Android and the external courier guest link.
- **Prohibited:** client-side verification · the code echoed or logged · an error revealing contact
  existence · OTP as the only proof method when the device is offline · blocking paste · a CAPTCHA
  alongside it.
- **Requirements:** Rule 03 rules 2, 15, 16, 20; Rule 09 rule 2; Rule 21 rule 18; SC 3.3.8.

## CMP-065 — Map Preview Specification

- **Purpose:** Show a location for a pickup or delivery job.
- **Anatomy:** map surface · destination marker · optional courier position · optional stop list ·
  external-navigation handoff control · fallback text address block.
- **Variants:** `job-detail` · `route-overview` (a list of stops, **not** a drawn route) · `static-image`
  (low-bandwidth fallback).
- **Sizes:** minimum 200 dp tall; never the primary content of a screen.
- **States:** loading, loaded, failed, offline, unavailable, permission denied.
- **Tokens:** container `radius.md` with a hairline border · marker `color.semantic.primary` · the
  courier position visually distinct from the destination and labelled.
- **Behaviour:**
  1. **The map is always optional and never blocking.** It loads **after** the job's textual content,
     and its failure never prevents the courier from working (`DESIGN_PRINCIPLES.md` P12).
  2. **A text fallback is always present** — the destination as text, at the precision the job
     requires.
  3. **Offline, the map is unavailable and says so**; the text destination remains.
  4. **The stop order is a suggestion.** Where multiple stops are shown, they are a labelled list
     headed "Usulan urutan kunjungan" with the note "Urutan ini saran, bukan rute tercepat."
- **Keyboard:** the map is not the only route to any information; the external-navigation handoff and
  the text address are focusable.
- **Screen reader:** the map is **decorative and hidden**; the destination text and stop list carry the
  information. A map is never the sole source of an address a courier needs.
- **Validation:** n/a. **Loading:** a skeleton block; the text content is already present.
- **Disabled:** unavailable states are explained. **Error:** the map region shows a brief message; the
  job remains fully workable.
- **Privacy:** **the destination is shown at the precision the job genuinely requires and no more**
  (Rule 09 rule 6). **The map never displays customer history, other orders, or another assignment.**
  In the guest-courier variant it is tenant-scoped and shows only the one assignment. The address is
  never rendered in a shareable or indexable form. **The map is never shown on the public tracking
  portal.**
- **Platform:** Ops Android and the guest link. **Never on the public portal.** Console may show a
  static location on an outlet record.
- **Prohibited:** **a drawn "optimal route" or any optimisation claim** (Rule 09 rule 1) · a guaranteed
  arrival time or ETA the system does not compute · the map as the only source of the destination · a
  map blocking the job flow · a map on the public portal · customer history or other orders on the map
  · address precision beyond what the job requires.
  **The map provider itself is not selected in Step 2** (`DESIGN_DECISION_LOG.md` §4).
- **Requirements:** Rule 09 rules 1, 6, 7; Master Source §9.3; NFR-028; `DESIGN_PRINCIPLES.md` P2, P12.

---

# Analytics

## CMP-066 — Chart

- **Purpose:** Show a measure visually where a chart genuinely answers the question better than a
  number or a table.
- **Anatomy:** title · plot area · axes with labels · direct series labels or a legend with patterns ·
  **text alternative** · **accessible data table** (in a "Lihat data" expander).
- **Variants:** `line` · `bar-vertical` · `bar-horizontal` · `stacked-bar` · `grouped-bar` ·
  `sparkline`.
- **Sizes:** minimum 240 dp tall at compact; **never wider than 800 dp**
  (`DATA_VISUALIZATION.md` §8).
- **States:** default, loading, empty, error, no-data, read-only, permission denied.
- **Tokens:** series palette per `DATA_VISUALIZATION.md` §4 (all ≥ 5.79:1) · axis
  `color.semantic.border.strong` · grid `color.semantic.border`, horizontal only · labels
  `font.size.label.sm`.
- **Behaviour:** **maximum 4 series.** Every series carries a non-colour encoding — a direct label, a
  dash pattern, or a marker shape. **Money bar charts start at zero. Money is never abbreviated on an
  axis.**
- **Keyboard:** any interaction is keyboard operable; the data-table expander is focusable.
- **Screen reader:** the chart itself is described by its **text alternative**, which states what it
  shows and its key finding — not its shape. The **accessible data table is mandatory** and is visible
  to sighted users too, not screen-reader-only.
- **Validation:** n/a. **Loading:** a skeleton preserving the plot dimensions.
- **Empty:** an explanation, not an empty axis frame.
- **Error:** explicit; **an unavailable value shows "—", never "0"** (RPT-003).
- **Privacy:** tenant-scoped. **A portfolio chart aggregates only across tenants the user legitimately
  belongs to and names which are included** (Rule 02 hard rule 13).
- **Platform:** Console Web primarily; a reduced form on Android. **Never on the public portal.**
- **Prohibited:** **any 3D chart** · **dual axis without a recorded justification** · more than 4 series
  · series by colour alone · **no text alternative or data table** · abbreviated money · a truncated
  axis on a money bar chart · a drawn trend line or forecast the product does not compute · animated
  transitions · a chart as the sole source of an actionable money value · a chart on the portal ·
  green-up/red-down applied without regard to whether the change is favourable.
- **Requirements:** RPT-001, RPT-002, RPT-003, RPT-004; Rule 02 hard rule 13; NFR-026;
  `DATA_VISUALIZATION.md`.

## CMP-067 — KPI Card

- **Purpose:** Present a single decision-relevant figure prominently. Preferred over a chart wherever
  it answers the question.
- **Anatomy:** label · value · optional comparison with baseline · optional sparkline · optional info
  affordance explaining what the metric counts and its period.
- **Variants:** `value-only` · `with-comparison` · `with-sparkline` · `financial`.
- **Sizes:** 1 per row compact · 2 medium · 4 expanded.
- **States:** default, loading, empty, unavailable, error, read-only, permission denied.
- **Tokens:** container `radius.lg`, hairline border, `elevation.0` · value numeric-emphasis at
  `font.size.headline.md`, tabular · label `font.size.label.md` / `text.secondary` · comparison
  `font.size.body.sm`.
- **Behaviour:** per `DATA_VISUALIZATION.md` §7. **The value is never truncated and never abbreviated.**
  A comparison always states its baseline ("dari minggu lalu"). **Direction is never colour-only** — an
  arrow glyph and the word "naik" or "turun" accompany it. **Up is not automatically good**: an
  increase in unpaid balance is unfavourable, and the card's colour reflects favourability, not
  direction.
- **Keyboard:** focusable only when the whole card is a link, in which case it is a real button with an
  accessible name.
- **Screen reader:** label, value, and comparison announced as one coherent statement.
- **Validation:** n/a. **Loading:** a skeleton preserving the card dimensions.
- **Empty / unavailable:** **"—" with an explanation, never "0" and never blank.** Zero and unknown are
  different facts (RPT-003).
- **Error:** an inline message with a retry.
- **Privacy:** tenant-scoped; the portfolio variant names the tenants included.
- **Platform:** Console Web primarily; a reduced form on Android. Not on the portal.
- **Prohibited:** an abbreviated or truncated value · a comparison with no stated baseline · direction
  by colour alone · blanket green-up/red-down · "0" standing in for unknown · a money figure computed
  client-side rather than read from the financial record.
- **Requirements:** RPT-001, RPT-002, RPT-003; Rule 04; NFR-026; `DATA_VISUALIZATION.md` §7.

---

# Controls

## CMP-068 — Filter Bar

- **Purpose:** Narrow a list or table, and make the active narrowing visible.
- **Anatomy:** filter controls (chips, dropdowns, date range) · active-filter chips · clear-all control
  · result count.
- **Variants:** `inline` (Console) · `button-and-sheet` (Android and tight vertical space) ·
  `persistent-sidebar` (Console, wide).
- **Sizes:** inline height 56 dp; collapses to a filter button above 64 dp of vertical consumption.
- **States:** default, focus, active-filters, no-filters, loading, error, disabled.
- **Tokens:** active filter chips per CMP-021 selected state · result count
  `color.semantic.text.secondary`.
- **Behaviour:** **active filters are always visible as removable chips** — a user must never be
  confused about why a list looks short. The result count updates and is announced. Filter state
  survives navigation and is reflected in the URL on Console Web. A clear-all control is present
  whenever any filter is active.
- **Keyboard:** each control and each active-filter chip is a tab stop; chips are removable by Delete.
- **Screen reader:** the region has an accessible name · the active filter count and the result count
  are announced politely on change.
- **Validation:** a date range validates that the end is not before the start.
- **Loading:** the result region shows a skeleton; the bar stays interactive.
- **Disabled:** individual filters disable with a reason.
- **Error:** an inline message with a retry; existing filters are preserved.
- **Privacy:** **filter options are tenant-scoped.** An outlet, staff, or customer filter never lists
  another tenant's records (Rule 02). Filtering never becomes an enumeration mechanism: an empty result
  is identical whether nothing matches or the matches are out of scope.
- **Platform:** Console Web and Ops Android. Not on the portal.
- **Prohibited:** hidden active filters · a filtered empty state that does not offer to clear the filter
  · cross-tenant filter options · an unannounced result-count change · a filter bar consuming more than
  64 px of vertical space at the 1366 × 768 baseline.
- **Requirements:** FR-057; Rule 02 hard rule 8; `RESPONSIVE_FOUNDATION.md` §4; NFR-026.

## CMP-069 — Bulk Action Bar

- **Purpose:** Act on a set of selected records.
- **Anatomy:** selection count · select-all / clear-selection controls · permitted action controls.
- **Variants:** `standard` · `contextual-app-bar` (Android).
- **Sizes:** height 56 dp; appears only when a selection exists.
- **States:** hidden, visible, loading, error, partial-selection, permission denied.
- **Tokens:** surface `color.semantic.surface.inverse` with `text.inverse` (14.33:1) · `elevation.2`.
- **Behaviour:** appears only with a non-empty selection and **always states the count** — "12 pesanan
  dipilih". "Select all" distinguishes the current page from the entire result set and says which it
  did. Selection survives pagination. Escape or a clear control empties the selection.
- **Keyboard:** all controls are tab stops; Escape clears the selection.
- **Screen reader:** the selection count is announced on change; each action is named with its scope
  ("Ekspor 12 pesanan").
- **Validation:** an action unavailable for part of the selection is explained before it runs, not after.
- **Loading:** actions show a spinner; the selection is preserved.
- **Disabled:** an action the role cannot perform is absent or explained.
- **Error:** a per-record outcome summary; **a partial failure is reported explicitly with which records
  failed and why** — never a bare "some items failed".
- **Privacy:** the selection is tenant-scoped. **An export carries the same access rules as the
  underlying records** (Rule 03).
- **Platform:** Console Web primarily; a contextual app bar on Android.
- **Prohibited:** **any bulk destructive action** (bulk cancel, bulk delete) · **any bulk financial
  action** (bulk refund, bulk payment, bulk void) — these do not exist in this product
  (`PLATFORM_ADAPTATION.md` §4) · an ambiguous "select all" · an unstated selection count · a partial
  failure reported vaguely · a bulk action crossing the tenant boundary.
- **Requirements:** Rule 02 hard rules 8, 12; Rule 03; Rule 04 rules 6, 7.

## CMP-070 — Audit Timeline

- **Purpose:** Show the recorded history of who did what to a record, and when.
- **Anatomy:** chronological entries · each with actor, role, action, timestamp, before/after values
  where applicable, reason where recorded, and origin (surface or system).
- **Variants:** `record-scoped` · `financial` (before/after amounts) · `security` (access,
  impersonation, revocation).
- **Sizes:** compact density permitted on Console; entries 56 dp.
- **States:** default, loading, empty, error, read-only, permission denied, filtered.
- **Tokens:** entries as CMP-058 · amounts in tabular figures · the security variant marks impersonation
  entries with the user-shield icon and `color.semantic.warning`.
- **Behaviour:** **append-only and immutable in presentation.** There is no edit control and no delete
  control, for any role. Entries display in the outlet's local time with the timezone stated where
  ambiguous; **server timestamps are authoritative for ordering** (Rule 20 rule 11).
  **A financial entry shows actor, tenant, outlet, timestamp, before and after amounts, and the reason**
  (Rule 04). **A correction appears as a new reversal or adjustment entry, never as a modification of
  the original** (Rule 04 rule 8).
- **Keyboard:** entries are focusable where expandable; filters are tab stops.
- **Screen reader:** an ordered list; each entry announced as a coherent statement of actor, action,
  and time.
- **Validation:** n/a. **Loading:** skeleton entries.
- **Empty:** "Belum ada aktivitas tercatat."
- **Error:** inline with retry.
- **Read-only:** **always. There is no state in which an audit entry is editable.**
- **Privacy:** **an audit record is CONFIDENTIAL or RESTRICTED depending on its contents**
  (Rule 21 anchor 16). It is tenant-scoped and visible only to roles permitted to see it.
  **It never contains a password, an OTP, a token, or a credential** (Rule 03 rule 20).
  **Support impersonation sessions appear here — who, which tenant, when, for how long, and why —
  because platform support has no silent tenant access** (Rule 03 rules 18, 19).
- **Platform:** Console Web primarily; a reduced form on Ops Android for order-level history.
- **Prohibited:** **any edit or delete affordance** · a hard-deleted financial record · a correction
  shown as a modification rather than a reversal · a secret, token, OTP, or credential in an entry ·
  an impersonation session absent from the log · cross-tenant entries · an entry without an actor and a
  timestamp.
- **Requirements:** Rule 03 rules 18, 19, 20; Rule 04 rules 6, 7, 8; Rule 20 rule 11; Rule 21 anchor 16;
  SUB-017; NFR-050.

---

## Component index

| ID | Component | ID | Component |
|---|---|---|---|
| CMP-001 | Button | CMP-036 | Bottom Navigation |
| CMP-002 | Icon Button | CMP-037 | Navigation Rail |
| CMP-003 | Floating Action Button | CMP-038 | Side Navigation |
| CMP-004 | Link | CMP-039 | Breadcrumb |
| CMP-005 | Text Field | CMP-040 | Tenant Switcher |
| CMP-006 | Search Field | CMP-041 | Outlet Selector |
| CMP-007 | Phone Field | CMP-042 | App Bar |
| CMP-008 | Money Field | CMP-043 | Bottom Sheet |
| CMP-009 | Weight Field | CMP-044 | Dialog |
| CMP-010 | Quantity Field | CMP-045 | Confirmation Dialog |
| CMP-011 | OTP Field | CMP-046 | Drawer |
| CMP-012 | Text Area | CMP-047 | Banner |
| CMP-013 | Dropdown | CMP-048 | Snackbar |
| CMP-014 | Autocomplete | CMP-049 | Toast |
| CMP-015 | Date Picker | CMP-050 | Tooltip |
| CMP-016 | Time Window Picker | CMP-051 | Empty State |
| CMP-017 | Checkbox | CMP-052 | Loading State |
| CMP-018 | Radio | CMP-053 | Skeleton |
| CMP-019 | Switch | CMP-054 | Error State |
| CMP-020 | Segmented Control | CMP-055 | Offline Banner |
| CMP-021 | Chip | CMP-056 | Sync Indicator |
| CMP-022 | Status Badge | CMP-057 | Conflict Panel |
| CMP-023 | Avatar | CMP-058 | Timeline |
| CMP-024 | Customer Card | CMP-059 | Stepper |
| CMP-025 | Order Card | CMP-060 | Progress Indicator |
| CMP-026 | Production Job Card | CMP-061 | Attachment Uploader |
| CMP-027 | Courier Job Card | CMP-062 | Photo Evidence |
| CMP-028 | Tracking Summary Card | CMP-063 | Signature Capture Specification |
| CMP-029 | Payment Summary | CMP-064 | OTP Proof Specification |
| CMP-030 | Receivable Summary | CMP-065 | Map Preview Specification |
| CMP-031 | Receipt Preview | CMP-066 | Chart |
| CMP-032 | List | CMP-067 | KPI Card |
| CMP-033 | Data Table | CMP-068 | Filter Bar |
| CMP-034 | Pagination | CMP-069 | Bulk Action Bar |
| CMP-035 | Tabs | CMP-070 | Audit Timeline |

**Total: 70 components. All NOT IMPLEMENTED.**
