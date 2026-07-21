import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'routing/customer_router.dart';

/// The validated environment. Overridden in `main` and in every widget test, so
/// there is no ambient global and no hidden default.
final Provider<Environment> environmentProvider = Provider<Environment>(
  (ref) => throw UnimplementedError(
    'environmentProvider must be overridden with a validated Environment.',
  ),
);

/// The authentication service. Overridden with a fake in widget tests.
/// The assembled authentication runtime for this surface.
///
/// Customer Android is a BEARER-TOKEN surface: it runs on a device with a
/// keystore, so its credential is a token held in platform secure storage.
final Provider<AuthRuntime> authRuntimeProvider = Provider<AuthRuntime>((ref) {
  final runtime = AuthRuntime.create(
    environment: ref.watch(environmentProvider),
    transport: CredentialTransport.bearerToken,
    deviceName: 'Aish Laundry',
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

/// The current authentication state.
///
/// Seeded from the service's `current` so a test that sets a state before
/// pumping does not have to wait for a stream event.
final StreamProvider<AuthState> authStateProvider = StreamProvider<AuthState>((
  ref,
) {
  final service = ref.watch(authServiceProvider);
  return service.states;
});

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

class CustomerApp extends ConsumerWidget {
  const CustomerApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final GoRouter router = ref.watch(customerRouterProvider);
    return MaterialApp.router(
      title: 'Aish Laundry',
      theme: AishTheme.light(),
      // No `darkTheme` and no `themeMode`. Dark mode is DEFERRED; wiring a
      // fallback here would let the surface render an unspecified theme the
      // moment a device is in dark mode.
      debugShowCheckedModeBanner: false,
      routerConfig: router,
    );
  }
}
