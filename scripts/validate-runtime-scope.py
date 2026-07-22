#!/usr/bin/env python3
"""Step 3 runtime SCOPE guard — allowlist-based, fail-closed.

Supersedes the absence-only policy of scripts/validate-no-runtime.py for CURRENT
main and the Step 3 branch. It does NOT supersede it historically: Steps 0-2 were
genuinely runtime-free when tagged, and that remains provable by running the old
validator against those immutable tagged commits. See DEC-0024.

  Old policy (Steps 0-2): "no runtime may exist anywhere."
  New policy (Step 3+)  : "only APPROVED Step 3 runtime may exist, in APPROVED
                           locations, and Step 4+ business features remain
                           forbidden."

The new guard is deliberately STRICTER than the old one in every dimension except
the single one the owner authorised: the existence of Step 3 foundation runtime.

DESIGN NOTE — why this is not a keyword scanner.
A naive substring scan for words like "order" or "payment" produces both false
positives (`orderBy`, `ordering`, "in order to", `->orderByDesc()`) and false
assurance (a POS module named `kasir` sails straight through). This validator
therefore matches on STRUCTURED evidence: migration filenames, `Schema::create`
table arguments, route path segments, module/feature directory names, and Eloquent
model class names. Prose is never scanned for feature words.

Classification outcomes (printed verbatim, never a generic success message):
    STEP_3_RUNTIME_FOUNDATION_WITHIN_SCOPE
    FORBIDDEN_RUNTIME_SCOPE_DETECTED
    STEP_4_PLUS_FEATURE_LEAKAGE_DETECTED
    DEPLOYMENT_ARTIFACT_DETECTED
    VALIDATION_ERROR

Standard library only.
"""

from __future__ import annotations

import os
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import (  # noqa: E402
    CANONICAL_CURRENT_STEP,
    Reporter,
    repo_root,
    run_main,
)

# ---------------------------------------------------------------------------
# Approved runtime roots (DEC-0024). Anything runtime-shaped outside these fails.
# ---------------------------------------------------------------------------
APPROVED_RUNTIME_ROOTS = (
    "apps/customer_android/",
    "apps/ops_android/",
    "apps/admin_web/",
    "packages/design_system/",
    "packages/core/",
    "packages/domain/",
    "packages/auth/",
    "packages/networking/",
    "packages/local_storage/",
    "packages/offline_sync/",
    "packages/observability/",
    "packages/testing/",
    "backend/",
    "infrastructure/",
)

# Project-level workspace/toolchain manifests explicitly permitted by DEC-0024.
APPROVED_ROOT_MANIFESTS = {
    "pubspec.yaml",           # Dart workspace root
    "analysis_options.yaml",  # shared lints
    # `pubspec.lock` is the resolved-version artefact OF the already-approved
    # workspace `pubspec.yaml`; a Dart workspace cannot exist without producing
    # one. Committing it is what makes the dependency set reproducible on a fresh
    # clone, which Rule 37 requires — so deleting or ignoring it to satisfy this
    # validator would trade away the reproducibility the pin exists to provide.
    #
    # DEC-0024 §1 named only the two entries above, so this is a narrow completion
    # of that list rather than a new authorisation. Flagged to the owner rather
    # than made silently.
    "pubspec.lock",
}

# Governance tooling may use these languages in these directories only.
GOVERNANCE_TOOLING_DIRS = ("scripts/", ".claude/hooks/", ".github/")
GOVERNANCE_TOOLING_EXTENSIONS = {".py", ".sh"}

RUNTIME_MANIFEST_BASENAMES = {
    "pubspec.yaml", "pubspec.lock", "analysis_options.yaml",
    "composer.json", "composer.lock", "artisan",
    "package.json", "package-lock.json", "yarn.lock", "pnpm-lock.yaml",
    "build.gradle", "build.gradle.kts", "settings.gradle", "pom.xml",
    "go.mod", "go.sum", "cargo.toml", "cargo.lock",
    "gemfile", "requirements.txt", "pyproject.toml", "pipfile",
    "deno.json", "deno.jsonc",
}

APPLICATION_SOURCE_EXTENSIONS = {
    ".dart", ".php", ".kt", ".java", ".swift",
    ".ts", ".tsx", ".js", ".jsx", ".mjs", ".cjs",
    ".go", ".rs", ".rb", ".cs",
}

SKIP_DIRS = {".git", ".dart_tool", "node_modules", "vendor", "build", ".gradle", "graphify-out"}

# ---------------------------------------------------------------------------
# Business features beyond the current step. Matched STRUCTURALLY, never as prose
# substrings. Key = canonical feature label; value = identifier tokens that name it.
#
# SPLIT BY DELIVERING STEP (DEC-0030). This map was a single STEP4_FEATURE_TOKENS
# set meaning "Step 4 or later", which was correct while Step 3 was current and
# became self-contradictory the moment DEC-0028 authorised Step 4: the four labels
# Step 4 exists to build were the ones the guard refused to allow.
#
# Deleting the map to unblock those four was rejected. Twenty-eight of its labels
# are Step 5+ scope — orders, payments, QRIS, production, tracking, WhatsApp,
# pickup, delivery, settlement, the reminder ladder, receivables, finance,
# loyalty — and removing it would unblock all of them at exactly the moment the
# tree first has enough runtime for forward leak to be tempting.
# ---------------------------------------------------------------------------

#: Labels Step 4 delivers (FR-021 … FR-047). Permitted once the canonical current
#: step reaches 4, and forbidden before it. DEC-0030 authorises exactly these four
#: and no others.
STEP4_FEATURE_TOKENS = {
    "service catalog":      {"service_catalog", "servicecatalog", "katalog_layanan"},
    "price list":           {"price_list", "pricelist", "daftar_harga", "price_lists"},
    "customer management":  {"customers", "customer_profiles", "pelanggan"},
    # FR-045 registers printer CONFIGURATION as outlet master data. The nota itself
    # is FR-052 (Step 5), so "receipt" below stays forbidden. The distinction is
    # deliberate and is covered by an adversarial fixture.
    "printer":              {"printers", "printer_settings"},
}

#: Labels owned by Step 5 and later. Forbidden unconditionally at Step 4.
#: Editing this set to unblock work is a governance breach, not a fix (DEC-0030
#: supersession policy, Rule 36 hard rule 8).
STEP5_PLUS_FEATURE_TOKENS = {
    "POS":                  {"pos", "kasir", "point_of_sale"},
    "order":                {"orders", "order_lines", "order_items", "transaksi", "pesanan"},
    "laundry intake":       {"intakes", "laundry_intake", "penerimaan"},
    "payment":              {"payments", "pembayaran", "payment_transactions"},
    "refund":               {"refunds", "pengembalian_dana"},
    "QRIS":                 {"qris"},
    "receipt":              {"receipts", "nota", "struk"},
    "production":           {"production_jobs", "produksi"},
    "washing":              {"washing", "pencucian"},
    "drying":               {"drying", "pengeringan"},
    "finishing":            {"finishing", "penyelesaian"},
    "quality control":      {"quality_controls", "qc_inspections"},
    "rework":               {"reworks", "pengerjaan_ulang"},
    "tracking portal":      {"tracking_portal", "public_tracking"},
    "tracking token":       {"tracking_tokens"},
    "WhatsApp":             {"whatsapp", "wa_provider", "whatsapp_messages"},
    "notification provider":{"notification_providers", "notification_dispatch"},
    "pickup":               {"pickups", "pickup_requests", "penjemputan"},
    "delivery":             {"deliveries", "delivery_requests", "pengantaran"},
    "courier routing":      {"routes", "route_stops", "courier_routes"},
    "proof of delivery":    {"delivery_proofs", "proof_of_delivery"},
    "courier settlement":   {"courier_settlements", "cash_settlements"},
    "unclaimed laundry":    {"unclaimed_laundry", "cucian_menumpuk"},
    "reminder ladder":      {"reminders", "reminder_stages", "reminder_schedules"},
    "storage fee":          {"storage_fees", "biaya_penyimpanan"},
    "receivables":          {"receivables", "piutang"},
    "finance reports":      {"finance_reports", "financial_reports", "laporan_keuangan"},
    "loyalty":              {"loyalty", "loyalty_points", "poin_loyalitas"},
    "commercial membership":{"membership_programs", "loyalty_memberships"},
    "subscription billing": {"subscriptions", "subscription_invoices", "billing"},
}

# Tokens that are legitimate Step 3 vocabulary and must never be flagged even
# though they collide with a Step 4 word. Prevents false positives.
STEP3_ALLOWED_TOKENS = {
    "memberships",       # tenancy membership, NOT a loyalty programme
    "membership_role",
    "membership_roles",
    "sessions", "device_sessions", "personal_access_tokens",
    "users", "tenants", "laundry_brands", "outlets",
    "roles", "permissions", "role_permission", "role_permissions",
    "audit_entries", "password_reset_tokens", "failed_jobs", "jobs", "job_batches",
    "cache", "cache_locks",
}

# ---------------------------------------------------------------------------
# Deployment artifacts — forbidden outright in Step 3.
# ---------------------------------------------------------------------------
DEPLOYMENT_PATH_PATTERNS = [
    re.compile(r"(^|/)(k8s|kubernetes|helm|charts)/"),
    re.compile(r"(^|/)terraform/"),
    re.compile(r"(^|/)ansible/"),
    re.compile(r"\.tf$"),
    re.compile(r"(^|/)(Procfile|fly\.toml|render\.yaml|vercel\.json|netlify\.toml)$"),
    re.compile(r"(^|/)docker-compose\.(prod|production|staging)\.ya?ml$"),
    re.compile(r"(^|/)compose\.(prod|production|staging)\.ya?ml$"),
]

DEPLOY_WORKFLOW_TRIGGERS = re.compile(
    r"\b(azure/webapps-deploy|appleboy/ssh-action|aws-actions/amazon-ecs-deploy"
    r"|google-github-actions/deploy|superfly/flyctl-actions|kubectl\s+apply"
    r"|terraform\s+apply|helm\s+upgrade)\b",
    re.IGNORECASE,
)

# ---------------------------------------------------------------------------
# Secrets / PII — at least as strict as the previous guard.
# ---------------------------------------------------------------------------
SECRET_FILE_PATTERNS = [
    re.compile(r"(^|/)\.env$"),
    re.compile(r"(^|/)\.env\.(local|production|prod|staging)$"),
    re.compile(r"\.(pem|key|p12|pfx|jks|keystore)$"),
    re.compile(r"(^|/)id_(rsa|dsa|ecdsa|ed25519)$"),
    re.compile(r"\.(sql|dump|bak)$"),
]

SECRET_CONTENT_PATTERNS = [
    (re.compile(r"-----BEGIN [A-Z ]*PRIVATE KEY-----"), "private key block"),
    (re.compile(r"\bgh[pousr]_[A-Za-z0-9]{30,}"), "GitHub token"),
    (re.compile(r"\bAKIA[0-9A-Z]{16}\b"), "AWS access key id"),
    (re.compile(r"\bsk_live_[A-Za-z0-9]{20,}"), "live secret key"),
    (re.compile(r"\bxox[baprs]-[A-Za-z0-9-]{10,}"), "Slack token"),
]

# Indonesian mobile numbers in realistic form — PII risk on a PUBLIC repo.
PII_PHONE = re.compile(r"(?<![\w+])(?:\+62|62|08)\d{9,12}(?![\w])")


def is_recognisably_fictional_phone(value: str) -> bool:
    """True when the number is structurally a placeholder, not a real subscriber.

    Rule 23 requires every example datum to be fictional AND recognisably so. A
    number qualifies when its subscriber digits form an obvious fabricated pattern:
    a strictly ascending run (081234567890), a single repeated digit (081111111111),
    or an all-zero block (081200000000).

    This is deliberately a STRUCTURAL test, not an allowlist of blessed strings.
    Widening an allowlist until a finding disappears is how a real disclosure gets
    normalised; requiring the number to *look* fabricated keeps the guard honest
    about anything that looks like a genuine subscriber.
    """
    digits = re.sub(r"\D", "", value)
    # Drop the country/operator lead-in so we test the subscriber body.
    body = digits[4:] if digits.startswith("62") else digits[3:]
    if len(body) < 6:
        return False
    if len(set(body)) == 1:
        return True
    if body.count("0") >= len(body) - 1:
        return True
    # A run of four or more consecutive zeros. This is the same convention
    # scripts/validate-public-repository-safety.sh and scripts/validate-secrets.sh
    # already treat as an obvious placeholder, and aligning the three validators on
    # one notion of "recognisably fabricated" prevents a fixture that satisfies one
    # scanner from tripping another — which is how pressure to weaken a scanner
    # builds in the first place.
    if "0000" in body:
        return True
    ascending = all(int(b) - int(a) == 1 for a, b in zip(body, body[1:]))
    if ascending:
        return True
    # Ascending with wraparound, e.g. ...7890
    wrapping = all((int(b) - int(a)) % 10 == 1 for a, b in zip(body, body[1:]))
    return wrapping

TEXT_SCAN_EXTENSIONS = {
    ".md", ".yml", ".yaml", ".json", ".env", ".example", ".txt",
    ".dart", ".php", ".sh", ".py", ".xml", ".gradle", ".properties",
}


class Outcome:
    WITHIN_SCOPE = "STEP_3_RUNTIME_FOUNDATION_WITHIN_SCOPE"
    FORBIDDEN_SCOPE = "FORBIDDEN_RUNTIME_SCOPE_DETECTED"
    FEATURE_LEAK = "STEP_4_PLUS_FEATURE_LEAKAGE_DETECTED"
    DEPLOYMENT = "DEPLOYMENT_ARTIFACT_DETECTED"
    ERROR = "VALIDATION_ERROR"


def in_approved_runtime_root(rel: str) -> bool:
    return any(rel.startswith(root) for root in APPROVED_RUNTIME_ROOTS)


def is_governance_tooling(rel: str, ext: str) -> bool:
    return ext in GOVERNANCE_TOOLING_EXTENSIONS and rel.startswith(GOVERNANCE_TOOLING_DIRS)


def tokens_in(text: str) -> set[str]:
    """Lowercased identifier-ish tokens, snake_case preserved."""
    return {t.lower() for t in re.findall(r"[A-Za-z_][A-Za-z0-9_]*", text)}


#: How findings name the forbidden band. Derived, so the message can never claim
#: "Step 4+" after Step 4 has legitimately started.
FORBIDDEN_LABEL = f"Step {CANONICAL_CURRENT_STEP + 1}+"


def forbidden_feature_map() -> dict[str, set[str]]:
    """Feature labels that are forbidden AT THE CURRENT CANONICAL STEP.

    Step 5+ labels are always forbidden. The four Step 4 labels are forbidden only
    while the canonical current step is below 4, so this guard cannot retroactively
    permit anything in a Step 0-3 tree (DEC-0030, decision 4).
    """
    forbidden = dict(STEP5_PLUS_FEATURE_TOKENS)
    if CANONICAL_CURRENT_STEP < 4:
        forbidden.update(STEP4_FEATURE_TOKENS)
    return forbidden


def feature_hits(tokens: set[str]) -> list[str]:
    """Return forbidden feature labels whose tokens appear, minus allowed Step 3 words."""
    usable = tokens - STEP3_ALLOWED_TOKENS
    hits = []
    for label, toks in forbidden_feature_map().items():
        if toks & usable:
            hits.append(label)
    return hits


# ---------------------------------------------------------------------------
# Structured Step 4 detection
# ---------------------------------------------------------------------------
def scan_step4_leakage(root: Path, files: list[tuple[str, Path]], rep: Reporter) -> list[str]:
    findings: list[str] = []

    for rel, path in files:
        low = rel.lower()
        ext = path.suffix.lower()

        # (a) Laravel migration filenames: ..._create_<table>_table.php
        m = re.search(r"/migrations/\d[\d_]*_create_([a-z0-9_]+)_table\.php$", low)
        if m:
            for label in feature_hits({m.group(1)}):
                findings.append(f"{FORBIDDEN_LABEL} migration creates '{m.group(1)}' table ({label}): {rel}")

        # (b) Schema::create('<table>') inside any PHP file
        if ext == ".php":
            try:
                text = path.read_text(encoding="utf-8", errors="replace")
            except OSError as exc:
                rep.fail(f"unreadable file (failing closed): {rel} — {exc}")
                continue
            for tm in re.finditer(r"Schema::(?:create|table)\(\s*['\"]([a-z0-9_]+)['\"]", text):
                for label in feature_hits({tm.group(1)}):
                    findings.append(f"{FORBIDDEN_LABEL} table '{tm.group(1)}' ({label}) in {rel}")
            # (c) Route path segments
            for rm in re.finditer(r"Route::[a-zA-Z]+\(\s*['\"]([^'\"]+)['\"]", text):
                segs = {s.lower() for s in rm.group(1).split("/") if s and not s.startswith("{")}
                for label in feature_hits(segs):
                    findings.append(f"{FORBIDDEN_LABEL} route '/{rm.group(1)}' ({label}) in {rel}")
            # (d) Eloquent model class names
            for cm in re.finditer(r"class\s+([A-Za-z0-9_]+)\s+extends\s+Model\b", text):
                snake = re.sub(r"(?<!^)(?=[A-Z])", "_", cm.group(1)).lower()
                plural = snake + "s"
                for label in feature_hits({snake, plural}):
                    findings.append(f"{FORBIDDEN_LABEL} Eloquent model '{cm.group(1)}' ({label}) in {rel}")

        # (e) Module / feature directory names (backend modules, Flutter features)
        for seg_pat in (r"/modules/([a-z0-9_]+)/", r"/features/([a-z0-9_]+)/",
                        r"/domain/([a-z0-9_]+)/"):
            dm = re.search(seg_pat, low)
            if dm:
                for label in feature_hits({dm.group(1)}):
                    findings.append(f"{FORBIDDEN_LABEL} module directory '{dm.group(1)}' ({label}): {rel}")

    return sorted(set(findings))


def scan_deployment(root: Path, files: list[tuple[str, Path]], rep: Reporter) -> list[str]:
    findings: list[str] = []
    for rel, path in files:
        for pat in DEPLOYMENT_PATH_PATTERNS:
            if pat.search(rel):
                findings.append(f"deployment artifact: {rel}")
                break
        if rel.startswith(".github/workflows/") and path.suffix.lower() in (".yml", ".yaml"):
            try:
                text = path.read_text(encoding="utf-8", errors="replace")
            except OSError as exc:
                rep.fail(f"unreadable workflow (failing closed): {rel} — {exc}")
                continue
            m = DEPLOY_WORKFLOW_TRIGGERS.search(text)
            if m:
                findings.append(f"deployment action in workflow: {rel} ({m.group(0)})")
    return sorted(set(findings))


def scan_secrets_and_pii(root: Path, files: list[tuple[str, Path]], rep: Reporter) -> list[str]:
    findings: list[str] = []
    for rel, path in files:
        for pat in SECRET_FILE_PATTERNS:
            if pat.search(rel):
                findings.append(f"forbidden secret-bearing file: {rel}")
                break
        if path.suffix.lower() not in TEXT_SCAN_EXTENSIONS:
            continue
        # This validator's own pattern table is not a finding.
        if rel == "scripts/validate-runtime-scope.py":
            continue
        try:
            text = path.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            rep.fail(f"unreadable file (failing closed): {rel} — {exc}")
            continue
        for pat, label in SECRET_CONTENT_PATTERNS:
            if pat.search(text):
                findings.append(f"{label} detected in {rel}")
        for pm in PII_PHONE.finditer(text):
            val = pm.group(0)
            if not is_recognisably_fictional_phone(val):
                findings.append(
                    f"phone number that is not recognisably fictional in {rel}: {val[:4]}…"
                )
    return sorted(set(findings))


def scan_runtime_placement(root: Path, files: list[tuple[str, Path]], rep: Reporter) -> list[str]:
    findings: list[str] = []
    for rel, path in files:
        base = path.name.lower()
        ext = path.suffix.lower()

        if base in RUNTIME_MANIFEST_BASENAMES:
            if "/" not in rel:
                if base not in APPROVED_ROOT_MANIFESTS:
                    findings.append(f"runtime manifest at repository root not approved: {rel}")
            elif not in_approved_runtime_root(rel):
                findings.append(f"runtime manifest outside approved roots: {rel}")

        if ext in APPLICATION_SOURCE_EXTENSIONS:
            if not in_approved_runtime_root(rel):
                findings.append(f"application source outside approved roots: {rel}")

        if ext in GOVERNANCE_TOOLING_EXTENSIONS:
            if not is_governance_tooling(rel, ext) and not in_approved_runtime_root(rel):
                findings.append(f"tooling-language source outside permitted directories: {rel}")

    return sorted(set(findings))


def check_governance_transition(root: Path, rep: Reporter) -> list[str]:
    """Runtime may only exist if the governance transition is actually in place."""
    findings: list[str] = []

    ms = root / "docs/MASTER_SOURCE.md"
    if not ms.is_file():
        return ["docs/MASTER_SOURCE.md missing"]
    text = ms.read_text(encoding="utf-8")
    m = re.search(r"\*\*Document version:\s*([0-9]+\.[0-9]+\.[0-9]+)\*\*", text)
    if not m:
        findings.append("Master Source document version not parseable")
    else:
        version = m.group(1)
        major, minor, _ = (int(x) for x in version.split("."))
        if (major, minor) < (1, 4):
            findings.append(
                f"runtime present but Master Source is {version}; "
                "runtime introduction requires >= 1.4.0 (DEC-0024)"
            )

    decisions = root / "docs/decisions"
    if not decisions.is_dir():
        findings.append("docs/decisions/ missing")
    else:
        if not list(decisions.glob("DEC-0024-*.md")):
            findings.append("DEC-0024 (runtime introduction) is absent")

    # Status honesty: a step AFTER the current one must not be claimed as started.
    #
    # The boundary is derived from _common.CANONICAL_CURRENT_STEP, not hardcoded.
    # It was pinned to "Step 4+ during Step 3", which was correct while Step 3 was
    # current and became wrong in both directions the moment Step 4 was authorised:
    # it rejected the legitimate Step 4 status AND stopped guarding Step 5.
    next_step = CANONICAL_CURRENT_STEP + 1
    future_steps = "|".join(str(n) for n in range(next_step, 15))
    status = root / "docs/STATUS.md"
    if status.is_file():
        for line in status.read_text(encoding="utf-8").splitlines():
            if re.search(rf"\|\s*Step\s+({future_steps})\s*\|", line):
                if re.search(r"\b(IN PROGRESS|TESTED|GO|IMPLEMENTED)\b", line):
                    findings.append(
                        f"Step {next_step}+ status claim not permitted during "
                        f"Step {CANONICAL_CURRENT_STEP}: {line.strip()}"
                    )

    return findings


def git_ignored_paths(root: Path) -> set[str]:
    """Repo-relative paths that git actually ignores.

    A git-ignored file CANNOT be committed, so it cannot become a disclosure on a
    PUBLIC repository — which is the risk this guard exists to prevent. A developer's
    local `backend/.env` is required to run the application and is ignored by
    .gitignore; flagging it would force the developer to choose between a working
    environment and a green gate, and the usual resolution to that pressure is to
    weaken the pattern, which is far worse.

    This deliberately does NOT exempt untracked-but-unignored files: those can still
    be swept in by `git add -A`, so they remain in scope.

    Fails CLOSED: if git cannot be consulted, nothing is treated as ignored and the
    full tree is scanned.
    """
    import subprocess
    try:
        proc = subprocess.run(
            ["git", "-C", str(root), "ls-files", "--others", "--ignored",
             "--exclude-standard", "-z"],
            capture_output=True, timeout=60, check=True,
        )
    except (OSError, subprocess.SubprocessError):
        return set()
    return {p for p in proc.stdout.decode("utf-8", "replace").split("\0") if p}


def collect_files(root: Path, rep: Reporter) -> list[tuple[str, Path]]:
    out: list[tuple[str, Path]] = []
    ignored = git_ignored_paths(root)
    for dirpath, dirnames, filenames in os.walk(root, followlinks=False):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        here = Path(dirpath)
        for d in list(dirnames):
            p = here / d
            if p.is_symlink():
                dirnames.remove(d)
                target = os.path.realpath(p)
                if not target.startswith(str(root) + os.sep) and target != str(root):
                    rep.fail(f"symlink escapes repository: {p.relative_to(root)} -> {target}")
        for f in filenames:
            p = here / f
            if p.is_symlink():
                target = os.path.realpath(p)
                if not target.startswith(str(root) + os.sep) and target != str(root):
                    rep.fail(f"symlink escapes repository: {p.relative_to(root)} -> {target}")
                continue
            rel = p.relative_to(root).as_posix()
            if rel in ignored:
                continue
            out.append((rel, p))
    return out


def main() -> int:
    root = repo_root()
    rep = Reporter("runtime-scope")

    try:
        files = collect_files(root, rep)
    except OSError as exc:
        rep.fail(f"could not walk repository (failing closed): {exc}")
        print(f"\nCLASSIFICATION: {Outcome.ERROR}")
        return rep.finish()

    runtime_present = any(
        in_approved_runtime_root(rel)
        and (p.name.lower() in RUNTIME_MANIFEST_BASENAMES
             or p.suffix.lower() in APPLICATION_SOURCE_EXTENSIONS)
        for rel, p in files
    )

    placement = scan_runtime_placement(root, files, rep)
    leakage = scan_step4_leakage(root, files, rep)
    deployment = scan_deployment(root, files, rep)
    secrets = scan_secrets_and_pii(root, files, rep)
    governance = check_governance_transition(root, rep) if runtime_present else []

    for msg in placement:
        rep.fail(f"scope: {msg}")
    if not placement:
        rep.ok("every runtime manifest and source file sits inside an approved root")

    for msg in leakage:
        rep.fail(f"step-4-leakage: {msg}")
    if not leakage:
        rep.ok(f"no {FORBIDDEN_LABEL} business feature detected by table, route, model, or module")

    for msg in deployment:
        rep.fail(f"deployment: {msg}")
    if not deployment:
        rep.ok("no deployment artifact present")

    for msg in secrets:
        rep.fail(f"secret-or-pii: {msg}")
    if not secrets:
        rep.ok("no secret-bearing file, credential pattern, or real phone number detected")

    for msg in governance:
        rep.fail(f"governance: {msg}")
    if runtime_present and not governance:
        rep.ok("runtime introduction is backed by Master Source >= 1.4.0 and DEC-0024")
    elif not runtime_present:
        rep.ok("no runtime present yet — governance transition check not applicable")

    # ---- classification -------------------------------------------------
    if deployment:
        outcome = Outcome.DEPLOYMENT
    elif leakage:
        outcome = Outcome.FEATURE_LEAK
    elif placement or secrets or governance:
        outcome = Outcome.FORBIDDEN_SCOPE
    else:
        outcome = Outcome.WITHIN_SCOPE

    print()
    print("=" * 72)
    print(f"CLASSIFICATION: {outcome}")
    print("=" * 72)
    print(f"  approved runtime roots : {len(APPROVED_RUNTIME_ROOTS)}")
    print(f"  files examined         : {len(files)}")
    print(f"  runtime present        : {'yes' if runtime_present else 'no'}")
    print()
    print("  A runtime manifest is not evidence that a feature works.")
    print("  This validator classified scope. It executed no application test")
    print("  and claims no application test result.")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
