import 'package:meta/meta.dart';

import '../master_data/rupiah.dart';

/// The canonical order status (Rule 19). Step 5 operates only the intake
/// statuses (DRAFT, RECEIVED, CANCELLED); the production stages are Step 6, but
/// the full set is modelled so a status the server sends is never unrecognised.
enum OrderStatus {
  draft,
  received,
  awaitingProcess,
  sorting,
  washing,
  drying,
  finishing,
  qualityControl,
  rework,
  readyForPickup,
  scheduledForDelivery,
  outForDelivery,
  completed,
  cancelled,
  issue;

  static OrderStatus parse(String value) => switch (value) {
    'DRAFT' => OrderStatus.draft,
    'RECEIVED' => OrderStatus.received,
    'AWAITING_PROCESS' => OrderStatus.awaitingProcess,
    'SORTING' => OrderStatus.sorting,
    'WASHING' => OrderStatus.washing,
    'DRYING' => OrderStatus.drying,
    'FINISHING' => OrderStatus.finishing,
    'QUALITY_CONTROL' => OrderStatus.qualityControl,
    'REWORK' => OrderStatus.rework,
    'READY_FOR_PICKUP' => OrderStatus.readyForPickup,
    'SCHEDULED_FOR_DELIVERY' => OrderStatus.scheduledForDelivery,
    'OUT_FOR_DELIVERY' => OrderStatus.outForDelivery,
    'COMPLETED' => OrderStatus.completed,
    'CANCELLED' => OrderStatus.cancelled,
    'ISSUE' => OrderStatus.issue,
    _ => throw ArgumentError.value(value, 'status', 'Status pesanan tidak dikenal'),
  };

  /// Bahasa Indonesia label (Rule 30). The enum name is the technical id.
  String get label => switch (this) {
    OrderStatus.draft => 'Draf',
    OrderStatus.received => 'Diterima',
    OrderStatus.awaitingProcess => 'Menunggu Proses',
    OrderStatus.sorting => 'Pemilahan',
    OrderStatus.washing => 'Pencucian',
    OrderStatus.drying => 'Pengeringan',
    OrderStatus.finishing => 'Penyelesaian',
    OrderStatus.qualityControl => 'Kendali Mutu',
    OrderStatus.rework => 'Pengerjaan Ulang',
    OrderStatus.readyForPickup => 'Siap Diambil',
    OrderStatus.scheduledForDelivery => 'Terjadwal Antar',
    OrderStatus.outForDelivery => 'Dalam Pengantaran',
    OrderStatus.completed => 'Selesai',
    OrderStatus.cancelled => 'Dibatalkan',
    OrderStatus.issue => 'Bermasalah',
  };
}

/// The derived settlement state of an order (FR-070). Derived by the SERVER from
/// the ledger; the client only displays it (Rule 04).
enum PaymentState {
  unpaid,
  partial,
  paid;

  static PaymentState parse(String value) => switch (value) {
    'unpaid' => PaymentState.unpaid,
    'partial' => PaymentState.partial,
    'paid' => PaymentState.paid,
    _ => throw ArgumentError.value(value, 'payment_state', 'Status pembayaran tidak dikenal'),
  };

  String get label => switch (this) {
    PaymentState.unpaid => 'Belum Dibayar',
    PaymentState.partial => 'Dibayar Sebagian',
    PaymentState.paid => 'Lunas',
  };
}

/// One priced line of an order, carrying the price SNAPSHOT the server captured
/// at intake (FR-036). Money is [Rupiah]; the client never recomputes it.
@immutable
final class OrderLine {
  const OrderLine({
    required this.id,
    required this.lineNumber,
    required this.serviceName,
    required this.unit,
    required this.quantityMilli,
    required this.unitPrice,
    required this.discount,
    required this.subtotal,
  });

  factory OrderLine.fromJson(Map<String, Object?> json) => OrderLine(
    id: json['id']! as String,
    lineNumber: json['line_number']! as int,
    serviceName: json['service_name']! as String,
    unit: json['unit']! as String,
    quantityMilli: json['quantity_milli']! as int,
    unitPrice: Rupiah.parse(json['unit_price_rupiah']),
    discount: Rupiah.parse(json['discount_rupiah']),
    subtotal: Rupiah.parse(json['subtotal_rupiah']),
  );

  final String id;
  final int lineNumber;
  final String serviceName;
  final String unit;

  /// Quantity in thousandths (2.5 kg = 2500, 1 pcs = 1000). Integer, never a
  /// double — the same discipline as the server (Rule 04).
  final int quantityMilli;
  final Rupiah unitPrice;
  final Rupiah discount;
  final Rupiah subtotal;
}

/// The list projection of an order (server `OrderProjection::summary`).
@immutable
final class OrderSummary {
  const OrderSummary({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.customerId,
    required this.outletId,
    required this.subtotal,
    required this.discount,
    required this.total,
    required this.version,
    this.createdAt,
  });

  factory OrderSummary.fromJson(Map<String, Object?> json) => OrderSummary(
    id: json['id']! as String,
    orderNumber: json['order_number']! as String,
    status: OrderStatus.parse(json['status']! as String),
    customerId: json['customer_id']! as String,
    outletId: json['outlet_id']! as String,
    subtotal: Rupiah.parse(json['subtotal_rupiah']),
    discount: Rupiah.parse(json['discount_rupiah']),
    total: Rupiah.parse(json['total_rupiah']),
    version: json['version'] as int?,
    createdAt: json['created_at'] as String?,
  );

  final String id;
  final String orderNumber;
  final OrderStatus status;
  final String customerId;
  final String outletId;
  final Rupiah subtotal;
  final Rupiah discount;
  final Rupiah total;
  final int? version;
  final String? createdAt;
}

/// The full projection of an order (server `OrderProjection::detail`): lines,
/// the derived balance, and the cancellation record.
@immutable
final class OrderDetail {
  const OrderDetail({
    required this.summary,
    required this.lines,
    required this.paid,
    required this.outstanding,
    required this.paymentState,
    this.specialInstructions,
    this.placedAt,
    this.cancelledAt,
    this.cancellationReason,
  });

  factory OrderDetail.fromJson(Map<String, Object?> json) {
    final rawLines = json['lines'];
    final lines = rawLines is List
        ? rawLines
              .cast<Map<String, Object?>>()
              .map(OrderLine.fromJson)
              .toList(growable: false)
        : const <OrderLine>[];

    return OrderDetail(
      summary: OrderSummary.fromJson(json),
      lines: lines,
      paid: Rupiah.parse(json['paid_rupiah'] ?? 0),
      outstanding: Rupiah.parse(json['outstanding_rupiah'] ?? 0),
      paymentState: PaymentState.parse(json['payment_state']! as String),
      specialInstructions: json['special_instructions'] as String?,
      placedAt: json['placed_at'] as String?,
      cancelledAt: json['cancelled_at'] as String?,
      cancellationReason: json['cancellation_reason'] as String?,
    );
  }

  final OrderSummary summary;
  final List<OrderLine> lines;
  final Rupiah paid;
  final Rupiah outstanding;
  final PaymentState paymentState;
  final String? specialInstructions;
  final String? placedAt;
  final String? cancelledAt;
  final String? cancellationReason;

  String get id => summary.id;
  String get orderNumber => summary.orderNumber;
  OrderStatus get status => summary.status;
  Rupiah get total => summary.total;
}
