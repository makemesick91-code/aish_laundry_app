#!/usr/bin/env python3
"""Verify every relative markdown link target exists on disk.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path
from urllib.parse import unquote

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main, tracked_files  # noqa: E402

# Inline markdown link: [text](target)  and reference definition: [id]: target
INLINE_LINK = re.compile(r"(?<!\!)\[[^\]\n]*\]\(\s*<?([^)>\s]+)>?(?:\s+\"[^\"]*\")?\s*\)")
IMAGE_LINK = re.compile(r"!\[[^\]\n]*\]\(\s*<?([^)>\s]+)>?(?:\s+\"[^\"]*\")?\s*\)")
REF_DEF = re.compile(r"^\s{0,3}\[[^\]\n]+\]:\s*<?([^>\s]+)>?", re.MULTILINE)

IGNORED_SCHEMES = ("http://", "https://", "mailto:", "tel:", "ftp://", "data:")

FENCE = re.compile(r"^\s*(```|~~~)")


def strip_code_fences(text: str) -> list[tuple[int, str]]:
    """Return (lineno, line) for lines outside fenced code blocks."""
    out: list[tuple[int, str]] = []
    in_fence = False
    for i, line in enumerate(text.splitlines(), start=1):
        if FENCE.match(line):
            in_fence = not in_fence
            continue
        if not in_fence:
            out.append((i, line))
    return out


def main() -> int:
    root = repo_root()
    rep = Reporter("markdown-links")

    md_files = sorted(
        p for p in tracked_files(root)
        if p.suffix.lower() == ".md" and ".git/" not in p.as_posix()
    )
    if not md_files:
        rep.fail("found markdown files to check")
        return rep.finish()
    rep.info(f"checking {len(md_files)} markdown files")

    broken = 0
    checked = 0

    for path in md_files:
        rel_file = path.relative_to(root).as_posix()
        text = read_text(path)
        for lineno, line in strip_code_fences(text):
            targets = [m.group(1) for m in INLINE_LINK.finditer(line)]
            targets += [m.group(1) for m in IMAGE_LINK.finditer(line)]
            for target in targets:
                target = target.strip()
                if not target:
                    continue
                low = target.lower()
                if low.startswith(IGNORED_SCHEMES) or low.startswith("//"):
                    continue
                if target.startswith("#"):
                    continue  # pure anchor
                # strip anchor and query
                clean = target.split("#", 1)[0].split("?", 1)[0]
                if not clean:
                    continue
                clean = unquote(clean)
                if clean.startswith("/"):
                    resolved = root / clean.lstrip("/")
                else:
                    resolved = path.parent / clean
                checked += 1
                if not (resolved.exists() or resolved.is_symlink()):
                    broken += 1
                    rep.fail(
                        f"broken relative link: '{target}' ({rel_file}:{lineno})"
                    )
                    try:
                        shown = resolved.resolve().relative_to(root).as_posix()
                    except (ValueError, OSError):
                        shown = str(resolved)
                    rep.info(f"resolved to: {shown} (does not exist)")

        # reference-style definitions
        for m in REF_DEF.finditer(text):
            target = m.group(1).strip()
            low = target.lower()
            if low.startswith(IGNORED_SCHEMES) or target.startswith("#") or low.startswith("//"):
                continue
            clean = unquote(target.split("#", 1)[0].split("?", 1)[0])
            if not clean:
                continue
            resolved = (root / clean.lstrip("/")) if clean.startswith("/") else (path.parent / clean)
            checked += 1
            lineno = text[: m.start()].count("\n") + 1
            if not (resolved.exists() or resolved.is_symlink()):
                broken += 1
                rep.fail(
                    f"broken reference-style link: '{target}' ({rel_file}:{lineno})"
                )

    if broken == 0:
        rep.ok(f"all {checked} relative markdown links resolve to existing paths")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
