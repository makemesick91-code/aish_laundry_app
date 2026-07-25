import 'dart:math';

import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';
import '../master_data/ops_master_data_scaffold.dart';
import 'tracking_providers.dart';
import 'tracking_views.dart';

/// THE OPERATOR TRACKING AND NOTIFICATION SURFACE (Step 7, FR-086 … FR-099).
///
/// Everything on this screen is a REAL server read or a real server command.
/// There is no placeholder control, no dead button, and no fabricated state
/// (Rule 34).
///
/// THE ONE-TIME LINK IS THE DESIGN CONSTRAINT. The plaintext URL exists only in
/// the response to issue/rotate, so it is held in memory for this screen's
/// lifetime, shown with an explicit "ditampilkan sekali" warning, and offered
/// for copying — never written to storage and never re-fetchable. When it is
/// lost, the recovery is rotation, which is exactly what the UI offers
/// (TRK-019, Rule 32 hard rule 23: the clipboard is shared, so copying warns).
class OrderTrackingScreen extends ConsumerStatefulWidget {
  const OrderTrackingScreen({required this.orderId, super.key});

  final String orderId;

  @override
  ConsumerState<OrderTrackingScreen> createState() =>
      _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends ConsumerState<OrderTrackingScreen> {
  TrackingLinkView? _linkView;
  List<NotificationRecord> _notifications = const <NotificationRecord>[];
  NotificationProviderState? _provider;

  /// The plaintext URL, held for THIS SCREEN ONLY and never persisted.
  String? _oneTimeUrl;

  /// A fresh RFC-4122 v4 identifier for one command.
  ///
  /// The server validates `client_reference` as a UUID, and it is the
  /// idempotency key: a replayed issue command with the same reference is
  /// refused rather than minting a second live link. A NEW one is generated per
  /// deliberate operator action, because two different acts must never share a
  /// reference (Rule 20 hard rule 13).
  ///
  /// Built from `Random.secure()` rather than adding a package: Rule 37 requires
  /// owner approval before a new dependency, and this is four lines.
  static String _newClientReference() {
    final Random random = Random.secure();
    final List<int> bytes = List<int>.generate(16, (_) => random.nextInt(256));

    bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
    bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant 10xx

    final String hex = bytes
        .map((int b) => b.toRadixString(16).padLeft(2, '0'))
        .join();

    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final repository = ref.read(trackingRepositoryProvider);

    final linkResult = await repository.link(widget.orderId);
    final notificationsResult = await repository.notifications(widget.orderId);
    final providerResult = await repository.providerState();

    if (!mounted) {
      return;
    }

    setState(() {
      _loading = false;

      switch (linkResult) {
        case Ok<TrackingLinkView?>(:final value):
          _linkView = value;
        case Err<TrackingLinkView?>(:final failure):
          _error = failure.message;
      }

      if (notificationsResult case Ok<List<NotificationRecord>>(:final value)) {
        _notifications = value;
      }

      if (providerResult case Ok<NotificationProviderState>(:final value)) {
        _provider = value;
      }
    });
  }

  Future<void> _run(Future<Result<void>> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });

    final result = await action();

    if (!mounted) {
      return;
    }

    if (result case Err<void>(:final failure)) {
      setState(() {
        _busy = false;
        _error = failure.message;
      });
      return;
    }

    setState(() => _busy = false);
    await _load();
  }

  Future<void> _issue() async {
    final repository = ref.read(trackingRepositoryProvider);
    final reference = _newClientReference();

    setState(() {
      _busy = true;
      _error = null;
    });

    final result = await repository.issue(
      widget.orderId,
      clientReference: reference,
    );

    if (!mounted) {
      return;
    }

    switch (result) {
      case Ok<IssuedTrackingLink>(:final value):
        setState(() {
          _busy = false;
          // Held in memory only. Nothing writes this to storage.
          _oneTimeUrl = value.url;
        });
        await _load();
      case Err<IssuedTrackingLink>(:final failure):
        setState(() {
          _busy = false;
          _error = failure.message;
        });
    }
  }

  Future<void> _rotate(TrackingLink link) async {
    final reason = await _askReason(
      title: 'Rotasi tautan pelacakan',
      // Rotation invalidates the link the customer is holding, so the
      // consequence is restated at the moment of commitment (Rule 32 rule 15).
      warning:
          'Tautan lama akan langsung berhenti berlaku. Pelanggan perlu tautan baru.',
    );

    if (reason == null) {
      return;
    }

    final repository = ref.read(trackingRepositoryProvider);
    final reference = _newClientReference();

    setState(() {
      _busy = true;
      _error = null;
    });

    final result = await repository.rotate(
      link.id,
      reasonCode: reason,
      clientReference: reference,
    );

    if (!mounted) {
      return;
    }

    switch (result) {
      case Ok<IssuedTrackingLink>(:final value):
        setState(() {
          _busy = false;
          _oneTimeUrl = value.url;
        });
        await _load();
      case Err<IssuedTrackingLink>(:final failure):
        setState(() {
          _busy = false;
          _error = failure.message;
        });
    }
  }

  Future<void> _revoke(TrackingLink link) async {
    final reason = await _askReason(
      title: 'Cabut tautan pelacakan',
      warning:
          'Tautan ini akan berhenti berlaku selamanya dan tidak dapat diaktifkan kembali. '
          'Untuk memberi akses lagi, buat tautan baru.',
    );

    if (reason == null) {
      return;
    }

    await _run(() async {
      final result = await ref
          .read(trackingRepositoryProvider)
          .revoke(link.id, reasonCode: reason);
      return result.map((_) {});
    });
  }

  Future<void> _retry(NotificationRecord record) => _run(() async {
    final result = await ref.read(trackingRepositoryProvider).retry(record.id);
    return result.map((_) {});
  });

  Future<void> _prepareManualLink(NotificationRecord record) async {
    final repository = ref.read(trackingRepositoryProvider);

    setState(() {
      _busy = true;
      _error = null;
    });

    final result = await repository.prepareManualLink(record.id);

    if (!mounted) {
      return;
    }

    switch (result) {
      case Ok<PreparedManualLink>(:final value):
        setState(() => _busy = false);
        await _showManualLink(value);
        await _load();
      case Err<PreparedManualLink>(:final failure):
        setState(() {
          _busy = false;
          _error = failure.message;
        });
    }
  }

  /// A mandatory reason code. Empty is refused, and whitespace is not a reason —
  /// a reason field that accepts a space records nothing (Rule 32 rule 16).
  Future<String?> _askReason({
    required String title,
    required String warning,
  }) async {
    String selected = 'lost';

    return showDialog<String>(
      context: context,
      builder: (BuildContext dialogContext) => AlertDialog(
        title: Text(title),
        // SCROLLABLE, not merely sized. Four reason rows plus a warning
        // overflow a short viewport, and they overflow much sooner at a large
        // system font size. Critical information must REFLOW rather than be
        // clipped (Rule 31 hard rule 2, Rule 27 hard rule 7) — clipping the
        // reason list would leave an operator unable to pick a reason at all.
        content: SingleChildScrollView(
          child: StatefulBuilder(
            builder: (BuildContext context, StateSetter setDialogState) => Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(warning),
                const SizedBox(height: 12),
                const Text('Alasan (wajib):'),
                // Selection is carried by a text label plus a CHECK ICON, never
                // by colour or position alone (Rule 27 hard rule 3), and each row
                // is a full-width tap target well over 48dp (Rule 27 rule 5).
                for (final MapEntry<String, String> option
                    in const <String, String>{
                      'lost': 'Pelanggan kehilangan tautan',
                      'over_shared': 'Tautan tersebar terlalu luas',
                      'wrong_recipient': 'Terkirim ke nomor yang salah',
                      'suspected_leak': 'Diduga bocor',
                    }.entries)
                  ListTile(
                    title: Text(option.value),
                    selected: selected == option.key,
                    leading: Icon(
                      selected == option.key
                          ? Icons.radio_button_checked
                          : Icons.radio_button_unchecked,
                    ),
                    onTap: () => setDialogState(() => selected = option.key),
                  ),
              ],
            ),
          ),
        ),
        actions: <Widget>[
          // The SAFE choice is the default focus and is spatially separated
          // from the destructive one (Rule 32 hard rule 14).
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(selected),
            child: const Text('Lanjutkan'),
          ),
        ],
      ),
    );
  }

  Future<void> _showManualLink(PreparedManualLink prepared) => showDialog<void>(
    context: context,
    builder: (BuildContext dialogContext) => AlertDialog(
      title: const Text('Tautan WhatsApp manual'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          // FR-095: explicit, visible, and NEVER described as automation.
          Text(prepared.notice),
          const SizedBox(height: 12),
          SelectableText(prepared.url),
        ],
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () async {
            await Clipboard.setData(ClipboardData(text: prepared.url));
            if (dialogContext.mounted) {
              Navigator.of(dialogContext).pop();
            }
          },
          child: const Text('Salin tautan'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('Tutup'),
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final SessionState? session = ref
        .watch(authServiceProvider)
        .current
        .session;

    if (session == null || !session.hasTenantContext) {
      // No tenant context means nothing on this screen has a tenant to belong
      // to. Rendering it would be rendering a screen with no answer to "whose
      // data is this?" (Rule 28 hard rule 1).
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return OpsMasterDataScaffold(
      title: 'Pelacakan & Pesan',
      session: session,
      onBack: () => Navigator.of(context).maybePop(),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: <Widget>[
                  if (_error != null) _ErrorBanner(message: _error!),
                  _linkSection(session),
                  const SizedBox(height: 24),
                  _notificationSection(),
                ],
              ),
            ),
    );
  }

  Widget _linkSection(SessionState session) {
    final TrackingLink? link = _linkView?.link;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Tautan pelacakan pelanggan',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 8),

        if (link == null)
          const Text(
            'Belum ada tautan pelacakan untuk pesanan ini. Buat tautan lalu '
            'kirimkan kepada pelanggan.',
          )
        else ...<Widget>[
          trackingLinkChip(link),
          const SizedBox(height: 8),
          _LinkFacts(link: link),
        ],

        if (_oneTimeUrl != null) ...<Widget>[
          const SizedBox(height: 12),
          _OneTimeLinkCard(
            url: _oneTimeUrl!,
            onCopy: () async {
              await Clipboard.setData(ClipboardData(text: _oneTimeUrl!));
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text(
                      'Tautan disalin. Papan klip dapat dibaca aplikasi lain — '
                      'kirim sekarang lalu bersihkan bila perlu.',
                    ),
                  ),
                );
              }
            },
          ),
        ],

        const SizedBox(height: 12),
        // The tenant is restated in the same visual block as the committing
        // action (Rule 28 hard rule 2).
        CommitContextLine(
          session: session,
          action: 'Kelola tautan pelacakan pelanggan',
        ),
        const SizedBox(height: 8),

        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: <Widget>[
            // A control the operator may not use is NOT rendered (Rule 28
            // hard rule 5): rotate and revoke appear only for a live link,
            // because a terminal link cannot be either.
            if (link == null || link.state.isTerminal)
              FilledButton.icon(
                onPressed: _busy ? null : _issue,
                icon: const Icon(Icons.add_link),
                label: const Text('Buat tautan'),
              ),
            if (link != null && link.isLive) ...<Widget>[
              OutlinedButton.icon(
                onPressed: _busy ? null : () => _rotate(link),
                icon: const Icon(Icons.autorenew),
                label: const Text('Rotasi tautan'),
              ),
              OutlinedButton.icon(
                onPressed: _busy ? null : () => _revoke(link),
                icon: const Icon(Icons.link_off),
                label: const Text('Cabut tautan'),
              ),
            ],
          ],
        ),

        if (_linkView != null && _linkView!.timeline.isNotEmpty) ...<Widget>[
          const SizedBox(height: 16),
          Text('Riwayat tautan', style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 4),
          for (final TrackingLinkEvent event in _linkView!.timeline)
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              title: Text(event.label),
              subtitle: Text(_formatTime(event.occurredAt)),
            ),
        ],
      ],
    );
  }

  Widget _notificationSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Pesan ke pelanggan',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 8),

        // Honest about what is automated (FR-094/FR-095): when no provider is
        // configured, the surface says so rather than implying automation.
        if (_provider != null)
          Row(
            children: <Widget>[
              Icon(
                _provider!.available
                    ? Icons.cloud_done_outlined
                    : Icons.cloud_off_outlined,
                size: 18,
              ),
              const SizedBox(width: 6),
              Expanded(child: Text(_provider!.label)),
            ],
          ),

        const SizedBox(height: 8),

        if (_notifications.isEmpty)
          const Text('Belum ada pesan yang dibuat untuk pesanan ini.')
        else
          for (final NotificationRecord record in _notifications)
            _NotificationTile(
              record: record,
              busy: _busy,
              onRetry: () => _retry(record),
              onManualLink: () => _prepareManualLink(record),
            ),
      ],
    );
  }

  static String _formatTime(DateTime? value) => value == null
      ? '—'
      : '${value.day.toString().padLeft(2, '0')}/'
            '${value.month.toString().padLeft(2, '0')}/${value.year} '
            '${value.hour.toString().padLeft(2, '0')}:'
            '${value.minute.toString().padLeft(2, '0')}';
}

class _LinkFacts extends StatelessWidget {
  const _LinkFacts({required this.link});

  final TrackingLink link;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Text(
        'Berlaku sampai: '
        '${_OrderTrackingScreenStateFormat.time(link.expiresAt)}',
      ),
      // "Has the customer opened it?" is the question a counter actually asks,
      // and answering it stops staff re-sending unnecessarily.
      Text('Dibuka pelanggan: ${link.viewCount}×'),
      if (link.lastViewedAt != null)
        Text(
          'Terakhir dibuka: '
          '${_OrderTrackingScreenStateFormat.time(link.lastViewedAt)}',
        ),
      if (link.revokeReasonCode != null)
        Text('Alasan pencabutan: ${link.revokeReasonCode}'),
    ],
  );
}

abstract final class _OrderTrackingScreenStateFormat {
  static String time(DateTime? value) =>
      _OrderTrackingScreenState._formatTime(value);
}

/// The one-time URL card.
///
/// Says "ditampilkan sekali" explicitly, because it is true and because the
/// recovery when it is lost (rotation) is different from the recovery a user
/// would otherwise assume (re-open the screen).
class _OneTimeLinkCard extends StatelessWidget {
  const _OneTimeLinkCard({required this.url, required this.onCopy});

  final String url;
  final VoidCallback onCopy;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Text(
            'Tautan ini hanya DITAMPILKAN SEKALI. Salin dan kirimkan kepada '
            'pelanggan sekarang. Bila hilang, buat tautan baru melalui rotasi.',
          ),
          const SizedBox(height: 8),
          SelectableText(url),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: onCopy,
              icon: const Icon(Icons.copy),
              label: const Text('Salin tautan'),
            ),
          ),
        ],
      ),
    ),
  );
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({
    required this.record,
    required this.busy,
    required this.onRetry,
    required this.onManualLink,
  });

  final NotificationRecord record;
  final bool busy;
  final VoidCallback onRetry;
  final VoidCallback onManualLink;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            notificationEventLabel(record.eventType),
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: 6),
          notificationChip(record),
          const SizedBox(height: 6),
          // Masked even for staff (Rule 32 hard rule 4).
          Text('Ke: ${record.recipientMasked}'),
          Text('Percobaan: ${record.attemptCount}/${record.maxAttempts}'),

          // A suppressed message states WHY, so a tenant can act on it rather
          // than wondering.
          if (record.suppressionLabel != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text('Tidak dikirim: ${record.suppressionLabel}'),
            ),

          if (record.deferredForQuietHours)
            const Padding(
              padding: EdgeInsets.only(top: 6),
              child: Text(
                'Ditunda karena jam tenang (20.00–08.00 waktu outlet). '
                'Pesan akan dikirim otomatis setelah jam tenang berakhir.',
              ),
            ),

          // DEC-0040. Shown so a message sent inside 20.00–08.00 explains
          // itself: an operator who sees one at 02.00 reads WHY it was
          // permitted instead of concluding quiet hours were broken. The
          // wording comes from the server, so the two surfaces cannot drift.
          if (record.securityClassificationLabel != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(record.securityClassificationLabel!),
            ),

          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: <Widget>[
              // Offered only when the server says a retry is possible — never a
              // control that would be refused (Rule 28 hard rule 5).
              if (record.canRetry)
                OutlinedButton.icon(
                  onPressed: busy ? null : onRetry,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Coba kirim lagi'),
                ),
              if (record.state.warrantsManualFallback)
                OutlinedButton.icon(
                  onPressed: busy ? null : onManualLink,
                  icon: const Icon(Icons.open_in_new),
                  label: const Text('Kirim manual lewat WhatsApp'),
                ),
            ],
          ),
        ],
      ),
    ),
  );
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Row(
      children: <Widget>[
        const Icon(Icons.error_outline),
        const SizedBox(width: 8),
        // The message states what happened AND what to do next; it comes from
        // the server's Bahasa Indonesia error contract (Rule 29 hard rule 9).
        Expanded(child: Text(message)),
      ],
    ),
  );
}
