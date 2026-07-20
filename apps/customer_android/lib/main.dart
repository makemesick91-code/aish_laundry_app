import 'package:aish_core/aish_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/app.dart';
import 'src/startup/environment_failure_app.dart';

/// Entry point for Aish Laundry Customer Android.
///
/// Configuration is validated BEFORE the first frame. A surface with an invalid
/// API base URL renders an explicit configuration screen instead of a login form
/// that would fail in a way no user could distinguish from wrong credentials.
void main() {
  WidgetsFlutterBinding.ensureInitialized();

  final environment = Environment.fromDartDefines(appName: 'Aish Laundry');

  runApp(
    environment.fold(
      (env) => ProviderScope(
        overrides: [environmentProvider.overrideWithValue(env)],
        child: const CustomerApp(),
      ),
      EnvironmentFailureApp.new,
    ),
  );
}
