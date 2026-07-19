# Graphify — Step 2 Relationship Analysis

> **Step 2 — Design System and UX Foundation. Documentation only.**
> This is a relationship analysis of specification documents. It says
> nothing about a running system, because there is none. Every product
> feature is `NOT IMPLEMENTED`.

| Field | Value |
|---|---|
| Tool | Graphify 0.8.35 |
| Graph | `graphify-out/step-02-graph.json` |
| Generator | `scripts/build-graphify-step02.py` |
| Nodes | 1190 |
| Links | 2493 |
| Total orphans | **0** |

## Node types

| Type | Count |
|---|---:|
| component | 70 |
| decision | 23 |
| journey | 32 |
| recovery | 20 |
| requirement | 498 |
| rule | 36 |
| screen | 89 |
| threat | 36 |
| token | 294 |
| ux-class | 4 |
| ux-mitigation | 36 |
| ux-state | 20 |
| wireframe | 32 |

## Relationship types

| Relation | Count |
|---|---:|
| `classified-as` | 498 |
| `depicts` | 32 |
| `exercised-by` | 440 |
| `implements` | 98 |
| `mitigated-by` | 36 |
| `protects` | 115 |
| `recovers-via` | 20 |
| `references` | 85 |
| `satisfies` | 376 |
| `surfaces-on` | 440 |
| `uses` | 267 |
| `walks` | 86 |

## Orphan detection

Each row is a relationship defect the Step 2 Definition of Done names.

| Check | Orphans | Result |
|---|---:|---|
| requirements with no UX classification | 0 | PASS |
| UI-bearing requirements with no screen | 0 | PASS |
| UI-bearing requirements with no journey | 0 | PASS |
| screens no journey walks | 0 | PASS |
| journeys with no screen | 0 | PASS |
| components with no token | 0 | PASS |
| components absent from the state matrix | 0 | PASS |
| UX states with no recovery | 0 | PASS |
| threats with no UX mitigation | 0 | PASS |
| UX mitigations with no requirement | 0 | PASS |
| semantic tokens with no consumer | 0 | PASS |
| wireframes depicting no inventoried screen | 0 | PASS |

## What this analysis does and does not establish

It establishes that the relationships it checks close in both
directions across the Step 2 corpus at this commit.

It does **not** establish that the specifications are correct, that a
screen would render, that an accessibility criterion is met, or that
the check set is complete. It detects the defect classes it was written
to detect, and nothing beyond them.

A graph with zero orphans is a graph with no *detected* orphans.

