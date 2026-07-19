#!/usr/bin/env python3
"""Validate docs/MASTER_SOURCE.md content, size, and recorded SHA-256 digest.

Standard library only.
"""

from __future__ import annotations

import hashlib
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

MASTER = "docs/MASTER_SOURCE.md"
DIGEST_FILE = "docs/MASTER_SOURCE.sha256"

PRODUCT_NAME = "Aish Laundry App"
VERSION = "1.0.0"
MIN_LINES = 400

# Baseline date accepted in a few equivalent renderings.
BASELINE_DATE_PATTERNS = [
    r"19\s+Juli\s+2026",
    r"19\s+July\s+2026",
    r"2026-07-19",
    r"19/07/2026",
]

# Required topic sections. Each entry: (label, [accepted keywords]).
REQUIRED_TOPICS: list[tuple[str, list[str]]] = [
    ("multi-tenancy", ["multi-tenan", "multitenan"]),
    ("platforms", ["platform"]),
    ("architecture", ["arsitektur", "architecture"]),
    ("roles", ["role", "peran"]),
    ("tracking", ["tracking"]),
    ("pickup and delivery", ["pickup"]),
    ("unclaimed laundry", ["unclaimed", "menumpuk"]),
    ("offline-first", ["offline"]),
    ("notifications", ["notif"]),
    ("security", ["security", "keamanan"]),
    ("financial integrity", ["financial", "finansial", "keuangan"]),
    ("privacy", ["privacy", "privasi"]),
    ("UX", ["ux", "user experience", "pengalaman pengguna"]),
    ("performance", ["performance", "performa", "kinerja"]),
    ("observability", ["observability", "observabilitas"]),
    ("pricing", ["pricing", "harga", "paket"]),
    ("MVP", ["mvp"]),
    ("non-goals", ["non-goal", "non goal", "bukan tujuan"]),
    ("roadmap", ["roadmap", "peta jalan"]),
    ("definition of done", ["definition of done", "dod"]),
    ("git policy", ["git"]),
    ("testing", ["testing", "pengujian", "test"]),
    ("metrics", ["metric", "metrik"]),
    ("positioning", ["positioning", "posisi"]),
    ("changelog", ["changelog"]),
]

# Any "<Something> Laundry App" that is not the canonical name is a competing name.
COMPETING_NAME = re.compile(r"\b([A-Z][A-Za-z0-9_]*)\s+Laundry\s+App\b")
# Names that are legitimately not competing product names.
ALLOWED_NAME_PREFIXES = {"Aish"}


def main() -> int:
    root = repo_root()
    rep = Reporter("master-source")

    master_path = root / MASTER
    if not rep.check(master_path.is_file(), f"{MASTER} exists"):
        return rep.finish()

    text = read_text(master_path)
    lines = text.splitlines()
    lower = text.lower()

    # --- version ---
    rep.check(
        re.search(r"(?<!\d)" + re.escape(VERSION) + r"(?!\d)", text) is not None,
        f"declares Master Source version {VERSION}",
    )

    # --- baseline date ---
    date_hit = next(
        (p for p in BASELINE_DATE_PATTERNS if re.search(p, text, re.IGNORECASE)),
        None,
    )
    if date_hit:
        rep.ok(f"declares baseline date 19 July 2026 (matched /{date_hit}/)")
    else:
        rep.fail("declares baseline date 19 July 2026")

    # --- product name ---
    rep.check(PRODUCT_NAME in text, f'contains canonical product name "{PRODUCT_NAME}"')

    competing = sorted(
        {
            m.group(1)
            for m in COMPETING_NAME.finditer(text)
            if m.group(1) not in ALLOWED_NAME_PREFIXES
        }
    )
    if competing:
        rep.fail(
            "no competing canonical product name; found: "
            + ", ".join(f'"{c} Laundry App"' for c in competing)
        )
    else:
        rep.ok("no competing canonical product name")

    # --- topic sections ---
    for label, keywords in REQUIRED_TOPICS:
        hit = any(k in lower for k in keywords)
        rep.check(hit, f"covers topic: {label}")

    # --- substance ---
    rep.check(
        len(lines) >= MIN_LINES,
        f"is substantial: {len(lines)} lines (minimum {MIN_LINES})",
    )

    # --- SHA-256 digest ---
    digest_path = root / DIGEST_FILE
    if not rep.check(digest_path.is_file(), f"{DIGEST_FILE} exists"):
        return rep.finish()

    actual = hashlib.sha256(master_path.read_bytes()).hexdigest()
    recorded = None
    for raw in read_text(digest_path).splitlines():
        raw = raw.strip()
        if not raw or raw.startswith("#"):
            continue
        m = re.match(r"^([0-9a-fA-F]{64})\s+[*]?(\S.*)$", raw)
        if m:
            recorded = (m.group(1).lower(), m.group(2).strip())
            break
        m = re.match(r"^([0-9a-fA-F]{64})$", raw)
        if m:
            recorded = (m.group(1).lower(), MASTER)
            break

    if recorded is None:
        rep.fail(f"{DIGEST_FILE} contains a parseable sha256sum line")
        return rep.finish()

    recorded_hex, recorded_path = recorded
    rep.info(f"recorded path: {recorded_path}")
    rep.check(
        Path(recorded_path).name == Path(MASTER).name,
        f"digest line refers to MASTER_SOURCE.md (got: {recorded_path})",
    )
    if recorded_hex == actual:
        rep.ok(f"SHA-256 of {MASTER} matches recorded digest ({actual[:16]}...)")
    else:
        rep.fail("SHA-256 of MASTER_SOURCE.md matches recorded digest")
        rep.info(f"recorded: {recorded_hex}")
        rep.info(f"actual:   {actual}")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
