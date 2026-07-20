# Step 3 — Graphify Relationship Summary

**Tool:** Graphify **0.8.35** (matches the version recorded at Step 2)
**Command:** `graphify update . --no-cluster`
**Captured at:** `5241c0fddc81fccab621a48960d84bb0ef64e0e7`

---

## 1. What was extracted

| Metric | Value |
|---|---|
| Files processed | 9,218 |
| Nodes | 83,857 |
| Edges | 134,475 |
| Output | `graphify-out/` (171 MB, **git-ignored, not committed**) |

**These counts are NOT comparable to Step 2's 1,190 nodes / 2,493 links.** Step 2's graph covered a
documentation-only corpus. This extraction walks the full runtime tree, and the overwhelming majority
of nodes come from `backend/vendor/` — Laravel and its transitive dependencies — not from Aish
Laundry source. Presenting 83,857 as growth over 1,190 would be a meaningless comparison.

## 2. What Graphify DID establish

- The Step 3 runtime corpus is **extractable and internally connected**: the backend modules, models,
  and the Dart workspace all resolve into one graph without orphaned islands at the module level.
- Aish Laundry domain nodes are present and correctly located, e.g.
  `Membership → backend/app/Modules/Tenancy/Models/Membership.php:34`,
  `DeviceSession → backend/app/Modules/Tenancy/Models/DeviceSession.php:35`,
  `permissionregistry`.
- No extraction error or parse failure was reported across 9,218 files.

## 3. What Graphify did NOT establish — stated plainly

The Phase D brief asked for graph-derived assertions such as *zero protected routes without
authentication*, *zero tenant-owned models without ownership controls*, *zero permissions without
enforcement*, and *zero policies without tests*.

**Graphify did not independently establish those, and this summary does not claim it did.**

Two honest reasons:

1. **Vendor dominance.** Traversals seeded on terms like "Authentication" or "Middleware" return
   `backend/vendor/laravel/framework/...` nodes ahead of application nodes. A "zero unguarded routes"
   claim derived from that traversal would be measuring Laravel's own source, not this application's.
2. **No semantic clustering.** The run used `--no-cluster` and no LLM backend, so communities are
   unlabelled. Relationship *classification* — which is what an orphan-detection claim depends on —
   was therefore not performed.

Forcing a number out of this graph would produce a confident figure with nothing behind it. That is
precisely the failure mode the Step 3 evidence discipline exists to prevent.

## 4. How those properties ARE verified

Every relationship the brief lists is enforced by an executable gate, and each is stronger evidence
than a graph traversal because each *fails the build*:

| Property | Actually verified by |
|---|---|
| Tenant routes enforce tenant context | `TenantContext` suite; forged-header and forged-route cases in the tenant-isolation matrix |
| Tenant-owned models carry ownership | composite foreign keys asserted at the database: cross-tenant inserts rejected with `SQLSTATE 23503` and the named constraint |
| Permissions are enforced, not merely declared | RBAC matrix **generated from `PermissionRegistry`**, asserting allowed and denied sets by set equality across all 9 tenant roles |
| Policies have tests | RBAC matrix layer 2 is data-provided from the registry, so a policy without coverage cannot pass silently |
| Session operations have ownership/revocation tests | `SessionManagementTest`, plus adversarial cases D08–D18 |
| Cache paths carry a tenant namespace | `RedisTenantPartitioning`, including a case run against **real Redis** |
| Auth endpoints are throttled | adversarial case D03 (`RATE_LIMITED`) |
| No Step 4+ implementation exists | `assert-schema-scope.php` reads `pg_tables`; `validate-runtime-scope.py` structural detection |
| Requirements/decisions own their artefacts | `validate-rules-traceability.py`, `validate-required-files.py` |

## 5. Honest status

**GRAPHIFY EXTRACTION COMPLETED — RELATIONSHIP-ORPHAN ANALYSIS NOT PERFORMED.**

The corpus extracts cleanly and the runtime is connected. The zero-orphan and zero-unguarded-route
targets are **not** claimed from this run. Delivering that analysis meaningfully would require
excluding `vendor/` from extraction and running clustering with a semantic backend, which is recorded
here as an open limitation rather than quietly dropped.

Raw graph artefacts are **not committed**: `graphify-out/` is 171 MB and git-ignored. Only this
sanitised summary is tracked.
