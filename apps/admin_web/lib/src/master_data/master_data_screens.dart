import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// Supplies the repository, built from the surface's authenticated
/// [ApiClient]. A test overrides it with a fake.
///
/// THIS PROVIDER PREVIOUSLY THREW `UnimplementedError` and was overridden only
/// in tests, so every production master-data screen threw the moment it was
/// opened in a real build while the widget suite stayed green — the same defect
/// DEC-0032 records for `authServiceProvider`, one layer up.
final Provider<MasterDataRepository> masterDataRepositoryProvider =
    Provider<MasterDataRepository>(
      (ref) => MasterDataRepository(ref.watch(apiClientProvider)),
    );

/// The Step 4 master-data section of the console (FR-021 … FR-047).
///
/// WHAT THIS SCREEN IS FOR, AND WHAT IT IS NOT
/// -------------------------------------------
/// It manages MASTER DATA: who the customers are, what the tenant sells, what it
/// costs, how its outlets are configured, and who works where. It creates no
/// order, takes no payment, and prints no document — those are Step 5 and later
/// (Rule 42, DEC-0030).
///
/// EVERY TAB RENDERS ALL OF ITS STATES.
/// `LOADING`, `EMPTY`, `LOADED`, `ERROR`, and `DENIED` each have a defined
/// rendering, and every non-loaded state names a recovery action in Bahasa
/// Indonesia. A surface that only draws the happy path lies whenever reality
/// departs from it, and Rule 29 hard rule 13 treats an indefinite spinner as an
/// absent state model rather than a placeholder.
///
/// A CONTROL THE USER MAY NOT USE IS NOT RENDERED (Rule 28 hard rule 5).
/// The edit and publish affordances are drawn only when the session reports the
/// matching permission. That is a convenience, never the control: the server
/// re-derives permissions from live membership on every request, so a console
/// that is wrong about one produces a refused request rather than an
/// unauthorized effect (Rule 40 hard rule 2).
class MasterDataScreen extends ConsumerStatefulWidget {
  const MasterDataScreen({super.key});

  @override
  ConsumerState<MasterDataScreen> createState() => _MasterDataScreenState();
}

class _MasterDataScreenState extends ConsumerState<MasterDataScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 4, vsync: this);

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;

    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    // The tenant whose data this is, restated at the top of the section. Rule 28
    // hard rule 1 treats a screen without visible tenant context as a
    // tenant-isolation design defect, not a layout preference.
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Padding(
          padding: EdgeInsets.all(AishSpacing.space4),
          child: Semantics(
            header: true,
            child: Text(
              'Data induk — ${session.activeTenant!.name}',
              style: Theme.of(context).textTheme.titleLarge,
            ),
          ),
        ),
        TabBar(
          controller: _tabs,
          tabs: const <Widget>[
            Tab(text: 'Pelanggan'),
            Tab(text: 'Layanan'),
            Tab(text: 'Daftar harga'),
            Tab(text: 'Staf'),
          ],
        ),
        Expanded(
          child: TabBarView(
            controller: _tabs,
            children: <Widget>[
              _CustomersTab(
                canManage: session.allows(Permission.customerManage),
              ),
              _ServicesTab(canManage: session.allows(Permission.serviceManage)),
              _PriceListsTab(
                canPublish: session.allows(Permission.priceListPublish),
              ),
              _StaffTab(
                canAssign: session.allows(Permission.staffAssignmentManage),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// A shared async-section frame so every tab renders the SAME state set.
// ---------------------------------------------------------------------------

/// Renders one of the canonical UX states for a loaded collection.
///
/// Extracted so the five states cannot drift between tabs: a section that
/// forgot its `DENIED` rendering, or that spun forever on an error, would be an
/// incomplete state model rather than a smaller feature (Rule 29, Rule 34).
class _AsyncSection<T> extends StatefulWidget {
  const _AsyncSection({
    required this.load,
    required this.emptyTitle,
    required this.emptyDescription,
    required this.builder,
    super.key,
  });

  final Future<Result<List<T>>> Function() load;
  final String emptyTitle;
  final String emptyDescription;
  final Widget Function(BuildContext context, List<T> items) builder;

  @override
  State<_AsyncSection<T>> createState() => _AsyncSectionState<T>();
}

class _AsyncSectionState<T> extends State<_AsyncSection<T>> {
  late Future<Result<List<T>>> _future = widget.load();

  void _reload() => setState(() => _future = widget.load());

  @override
  Widget build(BuildContext context) => FutureBuilder<Result<List<T>>>(
    future: _future,
    builder: (context, snapshot) {
      // LOADING.
      if (snapshot.connectionState != ConnectionState.done) {
        return Center(
          // Labelled for assistive technology: a bare spinner announces
          // nothing, and "memuat" is what a screen-reader user needs to hear.
          child: Semantics(
            label: 'Memuat data',
            child: const SizedBox(
              width: 32,
              height: 32,
              child: CircularProgressIndicator(),
            ),
          ),
        );
      }

      final result = snapshot.data;

      // An absent result means the future itself threw — a defect, reported as
      // one rather than rendered as an empty list.
      if (result == null) {
        return StateMessage(
          title: 'Data tidak dapat ditampilkan',
          description:
              'Terjadi kesalahan saat menyiapkan data. Muat ulang halaman ini; '
              'bila berulang, hubungi admin tenant Anda.',
          icon: Icons.error_outline,
          tone: StatusTone.danger,
          recoveryLabel: 'Muat ulang',
          onRecover: _reload,
        );
      }

      return result.fold(
        (List<T> items) => items.isEmpty
            // EMPTY — states what would appear here and why, never a blank
            // panel (Rule 29 hard rule 10).
            ? StateMessage(
                title: widget.emptyTitle,
                description: widget.emptyDescription,
                icon: Icons.inbox_outlined,
              )
            // LOADED.
            : widget.builder(context, items),
        (Failure failure) => _FailureState(failure: failure, onRetry: _reload),
      );
    },
  );
}

/// The ERROR and DENIED renderings, chosen by failure kind.
class _FailureState extends StatelessWidget {
  const _FailureState({required this.failure, required this.onRetry});

  final Failure failure;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    // DENIED. Deliberately says nothing about whether the data exists: across a
    // tenant boundary the server answers identically for "not yours" and "not
    // there", and a client that distinguished them would leak what the server
    // just hid (Rule 48 hard rule 5, Rule 32 hard rule 2).
    if (failure.kind == FailureKind.authorization) {
      return StateMessage(
        title: 'Anda tidak memiliki akses ke data ini',
        description:
            'Hubungi admin tenant Anda bila Anda memerlukan akses ke bagian ini.',
        icon: Icons.lock_outline,
        tone: StatusTone.warning,
        supportReference: failure.correlationId,
      );
    }

    // A NON-RETRYABLE failure offers no retry button. Rule 29 hard rule 8 wants
    // a recovery action, and for a stale write or a validation refusal the
    // recovery is to reload and re-apply — resending the same payload is
    // exactly what would overwrite somebody else's edit (threat T-12).
    final bool retryable = failure.isRetryable;

    return StateMessage(
      title: retryable ? 'Data gagal dimuat' : 'Data ini sudah berubah',
      description: retryable
          ? 'Periksa koneksi Anda lalu coba lagi.'
          : 'Muat ulang untuk melihat perubahan terbaru, lalu ulangi tindakan Anda.',
      icon: retryable ? Icons.wifi_off_outlined : Icons.sync_problem_outlined,
      tone: retryable ? StatusTone.offline : StatusTone.warning,
      recoveryLabel: retryable ? 'Coba lagi' : 'Muat ulang',
      onRecover: onRetry,
      supportReference: failure.correlationId,
    );
  }
}

// ---------------------------------------------------------------------------
// Customers (FR-021 … FR-026)
// ---------------------------------------------------------------------------

class _CustomersTab extends ConsumerWidget {
  const _CustomersTab({required this.canManage});

  final bool canManage;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repository = ref.watch(masterDataRepositoryProvider);

    return _AsyncSection<CustomerSummary>(
      load: repository.customers,
      emptyTitle: 'Belum ada pelanggan',
      emptyDescription: canManage
          ? 'Pelanggan yang Anda daftarkan di konter akan muncul di sini.'
          : 'Pelanggan yang didaftarkan di konter akan muncul di sini.',
      builder: (context, items) => ListView.builder(
        itemCount: items.length,
        itemBuilder: (context, index) {
          final customer = items[index];

          return ListTile(
            title: Text(customer.name),
            // The phone arrives MASKED from the server and is rendered as it
            // arrived. There is no unmask control here: unmasking is a
            // deliberate, per-record, permissioned, recorded server action and
            // is never a hover or a bulk reveal (Rule 32 hard rule 5).
            subtitle: Text('${customer.code} · ${customer.phoneMasked}'),
            trailing: customer.isArchived
                // Archived is visually distinct AND labelled. Status is never
                // carried by colour alone (Rule 27 hard rule 3).
                ? const StatusChip(
                    label: 'Diarsipkan',
                    icon: Icons.archive_outlined,
                    tone: StatusTone.neutral,
                  )
                : null,
          );
        },
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Service catalogue (FR-031 … FR-033)
// ---------------------------------------------------------------------------

class _ServicesTab extends ConsumerWidget {
  const _ServicesTab({required this.canManage});

  final bool canManage;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repository = ref.watch(masterDataRepositoryProvider);

    return _AsyncSection<CatalogService>(
      load: () => repository.services(),
      emptyTitle: 'Katalog layanan masih kosong',
      emptyDescription: canManage
          ? 'Tambahkan layanan kiloan atau satuan agar dapat diberi harga.'
          : 'Layanan yang ditambahkan admin tenant akan muncul di sini.',
      builder: (context, items) => ListView.builder(
        itemCount: items.length,
        itemBuilder: (context, index) {
          final service = items[index];

          return ListTile(
            title: Text(service.name),
            subtitle: Text(
              // The unit is spelled out with its measure, so a reader never has
              // to guess whether a minimum of 2000 is grams or items.
              service.minimumQuantity == null
                  ? '${service.code} · ${service.unitKind.label}'
                  : '${service.code} · ${service.unitKind.label} · '
                        'min ${service.minimumQuantity} ${service.unitKind.quantityUnitLabel}',
            ),
            trailing: service.isActive
                ? null
                : const StatusChip(
                    label: 'Nonaktif',
                    icon: Icons.pause_circle_outline,
                    tone: StatusTone.neutral,
                  ),
          );
        },
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Price lists (FR-034 … FR-040)
// ---------------------------------------------------------------------------

class _PriceListsTab extends ConsumerWidget {
  const _PriceListsTab({required this.canPublish});

  final bool canPublish;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repository = ref.watch(masterDataRepositoryProvider);

    return _AsyncSection<PriceListSummary>(
      load: () => repository.priceLists(),
      emptyTitle: 'Belum ada daftar harga',
      emptyDescription:
          'Daftar harga dibuat per brand dan berlaku pada rentang tanggal tertentu.',
      builder: (context, items) => ListView.builder(
        itemCount: items.length,
        itemBuilder: (context, index) {
          final list = items[index];

          return ListTile(
            title: Text(list.name),
            subtitle: Text(
              list.effectiveUntil == null
                  ? '${list.code} · berlaku sejak ${list.effectiveFrom}'
                  : '${list.code} · ${list.effectiveFrom} s.d. ${list.effectiveUntil}',
            ),
            trailing: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                StatusChip(
                  label: list.status.label,
                  icon: switch (list.status) {
                    PriceListStatus.draft => Icons.edit_note_outlined,
                    PriceListStatus.active => Icons.check_circle_outline,
                    PriceListStatus.superseded => Icons.history_outlined,
                    PriceListStatus.archived => Icons.archive_outlined,
                  },
                  tone: switch (list.status) {
                    PriceListStatus.draft => StatusTone.information,
                    PriceListStatus.active => StatusTone.success,
                    PriceListStatus.superseded ||
                    PriceListStatus.archived => StatusTone.neutral,
                  },
                ),
                // PUBLISH IS OFFERED ONLY WHEN IT IS BOTH PERMITTED AND LEGAL.
                //
                // `isEditable` comes from the SERVER rather than being derived
                // from the status here — a second copy of the immutability rule
                // in the client could drift from the one that enforces it, and
                // a button the server would refuse is a dead end (Rule 29).
                if (canPublish && list.isEditable) ...<Widget>[
                  SizedBox(width: AishSpacing.space3),
                  TextButton(
                    onPressed: () => _confirmPublish(context, list),
                    child: const Text('Terbitkan'),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  /// Publishing is irreversible, so it is confirmed with the specific object and
  /// effect restated, and focus defaults to the SAFE choice.
  ///
  /// Rule 32 hard rules 14–15: confirmation strength scales with consequence,
  /// the destructive option is never the visual default, and the dialogue names
  /// what is about to happen rather than asking a generic "are you sure".
  Future<void> _confirmPublish(
    BuildContext context,
    PriceListSummary list,
  ) async {
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Terbitkan daftar harga ini?'),
        content: Text(
          'Daftar harga "${list.name}" (${list.code}) akan menjadi permanen dan '
          'tidak dapat diubah lagi. Harga ini yang akan ditagihkan kepada '
          'pelanggan sejak ${list.effectiveFrom}.\n\n'
          'Untuk mengubah harga setelahnya, Anda harus menerbitkan versi baru.',
        ),
        actions: <Widget>[
          // The safe choice is first and is the autofocused control.
          TextButton(
            autofocus: true,
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Ya, terbitkan'),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Staff assignment (ROADMAP Step 4 scope, FR-018)
// ---------------------------------------------------------------------------

class _StaffTab extends ConsumerWidget {
  const _StaffTab({required this.canAssign});

  final bool canAssign;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repository = ref.watch(masterDataRepositoryProvider);

    return _AsyncSection<StaffMember>(
      load: repository.staff,
      emptyTitle: 'Belum ada staf pada tenant ini',
      emptyDescription:
          'Anggota yang diundang ke tenant ini akan muncul di sini beserta '
          'peran dan outlet penugasannya.',
      builder: (context, items) => ListView.builder(
        itemCount: items.length,
        itemBuilder: (context, index) {
          final member = items[index];

          return ListTile(
            title: Text(member.userName),
            subtitle: Text(
              // Roles are shown for information. They are NOT what decides
              // anything here: the server re-derives permissions from live
              // membership on every request.
              member.roles.isEmpty
                  ? 'Belum memiliki peran · '
                        '${member.liveAssignments.length} outlet'
                  : '${member.roles.join(", ")} · '
                        '${member.liveAssignments.length} outlet',
            ),
            trailing: member.isActive
                ? null
                : StatusChip(
                    label: switch (member.status) {
                      'suspended' => 'Ditangguhkan',
                      'revoked' => 'Dicabut',
                      _ => 'Diundang',
                    },
                    icon: Icons.person_off_outlined,
                    tone: StatusTone.warning,
                  ),
          );
        },
      ),
    );
  }
}
