import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:test/test.dart';

const User user = User(id: 'usr_1', displayName: 'Bu Rina (fiktif)');

void main() {
  group('AuthState — the ten required states exist and are distinct', () {
    final states = <String, AuthState>{
      'unauthenticated': const AuthState.unauthenticated(),
      'authenticating': const AuthState.authenticating(),
      'authenticated': const AuthState.authenticated(
        SessionState(user: user, availableTenants: <Tenant>[]),
      ),
      'sessionExpired': const AuthState.sessionExpired(),
      'sessionRevoked': const AuthState.sessionRevoked(),
      'deviceRevoked': const AuthState.deviceRevoked(),
      'membershipSuspended': const AuthState.membershipSuspended(),
      'membershipRevoked': const AuthState.membershipRevoked(),
      'accessDenied': const AuthState.accessDenied(),
      'loggedOut': const AuthState.loggedOut(),
    };

    test('all ten are present', () {
      expect(states.length, 10);
    });

    test('no two states are equal to each other', () {
      // Collapsing two states would produce one dishonest message for two
      // different situations.
      final entries = states.entries.toList();
      for (var i = 0; i < entries.length; i++) {
        for (var j = i + 1; j < entries.length; j++) {
          expect(
            entries[i].value,
            isNot(equals(entries[j].value)),
            reason: '${entries[i].key} must differ from ${entries[j].key}',
          );
        }
      }
    });

    test('only authenticated reports isAuthenticated', () {
      states.forEach((name, state) {
        expect(state.isAuthenticated, name == 'authenticated', reason: name);
      });
    });

    test(
      'isTerminatedSession identifies exactly the session-ending states',
      () {
        const terminated = <String>{
          'sessionExpired',
          'sessionRevoked',
          'deviceRevoked',
          'membershipSuspended',
          'membershipRevoked',
        };
        states.forEach((name, state) {
          expect(
            state.isTerminatedSession,
            terminated.contains(name),
            reason: name,
          );
        });
      },
    );

    test('accessDenied does NOT end the session', () {
      // A denial for one action must not sign a user out of everything.
      expect(const AuthState.accessDenied().isTerminatedSession, isFalse);
    });
  });

  group('authStateFor — consequence to state mapping', () {
    void expectState(ClientErrorConsequence consequence, Matcher matcher) {
      expect(authStateFor(consequence), matcher);
    }

    test('session-ending consequences map to their distinct states', () {
      expectState(ClientErrorConsequence.sessionExpired, isA<SessionExpired>());
      expectState(ClientErrorConsequence.sessionRevoked, isA<SessionRevoked>());
      expectState(ClientErrorConsequence.deviceRevoked, isA<DeviceRevoked>());
      expectState(
        ClientErrorConsequence.membershipSuspended,
        isA<MembershipSuspended>(),
      );
      expectState(
        ClientErrorConsequence.membershipRevoked,
        isA<MembershipRevoked>(),
      );
      expectState(
        ClientErrorConsequence.requiresAuthentication,
        isA<Unauthenticated>(),
      );
    });

    test('denial consequences map to accessDenied', () {
      expectState(ClientErrorConsequence.accessDenied, isA<AccessDenied>());
      expectState(
        ClientErrorConsequence.contextAccessDenied,
        isA<AccessDenied>(),
      );
    });

    test('a CSRF failure is treated as an expiry', () {
      expectState(ClientErrorConsequence.csrfFailed, isA<SessionExpired>());
    });

    test('TRANSIENT consequences never end a live session', () {
      // A rate limit or a network blip must not discard the user's working
      // context. The caller passes the live state as the fallback and gets it
      // back unchanged.
      const live = AuthState.authenticated(
        SessionState(user: user, availableTenants: <Tenant>[]),
      );
      for (final consequence in <ClientErrorConsequence>[
        ClientErrorConsequence.rateLimited,
        ClientErrorConsequence.networkUnavailable,
        ClientErrorConsequence.serviceUnavailable,
        ClientErrorConsequence.validationFailed,
        ClientErrorConsequence.recoverableUnknown,
      ]) {
        expect(
          authStateFor(consequence, transientFallback: live),
          same(live),
          reason: '$consequence must not end the session',
        );
      }
    });

    test('an unknown server code does not end a session', () {
      // End to end: unrecognised wire code -> recoverableUnknown -> session
      // kept. This is the whole fail-safe chain in one assertion.
      final (failure, consequence) = ApiErrorMapper.fromEnvelope(
        statusCode: 403,
        body: <String, Object?>{
          'error': <String, Object?>{'code': 'BRAND_NEW_CODE'},
        },
      );
      const live = AuthState.authenticated(
        SessionState(user: user, availableTenants: <Tenant>[]),
      );
      expect(failure.kind, FailureKind.unexpected);
      expect(authStateFor(consequence, transientFallback: live), same(live));
    });
  });

  group('AuthState cause', () {
    test('carries the originating failure where there is one', () {
      const failure = Failure(
        kind: FailureKind.authentication,
        message: 'x',
        code: 'SESSION_REVOKED',
      );
      const state = AuthState.sessionRevoked(cause: failure);
      expect(state.cause, failure);
    });

    test('is null for states that did not come from a failure', () {
      expect(const AuthState.unauthenticated().cause, isNull);
      expect(const AuthState.loggedOut().cause, isNull);
    });
  });
}
