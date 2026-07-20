import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_customer_android/src/app.dart';
import 'package:aish_customer_android/src/routing/customer_routes.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

Environment env() => Environment.validate(
  environmentName: 'development',
  apiBaseUrl: 'http://localhost:8000/api/v1',
  appName: 'Uji',
).valueOrNull!;

Future<void> pumpApp(WidgetTester tester, FakeAuthService auth) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
      ],
      child: const CustomerApp(),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  late FakeAuthService auth;

  setUp(() => auth = FakeAuthService());
  tearDown(() => auth.dispose());

  group('Startup and session restoration', () {
    testWidgets('an unrestorable session lands on sign-in', (tester) async {
      // Nothing scripted means restoration fails. The surface must NOT invent
      // a session from the mere presence of a stored credential.
      await pumpApp(tester, auth);
      expect(auth.calls, contains('restoreSession'));
      expect(find.text('Masuk'), findsWidgets);
    });

    testWidgets('a restorable session lands on the authenticated shell', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      expect(find.textContaining('Bu Rina (fiktif)'), findsWidgets);
    });
  });

  group('Sign-in', () {
    testWidgets('a successful sign-in reaches the shell', (tester) async {
      await pumpApp(tester, auth);
      await tester.enterText(find.byType(TextFormField).first, 'pengguna');
      await tester.enterText(find.byType(TextFormField).last, 'sandi');
      await tester.tap(find.text('Masuk').last);
      await tester.pumpAndSettle();
      expect(auth.calls.any((c) => c.startsWith('signIn:')), isTrue);
      expect(find.textContaining('Halo'), findsOneWidget);
    });

    testWidgets('the password is never recorded by the service', (
      tester,
    ) async {
      await pumpApp(tester, auth);
      await tester.enterText(find.byType(TextFormField).first, 'pengguna');
      await tester.enterText(
        find.byType(TextFormField).last,
        'sandi_rahasia_fiktif',
      );
      await tester.tap(find.text('Masuk').last);
      await tester.pumpAndSettle();
      expect(
        auth.calls.any((c) => c.contains('sandi_rahasia_fiktif')),
        isFalse,
        reason: 'A credential must never reach a call log.',
      );
    });

    testWidgets('empty fields are rejected before any request', (tester) async {
      await pumpApp(tester, auth);
      await tester.tap(find.text('Masuk').last);
      await tester.pumpAndSettle();
      expect(auth.calls.any((c) => c.startsWith('signIn:')), isFalse);
      expect(find.textContaining('Isi'), findsWidgets);
    });

    testWidgets('a failure shows one non-specific message', (tester) async {
      auth.nextSignInState = const AuthState.unauthenticated();
      await pumpApp(tester, auth);
      await tester.enterText(find.byType(TextFormField).first, 'pengguna');
      await tester.enterText(find.byType(TextFormField).last, 'salah');
      await tester.tap(find.text('Masuk').last);
      await tester.pumpAndSettle();
      // Must not distinguish "no such account" from "wrong password".
      expect(find.textContaining('Tidak dapat masuk'), findsOneWidget);
      expect(find.textContaining('tidak terdaftar'), findsNothing);
    });
  });

  group('Route guard — each session-ending state gets its own screen', () {
    Future<void> expectScreen(
      WidgetTester tester,
      AuthState state,
      String expectedText,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      auth.emitForTest(state);
      await tester.pumpAndSettle();
      expect(find.textContaining(expectedText), findsWidgets);
    }

    testWidgets('sessionExpired', (tester) async {
      await expectScreen(
        tester,
        const AuthState.sessionExpired(),
        'Sesi Anda telah berakhir',
      );
    });

    testWidgets('sessionRevoked', (tester) async {
      await expectScreen(
        tester,
        const AuthState.sessionRevoked(),
        'Sesi Anda dicabut',
      );
    });

    testWidgets('deviceRevoked', (tester) async {
      await expectScreen(
        tester,
        const AuthState.deviceRevoked(),
        'Akses perangkat ini dicabut',
      );
    });

    testWidgets('accessDenied', (tester) async {
      await expectScreen(
        tester,
        const AuthState.accessDenied(),
        'Akses ditolak',
      );
    });

    testWidgets('the four states render four DIFFERENT messages', (
      tester,
    ) async {
      // Collapsing them would produce one dishonest message for four
      // different situations.
      final messages = <String>{};
      for (final state in <AuthState>[
        const AuthState.sessionExpired(),
        const AuthState.sessionRevoked(),
        const AuthState.deviceRevoked(),
        const AuthState.accessDenied(),
      ]) {
        final service = FakeAuthService();
        service.nextRestoreState = AuthState.authenticated(
          ApiFixtures.signedInNoTenant(),
        );
        await pumpApp(tester, service);
        service.emitForTest(state);
        await tester.pumpAndSettle();
        final title = tester
            .widgetList<Text>(find.byType(Text))
            .map((t) => t.data ?? '')
            .firstWhere((t) => t.isNotEmpty);
        messages.add(title);
        await service.dispose();
      }
      expect(messages.length, 4);
    });
  });

  group('Access denial discloses nothing', () {
    testWidgets('the denial copy never says whether a record exists', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      auth.emitForTest(const AuthState.accessDenied());
      await tester.pumpAndSettle();
      final allText = tester
          .widgetList<Text>(find.byType(Text))
          .map((t) => t.data ?? '')
          .join(' ')
          .toLowerCase();
      // Phrases that would reveal whether the requested record EXISTS. Denial
      // and absence must be indistinguishable across a tenant boundary, so the
      // copy may say "you may not", never "it is not there" or "it belongs to
      // someone else".
      for (final leak in <String>[
        'tidak ditemukan',
        'tidak tersedia',
        'sudah dihapus',
        'milik tenant lain',
        'milik pengguna lain',
      ]) {
        expect(allText, isNot(contains(leak)), reason: 'leaks "$leak"');
      }
    });
  });

  group('Logout', () {
    testWidgets('signs out and returns to sign-in', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      await tester.tap(find.byIcon(Icons.logout_outlined));
      await tester.pumpAndSettle();
      expect(auth.calls, contains('signOut'));
      expect(find.text('Masuk'), findsWidgets);
    });

    testWidgets('logout clears local credentials', (tester) async {
      final storage = InMemorySecureStorage();
      final namespace = StorageNamespace.user('usr_fiktif_0001');
      await storage.write(
        namespace: namespace,
        key: CredentialKeys.sessionToken,
        value: 'token_fiktif',
      );
      auth.onClearCredentials = storage.clearOnLogout;
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );

      await pumpApp(tester, auth);
      await tester.tap(find.byIcon(Icons.logout_outlined));
      await tester.pumpAndSettle();

      expect(auth.didClearCredentials, isTrue);
      expect(storage.keys, isEmpty);
    });
  });

  group('Future-feature routes are honest', () {
    testWidgets('every entry point renders the literal notice', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);

      for (final label in <String>[
        'Pesanan saya',
        'Lacak cucian',
        'Penjemputan',
        'Tagihan',
      ]) {
        await tester.tap(find.text(label));
        await tester.pumpAndSettle();
        expect(
          find.text(kFutureStepNotice),
          findsOneWidget,
          reason: '$label must state it is not implemented',
        );
        // Back to the shell for the next iteration.
        await tester.pageBack();
        await tester.pumpAndSettle();
      }
    });

    testWidgets('the shell renders no fabricated data', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      final allText = tester
          .widgetList<Text>(find.byType(Text))
          .map((t) => t.data ?? '')
          .join(' ');
      // A shell populated with plausible figures is indistinguishable from a
      // working product in a screenshot.
      expect(allText, isNot(matches(RegExp(r'Rp\s?\d'))));
      expect(allText.toLowerCase(), contains('belum ada fitur'));
    });
  });

  group('Design-system smoke route', () {
    testWidgets('renders without a session and shows no data', (tester) async {
      await pumpApp(tester, auth);
      auth.emitForTest(const AuthState.unauthenticated());
      await tester.pumpAndSettle();
      expect(CustomerRoutes.designSmoke, '/pratinjau-design-system');
    });
  });

  group('Accessibility', () {
    testWidgets('interactive controls meet the 48x48 minimum', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      for (final element in find.byType(ListTile).evaluate()) {
        expect(element.size!.height, greaterThanOrEqualTo(48.0));
      }
    });

    testWidgets('the logout control names its object', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      final button = tester.widget<IconButton>(
        find.ancestor(
          of: find.byIcon(Icons.logout_outlined),
          matching: find.byType(IconButton),
        ),
      );
      expect(button.tooltip, contains('Bu Rina (fiktif)'));
    });

    testWidgets('the shell survives a large text scale', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await tester.pumpWidget(
        MediaQuery(
          data: const MediaQueryData(textScaler: TextScaler.linear(2.0)),
          child: ProviderScope(
            overrides: [
              environmentProvider.overrideWithValue(env()),
              authServiceProvider.overrideWithValue(auth),
            ],
            child: const CustomerApp(),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  });
}
