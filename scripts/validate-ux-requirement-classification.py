#!/usr/bin/env python3
"""Validate the UX classification of every requirement.

Checks the generated matrix against the registry independently of the
generator, so a generator bug cannot pass itself. Enforces:

  * every requirement in the registry is classified — no exceptions;
  * every classification is drawn from the approved vocabulary;
  * no UI-bearing requirement is orphaned from a screen or a journey;
  * every security requirement remains visible in the mapping.

That last one repeats a real Step 1 defect in which SEC definitions dropped out
of a mapping. It is checked explicitly so it cannot happen twice.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import (
    JOURNEY_ID,
    REQUIREMENT_ID,
    SCREEN_ID,
    UX_CLASSES,
    read,
    requirement_registry,
)

DOC = "docs/quality/STEP_02_TRACEABILITY.md"

EXPECTED_REGISTRY_SIZE = 498

# Every prefix Step 1 closed, with the count it closed at. A prefix that loses
# requirements between Steps is a silent scope reduction.
EXPECTED_PREFIX_COUNTS = {
    "FR": 120, "SEC": 68, "NFR": 50, "FIN": 40, "DEL": 35, "TEN": 30,
    "TRK": 30, "UCL": 30, "NOT": 30, "OFF": 25, "SUB": 20, "RPT": 20,
}


def main() -> int:
    root = repo_root()
    rep = Reporter("UX requirement classification")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    if not text:
        return rep.finish()

    registry = requirement_registry(root)
    rep.check(
        len(registry) == EXPECTED_REGISTRY_SIZE,
        f"the registry still holds {EXPECTED_REGISTRY_SIZE} requirement IDs "
        f"(found {len(registry)})",
    )

    # -- per-prefix counts have not silently shrunk ------------------------
    actual_counts: dict = {}
    for rid in registry:
        actual_counts[rid.split("-")[0]] = \
            actual_counts.get(rid.split("-")[0], 0) + 1
    for prefix, expected in sorted(EXPECTED_PREFIX_COUNTS.items()):
        got = actual_counts.get(prefix, 0)
        rep.check(got == expected,
                  f"the {prefix} series still holds {expected} requirements "
                  f"(found {got})")

    # -- parse the matrix rows ---------------------------------------------
    classified: dict = {}
    malformed = []
    for line in text.splitlines():
        stripped = line.strip()
        if not stripped.startswith("|"):
            continue
        cells = [c.strip().strip("`") for c in stripped.strip("|").split("|")]
        if len(cells) < 3:
            continue
        if not REQUIREMENT_ID.fullmatch(cells[0]):
            continue
        cls = cells[1]
        if cls not in UX_CLASSES:
            malformed.append(f"{cells[0]}: classification '{cls}'")
            continue
        classified[cells[0]] = cells

    for m in malformed[:20]:
        rep.info(m)
    rep.check(
        not malformed,
        f"every classification uses the approved vocabulary "
        f"{list(UX_CLASSES)} ({len(malformed)} malformed)",
    )

    # -- the core gate: nothing unclassified -------------------------------
    missing = sorted(registry - set(classified))
    for m in missing[:25]:
        rep.info(f"requirement with no UX classification: {m}")
    rep.check(
        not missing,
        f"every requirement in the registry is classified "
        f"({len(classified)} classified, {len(missing)} unclassified)",
    )

    extra = sorted(set(classified) - registry)
    for e in extra[:20]:
        rep.info(f"matrix classifies an ID that is not in the registry: {e}")
    rep.check(
        not extra,
        f"the matrix invents no requirement ID ({len(extra)} invented)",
    )

    # -- security requirements must all survive the mapping ----------------
    sec_registry = {r for r in registry if r.startswith("SEC-")}
    sec_classified = {r for r in classified if r.startswith("SEC-")}
    dropped = sorted(sec_registry - sec_classified)
    for d in dropped[:25]:
        rep.info(f"security requirement dropped from the mapping: {d}")
    rep.check(
        not dropped,
        f"every security requirement remains visible in the mapping "
        f"({len(sec_classified)}/{len(sec_registry)} present, "
        f"{len(dropped)} dropped)",
    )

    # The same guard for every other hard-gate series.
    for prefix, label in (("TEN", "tenancy"), ("FIN", "financial"),
                          ("OFF", "offline"), ("TRK", "tracking")):
        in_registry = {r for r in registry if r.startswith(f"{prefix}-")}
        in_matrix = {r for r in classified if r.startswith(f"{prefix}-")}
        rep.check(
            in_registry == in_matrix,
            f"every {label} requirement remains visible in the mapping "
            f"({len(in_matrix)}/{len(in_registry)})",
        )

    # -- UI-bearing requirements are not orphaned --------------------------
    orphan_screen = []
    orphan_journey = []
    ui_count = 0
    for rid, cells in sorted(classified.items()):
        if cells[1] not in ("UI-DIRECT", "UI-INDIRECT"):
            continue
        ui_count += 1
        row = " | ".join(cells)
        if not SCREEN_ID.search(row):
            orphan_screen.append(rid)
        if not JOURNEY_ID.search(row):
            orphan_journey.append(rid)

    for o in orphan_screen[:20]:
        rep.info(f"UI-bearing requirement with no screen: {o}")
    rep.check(
        not orphan_screen,
        f"no UI-critical requirement is orphaned from a screen "
        f"({ui_count} UI-bearing, {len(orphan_screen)} orphaned)",
    )
    for o in orphan_journey[:20]:
        rep.info(f"UI-bearing requirement with no journey: {o}")
    rep.check(
        not orphan_journey,
        f"no UI-critical requirement is orphaned from a journey "
        f"({len(orphan_journey)} orphaned)",
    )

    # -- every cited screen exists ------------------------------------------
    inventory = read(root, "docs/ux/SCREEN_INVENTORY.md")
    if inventory:
        inv_ids = {m.group(0) for m in SCREEN_ID.finditer(inventory)}
        cited = {m.group(0) for m in SCREEN_ID.finditer(text)}
        unknown = sorted(cited - inv_ids)
        for u in unknown[:20]:
            rep.info(f"matrix cites a screen not in the inventory: {u}")
        rep.check(not unknown,
                  f"every screen the matrix cites exists ({len(unknown)} unknown)")
    else:
        rep.fail("docs/ux/SCREEN_INVENTORY.md exists")

    # -- every cited journey exists ----------------------------------------
    journeys = read(root, "docs/ux/CRITICAL_JOURNEYS.md")
    if journeys:
        jrn_ids = {m.group(0) for m in JOURNEY_ID.finditer(journeys)}
        cited = {m.group(0) for m in JOURNEY_ID.finditer(text)}
        unknown = sorted(cited - jrn_ids)
        for u in unknown[:20]:
            rep.info(f"matrix cites a journey that does not exist: {u}")
        rep.check(not unknown,
                  f"every journey the matrix cites exists ({len(unknown)} unknown)")
    else:
        rep.fail("docs/ux/CRITICAL_JOURNEYS.md exists")

    # -- deferred entries must say why -------------------------------------
    deferred_without_reason = []
    for rid, cells in sorted(classified.items()):
        if cells[1] != "DEFERRED-UX":
            continue
        if len(" ".join(cells[2:]).strip(" —|")) < 20:
            deferred_without_reason.append(rid)
    for d in deferred_without_reason[:20]:
        rep.info(f"DEFERRED-UX with no rationale: {d}")
    rep.check(
        not deferred_without_reason,
        "every DEFERRED-UX requirement records a rationale and an owner step",
    )

    # -- honesty ------------------------------------------------------------
    rep.check(
        "NOT IMPLEMENTED" in text,
        "the matrix states that every requirement is NOT IMPLEMENTED",
    )
    rep.check(
        bool(re.search(r"(not|never)[^.\n]{0,80}(evidence|test result|"
                       r"satisfied)", text, re.I)),
        "the matrix states that a mapping is not evidence of satisfaction",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
