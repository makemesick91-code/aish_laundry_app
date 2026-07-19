#!/usr/bin/env python3
"""Validate .claude/rules/ coverage and docs/GOVERNANCE_TRACEABILITY.md references.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

RULES_DIR = ".claude/rules"
TRACEABILITY = "docs/GOVERNANCE_TRACEABILITY.md"
EXPECTED_RULE_COUNT = 25

RULE_FILE = re.compile(r"^(\d{2})-(.+)\.md$")

# rule number -> (topic label, [keywords, any of which satisfies the check])
TOPIC_REQUIREMENTS: dict[str, tuple[str, list[str]]] = {
    "02": ("tenant isolation hard gate", ["tenant isolation", "isolasi tenant", "tenant_id"]),
    "04": (
        "financial integrity hard gate",
        ["financial integrity", "integritas finansial", "integritas keuangan"],
    ),
    "07": ("offline-first", ["offline"]),
    "08": ("WhatsApp", ["whatsapp"]),
    "09": ("pickup and delivery", ["pickup", "penjemputan"]),
    "10": ("aging / H+1 H+3 H+7", ["h+1", "h+3", "h+7", "aging", "menumpuk"]),
    "11": ("git and CI", ["git", " ci", "continuous integration"]),
    "12": ("autonomous execution", ["autonomous", "otonom", "eksekusi mandiri"]),
    "14": ("pricing", ["pricing", "harga", "paket"]),
    "15": ("status", ["status"]),
}

HARD_GATE_WORDS = ["hard gate", "no-go", "no go", "automatic no-go", "gerbang keras"]

# Tracking foundation may live in rule 03 or any tracking-named rule.
TRACKING_KEYWORDS = ["tracking", "pelacakan"]


def main() -> int:
    root = repo_root()
    rep = Reporter("rules-traceability")

    rules_dir = root / RULES_DIR
    if not rep.check(rules_dir.is_dir(), f"{RULES_DIR}/ exists"):
        return rep.finish()

    by_number: dict[str, Path] = {}
    for entry in sorted(rules_dir.iterdir()):
        if not entry.is_file() or not entry.name.endswith(".md"):
            continue
        m = RULE_FILE.match(entry.name)
        if m:
            by_number.setdefault(m.group(1), entry)

    rep.check(
        len(by_number) == EXPECTED_RULE_COUNT,
        f"all {EXPECTED_RULE_COUNT} rule files exist (found {len(by_number)})",
    )
    # Rules are numbered 00..24 (twenty-five files).
    for n in range(0, EXPECTED_RULE_COUNT):
        key = f"{n:02d}"
        rep.check(key in by_number, f"rule {key} file exists")

    contents = {k: read_text(p).lower() for k, p in by_number.items()}

    # --- topic placement ---
    for key, (label, keywords) in TOPIC_REQUIREMENTS.items():
        path = by_number.get(key)
        if path is None:
            rep.fail(f"rule {key} covers {label} (file missing)")
            continue
        body = contents[key]
        hit = any(k in body for k in keywords)
        rep.check(hit, f"rule {key} ({path.name}) covers {label}")

    # --- hard gate language ---
    for key, label in (("02", "tenant isolation"), ("04", "financial integrity")):
        body = contents.get(key, "")
        rep.check(
            any(w in body for w in HARD_GATE_WORDS),
            f"rule {key} states {label} as a hard gate / automatic NO-GO",
        )

    # --- tracking foundation in rule 03 or a tracking-named rule ---
    tracking_sources = []
    if "03" in contents and any(k in contents["03"] for k in TRACKING_KEYWORDS):
        tracking_sources.append(by_number["03"].name)
    for key, path in by_number.items():
        if any(k in path.name.lower() for k in TRACKING_KEYWORDS) and any(
            k in contents[key] for k in TRACKING_KEYWORDS
        ):
            tracking_sources.append(path.name)
    if tracking_sources:
        rep.ok(
            "tracking foundation covered by: "
            + ", ".join(sorted(set(tracking_sources)))
        )
    else:
        rep.fail("tracking foundation covered by rule 03 or a tracking-named rule")

    # --- traceability document references every rule filename ---
    trace_path = root / TRACEABILITY
    if not rep.check(trace_path.is_file(), f"{TRACEABILITY} exists"):
        return rep.finish()
    trace = read_text(trace_path)
    for key in sorted(by_number):
        name = by_number[key].name
        rep.check(name in trace, f"{TRACEABILITY} references {name}")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
