# Form and Validation Patterns — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY. No form and no validation exists.

---

## 1. Position

A form in this product is usually someone standing at a counter with a queue behind them, entering
data that becomes a financial record. Speed and correctness are both mandatory, and where they
conflict, **correctness wins** (`DESIGN_PRINCIPLES.md` P1).

Three rules govern everything below:

1. **The server is authoritative.** Client-side validation is a courtesy that saves a round trip. It is
   never the control (Rule 03, rule 2; Rule 18, rule 1).
2. **Errors explain recovery.** Naming the problem is half the job; naming the fix is the other half
   (Master Source §18.2 rule 4).
3. **Nothing consequential happens by accident.** Destructive and financial actions are separated,
   confirmed, and reasoned (Master Source §18.2 rule 3).

---

## 2. Required and optional

- **Every field is explicitly one or the other.** Ambiguity produces submit-time surprises.
- **The convention is stated once per form**, above the first field: "Kolom bertanda * wajib diisi."
- **Required is indicated in text**, never by colour alone (NFR-026). The asterisk carries a legend, and
  the field is programmatically marked required so a screen reader announces it.
- **Where most fields are required, mark the optional ones instead** — label them "(opsional)". Marking
  fourteen fields required and one optional is noise.
- **Optional fields are collapsed** behind an "Opsi lain" expander in high-frequency flows such as order
  intake, so the fast path stays fast (`DESIGN_PRINCIPLES.md` P4).
- **A field is required because the business needs it**, never because a schema is convenient. A
  required field that staff routinely fill with "-" is a design failure.

---

## 3. Validation timing

| Trigger | What validates | Why |
|---|---|---|
| **On blur** | Format, range, and length of the field just left | The user has finished with the field; correcting now is cheap |
| **On change** | **Only** clearing an error that has just been resolved | Positive feedback is welcome; new accusations mid-typing are not |
| **On submit** | Everything, including cross-field rules and server rules | The authoritative check |
| **On server response** | Anything only the server can know | Availability, uniqueness, entitlement, balance |

### Rules

1. **Never validate on every keystroke** for a new error. Telling someone their phone number is invalid
   after they have typed `08` is hostile and teaches them to ignore the interface.
2. **An existing error clears as soon as the input becomes valid**, without waiting for blur.
3. **Cross-field rules validate on submit**, and on blur of the second field once both are populated —
   a date range, a payment not exceeding a balance.
4. **Async validation never blocks typing.** A trailing spinner appears; the field stays editable.
5. **Validation never runs on a field the user has not yet reached.** No pre-emptive red.
6. **A submit that fails validation never clears entered data.**

---

## 4. Inline versus form-level errors

### Inline errors — the default

- Placed **directly below the field**, replacing its helper text.
- Rendered in `color.semantic.danger` (6.54:1) **with an error icon and the message text** — never
  colour alone (NFR-026).
- Programmatically associated with the field, so a screen reader announces it on focus
  (`ACCESSIBILITY.md` §8).
- The field's border becomes `border.width.thick` in `color.semantic.danger`, **growing inward so the
  layout does not shift** (`SHAPE_BORDER_ELEVATION.md` §3).

### Form-level errors — for what inline cannot carry

Used for: cross-field rules; server rejections not attributable to one field; and the **error summary
on submit**.

The error summary:

1. Appears at the top of the form as a banner (CMP-047) in the danger variant.
2. **States the count**: "3 kolom perlu diperbaiki."
3. **Lists every error, each linking to its field.**
4. **Receives focus** so a keyboard and screen-reader user lands on it immediately.
5. Is announced **assertively** (`ACCESSIBILITY.md` §7).
6. Disappears once all errors are resolved.

### Never

- An error shown only in a toast (CMP-049) — a toast cannot be re-read or acted on.
- An error shown only at the top when it belongs to a field.
- An error shown only at the field when the user cannot see the field without scrolling — the summary
  covers that case.
- A generic "Terjadi kesalahan" (Master Source §18.2 rule 4).

---

## 5. Error message content

Structure: **`[What happened]. [What to do next].`** Optionally a reference code in caption style.

| Bad | Good |
|---|---|
| "Invalid" | "Nomor telepon harus 10–13 digit. Contoh: 081234567890." |
| "Field required" | "Alasan pembatalan wajib diisi." |
| "Error 422" | "Jumlah pembayaran melebihi sisa tagihan Rp45.000. Kurangi jumlahnya." |
| "Terjadi kesalahan" | "Pesanan belum terkirim. Data tersimpan di perangkat dan akan dikirim otomatis saat koneksi kembali." |
| "Value out of range" | "Berat maksimum 50,0 kg per pesanan. Pisahkan menjadi beberapa pesanan." |

Rules: never blame the user · never expose a stack trace, exception name, HTTP status, or database
error · never truncate an error message · never include a token, an OTP, a credential, or unmasked
personal data · never reveal whether a record exists (`CONTENT_DESIGN.md` §7).

---

## 6. Formatting and keyboard type

| Input | Keyboard | Format applied | Autocomplete hint |
|---|---|---|---|
| Text | default | none | as appropriate |
| Name | default, word capitalisation | none | name |
| Phone (CMP-007) | telephone | on blur, normalised | tel |
| Money (CMP-008) | numeric | thousands `.` as typed, `Rp` prefix fixed | off |
| Weight (CMP-009) | decimal numeric | one decimal, comma separator, on blur | off |
| Quantity (CMP-010) | numeric | integer only | off |
| OTP (CMP-011) | numeric | none; paste always permitted | one-time-code |
| Date (CMP-015) | default with a picker | `19 Juli 2026`, accepts `19/07/2026` | as appropriate |
| Address | default, multiline | none | street-address |
| Search (CMP-006) | default with a search action | none | off |
| Notes (CMP-012) | default, sentence capitalisation | none | off |

**Formatting never fights the user.** Separators are inserted as typed for money because that aids
comprehension of a large number; the decimal comma for weight is applied on blur because applying it
mid-entry interferes with typing.

---

## 7. Money input — the strictest field

Governed by Rule 04 and CMP-008.

1. **Integer Rupiah only.** No decimal separator is accepted, offered, or displayed. The smallest unit
   is one Rupiah.
2. **Floating point never appears in the path** — not in the field, not in a preview calculation, not
   in a total. This is not a performance preference; it is a correctness rule (Rule 04, rule 2).
3. **The server computes the authoritative total.** Any client-side figure is a preview and is
   **labelled as one**: "Perkiraan total: Rp75.000. Total final dihitung saat pesanan disimpan."
4. **A client preview is never presented as final**, never printed on a nota as final, and never used to
   decide that an order is paid.
5. **An order is never marked paid on a client claim** (Rule 04, rule 5). The paid state originates
   from a server-verified event or an authorised, authenticated in-person action.
6. **The historical price is immutable.** An order captures the price that applied when it was created;
   editing the master price list never changes a past order, invoice, or reprint (Rule 04 rule 9,
   Rule 18 invariant 11). No form may offer to "refresh" a historical order's prices.
7. **Payment is idempotent on its `ClientReference`.** A retried submission produces exactly one
   payment. The reference is generated once, persisted with the queued operation, and **reused on every
   retry** — regenerating it on retry is the single highest-risk bug class in the offline design
   (Rule 07 rule 1, Rule 20 rule 13).
8. **A payment never exceeds the outstanding balance** without an explicit, permissioned overpayment
   path with its own confirmation.
9. **There is no negative money input.** A reversal is a separate, permissioned, reasoned operation —
   not a minus sign in an amount field (Rule 04 rule 8).
10. **There is no delete-payment control** for any ordinary role (Rule 04 rule 7).

---

## 8. Weight, quantity, and their separation

**Weight and quantity are distinct value objects and are never conflated** (Rule 17, rule 13).

| | Weight | Quantity |
|---|---|---|
| Meaning | A measurement | A count |
| Unit | kg | pcs |
| Decimals | Exactly one, comma separator | None |
| Example | `1,5 kg`, `12,0 kg` | `3 pcs`, `12 pcs` |
| Keyboard | Decimal numeric | Numeric |
| Zero | Invalid | Invalid |
| Component | CMP-009 | CMP-010 |

A service is priced by weight **or** by quantity, and the form shows the relevant field for the
selected service. **Showing both and letting the user pick is a modelling failure surfacing as a UI
problem.** Where a service genuinely uses both — a per-kg wash with per-item surcharges — they are
separate, separately labelled line inputs, never one ambiguous "jumlah" field.

---

## 9. Address input

- Structured into parts (jalan, kelurahan, kecamatan, kota, kode pos) rather than one free-text blob,
  so masking can operate on components.
- Autocomplete hints supplied for each part.
- Grouped in a fieldset with a legend (`ACCESSIBILITY.md` §8 rule 6).
- A map picker, if present, is **supplementary** — manual entry is always available and never blocked
  by a map failure (CMP-065).
- **Address is RESTRICTED data** (Rule 21, anchor 16). It is masked per the viewer's context, and
  **never displayed in full on the public tracking portal, under any condition** (Master Source §9.2
  rule 8).
- **A tracking-portal address change requires OTP** before any address field becomes editable
  (Master Source §9.2 rule 9). OTP unlocks a change flow, not a full-address display.

---

## 10. Time window input

- Presented as selectable preset windows wherever possible (CMP-016), not as free start/end entry.
- Rendered `08:00–10:00` — 24-hour, en dash.
- **Times are in the outlet's local time and the timezone is stated.** Storage is UTC.
- Availability is **server-authoritative**. A window that became unavailable between selection and
  submit produces a specific error and a re-selection prompt, never a silent reassignment.
- Start and end, in the custom variant, are one fieldset with cross-field validation.
- **A window is never presented as a guaranteed arrival time.** No copy in or around this input may
  claim a delivery guarantee or an optimal route (Rule 09, rule 1).

---

## 11. Retry and unsaved changes

### Retry

1. **A retry reuses the original `ClientReference`.** Never a fresh one (Rule 07 rule 1, Rule 20
   rule 13).
2. **Retries back off exponentially**, bounded, never a tight loop (Rule 07 rule 3, Rule 20 rule 18).
3. **A failed operation is never silently dropped.** It stays visible and actionable in the queue
   (Rule 07).
4. **A retry that cannot help is not offered.** "Coba lagi" appears only where retrying can succeed.
5. **A duplicate order or duplicate payment produced by a retry is an automatic NO-GO** (Rule 04
   rule 12, Rule 20 rule 16). The form design must make this structurally impossible, not merely
   unlikely.

### Unsaved changes

1. **Leaving a form with unsaved changes prompts** — via hardware back, browser back, navigation, tab
   change, or close.
2. The prompt **names what will be lost** and offers to stay. Its buttons name their outcomes: "Lanjut
   edit" / "Buang perubahan".
3. **The default focus is "Lanjut edit"**, the safe option.
4. **Escape cancels the prompt and returns to the form**; it never discards.
5. **Rotation, backgrounding, and app kill never lose entered data** (SC 1.3.4, Rule 07 rule 2).
6. **A queued financial operation is never cleared by leaving a form, a logout, a cache clear, or a
   version upgrade** (Rule 07 rule 4). Removing one requires an explicit, permissioned, audited action
   whose confirmation names the amount and the customer.

---

## 12. Destructive and consequential actions

Governed by `DESIGN_PRINCIPLES.md` P5 and CMP-045.

1. **The destructive action is never the default.** Not the default focus, not the visually primary
   control, not the action Enter triggers.
2. **Spatial separation of at least `space.8` (32 dp)** from the nearest routine action
   (`SPACING_SIZING_DENSITY.md` §4 rule 6). **A refund control is never adjacent to a print control**
   (Master Source §18.2 rule 3).
3. **Destructive actions live in overflow, not in an app bar's primary slots** (CMP-042).
4. **Confirmation names the consequence**, with the specific record and amount.
5. **Buttons name their outcomes** — never "Ya"/"Tidak"/"OK".
6. **Escape cancels; it never confirms.**
7. **A reason is required and recorded** where governance requires it: order cancellation (FR-058),
   refund and void (Rule 04 rule 6), a QC waiver (Rule 19), removal of a queued financial operation
   (Rule 07 rule 4). **Whitespace does not satisfy a required reason.**
8. **Permission is verified server-side.** A hidden or disabled control is a UX affordance, never an
   access control (Rule 03, rule 2).
9. **No bulk destructive and no bulk financial action exists in this product** — no bulk refund, no
   bulk cancel, no bulk void (`PLATFORM_ADAPTATION.md` §4).
10. **Nothing financial is hard-deleted.** Corrections are reversal or adjustment entries, and the
    interface says "Kembalikan", never "Hapus" (Rule 04 rules 7, 8).

---

## 13. Discount approval

Discount is called out separately because it is the most common route by which margin leaks and the
most common informal workaround at a counter.

1. **A discount above the tenant's configured threshold requires approval**, and the threshold is
   configured per tenant, not hard-coded.
2. **The approval is a distinct UX flow, not a checkbox.** It captures the approver's identity, an
   authenticated action, the amount, and a reason.
3. **The requesting user cannot approve their own discount** unless their role independently carries
   that permission — and even then, the record shows that requester and approver were the same person.
4. **The state is visible before submission**: "Diskon Rp25.000 melebihi batas Rp20.000. Perlu
   persetujuan manajer." The order is not silently blocked at submit with no explanation.
5. **A pending approval is a real state**, visible to both the requester and the approver, not an
   invisible queue.
6. **Approval is enforced server-side.** A client that hides the discount field is not a control.
7. **The approval is audited** — actor, approver, tenant, outlet, timestamp, amount, reason
   (CMP-070, Rule 04).
8. **A discount never alters a historical order.** It applies at creation and is captured in the price
   snapshot (Rule 04 rule 9).
9. **There is no bulk discount approval.**

---

## 14. Offline form behaviour

Governed by Rule 07 and `DESIGN_PRINCIPLES.md` P6.

1. **Forms work offline.** Order intake, payment recording, production transitions, and proof capture
   all submit with no connection (FR-059, FR-079, FR-107).
2. **A `client_reference` is generated once, before the first attempt, and persisted with the queued
   operation.**
3. **The queued state is visible** on the record and in the queue view — "Menunggu Sinkronisasi".
4. **A queued operation is never presented as complete.** A queued payment is not "Lunas".
5. **Dependent operations preserve their order** — `CreateOrder` before `RecordPayment`; an operation
   whose predecessor failed does not jump ahead (Rule 07, Rule 20 rule 20).
6. **Server timestamps are authoritative** for ordering and reporting; the device clock is untrusted
   metadata (Rule 20 rule 11).
7. **On divergence the server prevails**, and the divergence is **made visible** rather than resolved
   silently (Rule 07 rules 5, 6).
8. **A payment conflict is always a human decision** (CMP-057). There is no default winner and no
   automatic resolution.
9. **Local form data is encrypted on device and separated per tenant and per user.** A tenant or user
   switch clears it (Rule 03 rule 7, Rule 07 rules 7, 8).

---

## 15. Prohibited form practices

| Prohibited | Reason |
|---|---|
| Placeholder used as a label | `ACCESSIBILITY.md` §8 rule 1 |
| Validation on every keystroke for a new error | §3 rule 1 |
| An error indicated by colour alone | NFR-026 |
| A generic "Terjadi kesalahan" | Master Source §18.2 rule 4 |
| An error exposing a stack trace, status code, or database error | `CONTENT_DESIGN.md` §4 |
| An error revealing whether a record exists | `CONTENT_DESIGN.md` §7 |
| Client-side validation treated as the control | Rule 03 rule 2; Rule 18 rule 1 |
| **Floating point anywhere in a money path** | Rule 04 rule 2 |
| Decimals in a money field | Rule 04 rule 1 |
| A client-computed total presented as final | §7 rule 3 |
| An order marked paid on a client claim | Rule 04 rule 5 |
| A price-list change altering a historical order | Rule 04 rule 9 |
| A retry with a fresh `ClientReference` | Rule 07 rule 1 |
| A queued financial operation cleared without an audited action | Rule 07 rule 4 |
| A queued operation shown as complete | §14 rule 4 |
| A payment conflict resolved silently | Rule 07 rule 5 |
| Weight and quantity in one ambiguous field | §8 |
| A destructive action as the default | §12 rule 1 |
| A destructive action adjacent to a routine action | Master Source §18.2 rule 3 |
| "Ya"/"Tidak"/"OK" on a consequential confirmation | `CONTENT_DESIGN.md` §3 |
| Escape confirming or submitting | `ACCESSIBILITY.md` §11 |
| A required reason satisfiable by whitespace | §12 rule 7 |
| A delete-payment control | Rule 04 rule 7 |
| Any bulk refund, bulk void, or bulk cancel | §12 rule 9 |
| A discount above threshold applied without server-side approval | §13 |
| Blocking paste in any field, especially OTP | SC 3.3.8 |
| A full address displayed on the public tracking portal | Master Source §9.2 rule 8 |
| A tracking-portal address change without OTP | Master Source §9.2 rule 9 |
| Unsaved data lost to rotation, back, or backgrounding | §11 |
| A multi-column form layout | `RESPONSIVE_FOUNDATION.md` §6 |
| A fixed-height field container | `TYPOGRAPHY.md` §5 |

---

## 16. Critical validation scenarios — Given / When / Then

These scenarios express the intent of the rules above in testable form. **Not one of them has been
executed.** Writing a criterion is not running one, and there is nothing to run it against: the
Flutter workspace is `ABSENT` and the backend runtime is `ABSENT` (Rule 22, rule 15). Every datum
below is fictional (Rule 23).

### 16.1 Money is never validated per keystroke

```
Given a kasir at Outlet Cempaka is recording a payment on order AL-2026-000123
  And the outstanding balance is Rp79.000
When the kasir types the digits 7, 9, 0, 0, 0 in sequence into the money field
Then no error appears at any intermediate value
  And thousands separators are inserted by the field, producing Rp79.000
  And no decimal separator is accepted, offered, or displayed
  And validation runs on blur and again on submit, and at no point between keystrokes
```

### 16.2 A client-side figure is a preview, never the financial figure

```
Given a kasir has entered 3,0 kg of a per-kilogram service in tenant Laundry Bersih Sejahtera
  And the client displays "Perkiraan total: Rp45.000"
When the order is submitted and the server returns a different authoritative total
Then the server total replaces the preview
  And the change is surfaced explicitly, naming the previous and the new amount
  And the nota, invoice, and payment confirmation display only the server-returned amount
  And no client-computed figure is printed or stored as final
```

### 16.3 A retry produces exactly one payment

```
Given a kasir submits a payment of Rp79.000 on an unstable connection
  And the queued operation carries a ClientReference generated once and persisted with it
When the request times out and the client retries three times with exponential backoff
Then every retry carries the same unchanged ClientReference
  And the server returns the original result rather than creating a second payment
  And the interface shows exactly one payment
  And a second payment created by a retry would be an automatic NO-GO under Rule 04
```

### 16.4 An error names the failure and the next action

```
Given a staff member enters a weight of 65,0 kg
  And the tenant's configured maximum is 50,0 kg per order
When the weight field loses focus
Then an error appears with an error border, an error icon, and error text together
  And the text reads "Berat maksimum 50,0 kg per pesanan. Pisahkan menjadi beberapa pesanan."
  And the error is programmatically associated with the weight field
  And a bare "Terjadi kesalahan" would be a defect under §5
```

### 16.5 Unsaved changes survive an exit attempt

```
Given a manager has edited two fields on order AL-2026-000123 without saving
When the manager triggers the hardware back gesture
Then a prompt appears naming what would be lost
  And "Lanjut edit" holds the initial focus as the safe option
  And "Buang perubahan" is separated by at least space.8 and is never the default
  And pressing Escape returns to the form and discards nothing
```

### 16.6 A phone number never leaks across the tenant boundary

```
Given customer Budi Santoso exists in tenant Laundry Bersih Sejahtera with phone 0812-XXXX-1234
  And a different tenant holds a customer record with the same phone number
When a kasir in the second tenant types that number into the phone field
Then no suggestion, autocomplete entry, duplicate warning, or merge prompt derived from the first
    tenant appears
  And the two profiles remain unrelated (Rule 02 hard rule 11, Rule 18 invariant 9)
  And any cross-tenant leakage through this field would be an automatic NO-GO
```

### 16.7 A discount above threshold cannot be applied silently

```
Given a kasir applies a discount of Rp25.000 and the tenant's threshold is Rp20.000
When the kasir attempts to submit the order
Then the requirement for approval is stated before submission, not discovered at submit
  And an approval flow captures the approver identity, an authenticated action, the amount, and a
      non-whitespace reason
  And approval is enforced server-side, not by hiding the field
  And the approval is audited with actor, approver, tenant, outlet, timestamp, amount, and reason
```

### 16.8 A queued operation is never shown as complete

```
Given Siti Rahmawati records a cash payment on Ops Android while the device is offline
When the payment is written to the local queue
Then the record displays "Menunggu Sinkronisasi" and never "Lunas"
  And the operation is visible and actionable in the queue view
  And it is not cleared by logout, cache clear, or a version upgrade
  And removing it requires an explicit, permissioned, audited action naming the amount
```

---

## 17. Status

| Item | Status |
|---|---|
| This specification | NOT IMPLEMENTED |
| Form and validation implementation on any surface | NOT IMPLEMENTED |
| Flutter workspace | ABSENT |
| Backend runtime | ABSENT |
| Runtime accessibility testing of forms | NOT STARTED |
| Usability testing of forms | NOT STARTED |
| Application CI | NOT APPLICABLE |

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

Related: [`COMPONENT_CATALOG.md`](COMPONENT_CATALOG.md) ·
[`COMPONENT_STATE_MATRIX.md`](COMPONENT_STATE_MATRIX.md) ·
[`ACCESSIBILITY.md`](ACCESSIBILITY.md) · [`CONTENT_DESIGN.md`](CONTENT_DESIGN.md) ·
[`UX_COPY_GLOSSARY.md`](UX_COPY_GLOSSARY.md) ·
[`DESIGN_DECISION_LOG.md`](DESIGN_DECISION_LOG.md) ·
[`DESIGN_DEBT_REGISTER.md`](DESIGN_DEBT_REGISTER.md)
