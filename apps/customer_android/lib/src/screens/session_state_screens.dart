import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/customer_routes.dart';

/// Base frame for every session-ended and error screen.
///
/// The four session-ending states get FOUR DISTINCT screens rather than one
/// shared "signed out" page. The recovery genuinely differs, and one message
/// covering four situations would be dishonest in at least three of them:
/// an expired session is nobody's fault, a revoked session means somebody did
/// that deliberately, and a revoked device means the user's other devices may
/// be unaffected.
class _SessionEndedScaffold extends ConsumerWidget {
  const _SessionEndedScaffold({
    required this.title,
    required this.description,
    required this.icon,
    required this.statusLabel,
    this.tone = StatusTone.warning,
    this.recoveryLabel,
  });

  final String title;
  final String description;
  final IconData icon;
  final String statusLabel;
  final StatusTone tone;
  final String? recoveryLabel;

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
              // Signing out clears every local credential before returning
              // to the sign-in screen. Leaving a dead credential on the
              // device would let a later restoration attempt present it.
              await ref.read(authServiceProvider).signOut();
              if (context.mounted) {
                context.go(CustomerRoutes.signIn);
              }
            },
    ),
  );
}

class SessionExpiredScreen extends StatelessWidget {
  const SessionExpiredScreen({super.key});

  @override
  Widget build(BuildContext context) => const _SessionEndedScaffold(
    title: 'Sesi Anda telah berakhir',
    description:
        'Demi keamanan, sesi berakhir setelah tidak digunakan. '
        'Masuk kembali untuk melanjutkan.',
    icon: Icons.schedule_outlined,
    statusLabel: 'Sesi berakhir',
    recoveryLabel: 'Masuk kembali',
  );
}

class SessionRevokedScreen extends StatelessWidget {
  const SessionRevokedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _SessionEndedScaffold(
    title: 'Sesi Anda dicabut',
    description:
        'Sesi ini dihentikan dari perangkat lain atau oleh '
        'pengelola akun. Jika Anda tidak melakukannya, masuk kembali lalu '
        'periksa daftar sesi aktif Anda.',
    icon: Icons.no_accounts_outlined,
    statusLabel: 'Sesi dicabut',
    tone: StatusTone.danger,
    recoveryLabel: 'Masuk kembali',
  );
}

class DeviceRevokedScreen extends StatelessWidget {
  const DeviceRevokedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _SessionEndedScaffold(
    title: 'Akses perangkat ini dicabut',
    description:
        'Perangkat ini tidak lagi diizinkan mengakses akun Anda. '
        'Perangkat Anda yang lain mungkin masih dapat digunakan. Hubungi '
        'pengelola akun jika ini tidak Anda harapkan.',
    icon: Icons.phonelink_erase_outlined,
    statusLabel: 'Perangkat dicabut',
    tone: StatusTone.danger,
    recoveryLabel: 'Masuk kembali',
  );
}

class AccessDeniedScreen extends StatelessWidget {
  const AccessDeniedScreen({super.key});

  @override
  Widget build(BuildContext context) => const _SessionEndedScaffold(
    title: 'Akses ditolak',
    description:
        'Anda tidak memiliki akses ke bagian ini. Hubungi '
        'pengelola akun Anda bila Anda merasa seharusnya memilikinya.',
    // Says nothing about WHETHER the requested thing exists. Across a
    // tenant boundary denial and absence must be indistinguishable.
    icon: Icons.lock_outline,
    statusLabel: 'Akses ditolak',
    tone: StatusTone.danger,
    recoveryLabel: 'Keluar',
  );
}

/// No usable network path. Retrying is a genuine recovery, so it is offered.
class NetworkUnavailableScreen extends StatelessWidget {
  const NetworkUnavailableScreen({super.key, this.onRetry});

  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: StateMessage(
      title: 'Tidak ada koneksi',
      description:
          'Aplikasi tidak dapat menghubungi server. Periksa '
          'koneksi internet Anda, lalu coba lagi.',
      icon: Icons.wifi_off_outlined,
      tone: StatusTone.offline,
      statusLabel: 'Luring',
      recoveryLabel: onRetry == null ? null : 'Coba lagi',
      onRecover: onRetry,
    ),
  );
}

/// The service is reachable but not serving. Distinguished from a network
/// failure because the user's own connection is fine and telling them to check
/// their internet would be wrong.
class ServiceUnavailableScreen extends StatelessWidget {
  const ServiceUnavailableScreen({super.key, this.onRetry, this.reference});

  final VoidCallback? onRetry;
  final String? reference;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: StateMessage(
      title: 'Layanan sedang tidak tersedia',
      description:
          'Server sedang tidak dapat melayani permintaan. Koneksi '
          'Anda tidak bermasalah. Coba lagi beberapa saat lagi.',
      icon: Icons.cloud_off_outlined,
      tone: StatusTone.warning,
      statusLabel: 'Layanan tidak tersedia',
      recoveryLabel: onRetry == null ? null : 'Coba lagi',
      onRecover: onRetry,
      supportReference: reference,
    ),
  );
}
