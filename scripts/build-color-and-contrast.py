#!/usr/bin/env python3
"""Generate docs/design/COLOR_AND_CONTRAST.md from the design tokens.

The colour system is generated rather than hand-written for one reason: a
hand-written colour document drifts from the tokens, and when it drifts the
contrast figures in it become fiction. Every hex, every RGB triple and every
ratio below is read or computed from `docs/design/tokens/`, so the document
cannot disagree with the system it describes.

`scripts/validate-color-contrast.py` independently recomputes every ratio, and
it fails if any design document cites a hex value that is not a token.

Standard library only. Step 2 is documentation only — no runtime renders any of
these colours and the design token system is NOT IMPLEMENTED.
"""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from _common import repo_root  # noqa: E402
from _step02 import contrast_ratio, load_tokens, resolve_token  # noqa: E402

OUT = "docs/design/COLOR_AND_CONTRAST.md"

PAGE = "#FFFFFF"
RAISED = "#F7F8FA"

TARGET_LABEL = {
    "normal-text-4.5": "normal text — 4.5:1",
    "large-text-3.0": "large text — 3:1",
    "interactive-boundary-3.0": "interactive boundary — 3:1",
    "decorative-exempt": "decorative — exempt",
    "inactive-exempt": "inactive component — exempt (SC 1.4.3)",
    "background-only": "background only",
}

RAMP_ORDER = ["blue", "gold", "neutral", "green", "amber", "red", "teal",
              "violet"]

RAMP_PURPOSE = {
    "blue": "The brand spine. `color.blue.700` is the canonical primary.",
    "gold": "The restrained brand accent. Only `color.gold.600` may carry text.",
    "neutral": "Surfaces, text, borders and dividers.",
    "green": "Success semantics only.",
    "amber": "Warning semantics only — never gold.",
    "red": "Danger and destructive semantics only.",
    "teal": "Synchronisation-in-progress semantics only.",
    "violet": "Conflict semantics only.",
}


def main() -> int:
    root = repo_root()
    tokens, _origin, errors = load_tokens(root)
    if errors:
        for e in errors:
            print("ERROR:", e, file=sys.stderr)
        return 1

    def hexof(name: str) -> str:
        value, err = resolve_token(name, tokens)
        return value if not err and isinstance(value, str) else "?"

    L = [
        "# Colour and Contrast",
        "",
        "> **Step 2 — Design System and UX Foundation. Documentation only.**",
        "> No runtime renders these colours. The design token system is",
        "> `NOT IMPLEMENTED` and the Flutter workspace is `ABSENT`.",
        ">",
        "> **This file is generated** by `scripts/build-color-and-contrast.py`",
        "> from [`tokens/`](tokens/). Every hex, RGB triple and contrast ratio",
        "> below is read or computed from the token files, so this document",
        "> cannot drift from the system it describes. Do not hand-edit it —",
        "> edit the tokens and regenerate.",
        "",
        "---",
        "",
        "## 1. How contrast is established here",
        "",
        "Every ratio in this document is **computed** from the token's own hex",
        "value using the WCAG 2.2 relative-luminance formula. None is copied",
        "from a design tool and none is estimated.",
        "",
        "`scripts/validate-color-contrast.py` recomputes all of them on every",
        "commit and fails if a recorded figure and a computed figure disagree.",
        "A hand-edited contrast figure is falsified evidence under",
        "[Rule 01](../../.claude/rules/01-status-and-evidence.md), not a typo.",
        "",
        "The accessibility position is",
        "**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**.",
        "Contrast is checkable at design time; whether a real screen renders it",
        "correctly is not, and is `NOT STARTED` until Step 13.",
        "",
        "| Target | Requirement |",
        "|---|---|",
        "| `normal-text-4.5` | ≥ 4.5:1 against every surface it is permitted on |",
        "| `large-text-3.0` | ≥ 3:1 |",
        "| `interactive-boundary-3.0` | ≥ 3:1 — inputs, checkboxes, switches, the focus ring |",
        "| `decorative-exempt` | carries no meaning and is never the sole indicator of anything |",
        "| `inactive-exempt` | inactive components only (WCAG 2.2 SC 1.4.3 exemption) |",
        "| `background-only` | never a foreground colour |",
        "",
        "## 2. Status is never conveyed by colour alone",
        "",
        "This is the rule the palette is built around, and it is not advisory.",
        "Every status carries **three redundant signals**:",
        "",
        "1. a semantic colour from §4,",
        "2. a semantic icon from [`tokens/iconography.json`](tokens/iconography.json),",
        "3. a Bahasa Indonesia text label from [`UX_COPY_GLOSSARY.md`](UX_COPY_GLOSSARY.md).",
        "",
        "A shop floor is brightly lit, screens are cheap, and roughly one in",
        "twelve men has a colour vision deficiency. A status that depends on hue",
        "alone is a status that will be misread — and on this product misreading",
        "`SYNCING` as `SYNCED` means a cashier believes a payment was accepted",
        "when the server never saw it.",
        "",
        "`syncing` (teal), `conflict` (violet), `warning` (amber) and `danger`",
        "(red) were chosen to stay distinguishable from one another under the",
        "common forms of colour vision deficiency — but the redundant icon and",
        "label are what actually carry the meaning.",
        "",
        "## 3. Primitive ramps",
        "",
        "Primitives hold values and carry no meaning. **A component never names",
        "a primitive colour.** Ratios are against the page surface "
        f"(`{PAGE}`) and the raised surface (`{RAISED}`).",
        "",
    ]

    for ramp in RAMP_ORDER:
        names = sorted(
            (n for n in tokens
             if n.startswith(f"color.{ramp}.") and tokens[n]["type"] == "color"),
            key=lambda n: int(n.rsplit(".", 1)[1]),
        )
        if not names:
            continue
        L += [
            f"### 3.{RAMP_ORDER.index(ramp) + 1} `{ramp}`",
            "",
            RAMP_PURPOSE.get(ramp, ""),
            "",
            "| Token | Hex | RGB | On page | On raised | Target |",
            "|---|---|---|---:|---:|---|",
        ]
        for n in names:
            body = tokens[n]
            h = str(body["value"])
            L.append(
                f"| `{n}` | `{h}` | `{body.get('rgb', '')}` | "
                f"{contrast_ratio(h, PAGE)}:1 | {contrast_ratio(h, RAISED)}:1 | "
                f"{TARGET_LABEL.get(body.get('contrastTarget'), '')} |"
            )
        L.append("")

    L += [
        "## 4. Semantic colours",
        "",
        "A semantic token binds a meaning to a primitive. **This is the only",
        "layer where the meaning of a colour may be changed**, and it is the",
        "layer a component specification names.",
        "",
        "| Token | Resolves to | Hex | On page | Target | Meaning |",
        "|---|---|---|---:|---|---|",
    ]
    for n in sorted(t for t in tokens if t.startswith("color.semantic.")):
        body = tokens[n]
        ref = str(body["value"]).strip("{}")
        h = hexof(n)
        target = body.get("contrastTarget", "")
        ratio = contrast_ratio(h, PAGE) if h.startswith("#") else 0
        meaning = body.get("intendedUsage", "").split(".")[0]
        L.append(
            f"| `{n}` | `{ref}` | `{h}` | {ratio}:1 | "
            f"{TARGET_LABEL.get(target, target)} | {meaning} |"
        )

    focus = hexof("color.semantic.focus")
    border = hexof("color.semantic.border.interactive")
    gold_dec = hexof("color.gold.400")
    gold_txt = hexof("color.semantic.accent.strong")
    disabled = hexof("color.semantic.disabled")

    L += [
        "",
        "## 5. The focus ring",
        "",
        f"`color.semantic.focus` resolves to `{focus}` and measures "
        f"**{contrast_ratio(focus, PAGE)}:1** against the page surface, "
        f"clearing the 3:1 interactive-boundary target.",
        "",
        "It is rendered as a 2px outline with a 2px offset "
        "(`border.width.focus`, `border.focus.offset`).",
        "",
        "**The focus indicator is never removed, never set to `none`, and never",
        "reduced below 3:1.** Not for aesthetics, not because a design reads",
        "more cleanly without it, not on any surface. A keyboard user who",
        "cannot see where they are cannot use the product at all.",
        "",
        "## 6. Interactive boundaries",
        "",
        f"`color.semantic.border.interactive` resolves to `{border}` and "
        f"measures **{contrast_ratio(border, PAGE)}:1**, clearing the 3:1 "
        f"boundary target (WCAG 2.2 SC 1.4.11).",
        "",
        "`color.semantic.border.subtle` is deliberately **decorative-exempt**:",
        "it draws dividers that carry no meaning. It is never the boundary of",
        "an interactive control, because a control whose edge a user cannot see",
        "is a control they cannot find.",
        "",
        "## 7. The gold constraint",
        "",
        "Gold is a **restrained accent**, and the token set enforces that",
        "structurally rather than asking designers to remember it.",
        "",
        f"- `color.gold.400` (`{gold_dec}`) measures "
        f"{contrast_ratio(gold_dec, PAGE)}:1 and is **decorative-exempt**. It "
        "may fill a small badge or draw a thin rule. It is **never** body text, "
        "**never** the sole indicator of a warning, **never** dominant, and "
        "**never** a meaning-bearing boundary.",
        f"- `color.semantic.accent.strong` (`{gold_txt}`) measures "
        f"{contrast_ratio(gold_txt, PAGE)}:1 and is the **only** gold permitted "
        "to carry text or a meaning-bearing boundary.",
        "",
        "**Warning semantics use amber, never gold.** The two are deliberately",
        "different hues so that \"premium accent\" and \"something needs your",
        "attention\" can never be confused.",
        "",
        "## 8. Disabled and inactive",
        "",
        f"`color.semantic.disabled` resolves to `{disabled}` and measures "
        f"{contrast_ratio(disabled, PAGE)}:1 — below 4.5:1 **by design**. WCAG "
        "2.2 SC 1.4.3 exempts inactive components.",
        "",
        "The exemption is not a licence to be unhelpful: **a disabled control is",
        "never the only signal that something is unavailable.** The reason is",
        "always available to the user, because a greyed-out button with no",
        "explanation is a dead end at a counter with a customer waiting.",
        "",
        "## 9. Theme scope",
        "",
        "The **light theme is the canonical MVP theme** (DEC-0019). Every ratio",
        "in this document is a light-theme ratio.",
        "",
        "**Dark mode is `PLANNED` and `NOT IMPLEMENTED`.** There is no",
        "`semantic-dark.json`, none may be claimed, and a dark theme would need",
        "its own full contrast pass — the ratios here would not carry over.",
        "",
        "## 10. Related",
        "",
        "- [`tokens/README.md`](tokens/README.md) — the token layer model",
        "- [`ACCESSIBILITY.md`](ACCESSIBILITY.md) — the accessibility foundation",
        "- [`UX_COPY_GLOSSARY.md`](UX_COPY_GLOSSARY.md) — the Bahasa Indonesia labels that accompany every status colour",
        "- [Rule 26 — Design Token Governance](../../.claude/rules/26-design-token-governance.md)",
        "- [Rule 27 — Accessibility Foundation](../../.claude/rules/27-accessibility-foundation.md)",
        "",
    ]

    (root / OUT).write_text("\n".join(L) + "\n", encoding="utf-8")
    colours = sum(1 for t in tokens.values() if t["type"] == "color")
    print(f"colour tokens documented: {colours}")
    print(f"written: {OUT}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
