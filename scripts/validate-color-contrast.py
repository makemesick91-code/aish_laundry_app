#!/usr/bin/env python3
"""Recompute every colour contrast ratio from the hex values.

The token files record a `measuredContrast` block. This validator recomputes
those numbers using the WCAG 2.2 relative-luminance formula and fails if a
recorded figure disagrees with the computed one, or if a token misses the
contrast target it declares.

A hand-edited contrast figure is falsified evidence under Rule 01, not a typo.
Standard library only.
"""

from __future__ import annotations

import glob
import re
import sys
from pathlib import Path

from _common import Reporter, repo_root
from _step02 import (
    CONTRAST_MINIMUM,
    EXEMPT_TARGETS,
    load_tokens,
    contrast_ratio,
    resolve_token,
)

# The canonical light-theme backgrounds a foreground colour is measured against.
BACKGROUNDS = {
    "onNeutral0": "#FFFFFF",
    "onNeutral50": "#F7F8FA",
    "onSurfacePage": "#FFFFFF",
    "onSurfaceRaised": "#F7F8FA",
}

TOLERANCE = 0.01


def main() -> int:
    root = repo_root()
    rep = Reporter("colour contrast")

    tokens, _origin, errors = load_tokens(root)
    for err in errors:
        rep.fail(err)
    if not tokens:
        rep.fail("no tokens could be loaded")
        return rep.finish()

    colours = {
        name: body for name, body in tokens.items()
        if body.get("type") == "color"
    }
    rep.info(f"recomputing contrast for {len(colours)} colour tokens")

    # -- every colour resolves to a real hex value -------------------------
    resolved: dict = {}
    unresolvable = 0
    for name in sorted(colours):
        value, err = resolve_token(name, tokens)
        if err or not isinstance(value, str) or not value.startswith("#"):
            unresolvable += 1
            rep.info(f"{name}: does not resolve to a hex colour ({err or value})")
            continue
        resolved[name] = value
    rep.check(unresolvable == 0,
              "every colour token resolves to a literal hex value")

    # -- recorded figures must match computed figures ----------------------
    mismatches = 0
    checked = 0
    for name, hex_value in sorted(resolved.items()):
        recorded = colours[name].get("measuredContrast")
        if not isinstance(recorded, dict):
            continue
        for key, claimed in recorded.items():
            if key in BACKGROUNDS:
                actual = contrast_ratio(hex_value, BACKGROUNDS[key])
            elif key == "whiteOnThis":
                actual = contrast_ratio("#FFFFFF", hex_value)
            elif key.startswith("on"):
                # Measured against another semantic token, e.g. onPrimary.
                target = "color.semantic." + key[2].lower() + key[3:]
                if target not in resolved:
                    continue
                actual = contrast_ratio(hex_value, resolved[target])
            else:
                continue
            checked += 1
            if abs(float(claimed) - actual) > TOLERANCE:
                mismatches += 1
                rep.info(
                    f"{name}.{key}: recorded {claimed}, computed {actual}")
    rep.check(
        mismatches == 0,
        f"every recorded contrast figure matches its computed value "
        f"({checked} figures checked, {mismatches} mismatched)",
    )

    # -- every token meets the target it declares --------------------------
    missed = 0
    enforced = 0
    for name, hex_value in sorted(resolved.items()):
        target = colours[name].get("contrastTarget")
        if target in EXEMPT_TARGETS or target is None:
            continue
        minimum = CONTRAST_MINIMUM.get(target)
        if minimum is None:
            missed += 1
            rep.info(f"{name}: unknown contrastTarget {target!r}")
            continue
        enforced += 1

        allowed = colours[name].get("allowedBackground") or []
        candidates = []
        for bg in allowed:
            if bg in resolved:
                candidates.append((bg, resolved[bg]))
        if not candidates:
            candidates = [("color.neutral.0", "#FFFFFF")]

        worst_name, worst = None, None
        for bg_name, bg_hex in candidates:
            ratio = contrast_ratio(hex_value, bg_hex)
            if worst is None or ratio < worst:
                worst, worst_name = ratio, bg_name
        if worst < minimum:
            missed += 1
            rep.info(
                f"{name} ({hex_value}) declares {target} but measures "
                f"{worst}:1 against {worst_name} (needs {minimum}:1)"
            )
    rep.check(
        missed == 0,
        f"every colour token meets its declared contrast target "
        f"({enforced} enforced, {missed} missed)",
    )

    # -- the focus ring specifically ---------------------------------------
    focus = resolved.get("color.semantic.focus")
    if focus:
        ratio = contrast_ratio(focus, "#FFFFFF")
        rep.check(
            ratio >= 3.0,
            f"the focus ring meets 3:1 against the page surface "
            f"(measured {ratio}:1)",
        )
    else:
        rep.fail("color.semantic.focus is defined")

    # -- gold may not carry text unless it clears 4.5:1 --------------------
    gold_text_violations = 0
    for name, hex_value in sorted(resolved.items()):
        if ".gold." not in name and "accent" not in name:
            continue
        if colours[name].get("contrastTarget") != "normal-text-4.5":
            continue
        ratio = contrast_ratio(hex_value, "#FFFFFF")
        if ratio < 4.5:
            gold_text_violations += 1
            rep.info(f"{name} claims text use but measures only {ratio}:1")
    rep.check(
        gold_text_violations == 0,
        "no gold token claims text use without clearing 4.5:1",
    )

    # -- no design document may cite a colour the tokens do not define ----
    # This is the drift gate. A hand-written hex in a design document is a
    # second source of truth for a colour, and the moment the token changes the
    # document starts lying about a contrast ratio. Every hex in the Step 2
    # corpus must therefore BE a token value, and every token name cited must
    # resolve.
    token_hexes = {str(b.get("value", "")).upper()
                   for b in tokens.values()
                   if str(b.get("value", "")).startswith("#")}
    hex_pattern = re.compile(r"#[0-9A-Fa-f]{6}")
    name_pattern = re.compile(
        r"`((?:color|space|size|radius|border|elevation|motion|opacity|font|"
        r"density|icon|component)\.[A-Za-z0-9_.]+)`")

    foreign_hex: list = []
    unknown_names: list = []
    scanned = 0
    for path_str in sorted(glob.glob(str(root / "docs/design/*.md"))
                           + glob.glob(str(root / "docs/ux/*.md"))):
        path = Path(path_str)
        rel = path.relative_to(root).as_posix()
        body = path.read_text(encoding="utf-8", errors="replace")
        scanned += 1
        for line in body.splitlines():
            low = line.lower()
            guarded = any(g in low for g in
                          ("never", "not ", "no ", "forbidden", "prohibit",
                           "must not", "instead of", "wrong"))
            for h in hex_pattern.findall(line):
                if h.upper() not in token_hexes and not guarded:
                    foreign_hex.append(f"{rel}: {h} — not a token value")
        for name in set(name_pattern.findall(body)):
            if name not in tokens:
                unknown_names.append(f"{rel}: `{name}` does not exist")

    for msg in foreign_hex[:20]:
        rep.info(msg)
    rep.check(
        not foreign_hex,
        f"no design document cites a colour the tokens do not define "
        f"({scanned} documents scanned, {len(foreign_hex)} foreign hexes)",
    )
    for msg in unknown_names[:20]:
        rep.info(msg)
    rep.check(
        not unknown_names,
        f"every token name cited in the Step 2 corpus resolves "
        f"({len(unknown_names)} unresolved)",
    )

    # -- decorative gold must never claim a meaning-bearing target ---------
    decorative_gold = [
        n for n in sorted(resolved)
        if ".gold." in n
        and colours[n].get("contrastTarget") == "decorative-exempt"
    ]
    rep.check(
        bool(decorative_gold),
        "the decorative gold accents are explicitly marked decorative-exempt",
    )
    rep.info(f"decorative gold tokens: {', '.join(decorative_gold)}")

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
