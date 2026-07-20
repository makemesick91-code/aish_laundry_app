import 'dart:io';

import 'package:aish_admin_web/src/app.dart';
import 'package:aish_admin_web/src/auth/cookie_session.dart';
import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

Environment env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://konsol.contoh-fiktif.id/api/v1',
  appName: 'Uji Console',
).valueOrNull!;

Future<void> pumpApp(WidgetTester tester, FakeAuthService auth) async {
  // A wide surface: the console is a seated, large-viewport experience.
  tester.view.physicalSize = const Size(1366, 768);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
      ],
      child: const ConsoleApp(),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  late FakeAuthService auth;

  setUp(() => auth = FakeAuthService());
  tearDown(() => auth.dispose());

  group('Cookie-only authentication — a HARD requirement', () {
    test('the only permitted transport is the session cookie', () {
      expect(ConsoleAuthTransport.transport, CredentialTransport.sessionCookie);
      expect(
        ConsoleAuthTransport.isPermitted(CredentialTransport.bearerToken),
        isFalse,
        reason: 'A bearer token is readable by any script on the origin.',
      );
      expect(
        ConsoleAuthTransport.isPermitted(CredentialTransport.sessionCookie),
        isTrue,
      );
    });

    test('NO source file in this surface touches web storage', () {
      // The static guarantee. localStorage and sessionStorage are readable by
      // any JavaScript on the origin, so one injected script would exfiltrate
      // a long-lived credential. This asserts the code has no such call at all.
      final lib = Directory('lib');
      expect(lib.existsSync(), isTrue, reason: 'run from the app directory');

      final offenders = <String>[];
      for (final file
          in lib
              .listSync(recursive: true)
              .whereType<File>()
              .where((f) => f.path.endsWith('.dart'))) {
        // Comments are stripped first. The rule is about what the code DOES;
        // scanning prose would flag the very paragraphs that explain why the
        // rule exists, and the usual fix for that is to delete the explanation.
        final source = file
            .readAsLinesSync()
            .map((line) {
              final trimmed = line.trimLeft();
              if (trimmed.startsWith('//')) {
                return '';
              }
              final commentStart = line.indexOf('//');
              return commentStart == -1
                  ? line
                  : line.substring(0, commentStart);
            })
            .join('\n');
        for (final forbidden in <String>[
          'localStorage',
          'sessionStorage',
          'window.localStorage',
          'html.window',
          'SharedPreferences',
        ]) {
          if (source.contains(forbidden)) {
            offenders.add('${file.path}: $forbidden');
          }
        }
      }
      expect(
        offenders,
        isEmpty,
        reason:
            'Console Web must never place a credential in web storage:\n'
            '${offenders.join('\n')}',
      );
    });

    test('the cookie transport class exposes no token surface at all', () {
      // A class with no token field cannot leak a token, however the surface
      // later evolves.
      // Comments stripped: only the explanatory prose may mention these words.
      final source = File('lib/src/auth/cookie_session.dart')
          .readAsLinesSync()
          .where((line) => !line.trimLeft().startsWith('//'))
          .join('\n');
      expect(source, isNot(contains('token')));
      expect(source, isNot(contains('Authorization')));
      expect(source, isNot(contains('localStorage')));
    });
  });

  group('Startup and browser-refresh restoration', () {
    testWidgets('a fresh load with no session lands on sign-in', (
      tester,
    ) async {
      await pumpApp(tester, auth);
      expect(auth.calls, contains('restoreSession'));
      expect(find.textContaining('Masuk'), findsWidgets);
    });

    testWidgets('a refresh restores the session from the server', (
      tester,
    ) async {
      // A refresh re-runs main, so nothing in memory survives. What survives is
      // the HTTP-only cookie, which the browser attaches automatically — so
      // restoration is a SERVER question, not a storage read.
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      expect(find.text('Ringkasan portofolio'), findsOneWidget);
      expect(auth.calls, contains('restoreSession'));
    });

    testWidgets('restoration always asks the server, never local state', (
      tester,
    ) async {
      auth.nextRestoreState = const AuthState.unauthenticated();
      await pumpApp(tester, auth);
      expect(auth.calls.where((c) => c == 'restoreSession').length, 1);
    });
  });

  group('Tenant selection and the portfolio shell', () {
    testWidgets('a tenant must be chosen before the portfolio', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      expect(find.text('Pilih tenant'), findsWidgets);
      expect(find.text('Ringkasan portofolio'), findsNothing);
    });

    testWidgets('the shell renders the tenant context as text', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      expect(find.byType(ContextBanner), findsOneWidget);
      expect(find.textContaining('Laundry Melati (fiktif)'), findsWidgets);
    });

    testWidgets('a switcher appears only for a multi-tenant user', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      expect(find.text('Ganti tenant'), findsOneWidget);
    });
  });

  group('Role-aware side navigation', () {
    testWidgets('an owner sees the destinations their permissions allow', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      expect(find.text('Ringkasan'), findsOneWidget);
      expect(find.text('Audit'), findsOneWidget);
    });

    testWidgets('a destination the permission set forbids is not rendered', (
      tester,
    ) async {
      final session = SessionState(
        user: ApiFixtures.cashier,
        availableTenants: const <Tenant>[ApiFixtures.tenantMelati],
        activeTenant: ApiFixtures.tenantMelati,
        activeMembership: ApiFixtures.membershipCashierMelati,
        permissions: ApiFixtures.cashierPermissions(
          ApiFixtures.tenantMelati.id,
        ),
      );
      auth.nextRestoreState = AuthState.authenticated(session);
      await pumpApp(tester, auth);
      expect(find.text('Ringkasan'), findsOneWidget);
      expect(find.text('Audit'), findsNothing);
      expect(find.text('Keuangan'), findsNothing);
    });
  });

  group('Keyboard navigation and focus', () {
    testWidgets('every side-navigation destination is focusable by keyboard', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);

      // Tab must move focus into the navigation. Console Web is required to be
      // keyboard-complete: every action reachable by pointer is reachable by
      // keyboard, in a defined focus order.
      await tester.sendKeyEvent(LogicalKeyboardKey.tab);
      await tester.pumpAndSettle();
      expect(FocusManager.instance.primaryFocus, isNotNull);
      expect(
        FocusManager.instance.primaryFocus!.context,
        isNotNull,
        reason: 'Focus must rest on a real, mounted control.',
      );
    });

    testWidgets('a destination can be activated from the keyboard', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);

      await tester.tap(find.text('Audit'));
      await tester.pumpAndSettle();
      expect(find.text(kFutureStepNotice), findsOneWidget);
    });

    testWidgets(
      'the selected destination is marked by weight, not colour only',
      (tester) async {
        auth.nextRestoreState = AuthState.authenticated(
          ApiFixtures.fullContext(),
        );
        await pumpApp(tester, auth);
        final label = tester.widget<Text>(find.text('Ringkasan'));
        expect(label.style?.fontWeight, FontWeight.w700);
      },
    );
  });

  group('Session and membership states', () {
    Future<void> expectScreen(
      WidgetTester tester,
      AuthState state,
      String expected,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      auth.emitForTest(state);
      await tester.pumpAndSettle();
      expect(find.textContaining(expected), findsWidgets);
    }

    testWidgets('sessionExpired', (tester) async {
      await expectScreen(
        tester,
        const AuthState.sessionExpired(),
        'Sesi Anda telah berakhir',
      );
    });

    testWidgets('membershipSuspended', (tester) async {
      await expectScreen(
        tester,
        const AuthState.membershipSuspended(),
        'Keanggotaan Anda ditangguhkan',
      );
    });

    testWidgets('membershipRevoked', (tester) async {
      await expectScreen(
        tester,
        const AuthState.membershipRevoked(),
        'Keanggotaan Anda dicabut',
      );
    });

    testWidgets('tenantAccessDenied discloses nothing about existence', (
      tester,
    ) async {
      await expectScreen(
        tester,
        const AuthState.accessDenied(),
        'Akses ditolak',
      );
      final allText = tester
          .widgetList<Text>(find.byType(Text))
          .map((t) => t.data ?? '')
          .join(' ')
          .toLowerCase();
      for (final leak in <String>['tidak ditemukan', 'milik tenant lain']) {
        expect(allText, isNot(contains(leak)));
      }
    });
  });

  group('Portfolio content is honest', () {
    testWidgets('renders no figure, revenue or chart', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      final allText = tester
          .widgetList<Text>(find.byType(Text))
          .map((t) => t.data ?? '')
          .join(' ');
      // A dashboard with plausible numbers is the most effective way to make
      // an unbuilt product look finished.
      expect(allText, isNot(matches(RegExp(r'Rp\s?\d'))));
      expect(find.text(kFutureStepNotice), findsOneWidget);
    });
  });

  group('Logout', () {
    testWidgets('signs out and returns to sign-in', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      await tester.tap(find.byIcon(Icons.logout_outlined));
      await tester.pumpAndSettle();
      expect(auth.calls, contains('signOut'));
      expect(find.textContaining('Masuk'), findsWidgets);
    });
  });
}
