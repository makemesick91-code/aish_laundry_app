import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';

import 'edit_outcome.dart';

/// Renders the canonical UX states for a loaded collection on the Ops surface.
///
/// Extracted so the state set cannot drift between counter screens: a screen
/// that forgot its `DENIED` rendering, or that spun forever on an error, would
/// be an incomplete state model rather than a smaller feature (Rule 29,
/// Rule 34). Rule 29 hard rule 13 treats an indefinite spinner as an absent
/// state model, not a placeholder.
///
/// Every non-loaded state names a recovery action in Bahasa Indonesia, and no
/// state is a dead end.
class OpsAsyncSection<T> extends StatefulWidget {
  const OpsAsyncSection({
    required this.load,
    required this.emptyTitle,
    required this.emptyDescription,
    required this.builder,
    this.emptyAction,
    this.queryKey,
    super.key,
  });

  final Future<Result<List<T>>> Function() load;
  final String emptyTitle;
  final String emptyDescription;
  final Widget Function(BuildContext context, List<T> items) builder;

  /// Offered from the EMPTY state when there is a next action to take.
  final Widget? emptyAction;

  /// Changes when the QUERY changes — a new search term, a different filter.
  ///
  /// An explicit value rather than a comparison of [load], and the distinction
  /// is not cosmetic: `load: repository.customers` builds a NEW tear-off object
  /// on every build, so comparing closures would report "the query changed" on
  /// every single rebuild and reload forever. A value compared with `==` says
  /// what actually changed.
  final Object? queryKey;

  @override
  State<OpsAsyncSection<T>> createState() => OpsAsyncSectionState<T>();
}

class OpsAsyncSectionState<T> extends State<OpsAsyncSection<T>> {
  late Future<Result<List<T>>> _future = widget.load();

  /// Re-run the load. Public so a parent — a search field, a successful edit —
  /// can refresh the section without the section owning that trigger.
  void reload() {
    if (mounted) {
      // A BLOCK body, not an arrow. `setState(() => _future = ...)` returns the
      // assigned value — a Future — and Flutter rejects a setState callback
      // that returns one, because it cannot tell an accidental `async` closure
      // from a deliberate assignment.
      setState(() {
        _future = widget.load();
      });
    }
  }

  @override
  void didUpdateWidget(covariant OpsAsyncSection<T> oldWidget) {
    super.didUpdateWidget(oldWidget);
    // Reload only when the QUERY changed, compared by VALUE.
    //
    // Comparing the `load` closures instead would reload on every rebuild —
    // a method tear-off is a fresh object each time — and the resulting
    // setState/rebuild cycle never terminates.
    if (oldWidget.queryKey != widget.queryKey) {
      reload();
    }
  }

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
      // one rather than rendered as an empty list. A master-data screen that
      // quietly shows nothing is worse than one that fails loudly.
      if (result == null) {
        return StateMessage(
          title: 'Data tidak dapat ditampilkan',
          description:
              'Terjadi kesalahan saat menyiapkan data. Muat ulang layar ini; '
              'bila berulang, hubungi admin tenant Anda.',
          icon: Icons.error_outline,
          tone: StatusTone.danger,
          recoveryLabel: 'Muat ulang',
          onRecover: reload,
        );
      }

      return result.fold(
        (List<T> items) => items.isEmpty
            // EMPTY — states what would appear here and why, never a blank
            // panel (Rule 29 hard rule 10).
            ? RefreshIndicator(
                onRefresh: () async => reload(),
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: <Widget>[
                    SizedBox(height: AishSpacing.space8),
                    StateMessage(
                      title: widget.emptyTitle,
                      description: widget.emptyDescription,
                      icon: Icons.inbox_outlined,
                    ),
                    if (widget.emptyAction != null)
                      Padding(
                        padding: EdgeInsets.all(AishSpacing.space4),
                        child: widget.emptyAction,
                      ),
                  ],
                ),
              )
            // LOADED.
            : RefreshIndicator(
                onRefresh: () async => reload(),
                child: widget.builder(context, items),
              ),
        (Failure failure) => OpsFailureState(failure: failure, onRetry: reload),
      );
    },
  );
}

/// The ERROR and DENIED renderings for a READ, chosen by failure kind.
class OpsFailureState extends StatelessWidget {
  const OpsFailureState({
    required this.failure,
    required this.onRetry,
    super.key,
  });

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

    // OFFLINE is distinguished from a server failure, because the recovery
    // differs: one waits for a signal, the other waits for the service.
    final bool offline =
        failure.kind == FailureKind.network ||
        failure.kind == FailureKind.timeout;

    // A NON-RETRYABLE failure offers no retry button. Rule 29 hard rule 8 wants
    // a recovery action, and for a refusal the recovery is to reload and look
    // again — hammering a control that will never work is not a recovery.
    final bool retryable = failure.isRetryable;

    return StateMessage(
      title: offline
          ? 'Perangkat sedang luring'
          : (retryable ? 'Data gagal dimuat' : 'Data ini tidak dapat dimuat'),
      description: offline
          ? 'Periksa koneksi Anda. Data ini dibaca langsung dari server dan '
                'belum tersedia luring.'
          : (retryable
                ? 'Layanan sedang tidak dapat melayani permintaan ini. Coba lagi '
                      'beberapa saat lagi.'
                : 'Muat ulang untuk melihat keadaan terbaru.'),
      icon: offline
          ? Icons.cloud_off_outlined
          : (retryable ? Icons.error_outline : Icons.sync_problem_outlined),
      tone: offline
          ? StatusTone.offline
          : (retryable ? StatusTone.danger : StatusTone.warning),
      recoveryLabel: retryable ? 'Coba lagi' : 'Muat ulang',
      onRecover: onRetry,
      supportReference: failure.correlationId,
    );
  }
}

/// The STALE-WRITE notice. Shown when, and only when, the server said `CONFLICT`.
///
/// WHAT THIS WIDGET DELIBERATELY DOES NOT HAVE
/// -------------------------------------------
/// A "Coba lagi" button. Resending the identical payload after a conflict does
/// not fail — it SUCCEEDS, and destroys the edit that caused the conflict. The
/// only action offered is to reload the current server state, after which the
/// operator can decide whether their change still applies (threat T-12).
///
/// It also does not clear the form. What the operator typed stays on screen so
/// they can re-apply it deliberately; discarding their work to "reset cleanly"
/// would punish them for somebody else's edit.
class StaleWriteNotice extends StatelessWidget {
  const StaleWriteNotice({
    required this.conflict,
    required this.onReload,
    required this.recordLabel,
    super.key,
  });

  final EditConflict conflict;

  /// Reloads the record from the server. Never resubmits.
  final VoidCallback onReload;

  /// What changed, named specifically — "Outlet Konter Uji", not "the record".
  final String recordLabel;

  @override
  Widget build(BuildContext context) => Semantics(
    // Announced, because a change the operator did not cause and cannot see is
    // exactly what assistive technology has to be told about (Rule 27 hard
    // rule 12).
    liveRegion: true,
    container: true,
    child: StateMessage(
      title: 'Data ini sudah diubah orang lain',
      description:
          '$recordLabel telah diperbarui di perangkat lain sejak Anda membukanya. '
          'Perubahan Anda BELUM disimpan, dan tidak akan dikirim ulang secara '
          'otomatis.\n\n'
          'Muat ulang untuk melihat keadaan terbaru, lalu terapkan kembali '
          'perubahan Anda bila masih diperlukan. Isian Anda tetap tersimpan di '
          'layar ini.',
      icon: Icons.sync_problem_outlined,
      tone: StatusTone.warning,
      statusLabel: 'Perubahan tertunda',
      recoveryLabel: 'Muat ulang data terbaru',
      onRecover: onReload,
      supportReference: conflict.failure.correlationId,
    ),
  );
}

/// Bahasa Indonesia copy for a write outcome, for the inline form banner.
///
/// Returns null for [EditSaved]: success is announced by the surface itself,
/// which knows what was saved and where to go next.
({String title, String description, StatusTone tone, IconData icon})?
messageForOutcome(EditOutcome outcome) => switch (outcome) {
  EditSaved<Object?>() => null,
  // Handled by StaleWriteNotice, which carries a reload action this inline
  // banner has no way to offer.
  EditConflict() => (
    title: 'Data ini sudah diubah orang lain',
    description:
        'Muat ulang untuk melihat keadaan terbaru, lalu terapkan kembali '
        'perubahan Anda.',
    tone: StatusTone.warning,
    icon: Icons.sync_problem_outlined,
  ),
  EditRejected() => (
    title: 'Periksa kembali isian Anda',
    description: 'Ada isian yang belum sesuai. Perbaiki lalu simpan lagi.',
    tone: StatusTone.danger,
    icon: Icons.error_outline,
  ),
  EditDenied() => (
    title: 'Anda tidak memiliki akses untuk tindakan ini',
    description: 'Hubungi admin tenant Anda bila Anda memerlukan akses.',
    tone: StatusTone.warning,
    icon: Icons.lock_outline,
  ),
  EditUnreachable() => (
    title: 'Perangkat sedang luring',
    description:
        'Perubahan ini BELUM tersimpan di server. Periksa koneksi Anda lalu '
        'simpan lagi.',
    tone: StatusTone.offline,
    icon: Icons.cloud_off_outlined,
  ),
  EditUnavailable() => (
    title: 'Perubahan gagal disimpan',
    description:
        'Layanan sedang tidak dapat memproses permintaan ini. Coba simpan lagi '
        'beberapa saat lagi.',
    tone: StatusTone.danger,
    icon: Icons.error_outline,
  ),
};
