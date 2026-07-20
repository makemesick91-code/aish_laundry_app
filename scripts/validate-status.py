#!/usr/bin/env python3
"""Validate docs/STATUS.md declares the canonical Step 0 status posture.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import (  # noqa: E402
    Reporter,
    declared_statuses,
    read_text,
    repo_root,
    run_main,
)

STATUS = "docs/STATUS.md"

STEP0_ALLOWED = ["IN PROGRESS", "TESTED", "WATCH", "GO"]

# The step currently under way. Bump only when a step actually starts, in the same
# pull request that moves the status in STATUS.md and ROADMAP.md.
#
# Raised to 3 when DEC-0024 authorised Step 3 runtime. This was raised LATE: runtime
# had already been committed while this constant still read 2 and STATUS.md still
# declared "Step 3 has not begun", "Backend runtime ABSENT", and "Flutter workspace
# ABSENT" — all false. The drift was invisible because REQUIRED_DECLARATIONS below
# actively REQUIRED those false strings, so the validator enforced the untruth
# instead of catching it. The declarations are now phase-aware for exactly that
# reason, and check_runtime_matches_reality() cross-checks the claim against the
# filesystem so the same class of drift cannot recur silently.
CURRENT_STEP = 3
CURRENT_STEP_ALLOWED = ["IN PROGRESS", "TESTED", "WATCH", "GO"]

# Statuses that must never appear against a step later than CURRENT_STEP.
FORWARD_LEAK_STATUSES = ["IN PROGRESS", "TESTED", "WATCH", "GO", "NO-GO"]

# Declarations that hold in EVERY step. These never soften.
REQUIRED_DECLARATIONS: list[tuple[str, str]] = [
    ("deployment is ABSENT", r"deploy\w*[^\n]{0,60}?\bABSENT\b"),
    ("UAT is NOT STARTED", r"uat[^\n]{0,60}?\bNOT[ _-]?STARTED\b"),
]

# The three Step 3 runtime CI contexts. Application CI may be declared ACTIVE
# only when every one of these workflows genuinely exists — see
# check_application_ci_claim().
RUNTIME_CI_WORKFLOWS = [
    ".github/workflows/runtime-foundation.yml",
    ".github/workflows/tenant-isolation.yml",
    ".github/workflows/authentication-rbac.yml",
]


def check_application_ci_claim(root, text, rep) -> None:
    """Application CI is NOT APPLICABLE until real runtime workflows exist.

    Previously this was an unconditional REQUIRED_DECLARATION, which is the same
    trap that made the validator enforce 'backend runtime is ABSENT' after the
    backend existed: the document was required to state something that had stopped
    being true.

    Now the claim is checked against reality in BOTH directions. ACTIVE without the
    workflows is a false claim; NOT APPLICABLE while all three exist is stale.
    """
    low = text.lower()
    claims_active = re.search(r"application ci[^\n]{0,80}?\bactive\b", low) is not None
    claims_na = re.search(
        r"(?:application|aplikasi)[^\n]{0,40}ci[^\n]{0,60}?\bnot[ _-]?applicable\b", low
    ) is not None

    present = [w for w in RUNTIME_CI_WORKFLOWS if (root / w).is_file()]
    all_present = len(present) == len(RUNTIME_CI_WORKFLOWS)

    if claims_active:
        rep.check(
            all_present,
            "Application CI is declared ACTIVE and all three runtime workflows exist "
            f"({len(present)}/{len(RUNTIME_CI_WORKFLOWS)} present)",
        )
    elif claims_na:
        rep.check(
            not all_present,
            "Application CI is declared NOT APPLICABLE and the runtime workflows are absent "
            f"({len(present)}/{len(RUNTIME_CI_WORKFLOWS)} present)",
        )
    else:
        rep.fail("STATUS.md makes no Application CI declaration")

# Declarations that held only while no runtime was authorised (Steps 0-2).
PRE_RUNTIME_DECLARATIONS: list[tuple[str, str]] = [
    ("backend runtime is ABSENT", r"backend[^\n]{0,60}?\bABSENT\b"),
    ("Flutter workspace is ABSENT", r"flutter[^\n]{0,60}?\bABSENT\b"),
]

# ---------------------------------------------------------------------------
# Machine-readable canonical step state.
# ---------------------------------------------------------------------------
STATE_BEGIN = "<!-- CANONICAL_STEP_STATE_BEGIN -->"
STATE_END = "<!-- CANONICAL_STEP_STATE_END -->"
STATE_LINE = re.compile(r"^STEP_(\d{2})_STATUS=([A-Z_]+)$")
VALID_MACHINE_STATUSES = {
    "PLANNED", "IN_PROGRESS", "TESTED", "WATCH", "GO", "NO_GO",
    "NOT_IMPLEMENTED", "ABSENT", "NOT_APPLICABLE", "NOT_STARTED",
}
# machine form -> human form as written in the step table
MACHINE_TO_HUMAN = {
    "PLANNED": "PLANNED",
    "IN_PROGRESS": "IN PROGRESS",
    "TESTED": "TESTED",
    "WATCH": "WATCH",
    "GO": "GO",
    "NO_GO": "NO-GO",
}


def parse_canonical_state(text: str) -> tuple[dict[int, str], list[str]]:
    """Parse the machine-readable block. FAILS CLOSED: any structural problem
    returns an error list, and the caller must treat that as a failure rather
    than as 'no constraints found'."""
    errors: list[str] = []
    begins = text.count(STATE_BEGIN)
    ends = text.count(STATE_END)
    if begins == 0 or ends == 0:
        return {}, [f"canonical state block missing ({STATE_BEGIN} x{begins}, {STATE_END} x{ends})"]
    if begins != 1 or ends != 1:
        return {}, [f"exactly one canonical state block required (found {begins} begin, {ends} end)"]

    body = text.split(STATE_BEGIN, 1)[1].split(STATE_END, 1)[0]
    state: dict[int, str] = {}
    for raw in body.splitlines():
        line = raw.strip()
        if not line or line.startswith("<!--") or line.startswith("-->"):
            continue
        m = STATE_LINE.match(line)
        if not m:
            # An unparseable line inside the block is a structural error, never
            # something to skip: skipping is how a malformed key silently drops
            # a constraint.
            if line.upper().startswith("STEP"):
                errors.append(f"unparseable canonical state line: {line!r}")
            continue
        n, status = int(m.group(1)), m.group(2)
        if n in state:
            errors.append(f"duplicate canonical state key STEP_{n:02d}_STATUS")
        if status not in VALID_MACHINE_STATUSES:
            errors.append(f"unknown status {status!r} for STEP_{n:02d}_STATUS")
        state[n] = status
    return state, errors


def check_runtime_matches_reality(root, rep) -> None:
    """Cross-check runtime claims against the filesystem.

    A status file that merely asserts is a status file that can lie. These checks
    compare the claim to an artefact that actually exists, which is what would
    have caught the 'Backend runtime ABSENT' drift immediately."""
    backend_present = (root / "backend" / "composer.json").is_file()
    flutter_present = (root / "pubspec.yaml").is_file()
    text = read_text(root / STATUS)
    low = text.lower()

    claims_backend_absent = re.search(r"backend[^\n]{0,60}?\babsent\b", low) is not None
    claims_flutter_absent = re.search(r"flutter workspace[^\n]{0,60}?\babsent\b", low) is not None

    rep.check(
        not (backend_present and claims_backend_absent),
        "STATUS.md does not claim the backend is ABSENT while backend/composer.json exists",
    )
    rep.check(
        not (flutter_present and claims_flutter_absent),
        "STATUS.md does not claim the Flutter workspace is ABSENT while pubspec.yaml exists",
    )
    # Deployment must stay absent, and that claim must also be true.
    rep.check(
        not (root / "infrastructure" / "docker-compose.prod.yml").exists(),
        "no production deployment artefact contradicts the ABSENT deployment claim",
    )

# ---------------------------------------------------------------------------
# Cross-document consistency (DEC-0027).
#
# STATUS.md was already cross-checked against the filesystem. CLAUDE.md and
# Rule 49 were not, and both drifted: each still declared the backend runtime
# and the Flutter workspace ABSENT, and Application CI NOT APPLICABLE, long
# after backend/composer.json, pubspec.yaml, and all three runtime workflows
# existed. STATUS.md was correct and the two enforcement layers contradicted it,
# which is precisely the direction Rule 00 says must fail.
#
# These checks are deliberately NEGATIVE — they look for a claim that reality
# contradicts. A check that merely searched for a desired success phrase would
# pass on a document that says the right words and means nothing, and would have
# to be updated every time the wording changed. Asserting an absence that the
# filesystem refutes is the actual defect, so that is what is detected.
# ---------------------------------------------------------------------------
CANONICAL_STATUS_DOCS = [
    "CLAUDE.md",
    ".claude/rules/49-current-step-03-status.md",
]

# claim label -> (regex over the document, predicate over the repo)
ABSENCE_CLAIMS: list[tuple[str, str, str]] = [
    (
        "backend runtime ABSENT",
        r"\|\s*backend runtime\s*\|[^|\n]*\babsent\b",
        "backend/composer.json",
    ),
    (
        "Flutter workspace ABSENT",
        r"\|\s*flutter workspace\s*\|[^|\n]*\babsent\b",
        "pubspec.yaml",
    ),
]


def check_cross_document_consistency(root, rep) -> None:
    """No canonical status document may assert an absence the tree refutes."""
    rep.info("--- cross-document canonical status (DEC-0027) ---")

    for rel in CANONICAL_STATUS_DOCS:
        path = root / rel
        if not rep.check(path.is_file(), f"canonical status document exists: {rel}"):
            continue
        low = read_text(path).lower()

        for label, pattern, artefact in ABSENCE_CLAIMS:
            claims_absent = re.search(pattern, low) is not None
            artefact_exists = (root / artefact).is_file()
            rep.check(
                not (claims_absent and artefact_exists),
                f"{rel} does not declare {label} while {artefact} exists",
            )

        # Application CI: NOT APPLICABLE is only honest while a runtime workflow
        # is genuinely missing.
        claims_ci_na = re.search(
            r"\|\s*application ci\s*\|[^|\n]*\bnot[ _-]?applicable\b", low
        ) is not None
        all_workflows = all((root / w).is_file() for w in RUNTIME_CI_WORKFLOWS)
        rep.check(
            not (claims_ci_na and all_workflows),
            f"{rel} does not declare Application CI NOT APPLICABLE while all "
            f"{len(RUNTIME_CI_WORKFLOWS)} runtime workflows exist",
        )

        # A later step must not be declared started in an enforcement layer
        # either. STATUS.md is checked for this above; the derived documents are
        # checked here so a forward leak cannot hide in one of them.
        leaked = re.search(
            r"\|\s*steps?\s*4\b[^|\n]*\|[^|\n]*\b(in progress|tested|watch|go)\b", low
        )
        rep.check(
            leaked is None,
            f"{rel} does not declare Step 4 started",
        )


FORBIDDEN_IMPLEMENTED = re.compile(r"\bIMPLEMENTED\b")
# "NOT IMPLEMENTED" / "BELUM IMPLEMENTED" are the safe forms.
#
# "FOUNDATION IMPLEMENTED" is also permitted, and only in that exact bounded
# form. The rule exists to stop the document claiming that PRODUCT FEATURES
# exist; a client shell whose debug build has actually been executed and whose
# artefact SHA-256 is recorded is a different and much narrower claim. It stays
# narrow deliberately: a bare "IMPLEMENTED", or any other qualifier, still fails,
# so this cannot be stretched into "POS IMPLEMENTED".
NEGATED_IMPLEMENTED = re.compile(
    r"\b(?:NOT|NON|BELUM|TIDAK|NEVER|NO)[ _-]+IMPLEMENTED\b"
    r"|\bFOUNDATION IMPLEMENTED\b"
)

STEP_LINE = re.compile(
    r"^\s{0,3}(?:#{1,6}\s*|[-*+]\s+|\|\s*|\d+\.\s+)?\*{0,2}Step\s+(\d{1,2})\b",
    re.IGNORECASE,
)


def main() -> int:
    root = repo_root()
    rep = Reporter("status")

    path = root / STATUS
    if not rep.check(path.is_file(), f"{STATUS} exists"):
        return rep.finish()

    text = read_text(path)
    lines = text.splitlines()
    upper = text.upper()

    # --- Step 0 status ---
    step0_lines = [ln for ln in lines if STEP_LINE.match(ln) and
                   int(STEP_LINE.match(ln).group(1)) == 0]
    if not step0_lines:
        rep.fail("Step 0 status line found")
    else:
        blob = " ".join(step0_lines).upper()
        hit = next((s for s in STEP0_ALLOWED if s in blob), None)
        if hit:
            rep.ok(f"Step 0 status is one of {STEP0_ALLOWED} (found: {hit})")
        else:
            rep.fail(f"Step 0 status must be one of {STEP0_ALLOWED}")
            rep.info(f"line: {step0_lines[0].strip()}")

    # --- Step status posture ---
    #
    # The current step may carry a working status. Every step after it must be
    # PLANNED and nothing else: a later step showing a working status means work
    # has leaked forward out of its declared scope.
    entry_index = [
        (i, int(STEP_LINE.match(ln).group(1)))
        for i, ln in enumerate(lines)
        if STEP_LINE.match(ln)
    ]
    starts = sorted(i for i, _ in entry_index)

    def blocks_for(n: int) -> list[str]:
        out = []
        for start in [i for i, num in entry_index if num == n]:
            following = [i for i in starts if i > start]
            end = following[0] if following else len(lines)
            out.append("\n".join(lines[start:end]).upper())
        return out

    def declared_for(n: int) -> list[str]:
        out: list[str] = []
        for blk in blocks_for(n):
            out.extend(declared_statuses(blk))
        return out

    current = declared_for(CURRENT_STEP)
    if not current:
        rep.fail(f"Step {CURRENT_STEP} declares a recognisable status")
    elif [s for s in current if s in CURRENT_STEP_ALLOWED]:
        rep.ok(
            f"Step {CURRENT_STEP} carries an allowed working status "
            f"(declared: {', '.join(sorted(set(current)))})"
        )
    else:
        rep.fail(
            f"Step {CURRENT_STEP} must carry one of {CURRENT_STEP_ALLOWED}; "
            f"declared: {', '.join(sorted(set(current)))}"
        )

    for n in range(CURRENT_STEP + 1, 15):
        declared = declared_for(n)
        if not declared:
            rep.fail(f"Step {n} declares a recognisable status")
            continue
        leaked = sorted({s for s in declared if s in FORWARD_LEAK_STATUSES})
        if leaked:
            rep.fail(
                f"Step {n} must be PLANNED only, but declares: "
                + ", ".join(leaked)
            )
        elif "PLANNED" in declared:
            rep.ok(f"Step {n} declared PLANNED")
        else:
            rep.fail(
                f"Step {n} declared PLANNED; found: "
                + ", ".join(sorted(set(declared)))
            )

    # --- no feature marked IMPLEMENTED ---
    # Status vocabulary is UPPERCASE, so the match is case-sensitive on the raw
    # line. Lowercase prose such as "never evidence of an implemented feature"
    # is not a status declaration and must not be flagged.
    offending = []
    for i, line in enumerate(lines):
        if not FORBIDDEN_IMPLEMENTED.search(line):
            continue
        cleaned = NEGATED_IMPLEMENTED.sub("", line)
        if FORBIDDEN_IMPLEMENTED.search(cleaned):
            offending.append((i + 1, line.strip()))
    if offending:
        rep.fail("no feature is marked IMPLEMENTED")
        for lineno, content in offending[:10]:
            rep.info(f"line {lineno}: {content}")
    else:
        rep.ok("no feature is marked IMPLEMENTED")

    # --- explicit absence declarations ---
    for label, pattern in REQUIRED_DECLARATIONS:
        rep.check(
            re.search(pattern, text, re.IGNORECASE) is not None,
            f"declares {label}",
        )

    # Runtime-absence declarations apply only while no runtime is authorised.
    if CURRENT_STEP < 3:
        for label, pattern in PRE_RUNTIME_DECLARATIONS:
            rep.check(
                re.search(pattern, text, re.IGNORECASE) is not None,
                f"declares {label}",
            )
    else:
        rep.ok("runtime-absence declarations not applicable from Step 3 (DEC-0024)")

    check_application_ci_claim(root, text, rep)

    rep.check("NOT STARTED" in upper, "uses status vocabulary NOT STARTED")

    # --- machine-readable canonical state ---------------------------------
    state, state_errors = parse_canonical_state(text)
    if state_errors:
        for e in state_errors:
            rep.fail(f"canonical state: {e}")
    else:
        rep.ok("canonical state block parses (exactly one, no duplicate keys)")

        missing = [n for n in range(0, 15) if n not in state]
        rep.check(not missing,
                  f"canonical state declares every step 00-14 (missing: {missing})")

        rep.check(state.get(3) == "IN_PROGRESS",
                  f"STEP_03_STATUS is IN_PROGRESS (found {state.get(3)!r})")

        later_bad = {n: s for n, s in state.items() if n >= 4 and s != "PLANNED"}
        rep.check(not later_bad,
                  f"every step 04-14 is PLANNED (violations: {later_bad})")

        # --- machine vs human agreement -----------------------------------
        # Neither form may drift from the other. This is the check that would
        # have caught the stale Step 3 row immediately.
        disagreements = []
        for n, machine in sorted(state.items()):
            human_expected = MACHINE_TO_HUMAN.get(machine)
            if human_expected is None:
                continue
            row = re.search(
                rf"^\|\s*Step {n}\s*\|[^|]*\|\s*([^|]+?)\s*\|",
                text, re.MULTILINE,
            )
            if row is None:
                continue
            declared = row.group(1).strip().upper()
            if human_expected not in declared:
                disagreements.append(
                    f"Step {n}: machine={machine} human={declared!r}"
                )
        rep.check(
            not disagreements,
            f"machine-readable and human-readable status agree ({len(disagreements)} disagreement(s))",
        )
        for d in disagreements[:5]:
            rep.info(d)

    # --- claims cross-checked against the filesystem ----------------------
    check_runtime_matches_reality(root, rep)
    check_cross_document_consistency(root, rep)

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
