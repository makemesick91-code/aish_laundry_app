# Application and Package Identifiers

**Status:** `IN PROGRESS` — Step 3
**Canonical for:** Android `applicationId`, Dart package names, and the shared namespace.

Identifiers are hard to change once published: an Android `applicationId` is the permanent identity of
an app on a device and in a store listing, and a Dart package name is baked into every import across
the workspace. They are recorded here **before** platform scaffolding is generated, so the generator
is given a decided value rather than a default.

---

## 1. Android application identifiers

| Application | `applicationId` | Platform |
|---|---|---|
| Aish Laundry Customer | `id.aishtech.laundry.customer` | Android only |
| Aish Laundry Ops | `id.aishtech.laundry.ops` | Android only |

Reverse-DNS on `aishtech.id`, the owner being **Aish Tech Solution** (Master Source §owner). The
`.id` country namespace matches the primary market.

**Domain ownership is NOT verified by this repository.** These identifiers are internally consistent
and unique, which is what the build requires. Publishing to a store additionally requires that the
owner actually controls `aishtech.id`, and that verification has **not** been performed here. No
publication step exists in Step 3, so nothing depends on it yet. This is recorded as a limitation
rather than assumed away.

`com.example`, `example`, `example_app`, `my_app`, and `untitled` are **forbidden** and are rejected
by the DEC-0026 scaffolding gate.

## 2. Admin Web

Flutter Web has no `applicationId`. It is identified by its Dart package name, `aish_admin_web`, and
is served from a path the deployment step will decide. **No deployment exists** (`ABSENT`), so no host
or origin is claimed here.

## 3. Dart package names

Dart package names must be valid lowercase identifiers, so they cannot mirror the reverse-DNS form.
The `aish_` prefix namespaces them within the workspace and avoids collision with any pub.dev package.

| Path | Package name |
|---|---|
| `apps/customer_android` | `aish_customer_android` |
| `apps/ops_android` | `aish_ops_android` |
| `apps/admin_web` | `aish_admin_web` |
| `packages/design_system` | `aish_design_system` |
| `packages/core` | `aish_core` |
| `packages/domain` | `aish_domain` |
| `packages/auth` | `aish_auth` |
| `packages/networking` | `aish_networking` |
| `packages/local_storage` | `aish_local_storage` |
| `packages/offline_sync` | `aish_offline_sync` |
| `packages/observability` | `aish_observability` |
| `packages/testing` | `aish_testing` |

None of these is published to pub.dev. They resolve through the Dart workspace declared at the
repository root.

## 4. Rules

1. An identifier here is **canonical**. Changing one is a breaking change requiring a decision record,
   because it changes app identity and every import that cites it.
2. **No example or placeholder namespace** may reach a generated artefact.
3. The Android `applicationId` is supplied to the generator explicitly via `--org`; it is never left
   to a template default.
4. **An identifier is not a domain claim.** Recording `id.aishtech.laundry.*` asserts a namespace for
   builds, not verified ownership of `aishtech.id`.
5. Package names stay lowercase with underscores, prefixed `aish_`.

## 5. Honest status

These identifiers are **recorded**, and as of this document **no Android or Web artefact has been
built with them**. Each application remains `PLATFORM SCAFFOLDING ABSENT — BUILD NOT VERIFIED` until a
real build exits zero and an artefact exists. Domain ownership for `aishtech.id` is **unverified** and
is required before any store publication, which is out of scope for Step 3.
