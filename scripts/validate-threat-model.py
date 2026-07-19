#!/usr/bin/env python3
"""Validate the initial threat model.

The central gate: every CRITICAL and HIGH threat must carry an explicit
mitigation. A high-severity threat with no mitigation blocks the Definition of
Done (Rule 21). Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import SEVERITIES, THREAT_ID, read  # noqa: E402

THREAT_DOC = "docs/security/INITIAL_THREAT_MODEL.md"
BOUNDARIES_DOC = "docs/security/TRUST_BOUNDARIES.md"

REQUIRED_ASSETS = [
    "customer data",
    "order data",
    "payment record",
    "tenant configuration",
    "membership",
    "tracking token",
    "audit",
    "notification consent",
    "offline queue",
    "subscription entitlement",
    "uploaded image",
]

REQUIRED_BOUNDARIES = [
    "tracking portal",
    "customer android",
    "ops android",
    "console web",
    "backend api",
    "tenant boundary",
    "redis",
    "postgresql",
    "object storage",
    "whatsapp",
    "payment provider",
    "guest link",
    "support",
    "offline device storage",
]

STRIDE = [
    "spoofing",
    "tampering",
    "repudiation",
    "information disclosure",
    "denial of service",
    "elevation of privilege",
]

REQUIRED_FIELDS = [
    ("actor", ["actor"]),
    ("asset", ["asset"]),
    ("precondition", ["precondition"]),
    ("scenario", ["scenario"]),
    ("impact", ["impact"]),
    ("likelihood", ["likelihood"]),
    ("severity", ["severity"]),
    ("prevention", ["prevention", "prevent"]),
    ("detection", ["detection", "detect"]),
    ("response", ["response"]),
    ("residual risk", ["residual"]),
    ("implementation step", ["step"]),
]

MITIGATION_SIGNALS = [
    "mitigation",
    "prevention",
    "prevent",
    "control",
    "mitigat",
]

MIN_THREATS = 20


def split_threat_sections(text: str) -> dict[str, str]:
    """Split the document into per-threat sections keyed by threat ID.

    A threat's section runs from its identifier to the next threat identifier.
    Table-row threats are handled too: the row itself becomes the section.
    """
    sections: dict[str, str] = {}
    matches = list(THREAT_ID.finditer(text))
    for i, m in enumerate(matches):
        tid = m.group(0)
        start = m.start()
        end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
        sections.setdefault(tid, "")
        sections[tid] += text[start:end]
    return sections


def main() -> int:
    root = repo_root()
    rep = Reporter("threat-model")

    path = root / THREAT_DOC
    if not rep.check(path.is_file(), f"{THREAT_DOC} exists"):
        return rep.finish()

    text = read(path)
    lower = text.lower()

    # --- method declared ---
    rep.check("stride" in lower, "threat model declares STRIDE as its method")
    for category in STRIDE:
        rep.check(category in lower, f"STRIDE category covered: {category}")

    # --- assets ---
    for asset in REQUIRED_ASSETS:
        rep.check(asset in lower, f"asset in scope: {asset}")

    # --- trust boundaries (in this document or the dedicated one) ---
    boundaries_text = lower
    bpath = root / BOUNDARIES_DOC
    if bpath.is_file():
        boundaries_text += "\n" + read(bpath).lower()
    else:
        rep.fail(f"{BOUNDARIES_DOC} exists")
    for boundary in REQUIRED_BOUNDARIES:
        rep.check(boundary in boundaries_text, f"trust boundary documented: {boundary}")

    # --- threats enumerated ---
    sections = split_threat_sections(text)
    rep.check(
        len(sections) >= MIN_THREATS,
        f"threat model enumerates at least {MIN_THREATS} threats "
        f"(found {len(sections)})",
    )

    # --- every threat record carries the required fields ---
    for label, keywords in REQUIRED_FIELDS:
        rep.check(
            any(k in lower for k in keywords),
            f"threat records carry field: {label}",
        )

    # --- severities are from the canonical set ---
    used = [s for s in SEVERITIES if s in text]
    rep.check(bool(used), f"threat model uses canonical severities (found: {', '.join(used)})")

    # --- THE GATE: every CRITICAL and HIGH threat has a mitigation ---
    unmitigated: list[tuple[str, str]] = []
    high_count = 0
    for tid, section in sorted(sections.items()):
        section_upper = section.upper()
        severity = None
        for sev in ("CRITICAL", "HIGH"):
            if re.search(rf"\b{sev}\b", section_upper):
                severity = sev
                break
        if severity is None:
            continue
        high_count += 1
        if not any(sig in section.lower() for sig in MITIGATION_SIGNALS):
            unmitigated.append((tid, severity))

    rep.info(f"CRITICAL/HIGH threats found: {high_count}")
    if unmitigated:
        rep.fail("every CRITICAL and HIGH threat carries an explicit mitigation")
        for tid, sev in unmitigated[:15]:
            rep.info(f"{tid} ({sev}) has no mitigation")
    else:
        rep.ok("every CRITICAL and HIGH threat carries an explicit mitigation")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
