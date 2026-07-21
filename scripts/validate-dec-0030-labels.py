#!/usr/bin/env python3
"""Audit the four feature labels DEC-0030 moved from forbidden to permitted.

WHY THIS VALIDATOR EXISTS
-------------------------
`validate-runtime-scope.py` answers "is a Step 5+ feature present?". DEC-0030
made that question narrower by permitting four labels — service catalog, price
list, customer management, printer configuration — because Step 4 was
authorised to build them (DEC-0028) and they trace to FR-021 … FR-047.

Narrowing a guard reduces what it protects. This validator audits the residual:
it checks that each permitted label is still being used for MASTER DATA and has
not quietly become the Step 5 workflow that consumes it, and that the labels
DEC-0030 did NOT permit are still absent from every structural position.

Rule 36 hard rule 8 permits narrowing the scope guard only through a decision
record. This file does not narrow anything — it adds a check that the narrowing
already taken is still bounded.

WHAT "STRUCTURAL" MEANS HERE, AND WHY IT IS NOT PROSE MATCHING
--------------------------------------------------------------
Detection reads migration filenames, `Schema::create` table arguments, route
path segments, Eloquent model class names, and module/feature directory names —
the same signals Rule 36 hard rule 4 fixes. A guard that flagged the word
"order" in prose would fire on `orderBy()` and on the phrase "in order to", and
would still miss a POS module named `kasir`.

RENAME EVASION IS TREATED AS THE SAME VIOLATION as building the feature under
its plain name (Rule 36 hard rule 4). Compound and affixed forms are matched, so
`sales_receipts`, `nota_penjualan` and `receipt_templates` are all caught.

Exit 0 = PASS, 1 = FAIL.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent

# ---------------------------------------------------------------------------
# The four labels DEC-0030 permitted, and the requirements that authorise them.
# ---------------------------------------------------------------------------
PERMITTED_LABELS: dict[str, dict[str, object]] = {
    "service catalog": {
        "tokens": {"services", "service_categories", "service_packages", "service_addons"},
        "requirements": ["FR-031", "FR-032", "FR-033"],
        "boundary": "The catalogue says WHAT is sold. Applying an entry to an "
                    "orderable line is Step 5.",
    },
    "price list": {
        "tokens": {"price_lists", "price_list_items"},
        "requirements": ["FR-034", "FR-035", "FR-036", "FR-037", "FR-038", "FR-039", "FR-040"],
        "boundary": "A price list is not a priced order. Charging a customer is "
                    "Step 5.",
    },
    "customer management": {
        "tokens": {"customers", "customer_addresses", "customer_consents"},
        "requirements": ["FR-021", "FR-022", "FR-023", "FR-024", "FR-025",
                         "FR-026", "FR-027", "FR-028", "FR-029", "FR-030"],
        "boundary": "A customer record is not an order history. Orders are Step 5.",
    },
    "printer configuration": {
        "tokens": {"outlet_printers"},
        "requirements": ["FR-045"],
        "boundary": "FR-045 authorises printer CONFIGURATION as outlet master "
                    "data. The document a printer prints is FR-052 in Step 5.",
    },
}

# Labels DEC-0030 did NOT permit, restated here so the audit is self-contained.
# `receipt` is the one that matters most: it sits closest to `printer`, and the
# two are separated by a step boundary rather than by subject matter.
STILL_FORBIDDEN: dict[str, set[str]] = {
    "receipt / nota / struk (FR-052, Step 5)": {"receipt", "nota", "struk"},
    "order (Step 5)": {"order", "pesanan", "transaksi"},
    "payment (Step 5)": {"payment", "pembayaran", "qris"},
    "invoice (Step 5)": {"invoice", "faktur"},
    "checkout / cart (Step 5)": {"checkout", "cart", "keranjang"},
    "production (Step 6)": {"produksi", "washing", "drying", "finishing"},
    "tracking (Step 7)": {"tracking_token", "public_tracking"},
    "pickup / delivery (Step 8)": {"pickup", "penjemputan", "delivery", "pengantaran"},
    "reminder ladder (Step 9)": {"reminder", "pengingat"},
    "subscription billing (Step 12)": {"subscription", "langganan"},
}

# Structural identifiers that legitimately contain a forbidden substring and are
# NOT the feature. Each needs a reason; an unexplained entry here would be a
# silent widening of the guard.
STRUCTURAL_ALLOWLIST: dict[str, str] = {
    # `order` as a sort/sequence column, not the aggregate.
    "display_order": "a sort column on catalogue categories, not an order aggregate",
    "sort_order": "a sort column, not an order aggregate",
    # `delivery`/`pickup` as ADDRESS SUITABILITY flags — FR-025 records whether an
    # address can be served, which is master data. Scheduling is Step 8.
    "is_pickup_suitable": "FR-025 address suitability flag; scheduling is Step 8",
    "is_delivery_suitable": "FR-025 address suitability flag; routing is Step 8",
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


def structural_identifiers(path: Path, text: str) -> set[str]:
    """Extract only the identifiers Rule 36 hard rule 4 treats as structural."""
    found: set[str] = set()

    # Migration filenames: 2026_07_21_030000_create_outlet_master_data_tables.php
    if "migrations" in path.parts:
        found.add(path.stem.lower())

    # Module / feature directory names.
    for part in path.parts:
        if part in {"Modules", "app", "lib", "src"}:
            continue
        found.add(part.lower().removesuffix(path.suffix))

    # Schema::create('table_name'
    found.update(m.lower() for m in re.findall(r"Schema::create\(\s*'([a-z0-9_]+)'", text))
    found.update(m.lower() for m in re.findall(r"Schema::table\(\s*'([a-z0-9_]+)'", text))

    # An Eloquent model's explicit table name. A model can name a table the
    # migrations in this repository never created — an out-of-scope table
    # reached through a model is still an out-of-scope table.
    found.update(
        m.lower() for m in re.findall(r"\$table\s*=\s*'([a-z0-9_]+)'", text)
    )

    # Route path segments: Route::get('customers/{customer}/consents'
    for route in re.findall(r"Route::(?:get|post|patch|put|delete)\(\s*'([^']+)'", text):
        for segment in route.split("/"):
            segment = segment.strip("{}?").lower()
            if segment:
                found.add(segment)

    # Dart route constants: static const String customers = '/beranda/pelanggan';
    #
    # A constant named `futureX` is EXCLUDED, and the exclusion is narrow and
    # verified rather than assumed. Those routes exist to DECLARE THE ABSENCE of
    # a later step's feature: they resolve to a placeholder that says "belum
    # tersedia" and reach nothing. Treating `/beranda/produksi` as evidence that
    # production was built would invert what the route is for — and
    # `check_future_routes_are_really_placeholders` below proves each one still
    # resolves to a placeholder rather than trusting the name.
    for name, route in re.findall(
        r"String\s+([A-Za-z0-9_]+)\s*=\s*'(/[a-z0-9\-/:]+)'\s*;", text
    ):
        if name.startswith("future"):
            continue
        for segment in route.split("/"):
            segment = segment.strip(":").lower()
            if segment:
                found.add(segment)

    # Eloquent model class names — only files under a Models directory.
    #
    # SPLIT ON CASE BOUNDARIES before lowercasing. `OutletPrinterReceiptTemplate`
    # lowercases to one unbroken word, and a token matcher that requires a
    # separator would never see `receipt` inside it — which is precisely the
    # rename a scope guard has to catch (Rule 36 hard rule 4).
    if "Models" in path.parts:
        for class_name in re.findall(r"\bclass\s+([A-Za-z0-9_]+)", text):
            found.add(class_name.lower())
            found.add(_snake_case(class_name))

    return {f for f in found if f}


def _snake_case(identifier: str) -> str:
    """`OutletPrinterReceiptTemplate` -> `outlet_printer_receipt_template`."""
    spaced = re.sub(r"(?<!^)(?=[A-Z])", "_", identifier)
    return spaced.lower()


def token_is_allowlisted(identifier: str) -> str | None:
    for allowed, reason in STRUCTURAL_ALLOWLIST.items():
        if allowed in identifier:
            return reason
    return None


def matches_forbidden(identifier: str, token: str) -> bool:
    """Match the token as a whole word OR as an affixed compound.

    `receipts`, `sales_receipt`, `receipt_templates` and `nota_penjualan` all
    match `receipt`/`nota`. Renaming to evade structural detection is the same
    violation as building the feature under its plain name.
    """
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
    """No structural identifier may carry a label DEC-0030 did not permit."""
    failures: list[str] = []
    examined = 0

    for path in iter_source_files():
        try:
            text = path.read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue

        examined += 1
        identifiers = structural_identifiers(path, text)

        for identifier in identifiers:
            if token_is_allowlisted(identifier):
                continue
            for label, tokens in STILL_FORBIDDEN.items():
                for token in tokens:
                    if matches_forbidden(identifier, token):
                        failures.append(
                            f"{path.relative_to(REPO)}: structural identifier "
                            f"'{identifier}' carries the token '{token}' "
                            f"({label}). DEC-0030 did not permit this label."
                        )
    return failures, examined


def check_printer_did_not_become_a_document() -> list[str]:
    """`printer` is permitted; the document it prints is not.

    The two live one word apart, which is exactly why this is checked
    separately: a `printer` module that grows a `receipt_template` has crossed
    a step boundary while keeping a permitted name.
    """
    failures: list[str] = []

    for path in iter_source_files():
        try:
            text = path.read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue

        if "printer" not in text.lower():
            continue

        identifiers = structural_identifiers(path, text)
        for identifier in identifiers:
            for token in ("receipt", "nota", "struk", "template", "render", "print_job"):
                if matches_forbidden(identifier, token):
                    failures.append(
                        f"{path.relative_to(REPO)}: '{identifier}' appears "
                        f"alongside printer configuration. FR-045 authorises "
                        f"printer CONFIGURATION only; the document is FR-052 in "
                        f"Step 5."
                    )
    return failures


def check_future_routes_are_really_placeholders() -> list[str]:
    """A `future*` route constant must resolve to a placeholder, not a screen.

    The forbidden-token scan skips `future*` constants because those routes
    DECLARE the absence of a later step's feature. That skip is only safe while
    the claim is true. If `futureCounter` ever pointed at a real POS screen, the
    exemption would be the exact hole a scope guard exists to close — so the
    claim is checked rather than trusted.

    The check is textual and deliberately conservative: every router file that
    declares `future*` routes must build a placeholder for them, and the router
    must not import a screen whose name suggests the real feature.
    """
    failures: list[str] = []

    for path in iter_source_files():
        if path.suffix != ".dart" or "routing" not in path.parts:
            continue

        try:
            text = path.read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue

        future_constants = re.findall(
            r"String\s+(future[A-Za-z0-9_]*)\s*=\s*'(/[a-z0-9\-/:]+)'\s*;", text
        )
        if not future_constants:
            continue

        # The router that consumes these constants lives alongside them.
        router = path.with_name(path.name.replace("_routes.dart", "_router.dart"))
        if not router.exists():
            failures.append(
                f"{path.relative_to(REPO)} declares {len(future_constants)} "
                f"future-step route(s) but no sibling router was found to check "
                f"what they resolve to."
            )
            continue

        router_text = router.read_text(encoding="utf-8")

        # PER ROUTE, not per file. Asking only whether the word "placeholder"
        # appears anywhere in the router is far too loose: one surviving
        # placeholder elsewhere in the file would vouch for every future route,
        # including one that had been repointed at a real screen.
        for name, route in future_constants:
            segment = route.rstrip("/").rsplit("/", 1)[-1]

            match = re.search(
                rf"path:\s*'{re.escape(segment)}'", router_text
            )
            if match is None:
                failures.append(
                    f"{router.relative_to(REPO)}: `{name}` declares "
                    f"'{route}', but no route with the segment '{segment}' was "
                    f"found. The exemption that skips this segment in the "
                    f"forbidden-token scan cannot be justified."
                )
                continue

            # The builder follows the path within the same GoRoute entry.
            window = router_text[match.end(): match.end() + 320]

            if "_FuturePage" not in window and "FutureStepPlaceholder" not in window:
                failures.append(
                    f"{router.relative_to(REPO)}: `{name}` ('{route}') no "
                    f"longer resolves to a future-step placeholder. A "
                    f"`future*` route that reaches a real screen is scope "
                    f"leakage wearing an exempt name — and the token scan is "
                    f"skipping this segment on the strength of that name."
                )

    return failures


def main() -> int:
    print("=" * 72)
    print("DEC-0030 LABEL AUDIT — the residual after four labels were permitted")
    print("=" * 72)
    print()

    all_failures: list[str] = []

    trace_failures = check_permitted_labels_trace_to_requirements()
    status = "PASS" if not trace_failures else "FAIL"
    print(f"{status}  every permitted label traces to a requirement the PRD carries")
    all_failures += trace_failures

    forbidden_failures, examined = check_still_forbidden_labels_absent()
    status = "PASS" if not forbidden_failures else "FAIL"
    print(f"{status}  no structural identifier carries a label DEC-0030 did not permit")
    all_failures += forbidden_failures

    printer_failures = check_printer_did_not_become_a_document()
    status = "PASS" if not printer_failures else "FAIL"
    print(f"{status}  printer configuration has not become a printed document")
    all_failures += printer_failures

    placeholder_failures = check_future_routes_are_really_placeholders()
    status = "PASS" if not placeholder_failures else "FAIL"
    print(f"{status}  every future-step route still resolves to a placeholder")
    all_failures += placeholder_failures

    print()
    print("-" * 72)
    print(f"  permitted labels audited : {len(PERMITTED_LABELS)}")
    print(f"  forbidden labels checked : {len(STILL_FORBIDDEN)}")
    print(f"  source files examined    : {examined}")
    print(f"  structural allowlist     : {len(STRUCTURAL_ALLOWLIST)} entries, each with a reason")
    print()
    print("  RESIDUAL RISK, stated rather than implied: DEC-0030 reduced token")
    print("  protection for four labels. Those labels are now guarded by the")
    print("  boundary checks above rather than by outright prohibition, which is")
    print("  a weaker control. A Step 5 workflow built INSIDE one of the four")
    print("  permitted modules, under a permitted name, would not be caught here")
    print("  by name alone — it is caught by review and by the absence of any")
    print("  order/payment route (asserted in Step04IsolationMatrixTest).")
    print("-" * 72)

    if all_failures:
        print()
        print("FAILURES:")
        for failure in all_failures:
            print(f"  - {failure}")
        print()
        print(f"SUMMARY [dec-0030-labels]: {len(all_failures)} failure(s)")
        print("RESULT: FAIL (dec-0030-labels)")
        return 1

    print()
    print("SUMMARY [dec-0030-labels]: 4/4 checks passed, 0 failed")
    print("RESULT: PASS (dec-0030-labels)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
