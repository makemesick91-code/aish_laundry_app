#!/usr/bin/env python3
"""Generate Dart design-token source from the canonical token JSON.

CANONICAL SOURCE: docs/design/tokens/*.json — READ ONLY. This script never
writes to it, and a design value that is not in it does not exist.

OUTPUT: packages/design_system/lib/src/generated/*.dart

Three properties this generator guarantees, because a token layer that lacks any
one of them stops being a source of truth:

  1. DETERMINISM. The same inputs produce byte-identical output. Nothing in the
     emitted text depends on the wall clock, the hostname, the working
     directory, the Python build, or dictionary iteration luck. That is what
     makes "regenerate and diff" a usable drift check — a generator that stamps
     a timestamp can never be diffed against its own output.

  2. PROVENANCE. Every generated file records the SHA256 of every source file it
     was produced from. A token JSON edited without regenerating is therefore
     detectable mechanically rather than by eye.

  3. NO HAND EDITING. Every file carries a GENERATED — DO NOT EDIT banner. A fix
     applied by hand here is silently destroyed by the next run, which is worse
     than no fix at all.

LIGHT THEME ONLY. semantic-light.json is the only semantic mapping that exists.
Dark mode is DEFERRED: no dark mapping is generated, claimed, or implied.

Standard library only. Run from anywhere:

    python3 scripts/generate-design-tokens.py            # write
    python3 scripts/generate-design-tokens.py --check    # verify, write nothing
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
TOKEN_DIR = REPO_ROOT / "docs" / "design" / "tokens"
OUT_DIR = REPO_ROOT / "packages" / "design_system" / "lib" / "src" / "generated"

# Order is fixed and alphabetical by filename so the provenance block is stable.
SOURCE_FILES = [
    "border.json",
    "elevation.json",
    "motion.json",
    "opacity.json",
    "primitives.json",
    "radius.json",
    "semantic-light.json",
    "sizing.json",
    "spacing.json",
    "typography.json",
]

BANNER_LINES = [
    "// GENERATED — DO NOT EDIT.",
    "//",
    "// Produced by scripts/generate-design-tokens.py from the canonical token",
    "// JSON under docs/design/tokens/. Edit the JSON and regenerate; an edit made",
    "// here is destroyed by the next run.",
    "//",
    "// Light theme only. Dark mode is DEFERRED — no dark mapping exists.",
]


# ---------------------------------------------------------------------------
# helpers
# ---------------------------------------------------------------------------
def sha256_of(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def load_tokens(name: str) -> dict:
    """Load a token file, preserving declaration order."""
    with (TOKEN_DIR / name).open(encoding="utf-8") as handle:
        return json.load(handle)["tokens"]


def dart_name(token_key: str) -> str:
    """Mechanically camelCase a dotted token key.

    Every segment is kept. Nothing is dropped, abbreviated, or special-cased,
    because a generator that applies judgement produces names a reader cannot
    predict from the token key — and then the mapping needs its own document.

        color.semantic.primary.hover -> colorSemanticPrimaryHover
        space.0                      -> space0
        font.size.body.md            -> fontSizeBodyMd
    """
    segments = token_key.split(".")
    head = segments[0]
    tail = "".join(seg[:1].upper() + seg[1:] for seg in segments[1:])
    name = head + tail
    if not re.fullmatch(r"[a-z][A-Za-z0-9]*", name):
        raise ValueError(f"token key {token_key!r} does not yield a Dart identifier")
    return name


def dart_doc(description: str) -> list[str]:
    """Wrap a token description as a Dart doc comment, deterministically."""
    words = description.split()
    lines: list[str] = []
    current = "///"
    for word in words:
        candidate = f"{current} {word}"
        if len(candidate) > 78 and current != "///":
            lines.append(current)
            current = f"/// {word}"
        else:
            current = candidate
    if current != "///":
        lines.append(current)
    return lines


def hex_to_dart_color(value: str) -> str:
    digits = value.lstrip("#").upper()
    if len(digits) != 6:
        raise ValueError(f"unsupported colour literal {value!r}")
    return f"Color(0xFF{digits})"


def num_literal(value: float | int) -> str:
    """Emit a Dart double literal with a stable textual form."""
    as_float = float(value)
    if as_float == int(as_float):
        return f"{int(as_float)}.0"
    return repr(as_float)


def resolve_reference(value: str, primitives: dict) -> str:
    """Resolve a `{token.key}` semantic reference to its primitive hex value."""
    match = re.fullmatch(r"\{([A-Za-z0-9_.]+)\}", value)
    if not match:
        raise ValueError(f"semantic token value {value!r} is not a primitive reference")
    key = match.group(1)
    if key not in primitives:
        raise ValueError(f"semantic token references unknown primitive {key!r}")
    return primitives[key]["value"]


RGBA_RE = re.compile(
    r"rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([0-9.]+)\s*\)"
)
SHADOW_RE = re.compile(
    r"^(-?[0-9.]+)(?:px)?\s+(-?[0-9.]+)(?:px)?\s+(-?[0-9.]+)(?:px)?\s+(rgba\(.*\))$"
)


def shadow_to_dart(value: str) -> str:
    if value.strip() == "none":
        return "<BoxShadow>[]"
    match = SHADOW_RE.match(value.strip())
    if not match:
        raise ValueError(f"unsupported shadow literal {value!r}")
    dx, dy, blur, rgba = match.groups()
    rgba_match = RGBA_RE.fullmatch(rgba)
    if not rgba_match:
        raise ValueError(f"unsupported rgba literal {rgba!r}")
    red, green, blue, alpha = rgba_match.groups()
    argb = (
        f"Color.fromRGBO({int(red)}, {int(green)}, {int(blue)}, {num_literal(float(alpha))})"
    )
    return (
        "<BoxShadow>[\n"
        f"    BoxShadow(\n"
        f"      color: {argb},\n"
        f"      offset: Offset({num_literal(float(dx))}, {num_literal(float(dy))}),\n"
        f"      blurRadius: {num_literal(float(blur))},\n"
        f"    ),\n"
        "  ]"
    )


CUBIC_RE = re.compile(
    r"cubic-bezier\(\s*(-?[0-9.]+)\s*,\s*(-?[0-9.]+)\s*,\s*(-?[0-9.]+)\s*,\s*(-?[0-9.]+)\s*\)"
)


def cubic_to_dart(value: str) -> str:
    match = CUBIC_RE.fullmatch(value.strip())
    if not match:
        raise ValueError(f"unsupported easing literal {value!r}")
    a, b, c, d = (num_literal(float(g)) for g in match.groups())
    return f"Cubic({a}, {b}, {c}, {d})"


def font_stack_to_dart(value: str) -> str:
    families = [part.strip().strip("'\"") for part in value.split(",")]
    inner = ", ".join(f"'{family}'" for family in families if family)
    return f"<String>[{inner}]"


# ---------------------------------------------------------------------------
# file emission
# ---------------------------------------------------------------------------
def header(source_shas: list[tuple[str, str]], imports: list[str]) -> list[str]:
    lines = list(BANNER_LINES)
    lines.append("//")
    lines.append("// Source files and their SHA256 at generation time:")
    for name, digest in source_shas:
        lines.append(f"//   {name} = {digest}")
    lines.append("")
    lines.append("// ignore_for_file: public_member_api_docs")
    lines.append("")
    for imp in imports:
        lines.append(imp)
    if imports:
        lines.append("")
    return lines


def emit_class(
    class_name: str,
    doc: str,
    entries: list[tuple[str, str, str, str]],
) -> list[str]:
    """entries: (dart_name, dart_type, dart_value, description)"""
    lines = [f"/// {doc}", f"abstract final class {class_name} {{"]
    for name, dart_type, value, description in entries:
        if description:
            for doc_line in dart_doc(description):
                lines.append(f"  {doc_line}")
        lines.append(f"  static const {dart_type} {name} = {value};")
        lines.append("")
    if lines[-1] == "":
        lines.pop()
    lines.append("}")
    lines.append("")
    return lines


def build_files() -> dict[str, str]:
    source_shas = [(name, sha256_of(TOKEN_DIR / name)) for name in SOURCE_FILES]

    primitives = load_tokens("primitives.json")
    semantic = load_tokens("semantic-light.json")
    spacing = load_tokens("spacing.json")
    radius = load_tokens("radius.json")
    sizing = load_tokens("sizing.json")
    border = load_tokens("border.json")
    opacity = load_tokens("opacity.json")
    typography = load_tokens("typography.json")
    elevation = load_tokens("elevation.json")
    motion = load_tokens("motion.json")

    files: dict[str, list[str]] = {}

    # -- provenance ---------------------------------------------------------
    prov = header(source_shas, [])
    prov += [
        "/// SHA256 of every canonical token source this package was generated from.",
        "///",
        "/// A drift test compares these digests against the files on disk. A token",
        "/// JSON edited without regenerating is therefore a failing test rather than",
        "/// a discrepancy somebody notices six screens later.",
        "abstract final class AishTokenSources {",
        "  static const Map<String, String> sha256 = <String, String>{",
    ]
    for name, digest in source_shas:
        prov.append(f"    '{name}': '{digest}',")
    prov += [
        "  };",
        "",
        "  /// The only theme this package maps. Dark mode is DEFERRED.",
        "  static const String theme = 'light';",
        "}",
        "",
    ]
    files["token_sources.dart"] = prov

    # -- colours ------------------------------------------------------------
    primitive_entries = [
        (
            dart_name(key),
            "Color",
            hex_to_dart_color(str(token["value"])),
            str(token.get("description", "")),
        )
        for key, token in primitives.items()
        if token["type"] == "color"
    ]
    semantic_entries = [
        (
            dart_name(key),
            "Color",
            hex_to_dart_color(resolve_reference(str(token["value"]), primitives)),
            str(token.get("description", "")),
        )
        for key, token in semantic.items()
        if token["type"] == "color"
    ]
    colors = header(source_shas, ["import 'dart:ui' show Color;"])
    colors += emit_class(
        "AishColorPrimitives",
        "Primitive colour ramp. Carries no meaning. A widget must never reference "
        "one of these directly — reference AishSemanticColors instead.",
        primitive_entries,
    )
    colors += emit_class(
        "AishSemanticColors",
        "Semantic colour roles for the canonical light theme. This is the only "
        "colour surface a widget may reference.",
        semantic_entries,
    )
    files["color_tokens.dart"] = colors

    # -- dimensions ---------------------------------------------------------
    def dimension_entries(tokens: dict) -> list[tuple[str, str, str, str]]:
        return [
            (
                dart_name(key),
                "double",
                num_literal(token["value"]),
                str(token.get("description", "")),
            )
            for key, token in tokens.items()
        ]

    dims = header(source_shas, [])
    dims += emit_class(
        "AishSpacing", "Spacing scale on a strict 4px grid.", dimension_entries(spacing)
    )
    dims += emit_class(
        "AishRadius",
        "Corner radius scale. Radius is decorative and never conveys state.",
        dimension_entries(radius),
    )
    dims += emit_class(
        "AishSizing",
        "Sizing scale, including the non-negotiable 48x48 minimum touch target.",
        dimension_entries(sizing),
    )
    dims += emit_class(
        "AishBorders", "Border widths and focus-ring geometry.", dimension_entries(border)
    )
    dims += emit_class(
        "AishOpacity",
        "Opacity scale. Opacity never substitutes for a contrast-compliant colour.",
        dimension_entries(opacity),
    )
    files["dimension_tokens.dart"] = dims

    # -- typography ---------------------------------------------------------
    type_entries: list[tuple[str, str, str, str]] = []
    for key, token in typography.items():
        kind = token["type"]
        description = str(token.get("description", ""))
        if kind == "fontFamily":
            type_entries.append(
                (dart_name(key), "List<String>", font_stack_to_dart(str(token["value"])), description)
            )
        elif kind == "string":
            type_entries.append(
                (dart_name(key), "String", f"'{token['value']}'", description)
            )
        elif kind == "fontWeight":
            type_entries.append(
                (dart_name(key), "int", str(int(token["value"])), description)
            )
        else:
            type_entries.append(
                (dart_name(key), "double", num_literal(token["value"]), description)
            )
    typo = header(source_shas, [])
    typo += emit_class(
        "AishTypography",
        "Typography primitives. System-first: no font binary is committed, so every "
        "surface renders in the platform UI face.",
        type_entries,
    )
    files["typography_tokens.dart"] = typo

    # -- elevation ----------------------------------------------------------
    elev_entries = [
        (
            dart_name(key),
            "List<BoxShadow>",
            shadow_to_dart(str(token["value"])),
            str(token.get("description", "")),
        )
        for key, token in elevation.items()
    ]
    elev = header(source_shas, ["import 'package:flutter/painting.dart';"])
    elev += emit_class(
        "AishElevation",
        "Light-theme elevation. Elevation is spatial and never semantic.",
        elev_entries,
    )
    files["elevation_tokens.dart"] = elev

    # -- motion -------------------------------------------------------------
    motion_entries: list[tuple[str, str, str, str]] = []
    for key, token in motion.items():
        description = str(token.get("description", ""))
        if token["type"] == "duration":
            motion_entries.append(
                (
                    dart_name(key),
                    "Duration",
                    f"Duration(milliseconds: {int(token['value'])})",
                    description,
                )
            )
        else:
            motion_entries.append(
                (dart_name(key), "Cubic", cubic_to_dart(str(token["value"])), description)
            )
    mot = header(source_shas, ["import 'package:flutter/animation.dart';"])
    mot += emit_class(
        "AishMotion",
        "Motion durations and easing. Motion serves comprehension, never decoration, "
        "and never carries state on its own.",
        motion_entries,
    )
    files["motion_tokens.dart"] = mot

    # -- barrel -------------------------------------------------------------
    barrel = header(source_shas, [])
    barrel += [
        "export 'color_tokens.dart';",
        "export 'dimension_tokens.dart';",
        "export 'elevation_tokens.dart';",
        "export 'motion_tokens.dart';",
        "export 'token_sources.dart';",
        "export 'typography_tokens.dart';",
        "",
    ]
    files["tokens.dart"] = barrel

    raw = {name: "\n".join(lines) for name, lines in files.items()}
    return _format_dart(raw)


def _format_dart(files: dict[str, str]) -> dict[str, str]:
    """Run `dart format` over the generated text.

    Without this the repository has two incompatible invariants: `dart format
    --set-exit-if-changed` wants the output formatted, and the drift check wants
    regeneration to be byte-identical. Formatting HERE satisfies both — the
    generator emits exactly what the formatter would produce, so running the
    formatter afterwards is a no-op.

    `dart format` is deterministic for a given SDK version, and the SDK is
    pinned (Dart 3.12.2, docs/runtime/TOOLCHAIN.md), so this does not introduce
    a moving part.

    If `dart` is not on PATH the unformatted text is returned rather than
    failing: a developer without the SDK can still inspect the output, and CI —
    which does have the SDK — is where the format gate actually runs.
    """
    import shutil
    import subprocess
    import tempfile

    if shutil.which("dart") is None:
        # FAIL CLOSED. Emitting unformatted output here made the generator
        # NON-DETERMINISTIC on a hidden input: with dart present it produced the
        # committed, formatted bytes; without dart it produced different bytes and
        # `--check` then reported "DRIFT DETECTED" against a tree that had not
        # drifted at all. A drift check that cries wolf gets ignored, and an
        # ignored drift check is worse than none — so refuse to answer rather
        # than answer differently depending on the environment.
        print(
            "generate-design-tokens: FATAL: dart is not on PATH.\n"
            "  Generated output is dart-formatted, so without the SDK this script\n"
            "  would emit different bytes and report false drift. Refusing to run.\n"
            "  Fix: export PATH=\"$HOME/flutter/bin:$PATH\"",
            file=sys.stderr,
        )
        sys.exit(2)

    with tempfile.TemporaryDirectory() as tmp:
        tmp_path = Path(tmp)
        for name, content in files.items():
            (tmp_path / name).write_text(content, encoding="utf-8")
        try:
            subprocess.run(
                ["dart", "format", str(tmp_path)],
                capture_output=True,
                check=True,
                timeout=120,
            )
        except (OSError, subprocess.SubprocessError) as exc:
            raise ValueError(f"dart format failed on generated output: {exc}") from exc
        return {
            name: (tmp_path / name).read_text(encoding="utf-8")
            for name in files
        }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--check",
        action="store_true",
        help="verify the checked-in output matches; write nothing",
    )
    args = parser.parse_args()

    try:
        generated = build_files()
    except (OSError, ValueError, KeyError) as exc:
        print(f"generate-design-tokens: FAILED: {exc}", file=sys.stderr)
        return 1

    if args.check:
        drift = []
        for name, content in generated.items():
            path = OUT_DIR / name
            if not path.is_file():
                drift.append(f"missing: {path.relative_to(REPO_ROOT)}")
            elif path.read_text(encoding="utf-8") != content:
                drift.append(f"stale: {path.relative_to(REPO_ROOT)}")
        for line in drift:
            print(f"generate-design-tokens: {line}", file=sys.stderr)
        if drift:
            print("generate-design-tokens: DRIFT DETECTED", file=sys.stderr)
            return 1
        print(f"generate-design-tokens: {len(generated)} files up to date")
        return 0

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for name, content in sorted(generated.items()):
        (OUT_DIR / name).write_text(content, encoding="utf-8")
        print(f"generate-design-tokens: wrote {(OUT_DIR / name).relative_to(REPO_ROOT)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
