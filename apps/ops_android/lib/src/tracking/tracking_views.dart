import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';

/// The status chip for a tracking link's SERVER-AUTHORITATIVE state (FR-088).
///
/// Only an ISSUED-and-live link reads as success. Everything else is
/// deliberately not-success: a revoked, expired, or superseded link no longer
/// works for the customer, and rendering any of them in a settled green would
/// tell a counter the opposite of the truth.
StatusChip trackingLinkChip(TrackingLink link) => StatusChip(
  label: link.isLive ? link.state.label : _terminalLabel(link),
  icon: _linkIcon(link),
  tone: link.isLive ? StatusTone.success : _linkTone(link.state),
);

/// A link whose row still reads ISSUED but whose expiry has passed is EXPIRED to
/// the customer. The server decides that (`is_live`); this only makes sure the
/// label agrees rather than showing "Aktif" for a link that does not open.
String _terminalLabel(TrackingLink link) =>
    link.state == TrackingLinkState.issued
    ? TrackingLinkState.expired.label
    : link.state.label;

// Meaning is carried by text AND icon; tone only reinforces (Rule 27 hard
// rule 3). Adjacent states differ in SHAPE, not merely hue, so the chip is
// readable in greyscale and in daylight.
IconData _linkIcon(TrackingLink link) {
  if (!link.isLive && link.state == TrackingLinkState.issued) {
    return Icons.schedule_outlined;
  }

  return switch (link.state) {
    TrackingLinkState.issued => Icons.link_outlined,
    TrackingLinkState.revoked => Icons.link_off_outlined,
    TrackingLinkState.expired => Icons.schedule_outlined,
    TrackingLinkState.superseded => Icons.autorenew_outlined,
  };
}

StatusTone _linkTone(TrackingLinkState state) => switch (state) {
  TrackingLinkState.issued => StatusTone.warning,
  TrackingLinkState.revoked => StatusTone.danger,
  TrackingLinkState.expired => StatusTone.neutral,
  TrackingLinkState.superseded => StatusTone.neutral,
};

/// The status chip for one notification.
///
/// THE ONLY SUCCESS TONE IS [NotificationState.sent], and even its LABEL says
/// "diterima penyedia" rather than "terkirim ke pelanggan" — we hold no delivery
/// receipt (Rule 01). `MANUAL_FALLBACK_PREPARED` is deliberately NOT success:
/// nothing has been sent, and showing it as success would present the fallback
/// as automation (FR-095).
StatusChip notificationChip(NotificationRecord record) => StatusChip(
  label: record.stateLabel,
  icon: _notificationIcon(record.state),
  tone: _notificationTone(record.state),
);

IconData _notificationIcon(NotificationState state) => switch (state) {
  NotificationState.pending => Icons.schedule_send_outlined,
  NotificationState.deferred => Icons.bedtime_outlined,
  NotificationState.sending => Icons.sync_outlined,
  NotificationState.sent => Icons.cloud_done_outlined,
  NotificationState.failedRetryable => Icons.sync_problem_outlined,
  NotificationState.failedPermanent => Icons.error_outline,
  NotificationState.suppressed => Icons.block_outlined,
  NotificationState.manualFallbackPrepared => Icons.open_in_new_outlined,
};

StatusTone _notificationTone(NotificationState state) => switch (state) {
  // The ONLY success tone in this map.
  NotificationState.sent => StatusTone.success,
  NotificationState.pending => StatusTone.neutral,
  NotificationState.deferred => StatusTone.neutral,
  NotificationState.sending => StatusTone.syncing,
  NotificationState.failedRetryable => StatusTone.warning,
  NotificationState.failedPermanent => StatusTone.danger,
  NotificationState.suppressed => StatusTone.neutral,
  NotificationState.manualFallbackPrepared => StatusTone.warning,
};

/// A short Bahasa Indonesia label for a notification event (Rule 30).
String notificationEventLabel(String eventType) => switch (eventType) {
  'order.received' => 'Pesanan diterima',
  'order.in_production' => 'Pesanan dikerjakan',
  'order.ready' => 'Siap diambil',
  'payment.recorded' => 'Pembayaran dicatat',
  'tracking.otp.requested' => 'Kode verifikasi pelanggan',
  'marketing.promo' => 'Promosi',
  _ => eventType,
};
