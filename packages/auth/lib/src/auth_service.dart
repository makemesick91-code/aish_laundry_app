import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';

import 'auth_state.dart';

/// What a surface may ask of authentication.
///
/// An interface rather than a concrete class so a widget test can drive every
/// state — including the ones that are hard to provoke against a real server,
/// such as a revoked device — without a network.
abstract interface class AuthService {
  /// The current state, and every subsequent state.
  Stream<AuthState> get states;

  /// The state right now.
  AuthState get current;

  /// Attempt to restore a session from whatever credential the surface holds.
  ///
  /// Returns the resulting state. Restoration is ALWAYS server-verified: the
  /// presence of a stored token proves only that a token is stored, never that
  /// it still works. A client that trusted its own storage would show an
  /// authenticated shell to a user whose access was revoked yesterday.
  Future<AuthState> restoreSession();

  /// Sign in with an identifier and a password.
  Future<AuthState> signIn({
    required String identifier,
    required String password,
  });

  /// Select the tenant to act in. Explicit, never inferred.
  Future<AuthState> selectTenant(String tenantId);

  /// Select the outlet to act in, within the active tenant.
  Future<AuthState> selectOutlet(String outletId);

  /// List the outlets the server permits in the active tenant.
  Future<Result<List<Outlet>>> authorizedOutlets();

  /// Sign out, clearing every local credential and every tenant-scoped cache.
  Future<AuthState> signOut();
}

/// Translates a server consequence into the state a surface should adopt.
///
/// Kept as a free function so it is testable in isolation and so the mapping
/// lives in exactly one place. A second copy of this switch, drifting, is how
/// one surface starts logging users out on a rate limit.
///
/// [transientFallback] is the state to adopt for a consequence that does NOT
/// end a session — a rate limit, a flaky connection, an unknown code. The caller
/// supplies it because only the caller knows what was true before: during
/// restoration nothing was established, so `unauthenticated` is right; during a
/// live session the existing `authenticated` state must be KEPT, because ending
/// a session over a network blip discards the user's working context to punish
/// them for a problem that was never theirs.
AuthState authStateFor(
  ClientErrorConsequence consequence, {
  Failure? cause,
  String? tenantName,
  AuthState transientFallback = const AuthState.unauthenticated(),
}) => switch (consequence) {
  ClientErrorConsequence.requiresAuthentication =>
    const AuthState.unauthenticated(),
  ClientErrorConsequence.sessionExpired => AuthState.sessionExpired(
    cause: cause,
  ),
  ClientErrorConsequence.sessionRevoked => AuthState.sessionRevoked(
    cause: cause,
  ),
  ClientErrorConsequence.deviceRevoked => AuthState.deviceRevoked(cause: cause),
  ClientErrorConsequence.membershipSuspended => AuthState.membershipSuspended(
    tenantName: tenantName,
    cause: cause,
  ),
  ClientErrorConsequence.membershipRevoked => AuthState.membershipRevoked(
    tenantName: tenantName,
    cause: cause,
  ),
  ClientErrorConsequence.contextAccessDenied ||
  ClientErrorConsequence.accessDenied => AuthState.accessDenied(cause: cause),
  // A CSRF failure means the browser session is no longer usable, so it is
  // genuinely session-ending on web and is reported as an expiry.
  ClientErrorConsequence.csrfFailed => AuthState.sessionExpired(cause: cause),
  // Everything below is TRANSIENT. None of these ends a session.
  ClientErrorConsequence.validationFailed ||
  ClientErrorConsequence.rateLimited ||
  ClientErrorConsequence.serviceUnavailable ||
  ClientErrorConsequence.networkUnavailable ||
  ClientErrorConsequence.recoverableUnknown => transientFallback,
};
