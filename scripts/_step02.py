#!/usr/bin/env python3
"""Shared helpers for Aish Laundry App Step 2 validators.

Standard library only. No third-party dependencies.

Step 2 is documentation only. Nothing here builds, deploys, or tests an
application, because no application exists.
"""

from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Dict, Iterable, List, Tuple

from _common import repo_root

TOKENS_DIR = "docs/design/tokens"
DESIGN_DIR = "docs/design"
UX_DIR = "docs/ux"
WIREFRAME_DIR = "docs/ux/wireframes"

TOKEN_REF = re.compile(r"^\{([A-Za-z0-9_.]+)\}$")

# The exact wording Step 2 is required to use about accessibility. Anything
# stronger is a false claim under Rule 01: nothing has been runtime-tested.
A11Y_CLAIM = "DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS"
A11Y_CAVEAT = "NOT YET RUNTIME-TESTED"

# Wording that would overstate what Step 2 produced.
FORBIDDEN_CLAIMS = [
    "WCAG certified",
    "WCAG-certified",
    "accessibility audit passed",
    "accessibility tested",
    "dark mode is available",
    "dark theme is available",
    "dark mode available",
    "final logo",
    "official logo",
    "logo approved",
    "screens implemented",
    "wireframes implemented",
    "design system implemented",
]

REQUIREMENT_ID = re.compile(
    r"\b(FR|NFR|SEC|TEN|FIN|OFF|TRK|DEL|UCL|NOT|SUB|RPT)-[0-9]{3,4}\b"
)

SCREEN_ID = re.compile(r"\bSCR-(CUS|OPS|CON|TRK)-[0-9]{3}\b")
COMPONENT_ID = re.compile(r"\bCMP-[0-9]{3}\b")
UX_STATE_ID = re.compile(r"\bUXS-[0-9]{3}\b")
JOURNEY_ID = re.compile(r"\bJRN-[0-9]{3}\b")
FINDING_ID = re.compile(r"\bDUX-[0-9]{3}\b")

UX_CLASSES = ("UI-DIRECT", "UI-INDIRECT", "NON-UI", "DEFERRED-UX")

CANONICAL_ORDER_STATUSES = [
    "DRAFT", "RECEIVED", "AWAITING_PROCESS", "SORTING", "WASHING", "DRYING",
    "FINISHING", "QUALITY_CONTROL", "REWORK", "READY_FOR_PICKUP",
    "SCHEDULED_FOR_DELIVERY", "OUT_FOR_DELIVERY", "COMPLETED", "CANCELLED",
    "ISSUE",
]

CANONICAL_DELIVERY_STATUSES = [
    "REQUESTED", "CONFIRMED", "SCHEDULED", "ASSIGNED", "EN_ROUTE", "ARRIVED",
    "PICKED_UP", "DELIVERED", "FAILED", "RESCHEDULED", "CANCELLED",
]

CANONICAL_QC_STATUSES = [
    "PENDING", "PASSED", "FAILED_REWORK_REQUIRED", "WAIVED_WITH_AUTHORIZATION",
]

REQUIRED_SEMANTIC_COLORS = [
    "primary", "secondary", "success", "warning", "danger", "information",
    "neutral", "focus", "selected", "disabled", "offline", "syncing",
    "conflict",
]

# Sync states the Ops Android surface must distinguish. Collapsing any two of
# these is what produces a silent sync failure.
REQUIRED_SYNC_STATES = [
    "Saved Locally", "Waiting to Sync", "Syncing", "Synced", "Sync Failed",
    "Conflict", "Server Rejected", "Retry Scheduled",
    "Manual Attention Required",
]

MIN_TOUCH_TARGET_DP = 48
GRID_BASE_PX = 4


# --------------------------------------------------------------------------
# Token loading
# --------------------------------------------------------------------------

def token_files(root: Path) -> List[Path]:
    """Every token file except the schema itself, in deterministic order."""
    d = root / TOKENS_DIR
    if not d.is_dir():
        return []
    return sorted(p for p in d.glob("*.json") if p.name != "token-schema.json")


def load_tokens(root: Path) -> Tuple[Dict[str, dict], Dict[str, str], List[str]]:
    """Load every token.

    Returns (tokens, origin_file_by_name, errors). A duplicate token name is
    an error, not a merge: two files claiming the same name means two sources
    of truth for one value.
    """
    tokens: Dict[str, dict] = {}
    origin: Dict[str, str] = {}
    errors: List[str] = []

    for path in token_files(root):
        rel = path.relative_to(root).as_posix()
        try:
            doc = json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError as exc:
            errors.append(f"{rel} is not valid JSON: {exc}")
            continue
        if not isinstance(doc, dict) or "tokens" not in doc:
            errors.append(f"{rel} has no top-level 'tokens' object")
            continue
        for name, body in doc["tokens"].items():
            if name in tokens:
                errors.append(
                    f"duplicate token '{name}' in {rel} "
                    f"(already defined in {origin[name]})"
                )
                continue
            tokens[name] = body
            origin[name] = rel
    return tokens, origin, errors


def resolve_token(
    name: str, tokens: Dict[str, dict], _seen: frozenset = frozenset()
) -> Tuple[object, str]:
    """Resolve a token to its literal value.

    Returns (value, error). Exactly one of the two is meaningful: on success
    error is "", on failure value is None.
    """
    if name not in tokens:
        return None, f"unknown token '{name}'"
    if name in _seen:
        chain = " -> ".join(sorted(_seen)) + f" -> {name}"
        return None, f"circular reference: {chain}"
    value = tokens[name].get("value")
    if not isinstance(value, str):
        return value, ""
    match = TOKEN_REF.match(value)
    if not match:
        return value, ""
    return resolve_token(match.group(1), tokens, _seen | {name})


# --------------------------------------------------------------------------
# WCAG 2.2 contrast — computed from the hex value, never asserted
# --------------------------------------------------------------------------

def _channel(value: int) -> float:
    c = value / 255.0
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def relative_luminance(hex_color: str) -> float:
    h = hex_color.lstrip("#")
    if len(h) != 6:
        raise ValueError(f"expected a 6-digit hex colour, got '{hex_color}'")
    r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
    return 0.2126 * _channel(r) + 0.7152 * _channel(g) + 0.0722 * _channel(b)


def contrast_ratio(a: str, b: str) -> float:
    la, lb = relative_luminance(a), relative_luminance(b)
    hi, lo = max(la, lb), min(la, lb)
    return round((hi + 0.05) / (lo + 0.05), 2)


CONTRAST_MINIMUM = {
    "normal-text-4.5": 4.5,
    "large-text-3.0": 3.0,
    "interactive-boundary-3.0": 3.0,
}

EXEMPT_TARGETS = {"decorative-exempt", "inactive-exempt", "background-only"}


# --------------------------------------------------------------------------
# Document helpers
# --------------------------------------------------------------------------

def read(root: Path, rel: str) -> str:
    path = root / rel
    if not path.is_file():
        return ""
    return path.read_text(encoding="utf-8", errors="replace")


def markdown_files(root: Path, *rel_dirs: str) -> List[Path]:
    out: List[Path] = []
    for rel in rel_dirs:
        d = root / rel
        if d.is_dir():
            out.extend(sorted(d.rglob("*.md")))
    return out


def ids_in(text: str, pattern: re.Pattern) -> set:
    return {m.group(0) for m in pattern.finditer(text)}


def strip_emphasis(text: str) -> str:
    """Remove markdown emphasis so a guard word survives the scan.

    "The product is **not** WCAG certified" must read as guarded. Without this,
    the literal "not " never appears and an honest sentence is flagged as a
    false claim — which would train an author to remove the honesty.
    """
    return text.replace("**", "").replace("__", "").replace("*", "").replace("`", "")


def unclosed_fences(text: str) -> bool:
    """True when the file has an odd number of ``` fences."""
    return text.count("```") % 2 != 0


def requirement_registry(root: Path) -> set:
    """Every requirement ID referenced anywhere under docs/.

    This is the union that Step 1 closed at 498 IDs. Step 2 classifies this
    same set; it never redefines it and never invents a new one.
    """
    found: set = set()
    for path in (root / "docs").rglob("*.md"):
        found |= ids_in(path.read_text(encoding="utf-8", errors="replace"),
                        REQUIREMENT_ID)
    return found


__all__ = [name for name in dir() if not name.startswith("_")]
