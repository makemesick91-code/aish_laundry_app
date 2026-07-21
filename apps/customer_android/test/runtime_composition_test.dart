import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_customer_android/src/app.dart';
import 'package:aish_testing/aish_testing.dart';
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
  appName: 'Uji Customer',
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
          child: const CustomerApp(),
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
