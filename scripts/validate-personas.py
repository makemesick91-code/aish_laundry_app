#!/usr/bin/env python3
"""Validate docs/product/PERSONAS.md covers the fourteen mandatory personas.

Also asserts the External Local Courier is documented as a guest-access actor
rather than a tenant member. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import PERSONAS, read  # noqa: E402

PERSONAS_DOC = "docs/product/PERSONAS.md"

REQUIRED_ATTRIBUTES = [
    ("goals", ["goal"]),
    ("pain points", ["pain point", "pain"]),
    ("responsibilities", ["responsibilit"]),
    ("devices", ["device"]),
    ("connectivity context", ["connectivity"]),
    ("frequency of use", ["frequency"]),
    ("sensitive data exposure", ["sensitive data"]),
    ("critical actions", ["critical action"]),
    ("prohibited actions", ["prohibited"]),
    ("success metrics", ["success metric"]),
    ("accessibility considerations", ["accessibilit"]),
]


def normalise(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip().lower()


def main() -> int:
    root = repo_root()
    rep = Reporter("personas")

    path = root / PERSONAS_DOC
    if not rep.check(path.is_file(), f"{PERSONAS_DOC} exists"):
        return rep.finish()

    text = read(path)
    flat = normalise(text)

    # --- all fourteen personas present ---
    for persona in PERSONAS:
        rep.check(normalise(persona) in flat, f"persona documented: {persona}")

    rep.check(
        len(PERSONAS) == 14,
        f"the canonical persona set is fourteen (declared {len(PERSONAS)})",
    )

    # --- every required attribute is addressed somewhere ---
    for label, keywords in REQUIRED_ATTRIBUTES:
        rep.check(
            any(k in flat for k in keywords),
            f"personas document attribute: {label}",
        )

    # --- external courier is a guest, not a member ---
    #
    # This is the whole point of separating that persona: an external ojek rider
    # must never acquire a tenant membership, because a membership carries
    # authorisation across the tenant's data (Rule 02, Rule 09).
    guest_signals = ["guest link", "guest job", "guest access", "tautan tamu"]
    rep.check(
        any(s in flat for s in guest_signals),
        "External Local Courier is documented as using a guest job link",
    )
    for signal, label in (
        ("expir", "the guest link expires"),
        ("revok", "the guest link is revocable"),
    ):
        rep.check(signal in flat, f"External Local Courier: {label}")

    no_membership = [
        "not a tenant member",
        "no membership",
        "does not get a membership",
        "without a membership",
        "not receive a membership",
        "no tenant membership",
        "does not receive a membership",
    ]
    rep.check(
        any(s in flat for s in no_membership),
        "External Local Courier is documented as having no tenant membership",
    )

    # --- substance ---
    lines = len(text.splitlines())
    rep.check(lines >= 150, f"personas document is substantial: {lines} lines (minimum 150)")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
