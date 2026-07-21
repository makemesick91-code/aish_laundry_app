import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/ops_routes.dart';
import 'edit_outcome.dart';
import 'master_data_providers.dart';
import 'master_data_views.dart';
import 'ops_master_data_scaffold.dart';

/// THE COUNTER CUSTOMER SURFACE (FR-021 … FR-030).
///
/// WHAT THIS SCREEN IS, AND WHAT IT IS NOT
/// ---------------------------------------
/// It is the counter's MASTER-DATA surface: find a customer, register a new one,
/// read their addresses, and record a consent decision. It creates no order,
/// takes no payment, quotes no total and prints no document — every one of those
/// is Step 5 or later (Rule 42, DEC-0030). A price shown anywhere in this module
/// is a PRICE LIST entry, never a line on a transaction.
///
/// THE LOOKUP IS BOUNDED, NOT BROWSABLE.
/// A counter workflow is "find this one customer", so the search is server-side
/// and the page is small. Nothing here loads a tenant's customer database into a
/// cheap phone's memory, and nothing offers a bulk view or an export — the
/// backend registers neither route (threats T-19, T-20).
class CustomerCounterScreen extends ConsumerStatefulWidget {
  const CustomerCounterScreen({super.key});

  @override
  ConsumerState<CustomerCounterScreen> createState() =>
      _CustomerCounterScreenState();
}

class _CustomerCounterScreenState extends ConsumerState<CustomerCounterScreen> {
  final TextEditingController _search = TextEditingController();

  /// The term the LIST is currently showing results for.
  ///
  /// Separate from the text field so a keystroke does not fire a request. The
  /// counter is on mobile data; searching per character would be a request
  /// storm against the outlet that is busiest.
  String _appliedTerm = '';

  /// Only `active` by default. An archived customer is still resolvable — a
  /// future order may reference them (threat T-18) — but showing them in the
  /// default counter lookup would put a customer nobody serves at the top of a
  /// hurried search.
  bool _includeArchived = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _apply() => setState(() => _appliedTerm = _search.text.trim());

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    final repository = ref.watch(masterDataRepositoryProvider);

    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    final bool canManage = session.allows(Permission.customerManage);

    return OpsMasterDataScaffold(
      title: 'Pelanggan',
      session: session,
      // A control the user may not use is not rendered (Rule 28 hard rule 5).
      // This is a courtesy, never the control: the server re-checks
      // `customer.create` regardless of what this predicate decided.
      floatingAction: canManage
          ? FloatingActionButton.extended(
              onPressed: () => context.go(OpsRoutes.customerCreate),
              icon: const Icon(Icons.person_add_alt_1_outlined),
              label: const Text('Pelanggan baru'),
            )
          : null,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: EdgeInsets.all(AishSpacing.space4),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _apply(),
                  decoration: InputDecoration(
                    labelText: 'Cari nama atau kode pelanggan',
                    // States what is searchable so an operator does not test the
                    // field by typing a phone number that will never match: the
                    // server searches name and code, and a phone number is a
                    // normalised match key rather than a searchable field.
                    helperText: 'Ketik lalu tekan cari. Minimal 2 huruf.',
                    prefixIcon: const Icon(Icons.search_outlined),
                    suffixIcon: IconButton(
                      tooltip: 'Cari pelanggan',
                      icon: const Icon(Icons.arrow_forward),
                      onPressed: _apply,
                    ),
                    border: const OutlineInputBorder(),
                  ),
                ),
                SizedBox(height: AishSpacing.space2),
                Row(
                  children: <Widget>[
                    // A 48×48 dp target minimum, including this one (Rule 27
                    // hard rule 5).
                    ConstrainedBox(
                      constraints: BoxConstraints(
                        minHeight: AishSizing.sizeTouchMin,
                        minWidth: AishSizing.sizeTouchMin,
                      ),
                      child: Checkbox(
                        value: _includeArchived,
                        onChanged: (value) =>
                            setState(() => _includeArchived = value ?? false),
                      ),
                    ),
                    const Expanded(
                      child: Text('Tampilkan juga pelanggan yang diarsipkan'),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: OpsAsyncSection<CustomerSummary>(
              // The query, by VALUE. It is what tells the section its query
              // changed; comparing the loader closures would reload forever,
              // because a tear-off is a fresh object on every build.
              queryKey: '$_appliedTerm|$_includeArchived',
              // Capturing the term here rather than reading it later means a
              // response that arrives after the operator has moved on cannot be
              // rendered against the newer term.
              load: _loaderFor(repository, _appliedTerm, _includeArchived),
              emptyTitle: _appliedTerm.isEmpty
                  ? 'Belum ada pelanggan'
                  : 'Tidak ada pelanggan yang cocok',
              emptyDescription: _appliedTerm.isEmpty
                  ? (canManage
                        ? 'Pelanggan yang Anda daftarkan di konter akan muncul di sini.'
                        : 'Pelanggan yang didaftarkan di konter akan muncul di sini.')
                  : 'Tidak ada pelanggan dengan nama atau kode yang memuat '
                        '"$_appliedTerm" pada tenant ini. Periksa ejaannya, atau '
                        'daftarkan pelanggan baru.',
              builder: (context, items) => ListView.builder(
                itemCount: items.length,
                itemBuilder: (context, index) =>
                    _CustomerRow(customer: items[index]),
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Built once per (term, filter) pair so the section can compare identity.
  Future<Result<List<CustomerSummary>>> Function() _loaderFor(
    MasterDataRepository repository,
    String term,
    bool includeArchived,
  ) =>
      () => repository.customers(
        query: term.isEmpty ? null : term,
        // Omitting `status` asks for every status; `active` narrows it. The client
        // never filters a received list — a client-side `where` would imply the
        // server might return rows it should not, which is the assumption Rule 02
        // forbids.
        status: includeArchived ? null : 'active',
        perPage: MasterDataRepository.counterPageSize,
      );
}

class _CustomerRow extends StatelessWidget {
  const _CustomerRow({required this.customer});

  final CustomerSummary customer;

  @override
  Widget build(BuildContext context) => ConstrainedBox(
    constraints: BoxConstraints(minHeight: AishSizing.sizeTouchMin),
    child: ListTile(
      title: Text(customer.name),
      // The phone arrives MASKED from the server and is rendered as it arrived.
      // There is no unmask control anywhere on this surface: unmasking is a
      // deliberate, per-record, permissioned, recorded server action and is
      // never a tap, a hover or a bulk reveal (Rule 32 hard rule 5). Step 4
      // exposes no unmasking endpoint, so no unmasked number exists to show.
      //
      // NO ADDRESS IN A LIST ROW, EVER (Rule 32 hard rule 4). The summary type
      // has no address field at all, so this row could not render one.
      subtitle: Text('${customer.code} · ${customer.phoneMasked}'),
      trailing: customer.isArchived
          // Status carries text AND an icon, never colour alone (Rule 27 hard
          // rule 3). A cheap screen in direct sunlight must read the same state.
          ? const StatusChip(
              label: 'Diarsipkan',
              icon: Icons.archive_outlined,
              tone: StatusTone.neutral,
            )
          : const Icon(Icons.chevron_right),
      onTap: () => context.go(OpsRoutes.customerDetailFor(customer.id)),
    ),
  );
}

// ---------------------------------------------------------------------------
// Registration (FR-021)
// ---------------------------------------------------------------------------

/// Register a customer at the counter.
///
/// A CREATE HAS NO VERSION PRECONDITION, and that is not an oversight: there is
/// no prior version to be stale against. The concurrency concern for a create is
/// a DUPLICATE, which the server settles — it owns the tenant-scoped uniqueness
/// rule, and a client-side "does this phone already exist" check would be both a
/// race and a way to probe whether a number is on file.
class CustomerCreateScreen extends ConsumerStatefulWidget {
  const CustomerCreateScreen({super.key});

  @override
  ConsumerState<CustomerCreateScreen> createState() =>
      _CustomerCreateScreenState();
}

class _CustomerCreateScreenState extends ConsumerState<CustomerCreateScreen> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _email = TextEditingController();

  bool _submitting = false;
  EditOutcome? _outcome;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
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
          .createCustomer(
            name: _name.text.trim(),
            phone: _phone.text.trim(),
            email: _email.text.trim().isEmpty ? null : _email.text.trim(),
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
      // Straight to the record that was just created, so the operator can add
      // an address or record consent without hunting for it again.
      context.go(OpsRoutes.customerDetailFor(outcome.record.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    final outcome = _outcome;
    final rejected = outcome is EditRejected ? outcome : null;

    return OpsMasterDataScaffold(
      title: 'Pelanggan baru',
      session: session,
      onBack: () => context.go(OpsRoutes.customers),
      body: Form(
        key: _form,
        child: ListView(
          padding: EdgeInsets.all(AishSpacing.space4),
          children: <Widget>[
            if (outcome != null && outcome is! EditSaved)
              Padding(
                padding: EdgeInsets.only(bottom: AishSpacing.space4),
                child: _OutcomeBanner(outcome: outcome),
              ),
            TextFormField(
              controller: _name,
              textCapitalization: TextCapitalization.words,
              decoration: InputDecoration(
                labelText: 'Nama pelanggan',
                border: const OutlineInputBorder(),
                errorText: rejected?.fieldErrors['name']?.first,
              ),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? 'Nama pelanggan wajib diisi.'
                  : null,
            ),
            SizedBox(height: AishSpacing.space4),
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: 'Nomor WhatsApp / telepon',
                helperText: 'Contoh: 08123456789',
                border: const OutlineInputBorder(),
                errorText: rejected?.fieldErrors['phone']?.first,
              ),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? 'Nomor telepon wajib diisi.'
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
            SizedBox(height: AishSpacing.space6),
            PrimaryAction(
              label: 'Simpan pelanggan',
              icon: Icons.save_outlined,
              isBusy: _submitting,
              expand: true,
              onPressed: _submitting ? null : _submit,
            ),
          ],
        ),
      ),
    );
  }
}

/// The inline outcome banner for a form.
///
/// A conflict is rendered by [StaleWriteNotice] on screens that have a record to
/// reload. A CREATE cannot conflict — there is no prior version — so this banner
/// covers the remaining outcomes.
class _OutcomeBanner extends StatelessWidget {
  const _OutcomeBanner({required this.outcome});

  final EditOutcome outcome;

  @override
  Widget build(BuildContext context) {
    final message = messageForOutcome(outcome);
    if (message == null) {
      return const SizedBox.shrink();
    }

    return Semantics(
      // A save result the operator did not watch for must still be announced
      // (Rule 27 hard rule 12 — live regions announce money- and
      // security-relevant change).
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
