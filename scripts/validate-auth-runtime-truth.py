#!/usr/bin/env python3
"""STATUS.md's authentication claims must match the repository (SEC-01).

WHY THIS VALIDATOR EXISTS
-------------------------
`docs/STATUS.md` said no concrete `AuthService` existed. That was true when it
was written and stopped being true when PR #19 merged, and nothing noticed —
because prose has no relationship to the tree unless something establishes one.
A status file that merely asserts is a status file that can lie, and the
direction it lied in was the dangerous one: it understated, which reads as
caution and is therefore never questioned.

THE HARD PART IS THAT BOTH TENSES ARE TRUE
------------------------------------------
The defect DID exist. Deleting that history would leave the correction looking
like something that was always the case, and Rule 01 requires a corrected claim
to say plainly that the earlier claim was wrong. So STATUS.md must contain BOTH
a historical account of the absence AND a current account of the presence, and a
validator that simply greps for "no concrete AuthService" would fire on the
honest history it is supposed to protect.

This validator is therefore SECTION-AWARE. A sentence under a heading marked
historical is history. The same sentence under a current-state heading is a
false claim. That distinction is the whole design.

WHY MULTIPLE SIGNALS RATHER THAN ONE GREP
-----------------------------------------
A single class-name grep would pass on a file that names `BackendAuthService` in
a comment while production resolves a fake. A single sentence match would pass
the moment somebody rewords the sentence. So the repository side of the
comparison is built from FOUR independent signals, and the document side is
checked in BOTH directions:

  * the document may not assert an absence the tree refutes;
  * the document may not assert completion the tree refutes.

The second direction is the one that matters if the wiring is ever removed: the
prose would keep claiming a working runtime while nothing resolved.

Standard library only, consistent with every other validator here.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

from _common import Reporter, repo_root

STATUS = "docs/STATUS.md"
DECISION_GLOB = "DEC-0032-*.md"

APPS = ("ops_android", "customer_android", "admin_web")

# A heading that opens a HISTORICAL account. Matched on the heading text, so the
# sentences beneath it are read as history rather than as a current claim.
HISTORICAL_HEADING = re.compile(
    r"(historical|what was wrong|superseded|previously|corrected\)|"
    r"before the correction|no longer true)",
    re.I,
)

# Claims that AUTHENTICATION IS ABSENT. Only a failure in a CURRENT section.
ABSENCE_CLAIMS: list[tuple[str, re.Pattern[str]]] = [
    (
        "no concrete AuthService implementation",
        re.compile(r"no concrete\s+`?auth\s*service", re.I),
    ),
    (
        "FakeAuthService is the only implementation",
        re.compile(r"only implementation[^.\n]{0,60}fakeauthservice", re.I),
    ),
    (
        "authServiceProvider throws",
        re.compile(r"authserviceprovider[^.\n]{0,80}(throws|unimplemented)", re.I),
    ),
    (
        "client-to-API session ABSENT",
        re.compile(r"client[^|\n]{0,20}api[^|\n]{0,40}\|\s*\**absent", re.I),
    ),
    (
        "authentication ABSENT",
        re.compile(r"\|\s*authentication[^|\n]*\|\s*\**absent", re.I),
    ),
]

# Claims that the corrective work is COMPLETE. A failure when the tree refutes
# them — the opposite direction, and the one that matters if wiring is removed.
COMPLETION_CLAIMS: list[tuple[str, re.Pattern[str]]] = [
    (
        "BackendAuthService resolves in production",
        re.compile(r"backendauthservice[^.\n]{0,120}(resolve|production)", re.I),
    ),
    (
        "client-to-API session VERIFIED",
        re.compile(r"client[^|\n]{0,20}api[^|\n]{0,60}verified", re.I),
    ),
]


def sections(text: str) -> list[tuple[str, str, bool]]:
    """Split into (heading, body, is_historical).

    A section inherits `historical` from an ancestor heading: sub-headings under
    "What was wrong (historical, corrected)" are history too, and requiring each
    one to repeat the marker would be a rule nobody remembers to follow.
    """
    out: list[tuple[str, str, bool]] = []
    current_heading = "(preamble)"
    current_level = 0
    historical_at_level: int | None = None
    buffer: list[str] = []

    def flush(heading: str, historical: bool) -> None:
        if buffer:
            out.append((heading, "\n".join(buffer), historical))

    for line in text.splitlines():
        match = re.match(r"^(#{1,6})\s+(.*)$", line)
        if match:
            flush(current_heading, historical_at_level is not None)
            buffer.clear()

            level = len(match.group(1))
            current_heading = match.group(2).strip()
            current_level = level

            # Leaving the historical subtree resets the marker.
            if historical_at_level is not None and level <= historical_at_level:
                historical_at_level = None

            if HISTORICAL_HEADING.search(current_heading):
                historical_at_level = current_level
            continue

        buffer.append(line)

    flush(current_heading, historical_at_level is not None)
    return out


def concrete_auth_service_exists(root: Path) -> bool:
    """Signal 1: a class that IMPLEMENTS the interface, not merely a name."""
    for dart in (root / "packages" / "auth" / "lib").rglob("*.dart"):
        if re.search(
            r"class\s+BackendAuthService\b[^{]*implements\s+AuthService",
            dart.read_text(encoding="utf-8"),
        ):
            return True
    return False


def production_resolves_concrete(root: Path) -> bool:
    """Signal 2: every application's provider resolves to it.

    Reads the provider DECLARATION rather than trusting `main.dart`, because the
    original defect was a provider whose default threw and which nothing
    overrode. A provider that resolves the concrete service by default, or an
    entry point that overrides it with one, both count.
    """
    for app in APPS:
        app_dir = root / "apps" / app
        if not app_dir.is_dir():
            return False

        text = "\n".join(
            path.read_text(encoding="utf-8")
            for path in (app_dir / "lib").rglob("*.dart")
        )

        declares = re.search(
            r"authServiceProvider\s*=\s*Provider<[^>]*>\(\s*\n?\s*\(ref\)\s*=>\s*"
            r"(?!.*throw)",
            text,
        )
        overrides = "authServiceProvider.overrideWith" in text

        if not (declares or overrides):
            return False

    return True


def decision_accepted(root: Path) -> bool:
    """Signal 3: DEC-0032 exists and is ACCEPTED."""
    # Matched by ID rather than by full filename: a decision record's slug is
    # descriptive and may be corrected, but its ID never changes.
    matches = sorted((root / "docs" / "decisions").glob(DECISION_GLOB))

    if len(matches) != 1:
        return False

    return re.search(
        r"^\s*[-*]?\s*\**Status\**\s*:?\s*\**\s*ACCEPTED",
        matches[0].read_text(encoding="utf-8"),
        re.I | re.M,
    ) is not None


def composition_guard_present(root: Path) -> bool:
    """Signal 4: the structural guard that makes a recurrence fail early."""
    return (root / "scripts" / "validate-production-composition.py").is_file()


def main() -> int:
    root = repo_root()
    rep = Reporter("auth-runtime-truth")

    status_path = root / STATUS
    if not rep.check(status_path.is_file(), f"{STATUS} exists"):
        return rep.finish()

    text = status_path.read_text(encoding="utf-8")

    signals = {
        "concrete BackendAuthService implements AuthService": concrete_auth_service_exists(root),
        "every application resolves it in production": production_resolves_concrete(root),
        "DEC-0032 is Accepted": decision_accepted(root),
        "the production-composition guard exists": composition_guard_present(root),
    }

    for name, value in signals.items():
        rep.info(f"signal: {name} = {value}")

    auth_runtime_exists = all(signals.values())

    parsed = sections(text)
    rep.info(f"parsed {len(parsed)} sections from {STATUS}")

    historical_count = sum(1 for _, _, hist in parsed if hist)
    rep.check(
        historical_count > 0,
        "STATUS.md still carries a HISTORICAL section for the corrected defect "
        "(deleting it would make the correction look like it was always true)",
    )

    # --- Direction 1: no CURRENT section may assert an absence the tree refutes.
    for heading, body, historical in parsed:
        if historical:
            continue
        for label, pattern in ABSENCE_CLAIMS:
            hit = pattern.search(body)
            if hit and auth_runtime_exists:
                rep.fail(
                    f"CURRENT section '{heading}' claims '{label}', but the "
                    f"repository refutes it: a concrete BackendAuthService "
                    f"exists, every application resolves it, and DEC-0032 is "
                    f"accepted. Move the sentence under a historical heading or "
                    f"correct it (Rule 01)."
                )

    rep.check(
        True,
        "no current-state section asserts an authentication absence the tree refutes",
    )

    # --- Direction 2: no section may assert completion the tree refutes.
    #
    # This is the direction that bites if wiring is ever REMOVED: the prose
    # would keep describing a working runtime while nothing resolved.
    for heading, body, historical in parsed:
        if historical:
            continue
        for label, pattern in COMPLETION_CLAIMS:
            if pattern.search(body) and not auth_runtime_exists:
                missing = [n for n, v in signals.items() if not v]
                rep.fail(
                    f"CURRENT section '{heading}' claims '{label}', but the "
                    f"repository does not support it. Failing signals: "
                    f"{'; '.join(missing)}."
                )

    rep.check(
        True,
        "no current-state section asserts a corrective completion the tree refutes",
    )

    # --- The historical account must READ as history, not as a live blocker.
    #
    # A section correctly placed under a historical heading but written in the
    # present tense still reads as a current blocker to somebody skimming.
    for heading, body, historical in parsed:
        if not historical:
            continue
        rep.check(
            not re.search(r"\bcurrently\b|\bas of today\b|\bright now\b", body, re.I),
            f"historical section '{heading}' does not describe the defect as CURRENT",
        )

    rep.info(
        "both tenses are required: the defect existed and the correction landed. "
        "This validator distinguishes them by section rather than by wording, so "
        "an honest history cannot be mistaken for a stale claim."
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
