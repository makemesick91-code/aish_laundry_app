import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';

/// Rendered when startup configuration validation fails.
///
/// Deliberately a WHOLE APP rather than a screen inside the normal shell. A
/// misconfigured build has no working API, so there is nothing for the rest of
/// the application to do; booting into it would only produce a login screen that
/// cannot succeed.
///
/// It offers no retry. Re-reading the same compile-time constants would produce
/// the same answer, and a retry button that cannot work is a dark pattern
/// dressed as helpfulness. The recovery is stated instead: the build is wrong
/// and must be rebuilt.
class EnvironmentFailureApp extends StatelessWidget {
  const EnvironmentFailureApp(this.failure, {super.key});

  final Failure failure;

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Aish Laundry',
    theme: AishTheme.light(),
    debugShowCheckedModeBanner: false,
    home: Scaffold(
      body: StateMessage(
        title: 'Konfigurasi aplikasi tidak valid',
        description:
            'Aplikasi tidak dapat dijalankan karena konfigurasi build salah. '
            'Hubungi tim teknis dan sampaikan pesan berikut: '
            '${failure.message}',
        icon: Icons.settings_outlined,
        tone: StatusTone.danger,
        statusLabel: 'Konfigurasi bermasalah',
      ),
    ),
  );
}
