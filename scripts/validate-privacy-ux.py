#!/usr/bin/env python3
"""Validate the privacy and security UX patterns.

The rules enforced here are the ones whose violation is expensive rather than
untidy: a full address on a public portal, a payment marked paid from client
state, an external courier given tenant-wide navigation, or a silent sync
failure.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import REQUIRED_SYNC_STATES, markdown_files, read

PRIVACY_DOC = "docs/ux/SECURITY_AND_PRIVACY_UX.md"
TRACKING_DOC = "docs/ux/TRACKING_PORTAL_UX.md"
OFFLINE_DOC = "docs/ux/OFFLINE_AND_SYNC_UX.md"
COURIER_DOC = "docs/ux/COURIER_UX.md"
UNCLAIMED_DOC = "docs/ux/UNCLAIMED_LAUNDRY_UX.md"

REQUIRED_PATTERNS = {
    "phone masking": ["phone mask", "mask", "masked phone"],
    "address masking": ["address mask", "masked address", "full address"],
    "tracking token handling": ["tracking token", "token"],
    "clipboard warning": ["clipboard"],
    "screenshot considerations": ["screenshot"],
    "session expiry": ["session expir"],
    "device revocation": ["device revoc", "device revok",
                          "revoked device", "device is revoked"],
    "step-up authentication": ["step-up", "step up"],
    "OTP": ["otp"],
    "permission denied": ["permission denied"],
    "support impersonation banner": ["impersonat"],
    "audit reason": ["audit reason", "reason", "audit"],
    "external courier guest access": ["guest", "external courier"],
    "payment confirmation": ["payment confirmation", "confirm payment",
                             "payment is confirmed"],
    "refund confirmation": ["refund"],
    "void confirmation": ["void"],
    "tenant switching": ["tenant switch"],
    "export warning": ["export"],
    "retention notice": ["retention"],
    "marketing consent": ["marketing consent", "marketing"],
    "transactional consent": ["transactional"],
    "opt-out": ["opt-out", "opt out"],
}

# What the public tracking projection must never contain.
TRACKING_PROHIBITIONS = [
    "full address", "internal note", "margin", "cost price",
]

# A real-looking Indonesian mobile number committed to a PUBLIC repository.
REAL_PHONE = re.compile(r"\b(?:\+62|62)8[1-9][0-9]{7,10}\b")


def main() -> int:
    root = repo_root()
    rep = Reporter("privacy and security UX")

    privacy = read(root, PRIVACY_DOC)
    rep.check(bool(privacy), f"{PRIVACY_DOC} exists")
    low_privacy = privacy.lower()

    missing = []
    for name, needles in sorted(REQUIRED_PATTERNS.items()):
        if any(n in low_privacy for n in needles):
            rep.ok(f"{PRIVACY_DOC} documents '{name}'")
        else:
            rep.fail(f"{PRIVACY_DOC} documents '{name}'")
            missing.append(name)
    rep.check(not missing,
              f"every mandated privacy UX pattern is documented "
              f"({len(missing)} missing)")

    # -- the public tracking projection prohibitions -----------------------
    tracking = read(root, TRACKING_DOC)
    rep.check(bool(tracking), f"{TRACKING_DOC} exists")
    combined = (tracking + privacy).lower()
    for item in TRACKING_PROHIBITIONS:
        rep.check(
            item in combined,
            f"the tracking portal prohibition on '{item}' is stated",
        )
    rep.check(
        "noindex" in combined,
        "the tracking portal noindex requirement is stated",
    )

    # A masked value must actually be shown as masked somewhere.
    rep.check(
        bool(re.search(r"[x\*•●·]{3,}", privacy, re.I)),
        f"{PRIVACY_DOC} shows a worked masking example",
    )

    # -- payment honesty ----------------------------------------------------
    payment_texts = ""
    for rel in (OFFLINE_DOC, PRIVACY_DOC, "docs/ux/OPS_ANDROID_UX.md"):
        payment_texts += read(root, rel).lower()
    rep.check(
        bool(re.search(r"(never|not).{0,80}(paid|payment).{0,80}"
                       r"(client|local|device)", payment_texts))
        or bool(re.search(r"(client|local|device).{0,80}"
                          r"(never|not).{0,80}(paid|payment)", payment_texts)),
        "an order is never marked paid from client or local state",
    )
    rep.check(
        "acknowledg" in payment_texts,
        "server acknowledgement is the condition for treating an operation as final",
    )

    # -- the nine sync states are all distinguished ------------------------
    offline = read(root, OFFLINE_DOC)
    rep.check(bool(offline), f"{OFFLINE_DOC} exists")
    missing_states = [s for s in REQUIRED_SYNC_STATES
                      if s.lower() not in offline.lower()]
    for s in missing_states:
        rep.info(f"{OFFLINE_DOC} does not distinguish '{s}'")
    rep.check(
        not missing_states,
        f"all {len(REQUIRED_SYNC_STATES)} sync states are distinguished "
        f"({len(missing_states)} missing)",
    )
    rep.check(
        "silent" in offline.lower(),
        f"{OFFLINE_DOC} explicitly forbids a silent sync failure",
    )
    rep.check(
        "client_reference" in offline.lower()
        or "clientreference" in offline.lower(),
        f"{OFFLINE_DOC} names the client_reference idempotency key",
    )

    # -- external courier minimum access ------------------------------------
    courier = read(root, COURIER_DOC).lower()
    rep.check(bool(courier), f"{COURIER_DOC} exists")
    rep.check(
        "external courier" in courier or "guest" in courier,
        f"{COURIER_DOC} covers the external courier guest link",
    )
    rep.check(
        bool(re.search(r"(never|not|no ).{0,120}"
                       r"(customer database|other orders|customer history|"
                       r"full customer|entire customer)", courier)),
        "the external courier is explicitly denied the wider customer data set",
    )
    rep.check(
        "usulan rute" in courier or "suggestion" in courier,
        "route ordering is described as a suggestion, not an optimisation",
    )
    route_claims = []
    for line in read(root, COURIER_DOC).splitlines():
        low_line = line.lower()
        if not re.search(r"(optimal route|route optimi[sz]ation|"
                         r"guaranteed (?:delivery|arrival))", low_line):
            continue
        guard = ("no ", "never", "not ", "without", "forbidden", "prohibit",
                 "must not", "any claim", "claim an", "suggestion",
                 "usulan", "is not claimed")
        if not any(g in low_line for g in guard):
            route_claims.append(line.strip()[:110])
    for c in route_claims:
        rep.info(c)
    rep.check(
        not route_claims,
        "no route optimisation or delivery guarantee is claimed",
    )

    # -- unclaimed laundry: the ladder and the absolute prohibition --------
    unclaimed = read(root, UNCLAIMED_DOC)
    rep.check(bool(unclaimed), f"{UNCLAIMED_DOC} exists")
    for stage in ("H+1", "H+3", "H+7", "H+14"):
        rep.check(stage in unclaimed,
                  f"{UNCLAIMED_DOC} documents the {stage} stage")
    low_unclaimed = unclaimed.lower()
    rep.check(
        "first" in low_unclaimed and "ready_for_pickup" in low_unclaimed,
        "ageing is anchored to the FIRST READY_FOR_PICKUP timestamp",
    )
    rep.check(
        bool(re.search(r"(never|not|does not).{0,60}restart", low_unclaimed)),
        "the ageing clock is stated never to restart",
    )
    disposal = re.search(
        r"(never|no |not|forbidden|prohibit)[^.\n]{0,120}"
        r"(dispos|auction|sell|sale|donat|transfer of ownership|"
        r"ownership transfer)", low_unclaimed)
    rep.check(
        bool(disposal),
        "automatic disposal, sale, auction, donation or ownership transfer is "
        "explicitly prohibited",
    )
    rep.check(
        "not assumed active" in low_unclaimed or "tenant-configured" in low_unclaimed,
        "the storage fee is described as optional and not assumed active",
    )

    # -- no real personal data anywhere in the Step 2 corpus ---------------
    leaks: list = []
    for path in markdown_files(root, "docs/design", "docs/ux", "docs/security",
                               "docs/quality"):
        body = path.read_text(encoding="utf-8", errors="replace")
        rel = path.relative_to(root).as_posix()
        for match in REAL_PHONE.finditer(body):
            leaks.append(f"{rel}: {match.group(0)}")
    for leak in leaks:
        rep.info(leak)
    rep.check(
        not leaks,
        "no unmasked real-format Indonesian mobile number appears in the "
        "Step 2 corpus",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
