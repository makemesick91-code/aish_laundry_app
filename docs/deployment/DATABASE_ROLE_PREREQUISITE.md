# Deployment prerequisite — a dedicated, unprivileged application database role

**Status:** `REQUIRED_FOR_FUTURE_DEPLOYMENT` · `NOT_YET_PROVISIONED` ·
`NOT_CLAIMED_AS_CURRENT_CONTROL`

**Raised by:** SEC-12, Step 4 independent review.
**Recorded in:** [DEC-0033](../decisions/DEC-0033-step-04-independent-review-closure.md) §7.

---

## Why this file exists

Step 4 protects `customer_consents` with `ENABLE ALWAYS` triggers that refuse
`UPDATE`, `DELETE` and `TRUNCATE`. Those triggers hold against the application's
own connection, and they hold against `session_replication_role = 'replica'` —
the bypass an independent review used to defeat the first remediation.

They do not, and cannot, hold against a principal that may rewrite the schema.

**In the development environment that principal is the application itself.**
`aish_dev` is a superuser and owns every table it created. So the guarantee
currently rests on the application not choosing to disable its own control —
which is a statement about intent, not a boundary.

This is recorded here rather than buried in a migration comment because it is
the one part of the consent guarantee that Step 4 **cannot** deliver, and a
reader who does not know that will over-read what Step 4 achieved.

---

## What is protected today

At the application database connection boundary, against:

- application `UPDATE` on `customer_consents`;
- application `DELETE`;
- application `TRUNCATE`;
- raw SQL issued through the normal application connection;
- `session_replication_role = 'replica'` bypass;
- `pg_restore`-style trigger disabling **that relies only on the
  replication-role mechanism**.

This is enforced by `ENABLE ALWAYS` triggers raising SQLSTATE `23001`, verified
behaviourally and against `pg_trigger.tgenabled = 'A'`.

## What is NOT claimed

No protection is claimed against a principal that can:

- `ALTER TABLE`;
- `DISABLE TRIGGER`;
- `DROP TRIGGER`;
- `DROP TABLE`;
- rewrite the schema;
- replace the trigger function;
- change ownership;
- execute equivalent privileged DDL.

A trigger cannot defend against the authority that installs triggers. Saying so
plainly is the point: the earlier version of this claim omitted the cheapest
bypass, and a "stated plainly" list that misses one invites the reader to stop
looking.

---

## The prerequisite

Production runtime **must** connect as a dedicated database role that is:

1. **not a superuser**;
2. **not the schema owner**;
3. **not the owner of `customer_consents`** (nor of any table carrying an
   integrity trigger);
4. **unable to disable, drop, or replace triggers** on protected tables;
5. **unable to `ALTER` protected consent tables**;
6. granted only the minimum runtime privileges — `SELECT`/`INSERT` on
   `customer_consents`, and no `UPDATE`, `DELETE` or `TRUNCATE` privilege at all,
   so the trigger becomes the second line of defence rather than the only one;
7. distinct from the role that runs migrations, which necessarily holds DDL
   rights and must therefore not be the role the application holds open.

Point 7 is the structural one. Migrations legitimately need to create and alter
triggers; the request-serving connection never does. Today they are the same
role, and that is what collapses the boundary.

---

## Status discipline

Deployment is **`ABSENT`** and is not authorised by anything in Step 4
(Rule 36, Rule 49). Therefore:

- this role **does not exist** and must never be described as existing;
- it is **not** an implemented runtime control and must not appear in any
  control inventory as one;
- it is **not** an external blocker for Step 4 closure, because Step 4's
  canonical `GO` criteria do not include production deployment. Step 4 is a
  non-deployment step, and the absence of a production role cannot block a step
  that ships no production;
- it **is** a mandatory prerequisite for the step that first deploys, and that
  step may not claim consent immutability as a deployed guarantee until this
  role exists and is verified.

## Verification required at deployment time

Before any environment serving real tenant data is accepted:

1. `SELECT usesuper FROM pg_user WHERE usename = current_user` returns false for
   the application role;
2. `SELECT tableowner FROM pg_tables WHERE tablename = 'customer_consents'`
   returns a role other than the application role;
3. the application role's attempt to `ALTER TABLE customer_consents DISABLE
   TRIGGER ...` is refused;
4. the application role holds no `UPDATE`, `DELETE` or `TRUNCATE` privilege on
   `customer_consents`;
5. the behavioural replica-mode refusal test still passes as that role;
6. all three triggers still report `tgenabled = 'A'`.

Each is a command with an observable result, not a review checkbox.
