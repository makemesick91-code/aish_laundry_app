import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'routing/console_router.dart';

final Provider<Environment> environmentProvider = Provider<Environment>(
  (ref) => throw UnimplementedError(
    'environmentProvider must be overridden with a validated Environment.',
  ),
);

final Provider<AuthService> authServiceProvider = Provider<AuthService>(
  (ref) => throw UnimplementedError('authServiceProvider must be overridden.'),
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

class ConsoleApp extends ConsumerWidget {
  const ConsoleApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final GoRouter router = ref.watch(consoleRouterProvider);
    return MaterialApp.router(
      title: 'Aish Laundry Console',
      theme: AishTheme.light(),
      debugShowCheckedModeBanner: false,
      routerConfig: router,
      // Shortcuts are left at the framework defaults so Tab, Shift+Tab, Enter
      // and Escape behave as a keyboard user expects. Console Web must be
      // keyboard-complete: every action reachable by pointer is reachable by
      // keyboard, in a defined focus order (Rule 27 rule 8, Rule 28 rule 8).
    );
  }
}
