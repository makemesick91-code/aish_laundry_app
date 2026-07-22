import 'dart:async';

import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/ops_routes.dart';
import 'edit_outcome.dart';
import 'master_data_providers.dart';
import 'master_data_views.dart';
import 'ops_master_data_scaffold.dart';

/// STAFF ASSIGNMENT WITHIN THE TENANT (ROADMAP Step 4 scope, FR-018).
///
/// TWO DIFFERENT ACTS, KEPT APART ON PURPOSE.
///   * Assigning an OUTLET says WHERE somebody works. It confers no capability
///     whatsoever.
///   * Assigning a ROLE says WHAT somebody may do. It passes the server's
///     escalation guard.
///
/// One screen control doing both would make the roster a privilege-escalation
/// path wearing an innocent name, so they are separate calls behind separate
/// permissions, and this surface never bundles them into one confirmation.
///
/// THIS SCREEN CREATES NO ROLE AND NO PERMISSION.
/// Step 4 introduces no new role or permission model (DEC-0031 A2). It offers
/// the roles the Step 3 registry already defines, and it offers no platform role
/// at all — `TenantRole` has no member for one, so this picker cannot render one
/// even by mistake (DEC-0025 §8).
///
/// AND IT IS NOT THE ACCESS CONTROL.
/// Every control below is drawn from a permission the SERVER reported, and every
/// action it triggers is re-checked server-side against live membership. A
/// client that showed too much produces a refused request, never an unauthorized
/// grant (Rule 40 hard rules 1–2).
class StaffRosterScreen extends ConsumerStatefulWidget {
  const StaffRosterScreen({super.key});

  @override
  ConsumerState<StaffRosterScreen> createState() => _StaffRosterScreenState();
}

class _StaffRosterScreenState extends ConsumerState<StaffRosterScreen> {
  final GlobalKey<OpsAsyncSectionState<StaffMember>> _section =
      GlobalKey<OpsAsyncSectionState<StaffMember>>();

  /// The outlets the SERVER confirmed this caller may act in.
  ///
  /// Read from `authorizedOutlets()` — the same tenant-scoped source the outlet
  /// selector uses — rather than from anything the roster response contained. A
  /// roster row naming an outlet is not authority to act on that outlet, and
  /// resolving names from a list the caller was independently granted keeps
  /// those two questions apart.
  List<Outlet> _outlets = const <Outlet>[];

  @override
  void initState() {
    super.initState();
    unawaited(_loadOutlets());
  }

  Future<void> _loadOutlets() async {
    final result = await ref.read(authServiceProvider).authorizedOutlets();
    if (!mounted) {
      return;
    }
    // A failure here degrades the picker, never the roster: assignment simply
    // has nothing to offer, and the roster still reads correctly.
    result.fold(
      (List<Outlet> outlets) => setState(() => _outlets = outlets),
      (Failure _) => setState(() => _outlets = const <Outlet>[]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    final repository = ref.watch(masterDataRepositoryProvider);

    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    final bool canAssign = session.allows(Permission.staffAssignmentManage);

    return OpsMasterDataScaffold(
      title: 'Staf dan peran',
      session: session,
      onBack: () => context.go(OpsRoutes.home),
      body: OpsAsyncSection<StaffMember>(
        key: _section,
        load: repository.staff,
        emptyTitle: 'Belum ada staf pada tenant ini',
        emptyDescription:
            'Anggota yang diundang ke tenant ini akan muncul di sini beserta '
            'peran dan outlet penugasannya.',
        builder: (context, items) => ListView.builder(
          itemCount: items.length,
          itemBuilder: (context, index) => _StaffCard(
            member: items[index],
            session: session,
            outlets: _outlets,
            canAssign: canAssign,
            onChanged: () => _section.currentState?.reload(),
          ),
        ),
      ),
    );
  }
}

class _StaffCard extends ConsumerStatefulWidget {
  const _StaffCard({
    required this.member,
    required this.session,
    required this.outlets,
    required this.canAssign,
    required this.onChanged,
  });

  final StaffMember member;
  final SessionState session;
  final List<Outlet> outlets;
  final bool canAssign;
  final VoidCallback onChanged;

  @override
  ConsumerState<_StaffCard> createState() => _StaffCardState();
}

class _StaffCardState extends ConsumerState<_StaffCard> {
  bool _busy = false;
  EditOutcome? _outcome;

  @override
  Widget build(BuildContext context) {
    final member = widget.member;
    final textTheme = Theme.of(context).textTheme;
    final outcome = _outcome;

    // GRANTING and REVOKING are gated differently, and the difference matters.
    //
    // A new grant is offered only for a LIVE membership: granting a role to a
    // suspended member is an action whose effect would be invisible until they
    // were reinstated, and the server refuses it (SEC-08).
    //
    // REVOCATION stays available whatever the status. Hiding it when somebody is
    // suspended is the wrong failure direction — an administrator responding to
    // an incident would be able to suspend a member and then find themselves
    // unable to strip the roles they had just suspended, with the controls
    // disappearing exactly when they were most needed. A lifecycle state must
    // never make a membership HARDER to lock down.
    final bool canGrant = widget.canAssign && member.isActive;
    final bool canRevoke = widget.canAssign;

    return Card(
      margin: EdgeInsets.symmetric(
        horizontal: AishSpacing.space4,
        vertical: AishSpacing.space2,
      ),
      child: Padding(
        padding: EdgeInsets.all(AishSpacing.space4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(member.userName, style: textTheme.titleSmall),
                      if (member.userEmail != null)
                        Text(
                          member.userEmail!,
                          style: textTheme.bodySmall?.copyWith(
                            color:
                                AishSemanticColors.colorSemanticTextSecondary,
                          ),
                        ),
                    ],
                  ),
                ),
                // Membership status carries text AND an icon, never colour
                // alone (Rule 27 hard rule 3). A suspended member must read as
                // suspended on a cheap screen in direct sunlight.
                if (!member.isActive)
                  StatusChip(
                    label: switch (member.status) {
                      'suspended' => 'Ditangguhkan',
                      'revoked' => 'Dicabut',
                      _ => 'Diundang',
                    },
                    icon: Icons.person_off_outlined,
                    tone: StatusTone.warning,
                  ),
              ],
            ),

            if (!member.isActive) ...<Widget>[
              SizedBox(height: AishSpacing.space2),
              Text(switch (member.status) {
                'suspended' =>
                  'Akses anggota ini sedang ditangguhkan. Penugasan tidak '
                      'dapat diubah sampai keanggotaannya diaktifkan kembali.',
                'revoked' =>
                  'Keanggotaan ini telah dicabut. Riwayat penugasannya tetap '
                      'tersimpan sebagai catatan.',
                _ =>
                  'Undangan belum diterima. Penugasan dapat diatur setelah '
                      'anggota ini bergabung.',
              }, style: textTheme.bodySmall),
            ],

            SizedBox(height: AishSpacing.space3),

            if (outcome != null && outcome is! EditSaved) ...<Widget>[
              _RosterOutcome(outcome: outcome),
              SizedBox(height: AishSpacing.space3),
            ],

            // --------------------------------------------------------------
            // Roles — WHAT this person may do
            // --------------------------------------------------------------
            Text('Peran', style: textTheme.labelLarge),
            SizedBox(height: AishSpacing.space2),
            if (member.roles.isEmpty)
              Text(
                'Belum memiliki peran. Anggota tanpa peran dapat masuk tetapi '
                'tidak dapat melakukan tindakan apa pun.',
                style: textTheme.bodySmall,
              )
            else
              Wrap(
                spacing: AishSpacing.space2,
                runSpacing: AishSpacing.space2,
                children: member.roles
                    .map((key) {
                      final assigned = AssignedRole.fromWire(key);

                      return InputChip(
                        label: Text(assigned.label),
                        // A role key this build does not recognise is DISPLAYED and
                        // left alone. Offering to revoke a key we cannot interpret
                        // would be acting on something we do not understand.
                        onDeleted: canRevoke && assigned.isRecognised && !_busy
                            ? () => _confirmRemoveRole(assigned.role!)
                            : null,
                        deleteButtonTooltipMessage:
                            'Cabut peran ${assigned.label} dari ${member.userName}',
                      );
                    })
                    .toList(growable: false),
              ),

            if (canGrant) ...<Widget>[
              SizedBox(height: AishSpacing.space2),
              TextButton.icon(
                onPressed: _busy ? null : () => _pickRole(member),
                icon: const Icon(Icons.add_moderator_outlined),
                label: const Text('Berikan peran'),
              ),
            ],

            SizedBox(height: AishSpacing.space3),

            // --------------------------------------------------------------
            // Outlets — WHERE this person works. Confers nothing.
            // --------------------------------------------------------------
            Text('Penugasan outlet', style: textTheme.labelLarge),
            SizedBox(height: AishSpacing.space1),
            Text(
              'Menentukan tempat bekerja. Penugasan outlet tidak memberikan '
              'wewenang apa pun.',
              style: textTheme.bodySmall?.copyWith(
                color: AishSemanticColors.colorSemanticTextSecondary,
              ),
            ),
            SizedBox(height: AishSpacing.space2),
            if (member.liveAssignments.isEmpty)
              Text(
                'Belum ditugaskan ke outlet mana pun.',
                style: textTheme.bodySmall,
              )
            else
              ...member.liveAssignments.map(
                (assignment) => Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        _outletName(assignment.outletId),
                        style: textTheme.bodyMedium,
                      ),
                    ),
                    if (canRevoke)
                      ConstrainedBox(
                        constraints: BoxConstraints(
                          minHeight: AishSizing.sizeTouchMin,
                          minWidth: AishSizing.sizeTouchMin,
                        ),
                        child: TextButton(
                          onPressed: _busy
                              ? null
                              : () => _confirmRevokeOutlet(assignment),
                          child: const Text('Cabut'),
                        ),
                      ),
                  ],
                ),
              ),

            if (canGrant) ...<Widget>[
              SizedBox(height: AishSpacing.space2),
              TextButton.icon(
                onPressed: _busy ? null : () => _pickOutlet(member),
                icon: const Icon(Icons.add_business_outlined),
                label: const Text('Tugaskan ke outlet'),
              ),
            ],
          ],
        ),
      ),
    );
  }

  /// Resolve an outlet id to a name using ONLY the session's own outlet list.
  ///
  /// The session's outlets are the ones the server confirmed this caller may
  /// see. An id that is not among them is shown as an id rather than looked up
  /// through some other path: a roster row is not a reason to widen what this
  /// screen can resolve.
  String _outletName(String outletId) {
    for (final outlet in widget.outlets) {
      if (outlet.id == outletId) {
        return outlet.name;
      }
    }
    // An id the caller was not granted is described, never resolved through
    // some other path. A roster row is not a reason to widen what this screen
    // can look up.
    return 'Outlet lain pada tenant ini';
  }

  // ------------------------------------------------------------------
  // Role assignment
  // ------------------------------------------------------------------

  Future<void> _pickRole(StaffMember member) async {
    // The picker offers STAFF roles only. `TenantRole.customer` is
    // tenant-assignable on the server but is a self-service role, not a roster
    // one, so it is filtered out here rather than presented to an operator who
    // would reasonably read it as "make this person a customer".
    final held = member.roles.toSet();
    final options = TenantRole.assignableToStaff
        .where((role) => !held.contains(role.wireValue))
        .toList(growable: false);

    if (options.isEmpty) {
      return;
    }

    final chosen = await showModalBottomSheet<TenantRole>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: <Widget>[
            Padding(
              padding: EdgeInsets.all(AishSpacing.space4),
              child: Text(
                'Berikan peran kepada ${member.userName}',
                style: Theme.of(sheetContext).textTheme.titleMedium,
              ),
            ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: AishSpacing.space4),
              child: Text(
                // Says plainly that the server decides. An operator who sees a
                // role refused should understand why rather than assume a bug.
                'Anda hanya dapat memberikan peran yang izinnya juga Anda '
                'miliki. Peran di luar itu akan ditolak server.',
                style: Theme.of(sheetContext).textTheme.bodySmall,
              ),
            ),
            SizedBox(height: AishSpacing.space2),
            ...options.map(
              (role) => ConstrainedBox(
                constraints: BoxConstraints(minHeight: AishSizing.sizeTouchMin),
                child: ListTile(
                  title: Text(role.label),
                  onTap: () => Navigator.of(sheetContext).pop(role),
                ),
              ),
            ),
          ],
        ),
      ),
    );

    if (chosen == null || !mounted) {
      return;
    }

    await _confirmAssignRole(chosen);
  }

  /// Granting a role changes what a person may do, so it is confirmed with the
  /// specific person, the specific role, and the effect restated.
  ///
  /// Rule 32 hard rules 14–15: confirmation strength scales with consequence,
  /// and the committing option is never the autofocused control.
  Future<void> _confirmAssignRole(TenantRole role) async {
    final member = widget.member;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Berikan peran ${role.label}?'),
        content: Text(
          '${member.userName} akan memperoleh seluruh wewenang peran '
          '${role.label} pada tenant ${widget.session.activeTenant!.name}.\n\n'
          'Perubahan wewenang berlaku pada permintaan berikutnya, tanpa perlu '
          'masuk ulang.',
        ),
        actions: <Widget>[
          TextButton(
            autofocus: true,
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Ya, berikan peran'),
          ),
        ],
      ),
    );

    if (!(confirmed ?? false)) {
      return;
    }

    await _run(
      () => ref
          .read(masterDataRepositoryProvider)
          .assignRole(membershipId: member.membershipId, role: role),
    );
  }

  /// Revoking a role REMOVES capability, so it is confirmed as a destructive
  /// action: the safe choice is autofocused and the effect is restated.
  Future<void> _confirmRemoveRole(TenantRole role) async {
    final member = widget.member;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Cabut peran ${role.label}?'),
        content: Text(
          '${member.userName} akan kehilangan seluruh wewenang peran '
          '${role.label} pada tenant ${widget.session.activeTenant!.name}.\n\n'
          'Pencabutan berlaku SEGERA, termasuk pada perangkat yang sedang '
          'digunakan anggota ini.',
        ),
        actions: <Widget>[
          TextButton(
            autofocus: true,
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Ya, cabut peran'),
          ),
        ],
      ),
    );

    if (!(confirmed ?? false)) {
      return;
    }

    await _run(
      () => ref
          .read(masterDataRepositoryProvider)
          .removeRole(membershipId: member.membershipId, role: role),
    );
  }

  // ------------------------------------------------------------------
  // Outlet assignment
  // ------------------------------------------------------------------

  Future<void> _pickOutlet(StaffMember member) async {
    // OFFERED FROM THE SESSION'S OWN OUTLET LIST, never from a free-text field.
    //
    // These are the outlets the server confirmed this caller may act in. There
    // is no place to type an identifier, so a cross-tenant outlet id cannot be
    // entered here at all — and if one were somehow sent, the server resolves
    // the id WITHIN the active tenant and answers 404 exactly as it would for
    // an id that does not exist (threat T-13).
    final assigned = member.liveAssignments
        .map((assignment) => assignment.outletId)
        .toSet();

    final options = widget.outlets
        .where((outlet) => !assigned.contains(outlet.id))
        .toList(growable: false);

    if (options.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Tidak ada outlet lain yang dapat Anda tugaskan kepada anggota ini.',
            ),
          ),
        );
      }
      return;
    }

    final chosen = await showModalBottomSheet<Outlet>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: <Widget>[
            Padding(
              padding: EdgeInsets.all(AishSpacing.space4),
              child: Text(
                'Tugaskan ${member.userName} ke outlet',
                style: Theme.of(sheetContext).textTheme.titleMedium,
              ),
            ),
            ...options.map(
              (outlet) => ConstrainedBox(
                constraints: BoxConstraints(minHeight: AishSizing.sizeTouchMin),
                child: ListTile(
                  title: Text(outlet.name),
                  onTap: () => Navigator.of(sheetContext).pop(outlet),
                ),
              ),
            ),
          ],
        ),
      ),
    );

    if (chosen == null || !mounted) {
      return;
    }

    await _run(
      () => ref
          .read(masterDataRepositoryProvider)
          .assignOutlet(membershipId: member.membershipId, outletId: chosen.id),
    );
  }

  Future<void> _confirmRevokeOutlet(OutletAssignment assignment) async {
    final member = widget.member;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Cabut penugasan outlet?'),
        content: Text(
          '${member.userName} tidak lagi terdaftar bekerja di '
          '${_outletName(assignment.outletId)}.\n\n'
          'Pencabutan ini dicatat beserta waktunya; riwayat penugasan tetap '
          'tersimpan dan tidak dihapus.',
        ),
        actions: <Widget>[
          TextButton(
            autofocus: true,
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Ya, cabut penugasan'),
          ),
        ],
      ),
    );

    if (!(confirmed ?? false)) {
      return;
    }

    await _run(
      () => ref
          .read(masterDataRepositoryProvider)
          .revokeOutletAssignment(
            membershipId: member.membershipId,
            assignmentId: assignment.id,
          ),
    );
  }

  // ------------------------------------------------------------------

  /// Run a roster mutation and reload the CANONICAL state afterwards.
  ///
  /// The card never patches itself from what it believed it just did. The
  /// server is the authority on who holds what, and after any change the roster
  /// is re-read — so a grant the escalation guard refused, or a revocation that
  /// took effect differently than expected, shows the truth rather than an
  /// optimistic local guess.
  Future<void> _run(Future<Result<Object?>> Function() action) async {
    setState(() {
      _busy = true;
      _outcome = null;
    });

    final outcome = classifyEdit(await action());

    if (!mounted) {
      return;
    }

    setState(() {
      _busy = false;
      _outcome = outcome;
    });

    if (outcome is EditSaved) {
      widget.onChanged();
    }
  }
}

class _RosterOutcome extends StatelessWidget {
  const _RosterOutcome({required this.outcome});

  final EditOutcome outcome;

  @override
  Widget build(BuildContext context) {
    // A refused ROLE GRANT gets its own copy. The generic "you lack access"
    // message would be misleading: the caller may well hold the assignment
    // permission and still be refused, because the escalation guard forbids
    // granting a role carrying a permission the caller does not itself hold.
    if (outcome is EditDenied) {
      final denied = outcome as EditDenied;
      return Semantics(
        liveRegion: true,
        container: true,
        child: StateMessage(
          title: 'Tindakan ini ditolak server',
          description:
              'Anda tidak dapat memberikan peran yang memuat izin yang tidak '
              'Anda miliki sendiri. Minta pemilik tenant untuk memberikannya.',
          icon: Icons.lock_outline,
          tone: StatusTone.warning,
          supportReference: denied.failure.correlationId,
        ),
      );
    }

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
