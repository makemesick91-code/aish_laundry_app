#!/usr/bin/env python3
"""Validate the local-development environment contract and its bootstrap path.

Backed by DEC-0027.

Two things are validated, and both must hold:

1. THE TEMPLATE CONTRACT — `.env.example` is the canonical source of shared
   local-development values; `backend/.env.example` is the Laravel template and
   must carry the identical DB_* contract. Divergence between them is exactly
   what broke fresh-clone reproducibility once already.

2. THE BOOTSTRAP PATH — committed documentation and a committed script must
   actually create BOTH ignored destinations (`.env` and `backend/.env`) from
   those templates, without overwriting an existing file. Correct template
   VALUES with no instruction to create `backend/.env` still yields a fresh
   clone that cannot authenticate, which is the defect DEC-0027 records.

Documentation alone is never accepted as executable bootstrap proof: an
executable script must implement both copies.

This validator FAILS CLOSED — an unparseable file, a missing key, or an
unreadable script is a failure, never a skipped check.

It NEVER prints DB_PASSWORD. Password checks report only a boolean property
(present / non-blank / carries the fictional marker), so a validator log can be
committed to a PUBLIC repository without disclosing the value it inspected.

Standard library only.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

ROOT_ENV = ".env.example"
BACKEND_ENV = "backend/.env.example"

DB_KEYS = ["DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"]

# Redis keys shared by BOTH templates. REDIS_CLIENT is deliberately absent: it is
# Laravel-specific and lives only in the backend template, so requiring parity on
# it would fail a correct repository.
#
# These are governed for the same reason DB_* are. Until DEC-0028 the validator
# checked DB_* only, and `backend/.env.example` carried REDIS_PORT=6379 — the
# container-INTERNAL port — while the compose file publishes 56379. A fresh clone
# following the documented bootstrap therefore could not reach Redis, and
# /api/v1/readiness failed closed with 503. Every path that could have caught it
# was blind: the maintainer's host had a hand-corrected ignored `backend/.env`,
# and CI sets REDIS_PORT at job level, so neither ever read this template value.
# A parity contract enforced on one service and not the other is not a contract.
REDIS_KEYS = ["REDIS_HOST", "REDIS_PORT", "REDIS_PASSWORD"]

# Every key this validator governs structurally.
GOVERNED_KEYS = DB_KEYS + REDIS_KEYS

# ---------------------------------------------------------------------------
# The canonical contract. These are the values a fresh clone must receive.
#
# DB_PORT is the PUBLISHED HOST port from infrastructure/docker-compose.dev.yml
# ("127.0.0.1:55433:5432"). The container-internal listener remains 5432 and is
# NOT governed here — replacing an internal container port with the published
# host port would break the compose file, and GitHub Actions service containers
# legitimately use 5432 in their own network.
# ---------------------------------------------------------------------------
#
# REDIS_PORT is likewise the PUBLISHED HOST port ("127.0.0.1:56379:6379"). The
# container-internal listener remains 6379 and is NOT governed here.
# ---------------------------------------------------------------------------
CANONICAL = {
    "DB_HOST": "127.0.0.1",
    "DB_PORT": "55433",
    "DB_DATABASE": "aish_laundry_dev",
    "DB_USERNAME": "aish_dev",
    "REDIS_HOST": "127.0.0.1",
    "REDIS_PORT": "56379",
}

# Loopback forms accepted for DB_HOST. Deliberately small: a host outside this
# set is not a local development host, whatever it claims about itself.
LOOPBACK_HOSTS = {"127.0.0.1", "::1", "localhost"}

# The password must be recognisably fabricated. Rule 45 is explicit that a
# plausible-looking fake reads as a genuine disclosure to an outside reader, so
# a marker is required rather than merely "not a real password".
PASSWORD_MARKERS = ["CHANGEME", "LOCAL_DEV_ONLY"]

# Unresolved template placeholders. A template that still says "replace_with_…"
# is the exact state that produced the fresh-clone authentication failure.
PLACEHOLDER_PATTERNS = [
    "replace_with_local_username",
    "replace_with_local_database_name",
    "replace_with_local_password",
    "replace_with",
    "replace_me",
    "example_username",
    "example_database",
    "example_password",
    "changeme_here",
    "your_username",
    "your_password",
    "todo",
    "xxx",
]

# Markers that indicate a value belongs to a real deployed environment.
PRODUCTION_MARKERS = ["prod", "production", "staging", "stg", "live", "release"]

# A host that looks like a routable name or a managed database endpoint.
PRODUCTION_HOST_PATTERNS = [
    re.compile(r"\.(com|net|org|io|id|co|dev|app|cloud|sh)\b", re.IGNORECASE),
    re.compile(r"amazonaws|rds\.|azure|gcp|digitalocean|supabase|neon\.|heroku",
               re.IGNORECASE),
]

VALID_LINE = re.compile(r"^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$")


# ---------------------------------------------------------------------------
# Structured parsing
# ---------------------------------------------------------------------------
def parse_env(path: Path) -> tuple[dict[str, str], list[str], list[str]]:
    """Parse an env file structurally.

    Returns (values, duplicate_keys, parse_errors). Deliberately NOT a substring
    search: `grep DB_PORT` matches a comment, a similarly-named key, and a
    commented-out override, none of which is the effective value.
    """
    values: dict[str, str] = {}
    duplicates: list[str] = []
    errors: list[str] = []

    try:
        text = read_text(path)
    except OSError as exc:
        return {}, [], [f"unreadable: {exc}"]

    for lineno, raw in enumerate(text.splitlines(), start=1):
        line = raw.rstrip("\n")
        stripped = line.strip()
        if not stripped or stripped.startswith("#"):
            continue
        m = VALID_LINE.match(line)
        if not m:
            # Only report lines that look like they were MEANT to be settings.
            if "=" in stripped:
                errors.append(f"line {lineno}: unparseable assignment")
            continue
        key, value = m.group(1), m.group(2)
        if key not in GOVERNED_KEYS:
            continue
        if key in values:
            duplicates.append(key)
        values[key] = value

    return values, duplicates, errors


def value_is_quoted_or_padded(value: str) -> bool:
    """True when a value carries surrounding quotes or whitespace.

    The canonical format is bare `KEY=value`. A quoted or padded value is not
    merely untidy: `DB_PORT=" 55433"` and `DB_PORT=55433` are different strings
    to a naive consumer, and the difference surfaces as a connection failure
    rather than as a parse error.
    """
    if value != value.strip():
        return True
    stripped = value.strip()
    if len(stripped) >= 2 and stripped[0] == stripped[-1] and stripped[0] in "\"'":
        return True
    return False


def contains_placeholder(value: str) -> str | None:
    low = value.lower()
    for pat in PLACEHOLDER_PATTERNS:
        if pat in low:
            return pat
    return None


def contains_production_marker(value: str) -> str | None:
    low = value.lower()
    for marker in PRODUCTION_MARKERS:
        if re.search(rf"(?<![a-z]){re.escape(marker)}(?![a-z])", low):
            return marker
    return None


# ---------------------------------------------------------------------------
# Part 1 — the template contract
# ---------------------------------------------------------------------------
def check_templates(root: Path, rep: Reporter) -> None:
    rep.info("--- template contract (DEC-0027) ---")

    root_path = root / ROOT_ENV
    backend_path = root / BACKEND_ENV

    have_root = rep.check(root_path.is_file(), f"[ENV_FILE_PRESENT] {ROOT_ENV} exists")
    have_backend = rep.check(
        backend_path.is_file(), f"[ENV_FILE_PRESENT] {BACKEND_ENV} exists"
    )
    if not (have_root and have_backend):
        rep.fail("[ENV_MISSING_FILE] cannot validate the contract without both templates")
        return

    parsed: dict[str, dict[str, str]] = {}
    for label, path in ((ROOT_ENV, root_path), (BACKEND_ENV, backend_path)):
        values, duplicates, errors = parse_env(path)

        for err in errors:
            rep.fail(f"[ENV_PARSE_ERROR] {label}: {err}")
        if not errors:
            rep.ok(f"[ENV_PARSE_OK] {label} parses cleanly")

        for key in GOVERNED_KEYS:
            rep.check(key in values, f"[ENV_KEY_PRESENT] {label}: {key} declared")

        if duplicates:
            for key in sorted(set(duplicates)):
                rep.fail(f"[ENV_KEY_DUPLICATE] {label}: {key} declared more than once")
        else:
            rep.ok(f"[ENV_KEY_UNIQUE] {label}: no duplicate governed key")

        for key, value in sorted(values.items()):
            if value.strip() == "":
                rep.fail(f"[ENV_VALUE_BLANK] {label}: {key} is blank")
            if value_is_quoted_or_padded(value):
                rep.fail(
                    f"[ENV_VALUE_QUOTED] {label}: {key} carries surrounding "
                    "quotes or whitespace; canonical format is bare KEY=value"
                )
            hit = contains_placeholder(value)
            if hit:
                rep.fail(
                    f"[ENV_PLACEHOLDER_UNRESOLVED] {label}: {key} still contains "
                    f"the unresolved placeholder {hit!r}"
                )

        parsed[label] = values

    root_vals = parsed[ROOT_ENV]
    backend_vals = parsed[BACKEND_ENV]

    # --- the two templates must agree, key by key ---------------------------
    for key in GOVERNED_KEYS:
        rv, bv = root_vals.get(key), backend_vals.get(key)
        if rv is None or bv is None:
            continue  # already reported as missing
        if "PASSWORD" in key:
            # Compared, never printed.
            rep.check(
                rv == bv,
                f"[ENV_MISMATCH] {key} identical in both templates (value not printed)",
            )
        else:
            rep.check(
                rv == bv,
                f"[ENV_MISMATCH] {key} identical in both templates "
                f"(root={rv!r} backend={bv!r})",
            )

    # --- each value against the canonical contract --------------------------
    for label, values in parsed.items():
        host = values.get("DB_HOST")
        if host is not None:
            rep.check(
                host.strip() in LOOPBACK_HOSTS,
                f"[ENV_HOST_NOT_LOOPBACK] {label}: DB_HOST is a loopback host "
                f"(found {host!r})",
            )
            for pattern in PRODUCTION_HOST_PATTERNS:
                if pattern.search(host):
                    rep.fail(
                        f"[ENV_PRODUCTION_HOST] {label}: DB_HOST {host!r} looks like "
                        "a routable or managed endpoint"
                    )
                    break
            marker = contains_production_marker(host)
            if marker:
                rep.fail(
                    f"[ENV_PRODUCTION_HOST] {label}: DB_HOST carries the "
                    f"deployed-environment marker {marker!r}"
                )

        port = values.get("DB_PORT")
        if port is not None:
            raw = port.strip()
            if not re.fullmatch(r"[0-9]+", raw):
                rep.fail(
                    f"[ENV_PORT_MALFORMED] {label}: DB_PORT {port!r} is not a "
                    "bare positive integer"
                )
            else:
                n = int(raw)
                if not (1 <= n <= 65535):
                    rep.fail(
                        f"[ENV_PORT_OUT_OF_RANGE] {label}: DB_PORT {n} is outside 1-65535"
                    )
                else:
                    rep.check(
                        raw == CANONICAL["DB_PORT"],
                        f"[ENV_PORT_UNEXPECTED] {label}: DB_PORT is the published host "
                        f"port {CANONICAL['DB_PORT']} (found {raw}); the container-internal "
                        "listener stays 5432 and is not governed here",
                    )

        # --- Redis, governed exactly as PostgreSQL is above -----------------
        redis_host = values.get("REDIS_HOST")
        if redis_host is not None:
            rep.check(
                redis_host.strip() in LOOPBACK_HOSTS,
                f"[ENV_HOST_NOT_LOOPBACK] {label}: REDIS_HOST is a loopback host "
                f"(found {redis_host!r})",
            )
            for pattern in PRODUCTION_HOST_PATTERNS:
                if pattern.search(redis_host):
                    rep.fail(
                        f"[ENV_PRODUCTION_HOST] {label}: REDIS_HOST {redis_host!r} "
                        "looks like a routable or managed endpoint"
                    )
                    break
            marker = contains_production_marker(redis_host)
            if marker:
                rep.fail(
                    f"[ENV_PRODUCTION_HOST] {label}: REDIS_HOST carries the "
                    f"deployed-environment marker {marker!r}"
                )

        redis_port = values.get("REDIS_PORT")
        if redis_port is not None:
            raw = redis_port.strip()
            if not re.fullmatch(r"[0-9]+", raw):
                rep.fail(
                    f"[ENV_PORT_MALFORMED] {label}: REDIS_PORT {redis_port!r} is not "
                    "a bare positive integer"
                )
            else:
                n = int(raw)
                if not (1 <= n <= 65535):
                    rep.fail(
                        f"[ENV_PORT_OUT_OF_RANGE] {label}: REDIS_PORT {n} is outside "
                        "1-65535"
                    )
                else:
                    rep.check(
                        raw == CANONICAL["REDIS_PORT"],
                        f"[ENV_PORT_UNEXPECTED] {label}: REDIS_PORT is the published "
                        f"host port {CANONICAL['REDIS_PORT']} (found {raw}); the "
                        "container-internal listener stays 6379 and is not governed "
                        "here",
                    )

        for key, code in (
            ("DB_DATABASE", "ENV_DATABASE_UNEXPECTED"),
            ("DB_USERNAME", "ENV_USERNAME_UNEXPECTED"),
        ):
            value = values.get(key)
            if value is None:
                continue
            rep.check(
                value.strip() == CANONICAL[key],
                f"[{code}] {label}: {key} is {CANONICAL[key]!r} (found {value!r})",
            )
            marker = contains_production_marker(value)
            if marker:
                rep.fail(
                    f"[ENV_PRODUCTION_VALUE] {label}: {key} carries the "
                    f"deployed-environment marker {marker!r}"
                )

        password = values.get("DB_PASSWORD")
        if password is not None:
            # Every assertion below is a PROPERTY of the value. The value itself
            # is never printed, at any verbosity, in any branch.
            rep.check(
                password.strip() != "",
                f"[ENV_PASSWORD_BLANK] {label}: DB_PASSWORD is non-blank",
            )
            upper = password.upper()
            present = [m for m in PASSWORD_MARKERS if m in upper]
            rep.check(
                bool(present),
                f"[ENV_PASSWORD_NO_MARKER] {label}: DB_PASSWORD carries a fictional "
                f"local-only marker (one of {PASSWORD_MARKERS}); "
                f"{len(present)} marker(s) found",
            )


# ---------------------------------------------------------------------------
# Part 2 — the bootstrap path
# ---------------------------------------------------------------------------
DOC = "docs/runtime/LOCAL_DEVELOPMENT.md"
BOOTSTRAP = "scripts/bootstrap-step-03.sh"
ENV_BOOTSTRAP = "scripts/bootstrap-env-files.sh"


def check_bootstrap(root: Path, rep: Reporter) -> None:
    rep.info("--- bootstrap path (DEC-0027) ---")

    doc_path = root / DOC
    if not rep.check(doc_path.is_file(), f"[BOOT_DOC_PRESENT] {DOC} exists"):
        return
    doc = read_text(doc_path)

    # Documentation must instruct BOTH creations. The root instruction alone was
    # present before DEC-0027 and was precisely what made the clone unusable.
    root_instruction = re.search(
        r"cp\s+\.env\.example\s+\.env\b", doc
    ) or re.search(r"bootstrap-env-files\.sh", doc)
    backend_instruction = re.search(
        r"cp\s+backend/\.env\.example\s+backend/\.env\b", doc
    ) or re.search(r"bootstrap-env-files\.sh", doc)

    rep.check(
        bool(root_instruction),
        f"[BOOT_DOC_ROOT] {DOC} instructs creating .env from {ROOT_ENV}",
    )
    rep.check(
        bool(backend_instruction),
        f"[BOOT_DOC_BACKEND] {DOC} instructs creating backend/.env from {BACKEND_ENV}",
    )
    rep.check(
        "backend/.env" in doc,
        f"[BOOT_DOC_BACKEND] {DOC} names backend/.env explicitly",
    )

    # An executable path must exist. Documentation is never accepted as proof
    # that a bootstrap step actually runs.
    boot_path = root / BOOTSTRAP
    env_boot_path = root / ENV_BOOTSTRAP
    if not rep.check(
        env_boot_path.is_file(), f"[BOOT_SCRIPT_PRESENT] {ENV_BOOTSTRAP} exists"
    ):
        return
    env_boot = read_text(env_boot_path)

    rep.check(
        boot_path.is_file() and ENV_BOOTSTRAP.split("/")[-1] in read_text(boot_path),
        f"[BOOT_DELEGATION] {BOOTSTRAP} delegates to {ENV_BOOTSTRAP}",
    )

    rep.check(
        "set -euo pipefail" in env_boot,
        f"[BOOT_STRICT_MODE] {ENV_BOOTSTRAP} runs under set -euo pipefail",
    )

    # Both copies must be implemented.
    rep.check(
        re.search(r'install_env\s+"?\.env\.example"?\s+"?\.env"?', env_boot) is not None,
        f"[BOOT_COPIES_ROOT] {ENV_BOOTSTRAP} creates .env from {ROOT_ENV}",
    )
    rep.check(
        re.search(
            r'install_env\s+"?backend/\.env\.example"?\s+"?backend/\.env"?', env_boot
        )
        is not None,
        f"[BOOT_COPIES_BACKEND] {ENV_BOOTSTRAP} creates backend/.env from {BACKEND_ENV}",
    )

    # Overwrite refusal must be structurally present.
    rep.check(
        re.search(r'if\s+\[\s+-e\s+"\$\{REPO_ROOT\}/\$\{dest\}"\s+\]', env_boot)
        is not None,
        f"[BOOT_NO_OVERWRITE] {ENV_BOOTSTRAP} tests for an existing destination "
        "before copying",
    )
    rep.check(
        "PRESERVED — ALREADY EXISTS" in env_boot,
        f"[BOOT_NO_OVERWRITE] {ENV_BOOTSTRAP} reports a preserved destination",
    )
    rep.check(
        re.search(r'if\s+\[\s+-L\s+"\$\{REPO_ROOT\}/\$\{dest\}"\s+\]', env_boot)
        is not None,
        f"[BOOT_SYMLINK_GUARD] {ENV_BOOTSTRAP} refuses to write through a symlink",
    )

    # Templates must be validated before anything is copied.
    validator_pos = env_boot.find("validate-dev-environment-contract.py")
    copy_pos = env_boot.find("install_env .env.example")
    rep.check(
        validator_pos != -1 and (copy_pos == -1 or validator_pos < copy_pos),
        f"[BOOT_VALIDATES_FIRST] {ENV_BOOTSTRAP} validates the templates before copying",
    )

    # It must fail closed on a missing template.
    rep.check(
        re.search(r'\[\s+-f\s+"\$\{REPO_ROOT\}/\$\{template\}"\s+\]', env_boot)
        is not None,
        f"[BOOT_MISSING_TEMPLATE] {ENV_BOOTSTRAP} fails when a template is absent",
    )

    # It must never echo the password or the file contents.
    leaks = []
    for lineno, line in enumerate(env_boot.splitlines(), start=1):
        if not re.match(r"\s*(echo|printf|cat)\b", line):
            continue
        if "DB_PASSWORD" in line:
            leaks.append(f"line {lineno}: prints DB_PASSWORD")
        if re.search(r"cat\s+.*(\$\{?dest|\$\{?template|\.env\b)", line):
            leaks.append(f"line {lineno}: prints environment file content")
    if leaks:
        for leak in leaks:
            rep.fail(f"[BOOT_PRINTS_SECRET] {ENV_BOOTSTRAP}: {leak}")
    else:
        rep.ok(
            f"[BOOT_NO_SECRET_OUTPUT] {ENV_BOOTSTRAP} prints status only, "
            "never DB_PASSWORD or file content"
        )

    # No deployed-environment values may appear in the bootstrap itself.
    prod_hits = []
    for lineno, line in enumerate(env_boot.splitlines(), start=1):
        if line.strip().startswith("#"):
            continue
        for pattern in PRODUCTION_HOST_PATTERNS:
            if pattern.search(line):
                prod_hits.append(f"line {lineno}")
                break
    if prod_hits:
        for hit in prod_hits:
            rep.fail(f"[BOOT_PRODUCTION_VALUE] {ENV_BOOTSTRAP}: {hit}")
    else:
        rep.ok(
            f"[BOOT_NO_PRODUCTION_VALUE] {ENV_BOOTSTRAP} carries no production "
            "or staging endpoint"
        )


def main() -> int:
    parser = argparse.ArgumentParser(add_help=True)
    parser.add_argument(
        "--templates-only",
        action="store_true",
        help="validate only the template contract (used by the bootstrap itself)",
    )
    args = parser.parse_args()

    root = repo_root()
    rep = Reporter("dev-environment-contract")

    check_templates(root, rep)
    if not args.templates_only:
        check_bootstrap(root, rep)

    code = rep.finish()
    if code == 0:
        print()
        print("DEV ENVIRONMENT CONTRACT:")
        print("PASS")
        if not args.templates_only:
            print()
            print("BOOTSTRAP ENVIRONMENT PATH:")
            print("PASS")
    return code


if __name__ == "__main__":
    run_main(main)
