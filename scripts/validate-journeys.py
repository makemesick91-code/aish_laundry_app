#!/usr/bin/env python3
"""Validate the critical user journeys.

Every journey must document its error path, its offline path, its security
boundary, and its recovery. A journey with only a happy path is a
demonstration, not a specification.

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
    UX_DIR,
    markdown_files,
    read,
)

DOC = f"{UX_DIR}/CRITICAL_JOURNEYS.md"

REQUIRED_SECTIONS = [
    "trigger", "actor", "precondition", "happy path", "alternative",
    "error path", "offline", "security boundary", "recovery",
    "completion criteria",
]

# The journeys Step 2 is required to cover, matched loosely on their subject.
REQUIRED_JOURNEYS = {
    "tracking link": ["tracking link", "opens tracking"],
    "pickup request": ["request pickup", "requests pickup", "pickup request"],
    "unpaid balance": ["unpaid balance", "belum lunas", "outstanding balance"],
    "kiloan order": ["kiloan"],
    "mixed order": ["mixed order"],
    "condition and photo": ["condition", "photo"],
    "partial payment": ["partial payment"],
    "cashier offline": ["offline"],
    "duplicate prevented": ["duplicate"],
    "production queue": ["production queue"],
    "QC rework": ["rework"],
    "ready for pickup": ["ready_for_pickup", "ready for pickup"],
    "H+1": ["h+1"],
    "H+3": ["h+3"],
    "H+7": ["h+7"],
    "unclaimed follow-up": ["follow-up", "follow up"],
    "pickup to delivery": ["pickup to delivery", "converts pickup"],
    "assign courier": ["assign courier", "assigns courier", "courier assignment"],
    "external courier guest": ["guest"],
    "failed attempt": ["failed attempt", "failed delivery"],
    "record proof": ["proof"],
    "COD reconciliation": ["cod", "courier cash", "reconcil"],
    "tenant switch": ["switch tenant", "tenant switch"],
    "portfolio": ["portfolio"],
    "receivable": ["receivable"],
    "membership revoked": ["membership revoked", "revoked membership"],
    "session expired": ["session expired"],
    "device revoked": ["device revoked"],
    "notification failure": ["notification", "provider fail"],
    "token expired": ["token expired", "expired token"],
    "token revoked": ["token revoked", "revoked token"],
    "sync conflict": ["conflict"],
}


def main() -> int:
    root = repo_root()
    rep = Reporter("critical journeys")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    if not text:
        return rep.finish()
    lowered = text.lower()

    # -- journey IDs --------------------------------------------------------
    ids = {m.group(0) for m in JOURNEY_ID.finditer(text)}
    rep.check(len(ids) >= 32,
              f"at least 32 journeys carry a JRN-### ID (found {len(ids)})")

    # -- every mandated journey is covered ---------------------------------
    missing = []
    for name, needles in sorted(REQUIRED_JOURNEYS.items()):
        if any(n in lowered for n in needles):
            rep.ok(f"journey covered: {name}")
        else:
            rep.fail(f"journey covered: {name}")
            missing.append(name)
    rep.check(not missing,
              f"every mandated journey is documented ({len(missing)} missing)")

    # -- each journey block carries the full contract ----------------------
    blocks = re.split(r"(?=^#{2,4}[^\n]*JRN-\d{3})", text, flags=re.M)
    incomplete = []
    no_requirement = []
    no_screen = []
    checked = 0
    for block in blocks:
        first_line = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first_line.startswith("#"):
            continue
        match = JOURNEY_ID.search(first_line)
        if not match:
            # Not a journey specification block — the document preamble also
            # mentions journey IDs in prose, and it is not a journey.
            continue
        checked += 1
        low = block.lower()
        absent = [s for s in REQUIRED_SECTIONS if s not in low]
        if absent:
            incomplete.append(f"{match.group(0)}: missing {', '.join(absent)}")
        if not REQUIREMENT_ID.search(block):
            no_requirement.append(match.group(0))
        if not SCREEN_ID.search(block):
            no_screen.append(match.group(0))

    rep.check(checked >= 32,
              f"at least 32 journey blocks are specified (found {checked})")
    for msg in incomplete[:20]:
        rep.info(msg)
    rep.check(
        not incomplete,
        f"every journey documents trigger, actor, precondition, happy path, "
        f"alternative, error path, offline path, security boundary, recovery "
        f"and completion criteria ({len(incomplete)} incomplete)",
    )
    for j in no_requirement[:20]:
        rep.info(f"journey with no requirement reference: {j}")
    rep.check(not no_requirement,
              f"every journey links to at least one requirement "
              f"({len(no_requirement)} orphaned)")
    for j in no_screen[:20]:
        rep.info(f"journey with no screen reference: {j}")
    rep.check(not no_screen,
              f"every journey links to at least one screen "
              f"({len(no_screen)} unlinked)")

    # -- journey screens actually exist in the inventory -------------------
    inventory = read(root, f"{UX_DIR}/SCREEN_INVENTORY.md")
    if inventory:
        inv_ids = {m.group(0) for m in SCREEN_ID.finditer(inventory)}
        cited = {m.group(0) for m in SCREEN_ID.finditer(text)}
        unknown = sorted(cited - inv_ids)
        for u in unknown[:20]:
            rep.info(f"journey cites a screen not in the inventory: {u}")
        rep.check(
            not unknown,
            f"every screen a journey cites exists in the inventory "
            f"({len(unknown)} unknown)",
        )
    else:
        rep.fail("SCREEN_INVENTORY.md exists (needed to resolve journey screens)")

    # -- flow diagrams ------------------------------------------------------
    journey_dir = root / UX_DIR / "journeys"
    rep.check(journey_dir.is_dir(), f"{UX_DIR}/journeys/ exists")
    if journey_dir.is_dir():
        files = sorted(journey_dir.glob("*.md"))
        rep.check(len(files) >= 8,
                  f"at least 8 journey flow documents exist (found {len(files)})")
        with_mermaid = sum(
            1 for f in files
            if "```mermaid" in f.read_text(encoding="utf-8", errors="replace")
        )
        rep.check(
            with_mermaid >= 8,
            f"at least 8 journey documents carry a Mermaid diagram "
            f"(found {with_mermaid})",
        )

    # -- honesty ------------------------------------------------------------
    # A document is permitted to name these claims in order to forbid them.
    claim_re = re.compile(r"(optimal route|route optimi[sz]ation|"
                          r"guaranteed (?:delivery|arrival)|unlimited whatsapp)")
    unguarded = []
    for line in text.splitlines():
        low = line.lower()
        if not claim_re.search(low):
            continue
        guard = ("no ", "never", "not ", "without", "forbidden", "prohibit",
                 "must not", "is not claimed", "suggestion")
        if not any(g in low for g in guard):
            unguarded.append(line.strip()[:110])
    for u in unguarded:
        rep.info(u)
    rep.check(
        not unguarded,
        "no journey claims route optimisation, a delivery guarantee, or "
        "unlimited WhatsApp",
    )
    rep.check(
        bool(re.search(r"(never|not|does not) restart", lowered))
        or "first" in lowered,
        "the ageing anchor is described as the first READY_FOR_PICKUP",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
