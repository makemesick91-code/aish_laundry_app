import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';

/// Rendered when startup configuration validation fails.
class EnvironmentFailureApp extends StatelessWidget {
  const EnvironmentFailureApp(this.failure, {super.key});

  final Failure failure;

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Aish Laundry Console',
    theme: AishTheme.light(),
    debugShowCheckedModeBanner: false,
    home: Scaffold(
      body: StateMessage(
        title: 'Konfigurasi aplikasi tidak valid',
        description:
            'Konsol tidak dapat dijalankan karena konfigurasi build salah. '
            'Hubungi tim teknis dan sampaikan pesan berikut: '
            '${failure.message}',
        icon: Icons.settings_outlined,
        tone: StatusTone.danger,
        statusLabel: 'Konfigurasi bermasalah',
      ),
    ),
  );
}
