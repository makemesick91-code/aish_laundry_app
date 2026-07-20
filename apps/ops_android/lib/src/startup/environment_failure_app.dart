import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';

/// Rendered when startup configuration validation fails.
///
/// The Ops surface refuses to boot rather than presenting a counter operator
/// with a login screen that cannot succeed. A cashier with a queue of customers
/// needs to know immediately that the BUILD is wrong, not spend five minutes
/// retyping a password.
class EnvironmentFailureApp extends StatelessWidget {
  const EnvironmentFailureApp(this.failure, {super.key});

  final Failure failure;

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Aish Laundry Ops',
    theme: AishTheme.light(),
    debugShowCheckedModeBanner: false,
    home: Scaffold(
      body: StateMessage(
        title: 'Konfigurasi aplikasi tidak valid',
        description:
            'Aplikasi kasir tidak dapat dijalankan karena konfigurasi build '
            'salah. Hubungi tim teknis dan sampaikan pesan berikut: '
            '${failure.message}',
        icon: Icons.settings_outlined,
        tone: StatusTone.danger,
        statusLabel: 'Konfigurasi bermasalah',
      ),
    ),
  );
}
