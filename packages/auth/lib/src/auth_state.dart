import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:meta/meta.dart';

/// The complete, CLOSED set of authentication states a surface may be in.
///
/// It is a sealed class so that a `switch` over it is exhaustive and the
/// analyser rejects a screen that forgot a state. Rule 29's complaint about
/// interfaces that only render the happy path is answered structurally here:
/// you cannot compile a router that handles `authenticated` and ignores
/// `deviceRevoked`.
///
/// The session-ending states are kept DISTINCT rather than collapsed into one
/// "signed out" state, because the recovery genuinely differs. A user whose
/// session merely expired signs in again; a user whose device was revoked needs
/// to know that somebody did that deliberately; a user whose membership was
/// revoked cannot fix anything by signing in at all. Collapsing them would
/// produce one dishonest message for four different situations.
@immutable
sealed class AuthState {
  const AuthState();

  /// No credible session. The starting state, and the state after logout.
  const factory AuthState.unauthenticated() = Unauthenticated;

  /// A sign-in or a session restoration is in flight.
  const factory AuthState.authenticating() = Authenticating;

  /// A session the server accepted.
  const factory AuthState.authenticated(SessionState session) = Authenticated;

  /// The session's lifetime ran out.
  const factory AuthState.sessionExpired({Failure? cause}) = SessionExpired;

  /// The session was revoked server-side, deliberately.
  const factory AuthState.sessionRevoked({Failure? cause}) = SessionRevoked;

  /// This device's access was revoked. Other devices may be unaffected.
  const factory AuthState.deviceRevoked({Failure? cause}) = DeviceRevoked;

  /// Membership in the active tenant is suspended — recoverable by an admin.
  const factory AuthState.membershipSuspended({
    String? tenantName,
    Failure? cause,
  }) = MembershipSuspended;

  /// Membership in the active tenant is revoked — not recoverable by the user.
  const factory AuthState.membershipRevoked({
    String? tenantName,
    Failure? cause,
  }) = MembershipRevoked;

  /// Authenticated, but the server refused this tenant, outlet, or action.
  ///
  /// Carries NO indication of whether the refused thing exists. Across a tenant
  /// boundary, denial and absence must be indistinguishable (Rule 32).
  const factory AuthState.accessDenied({Failure? cause}) = AccessDenied;

  /// The user signed out deliberately. Distinct from [Unauthenticated] so the
  /// interface can confirm the action rather than looking like a crash.
  const factory AuthState.loggedOut() = LoggedOut;

  /// The live session, when there is one.
  SessionState? get session =>
      this is Authenticated ? (this as Authenticated).sessionState : null;

  /// Whether tenant-scoped work may proceed right now.
  bool get isAuthenticated => this is Authenticated;

  /// Whether this state means the session is finished and sign-in is the way
  /// back. Used by route guards, which must not enumerate states by hand.
  bool get isTerminatedSession => switch (this) {
    SessionExpired() ||
    SessionRevoked() ||
    DeviceRevoked() ||
    MembershipRevoked() ||
    MembershipSuspended() => true,
    Unauthenticated() ||
    Authenticating() ||
    Authenticated() ||
    AccessDenied() ||
    LoggedOut() => false,
  };

  /// The failure that produced this state, when it came from one.
  Failure? get cause => switch (this) {
    SessionExpired(:final cause) => cause,
    SessionRevoked(:final cause) => cause,
    DeviceRevoked(:final cause) => cause,
    MembershipSuspended(:final cause) => cause,
    MembershipRevoked(:final cause) => cause,
    AccessDenied(:final cause) => cause,
    _ => null,
  };
}

@immutable
final class Unauthenticated extends AuthState {
  const Unauthenticated();

  @override
  bool operator ==(Object other) => other is Unauthenticated;

  @override
  int get hashCode => (Unauthenticated).hashCode;

  @override
  String toString() => 'AuthState.unauthenticated';
}

@immutable
final class Authenticating extends AuthState {
  const Authenticating();

  @override
  bool operator ==(Object other) => other is Authenticating;

  @override
  int get hashCode => (Authenticating).hashCode;

  @override
  String toString() => 'AuthState.authenticating';
}

@immutable
final class Authenticated extends AuthState {
  const Authenticated(this.sessionState);

  final SessionState sessionState;

  @override
  bool operator ==(Object other) =>
      other is Authenticated && other.sessionState == sessionState;

  @override
  int get hashCode => Object.hash(Authenticated, sessionState);

  @override
  String toString() => 'AuthState.authenticated(${sessionState.user.id})';
}

@immutable
final class SessionExpired extends AuthState {
  const SessionExpired({this.cause});

  @override
  final Failure? cause;

  @override
  bool operator ==(Object other) => other is SessionExpired;

  @override
  int get hashCode => (SessionExpired).hashCode;

  @override
  String toString() => 'AuthState.sessionExpired';
}

@immutable
final class SessionRevoked extends AuthState {
  const SessionRevoked({this.cause});

  @override
  final Failure? cause;

  @override
  bool operator ==(Object other) => other is SessionRevoked;

  @override
  int get hashCode => (SessionRevoked).hashCode;

  @override
  String toString() => 'AuthState.sessionRevoked';
}

@immutable
final class DeviceRevoked extends AuthState {
  const DeviceRevoked({this.cause});

  @override
  final Failure? cause;

  @override
  bool operator ==(Object other) => other is DeviceRevoked;

  @override
  int get hashCode => (DeviceRevoked).hashCode;

  @override
  String toString() => 'AuthState.deviceRevoked';
}

@immutable
final class MembershipSuspended extends AuthState {
  const MembershipSuspended({this.tenantName, this.cause});

  final String? tenantName;

  @override
  final Failure? cause;

  @override
  bool operator ==(Object other) =>
      other is MembershipSuspended && other.tenantName == tenantName;

  @override
  int get hashCode => Object.hash(MembershipSuspended, tenantName);

  @override
  String toString() => 'AuthState.membershipSuspended';
}

@immutable
final class MembershipRevoked extends AuthState {
  const MembershipRevoked({this.tenantName, this.cause});

  final String? tenantName;

  @override
  final Failure? cause;

  @override
  bool operator ==(Object other) =>
      other is MembershipRevoked && other.tenantName == tenantName;

  @override
  int get hashCode => Object.hash(MembershipRevoked, tenantName);

  @override
  String toString() => 'AuthState.membershipRevoked';
}

@immutable
final class AccessDenied extends AuthState {
  const AccessDenied({this.cause});

  @override
  final Failure? cause;

  @override
  bool operator ==(Object other) => other is AccessDenied;

  @override
  int get hashCode => (AccessDenied).hashCode;

  @override
  String toString() => 'AuthState.accessDenied';
}

@immutable
final class LoggedOut extends AuthState {
  const LoggedOut();

  @override
  bool operator ==(Object other) => other is LoggedOut;

  @override
  int get hashCode => (LoggedOut).hashCode;

  @override
  String toString() => 'AuthState.loggedOut';
}
