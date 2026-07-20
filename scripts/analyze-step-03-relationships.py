#!/usr/bin/env python3
"""Step 3 first-party relationship and orphan analysis.

WHAT THIS IS, AND WHAT IT IS NOT
--------------------------------
Graphify extracts a graph. Extraction is not analysis: a graph that builds
cleanly proves the corpus parses, not that a protected route carries
authentication. The Step 3 brief asks for assertions Graphify does not itself
establish, and the earlier summary said so honestly rather than claiming them.

This script closes that gap by deriving each required assertion STRUCTURALLY
from first-party source, and by mapping every assertion to the executable
validator that is authoritative for it. Where a PHPUnit suite is the real
proof, this script asserts the suite EXISTS and COVERS the subject; it never
re-implements the suite's judgement and never claims to have run it.

Scope: first-party only. `backend/vendor/`, `.dart_tool/`, build output and
dependency trees are excluded by construction — the corpus is the committed
tree (`git archive`), and none of those are tracked.

FAILS CLOSED: an unreadable file, an unmatched pattern, or a subject with no
mapped proof is a failure, never a silent pass.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

BACKEND = "backend"
ROUTES = "backend/routes/api.php"

# ---------------------------------------------------------------------------
# The PUBLIC allow-list. A route may be unauthenticated ONLY if it is named
# here. This is an allow-list rather than a pattern deliberately: a new public
# route must be added consciously, and adding one is visible in review. A
# pattern would silently absorb the next `auth/…` endpoint somebody adds.
#
# Each entry records WHY it is safe to expose:
#   health/readiness   — operational probes; carry no tenant or personal datum.
#   auth/login         — the unauthenticated half of authentication itself.
#   password-reset/*   — same, and both respond identically for known and
#                        unknown accounts, so neither is a user-enumeration
#                        channel (Rule 38, hard rule 7).
# ---------------------------------------------------------------------------
PUBLIC_ALLOWLIST = {
    "api.v1.health",
    "api.v1.readiness",
    "api.v1.auth.login",
    "api.v1.auth.password-reset.request",
    "api.v1.auth.password-reset.complete",
}

# Routes that are authenticated but deliberately have NO tenant context, because
# requiring an active tenant in order to CHOOSE one is circular. Each compensates
# by scoping every query to the caller's own records.
IDENTITY_SCOPED_ALLOWLIST = {
    "api.v1.auth.logout",
    "api.v1.auth.me",
    "api.v1.sessions.index",
    "api.v1.sessions.revoke-others",
    "api.v1.sessions.revoke",
    "api.v1.context.tenants",
    "api.v1.context.tenant",
}

# Tenant-owned models: every one must carry a tenant boundary. `tenant_id` is the
# usual form; Tenant itself IS the boundary; User is an identity that crosses
# tenants by design and is scoped through Membership, never owned by a tenant.
TENANT_OWNED_MODELS = {
    "Membership": "backend/app/Modules/Tenancy/Models/Membership.php",
    "DeviceSession": "backend/app/Modules/Tenancy/Models/DeviceSession.php",
    "LaundryBrand": "backend/app/Modules/Organization/Models/LaundryBrand.php",
    "Outlet": "backend/app/Modules/Organization/Models/Outlet.php",
    "AuditEntry": "backend/app/Modules/Audit/Models/AuditEntry.php",
}

IDENTITY_MODELS = {
    "User": "backend/app/Modules/Identity/Models/User.php",
    "AccessToken": "backend/app/Modules/Identity/Models/AccessToken.php",
    "Tenant": "backend/app/Modules/Tenancy/Models/Tenant.php",
    "Role": "backend/app/Modules/Authorization/Models/Role.php",
    "Permission": "backend/app/Modules/Authorization/Models/Permission.php",
}

POLICIES = {
    "AuditEntryPolicy": "backend/app/Modules/Authorization/Policies/AuditEntryPolicy.php",
    "DeviceSessionPolicy": "backend/app/Modules/Authorization/Policies/DeviceSessionPolicy.php",
    "LaundryBrandPolicy": "backend/app/Modules/Authorization/Policies/LaundryBrandPolicy.php",
    "MembershipPolicy": "backend/app/Modules/Authorization/Policies/MembershipPolicy.php",
    "OutletPolicy": "backend/app/Modules/Authorization/Policies/OutletPolicy.php",
}

# The executable suite that is AUTHORITATIVE for each subject. This script proves
# the mapping exists and is non-empty; the suite itself proves the behaviour.
PROOF_SUITES = {
    "tenant isolation": "backend/tests/Feature/Security/TenantIsolationMatrixTest.php",
    "structural isolation": "backend/tests/Feature/Security/StructuralIsolationMatrixTest.php",
    "rbac enforcement": "backend/tests/Feature/Security/RbacMatrixTest.php",
    "authentication": "backend/tests/Feature/Security/AuthenticationAdversarialMatrixTest.php",
    "session ownership": "backend/tests/Feature/SessionManagementTest.php",
    "redis partitioning": "backend/tests/Feature/Security/RedisTenantPartitioningTest.php",
    "log redaction": "backend/tests/Feature/Security/LogRedactionTest.php",
    "permission registry": "backend/tests/Feature/AuthorizationRegistryTest.php",
    "policy authorization": "backend/tests/Feature/Security/PolicyAuthorizationMatrixTest.php",
    "tenant context": "backend/tests/Feature/TenantContextTest.php",
    "cache key unit": "backend/tests/Unit/TenantCacheKeyTest.php",
}

ROUTE_DEF = re.compile(
    r"Route::(get|post|put|patch|delete)\(\s*'([^']+)'.*?->name\('([^']+)'\)",
    re.DOTALL,
)
GROUP_MW = re.compile(r"Route::middleware\(\s*(\[[^\]]*\]|'[^']*')\s*\)->group")


# ---------------------------------------------------------------------------
def parse_routes(text: str) -> list[tuple[str, str, str, frozenset[str]]]:
    """Return (method, uri, name, middleware) with GROUP middleware applied.

    Scans by CHARACTER OFFSET over the whole file, not line by line. A route
    definition is routinely wrapped across lines:

        Route::delete('sessions/{session}', [SessionController::class, 'revoke'])
            ->name('api.v1.sessions.revoke');

    A line-based scan silently misses those, and a route this analyzer cannot
    see is a route it cannot hold to the authentication contract — the failure
    mode is a false PASS, which is worse than a crash.

    Group middleware is resolved by brace depth at the route's offset, because a
    route's effective middleware is a property of the group enclosing it and no
    flat regex can see that nesting.
    """
    # Depth at every character offset, and the middleware opened at each depth.
    groups: list[tuple[int, int, int, frozenset[str]]] = []  # (start, end, depth, mw)
    depth = 0
    pending: frozenset[str] | None = None
    open_stack: list[tuple[int, int, frozenset[str] | None]] = []

    i = 0
    while i < len(text):
        m = GROUP_MW.match(text, i)
        if m:
            pending = frozenset(re.findall(r"'([^']+)'", m.group(1)))
        ch = text[i]
        if ch == "{":
            depth += 1
            open_stack.append((i, depth, pending))
            pending = None
        elif ch == "}":
            if open_stack:
                start, d, mw = open_stack.pop()
                if mw:
                    groups.append((start, i, d, mw))
            depth -= 1
        i += 1

    routes: list[tuple[str, str, str, frozenset[str]]] = []
    for rm in ROUTE_DEF.finditer(text):
        pos = rm.start()
        active: set[str] = set()
        for start, end, _d, mw in groups:
            if start < pos < end:
                active |= set(mw)
        routes.append((rm.group(1), rm.group(2), rm.group(3), frozenset(active)))

    return routes


def check_routes(root: Path, rep: Reporter) -> None:
    rep.info("--- routes: authentication and tenant enforcement ---")
    path = root / ROUTES
    if not path.is_file():
        rep.fail(f"[REL_ROUTES_MISSING] {ROUTES} not found")
        return
    text = read_text(path)
    routes = parse_routes(text)

    rep.check(len(routes) > 0, f"[REL_ROUTES_PARSED] api.php yields routes ({len(routes)} found)")

    for method, uri, name, mw in routes:
        authed = "auth.api" in mw
        tenant = "tenant.context" in mw

        if not authed:
            rep.check(
                name in PUBLIC_ALLOWLIST,
                f"[REL_ROUTE_UNAUTH_NOT_ALLOWLISTED] {method.upper()} {uri} ({name}) "
                "is unauthenticated and appears on the reviewed PUBLIC allow-list",
            )
            continue

        if not tenant:
            rep.check(
                name in IDENTITY_SCOPED_ALLOWLIST,
                f"[REL_ROUTE_NO_TENANT_NOT_ALLOWLISTED] {method.upper()} {uri} ({name}) "
                "is authenticated without tenant.context and is a reviewed "
                "identity-scoped endpoint",
            )
        else:
            rep.ok(
                f"[REL_ROUTE_TENANT_ENFORCED] {method.upper()} {uri} ({name}) "
                "carries auth.api + tenant.context"
            )

    # Orphan check in the other direction: an allow-list entry naming a route
    # that no longer exists is stale permission, and stale permission is how a
    # deleted endpoint's exemption silently covers a new one that reuses the name.
    defined = {r[2] for r in routes}
    for name in sorted(PUBLIC_ALLOWLIST | IDENTITY_SCOPED_ALLOWLIST):
        rep.check(
            name in defined,
            f"[REL_ALLOWLIST_ORPHAN] allow-list entry {name!r} still names a defined route",
        )


def check_models(root: Path, rep: Reporter) -> None:
    rep.info("--- models: tenant ownership controls ---")
    for name, rel in sorted(TENANT_OWNED_MODELS.items()):
        path = root / rel
        if not path.is_file():
            rep.fail(f"[REL_MODEL_MISSING] {rel} not found")
            continue
        text = read_text(path)
        rep.check(
            "tenant_id" in text,
            f"[REL_MODEL_NO_TENANT_COLUMN] {name} references tenant_id",
        )
    for name, rel in sorted(IDENTITY_MODELS.items()):
        path = root / rel
        rep.check(
            path.is_file(),
            f"[REL_MODEL_MISSING] identity/boundary model {name} present (not tenant-owned by design)",
        )


def check_policies(root: Path, rep: Reporter) -> None:
    rep.info("--- policies: every policy is covered by an executable suite ---")
    rbac = root / PROOF_SUITES["rbac enforcement"]
    struct = root / PROOF_SUITES["structural isolation"]
    iso = root / PROOF_SUITES["tenant isolation"]
    policy = root / PROOF_SUITES["policy authorization"]
    corpus = ""
    for p in (rbac, struct, iso, policy):
        if p.is_file():
            corpus += read_text(p)
    if not corpus:
        rep.fail("[REL_POLICY_NO_SUITE] no RBAC/isolation suite available to map policies onto")
        return
    for policy, rel in sorted(POLICIES.items()):
        if not (root / rel).is_file():
            rep.fail(f"[REL_POLICY_MISSING] {rel} not found")
            continue
        subject = policy.replace("Policy", "")
        rep.check(
            subject in corpus,
            f"[REL_POLICY_UNTESTED] {policy}: subject {subject!r} appears in the "
            "RBAC/isolation suites that are authoritative for it",
        )


def check_permissions(root: Path, rep: Reporter) -> None:
    rep.info("--- permissions: declared permissions are enforced, not merely listed ---")
    reg = root / "backend/app/Modules/Authorization/PermissionRegistry.php"
    if not reg.is_file():
        rep.fail("[REL_REGISTRY_MISSING] PermissionRegistry.php not found")
        return
    text = read_text(reg)
    perms = sorted(set(re.findall(r"'([a-z][a-z0-9_]*\.[a-z0-9_.]+)'", text)))
    rep.check(bool(perms), f"[REL_PERMISSIONS_FOUND] registry declares permissions ({len(perms)} found)")

    suite = root / PROOF_SUITES["permission registry"]
    rbac = root / PROOF_SUITES["rbac enforcement"]
    corpus = ""
    for p in (suite, rbac):
        if p.is_file():
            corpus += read_text(p)
    rep.check(
        bool(corpus),
        "[REL_PERMISSION_NO_SUITE] an executable suite exists for the permission registry",
    )
    # The registry is enforced as a whole through EffectivePermissions; assert the
    # enforcement path exists rather than pretending each string is individually
    # grep-provable, which would be a weaker claim dressed up as a stronger one.
    eff = root / "backend/app/Modules/Authorization/EffectivePermissions.php"
    rep.check(
        eff.is_file(),
        "[REL_PERMISSION_NO_ENFORCEMENT] EffectivePermissions enforcement path exists",
    )


def check_cache_and_sessions(root: Path, rep: Reporter) -> None:
    rep.info("--- cache partitioning and session ownership ---")
    key = root / "backend/app/Modules/SharedKernel/Cache/TenantCacheKey.php"
    rep.check(
        key.is_file(),
        "[REL_CACHE_NO_BUILDER] a single tenant-partitioned cache-key builder exists",
    )
    if key.is_file():
        text = read_text(key)
        rep.check(
            "tenant" in text.lower(),
            "[REL_CACHE_NOT_PARTITIONED] the cache-key builder carries a tenant dimension",
        )
    for label in ("redis partitioning", "cache key unit", "session ownership"):
        rel = PROOF_SUITES[label]
        rep.check(
            (root / rel).is_file(),
            f"[REL_PROOF_SUITE_MISSING] {label}: authoritative suite {rel} present",
        )


def check_proof_mapping(root: Path, rep: Reporter) -> None:
    rep.info("--- every subject maps to an authoritative executable suite ---")
    for label, rel in sorted(PROOF_SUITES.items()):
        rep.check(
            (root / rel).is_file(),
            f"[REL_PROOF_SUITE_MISSING] {label} -> {rel}",
        )


def main() -> int:
    root = repo_root()
    rep = Reporter("step-03-relationships")
    check_routes(root, rep)
    check_models(root, rep)
    check_policies(root, rep)
    check_permissions(root, rep)
    check_cache_and_sessions(root, rep)
    check_proof_mapping(root, rep)
    return rep.finish()


if __name__ == "__main__":
    run_main(main)
