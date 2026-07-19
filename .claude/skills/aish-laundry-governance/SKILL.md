---
name: aish-laundry-governance
description: Enforces Aish Laundry App governance. Use before any change to this repository — reading docs/MASTER_SOURCE.md as the canonical source, applying the approved status vocabulary and exact-SHA evidence rules, honouring the tenant-isolation and financial-integrity hard gates, respecting the Step 0 no-runtime boundary, and running `bash scripts/verify-step-00.sh` to validate governance before opening or updating a pull request.
---

# Aish Laundry App — Governance Skill

Use this skill whenever you touch this repository: documentation, governance rules, scripts,
workflows, structure, or any future application code.

Product: **Aish Laundry App** (Aish Tech Solution). Bahasa Indonesia, Rupiah, Asia/Jakarta.

## 1. Read the canonical source first

`docs/MASTER_SOURCE.md` is the single source of truth (version 1.0.0, baseline 19 July 2026).
**Read it before making any change.** Recollection is not a substitute.

Conflict order: Master Source → decision records in `docs/decisions/` → `CLAUDE.md` →
`.claude/rules/**` → everything else. A lower layer contradicting a higher one is a defect in the
lower layer.

Never invent a product decision. Unanswered questions go to the repository owner.

## 2. Know the current status before you write a sentence about it

| Item | Status |
|---|---|
| Step 0 — Master Source and Governance | IN PROGRESS |
| Steps 1–14 | PLANNED |
| All product features | NOT IMPLEMENTED |
| Backend runtime | ABSENT |
| Flutter workspace | ABSENT |
| Deployment | ABSENT |
| Application CI | NOT APPLICABLE |
| UAT | NOT STARTED |

Approved vocabulary only: `PLANNED`, `IN PROGRESS`, `TESTED`, `WATCH`, `NOT IMPLEMENTED`, `ABSENT`,
`NOT APPLICABLE`, `NOT STARTED`, `NO-GO`. **Never write `GO` as the Step 0 status** — the maximum is
`IN PROGRESS`, `TESTED`, or `WATCH`. `GO` is conferred by the owner.

The repository is **PUBLIC** by deliberate owner decision (AMENDMENT-0001). Never call it private.

## 3. Evidence discipline

Every verification claim binds to the **full 40-character commit SHA**, with the exact command,
captured output, timestamp, and environment. Evidence at one SHA does not transfer to another.
Evidence lives under `evidence/` and never contains secrets or personal data.

Never claim an implementation, test, deployment, CI run, or UAT result that does not exist. An empty
folder, README, or `.gitkeep` is never a feature.

## 4. Hard gates

- **Tenant isolation** — cross-tenant data exposure is an automatic **NO-GO**. See
  `.claude/rules/02-multi-tenancy.md`.
- **Financial integrity** — integer Rupiah, idempotent payments, no hard deletes, corrections by
  reversal. Any failure is an automatic **NO-GO**. See `.claude/rules/04-financial-integrity.md`.

On a NO-GO: stop, preserve evidence at the exact SHA, disclose plainly, propose the minimal
correction, wait for the owner.

## 5. Step 0 boundary

Step 0 creates **no runtime**. Do not run `flutter create`, `dart create`, `laravel new`,
`composer create-project`, or `npm create`. Do not create `pubspec.yaml`, `composer.json`,
`package.json`, migrations, schema, API runtime, UI, Docker runtime, or any deployment. Runtime
folders hold only `README` or `.gitkeep`.

## 6. Verify before you open or update a pull request

```bash
bash scripts/verify-step-00.sh
bash .claude/hooks/guard-destructive-operations.sh --self-test
```

Capture the output alongside the exact SHA it ran against. Step 0 has **governance validators only**
— there are no application tests, and none may be claimed.

## 7. Workflow

Feature branch → Conventional Commits (`docs:`, `feat:`, `fix:`, `chore:`, `ci:`) → push the feature
branch → open a pull request. **All changes reach `main` through a pull request**; direct pushes are
forbidden and merging is the owner's decision. No force push, no history rewriting, no moved tags.

Destructive commands are blocked by `.claude/hooks/guard-destructive-operations.sh` (exit 2 =
block). Never disable, weaken, or route around it — escalate instead.

## 8. Changing the Master Source

Requires all three, in the same pull request: a **version bump**, a **tool-regenerated checksum**
(never hand-edited), and a **decision record** with every mandated heading when a product decision
changes.

## 9. Rule index

Read the relevant rule file before working in its area: `.claude/rules/00-canonical-source.md`
through `.claude/rules/15-current-product-status.md`, indexed in `CLAUDE.md` section 15.

## 10. Always

No secrets anywhere. No `.claude/settings.local.json` or user-level config changes. Report what was
done, what was verified and at which SHA, and what remains unverified — without rounding an
unverified result up to a verified one.
