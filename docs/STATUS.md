# Aish Laundry App — Canonical Status

**This file is machine-validated. The status words below are exact and must not be paraphrased.**

Baseline date: 19 July 2026 · Master Source version: 1.4.1
Status vocabulary: [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md)
Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md)

---

## 1. Step status

| Step | Title | Status |
| --- | --- | --- |
| Step 0 | Master Source and Governance | GO WITH ACCEPTED DEVIATION |
| Step 1 | Product Requirement and Domain Model | GO WITH ACCEPTED DEVIATION |
| Step 2 | Design System and UX Foundation | GO WITH ACCEPTED DEVIATION |
| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | GO WITH ACCEPTED DEVIATION |
| Step 4 | Laundry Master Data | GO |
| Step 5 | POS, Order, and Payment Foundation | IN PROGRESS |
| Step 6 | Production Operations | PLANNED |
| Step 7 | Customer Tracking and WhatsApp | PLANNED |
| Step 8 | Pickup and Delivery Operations | PLANNED |
| Step 9 | Unclaimed Laundry and Cashflow Recovery | PLANNED |
| Step 10 | Finance, Reports, and Owner Portfolio | PLANNED |
| Step 11 | Customer Android Experience | PLANNED |
| Step 12 | Subscription and Platform Administration | PLANNED |
| Step 13 | Security, Performance, Backup, and Recovery | PLANNED |
| Step 14 | Pilot and Commercial Launch | PLANNED |

Step 0 reached **GO** on 19 July 2026.

| Closure item | Value |
|---|---|
| Foundation PR | `#1`, merged |
| Foundation merge SHA | `8494bc8543b9301351da6055337832597f1f2d9f` |
| GO tag | `aish-laundry-step-00-master-source-governance-v1.0.0-go` |
| GO tag peeled commit | `8494bc8543b9301351da6055337832597f1f2d9f` |
| Ruleset | ID `19164588`, active, enforcement proven |
| Governance validators | 11 / 11 PASS |
| Security findings | 4 CRITICAL, 6 HIGH — all closed and re-verified |

**One Definition of Done item is NOT satisfied and is recorded as a deviation,
not as a pass:** the repository is **PUBLIC**, whereas the canonical facts
required PRIVATE. GitHub's free plan cannot apply a ruleset to a private
repository, so private visibility and enforced branch protection were mutually
exclusive. The repository owner was shown the tradeoff and chose PUBLIC so that
enforcement could be applied. See `ASSUMPTIONS.md` — `AMENDMENT-0001`.

Step 0 GO therefore means: every technical and governance gate passed, with the
visibility requirement deliberately amended by the owner and documented. It does
not mean the original PRIVATE requirement was met.

The deviation is locked as a decision record —
[`DEC-0016`](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md) — which records that
the **canonical desired visibility remains PRIVATE**, enumerates the binding public-repository authoring
constraints, and states that governance operates in **single-maintainer** mode with independent human
approval **ABSENT**.

---

## 1a. Step 1 closure

Step 1 reached **GO WITH ACCEPTED DEVIATION** on 19 July 2026, conferred by the repository owner
against exact-SHA evidence (DEC-0013).

| Closure item | Value |
|---|---|
| Step 1 PR | `#6`, merged |
| Step 1 merge SHA | `a518ab56e1bee53751fa99b6741b7ae598283fcf` |
| Governance amendment PR | `#7`, merged (DEC-0017, unambiguous CI contexts) |
| **Tagged commit** | `4eadbc73f8bacdc9cd2acfcc62280ac932116089` |
| GO tag | `aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go` |
| GO tag object SHA | `faed53c7ed3c5c164e48c861ed065661f6461270` |
| GO tag type | annotated, immutable |
| Ruleset | ID `19164588`, active, 0 bypass actors, 9 required checks |
| Governance validators | 32 / 32 PASS |
| Required CI checks at the tagged SHA | 9 / 9 success |
| Open `CRITICAL` findings | 0 |
| Open `HIGH` findings | 0 |

**Accepted deviation — single-maintainer governance, no independent human review.**
`MASTER_SOURCE.md` §25.1 item 12 requires a Step-closing pull request to be approved by someone other
than the author. Under single-maintainer governance that person does not exist, so the item **cannot be
satisfied**. It is recorded as a standing accepted deviation in
[`DEC-0017`](decisions/DEC-0017-single-maintainer-approval-standing-deviation.md), which names the
compensating controls and states plainly that they are **not equivalent** to independent review: a
defect that both the maintainer and the validators miss is not caught.

Step 1 GO therefore means every technical and governance gate passed, **with the independent-approval
requirement deliberately deviated from and documented**. It does not mean the requirement was met.

**Step 1 delivered documentation only.** No runtime was created. **Documentation is not
implementation**, and a written acceptance criterion is not a passed test.

**26 documented open questions remain open** for the repository owner
([`product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md`](product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md)). None was
closed by inventing a product decision, and none is a retroactive Step 1 blocker.

---

## 1b. Step 2 closure

Step 2 reached **GO WITH ACCEPTED DEVIATION** on 20 July 2026, conferred by the
repository owner against exact-SHA evidence (DEC-0013).

| Closure item | Value |
|---|---|
| Step 2 PR | `#9`, merged |
| Step 2 merge SHA | `fc4449e922a0effa86b9770f5a2863a99fe776d6` |
| Pre-tag evidence PR | `#11`, merged (part of the verified pre-tag closure) |
| **Tagged commit** | `47c07d360e8802fd78f61d41435cae3f28313137` |
| GO tag | `aish-laundry-step-02-design-system-ux-foundation-v1.3.0-go` |
| GO tag object SHA | `d02598b1e3a43db0ebfb6217d7e1d9ddf8484c3a` |
| GO tag type | annotated, immutable |
| Ruleset | ID `19164588`, active, 0 bypass actors, **12** required checks |
| Governance validators | 53 / 53 PASS |
| Adversarial mutations caught | 30 / 30 |
| Required CI checks at the tagged SHA | 12 / 12 success |
| Relationship orphans | 0 across 12 checked classes |
| Requirements classified | 498 / 498, 0 unclassified |
| Open `CRITICAL` findings | 0 |
| Open `HIGH` findings | 0 |

**Accepted deviations recorded at Step 2 GO.** Four, none of them silent:

1. **PUBLIC repository visibility** — an accepted deviation from a canonical
   desired PRIVATE ([DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)).
2. **Single-maintainer governance.**
3. **No independent human review** — `MASTER_SOURCE.md` §25.1 item 12 requires a
   Step-closing pull request to be approved by someone other than the author.
   Under single-maintainer governance that person does not exist, so the item
   **cannot be satisfied**
   ([DEC-0017](decisions/DEC-0017-single-maintainer-approval-standing-deviation.md)).
4. **Design-only accessibility** — the position is `DESIGNED TO MEET WCAG 2.2 AA
   REQUIREMENTS — NOT YET RUNTIME-TESTED`
   ([DEC-0021](decisions/DEC-0021-wcag-22-aa-aligned-accessibility-target.md)).

**The compensating controls are not equivalent to independent peer review or to
runtime accessibility testing.** A defect that both the maintainer and the
validators miss is not caught, and no assistive technology has exercised
anything. Both residual risks are accepted, not eliminated.

Step 2 GO therefore means every technical and governance gate passed, **with
those four deviations deliberately taken and documented**. It does not mean the
deviated requirements were met.

**Step 2 delivered documentation only.** No runtime was created. **Documentation
is not implementation:** a design token is not a theme, a component
specification is not a component, a wireframe is not a screen, and an
accessibility requirement is not a passed audit.

Enforcement was proved rather than assumed. Temporary pull request `#10` at
`0694ca726ac9f549f2638f0b09266bd5790d7ad4` made exactly one required context
(`ux-foundation`) fail; `mergeStateStatus` was `BLOCKED`; the pull request was
closed unmerged. The temporary branch `test/step-02-negative-enforcement-proof`
remains on the remote — branch deletion is blocked by the destructive-operations
guard and is owner territory. It is **not** a Step 2 blocker.

**26 documented open questions remain open** for the repository owner
([`product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md`](product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md)),
plus the Step 2 design open questions in
[`ux/UX_OPEN_QUESTIONS.md`](ux/UX_OPEN_QUESTIONS.md). None was closed by
inventing a product decision.

**Step 3 is COMPLETE — GO WITH ACCEPTED DEVIATION, and GO-tagged.** Runtime was
authorised by
[DEC-0024](decisions/DEC-0024-step-3-runtime-introduction-and-runtime-scope-guard-transition.md),
built, verified, merged to `main`, and the owner conferred `GO` against exact-SHA
evidence. The immutable annotated tag
`aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go` (object
`8b37230ed8df8da343a1546fd949d8a41329fbdf`) peels to the runtime merge SHA
`0e2554338812b05eba8411afeb099212b05f9761` — **not** to the later post-tag
evidence commit `ad31473da8376e91b67449bf7820ab9877ea8a4a`. The full closure
evidence is [`evidence/step-03/STEP-03-GO-CLOSURE.md`](../evidence/step-03/STEP-03-GO-CLOSURE.md).

`GO WITH ACCEPTED DEVIATION` is not an unqualified `GO`. The deviations accepted
at Step 3 closure are **DEC-0017** (single-maintainer governance; no independent
human review — compensating controls are not equivalent to peer review),
**DEC-0026** (the scaffold-authorization suite runs 38/38 only on a Step 3
feature branch; on `main` and in a fresh clone it is a visible exit-78 SKIP by
owner-approved branch/path pin, never represented as PASS), and the runtime
limitations recorded in §2 — Android artefacts are debug-only, and there is no
signing, no device execution, no performance or accessibility audit, no UAT, and
no deployment. Runtime existing and passing its gates is not runtime deployed:
see §2 for exactly what is verified and what is not.

**Step 3 GO did not start Step 4 and does not authorise deployment.** Step 4
began only through the separately authorised canonical process Step 3 required,
recorded as
[DEC-0028](decisions/DEC-0028-step-04-scope-resolution-and-canonical-authorization.md)
on 21 July 2026. Step 4 has since reached `GO` (PR #18 merged, tag on the merge
commit — see the Step 4 closure section below); deployment remains `ABSENT`, and
the Step 3 tag never moves. Step 4 `GO` did not start Step 5.

This paragraph previously read "Step 4 remains `PLANNED / NOT STARTED`". That
was true when written and stopped being true at DEC-0028. It contradicted the
roadmap table in §1 of this same file, and a status file that contradicts itself
is a false claim rather than a conservative one (Rule 01, DEC-0029).

---

## 2. System status

| Item | Status |
| --- | --- |
| All product business features | NOT IMPLEMENTED |
| Backend runtime | PRESENT — STEP 3 FOUNDATION ONLY |
| PostgreSQL runtime foundation | PRESENT |
| Redis runtime foundation | PRESENT |
| Flutter workspace | PRESENT |
| Customer Android | FOUNDATION IMPLEMENTED AND DEBUG BUILD VERIFIED |
| Ops Android | FOUNDATION IMPLEMENTED AND DEBUG BUILD VERIFIED |
| Admin Web | FOUNDATION IMPLEMENTED AND BUILD VERIFIED |
| Deployment | ABSENT |
| Application CI | ACTIVE — THREE STEP 3 RUNTIME CONTEXTS VERIFIED |
| UAT | NOT STARTED |
| Client↔API end-to-end session | **PRESENT — VERIFIED ON A REAL DEVICE AND IN A REAL BROWSER** |

### Client↔API authentication: the defect, and its correction

**Current state:** all three canonical Flutter surfaces resolve a concrete
`BackendAuthService` in their production composition and have authenticated
against a running backend. The paragraphs below record the defect that made this
section necessary, because deleting that history would leave the correction
looking like something that was always true.

#### What was wrong (historical, corrected)

Recorded 21 July 2026. `AuthService` was an interface in `packages/auth` whose
only implementation in the workspace was `FakeAuthService` in
`packages/testing`. No `main.dart` overrode `authServiceProvider` — each entry
point overrode `environmentProvider` and nothing else — so every authenticated
screen read a provider that threw `UnimplementedError` on the startup frame. No
build of any surface could sign in. Widget suites stayed green throughout,
because each test supplied the missing dependency through the same provider the
production code read.

The same defect then recurred one layer up in Step 4:
`masterDataRepositoryProvider` threw identically in Ops Android and Console Web,
so every master-data screen died the moment it was opened.

#### What is true now

Both are fixed and the fixes are verified, not asserted:

- `BackendAuthService` is a concrete production implementation in
  `packages/auth`. `authServiceProvider` resolves to it in all three
  applications; no `main.dart` override is needed because the production default
  IS the real implementation and a test overrides it.
- `masterDataRepositoryProvider` is built from the surface's authenticated
  `ApiClient` in Ops Android and Console Web, so master-data requests carry the
  same credential and `X-Tenant-Id`. Customer Android deliberately has none.
- A real device run against a running backend has executed sign-in, session
  restoration from the Android Keystore, tenant and outlet selection, customer
  search, a customer write with a server-confirmed reload, catalogue, outlet
  detail and staff roster.
- Console Web boots and authenticates in real Chrome with `localStorage` and
  `sessionStorage` empty after authentication.
- `scripts/validate-production-composition.py` and its adversarial harness make
  a recurrence fail at validation time rather than at first user navigation.

The correction was made on a separate branch after Step 3 `GO`, merged through
PR #19 (merge `0f065a330e085228aaeed086f620d8752291e0af`), and recorded as
**DEC-0032**. Threat **T-51** records the failure mode.

#### What the correction does NOT change

**The Step 3 `GO` tag was not moved and does not cover this fix.**
`aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go` still resolves to
tag object `8b37230ed8df8da343a1546fd949d8a41329fbdf`, peeling to
`0e2554338812b05eba8411afeb099212b05f9761`. Step 3 remains
`GO WITH ACCEPTED DEVIATION`. The tag records what was true at closure —
including that this defect was present and undetected — and rewriting it would
erase the evidence that the gate missed something.

**Runtime existing is still not runtime working.** Every claim above is bound to
captured output at an exact SHA in
[`../evidence/step-03-corrective-auth-runtime/`](../evidence/step-03-corrective-auth-runtime/)
and [`../evidence/step-04/`](../evidence/step-04/). Step 4 is **`GO`**; PR #18 is
merged and the GO tag is on the verified merge commit.

### Step 4 closure

**Classification: `GO`** — owner-authorized, conferred 22 July 2026 against
exact-SHA evidence after merge.

- PR #18 **MERGED** as merge commit
  `af31ea3b0945b274b249ff21cf30918cb2d17a5f` (first parent `0f065a33` = prior
  main; second parent `1a9e2d3b` = the tested candidate; merge tree
  byte-identical to the tested candidate).
- GO tag **`aish-laundry-step-04-laundry-master-data-v1.0.0-go`** (object
  `55ed19761714aea945ecfcc919a78bae769339ac`) peels to the merge commit
  `af31ea3` — **never** to this later evidence commit. The tag is immutable and
  is never moved.
- **Post-merge CI:** 11 required workflows, 11 successful, at the exact merge SHA
  on `main` (`push`, not inherited from the PR-head). Recorded in
  [`../evidence/step-04/authoritative-ci.txt`](../evidence/step-04/authoritative-ci.txt).
- **Fresh clean-checkout verification at the merge SHA:** backend 466/466,
  lifecycle clean, five hardened triggers `tgenabled='A'`, the destructive-
  boundary protocol refusing every prohibited mutation with asserted fixtures
  and unchanged row counts, 8 validators, harnesses 11/11 · 9/9 · 10/10,
  verify-step-00/01/02 PASS, verify-step-03 **52/0/1**, verify-step-04
  **27/0/1**. The one skip is the named `DEC-0026` exit-78 scaffold suite and is
  not counted as a pass.
- The earlier interrupted clean-checkout run reported two build-gate failures;
  those were **disk exhaustion**, and the re-run with adequate disk resolved
  them to a clean pass. Recorded rather than hidden — a failure interrupted by a
  resource limit is neither a pass nor a confirmed regression until re-run.

This `GO` is not an unqualified endorsement of everything downstream. It carries
the accepted boundaries below, and Step 4 `GO` does not start Step 5 and does not
authorise deployment.

The Step 3 GO tag is unchanged and immutable
(`8b37230…` peeling to `0e25543…`).

Three independent review rounds produced twenty-three findings. **Three first
remediations were refuted by a later round**, each failing the same way: a
control documented as absolute, an unenumerated bypass, and a green test proving
only the narrower case. The chronology is preserved in
[`../evidence/step-04/INDEPENDENT-REVIEW-CLOSURE.md`](../evidence/step-04/INDEPENDENT-REVIEW-CLOSURE.md).

All findings are `FIXED_AND_VERIFIED` except **NEW-04**, an
`ACCEPTED_OPERATIONAL_RESIDUAL` covering local developer and reviewer
reliability, accepted only after CI database isolation was proven across six
conditions.

**FR-024 and FR-025 are `COMPLETE_AND_VERIFIED`.** Seven requirements remain
`PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING`, each with a handoff
entry naming the proof Step 5 must produce — FR-036 among them, and it is a
mandatory financial-integrity obligation.

**A deployment prerequisite is recorded and is not a current control.** The
consent and price-list guarantees hold at the application database connection
boundary and do not bind a principal that may rewrite the schema; in development
the application role *is* the superuser and table owner. A non-owner,
non-superuser role is
[`REQUIRED_FOR_FUTURE_DEPLOYMENT` / `NOT_YET_PROVISIONED` / `NOT_CLAIMED_AS_CURRENT_CONTROL`](../docs/deployment/DATABASE_ROLE_PREREQUISITE.md).

<!-- CANONICAL_STEP_STATE_BEGIN -->
<!--
Machine-readable canonical step state. This block is the DETERMINISTIC source for
tooling; the tables above are the human-readable form. scripts/validate-status.py
fails if the two disagree, so neither can drift silently.

Exactly one block. Duplicate blocks, duplicate keys, missing markers, or an
unknown status value are all failures, and a parse error fails CLOSED.
Transitions remain governed by the ordinary canonical process — editing this
block does not by itself advance a step.
-->
STEP_00_STATUS=GO
STEP_01_STATUS=GO
STEP_02_STATUS=GO
STEP_03_STATUS=GO
STEP_04_STATUS=GO
STEP_05_STATUS=IN_PROGRESS
STEP_06_STATUS=PLANNED
STEP_07_STATUS=PLANNED
STEP_08_STATUS=PLANNED
STEP_09_STATUS=PLANNED
STEP_10_STATUS=PLANNED
STEP_11_STATUS=PLANNED
STEP_12_STATUS=PLANNED
STEP_13_STATUS=PLANNED
STEP_14_STATUS=PLANNED
<!-- CANONICAL_STEP_STATE_END -->

<!--
Step 3 closure facts, machine-readable. `scripts/validate-status.py` checks these
against its own committed constants and for internal consistency: the GO tag must
peel to the RUNTIME merge SHA, never to the post-tag EVIDENCE SHA, and the two
must differ. When a `.git` tag is present locally the validator additionally
verifies the real tag object and peeled commit match these values. FAILS CLOSED.
STEP_03_STATUS=GO above without this block, or this block disagreeing with it, is
a failure. Editing these lines never moves the real immutable tag.
-->
<!-- STEP_03_CLOSURE_BEGIN -->
STEP_03_CLOSURE_CLASSIFICATION=GO_WITH_ACCEPTED_DEVIATION
STEP_03_RUNTIME_MERGE_SHA=0e2554338812b05eba8411afeb099212b05f9761
STEP_03_EVIDENCE_MERGE_SHA=ad31473da8376e91b67449bf7820ab9877ea8a4a
STEP_03_GO_TAG=aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go
STEP_03_GO_TAG_OBJECT=8b37230ed8df8da343a1546fd949d8a41329fbdf
STEP_03_GO_TAG_PEELED=0e2554338812b05eba8411afeb099212b05f9761
STEP_04_STATUS_NOTE=GO_MERGE_af31ea3_TAG_step-04-v1.0.0-go
DEPLOYMENT=ABSENT
<!-- STEP_03_CLOSURE_END -->

---

## 3. Feature status

Every product feature is **NOT IMPLEMENTED**.

| Feature | Status |
| --- | --- |
| Authentication and phone + OTP login | NOT IMPLEMENTED |
| Tenancy, brands, outlets, memberships, tenant switcher | NOT IMPLEMENTED |
| RBAC and server-side authorisation | NOT IMPLEMENTED |
| Customer management | NOT IMPLEMENTED |
| Service and price list master data | NOT IMPLEMENTED |
| POS and order intake | NOT IMPLEMENTED |
| Payment, refund, and void | NOT IMPLEMENTED |
| Production operations and quality control | NOT IMPLEMENTED |
| Public tracking portal | NOT IMPLEMENTED |
| WhatsApp and notifications | NOT IMPLEMENTED |
| Pickup and delivery | NOT IMPLEMENTED |
| Unclaimed laundry H+1/H+3/H+7/H+14 | NOT IMPLEMENTED |
| Finance, reporting, and owner portfolio | NOT IMPLEMENTED |
| Customer Android experience | NOT IMPLEMENTED |
| Subscription and platform administration | NOT IMPLEMENTED |
| Offline-first synchronisation | NOT IMPLEMENTED |
| Observability | NOT IMPLEMENTED |

---

## 4. Runtime status by path

Step 3 introduced runtime into the approved roots (DEC-0024). These paths are no
longer placeholders. **Runtime present is not runtime working** — the third column
records what has actually been executed, not what exists.

| Path | Runtime | Verified |
| --- | --- | --- |
| `backend` | PRESENT | Laravel 13.20.0 boots; migrate fresh/rollback/re-apply on PostgreSQL 18.4; 385 tests, 3562 assertions |
| `apps/customer_android` | PRESENT | analyse clean, 20 widget tests; **debug APK built, exit 0** |
| `apps/ops_android` | PRESENT | analyse clean, 78 widget tests; **debug APK built, exit 0** (Step 3 build; the Step 4 master-data surface is test-verified, not re-built) |
| `apps/admin_web` | PRESENT | analyse clean, 34 widget tests; **release web build, exit 0** |
| `packages/design_system` | PRESENT | deterministic token generation, drift test |
| `packages/core` | PRESENT | pure Dart, unit tests |
| `packages/domain` | PRESENT | pure Dart, unit tests |
| `packages/auth` | PRESENT | auth-state tests |
| `packages/networking` | PRESENT | error-mapping tests |
| `packages/local_storage` | PRESENT | secure-storage abstraction tests |
| `packages/offline_sync` | PRESENT — interfaces only | no business queue exists |
| `packages/observability` | PRESENT | redaction tests |
| `packages/testing` | PRESENT | fakes and helpers |
| `infrastructure` | PRESENT — development only | PostgreSQL 18.4 and Redis 8.2.7 connectivity proven; **no deployment artefact** |

All three artefacts have been compiled and their evidence recorded in
`evidence/step-03/`. **No deployment exists**, and no artefact is committed.

---

## 5. Testing and quality status

| Item | Status |
| --- | --- |
| Unit tests | PRESENT — STEP 3 SCOPE ONLY |
| Integration tests | PRESENT — STEP 3 SCOPE ONLY |
| Tenant isolation test suite | PRESENT — STEP 3 SCOPE ONLY |
| Financial integrity test suite | NOT APPLICABLE |
| End-to-end tests | NOT APPLICABLE |
| Application CI | ACTIVE — THREE STEP 3 RUNTIME CONTEXTS VERIFIED |
| UAT | NOT STARTED |

**Step 3 application foundations and runtime CI are present and tested. Step 4+ laundry business
functionality remains `NOT IMPLEMENTED`.**

This paragraph previously read "There is no application code, therefore there is nothing to build or
test. Application CI becomes applicable at Step 3." That was stale Step 1 prose left standing beside a
table declaring Application CI `ACTIVE` — a document contradicting itself on the same screen. It is
corrected under [DEC-0027](decisions/DEC-0027-local-development-environment-bootstrap-and-template-contract.md).

**Three rows in this table were also stale and are corrected.** Unit, integration, and tenant-isolation
suites were each declared `NOT APPLICABLE` while `backend/tests/` already held executable suites and
the `tenant-isolation` and `authentication-rbac` CI contexts were running against them. `PRESENT —
STEP 3 SCOPE ONLY` is the accurate status: the suites exist and cover authentication, tenancy, RBAC,
session management, audit redaction, and tenant cache-key scoping — and nothing beyond that.

**`PRESENT` is not `TESTED`.** A suite existing is not a suite passing. Every execution result is bound
to the exact 40-character commit SHA it ran against, in CI and in the Step 3 evidence pack; a result
quoted without its SHA is not evidence (Rule 01, DEC-0013).

The two remaining `NOT APPLICABLE` rows are genuinely inapplicable: there is no financial-integrity
suite because there is no money path, and no end-to-end suite because there is no end-to-end product.
Both arrive with the Steps that build what they would test.

Governance validation is performed by `scripts/verify-step-03.sh`, which runs the Step 0, Step 1, and
Step 2 gates still in force plus the Step 3 gates.

**A written acceptance criterion is not a passed test.** Step 1 defined acceptance criteria; it executed
none of them, because there is nothing to execute them against.

---

## 6. Environment status

Every row names the environment it describes. A service verified locally is never reported as
deployed infrastructure, and a bare status word is never used where two environments would read the
same (DEC-0029).

| Environment | Status |
| --- | --- |
| Local development runtime | PRESENT — VERIFIED LOCALLY ONLY |
| Local development PostgreSQL | PRESENT — VERIFIED LOCALLY ONLY |
| Local development Redis | PRESENT — VERIFIED LOCALLY ONLY |
| CI runtime | PRESENT — EPHEMERAL, PER-RUN |
| Staging | ABSENT |
| Production | ABSENT |
| Staging or production database | ABSENT |
| Staging or production Redis | ABSENT |
| Object storage | NOT CONFIGURED |
| Deployment pipeline | ABSENT |

**Four rows in this table were stale and self-contradictory, and are corrected under
[DEC-0029](decisions/DEC-0029-canonical-status-drift-remediation-and-cross-document-validation.md).**
This table previously declared `Local development runtime | ABSENT`, `Database | ABSENT`, and
`Redis | ABSENT` while §2 of this same document declared PostgreSQL and Redis runtime foundations
`PRESENT`, `infrastructure/docker-compose.dev.yml` defined both services, and
`scripts/verify-step-03.sh` reported them reachable with `migrate:fresh --seed`, `migrate:rollback`,
and `migrate` re-apply all passing. Two tables four sections apart contradicted each other on the same
screen, and a reader could not tell which was true.

The rows were not simply wrong: they were **unqualified**. §2 describes runtime foundations and §6
describes environments, but neither said so, so `PRESENT` and `ABSENT` collided on the same subject.
Naming the environment in the row is what makes both statements true at once.

`scripts/validate-status.py` now cross-checks these declarations for contradiction and against
`infrastructure/docker-compose.dev.yml` in both directions, so the same drift cannot recur silently.

**Local and CI verification is never production evidence.** `PRESENT — VERIFIED LOCALLY ONLY` means a
loopback-bound development service with fictional seed data was reached from a developer machine. It
is not a staging service, not a production service, and not a claim that anything is deployed.
**Deployment remains `ABSENT`**, and no Step 3 or Step 4 result upgrades that.

---

## 7. Rules for updating this file

1. Only the canonical vocabulary in [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md) may be used.
2. A status may only be advanced with evidence bound to an exact commit SHA
   ([`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md)).
3. **`GO` is conferred by the repository owner and is never self-declared by an agent.** While a Step's
   pull request is open, its status is IN PROGRESS, and after validation it may be TESTED or WATCH.
   `GO` is written here only after the owner confers it against exact-SHA evidence and the Step has
   merged — as happened for Step 0 on 19 July 2026. An agent that writes `GO` for a Step whose pull
   request is still open has committed a status-inflation violation and the wording is reverted.
4. A status is never advanced to make a report look better. An honest NO-GO outranks a convenient claim.
5. Any change to this file is reflected in [`CHANGELOG.md`](CHANGELOG.md).
