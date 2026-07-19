#!/usr/bin/env python3
"""Validate the information architecture and role-adaptive navigation.

Four surfaces, fourteen roles, and one rule that matters more than the rest:
hiding a menu item is not authorization. This validator checks that the IA
documents say so, that the external courier is not handed tenant-wide
navigation, and that tenant and outlet context is never implicit.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import UX_DIR, read

IA_DIR = f"{UX_DIR}/information-architecture"

SURFACES = {
    "CUSTOMER_ANDROID_IA.md": "Customer Android",
    "OPS_ANDROID_IA.md": "Ops Android",
    "CONSOLE_WEB_IA.md": "Console Web",
    "TRACKING_PORTAL_IA.md": "Public Tracking Portal",
}

SUPPORTING = [
    "ROLE_NAVIGATION_MATRIX.md",
    "TENANT_OUTLET_CONTEXT_MODEL.md",
    "GLOBAL_SEARCH_MODEL.md",
]

REQUIRED_IA_TOPICS = [
    "top-level", "secondary", "persona", "role", "tenant", "outlet",
    "deep link", "back", "unsaved", "offline", "loading", "error",
    "recovery",
]

# Written with a space or a hyphen depending on whether it heads a section.
PERMISSION_DENIED = ("permission denied", "permission-denied")

ROLES = [
    "Platform Super Admin", "Platform Support", "Tenant Owner", "Tenant Admin",
    "Outlet Manager", "Cashier", "Production Operator", "Quality Control",
    "Courier Internal", "External Courier", "Finance", "Customer",
    "Corporate Customer Contact", "Authorized Recipient",
]

CONSOLE_MODES = ["Portfolio Mode", "Tenant Mode", "Outlet Mode"]


def main() -> int:
    root = repo_root()
    rep = Reporter("information architecture and navigation")

    corpus = ""

    # -- the four surface IA documents --------------------------------------
    for filename, label in sorted(SURFACES.items()):
        rel = f"{IA_DIR}/{filename}"
        text = read(root, rel)
        if not text:
            rep.fail(f"{rel} exists")
            continue
        rep.ok(f"{rel} exists ({len(text.splitlines())} lines)")
        corpus += "\n" + text
        low = text.lower()

        missing = [t for t in REQUIRED_IA_TOPICS if t not in low]
        if not any(v in low for v in PERMISSION_DENIED):
            missing.append("permission denied")
        for t in missing:
            rep.info(f"{label}: IA topic absent — {t}")
        rep.check(
            not missing,
            f"{label} IA documents every mandated topic "
            f"({len(missing)} missing)",
        )
        rep.check(
            "```mermaid" in text,
            f"{label} IA carries a Mermaid navigation diagram",
        )

    # -- the supporting models ----------------------------------------------
    for filename in SUPPORTING:
        rel = f"{IA_DIR}/{filename}"
        text = read(root, rel)
        rep.check(bool(text), f"{rel} exists")
        corpus += "\n" + text

    low_corpus = corpus.lower()

    # -- the role matrix covers every role ---------------------------------
    matrix = read(root, f"{IA_DIR}/ROLE_NAVIGATION_MATRIX.md")
    missing_roles = [r for r in ROLES if r.lower() not in matrix.lower()]
    for r in missing_roles:
        rep.info(f"role absent from the navigation matrix: {r}")
    rep.check(
        not missing_roles,
        f"all {len(ROLES)} roles appear in the navigation matrix "
        f"({len(missing_roles)} missing)",
    )

    # -- visibility is not authorization ------------------------------------
    rep.check(
        bool(re.search(r"(visibility|hidden|menu|hiding)[^.\n]{0,110}"
                       r"(not|never)[^.\n]{0,40}authori", low_corpus))
        or bool(re.search(r"(not|never)[^.\n]{0,70}authori[^.\n]{0,110}"
                          r"(visibility|hidden|menu|client)", low_corpus)),
        "the IA states that client-side visibility is not authorization",
    )
    rep.check(
        "step 3" in low_corpus,
        "the IA names Step 3 as where server-side authorization is delivered",
    )

    # -- the external courier is confined ----------------------------------
    ext = ""
    for line in corpus.splitlines():
        if "external courier" in line.lower():
            ext += line.lower() + " "
    rep.check(bool(ext), "the external courier appears in the navigation model")
    rep.check(
        bool(re.search(r"(not|never|no |without|minimum|only)", ext)),
        "the external courier is explicitly confined to minimum navigation",
    )

    # -- tenant and outlet context ------------------------------------------
    context_model = read(root, f"{IA_DIR}/TENANT_OUTLET_CONTEXT_MODEL.md")
    low_ctx = context_model.lower()
    rep.check(
        bool(re.search(r"\b(never|not)\b[^.\n]{0,60}silent", low_ctx)),
        "a tenant switch is explicitly never silent",
    )
    rep.check(
        "unsynced" in low_ctx or "unsync" in low_ctx or "queue" in low_ctx,
        "a tenant switch warns about unsynced critical operations",
    )
    rep.check(
        "cache" in low_ctx,
        "the model addresses stale tenant cache on switch",
    )
    for mode in CONSOLE_MODES:
        rep.check(mode.lower() in low_corpus,
                  f"Console Web defines '{mode}'")

    for state in ("tenant inaccessible", "membership revoked",
                  "outlet inactive", "subscription limited"):
        rep.check(state in low_ctx,
                  f"the context model defines the '{state}' state")

    # -- global search does not become a cross-tenant leak -----------------
    search = read(root, f"{IA_DIR}/GLOBAL_SEARCH_MODEL.md").lower()
    rep.check(
        "tenant" in search,
        "the global search model is tenant-scoped",
    )
    rep.check(
        bool(re.search(r"(never|not|no )[^.\n]{0,110}(cross-tenant|other "
                       r"tenant|another tenant)", search)),
        "global search explicitly cannot cross the tenant boundary",
    )

    # -- platform-appropriate navigation patterns --------------------------
    ops = read(root, f"{IA_DIR}/OPS_ANDROID_IA.md").lower()
    rep.check(
        "bottom navigation" in ops or "bottom nav" in ops,
        "Ops Android uses bottom navigation where appropriate",
    )
    console = read(root, f"{IA_DIR}/CONSOLE_WEB_IA.md").lower()
    rep.check(
        any(v in console for v in ("side navigation", "side nav", "sidebar",
                                   "navigation rail", "left navigation")),
        "Console Web uses a persistent side navigation or navigation rail",
    )
    rep.check(
        "keyboard" in console,
        "Console Web documents keyboard navigation",
    )
    portal = read(root, f"{IA_DIR}/TRACKING_PORTAL_IA.md").lower()
    rep.check(
        "minimal" in portal or "no navigation" in portal
        or "single" in portal,
        "the Public Tracking Portal keeps navigation minimal",
    )
    rep.check(
        "install" not in portal
        or bool(re.search(r"(no|never|without)[^.\n]{0,40}install", portal)),
        "the Public Tracking Portal never requires an app install",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
