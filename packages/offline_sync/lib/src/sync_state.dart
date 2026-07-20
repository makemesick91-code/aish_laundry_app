/// The honest state of one locally-originated operation.
///
/// Rule 29 hard rule 1: a queued operation is NEVER rendered as a committed one.
/// These are the only values a surface may render, and the Bahasa Indonesia
/// labels are fixed here so two screens cannot describe the same state
/// differently.
enum SyncState {
  /// Written to the device, not yet offered to the server.
  storedOnDevice('TERSIMPAN DI PERANGKAT'),

  /// Offered to the server; no confirmation yet. NOT success.
  awaitingSync('MENUNGGU SINKRONISASI'),

  /// Server-confirmed. The ONLY state that may be rendered as success.
  synced('TERSINKRON'),

  /// Failed and requires a human. Never silently dropped, never auto-resolved.
  failedNeedsAction('GAGAL — PERLU TINDAKAN');

  const SyncState(this.label);

  /// The canonical Bahasa Indonesia label.
  final String label;

  /// Whether success styling — the success colour, the tick, the word
  /// "berhasil" — may be used.
  ///
  /// Only [SyncState.synced] qualifies. This getter exists so no screen has to
  /// remember the rule, and so a reviewer can grep for a violation.
  bool get mayRenderAsSuccess => this == SyncState.synced;
}

/// Overall connectivity and queue health, for the always-visible Ops indicator.
enum SyncHealth {
  /// Online with an empty queue.
  idle,

  /// Online and working through the queue.
  syncing,

  /// No network. Work continues locally.
  offline,

  /// At least one operation needs a human decision.
  attentionRequired,
}
