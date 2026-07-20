#!/usr/bin/env bash
#
# OWNER-RUN — apply the DEC-0026 step-aware amendment to the
# destructive-operations guard.
#
# WHY THIS IS A SEPARATE OWNER SCRIPT
# -----------------------------------
# CLAUDE.md section 10 makes the guard owner territory: an agent must never edit,
# weaken, or route around it. The agent additionally cannot edit the file at all —
# the Claude Code auto-mode classifier refuses writes to
# .claude/hooks/guard-destructive-operations.sh. So the amendment is delivered as
# a script YOU review and run, keeping the security-critical edit under your hand.
#
# WHAT IT DOES
#   Replaces ONE block in the guard: the unconditional "Step 0 forbids creating a
#   Flutter/Dart runtime" rule becomes phase-aware.
#     - `dart create`    stays UNCONDITIONALLY BLOCKED (no approved scaffold needs it)
#     - `flutter create` is allowed ONLY for the three approved application roots,
#       ONLY with the approved platform, and ONLY when every canonical-state check
#       passes. Everything unverifiable blocks.
#   No other rule in the guard is touched.
#
# SAFETY
#   - refuses to run unless the guard is byte-identical to its committed state
#   - takes a backup before writing
#   - runs the guard's own --self-test after patching and RESTORES the backup if
#     the self-test regresses
#   - idempotent: re-running after a successful patch is a no-op
#
# USAGE
#   cd /home/fikri/Projects/aish_laundry
#   bash scripts/owner/apply-dec-0026-guard-amendment.sh          # apply
#   bash scripts/owner/apply-dec-0026-guard-amendment.sh --dry-run # show, do not write

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${REPO_ROOT}"

GUARD=".claude/hooks/guard-destructive-operations.sh"
DRY_RUN=0
[ "${1:-}" = "--dry-run" ] && DRY_RUN=1

ok()   { printf '  \033[32mOK\033[0m    %s\n' "$*"; }
warn() { printf '  \033[33mWARN\033[0m  %s\n' "$*"; }
die()  { printf '  \033[31mFAIL\033[0m  %s\n' "$*" >&2; exit 1; }

echo "== DEC-0026 guard amendment =="

[ -f "${GUARD}" ] || die "${GUARD} not found"

# --- idempotency -------------------------------------------------------------
if grep -q "_step3_flutter_scaffold_authorized" "${GUARD}"; then
  ok "amendment already present — nothing to do"
  bash "${GUARD}" --self-test >/dev/null 2>&1 \
    && ok "guard self-test passes" \
    || die "guard self-test FAILS — restore from git and re-run"
  exit 0
fi

# --- refuse to patch a locally-modified guard --------------------------------
if ! git diff --quiet -- "${GUARD}" 2>/dev/null; then
  die "${GUARD} has uncommitted local changes. Reconcile them first;
      this script refuses to patch a guard it cannot reason about."
fi
ok "guard is byte-identical to its committed state"

bash "${GUARD}" --self-test >/dev/null 2>&1 \
  || die "guard self-test FAILS before patching — fix that first"
ok "guard self-test passes before patching"

# --- the replacement ---------------------------------------------------------
OLD_BLOCK='    # -- Step 0 scope guard: runtime scaffolding is forbidden -----------------
    # Step 0 is governance only. These generators would create a Flutter or
    # Laravel runtime, which docs/STATUS.md declares ABSENT. Creating one would
    # make the canonical status false, so block at the source.
    if _m "$cmd" "${A}(flutter[[:space:]]+create|dart[[:space:]]+create)([[:space:]]|$)"; then
        echo "BLOCKED: Step 0 forbids creating a Flutter/Dart runtime (see .claude/rules/12)." >&2
        return $EXIT_BLOCK
    fi'

if ! grep -qF -- '# -- Step 0 scope guard: runtime scaffolding is forbidden' "${GUARD}"; then
  die "expected Step 0 scaffolding block not found — guard has diverged; aborting"
fi
ok "target block located"

if [ "${DRY_RUN}" -eq 1 ]; then
  echo
  echo "  --dry-run: the block above would be replaced by a phase-aware dispatch,"
  echo "  and the _step3_flutter_scaffold_authorized() function would be inserted"
  echo "  before the _m() helper. No file was written."
  exit 0
fi

BACKUP="$(mktemp)"
cp "${GUARD}" "${BACKUP}"
ok "backup taken"

python3 - "${GUARD}" <<'PYEOF'
import sys, pathlib

guard = pathlib.Path(sys.argv[1])
src = guard.read_text(encoding="utf-8")

OLD = '''    # -- Step 0 scope guard: runtime scaffolding is forbidden -----------------
    # Step 0 is governance only. These generators would create a Flutter or
    # Laravel runtime, which docs/STATUS.md declares ABSENT. Creating one would
    # make the canonical status false, so block at the source.
    if _m "$cmd" "${A}(flutter[[:space:]]+create|dart[[:space:]]+create)([[:space:]]|$)"; then
        echo "BLOCKED: Step 0 forbids creating a Flutter/Dart runtime (see .claude/rules/12)." >&2
        return $EXIT_BLOCK
    fi'''

NEW_DISPATCH = '''    # -- runtime scaffolding: PHASE-AWARE (DEC-0024, DEC-0026) ----------------
    #
    # Through Steps 0-2 this was an UNCONDITIONAL block: those steps created no
    # runtime, so any Flutter/Dart generator would have made docs/STATUS.md false.
    # That rule was correct for its period and is preserved historically: Step 0-2
    # runtime absence is still proven against their immutable GO tags.
    #
    # DEC-0024 authorised Step 3 runtime; DEC-0026 authorises the OFFICIAL Flutter
    # platform generator for the three approved application roots only. The block
    # is therefore phase-aware rather than removed, and authorisation is NOT
    # granted merely because a decision file exists — the canonical state is
    # re-verified independently on every invocation and every check fails closed.
    #
    # `dart create` stays UNCONDITIONALLY BLOCKED: no approved scaffold needs it.
    if _m "$cmd" "${A}dart[[:space:]]+create([[:space:]]|$)"; then
        echo "BLOCKED: 'dart create' is not authorised (DEC-0026 covers 'flutter create' only)." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}flutter[[:space:]]+create([[:space:]]|$)"; then
        if _step3_flutter_scaffold_authorized "$cmd"; then
            return $EXIT_ALLOW
        fi
        return $EXIT_BLOCK
    fi'''

FUNCTION = r'''# ---------------------------------------------------------------------------
# DEC-0026 — Step 3 Flutter platform scaffolding authorisation.
#
# Returns 0 ONLY when every canonical-state condition holds AND the command is
# one of the three exact approved shapes. Any unverifiable condition returns
# non-zero, so the caller blocks. This grants no general bypass: it is a narrow
# allow for one command family, one set of targets, one platform each.
#
# The decision is announced on stderr in SANITISED form — category, repository,
# target, platform, Master Source version, step status, result. Full command
# arguments are never echoed, because they can carry values the operator did not
# intend to surface.
# ---------------------------------------------------------------------------
readonly _DEC0026_CUSTOMER="apps/customer_android"
readonly _DEC0026_OPS="apps/ops_android"
readonly _DEC0026_WEB="apps/admin_web"

_dec0026_deny() {
    echo "BLOCKED: flutter create refused — $1" >&2
    echo "  DEC-0026 permits the official Flutter platform generator only for" >&2
    echo "  apps/customer_android (android), apps/ops_android (android)," >&2
    echo "  apps/admin_web (web), and only when canonical Step 3 state verifies." >&2
    echo "  Do not edit this guard; escalate to the repository owner." >&2
    return 1
}

_step3_flutter_scaffold_authorized() {
    local cmd="$1"
    local repo branch remote target platforms ms_version step_line tok
    local want_platform="" resolved rel dec f t peeled

    # --- canonical repository ---------------------------------------------
    repo="$(git rev-parse --show-toplevel 2>/dev/null || true)"
    [ -n "$repo" ] || { _dec0026_deny "not inside a git repository"; return 1; }
    repo="$(readlink -f "$repo" 2>/dev/null || printf '%s' "$repo")"
    [ "$repo" = "/home/fikri/Projects/aish_laundry" ] \
        || { _dec0026_deny "not the canonical repository path"; return 1; }

    # --- canonical remote --------------------------------------------------
    remote="$(git -C "$repo" remote get-url origin 2>/dev/null || true)"
    printf '%s' "$remote" | grep -q "makemesick91-code/aish_laundry_app" \
        || { _dec0026_deny "origin is not makemesick91-code/aish_laundry_app"; return 1; }

    # --- branch: a Step 3 branch, never main -------------------------------
    branch="$(git -C "$repo" rev-parse --abbrev-ref HEAD 2>/dev/null || true)"
    [ "$branch" != "main" ] || { _dec0026_deny "refusing to scaffold on main"; return 1; }
    printf '%s' "$branch" | grep -Eq "^(feature|feat)/step-03-" \
        || { _dec0026_deny "branch '$branch' is not a Step 3 feature branch"; return 1; }

    # --- Master Source version and checksum --------------------------------
    ms_version="$(grep -m1 -oE '\*\*Document version: [0-9]+\.[0-9]+\.[0-9]+\*\*' \
        "$repo/docs/MASTER_SOURCE.md" 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"
    [ "$ms_version" = "1.4.0" ] \
        || { _dec0026_deny "Master Source version is '${ms_version:-unreadable}', expected 1.4.0"; return 1; }
    ( cd "$repo/docs" && sha256sum -c MASTER_SOURCE.sha256 >/dev/null 2>&1 ) \
        || { _dec0026_deny "Master Source checksum does not validate"; return 1; }

    # --- authorising decisions exist AND are ACCEPTED ----------------------
    for dec in DEC-0024 DEC-0026; do
        f="$(ls "$repo"/docs/decisions/${dec}-*.md 2>/dev/null | head -1)"
        [ -n "$f" ] || { _dec0026_deny "$dec is absent"; return 1; }
        grep -qiE '^\*\*Status:\*\*[[:space:]]*ACCEPTED' "$f" \
            || { _dec0026_deny "$dec is not ACCEPTED"; return 1; }
    done

    # --- canonical step status ---------------------------------------------
    step_line="$(grep -m1 -E '^\|[[:space:]]*Step 3[[:space:]]*\|' \
        "$repo/docs/STATUS.md" 2>/dev/null || true)"
    printf '%s' "$step_line" | grep -q "IN PROGRESS" \
        || { _dec0026_deny "docs/STATUS.md does not report Step 3 as IN PROGRESS"; return 1; }
    if grep -qE '^\|[[:space:]]*Step ([4-9]|1[0-4])[[:space:]]*\|.*(IN PROGRESS|TESTED|\bGO\b)' \
        "$repo/docs/STATUS.md" 2>/dev/null; then
        _dec0026_deny "a Step 4+ entry is already advanced; scaffolding refused"; return 1
    fi

    # --- Step 0-2 immutable tags unmoved ------------------------------------
    for t in \
        "aish-laundry-step-00-master-source-governance-v1.0.0-go:8494bc8543b9301351da6055337832597f1f2d9f" \
        "aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go:4eadbc73f8bacdc9cd2acfcc62280ac932116089" \
        "aish-laundry-step-02-design-system-ux-foundation-v1.3.0-go:47c07d360e8802fd78f61d41435cae3f28313137"
    do
        peeled="$(git -C "$repo" rev-parse "${t%%:*}^{}" 2>/dev/null || true)"
        [ "$peeled" = "${t##*:}" ] \
            || { _dec0026_deny "immutable tag ${t%%:*} has moved or is missing"; return 1; }
    done

    # --- destructive / publishing options ----------------------------------
    if printf '%s' "$cmd" | grep -Eq -- "(^|[[:space:]])--(overwrite|force|delete)([[:space:]]|=|$)"; then
        _dec0026_deny "destructive or overwrite option present"; return 1
    fi
    if printf '%s' "$cmd" | grep -Eqi -- "(publish|deploy|--release-signing|keystore|upload)"; then
        _dec0026_deny "publishing, deployment, or signing option present"; return 1
    fi

    # --- exactly one approved target, no traversal, no symlink escape -------
    target=""
    set -f
    for tok in $cmd; do
        case "$tok" in
            -*|flutter|create) continue ;;
            *) target="$tok" ;;
        esac
    done
    set +f
    [ -n "$target" ] || { _dec0026_deny "no target path given"; return 1; }
    case "$target" in
        *..*) _dec0026_deny "target contains '..' path traversal"; return 1 ;;
        /*)   _dec0026_deny "target must be repository-relative"; return 1 ;;
    esac
    resolved="$(readlink -f "$repo/${target#./}" 2>/dev/null || true)"
    [ -n "$resolved" ] || resolved="$repo/${target#./}"
    case "$resolved" in
        "$repo"/*) : ;;
        *) _dec0026_deny "target resolves outside the repository"; return 1 ;;
    esac
    rel="${resolved#"$repo"/}"
    case "$rel" in
        "$_DEC0026_CUSTOMER") want_platform="android" ;;
        "$_DEC0026_OPS")      want_platform="android" ;;
        "$_DEC0026_WEB")      want_platform="web" ;;
        *) _dec0026_deny "target '$rel' is not one of the three approved application roots"; return 1 ;;
    esac

    # --- --platforms must be present and exactly the approved one ----------
    platforms="$(printf '%s' "$cmd" | sed -nE 's/.*--platforms[= ]+([A-Za-z0-9,_-]+).*/\1/p')"
    [ -n "$platforms" ] || { _dec0026_deny "--platforms is missing or ambiguous"; return 1; }
    [ "$platforms" = "$want_platform" ] \
        || { _dec0026_deny "platforms '$platforms' != approved '$want_platform' for $rel"; return 1; }

    # --- no example identifiers --------------------------------------------
    if printf '%s' "$cmd" | grep -Eqi -- "(com\.example|my_app|untitled|(^|[^a-z])example([^a-z]|$))"; then
        _dec0026_deny "example or placeholder identifier requested"; return 1
    fi

    # --- sanitised authorisation record ------------------------------------
    {
        echo "AUTHORIZED: STEP_3_FLUTTER_PLATFORM_SCAFFOLDING"
        echo "  category      : flutter-platform-scaffolding"
        echo "  repository    : $repo"
        echo "  branch        : $branch"
        echo "  target        : $rel"
        echo "  platform      : $platforms"
        echo "  master source : $ms_version (checksum valid)"
        echo "  decisions     : DEC-0024 ACCEPTED, DEC-0026 ACCEPTED"
        echo "  step status   : Step 3 IN PROGRESS, no Step 4+ advanced"
        echo "  result        : ALLOW (narrow, DEC-0026)"
    } >&2
    return 0
}

'''

if OLD not in src:
    sys.exit("target block not found — aborting without writing")

src = src.replace(OLD, NEW_DISPATCH, 1)

ANCHOR = "# case-sensitive extended-regex match\n_m() {"
if ANCHOR not in src:
    sys.exit("_m() helper anchor not found — aborting without writing")
src = src.replace(ANCHOR, FUNCTION + ANCHOR, 1)

guard.write_text(src, encoding="utf-8")
print("  patched")
PYEOF

ok "amendment written"

# --- verify, and roll back if the guard regressed ----------------------------
if bash "${GUARD}" --self-test >/dev/null 2>&1; then
  ok "guard self-test passes after patching"
else
  cp "${BACKUP}" "${GUARD}"
  die "guard self-test FAILED after patching — original restored, nothing changed"
fi

if bash "${GUARD}" "dart create foo" >/dev/null 2>&1; then
  cp "${BACKUP}" "${GUARD}"
  die "'dart create' is no longer blocked — original restored"
fi
ok "'dart create' remains blocked"

echo
echo "  DEC-0026 guard amendment applied."
echo "  Next: bash scripts/test-dec-0026-guard.sh    (27-case adversarial suite)"
