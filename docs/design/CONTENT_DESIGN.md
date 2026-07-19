# Content Design — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Derived from:** Master Source §18.3 (Copy rules), §1.6 (language), Rule 01, Rule 08
**Scope:** DOCUMENTATION ONLY. All example data below is fictional.

---

## 1. Voice

**The product speaks like a competent colleague** — someone who knows the work, says what happened,
says what to do, and does not waste your time.

| It is | It is not |
|---|---|
| Clear | Terse to the point of coldness |
| Direct | Blunt or blaming |
| Calm | Alarmed or apologetic in excess |
| Specific | Vague ("terjadi kesalahan") |
| Respectful | Formal or bureaucratic |
| Honest | Optimistic about things it does not know |

### Language

- **Bahasa Indonesia** for all user-facing text, on all four surfaces.
- **Everyday register.** Not formal legal Indonesian, not slang.
- **"Kamu"** for customer-facing surfaces. Neutral or imperative phrasing for staff surfaces — a staff
  screen says "Simpan pesanan", not "Kamu bisa menyimpan pesanan".
- **No English technical terms** where an Indonesian term exists. "Sinkronisasi", not "sync".
  "Pesanan", not "order". "Kedaluwarsa", not "expired".
- **Canonical identifiers stay in English** in code, events, and API fields, and never appear in the
  interface (`DESIGN_PRINCIPLES.md` P7, `UX_COPY_GLOSSARY.md`).

### Person and tense

- Instructions: second person imperative — "Periksa koneksi, lalu coba lagi."
- System state: third person, present or past — "Pesanan tersimpan." / "Pembayaran gagal."
- The system does not say "Saya" or "Kami" for routine operations. "Kami" is used only where a human
  organisation is genuinely the actor — support, policy, legal notices.

---

## 2. Casing and punctuation

- **Sentence case everywhere.** Buttons, labels, headings, table headers, menu items, tabs, badges.
- **No ALL CAPS.** Uppercase harms Indonesian legibility and reads as shouting
  (`TYPOGRAPHY.md` §7). This includes button labels and status badges.
- **No Title Case On Every Word.** It reads as English marketing copy.
- **Proper nouns keep their capitalisation:** WhatsApp, Aish Laundry App, outlet names.
- **No full stop** on button labels, field labels, table headers, badges, chips, or single-sentence
  helper text.
- **Full stops** on body copy, error messages, and any text of more than one sentence.
- **No exclamation marks** in operational, financial, or error copy. Permitted at most once in an
  onboarding or celebratory customer moment, and never in the Ops app.
- **No emoji** in product UI copy. Emoji in an outbound WhatsApp message template requires an explicit
  decision; it is not the default (Rule 08).
- **Ellipsis** only for a genuine in-progress state ("Memuat…"), never for truncation of critical
  information.

---

## 3. Button labels

**A button label is a verb phrase naming what will happen.**

| Write | Do not write |
|---|---|
| Simpan pesanan | OK |
| Bayar sekarang | Lanjutkan |
| Catat pembayaran | Submit |
| Kirim nota | Ya |
| Ambil foto bukti | Ambil |
| Minta tanda tangan | Tanda tangan |
| Batalkan pesanan | Hapus |
| Kembalikan dana | Refund |
| Coba lagi | Retry |
| Tutup | Dismiss |
| Lihat detail | Selengkapnya (as a button) |

### Rules

1. **Never "OK", "Ya", "Tidak", or "Batal"** as the sole label on a consequential dialog. A user who
   reads only the buttons must still understand the choice.
2. **Confirmation dialog buttons name their outcomes.** "Batalkan pesanan" / "Kembali", not "Ya" /
   "Tidak".
3. **Maximum four words**, ideally two.
4. **Never truncate a button label.** A label that does not fit needs to be shorter
   (`TYPOGRAPHY.md` §3.5).
5. **The destructive option is never the default focused button** and is styled distinctly
   (`DESIGN_PRINCIPLES.md` P5).
6. **The label matches the accessible name** (`ACCESSIBILITY.md` §6 rule 1).

---

## 4. Error messages

**Every error names what failed and what to do next** (Master Source §18.2 rule 4). "Terjadi kesalahan"
alone is a defect — stated as such in the Master Source, not a style preference.

### Structure

```
[What happened]. [What to do next].
```

Optionally a third part: a reference code, in `caption` style, for support.

### Examples

| Situation | Write | Do not write |
|---|---|---|
| Network failure on save | "Pesanan belum terkirim. Data tersimpan di perangkat dan akan dikirim otomatis saat koneksi kembali." | "Terjadi kesalahan" |
| Invalid phone | "Nomor telepon harus 10–13 digit. Contoh: 081234567890." | "Format salah" |
| Payment gateway rejection | "Pembayaran ditolak oleh penyedia. Coba metode lain atau catat pembayaran tunai." | "Payment failed" |
| Weight out of range | "Berat maksimum 50,0 kg per pesanan. Pisahkan menjadi beberapa pesanan." | "Nilai tidak valid" |
| Permission denied | "Kamu tidak punya akses untuk mengembalikan dana. Minta manajer outlet." | "Forbidden" |
| Expired tracking link | "Tautan ini sudah tidak berlaku. Minta tautan baru dari outlet." | "404 Not Found" |
| Session expiring | "Sesi akan berakhir dalam 1 menit. Pilih Lanjutkan untuk tetap masuk." | "Session timeout" |
| OTP wrong | "Kode salah atau kedaluwarsa. Minta kode baru." | "Invalid OTP" |
| Sync conflict | "Data pembayaran di perangkat berbeda dengan server. Periksa dan pilih yang benar." | "Conflict detected" |

### Rules

1. **Never blame the user.** "Nomor tidak ditemukan", not "Kamu salah memasukkan nomor".
2. **Never show a raw error code as the message.** A reference code may accompany the message in
   `caption` style.
3. **Never show a stack trace, exception name, HTTP status, or database error.**
4. **Never say "unexpected error"** — every error the design anticipates has a message; a genuinely
   unanticipated one still says what to do: "Coba lagi. Jika berlanjut, hubungi dukungan dengan kode
   REF-4821."
5. **The recovery must be real.** "Coba lagi" is only written where retrying can actually help.
6. **Never truncate an error message** (`TYPOGRAPHY.md` §7).
7. **Errors carry an icon and text**, never colour alone.
8. **No secret, token, OTP, credential, or full personal identifier appears in an error message**
   (Rule 03).

---

## 5. Success messages

Brief, specific, and stated in the past tense. Success does not need celebration.

| Situation | Write |
|---|---|
| Order saved | "Pesanan LDY-2026-000481 tersimpan." |
| Payment recorded | "Pembayaran Rp45.000 tercatat. Pesanan lunas." |
| Partial payment | "Pembayaran Rp30.000 tercatat. Sisa Rp45.000." |
| Proof captured | "Bukti pengiriman tersimpan." |
| Sync completed | "Semua data tersinkronisasi." |
| Settings saved | "Perubahan tersimpan." |
| Reminder sent | "Pengingat terkirim ke pelanggan." |
| Marketing opt-out | "Kamu tidak akan menerima pesan promo lagi." |

**Rules:** no exclamation marks; no "Berhasil!" alone — say what succeeded; a financial success always
states the amount and the resulting state; never claim a send succeeded when it was only queued —
"Pengingat masuk antrean" is the honest wording in that case (Rule 01).

---

## 6. Offline and sync copy

| State | Copy |
|---|---|
| Offline, nothing queued | "Mode offline. Perubahan akan dikirim saat koneksi kembali." |
| Offline, items queued | "Mode offline — 3 transaksi menunggu dikirim." |
| Syncing | "Sedang menyinkronkan 3 transaksi…" |
| Sync succeeded | "Semua data tersinkronisasi." |
| Sync failed, will retry | "2 transaksi gagal dikirim. Akan dicoba lagi otomatis." |
| Sync failed, needs action | "1 transaksi gagal dikirim. Buka antrean untuk memeriksa." |
| Conflict | "Ada data yang perlu diperiksa. Server dan perangkat berbeda." |
| Conflict detail | "Pembayaran di perangkat: Rp45.000. Di server: Rp30.000. Pilih data yang benar." |
| Queued item chip | "Menunggu Sinkronisasi" |

### Rules

1. **Offline is never phrased as an error.** No "Gagal terhubung", no "Koneksi terputus!" as a headline.
2. **The queue depth is always stated** when non-zero.
3. **A conflict never suggests a default.** Both values are shown; the user chooses (Rule 07, rule 5).
4. **Never promise a sync time.** "Akan dikirim saat koneksi kembali", never "dalam beberapa detik".
5. **A queued financial operation is never described as complete.**

---

## 7. Privacy, security, and masking copy

| Situation | Copy |
|---|---|
| Masked data on the portal | "Sebagian data disembunyikan untuk keamanan." |
| Masked phone | "08•• •••• ••21" with the caption "Nomor sebagian disembunyikan" |
| OTP required for a sensitive action | "Untuk keamanan, masukkan kode OTP yang dikirim ke nomor terdaftar." |
| Guest courier link scope | "Akses ini hanya untuk satu tugas dan akan berakhir otomatis." |
| Expired or revoked link | "Tautan ini sudah tidak berlaku. Minta tautan baru dari outlet." |
| Support impersonation active | "Sesi dukungan aktif untuk [nama tenant]. Semua aktivitas dicatat." |
| Consent request | "Boleh kami kirim info promo lewat WhatsApp? Kamu bisa berhenti kapan saja." |
| Photo privacy note | "Foto ini hanya bisa dilihat oleh kamu dan staf outlet." |

### The non-enumeration rule

**An error must never reveal whether a record exists.**

Invalid token, expired token, revoked token, and non-existent order all produce the **same** message:
"Tautan ini sudah tidak berlaku. Minta tautan baru dari outlet."

Distinguishing them would turn the message into an oracle for enumerating orders and tokens
(Rule 21, abuse cases). The same principle applies to login: "Nomor atau kode salah" is used
identically whether the number exists or not.

This rule extends to accessible names and announcements (`ACCESSIBILITY.md` §16 rule 5).

### Never in copy

Tokens, OTP values, credentials, API keys, full addresses on public surfaces, full unmasked phone
numbers on public surfaces, internal notes, staff personal details, cost or margin figures, or another
tenant's data (Rule 03, Rule 21, Master Source §9.3).

---

## 8. Payment copy

| Situation | Copy |
|---|---|
| Unpaid | "Belum Lunas — Rp75.000" |
| Partially paid | "Bayar Sebagian — Sisa Rp45.000" |
| Paid | "Lunas" |
| Reversed | "Dikembalikan Rp45.000 pada 19 Juli 2026" |
| Cash variance | "Selisih kas -Rp25.000. Tulis penjelasan sebelum menutup shift." |
| Refund confirmation | "Kembalikan Rp45.000 ke pelanggan? Tindakan ini dicatat dan tidak bisa dihapus." |
| Discount requiring approval | "Diskon di atas Rp20.000 perlu persetujuan manajer." |
| Courier cash handover | "Setor tunai Rp340.000 dari 6 pengiriman." |
| Client-side estimate | "Perkiraan total: Rp75.000. Total final dihitung saat pesanan disimpan." |

### Rules

1. **Every amount is complete and exact.** Never `Rp1,2jt` (`TYPOGRAPHY.md` §6).
2. **Never imply a client-computed figure is final.** A preview is labelled "Perkiraan".
3. **A reversal is described as a reversal**, never as a deletion. Nothing financial is deleted
   (Rule 04, rule 8).
4. **A variance is named, never softened.** No "penyesuaian kecil".
5. **Never say paid until the server confirmed it** (Rule 04, rule 5).
6. **A refund confirmation names the amount, the recipient, and the fact that it is recorded.**

---

## 9. Reminder and notification copy

The H+1 / H+3 / H+7 / H+14 ladder (Rule 10). Tone escalates in urgency, never in pressure.

| Stage | Tone | Example (fictional) |
|---|---|---|
| H+1 | Friendly | "Halo Bu Sri, cucian kamu sudah siap diambil di Outlet Melati sejak kemarin. Ditunggu ya." |
| H+3 | Second reminder | "Halo Bu Sri, cucian kamu masih di Outlet Melati sejak 16 Juli. Sisa pembayaran Rp45.000." |
| H+7 | Priority + follow-up | "Halo Bu Sri, cucian kamu sudah 7 hari di Outlet Melati. Mohon dijemput atau hubungi kami untuk atur pengantaran." |
| H+14 | Escalation to manager/owner | Internal task, not a customer message: "Pesanan LDY-2026-000481 belum diambil 14 hari. Perlu tindak lanjut manajer." |

### Rules

1. **Never threaten.** No mention of disposal, sale, donation, auction, or transfer — those are
   prohibited outright (Rule 10) and must not appear even as a warning.
2. **Never invent a storage fee.** Storage-fee legality is outside Step 2's authority
   (`DESIGN_DECISION_LOG.md` §4).
3. **Reminders respect quiet hours 20.00–08.00 outlet local time** and opt-out (Rule 08). Copy never
   implies a message will arrive immediately.
4. **Each stage fires once** (Rule 10, rule 2). Copy never references "reminder ke-3 dari 5".
5. **Transactional and marketing messages are separated** and read differently. A reminder is
   transactional and never carries a promotion.
6. **Never promise unlimited messaging** anywhere (Rule 08, rule 10).

---

## 10. Courier and delivery copy

| Situation | Copy |
|---|---|
| Suggested visit order | "Usulan urutan kunjungan" |
| Explanation beneath it | "Urutan ini saran, bukan rute tercepat. Kamu boleh mengubah urutan." |
| Estimated completion | "Perkiraan selesai: 20 Juli 2026" |
| Proof required | "Ambil foto bukti sebelum menyelesaikan pengiriman." |
| Failed delivery | "Pengiriman gagal. Pilih alasan dan cucian kembali ke outlet." |
| Cash collected | "Terima tunai Rp75.000 dari pelanggan." |
| Handover | "Setor tunai Rp340.000 ke kasir." |

### Absolutely prohibited

- "Rute optimal", "rute tercepat", "rute terbaik", or any optimisation claim (Rule 09, rule 1).
- "Dijamin sampai", "pasti tiba", or any delivery guarantee.
- A precise ETA the system does not compute. "Perkiraan" is the only permitted framing.
- Any suggestion that a proof step may be skipped.

---

## 11. Tracking portal copy

| Element | Copy |
|---|---|
| Heading | "Lacak Pesanan" |
| Order identity | "Pesanan LDY-2026-000481 · Aish Laundry Melati" |
| Status | The Indonesian label plus icon plus a one-line explanation |
| `READY_FOR_PICKUP` explanation | "Cucian kamu sudah siap diambil di outlet." |
| `OUT_FOR_DELIVERY` explanation | "Kurir sedang menuju alamat kamu." |
| Payment | "Belum Lunas — Rp45.000" |
| Estimate | "Perkiraan selesai: 20 Juli 2026" |
| Masking note | "Sebagian data disembunyikan untuk keamanan." |
| Invalid/expired link | "Tautan ini sudah tidak berlaku. Minta tautan baru dari outlet." |
| Optional app mention (dismissible, below content) | "Punya aplikasi Aish Laundry? Kamu bisa lihat semua pesanan di sana." |

**Rules:** never require an app install to see status; never show a full address; never show
photographs; never reveal whether an order exists for an invalid token; keep the answer to "sudah
selesai belum?" above the fold at 320 px.

---

## 12. Empty states

Three parts: **what is empty**, **why**, and **what to do**.

| Context | Copy |
|---|---|
| No orders yet | "Belum ada pesanan. Pesanan baru akan muncul di sini." + "Buat pesanan" |
| No search results | "Tidak ada hasil untuk 'melati'. Coba kata kunci lain atau ubah filter." |
| No unclaimed laundry | "Tidak ada cucian menumpuk. Semua pesanan siap sudah diambil." |
| Empty queue | "Tidak ada transaksi menunggu. Semua data tersinkronisasi." |
| No conflicts | "Tidak ada data yang perlu diperiksa." |
| Filtered to nothing | "Tidak ada pesanan dengan filter ini." + "Hapus filter" |

**Never** use a bare "Tidak ada data" or "Kosong". **Never** use humour or an illustration that implies
the user did something wrong. An empty state caused by a filter always offers to clear the filter.

---

## 13. Anti-dark-pattern rules

These are explicit prohibitions (`DESIGN_PRINCIPLES.md` P11).

| Prohibited | Description |
|---|---|
| Confirmshaming | "Tidak, saya tidak mau hemat". Decline options are neutral: "Tidak sekarang" |
| Pre-ticked consent | Marketing consent is always off by default |
| Asymmetric opt-out | Opting out must never take more steps than opting in |
| Roach motel | Never make leaving, exporting, or cancelling harder than joining |
| Manufactured urgency | No countdown or scarcity claim not backed by a real deadline |
| Disguised advertising | Marketing content is never styled as an operational message |
| Misleading defaults | A default never favours the business at the user's expense |
| Hidden costs | Every cost is visible before the user commits. WhatsApp provider fees are disclosed (Rule 08, rule 9) |
| Trick questions | No double negatives in a consent question |
| Forced continuity | No auto-upgrade after a trial without an explicit choice |
| Nagging | A dismissed prompt stays dismissed |
| Obstruction | Data export is never made harder for a lapsed tenant (Rule 14, guardrail 9) |
| Sneaking | Nothing is added to a total without the user seeing it |
| False hierarchy | The destructive option is never styled as the safe or preferred one |
| Bundled consent | Transactional and marketing consent are never a single checkbox |

---

## 14. Prohibited phrases

| Phrase | Why |
|---|---|
| "Terjadi kesalahan" (alone) | Master Source §18.2 rule 4 names it a defect |
| "Unlimited WhatsApp" / "WhatsApp tanpa batas" | False claim (Rule 08 rule 10, Rule 14) |
| "Rute optimal" / "rute tercepat" | False optimisation claim (Rule 09 rule 1) |
| "Dijamin sampai [waktu]" | Delivery guarantee the product does not provide |
| "Gratis selamanya" / any lifetime cloud plan | Prohibited commercially (DEC-0010) |
| "100% aman" / "sepenuhnya aman" | Unverifiable security claim |
| "Sudah teruji" for anything untested | False claim (Rule 01) |
| "Hapus pembayaran" | Financial records are reversed, never deleted (Rule 04 rule 7) |
| "Cucian akan kami lelang/donasikan/jual" | Prohibited outright (Rule 10) |
| Any raw status identifier (`READY_FOR_PICKUP`) | `DESIGN_PRINCIPLES.md` P7 |
| Abbreviated currency (`Rp1,2jt`) | `TYPOGRAPHY.md` §6 |
| "Oops", "Yuk", "Yeay", "Waduh" in operational copy | Tone (§1) |
| "Silakan hubungi administrator" without saying who | Non-actionable recovery |
| Any English technical term with an Indonesian equivalent | §1 |


---

## Money, weight, and quantity in copy

These three are the numbers that cost real money when they are misread, so the
copy rules for them are stricter than for anything else on the page.

### Money is integer Rupiah

**Money is stored and computed as integer Rupiah. Floating point is forbidden in
every money path** — pricing, totals, discounts, taxes, payments, refunds and
reconciliation alike (Rule 04). A Rupiah amount that reaches the interface has
already been computed on the server as a whole number of Rupiah; the interface's
only job is to render it.

| Rule | Correct | Wrong |
|---|---|---|
| Thousands separator is a full stop | `Rp79.000` | `Rp79,000` · `Rp 79000` |
| No decimal places — the smallest unit is one Rupiah | `Rp1.500` | `Rp1.500,00` |
| No space between the symbol and the amount | `Rp199.000` | `Rp 199.000` |
| Negative amounts are labelled, never bracketed | `Pengembalian Rp50.000` | `(Rp50.000)` |
| Tabular figures in any stacked column | aligned digits | proportional digits |

**A client-side calculation is a preview, never the final figure.** Where the
interface shows a running total before the server has answered, it says so —
"Perkiraan total" — and the confirmed figure replaces it once the server
responds. **An order is never presented as paid on client state alone.**

### Weight and quantity are never conflated

`1,5 kg` is a weight. `3 pcs` is a quantity. They are different input types with
different keyboards, different validation, and different price behaviour, and
copy never blurs them.

| Value | Format | Example |
|---|---|---|
| Weight | decimal comma, space before unit | `1,5 kg` |
| Quantity | integer, space before unit | `3 pcs` |

Writing `1,5 pcs` or `3 kg` for a per-item service is a defect, not a typo: it
is the difference between charging for one and a half kilograms and charging for
one and a half garments.

### Time and timezone

Times are 24-hour (`14:30`), displayed in **outlet local time** and stored in
**UTC**. Where a time could be read against the wrong clock — a courier working
across a timezone boundary, a report compared between outlets — the copy names
the timezone explicitly rather than leaving the reader to assume.
