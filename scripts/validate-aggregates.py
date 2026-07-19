#!/usr/bin/env python3
"""Validate the aggregate catalogue and the entity/value-object catalogue.

Every aggregate must be catalogued, must carry tenant ownership, and the Step 1
conceptual model must not be presented as a database schema. Standard library
only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import AGGREGATES, read  # noqa: E402

AGG_DOC = "docs/domain/AGGREGATE_CATALOG.md"
EVO_DOC = "docs/domain/ENTITY_AND_VALUE_OBJECT_CATALOG.md"

VALUE_OBJECTS = [
    "TenantId",
    "UserId",
    "OutletId",
    "OrderId",
    "HumanOrderNumber",
    "ClientReference",
    "Money",
    "Weight",
    "Quantity",
    "PhoneNumber",
    "MaskedPhoneNumber",
    "Address",
    "GeoPoint",
    "TimeWindow",
    "OrderStatus",
    "PaymentStatus",
    "DeliveryStatus",
    "TrackingTokenHash",
    "IdempotencyKey",
    "ReasonCode",
    "NotificationChannel",
    "NotificationPreference",
    "ConsentState",
    "AuditActor",
    "Version",
]

REQUIRED_FACETS = [
    ("aggregate root", ["aggregate root"]),
    ("entities", ["entit"]),
    ("value objects", ["value object"]),
    ("commands", ["command"]),
    ("invariants", ["invariant"]),
    ("domain events", ["event"]),
    ("tenant ownership", ["tenant"]),
    ("concurrency", ["concurren"]),
    ("idempotency", ["idempot"]),
    ("retention", ["retention"]),
    ("sensitive fields", ["sensitive"]),
    ("deletion or reversal policy", ["reversal", "deletion"]),
]

SCHEMA_MARKER = "CONCEPTUAL DOMAIN MODEL — NOT DATABASE SCHEMA"

# Step 1 must not drift into physical database design.
SCHEMA_LEAK = [
    (r"\bCREATE\s+TABLE\b", "SQL CREATE TABLE"),
    (r"\bALTER\s+TABLE\b", "SQL ALTER TABLE"),
    (r"\bVARCHAR\s*\(", "SQL column type"),
    (r"\bmigration\s+file\b", "a migration file"),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("aggregates")

    path = root / AGG_DOC
    if not rep.check(path.is_file(), f"{AGG_DOC} exists"):
        return rep.finish()

    text = read(path)

    rep.check(
        len(AGGREGATES) == 31,
        f"the canonical aggregate set is thirty-one (declared {len(AGGREGATES)})",
    )

    for name in AGGREGATES:
        rep.check(re.search(rf"\b{re.escape(name)}\b", text) is not None,
                  f"aggregate catalogued: {name}")

    lower = text.lower()
    for label, keywords in REQUIRED_FACETS:
        rep.check(any(k in lower for k in keywords), f"catalogue documents facet: {label}")

    # --- tenant ownership is stated, not assumed ---
    rep.check(
        re.search(r"tenant\s+ownership", text, re.IGNORECASE) is not None,
        "catalogue states tenant ownership explicitly",
    )

    # --- entity and value object catalogue ---
    evo_path = root / EVO_DOC
    if rep.check(evo_path.is_file(), f"{EVO_DOC} exists"):
        evo = read(evo_path)
        missing = [
            vo for vo in VALUE_OBJECTS
            if re.search(rf"\b{re.escape(vo)}\b", evo) is None
        ]
        if missing:
            rep.fail(f"all {len(VALUE_OBJECTS)} canonical value objects catalogued")
            for name in missing[:12]:
                rep.info(f"missing value object: {name}")
        else:
            rep.ok(f"all {len(VALUE_OBJECTS)} canonical value objects catalogued")

    # --- conceptual, not physical ---
    combined = text + "\n" + (read(evo_path) if evo_path.is_file() else "")
    rep.check(
        SCHEMA_MARKER in combined,
        f"conceptual model carries the marker: {SCHEMA_MARKER}",
    )
    for pattern, label in SCHEMA_LEAK:
        hit = re.search(pattern, combined, re.IGNORECASE)
        if hit:
            rep.fail(f"Step 1 domain model contains no {label}")
            rep.info(f"matched: {hit.group(0)!r}")
        else:
            rep.ok(f"Step 1 domain model contains no {label}")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
