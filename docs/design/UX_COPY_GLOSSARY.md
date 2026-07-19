# UX Copy Glossary — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Derived from:** Master Source §19 (state machines), Rule 19, Rule 17
**Scope:** DOCUMENTATION ONLY

---

## 1. The two registers

The system holds **one canonical identifier** per concept and **one Indonesian user-facing label** per
identifier (`DESIGN_PRINCIPLES.md` P7).

| Register | Form | Where it lives |
|---|---|---|
| **Canonical identifier** | English, `SCREAMING_SNAKE_CASE` | Code, database columns, API fields, domain events, logs, this documentation |
| **User-facing label** | Bahasa Indonesia, sentence case | Every user interface, on every surface |

### Binding rules

1. **A canonical identifier never appears in a user interface.** `READY_FOR_PICKUP` rendered on a screen
   is a defect.
2. **A user-facing label never appears as a value in code**, an API field, an event payload, or a
   database column.
3. **The mapping is one-to-one.** One identifier has exactly one label. One label serves exactly one
   identifier within its set.
4. **The label is identical on all four surfaces.** "Siap Diambil" reads the same on Customer Android,
   Ops Android, Console Web, and the tracking portal.
5. **Adding, removing, or renaming a canonical status requires a decision record** (Rule 19, rule 1).
   Changing a *label* requires an entry in `DESIGN_DECISION_LOG.md` and a review of every surface that
   shows it.
6. **Labels are never abbreviated to fit a layout.** The layout changes (`TYPOGRAPHY.md` §3.5).
7. **Labels are never translated per tenant.** A tenant cannot rename "Siap Diambil".

---

## 2. Order statuses — the fifteen

Canonical set from Master Source §19 / Rule 19. Colours from `COLOR_AND_CONTRAST.md` §8.1; icons from
`ICONOGRAPHY.md` §4.

| Canonical identifier | Indonesian label | Customer-facing explanation |
|---|---|---|
| `DRAFT` | Draf | "Pesanan belum selesai dibuat." |
| `RECEIVED` | Diterima | "Cucian kamu sudah kami terima." |
| `AWAITING_PROCESS` | Menunggu Proses | "Cucian kamu menunggu giliran diproses." |
| `SORTING` | Disortir | "Cucian kamu sedang dipilah." |
| `WASHING` | Dicuci | "Cucian kamu sedang dicuci." |
| `DRYING` | Dikeringkan | "Cucian kamu sedang dikeringkan." |
| `FINISHING` | Disetrika | "Cucian kamu sedang disetrika dan dirapikan." |
| `QUALITY_CONTROL` | Pemeriksaan | "Cucian kamu sedang diperiksa kualitasnya." |
| `REWORK` | Diproses Ulang | "Cucian kamu sedang diproses ulang agar hasilnya sesuai." |
| `READY_FOR_PICKUP` | **Siap Diambil** | "Cucian kamu sudah siap diambil di outlet." |
| `SCHEDULED_FOR_DELIVERY` | Dijadwalkan Antar | "Pengantaran cucian kamu sudah dijadwalkan." |
| `OUT_FOR_DELIVERY` | **Sedang Diantar** | "Kurir sedang menuju alamat kamu." |
| `COMPLETED` | Selesai | "Pesanan kamu sudah selesai." |
| `CANCELLED` | Dibatalkan | "Pesanan ini dibatalkan." |
| `ISSUE` | Ada Kendala | "Ada kendala pada pesanan ini. Kami akan menghubungi kamu." |

**Required anchors, fixed:** `READY_FOR_PICKUP` → **"Siap Diambil"**; `OUT_FOR_DELIVERY` →
**"Sedang Diantar"**.

**Notes:**

- `FINISHING` is "Disetrika" rather than a literal "Penyelesaian" because that is the word used in
  Indonesian laundry practice. It covers ironing, folding, and packing.
- `QUALITY_CONTROL` is "Pemeriksaan", not "Kontrol Kualitas" — shorter and more natural.
- `ISSUE` is "Ada Kendala", deliberately non-alarming. It is a real state with a reason and an owner
  (Rule 19, rule 7), not an error screen.
- `REWORK` never restarts the aging clock (Rule 10, Rule 18 invariant 17). No label or explanation may
  imply the order is "starting again".

---

## 3. Pickup and delivery statuses — the eleven

| Canonical identifier | Indonesian label | Explanation |
|---|---|---|
| `REQUESTED` | Diminta | "Permintaan jemput sudah dikirim." |
| `CONFIRMED` | Dikonfirmasi | "Permintaan kamu sudah dikonfirmasi outlet." |
| `SCHEDULED` | Dijadwalkan | "Jadwal sudah ditetapkan." |
| `ASSIGNED` | Ditugaskan | "Kurir sudah ditugaskan." |
| `EN_ROUTE` | Dalam Perjalanan | "Kurir sedang dalam perjalanan." |
| `ARRIVED` | Tiba di Lokasi | "Kurir sudah tiba di lokasi." |
| `PICKED_UP` | Sudah Dijemput | "Cucian kamu sudah dijemput kurir." |
| `DELIVERED` | Sudah Diantar | "Cucian kamu sudah diantar." |
| `FAILED` | Gagal | "Pengiriman gagal. Cucian kembali ke outlet." |
| `RESCHEDULED` | Dijadwalkan Ulang | "Jadwal diubah." |
| `CANCELLED` | Dibatalkan | "Permintaan ini dibatalkan." |

**Notes:**

- `CANCELLED` appears in both the order set and this set with the same label. That is intentional: it is
  the same concept in two lifecycles, and the surrounding context disambiguates.
- `FAILED` is a **first-class outcome**, not an exception (Rule 19, rule 8). Its presentation always
  includes the recorded reason and the next action — never a bare "Gagal".
- No label in this set implies a guaranteed arrival time. "Dalam Perjalanan" states a fact; it promises
  nothing.

---

## 4. Quality control statuses — the four

| Canonical identifier | Indonesian label | Explanation |
|---|---|---|
| `PENDING` | Menunggu Pemeriksaan | "Belum diperiksa." |
| `PASSED` | Lolos Pemeriksaan | "Hasil pemeriksaan sesuai standar." |
| `FAILED_REWORK_REQUIRED` | Perlu Diproses Ulang | "Hasil belum sesuai. Cucian diproses ulang." |
| `WAIVED_WITH_AUTHORIZATION` | Dikecualikan dengan Izin | "Pemeriksaan dikecualikan atas izin [peran]." |

`WAIVED_WITH_AUTHORIZATION` **always renders with the authorising actor and the recorded reason**. A
bare badge hides the accountability that makes the waiver legitimate (Rule 19).

---

## 5. Payment states

| Canonical identifier | Indonesian label | Explanation |
|---|---|---|
| `PAYMENT_PENDING` | **Belum Lunas** | "Pembayaran belum diterima." |
| `PAYMENT_PARTIAL` | Bayar Sebagian | "Sebagian sudah dibayar. Sisa Rp[jumlah]." |
| `PAYMENT_SETTLED` | Lunas | "Pembayaran sudah lunas." |
| `PAYMENT_REVERSED` | Dikembalikan | "Dana dikembalikan pada [tanggal]." |
| `PAYMENT_FAILED` | Pembayaran Gagal | "Pembayaran tidak berhasil diproses." |

**Required anchor, fixed:** `PAYMENT_PENDING` → **"Belum Lunas"**.

"Belum Lunas" is chosen over "Belum Dibayar" because it correctly covers the partial case in a
customer's mental model — the bill is not settled — and because "lunas" is the word an Indonesian
customer expects on a nota.

`PAYMENT_REVERSED` is "Dikembalikan", never "Dihapus". Financial records are never deleted
(Rule 04, rule 7).

---

## 6. Sync and connectivity states

| Canonical identifier | Indonesian label | Explanation |
|---|---|---|
| `ONLINE` | Terhubung | (usually not displayed; absence of the offline banner suffices) |
| `OFFLINE` | Mode Offline | "Perubahan akan dikirim saat koneksi kembali." |
| `SYNC_PENDING` | Menunggu Sinkronisasi | "Data ini belum terkirim ke server." |
| `SYNC_IN_PROGRESS` | Sedang Disinkronkan | "Data sedang dikirim." |
| `SYNC_SUCCEEDED` | Tersinkronisasi | "Data sudah tersimpan di server." |
| `SYNC_FAILED` | Gagal Sinkronisasi | "Data gagal dikirim. Akan dicoba lagi." |
| `SYNC_CONFLICT` | **Perlu Diperiksa** | "Data di perangkat berbeda dengan server." |
| `SYNC_QUEUED_FINANCIAL` | Transaksi Menunggu | "Transaksi keuangan menunggu dikirim." |

**Required anchor, fixed:** `SYNC_CONFLICT` → **"Perlu Diperiksa"**.

"Perlu Diperiksa" is deliberately not "Konflik". It states what the user must **do** rather than naming
a technical condition, and it avoids implying that something is broken — the data is intact, a choice
is required (Rule 07, rule 5).

---

## 7. UX states

Generic interface states, used across components.

| Canonical identifier | Indonesian label / copy |
|---|---|
| `LOADING` | "Memuat…" |
| `EMPTY` | Context-specific (`CONTENT_DESIGN.md` §12) |
| `ERROR` | Context-specific (`CONTENT_DESIGN.md` §4) |
| `NO_RESULTS` | "Tidak ada hasil" |
| `READ_ONLY` | "Hanya baca" |
| `PERMISSION_DENIED` | "Tidak ada akses" |
| `EXPIRED` | "Sudah tidak berlaku" |
| `REVOKED` | "Akses dicabut" |
| `REQUIRED` | "Wajib" |
| `OPTIONAL` | "Opsional" |
| `SELECTED` | "Dipilih" |
| `DISABLED` | (no label; always accompanied by a reason — `COLOR_AND_CONTRAST.md` §6) |
| `SAVING` | "Menyimpan…" |
| `SAVED` | "Tersimpan" |
| `UNSAVED_CHANGES` | "Ada perubahan yang belum disimpan" |
| `RETRYING` | "Mencoba lagi…" |
| `IMPERSONATION_ACTIVE` | "Sesi dukungan aktif" |

---

## 8. Aging ladder

| Band | Label | Note |
|---|---|---|
| Under H+1 | (no label; age shown as text) | — |
| H+1 | "H+1" | Pengingat pertama |
| H+3 | "H+3" | Pengingat kedua |
| H+7 | "H+7" | Pengingat prioritas + tugas tindak lanjut |
| H+14 | "H+14" | Eskalasi ke manajer / pemilik |

The age is displayed as `H+n` text, always. **No label in this ladder implies disposal, sale,
donation, auction, or transfer of a customer's belongings** — those are prohibited outright (Rule 10).

Unclaimed laundry as a concept is labelled **"Cucian Menumpuk"** in the interface.

---

## 9. Domain vocabulary

Canonical Indonesian terms for domain concepts. One concept, one term (Rule 17, rule 3).

| Concept | Indonesian term | Do not use |
|---|---|---|
| Order | Pesanan | Transaksi, order |
| Order number | Nomor pesanan | ID pesanan, kode |
| Customer | Pelanggan | Customer, klien |
| Outlet | Outlet | Cabang, toko, gerai |
| Laundry brand | Brand | Merek, nama usaha |
| Tenant / organisation | Bisnis | Tenant, organisasi, akun |
| Service | Layanan | Servis, paket |
| Price list | Daftar harga | Katalog, tarif |
| Receipt / nota | Nota | Struk, invoice, kwitansi |
| Invoice | Tagihan | Invoice |
| Payment | Pembayaran | Bayaran |
| Refund | Pengembalian dana | Refund |
| Cash | Tunai | Cash |
| Shift closing | Tutup shift | Closing, tutup kasir |
| Cash variance | Selisih kas | Selisih, gap |
| Pickup | Jemput | Pickup, penjemputan |
| Delivery | Antar | Delivery, pengiriman (except in "gagal dikirim") |
| Courier | Kurir | Driver, ojek (except "ojek lokal" for the external role) |
| External courier | Ojek lokal | Mitra, partner |
| Proof of delivery | Bukti pengiriman | POD |
| Proof of pickup | Bukti penjemputan | POP |
| Signature | Tanda tangan | TTD |
| Time window | Rentang waktu | Slot, jadwal |
| Route suggestion | Usulan rute | Rute optimal, rute tercepat |
| Weight | Berat | Bobot |
| Quantity | Jumlah | Qty, kuantitas |
| Reminder | Pengingat | Notifikasi (that is the channel, not the message) |
| Notification | Notifikasi | Pemberitahuan |
| Quality control | Pemeriksaan | QC, kontrol kualitas |
| Rework | Proses ulang | Rework, ulang |
| Tracking | Lacak / pelacakan | Tracking |
| Tracking link | Tautan lacak | Link tracking |
| Subscription | Langganan | Subscription |
| Plan | Paket | Plan, tier |
| Staff | Staf | Karyawan (in system copy), pegawai |
| Role | Peran | Role |
| Permission | Izin | Permission, hak akses |
| Tenant switcher | Ganti bisnis | Switch tenant |
| Sync | Sinkronisasi | Sync |
| Queue | Antrean | Queue |
| Offline | Mode offline | Luring |
| Export | Ekspor | Download data |
| Audit log | Catatan aktivitas | Audit log |

### Role names

| Canonical role | Indonesian label |
|---|---|
| Cashier | Kasir |
| Outlet manager | Manajer outlet |
| Production operator | Operator produksi |
| Quality control | Pemeriksa kualitas |
| Courier | Kurir |
| Laundry admin | Admin laundry |
| Owner | Pemilik |
| Tenant admin | Admin bisnis |
| Finance | Keuangan |
| Platform admin | Admin platform |

---

## 10. Indonesian data-format rules

Canonical formats. These are reproduced in `TYPOGRAPHY.md` §6 and must match character for character.

| Kind | Format | Example |
|---|---|---|
| Currency | `Rp` + `.` thousands separator, no space, no decimals | `Rp79.000`, `Rp1.240.000` |
| Zero | Explicit | `Rp0` |
| Negative / variance | Minus before the prefix | `-Rp25.000` |
| Weight | Decimal comma, one decimal, space before unit | `1,5 kg`, `12,0 kg` |
| Quantity | Integer, space before unit | `3 pcs` |
| Percentage | Decimal comma, space before `%` | `12,5 %` |
| Time | 24-hour, colon | `08:00`, `20:00` |
| Time window | En dash | `08:00–10:00` |
| Date | Day + Indonesian month + year | `19 Juli 2026` |
| Short date | Numeric | `19/07/2026` |
| Date and time | Date + space + 24-hour time | `19 Juli 2026 14:30` |
| Relative age | `H+` + integer | `H+3`, `H+14` |
| Order number | Tenant-defined prefix + year + sequence | `LDY-2026-000481` (fictional) |
| Masked phone (public) | Prefix + mask + last two digits | `08•• •••• ••21` |
| Masked name (public) | First name + initial | `Bu Sri W.` |
| Partial address (public) | Kecamatan and city only — **never the full address** | `Kec. Cicendo, Bandung` |

### Month names

Januari · Februari · Maret · April · Mei · Juni · Juli · Agustus · September · Oktober · November ·
Desember

### Day names

Senin · Selasa · Rabu · Kamis · Jumat · Sabtu · Minggu

### Storage and display

- **Stored in UTC.** Displayed in the **outlet's local time**.
- Business-day logic uses **Asia/Jakarta** (Master Source).
- Where a displayed time could be ambiguous — a cross-outlet report spanning timezones — the timezone is
  stated next to the value.
- Quiet hours are **20.00–08.00 outlet local time** (Rule 08, rule 6) and are displayed in that outlet's
  time, never in the viewer's.

### Number formatting rules

1. **Money is never abbreviated.** `Rp1,2jt` is prohibited everywhere, including charts and KPI cards.
2. **Money is never rendered with decimals.** The smallest unit is one Rupiah (Rule 04, rule 1).
3. **Weight always shows one decimal**, even when whole: `12,0 kg`.
4. **Quantity never shows a decimal.** Quantity is a count; weight is a measurement. They are distinct
   inputs and distinct displays (`FORM_AND_VALIDATION_PATTERNS.md` §8).
5. **Tabular figures for every number in a table, receipt, or money context** (`TYPOGRAPHY.md` §6).

---

## 11. Glossary maintenance

1. **A new domain term requires an entry here in the same pull request that introduces it**
   (Rule 17, rule 2).
2. **A new canonical status requires a decision record first** (Rule 19, rule 1), then an entry here.
3. **Changing a label** requires an entry in `DESIGN_DECISION_LOG.md` and a review of every surface
   that displays it.
4. **A synonym found in use** is corrected, and the synonym is added to the "do not use" column if it is
   genuinely common in the business.
5. **This document and `docs/domain/DOMAIN_GLOSSARY.md` must not contradict each other.** The domain
   glossary owns the concept; this document owns its user-facing label. Where they disagree, the domain
   glossary wins on the concept and this document is corrected.
