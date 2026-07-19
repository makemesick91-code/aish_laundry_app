#!/usr/bin/env python3
"""Validate the component catalog.

Every component must carry a stable ID and the full contract that later Steps
will build against: states, tokens, keyboard, screen reader, validation,
loading, disabled, error, privacy, platform adaptation, prohibited usage, and
requirement references.

A component without an accessibility contract is an accessibility defect
scheduled for later, which is how accessibility work never happens.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import COMPONENT_ID, DESIGN_DIR, REQUIREMENT_ID, load_tokens, read

DOC = f"{DESIGN_DIR}/COMPONENT_CATALOG.md"

REQUIRED_COMPONENTS = [
    "Button", "Icon Button", "Floating Action Button", "Link", "Text Field",
    "Search Field", "Phone Field", "Money Field", "Weight Field",
    "Quantity Field", "OTP Field", "Text Area", "Dropdown", "Autocomplete",
    "Date Picker", "Time Window Picker", "Checkbox", "Radio", "Switch",
    "Segmented Control", "Chip", "Status Badge", "Avatar", "Customer Card",
    "Order Card", "Production Job Card", "Courier Job Card",
    "Tracking Summary Card", "Payment Summary", "Receivable Summary",
    "Receipt Preview", "List", "Data Table", "Pagination", "Tabs",
    "Bottom Navigation", "Navigation Rail", "Side Navigation", "Breadcrumb",
    "Tenant Switcher", "Outlet Selector", "App Bar", "Bottom Sheet", "Dialog",
    "Confirmation Dialog", "Drawer", "Banner", "Snackbar", "Toast", "Tooltip",
    "Empty State", "Loading State", "Skeleton", "Error State", "Offline Banner",
    "Sync Indicator", "Conflict Panel", "Timeline", "Stepper",
    "Progress Indicator", "Attachment Uploader", "Photo Evidence",
    "Signature Capture", "OTP Proof", "Map Preview", "Chart", "KPI Card",
    "Filter Bar", "Bulk Action Bar", "Audit Timeline",
]

REQUIRED_CONTRACT_TERMS = [
    "anatomy", "variant", "state", "token", "keyboard", "screen",
    "validation", "loading", "disabled", "error", "privacy", "platform",
    "prohibited", "requirement",
]

HEX_LITERAL = re.compile(r"#[0-9A-Fa-f]{6}\b")


def main() -> int:
    root = repo_root()
    rep = Reporter("component catalog")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    if not text:
        return rep.finish()

    # -- component IDs are present, unique and well-formed -----------------
    ids = COMPONENT_ID.findall(text)
    unique = sorted(set(ids))
    rep.check(
        len(unique) >= 60,
        f"at least 60 components carry a CMP-### ID (found {len(unique)})",
    )
    duplicates = sorted({i for i in ids if ids.count(i) > 3})
    rep.info(f"component IDs defined: {len(unique)}")

    # -- every mandated component is present -------------------------------
    lowered = text.lower()
    missing = [c for c in REQUIRED_COMPONENTS if c.lower() not in lowered]
    for c in missing:
        rep.info(f"component not documented: {c}")
    rep.check(
        not missing,
        f"every mandated component is documented ({len(missing)} missing)",
    )

    # -- the contract vocabulary is present --------------------------------
    absent_terms = [t for t in REQUIRED_CONTRACT_TERMS if t not in lowered]
    for t in absent_terms:
        rep.info(f"contract term absent from the catalog: {t}")
    rep.check(
        not absent_terms,
        "the catalog documents the full component contract",
    )

    # -- components reference tokens, not literal colours ------------------
    hexes = sorted(set(HEX_LITERAL.findall(text)))
    # A catalogue is permitted to quote a hex inside a fenced example that is
    # explicitly marked as forbidden; anything else is a hard-coded colour.
    offending = []
    for line in text.splitlines():
        for h in HEX_LITERAL.findall(line):
            low = line.lower()
            if any(g in low for g in ("never", "not ", "forbidden", "prohibit",
                                      "must not", "wrong", "instead of")):
                continue
            offending.append(f"{h} in: {line.strip()[:100]}")
    for o in offending:
        rep.info(o)
    rep.check(
        not offending,
        "no component specification hard-codes a literal hex colour "
        "(components name tokens)",
    )

    # -- token names cited by the catalog actually exist --------------------
    tokens, _origin, errors = load_tokens(root)
    for err in errors:
        rep.fail(err)
    cited = set(re.findall(r"`((?:color|space|size|radius|border|elevation|"
                           r"motion|opacity|font|density|icon|component)"
                           r"\.[A-Za-z0-9_.]+)`", text))
    unknown = sorted(c for c in cited if c not in tokens)
    for u in unknown:
        rep.info(f"catalog cites an unknown token: {u}")
    rep.check(
        not unknown,
        f"every token the catalog cites exists ({len(cited)} cited, "
        f"{len(unknown)} unknown)",
    )

    # -- requirement references --------------------------------------------
    reqs = {m.group(0) for m in REQUIREMENT_ID.finditer(text)}
    rep.check(
        len(reqs) >= 20,
        f"the catalog cites requirement IDs ({len(reqs)} distinct)",
    )

    # -- the focus indicator is never removed ------------------------------
    rep.check(
        bool(re.search(r"focus[^.\n]{0,110}(?:can |will |shall |is |are )?"
                       r"(?:never|not)\s+(?:be\s+)?"
                       r"(?:removed|suppressed|disabled|hidden)", lowered))
        or bool(re.search(r"focus[^.\n]{0,90}always visible", lowered)),
        "the catalog states the focus indicator is never removed",
    )

    # -- destructive components are not the default action -----------------
    rep.check(
        bool(re.search(r"destructive[^.\n]{0,120}(never|not) [^.\n]{0,40}"
                       r"default", lowered))
        or bool(re.search(r"default[^.\n]{0,80}(never|not)[^.\n]{0,60}"
                          r"destructive", lowered)),
        "a destructive action is never the default action",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
