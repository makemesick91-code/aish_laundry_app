#!/usr/bin/env python3
"""Validate that every mandated Step 2 artifact exists and is substantive.

An empty file, a stub, or a heading with no body is not a deliverable. This
validator enforces existence, a minimum substance floor, closed code fences,
and the Step 2 honesty constraints on claim language.

Standard library only.
"""

from __future__ import annotations

import sys

from _common import Reporter, repo_root
from _step02 import (
    strip_emphasis,
    A11Y_CAVEAT,
    A11Y_CLAIM,
    FORBIDDEN_CLAIMS,
    markdown_files,
    read,
    unclosed_fences,
)

DESIGN_DOCS = [
    "DESIGN_SYSTEM.md", "DESIGN_PRINCIPLES.md", "BRAND_FOUNDATION.md",
    "COLOR_AND_CONTRAST.md", "TYPOGRAPHY.md", "SPACING_SIZING_DENSITY.md",
    "SHAPE_BORDER_ELEVATION.md", "ICONOGRAPHY.md",
    "MOTION_AND_REDUCED_MOTION.md", "RESPONSIVE_FOUNDATION.md",
    "PLATFORM_ADAPTATION.md", "ACCESSIBILITY.md", "CONTENT_DESIGN.md",
    "UX_COPY_GLOSSARY.md", "DATA_VISUALIZATION.md", "COMPONENT_CATALOG.md",
    "COMPONENT_STATE_MATRIX.md", "FORM_AND_VALIDATION_PATTERNS.md",
    "DESIGN_DEBT_REGISTER.md", "DESIGN_DECISION_LOG.md",
    "DESIGN_TRACEABILITY.md",
]

UX_DOCS = [
    "SCREEN_INVENTORY.md", "CRITICAL_JOURNEYS.md", "UX_STATE_MODEL.md",
    "OFFLINE_AND_SYNC_UX.md", "SECURITY_AND_PRIVACY_UX.md",
    "TENANT_AND_OUTLET_CONTEXT_UX.md", "CUSTOMER_ANDROID_UX.md",
    "OPS_ANDROID_UX.md", "COURIER_UX.md", "CONSOLE_WEB_UX.md",
    "TRACKING_PORTAL_UX.md", "UNCLAIMED_LAUNDRY_UX.md",
    "USABILITY_TEST_PLAN.md", "UX_ACCEPTANCE_CRITERIA.md",
    "UX_OPEN_QUESTIONS.md",
]

IA_DOCS = [
    "CUSTOMER_ANDROID_IA.md", "OPS_ANDROID_IA.md", "CONSOLE_WEB_IA.md",
    "TRACKING_PORTAL_IA.md", "ROLE_NAVIGATION_MATRIX.md",
    "TENANT_OUTLET_CONTEXT_MODEL.md", "GLOBAL_SEARCH_MODEL.md",
]

OTHER_DOCS = [
    "docs/security/DESIGN_AND_UX_THREAT_REVIEW.md",
    "docs/quality/STEP_02_DEFINITION_OF_DONE.md",
    "docs/quality/STEP_02_TRACEABILITY.md",
]

RULE_FILES = [
    "25-design-system-foundation.md", "26-design-token-governance.md",
    "27-accessibility-foundation.md", "28-platform-adaptive-navigation.md",
    "29-ux-state-model.md", "30-content-design-and-localization.md",
    "31-responsive-and-device-foundation.md", "32-security-and-privacy-ux.md",
    "33-design-traceability.md", "34-component-and-screen-governance.md",
    "35-current-step-02-status.md",
]

MIN_LINES = 40


def main() -> int:
    root = repo_root()
    rep = Reporter("Step 2 required files")

    required: list = []
    required += [f"docs/design/{n}" for n in DESIGN_DOCS]
    required += [f"docs/ux/{n}" for n in UX_DOCS]
    required += [f"docs/ux/information-architecture/{n}" for n in IA_DOCS]
    required += OTHER_DOCS
    required += [f".claude/rules/{n}" for n in RULE_FILES]

    missing = thin = 0
    for rel in required:
        path = root / rel
        if not path.is_file():
            rep.fail(f"{rel} exists")
            missing += 1
            continue
        lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
        if len(lines) < MIN_LINES:
            rep.fail(f"{rel} is substantive (only {len(lines)} lines, "
                     f"minimum {MIN_LINES})")
            thin += 1
        else:
            rep.ok(f"{rel} ({len(lines)} lines)")

    rep.check(missing == 0, f"no mandated Step 2 artifact is missing "
                            f"({missing} missing)")
    rep.check(thin == 0, f"no mandated Step 2 artifact is a stub ({thin} thin)")

    # -- structural integrity of every Step 2 markdown file ----------------
    scanned = 0
    broken_fences: list = []
    for path in markdown_files(root, "docs/design", "docs/ux"):
        scanned += 1
        text = path.read_text(encoding="utf-8", errors="replace")
        if unclosed_fences(text):
            broken_fences.append(path.relative_to(root).as_posix())
    for rel in broken_fences:
        rep.info(f"unclosed code fence in {rel}")
    rep.check(not broken_fences,
              f"every code fence is closed ({scanned} files scanned)")

    # -- honesty: no claim beyond what Step 2 produced ---------------------
    claim_hits: list = []
    for path in markdown_files(root, "docs/design", "docs/ux", "docs/quality",
                               "docs/security", ".claude/rules"):
        text = path.read_text(encoding="utf-8", errors="replace")
        lowered = text.lower()
        rel = path.relative_to(root).as_posix()
        for claim in FORBIDDEN_CLAIMS:
            if claim.lower() in lowered:
                # A file is allowed to quote the claim in order to forbid it.
                for line in text.splitlines():
                    line = strip_emphasis(line)
                    if claim.lower() not in line.lower():
                        continue
                    guard = ("never", "not ", "forbidden", "prohibit",
                             "must not", "no ", "is wrong", "false claim",
                             "fabricat", "placeholder", "remove")
                    if not any(g in line.lower() for g in guard):
                        claim_hits.append(f"{rel}: {line.strip()[:110]}")
    for hit in claim_hits:
        rep.info(hit)
    rep.check(
        not claim_hits,
        "no Step 2 document makes a claim beyond what Step 2 produced",
    )

    # -- the accessibility claim is worded exactly as mandated -------------
    a11y = read(root, "docs/design/ACCESSIBILITY.md")
    if a11y:
        rep.check(A11Y_CLAIM in a11y,
                  f"ACCESSIBILITY.md carries the exact claim '{A11Y_CLAIM}'")
        rep.check(A11Y_CAVEAT in a11y,
                  f"ACCESSIBILITY.md carries the exact caveat '{A11Y_CAVEAT}'")

    # -- the runtime folders -----------------------------------------------
    #
    # Through Steps 0-2 these folders had to contain nothing but a README or a
    # .gitkeep, because those steps created no runtime. DEC-0024 authorises Step 3
    # to place runtime inside them, so asserting emptiness here would make Step 3
    # impossible while proving nothing that is still true.
    #
    # The guarantee is NOT abandoned — it is relocated to where it remains true:
    #
    #   Step 0-2 emptiness -> proven against the immutable GO tags by the
    #                         `classify` job (scripts/validate-no-runtime.py)
    #   Step 3+ placement  -> proven on current main by
    #                         scripts/validate-runtime-scope.py, which is an
    #                         allowlist and rejects runtime outside approved roots
    #
    # This validator therefore checks that the folders still EXIST and remain
    # documented, and delegates placement policy to the scope guard rather than
    # duplicating a rule that has since been superseded.
    for folder in ("apps", "backend", "packages", "infrastructure"):
        d = root / folder
        rep.check(d.is_dir(), f"{folder}/ exists")
        if not d.is_dir():
            continue
        documented = (d / "README.md").is_file() or any(
            (d / child / "README.md").is_file() for child in
            [c.name for c in d.iterdir() if c.is_dir()]
        )
        rep.check(
            documented,
            f"{folder}/ carries a README describing its contents",
        )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
