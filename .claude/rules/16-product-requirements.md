# Rule 16 — Product Requirements

## Purpose

To make the Product Requirements Document the single place where a requirement lives, so that features
cannot be invented in a pull request, a chat message, or a screen mock. Delivered in Step 1.

Backed by the Master Source (canonical) and `docs/product/PRODUCT_REQUIREMENTS.md` (derived).

## The requirement hierarchy

1. **`docs/MASTER_SOURCE.md` is canonical.** It decides what the product *is*.
2. **`docs/product/PRODUCT_REQUIREMENTS.md` is the requirement baseline.** It decides what the product
   must *do*, expressed as identified, testable requirements derived from the Master Source.
3. Every other product document — personas, journeys, use cases, MVP scope, domain models — elaborates
   the PRD. None of them may introduce a requirement the PRD does not carry.

A requirement that appears only in a journey, a use case, a diagram, a commit message, or a pull request
description **does not exist**. Add it to the PRD or drop it.

## Hard rules

1. **Every requirement has a stable ID.** Prefixes are canonical: `FR-` functional, `NFR-`
   non-functional, `SEC-` security, `TEN-` tenancy, `FIN-` financial, `OFF-` offline, `TRK-` tracking,
   `DEL-` delivery, `UCL-` unclaimed laundry, `NOT-` notification, `SUB-` subscription, `RPT-` reporting.
2. **Identifiers are permanent and never reused.** A withdrawn requirement keeps its ID and gains a
   withdrawal note. Reusing an ID silently rewrites history in every document that cited it.
3. **No requirement without an ID.** An unidentified requirement cannot be traced, tested, or verified.
4. **Every requirement states**: ID, title, statement, rationale, priority (MUST / SHOULD / COULD), the
   canonical Step that delivers it, and its current status.
5. **Every requirement carries a status from the approved vocabulary** (Rule 01). At Step 1 every
   product requirement is `NOT IMPLEMENTED`, because no runtime exists.
6. **Every MUST requirement has at least one acceptance criterion** (Rule 22). A MUST with no criterion
   is unverifiable and blocks the Definition of Done.
7. **A new feature requires a new requirement ID before implementation begins**, not afterwards.
8. **Changing a requirement's meaning requires a new ID or an explicit, dated amendment note.** Editing a
   requirement's text so that it silently means something else is the requirements equivalent of a force
   push.
9. **Requirements never claim capability that does not exist.** A requirement describes an obligation,
   not an achievement. `NOT IMPLEMENTED` is the honest status until a Step delivers it.
10. **Pricing, the roadmap, the tenant hierarchy, and the reminder ladder are reproduced exactly** from
    the Master Source, never paraphrased and never restated from memory.

## Scope discipline

- A requirement belongs to exactly one canonical Step. Work for a later Step is not pulled forward, and
  work for the current Step is not quietly deferred (Master Source §1.5).
- If a requirement cannot be assigned to a Step, it is not ready; record it as an open question rather
  than assigning it arbitrarily.
- The MVP boundary in `docs/product/MVP_SCOPE.md` is derived from DEC-0015 and the Master Source §22. It
  is not re-negotiated per pull request.

## Open questions

Genuine gaps go to `docs/product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md` and are escalated to the repository
owner. **Never close a gap by inventing a product decision** (Rule 00, rule 6). A placeholder that looks
like a decision will be read as a decision.

## Step 1 note

Step 1 produces the requirement baseline as **documentation only**. No requirement is implemented, no
runtime exists, and application CI remains `NOT APPLICABLE`.

## Violation handling

- **A feature built with no requirement ID** — stop; add the requirement to the PRD, or remove the
  feature. Retro-fitting an ID after the fact is acceptable only if it happens before the Step closes.
- **A requirement ID reused for a different requirement** — reject the change; every citation of that ID
  in every other document has silently changed meaning.
- **A requirement asserted in a journey, use case, or diagram but absent from the PRD** — the PRD wins;
  either add it properly or delete the assertion.
- **A MUST requirement with no acceptance criterion** — the Step is not done (Rule 22).
- **A pricing figure, roadmap number, hierarchy level, or reminder stage paraphrased** — correct it to
  match the Master Source character for character and report the drift.
- **An invented product decision found in any product document** — remove it immediately and raise the
  open question to the owner. Do not leave it in place "as a placeholder".
