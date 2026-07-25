#!/usr/bin/env python3
"""Shared helpers for Aish Laundry App Step 0 validators.

Standard library only. No third-party dependencies.
"""

from __future__ import annotations

import os
import sys
from pathlib import Path


def repo_root() -> Path:
    """Resolve the repository root from this file's own location."""
    return Path(__file__).resolve().parent.parent


class Reporter:
    """Collects PASS/FAIL results and prints them deterministically."""

    def __init__(self, title: str) -> None:
        self.title = title
        self.passed = 0
        self.failed = 0
        print("=" * 72)
        print(f"VALIDATOR: {title}")
        print("=" * 72)

    def ok(self, message: str) -> None:
        self.passed += 1
        print(f"PASS  {message}")

    def fail(self, message: str) -> None:
        self.failed += 1
        print(f"FAIL  {message}")

    def check(self, condition: bool, message: str) -> bool:
        if condition:
            self.ok(message)
        else:
            self.fail(message)
        return bool(condition)

    def info(self, message: str) -> None:
        print(f"      {message}")

    def finish(self) -> int:
        total = self.passed + self.failed
        print("-" * 72)
        print(
            f"SUMMARY [{self.title}]: {self.passed}/{total} checks passed, "
            f"{self.failed} failed"
        )
        if self.failed:
            print(f"RESULT: FAIL ({self.title})")
            return 1
        print(f"RESULT: PASS ({self.title})")
        return 0


def read_text(path: Path) -> str:
    """Read a UTF-8 text file, tolerating decode errors instead of crashing."""
    try:
        return path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return path.read_text(encoding="utf-8", errors="replace")


def tracked_files(root: Path) -> list[Path]:
    """Return repository files, excluding .git. Uses git when available."""
    import subprocess

    try:
        out = subprocess.run(
            ["git", "-C", str(root), "ls-files", "-z", "--cached", "--others",
             "--exclude-standard"],
            capture_output=True,
            check=True,
        )
        names = [n for n in out.stdout.decode("utf-8", "replace").split("\0") if n]
        return [root / n for n in names if (root / n).is_file()]
    except (OSError, subprocess.CalledProcessError):
        result = []
        for dirpath, dirnames, filenames in os.walk(root):
            dirnames[:] = [d for d in dirnames if d != ".git"]
            for name in filenames:
                result.append(Path(dirpath) / name)
        return result


#: The highest canonical roadmap step that has STARTED.
#:
#: It may carry any working status through GO — GO is the terminal status of the
#: current step, not a signal to advance this constant. Bump it only when the NEXT
#: step actually starts, in the same pull request that moves the status in
#: MASTER_SOURCE.md §24, ROADMAP.md, and STATUS.md, and only under the separate
#: canonical authorization the step requires (Rule 49's precedent, DEC-0028).
#:
#: This lives here, once, deliberately. It was previously duplicated as a private
#: CURRENT_STEP in validate-roadmap.py, validate-status.py, and (as a hardcoded
#: literal) in validate-runtime-scope.py and test-status-advancement.sh. Bumping
#: one and not the others is not a hypothetical: it happened when Step 4 started,
#: and it produced four failures whose real cause was that a single fact was
#: recorded in four places. One source, imported everywhere, is the fix.
#:
#: History: 2 through Step 2. Raised to 3 for Step 3 (DEC-0024), LATE — runtime was
#: already committed while it still read 2 (DEC-0027). Raised to 4 for Step 4
#: (DEC-0028), in the same change that moved the status everywhere. Raised to 5 for
#: Step 5 (DEC-0035), likewise moving the status in §24, ROADMAP, and STATUS together.
#: Raised to 6 for Step 6 — Production Operations (DEC-0037), in the same change that
#: split STEP6_PLUS_FEATURE_TOKENS and moved the status everywhere. Raised to 7 for
#: Step 7 — Customer Tracking and WhatsApp (DEC-0039), in the same change that split
#: STEP7_PLUS_FEATURE_TOKENS (tracking + notification labels only) and moved the
#: status PLANNED -> IN PROGRESS in §24, ROADMAP, and STATUS together. Step 6's GO tag
#: was demoted into HISTORICAL_GO_TAGS in the same change (it is now a prior step).
CANONICAL_CURRENT_STEP = 7

#: Statuses the current step may legitimately carry.
CURRENT_STEP_ALLOWED = ["IN PROGRESS", "TESTED", "WATCH", "GO"]

#: Statuses that must never appear against a step LATER than the current step.
#: Work leaking forward out of its declared scope is a roadmap-lock violation.
FORWARD_LEAK_STATUSES = ["IN PROGRESS", "TESTED", "WATCH", "GO", "NO-GO"]

#: The complete canonical status vocabulary. Nothing else is a status.
STATUS_VOCABULARY = [
    "NOT IMPLEMENTED",
    "NOT APPLICABLE",
    "NOT STARTED",
    "IN PROGRESS",
    "PLANNED",
    "TESTED",
    "WATCH",
    "ABSENT",
    "NO-GO",
    "GO",
]

# ---------------------------------------------------------------------------
# Step 6 GO-tag lifecycle (shared by validate-roadmap.py and validate-status.py).
#
# The immutable Step 6 GO tag is created by the owner only AFTER the governance
# closure merges, so there is an authorised window in which the step is canonically
# GO but the tag is legitimately absent. That window is expressed as a DETERMINISTIC
# CANONICAL FACT — the STATUS.md STEP_06_GO_TAG_STATE marker for the canonical
# current step — not as an environmental coincidence (which tags happen to be
# fetched) and not as a branch-name bypass. The verdict functions below are PURE so
# the lifecycle can be adversarially tested without touching real git tags.
# ---------------------------------------------------------------------------
STEP6_GO_TAG_NAME = "aish-laundry-step-06-production-operations-v1.0.0-go"
STEP6_RUNTIME_MERGE_SHA = "82f162f25a39cc9501c6ee35a9728f0e01999725"

#: Historical GO tags and their immutable peel targets. A present tag whose peel
#: differs is a move/corruption incident.
HISTORICAL_GO_TAGS = {
    "aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go":
        "0e2554338812b05eba8411afeb099212b05f9761",
    "aish-laundry-step-04-laundry-master-data-v1.0.0-go":
        "af31ea3b0945b274b249ff21cf30918cb2d17a5f",
    "aish-laundry-step-05-pos-order-payment-foundation-v1.0.0-go":
        "f0524b3a07f5306ec8b5c0584f94f865ec9f9346",
    # Demoted to historical when Step 7 started (DEC-0039): Step 6 is now a prior
    # step. Its GO tag peels to the Step 6 runtime merge and must stay unchanged.
    "aish-laundry-step-06-production-operations-v1.0.0-go":
        "82f162f25a39cc9501c6ee35a9728f0e01999725",
}


def step6_tag_verdict(step6_tags, pretag_authorised):
    """Pure Step 6 GO-tag lifecycle verdict — no git, no environment.

    ``step6_tags`` is the list of tags whose name matches
    ``aish-laundry-step-06-*-go`` present in the checkout, each a dict
    ``{"name": str, "annotated": bool, "peeled": str | None}``.
    ``pretag_authorised`` is the deterministic canonical fact that the step is in
    its authorised pre-tag closure window.

    Returns a list of ``(ok: bool, message: str)`` results (fails closed):

    * no tag + authorised pre-tag  -> PASS (the whole point of the lifecycle);
    * no tag + NOT authorised       -> FAIL (a GO with no tag and no authorisation);
    * exactly the canonical annotated tag peeling to the runtime merge -> PASS;
    * lightweight / wrong-peel / wrong-name / duplicate                 -> FAIL.
    """
    results: list[tuple[bool, str]] = []
    if not step6_tags:
        if pretag_authorised:
            results.append((
                True,
                "Step 6 GO tag absent during the authorised pre-tag closure window "
                "(deterministic STATUS.md STEP_06_GO_TAG_STATE=NOT_YET_CREATED)",
            ))
        else:
            results.append((
                False,
                "Step 6 is declared GO but its GO tag is absent and no authorised "
                "pre-tag closure state is declared",
            ))
        return results

    names = sorted(t["name"] for t in step6_tags)
    if len(step6_tags) != 1 or names[0] != STEP6_GO_TAG_NAME:
        results.append((
            False,
            f"exactly one Step 6 GO tag named {STEP6_GO_TAG_NAME} is permitted "
            f"(found {names})",
        ))
        return results

    tag = step6_tags[0]
    results.append((
        bool(tag.get("annotated")),
        f"Step 6 GO tag {STEP6_GO_TAG_NAME} is annotated (not lightweight)",
    ))
    results.append((
        tag.get("peeled") == STEP6_RUNTIME_MERGE_SHA,
        f"Step 6 GO tag peels to the runtime merge {STEP6_RUNTIME_MERGE_SHA[:12]} "
        f"(found {str(tag.get('peeled'))[:12]})",
    ))
    return results


def historical_tag_verdict(present, expected=None):
    """Pure verdict for historical GO tags. ``present`` maps tag name -> peeled sha
    for the historical tags actually present in the checkout; ``expected`` maps
    name -> required sha (defaults to HISTORICAL_GO_TAGS). A present tag whose peel
    differs from expected is a move/corruption and fails closed. Absent tags are not
    judged (a fresh clone has none)."""
    if expected is None:
        expected = HISTORICAL_GO_TAGS
    results: list[tuple[bool, str]] = []
    for name, want in expected.items():
        if name in present:
            results.append((
                present[name] == want,
                f"historical GO tag {name} still peels unchanged to {want[:12]} "
                f"(found {str(present[name])[:12]})",
            ))
    return results


def authorised_pretag_go_steps(root):
    """Steps whose GO tag may legitimately be ABSENT because the step is in an
    authorised pre-tag closure window, expressed as a DETERMINISTIC canonical fact
    in STATUS.md: a ``STEP_<nn>_GO_TAG_STATE=...NOT_YET_CREATED...`` marker in a
    closure block, for a step that declares GO. Not environmental, not branch-based,
    not dependent on which tags are fetched.

    Scans EVERY step, not only the canonical current step: when the next step
    starts (CANONICAL_CURRENT_STEP advances) the just-closed step becomes a prior
    step but may still carry its pre-tag marker until the owner creates the tag and
    the marker is flipped. Honouring the marker for any GO step that carries it is
    safe — a marker can only authorise an ABSENT tag; a tag that IS present is still
    validated in full against its committed peel target (step<n>_tag_verdict /
    historical_tag_verdict), so a stale marker never masks a wrong or moved tag."""
    import re as _re

    try:
        text = (root / "docs" / "STATUS.md").read_text(encoding="utf-8")
    except OSError:
        return set()
    out: set[int] = set()
    for n in range(0, 15):
        declares_go = _re.search(rf"^STEP_{n:02d}_STATUS=GO\b", text, _re.MULTILINE)
        marker = _re.search(rf"^STEP_{n:02d}_GO_TAG_STATE=\S*NOT_YET_CREATED", text, _re.MULTILINE)
        if declares_go and marker:
            out.add(n)
    return out


def declared_statuses(block: "str | None") -> list[str]:
    """Extract status words that a roadmap/status block actually *declares*.

    Only two shapes count as a declaration:

    * a markdown table cell, e.g. ``| 1 | Title | IN PROGRESS |``
    * a ``Status:`` line, e.g. ``**Status: IN PROGRESS**``

    Prose is deliberately ignored. Scanning a whole block for status words gives
    false positives that matter: "GO" is a substring of "GOVERNANCE", and a scope
    line such as "restore is tested" is not a declaration that the step is
    TESTED. Both produced spurious failures before this helper existed.

    Longest-first matching prevents "NOT IMPLEMENTED" from also reporting the
    substring "IMPLEMENTED", and prevents "NO-GO" from reporting "GO".
    """
    if not block:
        return []

    import re as _re

    candidates: list[str] = []
    for line in block.splitlines():
        stripped = line.strip()
        if stripped.startswith("|"):
            candidates.extend(
                cell.strip() for cell in stripped.strip("|").split("|")
            )
        m = _re.match(
            r"^[*_\s]*status[*_\s]*:\s*(.+?)\s*$", stripped, _re.IGNORECASE
        )
        if m:
            # Trim trailing commentary after an em dash or parenthesis.
            value = _re.split(r"[—(]", m.group(1))[0]
            candidates.append(value)

    found: list[str] = []
    for cell in candidates:
        text = _re.sub(r"[*_`]", "", cell).strip().upper()
        if not text:
            continue
        remaining = text
        for status in sorted(STATUS_VOCABULARY, key=len, reverse=True):
            if _re.search(rf"(?<![A-Z-]){_re.escape(status)}(?![A-Z-])", remaining):
                found.append(status)
                remaining = remaining.replace(status, " ")
    return found


def run_main(func) -> None:
    """Run a validator main() and exit with its code, never with a traceback."""
    try:
        sys.exit(func())
    except SystemExit:
        raise
    except Exception as exc:  # pragma: no cover - defensive
        print(f"FAIL  validator crashed: {type(exc).__name__}: {exc}")
        sys.exit(1)
