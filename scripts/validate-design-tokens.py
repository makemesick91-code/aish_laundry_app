#!/usr/bin/env python3
"""Validate the Aish Laundry App design token files.

Checks structure, uniqueness, required metadata, naming convention, and the
Step 2 honesty constraints (light theme is canonical; dark mode is
NOT IMPLEMENTED and no dark token file may exist).

Standard library only.
"""

from __future__ import annotations

import json
import re
import sys

from _common import Reporter, repo_root
from _step02 import (
    REQUIRED_SEMANTIC_COLORS,
    TOKENS_DIR,
    load_tokens,
    token_files,
)

REQUIRED_FILES = [
    "README.md",
    "token-schema.json",
    "primitives.json",
    "semantic-light.json",
    "typography.json",
    "spacing.json",
    "sizing.json",
    "radius.json",
    "border.json",
    "elevation.json",
    "motion.json",
    "opacity.json",
    "breakpoints.json",
    "density.json",
    "iconography.json",
    "component-aliases.json",
]

VALID_TYPES = {
    "color", "dimension", "fontFamily", "fontSize", "fontWeight", "lineHeight",
    "letterSpacing", "duration", "cubicBezier", "number", "opacity", "shadow",
    "border", "string",
}

VALID_UNITS = {"px", "dp", "sp", "ms", "percent", "ratio", "unitless", "none"}

VALID_SCOPES = {
    "global", "customer-android", "ops-android", "console-web",
    "tracking-portal", "print-receipt",
}

VALID_LAYERS = {"primitive", "semantic", "alias"}

NAME_PATTERN = re.compile(r"^[a-z][a-zA-Z0-9]*(\.[a-zA-Z0-9]+)+$")

RGB_PATTERN = re.compile(r"^rgb\(\d{1,3}, \d{1,3}, \d{1,3}\)$")


def main() -> int:
    root = repo_root()
    rep = Reporter("design tokens")

    # -- required files -----------------------------------------------------
    for name in REQUIRED_FILES:
        rep.check((root / TOKENS_DIR / name).is_file(),
                  f"{TOKENS_DIR}/{name} exists")

    # -- no dark theme token file may exist --------------------------------
    dark = sorted(p.name for p in (root / TOKENS_DIR).glob("*dark*")) \
        if (root / TOKENS_DIR).is_dir() else []
    rep.check(
        not dark,
        "no dark-theme token file exists (dark mode is PLANNED / NOT IMPLEMENTED)",
    )
    if dark:
        rep.info(f"found: {', '.join(dark)}")

    # -- schema file is itself valid JSON ----------------------------------
    schema_path = root / TOKENS_DIR / "token-schema.json"
    if schema_path.is_file():
        try:
            json.loads(schema_path.read_text(encoding="utf-8"))
            rep.ok("token-schema.json is valid JSON")
        except json.JSONDecodeError as exc:
            rep.fail(f"token-schema.json is not valid JSON: {exc}")

    # -- every token file parses and carries $meta -------------------------
    files = token_files(root)
    rep.check(len(files) >= 14,
              f"at least 14 token files present (found {len(files)})")

    for path in files:
        rel = path.relative_to(root).as_posix()
        try:
            doc = json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError as exc:
            rep.fail(f"{rel} is valid JSON ({exc})")
            continue
        rep.ok(f"{rel} is valid JSON")

        meta = doc.get("$meta")
        if not isinstance(meta, dict):
            rep.fail(f"{rel} has a $meta block")
            continue
        rep.check(meta.get("layer") in VALID_LAYERS,
                  f"{rel} $meta.layer is one of {sorted(VALID_LAYERS)}")
        rep.check(meta.get("status") == "NOT IMPLEMENTED",
                  f"{rel} $meta.status is NOT IMPLEMENTED")
        rep.check(meta.get("theme") in {"light", "none"},
                  f"{rel} $meta.theme is 'light' or 'none' (no dark theme)")
        rep.check(isinstance(meta.get("description"), str)
                  and len(meta.get("description", "")) >= 10,
                  f"{rel} $meta.description is present and substantive")
        rep.check(isinstance(doc.get("tokens"), dict) and doc["tokens"],
                  f"{rel} has a non-empty tokens object")

    # -- load everything, catching duplicates ------------------------------
    tokens, origin, errors = load_tokens(root)
    for err in errors:
        rep.fail(err)
    rep.check(not errors, "no duplicate token names across all token files")
    rep.info(f"total tokens loaded: {len(tokens)}")

    # -- per-token structural requirements ---------------------------------
    bad_name = bad_type = bad_unit = bad_desc = bad_scope = 0
    missing_rgb = 0
    for name, body in sorted(tokens.items()):
        if not NAME_PATTERN.match(name):
            bad_name += 1
            rep.info(f"malformed token name: {name}")
        if not isinstance(body, dict):
            bad_type += 1
            continue
        if body.get("type") not in VALID_TYPES:
            bad_type += 1
            rep.info(f"{name}: invalid type {body.get('type')!r}")
        if "unit" in body and body["unit"] not in VALID_UNITS:
            bad_unit += 1
            rep.info(f"{name}: invalid unit {body.get('unit')!r}")
        desc = body.get("description")
        if not isinstance(desc, str) or len(desc) < 10:
            bad_desc += 1
            rep.info(f"{name}: missing or trivial description")
        scope = body.get("scope")
        if (not isinstance(scope, list) or not scope
                or any(s not in VALID_SCOPES for s in scope)):
            bad_scope += 1
            rep.info(f"{name}: invalid scope {scope!r}")
        # Primitive colours must publish their RGB form.
        if (body.get("type") == "color"
                and origin.get(name, "").endswith("primitives.json")):
            if not RGB_PATTERN.match(str(body.get("rgb", ""))):
                missing_rgb += 1
                rep.info(f"{name}: primitive colour without a valid rgb field")

    rep.check(bad_name == 0, "every token name follows the naming convention")
    rep.check(bad_type == 0, "every token declares a valid type")
    rep.check(bad_unit == 0, "every declared unit is valid")
    rep.check(bad_desc == 0, "every token carries a substantive description")
    rep.check(bad_scope == 0, "every token declares a valid non-empty scope")
    rep.check(missing_rgb == 0, "every primitive colour token carries its RGB form")

    # -- the semantic colour set is complete -------------------------------
    present = {
        n.split("color.semantic.")[1].split(".")[0]
        for n in tokens if n.startswith("color.semantic.")
    }
    for required in REQUIRED_SEMANTIC_COLORS:
        rep.check(required in present,
                  f"semantic colour 'color.semantic.{required}' is defined")

    # -- colour tokens carry their usage contract --------------------------
    # The contract lives on the primitive and semantic layers. An alias
    # deliberately does NOT restate it: an alias resolves to exactly one
    # semantic token, and that token is the single place its usage rules are
    # allowed to be changed. Restating the contract on the alias would create a
    # second source of truth that will drift. That an alias resolves to a
    # semantic colour is enforced separately by validate-token-references.py.
    incomplete = 0
    contracted = 0
    for name, body in sorted(tokens.items()):
        if body.get("type") != "color" or name.startswith("component."):
            continue
        contracted += 1
        for field in ("intendedUsage", "allowedBackground",
                      "prohibitedUsage", "contrastTarget"):
            if field not in body:
                incomplete += 1
                rep.info(f"{name}: colour token missing '{field}'")
                break
    rep.check(
        incomplete == 0,
        f"every primitive and semantic colour token declares intendedUsage, "
        f"allowedBackground, prohibitedUsage and contrastTarget "
        f"({contracted} tokens carry the contract)",
    )

    # -- the touch-target floor exists and is 48 ---------------------------
    touch = tokens.get("size.touch.min", {})
    rep.check(touch.get("value") == 48,
              "size.touch.min is 48 (minimum touch target, non-negotiable)")

    # -- the 4px grid base exists ------------------------------------------
    rep.check(tokens.get("space.grid.base", {}).get("value") == 4,
              "space.grid.base is 4 (the spacing grid is 4px-based)")

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
