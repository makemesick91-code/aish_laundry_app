#!/usr/bin/env python3
"""Every provider a production screen depends on must resolve in production.

WHY THIS VALIDATOR EXISTS
-------------------------
Twice now the same defect has shipped, and it is invisible to a widget suite by
construction:

  * `authServiceProvider` threw `UnimplementedError` in all three applications
    (DEC-0032). Every real launch died on the first frame that read it.
  * `masterDataRepositoryProvider` threw the same way in Ops Android and Console
    Web. Every Step 4 master-data screen died the moment it was opened.

Both were green in CI the whole time. The reason is structural: each widget test
supplies the missing dependency THROUGH THE SAME PROVIDER the production code
reads, so the test proves the screen works when something provides the
dependency and proves nothing about whether anything actually does.

ROOT CAUSE, stated once: TEST-ONLY OVERRIDES MASK AN INCOMPLETE PRODUCTION
COMPOSITION ROOT.

THE RULE
--------
A provider whose default body throws is not banned — it is the correct shape for
a value that genuinely cannot exist until `main` computes it, such as the
validated `Environment`. What is banned is a throwing provider that NOTHING IN
PRODUCTION OVERRIDES. That combination compiles, passes every widget test, and
fails in the user's hands.

So: for each application, every throwing provider must be overridden in that
application's production entry point. If it is not, the only thing that ever
supplied it was a test.

WHAT THIS DELIBERATELY DOES NOT DO
----------------------------------
It does not propose giving throwing providers harmless no-op production
defaults. A silent no-op would convert a loud wiring failure into a screen that
renders empty forever, which is strictly worse: the failure moves from
validation time to user-navigation time and stops being detectable at all.
Production wiring stays explicit and this check stays fail-closed.

Standard library only, consistent with every other validator here.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

from _common import Reporter, repo_root

# Application roots that ship to a user, each with its production entry point.
APPS = {
    "ops_android": "apps/ops_android",
    "customer_android": "apps/customer_android",
    "admin_web": "apps/admin_web",
}

# Matched on the INITIALIZER, never on the type annotation.
#
# The first version of this pattern required an explicit `Provider<T>`
# annotation on the variable and anchored to column 0. An independent review
# showed it was blind to the idiomatic Dart form `final x = Provider<T>(...)`,
# where the type is inferred: an unwired throwing provider written that way
# passed the gate. `analysis_options.yaml` does not enable
# `always_specify_types`, so nothing forced the annotated style the pattern
# depended on. A guard that sees only one of two equally idiomatic spellings
# is not a guard.
PROVIDER_DECL = re.compile(
    r"^\s*(?:static\s+)?(?:late\s+)?final\s+(?:[\w<>,\s?]+?\s+)?(\w+)\s*=\s*"
    r"[\w.]*Provider\b",
    re.M,
)

# The sentinels a "must be overridden" provider is written with. Kept explicit
# rather than matching any `throw`, so a provider that legitimately throws on a
# genuine runtime error is not mistaken for an unwired dependency.
SENTINEL = re.compile(
    r"throw\s+(UnimplementedError|UnsupportedError|StateError)\b"
)

OVERRIDE = re.compile(r"(\w+)\s*\.\s*overrideWith")


def declarations(app_dir: Path) -> dict[str, tuple[Path, bool]]:
    """Map provider name -> (file, throws_by_default) for one application."""
    found: dict[str, tuple[Path, bool]] = {}
    for dart in sorted((app_dir / "lib").rglob("*.dart")):
        text = dart.read_text(encoding="utf-8")
        for match in PROVIDER_DECL.finditer(text):
            name = match.group(1)
            # The declaration body runs to the next top-level declaration.
            rest = text[match.end():]
            body = re.split(r"\n(?=final |class |void |abstract )", rest)[0]
            found[name] = (dart, bool(SENTINEL.search(body)))
    return found


def production_overrides(app_dir: Path) -> set[str]:
    """Providers the production entry point actually overrides."""
    main_dart = app_dir / "lib" / "main.dart"
    if not main_dart.is_file():
        return set()
    return set(OVERRIDE.findall(main_dart.read_text(encoding="utf-8")))


def main() -> int:
    root = repo_root()
    rep = Reporter("production-composition")

    total_providers = 0

    for app, rel in APPS.items():
        app_dir = root / rel
        if not app_dir.is_dir():
            rep.fail(f"{app}: application root {rel} is absent")
            continue

        decls = declarations(app_dir)
        overrides = production_overrides(app_dir)
        total_providers += len(decls)

        if not rep.check(bool(decls), f"{app}: providers were found to inspect"):
            continue

        throwing = {n for n, (_, t) in decls.items() if t}
        unwired = sorted(throwing - overrides)

        for name in sorted(throwing):
            path = decls[name][0].relative_to(root)
            if name in overrides:
                rep.ok(
                    f"{app}: {name} throws by default AND is overridden in "
                    f"main.dart ({path})"
                )
            else:
                rep.fail(
                    f"{app}: {name} throws by default and NOTHING IN "
                    f"PRODUCTION OVERRIDES IT — only a test ever supplied it "
                    f"({path})"
                )

        rep.check(
            not unwired,
            f"{app}: every throwing provider is wired in production",
        )

        # A production entry point that overrides a provider which does NOT
        # throw is not an error, but overriding one that does not exist is a
        # rename that silently stopped taking effect.
        for name in sorted(overrides):
            rep.check(
                name in decls,
                f"{app}: main.dart overrides {name}, which is a declared "
                f"provider",
            )

    rep.info(f"inspected {total_providers} provider declarations")
    rep.info(
        "a throwing provider is legitimate ONLY when main.dart supplies it; "
        "otherwise the sole supplier was a test"
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
