#!/usr/bin/env python3
"""Validate the low-fidelity SVG wireframes.

A wireframe in a PUBLIC repository is an attack surface and a claim surface at
the same time. This validator enforces both:

  * Safety   — valid XML, no script, no remote content, no embedded binary.
  * Honesty  — every wireframe is labelled LOW-FIDELITY - NOT IMPLEMENTED and
               is never presented as a final or implemented screen.
  * Coverage — minimum counts per platform.

Standard library only.
"""

from __future__ import annotations

import re
import sys
import xml.etree.ElementTree as ET

from _common import Reporter, repo_root
from _step02 import SCREEN_ID, WIREFRAME_DIR

# The literal label every wireframe must carry. The dash is a plain hyphen or
# an em dash; both are accepted, nothing else is.
LABEL_PATTERN = re.compile(r"LOW-FIDELITY\s*[—-]\s*NOT IMPLEMENTED")

PLATFORM_PREFIXES = {
    "customer-android": 10,
    "ops-android": 10,
    "console-web": 6,
    "tracking-portal": 4,
}

# Anything that would pull bytes from off this repository, or execute.
FORBIDDEN_PATTERNS = [
    (re.compile(r"<\s*script", re.I), "contains a <script> element"),
    (re.compile(r"\bon[a-z]+\s*=", re.I), "contains an inline event handler"),
    (re.compile(r"https?://(?!www\.w3\.org)", re.I), "references a remote URL"),
    (re.compile(r"<\s*foreignObject", re.I), "contains a <foreignObject>"),
    (re.compile(r"<\s*iframe", re.I), "contains an <iframe>"),
    (re.compile(r"@import", re.I), "contains a CSS @import"),
    (re.compile(r"data:(?!image/svg\+xml;charset)", re.I),
     "embeds a data: URI (no embedded binary is permitted)"),
    (re.compile(r"<!ENTITY", re.I), "declares an XML entity (XXE surface)"),
    (re.compile(r"javascript:", re.I), "contains a javascript: URL"),
]

# Fictional-data discipline: a wireframe must never carry a real-looking
# Indonesian mobile number in full.
REAL_PHONE = re.compile(r"\b(?:\+62|62|0)8[1-9][0-9]{7,10}\b")


def main() -> int:
    root = repo_root()
    rep = Reporter("wireframes")

    wf_dir = root / WIREFRAME_DIR
    rep.check(wf_dir.is_dir(), f"{WIREFRAME_DIR}/ exists")
    if not wf_dir.is_dir():
        return rep.finish()

    svgs = sorted(wf_dir.rglob("*.svg"))
    rep.check(len(svgs) >= 30,
              f"at least 30 wireframes exist (found {len(svgs)})")

    readme = wf_dir / "README.md"
    rep.check(readme.is_file(), f"{WIREFRAME_DIR}/README.md exists")
    readme_text = readme.read_text(encoding="utf-8", errors="replace") \
        if readme.is_file() else ""
    if readme_text:
        rep.check(
            "NOT IMPLEMENTED" in readme_text,
            f"{WIREFRAME_DIR}/README.md states the wireframes are NOT IMPLEMENTED",
        )

    # -- per-platform coverage ---------------------------------------------
    counts = {p: 0 for p in PLATFORM_PREFIXES}
    for path in svgs:
        for prefix in PLATFORM_PREFIXES:
            if path.name.startswith(prefix):
                counts[prefix] += 1
                break
    for prefix, minimum in sorted(PLATFORM_PREFIXES.items()):
        rep.check(
            counts[prefix] >= minimum,
            f"at least {minimum} '{prefix}' wireframes "
            f"(found {counts[prefix]})",
        )

    # -- per-file structure, safety and honesty ----------------------------
    invalid_xml = no_viewbox = unlabelled = no_screen_id = 0
    unsafe: list = []
    phone_leaks: list = []
    referenced_ids: set = set()

    for path in svgs:
        rel = path.relative_to(root).as_posix()
        raw = path.read_text(encoding="utf-8", errors="replace")

        try:
            tree = ET.fromstring(raw)
        except ET.ParseError as exc:
            invalid_xml += 1
            rep.info(f"{rel}: not valid XML ({exc})")
            continue

        if "viewBox" not in tree.attrib:
            no_viewbox += 1
            rep.info(f"{rel}: no viewBox attribute")

        if not LABEL_PATTERN.search(raw):
            unlabelled += 1
            rep.info(f"{rel}: missing the 'LOW-FIDELITY - NOT IMPLEMENTED' label")

        found_ids = {m.group(0) for m in SCREEN_ID.finditer(raw)}
        if not found_ids:
            no_screen_id += 1
            rep.info(f"{rel}: carries no screen ID")
        referenced_ids |= found_ids

        for pattern, why in FORBIDDEN_PATTERNS:
            if pattern.search(raw):
                unsafe.append(f"{rel}: {why}")

        for match in REAL_PHONE.finditer(raw):
            phone_leaks.append(f"{rel}: possible real phone number "
                               f"'{match.group(0)}'")

    rep.check(invalid_xml == 0,
              f"every wireframe is valid XML ({invalid_xml} invalid)")
    rep.check(no_viewbox == 0,
              f"every wireframe declares a viewBox ({no_viewbox} missing)")
    rep.check(
        unlabelled == 0,
        f"every wireframe carries the LOW-FIDELITY - NOT IMPLEMENTED label "
        f"({unlabelled} missing)",
    )
    rep.check(no_screen_id == 0,
              f"every wireframe carries a screen ID ({no_screen_id} missing)")

    for msg in unsafe:
        rep.info(msg)
    rep.check(
        not unsafe,
        "no wireframe contains a script, an event handler, a remote reference, "
        "an entity declaration or an embedded binary",
    )

    for msg in phone_leaks:
        rep.info(msg)
    rep.check(not phone_leaks,
              "no wireframe contains a real-looking phone number")

    # -- every wireframe screen ID is a screen that actually exists --------
    inventory = root / "docs" / "ux" / "SCREEN_INVENTORY.md"
    if inventory.is_file():
        inv_text = inventory.read_text(encoding="utf-8", errors="replace")
        inv_ids = {m.group(0) for m in SCREEN_ID.finditer(inv_text)}
        orphans = sorted(referenced_ids - inv_ids)
        for o in orphans:
            rep.info(f"wireframe references {o}, which is not in SCREEN_INVENTORY.md")
        rep.check(
            not orphans,
            "every wireframe screen ID appears in SCREEN_INVENTORY.md "
            "(no orphan wireframes)",
        )
        rep.info(f"wireframes cover {len(referenced_ids & inv_ids)} inventoried screens")
    else:
        rep.fail("docs/ux/SCREEN_INVENTORY.md exists (needed to detect orphan wireframes)")

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
