import 'dart:async';

import 'package:aish_auth/aish_auth.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../screens/customer_home_screen.dart';
import '../screens/design_smoke_screen.dart';
import '../screens/session_state_screens.dart';
import '../screens/sign_in_screen.dart';
import '../screens/startup_screen.dart';
import 'customer_routes.dart';

/// The router, with a guard driven by [AuthState].
///
/// A CRITICAL BOUNDARY, stated so nobody has to infer it: this guard is a
/// USER-EXPERIENCE affordance, not an access control. It decides which screen a
/// user looks at. It decides nothing about what data they may have, because the
/// server re-verifies authentication, membership and permission on every single
/// request. If this guard were removed entirely, no user would gain access to
/// anything — they would merely see screens that fail (Rule 28 rule 6).
final Provider<GoRouter> customerRouterProvider = Provider<GoRouter>((ref) {
  final AuthService service = ref.watch(authServiceProvider);
  final ValueNotifier<bool> startupGate = ref.watch(startupGateProvider);

  return GoRouter(
    initialLocation: CustomerRoutes.startup,
    // Rebuild the guard whenever authentication state moves.
    refreshListenable: Listenable.merge(<Listenable>[
      _AuthListenable(service),
      startupGate,
    ]),
    redirect: (context, state) {
      final AuthState auth = service.current;
      final String location = state.matchedLocation;

      // Until restoration has produced an answer, every route resolves to the
      // startup screen. Without this, a returning user with a valid session is
      // bounced to sign-in on the first frame and restoration never runs.
      if (!startupGate.value) {
        return location == CustomerRoutes.startup
            ? null
            : CustomerRoutes.startup;
      }

      // The design-system preview is reachable without a session on purpose:
      // it renders nothing but components and no data of any kind.
      if (location == CustomerRoutes.designSmoke) {
        return null;
      }

      return switch (auth) {
        Authenticating() => CustomerRoutes.startup,
        Unauthenticated() || LoggedOut() =>
          location == CustomerRoutes.signIn ? null : CustomerRoutes.signIn,
        SessionExpired() => CustomerRoutes.sessionExpired,
        SessionRevoked() => CustomerRoutes.sessionRevoked,
        DeviceRevoked() => CustomerRoutes.deviceRevoked,
        // The customer surface has no tenant switcher, so a membership problem
        // presents as a denial rather than as a context to re-select.
        MembershipSuspended() ||
        MembershipRevoked() ||
        AccessDenied() => CustomerRoutes.accessDenied,
        Authenticated() => _redirectAuthenticated(location),
      };
    },
    routes: <RouteBase>[
      GoRoute(
        path: CustomerRoutes.startup,
        builder: (_, _) => const StartupScreen(),
      ),
      GoRoute(
        path: CustomerRoutes.signIn,
        builder: (_, _) => const SignInScreen(),
      ),
      GoRoute(
        path: CustomerRoutes.home,
        builder: (_, _) => const CustomerHomeScreen(),
        routes: <RouteBase>[
          GoRoute(
            path: 'pesanan',
            builder: (_, _) => const _FuturePage(
              title: 'Pesanan saya',
              feature: 'Daftar pesanan pelanggan',
              step: 'Step 5',
            ),
          ),
          GoRoute(
            path: 'lacak',
            builder: (_, _) => const _FuturePage(
              title: 'Lacak cucian',
              feature: 'Pelacakan status cucian',
              step: 'Step 7',
            ),
          ),
          GoRoute(
            path: 'penjemputan',
            builder: (_, _) => const _FuturePage(
              title: 'Penjemputan',
              feature: 'Permintaan penjemputan dan pengantaran',
              step: 'Step 8',
            ),
          ),
          GoRoute(
            path: 'tagihan',
            builder: (_, _) => const _FuturePage(
              title: 'Tagihan',
              feature: 'Tagihan dan riwayat pembayaran',
              step: 'Step 5',
            ),
          ),
        ],
      ),
      GoRoute(
        path: CustomerRoutes.sessionExpired,
        builder: (_, _) => const SessionExpiredScreen(),
      ),
      GoRoute(
        path: CustomerRoutes.sessionRevoked,
        builder: (_, _) => const SessionRevokedScreen(),
      ),
      GoRoute(
        path: CustomerRoutes.deviceRevoked,
        builder: (_, _) => const DeviceRevokedScreen(),
      ),
      GoRoute(
        path: CustomerRoutes.accessDenied,
        builder: (_, _) => const AccessDeniedScreen(),
      ),
      GoRoute(
        path: CustomerRoutes.networkUnavailable,
        builder: (_, _) => const NetworkUnavailableScreen(),
      ),
      GoRoute(
        path: CustomerRoutes.serviceUnavailable,
        builder: (_, _) => const ServiceUnavailableScreen(),
      ),
      GoRoute(
        path: CustomerRoutes.designSmoke,
        builder: (_, _) => const DesignSmokeScreen(),
      ),
    ],
  );
});

/// Where an authenticated user should be.
///
/// Anything that is not an authenticated destination sends the user home,
/// rather than leaving them on a session-ended screen they have since recovered
/// from.
String? _redirectAuthenticated(String location) {
  const Set<String> unauthenticatedOnly = <String>{
    CustomerRoutes.startup,
    CustomerRoutes.signIn,
    CustomerRoutes.sessionExpired,
    CustomerRoutes.sessionRevoked,
    CustomerRoutes.deviceRevoked,
    CustomerRoutes.accessDenied,
  };
  return unauthenticatedOnly.contains(location) ? CustomerRoutes.home : null;
}

/// Bridges the auth stream to `go_router`'s [Listenable] refresh mechanism.
class _AuthListenable extends ChangeNotifier {
  _AuthListenable(AuthService service) {
    _subscription = service.states.listen((_) => notifyListeners());
  }

  late final StreamSubscription<AuthState> _subscription;

  @override
  void dispose() {
    unawaited(_subscription.cancel());
    super.dispose();
  }
}

/// A future-feature route. Renders the literal notice and nothing else.
class _FuturePage extends StatelessWidget {
  const _FuturePage({
    required this.title,
    required this.feature,
    required this.step,
  });

  final String title;
  final String feature;
  final String step;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Semantics(header: true, child: Text(title))),
    body: FutureStepPlaceholder(featureName: feature, owningStep: step),
  );
}
