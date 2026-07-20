# Module — Organization

Bounded context: **Tenant and Organization** — the structure beneath the tenant boundary
(Rule 17).

## Boundary

Owns the commercial and physical structure **inside** a tenant:

```
Tenant/Organization -> Laundry Brand -> Outlet
```

`Tenancy` owns the boundary itself; `Organization` owns what lives beneath it.

## In scope

- Laundry brands (`laundry_brands`) — a tenant may have multiple brands.
- Outlets (`outlets`) — a brand may have multiple outlets.
- Outlet-level attributes that later Steps depend on, notably **outlet local time**, which governs
  quiet hours (Rule 08) and aging display (Rule 10).

## Out of scope

- **Master data of the laundry business** — services, price lists, and anything a tenant sells.
  Those are Step 4 and creating them here is a scope breach.
- Staff assignment and scheduling.
- Anything that takes money.

## Non-negotiables

1. **Every row carries `tenant_id`**, including `outlets` — even though an outlet already reaches a
   tenant through its brand. Carrying `tenant_id` directly makes a cross-tenant brand/outlet
   pairing **structurally impossible** rather than merely discouraged, and lets every query scope
   on one column without a join.
2. **A brand belongs to exactly one tenant; an outlet belongs to exactly one brand and one tenant.**
3. **Outlet and tenant context must be visible** on every authenticated screen that later Steps
   build (Rule 28, Rule 32) — the data model must therefore always be able to answer "which tenant,
   which outlet" without inference.

## Status

`NOT IMPLEMENTED` — directory boundary only.
