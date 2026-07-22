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

  // Step 4 master-data permission keys (DEC-0028, DEC-0030). Used by the
  // console's navigation predicates and to decide whether an EDIT control is
  // drawn at all — a control the user may not use is not rendered, and a
  // visible control is never silently denied (Rule 28 hard rule 5).
  //
  // These remain RENDERING HINTS. The server re-derives the caller's
  // permissions from live membership on every request; a client that is wrong
  // about one produces a refused request, never an unauthorized effect.
  static const String customerView = 'customer.view';
  static const String customerManage = 'customer.manage';
  static const String customerConsentManage = 'customer.consent.manage';
  static const String serviceView = 'service.view';
  static const String serviceManage = 'service.manage';
  static const String priceListView = 'price_list.view';
  static const String priceListManage = 'price_list.manage';
  static const String priceListPublish = 'price_list.publish';
  static const String outletMasterDataManage = 'outlet.master_data.manage';
  static const String staffAssignmentManage = 'staff.assignment.manage';

  @override
  bool operator ==(Object other) =>
      identical(this, other) || (other is Permission && other.key == key);

  @override
  int get hashCode => key.hashCode;

  @override
  String toString() => key;
}
