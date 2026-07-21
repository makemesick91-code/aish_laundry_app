import 'package:aish_auth/aish_auth.dart';
import 'package:flutter/foundation.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

/// TOKEN PERSISTENCE ACROSS A REAL APPLICATION RESTART.
///
/// Run this file TWICE. Each `flutter test` invocation launches, drives and
/// tears down a real application process on the device, so the second run is a
/// genuine restart and the only thing that can carry a session across it is the
/// Android Keystore.
///
/// It must be the SAME file both times. An earlier attempt used two different
/// files and failed for a reason that had nothing to do with the product:
/// Flutter uninstalls before installing a CHANGED apk, and an uninstall wipes
/// app data — including `EncryptedSharedPreferences`. A bare keystore marker
/// carrying no session failed that way too, which is what identified it as a
/// harness artefact rather than a defect.
///
/// Run 1 finds nothing, signs in, and leaves a credential behind.
/// Run 2 finds run 1's credential and restores WITHOUT signing in.
const String kBaseUrl = String.fromEnvironment(
  'AISH_E2E_BASE_URL',
  defaultValue: 'http://127.0.0.1:8000/api/v1',
);
const String kIdentifier = String.fromEnvironment('AISH_E2E_IDENTIFIER');
const String kPassword = String.fromEnvironment('AISH_E2E_PASSWORD');

AuthRuntime productionRuntime() {
  final runtime = AuthRuntime.create(
    environment: Environment.validate(
      environmentName: 'development',
      apiBaseUrl: kBaseUrl,
      appName: 'Uji Restart',
    ).valueOrNull!,
    transport: CredentialTransport.bearerToken,
    store: PlatformSecureCredentialStore(),
    deviceName: 'Aish Laundry Ops',
    platform: 'android',
  );
  addTearDown(runtime.dispose);
  return runtime;
}

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('a session survives an application restart', (_) async {
    // Deliberately NOT wiped: what a previous run left is the whole point.
    final priorUser = await PlatformSecureCredentialStore().read(
      namespace: const StorageNamespace.device(),
      key: CredentialKeys.activeUserId,
    );

    if (priorUser.valueOrNull == null) {
      debugPrint('RESTART-PROOF: run=1 prior=absent action=sign-in');

      final runtime = productionRuntime();
      final state = await runtime.service.signIn(
        identifier: kIdentifier,
        password: kPassword,
      );
      expect(state.isAuthenticated, isTrue, reason: 'run 1 could not sign in');

      final stored = await PlatformSecureCredentialStore().read(
        namespace: StorageNamespace.user(state.session!.user.id),
        key: CredentialKeys.sessionToken,
      );
      expect(stored.valueOrNull, isNotNull);
      debugPrint('RESTART-PROOF: run=1 credential-left-in-keystore=yes');
      return;
    }

    // SECOND RUN. A brand new process, nothing in memory, no sign-in performed.
    debugPrint('RESTART-PROOF: run=2 prior=present action=restore-only');

    final restored = await productionRuntime().service.restoreSession();

    expect(
      restored.isAuthenticated,
      isTrue,
      reason: 'the session did NOT survive the application restart',
    );
    debugPrint('RESTART-PROOF: run=2 restored-without-sign-in=yes');

    // And logout still clears it for good, from a restored session.
    final userId = restored.session!.user.id;
    await productionRuntime().service.signOut();
    final after = await PlatformSecureCredentialStore().read(
      namespace: StorageNamespace.user(userId),
      key: CredentialKeys.sessionToken,
    );
    expect(after.valueOrNull, isNull);
    debugPrint('RESTART-PROOF: run=2 logout-cleared-keystore=yes');
  });
}
