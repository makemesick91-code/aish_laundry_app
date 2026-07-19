#!/usr/bin/env python3
"""Assert NO application runtime exists anywhere in the repository.

In force for Step 0 AND Step 1. Both steps are documentation only: Step 0 creates
the governance foundation, Step 1 creates requirements and a conceptual domain
model. Neither creates a Flutter workspace, a Laravel application, a schema, a
migration, an API, a UI, or a deployment. Runtime work begins at Step 3.

A conceptual domain model is not a database schema, and a requirement is not an
implementation. If this validator ever fails during Step 1, the correct response
is to remove the runtime artefact and report the scope breach — never to relax
the list below.

Standard library only.
"""

from __future__ import annotations

import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402

# Exact repo-relative paths that must not exist.
FORBIDDEN_PATHS = [
    "pubspec.yaml",
    "pubspec.lock",
    ".dart_tool",
    "analysis_options.yaml",
    "backend/composer.json",
    "backend/artisan",
    "backend/bootstrap/app.php",
    "composer.json",
    "composer.lock",
    "package.json",
]

# Basenames that must not exist anywhere in the tree.
FORBIDDEN_BASENAMES = {
    "pubspec.yaml",
    "pubspec.lock",
    "analysis_options.yaml",
    "composer.json",
    "composer.lock",
    "package.json",
    "package-lock.json",
    "artisan",
    "yarn.lock",
    "pnpm-lock.yaml",
    # Finding M1: non-canonical runtime manifests were absent from this list.
    "deno.json",
    "deno.jsonc",
    "go.mod",
    "go.sum",
    "cargo.toml",
    "cargo.lock",
    "gemfile",
    "requirements.txt",
    "pyproject.toml",
    "pipfile",
    "build.gradle",
    "build.gradle.kts",
    "settings.gradle",
    "pom.xml",
}

# Directory names that must not exist anywhere in the tree.
FORBIDDEN_DIRNAMES = {
    ".dart_tool",
    "migrations",
    "migration",
    "node_modules",
    "vendor",
}

# Source-file extensions that indicate an application runtime.
# Application source extensions. Finding M1: the original list covered only the
# canonical Flutter/Laravel/Android languages, so a complete Node, Deno, Go, Rust
# or Python backend could be committed while this validator still printed
# "no application runtime present" and the required check stayed green. Step 0's
# central guarantee is that NO runtime exists, so the list must cover any language
# a runtime could plausibly be written in -- not just the two in the canonical stack.
FORBIDDEN_EXTENSIONS = {
    ".dart", ".php", ".kt", ".java", ".swift",
    ".ts", ".tsx", ".js", ".jsx", ".mjs", ".cjs",
    ".go", ".rs", ".rb", ".cs", ".py",
}

# Governance tooling is legitimately written in Python and shell. These extensions
# are permitted ONLY inside the governance tooling directories; anywhere else they
# are treated as application source.
GOVERNANCE_TOOLING_DIRS = ("scripts/", ".claude/hooks/", ".github/")
GOVERNANCE_TOOLING_EXTENSIONS = {".py", ".sh"}

# Container manifests for an application runtime.
FORBIDDEN_CONTAINER_PREFIXES = ("dockerfile", "docker-compose", "compose.yaml", "compose.yml")

SKIP_DIRS = {".git"}


def main() -> int:
    root = repo_root()
    rep = Reporter("no-runtime")

    findings: list[str] = []

    # --- exact paths ---
    for rel in FORBIDDEN_PATHS:
        target = root / rel
        if target.exists() or target.is_symlink():
            findings.append(f"forbidden runtime path exists: {rel}")

    # --- full tree walk ---
    symlink_findings: list[str] = []
    for dirpath, dirnames, filenames in os.walk(root, followlinks=False):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        here = Path(dirpath)

        for d in list(dirnames):
            p = here / d
            rel = p.relative_to(root).as_posix()
            if p.is_symlink():
                dirnames.remove(d)
                target = os.path.realpath(p)
                if not target.startswith(str(root) + os.sep) and target != str(root):
                    symlink_findings.append(
                        f"symlink escapes repository: {rel} -> {target}"
                    )
                continue
            if d.lower() in FORBIDDEN_DIRNAMES:
                findings.append(f"forbidden runtime directory exists: {rel}/")

        for f in filenames:
            p = here / f
            rel = p.relative_to(root).as_posix()
            if p.is_symlink():
                target = os.path.realpath(p)
                if not target.startswith(str(root) + os.sep) and target != str(root):
                    symlink_findings.append(
                        f"symlink escapes repository: {rel} -> {target}"
                    )
                continue
            low = f.lower()
            if low in FORBIDDEN_BASENAMES:
                findings.append(f"forbidden runtime manifest exists: {rel}")
            elif p.suffix.lower() in FORBIDDEN_EXTENSIONS:
                ext = p.suffix.lower()
                rel_posix = str(rel).replace("\\", "/")
                exempt = (
                    ext in GOVERNANCE_TOOLING_EXTENSIONS
                    and rel_posix.startswith(GOVERNANCE_TOOLING_DIRS)
                )
                if not exempt:
                    findings.append(f"forbidden application source file exists: {rel}")
            elif low.startswith(FORBIDDEN_CONTAINER_PREFIXES):
                findings.append(f"forbidden application container manifest exists: {rel}")

    if findings:
        for msg in sorted(set(findings)):
            rep.fail(msg)
    else:
        rep.ok("no Flutter/Dart manifest or artifact present")
        rep.ok("no Laravel/PHP/Composer manifest present")
        rep.ok("no Node/package.json manifest present")
        rep.ok("no .dart/.php/.kt/.java/.swift source file present")
        rep.ok("no database migration directory present")
        rep.ok("no application Dockerfile or docker-compose present")

    if symlink_findings:
        for msg in sorted(set(symlink_findings)):
            rep.fail(msg)
    else:
        rep.ok("no symlink points outside the repository")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
