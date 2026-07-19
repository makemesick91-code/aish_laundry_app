#!/usr/bin/env python3
"""Validate canonical pricing consistency across docs/ and .claude/rules/.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

SEARCH_DIRS = ["docs", ".claude/rules"]

# Canonical monthly price per plan (as written, without the Rp prefix).
MONTHLY = {
    "starter": "79.000",
    "growth": "199.000",
    "scale": "399.000",
    "enterprise": "999.000",
}
# Canonical annual price per plan.
ANNUAL = {
    "starter": "790.000",
    "growth": "1.990.000",
    "scale": "3.990.000",
}
# Canonical limits per plan: outlet, staff, order/bulan.
LIMITS = {
    "starter": {"outlet": "1", "staff": "5", "order": "1.000"},
    "growth": {"outlet": "3", "staff": "20", "order": "5.000"},
    "scale": {"outlet": "10", "staff": "75", "order": "20.000"},
}

# Every canonical figure that must appear at least once across the corpus.
REQUIRED_FIGURES = [
    "Rp79.000",
    "Rp199.000",
    "Rp399.000",
    "Rp999.000",
    "Rp790.000",
    "Rp1.990.000",
    "Rp3.990.000",
]

TRIAL = re.compile(r"14\s*hari", re.IGNORECASE)
TRIAL_ANY = re.compile(r"(\d+)\s*hari[^\n]{0,20}(?:trial|gratis|percobaan)", re.IGNORECASE)
TRIAL_ANY2 = re.compile(r"trial[^\n]{0,20}?(\d+)\s*hari", re.IGNORECASE)

RP = re.compile(r"Rp\s?([\d][\d.]*)", re.IGNORECASE)
PLAN_NAMES = list(MONTHLY.keys())

ANNUAL_HINT = re.compile(r"/\s*tahun|per\s+tahun|tahunan|annual|yearly", re.IGNORECASE)

LIFETIME = re.compile(r"lifetime|seumur\s+hidup", re.IGNORECASE)
# A negated / prohibitive mention of lifetime is required and allowed.
NEGATION = re.compile(
    r"\b(no|not|never|non|without|tanpa|tidak|bukan|dilarang|forbidden|"
    r"prohibit\w*|larang\w*|menolak|menolak|reject\w*|escalat\w*|eskalasi|"
    r"tolak\w*|hindari|avoid\w*|anti|excluded?|exclude|ban\w*|jangan|"
    r"guardrail\w*|violation|melanggar|pantangan|red\s+flag|must\s+not)\b"
    r"|DEC-0010",
    re.IGNORECASE,
)
# How many preceding lines of context may carry the prohibition (e.g. a
# "The following are forbidden:" heading above a bulleted list).
LIFETIME_CONTEXT_LINES = 15

# A line that DEFINES a plan: the plan name opens the line, a list item, a table
# cell, or a heading, and is not merely the first word of an unrelated phrase
# (e.g. "Enterprise laundry systems" in a competitor comparison table).
PLAN_DEF = re.compile(
    r"^\s{0,3}(?:[-*+]\s+|\|\s*|#{1,6}\s*|\d+[.)]\s+)?"
    r"[*_]{0,2}\s*(starter|growth|scale|enterprise)[*_]{0,2}\s*"
    r"(?:\([^)]*\))?\s*(?:plan|paket)?\s*(?:[:|\-—–]|$)",
    re.IGNORECASE,
)


def corpus(root: Path) -> list[Path]:
    files: list[Path] = []
    for rel in SEARCH_DIRS:
        base = root / rel
        if base.is_dir():
            files.extend(sorted(p for p in base.rglob("*.md") if p.is_file()))
    return files


def main() -> int:
    root = repo_root()
    rep = Reporter("pricing")

    files = corpus(root)
    if not files:
        rep.fail("found markdown files under docs/ and .claude/rules/ to inspect")
        return rep.finish()
    rep.info(f"inspecting {len(files)} markdown files")

    all_text_by_file = {p: read_text(p) for p in files}
    joined = "\n".join(all_text_by_file.values())

    # --- every canonical figure appears at least once ---
    for figure in REQUIRED_FIGURES:
        amount = figure[2:]
        rep.check(
            re.search(rf"Rp\s?{re.escape(amount)}(?![\d.])", joined, re.IGNORECASE)
            is not None,
            f"canonical figure present somewhere: {figure}",
        )

    # --- 14 hari trial ---
    rep.check(bool(TRIAL.search(joined)), "canonical trial length present: 14 hari")

    # --- limits present ---
    for plan, limits in LIMITS.items():
        rep.check(
            re.search(
                rf"{limits['outlet']}\s*outlet", joined, re.IGNORECASE
            ) is not None,
            f"{plan} outlet limit present: {limits['outlet']} outlet",
        )
        rep.check(
            re.search(rf"{limits['staff']}\s*staff", joined, re.IGNORECASE) is not None,
            f"{plan} staff limit present: {limits['staff']} staff",
        )
        rep.check(
            re.search(
                rf"{re.escape(limits['order'])}\s*order", joined, re.IGNORECASE
            ) is not None,
            f"{plan} order limit present: {limits['order']} order",
        )

    # --- per-line conflict detection ---
    conflicts = 0
    for path, text in all_text_by_file.items():
        rel = path.relative_to(root)
        for lineno, line in enumerate(text.splitlines(), start=1):
            low = line.lower()
            # Only PLAN DEFINITION lines are authoritative. Prose or comparison
            # tables that merely mention a plan word are not treated as a price
            # declaration, which avoids false conflicts.
            m_def = PLAN_DEF.match(line)
            if not m_def:
                continue
            plan = m_def.group(1).lower()
            # A second plan name on the same line makes attribution ambiguous.
            if sum(1 for p in PLAN_NAMES if re.search(rf"\b{p}\b", low)) != 1:
                continue
            amounts = [m.group(1) for m in RP.finditer(line)]
            if not amounts:
                continue
            allowed = {MONTHLY[plan]}
            if plan in ANNUAL:
                allowed.add(ANNUAL[plan])
            for amount in amounts:
                if amount in allowed:
                    continue
                conflicts += 1
                rep.fail(
                    f"conflicting price for plan '{plan}': Rp{amount} "
                    f"({rel}:{lineno})"
                )
                rep.info(f"expected one of: {', '.join('Rp' + a for a in sorted(allowed))}")

            # Limit conflicts
            if plan in LIMITS:
                exp = LIMITS[plan]
                m = re.search(r"(?:hingga\s+|up\s+to\s+|maks\w*\s+)?([\d.]+)\s*outlet", low)
                if m and m.group(1) != exp["outlet"]:
                    conflicts += 1
                    rep.fail(
                        f"conflicting outlet limit for '{plan}': {m.group(1)} "
                        f"(expected {exp['outlet']}) ({rel}:{lineno})"
                    )
                m = re.search(r"([\d.]+)\s*staff", low)
                if m and m.group(1) != exp["staff"]:
                    conflicts += 1
                    rep.fail(
                        f"conflicting staff limit for '{plan}': {m.group(1)} "
                        f"(expected {exp['staff']}) ({rel}:{lineno})"
                    )
                m = re.search(r"([\d.]+)\s*order", low)
                if m and m.group(1) != exp["order"]:
                    conflicts += 1
                    rep.fail(
                        f"conflicting order limit for '{plan}': {m.group(1)} "
                        f"(expected {exp['order']}) ({rel}:{lineno})"
                    )

    if conflicts == 0:
        rep.ok("no conflicting plan price or plan limit found on any plan line")

    # --- trial length conflicts ---
    trial_conflicts = 0
    for path, text in all_text_by_file.items():
        rel = path.relative_to(root)
        for lineno, line in enumerate(text.splitlines(), start=1):
            seen: set[str] = set()
            for pattern in (TRIAL_ANY, TRIAL_ANY2):
                for m in pattern.finditer(line):
                    value = m.group(1)
                    if value != "14" and value not in seen:
                        seen.add(value)
                        trial_conflicts += 1
                        rep.fail(
                            f"conflicting trial length: {value} hari "
                            f"(expected 14) ({rel}:{lineno})"
                        )
    if trial_conflicts == 0:
        rep.ok("no conflicting trial length found")

    # --- lifetime must never be offered ---
    lifetime_offers = 0
    for path, text in all_text_by_file.items():
        rel = path.relative_to(root)
        file_lines = text.splitlines()
        for lineno, line in enumerate(file_lines, start=1):
            if not LIFETIME.search(line):
                continue
            if NEGATION.search(line):
                continue  # prohibitive statement: allowed and expected
            # The prohibition may live in the surrounding block, e.g. a
            # "The following are forbidden:" heading above a bulleted list.
            start = max(0, lineno - 1 - LIFETIME_CONTEXT_LINES)
            context = "\n".join(file_lines[start:lineno - 1])
            if NEGATION.search(context):
                continue
            lifetime_offers += 1
            rep.fail(
                f"'lifetime' appears without a prohibition: {rel}:{lineno}"
            )
            rep.info(f"line: {line.strip()}")
    if lifetime_offers == 0:
        rep.ok("no cloud plan is offered as 'lifetime'")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
