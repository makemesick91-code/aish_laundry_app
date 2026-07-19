# DEC-0004 — Flutter Client and Web Console

## ID

DEC-0004

## Title

Flutter Client and Web Console

## Status

ACCEPTED

## Date

19 July 2026

## Context

Aish Laundry App needs three authenticated client surfaces — a customer application, a staff operations
application, and a management console — plus one unauthenticated public tracking surface. The team is one
owner supported by AI agents. Every additional client technology multiplies the cost of every feature.

Market realities constrain the choice:

- **Android dominates.** Laundry staff and their customers in the Indonesian UMKM segment use Android,
  frequently low-end and mid-range devices. iOS is a minority not worth the cost at this stage (§23).
- **The Ops application must work offline** (§13). Shop-floor connectivity is unreliable and a kasir
  cannot stop taking orders. That requires real local storage, a durable queue, and background work —
  which pushes strongly toward a native or near-native client rather than a web page.
- **The console is a desktop-shaped, reporting-heavy surface** typically used on a laptop in an office.
- **The tracking portal is opened once, by a stranger, on an unknown device, over an unknown network.**
  Cold-start weight matters more there than anywhere else in the product.

Options considered: native Android plus a separate web stack; React Native plus a web stack; a
progressive web application for everything; or Flutter across mobile and web.

## Decision

**Flutter and Dart are the canonical client technology.**

1. **Aish Laundry Customer Android** — Flutter.
2. **Aish Laundry Ops Android** — Flutter.
3. **Aish Laundry Console Web** — Flutter Web.
4. **Portal Tracking Publik** — browser-based, **no app installation required**. **Flutter is not
   mandatory for this surface**: if a lighter web stack performs materially better on low-end Android
   browsers over poor networks, that stack is chosen, and the choice is recorded in a decision record in
   the Step that builds it.

Supporting rules:

- The three Flutter surfaces share code through the packages in `packages/` — design system, core,
  domain, auth, networking, local storage, offline sync, observability, and testing.
- All clients consume the same versioned REST API (DEC-0005). No client talks to the database, and no
  business logic lives only in a client.
- The design system (Step 2) is the single source of visual truth for all Flutter surfaces.
- Android is the canonical mobile platform. iOS is a non-goal at this stage (§23) and would require a new
  decision record.

## Consequences

One language, one toolchain, one component library, and one testing approach cover three of the four
surfaces. The `packages/` layout in the monorepo exists specifically to make that sharing real rather
than aspirational. The Flutter workspace is created in Step 3; the design system precedes screens in
Step 2. The tracking portal is deliberately excluded from the uniformity requirement because its
performance constraint outranks consistency.

## Positive consequences

- A single team — realistically one owner and AI agents — can maintain three clients.
- Domain models, validation rules, API clients, offline queue logic, and design tokens are written once.
- Flutter's control over rendering supports the design foundation in §18: consistent status treatment,
  accessible contrast, device font scaling, and restrained motion on low-end devices.
- Strong local-storage and background-work capabilities support the offline-first requirements in §13,
  which a pure web application could not meet.
- Flutter Web lets the console reuse the design system and domain packages rather than becoming a second
  independent product.

## Negative consequences / trade-offs

- **Flutter Web is heavy on first load.** This is precisely why the tracking portal is exempted; the
  exemption is an admission of a real weakness, not a hedge.
- Flutter Web accessibility and text-selection behaviour are weaker than a native HTML console would be.
  The console's audience is small and internal, which makes this tolerable; it would not be tolerable for
  the public portal.
- The team must maintain Dart expertise, which is a narrower talent pool than JavaScript in the local
  market.
- Application binary size on low-end Android devices is larger than a lean native application, which
  matters for the customer app where installation is optional (DEC-0014).
- Permitting a different stack for the portal reintroduces a second technology, contradicting the
  single-stack rationale. This is accepted deliberately: the portal is the product's most exposed and
  most performance-sensitive surface, and it is small enough that a second stack there is cheap.
- Locking to Flutter now means a future iOS decision inherits Flutter whether or not that is optimal then.

## Verification

- Step 2: the design system is delivered as `packages/design_system` and is the only source of visual
  tokens.
- Step 3: the Flutter workspace exists; shared packages are consumed by more than one client, proving the
  sharing is real.
- Step 7: the tracking portal's stack choice is recorded with measured load performance on a low-end
  Android device over a constrained network. A claim that a stack is faster requires a measurement, not an
  assertion (§3.1).
- Step 11: the customer application is verified not to be required for tracking (DEC-0014).
- Step 13: performance budgets are measured on real devices.
- At the Step 0 baseline: the Flutter workspace is `ABSENT` and no client exists.

## Supersession policy

Superseded only by a decision record that names the replacement client technology, justifies it with
measured evidence rather than preference, and specifies the migration path for each affected surface.
Adding iOS, or choosing the portal's stack, does **not** supersede this record — each is a new decision
record that refines it. Requires at least a **minor** version bump of
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §5 Platforms
- §6 Architecture
- §18 UX and design foundation
- §19 Performance
- §23 Non-goals
