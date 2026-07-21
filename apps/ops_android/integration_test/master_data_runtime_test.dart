import 'dart:math';

import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/master_data/master_data_providers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

/// STEP 4 MASTER DATA, DRIVEN THROUGH THE REAL PRODUCTION COMPOSITION.
///
/// Every other master-data test in this application drives the screens over a
/// SCRIPTED transport with the repository injected by the test. That proves the
/// screens behave correctly against the contract they believe in. It cannot
/// prove that the production graph builds, that the repository reaches a real
/// server, or that the contract is the one the server implements — and the
/// gap between those two things is exactly where `masterDataRepositoryProvider`
/// sat throwing `UnimplementedError` while the suite stayed green.
///
/// So nothing here is injected. The container is built with ONLY the override
/// `main` performs — the validated `Environment` — and everything else is read
/// out of the production graph:
///
///   production main.dart composition
///     -> AuthRuntime -> concrete BackendAuthService
///     -> platform Keystore session
///     -> canonical ApiClient (one instance, shared)
///     -> X-Tenant-Id from SessionCredentials
///     -> running backend
///     -> MasterDataRepository built from that same ApiClient
///
/// Configuration arrives by `--dart-define`; the test runs on the device, where
/// the host environment does not exist. No credential is committed: the seeder
/// emits a fresh random password per run.
const String kBaseUrl = String.fromEnvironment(
  'AISH_E2E_BASE_URL',
  defaultValue: 'http://127.0.0.1:8000/api/v1',
);
const String kIdentifier = String.fromEnvironment('AISH_E2E_IDENTIFIER');
const String kPassword = String.fromEnvironment('AISH_E2E_PASSWORD');
const String kTenantId = String.fromEnvironment('AISH_E2E_TENANT_ID');
const String kForeignTenantId = String.fromEnvironment(
  'AISH_E2E_FOREIGN_TENANT_ID',
);

/// A second, independently authenticated identity in the FOREIGN tenant, used
/// to obtain genuinely cross-tenant identifiers rather than guessed ones.
const String kForeignIdentifier = String.fromEnvironment(
  'AISH_E2E_FOREIGN_IDENTIFIER',
);
const String kForeignPassword = String.fromEnvironment(
  'AISH_E2E_FOREIGN_PASSWORD',
);

/// A deliberately LOW-PRIVILEGE identity — an outlet manager, not an owner.
///
/// Needed because a tenant owner granting `tenantOwner` is not an escalation at
/// all: the first run of this suite reported "ALLOWED-for-this-actor" and that
/// result proved nothing. An escalation negative requires an actor who genuinely
/// lacks the authority.
const String kLowPrivIdentifier = String.fromEnvironment(
  'AISH_E2E_LOWPRIV_IDENTIFIER',
);
const String kLowPrivPassword = String.fromEnvironment(
  'AISH_E2E_LOWPRIV_PASSWORD',
);
const String kLowPrivTenantId = String.fromEnvironment(
  'AISH_E2E_LOWPRIV_TENANT_ID',
);

/// A deterministic, recognisably fabricated fixture (Rule 45).
///
/// The shape `0811-0000-xxxx` is a placeholder pattern, not a plausible
/// Indonesian mobile number, and the name says so in words. A reader who finds
/// this in a log must not be able to mistake it for a real customer.
final int _runSuffix = Random().nextInt(9000) + 1000;
String get fixtureName => 'UJI FIKTIF Pelanggan $_runSuffix';
String get fixturePhone => '081100000$_runSuffix';

Environment environment() => Environment.validate(
  environmentName: 'development',
  apiBaseUrl: kBaseUrl,
  appName: 'Uji Ops Master Data',
).valueOrNull!;

/// A container wired exactly as `main` wires one: environment only.
ProviderContainer productionContainer() {
  final container = ProviderContainer(
    overrides: [environmentProvider.overrideWithValue(environment())],
  );
  addTearDown(container.dispose);
  return container;
}

/// Sign in and select the tenant, entirely through the production graph.
Future<ProviderContainer> authenticatedContainer({
  String identifier = kIdentifier,
  String password = kPassword,
  String tenantId = kTenantId,
}) async {
  final container = productionContainer();
  final auth = container.read(authServiceProvider);

  final signedIn = await auth.signIn(
    identifier: identifier,
    password: password,
  );
  expect(
    signedIn.isAuthenticated,
    isTrue,
    reason: 'production composition could not sign in against the backend',
  );

  final selected = await auth.selectTenant(tenantId);
  expect(
    selected.session!.hasTenantContext,
    isTrue,
    reason: 'tenant context was not established',
  );

  // The tenant identifier now travels on every request from the CANONICAL
  // runtime state, not from a test override.
  expect(
    container.read(authRuntimeProvider).credentials.context().tenantId,
    tenantId,
  );

  return container;
}

/// Assert a fixture precondition, failing the SUITE rather than skipping.
///
/// Three results from the first run of this file were reported as green when
/// they had proven nothing: an empty catalogue, a cross-tenant assertion that
/// skipped because no foreign customer existed, and an escalation attempt made
/// by an actor who was legitimately allowed to perform it.
///
/// The lesson is that a mandatory adversarial scenario must never degrade to a
/// SKIP or to a vacuous pass. If its precondition cannot be constructed, that is
/// a failure of the verification, and it is reported as one.
void requireFixture(bool condition, String what) {
  expect(
    condition,
    isTrue,
    reason:
        'MANDATORY FIXTURE COULD NOT BE CONSTRUCTED: $what. This scenario is '
        'not optional; it fails rather than skipping, because a skipped '
        'adversarial assertion reads as a pass.',
  );
}

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  group('PHASE B — authenticated Ops master-data flow, real backend', () {
    testWidgets('the production graph reaches Step 4 master data', (_) async {
      final container = await authenticatedContainer();

      // Built from the production provider — the one that used to throw.
      final repository = container.read(masterDataRepositoryProvider);
      expect(repository, isA<MasterDataRepository>());
      // And over the SAME client the session authenticated with.
      expect(
        identical(
          container.read(apiClientProvider),
          container.read(authRuntimeProvider).client,
        ),
        isTrue,
      );
      debugPrint('OPS-FLOW: production-composition=ok');

      // 3. outlet context
      final outlets = await container
          .read(authServiceProvider)
          .authorizedOutlets();
      expect(outlets.isOk, isTrue, reason: 'tenant-scoped outlets refused');
      expect(outlets.valueOrNull, isNotEmpty);
      final outletId = outlets.valueOrNull!.first.id;
      await container.read(authServiceProvider).selectOutlet(outletId);
      debugPrint('OPS-FLOW: outlet-context=ok');

      // 4. customer search
      final searched = await repository.customers(query: 'a');
      expect(searched.isOk, isTrue, reason: 'customer search refused');
      debugPrint('OPS-FLOW: customer-search=ok');

      // 5. authorized customer WRITE
      final created = await repository.createCustomer(
        name: fixtureName,
        phone: fixturePhone,
      );
      expect(created.isOk, isTrue, reason: 'customer write refused');
      final createdId = created.valueOrNull!.summary.id;
      debugPrint('OPS-FLOW: customer-write=ok');

      // 6. SERVER-CONFIRMED RELOAD. The write is not believed because the
      //    client said so; it is re-read from the server on a fresh request.
      final reloaded = await repository.customer(createdId);
      expect(reloaded.isOk, isTrue);
      expect(reloaded.valueOrNull!.summary.name, fixtureName);
      expect(reloaded.valueOrNull!.summary.version, isNotNull);
      debugPrint('OPS-FLOW: server-confirmed-reload=ok');

      // 7/8. detail and addresses render from the reloaded record
      expect(reloaded.valueOrNull!.summary.id, createdId);
      debugPrint('OPS-FLOW: customer-detail=ok');

      // 9. consent ledger — append-only, displayed
      final consents = await repository.consents(createdId);
      expect(consents.isOk, isTrue, reason: 'consent ledger refused');
      debugPrint('OPS-FLOW: consent-ledger=ok');

      // 10/11/12. catalogue, packages/add-ons, exact-Rupiah prices.
      //
      // A catalogue READ against an empty catalogue proves only that the
      // endpoint answers. The first run of this suite reported
      // `services=0 packages=0 addons=0`, which is not evidence that the
      // catalogue works. So the service is CREATED here and read back.
      final createdService = await repository.createService(
        code: 'UJI-FIKTIF-$_runSuffix',
        name: 'Cuci Uji Fiktif $_runSuffix',
        unitKind: ServiceUnitKind.kiloan,
      );
      expect(createdService.isOk, isTrue, reason: 'catalogue write refused');
      debugPrint('OPS-FLOW: catalogue-write=ok');

      final services = await repository.services();
      expect(services.isOk, isTrue, reason: 'catalogue refused');
      // Fail-closed: an EMPTY catalogue must never again read as success.
      requireFixture(
        services.valueOrNull!.isNotEmpty,
        'the service catalogue is empty, so a read proves only that the '
        'endpoint answers',
      );
      expect(
        services.valueOrNull!.any(
          (CatalogService s) => s.code == 'UJI-FIKTIF-$_runSuffix',
        ),
        isTrue,
        reason: 'the created service did not come back from the server',
      );
      final packages = await repository.packages();
      expect(packages.isOk, isTrue);
      final addons = await repository.addons();
      expect(addons.isOk, isTrue);
      final priceLists = await repository.priceLists();
      expect(priceLists.isOk, isTrue, reason: 'price lists refused');
      debugPrint(
        'OPS-FLOW: catalogue=ok services=${services.valueOrNull!.length} '
        'packages=${packages.valueOrNull!.length} '
        'addons=${addons.valueOrNull!.length} '
        'price-lists=${priceLists.valueOrNull!.length}',
      );

      // 13. outlet detail
      final outletData = await repository.outletMasterData(outletId);
      expect(outletData.isOk, isTrue, reason: 'outlet master data refused');
      expect(outletData.valueOrNull!.version, isNotNull);
      debugPrint('OPS-FLOW: outlet-detail=ok');

      // 15. staff roster
      final staff = await repository.staff();
      expect(staff.isOk, isTrue, reason: 'staff roster refused');
      debugPrint(
        'OPS-FLOW: staff-roster=ok count=${staff.valueOrNull!.length}',
      );

      // The session survived the whole flow.
      expect(
        container.read(authServiceProvider).current.isAuthenticated,
        isTrue,
      );
      debugPrint('OPS-FLOW: session-intact=ok');
    });
  });

  group('PHASE C — two-client version conflict, real backend', () {
    testWidgets('a stale write is refused and nothing is overwritten', (
      _,
    ) async {
      // TWO INDEPENDENT CLIENTS. Separate containers mean separate AuthRuntimes,
      // separate SessionCredentials and separate tokens — two real sessions,
      // not one client pretending to be two.
      final clientA = await authenticatedContainer();
      final clientB = await authenticatedContainer();

      final repoA = clientA.read(masterDataRepositoryProvider);
      final repoB = clientB.read(masterDataRepositoryProvider);

      final outlets = await clientA
          .read(authServiceProvider)
          .authorizedOutlets();
      final outletId = outlets.valueOrNull!.first.id;

      // 1/2. both load the same record and observe the SAME version
      final loadA = await repoA.outletMasterData(outletId);
      final loadB = await repoB.outletMasterData(outletId);
      expect(loadA.isOk, isTrue);
      expect(loadB.isOk, isTrue);
      final sharedVersion = loadA.valueOrNull!.version;
      expect(loadB.valueOrNull!.version, sharedVersion);
      debugPrint('CONFLICT: initial-version=$sharedVersion');

      // 3/4. client A updates successfully; the server increments the version
      final updateA = await repoA.updateOutletMasterData(
        outletId: outletId,
        expectedVersion: sharedVersion,
        changes: <String, Object?>{'name': 'Outlet Uji Fiktif A'},
      );
      expect(updateA.isOk, isTrue, reason: 'client A update was refused');
      final newVersion = updateA.valueOrNull!.version;
      expect(newVersion, isNot(sharedVersion));
      debugPrint('CONFLICT: A-updated new-version=$newVersion');

      // 5/6/7. client B submits the STALE version and is refused with 409,
      //        mapped to staleWrite by the shared error taxonomy.
      final updateB = await repoB.updateOutletMasterData(
        outletId: outletId,
        expectedVersion: sharedVersion,
        changes: <String, Object?>{'name': 'Outlet Uji Fiktif B'},
      );
      expect(updateB.isErr, isTrue, reason: 'a stale write was ACCEPTED');
      final failure = updateB.failureOrNull!;
      expect(failure.code, 'CONFLICT');
      expect(
        ApiErrorMapper.consequenceOf(failure),
        ClientErrorConsequence.staleWrite,
      );
      debugPrint('CONFLICT: B-refused code=${failure.code} -> staleWrite');

      // 8. a stale write is NOT retryable — the client must not offer a plain
      //    "coba lagi", because retrying resends the same payload and destroys
      //    the edit that caused the conflict.
      expect(failure.isRetryable, isFalse);
      debugPrint('CONFLICT: retryable=false');

      // 6/7 (session). The session survives; the credential is untouched.
      expect(clientB.read(authServiceProvider).current.isAuthenticated, isTrue);
      expect(
        await clientB.read(authRuntimeProvider).credentials.token(),
        isNotNull,
      );
      debugPrint('CONFLICT: session-intact=true credential-retained=true');

      // 10. client A's value was not overwritten
      final afterConflict = await repoA.outletMasterData(outletId);
      expect(afterConflict.valueOrNull!.name, 'Outlet Uji Fiktif A');
      debugPrint('CONFLICT: A-value-preserved=true');

      // 11/14. reload retrieves the canonical server state and the NEW version
      final reloadB = await repoB.outletMasterData(outletId);
      expect(reloadB.valueOrNull!.version, newVersion);
      expect(reloadB.valueOrNull!.name, 'Outlet Uji Fiktif A');
      debugPrint('CONFLICT: B-reload-canonical=true version=$newVersion');

      // 15. a deliberate new edit succeeds ONLY with the new version
      final retryWithNew = await repoB.updateOutletMasterData(
        outletId: outletId,
        expectedVersion: newVersion,
        changes: <String, Object?>{'name': 'Outlet Uji Fiktif B'},
      );
      expect(retryWithNew.isOk, isTrue, reason: 'a fresh-version edit failed');
      debugPrint('CONFLICT: B-succeeds-with-new-version=true');
    });
  });

  group('PHASE D — authorization, real backend', () {
    testWidgets('an unauthenticated client reaches no Step 4 surface', (
      _,
    ) async {
      // Production graph, never signed in.
      final container = productionContainer();
      final repository = container.read(masterDataRepositoryProvider);

      final customers = await repository.customers();
      expect(customers.isErr, isTrue, reason: 'unauthenticated read allowed');
      final staff = await repository.staff();
      expect(staff.isErr, isTrue);
      debugPrint('AUTHZ: unauthenticated-refused=true');
    });

    testWidgets('a cross-tenant identifier is refused by the real server', (
      _,
    ) async {
      // Obtain a genuinely foreign identifier by authenticating as a member of
      // the OTHER tenant, rather than guessing a UUID that may not exist. A
      // guessed identifier proves only that the server rejects nonsense.
      final foreign = await authenticatedContainer(
        identifier: kForeignIdentifier,
        password: kForeignPassword,
        tenantId: kForeignTenantId,
      );
      // CREATE the target rather than hoping one exists. The first run of this
      // suite skipped here because the foreign tenant had no customer, and a
      // skip is not a pass — it left the most important isolation assertion in
      // this file unproven.
      final foreignCreated = await foreign
          .read(masterDataRepositoryProvider)
          .createCustomer(
            name: 'UJI FIKTIF Seberang $_runSuffix',
            phone: '081100001$_runSuffix',
          );
      requireFixture(
        foreignCreated.isOk,
        'a customer in the FOREIGN tenant, without which the cross-tenant '
        'isolation assertion cannot execute at all',
      );
      final foreignCustomerId = foreignCreated.valueOrNull!.summary.id;

      // Now ask for it as the PRIMARY tenant's user.
      final own = await authenticatedContainer();
      final stolen = await own
          .read(masterDataRepositoryProvider)
          .customer(foreignCustomerId);

      expect(stolen.isErr, isTrue, reason: 'CROSS-TENANT READ SUCCEEDED');
      // Denial and absence are indistinguishable across a tenant boundary.
      expect(
        ApiErrorMapper.consequenceOf(stolen.failureOrNull!),
        ClientErrorConsequence.accessDenied,
      );
      debugPrint(
        'AUTHZ: cross-tenant-customer refused code='
        '${stolen.failureOrNull!.code}',
      );
    });

    testWidgets('a cross-tenant outlet identifier is refused', (_) async {
      final foreign = await authenticatedContainer(
        identifier: kForeignIdentifier,
        password: kForeignPassword,
        tenantId: kForeignTenantId,
      );
      final foreignOutlets = await foreign
          .read(authServiceProvider)
          .authorizedOutlets();
      expect(foreignOutlets.isOk, isTrue);
      final foreignOutletId = foreignOutlets.valueOrNull!.first.id;

      final own = await authenticatedContainer();
      final stolen = await own
          .read(masterDataRepositoryProvider)
          .outletMasterData(foreignOutletId);

      expect(
        stolen.isErr,
        isTrue,
        reason: 'CROSS-TENANT OUTLET READ SUCCEEDED',
      );
      debugPrint(
        'AUTHZ: cross-tenant-outlet refused code=${stolen.failureOrNull!.code}',
      );
    });

    testWidgets('a role above the caller\'s authority is refused', (_) async {
      // An OUTLET MANAGER, not an owner. The distinction is the whole test.
      final container = await authenticatedContainer(
        identifier: kLowPrivIdentifier,
        password: kLowPrivPassword,
        tenantId: kLowPrivTenantId,
      );
      final repository = container.read(masterDataRepositoryProvider);

      final staff = await repository.staff();
      expect(staff.isOk, isTrue, reason: 'roster refused for outlet manager');
      requireFixture(
        staff.valueOrNull!.isNotEmpty,
        'a staff roster for the low-privilege actor to target',
      );

      // Fail-closed on the ACTOR, not just the outcome. The first run of this
      // scenario passed while proving nothing, because the actor was a tenant
      // owner who may legitimately grant tenantOwner. If the seeded roles ever
      // change so that this identity becomes an owner or admin again, this
      // assertion fails instead of the test quietly going vacuous.
      final session = container.read(authServiceProvider).current.session!;
      final actorRoles = session.activeMembership!.roles
          .map((Role r) => r.slug)
          .toList();
      requireFixture(
        !actorRoles.contains(Role.tenantOwner) &&
            !actorRoles.contains(Role.tenantAdmin),
        'a LOW-PRIVILEGE actor. This identity holds $actorRoles, which may '
        'legitimately grant tenantOwner, so the attempt would not be an '
        'escalation',
      );
      debugPrint('AUTHZ: escalation-actor-roles=$actorRoles');

      // Ask the server to grant the highest tenant authority.
      final escalate = await repository.assignRole(
        membershipId: staff.valueOrNull!.first.membershipId,
        role: TenantRole.tenantOwner,
      );

      expect(
        escalate.isErr,
        isTrue,
        reason: 'AN OUTLET MANAGER GRANTED tenantOwner — privilege escalation',
      );
      debugPrint(
        'AUTHZ: role-escalation refused code=${escalate.failureOrNull!.code}',
      );

      // The refusal is an authorization decision, not a transport accident.
      expect(
        ApiErrorMapper.consequenceOf(escalate.failureOrNull!),
        anyOf(
          ClientErrorConsequence.accessDenied,
          ClientErrorConsequence.contextAccessDenied,
        ),
      );
      // And it does not end the session.
      expect(
        container.read(authServiceProvider).current.isAuthenticated,
        isTrue,
      );
      debugPrint('AUTHZ: session-survives-refusal=true');
    });
  });
}
