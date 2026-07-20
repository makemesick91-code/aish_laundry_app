import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/console_routes.dart';

class _ConsoleEndedScaffold extends ConsumerWidget {
  const _ConsoleEndedScaffold({
    required this.title,
    required this.description,
    required this.icon,
    required this.statusLabel,
    required this.tone,
    required this.recoveryLabel,
    this.recoveryRoute,
  });

  final String title;
  final String description;
  final IconData icon;
  final String statusLabel;
  final StatusTone tone;
  final String recoveryLabel;
  final String? recoveryRoute;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    body: StateMessage(
      title: title,
      description: description,
      icon: icon,
      tone: tone,
      statusLabel: statusLabel,
      recoveryLabel: recoveryLabel,
      onRecover: () async {
        if (recoveryRoute == ConsoleRoutes.selectTenant) {
          if (context.mounted) {
            context.go(ConsoleRoutes.selectTenant);
          }
          return;
        }
        await ref.read(authServiceProvider).signOut();
        if (context.mounted) {
          context.go(ConsoleRoutes.signIn);
        }
      },
    ),
  );
}

class ConsoleSessionExpiredScreen extends StatelessWidget {
  const ConsoleSessionExpiredScreen({super.key});

  @override
  Widget build(BuildContext context) => const _ConsoleEndedScaffold(
    title: 'Sesi Anda telah berakhir',
    description:
        'Demi keamanan, sesi konsol berakhir setelah tidak '
        'digunakan. Masuk kembali untuk melanjutkan.',
    icon: Icons.schedule_outlined,
    statusLabel: 'Sesi berakhir',
    tone: StatusTone.warning,
    recoveryLabel: 'Masuk kembali',
  );
}

class ConsoleMembershipSuspendedScreen extends StatelessWidget {
  const ConsoleMembershipSuspendedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _ConsoleEndedScaffold(
    title: 'Keanggotaan Anda ditangguhkan',
    description:
        'Akses Anda pada tenant ini sedang ditangguhkan dan dapat '
        'dibuka kembali oleh pengelola tenant. Bila Anda mengelola tenant '
        'lain, pilih tenant tersebut.',
    icon: Icons.pause_circle_outline,
    statusLabel: 'Keanggotaan ditangguhkan',
    tone: StatusTone.warning,
    recoveryLabel: 'Pilih tenant lain',
    recoveryRoute: ConsoleRoutes.selectTenant,
  );
}

class ConsoleMembershipRevokedScreen extends StatelessWidget {
  const ConsoleMembershipRevokedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _ConsoleEndedScaffold(
    title: 'Keanggotaan Anda dicabut',
    description:
        'Anda tidak lagi menjadi anggota tenant ini. Masuk kembali '
        'tidak akan memulihkan akses. Bila Anda mengelola tenant lain, '
        'pilih tenant tersebut.',
    icon: Icons.person_off_outlined,
    statusLabel: 'Keanggotaan dicabut',
    tone: StatusTone.danger,
    recoveryLabel: 'Pilih tenant lain',
    recoveryRoute: ConsoleRoutes.selectTenant,
  );
}

/// Tenant access refused.
///
/// The copy deliberately says nothing about whether the tenant exists. A
/// console user probing tenant identifiers must learn nothing from the
/// difference between "not yours" and "not a tenant".
class ConsoleTenantAccessDeniedScreen extends StatelessWidget {
  const ConsoleTenantAccessDeniedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _ConsoleEndedScaffold(
    title: 'Akses ditolak',
    description:
        'Anda tidak memiliki akses ke konteks yang diminta. Pilih '
        'tenant lain, atau hubungi pengelola akun Anda.',
    icon: Icons.lock_outline,
    statusLabel: 'Akses ditolak',
    tone: StatusTone.danger,
    recoveryLabel: 'Pilih tenant lain',
    recoveryRoute: ConsoleRoutes.selectTenant,
  );
}
