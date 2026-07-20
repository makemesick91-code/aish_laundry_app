/// The `/api/v1` paths this Step 3 runtime is permitted to call.
///
/// The list is exhaustive on purpose. Every path here corresponds to a route the
/// backend actually registers for Step 3; there is no path for an order, a
/// payment, a customer, a price, tracking, a pickup, a delivery, a reminder or a
/// subscription, because none of those exist yet and a client constant is how a
/// feature quietly acquires a foothold before its Step.
abstract final class ApiEndpoints {
  // Operational probes.
  static const String health = 'health';
  static const String readiness = 'readiness';

  // Authentication.
  static const String login = 'auth/login';
  static const String logout = 'auth/logout';
  static const String me = 'auth/me';
  static const String passwordResetRequest = 'auth/password-reset/request';
  static const String passwordResetComplete = 'auth/password-reset/complete';

  // Session self-service.
  static const String sessions = 'sessions';
  static const String sessionsRevokeOthers = 'sessions/revoke-others';

  // Tenant and outlet context.
  static const String contextTenants = 'context/tenants';
  static const String contextTenant = 'context/tenant';
  static const String contextOutlets = 'context/outlets';
  static const String contextOutlet = 'context/outlet';

  // Tenancy and authorization.
  static const String membershipsCurrent = 'memberships/current';
  static const String permissions = 'authorization/permissions';
}
