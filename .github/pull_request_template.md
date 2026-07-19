# Pull Request — Aish Laundry App

## 1. Purpose

<!-- Apa yang diubah dan mengapa. Satu paragraf, tanpa klaim berlebihan. -->

## 2. Canonical scope

- Step: <!-- contoh: Step 0 — Master Source and Governance -->
- Scope statement: <!-- apa yang termasuk DAN apa yang sengaja tidak termasuk -->
- Master Source version referenced: <!-- contoh: 1.0.0 -->

## 3. Files created or changed

<!-- Daftar path. Jangan menyalin isi file. -->

| Path | Created / Changed | Purpose |
| ---- | ----------------- | ------- |
|      |                   |         |

## 4. Explicit no-runtime statement

> This pull request creates **no** application runtime.
> No Flutter workspace, no Laravel backend, no database schema, no migration,
> no authentication implementation, no REST API runtime, no Android or Web UI,
> no application container, and no deployment were created.

- [ ] I confirm the statement above is true for this pull request.
- [ ] `scripts/validate-no-runtime.py` passes.

<!-- If this PR DOES introduce a runtime, delete the statement above, state the
     step number that authorizes it, and link the decision record. -->

## 5. Status matrix

| Item | Status |
| ---- | ------ |
| Step 0 — Master Source and Governance | |
| Steps 1–14 | PLANNED |
| Product features | NOT IMPLEMENTED |
| Backend runtime | ABSENT |
| Flutter workspace | ABSENT |
| Deployment | ABSENT |
| Application CI | NOT APPLICABLE |
| UAT | NOT STARTED |

## 6. Decision records

- Decision records added: <!-- DEC-XXXX, atau "none" -->
- Decision records affected or referenced: <!-- DEC-XXXX, atau "none" -->
- Any superseded decision: <!-- ID + alasan, atau "none" -->

## 7. Rule coverage

<!-- Rule mana di .claude/rules/ yang mengatur perubahan ini. -->

| Rule file | Relevance |
| --------- | --------- |
|           |           |

## 8. Validator result

Command: `bash scripts/verify-step-00.sh`

| Gate | Result |
| ---- | ------ |
| required-files | |
| master-source | |
| decisions | |
| roadmap | |
| status | |
| pricing | |
| rules-traceability | |
| no-runtime | |
| markdown-links | |
| secrets | |
| destructive-guard | |

- Overall: <!-- PASS / FAIL -->

## 9. Security review

- [ ] No secret, token, key, or credential is committed.
- [ ] `scripts/validate-secrets.sh` passes.
- [ ] No `pull_request_target` trigger was introduced.
- [ ] All third-party GitHub Actions are pinned to a full commit SHA.
- [ ] No `curl ... | bash` or equivalent pipe-to-shell install.
- [ ] Workflow permissions remain least-privilege (`contents: read` unless justified).
- Notes: <!-- temuan atau "no findings" -->

## 10. Graphify result

- Run: <!-- ya / tidak / not applicable -->
- Result: <!-- ringkasan, atau alasan not applicable -->

## 11. Limit Saver status

- Status: <!-- contoh: enabled / disabled / not applicable -->
- Notes:

## 12. MCP servers and tools used

<!-- Daftar MCP server / tool yang dipakai untuk menghasilkan PR ini. -->

## 13. Evidence

- Candidate SHA: <!-- commit SHA yang diverifikasi -->
- CI run ID / URL: <!-- ID atau tautan run -->
- Evidence location: <!-- contoh: docs/evidence/... atau tautan run artifact -->

## 14. Risks

<!-- Risiko nyata dari perubahan ini. Tulis "none identified" hanya jika benar. -->

## 15. Rollback

<!-- Langkah rollback konkret, misalnya revert commit SHA atau hapus tag. -->

## 16. Checklist

- [ ] Judul PR mengikuti kebijakan di `docs/GIT_AND_RELEASE_POLICY.md`.
- [ ] Tidak ada klaim implementasi, test, deployment, atau CI yang tidak ada.
- [ ] Semua tautan markdown relatif menunjuk file yang benar-benar ada.
- [ ] Angka harga sesuai `docs/MASTER_SOURCE.md` tanpa perbedaan.
- [ ] `docs/STATUS.md` masih konsisten dengan kenyataan repositori.
- [ ] `docs/CHANGELOG.md` diperbarui bila perlu.
- [ ] `docs/MASTER_SOURCE.sha256` cocok dengan `docs/MASTER_SOURCE.md`.
- [ ] Decision record dibuat untuk setiap keputusan yang mengikat.
- [ ] Tidak ada perubahan pada file di luar cakupan step ini.

## 17. Roadmap statement

> Steps 1 through 14 remain **PLANNED**. This pull request does not start,
> partially implement, or complete any of them.

- [ ] I confirm the statement above is true for this pull request.
