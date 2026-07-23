import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/pos/pos_counter_screen.dart';
import 'package:aish_ops_android/src/pos/pos_order_detail_screen.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// STEP 5 — OPS POS SURFACE (orders and payments). Every fixture is fictional
/// (Rule 23). Money is rendered from server integers via [Rupiah]; nothing here
/// is computed on the client (Rule 04).
Environment env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://ops.contoh-fiktif.id/api/v1',
  appName: 'Uji Ops',
).valueOrNull!;

class _Adapter implements HttpClientAdapter {
  _Adapter(this.rules, this.fallback);

  final List<(bool Function(RequestOptions), int, String)> rules;
  final (int, String) fallback;
  final List<RequestOptions> requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<List<int>>? s, Future<void>? c) async {
    requests.add(options);
    var status = fallback.$1;
    var body = fallback.$2;
    for (final (matches, ruleStatus, ruleBody) in rules) {
      if (matches(options)) {
        status = ruleStatus;
        body = ruleBody;
        break;
      }
    }
    return ResponseBody.fromString(body, status, headers: <String, List<String>>{
      Headers.contentTypeHeader: <String>[Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

(bool Function(RequestOptions), int, String) on(String method, int status, String body,
        {String? pathContains}) =>
    ((RequestOptions o) => o.method == method && (pathContains == null || o.path.contains(pathContains)), status, body);

({ApiClient client, _Adapter adapter}) scripted(List<(bool Function(RequestOptions), int, String)> rules) {
  final adapter = _Adapter(rules, (200, '{"data":{},"meta":{}}'));
  final dio = Dio()..httpClientAdapter = adapter;
  return (client: ApiClient(environment: env(), transport: CredentialTransport.bearerToken, dio: dio), adapter: adapter);
}

FakeAuthService fullContextAuth() =>
    FakeAuthService(initial: AuthState.authenticated(ApiFixtures.fullContext()));

Future<void> pump(WidgetTester tester, Widget screen, ApiClient client, FakeAuthService auth) async {
  tester.view.physicalSize = const Size(400, 900);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
        apiClientProvider.overrideWithValue(client),
      ],
      child: MaterialApp(theme: AishTheme.light(), home: screen),
    ),
  );
  await tester.pumpAndSettle();
}

const String _orderList =
    '{"data":{"orders":[{"id":"o1","order_number":"ORD-000001","status":"RECEIVED",'
    '"customer_id":"c1","outlet_id":"otl_fiktif_melati_pusat","subtotal_rupiah":20000,'
    '"discount_rupiah":0,"total_rupiah":20000,"version":1}],'
    '"pagination":{"page":1,"per_page":25,"total":1}},"meta":{"request_id":"uji"}}';

const String _orderEmpty =
    '{"data":{"orders":[],"pagination":{"page":1,"per_page":25,"total":0}},"meta":{}}';

const String _orderDetail =
    '{"data":{"order":{"id":"o1","order_number":"ORD-000001","status":"RECEIVED",'
    '"customer_id":"c1","outlet_id":"otl_fiktif_melati_pusat","subtotal_rupiah":20000,'
    '"discount_rupiah":0,"total_rupiah":20000,"version":1,'
    '"lines":[{"id":"l1","line_number":1,"service_name":"Cuci Kiloan Reguler",'
    '"unit":"kilogram","quantity_milli":2500,"unit_price_rupiah":8000,'
    '"discount_rupiah":0,"subtotal_rupiah":20000}],'
    '"paid_rupiah":12000,"outstanding_rupiah":8000,"payment_state":"partial"}},'
    '"meta":{"request_id":"uji"}}';

const String _payments =
    '{"data":{"payments":[{"id":"p1","payment_number":"PAY-000001","order_id":"o1",'
    '"kind":"payment","method":"cash","status":"succeeded","amount_rupiah":12000,'
    '"version":1}]},"meta":{}}';

void main() {
  testWidgets('the counter lists an order with its number, total and status', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[on('GET', 200, _orderList, pathContains: 'orders')]);
    await pump(tester, const PosCounterScreen(), s.client, fullContextAuth());

    expect(find.text('ORD-000001'), findsOneWidget);
    expect(find.text('Rp20.000'), findsOneWidget); // integer Rupiah, formatted
    expect(find.text('Diterima'), findsOneWidget); // OrderStatus.received label
    // The list request carried the active outlet (server scopes; no client filter).
    expect(s.adapter.requests.single.queryParameters['outlet_id'], 'otl_fiktif_melati_pusat');
  });

  testWidgets('the counter shows the empty state when there are no orders', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[on('GET', 200, _orderEmpty, pathContains: 'orders')]);
    await pump(tester, const PosCounterScreen(), s.client, fullContextAuth());
    expect(find.text('Belum ada pesanan'), findsOneWidget);
  });

  testWidgets('the detail shows lines, the derived balance and the payment history', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on('GET', 200, _payments, pathContains: 'payments'),
      on('GET', 200, _orderDetail, pathContains: 'orders'),
    ]);
    await pump(tester, const PosOrderDetailScreen(orderId: 'o1'), s.client, fullContextAuth());

    expect(find.text('ORD-000001'), findsWidgets);
    expect(find.text('Cuci Kiloan Reguler'), findsOneWidget);
    // Server-derived balance, shown as integer Rupiah.
    expect(find.text('Dibayar Sebagian'), findsOneWidget); // PaymentState.partial
    expect(find.text('Rp8.000'), findsWidgets); // outstanding
    expect(find.textContaining('PAY-000001'), findsOneWidget); // payment history row
  });

  testWidgets('the detail offers a payment action while a balance is outstanding', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on('GET', 200, _payments, pathContains: 'payments'),
      on('GET', 200, _orderDetail, pathContains: 'orders'),
    ]);
    await pump(tester, const PosOrderDetailScreen(orderId: 'o1'), s.client, fullContextAuth());
    expect(find.text('Terima pembayaran'), findsOneWidget);
    expect(find.text('Lihat nota'), findsOneWidget);
  });
}
