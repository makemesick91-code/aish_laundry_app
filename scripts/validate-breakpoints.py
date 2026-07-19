#!/usr/bin/env python3
"""Validate the responsive and device foundation.

Enforces the semantic breakpoint set, the two hard width guarantees (the
Public Tracking Portal at 320px and Console Web at 1366x768), the 4px grid,
the 48x48 touch-target floor, and the rule that compact density is never
applied to a touch surface.

Standard library only.
"""

from __future__ import annotations

import sys

from _common import Reporter, repo_root
from _step02 import DESIGN_DIR, GRID_BASE_PX, MIN_TOUCH_TARGET_DP, load_tokens, read

RESPONSIVE_DOC = f"{DESIGN_DIR}/RESPONSIVE_FOUNDATION.md"
SPACING_DOC = f"{DESIGN_DIR}/SPACING_SIZING_DENSITY.md"
PLATFORM_DOC = f"{DESIGN_DIR}/PLATFORM_ADAPTATION.md"

EXPECTED_BREAKPOINTS = {
    "compact": (0, 599),
    "medium": (600, 1023),
    "expanded": (1024, 1439),
    "wide": (1440, None),
}

REQUIRED_DENSITIES = ["compact", "standard", "comfortable"]


def main() -> int:
    root = repo_root()
    rep = Reporter("responsive and device foundation")

    tokens, _origin, errors = load_tokens(root)
    for err in errors:
        rep.fail(err)

    responsive = read(root, RESPONSIVE_DOC)
    spacing = read(root, SPACING_DOC)
    platform = read(root, PLATFORM_DOC)
    rep.check(bool(responsive), f"{RESPONSIVE_DOC} exists")
    rep.check(bool(spacing), f"{SPACING_DOC} exists")
    rep.check(bool(platform), f"{PLATFORM_DOC} exists")

    # -- the four semantic breakpoints, with the exact boundaries ----------
    for name, (lo, hi) in sorted(EXPECTED_BREAKPOINTS.items()):
        got_lo = tokens.get(f"breakpoint.{name}.min", {}).get("value")
        rep.check(
            got_lo == lo,
            f"breakpoint.{name}.min is {lo} (found {got_lo})",
        )
        if hi is not None:
            got_hi = tokens.get(f"breakpoint.{name}.max", {}).get("value")
            rep.check(
                got_hi == hi,
                f"breakpoint.{name}.max is {hi} (found {got_hi})",
            )

    # -- the two hard width guarantees -------------------------------------
    min_width = tokens.get("breakpoint.minSupportedWidth", {}).get("value")
    rep.check(
        min_width == 320,
        f"breakpoint.minSupportedWidth is 320 (found {min_width}) — the "
        f"Public Tracking Portal must be usable on an old handset",
    )
    console_width = tokens.get("breakpoint.consoleReferenceWidth", {}).get("value")
    rep.check(
        console_width == 1366,
        f"breakpoint.consoleReferenceWidth is 1366 (found {console_width})",
    )

    rep.check(
        "320" in responsive,
        f"{RESPONSIVE_DOC} states the 320px Tracking Portal guarantee",
    )
    rep.check(
        "1366" in responsive,
        f"{RESPONSIVE_DOC} states the 1366x768 Console Web guarantee",
    )
    rep.check(
        "horizontal scroll" in responsive.lower(),
        f"{RESPONSIVE_DOC} states the no-horizontal-scrolling rule for "
        f"primary Console Web workflows",
    )

    # -- the 4px grid -------------------------------------------------------
    base = tokens.get("space.grid.base", {}).get("value")
    rep.check(base == GRID_BASE_PX,
              f"space.grid.base is {GRID_BASE_PX} (found {base})")

    off_grid = []
    for name, body in sorted(tokens.items()):
        if not name.startswith("space.") or name == "space.grid.base":
            continue
        value = body.get("value")
        if isinstance(value, (int, float)) and value % GRID_BASE_PX != 0:
            off_grid.append(f"{name} = {value}")
    for msg in off_grid:
        rep.info(msg)
    rep.check(not off_grid,
              f"every spacing token is a multiple of {GRID_BASE_PX}px")

    # -- the touch-target floor --------------------------------------------
    touch = tokens.get("size.touch.min", {})
    rep.check(
        touch.get("value") == MIN_TOUCH_TARGET_DP,
        f"size.touch.min is {MIN_TOUCH_TARGET_DP} "
        f"(found {touch.get('value')})",
    )
    rep.check(
        bool(tokens.get("size.touch.spacing.min")),
        "a minimum gap between adjacent touch targets is defined",
    )

    # Any control offered as a primary touch control must clear the floor.
    touch_capable = [
        name for name, body in tokens.items()
        if name.startswith("size.control.")
        and isinstance(body.get("value"), (int, float))
        and body["value"] >= MIN_TOUCH_TARGET_DP
    ]
    rep.check(
        len(touch_capable) >= 2,
        f"at least two control heights satisfy the {MIN_TOUCH_TARGET_DP}dp "
        f"touch floor (found {len(touch_capable)}: "
        f"{', '.join(sorted(touch_capable))})",
    )

    # -- densities ----------------------------------------------------------
    for d in REQUIRED_DENSITIES:
        rep.check(
            bool(tokens.get(f"density.{d}.rowHeight")),
            f"density.{d}.rowHeight is defined",
        )

    restriction = tokens.get("density.compact.touchRestriction", {})
    rep.check(
        str(restriction.get("value")) == "pointer-only",
        "compact density is explicitly restricted to pointer-only surfaces",
    )
    prohibited = " ".join(restriction.get("prohibitedUsage", []))
    rep.check(
        "android" in prohibited.lower(),
        "compact density is explicitly prohibited on the Android surfaces",
    )

    compact_row = tokens.get("density.compact.rowHeight", {}).get("value", 0)
    standard_row = tokens.get("density.standard.rowHeight", {}).get("value", 0)
    rep.check(
        standard_row >= MIN_TOUCH_TARGET_DP,
        f"standard density row height ({standard_row}dp) satisfies the "
        f"{MIN_TOUCH_TARGET_DP}dp touch floor",
    )
    if compact_row and compact_row < MIN_TOUCH_TARGET_DP:
        rep.ok(
            f"compact density row height ({compact_row}dp) is below the touch "
            f"floor, which is exactly why it is pointer-only"
        )

    # -- Android density buckets -------------------------------------------
    dpi = tokens.get("density.android.dpiRange", {})
    rep.check(bool(dpi), "the Android density buckets are documented")
    if dpi:
        value = str(dpi.get("value", ""))
        for bucket in ("mdpi", "hdpi", "xhdpi", "xxhdpi", "xxxhdpi"):
            rep.check(bucket in value,
                      f"Android density bucket '{bucket}' is documented")

    # -- platform adaptation states the two anti-patterns ------------------
    lowered = platform.lower()
    rep.check(
        "mobile" in lowered and "desktop" in lowered,
        f"{PLATFORM_DOC} addresses both mobile and desktop adaptation",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
