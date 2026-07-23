import 'package:meta/meta.dart';

import '../master_data/rupiah.dart';
import 'order.dart';

/// One receipt line — the captured price snapshot (FR-052, FR-036). Built from
/// the server's `ReceiptProjection`, never reconstructed from live prices.
@immutable
final class ReceiptLine {
  const ReceiptLine({
    required this.lineNumber,
    required this.serviceName,
    required this.unit,
    required this.quantityMilli,
    required this.unitPrice,
    required this.discount,
    required this.subtotal,
  });

  factory ReceiptLine.fromJson(Map<String, Object?> json) => ReceiptLine(
    lineNumber: json['line_number']! as int,
    serviceName: json['service_name']! as String,
    unit: json['unit']! as String,
    quantityMilli: json['quantity_milli']! as int,
    unitPrice: Rupiah.parse(json['unit_price_rupiah']),
    discount: Rupiah.parse(json['discount_rupiah']),
    subtotal: Rupiah.parse(json['subtotal_rupiah']),
  );

  final int lineNumber;
  final String serviceName;
  final String unit;
  final int quantityMilli;
  final Rupiah unitPrice;
  final Rupiah discount;
  final Rupiah subtotal;
}

/// One payment row on a receipt.
@immutable
final class ReceiptPayment {
  const ReceiptPayment({
    required this.paymentNumber,
    required this.kind,
    required this.method,
    required this.status,
    required this.amount,
  });

  factory ReceiptPayment.fromJson(Map<String, Object?> json) => ReceiptPayment(
    paymentNumber: json['payment_number']! as String,
    kind: json['kind']! as String,
    method: json['method']! as String,
    status: json['status']! as String,
    amount: Rupiah.parse(json['amount_rupiah']),
  );

  final String paymentNumber;
  final String kind;
  final String method;
  final String status;
  final Rupiah amount;
}

/// The nota (FR-052): a reprintable receipt built ENTIRELY from server-authoritative
/// data (the captured-price snapshot and the ledger). The client never
/// reconstructs any of this from current master-data prices (FR-036).
@immutable
final class Receipt {
  const Receipt({
    required this.orderNumber,
    required this.status,
    required this.lines,
    required this.subtotal,
    required this.discount,
    required this.total,
    required this.payments,
    required this.paid,
    required this.outstanding,
    required this.paymentState,
  });

  factory Receipt.fromJson(Map<String, Object?> json) {
    final rawLines = json['lines'];
    final lines = rawLines is List
        ? rawLines.cast<Map<String, Object?>>().map(ReceiptLine.fromJson).toList(growable: false)
        : const <ReceiptLine>[];
    final rawPayments = json['payments'];
    final payments = rawPayments is List
        ? rawPayments.cast<Map<String, Object?>>().map(ReceiptPayment.fromJson).toList(growable: false)
        : const <ReceiptPayment>[];

    return Receipt(
      orderNumber: json['order_number']! as String,
      status: OrderStatus.parse(json['status']! as String),
      lines: lines,
      subtotal: Rupiah.parse(json['subtotal_rupiah']),
      discount: Rupiah.parse(json['discount_rupiah']),
      total: Rupiah.parse(json['total_rupiah']),
      payments: payments,
      paid: Rupiah.parse(json['paid_rupiah']),
      outstanding: Rupiah.parse(json['outstanding_rupiah']),
      paymentState: PaymentState.parse(json['payment_state']! as String),
    );
  }

  final String orderNumber;
  final OrderStatus status;
  final List<ReceiptLine> lines;
  final Rupiah subtotal;
  final Rupiah discount;
  final Rupiah total;
  final List<ReceiptPayment> payments;
  final Rupiah paid;
  final Rupiah outstanding;
  final PaymentState paymentState;
}
