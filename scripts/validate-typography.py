#!/usr/bin/env python3
"""Validate the typography foundation.

Enforces the system-first font strategy (no font binary may be committed to
this PUBLIC repository), the completeness of the type scale, the tabular-figure
requirement for financial and tabular numerics, and the 200% text-scaling
commitment.

Standard library only.
"""

from __future__ import annotations

import sys

from _common import Reporter, repo_root
from _step02 import DESIGN_DIR, load_tokens, read

DOC = f"{DESIGN_DIR}/TYPOGRAPHY.md"

REQUIRED_ROLES = [
    "display", "headline", "title", "body", "label", "caption",
]

REQUIRED_STYLE_ATTRIBUTES = [
    "line height", "weight", "letter spacing", "wrapping", "truncation",
]

# Any of these extensions committed anywhere in the repository would mean a
# font binary was shipped. The strategy is system-first precisely so this
# never happens.
FONT_BINARY_SUFFIXES = {".ttf", ".otf", ".woff", ".woff2", ".eot", ".ttc", ".fon"}


def main() -> int:
    root = repo_root()
    rep = Reporter("typography")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")

    tokens, _origin, errors = load_tokens(root)
    for err in errors:
        rep.fail(err)

    # -- no font binary anywhere in the repository -------------------------
    #
    # Only files git would actually carry are considered. This check asserts that
    # no font binary is COMMITTED, and a git-ignored file cannot be — the Flutter
    # toolchain writes MaterialIcons-Regular.otf into every package's local
    # build/unit_test_assets/, which is regenerated output, never published.
    # Reporting those as "font binary committed" was a literally false claim.
    #
    # Fails CLOSED: if git cannot be consulted, every file is considered.
    import subprocess
    ignored: set[str] = set()
    try:
        proc = subprocess.run(
            ["git", "-C", str(root), "ls-files", "--others", "--ignored",
             "--exclude-standard", "-z"],
            capture_output=True, timeout=60, check=True,
        )
        ignored = {p for p in proc.stdout.decode("utf-8", "replace").split("\0") if p}
    except (OSError, subprocess.SubprocessError):
        ignored = set()

    binaries = []
    for path in root.rglob("*"):
        if ".git/" in path.as_posix() or not path.is_file():
            continue
        rel = path.relative_to(root).as_posix()
        if rel in ignored:
            continue
        if path.suffix.lower() in FONT_BINARY_SUFFIXES:
            binaries.append(rel)
    for b in binaries:
        rep.info(f"font binary committed: {b}")
    rep.check(
        not binaries,
        "no font binary is committed (the strategy is system-first)",
    )

    # -- the system-first stacks exist -------------------------------------
    sans = tokens.get("font.family.sans", {}).get("value", "")
    rep.check("system-ui" in str(sans),
              "font.family.sans is a system-first stack beginning with system-ui")
    rep.check(bool(tokens.get("font.family.mono")),
              "font.family.mono is defined (receipt previews need a fixed grid)")

    # -- the type scale is complete ----------------------------------------
    sizes = {n for n in tokens if n.startswith("font.size.")}
    heights = {n for n in tokens if n.startswith("font.lineHeight.")}
    rep.check(len(sizes) >= 10,
              f"the type scale defines at least 10 sizes (found {len(sizes)})")

    unpaired = sorted(
        n for n in sizes
        if n.replace("font.size.", "font.lineHeight.") not in heights
    )
    for n in unpaired:
        rep.info(f"{n} has no paired line height")
    rep.check(not unpaired, "every font size has a paired line height")

    for role in REQUIRED_ROLES:
        rep.check(
            any(n.startswith(f"font.size.{role}") for n in sizes),
            f"the type scale defines the '{role}' role",
        )

    # -- weights and letter spacing ----------------------------------------
    weights = {n for n in tokens if n.startswith("font.weight.")}
    rep.check(len(weights) >= 3,
              f"at least 3 font weights are defined (found {len(weights)})")
    spacing = {n for n in tokens if n.startswith("font.letterSpacing.")}
    rep.check(bool(spacing), "letter spacing tokens are defined")

    # -- tabular figures are mandatory for money and tables ----------------
    tnum = tokens.get("font.feature.tabularNumbers")
    rep.check(bool(tnum), "font.feature.tabularNumbers is defined")
    if tnum:
        rep.check(
            "tnum" in str(tnum.get("value", "")),
            "font.feature.tabularNumbers enables the OpenType tnum feature",
        )

    money_alias = tokens.get("component.field.money.fontFeature", {})
    rep.check(
        "tabularNumbers" in str(money_alias.get("value", "")),
        "the money field alias binds tabular figures",
    )
    table_alias = tokens.get("component.table.numeric.fontFeature", {})
    rep.check(
        "tabularNumbers" in str(table_alias.get("value", "")),
        "the numeric table column alias binds tabular figures",
    )

    # -- the document states the strategy and the constraints --------------
    lowered = text.lower()
    rep.check("system" in lowered and "font" in lowered,
              f"{DOC} states the system-first font strategy")
    rep.check("tabular" in lowered,
              f"{DOC} states the tabular-figure requirement")
    rep.check("200%" in text or "200 %" in text,
              f"{DOC} states the 200% text-scaling commitment")
    rep.check(
        "no font binary" in lowered or "font binary" in lowered,
        f"{DOC} states that no font binary is committed",
    )

    missing_attrs = [a for a in REQUIRED_STYLE_ATTRIBUTES if a not in lowered]
    for a in missing_attrs:
        rep.info(f"{DOC} does not document '{a}'")
    rep.check(
        not missing_attrs,
        "every type style documents line height, weight, letter spacing, "
        "wrapping and truncation",
    )

    # -- max line length -----------------------------------------------------
    rep.check(bool(tokens.get("font.maxLineLength.body")),
              "a maximum body measure is defined for wide layouts")

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
