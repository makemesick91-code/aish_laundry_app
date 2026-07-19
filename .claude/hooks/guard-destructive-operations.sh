#!/usr/bin/env bash
#
# guard-destructive-operations.sh — Aish Laundry App destructive command guard.
#
# Governance: see CLAUDE.md section 10 and .claude/rules/12-autonomous-execution.md.
#
# Purpose
#   Inspect a candidate shell command and refuse the high-risk ones. The guard
#   FAILS CLOSED: when a command matches a destructive pattern it is blocked,
#   and when the input is ambiguous or un-inspectable it is also blocked.
#
# Usage
#   guard-destructive-operations.sh "<command>"    # command as argument
#   echo "<command>" | guard-destructive-operations.sh
#   guard-destructive-operations.sh --self-test    # run the internal test table
#   guard-destructive-operations.sh --help
#
# Exit codes
#   0  ALLOW  — no destructive pattern matched
#   2  BLOCK  — destructive pattern matched (or fail-closed); reason on stderr
#   1  usage/self-test failure
#
#   NOTE: in the PreToolUse contract ONLY exit 2 denies. Any fail-closed path
#   that is reached while inspecting a real payload must therefore return 2,
#   never 1 — a 1 would be treated as a non-blocking error and the command
#   would run.
#
# Privacy
#   The candidate command is NEVER written to a log file or any world-readable
#   location. It is echoed back only on stderr of the invoking process so the
#   operator can see what was refused. The candidate is NEVER executed or
#   eval'd — it is only ever matched as text.
#
set -euo pipefail

readonly EXIT_ALLOW=0
readonly EXIT_ERROR=1
readonly EXIT_BLOCK=2

# Anchor character class used in front of a command token: start of string, a
# shell separator, whitespace, or a quote character. Quotes matter because
# wrapper forms such as  bash -c "git push --force"  put the dangerous verb
# immediately after a quote; without the quote in the class the rule is blind
# to it. (normalize() also strips quotes, so this is belt-and-braces.)
readonly SQ="'"
readonly A="(^|[;&|(\"${SQ}]|[[:space:]])"

# ---------------------------------------------------------------------------
# helpers
# ---------------------------------------------------------------------------

usage() {
    cat <<'EOF'
guard-destructive-operations.sh — Aish Laundry App destructive command guard

  guard-destructive-operations.sh "<command>"   inspect a command (argument)
  <command> | guard-destructive-operations.sh   inspect a command (stdin)
  guard-destructive-operations.sh --self-test   run the internal test table
  guard-destructive-operations.sh --help        this message

Exit 0 = ALLOW, exit 2 = BLOCK (fail closed).
EOF
}

# Normalize a candidate command for matching:
#   1. collapse all whitespace to single spaces and trim
#   2. strip single and double quote characters, so quoted targets such as
#      rm -rf "/"  and wrapper bodies such as  bash -c "git push --force"
#      become visible to the token-anchored rules
#   3. repeatedly strip git *global* options that sit between `git` and the
#      subcommand. Without this, `git -C /path push --force` defeats every git
#      rule, because the rules require the subcommand to follow `git` directly.
# Case is preserved so git matching stays precise; SQL matching uses -i.
normalize() {
    printf '%s' "${1-}" \
        | tr '\n\r\t' '   ' \
        | sed -E 's/[[:space:]]+/ /g; s/^ //; s/ $//' \
        | tr -d "\"'" \
        | sed -E ':a; s@(^|[^[:alnum:]_/-])git ((-C|-c|--git-dir|--work-tree|--namespace|--exec-path) [^ ]+|(--git-dir|--work-tree|--namespace|--exec-path)=[^ ]+) @\1git @g; ta'
}

# case-sensitive extended-regex match
_m() { printf '%s' "$1" | grep -Eq -- "$2"; }
# case-insensitive extended-regex match
_mi() { printf '%s' "$1" | grep -Eqi -- "$2"; }

# Return 0 when any non-flag token of a normalized rm command names a
# "root-ish" path: /, ., ~, $HOME, an ancestor of $HOME, or any absolute path
# with two or fewer segments (/etc, /var, /home/fikri). Deep project paths such
# as /home/fikri/Projects/aish_laundry/tmpdir are deliberately NOT root-ish.
_rootish_rm_target() {
    local cmd="$1" t base slashes found=1
    set -f
    for t in $cmd; do
        case "$t" in
            -*|sudo|env|doas|xargs) continue ;;
        esac
        base="${t##*/}"
        [ "$base" = "rm" ] && continue
        t="${t%\*}"
        t="${t%/}"
        case "$t" in
            ""|"/"|"."|".."|"~") found=0; break ;;
            "~/"*)               found=0; break ;;
            "$HOME")             found=0; break ;;
            /*)
                slashes="$(printf '%s' "$t" | tr -cd '/' | wc -c | tr -d ' ')"
                if [ "$slashes" -le 2 ]; then found=0; break; fi
                case "$HOME/" in
                    "$t"/*) found=0; break ;;
                esac
                ;;
        esac
    done
    set +f
    return $found
}

# ---------------------------------------------------------------------------
# classifier
#
# Prints a human-readable reason on stderr and returns 2 when the command is
# destructive; returns 0 otherwise.
# ---------------------------------------------------------------------------

classify() {
    local cmd raw
    cmd="$(normalize "${1-}")"
    # Whitespace-normalized but quote-PRESERVING view. Used only by the output
    # redirection rule, where quoting is what distinguishes a real truncating
    # redirect (`> docs/x.md`) from a quoted literal (`grep -n ">" docs/x.md`).
    raw="$(printf '%s' "${1-}" | tr '\n\r\t' '   ' | sed -E 's/[[:space:]]+/ /g; s/^ //; s/ $//')"

    [ -z "$cmd" ] && return $EXIT_ALLOW

    # -- un-inspectable wrapper constructs -----------------------------------
    # `bash -c "..."`, `sh -c`, `zsh -c` and `eval` hide an arbitrary second
    # layer of shell from every rule below (quoting, $(...), variables). There
    # is no safe way to inspect them from the outside, so they are refused as a
    # class. Note this does NOT block `bash scripts/verify-step-00.sh`.
    if _m "$cmd" "${A}(ba|z|da|k)?sh[[:space:]]+-[[:alpha:]]*c([[:space:]]|$)"; then
        echo "BLOCKED: 'sh -c' / 'bash -c' wrappers hide their payload from this guard." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}eval([[:space:]]|$)"; then
        echo "BLOCKED: 'eval' constructs an un-inspectable command at runtime." >&2
        return $EXIT_BLOCK
    fi

    # -- recursive force delete of a root-ish target -------------------------
    # Requires an rm command token (possibly path-qualified, e.g. /bin/rm), a
    # recursive flag, a force flag, and a root-ish target. `rm -rf build/`,
    # `rm -rf ./node_modules` and deep in-project paths are NOT matched.
    if _m "$cmd" "${A}([^[:space:]]*/)?rm([[:space:]]|$)" \
        && _m "$cmd" '(^|[[:space:]])(-[[:alpha:]]*[rR][[:alpha:]]*|--recursive)([[:space:]]|$)' \
        && _m "$cmd" '(^|[[:space:]])(-[[:alpha:]]*[fF][[:alpha:]]*|--force)([[:space:]]|$)' \
        && { _m "$cmd" '[[:space:]](/|/\*|~|~/|~/\*|\.|\./|\./\*)([[:space:]]|$)' \
             || _rootish_rm_target "$cmd"; }; then
        echo "BLOCKED: recursive force delete of a root, home, or top-level system target." >&2
        return $EXIT_BLOCK
    fi

    # -- git history / worktree destruction ----------------------------------
    if _m "$cmd" "${A}git[[:space:]]+reset[[:space:]]([^[:space:]]+[[:space:]])*--hard([[:space:]]|$)"; then
        echo "BLOCKED: 'git reset --hard' discards committed and working-tree state." >&2
        return $EXIT_BLOCK
    fi

    # word boundary keeps 'git cleanup-notes' out of this rule
    if _m "$cmd" "${A}git[[:space:]]+clean([[:space:]]|$)"; then
        echo "BLOCKED: 'git clean' permanently removes untracked files." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+push[[:space:]]([^[:space:]]+[[:space:]])*(-f|--force|--force-with-lease|--force-if-includes)([[:space:]]|=|$)"; then
        echo "BLOCKED: force push rewrites shared history (CLAUDE.md section 8)." >&2
        return $EXIT_BLOCK
    fi

    # `git push origin +main` — a leading '+' on a refspec IS a force push and
    # carries exactly the same history-rewrite risk as --force.
    if _m "$cmd" "${A}git[[:space:]]+push[[:space:]]([^[:space:]]+[[:space:]])*\+[^[:space:]]+([[:space:]]|$)"; then
        echo "BLOCKED: '+<refspec>' on git push is a force push in disguise." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+push[[:space:]]([^[:space:]]+[[:space:]])*--delete([[:space:]]|$)"; then
        echo "BLOCKED: 'git push --delete' removes a remote branch or tag." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+branch[[:space:]]([^[:space:]]+[[:space:]])*-D([[:space:]]|$)"; then
        echo "BLOCKED: 'git branch -D' force-deletes a branch and its unmerged work." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+tag[[:space:]]([^[:space:]]+[[:space:]])*(-d|--delete)([[:space:]]|$)"; then
        echo "BLOCKED: tags are immutable in this repository (.claude/rules/11-git-and-ci.md)." >&2
        return $EXIT_BLOCK
    fi

    # generalized pathspec restore: covers `git checkout -- .` and the
    # equivalent `git checkout HEAD -- .`
    if _m "$cmd" "${A}git[[:space:]]+checkout[[:space:]]([^[:space:]]+[[:space:]])*(--[[:space:]]+)?\.([[:space:]]|$)"; then
        echo "BLOCKED: 'git checkout [HEAD] -- .' discards every uncommitted change." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+restore[[:space:]]([^[:space:]]+[[:space:]])*\.([[:space:]]|$)"; then
        echo "BLOCKED: 'git restore .' discards every uncommitted change." >&2
        return $EXIT_BLOCK
    fi

    # -- git history DESTRUCTION (irreversible) ------------------------------
    # Rationale: `git reset --hard` is recoverable from the reflog, and this
    # guard already blocks it. Expiring the reflog and then running
    # `git gc --prune=now` is what makes such a reset PERMANENTLY
    # unrecoverable. Blocking the reversible command while allowing the
    # irreversible pair is backwards, so the pair is blocked here too. The same
    # reasoning covers filter-branch/filter-repo, `update-ref -d`, and
    # `stash clear`: each destroys the object or ref that recovery depends on.
    if _m "$cmd" "${A}git[[:space:]]+reflog[[:space:]]+expire([[:space:]]|$)"; then
        echo "BLOCKED: 'git reflog expire' destroys the only recovery path for reset/rebase." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+gc([[:space:]]|$)" \
        && _m "$cmd" '(^|[[:space:]])(--prune|--aggressive)([[:space:]]|=|$)'; then
        echo "BLOCKED: 'git gc --prune' permanently deletes unreachable objects." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+filter-(branch|repo)([[:space:]]|$)"; then
        echo "BLOCKED: filter-branch/filter-repo rewrites every commit in history." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+update-ref[[:space:]]([^[:space:]]+[[:space:]])*(-d|--delete)([[:space:]]|$)"; then
        echo "BLOCKED: 'git update-ref -d' deletes a ref out from under its history." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+stash[[:space:]]+(clear|drop)([[:space:]]|$)"; then
        echo "BLOCKED: 'git stash clear/drop' discards stashed work irrecoverably." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}git[[:space:]]+rebase[[:space:]]([^[:space:]]+[[:space:]])*--root([[:space:]]|$)"; then
        echo "BLOCKED: 'git rebase --root' rewrites the entire branch history." >&2
        return $EXIT_BLOCK
    fi

    # -- non-rm destruction primitives ---------------------------------------
    if _m "$cmd" "${A}find([[:space:]]|$)" \
        && { _m "$cmd" '(^|[[:space:]])-delete([[:space:]]|$)' \
             || { _m "$cmd" '(^|[[:space:]])-execdir?([[:space:]]|$)' \
                  && _m "$cmd" "${A}([^[:space:]]*/)?rm([[:space:]]|$)"; }; }; then
        echo "BLOCKED: 'find -delete' / 'find -exec rm' performs an unbounded mass delete." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}([^[:space:]]*/)?dd([[:space:]]|$)" \
        && _m "$cmd" '(^|[[:space:]])of=/dev/'; then
        echo "BLOCKED: 'dd of=/dev/...' overwrites a raw device." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}([^[:space:]]*/)?shred([[:space:]]|$)"; then
        echo "BLOCKED: 'shred' overwrites file contents unrecoverably." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}mv([[:space:]]|$)" \
        && _m "$cmd" '[[:space:]]/dev/null([[:space:]]|$)'; then
        echo "BLOCKED: 'mv <path> /dev/null' silently destroys the source." >&2
        return $EXIT_BLOCK
    fi

    # Truncating redirection onto a governance-tracked file is the exact
    # equivalent of `truncate -s 0 <file>`, which is already blocked.
    if _m "$raw" '(^|[^>])>[[:space:]]*(\./)?(docs|\.claude|scripts|\.github)/[^[:space:]]*\.(md|sh|py|yml|yaml)([[:space:]]|$)'; then
        echo "BLOCKED: '>' truncates a governance-tracked file; use an explicit edit instead." >&2
        return $EXIT_BLOCK
    fi

    # -- Step 0 scope guard: runtime scaffolding is forbidden -----------------
    # Step 0 is governance only. These generators would create a Flutter or
    # Laravel runtime, which docs/STATUS.md declares ABSENT. Creating one would
    # make the canonical status false, so block at the source.
    if _m "$cmd" "${A}(flutter[[:space:]]+create|dart[[:space:]]+create)([[:space:]]|$)"; then
        echo "BLOCKED: Step 0 forbids creating a Flutter/Dart runtime (see .claude/rules/12)." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}(laravel[[:space:]]+new|composer[[:space:]]+create-project|npm[[:space:]]+create|npx[[:space:]]+create-)"; then
        echo "BLOCKED: Step 0 forbids scaffolding a backend/JS runtime (see .claude/rules/12)." >&2
        return $EXIT_BLOCK
    fi

    # -- reckless permission changes -----------------------------------------
    if _m "$cmd" "${A}chmod([[:space:]]|$)" \
        && _m "$cmd" '(^|[[:space:]])(-[[:alpha:]]*[rR][[:alpha:]]*|--recursive)([[:space:]]|$)' \
        && _m "$cmd" '[[:space:]]777([[:space:]]|$)'; then
        echo "BLOCKED: recursive chmod 777 grants world-write and destroys the permission model." >&2
        return $EXIT_BLOCK
    fi

    # -- destructive SQL ------------------------------------------------------
    if _mi "$cmd" '(^|[^[:alnum:]_])drop[[:space:]]+database([^[:alnum:]_]|$)'; then
        echo "BLOCKED: DROP DATABASE destroys the system of record." >&2
        return $EXIT_BLOCK
    fi

    if _mi "$cmd" '(^|[^[:alnum:]_])drop[[:space:]]+schema([^[:alnum:]_]|$)'; then
        echo "BLOCKED: DROP SCHEMA destroys tenant data structures." >&2
        return $EXIT_BLOCK
    fi

    if _mi "$cmd" '(^|[^[:alnum:]_])drop[[:space:]]+table([^[:alnum:]_]|$)'; then
        echo "BLOCKED: DROP TABLE destroys a table and every row in it." >&2
        return $EXIT_BLOCK
    fi

    if _mi "$cmd" '(^|[^[:alnum:]_])alter[[:space:]]+table([^[:alnum:]_])' \
        && _mi "$cmd" '(^|[^[:alnum:]_])drop([^[:alnum:]_]|$)'; then
        echo "BLOCKED: ALTER TABLE ... DROP destroys a column or constraint and its data." >&2
        return $EXIT_BLOCK
    fi

    if _mi "$cmd" '(^|[^[:alnum:]_])delete[[:space:]]+from([^[:alnum:]_]|$)' \
        && ! _mi "$cmd" '(^|[^[:alnum:]_])where([^[:alnum:]_]|$)'; then
        echo "BLOCKED: unqualified DELETE FROM (no WHERE clause) empties the table." >&2
        return $EXIT_BLOCK
    fi

    if _mi "$cmd" '(^|[^[:alnum:]_])truncate([^[:alnum:]_]|$)'; then
        echo "BLOCKED: TRUNCATE deletes all rows and bypasses financial audit trails." >&2
        return $EXIT_BLOCK
    fi

    # -- container / infrastructure teardown ---------------------------------
    if _m "$cmd" "${A}docker[[:space:]]+system[[:space:]]+prune([[:space:]]|$)"; then
        echo "BLOCKED: 'docker system prune' removes containers, images, and networks." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}docker[[:space:]]+volume[[:space:]]+prune([[:space:]]|$)"; then
        echo "BLOCKED: 'docker volume prune' destroys persistent data volumes." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}terraform[[:space:]]+destroy([[:space:]]|$)"; then
        echo "BLOCKED: 'terraform destroy' tears down provisioned infrastructure." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}kubectl[[:space:]]+delete([[:space:]]|$)"; then
        echo "BLOCKED: 'kubectl delete' removes live cluster resources." >&2
        return $EXIT_BLOCK
    fi

    # -- GitHub repository level ---------------------------------------------
    if _m "$cmd" "${A}gh[[:space:]]+repo[[:space:]]+delete([[:space:]]|$)"; then
        echo "BLOCKED: 'gh repo delete' is irreversible and is owner territory." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}gh[[:space:]]+repo[[:space:]]+archive([[:space:]]|$)"; then
        echo "BLOCKED: 'gh repo archive' makes the repository read-only; owner territory." >&2
        return $EXIT_BLOCK
    fi

    # `gh repo edit --visibility` is the exact unrequested visibility change the
    # gh-api rule below exists to prevent, just via the porcelain command.
    if _m "$cmd" "${A}gh[[:space:]]+repo[[:space:]]+edit([[:space:]]|$)" \
        && _m "$cmd" '(^|[[:space:]])--visibility([[:space:]]|=|$)'; then
        echo "BLOCKED: 'gh repo edit --visibility' changes repository exposure (AMENDMENT-0001)." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}gh[[:space:]]+release[[:space:]]+delete([[:space:]]|$)"; then
        echo "BLOCKED: 'gh release delete' removes a published release and its assets." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}gh[[:space:]]+workflow[[:space:]]+(disable|enable)([[:space:]]|$)"; then
        echo "BLOCKED: enabling/disabling a workflow silently changes the CI gate." >&2
        return $EXIT_BLOCK
    fi

    if _m "$cmd" "${A}gh[[:space:]]+ruleset[[:space:]]+delete([[:space:]]|$)"; then
        echo "BLOCKED: 'gh ruleset delete' removes branch protection rules." >&2
        return $EXIT_BLOCK
    fi

    # unrequested repository visibility / settings mutation via the API,
    # and any mutating call against branch protection endpoints
    if _m "$cmd" "${A}gh[[:space:]]+api([[:space:]]|$)" \
        && { _m "$cmd" '[[:space:]]-X[[:space:]]*(PATCH|DELETE|PUT)([[:space:]]|$)' \
          || _mi "$cmd" '[[:space:]](-f|-F|--field|--raw-field)[[:space:]]*(private|visibility)=' \
          || _mi "$cmd" '/branches/[^[:space:]]*/protection'; }; then
        echo "BLOCKED: repository visibility/settings/protection changes via 'gh api' require owner approval (AMENDMENT-0001)." >&2
        return $EXIT_BLOCK
    fi

    return $EXIT_ALLOW
}

# ---------------------------------------------------------------------------
# self-test
# ---------------------------------------------------------------------------

self_test() {
    local failures=0 total=0

    local -a block_cases=(
        # -- baseline (pre-existing coverage) --------------------------------
        'rm -rf /'
        'rm -rf ~'
        'rm -rf .'
        'sudo rm -rf /'
        'git reset --hard'
        'git reset --hard HEAD~3'
        'git clean -fd'
        'git clean -fdx'
        'git push --force'
        'git push -f origin main'
        'git push --force-with-lease origin main'
        'git branch -D feature/step-00-master-source-and-governance'
        'git tag -d v1.0.0'
        'git push origin --delete feature/x'
        'git checkout -- .'
        'git restore .'
        'psql -c "DROP DATABASE aish_laundry"'
        'DROP SCHEMA public CASCADE;'
        'TRUNCATE TABLE payments;'
        'truncate -s 0 docs/MASTER_SOURCE.md'
        'docker system prune -af'
        'docker volume prune'
        'terraform destroy -auto-approve'
        'kubectl delete pod aish-api-0'
        'gh repo delete makemesick91-code/aish_laundry_app'
        'gh repo archive makemesick91-code/aish_laundry_app'
        'gh api -X PATCH repos/makemesick91-code/aish_laundry_app -f private=true'
        'gh api repos/o/r -f visibility=private'
        'flutter create my_app'
        'dart create my_app'
        'laravel new my_app'
        'composer create-project laravel/laravel app'
        'npm create vite@latest'
        'npx create-react-app app'
        'chmod -R 777 /'

        # -- H1: git global options must not defeat the git rules ------------
        'git -C /some/path push --force'
        'git --git-dir=.git push --force origin main'
        'git --git-dir .git push --force origin main'
        'git -C /some/path reset --hard'
        'git --work-tree=/tmp/wt clean -fdx'
        'git -c user.name=x push --force'
        'git --namespace=ns push --force'
        'git --exec-path=/usr/lib/git-core push --force'
        'git -C /some/path branch -D feature/x'

        # -- H2: wrapper forms ------------------------------------------------
        'bash -c "git push --force"'
        'sh -c "git reset --hard HEAD~5"'
        'zsh -c "rm -rf /"'
        'eval "git push --force"'
        'eval rm -rf /'

        # -- H3: force push without --force ----------------------------------
        'git push origin +main'
        'git push origin +refs/heads/main:refs/heads/main'
        'git push origin HEAD:main --force-if-includes'

        # -- H4: history destruction -----------------------------------------
        'git reflog expire --expire=now --all'
        'git reflog expire --expire-unreachable=now --all'
        'git gc --prune=now'
        'git gc --prune=all --aggressive'
        'git gc --aggressive'
        'git filter-branch --tree-filter "rm -f x" HEAD'
        'git filter-repo --path secrets --invert-paths'
        'git update-ref -d refs/heads/main'
        'git update-ref --delete refs/tags/v1.0.0'
        'git stash clear'
        'git stash drop'
        'git rebase -i --root'
        'git rebase --root'
        'git checkout HEAD -- .'

        # -- H5: rm variants --------------------------------------------------
        'rm -rf "/"'
        '/bin/rm -rf /'
        '/usr/bin/rm -rf /'
        'rm -rf /home/fikri'
        'rm -rf ~/Projects'
        'rm -rf "/home/fikri"'
        'rm -rf /etc'
        'rm -rf /var'
        'rm -rf /home/fikri/'
        'rm --recursive --force /home/fikri'

        # -- H6: non-rm destruction primitives --------------------------------
        'find / -delete'
        'find /home/fikri -name "*.md" -delete'
        'find . -exec rm -rf {} +'
        'dd if=/dev/zero of=/dev/sda bs=1M'
        'dd of=/dev/sda'
        'shred -u docs/MASTER_SOURCE.md'
        'mv docs/MASTER_SOURCE.md /dev/null'
        '> docs/MASTER_SOURCE.md'
        'echo x > docs/MASTER_SOURCE.md'
        'cat /dev/null > .claude/hooks/guard-destructive-operations.sh'
        'echo "" > scripts/verify-step-00.sh'
        'printf x > .github/workflows/ci.yml'

        # -- M2: gh destructive subcommands -----------------------------------
        'gh repo edit --visibility public'
        'gh repo edit o/r --visibility private'
        'gh release delete v1.0.0'
        'gh workflow disable ci.yml'
        'gh workflow enable ci.yml'
        'gh ruleset delete 12345'
        'gh api -X DELETE repos/o/r/branches/main/protection'
        'gh api -X PUT repos/o/r/branches/main/protection'
        'gh api repos/o/r/branches/main/protection -X PATCH'

        # -- L2: SQL ----------------------------------------------------------
        'DROP TABLE orders;'
        'psql -c "DROP TABLE payments"'
        'ALTER TABLE orders DROP COLUMN total;'
        'DELETE FROM payments;'
        'psql -c "DELETE FROM orders"'
    )

    local -a allow_cases=(
        'git status'
        'git status --porcelain'
        'git diff'
        'git diff --stat'
        'git log --oneline -n 5'
        'git log --oneline -20'
        'git add -A'
        'git add CLAUDE.md'
        'git commit -m "docs(step-00): add governance rules"'
        'git commit -m "msg"'
        'git push origin feature/step-00-master-source-and-governance'
        'git push origin aish-laundry-step-00-master-source-governance-v1.0.0-go'
        'git push origin feature/f'
        'git fetch origin'
        'git checkout -b docs/step-00-post-tag-evidence'
        'git checkout -b feature/step-00-master-source-and-governance'
        'git cleanup-notes --dry-run'
        'git cleanup-notes'
        'git tag -a v1 -m msg'
        'git tag -a v1.0.0 -m "baseline"'
        'git clone https://github.com/x/y /tmp/z'
        'git restore --source=HEAD docs/MASTER_SOURCE.md'
        'git stash'
        'git stash pop'
        'git gc'
        'ls -la'
        'cat README.md'
        'grep -r foo .'
        'grep -rn TODO docs'
        'find . -name "*.md"'
        'find docs -type f -name "*.md"'
        'python3 scripts/validate-required-files.py'
        'python3 scripts/check_links.py'
        'bash scripts/verify-step-00.sh'
        'chmod 755 scripts/x.sh'
        'chmod -R 755 scripts'
        'gh pr create --fill'
        'gh pr view 1'
        'gh pr view'
        'gh pr merge 1 --merge'
        'gh run list'
        'gh api repos/o/r/rulesets'
        'gh repo view o/r'
        'rm -rf build/'
        'rm -rf ./node_modules'
        'rm -rf /home/fikri/Projects/aish_laundry/tmpdir'
        'flutter --version'
        'composer install'
        'echo "rm -rf is dangerous"'
        'cat docs/MASTER_SOURCE.md'
        'grep -n ">" docs/MASTER_SOURCE.md'
        'echo hi > /tmp/out.txt'
        'SELECT * FROM payments WHERE id = 1;'
        'DELETE FROM payments WHERE id = 1;'
    )

    # JSON envelope cases exercise main()'s PreToolUse path, which the plain
    # classifier table cannot reach. Each must fail closed with exit 2.
    local -a json_block_payloads=(
        '{"tool_name":"Bash","tool_input":{"command":"git push --force","command":"ls"}}'
        '{"tool_name":"Bash","tool_input":{"command":["git","push","--force"]}}'
        ' {"tool_name":"Bash","tool_input":{"command":"git push --force"}}'
        '{"tool_name":"Bash","tool_input":{"cmd":"ls"}}'
        '{"tool_name":"Bash","tool_input":{}}'
        '{"tool_name":"Bash"}'
        '{"tool_name":"Bash","tool_input":{"command":"rm -rf /"}}'
        '{"tool_name":"Bash","tool_input":{"command":"git -C /x push --force"}}'
        '{"tool_input":{"command":"ls"},"tool_input":{"command":"rm -rf /"}}'
        '{"tool_name":"Bash","tool_input":{"command":123}}'
        '{"tool_name":"Bash","tool_input":{"command":"  "}}'
        '{not valid json'
    )

    local -a json_allow_payloads=(
        '{"tool_name":"Bash","tool_input":{"command":"git status"}}'
        '{"tool_name":"Bash","tool_input":{"command":"rm -rf build/"}}'
        ' {"tool_name":"Bash","tool_input":{"command":"ls -la"}} '
    )

    local c rc
    for c in "${block_cases[@]}"; do
        total=$((total + 1))
        rc=0
        classify "$c" 2>/dev/null || rc=$?
        if [ "$rc" -ne "$EXIT_BLOCK" ]; then
            printf 'SELF-TEST FAIL: expected BLOCK, got %s -> %s\n' "$rc" "$c" >&2
            failures=$((failures + 1))
        fi
    done

    for c in "${allow_cases[@]}"; do
        total=$((total + 1))
        rc=0
        classify "$c" 2>/dev/null || rc=$?
        if [ "$rc" -ne "$EXIT_ALLOW" ]; then
            printf 'SELF-TEST FAIL: expected ALLOW, got %s -> %s\n' "$rc" "$c" >&2
            failures=$((failures + 1))
        fi
    done

    for c in "${json_block_payloads[@]}"; do
        total=$((total + 1))
        rc=0
        printf '%s' "$c" | "$GUARD_SELF" 2>/dev/null || rc=$?
        if [ "$rc" -ne "$EXIT_BLOCK" ]; then
            printf 'SELF-TEST FAIL: expected JSON BLOCK, got %s -> %s\n' "$rc" "$c" >&2
            failures=$((failures + 1))
        fi
    done

    for c in "${json_allow_payloads[@]}"; do
        total=$((total + 1))
        rc=0
        printf '%s' "$c" | "$GUARD_SELF" 2>/dev/null || rc=$?
        if [ "$rc" -ne "$EXIT_ALLOW" ]; then
            printf 'SELF-TEST FAIL: expected JSON ALLOW, got %s -> %s\n' "$rc" "$c" >&2
            failures=$((failures + 1))
        fi
    done

    if [ "$failures" -eq 0 ]; then
        printf 'SELF-TEST PASS: %d/%d cases behaved as expected.\n' "$total" "$total"
        return 0
    fi

    printf 'SELF-TEST FAILED: %d of %d cases misbehaved.\n' "$failures" "$total" >&2
    return $EXIT_ERROR
}

# ---------------------------------------------------------------------------
# entry point
# ---------------------------------------------------------------------------

main() {
    case "${1-}" in
        --self-test)
            self_test
            return $?
            ;;
        --help | -h)
            usage
            return 0
            ;;
    esac

    local candidate=""
    if [ "$#" -gt 0 ] && [ -n "${1-}" ]; then
        candidate="$1"
    elif [ ! -t 0 ]; then
        candidate="$(cat || true)"
    fi

    # Trim surrounding whitespace BEFORE the JSON sniff. Testing for a leading
    # '{' on the untrimmed string meant a single leading space skipped JSON
    # extraction entirely and the raw envelope was flat-matched (matching
    # nothing) -- i.e. the guard failed OPEN in production.
    candidate="$(printf '%s' "$candidate" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"

    # A PreToolUse hook receives a JSON envelope on stdin, not a bare command.
    # Without this, the whole JSON blob is pattern-matched as one string and
    # dangerous commands slip through. Extract tool_input.command instead.
    #
    # Fail-closed conditions (all return 2, because only 2 denies):
    #   * payload does not parse
    #   * any JSON object contains DUPLICATE keys (json.load silently keeps the
    #     last one, so {"command":"git push --force","command":"ls"} would be
    #     inspected as the harmless "ls" while the tool runtime may take either)
    #   * the command value is not a string (e.g. a JSON array) -- do not try to
    #     reassemble it, just refuse
    #   * no usable command key at all
    case "$candidate" in
        '{'*)
            local extracted rcx=0
            extracted="$(printf '%s' "$candidate" | python3 -c '
import json, sys

def no_dup(pairs):
    seen = set()
    for k, _ in pairs:
        if k in seen:
            raise ValueError("duplicate key: %s" % k)
        seen.add(k)
    return dict(pairs)

try:
    d = json.load(sys.stdin, object_pairs_hook=no_dup)
except Exception:
    sys.exit(3)

if not isinstance(d, dict):
    sys.exit(3)

ti = d.get("tool_input")
if ti is None:
    ti = {}
if not isinstance(ti, dict):
    sys.exit(3)

val = ti.get("command")
if val is None:
    val = ti.get("file_path")
if val is None or not isinstance(val, str) or not val.strip():
    sys.exit(3)

sys.stdout.write(val)
' 2>/dev/null)" || rcx=$?

            if [ "$rcx" -ne 0 ] || [ -z "$(normalize "$extracted")" ]; then
                echo "guard: unusable/ambiguous JSON hook payload -- failing closed." >&2
                return $EXIT_BLOCK
            fi
            candidate="$extracted"
            ;;
    esac

    if [ -z "$(normalize "$candidate")" ]; then
        echo "guard: no command supplied (pass it as an argument or on stdin)." >&2
        usage >&2
        return $EXIT_ERROR
    fi

    local rc=0
    classify "$candidate" || rc=$?

    if [ "$rc" -eq "$EXIT_BLOCK" ]; then
        echo "guard: command refused. Escalate to the repository owner; do not edit this guard." >&2
        return $EXIT_BLOCK
    fi

    return $EXIT_ALLOW
}

GUARD_SELF="${BASH_SOURCE[0]}"
main "$@"
