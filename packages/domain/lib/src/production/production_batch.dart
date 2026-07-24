import 'package:meta/meta.dart';

import 'production_job.dart' show ProductionServiceType;

/// The lifecycle status of a production batch (Step 6, FR-074).
///
/// Mirrors `App\Modules\Production\Models\ProductionBatch::STATUSES` on the
/// server EXACTLY. Server-authoritative: a client displays it and asks the server
/// to close a batch, it never sets the status itself. [parse] throws on an
/// unknown value rather than inventing a member.
enum ProductionBatchStatus {
  open,
  closed;

  static ProductionBatchStatus parse(String value) => switch (value) {
    'open' => ProductionBatchStatus.open,
    'closed' => ProductionBatchStatus.closed,
    _ => throw ArgumentError.value(
      value,
      'status',
      'Status batch tidak dikenal',
    ),
  };

  String get wireValue => switch (this) {
    ProductionBatchStatus.open => 'open',
    ProductionBatchStatus.closed => 'closed',
  };

  /// Bahasa Indonesia label (Rule 30).
  String get label => switch (this) {
    ProductionBatchStatus.open => 'Terbuka',
    ProductionBatchStatus.closed => 'Ditutup',
  };

  bool get isOpen => this == ProductionBatchStatus.open;
}

/// The list projection of a production batch (server `BatchProjection::summary`).
/// Carries NO money and no customer personal data — minimal exposure (Rule 32).
@immutable
final class ProductionBatchSummary {
  const ProductionBatchSummary({
    required this.id,
    required this.code,
    required this.stage,
    required this.status,
    required this.version,
    required this.outletId,
    required this.itemCount,
    this.closedAt,
    this.updatedAt,
  });

  factory ProductionBatchSummary.fromJson(Map<String, Object?> json) =>
      ProductionBatchSummary(
        id: json['id']! as String,
        code: json['code']! as String,
        stage: json['stage']! as String,
        status: ProductionBatchStatus.parse(json['status']! as String),
        version: (json['version']! as num).toInt(),
        outletId: json['outlet_id']! as String,
        itemCount: (json['item_count'] as num?)?.toInt() ?? 0,
        closedAt: json['closed_at'] as String?,
        updatedAt: json['updated_at'] as String?,
      );

  final String id;
  final String code;
  final String stage;
  final ProductionBatchStatus status;

  /// The optimistic-concurrency token, sent back as `expected_version` on the
  /// next command so a change applied against a stale read is refused rather than
  /// overwriting a concurrent one.
  final int version;

  final String outletId;
  final int itemCount;

  /// ISO-8601 server timestamp; present only when [status] is closed.
  final String? closedAt;
  final String? updatedAt;

  bool get isOpen => status.isOpen;
}

/// One current member of a batch (server `BatchProjection::detail` item shape).
@immutable
final class ProductionBatchMember {
  const ProductionBatchMember({
    required this.productionItemId,
    this.serviceType,
    this.stage,
    this.addedAt,
  });

  factory ProductionBatchMember.fromJson(Map<String, Object?> json) =>
      ProductionBatchMember(
        productionItemId: json['production_item_id']! as String,
        serviceType: json['service_type'] is String
            ? ProductionServiceType.parse(json['service_type']! as String)
            : null,
        stage: json['stage'] as String?,
        addedAt: json['added_at'] as String?,
      );

  final String productionItemId;
  final ProductionServiceType? serviceType;
  final String? stage;
  final String? addedAt;
}

/// One entry of the append-only batch timeline (server
/// `BatchProjection::timeline`). No history is ever deleted (FR-074).
@immutable
final class ProductionBatchTimelineEntry {
  const ProductionBatchTimelineEntry({
    required this.type,
    this.actorMembershipId,
    this.productionItemId,
    this.occurredAt,
  });

  factory ProductionBatchTimelineEntry.fromJson(Map<String, Object?> json) =>
      ProductionBatchTimelineEntry(
        type: json['type']! as String,
        actorMembershipId: json['actor_membership_id'] as String?,
        productionItemId: json['production_item_id'] as String?,
        occurredAt: json['occurred_at'] as String?,
      );

  final String type;
  final String? actorMembershipId;
  final String? productionItemId;
  final String? occurredAt;
}

/// The full projection of a batch: its summary, its current members, and its
/// append-only timeline (assembled from the server `show` response
/// `{ batch: detail, timeline: [...] }`).
@immutable
final class ProductionBatchDetail {
  const ProductionBatchDetail({
    required this.summary,
    required this.items,
    required this.timeline,
  });

  factory ProductionBatchDetail.fromResponse(Map<String, Object?> data) {
    final batch = data['batch'];
    final batchMap = batch is Map<String, Object?>
        ? batch
        : const <String, Object?>{};

    final rawItems = batchMap['items'];
    final items = rawItems is List
        ? rawItems
              .cast<Map<String, Object?>>()
              .map(ProductionBatchMember.fromJson)
              .toList(growable: false)
        : const <ProductionBatchMember>[];

    final rawTimeline = data['timeline'];
    final timeline = rawTimeline is List
        ? rawTimeline
              .cast<Map<String, Object?>>()
              .map(ProductionBatchTimelineEntry.fromJson)
              .toList(growable: false)
        : const <ProductionBatchTimelineEntry>[];

    return ProductionBatchDetail(
      summary: ProductionBatchSummary.fromJson(batchMap),
      items: items,
      timeline: timeline,
    );
  }

  final ProductionBatchSummary summary;
  final List<ProductionBatchMember> items;
  final List<ProductionBatchTimelineEntry> timeline;

  String get id => summary.id;
  ProductionBatchStatus get status => summary.status;
  int get version => summary.version;
}
