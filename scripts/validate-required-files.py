#!/usr/bin/env python3
"""Assert every required Step 0 file exists.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402

ROOT_FILES = [
    "README.md",
    "CLAUDE.md",
    "CONTRIBUTING.md",
    "SECURITY.md",
    ".editorconfig",
    ".gitignore",
]

DOC_FILES = [
    "docs/MASTER_SOURCE.md",
    "docs/MASTER_SOURCE.sha256",
    "docs/CHANGELOG.md",
    "docs/DEFINITION_OF_DONE.md",
    "docs/ROADMAP.md",
    "docs/STATUS.md",
    "docs/ASSUMPTIONS.md",
    "docs/GOVERNANCE_TRACEABILITY.md",
    "docs/GIT_AND_RELEASE_POLICY.md",
    "docs/AI_EXECUTION_POLICY.md",
    "docs/TOOLING_POLICY.md",
]

GOVERNANCE_FILES = [
    "docs/governance/REQUIRED_FILES.md",
    "docs/governance/STATUS_MODEL.md",
    "docs/governance/EVIDENCE_POLICY.md",
    "docs/governance/TENANT_ISOLATION_POLICY.md",
    "docs/governance/FINANCIAL_INTEGRITY_POLICY.md",
]

CLAUDE_FILES = [
    ".claude/hooks/guard-destructive-operations.sh",
    ".claude/skills/aish-laundry-governance/SKILL.md",
]

GITHUB_FILES = [
    ".github/pull_request_template.md",
    ".github/CODEOWNERS",
    ".github/dependabot.yml",
    ".github/ISSUE_TEMPLATE/bug.yml",
    ".github/ISSUE_TEMPLATE/feature.yml",
    ".github/ISSUE_TEMPLATE/governance.yml",
    ".github/ISSUE_TEMPLATE/config.yml",
]

# rules 00-50; 36-49 added at Master Source 1.4.0 (DEC-0024, Step 3 runtime);
# 50 added at 1.4.1 (DEC-0028, Step 4 status snapshot).
EXPECTED_RULE_COUNT = 51
# DEC-0024 (Step 3 runtime introduction and runtime scope guard transition) added at
# Master Source 1.4.0. Raising this count WIDENS coverage — every record up to the
# count must exist — so it can never be used to skip a record.
#
# Raised 27 -> 30 at Master Source 1.4.1 for DEC-0028, DEC-0029, and DEC-0030.
# Raised 31 -> 32 at Master Source 1.4.2 for DEC-0032 (Step 3 post-GO corrective
# remediation: runtime authentication wiring).
# Raised 32 -> 33 at Master Source 1.4.3 for DEC-0033 (Step 4 independent review
# findings and closure conditions).
#
# THIS CONSTANT AND `validate-master-source.py`'s VERSION ARE DERIVED FROM THE
# MASTER SOURCE and must move with it. They did not when 1.4.3 landed, and the
# result was `verify-step-04.sh` failing at HEAD while a status report claimed it
# passed — the report was accurate at the SHA it ran against and was never
# re-run after the bump. A Master Source version change is not complete until
# every derived validator has been re-run, not merely edited.
# Raised 33 -> 34 at Master Source 1.4.4 for DEC-0034 (Step 3 post-GO
# token-logging correction co-delivered in PR #18).
# Raised 34 -> 35 at Master Source 1.4.6 for DEC-0035 (Step 5 runtime scope
# transition — the guard transition that starts Step 5 POS/order/payment runtime).
EXPECTED_DECISION_COUNT = 35


def main() -> int:
    root = repo_root()
    rep = Reporter("required-files")

    for rel in ROOT_FILES + DOC_FILES + GOVERNANCE_FILES + CLAUDE_FILES + GITHUB_FILES:
        rep.check((root / rel).is_file(), f"required file exists: {rel}")

    # --- .claude/rules/NN-*.md : exactly 16, numbered 01..16, no gaps/dupes ---
    rules_dir = root / ".claude" / "rules"
    if not rules_dir.is_dir():
        rep.fail("required directory exists: .claude/rules/")
    else:
        rep.ok("required directory exists: .claude/rules/")
        pattern = re.compile(r"^(\d{2})-.+\.md$")
        found: dict[str, list[str]] = {}
        for entry in sorted(rules_dir.iterdir()):
            if not entry.is_file():
                continue
            m = pattern.match(entry.name)
            if m:
                found.setdefault(m.group(1), []).append(entry.name)
            elif entry.name.endswith(".md"):
                rep.fail(f"unexpected rule file (not NN-*.md): .claude/rules/{entry.name}")

        rep.check(
            len(found) == EXPECTED_RULE_COUNT,
            f"rule files present: {len(found)} distinct numbers "
            f"(expected {EXPECTED_RULE_COUNT})",
        )
        # Rules are numbered 00..24 (twenty-five files).
        for n in range(0, EXPECTED_RULE_COUNT):
            key = f"{n:02d}"
            names = found.get(key, [])
            if len(names) == 1:
                rep.ok(f"rule {key} present exactly once: .claude/rules/{names[0]}")
            elif not names:
                rep.fail(f"rule {key} MISSING in .claude/rules/")
            else:
                rep.fail(f"rule {key} duplicated: {', '.join(names)}")
        for key in sorted(found):
            if not (0 <= int(key) <= EXPECTED_RULE_COUNT - 1):
                rep.fail(f"unexpected rule number {key}: {', '.join(found[key])}")

    # --- docs/decisions/DEC-0001..DEC-0015 ---
    dec_dir = root / "docs" / "decisions"
    if not dec_dir.is_dir():
        rep.fail("required directory exists: docs/decisions/")
    else:
        rep.ok("required directory exists: docs/decisions/")
        dec_pattern = re.compile(r"^DEC-(\d{4})-.+\.md$|^DEC-(\d{4})\.md$")
        found_dec: dict[str, list[str]] = {}
        for entry in sorted(dec_dir.iterdir()):
            if not entry.is_file() or not entry.name.endswith(".md"):
                continue
            m = dec_pattern.match(entry.name)
            if m:
                num = m.group(1) or m.group(2)
                found_dec.setdefault(num, []).append(entry.name)
            else:
                rep.fail(f"unexpected file in docs/decisions/: {entry.name}")
        for n in range(1, EXPECTED_DECISION_COUNT + 1):
            key = f"{n:04d}"
            names = found_dec.get(key, [])
            if len(names) == 1:
                rep.ok(f"decision DEC-{key} present exactly once: {names[0]}")
            elif not names:
                rep.fail(f"decision DEC-{key} MISSING")
            else:
                rep.fail(f"decision DEC-{key} duplicated: {', '.join(names)}")
        for key in sorted(found_dec):
            if not (1 <= int(key) <= EXPECTED_DECISION_COUNT):
                rep.fail(f"unexpected decision DEC-{key}: {', '.join(found_dec[key])}")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
