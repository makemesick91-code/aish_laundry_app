# Rule 30 — Content Design and Localization

## Purpose

Copy is interface. A status label, an error message, a confirmation dialogue, and a WhatsApp template all
carry the same weight as a control, and a mistranslated or improvised term breaks the domain model just as
effectively as a renamed field. This rule fixes the content discipline. Delivered in Step 2.

## Language and locale

- **Primary language: Bahasa Indonesia.** All user-facing copy is written in Bahasa Indonesia.
- **Currency: Rupiah**, formatted for Indonesian conventions and backed by **integer** values (Rule 04).
- **Timezone: Asia/Jakarta** for business-day logic; **outlet local time** governs quiet hours and aging
  display (Rule 08, Rule 10).

## Hard rules

1. **UX copy follows the Bahasa Indonesia glossary.** `docs/domain/DOMAIN_GLOSSARY.md` is binding for
   user-facing copy exactly as it is binding for code identifiers. One concept, one term. `order` and
   `transaksi` are not interchangeable; `courier` and `driver` are not interchangeable (Rule 17).
2. **A new user-facing term requires a glossary entry in the same pull request that introduces it.** A term
   invented in a copy deck and never recorded is how a domain model quietly forks.
3. **Status labels are drawn from the canonical status sets**, rendered in their glossary Indonesian form.
   No improvised synonym, no softening adjective, no free-text status (Rule 19).
4. **Status is never conveyed by colour alone**; the text label is a required part of every status
   rendering, not an optional enhancement (Rule 27).
5. **Errors explain recovery**: what happened and what the user should do next. An error code alone is never
   an acceptable message. Copy states the recovery action in the imperative and in plain language.
6. **Copy never claims a capability the product does not have.** No "rute optimal", no guaranteed arrival
   time, no delivery guarantee, no "WhatsApp tanpa batas". Route suggestion is described with *usulan*
   semantics, never *optimal* semantics (Rule 01, Rule 08, Rule 09).
7. **Pricing text reproduces the Master Source character for character.** Figures are never rounded,
   reformatted, converted, simplified, translated, or restated from memory. Plan names are not translated.
   This repository is `PUBLIC`, so pricing drift is a commercial risk, not a typo (Rule 14, Rule 31).
8. **Copy never carries a secret or a sensitive value.** No OTP, no token, no credential, no full address,
   no full phone number appears in a notification body, a push preview, a page title, or an analytics label
   (Rule 32).
9. **Consent copy is unambiguous and positively worded.** No double negatives, no pre-ticked boxes, no
   bundling of marketing consent with terms acceptance or with transactional notifications. Withdrawal copy
   is at least as clear as the granting copy (Rule 32, Rule 08).
10. **Escalation copy never threatens.** Reminder and storage-fee templates state facts — amount, basis,
    how to stop it accruing — in neutral language. No platform template ever threatens disposal, sale,
    donation, transfer of ownership, legal action, or credit consequences (Rule 10).
11. **Copy is written and reviewed against its real rendering context**, including the lock screen for
    notifications, the smallest supported viewport, and the largest supported system font size (Rule 27).
12. **Every example datum in every copy artefact is fictional and recognisably so.** No real customer name,
    phone number, address, message, or screenshot. This constraint has no exception for "just an example"
    (Rule 31).
13. **Copy carries no unreleased commercial intent.** No unannounced plan, feature, or customer name appears
    in a label, a placeholder, or a comment on a published artefact (Rule 31).
14. **English is permitted only for technical identifiers**, never for user-facing copy. A canonical status
    identifier such as `READY_FOR_PICKUP` is a technical identifier; what the user reads is its glossary
    Indonesian label.

## Step 2 note

**No copy is implemented.** There is no string catalogue, no localization file, no widget rendering any of
it, and no runtime. The Flutter workspace is `ABSENT`. Step 2 defines the content discipline and the
user-facing terminology as **documentation only**. A copy deck is not a shipped string.

## Violation handling

- **A synonym used in place of a glossary term** — correct it, and add the mapping to the glossary if the
  synonym is genuinely common in the business (Rule 17).
- **A new user-facing term with no glossary entry** — the change is incomplete; add the entry.
- **A status label outside the canonical sets** — reject; it breaks tracking, aging, reporting, and
  notifications simultaneously (Rule 19).
- **An error message consisting only of a code, or with no recovery step** — reject.
- **A false capability claim in copy** — remove it immediately as a false claim under Rule 01, and report
  it.
- **A pricing figure altered, rounded, reformatted, or restated inaccurately** — correct it to match the
  Master Source exactly and report the drift (Rule 14).
- **An OTP, token, address, or phone number placed in notification copy** — treat as a privacy defect and
  fix before the Step closes (Rule 32).
- **Pre-ticked, bundled, or double-negative consent copy** — reject; consent obtained that way is not
  consent.
- **Threatening escalation copy, or copy implying disposal of customer laundry** — remove immediately and
  escalate to the repository owner (Rule 10).
- **A real customer datum found in a copy artefact** — remove and replace with a fictional one; if it was
  already pushed, treat it as a disclosure, not a typo (Rule 31).
