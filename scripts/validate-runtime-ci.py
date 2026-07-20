#!/usr/bin/env python3
"""Assert the three Step 3 runtime CI workflows are real gates, not decoration.

A workflow file existing proves nothing. A job that echoes "tests passed" is
worse than no job, because a required check that cannot fail manufactures
confidence. This validator asserts that each context actually invokes the
commands it claims to, that its services are the authoritative ones, and that
the supply-chain and permission hygiene holds.

Checks, per DEC-0024 and the Step 3 rules:
  - exactly one workflow publishes each of the three contexts;
  - no context name collides with an existing required check;
  - each runs real commands, not a static echo;
  - runtime-foundation builds both Android apps and Admin Web;
  - tenant-isolation uses PostgreSQL AND Redis, and never SQLite;
  - authentication-rbac runs authentication, RBAC, audit and redaction tests;
  - every `uses:` is pinned to a full 40-character commit SHA;
  - no pull_request_target;
  - least-privilege permissions;
  - explicit timeout and concurrency cancellation;
  - no production secret reference;
  - no deployment command.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402

WORKFLOW_DIR = ".github/workflows"

# context -> (workflow file, required command signatures)
REQUIRED_CONTEXTS: dict[str, tuple[str, list[tuple[str, str]]]] = {
    "runtime-foundation": (
        "runtime-foundation.yml",
        [
            ("Customer Android build", r"cd apps/customer_android[\s\S]{0,120}?flutter build apk --debug"),
            ("Ops Android build", r"cd apps/ops_android[\s\S]{0,120}?flutter build apk --debug"),
            ("Admin Web build", r"cd apps/admin_web[\s\S]{0,120}?flutter build web --release"),
            ("flutter analyze", r"flutter analyze"),
            ("dart format gate", r"dart format --output=none --set-exit-if-changed"),
            ("flutter test", r"flutter test"),
            ("design token drift", r"generate-design-tokens\.py --check"),
            ("runtime scope", r"validate-runtime-scope\.py"),
            ("master source checksum", r"sha256sum -c MASTER_SOURCE\.sha256"),
            ("secret scan", r"validate-secrets\.sh"),
            ("Flutter checksum verification", r"FLUTTER_SHA256"),
        ],
    ),
    "tenant-isolation": (
        "tenant-isolation.yml",
        [
            ("PostgreSQL service", r"image:\s*\"?postgres:"),
            ("Redis service", r"image:\s*\"?redis:"),
            ("migrate fresh", r"migrate:fresh"),
            ("migrate rollback", r"migrate:rollback"),
            ("isolation matrix", r"StructuralIsolation\|TenantIsolation"),
            ("redis partitioning", r"RedisTenantPartitioning"),
            ("forbidden table check", r"forbidden Step 4\+ tables|\$forbidden"),
            ("sqlite refusal", r"SQLite substitution"),
        ],
    ),
    "authentication-rbac": (
        "authentication-rbac.yml",
        [
            ("PostgreSQL service", r"image:\s*\"?postgres:"),
            ("Redis service", r"image:\s*\"?redis:"),
            ("authentication suite", r"AuthenticationTest\|PasswordResetTest\|SessionManagementTest"),
            ("adversarial matrix", r"AuthenticationAdversarialMatrix"),
            ("RBAC matrix", r"Rbac\|AuthorizationRegistry"),
            ("log redaction", r"LogRedaction"),
            ("redaction tap attached", r"ConfigureLogRedaction"),
            ("CORS wildcard check", r"allowed_origins"),
        ],
    ),
}

# Contexts that already exist and must not be duplicated by the new workflows.
EXISTING_CONTEXTS = {
    "validate", "Documentation / links", "Required Gate", "secret-scan",
    "Workflow / actionlint", "classify", "product-requirements", "domain-model",
    "threat-model", "design-system", "ux-foundation", "accessibility-privacy",
}

FULL_SHA = re.compile(r"^[0-9a-f]{40}$")
USES = re.compile(r"^\s*(?:-\s*)?uses:\s*([^\s#]+)", re.MULTILINE)
JOB_NAME = re.compile(r"^\s{4}name:\s*(\S.*?)\s*$", re.MULTILINE)

DEPLOY_TOKENS = re.compile(
    r"\b(kubectl\s+apply|helm\s+upgrade|terraform\s+apply|flyctl\s+deploy"
    r"|aws\s+s3\s+sync|scp\s+-r|rsync\s+.*@|ssh\s+\w+@)",
    re.IGNORECASE,
)
# A secret reference that is NOT the automatically-provided GITHUB_TOKEN.
SECRET_REF = re.compile(r"secrets\.(?!GITHUB_TOKEN\b)([A-Z0-9_]+)")


def main() -> int:
    root = repo_root()
    rep = Reporter("runtime-ci")

    wf_dir = root / WORKFLOW_DIR
    if not rep.check(wf_dir.is_dir(), f"{WORKFLOW_DIR} exists"):
        return rep.finish()

    all_files = sorted(p for p in wf_dir.glob("*.yml"))
    texts = {p.name: p.read_text(encoding="utf-8") for p in all_files}

    # --- each context is published exactly once ---------------------------
    for context, (fname, _) in REQUIRED_CONTEXTS.items():
        publishers = [n for n, t in texts.items()
                      if re.search(rf"^\s{{4}}name:\s*{re.escape(context)}\s*$", t, re.MULTILINE)]
        rep.check(
            publishers == [fname],
            f"exactly one workflow publishes '{context}' (found: {publishers or 'none'})",
        )

    # --- no collision with an existing required check ---------------------
    collisions = []
    for context, (fname, _) in REQUIRED_CONTEXTS.items():
        if context in EXISTING_CONTEXTS:
            collisions.append(context)
    rep.check(not collisions, f"new contexts do not collide with existing ones ({collisions})")

    # --- each context runs the commands it claims -------------------------
    for context, (fname, signatures) in REQUIRED_CONTEXTS.items():
        text = texts.get(fname)
        if text is None:
            rep.fail(f"{fname} is missing")
            continue

        missing = [label for label, pat in signatures
                   if re.search(pat, text) is None]
        rep.check(
            not missing,
            f"{context} runs its declared commands (missing: {missing})",
        )

        # A gate that cannot fail is not a gate.
        rep.check(
            "continue-on-error: true" not in text,
            f"{context} has no continue-on-error escape hatch",
        )
        rep.check(
            "pull_request_target" not in text,
            f"{context} does not use pull_request_target",
        )
        rep.check(
            re.search(r"timeout-minutes:\s*\d+", text) is not None,
            f"{context} declares an explicit timeout",
        )
        rep.check(
            "cancel-in-progress: true" in text,
            f"{context} cancels superseded runs",
        )
        # Least privilege is the ABSENCE of a write scope, not the presence of a
        # read one. These workflows declare permissions twice (workflow level and
        # job level); an earlier revision only required that some `contents: read`
        # existed, so escalating the JOB-level block to `write` left the top-level
        # match intact and the check stayed green. Assert no write scope anywhere.
        write_scopes = re.findall(
            r"^\s+(contents|packages|actions|deployments|id-token|issues"
            r"|pull-requests|security-events|statuses|checks|discussions"
            r"|pages|repository-projects):\s*(write|admin)\s*$",
            text, re.MULTILINE,
        )
        rep.check(
            not write_scopes,
            f"{context} grants no write permission (found: {write_scopes})",
        )
        rep.check(
            re.search(r"permissions:\s*\n\s*contents:\s*read", text) is not None,
            f"{context} declares an explicit read-only permission block",
        )

        deploy = DEPLOY_TOKENS.search(text)
        rep.check(deploy is None,
                  f"{context} contains no deployment command"
                  + (f" (found {deploy.group(0)!r})" if deploy else ""))

        secrets = sorted(set(SECRET_REF.findall(text)))
        rep.check(not secrets,
                  f"{context} requires no production secret (found: {secrets})")

        # Static-echo detection: a job whose only run steps are echoes.
        run_bodies = re.findall(r"run:\s*\|?\s*\n((?:\s{8,}.*\n)+)", text)
        real = [b for b in run_bodies
                if any(re.search(r"^\s*(?!echo|printf|#)\S", line)
                       for line in b.splitlines())]
        rep.check(len(real) >= 3,
                  f"{context} has {len(real)} non-echo run step(s), not a static success")

    # --- every action pinned to a full commit SHA -------------------------
    unpinned = []
    for name, text in texts.items():
        for ref in USES.findall(text):
            if "@" not in ref:
                unpinned.append(f"{name}: {ref}")
                continue
            pin = ref.rsplit("@", 1)[1]
            if not FULL_SHA.match(pin):
                unpinned.append(f"{name}: {ref}")
    rep.check(not unpinned,
              f"every action is pinned to a full commit SHA ({len(unpinned)} unpinned)")
    for u in unpinned[:8]:
        rep.info(u)

    # --- tenant-isolation must never substitute SQLite --------------------
    ti = texts.get("tenant-isolation.yml", "")
    rep.check(
        re.search(r"DB_CONNECTION:\s*sqlite", ti) is None
        and re.search(r"--database[= ]sqlite", ti) is None,
        "tenant-isolation does not substitute SQLite for the authoritative engine",
    )
    rep.check(
        re.search(r"DB_CONNECTION:\s*pgsql", ti) is not None,
        "tenant-isolation configures pgsql explicitly",
    )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
