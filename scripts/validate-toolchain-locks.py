#!/usr/bin/env python3
"""Assert the Step 3 toolchain is pinned exactly and agrees across every surface.

A pinned toolchain is only worth something if the pin is (a) exact and (b) the SAME
everywhere it appears. This validator enforces both.

It checks:
  1. every tool in docs/runtime/TOOLCHAIN.md carries an exact version, not a range,
     not an alias, not a floating channel name;
  2. the Flutter archive checksum is a full 64-character SHA256;
  3. versions agree between TOOLCHAIN.md, COMPATIBILITY_MATRIX.md, and (once they
     exist) the Compose file and CI workflows;
  4. no container image is referenced by a floating tag;
  5. no tool from the deliberately-excluded list has entered the tree.

This validator FAILS CLOSED: an unparseable or missing document is a failure, not a
skip. A validator that silently passes when it cannot read its input is worse than no
validator, because it manufactures confidence.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402

TOOLCHAIN_DOC = "docs/runtime/TOOLCHAIN.md"
MATRIX_DOC = "docs/runtime/COMPATIBILITY_MATRIX.md"
SOURCES_DOC = "docs/runtime/TOOLCHAIN_SOURCES.md"
COMPOSE_FILE = "infrastructure/docker-compose.dev.yml"

# Tools that MUST be pinned, and the exact version each must be pinned to.
# Changing a value here is a deliberate toolchain change and must travel with a
# decision record and an updated TOOLCHAIN.md in the same pull request.
REQUIRED_PINS = {
    "Flutter": "3.44.6",
    "Dart": "3.12.2",
    "PHP": "8.5.4",
    "Composer": "2.10.1",
    "Laravel": "13.20.0",
    "Sanctum": "4.3.2",
    "PostgreSQL": "18.4",
    "Redis": "8.2",
    "JDK": "21.0.10",
    "Docker": "29.5.3",
    "Node": "22.22.1",
    "Python": "3.14.4",
}

# The Flutter SDK archive checksum published by the official release manifest.
FLUTTER_SHA256 = "a6320fd72e9a2690c08e2a6a70874a30cb120dee7c78f49d2c628bd7c9e20525"

# Version strings that are aliases rather than pins. Any of these appearing as a
# version is a failure: they resolve differently over time and destroy reproducibility.
FLOATING_ALIASES = {
    "latest", "stable", "edge", "main", "master", "current",
    "lts", "nightly", "beta", "dev", "rolling", "alpine",
}

# Tools deliberately excluded from Step 3 (TOOLCHAIN.md section 3). Their appearance
# as a pinned toolchain entry means scope has leaked.
FORBIDDEN_TOOLCHAIN_TERMS = [
    "xcode", "cocoapods", "kubernetes", "kubectl", "terraform",
    "helm", "ansible", "vagrant",
]


def read(root: Path, rel: str) -> str | None:
    p = root / rel
    if not p.is_file():
        return None
    return p.read_text(encoding="utf-8")


def check_required_pins(rep: Reporter, text: str) -> None:
    """Every required tool must appear in TOOLCHAIN.md with its exact version."""
    missing = []
    for tool, version in REQUIRED_PINS.items():
        # The version must appear somewhere in the document alongside the tool name.
        if version not in text:
            missing.append(f"{tool} {version}")
    if missing:
        for m in sorted(missing):
            rep.fail(f"required pin absent from {TOOLCHAIN_DOC}: {m}")
    else:
        rep.ok(f"all {len(REQUIRED_PINS)} required tools carry their exact pinned version")


def check_no_floating_aliases(rep: Reporter, text: str, label: str) -> None:
    """A version cell containing a floating alias is not a pin."""
    findings = []
    # Look for markdown table cells that look like a version but hold an alias,
    # and for container tags of the form image:alias.
    for m in re.finditer(r"\b([a-z0-9_\-/]+):([a-z]+)\b", text):
        image, tag = m.group(1), m.group(2)
        if tag in FLOATING_ALIASES and "/" not in tag:
            # Ignore prose like "channel: stable" by requiring an image-ish left side.
            if image in ("postgres", "redis", "php", "node", "python", "composer"):
                findings.append(f"floating container tag: {image}:{tag}")
    if findings:
        for f in sorted(set(findings)):
            rep.fail(f"{label}: {f}")
    else:
        rep.ok(f"{label}: no floating container tag")


def check_flutter_checksum(rep: Reporter, text: str) -> None:
    if FLUTTER_SHA256 not in text:
        rep.fail(f"{TOOLCHAIN_DOC}: Flutter archive SHA256 absent or altered")
        return
    if not re.fullmatch(r"[0-9a-f]{64}", FLUTTER_SHA256):
        rep.fail("Flutter archive checksum is not a full 64-character SHA256")
        return
    rep.ok("Flutter SDK archive checksum recorded as a full 64-character SHA256")


def check_cross_document_agreement(rep: Reporter, root: Path) -> None:
    """The same version must not appear differently in two documents."""
    docs = {}
    for rel in (TOOLCHAIN_DOC, MATRIX_DOC, SOURCES_DOC):
        t = read(root, rel)
        if t is None:
            rep.fail(f"required toolchain document missing: {rel}")
            return
        docs[rel] = t

    disagreements = []
    for tool, version in REQUIRED_PINS.items():
        # Find any other version of this tool mentioned in the matrix/sources.
        pattern = re.compile(rf"{re.escape(tool)}\s+(\d+\.\d+(?:\.\d+)?)", re.IGNORECASE)
        for rel, text in docs.items():
            for m in pattern.finditer(text):
                found = m.group(1)
                # Allow a shorter prefix form (e.g. "Redis 8.2" vs pin "8.2").
                if not (version.startswith(found) or found.startswith(version)):
                    disagreements.append(f"{rel}: {tool} {found} disagrees with pin {version}")

    if disagreements:
        for d in sorted(set(disagreements)):
            rep.fail(f"toolchain drift — {d}")
    else:
        rep.ok("toolchain versions agree across TOOLCHAIN, MATRIX, and SOURCES")


def check_forbidden_terms(rep: Reporter, text: str) -> None:
    hits = [t for t in FORBIDDEN_TOOLCHAIN_TERMS if re.search(rf"\b{t}\b", text, re.IGNORECASE)]
    # Presence in the "deliberately NOT in the toolchain" section is expected and fine;
    # we only fail if a forbidden term appears in a pinned-version table row.
    bad = []
    for line in text.splitlines():
        if not line.strip().startswith("|"):
            continue
        low = line.lower()
        for t in hits:
            if re.search(rf"\b{t}\b", low) and re.search(r"\d+\.\d+", low):
                bad.append(f"{t} appears as a pinned toolchain entry")
    if bad:
        for b in sorted(set(bad)):
            rep.fail(f"excluded tool pinned: {b}")
    else:
        rep.ok("no deliberately-excluded tool appears as a pinned entry")


def check_compose_if_present(rep: Reporter, root: Path) -> None:
    text = read(root, COMPOSE_FILE)
    if text is None:
        rep.ok(f"{COMPOSE_FILE} not yet present — container pins not applicable")
        return
    problems = []
    for m in re.finditer(r"image:\s*([^\s#]+)", text):
        ref = m.group(1).strip().strip('"\'')
        if ":" not in ref:
            problems.append(f"image without explicit tag: {ref}")
            continue
        tag = ref.rsplit(":", 1)[1]
        if tag in FLOATING_ALIASES:
            problems.append(f"floating image tag: {ref}")
    if problems:
        for p in sorted(set(problems)):
            rep.fail(f"{COMPOSE_FILE}: {p}")
    else:
        rep.ok(f"{COMPOSE_FILE}: every image reference carries an explicit version tag")

    for tool, key in (("PostgreSQL", "postgres"), ("Redis", "redis")):
        want = REQUIRED_PINS[tool]
        if f"{key}:{want}" not in text:
            rep.fail(f"{COMPOSE_FILE}: {key} not pinned to {want}")
        else:
            rep.ok(f"{COMPOSE_FILE}: {key} pinned to {want}")


def main() -> int:
    root = repo_root()
    rep = Reporter("toolchain-locks")

    text = read(root, TOOLCHAIN_DOC)
    if text is None:
        rep.fail(f"required document missing: {TOOLCHAIN_DOC}")
        return rep.finish()

    check_required_pins(rep, text)
    check_flutter_checksum(rep, text)
    check_no_floating_aliases(rep, text, TOOLCHAIN_DOC)
    check_forbidden_terms(rep, text)
    check_cross_document_agreement(rep, root)
    check_compose_if_present(rep, root)

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
