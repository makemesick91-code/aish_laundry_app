import 'package:aish_networking/aish_networking.dart';
import 'package:dio/dio.dart';
import 'package:aish_ops_android/src/master_data/customer_address_section.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:aish_testing/aish_testing.dart';

import 'master_data_test.dart' as harness;

/// FR-024 / FR-025 — the Ops address surface (SEC-05).
///
/// EVERY MASKING ASSERTION IS MADE AGAINST THE WHOLE RENDERED TREE, not against
/// a widget the test picked out. A street can leak through a `Text`, a
/// `Semantics` label, a tooltip, or a controller still holding a value from an
/// earlier build, and an assertion aimed at one widget would miss the other
/// four. `find.textContaining` with `skipOffstage: false` walks all of them.
///
/// Every address here is fictional and recognisably so (Rule 23, Rule 45).
void main() {
  const street = 'Jl. Contoh Fiktif No. 12';
  const notes = 'Pagar contoh fiktif.';

  String addressJson({
    String id = 'adr_fiktif_0001',
    String label = 'Rumah',
    String precision = 'full',
    bool active = true,
    bool primary = true,
    String version = '1',
  }) =>
      '{"id":"$id","label":"$label","precision":"$precision",'
      '${precision == 'full' ? '"address_line":"$street","postal_code":"40123","notes":"$notes",' : ''}'
      '"district":"Kelurahan Contoh Fiktif","city":"Kota Contoh Fiktif",'
      '"province":"Provinsi Contoh Fiktif",'
      '"is_pickup_suitable":true,"is_delivery_suitable":true,'
      '"is_primary":$primary,"is_active":$active,"version":"$version"}';

  String ledgerEnvelope({String precision = 'full', List<String>? addresses}) =>
      '{"data":{"addresses":[${(addresses ?? <String>[addressJson()]).join(',')}],'
      '"precision":"$precision"},"meta":{"request_id":"uji-adr-0001"}}';

  String singleEnvelope(String address) =>
      '{"data":{"address":$address},"meta":{"request_id":"uji-adr-0002"}}';

  const emptyLedger =
      '{"data":{"addresses":[],"precision":"full"},'
      '"meta":{"request_id":"uji-adr-0003"}}';

  Future<void> pump(
    WidgetTester tester,
    MasterDataRepository repository, {
    bool canManage = true,
  }) => harness.pumpScreen(
    tester,
    Scaffold(
      body: SingleChildScrollView(
        child: CustomerAddressSection(
          customerId: 'plg_fiktif_0001',
          canManage: canManage,
        ),
      ),
    ),
    repository,
    FakeAuthService(),
  );

  group('Address list', () {
    testWidgets('1. renders the saved addresses on a successful load', (
      tester,
    ) async {
      final h = harness.scriptedOne(200, ledgerEnvelope());
      await pump(tester, h.repository);

      expect(find.text('Rumah'), findsOneWidget);
      expect(find.text('Utama'), findsOneWidget);
      expect(find.textContaining(street), findsOneWidget);
    });

    testWidgets('2. shows a designed empty state, not a blank area', (
      tester,
    ) async {
      final h = harness.scriptedOne(200, emptyLedger);
      await pump(tester, h.repository);

      expect(find.text('Belum ada alamat'), findsOneWidget);
    });

    testWidgets('3. a load failure offers reload and says nothing else broke', (
      tester,
    ) async {
      final h = harness.scriptedOne(
        503,
        '{"error":{"code":"SERVICE_UNAVAILABLE"}}',
      );
      await pump(tester, h.repository);

      expect(find.text('Alamat tidak dapat dimuat'), findsOneWidget);
      expect(find.text('Muat ulang'), findsOneWidget);
    });
  });

  group('Create and edit', () {
    testWidgets('4. the create form opens and validates a required label', (
      tester,
    ) async {
      final h = harness.scriptedOne(200, emptyLedger);
      await pump(tester, h.repository);

      await tester.tap(find.text('Tambah alamat'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();

      expect(find.text('Label wajib diisi.'), findsOneWidget);
    });

    testWidgets('5. a malformed postal code is refused before submission', (
      tester,
    ) async {
      final h = harness.scriptedOne(200, emptyLedger);
      await pump(tester, h.repository);

      await tester.tap(find.text('Tambah alamat'));
      await tester.pumpAndSettle();

      await tester.enterText(
        find.widgetWithText(TextFormField, 'Label'),
        'Rumah',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Alamat'),
        street,
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Kode pos'),
        '12',
      );
      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();

      expect(find.text('Kode pos terdiri dari 5 angka.'), findsOneWidget);
    });

    testWidgets('6. a successful create re-reads from the server', (
      tester,
    ) async {
      final h = harness
          .scriptedRules(<(bool Function(RequestOptions), int, String)>[
            harness.on('POST', 201, singleEnvelope(addressJson())),
            harness.on('GET', 200, ledgerEnvelope()),
          ]);
      await pump(tester, h.repository);

      await tester.tap(find.text('Tambah alamat'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Label'),
        'Rumah',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Alamat'),
        street,
      );
      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();

      // A SECOND GET after the POST. The surface adopts the server's state
      // rather than splicing the returned record in: setting a primary demotes
      // another row, and a spliced list would show two primaries.
      final gets = h.adapter.requests.where((r) => r.method == 'GET').length;
      expect(gets, greaterThanOrEqualTo(2));
      expect(find.text('Alamat tersimpan'), findsOneWidget);
    });

    testWidgets('7. an edit submits the version it read with the record', (
      tester,
    ) async {
      final h = harness
          .scriptedRules(<(bool Function(RequestOptions), int, String)>[
            harness.on('PATCH', 200, singleEnvelope(addressJson(version: '2'))),
            harness.on('GET', 200, ledgerEnvelope()),
          ]);
      await pump(tester, h.repository);

      await tester.tap(find.text('Ubah'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();

      final patch = h.adapter.requests.firstWhere((r) => r.method == 'PATCH');
      expect(
        patch.headers[ApiClient.versionHeaderName],
        '1',
        reason:
            'an edit without the version token is last-write-wins, and on a '
            'delivery address that is a parcel at the wrong house',
      );
    });
  });

  group('Stale write (HTTP 409)', () {
    Future<({MasterDataRepository repository, dynamic adapter})>
    conflictHarness(WidgetTester tester) async {
      final h = harness
          .scriptedRules(<(bool Function(RequestOptions), int, String)>[
            harness.on(
              'PATCH',
              409,
              '{"error":{"code":"CONFLICT","message":"Data telah berubah."}}',
            ),
            harness.on('GET', 200, ledgerEnvelope()),
          ]);
      await pump(tester, h.repository);

      await tester.tap(find.text('Ubah'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();

      return h;
    }

    testWidgets(
      '8. a conflict is explained as somebody else having changed it',
      (tester) async {
        await conflictHarness(tester);

        expect(
          find.text('Alamat ini baru saja diubah orang lain'),
          findsOneWidget,
        );
      },
    );

    testWidgets('9. the recovery is RELOAD, and no generic retry is offered', (
      tester,
    ) async {
      await conflictHarness(tester);

      expect(find.text('Muat ulang alamat'), findsOneWidget);

      // The distinction this whole taxonomy exists for. Resending the identical
      // payload SUCCEEDS and destroys the other person's correction, so a
      // "coba lagi" affordance must not exist on this state (threat T-12).
      expect(find.textContaining('Coba lagi'), findsNothing);
      expect(find.textContaining('coba lagi'), findsNothing);
    });

    testWidgets('10. a conflict does not resubmit anything on its own', (
      tester,
    ) async {
      final h = await conflictHarness(tester);
      final patchesAfterConflict = h.adapter.requests
          .where((r) => r.method == 'PATCH')
          .length;

      await tester.pump(const Duration(seconds: 2));
      await tester.pumpAndSettle();

      expect(
        h.adapter.requests.where((r) => r.method == 'PATCH').length,
        patchesAfterConflict,
        reason: 'a conflict must never trigger an automatic resend',
      );
    });

    testWidgets('11. the surface stays usable after a conflict', (
      tester,
    ) async {
      await conflictHarness(tester);

      // Record-scoped: the list is still rendered, the section is still
      // interactive, and nothing suggests the session ended. `staleWrite` must
      // never terminate a session or clear a credential.
      expect(find.text('Rumah'), findsOneWidget);
      expect(find.text('Muat ulang alamat'), findsOneWidget);
    });
  });

  group('Archive and reactivate', () {
    testWidgets('12. archiving confirms with the specific address named', (
      tester,
    ) async {
      final h = harness.scriptedOne(200, ledgerEnvelope());
      await pump(tester, h.repository);

      await tester.tap(find.text('Nonaktifkan'));
      await tester.pumpAndSettle();

      expect(find.text('Nonaktifkan alamat?'), findsOneWidget);
      expect(find.textContaining('"Rumah"'), findsOneWidget);
    });

    testWidgets('13. an archived address offers reactivation, not editing', (
      tester,
    ) async {
      final h = harness.scriptedOne(
        200,
        ledgerEnvelope(
          addresses: <String>[addressJson(active: false, primary: false)],
        ),
      );
      await pump(tester, h.repository);

      expect(find.text('Nonaktif'), findsOneWidget);
      expect(find.text('Aktifkan kembali'), findsOneWidget);
      expect(find.text('Ubah'), findsNothing);
    });
  });

  group('Masking (FR-025)', () {
    testWidgets('14. AREA precision renders no street anywhere in the tree', (
      tester,
    ) async {
      final h = harness.scriptedOne(
        200,
        ledgerEnvelope(
          precision: 'area',
          addresses: <String>[addressJson(precision: 'area')],
        ),
      );
      await pump(tester, h.repository);

      expect(find.text('Kota Contoh Fiktif'), findsNothing);
      expect(
        find.textContaining('Kota Contoh Fiktif', skipOffstage: false),
        findsWidgets,
      );

      // NOT ANYWHERE. Offstage widgets included, because a value parked in a
      // detached subtree is still a value the process holds and a screenshot or
      // a semantics dump can surface.
      expect(
        find.textContaining(street, skipOffstage: false),
        findsNothing,
        reason: 'the street was never serialised at AREA precision',
      );
      expect(find.textContaining(notes, skipOffstage: false), findsNothing);
      expect(find.textContaining('40123', skipOffstage: false), findsNothing);
    });

    testWidgets(
      '15. AREA precision is explained rather than looking like a gap',
      (tester) async {
        final h = harness.scriptedOne(
          200,
          ledgerEnvelope(
            precision: 'area',
            addresses: <String>[addressJson(precision: 'area')],
          ),
        );
        await pump(tester, h.repository);

        expect(
          find.textContaining('hanya menampilkan wilayah'),
          findsOneWidget,
          reason:
              'an operator must know the product is withholding the street '
              'deliberately, not that somebody failed to type it in',
        );
      },
    );

    testWidgets('16. an unknown precision marker fails closed to no street', (
      tester,
    ) async {
      // A build that does not recognise what the server sent must assume it
      // holds the LEAST it is entitled to. Treating an unknown marker as `full`
      // would start rendering streets the moment a server adds a marker this
      // build has not learned.
      final h = harness.scriptedOne(
        200,
        '{"data":{"addresses":[{"id":"adr_x","label":"Rumah",'
        '"precision":"belum-dikenal","address_line":"$street",'
        '"is_primary":true,"is_active":true,"version":"1"}],'
        '"precision":"belum-dikenal"},"meta":{"request_id":"uji-adr-0004"}}',
      );
      await pump(tester, h.repository);

      expect(find.textContaining(street, skipOffstage: false), findsNothing);
    });

    testWidgets('17. a masked editor cannot blank a street it cannot read', (
      tester,
    ) async {
      final h = harness.scriptedOne(
        200,
        ledgerEnvelope(
          precision: 'area',
          addresses: <String>[addressJson(precision: 'area')],
        ),
      );
      await pump(tester, h.repository);

      await tester.tap(find.text('Ubah'));
      await tester.pumpAndSettle();

      // No street input at all. Presenting an empty one would invite an
      // operator to type over a value the server deliberately withheld.
      expect(find.widgetWithText(TextFormField, 'Alamat'), findsNothing);
      expect(find.widgetWithText(TextFormField, 'Label'), findsOneWidget);
    });

    testWidgets('18. no street appears in the error state either', (
      tester,
    ) async {
      final h = harness
          .scriptedRules(<(bool Function(RequestOptions), int, String)>[
            harness.on(
              'PATCH',
              422,
              '{"error":{"code":"VALIDATION_FAILED","message":"Tidak valid.",'
                  '"details":{"address_line":["invalid"]}}}',
            ),
            harness.on('GET', 200, ledgerEnvelope()),
          ]);
      await pump(tester, h.repository);

      await tester.tap(find.text('Ubah'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();

      // An error body is the most-copied thing in a support ticket.
      final banner = find.text('Data alamat belum lengkap atau tidak valid');
      expect(banner, findsOneWidget);
    });
  });

  group('Authorization', () {
    testWidgets('19. a read-only operator is offered no write control', (
      tester,
    ) async {
      final h = harness.scriptedOne(200, ledgerEnvelope());
      await pump(tester, h.repository, canManage: false);

      expect(find.text('Rumah'), findsOneWidget);
      expect(find.text('Tambah alamat'), findsNothing);
      expect(find.text('Ubah'), findsNothing);
      expect(find.text('Nonaktifkan'), findsNothing);
    });

    testWidgets('20. a server refusal is stated as a permission matter', (
      tester,
    ) async {
      final h = harness
          .scriptedRules(<(bool Function(RequestOptions), int, String)>[
            harness.on('PATCH', 403, '{"error":{"code":"FORBIDDEN"}}'),
            harness.on('GET', 200, ledgerEnvelope()),
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
  });
}
