#!/usr/bin/env python3
"""Validate the Step 2 application rules.

Rules 25-35 must exist, follow the house structure, be referenced from
CLAUDE.md, and collectively lock all 35 mandated Step 2 constraints. A rule
that exists but locks nothing is decoration.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import read

RULES = [
    "25-design-system-foundation.md",
    "26-design-token-governance.md",
    "27-accessibility-foundation.md",
    "28-platform-adaptive-navigation.md",
    "29-ux-state-model.md",
    "30-content-design-and-localization.md",
    "31-responsive-and-device-foundation.md",
    "32-security-and-privacy-ux.md",
    "33-design-traceability.md",
    "34-component-and-screen-governance.md",
    "35-current-step-02-status.md",
]

REQUIRED_SECTIONS = ["## Purpose", "## Violation handling"]

# The 35 constraints Step 2 must lock. Each is matched by a set of needles;
# every constraint must appear somewhere across rules 25-35.
LOCKED = {
    1: ["master source"],
    2: ["prd", "product_requirements", "product requirements"],
    3: ["requirement id"],
    4: ["hard-code", "hard code", "hardcode"],
    5: ["light theme"],
    6: ["dark mode", "dark theme"],
    7: ["accessibility"],
    8: ["colour alone", "color alone"],
    9: ["48"],
    10: ["tenant", "outlet"],
    11: ["offline", "sync"],
    12: ["paid", "payment"],
    13: ["mask"],
    14: ["tracking", "projection"],
    15: ["external courier"],
    16: ["destructive"],
    17: ["reason"],
    18: ["state contract"],
    19: ["accessibility contract"],
    20: ["recovery"],
    21: ["classification", "classified"],
    22: ["requirement reference", "requirement ids", "requirement id"],
    23: ["permission"],
    24: ["bahasa indonesia", "glossary"],
    25: ["logo"],
    26: ["wireframe"],
    27: ["absent"],
    28: ["step 3"],
    29: ["exact-sha", "exact sha"],
    30: ["immutable"],
    31: ["public repository", "public repo"],
    32: ["independent", "peer review"],
    33: ["adversarial"],
    34: ["graphify"],
    35: ["traceability"],
}

STATUS_VOCAB = {
    "PLANNED", "IN PROGRESS", "TESTED", "WATCH", "NOT IMPLEMENTED", "ABSENT",
    "NOT APPLICABLE", "NOT STARTED", "NO-GO", "GO",
}


def main() -> int:
    root = repo_root()
    rep = Reporter("Step 2 application rules")

    corpus = ""
    for name in RULES:
        rel = f".claude/rules/{name}"
        text = read(root, rel)
        if not text:
            rep.fail(f"{rel} exists")
            continue
        rep.ok(f"{rel} exists ({len(text.splitlines())} lines)")
        corpus += "\n" + text.lower()

        for section in REQUIRED_SECTIONS:
            rep.check(
                section.lower() in text.lower(),
                f"{rel} has a '{section.strip('# ')}' section",
            )

    # -- all 35 constraints are locked somewhere ---------------------------
    unlocked = []
    for number, needles in sorted(LOCKED.items()):
        if any(n in corpus for n in needles):
            rep.ok(f"constraint {number} is locked")
        else:
            rep.fail(f"constraint {number} is locked (needles: {needles})")
            unlocked.append(number)
    rep.check(not unlocked,
              f"all 35 Step 2 constraints are locked "
              f"({len(unlocked)} unlocked: {unlocked})")

    # -- CLAUDE.md references every new rule -------------------------------
    claude = read(root, "CLAUDE.md")
    rep.check(bool(claude), "CLAUDE.md exists")
    unreferenced = [n for n in RULES if n not in claude]
    for n in unreferenced:
        rep.info(f"CLAUDE.md does not reference {n}")
    rep.check(not unreferenced,
              f"CLAUDE.md references every Step 2 rule "
              f"({len(unreferenced)} unreferenced)")

    # -- the Step 2 status rule tells the truth ----------------------------
    status_rule = read(root, ".claude/rules/35-current-step-02-status.md")
    if status_rule:
        rep.check("Step 2" in status_rule and "IN PROGRESS" in status_rule,
                  "rule 35 records Step 2 as IN PROGRESS")
        rep.check("PLANNED" in status_rule,
                  "rule 35 records the later Steps as PLANNED")
        for absent in ("Backend runtime", "Flutter workspace"):
            rep.check(absent in status_rule,
                      f"rule 35 records '{absent}' as ABSENT")
        rep.check("NOT APPLICABLE" in status_rule,
                  "rule 35 records application CI as NOT APPLICABLE")
        rep.check("NOT STARTED" in status_rule,
                  "rule 35 records UAT as NOT STARTED")
        rep.check(
            "documentation is not implementation" in status_rule.lower(),
            "rule 35 states that documentation is not implementation",
        )
        # An agent must never confer GO on itself.
        self_go = re.search(
            r"step 2[^.\n]{0,40}\bis\b[^.\n]{0,20}\bGO\b", status_rule)
        rep.check(
            not self_go,
            "rule 35 does not self-declare GO for Step 2",
        )
        rep.check(
            "owner" in status_rule.lower() and "GO" in status_rule,
            "rule 35 states that GO is owner-conferred",
        )

    # -- no unapproved status vocabulary in the Step 2 rules ---------------
    invented = set()
    for name in RULES:
        text = read(root, f".claude/rules/{name}")
        for match in re.finditer(r"\*\*([A-Z][A-Z \-]{2,24})\*\*", text):
            token = match.group(1).strip()
            if token.isupper() and " " not in token.strip() and len(token) > 3:
                if token not in STATUS_VOCAB and token not in {
                    "MUST", "SHOULD", "COULD", "NOT", "AND", "OR",
                    "CRITICAL", "HIGH", "MEDIUM", "LOW", "INFORMATIONAL",
                    "PUBLIC", "PRIVATE", "INTERNAL", "CONFIDENTIAL",
                    "RESTRICTED", "SECRET", "GATE", "READY", "OPEN",
                    "ACCEPTED", "APPLICABLE", "VISIBLE", "HIDDEN",
                    "DESIGNED", "WCAG", "SVG", "JSON", "AGE",
                }:
                    invented.add(f"{name}: {token}")
    for i in sorted(invented):
        rep.info(i)
    rep.check(
        not invented,
        "no Step 2 rule invents a status word outside the approved vocabulary",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
