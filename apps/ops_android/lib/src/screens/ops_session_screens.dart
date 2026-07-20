import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/ops_routes.dart';

/// Frame for every Ops session-ended and denial screen.
class _OpsEndedScaffold extends ConsumerWidget {
  const _OpsEndedScaffold({
    required this.title,
    required this.description,
    required this.icon,
    required this.statusLabel,
    required this.tone,
    this.recoveryLabel,
    this.recoveryRoute,
  });

  final String title;
  final String description;
  final IconData icon;
  final String statusLabel;
  final StatusTone tone;
  final String? recoveryLabel;
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
      onRecover: recoveryLabel == null
          ? null
          : () async {
              if (recoveryRoute == OpsRoutes.selectTenant) {
                // A membership problem in ONE tenant does not end the
                // session. The user may still have a working membership
                // elsewhere, so they are returned to tenant selection
                // rather than signed out.
                if (context.mounted) {
                  context.go(OpsRoutes.selectTenant);
                }
                return;
              }
              await ref.read(authServiceProvider).signOut();
              if (context.mounted) {
                context.go(OpsRoutes.signIn);
              }
            },
    ),
  );
}

class OpsSessionExpiredScreen extends StatelessWidget {
  const OpsSessionExpiredScreen({super.key});

  @override
  Widget build(BuildContext context) => const _OpsEndedScaffold(
    title: 'Sesi Anda telah berakhir',
    description:
        'Masuk kembali untuk melanjutkan pekerjaan. Pekerjaan yang '
        'sudah tersimpan di perangkat tidak dihapus.',
    icon: Icons.schedule_outlined,
    statusLabel: 'Sesi berakhir',
    tone: StatusTone.warning,
    recoveryLabel: 'Masuk kembali',
  );
}

class OpsSessionRevokedScreen extends StatelessWidget {
  const OpsSessionRevokedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _OpsEndedScaffold(
    title: 'Sesi Anda dicabut',
    description:
        'Sesi ini dihentikan oleh pengelola tenant atau dari '
        'perangkat lain. Hubungi pengelola tenant Anda bila ini tidak Anda '
        'harapkan.',
    icon: Icons.no_accounts_outlined,
    statusLabel: 'Sesi dicabut',
    tone: StatusTone.danger,
    recoveryLabel: 'Masuk kembali',
  );
}

class OpsDeviceRevokedScreen extends StatelessWidget {
  const OpsDeviceRevokedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _OpsEndedScaffold(
    title: 'Akses perangkat ini dicabut',
    description:
        'Perangkat ini tidak lagi diizinkan mengakses tenant Anda. '
        'Perangkat lain mungkin masih dapat digunakan. Hubungi pengelola '
        'tenant Anda.',
    icon: Icons.phonelink_erase_outlined,
    statusLabel: 'Perangkat dicabut',
    tone: StatusTone.danger,
    recoveryLabel: 'Masuk kembali',
  );
}

class MembershipSuspendedScreen extends StatelessWidget {
  const MembershipSuspendedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _OpsEndedScaffold(
    title: 'Keanggotaan Anda ditangguhkan',
    description:
        'Akses Anda pada tenant ini sedang ditangguhkan. '
        'Penangguhan dapat dibuka kembali oleh pengelola tenant. Jika Anda '
        'bekerja pada tenant lain, pilih tenant tersebut.',
    icon: Icons.pause_circle_outline,
    statusLabel: 'Keanggotaan ditangguhkan',
    tone: StatusTone.warning,
    recoveryLabel: 'Pilih tenant lain',
    recoveryRoute: OpsRoutes.selectTenant,
  );
}

class MembershipRevokedScreen extends StatelessWidget {
  const MembershipRevokedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _OpsEndedScaffold(
    title: 'Keanggotaan Anda dicabut',
    description:
        'Anda tidak lagi menjadi anggota tenant ini. Masuk kembali '
        'tidak akan memulihkan akses. Jika Anda bekerja pada tenant lain, '
        'pilih tenant tersebut.',
    icon: Icons.person_off_outlined,
    statusLabel: 'Keanggotaan dicabut',
    tone: StatusTone.danger,
    recoveryLabel: 'Pilih tenant lain',
    recoveryRoute: OpsRoutes.selectTenant,
  );
}

/// The selected outlet became inactive while it was the working context.
class OutletInactiveScreen extends StatelessWidget {
  const OutletInactiveScreen({super.key});

  @override
  Widget build(BuildContext context) => const _OpsEndedScaffold(
    title: 'Outlet ini nonaktif',
    description:
        'Outlet yang Anda pilih sudah tidak aktif dan tidak dapat '
        'dijadikan konteks kerja. Pilih outlet lain untuk melanjutkan.',
    icon: Icons.store_outlined,
    statusLabel: 'Outlet nonaktif',
    tone: StatusTone.warning,
    recoveryLabel: 'Pilih tenant lain',
    recoveryRoute: OpsRoutes.selectTenant,
  );
}

class OpsAccessDeniedScreen extends StatelessWidget {
  const OpsAccessDeniedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _OpsEndedScaffold(
    // Discloses nothing about whether the requested record exists.
    title: 'Akses ditolak',
    description:
        'Anda tidak memiliki akses ke bagian ini. Hubungi '
        'pengelola tenant Anda bila Anda merasa seharusnya memilikinya.',
    icon: Icons.lock_outline,
    statusLabel: 'Akses ditolak',
    tone: StatusTone.danger,
    recoveryLabel: 'Keluar',
  );
}
