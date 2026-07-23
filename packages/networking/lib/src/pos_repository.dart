import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';

import 'api_client.dart';
import 'api_endpoints.dart';
import 'api_response.dart';

/// One order line a cashier is composing, before the server prices it.
///
/// It carries WHAT to order and HOW MUCH, never a price: the server resolves the
/// unit price from the active price list and computes the total (FR-051). A
/// client price here would be a price the counter could choose, which is exactly
/// the control point FR-039 guards.
final class OrderLineInput {
  const OrderLineInput({
    required this.targetType,
    required this.targetId,
    required this.quantityMilli,
    this.discountRupiah = 0,
  });

  /// 'service' | 'package' | 'addon'.
  final String targetType;
  final String targetId;

  /// Quantity in thousandths (2.5 kg = 2500). Integer, never a double.
  final int quantityMilli;
  final int discountRupiah;

  Map<String, Object?> toJson() => <String, Object?>{
    'target_type': targetType,
    'target_id': targetId,
    'quantity_milli': quantityMilli,
    if (discountRupiah != 0) 'discount_rupiah': discountRupiah,
  };
}

/// Read/write access to Step 5 orders (FR-048 … FR-060, DEC-0035).
///
/// ONE PLACE THAT KNOWS THE ENDPOINTS. A screen asks for a typed result; it never
/// builds a path, decodes an envelope, or computes money. Totals are
/// server-authoritative (FR-051); this repository sends what to order and reads
/// back what it costs. `client_reference` is threaded through for idempotency
/// (FR-059): the SAME reference on a retry yields the original order, never a
/// second one.
final class OrdersRepository {
  const OrdersRepository(this._client);

  final ApiClient _client;

  static const int maxPerPage = 100;
  static const int counterPageSize = 20;

  Future<Result<List<OrderSummary>>> orders({
    String? orderNumber,
    String? status,
    String? outletId,
    String? customerId,
    int perPage = 25,
  }) async {
    final result = await _client.get(
      ApiEndpoints.orders,
      query: <String, Object?>{
        if (orderNumber != null && orderNumber.trim().isNotEmpty)
          'order_number': orderNumber.trim(),
        'status': ?status,
        'outlet_id': ?outletId,
        'customer_id': ?customerId,
        'per_page': _boundedPerPage(perPage),
      },
    );

    return result.map(
      (ApiSuccess success) => _list(success, 'orders', OrderSummary.fromJson),
    );
  }

  Future<Result<OrderDetail>> order(String id) async {
    final result = await _client.get(ApiEndpoints.order(id));
    return result.map(
      (ApiSuccess success) => OrderDetail.fromJson(_object(success, 'order')),
    );
  }

  /// Create a DRAFT order. Idempotent on [clientReference] (FR-062).
  Future<Result<OrderDetail>> createOrder({
    required String customerId,
    required String outletId,
    required String clientReference,
    required List<OrderLineInput> lines,
    int discountRupiah = 0,
    String? specialInstructions,
  }) async {
    final result = await _client.post(
      ApiEndpoints.orders,
      body: <String, Object?>{
        'customer_id': customerId,
        'outlet_id': outletId,
        'client_reference': clientReference,
        if (discountRupiah != 0) 'discount_rupiah': discountRupiah,
        if (specialInstructions != null && specialInstructions.isNotEmpty)
          'special_instructions': specialInstructions,
        'lines': lines.map((line) => line.toJson()).toList(growable: false),
      },
    );

    return result.map(
      (ApiSuccess success) => OrderDetail.fromJson(_object(success, 'order')),
    );
  }

  /// DRAFT -> RECEIVED (FR-048).
  Future<Result<OrderDetail>> placeOrder(String id) async {
    final result = await _client.post(ApiEndpoints.orderPlace(id));
    return result.map(
      (ApiSuccess success) => OrderDetail.fromJson(_object(success, 'order')),
    );
  }

  /// {DRAFT, RECEIVED} -> CANCELLED with a mandatory reason (FR-058).
  Future<Result<OrderSummary>> cancelOrder(String id, String reason) async {
    final result = await _client.post(
      ApiEndpoints.orderCancel(id),
      body: <String, Object?>{'reason': reason},
    );
    return result.map(
      (ApiSuccess success) => OrderSummary.fromJson(_object(success, 'order')),
    );
  }

  /// The nota (FR-052): server-authoritative, from the captured-price snapshot.
  Future<Result<Receipt>> receipt(String id) async {
    final result = await _client.get(ApiEndpoints.orderReceipt(id));
    return result.map(
      (ApiSuccess success) => Receipt.fromJson(_object(success, 'receipt')),
    );
  }
}

/// Read/append access to the Step 5 payment ledger (FR-061 … FR-069, DEC-0035).
///
/// Append-only: record, confirm, and reverse. There is no update and no delete
/// path, because a correction is a reversal (FR-066, FR-067). Paid state is never
/// claimed by the client — the server decides it (FR-064).
final class PaymentsRepository {
  const PaymentsRepository(this._client);

  final ApiClient _client;

  Future<Result<List<Payment>>> payments(String orderId) async {
    final result = await _client.get(ApiEndpoints.orderPayments(orderId));
    return result.map(
      (ApiSuccess success) => _list(success, 'payments', Payment.fromJson),
    );
  }

  /// Record a payment against an order. Idempotent on [clientReference] (FR-062).
  Future<Result<Payment>> recordPayment(
    String orderId, {
    required String method,
    required int amountRupiah,
    required String clientReference,
    String? gatewayReference,
  }) async {
    final result = await _client.post(
      ApiEndpoints.orderPayments(orderId),
      body: <String, Object?>{
        'method': method,
        'amount_rupiah': amountRupiah,
        'client_reference': clientReference,
        if (gatewayReference != null && gatewayReference.isNotEmpty)
          'gateway_reference': gatewayReference,
      },
    );
    return result.map(
      (ApiSuccess success) => Payment.fromJson(_object(success, 'payment')),
    );
  }

  /// Confirm a pending gateway payment from a verified callback (FR-063).
  Future<Result<Payment>> confirmPayment(
    String paymentId, {
    required int amountRupiah,
    String? gatewayReference,
  }) async {
    final result = await _client.post(
      ApiEndpoints.paymentConfirm(paymentId),
      body: <String, Object?>{
        'amount_rupiah': amountRupiah,
        if (gatewayReference != null && gatewayReference.isNotEmpty)
          'gateway_reference': gatewayReference,
      },
    );
    return result.map(
      (ApiSuccess success) => Payment.fromJson(_object(success, 'payment')),
    );
  }

  /// Reverse part or all of a payment, with a mandatory reason (FR-065, FR-067).
  Future<Result<Payment>> reversePayment(
    String paymentId, {
    required int amountRupiah,
    required String reason,
  }) async {
    final result = await _client.post(
      ApiEndpoints.paymentReverse(paymentId),
      body: <String, Object?>{'amount_rupiah': amountRupiah, 'reason': reason},
    );
    return result.map(
      (ApiSuccess success) => Payment.fromJson(_object(success, 'payment')),
    );
  }
}

// --- shared decode helpers (no client-side tenant filtering, ever) ----------

int _boundedPerPage(int requested) =>
    requested < 1 ? 1 : (requested > OrdersRepository.maxPerPage ? OrdersRepository.maxPerPage : requested);

Map<String, Object?> _object(ApiSuccess success, String key) {
  final raw = success.dataAsMap[key];
  return raw is Map<String, Object?> ? raw : const <String, Object?>{};
}

List<T> _list<T>(
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
