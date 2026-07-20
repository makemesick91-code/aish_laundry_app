import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/console_routes.dart';

/// The authenticated console frame: side navigation plus a content area.
///
/// KEYBOARD COMPLETENESS is the property this shell exists to guarantee. Every
/// destination is a real focusable control in a defined order, reachable by Tab
/// and activated by Enter or Space. Nothing here is a pointer-only affordance,
/// and no destination is reachable only by hovering.
///
/// The side navigation is ROLE-AWARE and, as everywhere else, that is a
/// convenience rather than a control: the server re-checks every request.
class PortfolioShell extends ConsumerWidget {
  const PortfolioShell({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authServiceProvider).current.session;
    if (session == null || !session.hasTenantContext) {
      return const Scaffold(body: SizedBox.shrink());
    }

    final destinations = <_ConsoleDestination>[
      const _ConsoleDestination(
        label: 'Ringkasan',
        icon: Icons.dashboard_outlined,
        route: ConsoleRoutes.portfolio,
        requiredPermission: Permission.tenantView,
      ),
      const _ConsoleDestination(
        label: 'Data induk',
        icon: Icons.inventory_2_outlined,
        route: ConsoleRoutes.futureMasterData,
        requiredPermission: Permission.brandView,
      ),
      const _ConsoleDestination(
        label: 'Keuangan',
        icon: Icons.account_balance_outlined,
        route: ConsoleRoutes.futureFinance,
        requiredPermission: Permission.auditView,
      ),
      const _ConsoleDestination(
        label: 'Langganan',
        icon: Icons.card_membership_outlined,
        route: ConsoleRoutes.futureSubscription,
        requiredPermission: Permission.membershipView,
      ),
      const _ConsoleDestination(
        label: 'Audit',
        icon: Icons.receipt_long_outlined,
        route: ConsoleRoutes.futureAudit,
        requiredPermission: Permission.auditView,
      ),
    ].where((d) => session.allows(d.requiredPermission)).toList();

    final currentRoute = GoRouterState.of(context).matchedLocation;

    return Scaffold(
      appBar: AppBar(
        title: Semantics(
          header: true,
          child: const Text('Aish Laundry Console'),
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Keluar dari akun ${session.user.displayName}',
            icon: const Icon(Icons.logout_outlined),
            onPressed: () async {
              await ref.read(authServiceProvider).signOut();
              if (context.mounted) {
                context.go(ConsoleRoutes.signIn);
              }
            },
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(kToolbarHeight * 0.5),
          child: ContextBanner(
            tenantName: session.activeTenant!.name,
            outletName: session.activeOutlet?.name,
            onSwitchTenant: session.needsTenantSwitcher
                ? () => context.go(ConsoleRoutes.selectTenant)
                : null,
          ),
        ),
      ),
      body: Row(
        children: <Widget>[
          _SideNavigation(
            destinations: destinations,
            currentRoute: currentRoute,
          ),
          const VerticalDivider(width: 1),
          Expanded(child: SafeArea(child: child)),
        ],
      ),
    );
  }
}

class _ConsoleDestination {
  const _ConsoleDestination({
    required this.label,
    required this.icon,
    required this.route,
    required this.requiredPermission,
  });

  final String label;
  final IconData icon;
  final String route;
  final String requiredPermission;
}

class _SideNavigation extends StatelessWidget {
  const _SideNavigation({
    required this.destinations,
    required this.currentRoute,
  });

  final List<_ConsoleDestination> destinations;
  final String currentRoute;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: AishSizing.sizeSidenavWidth,
    child: Semantics(
      container: true,
      label: 'Navigasi utama konsol',
      child: ListView(
        padding: EdgeInsets.symmetric(vertical: AishSpacing.space4),
        children: <Widget>[
          for (final destination in destinations)
            _NavButton(
              destination: destination,
              // Selection is carried by text weight and a leading marker
              // as well as by colour, so it survives a monochrome or
              // sunlight-washed screen.
              isSelected: currentRoute == destination.route,
            ),
        ],
      ),
    ),
  );
}

class _NavButton extends StatelessWidget {
  const _NavButton({required this.destination, required this.isSelected});

  final _ConsoleDestination destination;
  final bool isSelected;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Padding(
      padding: EdgeInsets.symmetric(
        horizontal: AishSpacing.space2,
        vertical: AishSpacing.space1,
      ),
      child: ConstrainedBox(
        constraints: BoxConstraints(minHeight: AishSizing.sizeTouchMin),
        child: TextButton(
          onPressed: () => GoRouter.of(context).go(destination.route),
          style: ButtonStyle(
            alignment: Alignment.centerLeft,
            backgroundColor: WidgetStatePropertyAll<Color>(
              isSelected
                  ? AishSemanticColors.colorSemanticSelectedSurface
                  : Colors.transparent,
            ),
            // The focus ring is explicit and is never removed.
            side: WidgetStateProperty.resolveWith<BorderSide?>((states) {
              if (states.contains(WidgetState.focused)) {
                return BorderSide(
                  color: AishSemanticColors.colorSemanticFocus,
                  width: AishBorders.borderWidthFocus,
                );
              }
              return null;
            }),
          ),
          child: Semantics(
            selected: isSelected,
            label: isSelected
                ? '${destination.label}. Halaman aktif.'
                : destination.label,
            child: ExcludeSemantics(
              child: Row(
                children: <Widget>[
                  Icon(destination.icon, size: AishSizing.sizeIconMd),
                  SizedBox(width: AishSpacing.space2),
                  Flexible(
                    child: Text(
                      destination.label,
                      style: textTheme.labelMedium?.copyWith(
                        fontWeight: isSelected
                            ? FontWeight.w700
                            : FontWeight.w400,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// The portfolio landing content.
///
/// Renders NO figure, NO revenue, NO order count and NO chart, because none of
/// those exist. A dashboard populated with plausible numbers is the single most
/// effective way to make an unbuilt product look finished.
class PortfolioOverview extends ConsumerWidget {
  const PortfolioOverview({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authServiceProvider).current.session;
    final textTheme = Theme.of(context).textTheme;
    return ListView(
      padding: EdgeInsets.all(AishSpacing.space6),
      children: <Widget>[
        Semantics(
          header: true,
          child: Text('Ringkasan portofolio', style: textTheme.titleLarge),
        ),
        SizedBox(height: AishSpacing.space2),
        Text(
          'Konteks aktif: ${session?.activeTenant?.name ?? '—'}.',
          style: textTheme.bodyMedium,
        ),
        SizedBox(height: AishSpacing.space6),
        const FutureStepPlaceholder(
          featureName: 'Ringkasan portofolio pemilik',
          owningStep: 'Step 10',
        ),
      ],
    );
  }
}
