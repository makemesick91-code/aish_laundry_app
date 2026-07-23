import 'dart:async';

import 'package:aish_auth/aish_auth.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../master_data/catalogue_screen.dart';
import '../master_data/customer_counter_screen.dart';
import '../master_data/customer_detail_screen.dart';
import '../master_data/outlet_master_data_screen.dart';
import '../master_data/staff_roster_screen.dart';
import '../pos/pos_counter_screen.dart';
import '../pos/pos_new_order_screen.dart';
import '../pos/pos_order_detail_screen.dart';
import '../screens/ops_home_screen.dart';
import '../screens/ops_session_screens.dart';
import '../screens/select_outlet_screen.dart';
import '../screens/select_tenant_screen.dart';
import '../screens/sign_in_screen.dart';
import '../screens/startup_screen.dart';
import 'ops_routes.dart';

/// The Ops router and its startup coordinator.
///
/// The ordering below is the whole point of the coordinator, and it is not
/// negotiable: authenticate, THEN choose a tenant, THEN choose an outlet, THEN
/// work. Nothing tenant-scoped is reachable before a tenant is chosen, and the
/// choice is never made on the user's behalf — not even when they belong to
/// exactly one tenant. A staff member who works for two competing laundries
/// must never learn which one they are in by reading the customer list.
///
/// As on every surface, this guard is presentation. The server re-verifies
/// membership and permission on every request.
final Provider<GoRouter> opsRouterProvider = Provider<GoRouter>((ref) {
  final AuthService service = ref.watch(authServiceProvider);
  final ValueNotifier<bool> startupGate = ref.watch(startupGateProvider);

  return GoRouter(
    initialLocation: OpsRoutes.startup,
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
        return location == OpsRoutes.startup ? null : OpsRoutes.startup;
      }

      return switch (auth) {
        Authenticating() => OpsRoutes.startup,
        Unauthenticated() ||
        LoggedOut() => location == OpsRoutes.signIn ? null : OpsRoutes.signIn,
        SessionExpired() => OpsRoutes.sessionExpired,
        SessionRevoked() => OpsRoutes.sessionRevoked,
        DeviceRevoked() => OpsRoutes.deviceRevoked,
        MembershipSuspended() => OpsRoutes.membershipSuspended,
        MembershipRevoked() => OpsRoutes.membershipRevoked,
        AccessDenied() => OpsRoutes.accessDenied,
        Authenticated(:final sessionState) => _authenticatedDestination(
          location: location,
          requiresTenant: sessionState.requiresTenantSelection,
          hasOutlet: sessionState.activeOutlet != null,
        ),
      };
    },
    routes: <RouteBase>[
      GoRoute(
        path: OpsRoutes.startup,
        builder: (_, _) => const StartupScreen(),
      ),
      GoRoute(path: OpsRoutes.signIn, builder: (_, _) => const SignInScreen()),
      GoRoute(
        path: OpsRoutes.selectTenant,
        builder: (_, _) => const SelectTenantScreen(),
      ),
      GoRoute(
        path: OpsRoutes.selectOutlet,
        builder: (_, _) => const SelectOutletScreen(),
      ),
      GoRoute(
        path: OpsRoutes.home,
        builder: (_, _) => const OpsHomeScreen(),
        routes: <RouteBase>[
          // -----------------------------------------------------------------
          // STEP 4 — LAUNDRY MASTER DATA. Real screens, backed by real routes.
          //
          // Every one is nested under `home`, so the redirect above has already
          // established an authenticated identity, a chosen tenant AND a chosen
          // outlet before any of them can build. That ordering is what stops a
          // master-data screen from ever rendering without tenant context.
          // -----------------------------------------------------------------
          GoRoute(
            path: 'pelanggan',
            builder: (_, _) => const CustomerCounterScreen(),
            routes: <RouteBase>[
              // DECLARED BEFORE the `:customerId` pattern. go_router matches in
              // order, so a literal segment registered after a parameter would
              // be swallowed by it and `/pelanggan/baru` would try to open a
              // customer whose id is the word "baru".
              GoRoute(
                path: 'baru',
                builder: (_, _) => const CustomerCreateScreen(),
              ),
              GoRoute(
                path: ':customerId',
                builder: (_, state) => CustomerDetailScreen(
                  customerId: state.pathParameters['customerId']!,
                ),
              ),
            ],
          ),
          GoRoute(path: 'layanan', builder: (_, _) => const CatalogueScreen()),
          GoRoute(
            path: 'outlet',
            builder: (_, _) => const OutletMasterDataScreen(),
          ),
          GoRoute(path: 'staf', builder: (_, _) => const StaffRosterScreen()),

          // Step 5 — POS counter (DEC-0035). `baru` is declared BEFORE the
          // `:orderId` pattern so `/kasir/baru` opens the intake screen rather
          // than an order whose id is "baru".
          GoRoute(
            path: 'kasir',
            builder: (_, _) => const PosCounterScreen(),
            routes: <RouteBase>[
              GoRoute(
                path: 'baru',
                builder: (_, _) => const PosNewOrderScreen(),
              ),
              GoRoute(
                path: ':orderId',
                builder: (_, state) => PosOrderDetailScreen(
                  orderId: state.pathParameters['orderId']!,
                ),
              ),
            ],
          ),
          GoRoute(
            path: 'produksi',
            builder: (_, _) => const _FuturePage(
              title: 'Produksi',
              feature: 'Operasi produksi cucian',
              step: 'Step 6',
            ),
          ),
          GoRoute(
            path: 'kendali-mutu',
            builder: (_, _) => const _FuturePage(
              title: 'Kendali mutu',
              feature: 'Kendali mutu dan pengerjaan ulang',
              step: 'Step 6',
            ),
          ),
          GoRoute(
            path: 'kurir',
            builder: (_, _) => const _FuturePage(
              title: 'Kurir',
              feature: 'Penjemputan, pengantaran, dan bukti serah terima',
              step: 'Step 8',
            ),
          ),
          GoRoute(
            path: 'laporan',
            builder: (_, _) => const _FuturePage(
              title: 'Laporan',
              feature: 'Laporan keuangan dan operasional',
              step: 'Step 10',
            ),
          ),
        ],
      ),
      GoRoute(
        path: OpsRoutes.sessionExpired,
        builder: (_, _) => const OpsSessionExpiredScreen(),
      ),
      GoRoute(
        path: OpsRoutes.sessionRevoked,
        builder: (_, _) => const OpsSessionRevokedScreen(),
      ),
      GoRoute(
        path: OpsRoutes.deviceRevoked,
        builder: (_, _) => const OpsDeviceRevokedScreen(),
      ),
      GoRoute(
        path: OpsRoutes.membershipSuspended,
        builder: (_, _) => const MembershipSuspendedScreen(),
      ),
      GoRoute(
        path: OpsRoutes.membershipRevoked,
        builder: (_, _) => const MembershipRevokedScreen(),
      ),
      GoRoute(
        path: OpsRoutes.outletInactive,
        builder: (_, _) => const OutletInactiveScreen(),
      ),
      GoRoute(
        path: OpsRoutes.accessDenied,
        builder: (_, _) => const OpsAccessDeniedScreen(),
      ),
    ],
  );
});

/// Enforce the startup ordering for an authenticated user.
String? _authenticatedDestination({
  required String location,
  required bool requiresTenant,
  required bool hasOutlet,
}) {
  if (requiresTenant) {
    // Nothing but tenant selection is reachable. Note that this holds even
    // when the user has exactly one tenant: selection is explicit.
    return location == OpsRoutes.selectTenant ? null : OpsRoutes.selectTenant;
  }
  if (!hasOutlet) {
    return location == OpsRoutes.selectOutlet ? null : OpsRoutes.selectOutlet;
  }
  // Every route that only makes sense BEFORE a full working context exists.
  // `selectOutlet` belongs here: once an outlet is chosen, leaving the user on
  // the outlet picker strands them on a screen whose job is already done.
  const Set<String> preContextOnly = <String>{
    OpsRoutes.startup,
    OpsRoutes.signIn,
    OpsRoutes.selectTenant,
    OpsRoutes.selectOutlet,
    OpsRoutes.sessionExpired,
    OpsRoutes.sessionRevoked,
    OpsRoutes.deviceRevoked,
    OpsRoutes.membershipSuspended,
    OpsRoutes.membershipRevoked,
    OpsRoutes.accessDenied,
  };
  return preContextOnly.contains(location) ? OpsRoutes.home : null;
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

class _FuturePage extends ConsumerWidget {
  const _FuturePage({
    required this.title,
    required this.feature,
    required this.step,
  });

  final String title;
  final String feature;
  final String step;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authServiceProvider).current.session;
    // Even a placeholder keeps the tenant banner. A screen without visible
    // tenant context is a tenant-isolation design defect regardless of whether
    // it renders data (Rule 28 rule 1).
    return AishScaffold(
      title: title,
      tenantName: session?.activeTenant?.name ?? '—',
      outletName: session?.activeOutlet?.name,
      body: FutureStepPlaceholder(featureName: feature, owningStep: step),
    );
  }
}
