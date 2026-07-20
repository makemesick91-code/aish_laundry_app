import 'package:meta/meta.dart';

import 'effective_permissions.dart';
import 'membership.dart';
import 'outlet.dart';
import 'tenant.dart';
import 'user.dart';

/// The resolved working context of an authenticated session.
///
/// A session is only usable for tenant-scoped work once a tenant has been
/// EXPLICITLY selected. There is no "first tenant wins" default anywhere in this
/// codebase: a user who belongs to two competing laundry businesses must never
/// discover which one they are in by reading the data on screen.
///
/// [activeOutlet] is separately optional, because a user may hold a tenant
/// context while still choosing among several outlets.
@immutable
final class SessionState {
  const SessionState({
    required this.user,
    required this.availableTenants,
    this.activeTenant,
    this.activeMembership,
    this.activeOutlet,
    this.permissions,
  });

  final User user;

  /// Tenants the SERVER confirmed this user may act in. The client never
  /// computes this list and never adds to it.
  final List<Tenant> availableTenants;

  final Tenant? activeTenant;

  final Membership? activeMembership;

  final Outlet? activeOutlet;

  /// Permissions for [activeTenant]. Absent until a tenant is selected, which
  /// means an unselected context grants nothing.
  final EffectivePermissions? permissions;

  /// Whether tenant-scoped work may proceed.
  bool get hasTenantContext =>
      activeTenant != null &&
      activeMembership != null &&
      activeMembership!.isActive;

  /// Whether a tenant must still be chosen before anything tenant-scoped runs.
  bool get requiresTenantSelection => activeTenant == null;

  /// Whether this user can belong to more than one tenant, and therefore needs
  /// a tenant switcher (Rule 02, hard rule 5).
  bool get needsTenantSwitcher => availableTenants.length > 1;

  /// Test a permission in the ACTIVE tenant, failing closed.
  ///
  /// Returns `false` when there is no tenant context at all, rather than
  /// throwing: "no context" is a legitimate rendering state and it must grant
  /// nothing.
  bool allows(String permissionKey) {
    final tenant = activeTenant;
    final granted = permissions;
    if (tenant == null || granted == null || !hasTenantContext) {
      return false;
    }
    return granted.allows(permissionKey, expectedTenantId: tenant.id);
  }

  SessionState copyWith({
    Tenant? activeTenant,
    Membership? activeMembership,
    Outlet? activeOutlet,
    EffectivePermissions? permissions,
    bool clearOutlet = false,
  }) => SessionState(
    user: user,
    availableTenants: availableTenants,
    activeTenant: activeTenant ?? this.activeTenant,
    activeMembership: activeMembership ?? this.activeMembership,
    activeOutlet: clearOutlet ? null : (activeOutlet ?? this.activeOutlet),
    permissions: permissions ?? this.permissions,
  );

  /// Drop every tenant-scoped element, keeping only the identity.
  ///
  /// Used when the tenant is switched. Rule 28 hard rule 3 requires switching
  /// to clear the visible working set; carrying the previous tenant's outlet or
  /// permissions across the switch is exactly the leak that rule prevents.
  SessionState withoutTenantContext() =>
      SessionState(user: user, availableTenants: availableTenants);

  @override
  String toString() =>
      'SessionState(user: ${user.id}, '
      'tenant: ${activeTenant?.id}, outlet: ${activeOutlet?.id})';
}
