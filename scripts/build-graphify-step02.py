#!/usr/bin/env python3
"""Build the Step 2 relationship graph and run the mandated orphan analysis.

Emits `graphify-out/step-02-graph.json` in the same node/link shape as the
existing Step 1 graph, then reports every orphan class the Step 2 Definition of
Done names. The graph is derived from the documents themselves, so it reflects
what the repository actually says rather than what anyone believes it says.

This is analysis, not a claim of completeness. It detects the relationship
defects it is written to detect and nothing else.

Standard library only.
"""

from __future__ import annotations

import json
import re
import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from _common import repo_root  # noqa: E402
from _step02 import (  # noqa: E402
    COMPONENT_ID,
    FINDING_ID,
    JOURNEY_ID,
    REQUIREMENT_ID,
    SCREEN_ID,
    UX_STATE_ID,
    load_tokens,
    read,
)

OUT = "graphify-out/step-02-graph.json"
SUMMARY = "evidence/step-02/graphify-summary.md"

DEC_ID = re.compile(r"\bDEC-\d{4}\b")
RULE_ID = re.compile(r"\b(?:rule|Rule)\s*(\d{2})\b")
TOKEN_NAME = re.compile(
    r"`((?:color|space|size|radius|border|elevation|motion|opacity|font|"
    r"density|icon|component)\.[A-Za-z0-9_.]+)`")


def main() -> int:
    root = repo_root()

    inventory = read(root, "docs/ux/SCREEN_INVENTORY.md")
    journeys = read(root, "docs/ux/CRITICAL_JOURNEYS.md")
    catalog = read(root, "docs/design/COMPONENT_CATALOG.md")
    matrix = read(root, "docs/design/COMPONENT_STATE_MATRIX.md")
    states = read(root, "docs/ux/UX_STATE_MODEL.md")
    threats = read(root, "docs/security/DESIGN_AND_UX_THREAT_REVIEW.md")
    classification = read(root, "docs/quality/STEP_02_TRACEABILITY.md")
    tokens, _origin, _errors = load_tokens(root)

    nodes: dict = {}
    links: list = []

    def node(nid, label, kind, source):
        nodes.setdefault(nid, {
            "id": nid, "label": label, "file_type": "doc",
            "node_type": kind, "source_file": source,
            "source_location": "L1",
        })

    def link(src, dst, relation):
        links.append({
            "source": src, "target": dst, "relation": relation,
            "confidence": "EXTRACTED", "step": "Step 2",
        })

    # ---- entities -------------------------------------------------------
    requirements = {m.group(0) for m in REQUIREMENT_ID.finditer(classification)}
    for r in sorted(requirements):
        node(r, r, "requirement", "docs/quality/STEP_02_TRACEABILITY.md")

    screens = {m.group(0) for m in SCREEN_ID.finditer(inventory)}
    for s in sorted(screens):
        node(s, s, "screen", "docs/ux/SCREEN_INVENTORY.md")

    journey_ids = set()
    for block in re.split(r"(?=^#{2,4}[^\n]*JRN-\d{3})", journeys, flags=re.M):
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        m = JOURNEY_ID.search(first) if first.startswith("#") else None
        if m:
            journey_ids.add(m.group(0))
            node(m.group(0), m.group(0), "journey",
                 "docs/ux/CRITICAL_JOURNEYS.md")
            for s in {x.group(0) for x in SCREEN_ID.finditer(block)}:
                link(m.group(0), s, "walks")
            for r in {x.group(0) for x in REQUIREMENT_ID.finditer(block)}:
                link(m.group(0), r, "satisfies")

    components = set()
    for block in re.split(r"(?=^#{2,4}\s*CMP-\d{3})", catalog, flags=re.M):
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        m = COMPONENT_ID.search(first) if first.startswith("#") else None
        if m:
            components.add(m.group(0))
            node(m.group(0), m.group(0), "component",
                 "docs/design/COMPONENT_CATALOG.md")
            for t in set(TOKEN_NAME.findall(block)):
                if t in tokens:
                    node(t, t, "token", "docs/design/tokens/")
                    link(m.group(0), t, "uses")
            for r in {x.group(0) for x in REQUIREMENT_ID.finditer(block)}:
                link(m.group(0), r, "implements")

    ux_states = set()
    for block in re.split(r"(?=^#{2,4}[^\n]*UXS-\d{3})", states, flags=re.M):
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        m = UX_STATE_ID.search(first) if first.startswith("#") else None
        if m:
            ux_states.add(m.group(0))
            node(m.group(0), m.group(0), "ux-state",
                 "docs/ux/UX_STATE_MODEL.md")
            if "recovery" in block.lower():
                node(f"{m.group(0)}__recovery", f"{m.group(0)} recovery",
                     "recovery", "docs/ux/UX_STATE_MODEL.md")
                link(m.group(0), f"{m.group(0)}__recovery", "recovers-via")

    findings = set()
    for block in re.split(r"(?=^#{2,5}[^\n]*DUX-\d{3})", threats, flags=re.M):
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        m = FINDING_ID.search(first) if first.startswith("#") else None
        if m:
            findings.add(m.group(0))
            node(m.group(0), m.group(0), "threat",
                 "docs/security/DESIGN_AND_UX_THREAT_REVIEW.md")
            if "mitigat" in block.lower():
                node(f"{m.group(0)}__mitigation", f"{m.group(0)} mitigation",
                     "ux-mitigation",
                     "docs/security/DESIGN_AND_UX_THREAT_REVIEW.md")
                link(m.group(0), f"{m.group(0)}__mitigation", "mitigated-by")
                for r in {x.group(0) for x in REQUIREMENT_ID.finditer(block)}:
                    link(f"{m.group(0)}__mitigation", r, "protects")

    for name in sorted(tokens):
        node(name, name, "token", "docs/design/tokens/")
    for name, body in sorted(tokens.items()):
        v = str(body.get("value", ""))
        if v.startswith("{") and v.endswith("}"):
            link(name, v.strip("{}"), "references")

    for path in sorted((root / "docs" / "decisions").glob("DEC-*.md")):
        did = path.stem.split("-")[0] + "-" + path.stem.split("-")[1]
        node(did, did, "decision", path.relative_to(root).as_posix())
    for path in sorted((root / ".claude" / "rules").glob("*.md")):
        rid = "Rule " + path.name[:2]
        node(rid, rid, "rule", path.relative_to(root).as_posix())

    # classification edges
    for line in classification.splitlines():
        if not line.strip().startswith("|"):
            continue
        cells = [c.strip().strip("`") for c in line.strip().strip("|").split("|")]
        if len(cells) < 2 or not REQUIREMENT_ID.fullmatch(cells[0]):
            continue
        rid, cls = cells[0], cells[1]
        node(cls, cls, "ux-class", "docs/quality/STEP_02_TRACEABILITY.md")
        link(rid, cls, "classified-as")
        for s in {x.group(0) for x in SCREEN_ID.finditer(line)}:
            link(rid, s, "surfaces-on")
        for j in {x.group(0) for x in JOURNEY_ID.finditer(line)}:
            link(rid, j, "exercised-by")

    # screens -> wireframes
    wf_dir = root / "docs/ux/wireframes"
    for svg in sorted(wf_dir.glob("*.svg")) if wf_dir.is_dir() else []:
        raw = svg.read_text(encoding="utf-8", errors="replace")
        wid = svg.stem
        node(wid, svg.name, "wireframe",
             svg.relative_to(root).as_posix())
        for s in {m.group(0) for m in SCREEN_ID.finditer(raw)}:
            link(wid, s, "depicts")

    graph = {
        "nodes": list(nodes.values()),
        "hyperedges": [],
        "links": links,
        "input_tokens": 0,
        "output_tokens": 0,
        "directed": True,
        "step": "Step 2 — Design System and UX Foundation",
        "status": "NOT IMPLEMENTED",
    }
    out_path = root / OUT
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(graph, indent=1), encoding="utf-8")

    # ---- orphan analysis -------------------------------------------------
    out_edges = defaultdict(set)
    in_edges = defaultdict(set)
    for l in links:
        out_edges[l["source"]].add((l["relation"], l["target"]))
        in_edges[l["target"]].add((l["relation"], l["source"]))

    ui_bearing = {
        cells[0]
        for line in classification.splitlines()
        if line.strip().startswith("|")
        for cells in [[c.strip().strip("`")
                       for c in line.strip().strip("|").split("|")]]
        if len(cells) > 1 and REQUIREMENT_ID.fullmatch(cells[0])
        and cells[1] in ("UI-DIRECT", "UI-INDIRECT")
    }

    checks = []

    def check(name, offenders):
        checks.append((name, sorted(offenders)))

    check("requirements with no UX classification",
          {r for r in requirements
           if not any(rel == "classified-as" for rel, _ in out_edges[r])})
    check("UI-bearing requirements with no screen",
          {r for r in ui_bearing
           if not any(rel == "surfaces-on" for rel, _ in out_edges[r])})
    check("UI-bearing requirements with no journey",
          {r for r in ui_bearing
           if not any(rel == "exercised-by" for rel, _ in out_edges[r])})
    check("screens no journey walks",
          {s for s in screens
           if not any(rel == "walks" for rel, _ in in_edges[s])
           and any(rel == "surfaces-on" for rel, _ in in_edges[s])})
    check("journeys with no screen",
          {j for j in journey_ids
           if not any(rel == "walks" for rel, _ in out_edges[j])})
    check("components with no token",
          {c for c in components
           if not any(rel == "uses" for rel, _ in out_edges[c])})
    check("components absent from the state matrix",
          {c for c in components
           if c not in {m.group(0) for m in COMPONENT_ID.finditer(matrix)}})
    check("UX states with no recovery",
          {s for s in ux_states
           if not any(rel == "recovers-via" for rel, _ in out_edges[s])})
    check("threats with no UX mitigation",
          {f for f in findings
           if not any(rel == "mitigated-by" for rel, _ in out_edges[f])})
    check("UX mitigations with no requirement",
          {n for n in nodes
           if n.endswith("__mitigation")
           and not any(rel == "protects" for rel, _ in out_edges[n])})
    check("semantic tokens with no consumer",
          {t for t in tokens
           if t.startswith("color.semantic.")
           and not in_edges[t]})
    check("wireframes depicting no inventoried screen",
          {n for n, b in nodes.items()
           if b["node_type"] == "wireframe"
           and not any(rel == "depicts" for rel, _ in out_edges[n])})

    print(f"nodes : {len(nodes)}")
    print(f"links : {len(links)}")
    print()
    total_orphans = 0
    for name, offenders in checks:
        total_orphans += len(offenders)
        flag = "OK  " if not offenders else "FAIL"
        print(f"{flag} {name}: {len(offenders)}")
        for o in offenders[:8]:
            print(f"       {o}")
    print()
    print(f"total orphans across all classes: {total_orphans}")

    write_summary(root, nodes, links, checks, total_orphans, requirements,
                  screens, journey_ids, components, ux_states, findings, tokens)
    return 1 if total_orphans else 0


def write_summary(root, nodes, links, checks, total, requirements, screens,
                  journeys, components, ux_states, findings, tokens) -> None:
    from collections import Counter
    kinds = Counter(b["node_type"] for b in nodes.values())
    rels = Counter(l["relation"] for l in links)

    lines = [
        "# Graphify — Step 2 Relationship Analysis",
        "",
        "> **Step 2 — Design System and UX Foundation. Documentation only.**",
        "> This is a relationship analysis of specification documents. It says",
        "> nothing about a running system, because there is none. Every product",
        "> feature is `NOT IMPLEMENTED`.",
        "",
        "| Field | Value |",
        "|---|---|",
        "| Tool | Graphify 0.8.35 |",
        "| Graph | `graphify-out/step-02-graph.json` |",
        "| Generator | `scripts/build-graphify-step02.py` |",
        f"| Nodes | {len(nodes)} |",
        f"| Links | {len(links)} |",
        f"| Total orphans | **{total}** |",
        "",
        "## Node types",
        "",
        "| Type | Count |",
        "|---|---:|",
    ]
    for k, v in sorted(kinds.items()):
        lines.append(f"| {k} | {v} |")
    lines += ["", "## Relationship types", "", "| Relation | Count |",
              "|---|---:|"]
    for k, v in sorted(rels.items()):
        lines.append(f"| `{k}` | {v} |")

    lines += [
        "",
        "## Orphan detection",
        "",
        "Each row is a relationship defect the Step 2 Definition of Done names.",
        "",
        "| Check | Orphans | Result |",
        "|---|---:|---|",
    ]
    for name, offenders in checks:
        result = "PASS" if not offenders else "**FAIL**"
        lines.append(f"| {name} | {len(offenders)} | {result} |")
        if offenders:
            lines.append(f"| ↳ examples | | {', '.join(offenders[:6])} |")

    lines += [
        "",
        "## What this analysis does and does not establish",
        "",
        "It establishes that the relationships it checks close in both",
        "directions across the Step 2 corpus at this commit.",
        "",
        "It does **not** establish that the specifications are correct, that a",
        "screen would render, that an accessibility criterion is met, or that",
        "the check set is complete. It detects the defect classes it was written",
        "to detect, and nothing beyond them.",
        "",
        "A graph with zero orphans is a graph with no *detected* orphans.",
        "",
    ]
    p = root / SUMMARY
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text("\n".join(lines) + "\n", encoding="utf-8")


if __name__ == "__main__":
    sys.exit(main())
