import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/tracking/order_tracking_screen.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// STEP 7 — THE OPS TRACKING AND NOTIFICATION SURFACE (DEC-0039).
///
/// Every fixture is fictional (Rule 23). The surface makes NO claim from local
/// state: a link's liveness, a message's outcome, and the provider's
/// availability are all read from the server, and the screen renders what it was
/// told (Rule 29, Rule 01).
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
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? s,
    Future<void>? c,
  ) async {
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
    return ResponseBody.fromString(
      body,
      status,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

(bool Function(RequestOptions), int, String) on(
  String method,
  int status,
  String body, {
  String? pathContains,
}) => (
  (RequestOptions o) =>
      o.method == method &&
      (pathContains == null || o.path.contains(pathContains)),
  status,
  body,
);

({ApiClient client, _Adapter adapter}) scripted(
  List<(bool Function(RequestOptions), int, String)> rules,
) {
  final adapter = _Adapter(rules, (200, '{"data":{},"meta":{}}'));
  final dio = Dio()..httpClientAdapter = adapter;
  return (
    client: ApiClient(
      environment: env(),
      transport: CredentialTransport.bearerToken,
      dio: dio,
    ),
    adapter: adapter,
  );
}

FakeAuthService ownerAuth() => FakeAuthService(
  initial: AuthState.authenticated(ApiFixtures.fullContext()),
);

Future<void> pump(
  WidgetTester tester,
  Widget screen,
  ApiClient client,
  FakeAuthService auth, {
  Size size = const Size(400, 900),
  double textScale = 1.0,
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
        apiClientProvider.overrideWithValue(client),
      ],
      child: MaterialApp(
        theme: AishTheme.light(),
        home: MediaQuery(
          data: MediaQueryData(textScaler: TextScaler.linear(textScale)),
          child: screen,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

// --- fixtures ---------------------------------------------------------------
//
// Every value is fictional and recognisably so: the masked phone body is all
// zeros and cannot reach a subscriber (Rule 23, Rule 45).

const String _noLink = '{"data":{"tracking_link":null},"meta":{}}';

String _linkBody({
  String state = 'ISSUED',
  bool isLive = true,
  int viewCount = 0,
  String? revokeReasonCode,
}) =>
    '{"data":{"tracking_link":{"id":"tl1","order_id":"o1","state":"$state",'
    '"issued_at":"2026-07-25T02:00:00+00:00",'
    '"expires_at":"2026-09-23T02:00:00+00:00",'
    '"last_viewed_at":${viewCount > 0 ? '"2026-07-25T03:00:00+00:00"' : 'null'},'
    '"view_count":$viewCount,'
    '"revoked_at":${revokeReasonCode == null ? 'null' : '"2026-07-25T04:00:00+00:00"'},'
    '"revoke_reason_code":${revokeReasonCode == null ? 'null' : '"$revokeReasonCode"'},'
    '"superseded_at":null,"is_live":$isLive,"version":1},'
    '"timeline":[{"type":"TrackingAccessIssued","actor_membership_id":"m1",'
    '"occurred_at":"2026-07-25T02:00:00+00:00"}]},"meta":{}}';

const String _issuedBody =
    '{"data":{"tracking_link":{"id":"tl2","order_id":"o1","state":"ISSUED",'
    '"issued_at":"2026-07-25T05:00:00+00:00",'
    '"expires_at":"2026-09-23T05:00:00+00:00","last_viewed_at":null,'
    '"view_count":0,"revoked_at":null,"revoke_reason_code":null,'
    '"superseded_at":null,"is_live":true,"version":1},'
    '"url":"https://contoh-fiktif.id/lacak/TOKEN-UJI-SEKALI-SAJA",'
    '"shown_once":true,'
    '"notice":"Tautan ini hanya ditampilkan sekali."},"meta":{}}';

String _notificationsBody(String inner) =>
    '{"data":{"notifications":[$inner]},"meta":{"page":1,"per_page":25,"total":1}}';

String _notification({
  String state = 'SENT',
  String stateLabel = 'Diterima penyedia pesan',
  bool canRetry = false,
  bool deferred = false,
  String? suppressionLabel,
}) =>
    '{"id":"n1","order_id":"o1","event_type":"order.ready",'
    '"template_key":"order_ready_for_pickup","category":"transactional",'
    '"channel":"whatsapp","recipient_masked":"+62 ···· 0001",'
    '"state":"$state","state_label":"$stateLabel",'
    '"suppression_reason":${suppressionLabel == null ? 'null' : '"marketing_opted_out"'},'
    '"suppression_label":${suppressionLabel == null ? 'null' : '"$suppressionLabel"'},'
    '"scheduled_for":"2026-07-25T05:00:00+00:00",'
    '"deferred_for_quiet_hours":$deferred,'
    '"attempt_count":1,"max_attempts":5,'
    '"last_attempted_at":"2026-07-25T05:00:00+00:00",'
    '"accepted_at":${state == 'SENT' ? '"2026-07-25T05:00:01+00:00"' : 'null'},'
    '"provider_key":"official_whatsapp_business",'
    '"failure_code":${state == 'SENT' ? 'null' : '"provider_timeout"'},'
    '"can_retry":$canRetry}';

String _providerBody({required bool available}) =>
    '{"data":{"provider":{"key":"${available ? 'official_whatsapp_business' : 'null_provider'}",'
    '"available":$available,'
    '"label":"${available ? 'Pengiriman otomatis aktif' : 'Pengiriman otomatis tidak aktif — gunakan tautan WhatsApp manual'}"}},'
    '"meta":{}}';

const String _manualLinkBody =
    '{"data":{"manual_link":{"url":"https://wa.me/6281200000000?text=Halo",'
    '"state":"MANUAL_FALLBACK_PREPARED",'
    '"prepared_at":"2026-07-25T06:00:00+00:00"},'
    '"notification":{"id":"n1","order_id":"o1","event_type":"order.ready",'
    '"template_key":"order_ready_for_pickup","category":"transactional",'
    '"channel":"whatsapp","recipient_masked":"+62 ···· 0001",'
    '"state":"MANUAL_FALLBACK_PREPARED",'
    '"state_label":"Tautan manual disiapkan — staf perlu mengirimnya",'
    '"suppression_reason":null,"suppression_label":null,'
    '"scheduled_for":"2026-07-25T05:00:00+00:00",'
    '"deferred_for_quiet_hours":false,"attempt_count":1,"max_attempts":5,'
    '"last_attempted_at":null,"accepted_at":null,"provider_key":null,'
    '"failure_code":null,"can_retry":false},'
    '"notice":"Tautan ini BELUM dikirim. Buka tautan lalu kirim pesannya sendiri melalui WhatsApp Anda."},'
    '"meta":{}}';

const String _emptyNotifications =
    '{"data":{"notifications":[]},"meta":{"page":1,"per_page":25,"total":0}}';

const String _serverError =
    '{"error":{"code":"INTERNAL_ERROR",'
    '"message":"Terjadi kesalahan pada sistem. Coba lagi beberapa saat lagi."},"meta":{}}';

void main() {
  // =====================================================================
  // Tracking link — the one-time URL and the lifecycle controls
  // =====================================================================

  testWidgets('an order with no link offers to create one and nothing else', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _noLink, pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    expect(find.text('Buat tautan'), findsOneWidget);

    // A control the operator cannot use is NOT rendered (Rule 28 hard rule 5):
    // there is nothing to rotate or revoke yet.
    expect(find.text('Rotasi tautan'), findsNothing);
    expect(find.text('Cabut tautan'), findsNothing);
  });

  testWidgets('issuing shows the URL once with an explicit warning', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('POST', 201, _issuedBody, pathContains: 'tracking-link'),
      on('GET', 200, _noLink, pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    await tester.tap(find.text('Buat tautan'));
    await tester.pumpAndSettle();

    expect(find.textContaining('TOKEN-UJI-SEKALI-SAJA'), findsOneWidget);
    // The warning is not decoration: it is why rotation exists (TRK-019).
    expect(find.textContaining('DITAMPILKAN SEKALI'), findsOneWidget);
    expect(find.text('Salin tautan'), findsOneWidget);
  });

  testWidgets('the issue command carries a uuid client_reference', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('POST', 201, _issuedBody, pathContains: 'tracking-link'),
      on('GET', 200, _noLink, pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );
    await tester.tap(find.text('Buat tautan'));
    await tester.pumpAndSettle();

    final RequestOptions post = s.adapter.requests.firstWhere(
      (RequestOptions o) => o.method == 'POST',
    );
    final Map<String, Object?> body = (post.data as Map)
        .cast<String, Object?>();

    // The server validates this as a UUID, and it is the idempotency key that
    // makes a replayed issue command refuse rather than mint a second link.
    expect(
      body['client_reference'],
      matches(
        RegExp(
          r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
        ),
      ),
    );
  });

  testWidgets('a live link offers rotate and revoke and shows the view count', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(viewCount: 3), pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    expect(find.text('Rotasi tautan'), findsOneWidget);
    expect(find.text('Cabut tautan'), findsOneWidget);
    // "Has the customer opened it?" is the question a counter actually asks.
    expect(find.textContaining('Dibuka pelanggan: 3×'), findsOneWidget);

    // Issuing is not offered while a live link exists — two live links for one
    // order is the state the server's partial unique index forbids.
    expect(find.text('Buat tautan'), findsNothing);
  });

  testWidgets('a revoked link offers only re-issue, never reactivation', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on(
        'GET',
        200,
        _linkBody(
          state: 'REVOKED',
          isLive: false,
          revokeReasonCode: 'over_shared',
        ),
        pathContains: 'tracking-link',
      ),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // Terminal states are never reactivated; recovery is a NEW issuance
    // (TRACKING_ACCESS_LIFECYCLE §5).
    expect(find.text('Buat tautan'), findsOneWidget);
    expect(find.text('Rotasi tautan'), findsNothing);
    expect(find.text('Cabut tautan'), findsNothing);
    expect(find.text('Dicabut'), findsOneWidget);
  });

  testWidgets('an expired link never reads as active', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      // The row still says ISSUED but the server says it is not live.
      on('GET', 200, _linkBody(isLive: false), pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // Showing "Aktif" for a link that does not open would tell the counter the
    // opposite of the truth.
    expect(find.text('Aktif'), findsNothing);
    expect(find.text('Kedaluwarsa'), findsOneWidget);
  });

  testWidgets('revoking requires a reason and restates the consequence', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on(
        'POST',
        200,
        _linkBody(state: 'REVOKED', isLive: false, revokeReasonCode: 'lost'),
        pathContains: 'revoke',
      ),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    await tester.tap(find.text('Cabut tautan'));
    await tester.pumpAndSettle();

    // The consequence is restated at the moment of commitment, and the reason is
    // mandatory (Rule 32 hard rules 15 and 16).
    expect(
      find.textContaining('tidak dapat diaktifkan kembali'),
      findsOneWidget,
    );
    expect(find.text('Alasan (wajib):'), findsOneWidget);
    expect(find.text('Pelanggan kehilangan tautan'), findsOneWidget);

    // The safe choice is available and separated from the destructive one.
    expect(find.text('Batal'), findsOneWidget);
  });

  testWidgets('the screen never renders a token or a token hash', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(viewCount: 1), pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // Rule 32 hard rule 10: ops surfaces show tracking STATE and a revoke
    // control, never the token. The projection carries neither, so there is
    // nothing here to render.
    expect(find.textContaining('token'), findsNothing);
    expect(find.textContaining('hash'), findsNothing);
  });

  // =====================================================================
  // Notifications — honesty about what was and was not sent
  // =====================================================================

  testWidgets('a sent message is never labelled as delivered to the customer', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on(
        'GET',
        200,
        _notificationsBody(_notification()),
        pathContains: 'notifications',
      ),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // We hold no delivery receipt (Rule 01).
    expect(find.text('Diterima penyedia pesan'), findsOneWidget);
    expect(find.textContaining('terkirim ke pelanggan'), findsNothing);
  });

  testWidgets('the recipient is masked even for staff', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on(
        'GET',
        200,
        _notificationsBody(_notification()),
        pathContains: 'notifications',
      ),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // A full phone number on a counter terminal is a personal datum on display
    // all day (Rule 32 hard rule 4).
    expect(find.textContaining('···· 0001'), findsOneWidget);
    expect(find.textContaining('6281200000000'), findsNothing);
  });

  testWidgets(
    'a quiet-hours deferral is explained rather than shown as failure',
    (tester) async {
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on(
          'GET',
          200,
          _providerBody(available: true),
          pathContains: 'provider-state',
        ),
        on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
        on(
          'GET',
          200,
          _notificationsBody(
            _notification(
              state: 'DEFERRED',
              stateLabel: 'Ditunda sampai di luar jam tenang',
              deferred: true,
            ),
          ),
          pathContains: 'notifications',
        ),
      ]);

      await pump(
        tester,
        const OrderTrackingScreen(orderId: 'o1'),
        s.client,
        ownerAuth(),
      );

      expect(find.textContaining('jam tenang'), findsWidgets);
      // Deferred is not dropped, and the operator is told it will still go
      // (FR-097, NOT-021).
      expect(find.textContaining('akan dikirim otomatis'), findsOneWidget);
    },
  );

  testWidgets('a suppressed message states WHY it was not sent', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on(
        'GET',
        200,
        _notificationsBody(
          _notification(
            state: 'SUPPRESSED',
            stateLabel: 'Tidak dikirim',
            suppressionLabel: 'Pelanggan menolak menerima pesan promosi',
          ),
        ),
        pathContains: 'notifications',
      ),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // "Suppressed" with no reason is an answer nobody can act on.
    expect(
      find.textContaining('Pelanggan menolak menerima pesan promosi'),
      findsOneWidget,
    );
  });

  testWidgets('a retryable failure offers retry and the manual fallback', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on(
        'GET',
        200,
        _notificationsBody(
          _notification(
            state: 'FAILED_RETRYABLE',
            stateLabel: 'Gagal — akan dicoba lagi otomatis',
            canRetry: true,
          ),
        ),
        pathContains: 'notifications',
      ),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    expect(find.text('Coba kirim lagi'), findsOneWidget);
    expect(find.text('Kirim manual lewat WhatsApp'), findsOneWidget);
  });

  testWidgets('a sent message offers neither retry nor the manual fallback', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on(
        'GET',
        200,
        _notificationsBody(_notification()),
        pathContains: 'notifications',
      ),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // Retrying a SENT message would be the duplicate FR-098 forbids.
    expect(find.text('Coba kirim lagi'), findsNothing);
    expect(find.text('Kirim manual lewat WhatsApp'), findsNothing);
  });

  testWidgets('the manual fallback says it was NOT sent', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('POST', 200, _manualLinkBody, pathContains: 'manual-link'),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on(
        'GET',
        200,
        _notificationsBody(
          _notification(
            state: 'FAILED_PERMANENT',
            stateLabel: 'Gagal permanen — kirim manual lewat WhatsApp',
          ),
        ),
        pathContains: 'notifications',
      ),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    await tester.tap(find.text('Kirim manual lewat WhatsApp'));
    await tester.pumpAndSettle();

    // FR-095: explicit, visible, and never presented as automation.
    expect(find.textContaining('BELUM dikirim'), findsOneWidget);
    expect(find.textContaining('wa.me'), findsOneWidget);
  });

  testWidgets('a disabled provider is stated plainly rather than implied', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: false),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    expect(
      find.textContaining('Pengiriman otomatis tidak aktif'),
      findsOneWidget,
    );
  });

  // =====================================================================
  // Failure, accessibility, and device states
  // =====================================================================

  testWidgets('a server error is surfaced with its recovery text', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 500, _serverError, pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // The message states what happened AND what to do next (Rule 29 rule 9).
    expect(find.textContaining('Coba lagi'), findsOneWidget);
  });

  testWidgets('the surface survives a narrow screen and large text', (
    tester,
  ) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(viewCount: 2), pathContains: 'tracking-link'),
      on(
        'GET',
        200,
        _notificationsBody(_notification()),
        pathContains: 'notifications',
      ),
    ]);

    // 320px is the guaranteed floor, and large system text must reflow rather
    // than truncate critical information (Rule 31 rules 1–2, Rule 27 rule 7).
    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
      size: const Size(320, 900),
      textScale: 1.6,
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Rotasi tautan'), findsOneWidget);
  });

  testWidgets('the tenant context is visible on the screen', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: true),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _linkBody(), pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // Rule 28 hard rule 1: the active tenant is rendered as TEXT in the primary
    // chrome of every authenticated screen. A staff member who works for two
    // competing laundries must be able to see whose data this is.
    expect(find.textContaining(ApiFixtures.tenantMelati.name), findsWidgets);
  });

  testWidgets('there is no dead control on the surface', (tester) async {
    final s = scripted(<(bool Function(RequestOptions), int, String)>[
      on(
        'GET',
        200,
        _providerBody(available: false),
        pathContains: 'provider-state',
      ),
      on('GET', 200, _noLink, pathContains: 'tracking-link'),
      on('GET', 200, _emptyNotifications, pathContains: 'notifications'),
    ]);

    await pump(
      tester,
      const OrderTrackingScreen(orderId: 'o1'),
      s.client,
      ownerAuth(),
    );

    // Every rendered button must have a real handler. A control that does
    // nothing is the dead control Rule 34 rejects.
    for (final Element element in find.byType(FilledButton).evaluate()) {
      expect((element.widget as FilledButton).onPressed, isNotNull);
    }
    for (final Element element in find.byType(OutlinedButton).evaluate()) {
      expect((element.widget as OutlinedButton).onPressed, isNotNull);
    }
  });
}
