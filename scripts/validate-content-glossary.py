#!/usr/bin/env python3
"""Validate the Bahasa Indonesia UX copy glossary.

Every canonical status identifier must map to exactly one user-facing
Indonesian label, and the identifier itself must never leak into user-facing
copy. Indonesian number, currency, weight and time formats are enforced here
too, because a Rupiah rendered with the wrong separator is a money bug wearing
a typography costume.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import (
    CANONICAL_DELIVERY_STATUSES,
    CANONICAL_ORDER_STATUSES,
    CANONICAL_QC_STATUSES,
    DESIGN_DIR,
    read,
)

DOC = f"{DESIGN_DIR}/UX_COPY_GLOSSARY.md"
CONTENT_DOC = f"{DESIGN_DIR}/CONTENT_DESIGN.md"

# Anchors the brief fixes exactly. These are not negotiable translations.
REQUIRED_MAPPINGS = {
    "READY_FOR_PICKUP": "Siap Diambil",
    "OUT_FOR_DELIVERY": "Sedang Diantar",
    "PAYMENT_PENDING": "Belum Lunas",
    "SYNC_CONFLICT": "Perlu Diperiksa",
}

# Indonesian formatting conventions that must be documented.
FORMAT_ANCHORS = {
    "Rupiah thousands separator": [r"Rp\s?79\.000", r"Rp79\.000"],
    "decimal comma for weight": [r"1,5\s?kg"],
    "24-hour time": [r"\b(?:1[3-9]|2[0-3]):[0-5][0-9]\b"],
}

DARK_PATTERNS = [
    "confirmshaming", "dark pattern", "pre-checked", "pre-ticked",
]


def main() -> int:
    root = repo_root()
    rep = Reporter("content design and UX copy glossary")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    content = read(root, CONTENT_DOC)
    rep.check(bool(content), f"{CONTENT_DOC} exists")
    if not text:
        return rep.finish()

    # -- every canonical status has an Indonesian label --------------------
    all_statuses = (
        [("order", s) for s in CANONICAL_ORDER_STATUSES]
        + [("pickup/delivery", s) for s in CANONICAL_DELIVERY_STATUSES]
        + [("quality control", s) for s in CANONICAL_QC_STATUSES]
    )
    missing = []
    for family, status in all_statuses:
        if status not in text:
            missing.append(f"{family}:{status}")
    for m in missing:
        rep.info(f"status not present in the glossary: {m}")
    rep.check(
        not missing,
        f"every canonical status appears in the glossary "
        f"({len(missing)} missing)",
    )

    # Each status must sit on a line that also carries an Indonesian label,
    # i.e. it is genuinely mapped rather than merely mentioned.
    unmapped = []
    lines = text.splitlines()
    for _family, status in all_statuses:
        mapped = False
        for line in lines:
            if status not in line:
                continue
            # Strip the identifier, then require some remaining prose that is
            # not just another SCREAMING_SNAKE identifier.
            remainder = re.sub(r"\b[A-Z][A-Z_]{2,}\b", "", line)
            remainder = re.sub(r"[|`*\-\s]", "", remainder)
            if len(remainder) >= 4:
                mapped = True
                break
        if not mapped:
            unmapped.append(status)
    for u in unmapped:
        rep.info(f"status has no user-facing label on its line: {u}")
    rep.check(
        not unmapped,
        f"every canonical status maps to a user-facing label "
        f"({len(unmapped)} unmapped)",
    )

    # -- the four fixed anchor translations --------------------------------
    for identifier, label in sorted(REQUIRED_MAPPINGS.items()):
        found = any(identifier in line and label in line for line in lines)
        rep.check(found, f"'{identifier}' maps to '{label}'")

    # -- the identifier/label separation is stated -------------------------
    low = text.lower()
    rep.check(
        "identifier" in low and ("label" in low or "bahasa" in low),
        "the glossary distinguishes canonical identifiers from user-facing labels",
    )
    rep.check(
        bool(re.search(r"(never|not|no)[^.\n]{0,90}"
                       r"(shown to|user-facing|displayed|surface)", low)),
        "the glossary states that a canonical identifier is never shown to a user",
    )

    # -- Indonesian formatting ----------------------------------------------
    combined = text + "\n" + content
    for name, patterns in sorted(FORMAT_ANCHORS.items()):
        rep.check(
            any(re.search(p, combined) for p in patterns),
            f"the {name} convention is documented with an example",
        )
    low_combined = combined.lower()
    rep.check("asia/jakarta" in low_combined or "outlet timezone" in low_combined
              or "zona waktu" in low_combined,
              "the timezone convention is documented")
    rep.check(
        "utc" in low_combined,
        "the rule that timestamps are stored in UTC is documented",
    )
    rep.check(
        "integer rupiah" in low_combined,
        "the integer-Rupiah rule is restated verbatim in the content layer",
    )
    # Floating point must appear only where it is being forbidden.
    float_leaks = []
    for line in combined.splitlines():
        low_line = line.lower()
        if not re.search(r"\b(floating[- ]point|float|double)\b", low_line):
            continue
        if not any(g in low_line for g in
                   ("never", "not ", "no ", "forbidden", "prohibit",
                    "must not", "avoid", "reject", "dilarang")):
            float_leaks.append(line.strip()[:110])
    for leak in float_leaks:
        rep.info(leak)
    rep.check(
        not float_leaks,
        "floating point is named only in order to forbid it in a money path",
    )

    # -- no dark patterns ----------------------------------------------------
    # A prohibition is often stated once in the section or table heading and
    # then the forbidden patterns are simply listed beneath it. Look at the
    # enclosing context, not only the line itself.
    guard = ("never", "not ", "no ", "forbidden", "prohibit", "must not",
             "avoid", "reject", "anti-pattern", "instead", "dilarang")
    unguarded = []
    lines_all = combined.splitlines()
    for index, line in enumerate(lines_all):
        low_line = line.lower()
        for pattern in DARK_PATTERNS:
            if pattern not in low_line:
                continue
            context = " ".join(
                lines_all[max(0, index - 8):index + 1]
            ).lower()
            if not any(g in context for g in guard):
                unguarded.append(line.strip()[:110])
    for u in unguarded:
        rep.info(u)
    rep.check(not unguarded,
              "dark patterns are named only in order to forbid them")
    rep.check(
        any(p in low_combined for p in DARK_PATTERNS),
        "the content design system explicitly forbids dark patterns",
    )

    # -- error copy must explain recovery ----------------------------------
    rep.check(
        bool(re.search(r"(recovery|what to do|next step|langkah)", low_combined)),
        "error copy is required to explain recovery",
    )
    rep.check(
        "terjadi kesalahan" in low_combined,
        "the generic-error anti-pattern is called out explicitly",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
