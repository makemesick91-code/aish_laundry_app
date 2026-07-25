#!/usr/bin/env bash
#
# ADVERSARIAL TEST OF THE STEP 6 VALIDATORS (the DEC-0037 guard transition).
#
# Rule 47 and Rule 33: a guard that permits new runtime must be tested against
# both the runtime it now ALLOWS and the runtime it still FORBIDS before it is
# relied upon as a gate. DEC-0037 moved six labels — production, washing, drying,
# finishing, quality control, rework — from forbidden to permitted, and raised
# _common.CANONICAL_CURRENT_STEP from 5 to 6. This harness proves the transition
# in BOTH directions:
#
#   ACCEPT — the six Step 6 labels are now authorised runtime.
#   REJECT — every later-step label remains forbidden, plainly and renamed. The
#            Step 7 tracking/WhatsApp assertions are STEP-AWARE: below step 7 they
#            are rejected, and from step 7 (DEC-0039) they are permitted — exactly as
#            test-step-05 made the Step 6 assertions step-aware. Pickup/delivery
#            (Step 8) and the reminder ladder / unclaimed aging (Step 9) stay rejected.
#
# Disciplines inherited from the Step 3/4/5 harness corrections (Rule 49):
#   1. FIXTURES ARE ASSEMBLED AT RUNTIME, never embedded as literals, so this
#      script's own text carries no forbidden identifier that could trip a guard.
#   2. SETUP FAILURE IS LOUD: a mutation that never applied has verified nothing.
#   3. THE WORKING TREE IS VERIFIED BYTE-IDENTICAL before and after.
#
# Exit 0 = every expectation met. Exit 1 = a guard mis-classified, or the tree
# was not restored.

set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO"

PASSED=0
FAILED=0
TOTAL=0

# Canonical state fingerprint INCLUDES tags: the isolated tag cases must never move,
# delete, or recreate a canonical tag, so tags are part of the before/after identity.
tree_fingerprint() {
  git status --porcelain=v1 | sort
  git diff --no-color | sha256sum
  git show-ref --tags | sort
}
BEFORE="$(tree_fingerprint)"

# Canonical Step 6 GO tag identity, recorded so the harness can prove it is untouched.
STEP6_TAG_NAME="aish-laundry-step-06-production-operations-v1.0.0-go"
STEP6_RUNTIME_SHA="82f162f25a39cc9501c6ee35a9728f0e01999725"
TAG_OBJ_BEFORE="$(git rev-parse "$STEP6_TAG_NAME" 2>/dev/null || echo absent)"
TAG_PEEL_BEFORE="$(git rev-parse "${STEP6_TAG_NAME}^{commit}" 2>/dev/null || echo absent)"

CREATED_FILES=()
BACKED_UP=()
CLONES=()

cleanup() {
  for f in "${CREATED_FILES[@]:-}"; do
    [ -n "$f" ] && rm -f "$f"
  done
  for pair in "${BACKED_UP[@]:-}"; do
    [ -z "$pair" ] && continue
    original="${pair%%::*}"
    backup="${pair##*::}"
    if [ -f "$backup" ]; then
      mv -f "$backup" "$original"
    fi
  done
  # Destroy every disposable clone — on success, on assertion failure, on interrupt.
  # Hard-guarded so this can never rm the canonical repository.
  for c in "${CLONES[@]:-}"; do
    [ -n "$c" ] && [ "$c" != "$REPO" ] && [ -d "$c/.git" ] && rm -rf "$c"
  done
}
trap cleanup EXIT

backup_file() {
  local original="$1"
  local backup
  backup="$(mktemp)"
  cp "$original" "$backup"
  BACKED_UP+=("${original}::${backup}")
}

expect_reject() {
  local description="$1"; local validator="$2"
  TOTAL=$((TOTAL + 1))
  if python3 "$validator" >/dev/null 2>&1; then
    echo "  FAIL  ${description}"
    echo "        ${validator} PASSED on deliberately broken input."
    FAILED=$((FAILED + 1))
  else
    echo "  ok    ${description}"
    PASSED=$((PASSED + 1))
  fi
}

expect_accept() {
  local description="$1"; local validator="$2"
  TOTAL=$((TOTAL + 1))
  if python3 "$validator" >/dev/null 2>&1; then
    echo "  ok    ${description}"
    PASSED=$((PASSED + 1))
  else
    echo "  FAIL  ${description}"
    echo "        ${validator} REJECTED legitimate Step 6 input."
    FAILED=$((FAILED + 1))
  fi
}

abort_setup() {
  echo "SETUP FAILED: $1" >&2
  echo "A mutation that never applied would score as 'caught'. Aborting." >&2
  exit 1
}

make_fixture() {
  local path="$1"; shift
  mkdir -p "$(dirname "$path")" || abort_setup "could not create $(dirname "$path")"
  printf '%s\n' "$@" > "$path" || abort_setup "could not write $path"
  CREATED_FILES+=("$path")
  [ -s "$path" ] || abort_setup "$path was written empty"
}

migration_fixture() {  # $1 = table token
  local table="$1"
  local path="backend/database/migrations/2026_07_24_999${RANDOM_SUFFIX}_create_${table}_table.php"
  RANDOM_SUFFIX=$((RANDOM_SUFFIX + 1))
  make_fixture "$path" \
    '<?php' \
    '// ADVERSARIAL FIXTURE — deliberately assembled. Removed by the harness.' \
    'use Illuminate\Database\Migrations\Migration;' \
    'use Illuminate\Database\Schema\Blueprint;' \
    'use Illuminate\Support\Facades\Schema;' \
    'return new class extends Migration {' \
    '  public function up(): void {' \
    "    Schema::create('${table}', function (Blueprint \$table) {" \
    "      \$table->uuid('id')->primary();" \
    '    });' \
    '  }' \
    '};'
  grep -q "$table" "$path" || abort_setup "fixture for '${table}' lacks its token"
  echo "$path"
}

SCOPE_GUARD="scripts/validate-runtime-scope.py"
LABEL_0035="scripts/validate-dec-0035-labels.py"
LABEL_0037="scripts/validate-dec-0037-labels.py"
RANDOM_SUFFIX=100

# Confirm the transition is actually live before asserting anything about it.
STEP="$(python3 -c 'import sys; sys.path.insert(0, "scripts"); import _common; print(_common.CANONICAL_CURRENT_STEP)')" \
  || abort_setup "could not read CANONICAL_CURRENT_STEP"
[ "$STEP" -ge 6 ] || abort_setup "CANONICAL_CURRENT_STEP is ${STEP}; this harness asserts the DEC-0037 transition and requires step >= 6"

echo "========================================================================"
echo "ADVERSARIAL TEST — STEP 6 VALIDATORS (DEC-0037 transition)"
echo "========================================================================"
echo

# ---------------------------------------------------------------------------
# CONTROL — the current tree is legitimate and must be accepted by all.
# ---------------------------------------------------------------------------
echo "CONTROL — legitimate input must be ACCEPTED"
expect_accept "runtime scope guard accepts the current tree" "$SCOPE_GUARD"
expect_accept "DEC-0035 label audit accepts the current tree" "$LABEL_0035"
expect_accept "DEC-0037 label audit accepts the current tree" "$LABEL_0037"
echo

# ---------------------------------------------------------------------------
# ACCEPT — the six Step 6 labels are now authorised (DEC-0037).
# ---------------------------------------------------------------------------
echo "AUTHORISED — a Step 6 table/route must now be ACCEPTED by the scope guard"

PRODUKSI="$(printf '%s%s' 'produk' 'si')"
F="$(migration_fixture "$PRODUKSI")"
expect_accept "scope guard accepts a Step 6 production table (DEC-0037)" "$SCOPE_GUARD"
expect_accept "DEC-0037 label audit accepts a Step 6 production table" "$LABEL_0037"
expect_accept "DEC-0035 label audit (step-aware) accepts a Step 6 production table" "$LABEL_0035"
rm -f "$F"

QC="$(printf '%s_%s' 'quality' 'controls')"
F="$(migration_fixture "$QC")"
expect_accept "scope guard accepts a Step 6 quality-control table (DEC-0037)" "$SCOPE_GUARD"
rm -f "$F"

REWORKS="$(printf '%s%s' 're' 'works')"
F="$(migration_fixture "$REWORKS")"
expect_accept "scope guard accepts a Step 6 rework table (DEC-0037)" "$SCOPE_GUARD"
rm -f "$F"

PRODUCTION_ROUTE="$(printf '%s' 'produksi')"
ROUTES="backend/routes/api.php"
[ -f "$ROUTES" ] || abort_setup "$ROUTES not found"
backup_file "$ROUTES"
printf "\n// ADVERSARIAL FIXTURE — removed by the harness.\nRoute::post('%s', [\\App\\Http\\Controllers\\HealthController::class, 'show']);\n" \
  "$PRODUCTION_ROUTE" >> "$ROUTES" || abort_setup "could not append the production route"
grep -q "$PRODUCTION_ROUTE" "$ROUTES" || abort_setup "the production route was not appended"
expect_accept "scope guard accepts a Step 6 production route (DEC-0037)" "$SCOPE_GUARD"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# REJECT — every Step 7+ label remains forbidden, plainly and renamed.
# ---------------------------------------------------------------------------
echo "FORWARD-LEAK GUARD — a Step 8+ feature must still be REJECTED"

# Step 7 — customer tracking portal / token. Permitted from step 7 (DEC-0039);
# forbidden before it. Step-aware exactly as test-step-05 handles Step 6 (DEC-0037).
TRACKING="$(printf '%s_%s' 'public' 'tracking')"
F="$(migration_fixture "$TRACKING")"
if [ "$STEP" -lt 7 ]; then
  expect_reject "scope guard rejects a Step 7 tracking-portal table (step < 7)" "$SCOPE_GUARD"
  expect_reject "DEC-0037 label audit rejects a Step 7 tracking-portal table (step < 7)" "$LABEL_0037"
else
  expect_accept "scope guard accepts a Step 7 tracking-portal table (DEC-0039, step >= 7)" "$SCOPE_GUARD"
  expect_accept "DEC-0037 label audit (step-aware) accepts a Step 7 tracking-portal table (step >= 7)" "$LABEL_0037"
fi
rm -f "$F"

# Step 7 — WhatsApp / notification provider.
WA="$(printf '%s' 'whatsapp')"
F="$(migration_fixture "${WA}_messages")"
if [ "$STEP" -lt 7 ]; then
  expect_reject "scope guard rejects a Step 7 WhatsApp table (step < 7)" "$SCOPE_GUARD"
  expect_reject "DEC-0037 label audit rejects a Step 7 WhatsApp table (step < 7)" "$LABEL_0037"
else
  expect_accept "scope guard accepts a Step 7 WhatsApp table (DEC-0039, step >= 7)" "$SCOPE_GUARD"
  expect_accept "DEC-0037 label audit (step-aware) accepts a Step 7 WhatsApp table (step >= 7)" "$LABEL_0037"
fi
rm -f "$F"

# Rename-evasion (Rule 36 hard rule 4): an Indonesian/compound of the token.
F="$(migration_fixture "laporan_${WA}")"
if [ "$STEP" -lt 7 ]; then
  expect_reject "DEC-0037 label audit rejects a compound-renamed WhatsApp table (step < 7)" "$LABEL_0037"
else
  expect_accept "DEC-0037 label audit accepts a compound WhatsApp table once permitted (step >= 7)" "$LABEL_0037"
fi
rm -f "$F"

# Step 8 — pickup / delivery.
PICKUP="$(printf '%s' 'penjemputan')"
F="$(migration_fixture "$PICKUP")"
expect_reject "scope guard rejects a Step 8 pickup table" "$SCOPE_GUARD"
expect_reject "DEC-0037 label audit rejects a Step 8 pickup table" "$LABEL_0037"
rm -f "$F"

DELIVERY="$(printf '%s' 'pengantaran')"
F="$(migration_fixture "$DELIVERY")"
expect_reject "scope guard rejects a Step 8 delivery table" "$SCOPE_GUARD"
rm -f "$F"

# Step 9 — reminder ladder / unclaimed aging.
REMINDERS="$(printf '%s' 'reminders')"
F="$(migration_fixture "$REMINDERS")"
expect_reject "scope guard rejects a Step 9 reminder-ladder table" "$SCOPE_GUARD"
expect_reject "DEC-0037 label audit rejects a Step 9 reminder-ladder table" "$LABEL_0037"
rm -f "$F"

UNCLAIMED="$(printf '%s_%s' 'unclaimed' 'laundry')"
F="$(migration_fixture "$UNCLAIMED")"
expect_reject "scope guard rejects a Step 9 unclaimed-laundry table" "$SCOPE_GUARD"
rm -f "$F"
echo

# ---------------------------------------------------------------------------
# DEC-0038 — private object-storage governance validator (Rule 33, Rule 47).
# A gate is only trustworthy once it is shown to REJECT deliberately broken
# input, not merely to ACCEPT the honest tree. Each mutation is applied at
# runtime, its application is verified, and the file is restored immediately.
# ---------------------------------------------------------------------------
echo "DEC-0038 — object-storage governance validator (both directions)"
DEC0038="scripts/validate-dec-0038-object-storage.py"

expect_accept "DEC-0038 validator accepts the honest governance tree" "$DEC0038"

# Break 1 — sever the FR-083 evidence -> DEC-0038 cross-reference.
EVID="evidence/step-06/README.md"
[ -f "$EVID" ] || abort_setup "$EVID not found"
backup_file "$EVID"
python3 - "$EVID" <<'PY' || abort_setup "could not mutate $EVID"
import re, sys
p = sys.argv[1]
s = open(p, encoding="utf-8").read()
new = re.sub(r"DEC-0038", "DEC-XXXX", s)
if new == s:
    sys.exit(1)
open(p, "w", encoding="utf-8").write(new)
PY
grep -q "DEC-0038" "$EVID" && abort_setup "the DEC-0038 evidence reference was not removed"
expect_reject "DEC-0038 validator rejects a severed FR-083 evidence link" "$DEC0038"
cleanup
BACKED_UP=()

# Break 2 — loosen the locked contract by dropping the digest-pin (a single,
# never-wrapped token, so the mutation and the validator agree on what changed).
DECREC="docs/decisions/DEC-0038-step-06-private-object-storage-introduction.md"
[ -f "$DECREC" ] || abort_setup "$DECREC not found"
backup_file "$DECREC"
python3 - "$DECREC" <<'PY' || abort_setup "could not mutate $DECREC"
import re, sys
p = sys.argv[1]
s = open(p, encoding="utf-8").read()
new = re.sub(r"(?i)digest-pinned", "floating-tag", s)
if new == s:
    sys.exit(1)
open(p, "w", encoding="utf-8").write(new)
PY
grep -qi "digest-pinned" "$DECREC" && abort_setup "the digest-pin lock was not removed"
expect_reject "DEC-0038 validator rejects a loosened object-storage contract" "$DEC0038"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# STEP 6 GO-CLOSURE facts — validate-status.py check_step6_closure (Rule 33/47).
# The closure block records the runtime merge SHA and the intended GO-tag peel
# target, and FR-071..085 must all be TESTED. Each is a gate, so each must be shown
# to REJECT deliberately broken input, not merely to ACCEPT the honest tree. Every
# mutation is applied at runtime, its application verified, then restored.
# ---------------------------------------------------------------------------
echo "STEP 6 GO-closure — status/closure validator (both directions)"
STATUSVAL="scripts/validate-status.py"
STATUSDOC="docs/STATUS.md"
MATRIX="docs/quality/STEP_06_REQUIREMENT_MATRIX.md"

expect_accept "validate-status accepts the honest Step 6 closure block" "$STATUSVAL"

# Break A — corrupt the recorded runtime merge SHA in the closure block.
backup_file "$STATUSDOC"
python3 - "$STATUSDOC" <<'PY' || abort_setup "could not mutate the runtime merge SHA"
import sys
p = sys.argv[1]; s = open(p, encoding="utf-8").read()
new = s.replace(
    "STEP_06_RUNTIME_MERGE_SHA=82f162f25a39cc9501c6ee35a9728f0e01999725",
    "STEP_06_RUNTIME_MERGE_SHA=deadbeefdeadbeefdeadbeefdeadbeefdeadbeef",
)
if new == s: sys.exit(1)
open(p, "w", encoding="utf-8").write(new)
PY
grep -q "STEP_06_RUNTIME_MERGE_SHA=82f162f" "$STATUSDOC" && abort_setup "runtime merge SHA not mutated"
expect_reject "validate-status rejects a corrupted Step 6 runtime merge SHA" "$STATUSVAL"
cleanup
BACKED_UP=()

# Break B — point the intended GO-tag peel at a DIFFERENT commit (peel != runtime).
backup_file "$STATUSDOC"
python3 - "$STATUSDOC" <<'PY' || abort_setup "could not mutate the peel target"
import sys
p = sys.argv[1]; s = open(p, encoding="utf-8").read()
new = s.replace(
    "STEP_06_GO_TAG_PEELED_EXPECTED=82f162f25a39cc9501c6ee35a9728f0e01999725",
    "STEP_06_GO_TAG_PEELED_EXPECTED=0e2554338812b05eba8411afeb099212b05f9761",
)
if new == s: sys.exit(1)
open(p, "w", encoding="utf-8").write(new)
PY
grep -q "STEP_06_GO_TAG_PEELED_EXPECTED=82f162f" "$STATUSDOC" && abort_setup "peel target not mutated"
expect_reject "validate-status rejects a Step 6 tag peel that is not the runtime merge" "$STATUSVAL"
cleanup
BACKED_UP=()

# Break C — understate the closure classification.
backup_file "$STATUSDOC"
python3 - "$STATUSDOC" <<'PY' || abort_setup "could not mutate the classification"
import sys
p = sys.argv[1]; s = open(p, encoding="utf-8").read()
new = s.replace(
    "STEP_06_CLOSURE_CLASSIFICATION=GO",
    "STEP_06_CLOSURE_CLASSIFICATION=IN_PROGRESS",
)
if new == s: sys.exit(1)
open(p, "w", encoding="utf-8").write(new)
PY
grep -q "STEP_06_CLOSURE_CLASSIFICATION=GO$" "$STATUSDOC" && abort_setup "classification not mutated"
expect_reject "validate-status rejects a downgraded Step 6 closure classification" "$STATUSVAL"
cleanup
BACKED_UP=()

# Break D — flip an FR-071..085 disposition away from TESTED.
[ -f "$MATRIX" ] || abort_setup "$MATRIX not found"
backup_file "$MATRIX"
python3 - "$MATRIX" <<'PY' || abort_setup "could not mutate an FR disposition"
import re, sys
p = sys.argv[1]; lines = open(p, encoding="utf-8").read().splitlines(keepends=True)
done = False; out = []
for ln in lines:
    if not done and re.match(r"^\|\s*FR-071\b", ln) and "TESTED" in ln:
        ln = ln.replace("TESTED", "PENDING"); done = True
    out.append(ln)
if not done: sys.exit(1)
open(p, "w", encoding="utf-8").write("".join(out))
PY
grep -qE "^\|\s*FR-071\b.*PENDING" "$MATRIX" || abort_setup "FR-071 disposition not mutated"
expect_reject "validate-status rejects an FR-071..085 row that is not TESTED" "$STATUSVAL"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# STEP 6 GO-TAG LIFECYCLE (pre-tag safe) — pure verdict functions (Rule 33/47).
# Deterministic: exercises every authorised and forbidden tag state WITHOUT
# creating, moving, or deleting any real git tag (the destructive-operations guard
# forbids tag deletion, and correctness must not depend on it). Each scenario the
# owner required is asserted against _common.step6_tag_verdict / historical_tag_verdict.
# ---------------------------------------------------------------------------
echo "STEP 6 GO-tag lifecycle — pure verdicts across every state (deterministic)"
TOTAL=$((TOTAL + 1))
if python3 - <<'PY'
import sys
sys.path.insert(0, "scripts")
import _common as c

CANON = c.STEP6_GO_TAG_NAME
SHA = c.STEP6_RUNTIME_MERGE_SHA
OTHER = "0e2554338812b05eba8411afeb099212b05f9761"  # a real but WRONG commit

def all_ok(results):
    return bool(results) and all(r[0] for r in results)
def any_fail(results):
    return any(not r[0] for r in results)

scenarios = [
    # (label, predicate)
    ("historical present + Step 6 tag absent + authorised pre-tag -> PASS",
     all_ok(c.step6_tag_verdict([], True))),
    ("no tags present + authorised pre-tag -> PASS",
     all_ok(c.step6_tag_verdict([], True))),
    ("Step 6 tag absent + NOT authorised pre-tag -> FAIL",
     any_fail(c.step6_tag_verdict([], False))),
    ("correct annotated Step 6 tag peeling to runtime merge -> PASS",
     all_ok(c.step6_tag_verdict([{"name": CANON, "annotated": True, "peeled": SHA}], False))),
    ("lightweight Step 6 tag -> FAIL",
     any_fail(c.step6_tag_verdict([{"name": CANON, "annotated": False, "peeled": SHA}], False))),
    ("annotated Step 6 tag with wrong peel target -> FAIL",
     any_fail(c.step6_tag_verdict([{"name": CANON, "annotated": True, "peeled": OTHER}], False))),
    ("wrong Step 6 tag name -> FAIL",
     any_fail(c.step6_tag_verdict([{"name": "aish-laundry-step-06-typo-go", "annotated": True, "peeled": SHA}], False))),
    ("duplicate Step 6 tags -> FAIL",
     any_fail(c.step6_tag_verdict([
         {"name": CANON, "annotated": True, "peeled": SHA},
         {"name": CANON + "-dup", "annotated": True, "peeled": SHA}], False))),
    ("historical tags intact -> PASS",
     all_ok(c.historical_tag_verdict(dict(c.HISTORICAL_GO_TAGS)))),
]
# historical tag corruption -> FAIL
corrupt = dict(c.HISTORICAL_GO_TAGS)
corrupt[next(iter(corrupt))] = "0" * 40
scenarios.append(("historical tag corruption/move -> FAIL", any_fail(c.historical_tag_verdict(corrupt))))

failed = [s for s, ok in scenarios if not ok]
for s, ok in scenarios:
    print(("    ok   " if ok else "    FAIL ") + s)
sys.exit(1 if failed else 0)
PY
then
  echo "  ok    Step 6 tag-lifecycle verdicts behave correctly in every state"
  PASSED=$((PASSED + 1))
else
  echo "  FAIL  Step 6 tag-lifecycle verdicts misbehaved"
  FAILED=$((FAILED + 1))
fi

# ---------------------------------------------------------------------------
# ISOLATED INTEGRATION CASES — REAL validator executions in DISPOSABLE clones.
#
# Every case that changes or depends on git TAG state runs inside a throwaway clone,
# NEVER in the canonical working repository. The earlier design mutated the pre-tag
# MARKER in the real repo but ran the validators there too, where — post-tag — the
# real, valid Step 6 tag is visible; so "tag absent" was not actually tag-absent and
# the case became a false expectation once the owner created the tag. Isolation fixes
# that: the tag-absent state is produced by deleting the Step 6 tag ONLY in a clone.
#
# The canonical Step 6 tag is never deleted, moved, or recreated here. `git tag -d`
# is executed only with `git -C <clone>` against a verified disposable clone. Cleanup
# runs on success, on assertion failure, and on interruption (EXIT trap + per-case rm).
# ---------------------------------------------------------------------------
echo "ISOLATED INTEGRATION — real validators in disposable clones (git tag state)"

VS="scripts/validate-status.py"
VR="scripts/validate-roadmap.py"
MARKER="STEP_06_GO_TAG_STATE=NOT_YET_CREATED_OWNER_TO_CREATE_AFTER_CLOSURE_MERGE"

CLONE_TEMPLATE=""
ensure_template() {  # one cross-device copy of the repo into /tmp (with tags)
  [ -n "$CLONE_TEMPLATE" ] && return 0
  local t
  t="$(mktemp -d)" || return 1
  # --no-hardlinks: /tmp and the repo are usually different filesystems, and a
  # hardlink clone fails cross-device. This copy happens ONCE.
  if ! git clone --quiet --no-hardlinks "$REPO" "$t" >/dev/null 2>&1; then
    rm -rf "$t"; return 1
  fi
  CLONE_TEMPLATE="$t"
  CLONES+=("$t")
  return 0
}
new_clone() {  # echoes a fresh disposable clone path (empty on failure)
  ensure_template || { echo ""; return 1; }
  local c
  c="$(mktemp -d)" || { echo ""; return 1; }
  # Hardlink clone FROM the /tmp template: both live on the same filesystem, so
  # this is fast and cross-device-safe.
  if ! git clone --quiet --local "$CLONE_TEMPLATE" "$c" >/dev/null 2>&1; then
    rm -rf "$c"; echo ""; return 1
  fi
  CLONES+=("$c")
  echo "$c"
}
drop_clone() {  # destroy one clone immediately; hard-guarded against the canonical repo
  local c="$1"
  [ -n "$c" ] && [ "$c" != "$REPO" ] && [ -d "$c/.git" ] && rm -rf "$c"
}
clone_git() {  # run a git command in a clone ONLY (never the canonical repo)
  local c="$1"; shift
  [ -n "$c" ] && [ "$c" != "$REPO" ] && [ -d "$c/.git" ] || return 1
  git -C "$c" "$@"
}
clone_del_tag()   { clone_git "$1" tag -d "$2" >/dev/null 2>&1 || true; }
clone_annot_tag() { clone_git "$1" tag -a "$2" -m "harness fixture" "$3" >/dev/null 2>&1; }
clone_light_tag() { clone_git "$1" tag "$2" "$3" >/dev/null 2>&1; }
clone_drop_marker() {
  python3 - "$1/docs/STATUS.md" <<'PY'
import sys
p = sys.argv[1]; s = open(p, encoding="utf-8").read()
open(p, "w", encoding="utf-8").write(
    s.replace("STEP_06_GO_TAG_STATE=NOT_YET_CREATED_OWNER_TO_CREATE_AFTER_CLOSURE_MERGE",
              "STEP_06_GO_TAG_STATE=CREATED"))
PY
}
assert_clone() {  # $1 label  $2 clone  $3 validator-relpath  $4 pass|fail
  TOTAL=$((TOTAL + 1))
  python3 "$2/$3" >/dev/null 2>&1
  local rc=$?
  if { [ "$4" = pass ] && [ "$rc" -eq 0 ]; } || { [ "$4" = fail ] && [ "$rc" -ne 0 ]; }; then
    echo "  ok    $1"
    PASSED=$((PASSED + 1))
  else
    echo "  FAIL  $1 (validator rc=${rc}, expected ${4})"
    FAILED=$((FAILED + 1))
  fi
}

# Setup faithfulness: a clone must carry the canonical annotated Step 6 tag and the
# historical tags, or every case below would be testing an unrepresentative fixture.
C="$(new_clone)"; [ -n "$C" ] || abort_setup "could not create the disposable clone"
[ "$(git -C "$C" cat-file -t "$STEP6_TAG_NAME" 2>/dev/null)" = "tag" ] \
  || abort_setup "clone lacks the annotated Step 6 tag (run in the canonical tagged repo)"
[ "$(git -C "$C" rev-parse "${STEP6_TAG_NAME}^{commit}" 2>/dev/null)" = "$STEP6_RUNTIME_SHA" ] \
  || abort_setup "clone Step 6 tag peel target is not the runtime merge"
git -C "$C" tag -l 'aish-laundry-step-03-*-go' | grep -q . || abort_setup "clone lacks historical Step 3 tag"
git -C "$C" tag -l 'aish-laundry-step-05-*-go' | grep -q . || abort_setup "clone lacks historical Step 5 tag"
grep -q "$MARKER" "$C/docs/STATUS.md" || abort_setup "clone lacks the authorised pre-tag marker"
drop_clone "$C"

# Case 1 — historical 0-5 present, Step 6 tag ABSENT, authorised marker present -> PASS.
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 1)"
clone_del_tag "$C" "$STEP6_TAG_NAME"
git -C "$C" tag -l | grep -qx "$STEP6_TAG_NAME" && abort_setup "case 1: Step 6 tag not removed in clone"
git -C "$C" tag -l 'aish-laundry-step-04-*-go' | grep -q . || abort_setup "case 1: historical tags lost"
assert_clone "isolated#1 step6 absent + marker present -> validate-status PASS" "$C" "$VS" pass
assert_clone "isolated#1 step6 absent + marker present -> validate-roadmap PASS" "$C" "$VR" pass
drop_clone "$C"

# Case 2 — Step 6 tag absent AND authorised marker ABSENT -> FAIL closed (both).
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 2)"
clone_del_tag "$C" "$STEP6_TAG_NAME"
clone_drop_marker "$C"
grep -q "$MARKER" "$C/docs/STATUS.md" && abort_setup "case 2: marker not removed in clone"
assert_clone "isolated#2 step6 absent + marker absent -> validate-status FAIL" "$C" "$VS" fail
assert_clone "isolated#2 step6 absent + marker absent -> validate-roadmap FAIL" "$C" "$VR" fail
drop_clone "$C"

# Case 3 — correct annotated Step 6 tag (canonical) -> PASS.
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 3)"
assert_clone "isolated#3 correct annotated step6 tag -> validate-status PASS" "$C" "$VS" pass
assert_clone "isolated#3 correct annotated step6 tag -> validate-roadmap PASS" "$C" "$VR" pass
drop_clone "$C"

# Case 4 — lightweight Step 6 tag -> validate-status FAIL closed.
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 4)"
clone_del_tag "$C" "$STEP6_TAG_NAME"
clone_light_tag "$C" "$STEP6_TAG_NAME" "$STEP6_RUNTIME_SHA" || abort_setup "case 4: could not create lightweight tag"
[ "$(git -C "$C" cat-file -t "$STEP6_TAG_NAME")" = "commit" ] || abort_setup "case 4: tag is not lightweight"
assert_clone "isolated#4 lightweight step6 tag -> validate-status FAIL" "$C" "$VS" fail
drop_clone "$C"

# Case 5 — annotated Step 6 tag at the WRONG commit -> validate-status FAIL closed.
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 5)"
clone_del_tag "$C" "$STEP6_TAG_NAME"
WRONG="$(git -C "$C" rev-parse HEAD)"
[ "$WRONG" != "$STEP6_RUNTIME_SHA" ] || abort_setup "case 5: HEAD unexpectedly equals the runtime merge"
clone_annot_tag "$C" "$STEP6_TAG_NAME" "$WRONG" || abort_setup "case 5: could not create annotated tag"
assert_clone "isolated#5 annotated step6 tag wrong commit -> validate-status FAIL" "$C" "$VS" fail
drop_clone "$C"

# Case 6 — misnamed Step 6 GO tag -> validate-status FAIL closed.
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 6)"
clone_del_tag "$C" "$STEP6_TAG_NAME"
clone_annot_tag "$C" "aish-laundry-step-06-typo-go" "$STEP6_RUNTIME_SHA" || abort_setup "case 6: could not create misnamed tag"
assert_clone "isolated#6 misnamed step6 GO tag -> validate-status FAIL" "$C" "$VS" fail
drop_clone "$C"

# Case 7 — conflicting/duplicate Step 6 GO tag (canonical kept, a second added) -> FAIL closed.
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 7)"
clone_annot_tag "$C" "aish-laundry-step-06-production-operations-v2.0.0-go" "$STEP6_RUNTIME_SHA" || abort_setup "case 7: could not create duplicate tag"
assert_clone "isolated#7 duplicate step6 GO tag -> validate-status FAIL" "$C" "$VS" fail
drop_clone "$C"

# Case 8 — corrupted/moved historical GO tag -> validate-status FAIL closed.
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 8)"
HIST="aish-laundry-step-05-pos-order-payment-foundation-v1.0.0-go"
clone_del_tag "$C" "$HIST"
clone_annot_tag "$C" "$HIST" "$(git -C "$C" rev-parse HEAD)" || abort_setup "case 8: could not retarget historical tag"
assert_clone "isolated#8 corrupted historical GO tag -> validate-status FAIL" "$C" "$VS" fail
drop_clone "$C"

# Case 9 — no GO tags at all + authorised pre-tag marker -> PASS (canonical policy).
C="$(new_clone)"; [ -n "$C" ] || abort_setup "clone failed (case 9)"
for t in $(git -C "$C" tag -l 'aish-laundry-step-*-go'); do clone_del_tag "$C" "$t"; done
[ -z "$(git -C "$C" tag -l 'aish-laundry-step-*-go')" ] || abort_setup "case 9: GO tags not all removed in clone"
assert_clone "isolated#9 no tags + authorised pre-tag -> validate-status PASS" "$C" "$VS" pass
assert_clone "isolated#9 no tags + authorised pre-tag -> validate-roadmap PASS" "$C" "$VR" pass
drop_clone "$C"
echo

# ---------------------------------------------------------------------------
# STEP 6 TRUTHFULNESS (Repair 2) — stale absolutes must FAIL once Step 6 is GO.
# ---------------------------------------------------------------------------
echo "STEP 6 truthfulness — stale absolute claims must be REJECTED"

# Break E — reintroduce "Every product feature is NOT IMPLEMENTED".
backup_file "$STATUSDOC"
python3 - "$STATUSDOC" <<'PY' || abort_setup "could not inject the stale feature claim"
import sys
p = sys.argv[1]; s = open(p, encoding="utf-8").read()
new = s.replace(
    "## 3. Feature status\n",
    "## 3. Feature status\n\nEvery product feature is **NOT IMPLEMENTED**.\n",
    1,
)
if new == s: sys.exit(1)
open(p, "w", encoding="utf-8").write(new)
PY
grep -q "Every product feature is \*\*NOT IMPLEMENTED\*\*" "$STATUSDOC" || abort_setup "stale feature claim not injected"
expect_reject "validate-status rejects 'Every product feature is NOT IMPLEMENTED' while Step 6 is GO" "$STATUSVAL"
cleanup
BACKED_UP=()

# Break F — reintroduce backend "STEP 3 FOUNDATION ONLY" scoping.
backup_file "$STATUSDOC"
python3 - "$STATUSDOC" <<'PY' || abort_setup "could not inject the stale backend claim"
import re, sys
p = sys.argv[1]; s = open(p, encoding="utf-8").read()
new = re.sub(r"\| Backend runtime \|[^\n]*",
             "| Backend runtime | PRESENT - STEP 3 FOUNDATION ONLY |", s, count=1)
if new == s: sys.exit(1)
open(p, "w", encoding="utf-8").write(new)
PY
grep -qi "STEP 3 FOUNDATION ONLY" "$STATUSDOC" || abort_setup "stale backend claim not injected"
expect_reject "validate-status rejects backend 'STEP 3 FOUNDATION ONLY' while Step 6 is GO" "$STATUSVAL"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# Tree + tag integrity + summary. The isolated cases run only in disposable clones,
# so the canonical working tree AND every canonical tag must be byte-identical after.
# ---------------------------------------------------------------------------
AFTER="$(tree_fingerprint)"
TAG_OBJ_AFTER="$(git rev-parse "$STEP6_TAG_NAME" 2>/dev/null || echo absent)"
TAG_PEEL_AFTER="$(git rev-parse "${STEP6_TAG_NAME}^{commit}" 2>/dev/null || echo absent)"

echo "========================================================================"
if [ "$BEFORE" = "$AFTER" ]; then
  echo "working tree + tags verified byte-identical before and after"
else
  echo "  FAIL  canonical working tree or tags changed — a fixture escaped isolation"
  FAILED=$((FAILED + 1))
fi
if [ "$TAG_OBJ_BEFORE" = "$TAG_OBJ_AFTER" ] && [ "$TAG_PEEL_BEFORE" = "$TAG_PEEL_AFTER" ]; then
  echo "canonical Step 6 GO tag unchanged (object ${TAG_OBJ_AFTER}, peel ${TAG_PEEL_AFTER})"
else
  echo "  FAIL  canonical Step 6 GO tag moved: object ${TAG_OBJ_BEFORE}->${TAG_OBJ_AFTER}, peel ${TAG_PEEL_BEFORE}->${TAG_PEEL_AFTER}"
  FAILED=$((FAILED + 1))
fi
# No disposable clone may survive the run.
LEFT=0
for c in "${CLONES[@]:-}"; do [ -n "$c" ] && [ -d "$c" ] && LEFT=$((LEFT + 1)); done
if [ "$LEFT" -ne 0 ]; then
  echo "  FAIL  ${LEFT} disposable clone(s) not cleaned up"
  FAILED=$((FAILED + 1))
else
  echo "all disposable clones destroyed"
fi

echo "SUMMARY [step-06-validators]: ${PASSED}/${TOTAL} expectations met, ${FAILED} failed"
if [ "$FAILED" -ne 0 ] || [ "$BEFORE" != "$AFTER" ]; then
  echo "RESULT: FAIL (step-06-validators)"
  exit 1
fi
echo "RESULT: PASS (step-06-validators)"
