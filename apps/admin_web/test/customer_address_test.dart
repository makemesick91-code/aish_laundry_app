import 'package:aish_admin_web/src/app.dart';
import 'package:aish_admin_web/src/master_data/customer_address_panel.dart';
import 'package:aish_admin_web/src/master_data/master_data_screens.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// FR-024 / FR-025 on the Console (SEC-05).
///
/// THE CONSOLE MUST NOT BE MORE PERMISSIVE THAN THE COUNTER. The same server
/// projection, the same conflict taxonomy, the same fail-closed precision
/// parsing. These tests exist because a second surface is exactly where a
/// privacy control quietly diverges: the Ops tests could stay green forever
/// while the Console rendered a street the server told it to withhold.
///
/// Every address is fictional and recognisably so (Rule 23, Rule 45).
void main() {
  const street = 'Jl. Contoh Fiktif No. 12';
  const notes = 'Pagar contoh fiktif.';

  Environment env() => Environment.validate(
    environmentName: 'production',
    apiBaseUrl: 'https://konsol.contoh-fiktif.id/api/v1',
    appName: 'Uji Console',
  ).valueOrNull!;

  String addressJson({
    String precision = 'full',
    bool active = true,
    bool primary = true,
    String version = '1',
    String label = 'Rumah',
  }) =>
      '{"id":"adr_fiktif_0001","label":"$label","precision":"$precision",'
      '${precision == 'full' ? '"address_line":"$street","postal_code":"40123","notes":"$notes",' : ''}'
      '"district":"Kelurahan Contoh Fiktif","city":"Kota Contoh Fiktif",'
      '"province":"Provinsi Contoh Fiktif",'
      '"is_pickup_suitable":true,"is_delivery_suitable":true,'
      '"is_primary":$primary,"is_active":$active,"version":"$version"}';

  String ledger({String precision = 'full', List<String>? rows}) =>
      '{"data":{"addresses":[${(rows ?? <String>[addressJson()]).join(',')}],'
      '"precision":"$precision"},"meta":{"request_id":"uji-web-adr"}}';

  ({MasterDataRepository repository, _Adapter adapter}) scripted(
    List<(String, int, String)> rules, {
    (int, String) fallback = (200, '{"data":{},"meta":{}}'),
  }) {
    final adapter = _Adapter(rules, fallback);
    final dio = Dio()..httpClientAdapter = adapter;
    return (
      repository: MasterDataRepository(
        ApiClient(
          environment: env(),
          transport: CredentialTransport.sessionCookie,
          dio: dio,
        ),
      ),
      adapter: adapter,
    );
  }

  Future<void> pump(
    WidgetTester tester,
    MasterDataRepository repository, {
    bool canManage = true,
  }) async {
    tester.view.physicalSize = const Size(1366, 768);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [masterDataRepositoryProvider.overrideWithValue(repository)],
        child: MaterialApp(
          theme: AishTheme.light(),
          home: Scaffold(
            body: SingleChildScrollView(
              child: CustomerAddressPanel(
                customerId: 'plg_fiktif_0001',
                customerName: 'Pelanggan Uji Fiktif',
                canManage: canManage,
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('the panel names WHOSE addresses these are', (tester) async {
    // Context restated at the point of action. An operator working through
    // several customers must not have to infer which one this panel belongs to
    // (Rule 28 hard rule 2).
    final h = scripted(<(String, int, String)>[('GET', 200, ledger())]);
    await pump(tester, h.repository);

    expect(find.textContaining('Pelanggan Uji Fiktif'), findsOneWidget);
  });

  testWidgets('a full projection renders the street', (tester) async {
    final h = scripted(<(String, int, String)>[('GET', 200, ledger())]);
    await pump(tester, h.repository);

    expect(find.textContaining(street), findsOneWidget);
  });

  testWidgets('an AREA projection renders no street anywhere in the tree', (
    tester,
  ) async {
    final h = scripted(<(String, int, String)>[
      (
        'GET',
        200,
        ledger(
          precision: 'area',
          rows: <String>[addressJson(precision: 'area')],
        ),
      ),
    ]);
    await pump(tester, h.repository);

    // Offstage included. A value parked in a detached subtree is still a value
    // the browser process holds and a devtools inspection can reach.
    expect(find.textContaining(street, skipOffstage: false), findsNothing);
    expect(find.textContaining(notes, skipOffstage: false), findsNothing);
    expect(find.textContaining('40123', skipOffstage: false), findsNothing);

    // The area itself IS rendered, so "nothing leaked" is not satisfied by
    // rendering nothing at all.
    expect(
      find.textContaining('Kota Contoh Fiktif', skipOffstage: false),
      findsWidgets,
    );
  });

  testWidgets('an unknown precision marker fails closed', (tester) async {
    final h = scripted(<(String, int, String)>[
      (
        'GET',
        200,
        '{"data":{"addresses":[{"id":"adr_x","label":"Rumah",'
            '"precision":"belum-dikenal","address_line":"$street",'
            '"is_primary":true,"is_active":true,"version":"1"}],'
            '"precision":"belum-dikenal"},"meta":{}}',
      ),
    ]);
    await pump(tester, h.repository);

    expect(find.textContaining(street, skipOffstage: false), findsNothing);
  });

  testWidgets('a NONE projection discloses no address at all', (tester) async {
    final h = scripted(<(String, int, String)>[
      (
        'GET',
        200,
        '{"data":{"addresses":[{"id":"adr_y","label":"Rumah",'
            '"precision":"none","is_primary":true,"is_active":true,'
            '"version":"1"}],"precision":"none"},"meta":{}}',
      ),
    ]);
    await pump(tester, h.repository);

    expect(find.textContaining(street, skipOffstage: false), findsNothing);
    expect(
      find.textContaining('tidak menampilkan detail alamat'),
      findsOneWidget,
    );
  });

  testWidgets('a masked editor is offered no street field', (tester) async {
    final h = scripted(<(String, int, String)>[
      (
        'GET',
        200,
        ledger(
          precision: 'area',
          rows: <String>[addressJson(precision: 'area')],
        ),
      ),
    ]);
    await pump(tester, h.repository);

    await tester.tap(find.text('Ubah'));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(TextFormField, 'Alamat'), findsNothing);
    expect(find.widgetWithText(TextFormField, 'Label'), findsOneWidget);
  });

  testWidgets('a create posts and then re-reads from the server', (
    tester,
  ) async {
    final h = scripted(<(String, int, String)>[
      ('POST', 201, '{"data":{"address":${addressJson()}},"meta":{}}'),
      ('GET', 200, ledger()),
    ]);
    await pump(tester, h.repository);

    await tester.tap(find.text('Tambah alamat'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Label'),
      'Kantor',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Alamat'),
      street,
    );
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();

    expect(
      h.adapter.requests.where((r) => r.method == 'GET').length,
      greaterThanOrEqualTo(2),
    );
    expect(find.text('Alamat tersimpan'), findsOneWidget);
  });

  testWidgets('an edit sends the version it read with the record', (
    tester,
  ) async {
    final h = scripted(<(String, int, String)>[
      (
        'PATCH',
        200,
        '{"data":{"address":${addressJson(version: '2')}},"meta":{}}',
      ),
      ('GET', 200, ledger()),
    ]);
    await pump(tester, h.repository);

    await tester.tap(find.text('Ubah'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();

    final patch = h.adapter.requests.firstWhere((r) => r.method == 'PATCH');
    expect(patch.headers[ApiClient.versionHeaderName], '1');
  });

  testWidgets('a 409 offers reload and never a generic retry', (tester) async {
    final h = scripted(<(String, int, String)>[
      ('PATCH', 409, '{"error":{"code":"CONFLICT","message":"Berubah."}}'),
      ('GET', 200, ledger()),
    ]);
    await pump(tester, h.repository);

    await tester.tap(find.text('Ubah'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();

    expect(find.text('Alamat ini baru saja diubah orang lain'), findsOneWidget);
    expect(find.text('Muat ulang alamat'), findsOneWidget);
    expect(find.textContaining('Coba lagi'), findsNothing);

    // Record-scoped. The panel stays rendered and nothing suggests the session
    // ended; a conflict must never clear a credential.
    expect(find.text('Rumah'), findsOneWidget);
  });

  testWidgets('a 403 is stated as a permission matter', (tester) async {
    final h = scripted(<(String, int, String)>[
      ('PATCH', 403, '{"error":{"code":"FORBIDDEN"}}'),
      ('GET', 200, ledger()),
    ]);
    await pump(tester, h.repository);

    await tester.tap(find.text('Ubah'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();

    expect(
      find.text('Tindakan ini tidak tersedia untuk peran Anda'),
      findsOneWidget,
    );
  });

  testWidgets('an archived address offers reactivation, not editing', (
    tester,
  ) async {
    final h = scripted(<(String, int, String)>[
      (
        'GET',
        200,
        ledger(rows: <String>[addressJson(active: false, primary: false)]),
      ),
    ]);
    await pump(tester, h.repository);

    expect(find.text('Nonaktif'), findsOneWidget);
    expect(find.text('Aktifkan kembali'), findsOneWidget);
    expect(find.text('Ubah'), findsNothing);
  });

  testWidgets('archiving confirms with the customer and address named', (
    tester,
  ) async {
    final h = scripted(<(String, int, String)>[('GET', 200, ledger())]);
    await pump(tester, h.repository);

    await tester.tap(find.text('Nonaktifkan'));
    await tester.pumpAndSettle();

    expect(find.textContaining('"Rumah"'), findsOneWidget);
    expect(find.textContaining('Pelanggan Uji Fiktif'), findsWidgets);
  });

  testWidgets('the filter narrows what is shown and never widens the query', (
    tester,
  ) async {
    final h = scripted(<(String, int, String)>[
      (
        'GET',
        200,
        ledger(
          rows: <String>[
            addressJson(label: 'Rumah'),
            addressJson(label: 'Kantor', primary: false),
          ],
        ),
      ),
    ]);
    await pump(tester, h.repository);

    final before = h.adapter.requests.length;

    await tester.enterText(
      find.widgetWithText(TextField, 'Cari label alamat'),
      'Kantor',
    );
    await tester.pumpAndSettle();

    // Scoped to the rendered ROWS. `find.text` alone would also match the
    // search field, which now contains the same word the test typed into it.
    expect(find.widgetWithText(Card, 'Kantor'), findsOneWidget);
    expect(find.widgetWithText(Card, 'Rumah'), findsNothing);

    // NO ADDITIONAL REQUEST. A filter that reached back for more rows would be
    // a way to enumerate a tenant's customer base one keystroke at a time.
    expect(h.adapter.requests.length, before);
  });

  testWidgets('a read-only operator is offered no write control', (
    tester,
  ) async {
    final h = scripted(<(String, int, String)>[('GET', 200, ledger())]);
    await pump(tester, h.repository, canManage: false);

    expect(find.text('Rumah'), findsOneWidget);
    expect(find.text('Tambah alamat'), findsNothing);
    expect(find.text('Ubah'), findsNothing);
  });

  testWidgets('every form field carries a visible label', (tester) async {
    // A placeholder disappears once typing starts, leaving a screen-reader user
    // and a distracted operator with an unlabelled box (Rule 27).
    final h = scripted(<(String, int, String)>[('GET', 200, ledger())]);
    await pump(tester, h.repository);

    await tester.tap(find.text('Tambah alamat'));
    await tester.pumpAndSettle();

    for (final label in <String>[
      'Label',
      'Alamat',
      'Kelurahan',
      'Kota',
      'Provinsi',
      'Kode pos',
    ]) {
      expect(
        find.widgetWithText(TextFormField, label),
        findsOneWidget,
        reason: '$label must be a visible label, not a placeholder',
      );
    }
  });

  test('the production graph resolves the address repository', () {
    // The DEC-0032 defect class on the Console: a screen dependency supplied
    // only by a widget test looks wired until a real browser reads it.
    final container = ProviderContainer(
      overrides: [environmentProvider.overrideWithValue(env())],
    );
    addTearDown(container.dispose);

    expect(
      container.read(masterDataRepositoryProvider),
      isA<MasterDataRepository>(),
    );
  });
}

class _Adapter implements HttpClientAdapter {
  _Adapter(this.rules, this.fallback);

  final List<(String, int, String)> rules;
  final (int, String) fallback;
  final List<RequestOptions> requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);

    var (status, body) = fallback;
    for (final (method, ruleStatus, ruleBody) in rules) {
      if (options.method == method) {
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
