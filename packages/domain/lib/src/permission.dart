import 'package:meta/meta.dart';

/// A single permission key the server reported as granted.
///
/// A [Permission] held by a client is a RENDERING HINT and nothing else. It
/// decides whether a control is drawn; it never decides whether an action is
/// allowed. The server re-checks every request, so a client that is wrong about
/// a permission produces a refused request, not an unauthorized effect
/// (Rule 03, Rule 28 hard rule 6).
@immutable
final class Permission {
  const Permission(this.key);

  /// The canonical dotted key, e.g. `outlet.switch`.
  final String key;

  // Step 3 permission keys used by the shells' navigation predicates.
  static const String tenantView = 'tenant.view';
  static const String tenantSwitch = 'tenant.switch';
  static const String brandView = 'brand.view';
  static const String outletView = 'outlet.view';
  static const String outletSwitch = 'outlet.switch';
  static const String membershipView = 'membership.view';
  static const String sessionViewSelf = 'session.view.self';
  static const String sessionRevokeSelf = 'session.revoke.self';
  static const String deviceSessionView = 'device_session.view';
  static const String permissionInspect = 'authorization.permission.inspect';
  static const String auditView = 'audit.view';

  @override
  bool operator ==(Object other) =>
      identical(this, other) || (other is Permission && other.key == key);

  @override
  int get hashCode => key.hashCode;

  @override
  String toString() => key;
}
