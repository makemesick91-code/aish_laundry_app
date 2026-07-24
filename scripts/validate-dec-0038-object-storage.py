#!/usr/bin/env python3
"""Validate DEC-0038 — the private object-storage introduction (FR-083).

Standard library only.

DEC-0038 records the owner-authorised introduction of the FIRST private
object-storage surface: an S3-compatible private abstraction, MinIO (digest-pinned,
loopback-bound) for development and CI, private buckets, no anonymous access, no
permanent public URLs, application-level-authorized signed-URL retrieval, random
keys, content-based validation, append-only audit, and idempotency.

This validator is the governance gate for that record. It does NOT re-run the
FR-083 runtime tests (that is `verify-step-06.sh` against real MinIO). It proves
the DECISION is present, indexed, locks the private S3-compatible contract, does
NOT authorise public buckets / permanent public URLs / Step 8 / deployment, and is
cross-referenced by the FR-083 evidence — so the architecture cannot be quietly
loosened without a superseding record.

It is adversarially tested by `scripts/test-step-06-validators.sh` before it is
relied upon as a gate (Rule 33, Rule 47).

Exit 0 = PASS, 1 = FAIL.
"""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

DECISION = "docs/decisions/DEC-0038-step-06-private-object-storage-introduction.md"
MASTER = "docs/MASTER_SOURCE.md"
EVIDENCE = "evidence/step-06/README.md"

# Locked private-object-storage contract phrases the record MUST carry. Removing
# any of these from the record loosens the canonical architecture and fails here.
LOCKED_PHRASES: list[tuple[str, str]] = [
    ("S3-compatible abstraction", "s3-compatible"),
    ("MinIO for dev/CI, digest-pinned", "digest-pinned"),
    ("MinIO named", "minio"),
    ("loopback-bound development store", "loopback-bound"),
    ("buckets remain private", "buckets remain private"),
    ("no anonymous object access", "no anonymous"),
    ("no permanent public URLs", "no permanent public url"),
    ("signed-URL retrieval", "signed url"),
    ("random, non-guessable keys", "non-guessable"),
    ("content-based validation", "content-based"),
    ("checksum verification", "checksum"),
    ("idempotency", "idempoten"),
    ("append-only evidence audit", "append-only"),
    ("encrypted offline queue for pending uploads", "encrypted offline queue"),
]

# Boundary statements the record MUST carry: what it does NOT authorise. Losing any
# of these would let the record read as authorising more than the owner granted.
BOUNDARY_PHRASES: list[tuple[str, str]] = [
    ("Step 8 is not authorised", "does not implement or authorise step 8"),
    ("deployment remains ABSENT", "deployment remains absent"),
    ("public buckets require a new decision record", "public bucket"),
    ("a superseding change requires a new decision record", "new decision record"),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("dec-0038-object-storage")

    # --- the decision record exists and is ACCEPTED ---
    dec_path = root / DECISION
    if not rep.check(dec_path.is_file(), f"{DECISION} exists"):
        return rep.finish()
    dec_lower = read_text(dec_path).lower()
    # Collapse all runs of whitespace (including line wraps) to a single space so a
    # multi-word lock phrase still matches when Markdown reflow split it across lines.
    dec_norm = " ".join(dec_lower.split())
    rep.check("status" in dec_lower and "accepted" in dec_lower,
              "DEC-0038 record is ACCEPTED")

    # --- the record is indexed in Master Source §31 ---
    master_path = root / MASTER
    if rep.check(master_path.is_file(), f"{MASTER} exists"):
        master_text = read_text(master_path)
        rep.check(
            any(line.strip().startswith("| DEC-0038 ")
                for line in master_text.splitlines()),
            "DEC-0038 is listed in Master Source §31 decision table",
        )

    # --- the record locks the private S3-compatible contract ---
    for label, needle in LOCKED_PHRASES:
        rep.check(needle in dec_norm,
                  f"DEC-0038 locks: {label}")

    # --- the record fixes the boundaries it does NOT authorise ---
    for label, needle in BOUNDARY_PHRASES:
        rep.check(needle in dec_norm,
                  f"DEC-0038 boundary: {label}")

    # --- the FR-083 evidence cross-references the canonical decision ---
    ev_path = root / EVIDENCE
    if rep.check(ev_path.is_file(), f"{EVIDENCE} exists"):
        rep.check("dec-0038" in read_text(ev_path).lower(),
                  "FR-083 evidence references the canonical decision DEC-0038")

    # --- structural non-widening: the canonical current step is still 6, so the
    #     runtime-scope guard still forbids Step 8 (delivery/proof) and beyond.
    #     DEC-0038 must not have advanced the step or widened the guard. ---
    try:
        import _common  # noqa: WPS433 (already on sys.path)
        step = int(_common.CANONICAL_CURRENT_STEP)
        rep.check(step == 6,
                  f"canonical current step is 6 (Step 8+ still forbidden); got {step}")
    except Exception as exc:  # pragma: no cover - defensive
        rep.fail(f"could not read _common.CANONICAL_CURRENT_STEP: {exc}")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
