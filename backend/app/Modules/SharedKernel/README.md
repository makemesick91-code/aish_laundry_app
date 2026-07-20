# Module — SharedKernel

## Boundary

The **only** module every other module is permitted to depend on. It holds the vocabulary that is
genuinely common: shared value objects, shared contracts and interfaces, shared exceptions, and the
base types the tenancy and authorization primitives are expressed in.

## In scope

- Value objects with meaning rather than a raw type — for example an identifier type, a
  `TenantId`, a `PhoneNumber` distinct from a `MaskedPhoneNumber` because their disclosure rules
  differ (Rule 17, hard rule 13).
- Contracts and interfaces that more than one module implements.
- Shared exception types.

## Out of scope

- **Any business behaviour.** The SharedKernel is a vocabulary, not a service layer. A rule that
  belongs to one context lives in that context.
- Anything that would make two modules couple through this one. If only one module needs it, it
  belongs to that module.
- `Money`. Money is integer Rupiah (Rule 04) and arrives with the Step that introduces a financial
  path. No money type is defined in Step 3, because no money table exists in Step 3.

## Dependency direction

SharedKernel depends on **nothing** in `app/Modules/`. Every other module may depend on it. A
dependency pointing the other way is a boundary defect.

## Status

`NOT IMPLEMENTED` — directory boundary only.
