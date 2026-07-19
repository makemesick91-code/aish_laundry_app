# Step 2 — Adversarial Validator Harness Results

| Field | Value |
|---|---|
| Exact commit SHA | `1af62cb60d2559d2a235ccaa8da91026c9381233` |
| Branch | `feature/step-02-design-system-ux-foundation` |
| Timestamp | 2026-07-19 21:50:34 WIB |
| Environment | Linux 7.0.0-27-generic, Python 3.14.4, bash 5.3.9(1)-release |
| Sanitisation | Performed. No secret, token, credential, OTP or personal datum appears in this capture. Output is reproduced in full and unedited. |

> A validator that has only ever seen correct input is untested. This harness
> breaks the repository in 30 specific ways and requires each break to be
> caught. It restores the tree after every mutation and verifies, against a
> snapshot taken before the first mutation, that nothing was left behind.
>
> **What this proves:** each validator turns red on the specific defect it
> targets.
> **What it does not prove:** that the validator set is complete, or that any
> application behaves correctly — there is no application.

## Command

```bash
bash scripts/test-step-02-validators.sh
```

## Captured output

```text
########################################################################
# AISH LAUNDRY APP — STEP 2 ADVERSARIAL VALIDATOR HARNESS
# repo root : /home/fikri/Projects/aish_laundry
# git sha   : 1af62cb60d2559d2a235ccaa8da91026c9381233
# started   : 2026-07-19T14:50:13Z
########################################################################

Each line below breaks the repository on purpose and requires the
validator to turn RED. CAUGHT is the desired result.

CAUGHT   1  duplicate token name                                    (exit 1)
CAUGHT   2  unresolved token reference                              (exit 1)
CAUGHT   3  circular token reference                                (exit 1)
CAUGHT   4  colour contrast below its declared target               (exit 1)
CAUGHT   5  focus-never-removed guarantee deleted                   (exit 1)
CAUGHT   6  screen error state removed                              (exit 1)
CAUGHT   7  touch target reduced below 48dp                         (exit 1)
CAUGHT   8  screen with no requirement reference                    (exit 1)
CAUGHT   9  requirement removed from the classification             (exit 1)
CAUGHT   10 security requirements dropped from the mapping          (exit 1)
CAUGHT   11 journey with no recovery path                           (exit 1)
CAUGHT   12 silent tenant switch permitted                          (exit 1)
CAUGHT   13 unmasked customer phone number                          (exit 1)
CAUGHT   14 tracking portal full-address prohibition removed        (exit 1)
CAUGHT   15 payment treated as final from client state              (exit 1)
CAUGHT   16 H+7 reminder stage removed                              (exit 1)
CAUGHT   17 automatic disposal path introduced                      (exit 1)
CAUGHT   18 floating-point money introduced                         (exit 1)
CAUGHT   19 SVG with an embedded script                             (exit 1)
CAUGHT   20 SVG referencing remote content                          (exit 1)
CAUGHT   21 secret committed to a PUBLIC repository                 (exit 1)
CAUGHT   22 personal data committed                                 (exit 1)
CAUGHT   23 pubspec.yaml introduced (Flutter runtime)               (exit 1)
CAUGHT   24 composer.json introduced (backend runtime)              (exit 1)
CAUGHT   25 Step 3 advanced to IN PROGRESS                          (exit 1)
CAUGHT   26 wireframe claimed as implemented                        (exit 1)
CAUGHT   27 dark mode claimed available                             (exit 1)
CAUGHT   28 placeholder wordmark claimed as the final logo          (exit 1)
CAUGHT   29 component accessibility contract removed                (exit 1)
CAUGHT   30 threat finding with no UX mitigation                    (exit 1)

------------------------------------------------------------------------
Working tree identical to the pre-harness snapshot — every mutation
was reverted and nothing was left behind.

########################################################################
# ADVERSARIAL HARNESS SUMMARY
########################################################################
MUTATIONS CAUGHT : 30
MUTATIONS MISSED : 0

All 30 mutations were caught. Note what this does and does not prove: it
proves each validator turns red on the specific defect it targets. It does
not prove the validators are complete, and it is not a test of any
application, because no application exists.
ADVERSARIAL HARNESS: PASS
```
