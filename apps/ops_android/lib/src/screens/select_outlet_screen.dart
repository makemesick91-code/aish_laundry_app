import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// Explicit outlet selection, within the already-chosen tenant.
///
/// The outlet list is fetched AFTER a tenant is resolved and is scoped to it by
/// the server. The tenant banner stays visible throughout, so a user choosing an
/// outlet can always see which business they are choosing within — the moment of
/// commitment is exactly when the context must be restated (Rule 32 rule 1).
class SelectOutletScreen extends ConsumerStatefulWidget {
  const SelectOutletScreen({super.key});

  @override
  ConsumerState<SelectOutletScreen> createState() => _SelectOutletScreenState();
}

class _SelectOutletScreenState extends ConsumerState<SelectOutletScreen> {
  List<Outlet>? _outlets;
  String? _errorDescription;
  bool _loading = true;
  String? _busyOutletId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _errorDescription = null;
    });
    final result = await ref.read(authServiceProvider).authorizedOutlets();
    if (!mounted) {
      return;
    }
    setState(() {
      _loading = false;
      result.fold(
        (outlets) => _outlets = outlets,
        (failure) => _errorDescription =
            'Daftar outlet tidak dapat dimuat. Periksa koneksi Anda, lalu coba '
            'lagi.',
      );
    });
  }

  Future<void> _select(Outlet outlet) async {
    setState(() => _busyOutletId = outlet.id);
    await ref.read(authServiceProvider).selectOutlet(outlet.id);
    if (mounted) {
      setState(() => _busyOutletId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    final tenantName = session?.activeTenant?.name ?? '—';

    return AishScaffold(
      title: 'Pilih outlet',
      tenantName: tenantName,
      body: _body(),
    );
  }

  Widget _body() {
    if (_loading) {
      return Semantics(
        liveRegion: true,
        label: 'Memuat daftar outlet.',
        child: const Center(child: CircularProgressIndicator()),
      );
    }
    if (_errorDescription != null) {
      // An error state WITH a recovery action. An error with no way forward is
      // an incomplete specification (Rule 29 rule 8).
      return StateMessage(
        title: 'Gagal memuat outlet',
        description: _errorDescription!,
        icon: Icons.error_outline,
        tone: StatusTone.danger,
        statusLabel: 'Gagal memuat',
        recoveryLabel: 'Coba lagi',
        onRecover: _load,
      );
    }
    final outlets = _outlets ?? const <Outlet>[];
    if (outlets.isEmpty) {
      return const StateMessage(
        title: 'Tidak ada outlet',
        description:
            'Tenant ini belum memiliki outlet yang dapat Anda akses. '
            'Hubungi pengelola tenant Anda.',
        icon: Icons.store_outlined,
        statusLabel: 'Tidak ada outlet',
      );
    }
    return ListView(
      padding: EdgeInsets.all(AishSpacing.space4),
      children: <Widget>[
        for (final outlet in outlets)
          Padding(
            padding: EdgeInsets.only(bottom: AishSpacing.space3),
            child: Semantics(
              button: outlet.isActive,
              enabled: outlet.isActive,
              label: outlet.isActive
                  ? 'Pilih outlet ${outlet.name}'
                  : 'Outlet ${outlet.name} nonaktif dan tidak dapat dipilih',
              child: ExcludeSemantics(
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: AishSizing.sizeTouchMin,
                  ),
                  child: Card(
                    child: ListTile(
                      enabled: outlet.isActive,
                      onTap: _busyOutletId == outlet.id || !outlet.isActive
                          ? null
                          : () => _select(outlet),
                      title: Text(outlet.name),
                      subtitle: outlet.isActive
                          ? null
                          : const Text(
                              'Outlet nonaktif. Tidak dapat dijadikan konteks '
                              'kerja.',
                            ),
                      trailing: _busyOutletId == outlet.id
                          ? SizedBox(
                              width: AishSizing.sizeIconMd,
                              height: AishSizing.sizeIconMd,
                              child: CircularProgressIndicator(
                                strokeWidth: AishBorders.borderWidthThick,
                              ),
                            )
                          : Icon(
                              outlet.isActive
                                  ? Icons.chevron_right
                                  : Icons.block_outlined,
                            ),
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
