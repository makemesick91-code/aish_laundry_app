import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'routing/ops_router.dart';

final Provider<Environment> environmentProvider = Provider<Environment>(
  (ref) => throw UnimplementedError(
    'environmentProvider must be overridden with a validated Environment.',
  ),
);

/// The assembled authentication runtime for this surface.
///
/// Ops Android is a BEARER-TOKEN surface: it runs on a device with a keystore,
/// so its credential is a token held in platform secure storage.
final Provider<AuthRuntime> authRuntimeProvider = Provider<AuthRuntime>((ref) {
  final runtime = AuthRuntime.create(
    environment: ref.watch(environmentProvider),
    transport: CredentialTransport.bearerToken,
    store: PlatformSecureCredentialStore(),
    deviceName: 'Aish Laundry Ops',
    platform: 'android',
  );
  ref.onDispose(runtime.dispose);
  return runtime;
});

/// The one HTTP client for this surface, sharing the session the user signed
/// into. Repositories added later read it rather than building their own.
final Provider<ApiClient> apiClientProvider = Provider<ApiClient>(
  (ref) => ref.watch(authRuntimeProvider).client,
);

/// The production authentication service.
///
/// This previously threw `UnimplementedError` and nothing overrode it outside a
/// test, so every real launch of this application crashed on the first frame
/// that read it. A widget test supplies `FakeAuthService` through this same
/// provider, which is why the suite stayed green while no build could sign in.
final Provider<AuthService> authServiceProvider = Provider<AuthService>(
  (ref) => ref.watch(authRuntimeProvider).service,
);

/// Whether startup session restoration has FINISHED (successfully or not).
///
/// This gate exists because of a real ordering bug: without it the route guard
/// sends an unauthenticated user straight to sign-in on the very first frame,
/// so the startup screen never mounts and session restoration never runs. A
/// returning user with a perfectly valid session would be shown a login form.
///
/// While the gate is closed, every route resolves to the startup screen. It
/// opens exactly once, when restoration has produced an answer.
final Provider<ValueNotifier<bool>> startupGateProvider =
    Provider<ValueNotifier<bool>>((ref) {
      final gate = ValueNotifier<bool>(false);
      ref.onDispose(gate.dispose);
      return gate;
    });

/// Connectivity and queue health, shown persistently in the Ops chrome.
///
/// Rule 29 rule 2 requires that what is pending, what failed and what needs
/// attention is visible AT ALL TIMES in the Ops app. Step 3 has no queue, so
/// this reports connectivity only and reports it honestly — it never claims a
/// synchronisation that did not happen.
final NotifierProvider<SyncHealthNotifier, SyncHealth> syncHealthProvider =
    NotifierProvider<SyncHealthNotifier, SyncHealth>(SyncHealthNotifier.new);

/// Holds the reported connectivity health.
///
/// Step 3 has no queue and no network monitor, so the value only ever changes
/// when something explicitly reports it. It defaults to [SyncHealth.idle] and
/// never fabricates a synchronisation that did not occur.
class SyncHealthNotifier extends Notifier<SyncHealth> {
  @override
  SyncHealth build() => SyncHealth.idle;

  void report(SyncHealth health) => state = health;
}

class OpsApp extends ConsumerWidget {
  const OpsApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final GoRouter router = ref.watch(opsRouterProvider);
    return MaterialApp.router(
      title: 'Aish Laundry Ops',
      theme: AishTheme.light(),
      debugShowCheckedModeBanner: false,
      routerConfig: router,
    );
  }
}
