#!/usr/bin/env python3
"""Validate docs/ROADMAP.md: Steps 0..14, canonical titles, Steps 1-14 PLANNED.

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

ROADMAP = "docs/ROADMAP.md"
MASTER = "docs/MASTER_SOURCE.md"
STATUS = "docs/STATUS.md"

# Single source of truth — see _common.CANONICAL_CURRENT_STEP.
CURRENT_STEP = CANONICAL_CURRENT_STEP

CANONICAL_TITLES = {
    0: "Master Source and Governance",
    1: "Product Requirement and Domain Model",
    2: "Design System and UX Foundation",
    3: "Runtime, Authentication, Multi-Tenancy, and RBAC",
    4: "Laundry Master Data",
    5: "POS, Order, and Payment Foundation",
    6: "Production Operations",
    7: "Customer Tracking and WhatsApp",
    8: "Pickup and Delivery Operations",
    9: "Unclaimed Laundry and Cashflow Recovery",
    10: "Finance, Reports, and Owner Portfolio",
    11: "Customer Android Experience",
    12: "Subscription and Platform Administration",
    13: "Security, Performance, Backup, and Recovery",
    14: "Pilot and Commercial Launch",
}

# A roadmap entry line: heading, list item, or table row starting with "Step N".
ENTRY = re.compile(
    r"^\s{0,3}(?:#{1,6}\s*|[-*+]\s+|\|\s*|\d+\.\s+)?\*{0,2}Step\s+(\d{1,2})\b",
    re.IGNORECASE,
)


def normalize(text: str) -> str:
    """Lowercase, collapse whitespace, normalize dashes and punctuation."""
    text = text.replace("—", " ").replace("–", " ").replace("-", " ")
    text = re.sub(r"[|*_`:#]", " ", text)
    text = re.sub(r"[,\.]", "", text)
    return re.sub(r"\s+", " ", text).strip().lower()


# ---------------------------------------------------------------------------
# Cross-source roadmap agreement (DEC-0029).
#
# THE GAP THIS CLOSES: this validator read docs/ROADMAP.md and nothing else. The
# Master Source's own §24 roadmap table — the single most authoritative roadmap
# statement in the repository — had never been parsed by any validator. It drifted
# accordingly: it declared Step 2 IN PROGRESS and Step 3 PLANNED while ROADMAP.md,
# STATUS.md, and two immutable GO tags all recorded both as GO WITH ACCEPTED
# DEVIATION, and while §32 of the same document described Step 3 runtime as
# delivered. A full verify-step-03.sh run reported 38 passed / 0 failed with that
# contradiction sitting in the canonical document, because nothing compared them.
#
# A SECOND GAP, found while closing the first: the status-posture checks below only
# examine CURRENT_STEP and the steps after it. Every step BEFORE the current one was
# entirely unvalidated, so a closed step could be silently reverted to PLANNED in
# either document and no gate would notice. These checks deliberately cover all of
# 0..14 for exactly that reason.
#
# The checks are comparative, not phrase-matching. A check that grepped for a
# desired success string would pass on a document that says the right words and
# means nothing, and would need rewriting every time the prose changed.
# ---------------------------------------------------------------------------

# GO WITH ACCEPTED DEVIATION is a qualified GO, not a separate status. Both
# renderings normalise to the same base so the two documents may legitimately
# differ in verbosity without differing in meaning.
_STATE_LINE = re.compile(r"^STEP_(\d{2})_STATUS=([A-Z_]+)$")
_MACHINE_TO_BASE = {
    "PLANNED": "PLANNED",
    "IN_PROGRESS": "IN PROGRESS",
    "TESTED": "TESTED",
    "WATCH": "WATCH",
    "GO": "GO",
    "NO_GO": "NO-GO",
}


def base_status(declared: list[str]) -> str | None:
    """Reduce declared statuses to the single base status they assert.

    'GO WITH ACCEPTED DEVIATION' declares GO. Longest-first matching in
    declared_statuses() already prevents NO-GO from also reporting GO.
    """
    if not declared:
        return None
    for candidate in ("NO-GO", "GO", "WATCH", "TESTED", "IN PROGRESS", "PLANNED"):
        if candidate in declared:
            return candidate
    return None


def master_source_statuses(root) -> tuple[dict[int, str], list[str]]:
    """Parse the Master Source §24 roadmap table. FAILS CLOSED."""
    path = root / MASTER
    if not path.is_file():
        return {}, [f"{MASTER} missing"]
    out: dict[int, str] = {}
    errors: list[str] = []
    for line in read_text(path).splitlines():
        m = re.match(r"^\|\s*Step\s+(\d{1,2})\s*\|", line.strip())
        if not m:
            continue
        n = int(m.group(1))
        status = base_status(declared_statuses(line.upper()))
        if status is None:
            errors.append(f"{MASTER} Step {n} row declares no recognisable status")
            continue
        if n in out and out[n] != status:
            errors.append(f"{MASTER} Step {n} declared twice with different statuses")
        out[n] = status
    return out, errors


def machine_statuses(root) -> tuple[dict[int, str], list[str]]:
    """Parse STATUS.md's machine-readable canonical block. FAILS CLOSED."""
    path = root / STATUS
    if not path.is_file():
        return {}, [f"{STATUS} missing"]
    text = read_text(path)
    begin, end = "<!-- CANONICAL_STEP_STATE_BEGIN -->", "<!-- CANONICAL_STEP_STATE_END -->"
    if text.count(begin) != 1 or text.count(end) != 1:
        return {}, ["STATUS.md canonical state block is missing or duplicated"]
    body = text.split(begin, 1)[1].split(end, 1)[0]
    out: dict[int, str] = {}
    errors: list[str] = []
    for raw in body.splitlines():
        line = raw.strip()
        if not line or line.startswith("<!--") or line.startswith("-->"):
            continue
        m = _STATE_LINE.match(line)
        if not m:
            if line.upper().startswith("STEP"):
                errors.append(f"unparseable canonical state line: {line!r}")
            continue
        n, machine = int(m.group(1)), m.group(2)
        base = _MACHINE_TO_BASE.get(machine)
        if base is None:
            errors.append(f"unknown machine status {machine!r} for STEP_{n:02d}")
            continue
        out[n] = base
    return out, errors


def check_cross_source_agreement(root, rep, roadmap_declared: dict[int, str]) -> None:
    """MASTER_SOURCE §24, ROADMAP.md, and STATUS.md must agree on every step."""
    rep.info("--- cross-source roadmap agreement (DEC-0029) ---")

    master, m_err = master_source_statuses(root)
    machine, s_err = machine_statuses(root)
    for e in m_err + s_err:
        rep.fail(f"cross-source: {e}")
    if m_err or s_err:
        return

    rep.check(bool(master), f"{MASTER} §24 declares a parseable roadmap table")

    for n in range(0, 15):
        want = machine.get(n)
        if want is None:
            rep.fail(f"STATUS.md declares no machine status for Step {n}")
            continue
        got_master = master.get(n)
        rep.check(
            got_master == want,
            f"Step {n}: MASTER_SOURCE §24 ({got_master}) agrees with "
            f"STATUS.md ({want})",
        )
        got_roadmap = roadmap_declared.get(n)
        rep.check(
            got_roadmap == want,
            f"Step {n}: ROADMAP.md ({got_roadmap}) agrees with STATUS.md ({want})",
        )


def check_go_tags(root, rep, machine: dict[int, str]) -> None:
    """A step declared GO must have its GO tag; a step not declared GO must not.

    Checked in BOTH directions. One direction alone is half a check: it would
    catch a fabricated GO but not a GO tag whose step was silently understated —
    which is the exact drift DEC-0029 remediates.

    Skipped entirely when tags are unavailable (a fresh clone without tags
    fetched). Absence of tags is not evidence of anything and must not fail.
    """
    import subprocess

    if not (root / ".git").exists():
        rep.info("no .git in this checkout; skipping live GO-tag cross-check")
        return
    try:
        out = subprocess.run(
            ["git", "-C", str(root), "tag", "--list", "aish-laundry-step-*-go"],
            capture_output=True, text=True, timeout=30,
        )
    except (OSError, subprocess.SubprocessError):
        rep.info("git unavailable; skipping live GO-tag cross-check")
        return
    if out.returncode != 0:
        rep.info("git tag listing failed; skipping live GO-tag cross-check")
        return
    tags = [t.strip() for t in out.stdout.splitlines() if t.strip()]
    if not tags:
        rep.info("no GO tags present in this checkout; skipping live GO-tag cross-check")
        return

    tagged: set[int] = set()
    for tag in tags:
        m = re.match(r"^aish-laundry-step-(\d{2})-", tag)
        if m:
            tagged.add(int(m.group(1)))

    rep.info(f"GO tags present for steps: {sorted(tagged)}")
    for n in range(0, 15):
        declared = machine.get(n)
        if declared is None:
            continue
        if declared == "GO":
            rep.check(n in tagged, f"Step {n} is declared GO and its GO tag exists")
        else:
            rep.check(
                n not in tagged,
                f"Step {n} is declared {declared} and carries no GO tag",
            )


def main() -> int:
    root = repo_root()
    rep = Reporter("roadmap")

    path = root / ROADMAP
    if not rep.check(path.is_file(), f"{ROADMAP} exists"):
        return rep.finish()

    lines = read_text(path).splitlines()

    entries: dict[int, list[tuple[int, str]]] = {}
    order: list[tuple[int, int]] = []
    for idx, line in enumerate(lines):
        m = ENTRY.match(line)
        if not m:
            continue
        num = int(m.group(1))
        entries.setdefault(num, []).append((idx, line))
        order.append((idx, num))

    if not entries:
        rep.fail(f"{ROADMAP} contains recognizable 'Step N' roadmap entries")
        return rep.finish()

    # Coverage: 0..14, exactly once each.
    for n in range(0, 15):
        hits = entries.get(n, [])
        if len(hits) == 1:
            rep.ok(f"Step {n} declared exactly once (line {hits[0][0] + 1})")
        elif not hits:
            rep.fail(f"Step {n} MISSING from {ROADMAP}")
        else:
            rep.fail(
                f"Step {n} duplicated at lines "
                + ", ".join(str(i + 1) for i, _ in hits)
            )

    for n in sorted(entries):
        if not (0 <= n <= 14):
            rep.fail(f"unexpected roadmap step number: Step {n}")

    # Titles
    for n, title in CANONICAL_TITLES.items():
        hits = entries.get(n, [])
        if len(hits) != 1:
            continue
        line = hits[0][1]
        if normalize(title) in normalize(line):
            rep.ok(f"Step {n} title matches canonical: {title}")
        else:
            rep.fail(f"Step {n} title does not match canonical '{title}'")
            rep.info(f"line: {line.strip()}")

    # Step status posture.
    #
    # Step 1 is the step currently under way, so it may legitimately carry a
    # working status. Steps 2..14 must still be PLANNED: a later step showing any
    # other status means work has leaked forward out of its declared scope, which
    # the roadmap lock in MASTER_SOURCE.md §24 forbids.
    entry_lines = sorted(i for i, _ in order)

    def block_for(n: int) -> str | None:
        hits = entries.get(n, [])
        if len(hits) != 1:
            return None
        start = hits[0][0]
        following = [i for i in entry_lines if i > start]
        end = following[0] if following else len(lines)
        return "\n".join(lines[start:end]).upper()

    # Collected for the cross-source check: every step's base status as ROADMAP.md
    # itself declares it, including steps BEFORE the current one.
    roadmap_declared: dict[int, str] = {}
    for n in range(0, 15):
        status = base_status(declared_statuses(block_for(n)))
        if status is not None:
            roadmap_declared[n] = status

    declared = declared_statuses(block_for(CURRENT_STEP))
    if declared:
        allowed = [s for s in declared if s in CURRENT_STEP_ALLOWED]
        if allowed:
            rep.ok(
                f"Step {CURRENT_STEP} carries an allowed working status "
                f"(declared: {', '.join(sorted(set(declared)))})"
            )
        else:
            rep.fail(
                f"Step {CURRENT_STEP} must carry one of {CURRENT_STEP_ALLOWED}; "
                f"declared: {', '.join(sorted(set(declared)))}"
            )
    else:
        rep.fail(f"Step {CURRENT_STEP} declares a recognisable status")

    for n in range(CURRENT_STEP + 1, 15):
        declared = declared_statuses(block_for(n))
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
            rep.ok(f"Step {n} is marked PLANNED")
        else:
            rep.fail(
                f"Step {n} is marked PLANNED; declared: "
                + ", ".join(sorted(set(declared)))
            )

    # --- cross-source agreement and live GO tags (DEC-0029) ---
    check_cross_source_agreement(root, rep, roadmap_declared)
    machine, machine_errors = machine_statuses(root)
    if not machine_errors:
        check_go_tags(root, rep, machine)

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
