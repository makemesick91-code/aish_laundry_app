#!/usr/bin/env python3
"""Validate design token references.

Every {reference} must resolve to a token that exists. No reference chain may
be circular. Colour aliases must route through the semantic layer rather than
naming a primitive colour or a literal hex value, so that a colour's meaning
has exactly one place it can be changed.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import TOKEN_REF, load_tokens, resolve_token

HEX_LITERAL = re.compile(r"^#[0-9A-Fa-f]{6}$")


def main() -> int:
    root = repo_root()
    rep = Reporter("design token references")

    tokens, origin, errors = load_tokens(root)
    for err in errors:
        rep.fail(err)
    if not tokens:
        rep.fail("no tokens could be loaded")
        return rep.finish()

    rep.info(f"resolving {len(tokens)} tokens")

    # -- every reference resolves, and no chain is circular ----------------
    unresolved: list = []
    circular: list = []
    for name in sorted(tokens):
        value, err = resolve_token(name, tokens)
        if err:
            (circular if "circular" in err else unresolved).append(
                f"{name}: {err}")

    for msg in unresolved:
        rep.info(msg)
    rep.check(not unresolved,
              f"every token reference resolves ({len(unresolved)} unresolved)")

    for msg in circular:
        rep.info(msg)
    rep.check(not circular,
              f"no circular token references ({len(circular)} circular)")

    # -- layering: a colour alias must reference a semantic colour ---------
    layer_violations: list = []
    literal_colours: list = []
    for name, body in sorted(tokens.items()):
        if not name.startswith("component."):
            continue
        value = str(body.get("value", ""))
        if body.get("type") != "color":
            continue
        if HEX_LITERAL.match(value):
            literal_colours.append(f"{name} = {value}")
            continue
        match = TOKEN_REF.match(value)
        if not match:
            layer_violations.append(f"{name} = {value!r} (not a reference)")
            continue
        target = match.group(1)
        if not target.startswith(("color.semantic.", "opacity.")):
            layer_violations.append(
                f"{name} -> {target} (must reference color.semantic.*)")

    for msg in literal_colours:
        rep.info(msg)
    rep.check(
        not literal_colours,
        "no component alias hard-codes a literal hex colour",
    )
    for msg in layer_violations:
        rep.info(msg)
    rep.check(
        not layer_violations,
        "every colour alias references the semantic layer, not a primitive",
    )

    # -- a semantic colour must reference a primitive, never a literal -----
    semantic_literals: list = []
    for name, body in sorted(tokens.items()):
        if not name.startswith("color.semantic."):
            continue
        value = str(body.get("value", ""))
        if HEX_LITERAL.match(value):
            semantic_literals.append(f"{name} = {value}")
        elif not TOKEN_REF.match(value):
            semantic_literals.append(f"{name} = {value!r} (not a reference)")
        else:
            target = TOKEN_REF.match(value).group(1)
            if not target.startswith("color."):
                semantic_literals.append(f"{name} -> {target}")
    for msg in semantic_literals:
        rep.info(msg)
    rep.check(
        not semantic_literals,
        "every semantic colour references a primitive colour token",
    )

    # -- a primitive must be a literal, never a reference ------------------
    primitive_refs: list = []
    for name, body in sorted(tokens.items()):
        if origin.get(name, "").endswith("primitives.json"):
            if TOKEN_REF.match(str(body.get("value", ""))):
                primitive_refs.append(name)
    for msg in primitive_refs:
        rep.info(msg)
    rep.check(not primitive_refs,
              "every primitive token holds a literal value, not a reference")

    # -- no orphan semantic tokens ----------------------------------------
    # A semantic colour nobody consumes is either dead weight or a sign that a
    # component specification forgot to name it. Surface it rather than hide it.
    referenced: set = set()
    for body in tokens.values():
        match = TOKEN_REF.match(str(body.get("value", "")))
        if match:
            referenced.add(match.group(1))

    doc_text = ""
    for md in sorted((root / "docs" / "design").rglob("*.md")):
        doc_text += md.read_text(encoding="utf-8", errors="replace")
    for md in sorted((root / "docs" / "ux").rglob("*.md")):
        doc_text += md.read_text(encoding="utf-8", errors="replace")

    orphans = [
        n for n in sorted(tokens)
        if n.startswith("color.semantic.")
        and n not in referenced
        and n not in doc_text
    ]
    for name in orphans:
        rep.info(f"semantic token with no consumer: {name}")
    rep.check(
        not orphans,
        "every semantic colour token has a consumer (an alias or a specification)",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
