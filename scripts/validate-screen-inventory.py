#!/usr/bin/env python3
"""Validate the screen inventory.

Every screen must carry a stable ID, requirement references, tenant and
permission behaviour, an error state, a recovery path, and accessibility
notes. A screen specification with no requirement behind it is a feature
somebody invented in a document.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import REQUIREMENT_ID, SCREEN_ID, UX_DIR, read

DOC = f"{UX_DIR}/SCREEN_INVENTORY.md"

PLATFORM_PREFIX = {
    "CUS": ("Customer Android", 17),
    "OPS": ("Ops Android", 36),
    "CON": ("Console Web", 23),
    "TRK": ("Public Tracking Portal", 11),
}

REQUIRED_FIELDS = [
    "platform", "persona", "purpose", "entry", "exit", "primary action",
    "empty", "loading", "error", "offline", "permission", "accessib",
    "responsive", "privacy", "analytics", "step",
]

# Screens whose absence would mean a whole surface was skipped.
ANCHOR_SCREENS = [
    "otp", "pos", "offline queue", "sync conflict", "session expired",
    "device revoked", "unclaimed", "courier", "proof", "portfolio",
    "expired token", "revoked token", "rate limited",
]


def main() -> int:
    root = repo_root()
    rep = Reporter("screen inventory")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    if not text:
        return rep.finish()

    lowered = text.lower()

    # -- screen IDs are present, unique and well-formed --------------------
    ids = SCREEN_ID.findall(text)
    all_ids = {m.group(0) for m in SCREEN_ID.finditer(text)}
    rep.check(len(all_ids) >= 80,
              f"at least 80 screens are inventoried (found {len(all_ids)})")

    # -- per-platform coverage ---------------------------------------------
    for prefix, (label, minimum) in sorted(PLATFORM_PREFIX.items()):
        count = len({i for i in all_ids if i.startswith(f"SCR-{prefix}-")})
        rep.check(
            count >= minimum,
            f"{label} has at least {minimum} screens (found {count})",
        )

    # -- no duplicate screen ID with a different definition ----------------
    heading_ids = re.findall(r"^#{2,5}\s*.*?(SCR-(?:CUS|OPS|CON|TRK)-\d{3})",
                             text, re.M)
    dupes = sorted({i for i in heading_ids if heading_ids.count(i) > 1})
    for d in dupes:
        rep.info(f"screen ID defined more than once: {d}")
    rep.check(not dupes,
              f"no screen ID is defined twice ({len(dupes)} duplicated)")

    # -- the specification vocabulary is present ---------------------------
    missing_fields = [f for f in REQUIRED_FIELDS if f not in lowered]
    for f in missing_fields:
        rep.info(f"screen specification field absent: {f}")
    rep.check(
        not missing_fields,
        f"the inventory documents every mandated screen field "
        f"({len(missing_fields)} missing)",
    )

    # -- anchor screens are present ----------------------------------------
    missing_anchors = [a for a in ANCHOR_SCREENS if a not in lowered]
    for a in missing_anchors:
        rep.info(f"anchor screen absent: {a}")
    rep.check(
        not missing_anchors,
        f"every anchor screen is inventoried ({len(missing_anchors)} missing)",
    )

    # -- requirement references --------------------------------------------
    reqs = {m.group(0) for m in REQUIREMENT_ID.finditer(text)}
    rep.check(
        len(reqs) >= 100,
        f"the inventory cites requirement IDs ({len(reqs)} distinct)",
    )

    # Every screen block must cite at least one requirement. Split on screen
    # IDs appearing in headings and check each block.
    blocks = re.split(r"(?=^#{2,5}[^\n]*SCR-(?:CUS|OPS|CON|TRK)-\d{3})",
                      text, flags=re.M)
    orphan_screens = []
    checked_blocks = 0
    for block in blocks:
        first_line = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first_line.startswith("#"):
            continue
        match = SCREEN_ID.search(first_line)
        if not match:
            continue
        checked_blocks += 1
        if not REQUIREMENT_ID.search(block):
            orphan_screens.append(match.group(0))
    for s in orphan_screens[:20]:
        rep.info(f"screen with no requirement reference: {s}")
    rep.check(
        not orphan_screens,
        f"every screen cites at least one requirement "
        f"({checked_blocks} screen blocks checked, "
        f"{len(orphan_screens)} orphaned)",
    )

    # Per-block rows. Checking the vocabulary document-wide is far too weak:
    # deleting the Error state row from one screen leaves the word "error"
    # everywhere else and the gate would pass. Each screen must carry its own.
    PER_BLOCK_ROWS = [
        ("| Error state", "Error state"),
        ("| Offline behaviour", "Offline behaviour"),
        ("| Permission behaviour", "Permission behaviour"),
        ("| Accessibility notes", "Accessibility notes"),
        ("| Data masked", "Data masked"),
        ("| Primary action", "Primary action"),
        ("| Empty state", "Empty state"),
        ("| Loading state", "Loading state"),
    ]
    incomplete_blocks: list = []
    for block in blocks:
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first.startswith("#"):
            continue
        match = SCREEN_ID.search(first)
        if not match:
            continue
        absent = [label for needle, label in PER_BLOCK_ROWS
                  if needle not in block]
        if absent:
            incomplete_blocks.append(
                f"{match.group(0)}: missing {', '.join(absent)}")
    for msg in incomplete_blocks[:20]:
        rep.info(msg)
    rep.check(
        not incomplete_blocks,
        f"every screen block carries its own error, offline, permission, "
        f"accessibility, masking, action, empty and loading rows "
        f"({len(incomplete_blocks)} incomplete)",
    )

    # -- tenant and permission behaviour -----------------------------------
    rep.check("tenant" in lowered,
              "the inventory documents tenant behaviour")
    rep.check("outlet" in lowered,
              "the inventory documents outlet context")
    rep.check("permission" in lowered,
              "the inventory documents permission behaviour")
    rep.check(
        bool(re.search(r"(visibility|menu)[^.\n]{0,80}(not|never)[^.\n]{0,40}"
                       r"authori", lowered))
        or bool(re.search(r"(not|never)[^.\n]{0,60}authori[^.\n]{0,80}"
                          r"(client|menu|visibility)", lowered)),
        "the inventory states that client-side visibility is not authorization",
    )

    # -- the tracking portal screens carry the masking constraint ----------
    trk_blocks = [b for b in blocks if re.search(r"SCR-TRK-\d{3}", b)]
    masked = sum(1 for b in trk_blocks if "mask" in b.lower())
    rep.check(
        masked > 0,
        f"the Public Tracking Portal screens document data masking "
        f"({masked} of {len(trk_blocks)} blocks mention masking)",
    )

    # -- future implementation step ----------------------------------------
    rep.check(
        bool(re.search(r"step\s*[3-9]|step\s*1[0-4]", lowered)),
        "screens name the future roadmap Step that implements them",
    )
    rep.check(
        "not implemented" in lowered,
        "the inventory states that the screens are NOT IMPLEMENTED",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
