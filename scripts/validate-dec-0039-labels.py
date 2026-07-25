#!/usr/bin/env python3
"""Audit the four feature labels DEC-0039 moved from forbidden to permitted.

WHY THIS VALIDATOR EXISTS
-------------------------
`validate-runtime-scope.py` answers "is a Step 8+ feature present?". DEC-0039
made that question narrower by permitting four labels — tracking portal, tracking
token, WhatsApp, notification provider — because Step 7 (Customer Tracking and
WhatsApp) is authorised to build them and they trace to FR-086 … FR-099.

This is the exact mirror of validate-dec-0037-labels.py (six Step 6 labels),
validate-dec-0035-labels.py (seven Step 5 labels), and validate-dec-0030-labels.py
(four Step 4 labels). Narrowing a guard reduces what it protects; this validator
audits the residual: each permitted label must still trace to a requirement the PRD
carries, and the Step 8+ labels DEC-0039 did NOT permit must stay absent from every
structural position — so a tracking/notification module cannot quietly grow the
pickup, delivery, courier, reminder, unclaimed-laundry, finance, or subscription
workflow that a later step owns.

Rule 36 hard rule 8 permits narrowing the scope guard only through a decision
record. This file does not narrow anything — it adds a check that the narrowing
DEC-0039 already took is still bounded.

DETECTION IS STRUCTURAL, never prose (Rule 36 hard rule 4): migration filenames,
`Schema::create` table arguments, route path segments, Eloquent model class names,
and module/feature directory names. Renaming a later-step feature to evade
detection is the same violation as building it under its plain name; compound and
affixed forms are matched, so `pickup_requests` and `reminder_schedules` are caught.

Exit 0 = PASS, 1 = FAIL.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent

# ---------------------------------------------------------------------------
# The four labels DEC-0039 permitted, and the requirements that authorise them.
# ---------------------------------------------------------------------------
PERMITTED_LABELS: dict[str, dict[str, object]] = {
    "tracking portal": {
        "tokens": {"tracking_portal", "public_tracking"},
        "requirements": ["FR-089", "FR-090", "FR-091", "FR-092"],
        "boundary": "The portal shows an order's safe-by-default status to the "
                    "customer. Scheduling a pickup or delivery from it is Step 8.",
    },
    "tracking token": {
        "tokens": {"tracking_tokens", "tracking_token"},
        "requirements": ["FR-086", "FR-087", "FR-088"],
        "boundary": "A high-entropy hashed token gates the portal view. The "
                    "external-courier guest link is a different credential, Step 8.",
    },
    "WhatsApp": {
        "tokens": {"whatsapp", "wa_provider", "whatsapp_messages"},
        "requirements": ["FR-093", "FR-094", "FR-095"],
        "boundary": "WhatsApp notifies a customer about an order. The H+1/H+3/H+7 "
                    "reminder LADDER that decides WHEN to chase unclaimed laundry is "
                    "Step 9.",
    },
    "notification provider": {
        "tokens": {"notification_providers", "notification_dispatch"},
        "requirements": ["FR-096", "FR-097", "FR-098", "FR-099"],
        "boundary": "The notification subsystem sends transactional/marketing "
                    "messages with consent, quiet hours, and dedup. Delivery-proof "
                    "and courier-settlement messaging is Step 8.",
    },
}

# Labels DEC-0039 did NOT permit, restated so the audit is self-contained. These are
# the Step 8+ workflows that consume the tracking/notification foundation. `routes`
# is deliberately NOT a bare token here: it would false-match the `backend/routes/`
# directory. The courier-routing feature is caught by its specific
# `route_stops`/`courier_routes` tokens instead — the same conservative choice the
# earlier DEC label audits made.
STILL_FORBIDDEN: dict[str, set[str]] = {
    "pickup / delivery (Step 8)": {"pickups", "pickup_requests", "penjemputan",
                                   "deliveries", "delivery_requests", "pengantaran"},
    "courier routing / proof / settlement (Step 8)": {
        "route_stops", "courier_routes", "delivery_proofs", "proof_of_delivery",
        "courier_settlements", "cash_settlements"},
    "unclaimed / reminder ladder / storage fee (Step 9)": {
        "unclaimed_laundry", "cucian_menumpuk", "reminders", "reminder_stages",
        "reminder_schedules", "storage_fees", "biaya_penyimpanan"},
    "receivables / finance reports (Step 10)": {"receivables", "piutang",
                                                "finance_reports", "financial_reports",
                                                "laporan_keuangan"},
    "loyalty / membership / subscription (Step 11/12)": {
        "loyalty", "loyalty_points", "poin_loyalitas", "membership_programs",
        "loyalty_memberships", "subscriptions", "subscription_invoices", "billing"},
}

# Structural identifiers that legitimately contain a forbidden substring and are
# NOT the feature. Each needs a stated reason; an unexplained entry would be a
# silent widening of the guard.
STRUCTURAL_ALLOWLIST: dict[str, str] = {}

SCAN_ROOTS = [
    REPO / "backend" / "app",
    REPO / "backend" / "database",
    REPO / "backend" / "routes",
    REPO / "packages",
    REPO / "apps",
]

SKIP_DIRS = {".dart_tool", "build", "node_modules", "vendor", ".git", "__pycache__"}

PRD = REPO / "docs" / "product" / "PRODUCT_REQUIREMENTS.md"


def iter_source_files():
    for root in SCAN_ROOTS:
        if not root.exists():
            continue
        for path in root.rglob("*"):
            if not path.is_file():
                continue
            if any(part in SKIP_DIRS for part in path.parts):
                continue
            if path.suffix in {".php", ".dart", ".yaml", ".yml"}:
                yield path


def _snake_case(identifier: str) -> str:
    spaced = re.sub(r"(?<!^)(?=[A-Z])", "_", identifier)
    return spaced.lower()


def structural_identifiers(path: Path, text: str) -> set[str]:
    """Extract only the identifiers Rule 36 hard rule 4 treats as structural."""
    found: set[str] = set()

    if "migrations" in path.parts:
        found.add(path.stem.lower())

    for part in path.parts:
        if part in {"Modules", "app", "lib", "src"}:
            continue
        found.add(part.lower().removesuffix(path.suffix))

    found.update(m.lower() for m in re.findall(r"Schema::create\(\s*'([a-z0-9_]+)'", text))
    found.update(m.lower() for m in re.findall(r"Schema::table\(\s*'([a-z0-9_]+)'", text))
    found.update(m.lower() for m in re.findall(r"\$table\s*=\s*'([a-z0-9_]+)'", text))

    for route in re.findall(r"Route::(?:get|post|patch|put|delete)\(\s*'([^']+)'", text):
        for segment in route.split("/"):
            segment = segment.strip("{}?").lower()
            if segment:
                found.add(segment)

    if "Models" in path.parts:
        for class_name in re.findall(r"\bclass\s+([A-Za-z0-9_]+)", text):
            found.add(class_name.lower())
            found.add(_snake_case(class_name))

    return {f for f in found if f}


def token_is_allowlisted(identifier: str) -> str | None:
    for allowed, reason in STRUCTURAL_ALLOWLIST.items():
        if allowed in identifier:
            return reason
    return None


def matches_forbidden(identifier: str, token: str) -> bool:
    """Match the token as a whole word OR as an affixed compound."""
    return re.search(rf"(^|[_\-]){re.escape(token)}(s|es)?([_\-]|$)", identifier) is not None


def check_permitted_labels_trace_to_requirements() -> list[str]:
    """Every permitted label must cite requirements the PRD actually carries."""
    failures: list[str] = []
    if not PRD.exists():
        return [f"PRD not found at {PRD.relative_to(REPO)}; cannot verify tracing."]
    prd_text = PRD.read_text(encoding="utf-8")
    for label, spec in PERMITTED_LABELS.items():
        for requirement in spec["requirements"]:  # type: ignore[index]
            if requirement not in prd_text:
                failures.append(
                    f"permitted label '{label}' cites {requirement}, which the PRD "
                    f"does not carry. A label permitted on the strength of a "
                    f"requirement that does not exist is permitted on nothing."
                )
    return failures


def check_still_forbidden_labels_absent() -> tuple[list[str], int]:
    """No structural identifier may carry a label DEC-0039 did not permit."""
    failures: list[str] = []
    examined = 0
    for path in iter_source_files():
        try:
            text = path.read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue
        examined += 1
        for identifier in structural_identifiers(path, text):
            if token_is_allowlisted(identifier):
                continue
            for label, tokens in STILL_FORBIDDEN.items():
                for token in tokens:
                    if matches_forbidden(identifier, token):
                        failures.append(
                            f"{path.relative_to(REPO)}: structural identifier "
                            f"'{identifier}' carries the token '{token}' "
                            f"({label}). DEC-0039 did not permit this label."
                        )
    return failures, examined


def main() -> int:
    print("=" * 72)
    print("DEC-0039 LABEL AUDIT — the residual after four labels were permitted")
    print("=" * 72)
    print()

    all_failures: list[str] = []

    trace_failures = check_permitted_labels_trace_to_requirements()
    status = "PASS" if not trace_failures else "FAIL"
    print(f"{status}  every permitted label traces to a requirement the PRD carries")
    all_failures += trace_failures

    forbidden_failures, examined = check_still_forbidden_labels_absent()
    status = "PASS" if not forbidden_failures else "FAIL"
    print(f"{status}  no structural identifier carries a label DEC-0039 did not permit")
    all_failures += forbidden_failures

    print()
    print("-" * 72)
    print(f"  permitted labels audited : {len(PERMITTED_LABELS)}")
    print(f"  forbidden labels checked : {len(STILL_FORBIDDEN)}")
    print(f"  source files examined    : {examined}")
    print(f"  structural allowlist     : {len(STRUCTURAL_ALLOWLIST)} entries, each with a reason")
    print()
    print("  RESIDUAL RISK, stated rather than implied: DEC-0039 reduced token")
    print("  protection for four labels. A Step 8+ workflow built INSIDE a permitted")
    print("  tracking/notification module, under a permitted name, would not be caught")
    print("  here by name alone — it is caught by review and by the absence of any")
    print("  pickup/delivery/courier/reminder route or table.")
    print("-" * 72)

    if all_failures:
        print()
        print("FAILURES:")
        for failure in all_failures:
            print(f"  - {failure}")
        print()
        print(f"SUMMARY [dec-0039-labels]: {len(all_failures)} failure(s)")
        print("RESULT: FAIL (dec-0039-labels)")
        return 1

    print()
    print("SUMMARY [dec-0039-labels]: 0 failures")
    print("RESULT: PASS (dec-0039-labels)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
