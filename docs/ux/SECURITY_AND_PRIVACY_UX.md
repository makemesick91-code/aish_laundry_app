# Security and Privacy UX Patterns — Step 2

**Step:** 2 — Design System and UX Foundation
**Status:** `IN PROGRESS` (Step 2 is `IN PROGRESS`; `GO` is owner-conferred and never self-declared)
**Master Source version:** 1.3.0 · baseline 19 July 2026

---

## 1. Purpose and standing

This document fixes the **interaction patterns** through which Aish Laundry App handles sensitive data,
money, consent, and access. A security control that a user cannot see, understand, or act on is not a
control — it is a hope with a log entry.

**Documentation is not implementation.** No pattern here is built. The backend is `ABSENT`, the Flutter
workspace is `ABSENT`, the database is `ABSENT`, deployment is `ABSENT`, and application CI is
`NOT APPLICABLE`. Each pattern is an **obligation on the Step that builds the surface**, not an achievement
of Step 2.

Accessibility statements carry the exact wording:
**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

Findings that motivate these patterns are recorded in
[`../security/DESIGN_AND_UX_THREAT_REVIEW.md`](../security/DESIGN_AND_UX_THREAT_REVIEW.md).

### 1.1 Fictional data convention

This repository is **PUBLIC**, an accepted deviation from a canonical desired **PRIVATE**
(AMENDMENT-0001, [`../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md`](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)).
PUBLIC is not the desired end state. **Every example below is fictional and recognisably so.** The names,
numbers, addresses, order references, and tokens are invented for this document. None is copied from a real
customer, a real device, a real message, or a real log, and none may ever be replaced with a real value.

Fictional cast used throughout:

| Placeholder | Value |
| --- | --- |
| Customer | Ibu Sari Wulandari |
| Customer phone | `+62 812-0000-1234` |
| Customer address | Jl. Melati Contoh No. 12, RT 03 / RW 05, Kelurahan Sukamaju, Bandung 40123 |
| Tenant | Laundry Bersih Contoh |
| Outlet | Outlet Sukamaju |
| Order reference | `ALS-2026-000042` |
| Staff | Rina (kasir) · Budi (kurir) |

---

## 2. Masking patterns

Masking level depends on **who is looking and where**. The same value has different renderings on different
surfaces. Classification drives masking:
[`../security/DATA_CLASSIFICATION.md`](../security/DATA_CLASSIFICATION.md).

### 2.1 Phone masking

Customer phone is `CONFIDENTIAL`. A complete tenant phone list is `RESTRICTED`, because aggregation raises
class.

| Surface / role | Rendering | Example |
| --- | --- | --- |
| Public tracking portal | Never shown | — |
| External courier guest link | Masked, call-through only | `+62 812-••••-1234` |
| Ops list view (kasir, operator) | Masked | `+62 812-••••-1234` |
| Ops detail view (kasir, on explicit reveal) | Full, recorded | `+62 812-0000-1234` |
| Console Web finance / owner | Masked by default, reveal per record | `+62 812-••••-1234` |
| Notification body | Never shown | — |
| Export file | Masked unless the export permission explicitly grants full | `+62 812-••••-1234` |

Rules:

1. The mask preserves the country code and the last four digits and nothing else. Preserving the first four
   subscriber digits would make the number guessable within a small operator prefix.
2. **Reveal is an action, not a hover.** It is per-record, never per-list, and never bulk.
3. A revealed value is not sticky: navigating away re-masks it.
4. The masked form is what the interface copies to the clipboard by default.
5. The courier surface offers *call* and *message* actions that dial through without displaying the full
   number.

### 2.2 Address masking

Customer address is `RESTRICTED`. Its disclosure carries a physical-safety dimension, not merely privacy.

| Surface / role | Rendering | Example |
| --- | --- | --- |
| Public tracking portal | Area only — **never** a full address | `Kelurahan Sukamaju, Bandung` |
| External courier guest link | Delivery-necessary precision only, non-copyable, non-shareable | `Jl. Melati Contoh No. 12, Kelurahan Sukamaju, Bandung` |
| Ops list view | Not rendered at all | — |
| Ops detail view, pickup/delivery roles | Full | Jl. Melati Contoh No. 12, RT 03 / RW 05, Kelurahan Sukamaju, Bandung 40123 |
| Ops detail view, production roles | Area only | `Kelurahan Sukamaju, Bandung` |
| Notification body | Never shown | — |
| Map provider | Minimised before it leaves the product | `Kelurahan Sukamaju, Bandung` |

Rules:

6. **The public tracking portal never shows a full address.** This is absolute.
7. Address never appears in a list row. Rendering fifty addresses on one screen is the tenant's customer
   base in a single photograph.
8. A role that does not perform pickup or delivery does not need house-number precision and does not
   receive it.
9. Map pins are never rendered at building resolution on any shareable or unauthenticated surface.

### 2.3 Name masking

| Surface | Rendering | Example |
| --- | --- | --- |
| Public tracking portal | Given name plus initial | `Sari W.` |
| External courier guest link | Given name plus initial | `Sari W.` |
| Authenticated tenant surfaces | Full | Ibu Sari Wulandari |

### 2.4 Staff identity

Staff identity beyond operational necessity is not exposed to customers. The public portal may show a role
(`Kurir`) and a given name (`Budi`) where the customer genuinely needs to identify the person at the door.
It never shows a staff surname, phone number, employee identifier, shift record, or performance data.

---

## 3. Tracking token handling

The plaintext tracking token is `SECRET`. Only its hash is ever stored. The token is a bearer credential:
whoever holds the link holds the access.

10. The token value **never** appears in a page title, heading, breadcrumb, visible label, analytics path or
    parameter, error message, support form, log line, event payload, telemetry field, or committed file.
11. The tracking portal is served with `noindex`, and outbound links carry `noreferrer`.
12. No third-party analytics script, marketing pixel, session recorder, remote font, or remote icon set is
    placed on any surface whose URL can carry a token.
13. Ops and Console surfaces show tracking **state** — active, expired, revoked — and a revoke control. They
    never render the token.
14. The customer-facing share affordance is labelled and warned:

```text
Bagikan tautan lacak

Siapa pun yang memiliki tautan ini dapat melihat status pesanan Anda.
Kirim hanya kepada orang yang Anda percaya.

[ Salin tautan ]   [ Batal ]
```

15. Revocation is available to the customer and to tenant staff with permission, takes effect immediately,
    and renders a neutral state to any subsequent holder that discloses nothing about whether the order
    exists:

```text
Tautan tidak berlaku

Tautan pelacakan ini sudah tidak aktif.
Hubungi outlet tempat Anda menitipkan cucian untuk mendapatkan tautan baru.
```

---

## 4. Clipboard, screenshot, and screen-sharing

### 4.1 Clipboard

16. Copy affordances exist only where copying is the intended workflow.
17. **No copy affordance** is offered for an OTP, a session token, a raw tracking token, or a credential.
18. Copying a tracking link or a masked phone number shows a brief non-blocking notice:

```text
Tautan lacak disalin. Papan klip dapat dibaca aplikasi lain di perangkat ini.
```

19. Nothing is placed on the clipboard automatically. A copy is always a user action.
20. Where a value is masked on screen, the masked form is what is copied unless the user explicitly reveals
    and then copies.

### 4.2 Screenshots and screen sharing

21. Screenshots cannot be reliably prevented and the design **does not claim** they can be. What the design
    does is reduce what a screenshot is worth.
22. Screens whose entire purpose is a sensitive value — OTP entry, revealed customer contact, support
    impersonation start — are kept minimal, so a screenshot captures one record rather than a list.
23. No screen renders a token, a credential, or a full customer list in a form that makes a single
    screenshot a bulk disclosure.
24. Support workflows never ask a customer or a staff member to send a screenshot containing an OTP, a
    token, or a full address. Support request forms state this explicitly.

---

## 5. Session, device, and step-up authentication

### 5.1 Session expiry

25. Expiry is **warned before it happens**, with the time remaining and a re-authenticate action that does
    not leave the current screen:

```text
Sesi akan berakhir dalam 2 menit

Masuk kembali untuk melanjutkan. Pekerjaan yang belum tersimpan akan dipertahankan.

[ Masuk kembali ]   [ Nanti ]
```

26. In-progress work is preserved locally through re-authentication and restored afterwards.
27. Re-authentication is **step-up**, never a full logout. It never clears the offline queue.
28. An expired session never silently discards a queued financial operation.

### 5.2 Device revocation

29. The device list names each device, its last activity, and its outlet, so a manager can identify the
    right one.
30. Revocation is immediate and does not force other devices to re-authenticate.
31. The confirmation states the consequence honestly and promises nothing it cannot deliver:

```text
Cabut akses perangkat "Ops-Android-Outlet-Sukamaju-02"?

Perangkat ini akan langsung kehilangan akses.

Operasi yang sudah tersinkron tetap tersimpan di server.
Operasi yang masih mengantre di perangkat ini TIDAK dapat dipulihkan.
Cocokkan catatan kas outlet dengan data server setelah pencabutan.

[ Cabut akses ]   [ Batal ]
```

### 5.3 Step-up authentication

32. Step-up is required for: viewing a full customer contact in bulk contexts, issuing a refund or void,
    changing a role or permission, revoking a device, starting support impersonation, and exporting data.
33. Step-up states **why** it is being asked, so it does not read as an arbitrary obstacle.
34. Step-up never re-enters the full login flow and never discards the in-progress task.

### 5.4 OTP

35. A single input accepts the full code. Paste is permitted. The keyboard is numeric. Platform autofill is
    supported.
36. Validity is stated plainly: `Kode berlaku 5 menit.`
37. Resend is disabled with a visible countdown rather than silently rate-limited.
38. Lockout is explained with a recovery step, never as a bare error code:

```text
Terlalu banyak percobaan

Demi keamanan, permintaan kode dijeda selama 15 menit.
Coba lagi setelah 15 menit, atau hubungi outlet Anda untuk bantuan.
```

39. The OTP is never echoed back, never shown in a notification preview, never auto-copied, and never
    logged.

---

## 6. Permission denied

40. A permission-denied state says what was refused and what the user can do next. An error code alone is
    never an acceptable message.
41. The message **never confirms the existence** of a record in another tenant. A cross-tenant identifier
    produces a not-found response indistinguishable from a genuinely absent record.

```text
Anda tidak memiliki akses ke halaman ini

Peran Anda saat ini: Operator Produksi — Outlet Sukamaju.
Hubungi manajer outlet Anda jika Anda memerlukan akses.

[ Kembali ke beranda ]
```

42. The interface never hides a control and then denies the action silently. If a control is visible, its
    denial is explained; if the user lacks the permission entirely, the control is not rendered.

---

## 7. Support impersonation banner

43. An impersonation session renders a persistent, high-contrast, **non-dismissible** banner on every
    screen, announced to assistive technology on entry to each screen:

```text
MODE DUKUNGAN AKTIF — Tim dukungan Aish sedang mengakses tenant Laundry Bersih Contoh.
Petugas: dukungan-07 · Alasan: investigasi tiket #contoh-1187 · Sisa waktu: 23 menit
[ Akhiri sesi dukungan ]
```

44. The banner is never conveyed by colour alone; it carries text and an icon.
45. A reason is **mandatory before** the session begins and cannot be supplied afterwards.
46. Ending impersonation is always one action away from every screen.
47. Every action performed during impersonation is recorded with the support actor's identity, not the
    tenant user's. The interface never renders a support action as if a tenant employee performed it.

---

## 8. Audit reason capture

48. Where a rule requires a reason — refund, void, quality-control waiver, storage-fee adjustment, courier
    cash variance, impersonation, forced status correction — the field is **mandatory**, **empty by
    default**, and rejects whitespace-only input.
49. No reason is pre-filled, pre-selected, or offered as a single default option. Where a reason code list
    exists it includes an explicit "Lainnya" that requires free text.
50. The reason is restated in the confirmation surface before commit, so the actor sees what will be
    recorded against their name.
51. The interface states that the reason is retained in the audit record. It is never described as optional
    or as an internal note that will not be kept.

---

## 9. External courier guest access

52. The guest surface renders **one assignment** and nothing else: no navigation chrome, no search, no
    history, no customer profile, no pricing, no order value, no other jobs, no link to any other record.
53. Address is shown at delivery-necessary precision only, and is neither selectable as text nor shareable
    as a link.
54. Customer phone is masked, with call-through and message-through actions that do not reveal the number.
55. The surface displays its own expiry plainly: `Tautan ini berlaku sampai 19 Juli 2026, 18.00 WIB.`
56. An expired or revoked link renders a neutral state that discloses nothing about whether the assignment
    ever existed.
57. The link is tenant-scoped. A courier working for two tenants receives two unrelated links, and neither
    surface offers any path toward the other.
58. Proof capture — OTP, photo, signature, recipient name — is the only write the surface permits. Proof
    artifacts are private and are never displayed back to the courier after submission.

---

## 10. Financial confirmation patterns

### 10.1 Payment

59. Three states are visually and textually distinct and are **never collapsed into one**:

| State | Label | Styling |
| --- | --- | --- |
| Queued on device | `TERKIRIM — MENUNGGU KONFIRMASI SERVER` | Neutral, with pending icon |
| Server-confirmed | `BERHASIL — DIKONFIRMASI SERVER` | Success, with confirmed icon |
| Failed | `GAGAL` | Danger, with failure icon and recovery text |

60. **Success is never claimed from client state.** The word "berhasil", the success colour, and the success
    icon are reserved for server confirmation.
61. The order remains visibly unpaid until the server confirms.
62. Retry reuses the original `client_reference`, and the interface says so, so an operator does not create
    a second payment by pressing the button again:

```text
Coba kirim ulang pembayaran ini?

Pembayaran akan dikirim ulang dengan referensi yang sama (ALS-2026-000042-P1),
sehingga tidak akan tercatat dua kali.

[ Kirim ulang ]   [ Batal ]
```

63. Amounts are integer Rupiah read from the authoritative financial record and formatted for display only.
    A displayed amount is never the source of a stored value.

### 10.2 Refund

64. Refund is a first-class, discoverable action on the order record, at the same prominence as payment. It
    is never buried.
65. No amount is pre-selected. The reason is mandatory and empty by default.
66. The confirmation restates amount, order, customer, outlet, tenant, actor, and reason before commit.
67. The interface states that the correction is recorded as a reversal or adjustment entry and that the
    original record is preserved — never as a deletion.

```text
Konfirmasi pengembalian dana

Tenant   : Laundry Bersih Contoh
Outlet   : Outlet Sukamaju
Pesanan  : ALS-2026-000042
Pelanggan: Ibu Sari Wulandari
Jumlah   : Rp45.000
Alasan   : (wajib diisi)
Petugas  : Rina

Pengembalian dicatat sebagai entri pembalik. Catatan pembayaran asli tetap tersimpan.

[ Proses pengembalian ]   [ Batal ]
```

### 10.3 Void

68. Void carries the same mandatory reason, the same restatement, and the same reversal-not-deletion
    language as refund.
69. Void requires step-up authentication and a permission the ordinary counter role does not hold.
70. There is **no** "hapus pembayaran" control anywhere in any ordinary role's interface.

---

## 11. Tenant switching

71. The active tenant is rendered persistently in the primary chrome of every authenticated screen. Outlet
    is shown alongside it whenever the user has access to more than one.
72. Tenant name is text, never a colour swatch alone.
73. Switching is deliberate and confirmed, and it clears the visible working set so that no previous
    tenant's data remains on screen:

```text
Pindah ke tenant "Laundry Cepat Contoh"?

Data tenant saat ini akan ditutup dari layar.
Operasi yang masih mengantre tetap tersimpan dan akan dikirim untuk tenant asalnya.

[ Pindah tenant ]   [ Batal ]
```

74. The new context is announced to assistive technology after the switch.
75. Any screen that writes data renders the tenant name inside the same visual block as the primary action,
    not only in the page chrome.

---

## 12. Export warning

76. Export is a permissioned, step-up-gated, recorded action.
77. The interface states before the export what the file will contain, which classification the contents
    carry, and what the recipient's obligation is:

```text
Ekspor daftar pesanan — Outlet Sukamaju

Berkas berisi 128 baris dan mencakup data pelanggan.
Nomor telepon: disamarkan. Alamat: tidak disertakan.

Berkas ini berisi data pelanggan tenant Anda. Simpan dengan aman dan jangan
bagikan di luar organisasi Anda.

[ Ekspor ]   [ Batal ]
```

78. Exports carry the same access rules as the underlying records, and are tenant-scoped and outlet-scoped
    to the requester's actual access.
79. An export containing unmasked personal data requires a permission distinct from ordinary export and is
    recorded with actor, tenant, scope, and reason.
80. Export files are delivered through private storage and signed expiring links, never through a public
    URL.

---

## 13. Retention notice

81. Where the product retains data with a defined lifetime — proof photographs, signatures, audit entries,
    tracking links, exports — the interface states the retention plainly at the point of capture rather than
    only in a policy document.

```text
Foto bukti pengiriman disimpan sebagai bukti serah terima pesanan ALS-2026-000042.
Foto bersifat privat, hanya dapat dilihat oleh staf berwenang, dan tidak pernah
ditampilkan pada halaman pelacakan publik.
```

82. A retention period that has not been decided is not invented for the interface. It is raised as an open
    question to the repository owner and shown as undefined, never as a plausible-looking number.

---

## 14. Consent patterns

### 14.1 Marketing consent

83. Marketing consent is a **separate, unbundled, explicitly opt-in** control, defaulted to **off**.
84. It is never combined with acceptance of terms and never combined with a transactional notification
    setting.
85. The label is positive and unambiguous in Bahasa Indonesia — no double negatives:

```text
[ ] Saya bersedia menerima informasi promo dan penawaran dari Laundry Bersih Contoh
    melalui WhatsApp.

Anda tetap menerima pemberitahuan status pesanan meskipun kotak ini tidak dicentang.
```

86. Withdrawal is at least as easy as granting, and is reachable from the message itself.
87. Consent state, actor, timestamp, and channel are recorded so the tenant can answer a dispute.

### 14.2 Transactional messages

88. Transactional messages follow the order — received, ready, out for delivery, delivered — and are **not**
    marketing. They are not consent-gated, and the interface says so, so a customer declining marketing does
    not believe they have declined order updates.
89. A marketing message is **never** sent through a transactional path to evade opt-out. The interface keeps
    the two categories visibly separate in templates, in sending, and in reporting.

### 14.3 Opt-out

90. Opt-out is honoured permanently, across every outlet of the tenant, and is respected at send time rather
    than only at campaign-build time.
91. The opt-out confirmation states exactly what stops and what continues:

```text
Anda telah berhenti berlangganan pesan promo dari Laundry Bersih Contoh.

Anda tetap menerima pemberitahuan status pesanan Anda.
Untuk berhenti menerima pemberitahuan status, hubungi outlet Anda.
```

---

## 15. Public Tracking Portal — explicit prohibition list

The public tracking portal is the **most exposed surface in the product**. It is reachable by anyone holding
a link. The projection rendered there is a **separate projection defined by an allow-list**, not a filtered
view of the internal order — a field absent from the allow-list cannot appear, because it is never
assembled.

The portal **must never** render:

| # | Prohibited on the public tracking portal |
| --- | --- |
| 1 | A full customer phone number |
| 2 | A full customer address |
| 3 | Any internal note, staff remark, or operational comment |
| 4 | Margin, profit, or any commercial performance figure |
| 5 | Cost price, supplier cost, or any input cost |
| 6 | Employee data beyond the operational minimum (given name and role only) |
| 7 | Any audit record, audit reason, or audit actor |
| 8 | Sensitive photographs — laundry photographs, proof-of-pickup or proof-of-delivery images, signatures |
| 9 | The tracking token value in any analytics field, path, parameter, event, or telemetry payload |

Supporting rules:

92. The portal carries `noindex`; tracking pages are never search-indexed.
93. The portal is self-contained — first-party fonts, icons, and styles. No remote asset, analytics script,
    marketing pixel, session recorder, or third-party embed.
94. Rate limiting and enumeration protection apply to token lookup.
95. Sensitive actions on the portal require OTP.
96. No app installation is ever required to use the portal. It is a differentiator and is never degraded
    into "install the app first" (DEC-0006, DEC-0014).

---

## 16. Push notification content rule

**Push notifications must not carry excessive sensitive data.** A notification renders on a locked device
to whoever is holding it.

97. A push payload carries the **minimum** needed to bring the user into the app: an event type and a
    non-identifying reference.
98. A push payload **never** carries: an OTP; a tracking token; a full address; a full phone number; a
    payment amount; a customer's full name; an audit reason; an internal note; or any credential.
99. Sensitive detail is retrieved **after authentication**, inside the app.
100. Notification copy is written and reviewed against the lock screen, not against the in-app inbox.

Acceptable and unacceptable examples:

```text
ACCEPTABLE
  Aish Laundry — Pesanan ALS-2026-000042 siap diambil.
  Buka aplikasi untuk melihat detail.

UNACCEPTABLE
  Aish Laundry — Kode OTP Anda 481920
  Aish Laundry — Cucian Ibu Sari Wulandari (+62 812-0000-1234) siap diantar ke
  Jl. Melati Contoh No. 12, Bandung. Tagihan Rp45.000.
```

101. Quiet hours 20.00–08.00 outlet local time apply to non-critical messages. A message queued inside the
     window is deferred to the next permitted window — never dropped, never sent anyway.
102. Deduplication is keyed on recipient, event, order, and intended send window. The same notification is
     not delivered twice across retries, queue replays, or scheduler restarts.
103. A messaging failure never changes business state. An order is never cancelled, blocked, or advanced
     because a message did or did not send.

---

## 17. Accessibility of the security surfaces

Security-critical states must be perceivable by every user, not only by sighted users on a good screen.

104. Impersonation banners, offline and sync state, payment state, and permission-denied states are
     conveyed by **text and icon**, never by colour alone.
105. Every interactive element declares role, accessible name, and state. Icon-only controls carry a text
     alternative naming the action and its object — "Batalkan pesanan ALS-2026-000042", not "Batal".
106. Minimum touch target is 48×48 dp, including confirmation buttons and the impersonation exit control.
107. Focus moves to the first invalid field on validation error and announces the error text. Dialogues trap
     focus and return it to the invoking control on dismissal.
108. Live regions announce changes to sync state and payment state.
109. Layouts survive large system font scaling without truncating a status, an amount, or a warning.

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

---

## 18. What this document does not claim

- **No pattern here is implemented.** All product features are `NOT IMPLEMENTED`; the backend is `ABSENT`;
  the Flutter workspace is `ABSENT`; the database is `ABSENT`; deployment is `ABSENT`; application CI is
  `NOT APPLICABLE`; UAT is `NOT STARTED`.
- **A wireframe is not a screen. A design token is not a theme. An accessibility requirement is not a
  passed audit.**
- **No acceptance criterion has been executed.**
- Step 2 is `IN PROGRESS`. `GO` is conferred by the repository owner and is never self-declared by an agent.

---

## 19. Related documents

- [`../security/DESIGN_AND_UX_THREAT_REVIEW.md`](../security/DESIGN_AND_UX_THREAT_REVIEW.md)
- [`../security/DATA_CLASSIFICATION.md`](../security/DATA_CLASSIFICATION.md) · [`../security/PRIVACY_REQUIREMENTS.md`](../security/PRIVACY_REQUIREMENTS.md)
- [`../security/INITIAL_THREAT_MODEL.md`](../security/INITIAL_THREAT_MODEL.md) · [`../security/TRUST_BOUNDARIES.md`](../security/TRUST_BOUNDARIES.md)
- [`../domain/DOMAIN_GLOSSARY.md`](../domain/DOMAIN_GLOSSARY.md)
- [`../quality/STEP_02_DEFINITION_OF_DONE.md`](../quality/STEP_02_DEFINITION_OF_DONE.md)
- [`../product/PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md)
- [`../STATUS.md`](../STATUS.md) · [`../ROADMAP.md`](../ROADMAP.md) · [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md)


---

## Payment confirmation

Payment is the point where a UX mistake becomes a financial one, so the
confirmation pattern is fixed rather than left to each screen.

### The rule that governs everything else

**An order is never marked paid on client state.** A payment is confirmed only
by a server acknowledgement or by an authorised in-person action recorded by an
authenticated staff member (Rule 04). Until the server has acknowledged, the
interface says so plainly — it does not round an unacknowledged payment up to a
confirmed one because the wording would read more cleanly.

### Confirm payment — the pattern

| Element | Specification |
|---|---|
| Trigger | Kasir taps **"Terima pembayaran"** on `SCR-OPS-016`. |
| Pre-confirmation summary | Amount in integer Rupiah (`Rp79.000`), method, order number `AL-2026-000123`, and the remaining balance if the payment is partial. |
| Ambiguity guard | If the entered amount differs from the balance, the difference is stated explicitly — "Kurang Rp20.000" or "Kembalian Rp5.000" — never silently absorbed. |
| Confirmation control | A single primary action labelled with the amount: **"Terima Rp79.000"**. A bare "OK" or "Ya" is prohibited on a financial confirmation. |
| Server acknowledgement | Until acknowledged, the payment shows `Menunggu konfirmasi server` with the sync state visible. It is **never** rendered as `Lunas`. |
| On acknowledgement | Status becomes `Lunas`, the receipt becomes printable, and the acknowledgement time is shown in outlet local time. |
| On offline | The payment is queued with its `client_reference`, shown as `Tersimpan lokal — belum dikonfirmasi`, and the printed receipt states plainly that the payment is pending server confirmation. |
| On retry | The original `client_reference` is reused. A retry produces exactly one payment, never a second. |
| On conflict | The conflict is surfaced for a human (`Perlu Diperiksa`). It is never resolved by overwriting either side. |

### Partial payment

A partial payment shows the amount received, the remaining balance, and the
order's payment status as `Belum Lunas` — not as a progress bar that could be
misread as completion. The balance is read from the authoritative financial
record, never recomputed in the interface.

### Refund and void

Refund and void each require a permission and a **recorded reason**, and both are
audited with actor, timestamp, amount and reason (Rule 04). Neither is ever the
default action, and neither sits adjacent to a routine action such as print. The
confirmation names the amount and the order explicitly, and **the destructive
control is never the initially focused control** in the dialog.

**A financial record is never hard-deleted.** A correction is a reversal or an
adjustment entry, and the interface offers no path that implies otherwise.
