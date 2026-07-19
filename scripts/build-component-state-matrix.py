#!/usr/bin/env python3
"""Generate docs/design/COMPONENT_STATE_MATRIX.md from the component catalog.

Every component is resolved against every state — APPLICABLE or NOT APPLICABLE,
never blank. A blank cell is an undecided design that becomes someone's bug in
Step 5, so the matrix is generated rather than hand-maintained: adding a
component to the catalog automatically adds a fully resolved row here.

Applicability is decided by rules over component category, not by guesswork per
cell. The rules are stated in the generated document so a reader can disagree
with them on the reasoning rather than on authority.

Standard library only. Step 2 is documentation only — a resolved state is a
specification, never an implemented behaviour.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from _common import repo_root  # noqa: E402

CATALOG = "docs/design/COMPONENT_CATALOG.md"
OUT = "docs/design/COMPONENT_STATE_MATRIX.md"

STATES = [
    "default", "hover", "focus", "pressed", "selected", "disabled", "loading",
    "success", "warning", "error", "offline", "syncing", "conflict",
    "read-only", "permission denied", "expired", "revoked",
]

# Component categories, matched against the component name in order.
INTERACTIVE = (
    "button", "link", "field", "area", "dropdown", "autocomplete", "picker",
    "checkbox", "radio", "switch", "segmented", "chip", "tabs", "navigation",
    "rail", "breadcrumb", "switcher", "selector", "pagination", "uploader",
    "signature", "filter", "bulk action", "stepper", "list", "table",
)
INPUT = (
    "field", "area", "dropdown", "autocomplete", "picker", "checkbox", "radio",
    "switch", "segmented", "uploader", "signature",
)
SELECTABLE = (
    "chip", "checkbox", "radio", "switch", "segmented", "tabs", "navigation",
    "rail", "list", "table", "card", "selector", "switcher",
)
CONTAINER = (
    "card", "summary", "preview", "list", "table", "timeline", "panel",
    "sheet", "dialog", "drawer", "banner", "bar", "state", "skeleton",
    "indicator", "chart", "kpi", "avatar", "tooltip", "snackbar", "toast",
    "badge", "progress",
)
# Components that exist precisely to express a sync or connectivity condition.
SYNC_AWARE = (
    "offline banner", "sync indicator", "conflict panel", "order card",
    "production job card", "courier job card", "payment summary",
    "receivable summary", "receipt preview", "photo evidence",
    "attachment uploader", "signature capture", "otp proof", "list",
    "data table", "timeline", "audit timeline",
)
# Components that can express an authorisation or lifetime condition.
GATED = (
    "card", "summary", "preview", "table", "list", "panel", "timeline",
    "bulk action bar", "filter bar", "tenant switcher", "outlet selector",
    "map preview", "photo evidence", "chart", "kpi card", "audit timeline",
)
TRANSIENT = ("snackbar", "toast", "tooltip", "skeleton", "progress indicator")

NOTE = {}


def has(name: str, needles) -> bool:
    low = name.lower()
    return any(n in low for n in needles)


def resolve(name: str, state: str, block: str = "") -> str:
    """Resolve one cell.

    Where the catalog states a contract for a component, that contract decides.
    Guessing from the component's name is what produced an earlier defect in
    which interactive cards and dialogs were marked focus = NOT APPLICABLE while
    the catalog gave them explicit Tab-and-Enter keyboard contracts. The catalog
    is the specification; this matrix must agree with it.
    """
    low_block = block.lower()

    # A component the catalog gives a keyboard contract to IS focusable, full
    # stop. The focus indicator is never removed (Rule 27, DEC-0021).
    if state == "focus" and block:
        if any(k in low_block for k in
               ("keyboard contract", "tab ", "tab,", "tab to", "enter",
                "space", "arrow key", "focus")):
            return "APPLICABLE"

    # Likewise, a component the catalog describes as carrying an offline,
    # syncing or conflict condition resolves those states.
    if state in ("offline", "syncing", "conflict") and block:
        if state in low_block or (
            state == "syncing" and "sync" in low_block
        ):
            return "APPLICABLE"

    if state in ("expired", "revoked") and block and state in low_block:
        return "APPLICABLE"

    if state == "permission denied" and block and "permission" in low_block:
        return "APPLICABLE"

    if state == "read-only" and block and (
        "read-only" in low_block or "read only" in low_block
    ):
        return "APPLICABLE"

    return _resolve_by_category(name, state)


def _resolve_by_category(name: str, state: str) -> str:
    low = name.lower()
    interactive = has(name, INTERACTIVE)
    transient = has(name, TRANSIENT)

    if state == "default":
        return "APPLICABLE"

    if state in ("hover", "pressed"):
        if transient or low.startswith(("empty state", "loading state",
                                        "error state", "skeleton")):
            return "NOT APPLICABLE"
        return "APPLICABLE" if interactive else "NOT APPLICABLE"

    if state == "focus":
        # Anything reachable by keyboard resolves focus, and the indicator is
        # never removed. Purely decorative surfaces do not receive focus.
        return "APPLICABLE" if interactive else "NOT APPLICABLE"

    if state == "selected":
        return "APPLICABLE" if has(name, SELECTABLE) else "NOT APPLICABLE"

    if state == "disabled":
        return "APPLICABLE" if interactive else "NOT APPLICABLE"

    if state == "loading":
        if transient:
            return "NOT APPLICABLE"
        return "APPLICABLE" if (interactive or has(name, CONTAINER)) \
            else "NOT APPLICABLE"

    if state == "success":
        return "APPLICABLE" if (has(name, INPUT) or has(name, CONTAINER)
                                or "button" in low) else "NOT APPLICABLE"

    if state == "warning":
        return "APPLICABLE" if (has(name, INPUT) or has(name, CONTAINER)) \
            else "NOT APPLICABLE"

    if state == "error":
        if low in ("empty state",):
            return "NOT APPLICABLE"
        return "APPLICABLE" if (has(name, INPUT) or has(name, CONTAINER)
                                or interactive) else "NOT APPLICABLE"

    if state in ("offline", "syncing", "conflict"):
        return "APPLICABLE" if has(name, SYNC_AWARE) else "NOT APPLICABLE"

    if state == "read-only":
        return "APPLICABLE" if (has(name, INPUT) or has(name, CONTAINER)) \
            else "NOT APPLICABLE"

    if state == "permission denied":
        return "APPLICABLE" if (has(name, GATED) or "button" in low
                                or "bulk" in low) else "NOT APPLICABLE"

    if state in ("expired", "revoked"):
        # Only surfaces that can carry a credential-bound or time-bound object.
        return "APPLICABLE" if any(
            n in low for n in ("tracking summary", "otp", "map preview",
                               "photo evidence", "attachment", "signature",
                               "tenant switcher", "audit timeline",
                               "courier job card")
        ) else "NOT APPLICABLE"

    return "NOT APPLICABLE"


def main() -> int:
    root = repo_root()
    catalog = (root / CATALOG).read_text(encoding="utf-8", errors="replace")

    components = re.findall(r"^#{2,4}\s*(CMP-\d{3})\s*[—-]\s*(.+)$",
                            catalog, re.M)
    if not components:
        print("ERROR: no components found in the catalog", file=sys.stderr)
        return 1

    lines = [
        "# Component State Matrix",
        "",
        "> **Step 2 — Design System and UX Foundation. Documentation only.**",
        "> A resolved cell is a specification, never an implemented behaviour.",
        "> No component exists. The Flutter workspace is `ABSENT` and every",
        "> component in this matrix is `NOT IMPLEMENTED`.",
        ">",
        "> This file is generated by `scripts/build-component-state-matrix.py`",
        "> from [`COMPONENT_CATALOG.md`](COMPONENT_CATALOG.md), so a component",
        "> added to the catalog cannot arrive here with an unresolved row.",
        "",
        "---",
        "",
        "## 1. Why every cell is filled",
        "",
        "Every component is resolved against every state as either "
        "`APPLICABLE` or `NOT APPLICABLE`. **There are no blank cells.** A "
        "blank cell is not a neutral absence — it is an undecided design, and "
        "an undecided design becomes a defect in Step 5 when somebody has to "
        "guess what a disabled Money Field looks like while a customer waits "
        "at a counter.",
        "",
        "`NOT APPLICABLE` is a real answer and a deliberate one. It means the "
        "state cannot arise for that component, not that nobody looked.",
        "",
        "## 2. The applicability rules",
        "",
        "| State | Applies to |",
        "|---|---|",
        "| `default` | Every component, without exception. |",
        "| `hover` · `pressed` | Interactive components on pointer and touch "
        "surfaces. Transient and purely presentational components do not "
        "resolve them. |",
        "| `focus` | Every keyboard-reachable component. **The focus indicator "
        "is never removed, never set to none, and never rendered below 3:1** "
        "(Rule 27, DEC-0021). |",
        "| `selected` | Components expressing choice or current position. |",
        "| `disabled` | Interactive components only. A disabled control always "
        "carries a reason; `color.semantic.disabled` is never the sole signal. |",
        "| `loading` | Components that await data or acknowledgement. |",
        "| `success` · `warning` · `error` | Inputs and content containers. "
        "**Every one carries an icon and a text label as well as a colour** — "
        "status is never conveyed by colour alone. |",
        "| `offline` · `syncing` · `conflict` | Components that carry an "
        "operation the server may not yet have acknowledged. `syncing` never "
        "means accepted; `conflict` is always resolved by a human. |",
        "| `read-only` | Components that can render without permitting edits. |",
        "| `permission denied` | Components whose content is authorisation-"
        "gated. The denial never reveals whether the record exists. |",
        "| `expired` · `revoked` | Components bound to a token, a credential, "
        "a device, or a time-limited grant. |",
        "",
        "## 3. The matrix",
        "",
        "| Component | " + " | ".join(f"`{s}`" for s in STATES) + " |",
        "|---" * (len(STATES) + 1) + "|",
    ]

    # Slice the catalog into per-component blocks so each row is resolved
    # against what the catalog actually specifies for that component.
    blocks: dict = {}
    parts = re.split(r"(?=^#{2,4}\s*CMP-\d{3}\s*[—-])", catalog, flags=re.M)
    for part in parts:
        first = part.lstrip().splitlines()[0] if part.strip() else ""
        m = re.search(r"CMP-\d{3}", first)
        if m:
            blocks[m.group(0)] = part

    counts = {s: 0 for s in STATES}
    for cid, name in components:
        cells = []
        for state in STATES:
            value = resolve(name.strip(), state, blocks.get(cid, ""))
            if value == "APPLICABLE":
                counts[state] += 1
            cells.append(value)
        lines.append(f"| `{cid}` {name.strip()} | " + " | ".join(cells) + " |")

    lines += [
        "",
        f"**{len(components)} components × {len(STATES)} states = "
        f"{len(components) * len(STATES)} resolved cells. Blank cells: 0.**",
        "",
        "## 4. Applicability totals",
        "",
        "| State | Components where it applies |",
        "|---|---:|",
    ]
    for state in STATES:
        lines.append(f"| `{state}` | {counts[state]} / {len(components)} |")

    lines += [
        "",
        "## 5. The states that are never negotiable",
        "",
        "- **`focus` is never removed.** Not for aesthetics, not because a "
        "design looks cleaner without it, not on any surface.",
        "- **No status is conveyed by colour alone.** Every `success`, "
        "`warning`, `error`, `offline`, `syncing` and `conflict` cell above "
        "carries a semantic colour, a semantic icon, and a Bahasa Indonesia "
        "text label — three redundant signals.",
        "- **`syncing` never means accepted.** A component in `syncing` states "
        "that the server has not yet acknowledged the operation.",
        "- **`conflict` is never auto-resolved.** It is surfaced for a human.",
        "- **`permission denied` never leaks existence.** It does not "
        "distinguish \"you may not see this\" from \"this does not exist\".",
        "",
        "## 6. Related",
        "",
        "- [`COMPONENT_CATALOG.md`](COMPONENT_CATALOG.md) — the component "
        "contracts these states resolve against",
        "- [`../ux/UX_STATE_MODEL.md`](../ux/UX_STATE_MODEL.md) — the "
        "screen-level state taxonomy",
        "- [`ACCESSIBILITY.md`](ACCESSIBILITY.md) — the focus and colour-"
        "independence obligations",
        "- [Rule 34 — Component and Screen Governance]"
        "(../../.claude/rules/34-component-and-screen-governance.md)",
        "",
    ]

    (root / OUT).write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"components resolved : {len(components)}")
    print(f"states per component: {len(STATES)}")
    print(f"cells written       : {len(components) * len(STATES)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
