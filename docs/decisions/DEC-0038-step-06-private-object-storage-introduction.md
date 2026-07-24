# DEC-0038 — Private Object-Storage Introduction (S3-Compatible Abstraction for QC Defect Evidence, FR-083)

- **ID:** DEC-0038
- **Title:** Private Object-Storage Introduction — S3-Compatible Abstraction for QC Defect Evidence (FR-083)
- **Status:** ACCEPTED
- **Date:** 25 July 2026

## Context

Step 6 (Production Operations) delivers the server-side quality-control gate and its defect evidence.
[FR-083](../product/PRODUCT_REQUIREMENTS.md) (Defect evidence, SHOULD) requires that a quality-control
inspector be able to attach a photograph as defect evidence to a **FAILED** inspection, "stored privately
and served only through signed expiring URLs." A photograph of a customer's garment is `RESTRICTED` data
(Rule 21): it can show the inside of a garment, a stain, or a defect a customer would not want a competitor
or a scraper to see.

Before this decision the repository had **no canonical file-upload or object-storage implementation**. Every
prior step deferred it: Rule 06 locks the architecture — "S3-compatible object storage", buckets "not
publicly readable or listable for tenant data", "private files served via signed, expiring URLs",
"object keys tenant-scoped and unguessable" — but no step had yet stood up the first concrete surface that
this architecture describes. FR-083 is that first surface.

FR-083 forced a concrete choice that Rule 06 states at the architectural level but does not pin at the
implementation level. Three paths were open:

1. **Local-disk storage now, migrate to S3-compatible later.** Fastest to write, but it inherits the exact
   migration debt Rule 06 was written to avoid: a local-disk path bakes in filesystem semantics
   (path-based access, no native signed URL, no bucket-privacy model) that Step 8 proof photos, exports,
   and every later file surface would have to be re-plumbed away from. It would also make the first
   private-file surface diverge from the locked architecture on day one.
2. **Defer FR-083 evidence to a later step.** FR-083 is a Step 6 requirement (SHOULD); deferring it leaves
   the quality-control gate without the evidence artefact the requirement names, and simply moves the same
   object-storage decision to a later step under more schedule pressure.
3. **Stand up the S3-compatible private object-storage abstraction now, as the first surface.** Honours
   Rule 06 at implementation time, avoids the local-disk migration debt, and produces a minimum reusable
   abstraction that later private-file surfaces (Step 8 proof photos, exports) can consume without a
   re-plumb.

The repository owner **explicitly selected the S3-compatible implementation (path 3)** rather than the
local-disk deviation (path 1) or deferral (path 2). This record formalises that owner-authorised choice so
that the object-storage contract, the development/CI dependency it introduces, and its security invariants
are canonical rather than an undocumented implementation detail of one test.

This record **operationalises an already-canonical architecture** (Rule 06 / Master Source §6). It does not
introduce a new architectural lock, reverse a decision, change pricing, or move a roadmap number; the only
Master Source body edit it carries is its §31 index row, the §31 count, and its §32 changelog note. It is
therefore a **PATCH** under §1.2 (see the Master Source 1.4.10 changelog entry), in the same class as
DEC-0036.

## Options considered

- **Local disk with a later migration to S3.** Rejected: reintroduces the migration debt Rule 06 exists to
  prevent, and makes the first private-file surface non-conformant with the locked architecture.
- **Defer FR-083 evidence.** Rejected: FR-083 is Step 6 scope; deferral moves the same decision to a later
  step and leaves the quality-control gate without its evidence artefact.
- **A generic media library / public asset CDN.** Rejected as out of scope and dangerous: FR-083 needs a
  narrow, private, authorization-gated evidence store, not a general-purpose or publicly readable one.
- **S3-compatible private object storage, MinIO for development and CI (SELECTED).** Honours Rule 06,
  produces a minimum reusable private-object-storage abstraction, and keeps every byte private behind
  application-level authorization and short-lived signed URLs.

## Decision

The first private object-storage surface uses an **S3-compatible private object-storage abstraction**,
under the following locks:

1. **S3-compatible abstraction.** The application talks to storage through an S3-compatible object-storage
   interface, never through a filesystem path. The provider is swappable behind the abstraction.
2. **MinIO for local development and CI, pinned by digest.** The development and CI object store is
   **digest-pinned MinIO** (not a floating tag), consistent with Rule 37's digest-pin requirement for
   mirrored/proxied images.
3. **Development MinIO is loopback-bound only.** The development object store binds to local/loopback
   infrastructure and grants access to nothing beyond a throwaway local service (Rule 43, Rule 45).
4. **Buckets remain private.** No bucket holding tenant data is publicly readable or listable.
5. **No anonymous object access.** The store grants no anonymous read of any object.
6. **No permanent public URLs.** No object is ever exposed through a permanent, public, or indexable URL.
7. **Retrieval only after application-level authorization.** A byte is served only after the application has
   verified the caller's tenant, outlet, membership, and permission for that specific object.
8. **Retrieval uses short-lived signed URLs.** Authorized retrieval is through a signed URL that expires.
9. **Object keys are random and non-guessable.** Keys are tenant-scoped and unguessable; a sequential or
   derivable key is an enumeration vulnerability (Rule 06 hard rule 17).
10. **File validation is content-based.** Validation reads the actual bytes; the client-declared content
    type is untrusted (Rule 03 hard rule 12).
11. **MIME, size, dimensions, malformed-image rejection, and checksum verification are mandatory.** A
    server-detected MIME check, a size bound, a dimension bound, malformed-image rejection, and a SHA-256
    checksum are all required before an object is accepted.
12. **Upload metadata and audit history are append-only where canonical.** The evidence audit record is
    append-only; a correction is a new record, never an in-place rewrite (Rule 04, Rule 46).
13. **Idempotency is required.** Upload is idempotent on `client_reference`; a retry produces exactly one
    stored object and one evidence record (Rule 07, Rule 20).
14. **Sensitive local pending uploads use the existing encrypted offline queue.** A pending upload on the
    Ops surface is held in the existing durable, encrypted offline queue, not a new insecure store
    (Rule 07 hard rule 8).
15. **Deployment remains ABSENT and is not authorised by this decision.** Standing up a development/CI
    object store is not a deployment, and this record confers no deployment authorization.

## Scope

**In scope.**

- FR-083 QC defect photos: capture, content validation, private storage, signed-URL retrieval, append-only
  evidence audit, and durable offline upload.
- The **minimum reusable private-object-storage abstraction** that FR-083 genuinely requires.

**Explicitly out of scope.**

- **Step 8 proof photos are a candidate future reuse, but this decision does not implement or authorise
  Step 8.** Pickup/delivery proof-of-custody photos (Rule 09) remain forbidden runtime and are authorised
  only by their own future canonical decision, exactly as Rule 49's precedent requires.
- **No generic media library.**
- **No public asset CDN.**
- **No arbitrary user file uploads.**
- **No production object-storage credentials, and no deployment.**

## Security invariants

The following invariants are canonical for this surface and any surface that later reuses the abstraction:

- **Tenant and outlet isolation.** Every object and evidence record carries `tenant_id`; a foreign
  tenant's or outlet's object is unreachable through every access path — direct ID, list, filter, search,
  export, and file URL (Rule 02, Rule 39, Rule 48).
- **Uploader authorization.** Only a caller with the quality-control permission may upload; operator,
  cashier, and unauthenticated callers are refused server-side (Rule 40).
- **Failed-QC precondition.** Defect evidence attaches only to a **FAILED** inspection.
- **Private retrieval.** Retrieval is served only after application-level authorization.
- **Signed-URL expiry.** A retrieval URL is short-lived and expires.
- **No filename-trusted MIME.** The server detects the content type from the bytes; the client-declared
  type is never trusted.
- **No executable content.** Only validated image content is accepted; executable or script content is
  rejected.
- **Random storage paths.** Object keys are random and non-guessable.
- **No object bytes in logs or evidence.** Neither object bytes, nor a signed URL, nor a token appear in
  logs, telemetry, or a committed evidence artefact (Rule 46, Rule 01, Rule 23).
- **Safe local cleanup after canonical acknowledgement.** A local pending upload is cleaned up only after
  the server has canonically acknowledged it.
- **Logout and tenant-switch protection for pending uploads.** A pending upload is not exposed or leaked
  across a logout or a tenant switch (Rule 02, Rule 07).

## Operational consequences

- **MinIO is a local/CI dependency.** The development and CI object store is a digest-pinned, loopback-bound
  MinIO service under `infrastructure/`, holding only fictional data.
- **Production must use an S3-compatible private service when deployment is later authorised.** The
  application contract does not change; only the concrete provider is configured at deployment time, under
  its own future authorization.
- **Production credentials remain external secrets.** No production object-storage credential is ever
  committed; configuration is read from the environment (Rule 03, Rule 45).
- **Storage health and bucket privacy must be checked.** Bucket privacy (no anonymous access) and storage
  reachability are verifiable gates; `scripts/check-dev-services.sh` proves the development bucket grants no
  anonymous access.
- **Backup, retention, lifecycle, and production storage provisioning remain future deployment concerns**
  and are not authorised or implemented here.

## Consequences

Recording the owner-authorised object-storage introduction makes the object-storage contract, its
dependency, and its security invariants canonical, so that later private-file surfaces inherit a conformant
abstraction instead of re-deciding it under pressure.

### Positive consequences

- The first private-file surface conforms to the locked Rule 06 architecture from day one, avoiding
  local-disk migration debt.
- A minimum reusable private-object-storage abstraction exists for later surfaces (Step 8 proof photos,
  exports) to consume without a re-plumb — better architectural portability.
- The object-storage security invariants (private buckets, no anonymous access, signed-URL retrieval,
  content-based validation, random keys, idempotency) are canonical and enforceable, not an undocumented
  property of one test.
- The owner's explicit choice is on record with a supersession policy, so a later provider change is a
  bounded, documented event.

### Negative consequences / trade-offs

- **An additional local and CI service.** Development and CI now run a MinIO container alongside PostgreSQL
  and Redis.
- **A larger dependency surface.** An S3-compatible client and MinIO image are added to the toolchain
  (digest-pinned; Rule 37).
- **More complex test setup.** FR-083 tests require a reachable MinIO, so a precondition that is not met is
  a visible SKIP rather than a false pass (Rule 01), and the backend suite has more moving parts.
- These costs are accepted in exchange for architectural conformance and the avoidance of a local-disk
  migration later.

## Verification

- **Real MinIO integration tests.** The backend `QualityControlEvidenceTest` runs against a **real private
  MinIO** bucket, not a filesystem or in-memory substitute.
- **No-anonymous-access check.** `scripts/check-dev-services.sh` proves the development bucket grants no
  anonymous read (no public bucket).
- **Content-validation tests.** A non-image, a malformed image, an undersized image, and an oversize file
  are all refused; the server-detected MIME is used, not the client-declared type.
- **Signed-URL tests.** Retrieval is only through a short-lived signed URL.
- **Tenant-isolation tests.** A foreign tenant's job, inspection, or evidence 404s exactly like an absent
  one, across every access path (Rule 48).
- **Idempotency tests.** A retry on the same `client_reference` produces exactly one object and one
  evidence record.
- **Offline upload persistence tests.** A pending upload survives app kill and a tenant switch on the Ops
  surface (Flutter F1/F3/F6).
- **Exact-SHA CI.** Every claim above is bound to the exact commit SHA it ran against and is re-verified by
  `scripts/verify-step-06.sh` and the runtime CI at the candidate SHA (Rule 01, Rule 47, DEC-0013).
- **This governance record itself** is audited by `scripts/validate-dec-0038-object-storage.py`, which is
  adversarially tested by `scripts/test-step-06-validators.sh` before it is relied upon as a gate (Rule 33,
  Rule 47).

## Requirement references

- FR-083 — Defect evidence (SHOULD), Step 6, File and Evidence Management.
- FR-081 — the server-side quality-control gate FR-083 evidence attaches to.

## Rule references

- Rule 06 — backend and API foundation; the locked S3-compatible object-storage architecture (hard rules
  15–17: private buckets, signed expiring URLs, unguessable tenant-scoped keys).
- Rule 03 — security and privacy; content validation, private files behind signed URLs, no secrets
  committed, laundry photographs are private data.
- Rule 21 — data classification; laundry photographs are `RESTRICTED`.
- Rule 02 / Rule 39 / Rule 48 — tenant isolation across every access path, including file URLs.
- Rule 40 — server-side RBAC; uploader authorization.
- Rule 04 / Rule 18 — append-only correction discipline for the evidence audit; idempotency.
- Rule 07 / Rule 20 — offline-first durable encrypted queue and idempotency on `client_reference`.
- Rule 37 — digest-pinned images and dependency discipline.
- Rule 43 / Rule 45 — PostgreSQL/loopback-bound dev services and public-repository seed/fixture safety.
- Rule 46 — no object bytes, signed URLs, or tokens in logs, telemetry, or evidence.
- Rule 36 hard rule 8 — widening the scope guard requires a decision record; this record does **not** widen
  the guard and does not authorise Step 8 or deployment.

## Supersession policy

- The **S3-compatible object-storage contract remains canonical.** A future provider may replace MinIO in
  production without changing the application contract; that is a configuration and adapter change, not a
  superseding decision.
- Moving to **public buckets, permanent public URLs, local-disk production storage, or a non-S3
  architecture** requires a **new decision record** and a Master Source version bump; none of those is
  authorised by this record.
- Authorizing **Step 8 proof photos**, or any additional private-file surface, requires that surface's own
  canonical authorization (Rule 49's precedent); reusing this abstraction there does not by itself authorise
  the surface.
- This record is superseded, never edited into a different decision; a superseded record keeps its content
  and gains a supersession note pointing at its replacement (§31.1).

## Related Master Source sections

- §1 — canonical rules, conflict order, and the amendment/versioning procedure (§1.2).
- §6 — architecture and the locked backend stack, including S3-compatible object storage.
- §15.8 — public-repository safety.
- §24 — Roadmap; the Step 6 entry and the roadmap lock.
- §31 — Decision records.
- §32 — Changelog.
