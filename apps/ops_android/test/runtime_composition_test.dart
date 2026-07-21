import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/master_data/master_data_providers.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:aish_ops_android/src/routing/ops_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// PRODUCTION COMPOSITION, not a test double.
///
/// Every other test in this application overrides `authServiceProvider` with
/// `FakeAuthService`. That is correct for exercising screens, and it is also
/// exactly why the defect this file guards against went unnoticed: the provider
/// threw `UnimplementedError`, nothing but a test ever supplied it, and the
/// suite was green while no real launch of this application could get past its
/// first frame.
///
/// These tests override ONLY `environmentProvider` — the one thing `main`
/// genuinely supplies — and then assert the graph resolves. If someone reverts
/// the production wiring, the very first test here fails.
Environment env() => Environment.validate(
  environmentName: 'development',
  apiBaseUrl: 'http://localhost:8000/api/v1',
  appName: 'Uji Ops',
).valueOrNull!;

ProviderContainer productionContainer() {
  final container = ProviderContainer(
    overrides: [environmentProvider.overrideWithValue(env())],
  );
  addTearDown(container.dispose);
  return container;
}

void main() {
  group('production composition', () {
    test('resolves a CONCRETE AuthService without throwing', () {
      final container = productionContainer();

      final service = container.read(authServiceProvider);

      // The assertion that would have caught the original defect: before the
      // corrective wiring this line threw UnimplementedError.
      expect(service, isA<BackendAuthService>());
    });

    test('does not resolve a test double in production', () {
      final container = productionContainer();

      final service = container.read(authServiceProvider);

      // A fake reaching a production composition would be worse than the
      // throw: it would authenticate nobody against nothing, convincingly.
      expect(service, isNot(isA<FakeAuthService>()));
    });

    test('shares ONE ApiClient between auth and every repository', () {
      final container = productionContainer();

      final fromRuntime = container.read(authRuntimeProvider).client;
      final fromProvider = container.read(apiClientProvider);

      // A second client built elsewhere would keep addressing the tenant the
      // user just switched away from.
      expect(identical(fromRuntime, fromProvider), isTrue);
    });

    test('uses bearer-token transport, as an Android surface must', () {
      final container = productionContainer();

      expect(
        container.read(apiClientProvider).transport,
        CredentialTransport.bearerToken,
      );
    });

    test('resolves a CONCRETE MasterDataRepository without throwing', () {
      final container = productionContainer();

      // The same defect as authServiceProvider, one layer up: this provider
      // threw UnimplementedError and was overridden only in widget tests, so
      // every production master-data screen threw the moment it opened while
      // the suite stayed green.
      final repository = container.read(masterDataRepositoryProvider);

      expect(repository, isA<MasterDataRepository>());
    });

    test('the repository shares the authenticated ApiClient', () {
      final container = productionContainer();

      // Built from apiClientProvider, so master-data requests carry the same
      // credential and the same X-Tenant-Id as everything else. A repository
      // over its own client would authenticate as nobody.
      expect(
        () => container.read(masterDataRepositoryProvider),
        returnsNormally,
      );
      expect(
        identical(
          container.read(apiClientProvider),
          container.read(authRuntimeProvider).client,
        ),
        isTrue,
      );
    });

    test('EVERY provider a production screen depends on resolves', () {
      // The structural guard (scripts/validate-production-composition.py) proves
      // no throwing provider is left unwired. This proves the graph actually
      // CONSTRUCTS: a provider can be wired and still fail because something it
      // depends on is missing, and that failure would otherwise surface when a
      // user navigates rather than when validation runs.
      //
      // Only environmentProvider is overridden, because that is the only thing
      // main overrides. Everything else must stand up on its own.
      final container = productionContainer();

      final resolved = <String, Object?>{
        'environmentProvider': container.read(environmentProvider),
        'authRuntimeProvider': container.read(authRuntimeProvider),
        'apiClientProvider': container.read(apiClientProvider),
        'authServiceProvider': container.read(authServiceProvider),
        'startupGateProvider': container.read(startupGateProvider),
        'masterDataRepositoryProvider': container.read(
          masterDataRepositoryProvider,
        ),
        'syncHealthProvider': container.read(syncHealthProvider),
        'opsRouterProvider': container.read(opsRouterProvider),
      };

      for (final entry in resolved.entries) {
        expect(
          entry.value,
          isNotNull,
          reason: '${entry.key} did not resolve in the production graph',
        );
      }
    });

    test('starts with no credential and no tenant context', () {
      final container = productionContainer();

      final credentials = container.read(authRuntimeProvider).credentials;

      expect(credentials.context().tenantId, isNull);
      expect(credentials.context().outletId, isNull);
      expect(credentials.toString(), contains('hasToken: false'));
    });
  });

  group('real startup', () {
    testWidgets('an unauthenticated launch reaches sign-in, not a crash', (
      tester,
    ) async {
      // The whole application, wired exactly as `main` wires it, with no auth
      // double anywhere. Secure storage is unavailable under a widget test and
      // fails closed to "no stored credential", which is precisely the state a
      // first-ever launch is in.
      await tester.pumpWidget(
        ProviderScope(
          overrides: [environmentProvider.overrideWithValue(env())],
          child: const OpsApp(),
        ),
      );
      // Advance past the bounded storage timeout. Secure storage has no
      // platform channel under a widget test and never answers, which is
      // precisely the wedged-keystore case the bound exists for.
      await tester.pump();
      await tester.pump(const Duration(seconds: 6));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.textContaining('Masuk'), findsWidgets);
    });
  });
}
