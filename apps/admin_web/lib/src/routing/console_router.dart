import 'dart:async';

import 'package:aish_auth/aish_auth.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../screens/console_session_screens.dart';
import '../screens/portfolio_shell.dart';
import '../screens/select_tenant_screen.dart';
import '../screens/sign_in_screen.dart';
import '../screens/startup_screen.dart';
import 'console_routes.dart';

/// The Console Web router.
///
/// BROWSER REFRESH is the case this guard is built around. A refresh re-runs
/// `main`, so every in-memory state is gone; what survives is the HTTP-only
/// session cookie, which the browser attaches automatically. The startup screen
/// therefore always asks the SERVER who the user is rather than reading anything
/// back out of storage — which is also why there is no storage to read.
final Provider<GoRouter> consoleRouterProvider = Provider<GoRouter>((ref) {
  final AuthService service = ref.watch(authServiceProvider);
  final ValueNotifier<bool> startupGate = ref.watch(startupGateProvider);

  return GoRouter(
    initialLocation: ConsoleRoutes.startup,
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
        return location == ConsoleRoutes.startup ? null : ConsoleRoutes.startup;
      }

      return switch (auth) {
        Authenticating() => ConsoleRoutes.startup,
        Unauthenticated() || LoggedOut() =>
          location == ConsoleRoutes.signIn ? null : ConsoleRoutes.signIn,
        // A CSRF failure also lands here, because on web it means the browser
        // session is no longer usable.
        SessionExpired() ||
        SessionRevoked() ||
        DeviceRevoked() => ConsoleRoutes.sessionExpired,
        MembershipSuspended() => ConsoleRoutes.membershipSuspended,
        MembershipRevoked() => ConsoleRoutes.membershipRevoked,
        AccessDenied() => ConsoleRoutes.tenantAccessDenied,
        Authenticated(:final sessionState) =>
          sessionState.requiresTenantSelection
              ? (location == ConsoleRoutes.selectTenant
                    ? null
                    : ConsoleRoutes.selectTenant)
              : _postContextDestination(location),
      };
    },
    routes: <RouteBase>[
      GoRoute(
        path: ConsoleRoutes.startup,
        builder: (_, _) => const StartupScreen(),
      ),
      GoRoute(
        path: ConsoleRoutes.signIn,
        builder: (_, _) => const SignInScreen(),
      ),
      GoRoute(
        path: ConsoleRoutes.selectTenant,
        builder: (_, _) => const SelectTenantScreen(),
      ),
      GoRoute(
        path: ConsoleRoutes.portfolio,
        builder: (_, _) => const PortfolioShell(child: PortfolioOverview()),
        routes: <RouteBase>[
          GoRoute(
            path: 'data-induk',
            builder: (_, _) => const PortfolioShell(
              child: FutureStepPlaceholder(
                featureName: 'Data induk laundry',
                owningStep: 'Step 4',
              ),
            ),
          ),
          GoRoute(
            path: 'keuangan',
            builder: (_, _) => const PortfolioShell(
              child: FutureStepPlaceholder(
                featureName: 'Keuangan dan laporan portofolio',
                owningStep: 'Step 10',
              ),
            ),
          ),
          GoRoute(
            path: 'langganan',
            builder: (_, _) => const PortfolioShell(
              child: FutureStepPlaceholder(
                featureName: 'Langganan dan administrasi platform',
                owningStep: 'Step 12',
              ),
            ),
          ),
          GoRoute(
            path: 'audit',
            builder: (_, _) => const PortfolioShell(
              child: FutureStepPlaceholder(
                featureName: 'Jejak audit tenant',
                owningStep: 'Step 12',
              ),
            ),
          ),
        ],
      ),
      GoRoute(
        path: ConsoleRoutes.sessionExpired,
        builder: (_, _) => const ConsoleSessionExpiredScreen(),
      ),
      GoRoute(
        path: ConsoleRoutes.membershipSuspended,
        builder: (_, _) => const ConsoleMembershipSuspendedScreen(),
      ),
      GoRoute(
        path: ConsoleRoutes.membershipRevoked,
        builder: (_, _) => const ConsoleMembershipRevokedScreen(),
      ),
      GoRoute(
        path: ConsoleRoutes.tenantAccessDenied,
        builder: (_, _) => const ConsoleTenantAccessDeniedScreen(),
      ),
    ],
  );
});

String? _postContextDestination(String location) {
  const Set<String> preContextOnly = <String>{
    ConsoleRoutes.startup,
    ConsoleRoutes.signIn,
    ConsoleRoutes.sessionExpired,
    ConsoleRoutes.membershipSuspended,
    ConsoleRoutes.membershipRevoked,
    ConsoleRoutes.tenantAccessDenied,
  };
  return preContextOnly.contains(location) ? ConsoleRoutes.portfolio : null;
}

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
