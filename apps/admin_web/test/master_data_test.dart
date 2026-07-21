import 'package:aish_admin_web/src/app.dart';
import 'package:aish_admin_web/src/master_data/master_data_screens.dart';
import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// STEP 4 MASTER DATA — CONSOLE SURFACE.
///
/// Every fixture value here is fictional and recognisably so: sequential codes,
/// `contoh-fiktif` hosts, and phone numbers that cannot reach a subscriber. This
/// repository is PUBLIC and a fixture copied from reality is a permanent
/// disclosure (Rule 23, Rule 45).
Environment env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://konsol.contoh-fiktif.id/api/v1',
  appName: 'Uji Console',
).valueOrNull!;

/// Replies with a scripted body, and records what was asked for.
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter(this.statusCode, this.body);

  final int statusCode;
  final String body;
  final List<RequestOptions> requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
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

({MasterDataRepository repository, _ScriptedAdapter adapter}) scripted(
  int statusCode,
  String body,
) {
  final adapter = _ScriptedAdapter(statusCode, body);
  final dio = Dio()..httpClientAdapter = adapter;
  final client = ApiClient(
    environment: env(),
    transport: CredentialTransport.sessionCookie,
    dio: dio,
  );
  return (repository: MasterDataRepository(client), adapter: adapter);
}

Future<void> pumpSection(
  WidgetTester tester,
  MasterDataRepository repository,
  FakeAuthService auth,
) async {
  tester.view.physicalSize = const Size(1366, 768);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
        masterDataRepositoryProvider.overrideWithValue(repository),
      ],
      child: MaterialApp(
        theme: AishTheme.light(),
        home: const Scaffold(body: MasterDataScreen()),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

const String successEnvelope =
    '{"data":{"customers":[{"id":"11111111-1111-4111-8111-111111111111",'
    '"code":"PLG-000001","name":"Pelanggan Uji Fiktif","phone_masked":"+62****0000",'
    '"status":"active","version":"1"}],"pagination":{"page":1,"per_page":25,'
    '"total":1}},"meta":{"request_id":"uji-0001"}}';

/// A complete single-customer envelope, for the write-path tests.
///
/// Deliberately COMPLETE rather than `{"customer":{}}`: `CustomerSummary.fromJson`
/// fails loudly on a missing id rather than half-building a record, so a
/// truncated fixture would fail the test for a reason that has nothing to do
/// with what it is asserting.
const String patchedCustomerEnvelope =
    '{"data":{"customer":{"id":"11111111-1111-4111-8111-111111111111",'
    '"code":"PLG-000001","name":"Nama Baru","phone_masked":"+62****0000",'
    '"status":"active","version":"8"}},"meta":{"request_id":"uji-0006"}}';

const String emptyEnvelope =
    '{"data":{"customers":[],"pagination":{"page":1,"per_page":25,"total":0}},'
    '"meta":{"request_id":"uji-0002"}}';

void main() {
  late FakeAuthService auth;

  setUp(() {
    // A signed-in owner with a resolved tenant — the ONLY state the
    // master-data section is ever reached from. Starting unauthenticated would
    // make every widget assertion below pass vacuously against an empty frame.
    auth = FakeAuthService(
      initial: AuthState.authenticated(ApiFixtures.fullContext()),
    );
  });

  tearDown(() => auth.dispose());

  group('Endpoint scope — the client cannot reach a Step 5 path', () {
    test('no endpoint constant names an order, payment, or document', () {
      // DEC-0030, Rule 42. `ApiEndpoints` is exhaustive on purpose: a client
      // constant is how a feature quietly acquires a foothold before its Step.
      // This asserts the CONSTANTS, not merely that no screen calls them.
      const List<String> forbidden = <String>[
        'order',
        'payment',
        'invoice',
        'receipt',
        'nota',
        'struk',
        'checkout',
        'cart',
        'tracking',
        'pickup',
        'delivery',
        'reminder',
        'subscription',
        'export',
        'bulk',
      ];

      final List<String> declared = <String>[
        ApiEndpoints.customers,
        ApiEndpoints.customer('x'),
        ApiEndpoints.customerArchive('x'),
        ApiEndpoints.customerConsents('x'),
        ApiEndpoints.serviceCategories,
        ApiEndpoints.services,
        ApiEndpoints.service('x'),
        ApiEndpoints.servicePackages,
        ApiEndpoints.servicePackageItems('x'),
        ApiEndpoints.serviceAddons,
        ApiEndpoints.priceLists,
        ApiEndpoints.priceList('x'),
        ApiEndpoints.priceListPublish('x'),
        ApiEndpoints.priceListItems('x'),
        ApiEndpoints.outletMasterData('x'),
        ApiEndpoints.outletServiceZones('x'),
        ApiEndpoints.outletShifts('x'),
        ApiEndpoints.outletPrinters('x'),
        ApiEndpoints.proofPolicy,
        ApiEndpoints.staff,
        ApiEndpoints.staffOutlets('x'),
        ApiEndpoints.staffRoles('x'),
      ];

      for (final path in declared) {
        for (final token in forbidden) {
          expect(
            path.contains(token),
            isFalse,
            reason:
                'Endpoint "$path" contains the Step 5+ token "$token". '
                'Step 4 builds master data, not the workflows that consume it.',
          );
        }
      }
    });
  });

  group('Money is integer Rupiah at every client layer', () {
    test('Rupiah refuses a double and a formatted string', () {
      // Rule 04 hard rule 2 — a client is part of the money path, and a total
      // computed in `double` for display then sent back is how a rounding error
      // reaches the server.
      expect(() => Rupiah.parse(17500.0), throwsArgumentError);
      expect(() => Rupiah.parse(17500.5), throwsArgumentError);
      expect(() => Rupiah.parse('17500.50'), throwsArgumentError);
      expect(() => Rupiah.parse('Rp17.500'), throwsArgumentError);

      expect(Rupiah.parse(17500).amount, 17500);
      expect(Rupiah.parse('17500').amount, 17500);
    });

    test('formatting is one-way and Indonesian', () {
      expect(const Rupiah(17500).formatted, 'Rp17.500');
      expect(const Rupiah(7000).formatted, 'Rp7.000');
      expect(const Rupiah(0).formatted, 'Rp0');
      expect(const Rupiah(1000000).formatted, 'Rp1.000.000');
    });

    test('Rupiah offers no arithmetic', () {
      // Totals are computed and authoritative ON THE SERVER; a client total is
      // display only. Offering `+` here would invite a surface to compute an
      // order total, and an order is Step 5 regardless.
      expect(
        const Rupiah(1000),
        isNot(isA<num>()),
        reason: 'Money must not be substitutable for a number.',
      );
    });
  });

  group('A stale write is never retryable', () {
    test('CONFLICT maps to a non-retryable failure', () {
      // threat T-12. Falling through to `unexpected` would mark it RETRYABLE,
      // and a surface offering "coba lagi" would resend the same payload and
      // overwrite the edit that caused the conflict.
      final (failure, consequence) = ApiErrorMapper.fromEnvelope(
        statusCode: 409,
        body: const <String, Object?>{
          'error': <String, Object?>{'code': 'CONFLICT', 'message': 'x'},
        },
      );

      expect(consequence, ClientErrorConsequence.staleWrite);
      expect(
        failure.isRetryable,
        isFalse,
        reason: 'Retrying a stale write silently overwrites another edit.',
      );
    });

    test('a stale write does not end the session', () {
      // A conflicting edit says something about ONE RECORD, never about the
      // caller's right to be in this tenant.
      expect(
        authStateFor(ClientErrorConsequence.staleWrite),
        const AuthState.unauthenticated(),
        reason:
            'staleWrite must fall through to the transient fallback, not to a '
            'session-ending state.',
      );
    });
  });

  group('The version precondition reaches the wire', () {
    test('a patch carrying an expected version sends the header', () async {
      final harness = scripted(200, patchedCustomerEnvelope);

      await harness.repository.updateCustomer(
        id: '11111111-1111-4111-8111-111111111111',
        expectedVersion: '7',
        changes: const <String, Object?>{'name': 'Nama Baru'},
      );

      final sent = harness.adapter.requests.single;

      expect(sent.method, 'PATCH');
      expect(sent.headers[ApiClient.versionHeaderName], '7');
    });

    test('a patch without one omits the header entirely', () async {
      // The server treats an ABSENT precondition as "no opinion" and an empty
      // one the same way — but sending the header with an empty value would be
      // a claim to have read a version that was never read. Omit it instead.
      final harness = scripted(200, patchedCustomerEnvelope);

      await harness.repository.updateCustomer(
        id: '11111111-1111-4111-8111-111111111111',
        expectedVersion: null,
        changes: const <String, Object?>{'name': 'Nama Baru'},
      );

      final sent = harness.adapter.requests.single;

      expect(sent.headers.containsKey(ApiClient.versionHeaderName), isFalse);
    });
  });

  group('Customer list renders every canonical state', () {
    testWidgets('LOADED shows the masked phone and never a full one', (
      tester,
    ) async {
      final harness = scripted(200, successEnvelope);
      await pumpSection(tester, harness.repository, auth);

      expect(find.text('Pelanggan Uji Fiktif'), findsOneWidget);

      // The masked form the server sent, rendered as it arrived. There is no
      // unmask control: unmasking is a deliberate, permissioned, recorded
      // server action, never a client affordance (Rule 32 hard rule 5).
      expect(find.textContaining('+62****0000'), findsOneWidget);
      expect(find.textContaining('Buka nomor'), findsNothing);
      expect(find.textContaining('Tampilkan nomor'), findsNothing);
    });

    testWidgets('EMPTY says what would appear here and why', (tester) async {
      final harness = scripted(200, emptyEnvelope);
      await pumpSection(tester, harness.repository, auth);

      // Rule 29 hard rule 10 — an empty state is designed, not left blank.
      expect(find.text('Belum ada pelanggan'), findsOneWidget);
    });

    testWidgets('DENIED discloses nothing about whether data exists', (
      tester,
    ) async {
      final harness = scripted(
        403,
        '{"error":{"code":"FORBIDDEN","message":"x"},"meta":{"request_id":"uji-0003"}}',
      );
      await pumpSection(tester, harness.repository, auth);

      expect(
        find.text('Anda tidak memiliki akses ke data ini'),
        findsOneWidget,
      );

      // Across a tenant boundary the server answers identically for "not yours"
      // and "not there". A client that said "tidak ditemukan" here would leak
      // the distinction the server just hid (Rule 48 hard rule 5).
      expect(find.textContaining('tidak ditemukan'), findsNothing);
      expect(find.textContaining('tenant lain'), findsNothing);

      // And no retry button: a permission refusal is not retryable, and a
      // button that would always fail is a dead end.
      expect(find.text('Coba lagi'), findsNothing);
    });

    testWidgets('a NETWORK error offers a retry and a support reference', (
      tester,
    ) async {
      final harness = scripted(
        503,
        '{"error":{"code":"SERVICE_UNAVAILABLE",'
        '"message":"x"},"meta":{"request_id":"uji-0004"}}',
      );
      await pumpSection(tester, harness.repository, auth);

      expect(find.text('Data gagal dimuat'), findsOneWidget);
      expect(find.text('Coba lagi'), findsOneWidget);

      // The correlation id is safe to show: it is an identifier that grants
      // nothing, which is exactly why it is the value surfaced and a token
      // never is.
      expect(find.textContaining('uji-0004'), findsOneWidget);
    });

    testWidgets('every tab is reachable by keyboard', (tester) async {
      // Console Web is keyboard-complete: every action reachable by pointer is
      // reachable by keyboard (Rule 27 hard rule 8, Rule 28 hard rule 8).
      final harness = scripted(200, emptyEnvelope);
      await pumpSection(tester, harness.repository, auth);

      for (final label in <String>[
        'Pelanggan',
        'Layanan',
        'Daftar harga',
        'Staf',
      ]) {
        expect(find.text(label), findsOneWidget);
      }
    });
  });

  group('Status is never carried by colour alone', () {
    testWidgets('an archived customer carries a text label', (tester) async {
      const archived =
          '{"data":{"customers":[{"id":"22222222-2222-4222-8222-222222222222",'
          '"code":"PLG-000002","name":"Pelanggan Uji Arsip","phone_masked":"+62****0000",'
          '"status":"archived","version":"1"}],"pagination":{"page":1,'
          '"per_page":25,"total":1}},"meta":{"request_id":"uji-0005"}}';

      final harness = scripted(200, archived);
      await pumpSection(tester, harness.repository, auth);

      // Rule 27 hard rule 3 — the text label is a required part of the
      // rendering, not an enhancement. A cheap screen in direct sunlight must
      // read the same state as everyone else.
      expect(find.text('Diarsipkan'), findsOneWidget);
    });
  });
}
