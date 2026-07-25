import 'package:meta/meta.dart';

/// The state of one notification intent (Step 7, FR-096 … FR-099).
///
/// Mirrors `App\Modules\Notification\Models\NotificationIntent::STATES`.
///
/// [sent] MEANS THE PROVIDER ACCEPTED THE MESSAGE. It does not mean the customer
/// received it, and no label in this enum says otherwise — we hold no delivery
/// receipt, and claiming one would be a false claim (Rule 01).
enum NotificationState {
  pending,
  deferred,
  sending,
  sent,
  failedRetryable,
  failedPermanent,
  suppressed,
  manualFallbackPrepared;

  static NotificationState parse(String value) => switch (value) {
    'PENDING' => NotificationState.pending,
    'DEFERRED' => NotificationState.deferred,
    'SENDING' => NotificationState.sending,
    'SENT' => NotificationState.sent,
    'FAILED_RETRYABLE' => NotificationState.failedRetryable,
    'FAILED_PERMANENT' => NotificationState.failedPermanent,
    'SUPPRESSED' => NotificationState.suppressed,
    'MANUAL_FALLBACK_PREPARED' => NotificationState.manualFallbackPrepared,
    _ => throw ArgumentError.value(
      value,
      'state',
      'Status pesan tidak dikenal',
    ),
  };

  String get wireValue => switch (this) {
    NotificationState.pending => 'PENDING',
    NotificationState.deferred => 'DEFERRED',
    NotificationState.sending => 'SENDING',
    NotificationState.sent => 'SENT',
    NotificationState.failedRetryable => 'FAILED_RETRYABLE',
    NotificationState.failedPermanent => 'FAILED_PERMANENT',
    NotificationState.suppressed => 'SUPPRESSED',
    NotificationState.manualFallbackPrepared => 'MANUAL_FALLBACK_PREPARED',
  };

  /// The fallback label used when the server sends none. The server's own
  /// `state_label` is preferred, so the two surfaces cannot disagree; this
  /// exists so an older build still renders something honest.
  String get label => switch (this) {
    NotificationState.pending => 'Menunggu dikirim',
    NotificationState.deferred => 'Ditunda sampai di luar jam tenang',
    NotificationState.sending => 'Sedang dikirim',
    // NOT "terkirim ke pelanggan".
    NotificationState.sent => 'Diterima penyedia pesan',
    NotificationState.failedRetryable => 'Gagal — akan dicoba lagi otomatis',
    NotificationState.failedPermanent =>
      'Gagal permanen — kirim manual lewat WhatsApp',
    NotificationState.suppressed => 'Tidak dikirim',
    NotificationState.manualFallbackPrepared =>
      'Tautan manual disiapkan — staf perlu mengirimnya',
  };

  /// Whether this state is a settled SUCCESS for display purposes.
  ///
  /// Only [sent] qualifies, and even that means "accepted by the provider".
  /// [manualFallbackPrepared] deliberately does NOT: nothing has been sent, and
  /// rendering it as success would present the fallback as automation (FR-095).
  bool get isProviderAccepted => this == NotificationState.sent;

  /// Whether the operator should be offered the manual WhatsApp fallback.
  bool get warrantsManualFallback =>
      this == NotificationState.failedPermanent ||
      this == NotificationState.failedRetryable;
}

/// One notification as an operator may see it.
///
/// The recipient is MASKED even here. An operator needs to confirm which
/// customer a message went to, which the last four digits answer; a full phone
/// number on a message-history screen is a personal datum on display all day on
/// a counter terminal (Rule 32 hard rule 4).
@immutable
final class NotificationRecord {
  const NotificationRecord({
    required this.id,
    required this.eventType,
    required this.category,
    required this.state,
    required this.stateLabel,
    required this.recipientMasked,
    required this.attemptCount,
    required this.maxAttempts,
    required this.canRetry,
    required this.deferredForQuietHours,
    this.orderId,
    this.templateKey,
    this.suppressionReason,
    this.suppressionLabel,
    this.securityClassificationLabel,
    this.scheduledFor,
    this.acceptedAt,
    this.failureCode,
    this.providerKey,
  });

  factory NotificationRecord.fromJson(Map<String, Object?> json) =>
      NotificationRecord(
        id: json['id']! as String,
        eventType: json['event_type']! as String,
        category: json['category']! as String,
        state: NotificationState.parse(json['state']! as String),
        // The SERVER's label wins, so the two surfaces cannot drift apart.
        stateLabel:
            json['state_label'] as String? ??
            NotificationState.parse(json['state']! as String).label,
        recipientMasked: json['recipient_masked'] as String? ?? '',
        attemptCount: (json['attempt_count'] as num?)?.toInt() ?? 0,
        maxAttempts: (json['max_attempts'] as num?)?.toInt() ?? 5,
        canRetry: json['can_retry'] as bool? ?? false,
        deferredForQuietHours:
            json['deferred_for_quiet_hours'] as bool? ?? false,
        orderId: json['order_id'] as String?,
        templateKey: json['template_key'] as String?,
        suppressionReason: json['suppression_reason'] as String?,
        suppressionLabel: json['suppression_label'] as String?,
        // The SERVER's label again. A client that composed its own wording for
        // the DEC-0040 exemption would eventually describe it differently from
        // the record that granted it.
        securityClassificationLabel:
            json['security_classification_label'] as String?,
        scheduledFor: _parseTime(json['scheduled_for']),
        acceptedAt: _parseTime(json['accepted_at']),
        failureCode: json['failure_code'] as String?,
        providerKey: json['provider_key'] as String?,
      );

  final String id;
  final String eventType;
  final String category;
  final NotificationState state;
  final String stateLabel;
  final String recipientMasked;
  final int attemptCount;
  final int maxAttempts;
  final bool canRetry;
  final bool deferredForQuietHours;
  final String? orderId;
  final String? templateKey;
  final String? suppressionReason;
  final String? suppressionLabel;

  /// Why this message was NOT held until 08.00, when it was not (DEC-0040).
  ///
  /// Null for every ordinary message. Present only for the one exempt class — a
  /// verification code the customer explicitly asked for — so an operator
  /// looking at a message sent at 02.00 can see the reason rather than assume
  /// quiet hours were broken.
  final String? securityClassificationLabel;
  final DateTime? scheduledFor;
  final DateTime? acceptedAt;
  final String? failureCode;
  final String? providerKey;

  bool get isMarketing => category == 'marketing';

  static DateTime? _parseTime(Object? value) =>
      value is String ? DateTime.tryParse(value)?.toLocal() : null;
}

/// One append-only attempt against a provider.
@immutable
final class NotificationAttemptRecord {
  const NotificationAttemptRecord({
    required this.attemptNumber,
    required this.providerKey,
    required this.outcome,
    this.failureCode,
    this.detail,
    this.occurredAt,
  });

  factory NotificationAttemptRecord.fromJson(Map<String, Object?> json) =>
      NotificationAttemptRecord(
        attemptNumber: (json['attempt_number'] as num?)?.toInt() ?? 1,
        providerKey: json['provider_key'] as String? ?? '',
        outcome: json['outcome']! as String,
        failureCode: json['failure_code'] as String?,
        detail: json['detail'] as String?,
        occurredAt: json['occurred_at'] is String
            ? DateTime.tryParse(json['occurred_at']! as String)?.toLocal()
            : null,
      );

  final int attemptNumber;
  final String providerKey;
  final String outcome;
  final String? failureCode;
  final String? detail;
  final DateTime? occurredAt;

  /// Bahasa Indonesia label. `accepted` reads "diterima penyedia", never
  /// "terkirim": the provider took the message and that is all we know.
  String get outcomeLabel => switch (outcome) {
    'accepted' => 'Diterima penyedia',
    'rejected' => 'Ditolak penyedia',
    'unavailable' => 'Penyedia tidak tersedia',
    'timeout' => 'Waktu tunggu habis',
    'malformed' => 'Respons penyedia tidak terbaca',
    'error' => 'Galat pengiriman',
    'manual_link_prepared' => 'Tautan manual disiapkan',
    _ => outcome,
  };
}

/// Whether the tenant currently has an automated channel at all (FR-094).
///
/// Carries the adapter KEY and availability, never a credential or an endpoint:
/// a status projection that leaked configuration would be a configuration
/// disclosure (Rule 03).
@immutable
final class NotificationProviderState {
  const NotificationProviderState({
    required this.key,
    required this.available,
    required this.label,
  });

  factory NotificationProviderState.fromJson(Map<String, Object?> json) =>
      NotificationProviderState(
        key: json['key']! as String,
        available: json['available'] as bool? ?? false,
        label: json['label'] as String? ?? '',
      );

  final String key;
  final bool available;
  final String label;
}
