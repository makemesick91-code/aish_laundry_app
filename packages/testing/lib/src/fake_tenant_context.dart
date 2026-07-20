import 'package:aish_domain/aish_domain.dart';

import 'api_fixtures.dart';

/// Builds tenant contexts for tests, including the ones that must be refused.
///
/// The cross-tenant helpers exist so an isolation test reads as an assertion
/// about behaviour rather than as a pile of literal identifiers, and so a test
/// author cannot accidentally construct a "cross-tenant" case where both sides
/// are actually the same tenant.
abstract final class FakeTenantContext {
  /// A user in exactly one tenant. No switcher is needed.
  static SessionState singleTenant() => SessionState(
    user: ApiFixtures.owner,
    availableTenants: const <Tenant>[ApiFixtures.tenantMelati],
    activeTenant: ApiFixtures.tenantMelati,
    activeMembership: ApiFixtures.membershipOwnerMelati,
    permissions: ApiFixtures.ownerPermissions(ApiFixtures.tenantMelati.id),
  );

  /// A user in two unrelated tenants. A switcher is REQUIRED.
  static SessionState multiTenantUnselected() => SessionState(
    user: ApiFixtures.owner,
    availableTenants: const <Tenant>[
      ApiFixtures.tenantMelati,
      ApiFixtures.tenantKenanga,
    ],
  );

  /// A context in Melati, plus the identifier of a record in Kenanga that must
  /// never be reachable from it.
  static (SessionState, String) crossTenantProbe() =>
      (FakeTenantContext.singleTenant(), ApiFixtures.outletKenanga.id);

  /// A membership that is suspended. Access is withheld and explained.
  static Membership suspendedMembership() => ApiFixtures.membershipSuspended;

  /// A permission set for a tenant the caller is NOT in, so a test can prove
  /// that consulting it across a boundary throws rather than quietly denying.
  static EffectivePermissions foreignPermissions() =>
      ApiFixtures.ownerPermissions(ApiFixtures.tenantKenanga.id);
}
