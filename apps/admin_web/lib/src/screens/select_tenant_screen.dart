import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// Explicit tenant selection.
///
/// THE LIST IS THE SERVER'S ANSWER. The client never computes which tenants a
/// user may act in, never adds to the list, and never filters it in a way that
/// could hide a legitimate entry. What the server returned is what is shown.
///
/// There is NO auto-selection, not even for a single-tenant user. The one-tenant
/// case is exactly where auto-selection is most tempting and most dangerous: it
/// trains the interface to choose a tenant on the user's behalf, and the code
/// path then exists for the multi-tenant case too.
///
/// An INACTIVE tenant is listed but not selectable, with the reason stated. The
/// alternative — filtering it out — leaves a user staring at a list that is
/// missing the business they know they belong to, with nothing to explain why.
class SelectTenantScreen extends ConsumerStatefulWidget {
  const SelectTenantScreen({super.key});

  @override
  ConsumerState<SelectTenantScreen> createState() => _SelectTenantScreenState();
}

class _SelectTenantScreenState extends ConsumerState<SelectTenantScreen> {
  String? _busyTenantId;

  Future<void> _select(Tenant tenant) async {
    setState(() => _busyTenantId = tenant.id);
    await ref.read(authServiceProvider).selectTenant(tenant.id);
    if (mounted) {
      setState(() => _busyTenantId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    final textTheme = Theme.of(context).textTheme;
    final tenants = session?.availableTenants ?? const <Tenant>[];

    return Scaffold(
      appBar: AppBar(
        title: Semantics(header: true, child: const Text('Pilih tenant')),
      ),
      body: tenants.isEmpty
          // A designed empty state, not a blank page.
          ? const StateMessage(
              title: 'Tidak ada tenant tersedia',
              description:
                  'Akun Anda belum terhubung ke tenant mana pun. Hubungi '
                  'pengelola akun Anda untuk mendapatkan akses.',
              icon: Icons.apartment_outlined,
              statusLabel: 'Tidak ada tenant',
            )
          : ListView(
              padding: EdgeInsets.all(AishSpacing.space4),
              children: <Widget>[
                Text(
                  'Pilih tenant tempat Anda akan bekerja. Semua data yang Anda '
                  'lihat berikutnya hanya milik tenant yang Anda pilih.',
                  style: textTheme.bodyMedium?.copyWith(
                    color: AishSemanticColors.colorSemanticTextSecondary,
                  ),
                ),
                SizedBox(height: AishSpacing.space4),
                for (final tenant in tenants)
                  _TenantRow(
                    tenant: tenant,
                    isBusy: _busyTenantId == tenant.id,
                    onSelect: tenant.isActive ? () => _select(tenant) : null,
                  ),
              ],
            ),
    );
  }
}

class _TenantRow extends StatelessWidget {
  const _TenantRow({
    required this.tenant,
    required this.isBusy,
    required this.onSelect,
  });

  final Tenant tenant;
  final bool isBusy;
  final VoidCallback? onSelect;

  @override
  Widget build(BuildContext context) {
    final isSelectable = onSelect != null;
    return Padding(
      padding: EdgeInsets.only(bottom: AishSpacing.space3),
      child: Semantics(
        button: isSelectable,
        enabled: isSelectable,
        label: isSelectable
            ? 'Pilih tenant ${tenant.name}'
            : 'Tenant ${tenant.name} tidak aktif dan tidak dapat dipilih',
        child: ExcludeSemantics(
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: AishSizing.sizeTouchMin),
            child: Card(
              child: ListTile(
                enabled: isSelectable,
                onTap: isBusy ? null : onSelect,
                title: Text(tenant.name),
                // The reason for a disabled row is EXPLAINED, never left as a
                // greyed-out mystery (Rule 28 rule 5).
                subtitle: tenant.isActive
                    ? null
                    : const Text('Tenant nonaktif. Hubungi pengelola akun.'),
                trailing: isBusy
                    ? SizedBox(
                        width: AishSizing.sizeIconMd,
                        height: AishSizing.sizeIconMd,
                        child: CircularProgressIndicator(
                          strokeWidth: AishBorders.borderWidthThick,
                        ),
                      )
                    : Icon(
                        isSelectable
                            ? Icons.chevron_right
                            : Icons.block_outlined,
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
