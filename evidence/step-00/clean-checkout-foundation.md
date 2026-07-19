# Clean-Checkout Verification — Step 0

Clean-checkout verification proves the repository validates from what is actually
committed, not from untracked files that happen to exist in the working
directory. Both checkouts below were made into empty temporary directories with
`git clone`, using no local file.

## Feature branch (before merge)

```bash
git clone --branch feature/step-00-master-source-and-governance \
  https://github.com/makemesick91-code/aish_laundry_app.git <tmp>
cd <tmp>
bash scripts/verify-step-00.sh
git status --short
```

| Item | Result |
|---|---|
| Clone HEAD | `b1bd1549b50f828b009c2241a0836ae23fcf4608` |
| Matches candidate SHA | YES |
| `verify-step-00.sh` | **GATES PASSED: 11 / 11 — PASS**, exit 0 |
| `git status --short` | empty (clean) |

Verified **before** the foundation PR was merged.

## Main branch (after merge)

```bash
git clone --branch main \
  https://github.com/makemesick91-code/aish_laundry_app.git <tmp>
cd <tmp>
bash scripts/verify-step-00.sh
git status --short
```

| Item | Result |
|---|---|
| Clone HEAD | `8494bc8543b9301351da6055337832597f1f2d9f` |
| Matches merge SHA | YES |
| `verify-step-00.sh` | **GATES PASSED: 11 / 11 — PASS**, exit 0 |
| `git status --short` | empty (clean) |

## Gates verified in both clean checkouts

`required-files`, `master-source`, `decisions`, `roadmap`, `status`, `pricing`,
`rules-traceability`, `no-runtime`, `markdown-links`, `secrets`,
`destructive-guard` — 11/11 in each.

The Master Source checksum gate is included, so the clean checkout independently
confirms that `docs/MASTER_SOURCE.md` in the committed tree hashes to
`9b9539d0eefa3c9bdbd403cf99139218b0c8aa17e9473d7b616f59d1513322fe`.

## Notes

- `verify-step-00.sh` resolves the repository root from its own location, so it
  runs correctly from any working directory. This was confirmed by running it
  from `/tmp` during development.
- No untracked or git-ignored file was required for any gate to pass.
  `graphify-out/` is git-ignored and absent from both clones; no gate depends on it.
- Working tree was clean in both cases, so no generated or cache file is missing
  from `.gitignore` and no required file is accidentally untracked.

## Final main verification

The final `main` clean checkout, taken after the post-tag evidence PR was merged,
is recorded in `post-tag-evidence.md` together with the confirmation that the GO
tag still points at the original foundation merge SHA.
