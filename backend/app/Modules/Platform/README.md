# Module — Platform

Bounded context: **Platform Administration** (Rule 17).

## Boundary

Owns **operating the runtime itself**, as distinct from operating a laundry. Platform is the only
module whose concerns are not tenant business concerns.

## In scope

- Operational endpoints — liveness (`/api/v1/health`) and readiness (`/api/v1/readiness`).
  Readiness performs a real round-trip against PostgreSQL and Redis and reports what it actually
  executed (Rule 01).
- Platform-administration access paths, which are **distinct and audited**.
- Support impersonation control, which is time-bound, reason-required, and audited by `Audit`.

## Out of scope

- **Subscription, entitlement, billing, and plan limits.** Those are Step 12. Pricing is a locked
  owner decision (Rule 14) and no plan, limit, or metering exists here.
- Deployment, infrastructure provisioning, and any remote environment. Deployment is `ABSENT`.

## Non-negotiables

1. **Platform administration never works by relaxing tenant scoping for ordinary roles.** It is a
   separate, audited path (Rule 02).
2. **There is no silent tenant access.** Every impersonation session is recorded and is
   unmistakable in the interface (Rule 03, Rule 32).
3. **Operational endpoints never disclose a credential, a connection string, a host, or a
   configuration value.** A readiness failure names the dependency, never the secret.
4. **An operational endpoint answering is not evidence that any feature exists.** All product
   features remain `NOT IMPLEMENTED` (Rule 01).

## Status

`NOT IMPLEMENTED` as a module — the health and readiness endpoints are implemented in
`app/Http/Controllers/HealthController.php` and are operational only. They are not a product
feature.
