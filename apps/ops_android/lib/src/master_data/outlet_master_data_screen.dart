import 'package:aish_networking/aish_networking.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/ops_routes.dart';
import 'master_data_providers.dart';
import 'master_data_views.dart';
import 'ops_master_data_scaffold.dart';

/// OUTLET MASTER DATA (FR-041 … FR-047).
///
/// Operating hours, capacity, quiet hours, service zones, shift definitions and
/// printer configuration for the outlet the operator is currently working in.
///
/// WHAT IS CONFIGURED HERE IS NOT WHAT CONSUMES IT.
/// A shift DEFINITION is Step 4; closing a shift and reconciling its cash is
/// Step 5. A service ZONE is a coverage definition; routing a courier through it
/// is Step 8. A PRINTER is a device configuration; the document it prints is
/// FR-052 in Step 5, and `nota`, `struk` and `receipt` remain forbidden labels
/// (DEC-0030). Quiet hours are recorded here; deferring a message into them is
/// Step 7.
///
/// THE EDIT PATH CARRIES THE VERSION TOKEN THE READ RETURNED.
/// Two managers editing the same outlet must not silently overwrite each other.
/// A stale write produces `CONFLICT`, and this screen answers with a RELOAD
/// action and no retry — resending the payload would succeed and destroy the
/// other edit (threat T-12).
class OutletMasterDataScreen extends ConsumerStatefulWidget {
  const OutletMasterDataScreen({super.key});

  @override
  ConsumerState<OutletMasterDataScreen> createState() =>
      _OutletMasterDataScreenState();
}

class _OutletMasterDataScreenState
    extends ConsumerState<OutletMasterDataScreen> {
  Future<Result<OutletMasterData>>? _record;
  String? _loadedForOutletId;

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;

    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    final outlet = session.activeOutlet;

    // NO OUTLET SELECTED is a real state with a real recovery, not a blank
    // screen. It also cannot be worked around by typing an id: this screen
    // never accepts an outlet identifier from anywhere except the server-
    // verified session, so there is no parameter to tamper with.
    if (outlet == null) {
      return OpsMasterDataScaffold(
        title: 'Data outlet',
        session: session,
        onBack: () => context.go(OpsRoutes.home),
        body: StateMessage(
          title: 'Belum ada outlet aktif',
          description:
              'Pilih outlet tempat Anda bekerja terlebih dahulu. Data outlet '
              'hanya dapat dibuka untuk outlet yang sedang aktif.',
          icon: Icons.storefront_outlined,
          tone: StatusTone.information,
          recoveryLabel: 'Pilih outlet',
          onRecover: () => context.go(OpsRoutes.selectOutlet),
        ),
      );
    }

    // A TENANT OR OUTLET SWITCH INVALIDATES WHAT IS ON SCREEN.
    // Re-keying the load on the active outlet id means the previous outlet's
    // record cannot survive a context change and be edited under the new one's
    // name — which would be a cross-context write, not merely a stale view
    // (Rule 28 hard rule 3).
    if (_loadedForOutletId != outlet.id) {
      _loadedForOutletId = outlet.id;
      _record = ref
          .read(masterDataRepositoryProvider)
          .outletMasterData(outlet.id);
    }

    return OpsMasterDataScaffold(
      title: 'Data outlet',
      session: session,
      onBack: () => context.go(OpsRoutes.home),
      body: FutureBuilder<Result<OutletMasterData>>(
        future: _record,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return Center(
              child: Semantics(
                label: 'Memuat data outlet',
                child: const SizedBox(
                  width: 32,
                  height: 32,
                  child: CircularProgressIndicator(),
                ),
              ),
            );
          }

          final result = snapshot.data;
          if (result == null) {
            return StateMessage(
              title: 'Data outlet tidak dapat ditampilkan',
              description: 'Muat ulang layar ini untuk mencoba lagi.',
              icon: Icons.error_outline,
              tone: StatusTone.danger,
              recoveryLabel: 'Muat ulang',
              onRecover: _reload,
            );
          }

          return result.fold(
            (OutletMasterData data) => _OutletForm(
              // Keyed on the version so a reload after a conflict rebuilds the
              // read-only fields against the record that now exists, rather
              // than leaving a stale `initialValue` behind.
              key: ValueKey<String>('${data.id}:${data.version}'),
              outlet: data,
              session: session,
              onReload: _reload,
            ),
            (Failure failure) =>
                OpsFailureState(failure: failure, onRetry: _reload),
          );
        },
      ),
    );
  }

  void _reload() {
    final outletId = _loadedForOutletId;
    if (outletId == null) {
      return;
    }
    setState(() {
      _record = ref
          .read(masterDataRepositoryProvider)
          .outletMasterData(outletId);
    });
  }
}

class _OutletForm extends ConsumerStatefulWidget {
  const _OutletForm({
    required this.outlet,
    required this.session,
    required this.onReload,
    super.key,
  });

  final OutletMasterData outlet;
  final SessionState session;
  final VoidCallback onReload;

  @override
  ConsumerState<_OutletForm> createState() => _OutletFormState();
}

class _OutletFormState extends ConsumerState<_OutletForm> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();

  late final TextEditingController _name = TextEditingController(
    text: widget.outlet.name,
  );
  late final TextEditingController _addressLine = TextEditingController(
    text: widget.outlet.addressLine ?? '',
  );
  late final TextEditingController _contactPhone = TextEditingController(
    text: widget.outlet.contactPhone ?? '',
  );
  late final TextEditingController _capacityKg = TextEditingController(
    text: widget.outlet.dailyCapacityKg?.toString() ?? '',
  );
  late final TextEditingController _capacityOrders = TextEditingController(
    text: widget.outlet.dailyCapacityOrders?.toString() ?? '',
  );
  late final TextEditingController _quietStart = TextEditingController(
    text: widget.outlet.quietHoursStart,
  );
  late final TextEditingController _quietEnd = TextEditingController(
    text: widget.outlet.quietHoursEnd,
  );
  late bool _isActive = widget.outlet.isActive;

  bool _submitting = false;
  EditOutcome? _outcome;

  @override
  void dispose() {
    _name.dispose();
    _addressLine.dispose();
    _contactPhone.dispose();
    _capacityKg.dispose();
    _capacityOrders.dispose();
    _quietStart.dispose();
    _quietEnd.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_form.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      _submitting = true;
      _outcome = null;
    });

    final outcome = classifyEdit(
      await ref
          .read(masterDataRepositoryProvider)
          .updateOutletMasterData(
            outletId: widget.outlet.id,
            // The token that came with THIS record. Sending it is what turns a
            // concurrent edit into a refused request instead of a silent
            // overwrite. Note it is NOT a timestamp: `updated_at` is
            // second-precision and blind to two edits inside one second.
            expectedVersion: widget.outlet.version,
            changes: <String, Object?>{
              'name': _name.text.trim(),
              'address_line': _nullIfBlank(_addressLine.text),
              'contact_phone': _nullIfBlank(_contactPhone.text),
              // INTEGER, parsed strictly. A capacity is a count; letting a
              // decimal through here would put a fractional kilogram into a
              // field a later shift report reads.
              'daily_capacity_kg': _intOrNull(_capacityKg.text),
              'daily_capacity_orders': _intOrNull(_capacityOrders.text),
              'quiet_hours_start': _quietStart.text.trim(),
              'quiet_hours_end': _quietEnd.text.trim(),
              'is_active': _isActive,
            },
          ),
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _submitting = false;
      _outcome = outcome;
    });

    if (outcome is EditSaved<OutletMasterData>) {
      widget.onReload();
    }
  }

  /// Reload the server's current record, keeping every field the operator typed.
  ///
  /// The ONLY action offered after a conflict. It does not resubmit anything.
  void _reloadKeepingInput() {
    setState(() => _outcome = null);
    widget.onReload();
  }

  static String? _nullIfBlank(String value) =>
      value.trim().isEmpty ? null : value.trim();

  static int? _intOrNull(String value) =>
      value.trim().isEmpty ? null : int.tryParse(value.trim());

  @override
  Widget build(BuildContext context) {
    final outlet = widget.outlet;
    final textTheme = Theme.of(context).textTheme;
    final bool canManage = widget.session.allows(
      Permission.outletMasterDataManage,
    );

    final outcome = _outcome;
    final rejected = outcome is EditRejected ? outcome : null;

    return Form(
      key: _form,
      child: ListView(
        padding: EdgeInsets.all(AishSpacing.space4),
        children: <Widget>[
          // THE CONFLICT RENDERING — reload only, never retry.
          if (outcome is EditConflict) ...<Widget>[
            StaleWriteNotice(
              conflict: outcome,
              recordLabel: 'Data outlet ${outlet.name} (${outlet.code})',
              onReload: _reloadKeepingInput,
            ),
            SizedBox(height: AishSpacing.space4),
          ] else if (outcome is EditSaved) ...<Widget>[
            Semantics(
              liveRegion: true,
              container: true,
              child: const StateMessage(
                title: 'Perubahan tersimpan',
                description:
                    'Data outlet telah diperbarui di server dan sudah berlaku.',
                icon: Icons.check_circle_outline,
                tone: StatusTone.success,
              ),
            ),
            SizedBox(height: AishSpacing.space4),
          ] else if (outcome != null) ...<Widget>[
            _OutletOutcomeBanner(outcome: outcome),
            SizedBox(height: AishSpacing.space4),
          ],

          Row(
            children: <Widget>[
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(outlet.name, style: textTheme.titleLarge),
                ),
              ),
              StatusChip(
                label: outlet.isActive ? 'Aktif' : 'Nonaktif',
                icon: outlet.isActive
                    ? Icons.check_circle_outline
                    : Icons.pause_circle_outline,
                tone: outlet.isActive ? StatusTone.success : StatusTone.neutral,
              ),
            ],
          ),
          SizedBox(height: AishSpacing.space1),
          Text(
            '${outlet.code} · ${outlet.timezone}',
            style: textTheme.bodySmall,
          ),

          SizedBox(height: AishSpacing.space6),

          // A READ-ONLY VIEW WHEN THE PERMISSION IS ABSENT.
          // The form is not rendered disabled — a control the user may not use
          // is not rendered at all (Rule 28 hard rule 5). The data itself is
          // still shown, because reading is a separate permission the server
          // already granted by answering this request.
          if (!canManage) ...<Widget>[
            const StateMessage(
              title: 'Anda hanya dapat melihat data outlet ini',
              description:
                  'Perubahan data outlet memerlukan izin tersendiri. Hubungi '
                  'admin tenant Anda bila Anda memerlukannya.',
              icon: Icons.lock_outline,
              tone: StatusTone.information,
            ),
            SizedBox(height: AishSpacing.space4),
            _ReadOnlyOutlet(outlet: outlet),
          ] else ...<Widget>[
            TextFormField(
              controller: _name,
              decoration: InputDecoration(
                labelText: 'Nama outlet',
                border: const OutlineInputBorder(),
                errorText: rejected?.fieldErrors['name']?.first,
              ),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? 'Nama outlet wajib diisi.'
                  : null,
            ),
            SizedBox(height: AishSpacing.space4),
            TextFormField(
              controller: _addressLine,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: 'Alamat outlet',
                border: const OutlineInputBorder(),
                errorText: rejected?.fieldErrors['address_line']?.first,
              ),
            ),
            SizedBox(height: AishSpacing.space4),
            TextFormField(
              controller: _contactPhone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: 'Telepon outlet',
                border: const OutlineInputBorder(),
                errorText: rejected?.fieldErrors['contact_phone']?.first,
              ),
            ),

            SizedBox(height: AishSpacing.space6),
            Text('Kapasitas harian', style: textTheme.titleMedium),
            SizedBox(height: AishSpacing.space1),
            Text(
              'Dipakai untuk memperkirakan beban outlet. Kosongkan bila belum '
              'ditentukan.',
              style: textTheme.bodySmall?.copyWith(
                color: AishSemanticColors.colorSemanticTextSecondary,
              ),
            ),
            SizedBox(height: AishSpacing.space3),
            TextFormField(
              controller: _capacityKg,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: 'Kapasitas (kg per hari)',
                border: const OutlineInputBorder(),
                errorText: rejected?.fieldErrors['daily_capacity_kg']?.first,
              ),
              validator: _wholeNumberOrEmpty,
            ),
            SizedBox(height: AishSpacing.space4),
            TextFormField(
              controller: _capacityOrders,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: 'Kapasitas (pesanan per hari)',
                border: const OutlineInputBorder(),
                errorText:
                    rejected?.fieldErrors['daily_capacity_orders']?.first,
              ),
              validator: _wholeNumberOrEmpty,
            ),

            SizedBox(height: AishSpacing.space6),
            Text('Jam tenang', style: textTheme.titleMedium),
            SizedBox(height: AishSpacing.space1),
            Text(
              // Says what it does AND what it does not do yet. Claiming the app
              // already defers messages would be a false claim: sending is
              // Step 7 (Rule 01).
              'Waktu lokal outlet. Dicatat sekarang; penundaan pengiriman pesan '
              'dibangun pada langkah berikutnya.',
              style: textTheme.bodySmall?.copyWith(
                color: AishSemanticColors.colorSemanticTextSecondary,
              ),
            ),
            SizedBox(height: AishSpacing.space3),
            Row(
              children: <Widget>[
                Expanded(
                  child: TextFormField(
                    controller: _quietStart,
                    decoration: InputDecoration(
                      labelText: 'Mulai (JJ:MM)',
                      border: const OutlineInputBorder(),
                      errorText:
                          rejected?.fieldErrors['quiet_hours_start']?.first,
                    ),
                    validator: _timeOfDay,
                  ),
                ),
                SizedBox(width: AishSpacing.space3),
                Expanded(
                  child: TextFormField(
                    controller: _quietEnd,
                    decoration: InputDecoration(
                      labelText: 'Selesai (JJ:MM)',
                      border: const OutlineInputBorder(),
                      errorText:
                          rejected?.fieldErrors['quiet_hours_end']?.first,
                    ),
                    validator: _timeOfDay,
                  ),
                ),
              ],
            ),

            SizedBox(height: AishSpacing.space6),
            SwitchListTile(
              value: _isActive,
              onChanged: (value) => setState(() => _isActive = value),
              title: const Text('Outlet aktif'),
              subtitle: const Text(
                'Outlet nonaktif tidak lagi dipilih untuk operasi harian. '
                'Datanya tetap tersimpan dan tidak dihapus.',
              ),
            ),

            SizedBox(height: AishSpacing.space6),
            CommitContextLine(
              session: widget.session,
              action: 'Simpan perubahan data outlet',
            ),
            SizedBox(height: AishSpacing.space3),
            PrimaryAction(
              label: 'Simpan perubahan',
              icon: Icons.save_outlined,
              isBusy: _submitting,
              expand: true,
              onPressed: _submitting ? null : _save,
            ),
          ],

          SizedBox(height: AishSpacing.space8),
          _OutletSatellites(outletId: outlet.id),
        ],
      ),
    );
  }

  static String? _wholeNumberOrEmpty(String? value) {
    if (value == null || value.trim().isEmpty) {
      return null;
    }
    final parsed = int.tryParse(value.trim());
    if (parsed == null) {
      return 'Isi dengan angka bulat, tanpa titik atau koma.';
    }
    return parsed < 0 ? 'Kapasitas tidak boleh negatif.' : null;
  }

  static String? _timeOfDay(String? value) {
    final text = value?.trim() ?? '';
    if (text.isEmpty) {
      return 'Wajib diisi, format JJ:MM.';
    }
    final match = RegExp(r'^([01]\d|2[0-3]):([0-5]\d)$').firstMatch(text);
    return match == null ? 'Gunakan format 24 jam, contoh 20:00.' : null;
  }
}

class _ReadOnlyOutlet extends StatelessWidget {
  const _ReadOnlyOutlet({required this.outlet});

  final OutletMasterData outlet;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      _row(context, 'Alamat', outlet.addressLine ?? '—'),
      _row(context, 'Telepon', outlet.contactPhone ?? '—'),
      _row(
        context,
        'Kapasitas',
        outlet.dailyCapacityKg == null && outlet.dailyCapacityOrders == null
            ? 'Belum ditentukan'
            : '${outlet.dailyCapacityKg ?? "—"} kg · '
                  '${outlet.dailyCapacityOrders ?? "—"} pesanan per hari',
      ),
      _row(
        context,
        'Jam tenang',
        '${outlet.quietHoursStart} – ${outlet.quietHoursEnd}',
      ),
    ],
  );

  Widget _row(BuildContext context, String label, String value) => Padding(
    padding: EdgeInsets.only(bottom: AishSpacing.space3),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          label,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: AishSemanticColors.colorSemanticTextSecondary,
          ),
        ),
        Text(value, style: Theme.of(context).textTheme.bodyLarge),
      ],
    ),
  );
}

/// Zones, shifts and printers — read-only on the counter surface.
///
/// They are shown because a counter operator needs to know what the outlet is
/// configured for. They are not edited here: each is its own satellite with its
/// own permission, and the console is where they are managed. Rendering an edit
/// control the counter role will never hold would be a dead end (Rule 29).
class _OutletSatellites extends ConsumerWidget {
  const _OutletSatellites({required this.outletId});

  final String outletId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repository = ref.watch(masterDataRepositoryProvider);
    final textTheme = Theme.of(context).textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Semantics(
          header: true,
          child: Text('Konfigurasi outlet', style: textTheme.titleMedium),
        ),
        SizedBox(height: AishSpacing.space3),

        _SatelliteList<OutletShift>(
          title: 'Definisi shift',
          // States the boundary in the interface, not only in a comment. An
          // operator must not read a shift definition as a shift that can be
          // closed — closing and cash reconciliation are Step 5.
          notice:
              'Definisi jadwal kerja. Penutupan shift dan penghitungan selisih '
              'kas dibangun pada langkah berikutnya.',
          load: () => repository.shifts(outletId),
          empty: 'Belum ada definisi shift untuk outlet ini.',
          label: (shift) =>
              '${shift.name} · ${shift.startsAt}–${shift.endsAt}'
              '${shift.crossesMidnight ? " (melewati tengah malam)" : ""}',
          isActive: (shift) => shift.isActive,
        ),

        _SatelliteList<OutletServiceZone>(
          title: 'Zona layanan',
          notice:
              'Definisi cakupan wilayah. Penjadwalan dan rute kurir dibangun '
              'pada langkah berikutnya.',
          load: () => repository.serviceZones(outletId),
          empty: 'Belum ada zona layanan untuk outlet ini.',
          label: (zone) => zone.postalCodes.isEmpty
              ? zone.name
              : '${zone.name} · ${zone.postalCodes.join(", ")}',
          isActive: (zone) => zone.isActive,
        ),

        _SatelliteList<OutletPrinter>(
          title: 'Printer',
          // FR-045 is printer CONFIGURATION. The document is FR-052 in Step 5,
          // and the words for it remain forbidden here (DEC-0030).
          notice:
              'Konfigurasi perangkat printer. Dokumen yang dicetak dibangun '
              'pada langkah berikutnya.',
          load: () => repository.printers(outletId),
          empty: 'Belum ada printer yang dikonfigurasi untuk outlet ini.',
          label: (printer) => printer.name,
          isActive: (printer) => printer.isActive,
        ),
      ],
    );
  }
}

class _SatelliteList<T> extends StatefulWidget {
  const _SatelliteList({
    required this.title,
    required this.notice,
    required this.load,
    required this.empty,
    required this.label,
    required this.isActive,
  });

  final String title;
  final String notice;
  final Future<Result<List<T>>> Function() load;
  final String empty;
  final String Function(T) label;
  final bool Function(T) isActive;

  @override
  State<_SatelliteList<T>> createState() => _SatelliteListState<T>();
}

class _SatelliteListState<T> extends State<_SatelliteList<T>> {
  /// Started ONCE, in the state, never in `build`.
  ///
  /// A `FutureBuilder` handed a fresh future on every build re-runs its request
  /// on every rebuild and never settles — the rebuild triggers the request,
  /// which triggers the rebuild. It is also a request storm against the server.
  late final Future<Result<List<T>>> _future = widget.load();

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final title = widget.title;
    final empty = widget.empty;

    return Padding(
      padding: EdgeInsets.only(bottom: AishSpacing.space5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(title, style: textTheme.titleSmall),
          SizedBox(height: AishSpacing.space1),
          Text(
            widget.notice,
            style: textTheme.bodySmall?.copyWith(
              color: AishSemanticColors.colorSemanticTextSecondary,
            ),
          ),
          SizedBox(height: AishSpacing.space2),
          FutureBuilder<Result<List<T>>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return Semantics(
                  label: 'Memuat $title',
                  child: const LinearProgressIndicator(),
                );
              }

              final result = snapshot.data;
              if (result == null) {
                return Text(
                  '$title tidak dapat ditampilkan.',
                  style: textTheme.bodySmall,
                );
              }

              return result.fold(
                (List<T> items) => items.isEmpty
                    ? Text(empty, style: textTheme.bodySmall)
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: items
                            .map(
                              (item) => Padding(
                                padding: EdgeInsets.only(
                                  bottom: AishSpacing.space1,
                                ),
                                child: Row(
                                  children: <Widget>[
                                    Expanded(child: Text(widget.label(item))),
                                    if (!widget.isActive(item))
                                      const StatusChip(
                                        label: 'Nonaktif',
                                        icon: Icons.pause_circle_outline,
                                        tone: StatusTone.neutral,
                                      ),
                                  ],
                                ),
                              ),
                            )
                            .toList(growable: false),
                      ),
                (Failure failure) => Text(
                  failure.kind == FailureKind.authorization
                      ? 'Anda tidak memiliki akses ke bagian ini.'
                      : '$title gagal dimuat.',
                  style: textTheme.bodySmall,
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

class _OutletOutcomeBanner extends StatelessWidget {
  const _OutletOutcomeBanner({required this.outcome});

  final EditOutcome outcome;

  @override
  Widget build(BuildContext context) {
    final message = messageForOutcome(outcome);
    if (message == null) {
      return const SizedBox.shrink();
    }

    return Semantics(
      liveRegion: true,
      container: true,
      child: StateMessage(
        title: message.title,
        description: message.description,
        icon: message.icon,
        tone: message.tone,
        supportReference: switch (outcome) {
          EditConflict(:final failure) ||
          EditRejected(:final failure) ||
          EditDenied(:final failure) ||
          EditUnreachable(:final failure) ||
          EditUnavailable(:final failure) => failure.correlationId,
          EditSaved<Object?>() => null,
        },
      ),
    );
  }
}
