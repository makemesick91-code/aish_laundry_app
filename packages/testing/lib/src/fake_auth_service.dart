import 'dart:async';

import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';

import 'api_fixtures.dart';

/// A scriptable [AuthService] for widget and unit tests.
///
/// Its value is that it can produce states a real server makes awkward to
/// provoke — a revoked device, a suspended membership, an expired session — so
/// those screens are genuinely exercised rather than assumed to work.
///
/// It also models the two rules a real implementation must honour, so a test
/// against this fake is a meaningful test of the caller:
///
///   * Tenant selection is EXPLICIT. There is no auto-selection even when the
///     user has exactly one tenant.
///   * Outlet listing is TENANT-SCOPED. Asking for outlets returns only those
///     of the active tenant, and asking with no tenant context returns a
///     denial rather than everything.
final class FakeAuthService implements AuthService {
  FakeAuthService({AuthState initial = const AuthState.unauthenticated()})
    : _current = initial;

  final StreamController<AuthState> _controller =
      StreamController<AuthState>.broadcast();

  AuthState _current;

  /// State to adopt on the next [signIn]. Defaults to a signed-in session with
  /// no tenant selected, which is the honest post-login state.
  AuthState? nextSignInState;

  /// State to adopt on the next [restoreSession]. Defaults to unauthenticated,
  /// because a restoration that has not been scripted must not invent a session.
  AuthState? nextRestoreState;

  /// Outlets the "server" permits in the active tenant.
  List<Outlet> outletsForActiveTenant = const <Outlet>[
    ApiFixtures.outletMelatiPusat,
    ApiFixtures.outletMelatiCabang,
  ];

  /// Set to make [selectTenant] refuse, as a server would for a tenant the user
  /// does not belong to.
  bool denyTenantSelection = false;

  /// Recorded calls, so a test can assert that logout actually happened rather
  /// than that a button existed.
  final List<String> calls = <String>[];

  /// Whether [signOut] cleared local state. Set by the caller's clear hook.
  bool didClearCredentials = false;

  /// Invoked by [signOut]. A surface wires its credential clearing here.
  Future<void> Function()? onClearCredentials;

  @override
  Stream<AuthState> get states => _controller.stream;

  @override
  AuthState get current => _current;

  AuthState _emit(AuthState state) {
    _current = state;
    _controller.add(state);
    return state;
  }

  /// Force a state directly. Used to drive a screen into a state under test.
  AuthState emitForTest(AuthState state) => _emit(state);

  @override
  Future<AuthState> restoreSession() async {
    calls.add('restoreSession');
    _emit(const AuthState.authenticating());
    return _emit(nextRestoreState ?? const AuthState.unauthenticated());
  }

  @override
  Future<AuthState> signIn({
    required String identifier,
    required String password,
  }) async {
    // The identifier is recorded; the password is NOT, not even in a test
    // double. A fake that records credentials teaches the pattern, and the
    // pattern gets copied into something that ships.
    calls.add('signIn:$identifier');
    _emit(const AuthState.authenticating());
    return _emit(
      nextSignInState ??
          AuthState.authenticated(ApiFixtures.signedInNoTenant()),
    );
  }

  @override
  Future<AuthState> selectTenant(String tenantId) async {
    calls.add('selectTenant:$tenantId');
    if (denyTenantSelection) {
      return _emit(const AuthState.accessDenied());
    }
    final session = _current.session;
    if (session == null) {
      return _emit(const AuthState.unauthenticated());
    }
    final tenant = session.availableTenants
        .where((candidate) => candidate.id == tenantId)
        .firstOrNull;
    if (tenant == null) {
      // Indistinguishable from "does not exist", exactly as the server behaves.
      return _emit(const AuthState.accessDenied());
    }
    return _emit(
      AuthState.authenticated(
        // Starts from a CLEARED context so nothing survives the switch.
        session.withoutTenantContext().copyWith(
          activeTenant: tenant,
          activeMembership: ApiFixtures.membershipOwnerMelati,
          permissions: ApiFixtures.ownerPermissions(tenant.id),
        ),
      ),
    );
  }

  @override
  Future<AuthState> selectOutlet(String outletId) async {
    calls.add('selectOutlet:$outletId');
    final session = _current.session;
    if (session == null || !session.hasTenantContext) {
      return _emit(const AuthState.accessDenied());
    }
    final outlet = outletsForActiveTenant
        .where(
          (candidate) =>
              candidate.id == outletId &&
              candidate.tenantId == session.activeTenant!.id &&
              candidate.isActive,
        )
        .firstOrNull;
    if (outlet == null) {
      return _emit(const AuthState.accessDenied());
    }
    return _emit(
      AuthState.authenticated(session.copyWith(activeOutlet: outlet)),
    );
  }

  @override
  Future<Result<List<Outlet>>> authorizedOutlets() async {
    calls.add('authorizedOutlets');
    final session = _current.session;
    if (session == null || !session.hasTenantContext) {
      return const Result<List<Outlet>>.err(
        Failure(
          kind: FailureKind.authorization,
          message: 'No tenant context.',
          code: 'TENANT_ACCESS_DENIED',
        ),
      );
    }
    final tenantId = session.activeTenant!.id;
    return Result<List<Outlet>>.ok(
      outletsForActiveTenant
          .where((outlet) => outlet.tenantId == tenantId)
          .toList(growable: false),
    );
  }

  @override
  Future<AuthState> signOut() async {
    calls.add('signOut');
    await onClearCredentials?.call();
    didClearCredentials = true;
    return _emit(const AuthState.loggedOut());
  }

  Future<void> dispose() => _controller.close();
}
