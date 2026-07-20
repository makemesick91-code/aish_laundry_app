import 'dart:async';

import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// Console startup, and the browser-refresh restoration path.
///
/// On every load — first visit or F5 — this asks the SERVER who the user is.
/// Nothing is read back from web storage, because nothing is written there.
/// The HTTP-only session cookie is attached by the browser automatically, so a
/// refresh restores the session without the page ever handling a credential.
///
/// Session restoration is ALWAYS server-verified. The presence of a stored
/// credential proves only that a credential is stored; a client that trusted its
/// own storage would show a signed-in shell to a user whose access was revoked
/// yesterday, and would only discover the truth on their first request.
class StartupScreen extends ConsumerStatefulWidget {
  const StartupScreen({super.key});

  @override
  ConsumerState<StartupScreen> createState() => _StartupScreenState();
}

class _StartupScreenState extends ConsumerState<StartupScreen> {
  @override
  void initState() {
    super.initState();
    // Deferred to after the first frame so the router is mounted before the
    // resulting state change triggers a redirect.
    WidgetsBinding.instance.addPostFrameCallback((_) => _restore());
  }

  /// Ask the SERVER whether the stored credential still stands, then open the
  /// startup gate exactly once.
  ///
  /// The gate is opened in a `finally`, so a restoration that FAILS still lets
  /// the application proceed to sign-in. Opening it only on success would leave
  /// a user staring at a spinner forever whenever the network was down.
  Future<void> _restore() async {
    if (!mounted) {
      return;
    }
    try {
      await ref.read(authServiceProvider).restoreSession();
    } finally {
      if (mounted) {
        ref.read(startupGateProvider).value = true;
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: Semantics(
      liveRegion: true,
      label: 'Memulai aplikasi dan memeriksa sesi.',
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const CircularProgressIndicator(),
            SizedBox(height: AishSpacing.space4),
            // Text as well as a spinner: an indefinite spinner alone tells
            // a user nothing about what is happening or whether it is stuck.
            Text(
              'Memeriksa sesi Anda…',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    ),
  );
}
