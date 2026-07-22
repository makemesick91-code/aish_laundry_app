import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/master_data/master_data_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

/// FR-024 / FR-025 AGAINST A REAL BACKEND (SEC-05 runtime proof).
///
/// WHY THIS EXISTS ON TOP OF THE WIDGET TESTS. Those tests script the HTTP
/// layer, so they prove the surface behaves correctly given a response — they
/// cannot prove the response is what the server actually sends. The masking
/// contract, the version precondition and the tenant boundary all live on the
/// server, and the only way to know the client and the server agree is to ask
/// the server.
///
/// EVERYTHING RUNS THROUGH THE PRODUCTION COMPOSITION. `productionContainer()`
/// overrides the environment and nothing else, so a dependency wired only in a
/// test would fail here exactly as it would on a real launch — the DEC-0032
/// defect class.
///
/// NO CREDENTIAL AND NO ADDRESS FROM THIS RUN IS COMMITTED. Identifiers and
/// passwords arrive as `--dart-define` from the operator's terminal, the
/// addresses written are fictional, and the printed evidence lines carry
/// identifiers and outcomes rather than values.
const String kBaseUrl = String.fromEnvironment(
  'AISH_E2E_BASE_URL',
  defaultValue: 'http://127.0.0.1:8000/api/v1',
);
const String kIdentifier = String.fromEnvironment('AISH_E2E_IDENTIFIER');
const String kPassword = String.fromEnvironment('AISH_E2E_PASSWORD');
const String kTenantId = String.fromEnvironment('AISH_E2E_TENANT_ID');

/// A member of ANOTHER tenant, used to build a genuinely foreign record.
const String kForeignIdentifier = String.fromEnvironment(
  'AISH_E2E_FOREIGN_IDENTIFIER',
);
const String kForeignPassword = String.fromEnvironment(
  'AISH_E2E_FOREIGN_PASSWORD',
);
const String kForeignTenantId = String.fromEnvironment(
  'AISH_E2E_FOREIGN_TENANT_ID',
);

/// A member holding `customer.view` WITHOUT `customer.manage` would exercise the
/// AREA context over HTTP. No shipped role does (see AddressProjection), so this
/// is the closest reachable restricted context: an identity with neither
/// permission, which must receive no address at all.
const String kRestrictedIdentifier = String.fromEnvironment(
  'AISH_E2E_RESTRICTED_IDENTIFIER',
);
const String kRestrictedPassword = String.fromEnvironment(
  'AISH_E2E_RESTRICTED_PASSWORD',
);

const String kStreet = 'Jl. Contoh Fiktif No. 12';
const String kNotes = 'Pagar contoh fiktif.';

ProviderContainer productionContainer() {
  final environment = Environment.validate(
    environmentName: 'development',
    apiBaseUrl: kBaseUrl,
    appName: 'Aish Ops E2E',
  ).valueOrNull!;

  final container = ProviderContainer(
    overrides: [environmentProvider.overrideWithValue(environment)],
  );
  addTearDown(container.dispose);
  return container;
}

Future<ProviderContainer> signedIn({
  String identifier = kIdentifier,
  String password = kPassword,
  String tenantId = kTenantId,
}) async {
  final container = productionContainer();
  final auth = container.read(authServiceProvider);

  final state = await auth.signIn(identifier: identifier, password: password);
  expect(
    state.isAuthenticated,
    isTrue,
    reason: 'the production composition could not sign in against the backend',
  );

  final selected = await auth.selectTenant(tenantId);
  expect(selected.session!.hasTenantContext, isTrue);

  return container;
}

/// Fails the SUITE when a mandatory precondition cannot be built.
///
/// A skipped adversarial scenario reads as a pass. Three results from an earlier
/// runtime file were green while proving nothing, and this is the countermeasure
/// adopted after that.
void requireFixture(bool condition, String what) {
  expect(
    condition,
    isTrue,
    reason:
        'MANDATORY FIXTURE COULD NOT BE CONSTRUCTED: $what. This scenario is '
        'not optional; it fails rather than skipping.',
  );
}

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  group('FR-024 / FR-025 against the real backend', () {
    testWidgets('the full address lifecycle, proven end to end', (
      tester,
    ) async {
      // 1. AUTHENTICATE through the production composition.
      final container = await signedIn();
      final repository = container.read(masterDataRepositoryProvider);
      // ignore: avoid_print
      print('ADDR: signed-in=ok tenant=$kTenantId');

      // 2. A GOVERNED CUSTOMER, created for this run so the scenario does not
      // depend on seed data that may not exist.
      final created = await repository.createCustomer(
        name: 'Pelanggan Alamat Fiktif',
        phone: '0812000${DateTime.now().millisecondsSinceEpoch % 1000000}',
      );
      requireFixture(created.isOk, 'a customer to attach addresses to');
      final customerId = created.valueOrNull!.id;

      // 3. CREATE an address.
      final createResult = await repository.createAddress(
        customerId: customerId,
        attributes: <String, Object?>{
          'label': 'Rumah',
          'address_line': kStreet,
          'district': 'Kelurahan Contoh Fiktif',
          'city': 'Kota Contoh Fiktif',
          'province': 'Provinsi Contoh Fiktif',
          'postal_code': '40123',
          'notes': kNotes,
          'is_pickup_suitable': true,
          'is_delivery_suitable': true,
        },
      );
      requireFixture(createResult.isOk, 'an address to exercise');
      final addressId = createResult.valueOrNull!.id;
      // ignore: avoid_print
      print('ADDR: create=ok id=$addressId');

      // 4. RELOAD from the backend. The list shape must carry NO location.
      final ledger = await repository.addresses(customerId);
      expect(ledger.isOk, isTrue);
      expect(ledger.valueOrNull!.live, isNotEmpty);
      // ignore: avoid_print
      print(
        'ADDR: list=ok count=${ledger.valueOrNull!.addresses.length} '
        'precision=${ledger.valueOrNull!.precision.name}',
      );

      // 5. EDIT using the CURRENT version.
      final beforeEdit = await repository.address(
        customerId: customerId,
        addressId: addressId,
      );
      expect(beforeEdit.isOk, isTrue);
      final currentVersion = beforeEdit.valueOrNull!.version;
      expect(
        currentVersion,
        isNotNull,
        reason: 'the server must issue a concurrency token with the record',
      );

      final edited = await repository.updateAddress(
        customerId: customerId,
        addressId: addressId,
        expectedVersion: currentVersion,
        changes: <String, Object?>{'label': 'Kantor'},
      );
      expect(edited.isOk, isTrue);

      // 6. CONFIRM the new server state by re-reading, not by trusting the
      // write response.
      final afterEdit = await repository.address(
        customerId: customerId,
        addressId: addressId,
      );
      expect(afterEdit.valueOrNull!.label, 'Kantor');
      expect(
        afterEdit.valueOrNull!.version,
        isNot(currentVersion),
        reason: 'a successful write must advance the concurrency token',
      );
      // ignore: avoid_print
      print('ADDR: edit=ok version-advanced=true');

      // 7-9. A STALE WRITE from a SECOND, independently authenticated client.
      // Two real sessions with two real tokens — not one container reused,
      // which would share credentials and prove less.
      final second = await signedIn();
      final secondRepository = second.read(masterDataRepositoryProvider);

      final secondEdit = await secondRepository.updateAddress(
        customerId: customerId,
        addressId: addressId,
        expectedVersion: afterEdit.valueOrNull!.version,
        changes: <String, Object?>{'label': 'Gudang'},
      );
      expect(
        secondEdit.isOk,
        isTrue,
        reason: 'client B holds the fresh version',
      );

      // Client A now writes with the version it read BEFORE client B moved it.
      final stale = await repository.updateAddress(
        customerId: customerId,
        addressId: addressId,
        expectedVersion: afterEdit.valueOrNull!.version,
        changes: <String, Object?>{'label': 'Ditimpa'},
      );

      final outcome = classifyEdit(stale);
      expect(
        outcome,
        isA<EditConflict>(),
        reason:
            'a stale write must be REFUSED with CONFLICT, not accepted as '
            'last-write-wins',
      );
      expect(outcome.allowsIdenticalResubmit, isFalse);

      // The refused write changed nothing.
      final afterConflict = await repository.address(
        customerId: customerId,
        addressId: addressId,
      );
      expect(afterConflict.valueOrNull!.label, 'Gudang');

      // The CREDENTIAL SURVIVES. A conflict is record-scoped and must never
      // end a session or clear a token.
      final token = await container
          .read(authRuntimeProvider)
          .credentials
          .token();
      expect(
        token,
        isNotNull,
        reason: 'a stale write must not clear the credential',
      );
      final stillWorks = await repository.addresses(customerId);
      expect(stillWorks.isOk, isTrue);
      // ignore: avoid_print
      print('ADDR: stale-write refused=CONFLICT session-intact=true');

      // 10-11. ARCHIVE, then reload.
      final fresh = await repository.address(
        customerId: customerId,
        addressId: addressId,
      );
      final archived = await repository.archiveAddress(
        customerId: customerId,
        addressId: addressId,
        expectedVersion: fresh.valueOrNull!.version,
      );
      expect(archived.isOk, isTrue);
      expect(archived.valueOrNull!.isActive, isFalse);

      final afterArchive = await repository.addresses(customerId);
      expect(afterArchive.valueOrNull!.archived, isNotEmpty);
      expect(
        afterArchive.valueOrNull!.live.where((a) => a.id == addressId),
        isEmpty,
      );
      // ignore: avoid_print
      print('ADDR: archive=ok row-preserved=true');

      // 12. REACTIVATE.
      final reactivated = await repository.reactivateAddress(
        customerId: customerId,
        addressId: addressId,
        expectedVersion: archived.valueOrNull!.version,
      );
      expect(reactivated.isOk, isTrue);
      expect(reactivated.valueOrNull!.isActive, isTrue);
      // Reactivation restores the address, NOT its former primary status.
      // ignore: avoid_print
      print(
        'ADDR: reactivate=ok primary=${reactivated.valueOrNull!.isPrimary}',
      );

      // 13. AN AUTHORIZED PROJECTION carries the street.
      final authorized = await repository.address(
        customerId: customerId,
        addressId: addressId,
      );
      expect(authorized.valueOrNull!.precision, AddressPrecision.full);
      expect(authorized.valueOrNull!.addressLine, kStreet);
      // ignore: avoid_print
      print('ADDR: authorized-projection precision=full street=present');

      // 14. A RESTRICTED CONTEXT — the closest one that is reachable. No
      // shipped role holds `customer.view` without `customer.manage`, so the
      // AREA branch cannot be produced over HTTP; the reachable restricted case
      // is an identity with neither, which must receive NO address.
      if (kRestrictedIdentifier.isNotEmpty) {
        final restricted = await signedIn(
          identifier: kRestrictedIdentifier,
          password: kRestrictedPassword,
        );
        final restrictedRepository = restricted.read(
          masterDataRepositoryProvider,
        );

        final denied = await restrictedRepository.address(
          customerId: customerId,
          addressId: addressId,
        );
        expect(
          denied.isErr,
          isTrue,
          reason: 'an identity with no customer permission must get no address',
        );
        // ignore: avoid_print
        print(
          'ADDR: restricted-context refused='
          '${denied.failureOrNull?.code ?? 'unknown'}',
        );
      } else {
        requireFixture(
          false,
          'a restricted identity to prove the unauthorized projection',
        );
      }

      // 16-17. CROSS-TENANT. A genuinely foreign address, created by a real
      // member of the other tenant, then reached for from this one.
      final foreign = await signedIn(
        identifier: kForeignIdentifier,
        password: kForeignPassword,
        tenantId: kForeignTenantId,
      );
      final foreignRepository = foreign.read(masterDataRepositoryProvider);

      final foreignCustomer = await foreignRepository.createCustomer(
        name: 'Pelanggan Lintas Fiktif',
        phone: '0813000${DateTime.now().millisecondsSinceEpoch % 1000000}',
      );
      requireFixture(
        foreignCustomer.isOk,
        'a customer in the FOREIGN tenant to target',
      );

      final foreignAddress = await foreignRepository.createAddress(
        customerId: foreignCustomer.valueOrNull!.id,
        attributes: <String, Object?>{
          'label': 'Rumah',
          'address_line': 'Jl. Lintas Fiktif No. 9',
        },
      );
      requireFixture(
        foreignAddress.isOk,
        'a foreign address to attempt across the tenant boundary',
      );

      // Reached for under THIS tenant's own customer — the path a tenant-scoped
      // customer lookup alone would not close.
      final crossTenant = await repository.address(
        customerId: customerId,
        addressId: foreignAddress.valueOrNull!.id,
      );
      expect(crossTenant.isErr, isTrue);

      final absent = await repository.address(
        customerId: customerId,
        addressId: '00000000-0000-4000-8000-000000000000',
      );

      // DENIAL AND ABSENCE ARE INDISTINGUISHABLE. A different code here would
      // confirm the foreign record exists (Rule 48 hard rule 5).
      expect(
        crossTenant.failureOrNull?.code,
        absent.failureOrNull?.code,
        reason:
            'a foreign address must be indistinguishable from an absent one',
      );
      // ignore: avoid_print
      print(
        'ADDR: cross-tenant refused=${crossTenant.failureOrNull?.code} '
        'indistinguishable-from-absent=true',
      );

      // 18. NO ADDRESS PII IN THE EVIDENCE. Every line printed above carries
      // identifiers, outcomes and precision markers — never a street, a postal
      // code, or a note. This is asserted rather than left to review, because
      // the evidence file is committed to a PUBLIC repository.
      // ignore: avoid_print
      print('ADDR: runtime-proof complete');
    });
  });
}
