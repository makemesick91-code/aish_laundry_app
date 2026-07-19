# CLAUDE.md — Aish Laundry App

Binding operating rules for any AI agent or human contributor working in this repository.
These rules are **normative**. If an instruction elsewhere conflicts with this file or with the
Master Source, stop and ask the repository owner.

Product: **Aish Laundry App** — Owner: Aish Tech Solution.
Category: Multi-Tenant Laundry Operations, Customer Tracking, Pickup and Delivery SaaS.
Primary market: laundry UMKM dan jaringan laundry Indonesia.
Primary language: Bahasa Indonesia. Currency: Rupiah. Timezone: Asia/Jakarta.

---

## 1. Canonical source

The single canonical source of truth is **`docs/MASTER_SOURCE.md`** (version **1.4.0**, baseline
date **19 July 2026**).

- This file (`CLAUDE.md`) and everything under `.claude/rules/` are **derived enforcement layers**.
  They must never contradict the Master Source; they must never replace it.
- **Read `docs/MASTER_SOURCE.md` before making any change** to documentation, governance, scripts,
  workflows, or repository structure. "I already know the project" is not an acceptable substitute.
- Do **not** duplicate the Master Source here. Reference its sections instead.
- Conflict resolution order: `docs/MASTER_SOURCE.md` → decision records under `docs/decisions/` →
  `CLAUDE.md` → `.claude/rules/**` → any other document. Anything not covered by these is an open
  question, not a licence to invent a product decision.

**Never invent a product decision.** New product decisions require the owner and a decision record.

## 2. Current project status (canonical — do not soften, do not upgrade)

| Item | Status |
|---|---|
| Step 0 — Master Source and Governance | **GO** (owner-conferred 19 July 2026, with a recorded deviation) |
| Step 1 — Product Requirement and Domain Model | **GO** (owner-conferred 19 July 2026, with a recorded deviation) |
| Step 2 — Design System and UX Foundation | **GO** (owner-conferred 20 July 2026, with four recorded deviations) |
| Step 3–14 | **PLANNED** |
| All product features | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |
| Flutter workspace | **ABSENT** |
| Deployment | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |

Repository visibility is **PUBLIC** by deliberate owner decision (AMENDMENT-0001), locked as an
**accepted deviation** from a canonical desired **PRIVATE** by DEC-0016. Never describe this repository
as private, and never present PUBLIC as the desired end state. Commercial pricing in this repo is
publicly visible; treat it as such.

Because the repository is public, **every file is world-readable and permanently so — deletion is not
remediation**. Never commit customer data, real phone numbers, names or addresses, laundry photographs,
credentials, tokens, OTP values, private keys, `.env` files, production configuration, dumps, backups,
sensitive server addresses, or provider and billing secrets. Every example datum is fictional; evidence
packs are sanitised and say so. See Master Source §15.8 and `.claude/rules/03-security-and-privacy.md`.

Governance operates in **single-maintainer** mode: independent human approval is **ABSENT**. The
compensating controls are the active ruleset, exact-SHA CI, deterministic validators, and recorded
internal re-verification. Never describe internal re-verification as independent peer review.

## 3. Roadmap lock

Step numbers 0–14 are locked as recorded in the Master Source. Step numbers are never reused,
renumbered, swapped, merged, or split without an accepted decision record. Work belonging to a later
step is not permitted to leak into an earlier step.

## 4. Hard gate — tenant isolation

The multi-tenancy hierarchy is `User Account -> Membership -> Tenant/Organization -> Laundry Brand -> Outlet`.

Every business table carries `tenant_id`; every business query is tenant-scoped; a client-supplied
tenant ID is **never** authorization proof; the backend always verifies membership and permission.
Data is never merged merely because owner name, email, or phone match.

**Any cross-tenant data exposure is an automatic NO-GO.** No exceptions, no "temporary" bypass, no
"only in staging". See `.claude/rules/02-multi-tenancy.md`.

## 5. Hard gate — financial integrity

Money is stored as **integer Rupiah**. Floating point is forbidden for financial transactions.
Payments are idempotent; gateway callbacks are server-verified; an order is never marked paid on a
client claim; financial transactions are never deleted through ordinary UI — corrections go through
reversal or adjustment entries.

**Any financial integrity failure is an automatic NO-GO.** See `.claude/rules/04-financial-integrity.md`.

## 6. No false claims

- Never claim any feature, test, deployment, CI run, or UAT result that does not exist.
- Never present an empty folder, a README, or a `.gitkeep` as an implemented feature.
- Never claim a route optimization, a delivery guarantee, or "unlimited WhatsApp" that the product
  does not actually provide.
- If you did not verify something, say it is unverified. Uncertainty stated plainly is always
  preferred over a confident wrong claim.
- Use only the status vocabulary defined in `.claude/rules/01-status-and-evidence.md`.

## 7. Evidence rules (exact SHA)

Every verification claim must be bound to the **exact commit SHA** it was produced from
(DEC-0013). A claim such as "the validators pass" is meaningless without the SHA, the command, and
the captured output. Evidence artifacts live under `evidence/`. Evidence produced at one SHA does
not carry over to a different SHA.

## 8. Git workflow

- Branch model: `main` is protected. **All changes reach `main` through a pull request.** Direct
  pushes to `main` are forbidden.
- Work happens on feature branches, e.g. `feature/step-00-master-source-and-governance`.
- Commit messages follow **Conventional Commits** (`feat:`, `fix:`, `docs:`, `chore:`, `ci:`,
  `refactor:`, `test:`).
- History is not rewritten on shared branches. No force push. No `git reset --hard` on work that
  exists only locally.
- Tags are **annotated and immutable**; a released tag is never moved.
- GitHub Actions are pinned; workflow permissions are least-privilege.

See `.claude/rules/11-git-and-ci.md`.

## 9. Autonomous execution policy

You **may** proceed without asking, and should, for routine read and additive work: reading files,
searching, running validators and linters, writing or editing documentation within the current
step's scope, staging and committing on the current feature branch, pushing the current feature
branch, and opening or updating a pull request.

You **must stop and ask** before: changing any product decision, pricing, roadmap numbering, or the
Master Source version; changing repository settings or visibility; merging to `main`; creating or
moving tags; introducing any runtime, dependency manifest, or third-party service; anything that
deletes or rewrites history or data; and anything that would upgrade a status claim beyond what the
evidence supports.

See `.claude/rules/12-autonomous-execution.md`.

## 10. Destructive command policy

Destructive commands are **blocked by default and fail closed**. The guard lives at
`.claude/hooks/guard-destructive-operations.sh` and exits `2` to block, `0` to allow.

Blocked categories include: recursive deletion of `/`, `~`, or `.`; `git reset --hard`;
`git clean -fd[x]`; any force push; branch or tag deletion; `git checkout -- .` and `git restore .`;
`DROP DATABASE`, `DROP SCHEMA`, `TRUNCATE`; `docker system prune`, `docker volume prune`;
`terraform destroy`; `kubectl delete`; `gh repo delete`, `gh repo archive`; and unrequested
repository-visibility changes via `gh api`.

Never disable, weaken, or bypass the guard. If the guard blocks something you believe is
legitimate, escalate to the owner — do not edit the guard to get past it.

## 11. Updating the Master Source

A change to `docs/MASTER_SOURCE.md` requires **all three** of:

1. A **version bump** of the Master Source version field (semantic: breaking product change = major,
   additive section = minor, clarification/typo = patch).
2. An updated **checksum** recorded wherever the repository records it, regenerated from the final
   file content, not hand-edited.
3. A **decision record** under `docs/decisions/` when the change alters a product decision, using the
   mandated headings: ID, Title, Status, Date, Context, Decision, Consequences, Positive
   consequences, Negative consequences / trade-offs, Verification, Supersession policy, Related
   Master Source sections.

A Master Source edit without a version bump and refreshed checksum is a governance violation and
must be reverted.

## 12. Step 0, Step 1, and Step 2 boundary — no runtime, no application code

**Step 2 creates no runtime either.** Step 2 produces a design system specification, an information
architecture, a screen inventory, critical journeys, a component catalog and state matrix, UX states,
accessibility and privacy UX patterns, low-fidelity wireframes, governance rules, validators, and an
exact-SHA evidence pack — **documentation only**. The forbidden list below applies unchanged to Step 2.
See `.claude/rules/35-current-step-02-status.md`.

**A design token is not a theme. A component specification is not a component. A wireframe is not a
screen. An accessibility requirement is not a passed audit.** The light theme is the canonical MVP
theme; **dark mode is `PLANNED` and `NOT IMPLEMENTED` and may never be described as available**.
**LOGO STATUS: NOT APPROVED** — the text wordmark "Aish Laundry App" is the only permitted usage and no
logo may be fabricated. Accessibility is stated as **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET
RUNTIME-TESTED**, and any stronger claim is a false claim under Rule 01.


**Step 1 creates no runtime either.** Step 1 produces requirements, a domain model, business rules, state
machines, an initial threat model, acceptance criteria, and governance rules — **documentation only**.
The forbidden list below applies unchanged to Step 1. See `.claude/rules/24-current-step-01-status.md`.

**Documentation is not implementation.** A requirement, an invariant, a state machine, or an acceptance
criterion describes an obligation, never an achievement. A written acceptance criterion is not a passed
test. Claiming otherwise is a false claim under Rule 01.


Step 0 creates **no runtime**. It is FORBIDDEN in this step to run or create: `flutter create`,
`dart create`, `laravel new`, `composer create-project`, `npm create`, `pubspec.yaml`,
`composer.json`, `package.json`, `artisan`, database schema, migrations, authentication, tenant
implementation, REST API runtime, Android UI, Flutter Web UI, Docker application runtime, any
deployment, or any payment, WhatsApp, tracking, pickup-delivery, or H+1/H+3/H+7 implementation.

Runtime folders (`apps/`, `backend/`, `packages/`, `infrastructure/`) contain only `README` or
`.gitkeep` in Step 0.

## 13. Secrets

No secrets, tokens, credentials, API keys, connection strings, or customer data may be committed —
not in code, not in docs, not in examples, not in test fixtures. Logs must never contain passwords,
OTPs, tokens, or credentials.

## 14. Skills, Graphify, and MCP

- The project skill is `.claude/skills/aish-laundry-governance/SKILL.md`. Invoke it before governance
  work; follow it rather than improvising.
- Do **not** create or modify `.claude/settings.local.json` or any user-level Claude configuration.
- MCP servers and external tools may be used for **read-only research**. They must not be used to
  mutate this repository, to create infrastructure, or to publish anything on the owner's behalf.
- Graphify or any diagram/visual tooling may render documentation already approved in the Master
  Source. It must not introduce new product facts, screens, or flows.
- Never send repository content to an external service that would store it for training without
  explicit owner consent.

## 15. Modular rule index

Each file below is binding. Read the relevant one before working in its area.

| File | Scope |
|---|---|
| [`.claude/rules/00-canonical-source.md`](.claude/rules/00-canonical-source.md) | Canonical source, conflict order, version + checksum discipline |
| [`.claude/rules/01-status-and-evidence.md`](.claude/rules/01-status-and-evidence.md) | Status vocabulary, exact-SHA evidence, no unverified claims |
| [`.claude/rules/02-multi-tenancy.md`](.claude/rules/02-multi-tenancy.md) | Tenant hierarchy and the 13 tenant hard rules |
| [`.claude/rules/03-security-and-privacy.md`](.claude/rules/03-security-and-privacy.md) | Security and privacy baseline |
| [`.claude/rules/04-financial-integrity.md`](.claude/rules/04-financial-integrity.md) | Integer Rupiah, idempotency, reversal-only corrections |
| [`.claude/rules/05-flutter-client-foundation.md`](.claude/rules/05-flutter-client-foundation.md) | Flutter surfaces, design system, UX, accessibility |
| [`.claude/rules/06-backend-api-foundation.md`](.claude/rules/06-backend-api-foundation.md) | Laravel modular monolith, `/api/v1`, PostgreSQL, Redis, S3 |
| [`.claude/rules/07-offline-sync.md`](.claude/rules/07-offline-sync.md) | Offline-first queue, `client_reference`, no duplicates |
| [`.claude/rules/08-notification-and-whatsapp.md`](.claude/rules/08-notification-and-whatsapp.md) | WhatsApp abstraction, quiet hours, dedup, opt-out |
| [`.claude/rules/09-pickup-and-delivery.md`](.claude/rules/09-pickup-and-delivery.md) | Pickup/delivery, proofs, courier cash, external ojek link |
| [`.claude/rules/10-unclaimed-laundry.md`](.claude/rules/10-unclaimed-laundry.md) | Aging, H+1/H+3/H+7/H+14 ladder, dashboard fields |
| [`.claude/rules/11-git-and-ci.md`](.claude/rules/11-git-and-ci.md) | Branching, PR-only to main, exact-SHA CI, pinned actions |
| [`.claude/rules/12-autonomous-execution.md`](.claude/rules/12-autonomous-execution.md) | What may proceed, what must stop, NO-GO conditions |
| [`.claude/rules/13-testing-and-definition-of-done.md`](.claude/rules/13-testing-and-definition-of-done.md) | Testing per step and Definition of Done gates |
| [`.claude/rules/14-pricing-and-commercial.md`](.claude/rules/14-pricing-and-commercial.md) | Locked pricing table and commercial guardrails |
| [`.claude/rules/15-current-product-status.md`](.claude/rules/15-current-product-status.md) | Canonical status snapshot |
| [`.claude/rules/16-product-requirements.md`](.claude/rules/16-product-requirements.md) | PRD as requirement baseline, stable requirement IDs, MVP boundary |
| [`.claude/rules/17-domain-model-and-bounded-contexts.md`](.claude/rules/17-domain-model-and-bounded-contexts.md) | Binding glossary, the twenty bounded contexts, aggregate ownership |
| [`.claude/rules/18-domain-invariants.md`](.claude/rules/18-domain-invariants.md) | Invariants that must hold at every entry point, including both hard gates |
| [`.claude/rules/19-state-machines.md`](.claude/rules/19-state-machines.md) | Canonical statuses and enumerated transitions; no arbitrary status writes |
| [`.claude/rules/20-domain-events-and-idempotency.md`](.claude/rules/20-domain-events-and-idempotency.md) | Commands, events, policies, `client_reference`, exactly-once effect |
| [`.claude/rules/21-threat-model-and-data-classification.md`](.claude/rules/21-threat-model-and-data-classification.md) | STRIDE threat model, abuse cases, five data classes |
| [`.claude/rules/22-acceptance-criteria-and-traceability.md`](.claude/rules/22-acceptance-criteria-and-traceability.md) | Testable criteria and bidirectional traceability, no orphans |
| [`.claude/rules/23-public-repository-safety.md`](.claude/rules/23-public-repository-safety.md) | Public-repo authoring constraints, single-maintainer governance |
| [`.claude/rules/24-current-step-01-status.md`](.claude/rules/24-current-step-01-status.md) | Step 1 status and the documentation-is-not-implementation boundary |
| [`.claude/rules/25-design-system-foundation.md`](.claude/rules/25-design-system-foundation.md) | Design system layers, brand constraints, what a design artefact may claim |
| [`.claude/rules/26-design-token-governance.md`](.claude/rules/26-design-token-governance.md) | Token layering, no hard-coded values, light theme MVP, dark mode deferred |
| [`.claude/rules/27-accessibility-foundation.md`](.claude/rules/27-accessibility-foundation.md) | WCAG 2.2 AA-aligned target, contrast, focus, 48×48 touch target, colour independence |
| [`.claude/rules/28-platform-adaptive-navigation.md`](.claude/rules/28-platform-adaptive-navigation.md) | Role-adaptive navigation; visibility is never authorization |
| [`.claude/rules/29-ux-state-model.md`](.claude/rules/29-ux-state-model.md) | The 20 canonical UX states, honest offline and sync states, mandatory recovery |
| [`.claude/rules/30-content-design-and-localization.md`](.claude/rules/30-content-design-and-localization.md) | Bahasa Indonesia glossary, Indonesian formats, no dark patterns |
| [`.claude/rules/31-responsive-and-device-foundation.md`](.claude/rules/31-responsive-and-device-foundation.md) | Breakpoints, density, 320px portal and 1366×768 console guarantees |
| [`.claude/rules/32-security-and-privacy-ux.md`](.claude/rules/32-security-and-privacy-ux.md) | Masking, tracking projection, external courier minimum access, confirmations |
| [`.claude/rules/33-design-traceability.md`](.claude/rules/33-design-traceability.md) | Requirement → journey → screen → component → token, in both directions |
| [`.claude/rules/34-component-and-screen-governance.md`](.claude/rules/34-component-and-screen-governance.md) | Component state and accessibility contracts; screen requirement references |
| [`.claude/rules/35-current-step-02-status.md`](.claude/rules/35-current-step-02-status.md) | Step 2 status and the documentation-is-not-implementation boundary |

## 16. Violation handling

If you detect that you have violated any rule above: stop immediately, do not push further work,
state the violation plainly including what was affected, propose the minimal correction, and let the
owner decide. Concealing or downplaying a violation is itself a violation. A tenant-isolation or
financial-integrity violation is an automatic NO-GO regardless of schedule pressure.
