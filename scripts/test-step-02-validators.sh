#!/usr/bin/env bash
# Adversarial mutation harness for the Aish Laundry App Step 2 validators.
#
# A validator that has only ever been run against correct input is untested.
# This harness deliberately breaks the repository in 30 specific ways and
# asserts that the relevant validator turns RED for each one. A mutation that
# slips through is a validator defect and fails this harness.
#
# Safety: every mutation is applied to a backup-protected copy of the real file
# and reverted immediately, and an EXIT trap restores everything even if the
# script is interrupted. No mutation is ever left in the working tree; the
# harness verifies that itself before exiting.
set -uo pipefail

SCRIPT_DIR="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

PY="${PYTHON:-python3}"
WORK="$(mktemp -d)"
declare -a TOUCHED=()
declare -a CREATED=()

PASS=0
FAIL=0
declare -a FAILED_NAMES=()

restore_all() {
  for f in "${TOUCHED[@]:-}"; do
    [ -n "$f" ] || continue
    local key
    key="$(printf '%s' "$f" | tr '/' '_')"
    if [ -f "$WORK/$key" ]; then
      cp -- "$WORK/$key" "$f"
    fi
  done
  for f in "${CREATED[@]:-}"; do
    [ -n "$f" ] || continue
    rm -f -- "$f"
  done
  TOUCHED=()
  CREATED=()
}

cleanup() {
  restore_all
  rm -rf -- "$WORK"
}
trap cleanup EXIT INT TERM

backup() {
  local f="$1"
  local key
  key="$(printf '%s' "$f" | tr '/' '_')"
  cp -- "$f" "$WORK/$key"
  TOUCHED+=("$f")
}

created() {
  CREATED+=("$1")
}

# assert_red <mutation name> <validator command...>
# Runs the validator and requires a NON-ZERO exit. Then restores the tree.
assert_red() {
  local name="$1"
  shift
  local rc=0
  "$@" >/dev/null 2>&1 || rc=$?
  if [ "$rc" -ne 0 ]; then
    printf 'CAUGHT   %-58s (exit %s)\n' "$name" "$rc"
    PASS=$((PASS + 1))
  else
    printf 'MISSED   %-58s (exit 0 — validator did not catch it)\n' "$name"
    FAIL=$((FAIL + 1))
    FAILED_NAMES+=("$name")
  fi
  restore_all
}

echo "########################################################################"
echo "# AISH LAUNDRY APP — STEP 2 ADVERSARIAL VALIDATOR HARNESS"
echo "# repo root : $REPO_ROOT"
echo "# git sha   : $(git rev-parse HEAD 2>/dev/null || echo 'unavailable')"
echo "# started   : $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "########################################################################"
echo ""
echo "Each line below breaks the repository on purpose and requires the"
echo "validator to turn RED. CAUGHT is the desired result."
echo ""

# Snapshot the tree BEFORE any mutation. Residue is defined as a difference
# from this snapshot, not as "the tree is modified" — the Step 2 branch has
# legitimate uncommitted work in it while it is being built.
BEFORE="$(mktemp)"
git status --porcelain > "$BEFORE" 2>/dev/null || true

TOKENS="docs/design/tokens"
SEM="$TOKENS/semantic-light.json"
PRIM="$TOKENS/primitives.json"
SIZ="$TOKENS/sizing.json"
ALIAS="$TOKENS/component-aliases.json"
INV="docs/ux/SCREEN_INVENTORY.md"
JRN="docs/ux/CRITICAL_JOURNEYS.md"
TRACE="docs/quality/STEP_02_TRACEABILITY.md"
PRIV="docs/ux/SECURITY_AND_PRIVACY_UX.md"
UNCL="docs/ux/UNCLAIMED_LAUNDRY_UX.md"
OFFL="docs/ux/OFFLINE_AND_SYNC_UX.md"
CTX="docs/ux/information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md"
CAT="docs/design/COMPONENT_CATALOG.md"
MTX="docs/design/COMPONENT_STATE_MATRIX.md"
THREAT="docs/security/DESIGN_AND_UX_THREAT_REVIEW.md"
GLOS="docs/design/UX_COPY_GLOSSARY.md"
A11Y="docs/design/ACCESSIBILITY.md"

# --- 1. duplicate token ----------------------------------------------------
backup "$SEM"
$PY - <<'EOF'
import json
p="docs/design/tokens/semantic-light.json"
d=json.load(open(p))
d["tokens"]["color.blue.700"]=d["tokens"]["color.semantic.primary"]
json.dump(d,open(p,"w"),indent=2)
EOF
assert_red "1  duplicate token name" $PY "$SCRIPT_DIR/validate-design-tokens.py"

# --- 2. unresolved token reference -----------------------------------------
backup "$SEM"
$PY - <<'EOF'
import json
p="docs/design/tokens/semantic-light.json"
d=json.load(open(p))
d["tokens"]["color.semantic.primary"]["value"]="{color.blue.999}"
json.dump(d,open(p,"w"),indent=2)
EOF
assert_red "2  unresolved token reference" $PY "$SCRIPT_DIR/validate-token-references.py"

# --- 3. circular token reference -------------------------------------------
backup "$SEM"
backup "$PRIM"
$PY - <<'EOF'
import json
p="docs/design/tokens/semantic-light.json"
d=json.load(open(p))
d["tokens"]["color.semantic.primary"]["value"]="{color.semantic.secondary}"
d["tokens"]["color.semantic.secondary"]["value"]="{color.semantic.primary}"
json.dump(d,open(p,"w"),indent=2)
EOF
assert_red "3  circular token reference" $PY "$SCRIPT_DIR/validate-token-references.py"

# --- 4. contrast below the floor -------------------------------------------
backup "$PRIM"
$PY - <<'EOF'
import json
p="docs/design/tokens/primitives.json"
d=json.load(open(p))
d["tokens"]["color.blue.700"]["value"]="#BBDDFF"   # far below 4.5:1 on white
json.dump(d,open(p,"w"),indent=2)
EOF
assert_red "4  colour contrast below its declared target" \
  $PY "$SCRIPT_DIR/validate-color-contrast.py"

# --- 5. focus indicator removed --------------------------------------------
backup "$A11Y"
$PY - <<'EOF'
import re
p="docs/design/ACCESSIBILITY.md"
s=open(p).read()
s=re.sub(r"(?i)focus[^.\n]{0,110}(?:can |will |shall |is |are )?"
         r"(?:never|not)\s+(?:be\s+)?(?:removed|suppressed|disabled|hidden)",
         "focus styling is left to the platform", s)
s=re.sub(r"(?i)focus[^.\n]{0,90}always visible", "focus styling varies", s)
open(p,"w").write(s)
EOF
assert_red "5  focus-never-removed guarantee deleted" \
  $PY "$SCRIPT_DIR/validate-accessibility.py"

# --- 6. screen error state removed -----------------------------------------
backup "$INV"
sed -i 's/^| Error state |/| Removed row |/' "$INV"
assert_red "6  screen error state removed" \
  $PY "$SCRIPT_DIR/validate-screen-inventory.py"

# --- 7. touch target below 48 ----------------------------------------------
backup "$SIZ"
$PY - <<'EOF'
import json
p="docs/design/tokens/sizing.json"
d=json.load(open(p))
d["tokens"]["size.touch.min"]["value"]=32
json.dump(d,open(p,"w"),indent=2)
EOF
assert_red "7  touch target reduced below 48dp" \
  $PY "$SCRIPT_DIR/validate-breakpoints.py"

# --- 8. screen with no requirement reference -------------------------------
backup "$INV"
$PY - <<'EOF'
import re
p="docs/ux/SCREEN_INVENTORY.md"
s=open(p).read()
# Strip requirement IDs from exactly one screen block.
i=s.index("### SCR-OPS-020")
j=s.index("### SCR-OPS-021")
block=s[i:j]
block=re.sub(r"\b(FR|NFR|SEC|TEN|FIN|OFF|TRK|DEL|UCL|NOT|SUB|RPT)-\d{3,4}\b",
             "none", block)
open(p,"w").write(s[:i]+block+s[j:])
EOF
assert_red "8  screen with no requirement reference" \
  $PY "$SCRIPT_DIR/validate-screen-inventory.py"

# --- 9. requirement left unclassified --------------------------------------
backup "$TRACE"
sed -i '0,/^| `SEC-010`/{/^| `SEC-010`/d}' "$TRACE"
assert_red "9  requirement removed from the classification" \
  $PY "$SCRIPT_DIR/validate-ux-requirement-classification.py"

# --- 10. security requirements dropped from the mapping --------------------
backup "$TRACE"
sed -i '/^| `SEC-0[0-2][0-9]`/d' "$TRACE"
assert_red "10 security requirements dropped from the mapping" \
  $PY "$SCRIPT_DIR/validate-ux-requirement-classification.py"

# --- 11. journey with no recovery ------------------------------------------
backup "$JRN"
sed -i '0,/^| Recovery |/{s/^| Recovery |/| Removed |/}' "$JRN"
assert_red "11 journey with no recovery path" \
  $PY "$SCRIPT_DIR/validate-journeys.py"

# --- 12. tenant context guarantee removed ----------------------------------
backup "$CTX"
$PY - <<'EOF'
import re
p="docs/ux/information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md"
s=open(p).read()
s=re.sub(r"(?i)\b(never|not)\b([^.\n]{0,60})silent", r"sometimes\2silent", s)
s=s.replace("never silent", "sometimes silent")
open(p,"w").write(s)
EOF
assert_red "12 silent tenant switch permitted" \
  $PY "$SCRIPT_DIR/validate-navigation.py"

# --- 13. full customer phone number in the corpus --------------------------
backup "$PRIV"
FAKE_A="62""81""2345""6789"
printf '\nContoh nomor pelanggan (fiktif): %s ditampilkan penuh.\n' "$FAKE_A" >> "$PRIV"
assert_red "13 unmasked customer phone number" \
  $PY "$SCRIPT_DIR/validate-privacy-ux.py"

# --- 14. tracking portal full-address prohibition removed ------------------
backup "$PRIV"
backup "docs/ux/TRACKING_PORTAL_UX.md"
sed -i 's/full address/street name/g' "$PRIV" "docs/ux/TRACKING_PORTAL_UX.md"
assert_red "14 tracking portal full-address prohibition removed" \
  $PY "$SCRIPT_DIR/validate-privacy-ux.py"

# --- 15. payment marked paid from client state -----------------------------
backup "$OFFL"
backup "$PRIV"
backup "docs/ux/OPS_ANDROID_UX.md"
$PY - <<'EOF'
import re
p="docs/ux/OFFLINE_AND_SYNC_UX.md"
s=open(p).read()
s=re.sub(r"(?i)acknowledg\w*", "assumed", s)
s=re.sub(r"(?i)(never|not)([^.\n]{0,80})(paid|payment)([^.\n]{0,80})"
         r"(client|local|device)", r"the\2\3\4\5", s)
open(p,"w").write(s)
for q in ["docs/ux/SECURITY_AND_PRIVACY_UX.md", "docs/ux/OPS_ANDROID_UX.md"]:
    t=open(q).read()
    t=re.sub(r"(?i)acknowledg\w*", "assumed", t)
    t=re.sub(r"(?i)(never|not)([^.\n]{0,80})(paid|payment)([^.\n]{0,80})"
             r"(client|local|device)", r"the\2\3\4\5", t)
    open(q,"w").write(t)
EOF
assert_red "15 payment treated as final from client state" \
  $PY "$SCRIPT_DIR/validate-privacy-ux.py"

# --- 16. H+7 reminder stage removed ----------------------------------------
backup "$UNCL"
sed -i 's/H+7/H+8/g' "$UNCL"
assert_red "16 H+7 reminder stage removed" \
  $PY "$SCRIPT_DIR/validate-privacy-ux.py"

# --- 17. automatic disposal introduced -------------------------------------
backup "$UNCL"
$PY - <<'EOF'
import re
p="docs/ux/UNCLAIMED_LAUNDRY_UX.md"
s=open(p).read()
s=re.sub(r"(?i)(never|no |not|forbidden|prohibit)([^.\n]{0,120})"
         r"(dispos|auction|sell|sale|donat)", r"the system may\2\3", s)
open(p,"w").write(s)
EOF
assert_red "17 automatic disposal path introduced" \
  $PY "$SCRIPT_DIR/validate-privacy-ux.py"

# --- 18. integer-Rupiah rule removed ---------------------------------------
backup "$GLOS"
backup "docs/design/CONTENT_DESIGN.md"
sed -i 's/[Ii]nteger Rupiah/floating-point Rupiah/g; s/[Ii]nteger rupiah/floating-point rupiah/g' \
  "$GLOS" "docs/design/CONTENT_DESIGN.md"
assert_red "18 floating-point money introduced" \
  $PY "$SCRIPT_DIR/validate-content-glossary.py"

# --- 19. SVG with an embedded script ---------------------------------------
FIRST_SVG="$(find docs/ux/wireframes -name '*.svg' | sort | head -1)"
backup "$FIRST_SVG"
sed -i 's#</svg>#<script>alert(1)</script></svg>#' "$FIRST_SVG"
assert_red "19 SVG with an embedded script" \
  $PY "$SCRIPT_DIR/validate-wireframes.py"

# --- 20. SVG with remote content -------------------------------------------
backup "$FIRST_SVG"
sed -i 's#</svg>#<image href="https://evil.example/x.png"/></svg>#' "$FIRST_SVG"
assert_red "20 SVG referencing remote content" \
  $PY "$SCRIPT_DIR/validate-wireframes.py"

# --- 21. secret committed ---------------------------------------------------
# The key is assembled from fragments at run time so that no complete AWS key
# literal is ever committed to this PUBLIC repository, while the file under
# test still receives a complete, detectable one.
K1="AKIA"
K2="IOSFODNN7"
K3="EXAMPLE"
$PY - "$K1$K2$K3" <<'EOF'
import sys
open("docs/design/leaked-fixture.md", "w").write(
    "# Fixture\nAWS_ACCESS_KEY_ID=%s\n" % sys.argv[1])
EOF
created "docs/design/leaked-fixture.md"
assert_red "21 secret committed to a PUBLIC repository" \
  bash "$SCRIPT_DIR/validate-secrets.sh"

# --- 22. PII fixture --------------------------------------------------------
backup "$PRIV"
FAKE_B="62""81""1987""6543"
printf '\nPelanggan (fiktif): %s, alamat lengkap ditampilkan.\n' "$FAKE_B" >> "$PRIV"
assert_red "22 personal data committed" \
  $PY "$SCRIPT_DIR/validate-privacy-ux.py"

# --- 23. pubspec.yaml introduced -------------------------------------------
cat > apps/pubspec.yaml <<'EOF'
name: aish_laundry
environment:
  sdk: '>=3.0.0 <4.0.0'
EOF
created "apps/pubspec.yaml"
assert_red "23 pubspec.yaml introduced (Flutter runtime)" \
  $PY "$SCRIPT_DIR/validate-no-runtime.py"

# --- 24. backend runtime introduced ----------------------------------------
cat > backend/composer.json <<'EOF'
{ "name": "aish/laundry", "require": { "laravel/framework": "^11.0" } }
EOF
created "backend/composer.json"
assert_red "24 composer.json introduced (backend runtime)" \
  $PY "$SCRIPT_DIR/validate-no-runtime.py"

# --- 25. a later Step advanced without evidence ----------------------------
backup "docs/STATUS.md"
sed -i '0,/Step 3/{s/PLANNED/IN PROGRESS/}' docs/STATUS.md
assert_red "25 Step 3 advanced to IN PROGRESS" \
  $PY "$SCRIPT_DIR/validate-status.py"

# --- 26. wireframe claimed as implemented ----------------------------------
backup "$FIRST_SVG"
sed -i 's/LOW-FIDELITY — NOT IMPLEMENTED/FINAL UI — IMPLEMENTED/' "$FIRST_SVG"
sed -i 's/LOW-FIDELITY - NOT IMPLEMENTED/FINAL UI - IMPLEMENTED/' "$FIRST_SVG"
assert_red "26 wireframe claimed as implemented" \
  $PY "$SCRIPT_DIR/validate-wireframes.py"

# --- 27. dark mode claimed available ---------------------------------------
backup "docs/design/DESIGN_SYSTEM.md"
printf '\nDark mode is available and ships with the MVP.\n' \
  >> docs/design/DESIGN_SYSTEM.md
assert_red "27 dark mode claimed available" \
  $PY "$SCRIPT_DIR/validate-design-required-files.py"

# --- 28. placeholder logo claimed final ------------------------------------
backup "docs/design/BRAND_FOUNDATION.md"
printf '\nThe final logo is approved and locked for release.\n' \
  >> docs/design/BRAND_FOUNDATION.md
assert_red "28 placeholder wordmark claimed as the final logo" \
  $PY "$SCRIPT_DIR/validate-design-required-files.py"

# --- 29. component accessibility contract removed --------------------------
backup "$CAT"
sed -i -e 's/[Ss]creen-reader contract/rendering note/g' \
  -e 's/[Ss]creen reader contract/rendering note/g' \
  -e 's/[Kk]eyboard contract/rendering note/g' \
  -e 's/\*\*Screen reader:\*\*/**Note:**/g' \
  -e 's/\*\*Keyboard:\*\*/**Note:**/g' \
  -e 's/Screen reader:/Note:/g' -e 's/Keyboard:/Note:/g' "$CAT"
assert_red "29 component accessibility contract removed" \
  $PY "$SCRIPT_DIR/validate-accessibility.py"

# --- 30. threat finding stripped of its UX mitigation ----------------------
backup "$THREAT"
sed -i -e 's/[Mm]itigations/Commentaries/g' -e 's/[Mm]itigation/Commentary/g' -e 's/[Mm]itigated/Noted/g' -e 's/MITIGATION/COMMENTARY/g' -e 's/MITIGATED/NOTED/g' "$THREAT"
assert_red "30 threat finding with no UX mitigation" \
  $PY "$SCRIPT_DIR/validate-design-threat-review.py"

# ---------------------------------------------------------------------------
# The harness must leave nothing behind.
# ---------------------------------------------------------------------------
echo ""
echo "------------------------------------------------------------------------"
AFTER="$(mktemp)"
git status --porcelain > "$AFTER" 2>/dev/null || true
RESIDUE="$(diff "$BEFORE" "$AFTER" || true)"
rm -f "$BEFORE" "$AFTER"
if [ -n "$RESIDUE" ]; then
  echo "RESIDUE: the tree differs from the pre-harness snapshot:"
  printf '%s\n' "$RESIDUE"
  FAIL=$((FAIL + 1))
  FAILED_NAMES+=("working tree left dirty")
else
  echo "Working tree identical to the pre-harness snapshot — every mutation"
  echo "was reverted and nothing was left behind."
fi

# Content, not just status: a restored file must be byte-identical.
CONTENT_DRIFT="$(git diff --stat -- docs/design/tokens docs/ux/SCREEN_INVENTORY.md \
  docs/security/DESIGN_AND_UX_THREAT_REVIEW.md 2>/dev/null | tail -1 || true)"
if [ -n "$CONTENT_DRIFT" ]; then
  echo "Note: tracked Step 2 files show diffs against HEAD, which is expected"
  echo "while the branch is in progress: $CONTENT_DRIFT"
fi

for leftover in apps/pubspec.yaml backend/composer.json docs/design/leaked-fixture.md; do
  if [ -e "$leftover" ]; then
    echo "RESIDUE: $leftover still exists"
    FAIL=$((FAIL + 1))
    FAILED_NAMES+=("residue: $leftover")
  fi
done

echo ""
echo "########################################################################"
echo "# ADVERSARIAL HARNESS SUMMARY"
echo "########################################################################"
echo "MUTATIONS CAUGHT : $PASS"
echo "MUTATIONS MISSED : $FAIL"
if [ "$FAIL" -ne 0 ]; then
  echo ""
  echo "The following mutations were NOT caught, which means the corresponding"
  echo "validator does not actually enforce what it claims:"
  for n in "${FAILED_NAMES[@]}"; do
    echo "  - $n"
  done
  echo ""
  echo "ADVERSARIAL HARNESS: FAIL"
  exit 1
fi
echo ""
echo "All 30 mutations were caught. Note what this does and does not prove: it"
echo "proves each validator turns red on the specific defect it targets. It does"
echo "not prove the validators are complete, and it is not a test of any"
echo "application, because no application exists."
echo "ADVERSARIAL HARNESS: PASS"
exit 0
