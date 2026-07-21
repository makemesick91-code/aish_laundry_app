import 'dart:async';

import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

/// ON-DEVICE verification of the corrective authentication runtime.
///
/// This runs on a real Android runtime against a real backend. It is the only
/// place `PlatformSecureCredentialStore` is genuinely exercised: under
/// `flutter test` that class has no platform channel and every call fails
/// closed, so the host suite proves the SERVICE and proves nothing about the
/// keystore. Here the channel exists and the Android Keystore actually stores.
///
/// Configuration arrives by `--dart-define`, not from the host environment: the
/// test executes on the device, where the host's environment does not exist. No
/// credential is committed; the development seeder emits a fresh random password
/// per run and the invocation is recorded in the evidence pack.
const String kBaseUrl = String.fromEnvironment(
  'AISH_E2E_BASE_URL',
  // 10.0.2.2 is the loopback of the host as seen from inside the emulator.
  defaultValue: 'http://10.0.2.2:8000/api/v1',
);
const String kIdentifier = String.fromEnvironment('AISH_E2E_IDENTIFIER');
const String kPassword = String.fromEnvironment('AISH_E2E_PASSWORD');
const String kTenantId = String.fromEnvironment('AISH_E2E_TENANT_ID');
const String kForeignTenantId = String.fromEnvironment(
  'AISH_E2E_FOREIGN_TENANT_ID',
);
const String kWrongPassword = 'kata-sandi-salah-fiktif-000000';

Environment environment() => Environment.validate(
  environmentName: 'development',
  apiBaseUrl: kBaseUrl,
  appName: 'Uji Ops On-Device',
).valueOrNull!;

/// The PRODUCTION composition: platform keystore, real client, real service.
AuthRuntime productionRuntime({SecureCredentialStore? store}) {
  final runtime = AuthRuntime.create(
    environment: environment(),
    transport: CredentialTransport.bearerToken,
    store: store ?? PlatformSecureCredentialStore(),
    deviceName: 'Aish Laundry Ops',
    platform: 'android',
  );
  addTearDown(runtime.dispose);
  return runtime;
}

Future<void> wipeDevice() async {
  await PlatformSecureCredentialStore().clearOnLogout();
}

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  setUp(wipeDevice);

  group('platform keystore — the thing host tests cannot reach', () {
    testWidgets('writes and reads back through the platform channel', (
      _,
    ) async {
      final store = PlatformSecureCredentialStore();
      const namespace = StorageNamespace.device();

      final written = await store.write(
        namespace: namespace,
        key: CredentialKeys.deviceIdentifier,
        value: 'dev_fiktif_on_device_0001',
      );
      expect(written.isOk, isTrue, reason: 'keystore write failed');

      final read = await store.read(
        namespace: namespace,
        key: CredentialKeys.deviceIdentifier,
      );
      expect(read.valueOrNull, 'dev_fiktif_on_device_0001');
    });

    testWidgets('a value survives a NEW store instance', (_) async {
      const namespace = StorageNamespace.device();
      await PlatformSecureCredentialStore().write(
        namespace: namespace,
        key: CredentialKeys.deviceIdentifier,
        value: 'dev_fiktif_on_device_0002',
      );

      // A different object, reading the same keystore. Proves persistence is in
      // the platform, not in a Dart field.
      final fresh = await PlatformSecureCredentialStore().read(
        namespace: namespace,
        key: CredentialKeys.deviceIdentifier,
      );
      expect(fresh.valueOrNull, 'dev_fiktif_on_device_0002');
    });

    testWidgets('clearOnLogout removes it from the platform', (_) async {
      const namespace = StorageNamespace.device();
      await PlatformSecureCredentialStore().write(
        namespace: namespace,
        key: CredentialKeys.sessionToken,
        value: 'token_fiktif_akan_dihapus',
      );

      await PlatformSecureCredentialStore().clearOnLogout();

      final after = await PlatformSecureCredentialStore().read(
        namespace: namespace,
        key: CredentialKeys.sessionToken,
      );
      expect(after.valueOrNull, isNull);
    });
  });

  group('authentication against the real backend', () {
    testWidgets('unauthenticated startup resolves, and asks nothing', (
      _,
    ) async {
      final runtime = productionRuntime();

      final state = await runtime.service.restoreSession();

      expect(state, const AuthState.unauthenticated());
    });

    testWidgets('a real sign-in produces a real session', (_) async {
      final runtime = productionRuntime();

      final state = await runtime.service.signIn(
        identifier: kIdentifier,
        password: kPassword,
      );

      expect(state.isAuthenticated, isTrue, reason: 'on-device sign-in failed');
      expect(state.session!.availableTenants, isNotEmpty);
      // Still explicit: no tenant is chosen for the user.
      expect(state.session!.requiresTenantSelection, isTrue);
    });

    testWidgets('invalid credentials are refused and store nothing', (_) async {
      final runtime = productionRuntime();

      final state = await runtime.service.signIn(
        identifier: kIdentifier,
        password: kWrongPassword,
      );

      expect(state.isAuthenticated, isFalse);
      final stored = await PlatformSecureCredentialStore().read(
        namespace: const StorageNamespace.device(),
        key: CredentialKeys.activeUserId,
      );
      expect(stored.valueOrNull, isNull);
    });

    testWidgets('the token really lands in the Android Keystore', (_) async {
      final runtime = productionRuntime();
      await runtime.service.signIn(
        identifier: kIdentifier,
        password: kPassword,
      );
      final userId = runtime.service.current.session!.user.id;

      // Read it back through a SEPARATE platform store, not through the service.
      final persisted = await PlatformSecureCredentialStore().read(
        namespace: StorageNamespace.user(userId),
        key: CredentialKeys.sessionToken,
      );

      expect(persisted.valueOrNull, isNotNull);
      expect(persisted.valueOrNull, isNotEmpty);
    });

    testWidgets('authenticated startup restores from the keystore', (_) async {
      // Sign in on one runtime...
      final first = productionRuntime();
      await first.service.signIn(identifier: kIdentifier, password: kPassword);
      expect(first.service.current.isAuthenticated, isTrue);

      // ...and restore on a COMPLETELY fresh one, holding nothing in memory.
      final second = productionRuntime();
      final restored = await second.service.restoreSession();

      expect(restored.isAuthenticated, isTrue);
    });

    testWidgets('tenant context reaches a real tenant-scoped endpoint', (
      _,
    ) async {
      final runtime = productionRuntime();
      await runtime.service.signIn(
        identifier: kIdentifier,
        password: kPassword,
      );

      final selected = await runtime.service.selectTenant(kTenantId);
      expect(selected.session!.hasTenantContext, isTrue);

      // The canonical context carries X-Tenant-Id. Nothing test-only is wired
      // here: this is the same SessionCredentials the production ApiClient
      // reads from.
      expect(runtime.credentials.context().tenantId, kTenantId);

      // A genuinely tenant-scoped call, which the backend refuses without the
      // header. Reaching it at all proves the header travelled.
      final outlets = await runtime.service.authorizedOutlets();
      expect(outlets.isOk, isTrue, reason: 'tenant-scoped call was refused');
    });

    testWidgets('a foreign tenant is refused by the real server', (_) async {
      final runtime = productionRuntime();
      await runtime.service.signIn(
        identifier: kIdentifier,
        password: kPassword,
      );

      final state = await runtime.service.selectTenant(kForeignTenantId);

      expect(state, isA<AccessDenied>());
    });

    testWidgets('logout deletes the credential from the platform', (_) async {
      final runtime = productionRuntime();
      await runtime.service.signIn(
        identifier: kIdentifier,
        password: kPassword,
      );
      final userId = runtime.service.current.session!.user.id;

      await runtime.service.signOut();

      final after = await PlatformSecureCredentialStore().read(
        namespace: StorageNamespace.user(userId),
        key: CredentialKeys.sessionToken,
      );
      expect(after.valueOrNull, isNull);
    });

    testWidgets('an unauthorized session is reported, and cleared', (_) async {
      final runtime = productionRuntime();
      await runtime.service.signIn(
        identifier: kIdentifier,
        password: kPassword,
      );
      final userId = runtime.service.current.session!.user.id;
      // Sign out revokes the token server-side; the credential is then replayed
      // to provoke the unauthorized path a stale device would hit.
      final token = await runtime.credentials.token();
      await runtime.service.signOut();

      await PlatformSecureCredentialStore().write(
        namespace: const StorageNamespace.device(),
        key: CredentialKeys.activeUserId,
        value: userId,
      );
      await PlatformSecureCredentialStore().write(
        namespace: StorageNamespace.user(userId),
        key: CredentialKeys.sessionToken,
        value: token!,
      );

      final replayed = await productionRuntime().service.restoreSession();

      expect(replayed.isAuthenticated, isFalse);
      final after = await PlatformSecureCredentialStore().read(
        namespace: StorageNamespace.user(userId),
        key: CredentialKeys.sessionToken,
      );
      expect(after.valueOrNull, isNull, reason: 'dead credential was kept');
    });

    testWidgets(
      'a transient network failure does NOT delete a good credential',
      (_) async {
        // Sign in against the real backend so a genuine token is on the device.
        final signedIn = productionRuntime();
        await signedIn.service.signIn(
          identifier: kIdentifier,
          password: kPassword,
        );
        final userId = signedIn.service.current.session!.user.id;

        // Now restore against an unreachable host — the device losing signal.
        final offline = AuthRuntime.create(
          environment: Environment.validate(
            environmentName: 'development',
            // A loopback port nothing listens on. It must be LOOPBACK:
            // Environment.validate refuses plaintext HTTP to any other host,
            // which is correct and which this test originally tripped over by
            // pointing at a TEST-NET-1 address.
            apiBaseUrl: 'http://127.0.0.1:59999/api/v1',
            appName: 'Uji Ops Offline',
          ).valueOrNull!,
          transport: CredentialTransport.bearerToken,
          store: PlatformSecureCredentialStore(),
        );
        addTearDown(offline.dispose);

        final state = await offline.service.restoreSession();

        // Honest: no session is claimed, because none could be verified.
        expect(state.isAuthenticated, isFalse);
        // But the credential SURVIVES — losing signal is not losing access.
        final kept = await PlatformSecureCredentialStore().read(
          namespace: StorageNamespace.user(userId),
          key: CredentialKeys.sessionToken,
        );
        expect(kept.valueOrNull, isNotNull, reason: 'a blip erased the token');
      },
    );

    testWidgets('startup is bounded when secure storage never answers', (
      _,
    ) async {
      // A store that never completes, standing in for a wedged keystore.
      final runtime = AuthRuntime.create(
        environment: environment(),
        transport: CredentialTransport.bearerToken,
        store: _NeverAnsweringStore(),
      );
      addTearDown(runtime.dispose);

      final stopwatch = Stopwatch()..start();
      final state = await runtime.service.restoreSession();
      stopwatch.stop();

      // Rule 29 hard rule 13: it must RESOLVE, not spin.
      expect(state, const AuthState.unauthenticated());
      expect(
        stopwatch.elapsed,
        lessThan(const Duration(seconds: 30)),
        reason: 'startup did not bound a non-responsive keystore',
      );
    });
  });
}

/// A store whose futures never complete.
final class _NeverAnsweringStore implements SecureCredentialStore {
  @override
  Future<Result<String?>> read({
    required StorageNamespace namespace,
    required String key,
  }) => Completer<Result<String?>>().future;

  @override
  Future<Result<void>> write({
    required StorageNamespace namespace,
    required String key,
    required String value,
  }) => Completer<Result<void>>().future;

  @override
  Future<Result<void>> delete({
    required StorageNamespace namespace,
    required String key,
  }) => Completer<Result<void>>().future;

  @override
  Future<Result<void>> clearNamespace(StorageNamespace namespace) =>
      Completer<Result<void>>().future;

  @override
  Future<Result<void>> clearOnLogout() => Completer<Result<void>>().future;
}
