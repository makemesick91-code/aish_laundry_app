#!/usr/bin/env python3
"""Audit the DEC-0041 public-tracking-portal boundaries, structurally.

DEC-0041 ratifies server-rendered Laravel Blade as the canonical Step 7 public
tracking portal stack. A ratification is only as good as its fences, and DEC-0041's
fences are written as prose in a decision record — which nothing enforces. This
validator enforces them.

WHAT IT AUDITS, AND WHY EACH ONE IS HERE
----------------------------------------
1. Blade is for the PUBLIC TRACKING PORTAL ONLY (DEC-0041 decision item 2). The view
   directory stays confined to `tracking/`, and `routes/web.php` declares exactly one
   route. A second Blade surface is how "one small operator page" becomes the parallel
   admin application the record forbids — and it would arrive one file at a time.

2. Views NEVER DUPLICATE BUSINESS RULES (decision item 3). A template that re-derives a
   status, re-applies a mask, or re-decides what a customer may see is a second
   implementation of the projection, and the two will disagree eventually. Detected
   structurally: no database access, no model class, no service resolution, and no
   status/masking decision logic inside a view.

3. NO PERSISTENT BROWSER STORAGE OF THE TOKEN and NO PUBLIC SESSION (items 4 and 5).
   The token is in the URL path; anything that writes it to a cookie, to
   localStorage, or into a session turns a one-request credential into a durable one.

4. THE TRANSPORT CONTRACT HOLDS (item 6). `no-store`, `noindex`, `Referrer-Policy`,
   CSP, and anti-framing are set in one middleware, and the portal page carries its own
   robots meta tag because a header can be stripped by an intermediary.

5. NO SCRIPT AND NO REMOTE ORIGIN. The CSP says `default-src 'none'`; a view carrying a
   script tag or a remote URL would be a page that cannot load what it asks for — a
   silent breakage — and on a token-bearing surface a remote request is a `SECRET`
   disclosure path (Rule 31 hard rule 10, Rule 32 hard rule 26).

6. NO UNESCAPED OUTPUT. Blade's `{!! !!}` bypasses contextual escaping. On a surface
   rendering tenant- and customer-supplied strings that is an XSS primitive (T7-15).

7. NO STEP 8 / STEP 9 CONTROL on the surface (item 8). The portal must not grow a
   pickup, delivery, courier, proof, or reminder affordance. Detection is STRUCTURAL —
   route segments, form actions, and control identifiers — never naive prose matching,
   because the page legitimately contains Indonesian words about laundry.

DETECTION IS STRUCTURAL, NOT PROSE (Rule 36 hard rule 4). Renaming a forbidden surface
to evade detection is the same violation as building it under its plain name.

This validator NARROWS nothing. DEC-0041 permitted Blade for one surface; this file
audits the residual, exactly as `validate-dec-0039-labels.py` audits DEC-0039's.

Exit 0 = PASS, 1 = FAIL.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

VIEWS_DIR = Path("backend/resources/views")
PORTAL_VIEWS_DIR = VIEWS_DIR / "tracking"
WEB_ROUTES = Path("backend/routes/web.php")
HEADERS_MIDDLEWARE = Path(
    "backend/app/Modules/Tracking/Http/Middleware/PublicTrackingHeaders.php"
)
DECISION = Path(
    "docs/decisions/DEC-0041-oq-014-laravel-blade-as-the-public-tracking-portal-stack.md"
)

# The only web route DEC-0041 permits. A second one is a second surface.
PERMITTED_WEB_ROUTE = "lacak/{token}"

# --- 2. Business logic that must live in a service, never in a view. ---------
# Each pattern is a STRUCTURAL signal of a rule being re-implemented in a template,
# not a word that happens to appear in Indonesian copy.
BUSINESS_LOGIC_IN_VIEW = {
    r"\bDB::": "direct database access",
    r"\bApp\\\\Modules\\\\": "a module class referenced from a template",
    r"\bapp\(\s*[A-Za-z\\\\]+::class": "a service resolved inside a template",
    r"\b(?:Order|Customer|TrackingToken|NotificationIntent)::query\b": "an Eloquent query in a template",
    r"@php\b": "an inline PHP block — business logic with the guard rails removed",
    r"\bhash\(\s*['\"]sha256": "token hashing re-implemented in a template",
    r"\bsubstr_replace\b|\bstr_pad\b.*\*{2,}": "masking re-implemented in a template",
}

# --- 3. Token durability. ----------------------------------------------------
BROWSER_STORAGE = {
    r"\blocalStorage\b": "localStorage",
    r"\bsessionStorage\b": "sessionStorage",
    r"\bdocument\.cookie\b": "document.cookie",
    r"\bsession\(\s*\)": "a session helper",
    r"\bsession\(\s*['\"]": "a session write",
    r"\bCookie::": "a cookie facade call",
    r"\bAuth::": "an authentication facade call",
}

# --- 5. Script and remote origin. -------------------------------------------
SCRIPT_OR_REMOTE = {
    r"<script\b": "a script tag",
    r"\bon(?:click|load|error|submit|change|focus|blur)\s*=": "an inline event handler",
    r"\bjavascript:": "a javascript: URL",
    r"""(?:src|href)\s*=\s*['"]\s*(?:https?:)?//""": "a remote asset URL",
    r"@vite\b": "a Vite bundle — a build toolchain the portal does not have",
}

# --- 7. Step 8 / Step 9 controls. -------------------------------------------
# Structural positions only: a route/action target, a form action, or a control
# identifier. Compound and affixed forms are matched.
STEP_8_9_TOKENS = [
    "pickup", "penjemputan", "jemput",
    "delivery", "deliveries", "pengiriman", "antar",
    "courier", "kurir", "ojek",
    "route", "rute",
    "proof", "bukti_serah",
    "reminder", "pengingat",
    "unclaimed", "menumpuk",
    "storage_fee", "biaya_penyimpanan",
    "settlement", "setoran",
]
STEP_8_9_STRUCTURAL = re.compile(
    r"""(?:action|href|name|id|route)\s*=\s*['"][^'"]*\b(?:%s)"""
    % "|".join(STEP_8_9_TOKENS),
    re.IGNORECASE,
)

REQUIRED_HEADERS = [
    ("Cache-Control", "no-store"),
    ("X-Robots-Tag", "noindex"),
    ("Referrer-Policy", "no-referrer"),
    ("Content-Security-Policy", "default-src 'none'"),
    ("X-Frame-Options", "DENY"),
    ("X-Content-Type-Options", "nosniff"),
]


def blade_views(root: Path) -> list[Path]:
    """Every Blade template in the repository, portal or otherwise."""
    views = root / VIEWS_DIR
    if not views.is_dir():
        return []
    return sorted(views.rglob("*.blade.php"))


def strip_comments(text: str) -> str:
    """Remove Blade and HTML comments before scanning.

    This is not cosmetic. These templates DOCUMENT their own constraints — the
    layout says "There is no <script> tag" and `show` says "There is no {!! !!}" —
    and a scanner that read those sentences would flag the very files whose comments
    prove the rule is being followed. Worse, it would train a future author to delete
    the explanation to make the gate pass, which is exactly backwards.

    Blade comments (`{{-- --}}`) never reach the browser; HTML comments do, but they
    render nothing and execute nothing. Neither can carry the behaviour this
    validator is looking for.
    """
    text = re.sub(r"\{\{--.*?--\}\}", " ", text, flags=re.DOTALL)
    return re.sub(r"<!--.*?-->", " ", text, flags=re.DOTALL)


def scan(text: str, patterns: dict[str, str]) -> list[str]:
    """Which of the named patterns appear? Returns the human descriptions."""
    return [
        description
        for pattern, description in patterns.items()
        if re.search(pattern, text)
    ]


def main() -> int:
    root = repo_root()
    rep = Reporter("dec-0041-portal-stack")

    # The record itself must exist and be ACCEPTED. Auditing boundaries set by a
    # decision that is not accepted would be auditing nothing.
    decision_path = root / DECISION
    if not rep.check(decision_path.is_file(), f"{DECISION} exists"):
        return rep.finish()
    rep.check(
        re.search(r"^\*\*Status:\*\* ACCEPTED", read_text(decision_path), re.MULTILINE)
        is not None,
        "DEC-0041 status is ACCEPTED",
    )

    # --- 1. Blade is for the public tracking portal only. -------------------
    views = blade_views(root)
    rep.check(bool(views), "at least one Blade view exists (the portal itself)")

    outside = [
        v.relative_to(root).as_posix()
        for v in views
        if not v.relative_to(root).as_posix().startswith(PORTAL_VIEWS_DIR.as_posix())
    ]
    rep.check(
        not outside,
        "every Blade view lives under backend/resources/views/tracking/ "
        f"(DEC-0041 item 2; stray: {outside})",
    )

    routes_path = root / WEB_ROUTES
    if rep.check(routes_path.is_file(), f"{WEB_ROUTES} exists"):
        routes_text = read_text(routes_path)
        declared = re.findall(
            r"Route::(?:get|post|put|patch|delete|any|match)\s*\(\s*['\"]([^'\"]+)['\"]",
            routes_text,
        )
        rep.check(
            declared == [PERMITTED_WEB_ROUTE],
            "routes/web.php declares exactly the one permitted web route "
            f"({PERMITTED_WEB_ROUTE!r}; found {declared})",
        )

    # --- 2..7, per view. -----------------------------------------------------
    for view in views:
        rel = view.relative_to(root).as_posix()
        text = strip_comments(read_text(view))

        found = scan(text, BUSINESS_LOGIC_IN_VIEW)
        rep.check(
            not found,
            f"{rel} duplicates no business rule (DEC-0041 item 3; found: {found})",
        )

        found = scan(text, BROWSER_STORAGE)
        rep.check(
            not found,
            f"{rel} persists no token and opens no public session "
            f"(DEC-0041 items 4-5; found: {found})",
        )

        found = scan(text, SCRIPT_OR_REMOTE)
        rep.check(
            not found,
            f"{rel} carries no script and no remote origin (DEC-0041 item 6; found: {found})",
        )

        rep.check(
            "{!!" not in text,
            f"{rel} uses no unescaped Blade output ({{!! !!}}) — T7-15",
        )

        hits = STEP_8_9_STRUCTURAL.findall(text)
        rep.check(
            not hits,
            f"{rel} offers no Step 8/Step 9 control (DEC-0041 item 8; found: {hits})",
        )

    # The portal page carries its own robots meta tag: a header can be stripped by
    # an intermediary, and a meta tag cannot.
    show = root / PORTAL_VIEWS_DIR / "show.blade.php"
    layout = root / PORTAL_VIEWS_DIR / "layout.blade.php"
    markup = "".join(strip_comments(read_text(p)) for p in (show, layout) if p.is_file())
    rep.check(
        re.search(r"""<meta\s+name=['"]robots['"]""", markup) is not None
        and "noindex" in markup,
        "the portal markup carries its own robots noindex meta tag, "
        "so a stripped header cannot cause indexing",
    )

    # --- 4. The transport contract, set in one place. ------------------------
    mw_path = root / HEADERS_MIDDLEWARE
    if rep.check(mw_path.is_file(), f"{HEADERS_MIDDLEWARE} exists"):
        mw = read_text(mw_path)
        for header, token in REQUIRED_HEADERS:
            rep.check(
                header in mw and token in mw,
                f"PublicTrackingHeaders sets {header} carrying {token!r} (DEC-0041 item 6)",
            )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
