import 'package:meta/meta.dart';

/// The lifecycle state of a customer's tracking access (Step 7, FR-088).
///
/// Mirrors `App\Modules\Tracking\Models\TrackingToken::STATES` on the server
/// EXACTLY. Server-authoritative: a client displays it and asks the server for a
/// transition, it never sets one. [parse] throws on an unknown value rather than
/// inventing a member, because a surface that silently accepted an unrecognised
/// state would render a link it does not understand as though it did.
enum TrackingLinkState {
  issued,
  revoked,
  expired,
  superseded;

  static TrackingLinkState parse(String value) => switch (value) {
    'ISSUED' => TrackingLinkState.issued,
    'REVOKED' => TrackingLinkState.revoked,
    'EXPIRED' => TrackingLinkState.expired,
    'SUPERSEDED' => TrackingLinkState.superseded,
    _ => throw ArgumentError.value(
      value,
      'state',
      'Status tautan pelacakan tidak dikenal',
    ),
  };

  String get wireValue => switch (this) {
    TrackingLinkState.issued => 'ISSUED',
    TrackingLinkState.revoked => 'REVOKED',
    TrackingLinkState.expired => 'EXPIRED',
    TrackingLinkState.superseded => 'SUPERSEDED',
  };

  /// Bahasa Indonesia label (Rule 30). The enum name is the technical id.
  String get label => switch (this) {
    TrackingLinkState.issued => 'Aktif',
    TrackingLinkState.revoked => 'Dicabut',
    TrackingLinkState.expired => 'Kedaluwarsa',
    TrackingLinkState.superseded => 'Diganti tautan baru',
  };

  /// Terminal states are never reactivated; recovery is a NEW issuance
  /// (TRACKING_ACCESS_LIFECYCLE §5). An operator surface uses this to decide
  /// whether to offer rotate/revoke at all, rather than offering a control that
  /// would be refused (Rule 28 hard rule 5).
  bool get isTerminal => this != TrackingLinkState.issued;
}

/// A tracking link as an operator may see it — METADATA ONLY.
///
/// THERE IS NO TOKEN FIELD ON THIS CLASS AND THERE NEVER WILL BE. The plaintext
/// is returned exactly once, at issuance or rotation, and is unrecoverable
/// afterwards because only its hash is stored (TRK-002, TRK-019). The hash is
/// not carried either: an operator has no use for it, and Rule 32 hard rule 10
/// is explicit that ops surfaces show tracking STATE and a revoke control, never
/// the token.
@immutable
final class TrackingLink {
  const TrackingLink({
    required this.id,
    required this.orderId,
    required this.state,
    required this.viewCount,
    required this.isLive,
    required this.version,
    this.issuedAt,
    this.expiresAt,
    this.lastViewedAt,
    this.revokedAt,
    this.revokeReasonCode,
    this.supersededAt,
  });

  factory TrackingLink.fromJson(Map<String, Object?> json) => TrackingLink(
    id: json['id']! as String,
    orderId: json['order_id']! as String,
    state: TrackingLinkState.parse(json['state']! as String),
    viewCount: (json['view_count'] as num?)?.toInt() ?? 0,
    isLive: json['is_live'] as bool? ?? false,
    version: (json['version'] as num?)?.toInt() ?? 1,
    issuedAt: _parseTime(json['issued_at']),
    expiresAt: _parseTime(json['expires_at']),
    lastViewedAt: _parseTime(json['last_viewed_at']),
    revokedAt: _parseTime(json['revoked_at']),
    revokeReasonCode: json['revoke_reason_code'] as String?,
    supersededAt: _parseTime(json['superseded_at']),
  );

  final String id;
  final String orderId;
  final TrackingLinkState state;

  /// How many times the customer (or whoever they forwarded it to) opened it.
  /// This is the question a counter actually asks — "has the customer seen it?"
  /// — and answering it stops staff re-sending unnecessarily.
  final int viewCount;

  /// The SERVER's judgement of whether the link resolves right now. Not derived
  /// from [state] and [expiresAt] on the client: expiry is decided server-side
  /// against server time, and a client clock never extends an access.
  final bool isLive;

  final int version;
  final DateTime? issuedAt;
  final DateTime? expiresAt;
  final DateTime? lastViewedAt;
  final DateTime? revokedAt;
  final String? revokeReasonCode;
  final DateTime? supersededAt;

  static DateTime? _parseTime(Object? value) =>
      value is String ? DateTime.tryParse(value)?.toLocal() : null;
}

/// The result of ISSUING or ROTATING a link — the one moment the URL exists.
///
/// [url] is shown once and cannot be retrieved again. The surface that displays
/// it says so explicitly, and offers rotation as the recovery path when a
/// customer loses it (TRK-019).
@immutable
final class IssuedTrackingLink {
  const IssuedTrackingLink({
    required this.link,
    required this.url,
    required this.notice,
  });

  factory IssuedTrackingLink.fromResponse(Map<String, Object?> data) =>
      IssuedTrackingLink(
        link: TrackingLink.fromJson(
          data['tracking_link']! as Map<String, Object?>,
        ),
        url: data['url']! as String,
        notice: data['notice'] as String? ?? '',
      );

  final TrackingLink link;

  /// SHOWN ONCE. Never persisted by the client, never written to a log, never
  /// placed on the clipboard automatically (Rule 32 hard rule 23).
  final String url;

  final String notice;
}

/// One entry of a tracking link's append-only lifecycle audit (TRK-024).
@immutable
final class TrackingLinkEvent {
  const TrackingLinkEvent({
    required this.type,
    this.actorMembershipId,
    this.occurredAt,
  });

  factory TrackingLinkEvent.fromJson(Map<String, Object?> json) =>
      TrackingLinkEvent(
        type: json['type']! as String,
        actorMembershipId: json['actor_membership_id'] as String?,
        occurredAt: json['occurred_at'] is String
            ? DateTime.tryParse(json['occurred_at']! as String)?.toLocal()
            : null,
      );

  final String type;
  final String? actorMembershipId;
  final DateTime? occurredAt;

  /// Bahasa Indonesia label. Unknown types render as-is rather than being
  /// dropped: a timeline that silently omits an event it does not recognise is
  /// a timeline that cannot be trusted as a record.
  String get label => switch (type) {
    'TrackingAccessIssued' => 'Tautan dibuat',
    'TrackingAccessViewed' => 'Tautan dibuka pelanggan',
    'TrackingAccessRevoked' => 'Tautan dicabut',
    'TrackingAccessExpired' => 'Tautan kedaluwarsa',
    'TrackingAccessReissued' => 'Tautan dirotasi',
    'TrackingAccessSuperseded' => 'Tautan lama diganti',
    'TrackingOtpChallengeIssued' => 'Kode verifikasi dibuat',
    'TrackingOtpVerified' => 'Kode verifikasi berhasil',
    'TrackingOtpFailed' => 'Kode verifikasi gagal',
    _ => type,
  };
}
