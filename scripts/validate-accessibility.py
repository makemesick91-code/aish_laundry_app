#!/usr/bin/env python3
"""Validate the accessibility foundation.

Step 2 states an accessibility target and designs to it. It has not tested
anything, and this validator enforces that distinction as strictly as it
enforces the coverage: an overstated claim fails just as hard as a missing
topic.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import (
    strip_emphasis,
    A11Y_CAVEAT,
    A11Y_CLAIM,
    DESIGN_DIR,
    MIN_TOUCH_TARGET_DP,
    markdown_files,
    read,
)

DOC = f"{DESIGN_DIR}/ACCESSIBILITY.md"

REQUIRED_TOPICS = {
    "contrast": ["contrast"],
    "focus": ["focus"],
    "keyboard navigation": ["keyboard"],
    "screen reader": ["screen reader", "screen-reader"],
    "reading order": ["reading order"],
    "headings": ["heading"],
    "form labels": ["form label", "field label", "label"],
    "error association": ["error association", "associated error",
                          "error is associated", "programmatically associated",
                          "aria-describedby"],
    "status announcements": ["announce", "announcement", "live region"],
    "touch targets": ["touch target"],
    "text scaling": ["text scaling", "font scaling", "200%"],
    "reduced motion": ["reduced motion", "reduced-motion"],
    "colour independence": ["colour alone", "color alone",
                            "colour independence", "color independence"],
    "chart alternatives": ["chart", "text alternative"],
    "table navigation": ["table"],
    "timeout warning": ["timeout", "time-out", "session expiry"],
    "OTP accessibility": ["otp"],
    "modal focus": ["focus trap", "modal focus", "focus is trapped"],
    "escape behaviour": ["escape"],
    "bottom sheet behaviour": ["bottom sheet"],
    "landscape": ["landscape", "orientation"],
}

# Claims Step 2 is not entitled to make about accessibility.
OVERSTATED = [
    "wcag certified", "wcag compliant", "fully accessible",
    "accessibility verified", "accessibility tested", "audit passed",
    "passes wcag", "wcag aa compliant",
]


def main() -> int:
    root = repo_root()
    rep = Reporter("accessibility foundation")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    if not text:
        return rep.finish()
    lowered = text.lower()

    # -- the claim is worded exactly as mandated ---------------------------
    rep.check(A11Y_CLAIM in text,
              f"the exact target wording '{A11Y_CLAIM}' is present")
    rep.check(A11Y_CAVEAT in text,
              f"the exact caveat '{A11Y_CAVEAT}' is present")
    rep.check("2.2" in text, "the target names WCAG 2.2 specifically")
    rep.check("AA" in text, "the target names conformance level AA")

    # -- and nothing stronger is claimed anywhere --------------------------
    overstated: list = []
    for path in markdown_files(root, "docs/design", "docs/ux", "docs/quality",
                               ".claude/rules"):
        body = path.read_text(encoding="utf-8", errors="replace")
        rel = path.relative_to(root).as_posix()
        for line in body.splitlines():
            low = strip_emphasis(line).lower()
            for claim in OVERSTATED:
                if claim not in low:
                    continue
                guard = ("never", "not ", "no ", "forbidden", "prohibit",
                         "must not", "false claim", "is wrong", "cannot",
                         "fabricat", "placeholder", "remove")
                if not any(g in low for g in guard):
                    overstated.append(f"{rel}: {line.strip()[:110]}")
    for hit in overstated:
        rep.info(hit)
    rep.check(
        not overstated,
        "no document claims accessibility conformance was verified or tested",
    )

    # -- topic coverage -----------------------------------------------------
    missing = []
    for topic, needles in sorted(REQUIRED_TOPICS.items()):
        if not any(n in lowered for n in needles):
            missing.append(topic)
        else:
            rep.ok(f"{DOC} covers '{topic}'")
    for topic in missing:
        rep.fail(f"{DOC} covers '{topic}'")
    rep.check(not missing,
              f"every mandated accessibility topic is covered "
              f"({len(missing)} missing)")

    # -- the non-negotiables are stated as non-negotiable ------------------
    rep.check(
        str(MIN_TOUCH_TARGET_DP) in text,
        f"the {MIN_TOUCH_TARGET_DP}x{MIN_TOUCH_TARGET_DP} touch-target floor "
        f"is stated",
    )
    rep.check("4.5" in text, "the 4.5:1 normal-text contrast target is stated")
    rep.check("3:1" in text or "3.0" in text,
              "the 3:1 large-text and boundary target is stated")

    focus_removal = re.search(
        r"focus[^.\n]{0,90}(?:can |will |shall |is |are )?"
        r"(?:never|not)\s+(?:be\s+)?(?:removed|suppressed|disabled|hidden)",
        lowered)
    rep.check(bool(focus_removal),
              "the document states that the focus indicator is never removed")

    colour_alone = re.search(
        r"(colou?r alone|not by colou?r|never by colou?r)", lowered)
    rep.check(bool(colour_alone),
              "the document states that status is never conveyed by colour alone")

    # -- every critical surface carries accessibility notes ----------------
    surfaces = [
        "docs/ux/CUSTOMER_ANDROID_UX.md",
        "docs/ux/OPS_ANDROID_UX.md",
        "docs/ux/COURIER_UX.md",
        "docs/ux/CONSOLE_WEB_UX.md",
        "docs/ux/TRACKING_PORTAL_UX.md",
    ]
    for rel in surfaces:
        body = read(root, rel)
        if not body:
            rep.fail(f"{rel} exists")
            continue
        rep.check(
            "accessib" in body.lower(),
            f"{rel} carries accessibility notes",
        )

    # -- the component catalogue promises an accessibility contract --------
    catalog = read(root, f"{DESIGN_DIR}/COMPONENT_CATALOG.md")
    if catalog:
        low = catalog.lower()
        rep.check(
            any(v in low for v in ("screen-reader contract",
                                   "screen reader contract",
                                   "**screen reader:**", "screen reader:")),
            "COMPONENT_CATALOG.md defines a screen-reader contract",
        )
        rep.check(
            any(v in low for v in ("keyboard contract", "**keyboard:**",
                                   "keyboard:")),
            "COMPONENT_CATALOG.md defines a keyboard contract",
        )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
