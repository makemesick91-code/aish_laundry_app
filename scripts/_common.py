#!/usr/bin/env python3
"""Shared helpers for Aish Laundry App Step 0 validators.

Standard library only. No third-party dependencies.
"""

from __future__ import annotations

import os
import sys
from pathlib import Path


def repo_root() -> Path:
    """Resolve the repository root from this file's own location."""
    return Path(__file__).resolve().parent.parent


class Reporter:
    """Collects PASS/FAIL results and prints them deterministically."""

    def __init__(self, title: str) -> None:
        self.title = title
        self.passed = 0
        self.failed = 0
        print("=" * 72)
        print(f"VALIDATOR: {title}")
        print("=" * 72)

    def ok(self, message: str) -> None:
        self.passed += 1
        print(f"PASS  {message}")

    def fail(self, message: str) -> None:
        self.failed += 1
        print(f"FAIL  {message}")

    def check(self, condition: bool, message: str) -> bool:
        if condition:
            self.ok(message)
        else:
            self.fail(message)
        return bool(condition)

    def info(self, message: str) -> None:
        print(f"      {message}")

    def finish(self) -> int:
        total = self.passed + self.failed
        print("-" * 72)
        print(
            f"SUMMARY [{self.title}]: {self.passed}/{total} checks passed, "
            f"{self.failed} failed"
        )
        if self.failed:
            print(f"RESULT: FAIL ({self.title})")
            return 1
        print(f"RESULT: PASS ({self.title})")
        return 0


def read_text(path: Path) -> str:
    """Read a UTF-8 text file, tolerating decode errors instead of crashing."""
    try:
        return path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return path.read_text(encoding="utf-8", errors="replace")


def tracked_files(root: Path) -> list[Path]:
    """Return repository files, excluding .git. Uses git when available."""
    import subprocess

    try:
        out = subprocess.run(
            ["git", "-C", str(root), "ls-files", "-z", "--cached", "--others",
             "--exclude-standard"],
            capture_output=True,
            check=True,
        )
        names = [n for n in out.stdout.decode("utf-8", "replace").split("\0") if n]
        return [root / n for n in names if (root / n).is_file()]
    except (OSError, subprocess.CalledProcessError):
        result = []
        for dirpath, dirnames, filenames in os.walk(root):
            dirnames[:] = [d for d in dirnames if d != ".git"]
            for name in filenames:
                result.append(Path(dirpath) / name)
        return result


#: The complete canonical status vocabulary. Nothing else is a status.
STATUS_VOCABULARY = [
    "NOT IMPLEMENTED",
    "NOT APPLICABLE",
    "NOT STARTED",
    "IN PROGRESS",
    "PLANNED",
    "TESTED",
    "WATCH",
    "ABSENT",
    "NO-GO",
    "GO",
]


def declared_statuses(block: "str | None") -> list[str]:
    """Extract status words that a roadmap/status block actually *declares*.

    Only two shapes count as a declaration:

    * a markdown table cell, e.g. ``| 1 | Title | IN PROGRESS |``
    * a ``Status:`` line, e.g. ``**Status: IN PROGRESS**``

    Prose is deliberately ignored. Scanning a whole block for status words gives
    false positives that matter: "GO" is a substring of "GOVERNANCE", and a scope
    line such as "restore is tested" is not a declaration that the step is
    TESTED. Both produced spurious failures before this helper existed.

    Longest-first matching prevents "NOT IMPLEMENTED" from also reporting the
    substring "IMPLEMENTED", and prevents "NO-GO" from reporting "GO".
    """
    if not block:
        return []

    import re as _re

    candidates: list[str] = []
    for line in block.splitlines():
        stripped = line.strip()
        if stripped.startswith("|"):
            candidates.extend(
                cell.strip() for cell in stripped.strip("|").split("|")
            )
        m = _re.match(
            r"^[*_\s]*status[*_\s]*:\s*(.+?)\s*$", stripped, _re.IGNORECASE
        )
        if m:
            # Trim trailing commentary after an em dash or parenthesis.
            value = _re.split(r"[—(]", m.group(1))[0]
            candidates.append(value)

    found: list[str] = []
    for cell in candidates:
        text = _re.sub(r"[*_`]", "", cell).strip().upper()
        if not text:
            continue
        remaining = text
        for status in sorted(STATUS_VOCABULARY, key=len, reverse=True):
            if _re.search(rf"(?<![A-Z-]){_re.escape(status)}(?![A-Z-])", remaining):
                found.append(status)
                remaining = remaining.replace(status, " ")
    return found


def run_main(func) -> None:
    """Run a validator main() and exit with its code, never with a traceback."""
    try:
        sys.exit(func())
    except SystemExit:
        raise
    except Exception as exc:  # pragma: no cover - defensive
        print(f"FAIL  validator crashed: {type(exc).__name__}: {exc}")
        sys.exit(1)
