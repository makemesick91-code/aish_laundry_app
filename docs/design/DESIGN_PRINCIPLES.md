# Design Principles — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Derived from:** Master Source §18.2 (Canonical UX rules), Rule 05
**Scope:** DOCUMENTATION ONLY. No principle below is implemented anywhere.

---

## How to use these principles

Each principle states what it means, why it exists, **what it forbids**, and a worked example drawn
from a real operational moment in an Indonesian laundry. The "forbids" clause is the useful part: a
principle that only says nice things cannot settle an argument.

When two principles conflict, resolve in this order: **P1 Correctness Over Comfort** beats everything;
then **P2 Honesty in the Interface**; then **P3 Accessibility Is Not Optional**; then the rest by
number.

All example data below is fictional.

---

## P1 — Correctness Over Comfort

**Means:** When a design choice makes the interface pleasanter but the data less trustworthy, the data
wins. Money, tenant boundaries, custody, and status are correctness surfaces; they are not places to
optimise for delight.

**Why:** The product's users reconcile cash by hand at the end of a shift. An interface that rounds, hides,
or smooths over a discrepancy does not remove the discrepancy — it removes the owner's ability to find
it. A hidden variance is fraud-shaped (Rule 04).

**Forbids:**
- Hiding a cash variance, a sync failure, or a rejected payment because it looks untidy.
- Displaying a client-computed total in a way that implies the server has confirmed it.
- Auto-resolving a payment conflict so the user is not bothered.
- Rounding a Rupiah figure for display in a financial context.

**Worked example:** Shift closing at Outlet Melati. Expected cash is `Rp1.240.000`; counted cash is
`Rp1.215.000`. The interface shows both figures and the variance `-Rp25.000` in
`color.semantic.danger`, with the label "Selisih kas" and a required acknowledgement field. It does
**not** show a single "Kas: Rp1.215.000" and quietly log the difference. The variance is the point of
the screen.

---

## P2 — Honesty in the Interface

**Means:** The interface states what the system actually knows, and labels what it does not. A pending
operation looks pending. An unverified figure looks unverified. A suggestion is labelled a suggestion.

**Why:** This is Rule 01 expressed as pixels. The product's credibility with a UMKM owner is built on
never being caught overstating. It is also a legal-exposure question: a delivery "guarantee" the
system cannot keep is a promise the tenant has to honour.

**Forbids:**
- The word "optimal" applied to a route. The copy is *usulan rute* (route suggestion), always.
- Any guaranteed arrival time or ETA the system does not compute and cannot honour.
- "Unlimited WhatsApp" or any equivalent phrasing, anywhere, in any surface (Rule 08, Rule 14).
- A success state shown before the server confirmed the operation.
- A spinner that implies progress when nothing is in flight.

**Worked example:** The courier job list on Ops Android shows six stops in a suggested order under the
heading "Usulan urutan". Beneath it: "Urutan ini saran, bukan rute tercepat. Kurir boleh mengubah
urutan." A design that titled this "Rute optimal" would be rejected as a false claim under Rule 01,
not softened.

---

## P3 — Accessibility Is Not Optional

**Means:** Contrast, touch targets, text scaling, focus visibility, screen-reader labelling, and colour
independence are entry requirements for a component, not enhancements scheduled later.

**Why:** The Ops app runs on a cheap phone, in a hot shop, under fluorescent light, held one-handed by
someone whose other hand is holding wet laundry. The Console runs for a finance user who may operate it
entirely by keyboard. Accessibility here is not an edge case; it is the median case.

Target wording, used verbatim everywhere: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET
RUNTIME-TESTED.**

**Forbids:**
- Removing a focus indicator for any reason, including visual preference.
- A touch target below 48 × 48 dp.
- Conveying any status by colour alone.
- A layout that truncates critical information at 200% text scaling.
- A form field whose error is announced only visually.

**Worked example:** The `READY_FOR_PICKUP` badge is not "a green pill". It is a pill with
`color.semantic.success` fill, a check-circle icon, and the text "Siap Diambil". Turn the screen
greyscale and it still reads correctly. Turn the text scaling to 200% and the badge wraps to two lines
rather than clipping the label.

---

## P4 — Shortest Path for the Primary Action

**Means:** Every screen has one obvious next action, reachable in the fewest interactions, positioned
within one-handed thumb reach on Android. Taking an order is the shortest path in the entire product.

**Why:** Master Source §18.2 rule 1. A queue forms at the counter on a Saturday morning. Every extra tap
in order intake is multiplied by a few hundred orders a week and is paid for in customer patience.

**Forbids:**
- Burying the primary action behind an overflow menu.
- More than one visually primary action competing in the same view.
- A confirmation step on a routine, non-destructive, easily reversible action.
- Placing the primary action of an Android screen in the top-right corner.

**Worked example:** New order intake. Customer search, service selection, weight entry, and "Simpan &
Cetak Nota" are reachable without leaving one scroll view. Optional fields — catatan, parfum,
pewangi — are collapsed behind "Opsi lain". The primary action sits in a bottom action bar, in thumb
reach, and it is the only filled button on the screen.

---

## P5 — Destructive Actions Are Separated and Confirmed

**Means:** Anything that reverses money, cancels an order, revokes access, or destroys work is placed
away from routine controls, styled distinctly, and confirmed with a dialog that names the consequence.

**Why:** Master Source §18.2 rule 3 states it plainly: a refund is never adjacent to a print button.
Muscle memory at a busy counter is real, and adjacency is how a misfire happens.

**Forbids:**
- A destructive control adjacent to, or in the same button group as, a routine control.
- A destructive action as the default focused or default-highlighted choice in a dialog.
- A confirmation dialog whose body text does not name what will be lost.
- Confirmation by a single tap on a red button with no dialog, for a financial reversal.

**Worked example:** The order detail screen for order `LDY-2026-000481` places "Cetak Nota" and "Kirim
WhatsApp" in the bottom action bar. "Batalkan Pesanan" lives in the overflow menu, styled with
`color.semantic.danger` text, and opens a confirmation dialog: "Batalkan pesanan LDY-2026-000481?
Pesanan yang dibatalkan tidak dapat dikembalikan tanpa persetujuan manajer." The default focused
button in that dialog is "Kembali", not "Batalkan".

---

## P6 — Offline Is a First-Class State, Not an Error

**Means:** Losing connectivity is an expected operating condition, not a failure. The interface shows
what is queued, what failed, and what needs a human decision — continuously, not only when something
goes wrong.

**Why:** Rule 07. The Ops app runs on a motorbike and in a shop with patchy data. A design that treats
offline as an error screen makes the app unusable exactly when the business is busiest.

**Forbids:**
- A full-screen blocking error on connectivity loss in the Ops app.
- Hiding queued operations from the user.
- Silently dropping a failed operation.
- A payment conflict resolved without a human.
- Any offline state that looks identical to a synced state.

**Worked example:** Kasir takes three orders while the shop's connection is down. A persistent offline
banner reads "Mode offline — 3 transaksi menunggu dikirim". Each order card carries a sync chip
"Menunggu Sinkronisasi". When connectivity returns, two sync and one conflicts on payment; that one
moves to "Perlu Diperiksa" (`SYNC_CONFLICT`) and appears in a conflict panel with both values shown
and no default winner.

---

## P7 — One Vocabulary, Two Registers

**Means:** The system holds one canonical identifier per concept, in English SCREAMING_SNAKE, and one
Indonesian user-facing label per identifier. The identifier never appears in the interface; the label
never appears in code, events, or API fields.

**Why:** Rule 17 makes the glossary binding, and Master Source §1.6 makes Bahasa Indonesia the
user-facing language. Mixing the two registers is how a domain model quietly forks into synonyms.

**Forbids:**
- `READY_FOR_PICKUP` rendered raw in any user-facing surface.
- "Siap Diambil" used as a status value in an API field, event payload, or database column.
- Two Indonesian labels for one canonical status.
- One Indonesian label reused for two canonical statuses.

**Worked example:** The tracking portal shows a timeline reading "Diterima → Dicuci → Dikeringkan →
Siap Diambil". The underlying projection carries `RECEIVED`, `WASHING`, `DRYING`, `READY_FOR_PICKUP`.
The mapping is defined once, in `UX_COPY_GLOSSARY.md`, and both sides read from it.

---

## P8 — Restraint Is the Brand

**Means:** White dominates. Soft blue carries interaction. Dark blue carries structure. Gold is a rare
accent for value and achievement. Nothing glows, nothing floats, nothing animates for pleasure.

**Why:** Master Source §18.1. The product should look like a trustworthy business tool an owner is
comfortable showing to a customer — not a fintech pitch deck. Low-end Android is the performance
baseline, and heavy visual effects cost frames on exactly the devices the product must serve.

**Forbids:**
- Neon, glassmorphism, heavy gradients, science-fiction styling.
- Gold as a background for any large surface, as body text, or as a primary action colour.
- Gold as the sole indicator of a warning.
- Decorative animation, parallax, or motion that does not explain a state change.
- More than one elevation level competing in a single view region.

**Worked example:** The loyalty tier badge on Customer Android uses `color.gold.400` as a small filled
chip with `color.neutral.900` text (7.80:1) and a star icon. That is the entire gold budget for the
screen. The screen's action button is `color.semantic.primary` blue; the header is dark blue; the page
is white.

---

## P9 — Density Follows the Job, Not the Screen

**Means:** Information density is chosen by what the user is doing, not by how much room the display
happens to have. Three densities exist — compact, standard, comfortable — and each is assigned to a
context deliberately.

**Why:** A finance user reconciling 400 rows wants density. A courier at a gate in the rain wants the
opposite. Giving the courier a dense layout because their phone technically fits it is a design
failure, not an efficiency.

**Forbids:**
- Applying compact density to any courier-facing surface.
- Applying compact density to any confirmation, payment, or destructive-action surface.
- Density that reduces a touch target below 48 × 48 dp.
- Density chosen implicitly by breakpoint rather than assigned explicitly by context.

**Worked example:** The Console Web receivables table uses compact density: 40 dp rows, tabular
numerals, tight column padding. The Ops Android courier job screen uses comfortable density: one job
per card, 56 dp action buttons, generous spacing. Both consume the same tokens; they differ only in
which density set is applied.

---

## P10 — Privacy Is a Layout Decision

**Means:** What a surface may display is determined by who is looking and where, and the design encodes
that — masked by default, revealed only through an explicit, authorised path.

**Why:** Master Source §9.2, §17.2. The tracking portal is the most exposed surface in the product and
is reachable by anyone holding a link. Address, full phone number, laundry photographs, internal notes,
and other orders are simply not available there.

**Forbids:**
- A full address on the public tracking portal, under any condition.
- A full phone number on the public tracking portal without OTP verification.
- Laundry photographs on any public surface.
- A proof-of-delivery photograph rendered from anything other than a signed, expiring URL.
- An external-courier guest link view that exposes customer history, pricing, or another assignment.

**Worked example:** The public tracking page for order `LDY-2026-000481` shows "Bu Sri W." and
"08•• •••• ••21" and "Kec. Cicendo, Bandung" — never "Jl. Melati No. 12". Requesting a delivery
address change from the portal triggers OTP verification before any address field becomes editable.

---

## P11 — The Interface Never Manipulates

**Means:** No dark patterns. Consent is asked plainly, opt-out is as easy as opt-in, defaults do not
favour the business at the user's expense, and no urgency is manufactured.

**Why:** The product sends WhatsApp messages to customers who did not install anything and holds a
tenant's business data. Trust is the whole product. Rule 08 makes opt-out binding; the interface must
make it real, not technically present.

**Forbids:**
- A pre-ticked marketing consent checkbox.
- An opt-out flow with more steps than the opt-in flow.
- Confirmshaming copy ("Tidak, saya tidak mau hemat").
- Countdown timers or scarcity language not backed by a real deadline.
- Making export or data access harder for a tenant whose subscription lapsed (Rule 14, guardrail 9).

**Worked example:** The notification preferences screen lists "Info pesanan" (transactional, cannot be
disabled, with an explanation of why) and "Promo dan penawaran" (marketing, a plain switch, default
off). Turning marketing off is one tap and takes effect immediately with the confirmation "Kamu tidak
akan menerima pesan promo lagi." There is no retention interstitial.

---

## P12 — Specify for the Worst Device on the Worst Network

**Means:** The performance and layout baseline is a low-end Android phone on a poor connection, in
sunlight, with a cracked screen and 200% text scaling. Anything that only works on a good device is
not done.

**Why:** Master Source §18.2 rule 8 names low-end Android as the baseline, not the exception. The public
tracking portal in particular is opened by whoever the customer forwarded the link to, on whatever they
have.

**Forbids:**
- A layout that requires horizontal scrolling below 320 px on the tracking portal.
- Skeleton or loading states that assume a fast response.
- Blocking a primary flow on an image, map, or chart load.
- Motion specifications that assume 60 fps is always available.
- A design that depends on hover to reveal essential information.

**Worked example:** The tracking portal at 320 px shows order number, status badge, timeline, amount
due, and payment state in a single column with no horizontal scroll. The map preview is deferred: it
loads after the status content and, if it fails, the page still answers the customer's question, which
is "sudah selesai belum?"
