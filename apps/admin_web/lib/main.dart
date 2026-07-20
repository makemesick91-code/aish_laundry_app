import 'package:aish_core/aish_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/app.dart';
import 'src/startup/environment_failure_app.dart';

/// Entry point for Aish Laundry Console Web.
void main() {
  WidgetsFlutterBinding.ensureInitialized();

  final environment = Environment.fromDartDefines(
    appName: 'Aish Laundry Console',
  );

  runApp(
    environment.fold(
      (env) => ProviderScope(
        overrides: [environmentProvider.overrideWithValue(env)],
        child: const ConsoleApp(),
      ),
      EnvironmentFailureApp.new,
    ),
  );
}
