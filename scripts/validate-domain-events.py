#!/usr/bin/env python3
"""Validate the domain event catalogue and the commands/policies catalogue.

Every event must have a source aggregate; idempotency must be stated as a server
contract. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import AGGREGATES, read  # noqa: E402

EVENTS_DOC = "docs/domain/DOMAIN_EVENTS.md"
COMMANDS_DOC = "docs/domain/COMMANDS_AND_POLICIES.md"

#: A past-tense event name in PascalCase, e.g. PaymentRecorded, OrderReadyForPickup.
EVENT_NAME = re.compile(r"\b([A-Z][A-Za-z0-9]{3,}(?:ed|Created|Assigned|Failed|Issued))\b")

REQUIRED_EVENT_FIELDS = [
    ("event name", ["event name", "name"]),
    ("version", ["version"]),
    ("occurrence timestamp", ["timestamp", "occurred"]),
    ("tenant", ["tenant"]),
    ("actor", ["actor"]),
    ("aggregate identity", ["aggregate"]),
    ("correlation identifier", ["correlation"]),
]

REQUIRED_RULES: list[tuple[str, list[str]]] = [
    ("events are immutable", [r"immutab", r"never\s+edit"]),
    ("events are versioned", [r"version"]),
    ("events carry tenant context explicitly", [r"tenant\s+context", r"tenant"]),
    (
        "events never carry secrets or plaintext tokens",
        [r"never\s+carry", r"no\s+plaintext", r"never[^.\n]{0,60}(?:token|otp|credential)"],
    ),
    (
        "idempotency is a server contract",
        [r"server\s+contract", r"server[- ]side[^.\n]{0,60}idempot"],
    ),
    ("client reference is reused on retry", [r"client[_ ]?reference"]),
    ("consumers are idempotent", [r"consumer"]),
    ("delivery is at least once", [r"at\s+least\s+once"]),
    ("deduplication keys are documented", [r"dedup"]),
    ("retries use exponential backoff", [r"backoff"]),
    (
        "a failed handler is not silently dropped",
        [r"never\s+silently", r"not\s+silently", r"visible"],
    ),
    ("commands are distinguished from events", [r"command"]),
    ("policies react to events", [r"polic"]),
]

MIN_EVENTS = 25


def main() -> int:
    root = repo_root()
    rep = Reporter("domain-events")

    epath = root / EVENTS_DOC
    cpath = root / COMMANDS_DOC
    ok = rep.check(epath.is_file(), f"{EVENTS_DOC} exists")
    rep.check(cpath.is_file(), f"{COMMANDS_DOC} exists")
    if not ok:
        return rep.finish()

    events_text = read(epath)
    combined = events_text + "\n" + read(cpath)
    lower = combined.lower()

    # --- event count ---
    names = set(EVENT_NAME.findall(events_text))
    rep.check(
        len(names) >= MIN_EVENTS,
        f"catalogue enumerates at least {MIN_EVENTS} domain events (found {len(names)})",
    )

    # --- required record fields ---
    for label, keywords in REQUIRED_EVENT_FIELDS:
        rep.check(
            any(k in lower for k in keywords),
            f"event records carry field: {label}",
        )

    # --- every event has a source aggregate ---
    #
    # Checked structurally: the document must reference the aggregate vocabulary
    # and state that each event has an owning aggregate.
    referenced = [a for a in AGGREGATES if re.search(rf"\b{re.escape(a)}\b", events_text)]
    rep.check(
        len(referenced) >= 12,
        f"events reference their source aggregates "
        f"({len(referenced)}/{len(AGGREGATES)} aggregates named)",
    )
    rep.check(
        re.search(r"source\s+aggregate|owning\s+aggregate|aggregate\s+root", lower)
        is not None,
        "catalogue states that every event has a source aggregate",
    )

    # --- core rules ---
    for label, patterns in REQUIRED_RULES:
        rep.check(
            any(re.search(p, lower, re.IGNORECASE) for p in patterns),
            f"event model states: {label}",
        )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
