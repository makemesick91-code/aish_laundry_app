import 'package:aish_networking/aish_networking.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/ops_routes.dart';
import 'customer_address_section.dart';
import 'master_data_providers.dart';
import 'master_data_views.dart';
import 'ops_master_data_scaffold.dart';

/// One customer, as the counter sees them (FR-021 … FR-030).
///
/// THE VERSION TOKEN IS THE POINT OF THIS SCREEN'S STRUCTURE.
/// It loads the record, holds the exact `version` the server sent, and sends
/// that token back with every edit. If the record moved underneath the operator,
/// the server answers `CONFLICT` and this screen shows a stale-write notice with
/// a RELOAD action — never a retry. Resending the same payload after a conflict
/// does not fail; it succeeds and destroys somebody else's edit (threat T-12).
///
/// The token is opaque and is NOT `updated_at`. A second-precision timestamp
/// cannot distinguish two edits inside the same second, so a timestamp-based
/// precondition silently permits exactly the overwrite it was meant to stop.
class CustomerDetailScreen extends ConsumerStatefulWidget {
  const CustomerDetailScreen({required this.customerId, super.key});

  final String customerId;

  @override
  ConsumerState<CustomerDetailScreen> createState() =>
      _CustomerDetailScreenState();
}

class _CustomerDetailScreenState extends ConsumerState<CustomerDetailScreen> {
  late Future<Result<CustomerDetail>> _record = _load();

  Future<Result<CustomerDetail>> _load() =>
      ref.read(masterDataRepositoryProvider).customer(widget.customerId);

  void _reload() {
    // A block body: an arrow would return the assigned Future, which Flutter
    // rejects as a setState callback.
    setState(() {
      _record = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    return OpsMasterDataScaffold(
      title: 'Detail pelanggan',
      session: session,
      onBack: () => context.go(OpsRoutes.customers),
      body: FutureBuilder<Result<CustomerDetail>>(
        future: _record,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return Center(
              child: Semantics(
                label: 'Memuat data pelanggan',
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
              title: 'Data tidak dapat ditampilkan',
              description:
                  'Terjadi kesalahan saat menyiapkan data pelanggan. Muat ulang '
                  'layar ini.',
              icon: Icons.error_outline,
              tone: StatusTone.danger,
              recoveryLabel: 'Muat ulang',
              onRecover: _reload,
            );
          }

          return result.fold(
            (CustomerDetail customer) => _CustomerBody(
              customer: customer,
              session: session,
              onChanged: _reload,
            ),
            (Failure failure) =>
                OpsFailureState(failure: failure, onRetry: _reload),
          );
        },
      ),
    );
  }
}

class _CustomerBody extends ConsumerStatefulWidget {
  const _CustomerBody({
    required this.customer,
    required this.session,
    required this.onChanged,
  });

  final CustomerDetail customer;
  final SessionState session;
  final VoidCallback onChanged;

  @override
  ConsumerState<_CustomerBody> createState() => _CustomerBodyState();
}

class _CustomerBodyState extends ConsumerState<_CustomerBody> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();
  late final TextEditingController _name = TextEditingController(
    text: widget.customer.name,
  );
  late final TextEditingController _email = TextEditingController(
    text: widget.customer.email ?? '',
  );
  late final TextEditingController _notes = TextEditingController(
    text: widget.customer.internalNotes ?? '',
  );

  bool _editing = false;
  bool _submitting = false;
  EditOutcome? _outcome;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _notes.dispose();
    super.dispose();
  }

  /// Reload the record WITHOUT discarding what the operator typed.
  ///
  /// Called from the stale-write notice. The controllers are deliberately left
  /// alone: their work survives, the form stays open, and they decide whether
  /// their change still applies to the record they can now see. Clearing the
  /// form to "reset cleanly" would punish them for somebody else's edit.
  void _reloadKeepingInput() {
    setState(() => _outcome = null);
    widget.onChanged();
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
          .updateCustomer(
            id: widget.customer.id,
            // EXACTLY the token that came with the record being edited. Not a
            // re-read, not a timestamp, not omitted.
            expectedVersion: widget.customer.version,
            changes: <String, Object?>{
              'name': _name.text.trim(),
              'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
              'internal_notes': _notes.text.trim().isEmpty
                  ? null
                  : _notes.text.trim(),
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

    if (outcome is EditSaved<CustomerDetail>) {
      setState(() => _editing = false);
      widget.onChanged();
    }
  }

  @override
  Widget build(BuildContext context) {
    final customer = widget.customer;
    final textTheme = Theme.of(context).textTheme;
    final bool canManage = widget.session.allows(Permission.customerManage);
    final bool canManageConsent = widget.session.allows(
      Permission.customerConsentManage,
    );

    final outcome = _outcome;
    final rejected = outcome is EditRejected ? outcome : null;

    return ListView(
      padding: EdgeInsets.all(AishSpacing.space4),
      children: <Widget>[
        // THE CONFLICT RENDERING. Reload only — no retry control exists on this
        // path, because a retry here would silently overwrite (threat T-12).
        if (outcome is EditConflict) ...<Widget>[
          StaleWriteNotice(
            conflict: outcome,
            recordLabel: 'Data pelanggan ${customer.name} (${customer.code})',
            onReload: _reloadKeepingInput,
          ),
          SizedBox(height: AishSpacing.space4),
        ] else if (outcome != null && outcome is! EditSaved) ...<Widget>[
          _InlineOutcome(outcome: outcome),
          SizedBox(height: AishSpacing.space4),
        ],

        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: Semantics(
                header: true,
                child: Text(customer.name, style: textTheme.titleLarge),
              ),
            ),
            if (customer.isArchived)
              const StatusChip(
                label: 'Diarsipkan',
                icon: Icons.archive_outlined,
                tone: StatusTone.neutral,
              ),
          ],
        ),
        SizedBox(height: AishSpacing.space1),
        Text(customer.code, style: textTheme.bodyMedium),
        SizedBox(height: AishSpacing.space4),

        // MASKED, as it arrived. No unmask control exists on this screen, and
        // Step 4 exposes no endpoint that would produce a full number
        // (Rule 32 hard rules 4–5).
        _Field(label: 'Nomor telepon', value: customer.phoneMasked),
        _Field(label: 'Email', value: customer.email ?? '—'),

        if (customer.isArchived) ...<Widget>[
          SizedBox(height: AishSpacing.space4),
          StateMessage(
            title: 'Pelanggan ini diarsipkan',
            description:
                'Data pelanggan yang diarsipkan tetap dapat dibuka karena '
                'riwayatnya masih dirujuk. Pelanggan ini tidak muncul pada '
                'pencarian konter biasa.',
            icon: Icons.archive_outlined,
            tone: StatusTone.neutral,
          ),
        ],

        SizedBox(height: AishSpacing.space6),

        // ------------------------------------------------------------------
        // Edit
        // ------------------------------------------------------------------
        if (canManage && !customer.isArchived)
          if (!_editing)
            PrimaryAction(
              label: 'Ubah data pelanggan',
              icon: Icons.edit_outlined,
              expand: true,
              onPressed: () => setState(() => _editing = true),
            )
          else
            Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  TextFormField(
                    controller: _name,
                    decoration: InputDecoration(
                      labelText: 'Nama pelanggan',
                      border: const OutlineInputBorder(),
                      errorText: rejected?.fieldErrors['name']?.first,
                    ),
                    validator: (value) =>
                        (value == null || value.trim().isEmpty)
                        ? 'Nama pelanggan wajib diisi.'
                        : null,
                  ),
                  SizedBox(height: AishSpacing.space4),
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(
                      labelText: 'Email (opsional)',
                      border: const OutlineInputBorder(),
                      errorText: rejected?.fieldErrors['email']?.first,
                    ),
                  ),
                  SizedBox(height: AishSpacing.space4),
                  TextFormField(
                    controller: _notes,
                    maxLines: 3,
                    decoration: InputDecoration(
                      labelText: 'Catatan internal (opsional)',
                      // Says plainly who can read it. An operator who believed
                      // this reached the customer would write something else.
                      helperText:
                          'Hanya terlihat oleh staf. Tidak pernah dikirim ke '
                          'pelanggan.',
                      border: const OutlineInputBorder(),
                    ),
                  ),
                  SizedBox(height: AishSpacing.space4),
                  CommitContextLine(
                    session: widget.session,
                    action: 'Simpan perubahan pelanggan',
                  ),
                  SizedBox(height: AishSpacing.space3),
                  PrimaryAction(
                    label: 'Simpan perubahan',
                    icon: Icons.save_outlined,
                    isBusy: _submitting,
                    expand: true,
                    onPressed: _submitting ? null : _save,
                  ),
                  SizedBox(height: AishSpacing.space2),
                  TextButton(
                    onPressed: _submitting
                        ? null
                        : () => setState(() => _editing = false),
                    child: const Text('Batal'),
                  ),
                ],
              ),
            ),

        SizedBox(height: AishSpacing.space6),

        // ------------------------------------------------------------------
        // Addresses (FR-024, FR-025)
        //
        // Managed by its own section, which loads from the server rather than
        // reading the addresses embedded in this detail payload. The embedded
        // copy is a snapshot taken when the customer was fetched; an address
        // edited in the section would leave it stale, and a stale delivery
        // address is the one failure this surface must not produce.
        // ------------------------------------------------------------------
        CustomerAddressSection(
          customerId: customer.id,
          canManage: canManage && !customer.isArchived,
        ),

        SizedBox(height: AishSpacing.space6),

        // ------------------------------------------------------------------
        // Consent (FR-027, FR-028)
        // ------------------------------------------------------------------
        _ConsentSection(
          customerId: customer.id,
          customerName: customer.name,
          canManage: canManageConsent && !customer.isArchived,
          session: widget.session,
        ),
      ],
    );
  }
}

class _Field extends StatelessWidget {
  const _Field({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
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

// ---------------------------------------------------------------------------
// Consent — APPEND ONLY
// ---------------------------------------------------------------------------

/// The consent history and the control that appends to it.
///
/// THERE IS NO EDIT CONTROL AND NO DELETE CONTROL, ANYWHERE IN THIS WIDGET.
/// A withdrawal is a NEW record appended to the history, never an edit of the
/// record that granted (invariant C5). The history IS the evidence: if a
/// customer disputes having consented, the answer is the ordered list of what
/// was recorded, when, and from where. An editable consent row would make that
/// evidence worthless — so the type carries no version, the repository exposes
/// no update method, and the server registers no such route.
class _ConsentSection extends ConsumerStatefulWidget {
  const _ConsentSection({
    required this.customerId,
    required this.customerName,
    required this.canManage,
    required this.session,
  });

  final String customerId;
  final String customerName;
  final bool canManage;
  final SessionState session;

  @override
  ConsumerState<_ConsentSection> createState() => _ConsentSectionState();
}

class _ConsentSectionState extends ConsumerState<_ConsentSection> {
  late Future<Result<ConsentLedger>> _ledger = _load();
  bool _submitting = false;
  EditOutcome? _outcome;

  Future<Result<ConsentLedger>> _load() =>
      ref.read(masterDataRepositoryProvider).consents(widget.customerId);

  void _reload() => setState(() {
    _outcome = null;
    _ledger = _load();
  });

  Future<void> _record(ConsentType type, ConsentState state) async {
    setState(() {
      _submitting = true;
      _outcome = null;
    });

    final outcome = classifyEdit(
      await ref
          .read(masterDataRepositoryProvider)
          .recordConsent(
            customerId: widget.customerId,
            type: type,
            state: state,
            // The counter is where this decision was captured. It is recorded
            // because "the customer said yes at the counter" and "an importer
            // asserted yes" are not the same evidence.
            source: ConsentSource.counter,
          ),
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _submitting = false;
      _outcome = outcome;
    });

    if (outcome is EditSaved<ConsentRecord>) {
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final outcome = _outcome;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Semantics(
          header: true,
          child: Text('Persetujuan promosi', style: textTheme.titleMedium),
        ),
        SizedBox(height: AishSpacing.space1),
        Text(
          'Persetujuan bersifat tambah-saja. Penarikan dicatat sebagai entri '
          'baru dan riwayat sebelumnya tidak pernah diubah atau dihapus.',
          style: textTheme.bodySmall?.copyWith(
            color: AishSemanticColors.colorSemanticTextSecondary,
          ),
        ),
        SizedBox(height: AishSpacing.space3),

        if (outcome != null && outcome is! EditSaved) ...<Widget>[
          _InlineOutcome(outcome: outcome),
          SizedBox(height: AishSpacing.space3),
        ],

        FutureBuilder<Result<ConsentLedger>>(
          future: _ledger,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return Center(
                child: Semantics(
                  label: 'Memuat riwayat persetujuan',
                  child: const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(),
                  ),
                ),
              );
            }

            final result = snapshot.data;
            if (result == null) {
              return StateMessage(
                title: 'Riwayat persetujuan tidak dapat ditampilkan',
                description: 'Muat ulang bagian ini untuk mencoba lagi.',
                icon: Icons.error_outline,
                tone: StatusTone.danger,
                recoveryLabel: 'Muat ulang',
                onRecover: _reload,
              );
            }

            return result.fold(
              _buildLedger,
              (Failure failure) =>
                  OpsFailureState(failure: failure, onRetry: _reload),
            );
          },
        ),
      ],
    );
  }

  Widget _buildLedger(ConsentLedger ledger) {
    final textTheme = Theme.of(context).textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        ...ConsentType.values.map((type) {
          final state = ledger.current[type];

          // A COLUMN, not a Row, and the narrow case is why.
          //
          // "Belum ditanyakan" beside a channel name and an action button does
          // not fit a 320 dp phone, and Rule 31 hard rule 1 specifies layouts
          // from the smallest supported viewport upward rather than designing
          // wide and shrinking. A horizontal row here truncated the status —
          // which is exactly the critical information that must never be cut
          // (Rule 27 hard rule 7).
          return Padding(
            padding: EdgeInsets.only(bottom: AishSpacing.space3),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(type.label),
                SizedBox(height: AishSpacing.space1),
                // A WRAP, not a Row. The status label and the action reflow
                // onto a second line on a narrow screen or at a large system
                // font size, instead of overflowing and clipping the status —
                // and a clipped status is the critical information Rule 27 hard
                // rule 7 forbids truncating.
                Wrap(
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: AishSpacing.space2,
                  runSpacing: AishSpacing.space1,
                  children: <Widget>[
                    // NEVER ASKED is rendered as never asked, not as withdrawn.
                    // Consent is opt-in and an absent record is not a decision;
                    // conflating them would let a screen imply the customer said
                    // no when nobody ever asked (Rule 32 hard rule 22).
                    StatusChip(
                      label: switch (state) {
                        ConsentState.granted => 'Disetujui',
                        ConsentState.withdrawn => 'Ditarik',
                        null => 'Belum ditanyakan',
                      },
                      icon: switch (state) {
                        ConsentState.granted => Icons.check_circle_outline,
                        ConsentState.withdrawn =>
                          Icons.do_not_disturb_on_outlined,
                        null => Icons.help_outline,
                      },
                      tone: switch (state) {
                        ConsentState.granted => StatusTone.success,
                        ConsentState.withdrawn => StatusTone.neutral,
                        null => StatusTone.neutral,
                      },
                    ),
                    if (widget.canManage)
                      ConstrainedBox(
                        constraints: BoxConstraints(
                          minHeight: AishSizing.sizeTouchMin,
                          minWidth: AishSizing.sizeTouchMin,
                        ),
                        child: TextButton(
                          onPressed: _submitting
                              ? null
                              : () => _confirm(
                                  type,
                                  state == ConsentState.granted
                                      ? ConsentState.withdrawn
                                      : ConsentState.granted,
                                ),
                          child: Text(
                            state == ConsentState.granted ? 'Tarik' : 'Setujui',
                          ),
                        ),
                      ),
                  ],
                ),
              ],
            ),
          );
        }),

        SizedBox(height: AishSpacing.space3),
        Text('Riwayat', style: textTheme.labelLarge),
        SizedBox(height: AishSpacing.space2),

        if (ledger.records.isEmpty)
          Text(
            'Belum ada catatan persetujuan untuk pelanggan ini.',
            style: textTheme.bodySmall?.copyWith(
              color: AishSemanticColors.colorSemanticTextSecondary,
            ),
          )
        else
          ...ledger.records.map(
            (record) => Padding(
              padding: EdgeInsets.only(bottom: AishSpacing.space2),
              child: Text(
                '${record.type?.label ?? "Jenis tidak dikenal"} · '
                '${record.state?.label ?? "Status tidak dikenal"} · '
                '${record.source?.label ?? "Sumber tidak dikenal"}'
                '${record.recordedAt == null ? "" : " · ${record.recordedAt}"}',
                style: textTheme.bodySmall,
              ),
            ),
          ),
      ],
    );
  }

  /// Recording a consent decision is confirmed with the specific object and
  /// effect restated, and focus defaults to the SAFE choice.
  ///
  /// Rule 32 hard rules 14–15: confirmation names what is about to happen rather
  /// than asking a generic "are you sure", and the committing option is never
  /// the autofocused control.
  Future<void> _confirm(ConsentType type, ConsentState target) async {
    final bool withdrawing = target == ConsentState.withdrawn;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          withdrawing
              ? 'Tarik persetujuan ${type.label}?'
              : 'Catat persetujuan ${type.label}?',
        ),
        content: Text(
          withdrawing
              ? 'Pesan promosi ${type.label} tidak akan lagi dikirim kepada '
                    '${widget.customerName}.\n\n'
                    'Penarikan ini dicatat sebagai entri BARU. Riwayat '
                    'persetujuan sebelumnya tetap tersimpan dan tidak diubah.'
              : '${widget.customerName} menyetujui menerima pesan promosi '
                    '${type.label}.\n\n'
                    'Catat hanya bila pelanggan menyatakannya sendiri di konter. '
                    'Catatan ini menjadi bukti persetujuan.',
        ),
        actions: <Widget>[
          TextButton(
            autofocus: true,
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(withdrawing ? 'Ya, tarik' : 'Ya, catat'),
          ),
        ],
      ),
    );

    if (confirmed ?? false) {
      await _record(type, target);
    }
  }
}

class _InlineOutcome extends StatelessWidget {
  const _InlineOutcome({required this.outcome});

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
