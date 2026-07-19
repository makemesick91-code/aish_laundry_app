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


def run_main(func) -> None:
    """Run a validator main() and exit with its code, never with a traceback."""
    try:
        sys.exit(func())
    except SystemExit:
        raise
    except Exception as exc:  # pragma: no cover - defensive
        print(f"FAIL  validator crashed: {type(exc).__name__}: {exc}")
        sys.exit(1)
