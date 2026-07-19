#!/usr/bin/env python3
"""Validate decision records DEC-0001..DEC-0015 in docs/decisions/.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

FIRST_DEC = 1
LAST_DEC = 15

# label -> list of accepted heading keywords (lowercase, substring match)
REQUIRED_HEADINGS: list[tuple[str, list[str]]] = [
    ("ID", ["id"]),
    ("Title", ["title", "judul"]),
    ("Status", ["status"]),
    ("Date", ["date", "tanggal"]),
    ("Context", ["context", "konteks"]),
    ("Decision", ["decision", "keputusan"]),
    ("Consequences", ["consequences", "konsekuensi"]),
    ("Positive consequences", ["positive consequences", "positive", "konsekuensi positif"]),
    (
        "Negative consequences / trade-offs",
        ["negative consequences", "negative", "trade-off", "tradeoff",
         "konsekuensi negatif"],
    ),
    ("Verification", ["verification", "verifikasi"]),
    ("Supersession policy", ["supersession", "supersede", "penggantian"]),
    (
        "Related Master Source sections",
        ["related master source", "master source section", "master source"],
    ),
]

DEC_FILE = re.compile(r"^DEC-(\d{4})(?:-.+)?\.md$")
# Markdown heading, definition-style bold label, or "Label:" line.
HEADING_LINE = re.compile(r"^\s{0,3}(?:#{1,6}\s*)?(?:[*_]{0,2})([^:#*_\n][^:#\n]*?)(?:[*_]{0,2})\s*:?\s*$")
LABELED_LINE = re.compile(r"^\s{0,3}(?:#{1,6}\s*)?(?:[-*+]\s*)?(?:[*_]{0,2})([A-Za-z][^:*_\n]{0,80}?)(?:[*_]{0,2})\s*:")


def extract_labels(text: str) -> list[str]:
    """Collect candidate heading/label strings from a markdown document."""
    labels: list[str] = []
    for line in text.splitlines():
        stripped = line.strip()
        if not stripped:
            continue
        if stripped.startswith("#"):
            labels.append(re.sub(r"^#+\s*", "", stripped).strip(" *_:").lower())
            continue
        m = LABELED_LINE.match(line)
        if m:
            labels.append(m.group(1).strip(" *_-").lower())
            continue
        m = HEADING_LINE.match(line)
        if m and (stripped.startswith("**") or stripped.startswith("__")):
            labels.append(m.group(1).strip(" *_:").lower())
    return labels


def main() -> int:
    root = repo_root()
    rep = Reporter("decisions")

    dec_dir = root / "docs" / "decisions"
    if not rep.check(dec_dir.is_dir(), "docs/decisions/ exists"):
        return rep.finish()

    found: dict[int, list[Path]] = {}
    for entry in sorted(dec_dir.iterdir()):
        if not entry.is_file() or not entry.name.endswith(".md"):
            continue
        m = DEC_FILE.match(entry.name)
        if not m:
            rep.fail(f"unexpected file in docs/decisions/: {entry.name}")
            continue
        found.setdefault(int(m.group(1)), []).append(entry)

    # ID coverage
    for n in range(FIRST_DEC, LAST_DEC + 1):
        paths = found.get(n, [])
        if len(paths) == 1:
            rep.ok(f"DEC-{n:04d} present exactly once ({paths[0].name})")
        elif not paths:
            rep.fail(f"DEC-{n:04d} MISSING")
        else:
            rep.fail(
                f"DEC-{n:04d} duplicated: " + ", ".join(p.name for p in paths)
            )
    for n in sorted(found):
        if not (FIRST_DEC <= n <= LAST_DEC):
            rep.fail(
                f"unexpected decision ID DEC-{n:04d}: "
                + ", ".join(p.name for p in found[n])
            )

    # Content checks
    for n in range(FIRST_DEC, LAST_DEC + 1):
        paths = found.get(n, [])
        if len(paths) != 1:
            continue
        path = paths[0]
        text = read_text(path)
        labels = extract_labels(text)
        lower_text = text.lower()

        # Status ACCEPTED
        status_ok = re.search(
            r"status\W{0,20}accepted\b", lower_text
        ) is not None or re.search(r"^\s*accepted\s*$", lower_text, re.MULTILINE) is not None
        rep.check(status_ok, f"DEC-{n:04d} status is ACCEPTED")

        missing = []
        for label, keywords in REQUIRED_HEADINGS:
            hit = any(any(k in lab for k in keywords) for lab in labels)
            if not hit:
                # fall back to plain-text presence of the primary keyword
                hit = keywords[0] in lower_text
            if not hit:
                missing.append(label)
        if missing:
            rep.fail(
                f"DEC-{n:04d} missing required headings: {', '.join(missing)}"
            )
        else:
            rep.ok(f"DEC-{n:04d} has all {len(REQUIRED_HEADINGS)} required headings")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
