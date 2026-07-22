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
# DEC-0024 (Step 3 runtime introduction and runtime scope guard transition) added
# at Master Source 1.4.0. Raising this bound WIDENS coverage — every record up to
# LAST_DEC must exist and be well-formed — so it can never be used to skip a record.
#
# Raised 27 -> 30 at Master Source 1.4.1 for DEC-0028 (Step 4 scope resolution and
# canonical authorization), DEC-0029 (canonical status drift remediation and
# cross-document validation), and DEC-0030 (Step 4 runtime scope transition).
LAST_DEC = 34

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

    check_master_source_index(root, rep, set(found))

    return rep.finish()


# ---------------------------------------------------------------------------
# Master Source §31 index agreement (DEC-0029).
#
# THE GAP THIS CLOSES: nothing compared MASTER_SOURCE.md §31 against the directory
# it describes. §31 accordingly declared "Twenty-four decisions are locked" and
# listed DEC-0001 … DEC-0024 while DEC-0025, DEC-0026, and DEC-0027 already existed
# as accepted records — the third instance of the same drift class as the stale §24
# roadmap table and the self-contradicting STATUS.md infrastructure rows.
#
# Checked in BOTH directions. A record on disk but absent from §31 is an
# understated canonical index; a row in §31 with no record is a fabricated one.
# ---------------------------------------------------------------------------
MASTER = "docs/MASTER_SOURCE.md"
INDEX_ROW = re.compile(r"^\|\s*DEC-(\d{4})\s*\|")


def check_master_source_index(root, rep, on_disk: set[int]) -> None:
    """MASTER_SOURCE §31 must list exactly the decision records that exist."""
    rep.info("--- Master Source §31 decision index (DEC-0029) ---")
    path = root / MASTER
    if not rep.check(path.is_file(), f"{MASTER} exists"):
        return

    listed: set[int] = set()
    duplicates: list[int] = []
    for line in read_text(path).splitlines():
        m = INDEX_ROW.match(line.strip())
        if not m:
            continue
        n = int(m.group(1))
        if n in listed:
            duplicates.append(n)
        listed.add(n)

    rep.check(not duplicates,
              f"{MASTER} §31 lists no decision twice (duplicates: {sorted(duplicates)})")

    unlisted = sorted(on_disk - listed)
    rep.check(
        not unlisted,
        f"every decision record on disk is listed in {MASTER} §31 "
        f"(unlisted: {[f'DEC-{n:04d}' for n in unlisted]})",
    )

    phantom = sorted(listed - on_disk)
    rep.check(
        not phantom,
        f"every decision listed in {MASTER} §31 has a record on disk "
        f"(phantom: {[f'DEC-{n:04d}' for n in phantom]})",
    )

    # The prose count must match the table. A table extended without updating the
    # sentence above it is how "Twenty-four decisions are locked" outlived DEC-0027.
    text = read_text(path)
    words = {
        "fifteen": 15, "sixteen": 16, "seventeen": 17, "eighteen": 18,
        "nineteen": 19, "twenty": 20, "twenty-one": 21, "twenty-two": 22,
        "twenty-three": 23, "twenty-four": 24, "twenty-five": 25,
        "twenty-six": 26, "twenty-seven": 27, "twenty-eight": 28,
        "twenty-nine": 29, "thirty": 30, "thirty-one": 31, "thirty-two": 32,
        "thirty-three": 33, "thirty-four": 34, "thirty-five": 35,
    }
    m = re.search(r"^([A-Za-z-]+) decisions are locked\b", text, re.MULTILINE)
    if m is None:
        rep.fail(f"{MASTER} §31 states how many decisions are locked")
        return
    claimed = words.get(m.group(1).lower())
    if claimed is None:
        rep.fail(f"{MASTER} §31 count word not recognised: {m.group(1)!r}")
        return
    rep.check(
        claimed == len(listed),
        f"{MASTER} §31 prose count ({m.group(1)} = {claimed}) matches the "
        f"{len(listed)} rows in its table",
    )


if __name__ == "__main__":
    run_main(main)
