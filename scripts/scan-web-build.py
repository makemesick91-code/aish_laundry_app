#!/usr/bin/env python3
"""Scan a built Flutter Web output for credentials and browser token storage.

Extracted from the CI workflow deliberately. It previously lived as an indented
heredoc inside a YAML block scalar, which is fragile to indentation and could not
be run locally — the step died with a shell parse error and produced no output at
all, which is the worst possible failure mode for a security scan: it looked like
a finding when it was a syntax error.

As a file it is testable, reviewable, and identical locally and in CI.

Usage:  python3 scripts/scan-web-build.py apps/admin_web/build/web
Exit 0 = clean, 1 = finding, 2 = usage/IO error (fails closed).
"""

from __future__ import annotations

import pathlib
import re
import sys

# Bearer tokens must never be persisted where page script can read them
# (Rule 38 hard rule 2). Admin Web uses HttpOnly cookie auth.
FORBIDDEN_STORAGE = ("localStorage", "sessionStorage")

# A credential ASSIGNED a literal value. Deliberately requires a quoted literal:
# `s.type="password"` and a `password:` named parameter are Flutter's own input
# handling, not secrets, and flagging them would be the false positive that
# creates pressure to weaken the scan.
CREDENTIAL_ASSIGNMENT = re.compile(
    r'(password|passwd|secret|api[_-]?key|access[_-]?token|client[_-]?secret)'
    r'["\']?\s*[:=]\s*["\'][A-Za-z0-9/+_.\-]{8,}["\']',
    re.IGNORECASE,
)

# Values that must never be baked into a shipped bundle.
KNOWN_DEV_VALUES = ("CHANGEME_local_dev_only", "CHANGEME_ci_only_not_secret", "aish_dev")

SCAN_SUFFIXES = {".js", ".json", ".html", ".map"}


def main() -> int:
    if len(sys.argv) != 2:
        print("usage: scan-web-build.py <build/web directory>", file=sys.stderr)
        return 2

    root = pathlib.Path(sys.argv[1])
    if not root.is_dir():
        print(f"not a directory: {root}", file=sys.stderr)
        return 2

    findings: list[str] = []
    scanned = 0

    for f in sorted(root.rglob("*")):
        if not f.is_file() or f.suffix.lower() not in SCAN_SUFFIXES:
            continue
        scanned += 1
        try:
            text = f.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            print(f"unreadable (failing closed): {f} — {exc}", file=sys.stderr)
            return 2

        rel = f.relative_to(root)

        for token in FORBIDDEN_STORAGE:
            if token in text:
                findings.append(f"{rel}: uses {token} — bearer tokens must not be browser-persisted")

        for m in CREDENTIAL_ASSIGNMENT.finditer(text):
            findings.append(f"{rel}: credential-shaped assignment {m.group(0)[:48]!r}")

        for dev in KNOWN_DEV_VALUES:
            if dev in text:
                findings.append(f"{rel}: development value {dev!r} is baked into the bundle")

    print(f"  scanned {scanned} file(s) under {root}")
    if findings:
        print(f"  FINDINGS: {len(findings)}")
        for line in findings[:20]:
            print(f"    {line}")
        return 1

    print("  no browser token storage, credential assignment, or dev value found")
    return 0


if __name__ == "__main__":
    sys.exit(main())
