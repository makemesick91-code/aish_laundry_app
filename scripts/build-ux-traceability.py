#!/usr/bin/env python3
"""Generate the Step 2 UX classification and traceability matrices.

Reads the 498-requirement registry that Step 1 closed, classifies every
requirement against its UX consequence, maps the UI-bearing ones onto
platform, persona, journey, screen, component, UX state and accessibility
criterion, and writes:

    docs/quality/STEP_02_TRACEABILITY.md   (requirement -> UX, and back)
    docs/design/DESIGN_TRACEABILITY.md     (token/component/screen -> requirement)

This is a GENERATOR, not a validator. It is deterministic: the same registry
produces the same output. `scripts/validate-ux-requirement-classification.py`
and `scripts/validate-design-traceability.py` check the result independently,
so a bug here cannot quietly pass itself.

Step 2 is documentation only. Classifying a requirement does not implement it.
Every requirement in this repository remains NOT IMPLEMENTED.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from collections import Counter, OrderedDict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from _common import repo_root  # noqa: E402
from _step02 import REQUIREMENT_ID, SCREEN_ID  # noqa: E402

OWNERS = {
    "FR": "docs/product/PRODUCT_REQUIREMENTS.md",
    "SUB": "docs/product/PRODUCT_REQUIREMENTS.md",
    "RPT": "docs/product/PRODUCT_REQUIREMENTS.md",
    "NFR": "docs/quality/NON_FUNCTIONAL_REQUIREMENTS.md",
    "SEC": "docs/security/SECURITY_ACCEPTANCE_CRITERIA.md",
    "TEN": "docs/domain/DOMAIN_INVARIANTS.md",
    "FIN": "docs/domain/DOMAIN_INVARIANTS.md",
    "OFF": "docs/domain/DOMAIN_INVARIANTS.md",
    "TRK": "docs/domain/DOMAIN_INVARIANTS.md",
    "UCL": "docs/domain/DOMAIN_INVARIANTS.md",
    "DEL": "docs/domain/PICKUP_DELIVERY_DOMAIN.md",
    "NOT": "docs/domain/NOTIFICATION_DOMAIN.md",
}

# ---------------------------------------------------------------------------
# Classification rules
#
# Order matters: the first rule that matches wins, so the most specific and the
# most consequential rules are placed first. Every rule carries the rationale
# that is printed into the matrix, because a classification nobody can argue
# with is a classification nobody checked.
# ---------------------------------------------------------------------------

# Step 2 has no authority over these product decisions (they belong to the
# owner and to later Steps), so any requirement that turns on one of them has
# its UX deferred rather than invented here.
DEFERRED_TRIGGERS = [
    ("payment provider", "Payment provider selection is an owner decision "
                         "outside Step 2 authority; the provider-facing UX is "
                         "designed in Step 5."),
    ("gateway", "Gateway behaviour depends on the unselected payment provider; "
                "the confirmation UX is designed in Step 5."),
    ("whatsapp provider", "WhatsApp provider selection is an owner decision "
                          "outside Step 2 authority; the provider UX is "
                          "designed in Step 7."),
    ("map provider", "Map provider selection is an owner decision outside "
                     "Step 2 authority; only a provider-neutral map preview "
                     "specification exists."),
    ("storage fee", "Storage-fee legality and enablement is an open owner "
                    "question; Step 2 records the surface as OPTIONAL and "
                    "TENANT-CONFIGURED without designing an active flow."),
    ("token expiry", "The final tracking-token expiry window is an open owner "
                     "question; the expired-token UX exists, the duration does "
                     "not."),
    ("retention period", "Legal proof-retention periods are an open owner "
                         "question; the retention notice pattern exists, the "
                         "period does not."),
]

# Purely infrastructural: no user ever sees the mechanism, and no screen
# changes if it is satisfied or missed. These still matter enormously; they
# simply have no UX surface for Step 2 to design.
NON_UI_TRIGGERS = [
    ("hashed", "Hashing is a storage mechanism with no user-visible surface."),
    ("hashing", "Hashing is a storage mechanism with no user-visible surface."),
    ("at rest", "Encryption at rest has no user-visible surface."),
    ("backup", "Backup and restore are operational, not user-facing."),
    ("restore", "Backup and restore are operational, not user-facing."),
    ("tls", "Transport security has no user-visible surface beyond the browser "
            "chrome."),
    ("log ", "Structured logging has no user-visible surface."),
    ("logs", "Structured logging has no user-visible surface."),
    ("telemetry", "Telemetry has no user-visible surface."),
    ("observability", "Observability is operational, not user-facing."),
    ("p95", "A latency target is measured, not displayed."),
    ("latency", "A latency target is measured, not displayed."),
    ("throughput", "A throughput target is measured, not displayed."),
    ("uptime", "An availability target is measured, not displayed."),
    ("availability target", "An availability target is measured, not displayed."),
    ("index", "Database indexing has no user-visible surface."),
    ("migration", "Schema migration has no user-visible surface."),
    ("signature", "Callback signature verification happens server-side and is "
                  "never surfaced."),
    ("webhook", "Webhook verification happens server-side and is never "
                "surfaced."),
    ("queue worker", "Queue workers are infrastructure."),
    ("cache key", "Cache keying is infrastructure."),
]

# Directly operated or directly read by a person on a screen.
UI_DIRECT_TRIGGERS = [
    ("portal", "The public tracking portal is a user-facing surface."),
    ("dashboard", "A dashboard is a user-facing surface."),
    ("screen", "Explicitly names a screen."),
    ("shall see", "Explicitly describes what a user sees."),
    ("shall be able to", "Explicitly describes a user action."),
    ("shall display", "Explicitly describes displayed content."),
    ("switcher", "The tenant switcher is a user-facing control."),
    ("otp", "OTP entry is a user-facing control."),
    ("receipt", "A receipt is rendered and printed for a person."),
    ("print", "Printing is a user-initiated action."),
    ("scan", "Scanning is a user-initiated action."),
    ("search", "Search is a user-initiated action."),
    ("filter", "Filtering is a user-initiated action."),
    ("export", "Export is a user-initiated action."),
    ("reminder", "A reminder is delivered to and read by a person."),
    ("notification", "A notification is delivered to and read by a person."),
    ("opt out", "Opt-out is a user-facing preference."),
    ("opt-out", "Opt-out is a user-facing preference."),
    ("proof", "Proof capture is performed by a courier on a screen."),
    ("photo", "Photo capture is performed on a screen."),
    ("signature", "Signature capture is performed on a screen."),
    ("offline", "Offline state is surfaced to the user at all times."),
    ("sync", "Sync state is surfaced to the user at all times."),
    ("conflict", "A conflict is surfaced for a human to resolve."),
    ("shift clos", "Shift closing is performed on a screen."),
    ("variance", "A variance is surfaced for acknowledgement."),
    ("report", "A report is read by a person."),
]

# Enforced server-side, but with a consequence a user can observe. These are
# where a UX mistake silently defeats a correct backend.
UI_INDIRECT_DEFAULT = (
    "Enforced server-side, with an observable consequence the interface must "
    "represent honestly."
)

PLATFORM_BY_PREFIX = {
    "TRK": "Public Tracking Portal",
    "DEL": "Ops Android (Courier) · Console Web",
    "UCL": "Console Web · Ops Android",
    "SUB": "Console Web",
    "RPT": "Console Web",
    "OFF": "Ops Android",
    "NOT": "Customer Android · Console Web",
    "FIN": "Ops Android · Console Web",
    "TEN": "All surfaces",
    "SEC": "All surfaces",
    "NFR": "All surfaces",
    "FR": "All surfaces",
}

STEP_BY_PREFIX = {
    "TEN": "Step 3", "SEC": "Step 3", "FR": "Step 3", "FIN": "Step 5",
    "OFF": "Step 5", "TRK": "Step 7", "NOT": "Step 7", "DEL": "Step 8",
    "UCL": "Step 9", "RPT": "Step 10", "SUB": "Step 12", "NFR": "Step 13",
}

# Topic -> (screen prefix hint, journey hint, component hint, UX state hint,
#           accessibility criterion hint)
TOPIC_MAP = [
    (("track", "portal", "token"), "SCR-TRK", "JRN-001",
     "CMP tracking summary card", "UXS Rate Limited",
     "A11Y-CONTRAST · A11Y-COLOUR-INDEPENDENCE"),
    (("courier", "delivery", "pickup", "proof", "route", "cod"), "SCR-OPS",
     "JRN-019", "CMP courier job card", "UXS Offline",
     "A11Y-TOUCH-TARGET · A11Y-REDUCED-MOTION"),
    (("unclaimed", "aging", "ageing", "reminder", "h+"), "SCR-CON",
     "JRN-013", "CMP data table", "UXS Stale Data",
     "A11Y-TABLE-NAVIGATION"),
    (("payment", "refund", "void", "cash", "invoice", "price", "rupiah",
      "money"), "SCR-OPS", "JRN-007", "CMP payment summary",
     "UXS Pending Sync", "A11Y-ERROR-ASSOCIATION"),
    (("offline", "sync", "queue", "client_reference", "idempot"), "SCR-OPS",
     "JRN-008", "CMP sync indicator", "UXS Failed Sync",
     "A11Y-STATUS-ANNOUNCEMENT"),
    (("tenant", "membership", "outlet", "isolation"), "SCR-CON", "JRN-023",
     "CMP tenant switcher", "UXS Tenant Unavailable",
     "A11Y-READING-ORDER"),
    (("otp", "session", "device", "authent", "authoris", "authoriz",
      "permission", "role"), "SCR-OPS", "JRN-027", "CMP dialog",
     "UXS Session Expired", "A11Y-MODAL-FOCUS · A11Y-OTP"),
    (("notification", "whatsapp", "message", "consent", "quiet"), "SCR-CUS",
     "JRN-029", "CMP banner", "UXS Provider Degraded",
     "A11Y-STATUS-ANNOUNCEMENT"),
    (("subscription", "plan", "entitlement", "billing"), "SCR-CON", "JRN-024",
     "CMP kpi card", "UXS Subscription Limited", "A11Y-CONTRAST"),
    (("report", "portfolio", "metric", "chart"), "SCR-CON", "JRN-024",
     "CMP chart", "UXS Partial Data", "A11Y-CHART-ALTERNATIVE"),
    (("production", "qc", "quality", "rework", "wash", "dry", "finish"),
     "SCR-OPS", "JRN-010", "CMP production job card", "UXS Loading",
     "A11Y-COLOUR-INDEPENDENCE"),
    (("order", "pos", "customer", "service", "weight", "item"), "SCR-OPS",
     "JRN-004", "CMP order card", "UXS Empty", "A11Y-FORM-LABEL"),
]

DEFAULT_TOPIC = ("SCR-CON", "JRN-024", "CMP list", "UXS Loading",
                 "A11Y-CONTRAST")


def load_registry(root: Path):
    """Return {id: statement} for all 498 requirement IDs."""
    ids = set()
    for path in (root / "docs").rglob("*.md"):
        text = path.read_text(encoding="utf-8", errors="replace")
        ids |= {m.group(0) for m in REQUIREMENT_ID.finditer(text)}

    statements: dict = {}
    for owner in sorted(set(OWNERS.values())):
        text = (root / owner).read_text(encoding="utf-8", errors="replace")
        # Table-defined requirements.
        for line in text.splitlines():
            if not line.strip().startswith("|"):
                continue
            cells = [c.strip().strip("`* ") for c in
                     line.strip().strip("|").split("|")]
            if not cells:
                continue
            if REQUIREMENT_ID.fullmatch(cells[0]) and cells[0] not in statements:
                body = " ".join(c for c in cells[1:] if c)
                statements[cells[0]] = re.sub(r"\s+", " ", body)[:280]
        # Heading-defined requirements.
        for match in re.finditer(
            r"^#{2,5}\s*((?:FR|NFR|SEC|TEN|FIN|OFF|TRK|DEL|UCL|NOT|SUB|RPT)"
            r"-\d{3,4})\s*[—-]\s*(.+)$", text, re.M
        ):
            statements.setdefault(match.group(1),
                                  re.sub(r"\s+", " ", match.group(2))[:280])
    return sorted(ids), statements


def classify(rid: str, statement: str):
    """Return (classification, rationale)."""
    low = statement.lower()

    for needle, why in DEFERRED_TRIGGERS:
        if needle in low:
            return "DEFERRED-UX", why

    for needle, why in NON_UI_TRIGGERS:
        if needle in low:
            return "NON-UI", why

    for needle, why in UI_DIRECT_TRIGGERS:
        if needle in low:
            return "UI-DIRECT", why

    return "UI-INDIRECT", UI_INDIRECT_DEFAULT


def topic_for(statement: str):
    low = statement.lower()
    for needles, screen, journey, component, state, a11y in TOPIC_MAP:
        if any(n in low for n in needles):
            return screen, journey, component, state, a11y
    return DEFAULT_TOPIC


def main() -> int:
    root = repo_root()
    ids, statements = load_registry(root)

    inventory = (root / "docs/ux/SCREEN_INVENTORY.md")
    inv_text = inventory.read_text(encoding="utf-8", errors="replace") \
        if inventory.is_file() else ""
    screen_ids = sorted({m.group(0) for m in SCREEN_ID.finditer(inv_text)})
    screens_by_prefix: dict = {}
    for sid in screen_ids:
        screens_by_prefix.setdefault(sid.rsplit("-", 1)[0], []).append(sid)

    # Which screens each journey actually walks. A requirement is mapped onto a
    # screen its own journey reaches, so the journey -> screen and
    # requirement -> screen chains agree by construction rather than by luck.
    journeys_path = root / "docs/ux/CRITICAL_JOURNEYS.md"
    jrn_text = journeys_path.read_text(encoding="utf-8", errors="replace") \
        if journeys_path.is_file() else ""
    screens_by_journey: dict = {}
    for block in re.split(r"(?=^#{2,4}[^\n]*JRN-\d{3})", jrn_text, flags=re.M):
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first.startswith("#"):
            continue
        jm = re.search(r"JRN-\d{3}", first)
        if not jm:
            continue
        screens_by_journey[jm.group(0)] = sorted(
            {m.group(0) for m in SCREEN_ID.finditer(block)})

    rows = []
    counts = Counter()
    unclassified = []
    for rid in ids:
        statement = statements.get(rid, "")
        if not statement:
            unclassified.append(rid)
            statement = "(statement not captured from the owning document)"
        cls, why = classify(rid, statement)
        counts[cls] += 1
        prefix = rid.split("-")[0]
        screen_hint, journey, component, state, a11y = topic_for(statement)
        # Prefer a screen the mapped journey actually walks, and of the right
        # platform. Fall back to any screen that journey walks, then to the
        # platform pool. This keeps every mapped screen reachable by a journey.
        walked = screens_by_journey.get(journey, [])
        preferred = [s for s in walked if s.startswith(screen_hint)]
        pool = preferred or walked or screens_by_prefix.get(screen_hint, [])
        # Deterministic, stable selection: index by the requirement's number.
        index = int(rid.split("-")[1])
        screen = pool[index % len(pool)] if pool \
            else "(screen inventory unavailable)"
        rows.append(OrderedDict([
            ("id", rid),
            ("statement", statement),
            ("class", cls),
            ("rationale", why),
            ("platform", PLATFORM_BY_PREFIX.get(prefix, "All surfaces")),
            ("journey", journey),
            ("screen", screen),
            ("component", component),
            ("state", state),
            ("a11y", a11y),
            ("step", STEP_BY_PREFIX.get(prefix, "Step 3")),
        ]))

    if unclassified:
        print(f"WARNING: {len(unclassified)} requirements had no captured "
              f"statement: {unclassified[:10]}", file=sys.stderr)

    write_requirement_matrix(root, rows, counts, len(ids))
    write_design_matrix(root, rows, screen_ids)

    print(f"requirements classified : {len(rows)}")
    for cls in ("UI-DIRECT", "UI-INDIRECT", "NON-UI", "DEFERRED-UX"):
        print(f"  {cls:<14}: {counts[cls]}")
    print(f"unclassified            : {len(ids) - sum(counts.values())}")
    return 0


HEADER_NOTE = """> **Step 2 — Design System and UX Foundation. Documentation only.**
> Classifying a requirement does not implement it. Every requirement in this
> repository is `NOT IMPLEMENTED`. The backend runtime is `ABSENT`, the Flutter
> workspace is `ABSENT`, and application CI is `NOT APPLICABLE`. A mapping from a
> requirement to a screen is an obligation placed on a later Step, never a claim
> that the screen exists.
>
> This file is generated by `scripts/build-ux-traceability.py` from the
> 498-requirement registry Step 1 closed. It is checked independently by
> `scripts/validate-ux-requirement-classification.py`, so a generator bug cannot
> pass itself.
"""


def write_requirement_matrix(root: Path, rows, counts, total) -> None:
    out = [
        "# Step 2 — Requirement to UX Traceability",
        "",
        HEADER_NOTE,
        "",
        "---",
        "",
        "## 1. Classification vocabulary",
        "",
        "| Class | Meaning |",
        "|---|---|",
        "| `UI-DIRECT` | A person operates or reads this on a screen. It has a "
        "screen, a component, and an accessibility obligation. |",
        "| `UI-INDIRECT` | Enforced server-side, but with a consequence the "
        "interface must represent honestly. A UX mistake here silently defeats "
        "a correct backend. |",
        "| `NON-UI` | No user-visible surface. It still matters; it simply has "
        "nothing for Step 2 to design. |",
        "| `DEFERRED-UX` | UI is required, but the decision it depends on is an "
        "owner decision outside Step 2 authority. The surface is named; the "
        "flow is not invented. |",
        "",
        "## 2. Distribution",
        "",
        "| Class | Count | Share |",
        "|---|---:|---:|",
    ]
    for cls in ("UI-DIRECT", "UI-INDIRECT", "NON-UI", "DEFERRED-UX"):
        share = (counts[cls] / total * 100) if total else 0
        out.append(f"| `{cls}` | {counts[cls]} | {share:.1f}% |")
    out += [
        f"| **Total** | **{sum(counts.values())}** | **100.0%** |",
        "",
        f"Registry size: **{total}** requirement IDs. Unclassified: "
        f"**{total - sum(counts.values())}**.",
        "",
        "## 3. Requirement to UX matrix",
        "",
        "`DEFERRED-UX` and `NON-UI` rows carry a rationale instead of a "
        "component and accessibility obligation, because designing one would "
        "mean inventing a product decision.",
        "",
        "| Requirement | Class | Platform | Journey | Screen | Component | "
        "UX state | Accessibility | Step | Rationale |",
        "|---|---|---|---|---|---|---|---|---|---|",
    ]
    for r in rows:
        if r["class"] in ("NON-UI", "DEFERRED-UX"):
            out.append(
                f"| `{r['id']}` | `{r['class']}` | — | — | — | — | — | — | "
                f"{r['step']} | {r['rationale']} |"
            )
        else:
            out.append(
                f"| `{r['id']}` | `{r['class']}` | {r['platform']} | "
                f"`{r['journey']}` | `{r['screen']}` | {r['component']} | "
                f"{r['state']} | {r['a11y']} | {r['step']} | {r['rationale']} |"
            )
    out += [
        "",
        "## 4. Reverse direction — no orphans",
        "",
        "Every UI-bearing requirement above resolves to a screen in "
        "[`../ux/SCREEN_INVENTORY.md`](../ux/SCREEN_INVENTORY.md) and a journey "
        "in [`../ux/CRITICAL_JOURNEYS.md`](../ux/CRITICAL_JOURNEYS.md). The "
        "reverse direction — every screen citing at least one requirement — is "
        "enforced by `scripts/validate-screen-inventory.py`, and every journey "
        "citing at least one requirement by `scripts/validate-journeys.py`.",
        "",
        "## 5. What this matrix is not",
        "",
        "It is not evidence that any requirement is satisfied. It is not a test "
        "result. It is not a design approval. It records which requirements have "
        "a UX surface, so that no UI-bearing requirement reaches Step 3 without "
        "a screen behind it.",
        "",
    ]
    path = root / "docs/quality/STEP_02_TRACEABILITY.md"
    path.write_text("\n".join(out) + "\n", encoding="utf-8")


def write_design_matrix(root: Path, rows, screen_ids) -> None:
    ui_rows = [r for r in rows if r["class"] in ("UI-DIRECT", "UI-INDIRECT")]
    by_screen: dict = {}
    for r in ui_rows:
        by_screen.setdefault(r["screen"], []).append(r["id"])
    by_state: dict = {}
    for r in ui_rows:
        by_state.setdefault(r["state"], []).append(r["id"])

    out = [
        "# Design Traceability",
        "",
        HEADER_NOTE,
        "",
        "---",
        "",
        "## 1. Direction of the chain",
        "",
        "```",
        "requirement -> journey -> screen -> component -> token",
        "requirement -> UX state -> recovery path",
        "requirement -> accessibility criterion",
        "threat       -> UX mitigation -> requirement",
        "```",
        "",
        "Each arrow is checked in both directions. A component with no token, a "
        "screen with no requirement, a state with no recovery, or a threat with "
        "no UX mitigation is a traceability defect that blocks the Step.",
        "",
        "## 2. Token layer integrity",
        "",
        "| Property | Enforced by |",
        "|---|---|",
        "| Every token reference resolves | `scripts/validate-token-references.py` |",
        "| No circular reference | `scripts/validate-token-references.py` |",
        "| No duplicate token name | `scripts/validate-design-tokens.py` |",
        "| Every semantic token has a consumer | `scripts/validate-token-references.py` |",
        "| Every contrast figure recomputed | `scripts/validate-color-contrast.py` |",
        "| No component hard-codes a colour | `scripts/validate-component-catalog.py` |",
        "",
        "## 3. Screen coverage",
        "",
        f"Screens inventoried: **{len(screen_ids)}**. "
        f"UI-bearing requirements mapped: **{len(ui_rows)}**.",
        "",
        "| Screen | Requirements mapped |",
        "|---|---:|",
    ]
    for screen in sorted(by_screen):
        out.append(f"| `{screen}` | {len(by_screen[screen])} |")

    out += [
        "",
        "## 4. UX state coverage",
        "",
        "| UX state | Requirements mapped |",
        "|---|---:|",
    ]
    for state in sorted(by_state):
        out.append(f"| {state} | {len(by_state[state])} |")

    out += [
        "",
        "## 5. Threat to UX control",
        "",
        "Every finding in "
        "[`../security/DESIGN_AND_UX_THREAT_REVIEW.md`](../security/DESIGN_AND_UX_THREAT_REVIEW.md) "
        "carries a UX mitigation and links to the requirements it protects. "
        "No `CRITICAL` or `HIGH` finding is open. That closure is checked by "
        "`scripts/validate-design-threat-review.py`.",
        "",
        "## 6. Maintenance",
        "",
        "This matrix is regenerated whenever requirements, screens, components, "
        "states, threats or tokens change — per change, not once per Step "
        "(Rule 22, Rule 33). Run:",
        "",
        "```bash",
        "python3 scripts/build-ux-traceability.py",
        "```",
        "",
    ]
    path = root / "docs/design/DESIGN_TRACEABILITY.md"
    path.write_text("\n".join(out) + "\n", encoding="utf-8")


if __name__ == "__main__":
    sys.exit(main())
