import 'package:meta/meta.dart';

import '../master_data/rupiah.dart';

/// A payment method the Step 5 backend supports (FR-061). QRIS is represented as
/// a method and a state only — no external provider is integrated (OQ-015).
enum PaymentMethod {
  cash,
  bankTransfer,
  qris;

  static PaymentMethod parse(String value) => switch (value) {
    'cash' => PaymentMethod.cash,
    'bank_transfer' => PaymentMethod.bankTransfer,
    'qris' => PaymentMethod.qris,
    _ => throw ArgumentError.value(value, 'method', 'Metode pembayaran tidak dikenal'),
  };

  String get wireValue => switch (this) {
    PaymentMethod.cash => 'cash',
    PaymentMethod.bankTransfer => 'bank_transfer',
    PaymentMethod.qris => 'qris',
  };

  String get label => switch (this) {
    PaymentMethod.cash => 'Tunai',
    PaymentMethod.bankTransfer => 'Transfer',
    PaymentMethod.qris => 'QRIS',
  };

  /// A gateway method settles asynchronously (awaits a verified callback); a
  /// counter method settles at record time. The UI must not claim a QRIS
  /// success on its own (FR-064).
  bool get isGateway => this == PaymentMethod.qris;
}

/// The lifecycle state of a payment. `pending` means a gateway payment awaiting
/// a server-verified callback — NEVER shown as paid (FR-064).
enum PaymentStatus {
  pending,
  succeeded,
  failed,
  reversed;

  static PaymentStatus parse(String value) => switch (value) {
    'pending' => PaymentStatus.pending,
    'succeeded' => PaymentStatus.succeeded,
    'failed' => PaymentStatus.failed,
    'reversed' => PaymentStatus.reversed,
    _ => throw ArgumentError.value(value, 'status', 'Status pembayaran tidak dikenal'),
  };

  String get label => switch (this) {
    PaymentStatus.pending => 'Menunggu',
    PaymentStatus.succeeded => 'Berhasil',
    PaymentStatus.failed => 'Gagal',
    PaymentStatus.reversed => 'Dibalik',
  };
}

/// Whether a ledger entry adds to (payment) or subtracts from (reversal) the
/// paid total. A reversal is a positive amount with a direction, never a
/// negative sign (Rule 04).
enum PaymentKind {
  payment,
  reversal;

  static PaymentKind parse(String value) => switch (value) {
    'payment' => PaymentKind.payment,
    'reversal' => PaymentKind.reversal,
    _ => throw ArgumentError.value(value, 'kind', 'Jenis transaksi tidak dikenal'),
  };

  String get label => switch (this) {
    PaymentKind.payment => 'Pembayaran',
    PaymentKind.reversal => 'Pembalikan',
  };
}

/// A single entry in the append-only financial ledger (server
/// `PaymentProjection::summary`). Money is [Rupiah]; nothing here is computed on
/// the client.
@immutable
final class Payment {
  const Payment({
    required this.id,
    required this.paymentNumber,
    required this.orderId,
    required this.kind,
    required this.method,
    required this.status,
    required this.amount,
    this.reversesPaymentId,
    this.gatewayReference,
    this.receivedAt,
    this.version,
    this.createdAt,
  });

  factory Payment.fromJson(Map<String, Object?> json) => Payment(
    id: json['id']! as String,
    paymentNumber: json['payment_number']! as String,
    orderId: json['order_id']! as String,
    kind: PaymentKind.parse(json['kind']! as String),
    method: PaymentMethod.parse(json['method']! as String),
    status: PaymentStatus.parse(json['status']! as String),
    amount: Rupiah.parse(json['amount_rupiah']),
    reversesPaymentId: json['reverses_payment_id'] as String?,
    gatewayReference: json['gateway_reference'] as String?,
    receivedAt: json['received_at'] as String?,
    version: json['version'] as int?,
    createdAt: json['created_at'] as String?,
  );

  final String id;
  final String paymentNumber;
  final String orderId;
  final PaymentKind kind;
  final PaymentMethod method;
  final PaymentStatus status;
  final Rupiah amount;
  final String? reversesPaymentId;
  final String? gatewayReference;
  final String? receivedAt;
  final int? version;
  final String? createdAt;

  bool get isSucceeded => status == PaymentStatus.succeeded;
  bool get isReversal => kind == PaymentKind.reversal;
}
