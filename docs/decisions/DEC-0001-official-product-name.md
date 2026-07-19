# DEC-0001 — Official Product Name

## ID

DEC-0001

## Title

Official Product Name

## Status

ACCEPTED

## Date

19 July 2026

## Context

A product that is developed largely by autonomous AI agents across many sessions, many documents, and
many future code modules will drift in naming unless the name is fixed early and enforced mechanically.
Name drift is not cosmetic: it corrupts search, breaks traceability between a decision record and the
code that implements it, produces inconsistent customer-facing copy, and makes it impossible for a
validator to check that documents agree with each other.

The situation is aggravated by three facts specific to this project:

- The local monorepo directory is `aish_laundry`, while the remote repository is `aish_laundry_app`
  (ASSUMPTION-0001 in [`../ASSUMPTIONS.md`](../ASSUMPTIONS.md)). Two plausible names already exist in the
  environment.
- The product spans four platforms, each of which will want its own shorthand.
- The owner, Aish Tech Solution, will use the name commercially in Bahasa Indonesia marketing where a
  half-translated or inconsistent name reads as amateurish.

Without a locked name, agents would reasonably invent "Aish Laundry", "Aish Laundry System",
"AishLaundry", "Laundry App", or a Bahasa Indonesia variant, and every one of those would appear
somewhere in the repository.

## Decision

The official product name is exactly **Aish Laundry App**.

1. This exact string is the only canonical product name. No alternative canonical product name may appear
   anywhere in this repository.
2. Capitalisation is exactly `Aish Laundry App`. Not `AISH LAUNDRY APP`, not `aish laundry app`, not
   `AishLaundryApp`.
3. The platform names are also canonical and derived from it:
   - **Aish Laundry Customer Android**
   - **Aish Laundry Ops Android**
   - **Aish Laundry Console Web**
   - **Portal Tracking Publik**
4. Identifiers may use technically necessary forms — `aish_laundry` for the local directory,
   `aish_laundry_app` for the remote repository, `aish-laundry-step-NN-...` for tags — without those forms
   becoming product names.
5. The owner is **Aish Tech Solution**, which is an organisation name, not a product name.

## Consequences

Every document, rule file, decision record, notification template, user-facing string, and future code
comment uses the exact name. Validators can assert its presence and assert the absence of near-miss
variants. Traceability from a decision to its implementation stays searchable. Commercial material in
Bahasa Indonesia carries one consistent brand.

## Positive consequences

- Eliminates an entire class of drift before any code exists.
- Makes mechanical validation of naming possible in `scripts/verify-step-00.sh` and its successors.
- Gives four platform names that are obviously related, which helps both staff and customers understand
  that Ops, Customer, Console, and Portal are one product.
- Removes a recurring micro-decision from every future agent session.
- Protects the owner's brand consistency from the first commit.

## Negative consequences / trade-offs

- The name is long. In narrow interface contexts a shortened display form will be needed; that shortening
  is a presentation decision and must not be promoted into documentation as a second canonical name.
- Locking the name this early forecloses a later rebrand without a superseding decision record and a
  Master Source major version bump.
- "App" in the name sits slightly awkwardly with the fact that the public tracking portal is deliberately
  **not** an app (DEC-0006, DEC-0014). This is accepted; the name describes the product, not every
  surface.
- The mismatch between the product name, the local directory, and the remote repository will need
  repeated explanation to newcomers. ASSUMPTION-0001 exists for exactly that reason.

## Verification

- `scripts/verify-step-00.sh` asserts that `docs/MASTER_SOURCE.md` contains the exact string
  `Aish Laundry App`.
- Review checks that no alternative canonical product name appears in any document.
- Any pull request introducing a new user-facing surface is reviewed for correct naming.
- From Step 2 onward, the design system holds the single display treatment of the name, so clients cannot
  diverge.

## Supersession policy

This decision is superseded only by a new decision record that:

1. states the new official product name exactly;
2. explains the commercial reason for the rebrand;
3. lists every artefact requiring a rename, including tags, which are immutable and therefore keep the
   old name permanently;
4. accompanies a **major** version bump of [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

This record is never edited to contain a different name. It keeps its content and gains a supersession
note.

## Related Master Source sections

- §1.4 Naming rules
- §2 Vision
- §5 Platforms
- §30 Positioning
