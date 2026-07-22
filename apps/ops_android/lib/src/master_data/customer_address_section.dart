import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'edit_outcome.dart';
import 'master_data_providers.dart';

/// FR-024 / FR-025 — saved addresses, managed from the customer detail screen.
///
/// THE SERVER DECIDES WHAT IS VISIBLE. This widget renders the projection it was
/// given and never reconstructs a fuller value from a narrower one. There is
/// nothing to reconstruct: at AREA precision the street was never serialised, so
/// it is absent from the model, absent from this widget's state, and absent from
/// anything the widget tree could be asked to dump. Masking that lived here
/// would be the control's own bypass (Rule 32 hard rule 3, FR-025).
///
/// A CONFLICT IS NOT A RETRY. HTTP 409 means somebody else changed this address
/// while it was open. The recovery is reload-and-review, and the identical
/// payload is never resent: resending a conflicting write SUCCEEDS and destroys
/// the other person's correction. `EditOutcome` carries that distinction in the
/// type so this surface cannot collapse it into a generic error (threat T-12).
///
/// A CONFLICT ALSO DOES NOT END THE SESSION. It is scoped to one record. The
/// operator stays signed in, their credential is untouched, and what they typed
/// is kept so a reload does not cost them the edit.
class CustomerAddressSection extends ConsumerStatefulWidget {
  const CustomerAddressSection({
    required this.customerId,
    required this.canManage,
    super.key,
  });

  final String customerId;

  /// Whether this operator may write. Hiding a control is a courtesy, never the
  /// access control — the server refuses regardless (Rule 40 hard rule 2).
  final bool canManage;

  @override
  ConsumerState<CustomerAddressSection> createState() =>
      CustomerAddressSectionState();
}

@visibleForTesting
class CustomerAddressSectionState
    extends ConsumerState<CustomerAddressSection> {
  AddressLedger? _ledger;
  Object? _loadFailure;
  bool _loading = true;
  bool _busy = false;

  /// The last non-success outcome, kept so the surface can explain itself
  /// rather than reverting to a bare list.
  EditOutcome? _outcome;

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

  /// Runs a write and ADOPTS THE SERVER'S ANSWER.
  ///
  /// Nothing is rendered as saved before the server confirms it. An optimistic
  /// row here would show an address as stored while the request is still in
  /// flight, and a failure would then have to un-show it — which is how an
  /// operator ends up believing a delivery address exists when it does not
  /// (Rule 29 hard rule 4's principle, applied to master data).
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

    if (outcome is EditSaved) {
      // Re-read from the server rather than splicing the returned record into
      // the local list. The write may have changed a SECOND row — setting a new
      // primary demotes the old one — and a spliced list would show two
      // primaries until something else forced a refresh.
      await _load();
    }
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
                child: Text('Alamat', style: textTheme.titleMedium),
              ),
            ),
            if (widget.canManage && ledger != null)
              TextButton.icon(
                onPressed: _busy ? null : () => _openForm(context, null),
                icon: const Icon(Icons.add_location_alt_outlined),
                label: const Text('Tambah alamat'),
              ),
          ],
        ),
        SizedBox(height: AishSpacing.space2),

        if (_outcome != null) ...<Widget>[
          _OutcomeBanner(
            outcome: _outcome!,
            onReload: _busy ? null : _load,
            onDismiss: () => setState(() => _outcome = null),
          ),
          SizedBox(height: AishSpacing.space3),
        ],

        if (_loading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_loadFailure != null)
          StateMessage(
            title: 'Alamat tidak dapat dimuat',
            description:
                'Periksa koneksi lalu muat ulang. Data pelanggan lain di '
                'halaman ini tidak terpengaruh.',
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
          // The precision the server would apply on a DETAIL read, stated
          // rather than implied. An operator seeing only an area needs to know
          // the product is withholding the street deliberately, not that
          // somebody failed to type it in.
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
          ...ledger.live.map(
            (address) => _AddressCard(
              address: address,
              canManage: widget.canManage,
              busy: _busy,
              onEdit: () => _openForm(context, address),
              onArchive: () => _confirmArchive(context, address),
              onReactivate: null,
            ),
          ),
          ...ledger.archived.map(
            (address) => _AddressCard(
              address: address,
              canManage: widget.canManage,
              busy: _busy,
              onEdit: null,
              onArchive: null,
              onReactivate: () => _run(
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
            ),
          ),
        ],
      ],
    );
  }

  Future<void> _confirmArchive(
    BuildContext context,
    CustomerAddress address,
  ) async {
    // Destructive, so it is confirmed and the specific object is restated —
    // never a bare "Anda yakin?" (Rule 32 hard rule 14).
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Nonaktifkan alamat?'),
        content: Text(
          'Alamat "${address.label}" tidak akan muncul sebagai pilihan '
          'penjemputan atau pengantaran. Riwayat alamat ini tetap tersimpan '
          'dan dapat diaktifkan kembali.',
        ),
        actions: <Widget>[
          // Focus defaults to the safe choice by placing it first.
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

  Future<void> _openForm(
    BuildContext context,
    CustomerAddress? existing,
  ) async {
    final values = await showDialog<Map<String, Object?>>(
      context: context,
      builder: (dialogContext) => _AddressFormDialog(existing: existing),
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
              // The version read WITH the record. Omitting it would be
              // last-write-wins on a delivery address.
              expectedVersion: existing.version,
              changes: values,
            );

      return classifyEdit(result);
    });
  }
}

/// Explains a non-success outcome, and offers the RIGHT recovery for each.
class _OutcomeBanner extends StatelessWidget {
  const _OutcomeBanner({
    required this.outcome,
    required this.onReload,
    required this.onDismiss,
  });

  final EditOutcome outcome;
  final VoidCallback? onReload;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
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
            'suntingan Anda bila masih diperlukan. Perubahan Anda tidak '
            'dikirim ulang secara otomatis.',
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
      // RELOAD, never a generic "coba lagi", for a conflict specifically. The
      // identical payload must not be resendable from here: it would succeed
      // and overwrite the change that caused the conflict.
      recoveryLabel: outcome is EditConflict ? 'Muat ulang alamat' : 'Tutup',
      onRecover: outcome is EditConflict ? onReload : onDismiss,
    );
  }
}

/// One saved address, rendered at whatever precision the server disclosed.
class _AddressCard extends StatelessWidget {
  const _AddressCard({
    required this.address,
    required this.canManage,
    required this.busy,
    required this.onEdit,
    required this.onArchive,
    required this.onReactivate,
  });

  final CustomerAddress address;
  final bool canManage;
  final bool busy;
  final VoidCallback? onEdit;
  final VoidCallback? onArchive;
  final VoidCallback? onReactivate;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Card(
      margin: EdgeInsets.only(bottom: AishSpacing.space3),
      child: Padding(
        padding: EdgeInsets.all(AishSpacing.space4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    address.label.isEmpty ? 'Alamat' : address.label,
                    style: textTheme.titleSmall,
                  ),
                ),
                // Status carries text AND an icon, never colour alone
                // (Rule 27 hard rule 3).
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
            SizedBox(height: AishSpacing.space2),

            // Rendered ONLY when the server disclosed it. At AREA precision
            // `addressLine` is not merely blanked here — it never arrived, so
            // there is no hidden value in this widget's state to recover.
            //
            // Rendered as plain text. Automatic link detection is OFF on any
            // field an untrusted party can populate: a "map" affordance built
            // from customer-supplied text is a navigation path out of the app
            // driven by content nobody reviewed (Rule 32 hard rule 24).
            if (address.precision.includesStreet &&
                address.addressLine.isNotEmpty)
              Text(address.addressLine, style: textTheme.bodyMedium),

            if (address.areaSummary.isNotEmpty) ...<Widget>[
              SizedBox(height: AishSpacing.space1),
              Text(
                address.areaSummary,
                style: textTheme.bodySmall?.copyWith(
                  color: AishSemanticColors.colorSemanticTextSecondary,
                ),
              ),
            ],

            SizedBox(height: AishSpacing.space2),
            Wrap(
              spacing: AishSpacing.space2,
              children: <Widget>[
                if (address.isPickupSuitable)
                  const StatusChip(
                    label: 'Bisa dijemput',
                    icon: Icons.local_shipping_outlined,
                    tone: StatusTone.neutral,
                  ),
                if (address.isDeliverySuitable)
                  const StatusChip(
                    label: 'Bisa diantar',
                    icon: Icons.home_outlined,
                    tone: StatusTone.neutral,
                  ),
              ],
            ),

            if (canManage) ...<Widget>[
              SizedBox(height: AishSpacing.space2),
              Row(
                children: <Widget>[
                  if (onEdit != null)
                    ConstrainedBox(
                      constraints: BoxConstraints(
                        minHeight: AishSizing.sizeTouchMin,
                        minWidth: AishSizing.sizeTouchMin,
                      ),
                      child: TextButton(
                        onPressed: busy ? null : onEdit,
                        child: const Text('Ubah'),
                      ),
                    ),
                  if (onArchive != null)
                    ConstrainedBox(
                      constraints: BoxConstraints(
                        minHeight: AishSizing.sizeTouchMin,
                        minWidth: AishSizing.sizeTouchMin,
                      ),
                      child: TextButton(
                        onPressed: busy ? null : onArchive,
                        child: const Text('Nonaktifkan'),
                      ),
                    ),
                  if (onReactivate != null)
                    ConstrainedBox(
                      constraints: BoxConstraints(
                        minHeight: AishSizing.sizeTouchMin,
                        minWidth: AishSizing.sizeTouchMin,
                      ),
                      child: TextButton(
                        onPressed: busy ? null : onReactivate,
                        child: const Text('Aktifkan kembali'),
                      ),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Create or edit, in one form.
///
/// The street field is offered ONLY when the operator already sees the street.
/// Presenting an empty street input to somebody the server masks it from would
/// invite them to type one in and overwrite a value they cannot read.
class _AddressFormDialog extends StatefulWidget {
  const _AddressFormDialog({required this.existing});

  final CustomerAddress? existing;

  @override
  State<_AddressFormDialog> createState() => _AddressFormDialogState();
}

class _AddressFormDialogState extends State<_AddressFormDialog> {
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
    final existing = widget.existing;

    _label = TextEditingController(text: existing?.label ?? '');
    _line = TextEditingController(text: existing?.addressLine ?? '');
    _district = TextEditingController(text: existing?.district ?? '');
    _city = TextEditingController(text: existing?.city ?? '');
    _province = TextEditingController(text: existing?.province ?? '');
    _postal = TextEditingController(text: existing?.postalCode ?? '');
    _pickup = existing?.isPickupSuitable ?? true;
    _delivery = existing?.isDeliverySuitable ?? true;
    _primary = existing?.isPrimary ?? false;
  }

  @override
  void dispose() {
    for (final controller in <TextEditingController>[
      _label,
      _line,
      _district,
      _city,
      _province,
      _postal,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.existing == null ? 'Tambah alamat' : 'Ubah alamat'),
      content: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              TextFormField(
                controller: _label,
                decoration: const InputDecoration(
                  labelText: 'Label',
                  helperText: 'Misalnya Rumah atau Kantor',
                ),
                validator: (value) => (value == null || value.trim().isEmpty)
                    ? 'Label wajib diisi.'
                    : null,
              ),
              if (_canEditStreet)
                TextFormField(
                  controller: _line,
                  decoration: const InputDecoration(labelText: 'Alamat'),
                  maxLines: 2,
                  validator: (value) => (value == null || value.trim().isEmpty)
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
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Kode pos'),
                validator: (value) {
                  final text = value?.trim() ?? '';
                  if (text.isEmpty) return null;
                  // Validated here so a typo is caught at the counter rather
                  // than at the doorstep. The server validates it too — this is
                  // an affordance, not the rule.
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
              // Omitted entirely when the operator cannot see the street, so a
              // masked editor cannot blank a value it was never shown.
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
