import 'package:meta/meta.dart';

import '../pos/order.dart';

/// The internal production lifecycle state of a job (Step 6, DEC-0037).
///
/// These mirror `App\Modules\Production\Models\ProductionJob::STATES` on the
/// server EXACTLY. The state is server-authoritative: a client displays it and
/// asks the server for a transition, it never sets it (Rule 19 hard rule 4).
/// [parse] throws on an unknown value rather than inventing a member, because a
/// production surface that silently accepted an unrecognised state would render
/// a job it does not understand as though it did.
enum ProductionState {
  created,
  inProgress,
  blocked,
  awaitingQc,
  reworkInProgress,
  closed,
  abandoned;

  static ProductionState parse(String value) => switch (value) {
    'CREATED' => ProductionState.created,
    'IN_PROGRESS' => ProductionState.inProgress,
    'BLOCKED' => ProductionState.blocked,
    'AWAITING_QC' => ProductionState.awaitingQc,
    'REWORK_IN_PROGRESS' => ProductionState.reworkInProgress,
    'CLOSED' => ProductionState.closed,
    'ABANDONED' => ProductionState.abandoned,
    _ => throw ArgumentError.value(
      value,
      'state',
      'Status produksi tidak dikenal',
    ),
  };

  /// The exact string on the wire.
  String get wireValue => switch (this) {
    ProductionState.created => 'CREATED',
    ProductionState.inProgress => 'IN_PROGRESS',
    ProductionState.blocked => 'BLOCKED',
    ProductionState.awaitingQc => 'AWAITING_QC',
    ProductionState.reworkInProgress => 'REWORK_IN_PROGRESS',
    ProductionState.closed => 'CLOSED',
    ProductionState.abandoned => 'ABANDONED',
  };

  /// Bahasa Indonesia label (Rule 30). The enum name is the technical id; this
  /// is what an operator reads.
  String get label => switch (this) {
    ProductionState.created => 'Dibuat',
    ProductionState.inProgress => 'Sedang Dikerjakan',
    ProductionState.blocked => 'Ditahan',
    ProductionState.awaitingQc => 'Menunggu Kendali Mutu',
    ProductionState.reworkInProgress => 'Pengerjaan Ulang',
    ProductionState.closed => 'Selesai Produksi',
    ProductionState.abandoned => 'Dihentikan',
  };

  /// CLOSED and ABANDONED are terminal (server `TERMINAL_STATES`).
  bool get isTerminal =>
      this == ProductionState.closed || this == ProductionState.abandoned;
}

/// A quality-control verdict (Step 6, Rule 19 QC status set).
///
/// Mirrors `QualityControlInspection::VERDICT_*`. FAILED drives a rework cycle;
/// PASSED and WAIVED_WITH_AUTHORIZATION close the job. A waiver is a
/// permissioned, reasoned, audited act on the server (Rule 19).
enum QualityControlVerdict {
  passed,
  failedReworkRequired,
  waivedWithAuthorization;

  static QualityControlVerdict parse(String value) => switch (value) {
    'PASSED' => QualityControlVerdict.passed,
    'FAILED_REWORK_REQUIRED' => QualityControlVerdict.failedReworkRequired,
    'WAIVED_WITH_AUTHORIZATION' =>
      QualityControlVerdict.waivedWithAuthorization,
    _ => throw ArgumentError.value(
      value,
      'verdict',
      'Verdict kendali mutu tidak dikenal',
    ),
  };

  String get wireValue => switch (this) {
    QualityControlVerdict.passed => 'PASSED',
    QualityControlVerdict.failedReworkRequired => 'FAILED_REWORK_REQUIRED',
    QualityControlVerdict.waivedWithAuthorization =>
      'WAIVED_WITH_AUTHORIZATION',
  };

  String get label => switch (this) {
    QualityControlVerdict.passed => 'Lulus',
    QualityControlVerdict.failedReworkRequired =>
      'Gagal — Perlu Pengerjaan Ulang',
    QualityControlVerdict.waivedWithAuthorization =>
      'Dikecualikan dengan Otorisasi',
  };

  /// Whether recording this verdict requires a defect reason code. The server
  /// rejects a FAILED verdict with no reason (QualityControlService), so the
  /// UI must collect it BEFORE sending rather than discover it at 422.
  bool get requiresDefectReason =>
      this == QualityControlVerdict.failedReworkRequired;
}

/// The service model of a production item (server `ProductionItem::SERVICE_*`).
enum ProductionServiceType {
  kiloan,
  satuan;

  static ProductionServiceType parse(String value) => switch (value) {
    'kiloan' => ProductionServiceType.kiloan,
    'satuan' => ProductionServiceType.satuan,
    _ => throw ArgumentError.value(
      value,
      'service_type',
      'Jenis layanan produksi tidak dikenal',
    ),
  };

  String get label => switch (this) {
    ProductionServiceType.kiloan => 'Kiloan',
    ProductionServiceType.satuan => 'Satuan',
  };
}

/// The list projection of a production job (server
/// `ProductionProjection::summary`). Carries NO money, no customer personal
/// data — the production surface is minimal exposure (Rule 32).
@immutable
final class ProductionJobSummary {
  const ProductionJobSummary({
    required this.id,
    required this.orderId,
    required this.outletId,
    required this.state,
    required this.version,
    this.blockReasonCode,
    this.updatedAt,
  });

  factory ProductionJobSummary.fromJson(Map<String, Object?> json) =>
      ProductionJobSummary(
        id: json['id']! as String,
        orderId: json['order_id']! as String,
        outletId: json['outlet_id']! as String,
        state: ProductionState.parse(json['state']! as String),
        version: (json['version']! as num).toInt(),
        blockReasonCode: json['block_reason_code'] as String?,
        updatedAt: json['updated_at'] as String?,
      );

  final String id;
  final String orderId;
  final String outletId;
  final ProductionState state;

  /// The optimistic-concurrency token. Sent back as `expected_version` on the
  /// next command so a transition applied against a stale read is refused
  /// (CONFLICT / version_conflict) rather than overwriting a concurrent change.
  final int version;

  /// Present only when [state] is BLOCKED.
  final String? blockReasonCode;

  /// ISO-8601 server timestamp of the last change, or null. Server-authoritative
  /// and preserved verbatim — never re-derived from a local clock.
  final String? updatedAt;
}

/// One production item — per order line (server `ProductionProjection::detail`
/// item shape). For kiloan it carries quantity progress; for satuan it carries
/// discrete unit counts (FR-075).
@immutable
final class ProductionItem {
  const ProductionItem({
    required this.id,
    required this.orderLineId,
    required this.serviceType,
    required this.unitsTotal,
    required this.unitsDone,
    this.stage,
    this.quantityDone,
  });

  factory ProductionItem.fromJson(Map<String, Object?> json) => ProductionItem(
    id: json['id']! as String,
    orderLineId: json['order_line_id']! as String,
    serviceType: ProductionServiceType.parse(json['service_type']! as String),
    stage: json['stage'] as String?,
    // A decimal:3 quantity the server serialises as a string ("2.500"). Kept as
    // the exact server string, never parsed into a double: kilogram progress
    // does not need float arithmetic on the client and float is banned in the
    // money path this shares a table style with (Rule 04).
    quantityDone: _asString(json['quantity_done']),
    unitsTotal: (json['units_total']! as num).toInt(),
    unitsDone: (json['units_done']! as num).toInt(),
  );

  final String id;
  final String orderLineId;
  final ProductionServiceType serviceType;

  /// The stage the item is at, e.g. WASHING. Free-form server text; null before
  /// the first stage starts.
  final String? stage;

  /// Exact server decimal string for kiloan progress; null for satuan.
  final String? quantityDone;
  final int unitsTotal;
  final int unitsDone;

  static String? _asString(Object? value) => value?.toString();
}

/// One entry of the production timeline (server
/// `ProductionProjection::timeline`). The append-only production history the
/// operator reads; no history is ever deleted (Rule 19, FR append-only).
@immutable
final class ProductionTimelineEntry {
  const ProductionTimelineEntry({
    required this.type,
    this.actorMembershipId,
    this.occurredAt,
  });

  factory ProductionTimelineEntry.fromJson(Map<String, Object?> json) =>
      ProductionTimelineEntry(
        type: json['type']! as String,
        actorMembershipId: json['actor_membership_id'] as String?,
        occurredAt: json['occurred_at'] as String?,
      );

  final String type;
  final String? actorMembershipId;
  final String? occurredAt;
}

/// The full projection of a production job: its summary, its items, and its
/// append-only timeline (assembled from the server `show` response
/// `{ job: detail, timeline: [...] }`).
@immutable
final class ProductionJobDetail {
  const ProductionJobDetail({
    required this.summary,
    required this.items,
    required this.timeline,
  });

  /// Build from the whole `data` map of the `show` response, whose members are
  /// `job` (the detail projection) and `timeline` (a sibling list).
  factory ProductionJobDetail.fromResponse(Map<String, Object?> data) {
    final job = data['job'];
    final jobMap = job is Map<String, Object?> ? job : const <String, Object?>{};

    final rawItems = jobMap['items'];
    final items = rawItems is List
        ? rawItems
              .cast<Map<String, Object?>>()
              .map(ProductionItem.fromJson)
              .toList(growable: false)
        : const <ProductionItem>[];

    final rawTimeline = data['timeline'];
    final timeline = rawTimeline is List
        ? rawTimeline
              .cast<Map<String, Object?>>()
              .map(ProductionTimelineEntry.fromJson)
              .toList(growable: false)
        : const <ProductionTimelineEntry>[];

    return ProductionJobDetail(
      summary: ProductionJobSummary.fromJson(jobMap),
      items: items,
      timeline: timeline,
    );
  }

  final ProductionJobSummary summary;
  final List<ProductionItem> items;
  final List<ProductionTimelineEntry> timeline;

  String get id => summary.id;
  ProductionState get state => summary.state;
  int get version => summary.version;
}

/// The result of recording a QC verdict (server `recordQualityControl`
/// response). Carries the created inspection id, the verdict, and the job's new
/// summary so the caller reconciles to server truth without a second read.
@immutable
final class QcInspectionResult {
  const QcInspectionResult({
    required this.inspectionId,
    required this.verdict,
    required this.job,
  });

  factory QcInspectionResult.fromResponse(Map<String, Object?> data) {
    final inspection = data['inspection'];
    final inspectionMap = inspection is Map<String, Object?>
        ? inspection
        : const <String, Object?>{};
    final job = data['job'];
    final jobMap = job is Map<String, Object?> ? job : const <String, Object?>{};

    return QcInspectionResult(
      inspectionId: inspectionMap['id']! as String,
      verdict: QualityControlVerdict.parse(inspectionMap['verdict']! as String),
      job: ProductionJobSummary.fromJson(jobMap),
    );
  }

  final String inspectionId;
  final QualityControlVerdict verdict;
  final ProductionJobSummary job;
}

/// The result of marking a closed job READY_FOR_PICKUP (server `markReady`
/// response `{ order: { id, status } }`). The order status is
/// server-authoritative — this is the first-ready anchor's visible effect
/// (FR-076), and the client never claims it before this acknowledgement.
@immutable
final class ProductionReadyResult {
  const ProductionReadyResult({required this.orderId, required this.orderStatus});

  factory ProductionReadyResult.fromResponse(Map<String, Object?> data) {
    final order = data['order'];
    final orderMap = order is Map<String, Object?>
        ? order
        : const <String, Object?>{};

    return ProductionReadyResult(
      orderId: orderMap['id']! as String,
      orderStatus: OrderStatus.parse(orderMap['status']! as String),
    );
  }

  final String orderId;
  final OrderStatus orderStatus;
}
