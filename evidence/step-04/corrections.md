# Step 4 — corrections to claims already committed

Claims that were wrong when written, kept here rather than amended away. History
on a pushed branch is not rewritten (Rule 11 hard rule 8), so a correction lives
alongside the original.

---

## 1. Test count in the SEC-04 commit message

**Commit:** `9143620b90974fc96c633b89516f677a0b9a3c0f`
**Claim as written:** "Verified: 411/411 backend tests on PostgreSQL 18.4."
**Actual at that SHA:** **413 passed (3681 assertions).**

**Why it was wrong.** The figure was composed before the final suite run in the
same command, from the count at an earlier intermediate state. The verification
itself was real and it passed; the number quoted was stale by two tests.

**Severity.** Low, and disclosed anyway. The direction is understatement rather
than overstatement, and no status depended on the figure. It is recorded because
a verification claim bound to a SHA is either accurate or it is not, and
"approximately right, in our favour or otherwise" is not a category Rule 01
provides.

**Prevention.** Suite counts are read from captured output at the SHA being
described, not carried forward from an earlier run in the same session. The
Phase H evidence rebuild regenerates every count from executed output.
