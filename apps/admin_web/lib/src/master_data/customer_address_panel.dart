import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'master_data_screens.dart' show masterDataRepositoryProvider;

/// FR-024 / FR-025 — saved addresses on the Console.
///
/// SHARES THE OPS CONTRACT RATHER THAN RESTATING IT. The repository, the
/// `EditOutcome` taxonomy and the server's `precision` marker are the same ones
/// the counter surface uses. A second implementation here would eventually
/// disagree with that one, and the disagreement that matters is a Console that
/// renders a street the counter is told to withhold.
///
/// MASKING IS NEVER PERFORMED IN DART. The Console renders exactly the
/// projection the server returned. At AREA precision the street was never
/// serialised, so there is no hidden value in this widget tree, in its state, or
/// in anything a browser devtools inspection could reach (FR-025, Rule 32 hard
/// rule 3).
///
/// Keyboard-complete: every action is reachable by keyboard in a defined focus
/// order, because the Console is a pointer-and-keyboard surface and a
/// mouse-only control is unusable for part of its audience (Rule 28 hard rule 8).
class CustomerAddressPanel extends ConsumerStatefulWidget {
  const CustomerAddressPanel({
    required this.customerId,
    required this.customerName,
    required this.canManage,
    super.key,
  });

  final String customerId;

  /// Shown in the heading so an operator managing several customers can see
  /// WHOSE addresses these are — context restated at the point of action
  /// (Rule 28 hard rule 2).
  final String customerName;

  final bool canManage;

  @override
  ConsumerState<CustomerAddressPanel> createState() =>
      _CustomerAddressPanelState();
}

class _CustomerAddressPanelState extends ConsumerState<CustomerAddressPanel> {
  AddressLedger? _ledger;
  Failure? _loadFailure;
  bool _loading = true;
  bool _busy = false;
  EditOutcome? _outcome;

  /// Bounded, client-side narrowing of an already-bounded list.
  ///
  /// It filters what the server already returned; it does not widen the query.
  /// A search that reached back for more rows would be a way to enumerate a
  /// tenant's customer base one keystroke at a time.
  String _filter = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadFailure = null;
    });

    final result = await ref
        .read(masterDataRepositoryProvider)
        .addresses(widget.customerId);

    if (!mounted) return;

    setState(() {
      _loading = false;
      result.fold((ledger) {
        _ledger = ledger;
        _loadFailure = null;
      }, (failure) => _loadFailure = failure);
    });
  }

  Future<void> _run(Future<EditOutcome> Function() action) async {
    setState(() {
      _busy = true;
      _outcome = null;
    });

    final outcome = await action();

    if (!mounted) return;

    setState(() {
      _busy = false;
      _outcome = outcome;
    });

    // Server-confirmed reload. Nothing is shown as saved until the server says
    // so, and the reload catches the second row a primary change moves.
    if (outcome is EditSaved) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final ledger = _ledger;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Semantics(
                header: true,
                child: Text(
                  'Alamat — ${widget.customerName}',
                  style: textTheme.titleMedium,
                ),
              ),
            ),
            if (widget.canManage && ledger != null)
              TextButton.icon(
                onPressed: _busy ? null : () => _openForm(null),
                icon: const Icon(Icons.add_location_alt_outlined),
                label: const Text('Tambah alamat'),
              ),
          ],
        ),
        SizedBox(height: AishSpacing.space3),

        if (_outcome != null) ...<Widget>[
          _outcomeBanner(_outcome!),
          SizedBox(height: AishSpacing.space3),
        ],

        if (_loading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 32),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_loadFailure != null)
          StateMessage(
            title: 'Alamat tidak dapat dimuat',
            description: 'Periksa koneksi lalu muat ulang.',
            icon: Icons.cloud_off_outlined,
            recoveryLabel: 'Muat ulang',
            onRecover: _load,
          )
        else if (ledger == null || ledger.isEmpty)
          const StateMessage(
            title: 'Belum ada alamat',
            description:
                'Alamat penjemputan dan pengantaran pelanggan akan muncul di '
                'sini setelah dicatat.',
            icon: Icons.location_off_outlined,
          )
        else ...<Widget>[
          if (ledger.precision != AddressPrecision.full)
            Padding(
              padding: EdgeInsets.only(bottom: AishSpacing.space2),
              child: Text(
                ledger.precision == AddressPrecision.area
                    ? 'Peran Anda hanya menampilkan wilayah, bukan alamat lengkap.'
                    : 'Peran Anda tidak menampilkan detail alamat.',
                style: textTheme.bodySmall?.copyWith(
                  color: AishSemanticColors.colorSemanticTextSecondary,
                ),
              ),
            ),
          TextField(
            decoration: const InputDecoration(
              labelText: 'Cari label alamat',
              prefixIcon: Icon(Icons.search),
            ),
            onChanged: (value) => setState(() => _filter = value.trim()),
          ),
          SizedBox(height: AishSpacing.space3),
          ..._visible(ledger).map(_row),
        ],
      ],
    );
  }

  List<CustomerAddress> _visible(AddressLedger ledger) {
    final all = <CustomerAddress>[...ledger.live, ...ledger.archived];

    if (_filter.isEmpty) return all;

    // Matched on the LABEL only. Filtering by street would require the street
    // to be in hand, which at AREA precision it is not, and offering the field
    // would imply the Console holds something it does not.
    final needle = _filter.toLowerCase();
    return all
        .where((a) => a.label.toLowerCase().contains(needle))
        .toList(growable: false);
  }

  Widget _row(CustomerAddress address) {
    final textTheme = Theme.of(context).textTheme;

    return Card(
      margin: EdgeInsets.only(bottom: AishSpacing.space2),
      child: Padding(
        padding: EdgeInsets.all(AishSpacing.space4),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Text(
                        address.label.isEmpty ? 'Alamat' : address.label,
                        style: textTheme.titleSmall,
                      ),
                      const SizedBox(width: 8),
                      if (address.isPrimary)
                        const StatusChip(
                          label: 'Utama',
                          icon: Icons.star_outline,
                          tone: StatusTone.information,
                        ),
                      if (!address.isActive) ...<Widget>[
                        const SizedBox(width: 8),
                        const StatusChip(
                          label: 'Nonaktif',
                          icon: Icons.block_outlined,
                          tone: StatusTone.neutral,
                        ),
                      ],
                    ],
                  ),
                  SizedBox(height: AishSpacing.space1),
                  // Rendered only when the server disclosed it. Plain text —
                  // automatic link detection stays off on a field an untrusted
                  // party populates (Rule 32 hard rule 24).
                  if (address.precision.includesStreet &&
                      address.addressLine.isNotEmpty)
                    Text(address.addressLine, style: textTheme.bodyMedium),
                  if (address.areaSummary.isNotEmpty)
                    Text(
                      address.areaSummary,
                      style: textTheme.bodySmall?.copyWith(
                        color: AishSemanticColors.colorSemanticTextSecondary,
                      ),
                    ),
                ],
              ),
            ),
            if (widget.canManage)
              Row(
                children: <Widget>[
                  if (address.isActive)
                    TextButton(
                      onPressed: _busy ? null : () => _openForm(address),
                      child: const Text('Ubah'),
                    ),
                  if (address.isActive)
                    TextButton(
                      onPressed: _busy ? null : () => _confirmArchive(address),
                      child: const Text('Nonaktifkan'),
                    )
                  else
                    TextButton(
                      onPressed: _busy
                          ? null
                          : () => _run(
                              () async => classifyEdit(
                                await ref
                                    .read(masterDataRepositoryProvider)
                                    .reactivateAddress(
                                      customerId: widget.customerId,
                                      addressId: address.id,
                                      expectedVersion: address.version,
                                    ),
                              ),
                            ),
                      child: const Text('Aktifkan kembali'),
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _outcomeBanner(EditOutcome outcome) {
    if (outcome is EditSaved) {
      return const StateMessage(
        title: 'Alamat tersimpan',
        description: 'Perubahan sudah dikonfirmasi oleh server.',
        icon: Icons.check_circle_outline,
        tone: StatusTone.success,
      );
    }

    final (String title, String description, IconData icon) = switch (outcome) {
      EditConflict() => (
        'Alamat ini baru saja diubah orang lain',
        'Muat ulang untuk melihat perubahan terbaru, lalu terapkan kembali '
            'suntingan Anda bila masih diperlukan.',
        Icons.sync_problem_outlined,
      ),
      EditRejected() => (
        'Data alamat belum lengkap atau tidak valid',
        'Periksa kembali isian yang ditandai lalu simpan ulang.',
        Icons.error_outline,
      ),
      EditDenied() => (
        'Tindakan ini tidak tersedia untuk peran Anda',
        'Hubungi admin tenant bila Anda memerlukan akses ini.',
        Icons.lock_outline,
      ),
      EditUnreachable() => (
        'Perubahan belum terkirim',
        'Periksa koneksi lalu coba lagi. Tidak ada perubahan yang tersimpan.',
        Icons.cloud_off_outlined,
      ),
      _ => (
        'Terjadi gangguan sementara',
        'Coba lagi beberapa saat lagi.',
        Icons.error_outline,
      ),
    };

    return StateMessage(
      title: title,
      description: description,
      icon: icon,
      tone: StatusTone.warning,
      // A conflict gets RELOAD and nothing else. Resending the identical
      // payload would succeed and destroy the other person's correction.
      recoveryLabel: outcome is EditConflict ? 'Muat ulang alamat' : 'Tutup',
      onRecover: outcome is EditConflict
          ? _load
          : () => setState(() => _outcome = null),
    );
  }

  Future<void> _confirmArchive(CustomerAddress address) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Nonaktifkan alamat?'),
        content: Text(
          'Alamat "${address.label}" milik ${widget.customerName} tidak akan '
          'muncul sebagai pilihan penjemputan atau pengantaran. Riwayatnya '
          'tetap tersimpan.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Nonaktifkan'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    await _run(
      () async => classifyEdit(
        await ref
            .read(masterDataRepositoryProvider)
            .archiveAddress(
              customerId: widget.customerId,
              addressId: address.id,
              expectedVersion: address.version,
            ),
      ),
    );
  }

  Future<void> _openForm(CustomerAddress? existing) async {
    final values = await showDialog<Map<String, Object?>>(
      context: context,
      builder: (dialogContext) => _AddressForm(existing: existing),
    );

    if (values == null) return;

    await _run(() async {
      final repository = ref.read(masterDataRepositoryProvider);

      final result = existing == null
          ? await repository.createAddress(
              customerId: widget.customerId,
              attributes: values,
            )
          : await repository.updateAddress(
              customerId: widget.customerId,
              addressId: existing.id,
              expectedVersion: existing.version,
              changes: values,
            );

      return classifyEdit(result);
    });
  }
}

class _AddressForm extends StatefulWidget {
  const _AddressForm({required this.existing});

  final CustomerAddress? existing;

  @override
  State<_AddressForm> createState() => _AddressFormState();
}

class _AddressFormState extends State<_AddressForm> {
  final _formKey = GlobalKey<FormState>();

  late final TextEditingController _label;
  late final TextEditingController _line;
  late final TextEditingController _district;
  late final TextEditingController _city;
  late final TextEditingController _province;
  late final TextEditingController _postal;

  late bool _pickup;
  late bool _delivery;
  late bool _primary;

  bool get _canEditStreet =>
      widget.existing == null || widget.existing!.precision.includesStreet;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _label = TextEditingController(text: e?.label ?? '');
    _line = TextEditingController(text: e?.addressLine ?? '');
    _district = TextEditingController(text: e?.district ?? '');
    _city = TextEditingController(text: e?.city ?? '');
    _province = TextEditingController(text: e?.province ?? '');
    _postal = TextEditingController(text: e?.postalCode ?? '');
    _pickup = e?.isPickupSuitable ?? true;
    _delivery = e?.isDeliverySuitable ?? true;
    _primary = e?.isPrimary ?? false;
  }

  @override
  void dispose() {
    for (final c in <TextEditingController>[
      _label,
      _line,
      _district,
      _city,
      _province,
      _postal,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.existing == null ? 'Tambah alamat' : 'Ubah alamat'),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                // Every field carries a visible label, not a placeholder. A
                // placeholder disappears once typing starts, which leaves a
                // screen-reader user and a distracted operator with an
                // unlabelled box (Rule 27).
                TextFormField(
                  controller: _label,
                  autofocus: true,
                  decoration: const InputDecoration(labelText: 'Label'),
                  validator: (v) => (v == null || v.trim().isEmpty)
                      ? 'Label wajib diisi.'
                      : null,
                ),
                if (_canEditStreet)
                  TextFormField(
                    controller: _line,
                    decoration: const InputDecoration(labelText: 'Alamat'),
                    maxLines: 2,
                    validator: (v) => (v == null || v.trim().isEmpty)
                        ? 'Alamat wajib diisi.'
                        : null,
                  ),
                TextFormField(
                  controller: _district,
                  decoration: const InputDecoration(labelText: 'Kelurahan'),
                ),
                TextFormField(
                  controller: _city,
                  decoration: const InputDecoration(labelText: 'Kota'),
                ),
                TextFormField(
                  controller: _province,
                  decoration: const InputDecoration(labelText: 'Provinsi'),
                ),
                TextFormField(
                  controller: _postal,
                  decoration: const InputDecoration(labelText: 'Kode pos'),
                  validator: (v) {
                    final text = v?.trim() ?? '';
                    if (text.isEmpty) return null;
                    return RegExp(r'^[0-9]{5}$').hasMatch(text)
                        ? null
                        : 'Kode pos terdiri dari 5 angka.';
                  },
                ),
                SwitchListTile(
                  value: _pickup,
                  onChanged: (v) => setState(() => _pickup = v),
                  title: const Text('Bisa dijemput'),
                ),
                SwitchListTile(
                  value: _delivery,
                  onChanged: (v) => setState(() => _delivery = v),
                  title: const Text('Bisa diantar'),
                ),
                SwitchListTile(
                  value: _primary,
                  onChanged: (v) => setState(() => _primary = v),
                  title: const Text('Jadikan alamat utama'),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        TextButton(
          onPressed: () {
            if (_formKey.currentState?.validate() != true) return;

            Navigator.of(context).pop(<String, Object?>{
              'label': _label.text.trim(),
              if (_canEditStreet) 'address_line': _line.text.trim(),
              'district': _district.text.trim(),
              'city': _city.text.trim(),
              'province': _province.text.trim(),
              if (_postal.text.trim().isNotEmpty)
                'postal_code': _postal.text.trim(),
              'is_pickup_suitable': _pickup,
              'is_delivery_suitable': _delivery,
              'is_primary': _primary,
            });
          },
          child: const Text('Simpan'),
        ),
      ],
    );
  }
}
