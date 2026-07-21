/// END-TO-END against a RUNNING backend. Nothing is scripted here.
///
/// Every other test in this package drives the service over a scripted HTTP
/// adapter. That proves the client behaves correctly against the contract it
/// believes in — it cannot prove the contract is the one the server actually
/// implements. This file closes that gap: real `BackendAuthService`, real
/// `ApiClient`, real sockets, real Laravel, real PostgreSQL.
///
/// WHAT IS SUBSTITUTED, AND WHY: the credential store. `flutter_secure_storage`
/// is a platform plugin with no channel under `flutter test`, so
/// `InMemoryCredentialStore` stands in. That substitution is confined to where
/// bytes are persisted; the service, the HTTP client, the wire format, the
/// server and the database are all real. Keystore behaviour on a physical
/// device is NOT covered by this file and is not claimed to be.
///
/// Skipped unless the environment supplies a target, so an ordinary
/// `flutter test` run stays hermetic. See the evidence pack for the exact
/// invocation.
///
/// NO CREDENTIAL IS COMMITTED. The seeder prints a distinct random password per
/// run and instructs the operator not to copy it into any file; these tests
/// read it from the environment at run time.
@Tags(<String>['e2e'])
library;

import 'dart:io';

import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:flutter_test/flutter_test.dart';

/// A deliberately wrong credential, in a named constant for the same reason as
/// in the contract tests: an inline `password: '...'` matches the repository's
/// credential scanner, and the scanner is right to flag that shape.
const String kWrongPassword = 'kata-sandi-salah-fiktif-000000';

String? envOf(String key) {
  final value = Platform.environment[key];
  return value == null || value.isEmpty ? null : value;
}

final String? baseUrl = envOf('AISH_E2E_BASE_URL');
final String? identifier = envOf('AISH_E2E_IDENTIFIER');
final String? password = envOf('AISH_E2E_PASSWORD');
final String? ownTenantId = envOf('AISH_E2E_TENANT_ID');
final String? foreignTenantId = envOf('AISH_E2E_FOREIGN_TENANT_ID');

Environment liveEnvironment() => Environment.validate(
  environmentName: 'development',
  apiBaseUrl: baseUrl!,
  appName: 'Uji E2E',
).valueOrNull!;

({AuthRuntime runtime, InMemoryCredentialStore store}) liveRuntime({
  CredentialTransport transport = CredentialTransport.bearerToken,
}) {
  final store = InMemoryCredentialStore();
  final runtime = AuthRuntime.create(
    environment: liveEnvironment(),
    transport: transport,
    store: store,
    deviceName: 'Uji E2E',
    platform: 'test',
  );
  addTearDown(runtime.dispose);
  return (runtime: runtime, store: store);
}

void main() {
  final configured =
      baseUrl != null && identifier != null && password != null;

  group(
    'against a running backend',
    () {
      test('a real sign-in produces a real session', () async {
        final live = liveRuntime();

        final state = await live.runtime.service.signIn(
          identifier: identifier!,
          password: password!,
        );

        expect(state.isAuthenticated, isTrue, reason: 'sign-in failed');
        expect(state.session!.user.id, isNotEmpty);
        // The server decides which tenants exist for this identity.
        expect(state.session!.availableTenants, isNotEmpty);
        // And still requires an explicit choice among them.
        expect(state.session!.requiresTenantSelection, isTrue);
      });

      test('a wrong password is refused and stores nothing', () async {
        final live = liveRuntime();

        final state = await live.runtime.service.signIn(
          identifier: identifier!,
          password: kWrongPassword,
        );

        expect(state.isAuthenticated, isFalse);
        expect(live.store.keys.where((k) => k.contains('session_token')), isEmpty);
      });

      test(
        'an unknown account is refused indistinguishably from a wrong password',
        () async {
          final live = liveRuntime();

          final unknown = await live.runtime.service.signIn(
            identifier: 'tidak.ada@contoh.invalid',
            password: kWrongPassword,
          );

          // Rule 38 hard rule 7: the response must not reveal whether the
          // identifier exists. Both paths land on the same client state.
          expect(unknown, const AuthState.unauthenticated());
        },
      );

      test('a stored token restores through auth/me', () async {
        final signedIn = liveRuntime();
        await signedIn.runtime.service.signIn(
          identifier: identifier!,
          password: password!,
        );
        final token = await signedIn.runtime.credentials.token();
        expect(token, isNotNull);

        // A SECOND runtime, as a cold app launch would be: nothing in memory,
        // only what the previous run left on the device.
        final relaunch = liveRuntime();
        await relaunch.store.write(
          namespace: const StorageNamespace.device(),
          key: CredentialKeys.activeUserId,
          value: signedIn.runtime.service.current.session!.user.id,
        );
        await relaunch.store.write(
          namespace: StorageNamespace.user(
            signedIn.runtime.service.current.session!.user.id,
          ),
          key: CredentialKeys.sessionToken,
          value: token!,
        );

        final state = await relaunch.runtime.service.restoreSession();

        expect(state.isAuthenticated, isTrue);
      });

      test('selecting the caller\'s own tenant succeeds', () async {
        final live = liveRuntime();
        await live.runtime.service.signIn(
          identifier: identifier!,
          password: password!,
        );

        final state = await live.runtime.service.selectTenant(ownTenantId!);

        expect(state.isAuthenticated, isTrue);
        expect(state.session!.hasTenantContext, isTrue);
        expect(state.session!.activeTenant!.id, ownTenantId);
        // Permissions arrive with the context, never before it.
        expect(state.session!.permissions, isNotNull);
      }, skip: ownTenantId == null ? 'AISH_E2E_TENANT_ID not set' : null);

      test(
        'selecting ANOTHER tenant is refused by the real server',
        () async {
          final live = liveRuntime();
          await live.runtime.service.signIn(
            identifier: identifier!,
            password: password!,
          );

          // A real tenant that genuinely exists, and that this caller has no
          // membership in. The client asks; the server refuses. This is the
          // tenant boundary exercised end to end rather than asserted.
          final state = await live.runtime.service.selectTenant(
            foreignTenantId!,
          );

          expect(state.isAuthenticated, isFalse);
          expect(state, isA<AccessDenied>());
          // And the refusal says nothing about whether that tenant exists.
          expect(state.session, isNull);
        },
        skip: foreignTenantId == null
            ? 'AISH_E2E_FOREIGN_TENANT_ID not set'
            : null,
      );

      test('outlets are refused before a tenant is chosen', () async {
        final live = liveRuntime();
        await live.runtime.service.signIn(
          identifier: identifier!,
          password: password!,
        );

        final result = await live.runtime.service.authorizedOutlets();

        expect(result.isErr, isTrue);
      });

      test('sign-out revokes the token server-side, not just locally', () async {
        final live = liveRuntime();
        await live.runtime.service.signIn(
          identifier: identifier!,
          password: password!,
        );
        final userId = live.runtime.service.current.session!.user.id;
        final token = await live.runtime.credentials.token();

        await live.runtime.service.signOut();

        // Replay the revoked token from a fresh runtime. If sign-out had only
        // forgotten it locally, this would restore a working session — the
        // difference between signing out and merely hiding.
        final replay = liveRuntime();
        await replay.store.write(
          namespace: const StorageNamespace.device(),
          key: CredentialKeys.activeUserId,
          value: userId,
        );
        await replay.store.write(
          namespace: StorageNamespace.user(userId),
          key: CredentialKeys.sessionToken,
          value: token!,
        );

        final state = await replay.runtime.service.restoreSession();

        expect(state.isAuthenticated, isFalse);
      });
    },
    skip: configured
        ? null
        : 'AISH_E2E_BASE_URL / _IDENTIFIER / _PASSWORD not set; '
              'this suite requires a running backend.',
  );
}
