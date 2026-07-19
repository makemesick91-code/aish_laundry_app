#!/usr/bin/env python3
"""Shared helpers for Aish Laundry App Step 1 validators.

Standard library only. No third-party dependencies.

Step 1 is documentation only, so every helper here reads markdown and asserts
something about its content. Nothing here executes, builds, or tests application
code, because no application code exists.
"""

from __future__ import annotations

import re
from pathlib import Path

# --- canonical document sets -------------------------------------------------

PRODUCT_DOCS = [
    "PRODUCT_REQUIREMENTS.md",
    "MVP_SCOPE.md",
    "PERSONAS.md",
    "JOBS_TO_BE_DONE.md",
    "USER_JOURNEYS.md",
    "OPERATIONAL_JOURNEYS.md",
    "USE_CASE_CATALOG.md",
    "SUCCESS_METRICS.md",
    "ASSUMPTIONS_AND_OPEN_QUESTIONS.md",
    "REQUIREMENT_TRACEABILITY.md",
]

DOMAIN_DOCS = [
    "DOMAIN_GLOSSARY.md",
    "BOUNDED_CONTEXTS.md",
    "CONTEXT_MAP.md",
    "AGGREGATE_CATALOG.md",
    "ENTITY_AND_VALUE_OBJECT_CATALOG.md",
    "DOMAIN_INVARIANTS.md",
    "DOMAIN_EVENTS.md",
    "COMMANDS_AND_POLICIES.md",
    "TENANT_BOUNDARIES.md",
    "DATA_OWNERSHIP.md",
    "ORDER_DOMAIN.md",
    "PRODUCTION_AND_QC_DOMAIN.md",
    "PAYMENT_DOMAIN.md",
    "TRACKING_DOMAIN.md",
    "PICKUP_DELIVERY_DOMAIN.md",
    "UNCLAIMED_LAUNDRY_DOMAIN.md",
    "NOTIFICATION_DOMAIN.md",
    "OFFLINE_SYNC_DOMAIN.md",
    "SUBSCRIPTION_DOMAIN.md",
]

STATE_MACHINE_DOCS = [
    "ORDER_STATE_MACHINE.md",
    "PAYMENT_STATE_MACHINE.md",
    "REFUND_STATE_MACHINE.md",
    "PRODUCTION_STATE_MACHINE.md",
    "QUALITY_CONTROL_STATE_MACHINE.md",
    "TRACKING_ACCESS_LIFECYCLE.md",
    "PICKUP_DELIVERY_STATE_MACHINE.md",
    "COURIER_SETTLEMENT_STATE_MACHINE.md",
    "UNCLAIMED_LAUNDRY_STATE_MACHINE.md",
    "SUBSCRIPTION_STATE_MACHINE.md",
]

SECURITY_DOCS = [
    "INITIAL_THREAT_MODEL.md",
    "ABUSE_CASES.md",
    "DATA_CLASSIFICATION.md",
    "TRUST_BOUNDARIES.md",
    "PRIVACY_REQUIREMENTS.md",
    "SECURITY_ACCEPTANCE_CRITERIA.md",
]

QUALITY_DOCS = [
    "NON_FUNCTIONAL_REQUIREMENTS.md",
    "ACCEPTANCE_CRITERIA.md",
    "STEP_01_DEFINITION_OF_DONE.md",
]

STEP01_DOC_SET: dict[str, list[str]] = {
    "docs/product": PRODUCT_DOCS,
    "docs/domain": DOMAIN_DOCS,
    "docs/state-machines": STATE_MACHINE_DOCS,
    "docs/security": SECURITY_DOCS,
    "docs/quality": QUALITY_DOCS,
}

# --- canonical vocabulary ----------------------------------------------------

ORDER_STATUSES = [
    "DRAFT",
    "RECEIVED",
    "AWAITING_PROCESS",
    "SORTING",
    "WASHING",
    "DRYING",
    "FINISHING",
    "QUALITY_CONTROL",
    "REWORK",
    "READY_FOR_PICKUP",
    "SCHEDULED_FOR_DELIVERY",
    "OUT_FOR_DELIVERY",
    "COMPLETED",
    "CANCELLED",
    "ISSUE",
]

DELIVERY_STATUSES = [
    "REQUESTED",
    "CONFIRMED",
    "SCHEDULED",
    "ASSIGNED",
    "EN_ROUTE",
    "ARRIVED",
    "PICKED_UP",
    "DELIVERED",
    "FAILED",
    "RESCHEDULED",
    "CANCELLED",
]

QC_STATUSES = [
    "PENDING",
    "PASSED",
    "FAILED_REWORK_REQUIRED",
    "WAIVED_WITH_AUTHORIZATION",
]

BOUNDED_CONTEXTS = [
    "Identity and Access",
    "Tenant and Organization",
    "Subscription and Entitlement",
    "Customer Management",
    "Service Catalog and Pricing",
    "Order Intake and POS",
    "Production Operations",
    "Quality Control and Rework",
    "Payment and Receivables",
    "Customer Tracking",
    "Pickup and Delivery",
    "Courier Assignment and Settlement",
    "Notification and Communication",
    "Unclaimed Laundry Recovery",
    "Loyalty, Membership, and Deposit",
    "Reporting and Owner Portfolio",
    "Audit and Compliance",
    "Platform Administration",
    "Offline Synchronization",
    "File and Evidence Management",
]

AGGREGATES = [
    "Tenant",
    "Membership",
    "LaundryBrand",
    "Outlet",
    "Customer",
    "CustomerAddress",
    "ServiceCatalog",
    "PriceList",
    "PriceRule",
    "LaundryOrder",
    "OrderLine",
    "OrderConditionEvidence",
    "ProductionJob",
    "QualityControlInspection",
    "Payment",
    "Refund",
    "Receivable",
    "CashierShift",
    "PickupDeliveryJob",
    "CourierAssignment",
    "DeliveryProof",
    "CourierSettlement",
    "TrackingAccess",
    "Notification",
    "ReminderSchedule",
    "UnclaimedLaundryCase",
    "Subscription",
    "AuditEntry",
    "Attachment",
    "OfflineOperation",
    "SyncConflict",
]

PERSONAS = [
    "Platform Super Admin",
    "Platform Support",
    "Tenant Owner",
    "Tenant Admin",
    "Outlet Manager",
    "Cashier",
    "Production Operator",
    "Quality Control",
    "Courier Internal",
    "External Local Courier",
    "Finance",
    "Customer",
    "Corporate Customer Contact",
    "Authorized Order Recipient",
]

DATA_CLASSES = ["PUBLIC", "INTERNAL", "CONFIDENTIAL", "RESTRICTED", "SECRET"]

REQUIREMENT_PREFIXES = [
    "FR",
    "NFR",
    "SEC",
    "TEN",
    "FIN",
    "OFF",
    "TRK",
    "DEL",
    "UCL",
    "NOT",
    "SUB",
    "RPT",
]

#: Matches a requirement identifier such as FR-001 or NFR-042.
REQUIREMENT_ID = re.compile(
    r"\b(" + "|".join(REQUIREMENT_PREFIXES) + r")-(\d{3,4})\b"
)

#: The authoritative register for each requirement series — the one document that
#: DEFINES those identifiers. The same ID legitimately reappears elsewhere as a
#: cross-reference: the aggregate catalogue restates invariants per aggregate,
#: and the traceability matrix lists every ID by design. Treating those
#: restatements as definitions reported the whole corpus as duplicated, so
#: duplicate detection is scoped to the register.
REQUIREMENT_REGISTERS: dict[str, str] = {
    "FR": "PRODUCT_REQUIREMENTS.md",
    "RPT": "PRODUCT_REQUIREMENTS.md",
    "SUB": "PRODUCT_REQUIREMENTS.md",
    "SEC": "SECURITY_ACCEPTANCE_CRITERIA.md",
    "NFR": "NON_FUNCTIONAL_REQUIREMENTS.md",
    "TEN": "DOMAIN_INVARIANTS.md",
    "FIN": "DOMAIN_INVARIANTS.md",
    "OFF": "DOMAIN_INVARIANTS.md",
    "TRK": "DOMAIN_INVARIANTS.md",
    "DEL": "DOMAIN_INVARIANTS.md",
    "UCL": "DOMAIN_INVARIANTS.md",
    "NOT": "DOMAIN_INVARIANTS.md",
}

#: A definition line: a heading, table row, bullet, or numbered item that leads
#: with the identifier. Backticked, bolded, and bracketed forms all count.
DEFINITION_LINE = re.compile(
    r"^\s{0,3}(?:#{1,6}\s*|[-*+]\s+|\|\s*|\d+\.\s+)?"
    r"[`*_\[]{0,3}("
    + "|".join(REQUIREMENT_PREFIXES)
    + r")-(\d{3,4})[`*_\]]{0,3}\s*(?:\||[-—:]|\s*$)"
)


def register_definitions(root: "Path") -> dict[str, str]:
    """Return {requirement_id: 'file:line'} for definitions in their registers.

    Only the authoritative register for a series is scanned, so a cross-reference
    in another document is not mistaken for a second definition.
    """
    out: dict[str, str] = {}
    for path in existing_step01_docs(root):
        rel = path.relative_to(root).as_posix()
        for lineno, line in enumerate(read(path).splitlines(), start=1):
            m = DEFINITION_LINE.match(line)
            if not m:
                continue
            prefix = m.group(1)
            if REQUIREMENT_REGISTERS.get(prefix) != path.name:
                continue
            rid = f"{prefix}-{int(m.group(2)):03d}"
            out.setdefault(rid, f"{rel}:{lineno}")
    return out


THREAT_ID = re.compile(r"\bTHREAT-(\d{3,4})\b")
ABUSE_ID = re.compile(r"\bABUSE-(\d{3,4})\b")

SEVERITIES = ["CRITICAL", "HIGH", "MEDIUM", "LOW", "INFORMATIONAL"]

#: Matches an explicit severity declaration, e.g. "**Severity:** HIGH".
SEVERITY_FIELD = re.compile(
    r"\*{0,2}Severity\*{0,2}\s*:?\*{0,2}\s*:?\s*\*{0,2}\s*"
    r"(CRITICAL|HIGH|MEDIUM|LOW|INFORMATIONAL)\b",
    re.IGNORECASE,
)


def declared_severity(section: str) -> "str | None":
    """Return the severity a threat record explicitly DECLARES, or None.

    Reads the ``Severity:`` field only. Scanning a whole record for the word
    "HIGH" is wrong and was a real defect: a record may carry
    ``Likelihood: HIGH`` alongside ``Severity: INFORMATIONAL``, which inflated
    the CRITICAL/HIGH population and produced a spurious traceability failure.
    Reading the declared field also means a genuinely HIGH threat cannot hide by
    omitting the word elsewhere.
    """
    m = SEVERITY_FIELD.search(section)
    return m.group(1).upper() if m else None


# --- helpers -----------------------------------------------------------------


def step01_paths(root: Path) -> list[Path]:
    """Every Step 1 document path, whether or not it exists yet."""
    out: list[Path] = []
    for folder, names in STEP01_DOC_SET.items():
        for name in names:
            out.append(root / folder / name)
    return out


def existing_step01_docs(root: Path) -> list[Path]:
    """Only the Step 1 documents that actually exist."""
    return [p for p in step01_paths(root) if p.is_file()]


def read(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except (OSError, UnicodeDecodeError):
        return ""


def corpus(root: Path) -> str:
    """All existing Step 1 documents concatenated, for cross-document checks."""
    return "\n".join(read(p) for p in existing_step01_docs(root))


def requirement_ids(text: str) -> set[str]:
    """Every requirement identifier mentioned in a document, normalised."""
    return {f"{m.group(1)}-{int(m.group(2)):03d}" for m in REQUIREMENT_ID.finditer(text)}


def strip_code_blocks(text: str) -> str:
    """Remove fenced code blocks so prose checks are not fooled by examples."""
    return re.sub(r"```.*?```", "", text, flags=re.DOTALL)


def fenced_blocks_balanced(text: str) -> tuple[bool, int]:
    """Return (balanced, count). An odd number of ``` fences means one is unclosed."""
    fences = re.findall(r"^\s*```", text, flags=re.MULTILINE)
    return (len(fences) % 2 == 0, len(fences))


def mermaid_blocks(text: str) -> list[str]:
    """Extract the body of every ```mermaid fenced block."""
    return re.findall(r"```mermaid\s*\n(.*?)```", text, flags=re.DOTALL)
