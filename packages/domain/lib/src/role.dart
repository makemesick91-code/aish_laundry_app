import 'package:meta/meta.dart';

/// A role slug granted to a membership, as reported by the server.
///
/// Modelled as a value object over an opaque slug rather than as a closed
/// `enum`. That is deliberate: the server owns the role catalogue, and a client
/// enum would either reject a role the server legitimately added, or force a
/// release of three applications before a new role can be used. An unknown slug
/// is carried faithfully and grants nothing on its own, because grants come
/// from [EffectivePermissions], never from a role name.
@immutable
final class Role {
  const Role({required this.slug, required this.label});

  /// The canonical machine slug, e.g. `outlet_manager`.
  final String slug;

  /// Server-supplied Bahasa Indonesia label for display.
  final String label;

  // The Step 3 catalogue, named for readability in tests and navigation
  // predicates. Presence here is NOT a grant.
  static const String tenantOwner = 'tenant_owner';
  static const String tenantAdmin = 'tenant_admin';
  static const String outletManager = 'outlet_manager';
  static const String cashier = 'cashier';
  static const String productionOperator = 'production_operator';
  static const String qualityControl = 'quality_control';
  static const String courier = 'courier';
  static const String finance = 'finance';
  static const String customer = 'customer';
  static const String platformSuperAdmin = 'platform_super_admin';
  static const String platformSupport = 'platform_support';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Role && other.slug == slug && other.label == label);

  @override
  int get hashCode => Object.hash(slug, label);

  @override
  String toString() => 'Role($slug)';
}
