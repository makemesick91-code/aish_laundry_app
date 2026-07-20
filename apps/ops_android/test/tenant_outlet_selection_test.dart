import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

Environment env() => Environment.validate(
  environmentName: 'development',
  apiBaseUrl: 'http://localhost:8000/api/v1',
  appName: 'Uji Ops',
).valueOrNull!;

Future<void> pumpApp(WidgetTester tester, FakeAuthService auth) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
      ],
      child: const OpsApp(),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  late FakeAuthService auth;

  setUp(() => auth = FakeAuthService());
  tearDown(() => auth.dispose());

  group('Startup coordinator ordering', () {
    testWidgets('an authenticated user MUST choose a tenant first', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      expect(find.text('Pilih tenant'), findsWidgets);
      // Nothing tenant-scoped is on screen yet.
      expect(find.text('Beranda'), findsNothing);
    });

    testWidgets('selection is explicit EVEN with exactly one tenant', (
      tester,
    ) async {
      // The single-tenant case is where auto-selection is most tempting and
      // most dangerous: the code path then exists for the multi-tenant case.
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(
          tenants: const <Tenant>[ApiFixtures.tenantMelati],
        ),
      );
      await pumpApp(tester, auth);
      expect(find.text('Pilih tenant'), findsWidgets);
      expect(
        auth.calls.any((c) => c.startsWith('selectTenant:')),
        isFalse,
        reason: 'A tenant must never be chosen on the user behalf.',
      );
    });

    testWidgets('after a tenant, an outlet MUST be chosen before work', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      await tester.tap(find.text('Laundry Melati (fiktif)'));
      await tester.pumpAndSettle();
      expect(find.text('Pilih outlet'), findsWidgets);
      expect(find.text('Beranda'), findsNothing);
    });

    testWidgets('the full ordering reaches the shell', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      await tester.tap(find.text('Laundry Melati (fiktif)'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Outlet Pusat (fiktif)'));
      await tester.pumpAndSettle();
      expect(find.text('Beranda'), findsWidgets);
    });
  });

  group('Tenant selection', () {
    testWidgets('lists exactly what the server returned', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      expect(find.text('Laundry Melati (fiktif)'), findsOneWidget);
      expect(find.text('Laundry Kenanga (fiktif)'), findsOneWidget);
    });

    testWidgets(
      'an inactive tenant is listed but not selectable, with a reason',
      (tester) async {
        auth.nextRestoreState = AuthState.authenticated(
          ApiFixtures.signedInNoTenant(
            tenants: const <Tenant>[
              ApiFixtures.tenantMelati,
              ApiFixtures.tenantInactive,
            ],
          ),
        );
        await pumpApp(tester, auth);
        // Listed, not silently filtered away.
        expect(find.text('Laundry Nonaktif (fiktif)'), findsOneWidget);
        // The reason is EXPLAINED, not left as a greyed-out mystery.
        expect(find.textContaining('Tenant nonaktif'), findsOneWidget);

        await tester.tap(find.text('Laundry Nonaktif (fiktif)'));
        await tester.pumpAndSettle();
        expect(
          auth.calls.any((c) => c.contains('tnt_fiktif_nonaktif')),
          isFalse,
        );
      },
    );

    testWidgets('a refused tenant lands on access denied', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      auth.denyTenantSelection = true;
      await pumpApp(tester, auth);
      await tester.tap(find.text('Laundry Melati (fiktif)'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Akses ditolak'), findsWidgets);
    });

    testWidgets('an empty tenant list renders a designed empty state', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(tenants: const <Tenant>[]),
      );
      await pumpApp(tester, auth);
      expect(find.textContaining('Tidak ada tenant'), findsWidgets);
      expect(find.textContaining('Hubungi pengelola akun'), findsOneWidget);
    });

    testWidgets('tenant rows meet the 48x48 minimum', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      for (final element in find.byType(ListTile).evaluate()) {
        expect(element.size!.height, greaterThanOrEqualTo(48.0));
      }
    });
  });

  group('Outlet selection is tenant-scoped', () {
    Future<void> reachOutletStep(WidgetTester tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      await tester.tap(find.text('Laundry Melati (fiktif)'));
      await tester.pumpAndSettle();
    }

    testWidgets('shows only outlets of the ACTIVE tenant', (tester) async {
      auth.outletsForActiveTenant = const <Outlet>[
        ApiFixtures.outletMelatiPusat,
        ApiFixtures.outletKenanga, // belongs to the other tenant
      ];
      await reachOutletStep(tester);
      expect(find.text('Outlet Pusat (fiktif)'), findsOneWidget);
      expect(
        find.text('Kenanga Pusat (fiktif)'),
        findsNothing,
        reason: 'An outlet of another tenant must never be reachable.',
      );
    });

    testWidgets('the tenant banner stays visible while choosing an outlet', (
      tester,
    ) async {
      await reachOutletStep(tester);
      // The moment of commitment is exactly when context must be restated.
      expect(find.byType(ContextBanner), findsOneWidget);
      expect(find.textContaining('Laundry Melati (fiktif)'), findsWidgets);
    });

    testWidgets('an inactive outlet cannot become the working context', (
      tester,
    ) async {
      auth.outletsForActiveTenant = const <Outlet>[
        ApiFixtures.outletMelatiPusat,
        ApiFixtures.outletMelatiTutup,
      ];
      await reachOutletStep(tester);
      expect(find.textContaining('Outlet nonaktif'), findsOneWidget);
      await tester.tap(find.text('Outlet Tutup (fiktif)'));
      await tester.pumpAndSettle();
      expect(find.text('Beranda'), findsNothing);
    });

    testWidgets('an empty outlet list renders a designed empty state', (
      tester,
    ) async {
      auth.outletsForActiveTenant = const <Outlet>[];
      await reachOutletStep(tester);
      expect(find.textContaining('Tidak ada outlet'), findsWidgets);
    });
  });

  group('Tenant switching clears the working set', () {
    testWidgets('switching rebuilds context from a cleared session', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.signedInNoTenant(),
      );
      await pumpApp(tester, auth);
      await tester.tap(find.text('Laundry Melati (fiktif)'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Outlet Pusat (fiktif)'));
      await tester.pumpAndSettle();
      expect(find.text('Beranda'), findsWidgets);

      // Switch tenant. The outlet from the previous tenant must not survive.
      await auth.selectTenant(ApiFixtures.tenantKenanga.id);
      await tester.pumpAndSettle();
      expect(auth.current.session!.activeOutlet, isNull);
      expect(find.text('Pilih outlet'), findsWidgets);
    });
  });

  group('Role-aware navigation', () {
    testWidgets('an entry the permission set does not allow is not rendered', (
      tester,
    ) async {
      // Hiding is a courtesy, not a control — but a control that would be
      // refused must not be offered.
      final session = SessionState(
        user: ApiFixtures.cashier,
        availableTenants: const <Tenant>[ApiFixtures.tenantMelati],
        activeTenant: ApiFixtures.tenantMelati,
        activeMembership: ApiFixtures.membershipCashierMelati,
        activeOutlet: ApiFixtures.outletMelatiPusat,
        permissions: ApiFixtures.cashierPermissions(
          ApiFixtures.tenantMelati.id,
        ),
      );
      auth.nextRestoreState = AuthState.authenticated(session);
      await pumpApp(tester, auth);

      // outlet.view is granted; audit.view is not.
      expect(find.text('Kasir'), findsOneWidget);
      expect(find.text('Laporan'), findsNothing);
    });

    testWidgets('an owner sees the entries their permissions allow', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      expect(find.text('Kasir'), findsOneWidget);
      expect(find.text('Laporan'), findsOneWidget);
    });
  });

  group('Ops session and membership states', () {
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

    testWidgets('accessDenied', (tester) async {
      await expectScreen(
        tester,
        const AuthState.accessDenied(),
        'Akses ditolak',
      );
    });

    testWidgets('a membership problem offers another tenant, not a dead end', (
      tester,
    ) async {
      // A membership problem in ONE tenant does not end the session.
      await expectScreen(
        tester,
        const AuthState.membershipRevoked(),
        'Keanggotaan Anda dicabut',
      );
      expect(find.text('Pilih tenant lain'), findsOneWidget);
    });
  });

  group('Offline indicator', () {
    testWidgets('reports connectivity as text AND icon, never colour alone', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      expect(find.text('Terhubung'), findsOneWidget);
      expect(find.byIcon(Icons.cloud_done_outlined), findsOneWidget);
    });

    testWidgets('never claims a synchronisation that did not happen', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      final allText = tester
          .widgetList<Text>(find.byType(Text))
          .map((t) => t.data ?? '')
          .join(' ');
      expect(allText, isNot(contains('Tersinkron')));
    });
  });

  group('Future-feature routes', () {
    testWidgets('render the literal notice and keep the tenant banner', (
      tester,
    ) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      await tester.tap(find.text('Kasir'));
      await tester.pumpAndSettle();
      expect(find.text(kFutureStepNotice), findsOneWidget);
      // Even a placeholder carries visible tenant context.
      expect(find.byType(ContextBanner), findsOneWidget);
    });

    testWidgets('the shell renders no fabricated figures', (tester) async {
      auth.nextRestoreState = AuthState.authenticated(
        ApiFixtures.fullContext(),
      );
      await pumpApp(tester, auth);
      final allText = tester
          .widgetList<Text>(find.byType(Text))
          .map((t) => t.data ?? '')
          .join(' ');
      expect(allText, isNot(matches(RegExp(r'Rp\s?\d'))));
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
