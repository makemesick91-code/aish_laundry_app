import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:dio/dio.dart';
import 'package:test/test.dart';

Environment env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://api.contoh-fiktif.id/api/v1',
  appName: 'Uji',
).valueOrNull!;

/// Captures the request and replies with a scripted, pre-encoded JSON envelope.
class _Adapter implements HttpClientAdapter {
  _Adapter(this.statusCode, this.body);

  int statusCode;
  String body;
  RequestOptions? last;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    last = options;
    return ResponseBody.fromString(
      body,
      statusCode,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

// ignore: library_private_types_in_public_api
ApiClient clientWith(_Adapter adapter) {
  final dio = Dio()..httpClientAdapter = adapter;
  return ApiClient(
    environment: env(),
    transport: CredentialTransport.bearerToken,
    dio: dio,
  );
}

const String _orderDetail =
    '{"data":{"order":{"id":"o1","order_number":"ORD-000001","status":"DRAFT",'
    '"customer_id":"c1","outlet_id":"ot1","subtotal_rupiah":20000,'
    '"discount_rupiah":0,"total_rupiah":20000,"version":1,'
    '"lines":[{"id":"l1","line_number":1,"service_name":"Cuci Kiloan",'
    '"unit":"kilogram","quantity_milli":2500,"unit_price_rupiah":8000,'
    '"discount_rupiah":0,"subtotal_rupiah":20000}],'
    '"paid_rupiah":0,"outstanding_rupiah":20000,"payment_state":"unpaid"}},'
    '"meta":{"request_id":"req_fiktif"}}';

const String _orderList =
    '{"data":{"orders":[{"id":"o1","order_number":"ORD-000001","status":"RECEIVED",'
    '"customer_id":"c1","outlet_id":"ot1","subtotal_rupiah":20000,'
    '"discount_rupiah":0,"total_rupiah":20000,"version":1}],'
    '"pagination":{"page":1,"per_page":25,"total":1}},"meta":{}}';

const String _payment =
    '{"data":{"payment":{"id":"p1","payment_number":"PAY-000001","order_id":"o1",'
    '"kind":"payment","method":"cash","status":"succeeded","amount_rupiah":20000,'
    '"version":1}},"meta":{}}';

void main() {
  group('OrdersRepository', () {
    test('createOrder posts customer, client_reference and lines; parses server totals', () async {
      final adapter = _Adapter(201, _orderDetail);
      final repo = OrdersRepository(clientWith(adapter));

      final result = await repo.createOrder(
        customerId: 'c1',
        outletId: 'ot1',
        clientReference: 'ref-abc',
        lines: const [
          OrderLineInput(targetType: 'service', targetId: 's1', quantityMilli: 2500),
        ],
      );

      expect(result.isOk, isTrue);
      final order = result.valueOrNull!;
      // Money is parsed EXACTLY as integer Rupiah, never a double.
      expect(order.total, const Rupiah(20000));
      expect(order.lines.single.unitPrice, const Rupiah(8000));
      expect(order.status, OrderStatus.draft);

      // The request carried what to order, and the idempotency key.
      final body = adapter.last!.data as Map<String, Object?>;
      expect(adapter.last!.path, contains('orders'));
      expect(body['customer_id'], 'c1');
      expect(body['client_reference'], 'ref-abc');
      // No client-supplied total is ever sent.
      expect(body.containsKey('total_rupiah'), isFalse);
      final lines = body['lines']! as List;
      expect((lines.single as Map)['quantity_milli'], 2500);
    });

    test('orders() sends filters and parses the summary list', () async {
      final adapter = _Adapter(200, _orderList);
      final repo = OrdersRepository(clientWith(adapter));

      final result = await repo.orders(status: 'RECEIVED', outletId: 'ot1');
      expect(result.isOk, isTrue);
      expect(result.valueOrNull!.single.orderNumber, 'ORD-000001');
      expect(adapter.last!.queryParameters['status'], 'RECEIVED');
      expect(adapter.last!.queryParameters['outlet_id'], 'ot1');
    });

    test('cancelOrder sends the reason', () async {
      final adapter = _Adapter(
        200,
        '{"data":{"order":{"id":"o1","order_number":"ORD-1","status":"CANCELLED",'
        '"customer_id":"c1","outlet_id":"ot1","subtotal_rupiah":0,'
        '"discount_rupiah":0,"total_rupiah":0,"version":2}},"meta":{}}',
      );
      final repo = OrdersRepository(clientWith(adapter));

      final result = await repo.cancelOrder('o1', 'Pelanggan batal');
      expect(result.isOk, isTrue);
      expect(result.valueOrNull!.status, OrderStatus.cancelled);
      expect((adapter.last!.data as Map)['reason'], 'Pelanggan batal');
    });

    test('a server error envelope becomes an Err result, never an exception', () async {
      final adapter = _Adapter(422, '{"error":{"code":"VALIDATION_FAILED","message":"salah"},"meta":{}}');
      final repo = OrdersRepository(clientWith(adapter));

      final result = await repo.createOrder(
        customerId: 'c1', outletId: 'ot1', clientReference: 'r',
        lines: const [OrderLineInput(targetType: 'service', targetId: 's1', quantityMilli: 1000)],
      );
      expect(result.isErr, isTrue);
      expect(result.failureOrNull, isNotNull);
    });
  });

  group('PaymentsRepository', () {
    test('recordPayment posts an integer amount and the idempotency key', () async {
      final adapter = _Adapter(201, _payment);
      final repo = PaymentsRepository(clientWith(adapter));

      final result = await repo.recordPayment(
        'o1', method: 'cash', amountRupiah: 20000, clientReference: 'pref-1',
      );
      expect(result.isOk, isTrue);
      final payment = result.valueOrNull!;
      expect(payment.amount, const Rupiah(20000));
      expect(payment.method, PaymentMethod.cash);
      expect(payment.status, PaymentStatus.succeeded);

      final body = adapter.last!.data as Map<String, Object?>;
      expect(adapter.last!.path, contains('orders/o1/payments'));
      expect(body['amount_rupiah'], 20000);
      expect(body['client_reference'], 'pref-1');
    });

    test('reversePayment sends amount and reason', () async {
      final adapter = _Adapter(
        201,
        '{"data":{"payment":{"id":"p2","payment_number":"PAY-000002","order_id":"o1",'
        '"kind":"reversal","method":"cash","status":"succeeded","amount_rupiah":5000,'
        '"reverses_payment_id":"p0","version":1}},"meta":{}}',
      );
      final repo = PaymentsRepository(clientWith(adapter));

      final result = await repo.reversePayment('p0', amountRupiah: 5000, reason: 'Kompensasi');
      expect(result.isOk, isTrue);
      final body = adapter.last!.data as Map<String, Object?>;
      expect(adapter.last!.path, contains('payments/p0/reverse'));
      expect(body['amount_rupiah'], 5000);
      expect(body['reason'], 'Kompensasi');
    });

    test('confirmPayment posts the verified amount', () async {
      final adapter = _Adapter(200, _payment);
      final repo = PaymentsRepository(clientWith(adapter));

      final result = await repo.confirmPayment('p1', amountRupiah: 20000, gatewayReference: 'REF-FIKTIF');
      expect(result.isOk, isTrue);
      final body = adapter.last!.data as Map<String, Object?>;
      expect(adapter.last!.path, contains('payments/p1/confirm'));
      expect(body['amount_rupiah'], 20000);
      expect(body['gateway_reference'], 'REF-FIKTIF');
    });
  });
}
