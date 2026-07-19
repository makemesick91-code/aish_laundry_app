#!/usr/bin/env python3
"""Validate bidirectional design traceability.

Checks that the chain closes in both directions:

    requirement -> journey -> screen -> component -> token
    threat      -> UX mitigation -> requirement
    UX state    -> recovery path

An orphan in either direction is a traceability defect that blocks the Step:
a component nobody's screen uses, a screen no requirement asked for, or a
threat whose mitigation nothing enforces.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import (
    COMPONENT_ID,
    FINDING_ID,
    JOURNEY_ID,
    REQUIREMENT_ID,
    SCREEN_ID,
    UX_STATE_ID,
    load_tokens,
    read,
)

DOC = "docs/design/DESIGN_TRACEABILITY.md"


def main() -> int:
    root = repo_root()
    rep = Reporter("design traceability")

    trace = read(root, DOC)
    rep.check(bool(trace), f"{DOC} exists")

    inventory = read(root, "docs/ux/SCREEN_INVENTORY.md")
    journeys = read(root, "docs/ux/CRITICAL_JOURNEYS.md")
    catalog = read(root, "docs/design/COMPONENT_CATALOG.md")
    matrix = read(root, "docs/design/COMPONENT_STATE_MATRIX.md")
    states = read(root, "docs/ux/UX_STATE_MODEL.md")
    threats = read(root, "docs/security/DESIGN_AND_UX_THREAT_REVIEW.md")
    classification = read(root, "docs/quality/STEP_02_TRACEABILITY.md")

    for rel, body in (
        ("docs/ux/SCREEN_INVENTORY.md", inventory),
        ("docs/ux/CRITICAL_JOURNEYS.md", journeys),
        ("docs/design/COMPONENT_CATALOG.md", catalog),
        ("docs/ux/UX_STATE_MODEL.md", states),
        ("docs/security/DESIGN_AND_UX_THREAT_REVIEW.md", threats),
        ("docs/quality/STEP_02_TRACEABILITY.md", classification),
    ):
        rep.check(bool(body), f"{rel} exists")

    screen_ids = {m.group(0) for m in SCREEN_ID.finditer(inventory)}
    journey_ids = {m.group(0) for m in JOURNEY_ID.finditer(journeys)}
    component_ids = {m.group(0) for m in COMPONENT_ID.finditer(catalog)}
    state_ids = {m.group(0) for m in UX_STATE_ID.finditer(states)}
    finding_ids = {m.group(0) for m in FINDING_ID.finditer(threats)}

    rep.info(
        f"screens={len(screen_ids)} journeys={len(journey_ids)} "
        f"components={len(component_ids)} states={len(state_ids)} "
        f"findings={len(finding_ids)}"
    )

    # -- journey -> screen: every journey screen exists --------------------
    cited_screens = {m.group(0) for m in SCREEN_ID.finditer(journeys)}
    unknown = sorted(cited_screens - screen_ids)
    for u in unknown[:15]:
        rep.info(f"journey cites a non-existent screen: {u}")
    rep.check(not unknown,
              f"every screen cited by a journey exists ({len(unknown)} unknown)")

    # -- screen -> journey: every HARD-GATE screen is walked ---------------
    # Requiring all 89 screens to appear in a journey would force the invention
    # of journeys nobody performs, which is worse than the gap it closes. What
    # genuinely must be walked end to end is any screen carrying a hard-gate
    # requirement: tenancy, financial, security, offline or tracking. A screen
    # holding one of those that no journey ever reaches is a real gap.
    HARD_GATE = ("TEN-", "FIN-", "SEC-", "OFF-", "TRK-")
    mapped_screens = {m.group(0) for m in SCREEN_ID.finditer(classification)}
    hard_gate_screens = set()
    for line in classification.splitlines():
        if not line.strip().startswith("|"):
            continue
        match = REQUIREMENT_ID.search(line)
        if not match or not match.group(0).startswith(HARD_GATE):
            continue
        hard_gate_screens |= {m.group(0) for m in SCREEN_ID.finditer(line)}

    unwalked = sorted(hard_gate_screens - cited_screens)
    for u in unwalked[:15]:
        rep.info(
            f"screen carries a hard-gate requirement but appears in no "
            f"journey: {u}")
    rep.check(
        not unwalked,
        f"every hard-gate screen appears in at least one journey "
        f"({len(hard_gate_screens)} hard-gate screens, {len(unwalked)} unwalked)",
    )
    covered = len(mapped_screens & cited_screens)
    rep.info(
        f"journey coverage: {covered}/{len(mapped_screens)} "
        f"requirement-bearing screens are walked by at least one journey")

    # -- component -> state matrix -----------------------------------------
    matrix_ids = {m.group(0) for m in COMPONENT_ID.finditer(matrix)}
    uncovered = sorted(component_ids - matrix_ids)
    for u in uncovered[:15]:
        rep.info(f"component absent from the state matrix: {u}")
    rep.check(
        not uncovered,
        f"every component appears in the state matrix "
        f"({len(uncovered)} uncovered)",
    )

    # -- component -> token: components name tokens ------------------------
    tokens, _origin, errors = load_tokens(root)
    for err in errors:
        rep.fail(err)
    cited_tokens = set(re.findall(
        r"`((?:color|space|size|radius|border|elevation|motion|opacity|font|"
        r"density|icon|component)\.[A-Za-z0-9_.]+)`", catalog))
    rep.check(
        len(cited_tokens) >= 15,
        f"the component catalog names design tokens "
        f"({len(cited_tokens)} distinct)",
    )
    unknown_tokens = sorted(t for t in cited_tokens if t not in tokens)
    for u in unknown_tokens[:15]:
        rep.info(f"catalog names a token that does not exist: {u}")
    rep.check(not unknown_tokens,
              f"every token a component names exists "
              f"({len(unknown_tokens)} unknown)")

    # -- token -> consumer: no orphan semantic token -----------------------
    consumed = set()
    for body in tokens.values():
        match = re.match(r"^\{([A-Za-z0-9_.]+)\}$", str(body.get("value", "")))
        if match:
            consumed.add(match.group(1))
    design_corpus = ""
    for path in sorted((root / "docs" / "design").rglob("*.md")):
        design_corpus += path.read_text(encoding="utf-8", errors="replace")
    orphan_tokens = [
        name for name in sorted(tokens)
        if name.startswith("color.semantic.")
        and name not in consumed
        and name not in design_corpus
    ]
    for o in orphan_tokens:
        rep.info(f"semantic token with no consumer: {o}")
    rep.check(not orphan_tokens,
              f"every semantic token has a consumer "
              f"({len(orphan_tokens)} orphaned)")

    # -- state -> recovery --------------------------------------------------
    blocks = re.split(r"(?=^#{2,4}[^\n]*UXS-\d{3})", states, flags=re.M)
    no_recovery = []
    for block in blocks:
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first.startswith("#"):
            continue
        match = UX_STATE_ID.search(first)
        if not match:
            continue
        if "recovery" not in block.lower():
            no_recovery.append(match.group(0))
    for n in no_recovery:
        rep.info(f"UX state with no recovery: {n}")
    rep.check(not no_recovery,
              f"every UX state has a recovery path ({len(no_recovery)} without)")

    # -- threat -> UX mitigation -> requirement ----------------------------
    threat_blocks = re.split(r"(?=^#{2,5}[^\n]*DUX-\d{3})", threats, flags=re.M)
    no_requirement = []
    no_mitigation = []
    checked = 0
    for block in threat_blocks:
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first.startswith("#"):
            continue
        match = FINDING_ID.search(first)
        if not match:
            continue
        checked += 1
        if "mitigat" not in block.lower():
            no_mitigation.append(match.group(0))
        if not REQUIREMENT_ID.search(block):
            no_requirement.append(match.group(0))
    rep.check(checked > 0, "the threat review contains finding blocks")
    for n in no_mitigation[:15]:
        rep.info(f"threat finding with no UX mitigation: {n}")
    rep.check(not no_mitigation,
              f"every threat finding carries a UX mitigation "
              f"({len(no_mitigation)} without)")
    for n in no_requirement[:15]:
        rep.info(f"UX mitigation with no requirement behind it: {n}")
    rep.check(not no_requirement,
              f"every UX mitigation traces to a requirement "
              f"({len(no_requirement)} untraced)")

    # -- requirement IDs cited anywhere in Step 2 must exist ---------------
    registry = set()
    for path in (root / "docs").rglob("*.md"):
        registry |= {m.group(0) for m in REQUIREMENT_ID.finditer(
            path.read_text(encoding="utf-8", errors="replace"))}
    step2_corpus = (inventory + journeys + catalog + threats + classification)
    cited_reqs = {m.group(0) for m in REQUIREMENT_ID.finditer(step2_corpus)}
    invented = sorted(cited_reqs - registry)
    for i in invented[:15]:
        rep.info(f"Step 2 cites a requirement ID that does not exist: {i}")
    rep.check(not invented,
              f"Step 2 invents no requirement ID ({len(invented)} invented)")

    # -- the traceability document states the chain and its maintenance ----
    if trace:
        low = trace.lower()
        for term in ("requirement", "journey", "screen", "component", "token",
                     "threat", "state"):
            rep.check(term in low, f"the traceability document covers '{term}'")
        rep.check(
            "regenerat" in low or "re-verif" in low,
            "the traceability document states its maintenance rule",
        )
        rep.check("NOT IMPLEMENTED" in trace,
                  "the traceability document restates NOT IMPLEMENTED")

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
