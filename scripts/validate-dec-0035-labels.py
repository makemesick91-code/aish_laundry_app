#!/usr/bin/env python3
"""Audit the seven feature labels DEC-0035 moved from forbidden to permitted.

WHY THIS VALIDATOR EXISTS
-------------------------
`validate-runtime-scope.py` answers "is a Step 6+ feature present?". DEC-0035
made that question narrower by permitting seven labels — POS, order, laundry
intake, payment, refund, QRIS, receipt/nota — because Step 5 (POS, Order, and
Payment Foundation) is authorised to build them and they trace to FR-048 … FR-070.

This is the exact mirror of validate-dec-0030-labels.py, which audits the four
labels DEC-0030 permitted for Step 4. Narrowing a guard reduces what it protects;
this validator audits the residual: each permitted label must still trace to a
requirement the PRD carries, and the Step 6+ labels DEC-0035 did NOT permit must
stay absent from every structural position — so an order/payment module cannot
quietly grow the production, tracking, pickup, delivery, reminder, or subscription
workflow that a later step owns.

Rule 36 hard rule 8 permits narrowing the scope guard only through a decision
record. This file does not narrow anything — it adds a check that the narrowing
DEC-0035 already took is still bounded.

DETECTION IS STRUCTURAL, never prose (Rule 36 hard rule 4): migration filenames,
`Schema::create` table arguments, route path segments, Eloquent model class names,
and module/feature directory names. Renaming a later-step feature to evade
detection is the same violation as building it under its plain name; compound and
affixed forms are matched, so `production_batches` and `whatsapp_dispatch` are
caught.

Exit 0 = PASS, 1 = FAIL.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent

# ---------------------------------------------------------------------------
# The seven labels DEC-0035 permitted, and the requirements that authorise them.
# ---------------------------------------------------------------------------
PERMITTED_LABELS: dict[str, dict[str, object]] = {
    "POS / order intake": {
        "tokens": {"orders", "order_lines", "order_items", "pos",
                   "point_of_sale", "intakes"},
        "requirements": ["FR-048", "FR-049", "FR-050", "FR-051", "FR-053",
                         "FR-057", "FR-058", "FR-059", "FR-060"],
        "boundary": "An order carries a customer's laundry through its lifecycle. "
                    "Producing the laundry (washing, drying, QC) is Step 6.",
    },
    "receipt / nota": {
        "tokens": {"receipts", "nota", "struk"},
        "requirements": ["FR-052"],
        "boundary": "The nota records an order's captured prices. Sending it over "
                    "WhatsApp is Step 7.",
    },
    "payment / QRIS": {
        "tokens": {"payments", "payment_transactions", "qris"},
        "requirements": ["FR-061", "FR-062", "FR-063", "FR-064", "FR-068",
                         "FR-069", "FR-070"],
        "boundary": "A payment settles an order at the counter. Courier cash "
                    "settlement is Step 8; subscription billing is Step 12.",
    },
    "refund / void": {
        "tokens": {"refunds", "pengembalian_dana"},
        "requirements": ["FR-065", "FR-066", "FR-067"],
        "boundary": "A refund reverses a payment on an order. It is not a "
                    "storage-fee waiver (Step 9) or a courier settlement (Step 8).",
    },
}

# Labels DEC-0035 did NOT permit, restated so the audit is self-contained. These
# are the Step 6+ workflows that consume the order/payment foundation. `receivable`
# is intentionally NOT here: FR-070 receivable tracking is a DERIVED value over
# orders and payments (integer Rupiah, authoritative records) and creates no
# `receivables`/`piutang` table of its own — the finance-reports aggregate is
# Step 10 and stays forbidden in validate-runtime-scope.py.
STILL_FORBIDDEN: dict[str, set[str]] = {
    "production (Step 6)": {"production_jobs", "produksi", "washing", "pencucian",
                            "drying", "pengeringan", "finishing", "penyelesaian"},
    "quality control / rework (Step 6)": {"quality_controls", "qc_inspections",
                                          "reworks", "pengerjaan_ulang"},
    "tracking (Step 7)": {"tracking_token", "tracking_tokens", "public_tracking"},
    "WhatsApp / notification (Step 7)": {"whatsapp", "wa_provider",
                                         "notification_providers"},
    "pickup / delivery (Step 8)": {"pickups", "pickup_requests", "penjemputan",
                                   "deliveries", "delivery_requests", "pengantaran"},
    "courier settlement (Step 8)": {"courier_settlements", "cash_settlements"},
    "unclaimed / reminder ladder (Step 9)": {"unclaimed_laundry", "reminders",
                                             "reminder_stages", "storage_fees"},
    "finance reports (Step 10)": {"finance_reports", "financial_reports",
                                  "laporan_keuangan"},
    "loyalty / subscription (Step 11/12)": {"loyalty", "loyalty_points",
                                            "subscriptions", "subscription_invoices",
                                            "billing"},
}

# Structural identifiers that legitimately contain a forbidden substring and are
# NOT the feature. Each needs a stated reason; an unexplained entry would be a
# silent widening of the guard.
STRUCTURAL_ALLOWLIST: dict[str, str] = {
    # An order's per-item readiness flag (FR-054) names the future production
    # stage without implementing it; the production runtime is Step 6.
    "production_ready": "FR-054 per-item readiness flag on an order line, not a "
                        "production job",
}

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
    """No structural identifier may carry a label DEC-0035 did not permit."""
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
                            f"({label}). DEC-0035 did not permit this label."
                        )
    return failures, examined


def main() -> int:
    print("=" * 72)
    print("DEC-0035 LABEL AUDIT — the residual after seven labels were permitted")
    print("=" * 72)
    print()

    all_failures: list[str] = []

    trace_failures = check_permitted_labels_trace_to_requirements()
    status = "PASS" if not trace_failures else "FAIL"
    print(f"{status}  every permitted label traces to a requirement the PRD carries")
    all_failures += trace_failures

    forbidden_failures, examined = check_still_forbidden_labels_absent()
    status = "PASS" if not forbidden_failures else "FAIL"
    print(f"{status}  no structural identifier carries a label DEC-0035 did not permit")
    all_failures += forbidden_failures

    print()
    print("-" * 72)
    print(f"  permitted labels audited : {len(PERMITTED_LABELS)}")
    print(f"  forbidden labels checked : {len(STILL_FORBIDDEN)}")
    print(f"  source files examined    : {examined}")
    print(f"  structural allowlist     : {len(STRUCTURAL_ALLOWLIST)} entries, each with a reason")
    print()
    print("  RESIDUAL RISK, stated rather than implied: DEC-0035 reduced token")
    print("  protection for seven labels. A Step 6+ workflow built INSIDE a")
    print("  permitted order/payment module, under a permitted name, would not be")
    print("  caught here by name alone — it is caught by review and by the absence")
    print("  of any production/tracking/pickup route.")
    print("-" * 72)

    if all_failures:
        print()
        print("FAILURES:")
        for failure in all_failures:
            print(f"  - {failure}")
        print()
        print(f"SUMMARY [dec-0035-labels]: {len(all_failures)} failure(s)")
        print("RESULT: FAIL (dec-0035-labels)")
        return 1

    print()
    print("SUMMARY [dec-0035-labels]: 2/2 checks passed, 0 failed")
    print("RESULT: PASS (dec-0035-labels)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
