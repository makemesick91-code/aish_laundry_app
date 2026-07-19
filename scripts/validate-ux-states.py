#!/usr/bin/env python3
"""Validate the UX state model.

Twenty states, each with a trigger, a message, allowed and prohibited actions,
and — most importantly — a recovery path. A state with no way out is a dead
end that a user will hit at a counter with a customer waiting.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import UX_DIR, UX_STATE_ID, read

DOC = f"{UX_DIR}/UX_STATE_MODEL.md"

REQUIRED_STATES = [
    "Loading", "Empty", "Error", "Offline", "Pending Sync", "Syncing",
    "Synced", "Failed Sync", "Conflict", "Permission Denied",
    "Session Expired", "Device Revoked", "Tenant Unavailable",
    "Outlet Inactive", "Subscription Limited", "Provider Degraded",
    "Rate Limited", "Maintenance", "Partial Data", "Stale Data",
]

REQUIRED_FIELDS = [
    "trigger", "message", "visual", "allowed action", "prohibited action",
    "recovery", "accessib", "audit", "analytics",
]


def main() -> int:
    root = repo_root()
    rep = Reporter("UX state model")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    if not text:
        return rep.finish()
    lowered = text.lower()

    # -- every mandated state is present -----------------------------------
    missing = [s for s in REQUIRED_STATES if s.lower() not in lowered]
    for s in missing:
        rep.info(f"UX state absent: {s}")
    rep.check(
        not missing,
        f"all {len(REQUIRED_STATES)} mandated UX states are defined "
        f"({len(missing)} missing)",
    )

    # -- state IDs ----------------------------------------------------------
    ids = {m.group(0) for m in UX_STATE_ID.finditer(text)}
    rep.check(
        len(ids) >= len(REQUIRED_STATES),
        f"at least {len(REQUIRED_STATES)} states carry a UXS-### ID "
        f"(found {len(ids)})",
    )

    # -- each state block carries the full contract, including recovery ----
    blocks = re.split(r"(?=^#{2,4}[^\n]*UXS-\d{3})", text, flags=re.M)
    incomplete = []
    no_recovery = []
    checked = 0
    for block in blocks:
        first_line = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first_line.startswith("#"):
            continue
        match = UX_STATE_ID.search(first_line)
        if not match:
            continue
        checked += 1
        low = block.lower()
        absent = [f for f in REQUIRED_FIELDS if f not in low]
        if absent:
            incomplete.append(f"{match.group(0)}: missing {', '.join(absent)}")
        if "recovery" not in low:
            no_recovery.append(match.group(0))

    rep.check(checked >= len(REQUIRED_STATES),
              f"at least {len(REQUIRED_STATES)} state blocks are specified "
              f"(found {checked})")
    for msg in incomplete[:20]:
        rep.info(msg)
    rep.check(
        not incomplete,
        f"every state documents trigger, message, visual pattern, allowed and "
        f"prohibited actions, recovery, accessibility, audit and analytics "
        f"({len(incomplete)} incomplete)",
    )
    for s in no_recovery:
        rep.info(f"state with no recovery path: {s}")
    rep.check(
        not no_recovery,
        f"every UX state has a recovery path ({len(no_recovery)} dead ends)",
    )

    # -- the sync states are genuinely distinguished -----------------------
    for pair in [("Pending Sync", "Syncing"), ("Syncing", "Synced"),
                 ("Failed Sync", "Conflict")]:
        rep.check(
            pair[0].lower() in lowered and pair[1].lower() in lowered,
            f"'{pair[0]}' and '{pair[1]}' are separately defined",
        )
    rep.check(
        bool(re.search(r"(never|not|no) [^.\n]{0,60}silent", lowered)),
        "the model forbids a silent failure",
    )

    # -- permission denied does not leak whether the record exists ---------
    perm = ""
    for block in blocks:
        if "permission denied" in block.lower():
            perm += block.lower()
    if perm:
        rep.check(
            bool(re.search(r"(not|never|without)[^.\n]{0,90}"
                           r"(reveal|disclose|confirm|leak|exist)", perm)),
            "Permission Denied does not reveal whether the record exists",
        )

    # -- session expiry must not silently discard work ---------------------
    sess = ""
    for block in blocks:
        if "session expired" in block.lower():
            sess += block.lower()
    if sess:
        rep.check(
            bool(re.search(r"(preserv|retain|restore|intact|not lost|"
                           r"without losing|unsaved|not discard)", sess)),
            "Session Expired preserves unsaved work rather than discarding it",
        )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
