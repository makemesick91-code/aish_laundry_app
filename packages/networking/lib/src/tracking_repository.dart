import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';

import 'api_client.dart';
import 'api_endpoints.dart';
import 'api_response.dart';

/// Read/command access to the Step 7 tracking and notification surface
/// (FR-086 … FR-099, DEC-0039).
///
/// ONE PLACE THAT KNOWS THE ENDPOINTS. A screen asks for a typed result; it
/// never builds a path or decodes an envelope.
///
/// THE PLAINTEXT TOKEN CROSSES THIS BOUNDARY EXACTLY TWICE — as the `url` on the
/// result of [issue] and [rotate] — and nothing here stores it, caches it, or
/// logs it. [link] cannot return it, because after issuance nothing on the server
/// can produce it either: only its hash was ever stored (TRK-002, TRK-019).
///
/// There is deliberately NO method to list a tenant's tracking links and NO
/// export method. Both would be enumeration surfaces over live customer
/// credentials, and the absence is asserted by test on the server side.
final class TrackingRepository {
  const TrackingRepository(this._client);

  final ApiClient _client;

  static const int maxPerPage = 100;
  static const int defaultPageSize = 25;

  // =====================================================================
  // Tracking links
  // =====================================================================

  /// The order's current link METADATA, or null when none has been issued.
  ///
  /// Null is a legitimate answer, not an error: an order simply may not have a
  /// link yet, and the surface offers to create one.
  Future<Result<TrackingLinkView?>> link(String orderId) async {
    final result = await _client.get(ApiEndpoints.orderTrackingLink(orderId));

    return _decode(result, (ApiSuccess success) {
      final raw = success.dataAsMap['tracking_link'];
      if (raw is! Map<String, Object?>) {
        return null;
      }

      return TrackingLinkView(
        link: TrackingLink.fromJson(raw),
        timeline: _list(success, 'timeline', TrackingLinkEvent.fromJson),
      );
    });
  }

  /// Issue a link (K-01). The returned URL is shown ONCE and is unrecoverable.
  Future<Result<IssuedTrackingLink>> issue(
    String orderId, {
    required String clientReference,
  }) async {
    final result = await _client.post(
      ApiEndpoints.orderTrackingLink(orderId),
      body: <String, Object?>{'client_reference': clientReference},
    );

    return _decode(
      result,
      (ApiSuccess success) =>
          IssuedTrackingLink.fromResponse(success.dataAsMap),
    );
  }

  /// Rotate (K-10): a NEW link is minted and the old one stops resolving at
  /// once. The reason code is mandatory — knowing WHY a link was rotated is what
  /// distinguishes a lost link from a leaked one.
  Future<Result<IssuedTrackingLink>> rotate(
    String tokenId, {
    required String reasonCode,
    required String clientReference,
    String? reason,
    int? expectedVersion,
  }) async {
    final result = await _client.post(
      ApiEndpoints.trackingLinkRotate(tokenId),
      body: <String, Object?>{
        'reason_code': reasonCode,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
        'client_reference': clientReference,
        'expected_version': ?expectedVersion,
      },
    );

    return _decode(
      result,
      (ApiSuccess success) =>
          IssuedTrackingLink.fromResponse(success.dataAsMap),
    );
  }

  /// Revoke (K-08). Terminal and immediate; there is no un-revoke, because a
  /// revocation the revoker could undo is not a security control.
  Future<Result<TrackingLink>> revoke(
    String tokenId, {
    required String reasonCode,
    String? reason,
    int? expectedVersion,
  }) async {
    final result = await _client.post(
      ApiEndpoints.trackingLinkRevoke(tokenId),
      body: <String, Object?>{
        'reason_code': reasonCode,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
        'expected_version': ?expectedVersion,
      },
    );

    return _decode(
      result,
      (ApiSuccess success) => TrackingLink.fromJson(
        success.dataAsMap['tracking_link']! as Map<String, Object?>,
      ),
    );
  }

  // =====================================================================
  // Notifications
  // =====================================================================

  /// Notification history for one order, newest first.
  Future<Result<List<NotificationRecord>>> notifications(
    String orderId, {
    int perPage = defaultPageSize,
  }) async {
    final result = await _client.get(
      ApiEndpoints.orderNotifications(orderId),
      query: <String, Object?>{'per_page': _boundedPerPage(perPage)},
    );

    return _decode(
      result,
      (ApiSuccess success) =>
          _list(success, 'notifications', NotificationRecord.fromJson),
    );
  }

  /// One notification with its append-only attempt history.
  Future<Result<NotificationDetailView>> notification(String intentId) async {
    final result = await _client.get(ApiEndpoints.notification(intentId));

    return _decode(
      result,
      (ApiSuccess success) => NotificationDetailView(
        record: NotificationRecord.fromJson(
          success.dataAsMap['notification']! as Map<String, Object?>,
        ),
        attempts: _list(
          success,
          'attempts',
          NotificationAttemptRecord.fromJson,
        ),
      ),
    );
  }

  /// Ask the server to attempt this message again.
  ///
  /// The server refuses a terminal intent: retrying a SENT message would be the
  /// duplicate FR-098 forbids, and retrying a SUPPRESSED one would be a route
  /// around opt-out.
  Future<Result<NotificationRecord>> retry(String intentId) async {
    final result = await _client.post(
      ApiEndpoints.notificationRetry(intentId),
      body: const <String, Object?>{},
    );

    return _decode(
      result,
      (ApiSuccess success) => NotificationRecord.fromJson(
        success.dataAsMap['notification']! as Map<String, Object?>,
      ),
    );
  }

  /// PREPARE the manual WhatsApp deep link (FR-095).
  ///
  /// The name says `prepare`, the state says PREPARED, and the returned notice
  /// says a staff member must send it. Nothing here claims a send, because
  /// nothing has been sent.
  Future<Result<PreparedManualLink>> prepareManualLink(String intentId) async {
    final result = await _client.post(
      ApiEndpoints.notificationManualLink(intentId),
      body: const <String, Object?>{},
    );

    return _decode(result, (ApiSuccess success) {
      final manual = success.dataAsMap['manual_link']! as Map<String, Object?>;

      return PreparedManualLink(
        url: manual['url']! as String,
        preparedAt: manual['prepared_at'] is String
            ? DateTime.tryParse(manual['prepared_at']! as String)?.toLocal()
            : null,
        notice: success.dataAsMap['notice'] as String? ?? '',
        record: NotificationRecord.fromJson(
          success.dataAsMap['notification']! as Map<String, Object?>,
        ),
      );
    });
  }

  /// Whether an automated channel exists at all, so the surface can be honest
  /// about what is automated and what is not (FR-094, FR-095).
  Future<Result<NotificationProviderState>> providerState() async {
    final result = await _client.get(ApiEndpoints.notificationProviderState);

    return _decode(
      result,
      (ApiSuccess success) => NotificationProviderState.fromJson(
        success.dataAsMap['provider']! as Map<String, Object?>,
      ),
    );
  }

  // =====================================================================

  /// Turn an unrecognised-enum or malformed payload into an `Err` rather than
  /// letting a parse exception escape as an unhandled async error. A state this
  /// build does not recognise fails SAFE: the caller sees a `Failure`, never a
  /// half-parsed link rendered as though it were understood.
  static Result<T> _decode<T>(
    Result<ApiSuccess> result,
    T Function(ApiSuccess) transform,
  ) => result.flatMap((ApiSuccess success) {
    try {
      return Result<T>.ok(transform(success));
    } on Object catch (error) {
      // The failure TYPE only. A message that echoed the payload could carry a
      // masked recipient or a URL into diagnostics.
      return Result<T>.err(
        Failure(
          kind: FailureKind.unexpected,
          message: 'Malformed tracking response (${error.runtimeType}).',
        ),
      );
    }
  });

  static int _boundedPerPage(int requested) =>
      requested < 1 ? 1 : (requested > maxPerPage ? maxPerPage : requested);

  static List<T> _list<T>(
    ApiSuccess success,
    String key,
    T Function(Map<String, Object?>) parse,
  ) {
    final raw = success.dataAsMap[key];
    if (raw is! List) {
      return const <Never>[];
    }
    return raw.cast<Map<String, Object?>>().map(parse).toList(growable: false);
  }
}

/// A link plus its lifecycle audit, as one screen reads them together.
final class TrackingLinkView {
  const TrackingLinkView({required this.link, required this.timeline});

  final TrackingLink link;
  final List<TrackingLinkEvent> timeline;
}

/// One notification plus its append-only attempt history.
final class NotificationDetailView {
  const NotificationDetailView({required this.record, required this.attempts});

  final NotificationRecord record;
  final List<NotificationAttemptRecord> attempts;
}

/// A manual WhatsApp link a staff member must send THEMSELVES (FR-095).
///
/// Named `Prepared`, not `Sent`. There is no field on this class that could be
/// mistaken for a delivery confirmation, because none exists.
final class PreparedManualLink {
  const PreparedManualLink({
    required this.url,
    required this.notice,
    required this.record,
    this.preparedAt,
  });

  final String url;
  final String notice;
  final NotificationRecord record;
  final DateTime? preparedAt;
}
