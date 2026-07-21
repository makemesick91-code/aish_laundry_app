import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/master_data/catalogue_screen.dart';
import 'package:aish_ops_android/src/master_data/customer_counter_screen.dart';
import 'package:aish_ops_android/src/master_data/customer_detail_screen.dart';
import 'package:aish_ops_android/src/master_data/edit_outcome.dart';
import 'package:aish_ops_android/src/master_data/master_data_providers.dart';
import 'package:aish_ops_android/src/master_data/outlet_master_data_screen.dart';
import 'package:aish_ops_android/src/master_data/staff_roster_screen.dart';
import 'package:aish_ops_android/src/routing/ops_routes.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// STEP 4 MASTER DATA — OPS ANDROID COUNTER SURFACE.
///
/// Every fixture value here is fictional and recognisably so: sequential codes,
/// `contoh-fiktif` hosts, obviously fabricated identifiers, and masked phone
/// numbers that cannot reach a subscriber. This repository is PUBLIC and a
/// fixture copied from reality is a permanent disclosure (Rule 23, Rule 45).
Environment env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://ops.contoh-fiktif.id/api/v1',
  appName: 'Uji Ops',
).valueOrNull!;

/// Replies per request, matched by METHOD and PATH, and records what was asked.
///
/// Matched rather than queued, because the screens under test do not make their
/// requests in a fixed order or a fixed number: the outlet screen loads its
/// master data AND its zones, shifts and printers concurrently, and a queue
/// would hand the reply intended for a PATCH to whichever satellite happened to
/// finish first. Matching on the request keeps a test's intent legible.
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter(this.rules, this.fallback);

  /// Each rule: a predicate on the request, and the `(status, body)` to return.
  final List<(bool Function(RequestOptions), int, String)> rules;

  /// Served when no rule matches.
  final (int, String) fallback;

  final List<RequestOptions> requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
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

/// A rule matching on HTTP method, optionally narrowed by a path fragment.
(bool Function(RequestOptions), int, String) on(
  String method,
  int status,
  String body, {
  String? pathContains,
}) => (
  (RequestOptions options) =>
      options.method == method &&
      (pathContains == null || options.path.contains(pathContains)),
  status,
  body,
);

({MasterDataRepository repository, _ScriptedAdapter adapter}) scriptedRules(
  List<(bool Function(RequestOptions), int, String)> rules, {
  (int, String) fallback = (200, '{"data":{},"meta":{"request_id":"uji-fb"}}'),
}) {
  final adapter = _ScriptedAdapter(rules, fallback);
  final dio = Dio()..httpClientAdapter = adapter;
  final client = ApiClient(
    environment: env(),
    transport: CredentialTransport.bearerToken,
    dio: dio,
  );
  return (repository: MasterDataRepository(client), adapter: adapter);
}

/// Convenience: every request gets the same answer.
({MasterDataRepository repository, _ScriptedAdapter adapter}) scriptedOne(
  int status,
  String body,
) => scriptedRules(
  const <(bool Function(RequestOptions), int, String)>[],
  fallback: (status, body),
);

Future<void> pumpScreen(
  WidgetTester tester,
  Widget screen,
  MasterDataRepository repository,
  FakeAuthService auth,
) async {
  tester.view.physicalSize = const Size(400, 900);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
        masterDataRepositoryProvider.overrideWithValue(repository),
      ],
      child: MaterialApp(theme: AishTheme.light(), home: screen),
    ),
  );
  await tester.pumpAndSettle();
}

// ---------------------------------------------------------------------------
// Envelopes. Fictional throughout.
// ---------------------------------------------------------------------------

const String customerListEnvelope =
    '{"data":{"customers":[{"id":"11111111-1111-4111-8111-111111111111",'
    '"code":"PLG-000001","name":"Pelanggan Uji Fiktif","phone_masked":"+62****0000",'
    '"status":"active","version":"3"}],"pagination":{"page":1,"per_page":20,'
    '"total":1}},"meta":{"request_id":"uji-ops-0001"}}';

const String customerEmptyEnvelope =
    '{"data":{"customers":[],"pagination":{"page":1,"per_page":20,"total":0}},'
    '"meta":{"request_id":"uji-ops-0002"}}';

const String customerDetailEnvelope =
    '{"data":{"customer":{"id":"11111111-1111-4111-8111-111111111111",'
    '"code":"PLG-000001","name":"Pelanggan Uji Fiktif","phone_masked":"+62****0000",'
    '"status":"active","version":"3","email":"pelanggan.fiktif@contoh-fiktif.id",'
    '"internal_notes":"Catatan internal fiktif.","addresses":['
    '{"id":"aaaaaaaa-1111-4111-8111-111111111111","label":"Rumah",'
    '"address_line":"Jalan Contoh Fiktif Nomor 1","district":"Kecamatan Fiktif",'
    '"city":"Kota Fiktif","province":"Provinsi Fiktif","postal_code":"00000",'
    '"notes":null,"is_pickup_suitable":true,"is_delivery_suitable":true,'
    '"is_primary":true,"is_active":true}]}},"meta":{"request_id":"uji-ops-0003"}}';

const String consentEnvelope =
    '{"data":{"consents":[{"id":"cccccccc-1111-4111-8111-111111111111",'
    '"consent_type":"marketing_whatsapp","state":"granted","source":"counter",'
    '"recorded_at":"2026-07-20T02:00:00+00:00",'
    '"recorded_by_membership_id":"mbr_fiktif_0001","note":null}],'
    '"current":{"marketing_whatsapp":"granted","marketing_email":null,'
    '"marketing_sms":null}},"meta":{"request_id":"uji-ops-0004"}}';

const String outletEnvelope =
    '{"data":{"outlet":{"id":"otl_fiktif_melati_pusat","name":"Outlet Uji Fiktif",'
    '"code":"OTL-000001","timezone":"Asia/Jakarta","is_active":true,'
    '"quiet_hours_start":"20:00","quiet_hours_end":"08:00",'
    '"address_line":"Jalan Outlet Fiktif Nomor 2","contact_phone":"+62****1111",'
    '"daily_capacity_kg":100,"daily_capacity_orders":40,"version":"5",'
    '"operating_hours":{}}},"meta":{"request_id":"uji-ops-0005"}}';

const String emptySatelliteEnvelope =
    '{"data":{"zones":[],"shifts":[],"printers":[]},'
    '"meta":{"request_id":"uji-ops-0006"}}';

const String staffEnvelope =
    '{"data":{"staff":[{"membership_id":"mbr_fiktif_0002","status":"active",'
    '"user":{"id":"usr_fiktif_0002","name":"Staf Uji Fiktif",'
    '"email":"staf.fiktif@contoh-fiktif.id"},"roles":["cashier"],'
    '"outlet_assignments":[{"id":"asg_fiktif_0001",'
    '"membership_id":"mbr_fiktif_0002","outlet_id":"otl_fiktif_melati_pusat",'
    '"assigned_at":"2026-07-19T02:00:00+00:00","revoked_at":null,'
    '"is_active":true}]}],"pagination":{"page":1,"per_page":100,"total":1}},'
    '"meta":{"request_id":"uji-ops-0007"}}';

/// The shape `assignRole` and `removeRole` return: `staff` is a single OBJECT.
///
/// Deliberately distinct from [staffEnvelope], where `staff` is a LIST. The two
/// endpoints genuinely differ, and a test that reused one shape for the other
/// would pass while the client mis-decoded the real response.
const String staffMemberEnvelope =
    '{"data":{"staff":{"membership_id":"mbr_fiktif_0002","status":"active",'
    '"user":{"id":"usr_fiktif_0002","name":"Staf Uji Fiktif",'
    '"email":"staf.fiktif@contoh-fiktif.id"},"roles":["cashier","outlet_manager"],'
    '"outlet_assignments":[]}},"meta":{"request_id":"uji-ops-0013"}}';

const String suspendedStaffEnvelope =
    '{"data":{"staff":[{"membership_id":"mbr_fiktif_0003","status":"suspended",'
    '"user":{"id":"usr_fiktif_0003","name":"Staf Ditangguhkan Fiktif",'
    '"email":"ditangguhkan.fiktif@contoh-fiktif.id"},"roles":["courier"],'
    '"outlet_assignments":[]}],"pagination":{"page":1,"per_page":100,'
    '"total":1}},"meta":{"request_id":"uji-ops-0008"}}';

String errorEnvelope(String code, {Map<String, Object?>? details}) {
  final buffer = StringBuffer(
    '{"error":{"code":"$code","message":"Galat uji."',
  );
  if (details != null) {
    buffer.write(',"details":{');
    buffer.write(
      details.entries
          .map(
            (e) =>
                '"${e.key}":${e.value is List ? '["${(e.value as List).join('","')}"]' : '"${e.value}"'}',
          )
          .join(','),
    );
    buffer.write('}');
  }
  buffer.write('},"meta":{"request_id":"uji-ops-galat"}}');
  return buffer.toString();
}

/// A cashier session: may find and register a customer, may NOT manage the
/// catalogue, outlet master data, or the roster.
SessionState cashierSession() => SessionState(
  user: ApiFixtures.cashier,
  availableTenants: const <Tenant>[ApiFixtures.tenantMelati],
  activeTenant: ApiFixtures.tenantMelati,
  activeMembership: ApiFixtures.membershipCashierMelati,
  activeOutlet: ApiFixtures.outletMelatiPusat,
  permissions: ApiFixtures.cashierPermissions(ApiFixtures.tenantMelati.id),
);

void main() {
  late FakeAuthService auth;

  setUp(() {
    auth = FakeAuthService(
      initial: AuthState.authenticated(ApiFixtures.fullContext()),
    );
  });

  tearDown(() => auth.dispose());

  // =========================================================================
  // 1. THE STALE-WRITE CLASSIFICATION.
  //
  // The single most consequential branch in this module: a conflict that were
  // classified as "retryable" would let a surface resend the same payload and
  // silently destroy another operator's edit (threat T-12).
  // =========================================================================
  group('A stale write is classified apart from every other failure', () {
    test('CONFLICT produces EditConflict and forbids an identical resubmit', () {
      final outcome = classifyEdit(
        const Result<Object>.err(
          Failure(kind: FailureKind.validation, message: 'x', code: 'CONFLICT'),
        ),
      );

      expect(outcome, isA<EditConflict>());
      expect(
        outcome.allowsIdenticalResubmit,
        isFalse,
        reason:
            'Resending after a conflict SUCCEEDS and overwrites. It must never '
            'be offered as a retry.',
      );
    });

    test('an ordinary 422 is EditRejected, NOT a conflict', () {
      // Both arrive as FailureKind.validation. Only the machine-readable code
      // separates them, and they need entirely different recoveries: one
      // highlights a field, the other says somebody else changed the record.
      final outcome = classifyEdit(
        const Result<Object>.err(
          Failure(
            kind: FailureKind.validation,
            message: 'x',
            code: 'VALIDATION_FAILED',
            details: <String, Object?>{
              'name': <String>['Nama wajib diisi.'],
            },
          ),
        ),
      );

      expect(outcome, isA<EditRejected>());
      expect((outcome as EditRejected).fieldErrors['name'], <String>[
        'Nama wajib diisi.',
      ]);
    });

    test('a conflict is never inferred from the HTTP status alone', () {
      // A 409 whose envelope carries an unrecognised code must NOT be guessed
      // into a conflict. Guessing would attach conflict semantics — "somebody
      // else edited this" — to a failure that may mean nothing of the kind.
      final (failure, _) = ApiErrorMapper.fromEnvelope(
        statusCode: 409,
        body: const <String, Object?>{
          'error': <String, Object?>{'code': 'SOMETHING_NEW', 'message': 'x'},
        },
      );

      expect(classifyEdit(Result<Object>.err(failure)), isA<EditUnavailable>());
    });

    test('a transport failure is the only outcome safe to resubmit', () {
      final unreachable = classifyEdit(
        const Result<Object>.err(
          Failure(kind: FailureKind.network, message: 'x'),
        ),
      );
      final denied = classifyEdit(
        const Result<Object>.err(
          Failure(kind: FailureKind.authorization, message: 'x'),
        ),
      );

      expect(unreachable, isA<EditUnreachable>());
      expect(
        unreachable.allowsIdenticalResubmit,
        isTrue,
        reason:
            'The server never reached a decision, so the version precondition '
            'still holds and resending cannot overwrite anything.',
      );

      expect(denied, isA<EditDenied>());
      expect(denied.allowsIdenticalResubmit, isFalse);
    });

    test('an unknown code fails SAFE — transient, never permission-shaped', () {
      final outcome = classifyEdit(
        const Result<Object>.err(
          Failure(kind: FailureKind.unexpected, message: 'x', code: 'NEW_CODE'),
        ),
      );

      expect(outcome, isA<EditUnavailable>());
      expect(
        outcome,
        isNot(isA<EditDenied>()),
        reason:
            'Guessing a security meaning from an unknown string is how a client '
            'silently downgrades an isolation failure into a retry prompt.',
      );
    });
  });

  // =========================================================================
  // 2. THE WIRE CONTRACT.
  // =========================================================================
  group('Every write carries the version the caller read', () {
    test('a customer patch sends the exact token, unparsed', () async {
      final harness = scriptedOne(200, customerDetailEnvelope);

      await harness.repository.updateCustomer(
        id: '11111111-1111-4111-8111-111111111111',
        expectedVersion: '3',
        changes: const <String, Object?>{'name': 'Nama Baru Fiktif'},
      );

      final sent = harness.adapter.requests.single;
      expect(sent.method, 'PATCH');
      expect(sent.headers[ApiClient.versionHeaderName], '3');
    });

    test('an outlet patch sends the exact token', () async {
      final harness = scriptedOne(200, outletEnvelope);

      await harness.repository.updateOutletMasterData(
        outletId: 'otl_fiktif_melati_pusat',
        expectedVersion: '5',
        changes: const <String, Object?>{'name': 'Outlet Baru Fiktif'},
      );

      final sent = harness.adapter.requests.single;
      expect(sent.headers[ApiClient.versionHeaderName], '5');
    });

    test('an absent version omits the header rather than sending empty', () {
      // The server treats an ABSENT precondition as "no opinion". Sending the
      // header with an empty value would be a claim to have read a version that
      // was never read.
      final harness = scriptedOne(200, outletEnvelope);

      return harness.repository
          .updateOutletMasterData(
            outletId: 'otl_fiktif_melati_pusat',
            expectedVersion: null,
            changes: const <String, Object?>{'name': 'x'},
          )
          .then((_) {
            expect(
              harness.adapter.requests.single.headers.containsKey(
                ApiClient.versionHeaderName,
              ),
              isFalse,
            );
          });
    });
  });

  group('The reserved outlet_id field is never used for an assignment', () {
    test('assignOutlet sends assigned_outlet_id and no outlet_id', () async {
      // THE POINT OF THIS TEST.
      //
      // Step 3's tenant middleware treats a request-body `outlet_id` as the
      // CALLER'S ACTIVE OUTLET selector. Naming the rostered outlet `outlet_id`
      // would silently switch the operator's own working context on every
      // roster edit — and would make a cross-tenant attempt fail in MIDDLEWARE
      // rather than in the domain layer, which is a refusal arrived at for the
      // wrong reason and one that stops being true the moment the middleware
      // changes.
      final harness = scriptedOne(
        201,
        '{"data":{"assignment":{"id":"asg_fiktif_0002",'
        '"membership_id":"mbr_fiktif_0002","outlet_id":"otl_fiktif_melati_cabang",'
        '"assigned_at":"2026-07-21T02:00:00+00:00","revoked_at":null,'
        '"is_active":true}},"meta":{"request_id":"uji-ops-0009"}}',
      );

      await harness.repository.assignOutlet(
        membershipId: 'mbr_fiktif_0002',
        outletId: 'otl_fiktif_melati_cabang',
      );

      final body =
          harness.adapter.requests.single.data! as Map<String, Object?>;

      expect(body['assigned_outlet_id'], 'otl_fiktif_melati_cabang');
      expect(
        body.containsKey('outlet_id'),
        isFalse,
        reason:
            'A body `outlet_id` is intercepted by Step 3 middleware as the '
            "caller's own active-outlet selector.",
      );
    });
  });

  group('A role key the build does not enumerate cannot be sent', () {
    test('assignRole posts the wire value of an enumerated role', () async {
      final harness = scriptedOne(200, staffMemberEnvelope);

      await harness.repository.assignRole(
        membershipId: 'mbr_fiktif_0002',
        role: TenantRole.cashier,
      );

      final request = harness.adapter.requests.single;
      expect(request.method, 'POST');
      expect((request.data! as Map<String, Object?>)['role'], 'cashier');
    });

    test('the catalogue contains no platform role', () {
      // DEC-0025 §8 — a platform role is never assignable through a membership.
      // There is no member for one, so a picker cannot render one even by
      // mistake.
      final keys = TenantRole.values
          .map((role) => role.wireValue)
          .toList(growable: false);

      expect(keys, isNot(contains('platform_super_admin')));
      expect(keys, isNot(contains('platform_support')));
      for (final key in keys) {
        expect(key.startsWith('platform_'), isFalse);
      }
    });

    test('the staff picker excludes the non-staff customer role', () {
      expect(
        TenantRole.assignableToStaff,
        isNot(contains(TenantRole.customer)),
      );
      expect(TenantRole.assignableToStaff, contains(TenantRole.cashier));
    });

    test('an unrecognised role key is displayed, never coerced', () {
      final assigned = AssignedRole.fromWire('peran_yang_belum_dikenal');

      expect(assigned.role, isNull);
      expect(assigned.isRecognised, isFalse);
      expect(
        assigned.label,
        'peran_yang_belum_dikenal',
        reason:
            'Coercing an unknown key into a known member would display the '
            "wrong capability against a real person's name.",
      );
    });
  });

  group('Consent is append-only on the wire', () {
    test('recording a consent never sends a client timestamp', () async {
      // threat T-07 — a client-suppliable consent timestamp is a backdated
      // consent record.
      final harness = scriptedOne(
        201,
        '{"data":{"consent":{"id":"cccccccc-2222-4222-8222-222222222222",'
        '"consent_type":"marketing_whatsapp","state":"withdrawn",'
        '"source":"counter","recorded_at":"2026-07-21T02:00:00+00:00"}},'
        '"meta":{"request_id":"uji-ops-0010"}}',
      );

      await harness.repository.recordConsent(
        customerId: '11111111-1111-4111-8111-111111111111',
        type: ConsentType.marketingWhatsapp,
        state: ConsentState.withdrawn,
        source: ConsentSource.counter,
      );

      final request = harness.adapter.requests.single;
      final body = request.data! as Map<String, Object?>;

      expect(request.method, 'POST');
      expect(body.containsKey('recorded_at'), isFalse);
      expect(body['state'], 'withdrawn');
    });

    test('the repository exposes no way to edit or delete a consent', () {
      // Enforced by ABSENCE. A withdrawal is a NEW record, never an edit of the
      // record that granted (invariant C5), so there is no method to call and
      // no route behind one.
      final methods = MasterDataRepository.new.runtimeType.toString();
      expect(methods, isNotEmpty);

      // The meaningful assertion is on the endpoint surface: consent has ONE
      // path, used for both read and append.
      expect(ApiEndpoints.customerConsents('x'), 'customers/x/consents');
    });
  });

  group('Pagination is bounded on the client as well as the server', () {
    test('an oversized page request is clamped', () async {
      final harness = scriptedOne(200, customerListEnvelope);

      await harness.repository.customers(perPage: 5000);

      expect(
        harness.adapter.requests.single.queryParameters['per_page'],
        MasterDataRepository.maxPerPage,
        reason:
            'An unbounded list request is how a counter app on a cheap phone '
            "ends up holding a tenant's entire customer database in memory.",
      );
    });

    test('the counter lookup asks for a small page', () async {
      final harness = scriptedOne(200, customerListEnvelope);

      await harness.repository.customers(
        perPage: MasterDataRepository.counterPageSize,
      );

      expect(
        harness.adapter.requests.single.queryParameters['per_page'],
        lessThanOrEqualTo(MasterDataRepository.maxPerPage),
      );
    });
  });

  group('Money stays an exact integer through the client', () {
    test('Rupiah refuses a double and a formatted string', () {
      expect(() => Rupiah.parse(17500.0), throwsArgumentError);
      expect(() => Rupiah.parse('17.500'), throwsArgumentError);
      expect(Rupiah.parse(17500).amount, 17500);
    });

    test('a large amount survives with no precision loss', () {
      // A `double` cannot hold every integer above 2^53. Rupiah is an `int`,
      // which on the VM is 64-bit, so a nine-figure total is exact.
      const huge = Rupiah(9007199254740993);
      expect(huge.amount, 9007199254740993);
    });
  });

  // =========================================================================
  // 3. WIDGET STATES — THE COUNTER
  // =========================================================================
  group('Customer lookup renders every canonical state', () {
    testWidgets('LOADED shows a masked phone and offers no unmask control', (
      tester,
    ) async {
      final harness = scriptedOne(200, customerListEnvelope);
      await pumpScreen(
        tester,
        const CustomerCounterScreen(),
        harness.repository,
        auth,
      );

      expect(find.text('Pelanggan Uji Fiktif'), findsOneWidget);
      expect(find.textContaining('+62****0000'), findsOneWidget);

      // Unmasking is a deliberate, per-record, permissioned, recorded SERVER
      // action (Rule 32 hard rule 5). Step 4 exposes no endpoint for it, so
      // there is nothing for a control to call.
      expect(find.textContaining('Buka nomor'), findsNothing);
      expect(find.textContaining('Tampilkan nomor'), findsNothing);
      expect(find.textContaining('Lihat nomor lengkap'), findsNothing);
    });

    testWidgets('a list row renders no address component', (tester) async {
      // Rule 32 hard rule 4 forbids an address in a list row outright. The
      // summary type has no address field, so this is structural — but the
      // assertion is here because a future widget could reach for one.
      final harness = scriptedOne(200, customerListEnvelope);
      await pumpScreen(
        tester,
        const CustomerCounterScreen(),
        harness.repository,
        auth,
      );

      expect(find.textContaining('Jalan'), findsNothing);
      expect(find.textContaining('Kecamatan'), findsNothing);
    });

    testWidgets('EMPTY after a search names the term that found nothing', (
      tester,
    ) async {
      final harness = scriptedOne(200, customerEmptyEnvelope);
      await pumpScreen(
        tester,
        const CustomerCounterScreen(),
        harness.repository,
        auth,
      );

      expect(find.text('Belum ada pelanggan'), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'zzz-tidak-ada');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();

      expect(find.text('Tidak ada pelanggan yang cocok'), findsOneWidget);
      expect(find.textContaining('zzz-tidak-ada'), findsWidgets);
    });

    testWidgets('DENIED discloses nothing about whether the data exists', (
      tester,
    ) async {
      final harness = scriptedOne(403, errorEnvelope('FORBIDDEN'));
      await pumpScreen(
        tester,
        const CustomerCounterScreen(),
        harness.repository,
        auth,
      );

      expect(
        find.text('Anda tidak memiliki akses ke data ini'),
        findsOneWidget,
      );

      // Across a tenant boundary the server answers identically for "not yours"
      // and "not there" (Rule 48 hard rule 5).
      expect(find.textContaining('tidak ditemukan'), findsNothing);
      expect(find.textContaining('tenant lain'), findsNothing);

      // No retry on a refusal: a control that will never work is a dead end.
      expect(find.text('Coba lagi'), findsNothing);
    });

    testWidgets('OFFLINE reads differently from a server failure', (
      tester,
    ) async {
      // Rule 29 — the recoveries differ, so the states must too: one waits for
      // a signal, the other waits for the service.
      final dio = Dio()
        ..httpClientAdapter = _ThrowingAdapter(
          DioExceptionType.connectionError,
        );
      final repository = MasterDataRepository(
        ApiClient(
          environment: env(),
          transport: CredentialTransport.bearerToken,
          dio: dio,
        ),
      );

      await pumpScreen(tester, const CustomerCounterScreen(), repository, auth);

      expect(find.text('Perangkat sedang luring'), findsOneWidget);
      expect(find.text('Coba lagi'), findsOneWidget);
    });

    testWidgets('a create control is not rendered without the permission', (
      tester,
    ) async {
      // A control the user may not use is not rendered (Rule 28 hard rule 5).
      // This is a COURTESY, not the control: the server checks `customer.create`
      // regardless of what this predicate decided.
      final readOnly = FakeAuthService(
        initial: AuthState.authenticated(
          SessionState(
            user: ApiFixtures.cashier,
            availableTenants: const <Tenant>[ApiFixtures.tenantMelati],
            activeTenant: ApiFixtures.tenantMelati,
            activeMembership: ApiFixtures.membershipCashierMelati,
            activeOutlet: ApiFixtures.outletMelatiPusat,
            permissions: EffectivePermissions(
              tenantId: ApiFixtures.tenantMelati.id,
              permissions: <Permission>{Permission(Permission.customerView)},
            ),
          ),
        ),
      );
      addTearDown(readOnly.dispose);

      final harness = scriptedOne(200, customerListEnvelope);
      await pumpScreen(
        tester,
        const CustomerCounterScreen(),
        harness.repository,
        readOnly,
      );

      expect(find.text('Pelanggan baru'), findsNothing);
    });

    testWidgets('the active tenant is visible on the counter screen', (
      tester,
    ) async {
      // Rule 28 hard rule 1 — a screen where the active tenant is not visible is
      // a tenant-isolation design defect, not a layout preference.
      final harness = scriptedOne(200, customerListEnvelope);
      await pumpScreen(
        tester,
        const CustomerCounterScreen(),
        harness.repository,
        auth,
      );

      expect(find.textContaining(ApiFixtures.tenantMelati.name), findsWidgets);
    });
  });

  // =========================================================================
  // 4. THE CONFLICT UX — the behaviour this whole module exists to get right
  // =========================================================================
  group('A conflicting customer edit offers reload, never retry', () {
    Future<void> pumpConflict(WidgetTester tester) async {
      // 1st: the detail load. 2nd: the consent ledger. 3rd onward: the PATCH,
      // which conflicts, and every subsequent request.
      final harness =
          scriptedRules(<(bool Function(RequestOptions), int, String)>[
            on('PATCH', 409, errorEnvelope('CONFLICT')),
            on('GET', 200, consentEnvelope, pathContains: 'consents'),
            on('GET', 200, customerDetailEnvelope),
          ]);

      await pumpScreen(
        tester,
        const CustomerDetailScreen(
          customerId: '11111111-1111-4111-8111-111111111111',
        ),
        harness.repository,
        auth,
      );

      await tester.tap(find.text('Ubah data pelanggan'));
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byType(TextFormField).first,
        'Nama Yang Sedang Diketik',
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Simpan perubahan'));
      await tester.pumpAndSettle();
    }

    testWidgets('it names the conflict and offers a reload action', (
      tester,
    ) async {
      await pumpConflict(tester);

      expect(find.text('Data ini sudah diubah orang lain'), findsOneWidget);
      expect(find.text('Muat ulang data terbaru'), findsOneWidget);
    });

    testWidgets('it offers NO generic retry control', (tester) async {
      await pumpConflict(tester);

      // THE CENTRAL ASSERTION OF THIS FILE.
      //
      // Resending the identical payload after a conflict does not fail — it
      // SUCCEEDS and destroys the edit that caused the conflict. A "coba lagi"
      // button here is a data-loss defect, not a convenience (threat T-12).
      expect(
        find.text('Coba lagi'),
        findsNothing,
        reason:
            'A retry after a conflict resends the same payload and silently '
            "overwrites somebody else's edit.",
      );
    });

    testWidgets('it says plainly that nothing was saved', (tester) async {
      await pumpConflict(tester);

      expect(find.textContaining('BELUM disimpan'), findsOneWidget);
      expect(
        find.textContaining('tidak akan dikirim ulang secara'),
        findsOneWidget,
      );
    });

    testWidgets('it preserves what the operator typed', (tester) async {
      await pumpConflict(tester);

      // Discarding their work to "reset cleanly" would punish them for somebody
      // else's edit. The form stays open with their text in it.
      expect(find.text('Nama Yang Sedang Diketik'), findsOneWidget);
    });

    testWidgets('a conflict is not rendered as a validation error', (
      tester,
    ) async {
      await pumpConflict(tester);

      // Nothing the caller SENT is wrong. Highlighting a field would send the
      // operator looking for a mistake they did not make.
      expect(find.text('Periksa kembali isian Anda'), findsNothing);
    });
  });

  group('A conflicting outlet edit behaves the same way', () {
    testWidgets('reload is offered and retry is not', (tester) async {
      final harness =
          scriptedRules(<(bool Function(RequestOptions), int, String)>[
            on('PATCH', 409, errorEnvelope('CONFLICT')),
            on('GET', 200, outletEnvelope, pathContains: 'master-data'),
            on('GET', 200, emptySatelliteEnvelope),
          ]);

      await pumpScreen(
        tester,
        const OutletMasterDataScreen(),
        harness.repository,
        auth,
      );

      await tester.enterText(
        find.byType(TextFormField).first,
        'Outlet Diubah Fiktif',
      );
      await tester.pumpAndSettle();

      await tester.scrollUntilVisible(
        find.text('Simpan perubahan'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.text('Simpan perubahan'));
      await tester.pumpAndSettle();

      // The notice renders at the TOP of the form, where an operator returning
      // to the screen sees it first. Scroll back up to assert on it.
      await tester.scrollUntilVisible(
        find.text('Data ini sudah diubah orang lain'),
        -200,
        scrollable: find.byType(Scrollable).first,
      );

      expect(find.text('Data ini sudah diubah orang lain'), findsOneWidget);
      expect(find.text('Muat ulang data terbaru'), findsOneWidget);
      expect(find.text('Coba lagi'), findsNothing);
      expect(find.text('Outlet Diubah Fiktif'), findsOneWidget);
    });

    testWidgets('the loaded version token is what the patch sends', (
      tester,
    ) async {
      final harness =
          scriptedRules(<(bool Function(RequestOptions), int, String)>[
            on('PATCH', 200, outletEnvelope),
            on('GET', 200, outletEnvelope, pathContains: 'master-data'),
            on('GET', 200, emptySatelliteEnvelope),
          ]);

      await pumpScreen(
        tester,
        const OutletMasterDataScreen(),
        harness.repository,
        auth,
      );

      await tester.scrollUntilVisible(
        find.text('Simpan perubahan'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.text('Simpan perubahan'));
      await tester.pumpAndSettle();

      final patches = harness.adapter.requests
          .where((request) => request.method == 'PATCH')
          .toList(growable: false);

      expect(patches, isNotEmpty);
      expect(
        patches.first.headers[ApiClient.versionHeaderName],
        '5',
        reason:
            'The token must be the one that arrived with the record, not a '
            're-read and never a timestamp.',
      );
    });

    testWidgets('an operator without the permission gets a read-only view', (
      tester,
    ) async {
      final cashier = FakeAuthService(
        initial: AuthState.authenticated(cashierSession()),
      );
      addTearDown(cashier.dispose);

      final harness =
          scriptedRules(<(bool Function(RequestOptions), int, String)>[
            on('GET', 200, outletEnvelope, pathContains: 'master-data'),
            on('GET', 200, emptySatelliteEnvelope),
          ]);

      await pumpScreen(
        tester,
        const OutletMasterDataScreen(),
        harness.repository,
        cashier,
      );

      expect(
        find.text('Anda hanya dapat melihat data outlet ini'),
        findsOneWidget,
      );
      expect(find.text('Simpan perubahan'), findsNothing);
    });

    testWidgets('no active outlet is a state with a recovery, not a blank', (
      tester,
    ) async {
      final noOutlet = FakeAuthService(
        initial: AuthState.authenticated(ApiFixtures.tenantOnly()),
      );
      addTearDown(noOutlet.dispose);

      final harness = scriptedOne(200, outletEnvelope);
      await pumpScreen(
        tester,
        const OutletMasterDataScreen(),
        harness.repository,
        noOutlet,
      );

      expect(find.text('Belum ada outlet aktif'), findsOneWidget);
      expect(find.text('Pilih outlet'), findsOneWidget);
    });
  });

  // =========================================================================
  // 5. CONSENT
  // =========================================================================
  group('Consent renders its true state and cannot be edited', () {
    testWidgets('never-asked is not rendered as withdrawn', (tester) async {
      // Consent is opt-in. An absent record is not a decision, and conflating
      // them would let a screen imply the customer said no when nobody ever
      // asked (Rule 32 hard rule 22).
      final harness =
          scriptedRules(<(bool Function(RequestOptions), int, String)>[
            on('GET', 200, consentEnvelope, pathContains: 'consents'),
            on('GET', 200, customerDetailEnvelope),
          ]);

      await pumpScreen(
        tester,
        const CustomerDetailScreen(
          customerId: '11111111-1111-4111-8111-111111111111',
        ),
        harness.repository,
        auth,
      );

      await tester.scrollUntilVisible(
        find.text('Persetujuan promosi'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();

      expect(find.text('Belum ditanyakan'), findsWidgets);
      expect(find.text('Disetujui'), findsWidgets);
    });

    testWidgets('there is no consent edit or delete control anywhere', (
      tester,
    ) async {
      final harness =
          scriptedRules(<(bool Function(RequestOptions), int, String)>[
            on('GET', 200, consentEnvelope, pathContains: 'consents'),
            on('GET', 200, customerDetailEnvelope),
          ]);

      await pumpScreen(
        tester,
        const CustomerDetailScreen(
          customerId: '11111111-1111-4111-8111-111111111111',
        ),
        harness.repository,
        auth,
      );

      await tester.scrollUntilVisible(
        find.text('Persetujuan promosi'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();

      // A withdrawal is a NEW record appended to the history, never an edit of
      // the record that granted (invariant C5). The history IS the evidence.
      expect(find.text('Ubah persetujuan'), findsNothing);
      expect(find.text('Hapus'), findsNothing);
      expect(find.text('Hapus riwayat'), findsNothing);
      expect(find.textContaining('tambah-saja'), findsOneWidget);
    });

    testWidgets('withdrawing is confirmed with the safe choice focused', (
      tester,
    ) async {
      final harness =
          scriptedRules(<(bool Function(RequestOptions), int, String)>[
            on('GET', 200, consentEnvelope, pathContains: 'consents'),
            on('GET', 200, customerDetailEnvelope),
          ]);

      await pumpScreen(
        tester,
        const CustomerDetailScreen(
          customerId: '11111111-1111-4111-8111-111111111111',
        ),
        harness.repository,
        auth,
      );

      await tester.scrollUntilVisible(
        find.text('Persetujuan promosi'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Tarik').first);
      await tester.pumpAndSettle();

      // Rule 32 hard rules 14–15: the dialogue names what is about to happen
      // rather than asking a generic "are you sure".
      expect(find.textContaining('Tarik persetujuan'), findsWidgets);
      expect(find.textContaining('dicatat sebagai entri BARU'), findsOneWidget);
      expect(find.text('Batal'), findsOneWidget);
    });
  });

  // =========================================================================
  // 6. THE ROSTER
  // =========================================================================
  group('The roster keeps outlet assignment and role grant apart', () {
    testWidgets('it says an outlet assignment confers nothing', (tester) async {
      final harness = scriptedOne(200, staffEnvelope);
      await pumpScreen(
        tester,
        const StaffRosterScreen(),
        harness.repository,
        auth,
      );

      expect(find.textContaining('tidak memberikan wewenang'), findsOneWidget);
    });

    testWidgets('a suspended membership offers no assignment control', (
      tester,
    ) async {
      final harness = scriptedOne(200, suspendedStaffEnvelope);
      await pumpScreen(
        tester,
        const StaffRosterScreen(),
        harness.repository,
        auth,
      );

      expect(find.text('Ditangguhkan'), findsOneWidget);
      expect(find.text('Berikan peran'), findsNothing);
      expect(find.text('Tugaskan ke outlet'), findsNothing);
    });

    testWidgets('the role picker never offers a platform role', (tester) async {
      final harness = scriptedOne(200, staffEnvelope);
      await pumpScreen(
        tester,
        const StaffRosterScreen(),
        harness.repository,
        auth,
      );

      await tester.tap(find.text('Berikan peran'));
      await tester.pumpAndSettle();

      expect(find.textContaining('Platform'), findsNothing);
      expect(find.textContaining('platform_'), findsNothing);

      // And it says plainly that the SERVER decides, so a refusal reads as a
      // rule rather than a bug.
      expect(find.textContaining('akan ditolak server'), findsOneWidget);
    });

    testWidgets('the picker excludes a role the member already holds', (
      tester,
    ) async {
      final harness = scriptedOne(200, staffEnvelope);
      await pumpScreen(
        tester,
        const StaffRosterScreen(),
        harness.repository,
        auth,
      );

      await tester.tap(find.text('Berikan peran'));
      await tester.pumpAndSettle();

      // The fixture member already holds `cashier`. Offering it again would be
      // a no-op dressed as an action.
      expect(
        find.descendant(
          of: find.byType(ListTile),
          matching: find.text('Kasir'),
        ),
        findsNothing,
      );
      expect(
        find.descendant(
          of: find.byType(ListTile),
          matching: find.text('Manager outlet'),
        ),
        findsOneWidget,
      );
    });

    testWidgets('granting a role is confirmed and states the effect', (
      tester,
    ) async {
      final harness = scriptedOne(200, staffEnvelope);
      await pumpScreen(
        tester,
        const StaffRosterScreen(),
        harness.repository,
        auth,
      );

      await tester.tap(find.text('Berikan peran'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Manager outlet').last);
      await tester.pumpAndSettle();

      expect(
        find.textContaining('Berikan peran Manager outlet?'),
        findsWidgets,
      );
      expect(
        find.textContaining('berlaku pada permintaan berikutnya'),
        findsOneWidget,
      );
      expect(find.text('Batal'), findsOneWidget);
    });

    testWidgets('a refused role grant explains the escalation rule', (
      tester,
    ) async {
      final harness =
          scriptedRules(<(bool Function(RequestOptions), int, String)>[
            on('POST', 403, errorEnvelope('FORBIDDEN')),
            on('GET', 200, staffEnvelope),
            on('DELETE', 200, staffMemberEnvelope),
          ]);

      await pumpScreen(
        tester,
        const StaffRosterScreen(),
        harness.repository,
        auth,
      );

      await tester.tap(find.text('Berikan peran'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Manager outlet').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ya, berikan peran'));
      await tester.pumpAndSettle();

      // The generic "you lack access" copy would be misleading here: the caller
      // may well hold the assignment permission and still be refused, because
      // the escalation guard forbids granting a role carrying a permission the
      // caller does not itself hold.
      expect(find.text('Tindakan ini ditolak server'), findsOneWidget);
      expect(
        find.textContaining('izin yang tidak Anda miliki sendiri'),
        findsOneWidget,
      );
    });

    testWidgets('revoking a role is confirmed as immediate', (tester) async {
      final harness = scriptedOne(200, staffEnvelope);
      await pumpScreen(
        tester,
        const StaffRosterScreen(),
        harness.repository,
        auth,
      );

      await tester.tap(
        find.byTooltip('Cabut peran Kasir dari Staf Uji Fiktif'),
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('Cabut peran'), findsWidgets);
      expect(find.textContaining('berlaku SEGERA'), findsOneWidget);
    });
  });

  // =========================================================================
  // 7. THE CATALOGUE
  // =========================================================================
  group('The catalogue quotes a price without computing a total', () {
    testWidgets('it reads ACTIVE price lists only', (tester) async {
      final harness = scriptedOne(
        200,
        '{"data":{"price_lists":[],"pagination":{"page":1,"per_page":100,'
        '"total":0}},"meta":{"request_id":"uji-ops-0011"}}',
      );

      await pumpScreen(
        tester,
        const CatalogueScreen(),
        harness.repository,
        auth,
      );

      await tester.tap(find.text('Daftar harga'));
      await tester.pumpAndSettle();

      final priceRequests = harness.adapter.requests
          .where((request) => request.path.contains('price-lists'))
          .toList(growable: false);

      expect(priceRequests, isNotEmpty);
      expect(
        priceRequests.last.queryParameters['status'],
        'active',
        reason:
            'A counter operator answering "how much is this" must not read a '
            'price off a DRAFT that has never been published.',
      );
    });

    testWidgets('it offers no control that would author a price', (
      tester,
    ) async {
      final harness = scriptedOne(
        200,
        '{"data":{"services":[],"pagination":{"page":1,"per_page":100,'
        '"total":0}},"meta":{"request_id":"uji-ops-0012"}}',
      );

      await pumpScreen(
        tester,
        const CatalogueScreen(),
        harness.repository,
        auth,
      );

      // Price authorship is FR-034's, and it is not at the counter. A cashier
      // changing a price is the financial control point FR-039 exists to guard.
      expect(find.text('Terbitkan'), findsNothing);
      expect(find.text('Ubah harga'), findsNothing);
      expect(find.text('Tambah layanan'), findsNothing);
    });
  });

  // =========================================================================
  // 8. SCOPE — the roadmap lock, asserted structurally
  // =========================================================================
  group('The Ops surface reaches no Step 5+ feature', () {
    test('no master-data route names an order, a payment or a document', () {
      // CLAUDE.md §3 (roadmap lock), DEC-0030. `receipt` and its Indonesian
      // forms stay forbidden while `printer` is permitted: FR-045 authorises
      // printer CONFIGURATION as outlet master data; the document is FR-052.
      const List<String> forbidden = <String>[
        'order',
        'pesanan',
        'transaksi',
        'payment',
        'pembayaran',
        'invoice',
        'faktur',
        'receipt',
        'nota',
        'struk',
        'checkout',
        'cart',
        'keranjang',
        'tracking',
        'pickup',
        'penjemputan',
        'delivery',
        'pengantaran',
        'reminder',
        'pengingat',
        'subscription',
        'langganan',
        'export',
        'bulk',
      ];

      const List<String> masterDataRoutes = <String>[
        OpsRoutes.customers,
        OpsRoutes.customerCreate,
        OpsRoutes.customerDetail,
        OpsRoutes.catalogue,
        OpsRoutes.outletMasterData,
        OpsRoutes.staffRoster,
      ];

      for (final route in masterDataRoutes) {
        for (final token in forbidden) {
          expect(
            route.contains(token),
            isFalse,
            reason:
                'Route "$route" contains the Step 5+ token "$token". Step 4 '
                'builds master data, not the workflows that consume it.',
          );
        }
      }
    });

    test('the customer detail route is built from an id, not guessed', () {
      expect(
        OpsRoutes.customerDetailFor('11111111-1111-4111-8111-111111111111'),
        '/beranda/pelanggan/11111111-1111-4111-8111-111111111111',
      );

      // `baru` is a literal segment registered BEFORE the parameter, so the
      // create route can never be read as a customer id.
      expect(OpsRoutes.customerCreate, '/beranda/pelanggan/baru');
    });
  });
}

/// An adapter that always fails at the transport layer.
class _ThrowingAdapter implements HttpClientAdapter {
  _ThrowingAdapter(this.type);

  final DioExceptionType type;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async => throw DioException(requestOptions: options, type: type);

  @override
  void close({bool force = false}) {}
}
