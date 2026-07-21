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
    CANONICAL_CURRENT_STEP,
    CURRENT_STEP_ALLOWED,
    FORWARD_LEAK_STATUSES,
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
#
# Raised 3 -> 4 when DEC-0028 recorded the owner's separate canonical authorization
# to start Step 4 (Laundry Master Data), in the same pull request that moves Step 4
# to IN PROGRESS in MASTER_SOURCE.md §24, ROADMAP.md, and STATUS.md. Starting a step
# confers IN PROGRESS and nothing else; GO stays owner-conferred against exact-SHA
# evidence.
#
# Now imported from _common rather than duplicated here — see
# _common.CANONICAL_CURRENT_STEP for why that matters.
CURRENT_STEP = CANONICAL_CURRENT_STEP

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


# ---------------------------------------------------------------------------
# Step 3 GO-tag closure facts (committed constants).
#
# These are the canonical truth the STATUS.md STEP_03_CLOSURE_* block must match.
# Keeping them in the validator, separate from the document, is the anti-drift
# mechanism: a hand-edit to STATUS.md that mis-states the tag target, or that
# confuses the post-tag EVIDENCE commit with the tag's true target, fails here.
#
# The tag PEELS to the RUNTIME merge SHA, never to the EVIDENCE merge SHA. That
# distinction is the whole point of the block — a tag silently re-pointed at the
# evidence commit would be a moved-tag incident, and this catches its paper trail.
# ---------------------------------------------------------------------------
STEP3_GO_TAG = "aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go"
STEP3_TAG_OBJECT = "8b37230ed8df8da343a1546fd949d8a41329fbdf"
STEP3_RUNTIME_MERGE_SHA = "0e2554338812b05eba8411afeb099212b05f9761"
STEP3_EVIDENCE_MERGE_SHA = "ad31473da8376e91b67449bf7820ab9877ea8a4a"

CLOSURE_BEGIN = "<!-- STEP_03_CLOSURE_BEGIN -->"
CLOSURE_END = "<!-- STEP_03_CLOSURE_END -->"
CLOSURE_LINE = re.compile(r"^([A-Z0-9_]+)=(.+)$")


def parse_closure_block(text: str) -> tuple[dict[str, str], list[str]]:
    """Parse the STEP_03_CLOSURE_* block. FAILS CLOSED on any structural fault."""
    begins, ends = text.count(CLOSURE_BEGIN), text.count(CLOSURE_END)
    if begins == 0 or ends == 0:
        return {}, [f"closure block missing ({CLOSURE_BEGIN} x{begins}, {CLOSURE_END} x{ends})"]
    if begins != 1 or ends != 1:
        return {}, [f"exactly one closure block required (found {begins} begin, {ends} end)"]
    body = text.split(CLOSURE_BEGIN, 1)[1].split(CLOSURE_END, 1)[0]
    kv: dict[str, str] = {}
    errors: list[str] = []
    for raw in body.splitlines():
        line = raw.strip()
        if not line or line.startswith("<!--") or line.startswith("-->"):
            continue
        m = CLOSURE_LINE.match(line)
        if not m:
            errors.append(f"unparseable closure line: {line!r}")
            continue
        key, val = m.group(1), m.group(2).strip()
        if key in kv:
            errors.append(f"duplicate closure key {key}")
        kv[key] = val
    return kv, errors


def check_step3_closure(root, rep) -> None:
    """The Step 3 GO-tag closure block must match the committed constants and be
    internally consistent; when a local git tag exists, the real tag must match."""
    rep.info("--- Step 3 GO-tag closure (status advancement) ---")
    text = read_text(root / STATUS)
    kv, errors = parse_closure_block(text)
    for e in errors:
        rep.fail(f"closure block: {e}")
    if errors:
        return

    expected = {
        "STEP_03_CLOSURE_CLASSIFICATION": "GO_WITH_ACCEPTED_DEVIATION",
        "STEP_03_GO_TAG": STEP3_GO_TAG,
        "STEP_03_GO_TAG_OBJECT": STEP3_TAG_OBJECT,
        "STEP_03_RUNTIME_MERGE_SHA": STEP3_RUNTIME_MERGE_SHA,
        "STEP_03_GO_TAG_PEELED": STEP3_RUNTIME_MERGE_SHA,
        "STEP_03_EVIDENCE_MERGE_SHA": STEP3_EVIDENCE_MERGE_SHA,
        "DEPLOYMENT": "ABSENT",
    }
    for key, want in expected.items():
        got = kv.get(key)
        rep.check(got == want, f"closure {key} == {want!r} (found {got!r})")

    # The invariant that matters most: the tag targets the RUNTIME merge, and the
    # evidence commit is a DIFFERENT, later SHA that the tag must never point to.
    peeled = kv.get("STEP_03_GO_TAG_PEELED")
    evidence = kv.get("STEP_03_EVIDENCE_MERGE_SHA")
    rep.check(peeled == STEP3_RUNTIME_MERGE_SHA,
              "GO tag peels to the runtime merge SHA")
    rep.check(peeled != evidence,
              "GO tag peeled SHA is NOT the post-tag evidence SHA")
    rep.check(STEP3_RUNTIME_MERGE_SHA != STEP3_EVIDENCE_MERGE_SHA,
              "runtime merge SHA and evidence merge SHA are distinct")

    # The accepted deviations must stay VISIBLE. `GO WITH ACCEPTED DEVIATION` is
    # not an unqualified GO, and silently dropping DEC-0017 or DEC-0026 from the
    # status narrative would misrepresent the closure. Both must be named.
    for dec in ("DEC-0017", "DEC-0026"):
        rep.check(dec in text,
                  f"STATUS.md keeps the accepted-deviation reference {dec} visible")

    # Optional live verification: only when a real local tag is present. A fresh
    # clone without tags fetched must not fail on this, so absence is not failure.
    git_dir = root / ".git"
    if not git_dir.exists():
        return
    try:
        import subprocess
        obj = subprocess.run(
            ["git", "-C", str(root), "rev-parse", STEP3_GO_TAG],
            capture_output=True, text=True,
        )
        peeled_real = subprocess.run(
            ["git", "-C", str(root), "rev-parse", f"{STEP3_GO_TAG}^{{commit}}"],
            capture_output=True, text=True,
        )
    except Exception:
        return
    if obj.returncode != 0 or peeled_real.returncode != 0:
        rep.info("Step 3 GO tag not present in this checkout; skipping live tag check")
        return
    rep.check(obj.stdout.strip() == STEP3_TAG_OBJECT,
              "real local tag object matches the recorded tag object")
    rep.check(peeled_real.stdout.strip() == STEP3_RUNTIME_MERGE_SHA,
              "real local tag peels to the recorded runtime merge SHA")


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
    ".claude/rules/15-current-product-status.md",
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
        #
        # Column-count-agnostic: a step's table row carries a working status in
        # its LAST cell whether the table is `| Step N — Title | Status |` (two
        # cells) or `| Step N | Title | Status |` (three). The earlier form matched
        # exactly one middle cell and silently missed the three-column layout.
        #
        # The step number is derived from CURRENT_STEP, not hardcoded. It was
        # pinned to 4 while CURRENT_STEP was 3; leaving it pinned would have
        # rejected the authorised Step 4 status as a forward leak AND stopped
        # checking Step 5 at the same time — failing in both directions at once.
        next_step = CURRENT_STEP + 1
        leaked = re.search(
            rf"\|\s*steps?\s*{next_step}\b(?:[^|\n]*\|)+\s*(in progress|tested|watch|go)\b",
            low,
        )
        rep.check(
            leaked is None,
            f"{rel} does not declare Step {next_step} started",
        )


# ---------------------------------------------------------------------------
# Infrastructure self-consistency (DEC-0029).
#
# THE GAP THIS CLOSES: this validator already cross-checked backend runtime, the
# Flutter workspace, deployment, and Application CI against the filesystem. It had
# no check that two tables INSIDE STATUS.md agree with each other, and none tying a
# PostgreSQL or Redis claim to infrastructure/docker-compose.dev.yml.
#
# STATUS.md §2 accordingly declared "PostgreSQL runtime foundation | PRESENT" and
# "Redis runtime foundation | PRESENT" while §6, four sections later, declared
# "Database | ABSENT" and "Redis | ABSENT" — with docker-compose.dev.yml committed
# and verify-step-03.sh reporting both services reachable and migrations applied.
# Each statement was individually well-formed; only their conjunction was wrong,
# which is why a per-claim check could never have caught it.
#
# The rule enforced here is not "never say ABSENT". It is: if one infrastructure
# subject is declared both present and absent, at least one of those rows must name
# the environment it describes. §2 (runtime foundations) and §6 (environments) can
# both be true at once — but only if they say which is which.
# ---------------------------------------------------------------------------

#: Infrastructure subjects whose present/absent claims must not collide unqualified.
#: A subject absent from this list is unchecked, so the list reduces the blind spot
#: rather than eliminating it (DEC-0029, negative consequences).
INFRA_SUBJECTS: list[tuple[str, str]] = [
    ("postgresql", r"postgres(?:ql)?|database"),
    ("redis", r"redis"),
    ("object storage", r"object storage"),
]

#: A row carrying any of these words is scoped to an environment and therefore does
#: not collide with a differently-scoped row about the same subject.
ENV_QUALIFIER = re.compile(
    r"\b(local|development|dev|ci|staging|production|prod|ephemeral|per-run)\b",
    re.IGNORECASE,
)

PRESENT_WORD = re.compile(r"\bPRESENT\b|\bACTIVE\b|\bREACHABLE\b")
ABSENT_WORD = re.compile(r"\bABSENT\b|\bNOT CONFIGURED\b")


def check_infrastructure_consistency(root, rep) -> None:
    """No infrastructure subject may be declared present AND absent unqualified."""
    rep.info("--- infrastructure self-consistency (DEC-0029) ---")
    text = read_text(root / STATUS)

    rows: list[tuple[str, str]] = []  # (subject cell, whole row)
    for line in text.splitlines():
        stripped = line.strip()
        if not stripped.startswith("|"):
            continue
        cells = [c.strip() for c in stripped.strip("|").split("|")]
        if len(cells) < 2:
            continue
        # A separator row (| --- | --- |) carries no claim.
        if all(set(c) <= set("-: ") for c in cells):
            continue
        rows.append((cells[0], stripped))

    for label, pattern in INFRA_SUBJECTS:
        subject_rows = [
            (subj, row) for subj, row in rows
            if re.search(pattern, subj, re.IGNORECASE)
        ]
        if not subject_rows:
            continue

        present_unqualified = [
            row for subj, row in subject_rows
            if PRESENT_WORD.search(row.upper()) and not ENV_QUALIFIER.search(row)
        ]
        absent_unqualified = [
            row for subj, row in subject_rows
            if ABSENT_WORD.search(row.upper()) and not ENV_QUALIFIER.search(row)
        ]
        collision = bool(present_unqualified) and bool(absent_unqualified)
        rep.check(
            not collision,
            f"{label}: no unqualified PRESENT/ABSENT collision in {STATUS} "
            f"({len(present_unqualified)} unqualified present, "
            f"{len(absent_unqualified)} unqualified absent)",
        )
        if collision:
            for row in (present_unqualified + absent_unqualified)[:4]:
                rep.info(f"  {row}")

    # Cross-check against the committed development compose file, in BOTH
    # directions. Claiming the local development database or Redis is ABSENT while
    # docker-compose.dev.yml defines it is the original defect; claiming it PRESENT
    # with no compose file at all would be the mirror-image false claim.
    compose = root / "infrastructure" / "docker-compose.dev.yml"
    compose_text = read_text(compose).lower() if compose.is_file() else ""

    for label, service in (("PostgreSQL", "postgres"), ("Redis", "redis")):
        defined = service in compose_text
        claims_local_absent = re.search(
            rf"\|[^|\n]*local[^|\n]*{service}[^|\n]*\|[^|\n]*\bABSENT\b",
            text, re.IGNORECASE,
        ) is not None
        rep.check(
            not (defined and claims_local_absent),
            f"{STATUS} does not declare local development {label} ABSENT while "
            f"infrastructure/docker-compose.dev.yml defines it",
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

        # Step 3 is GO WITH ACCEPTED DEVIATION and GO-tagged. Once the immutable
        # GO tag exists, PLANNED or IN_PROGRESS here is a FALSE UNDERSTATEMENT —
        # exactly the drift, in the opposite direction, that DEC-0027 caught when
        # this file still forced IN_PROGRESS after runtime shipped. It fails
        # closed on anything but GO.
        rep.check(state.get(3) == "GO",
                  f"STEP_03_STATUS is GO after the Step 3 GO tag (found {state.get(3)!r})")

        # The current step may carry a working status; every step AFTER it must be
        # PLANNED. This bound is derived from CURRENT_STEP rather than hardcoded.
        # It read `n >= 4` while CURRENT_STEP was 3, which was correct then and
        # would have been silently wrong the moment Step 4 legitimately started —
        # it would have forced the authorised current step back to PLANNED.
        current_machine = state.get(CURRENT_STEP)
        allowed_machine = {
            m for m, h in MACHINE_TO_HUMAN.items() if h in CURRENT_STEP_ALLOWED
        }
        rep.check(
            current_machine in allowed_machine,
            f"STEP_{CURRENT_STEP:02d}_STATUS is one of {sorted(allowed_machine)} "
            f"(found {current_machine!r})",
        )

        later_bad = {
            n: s for n, s in state.items() if n > CURRENT_STEP and s != "PLANNED"
        }
        rep.check(not later_bad,
                  f"every step {CURRENT_STEP + 1:02d}-14 is PLANNED (violations: {later_bad})")

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
    check_infrastructure_consistency(root, rep)
    check_cross_document_consistency(root, rep)
    check_step3_closure(root, rep)

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
