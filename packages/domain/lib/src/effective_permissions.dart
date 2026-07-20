import 'package:meta/meta.dart';

import 'permission.dart';

/// The permission set the server reported for one user in one tenant.
///
/// THIS IS NOT AN AUTHORIZATION DECISION. It is the server's answer to "what
/// would you currently let me do", cached so the interface can avoid drawing
/// controls that would be refused. Rule 28 hard rule 5 requires that a control
/// the user may not use is not rendered; this type is how a surface knows.
///
/// Two properties matter and are enforced by construction:
///
///   * It is TENANT-SCOPED. A permission set carries the tenant it was issued
///     for, and asking it about a different tenant is a programming error
///     rather than a quiet `false`. A silent `false` would look like a denial
///     and hide the fact that the wrong context was consulted.
///
///   * It is EMPTY BY DEFAULT. [EffectivePermissions.none] grants nothing. A
///     surface that has not yet loaded permissions therefore renders as if the
///     user may do nothing, which fails closed.
@immutable
final class EffectivePermissions {
  // Deliberately non-const: the incoming set is copied into an unmodifiable one
  // at construction, so a caller cannot retain a reference and grant itself a
  // permission afterwards. A const constructor would have to trust the caller's
  // set, which defeats the point.
  // ignore: prefer_const_constructors_in_immutables
  EffectivePermissions({
    required this.tenantId,
    required Set<Permission> permissions,
  }) : _permissions = Set<Permission>.unmodifiable(permissions);

  /// A permission set that grants nothing, for a context not yet resolved.
  const EffectivePermissions.none({required this.tenantId})
    : _permissions = const <Permission>{};

  /// The tenant these permissions were issued for.
  final String tenantId;

  final Set<Permission> _permissions;

  Set<Permission> get permissions => _permissions;

  bool get isEmpty => _permissions.isEmpty;

  /// Whether [key] was granted in [tenantId].
  ///
  /// [expectedTenantId] must be supplied and must match. Making the caller
  /// state which tenant it believes it is in turns a context mix-up into a
  /// loud failure instead of a wrong screen.
  bool allows(String key, {required String expectedTenantId}) {
    if (expectedTenantId != tenantId) {
      throw StateError(
        'Permission set belongs to tenant $tenantId but was consulted for '
        '$expectedTenantId. A permission is never evaluated across a tenant '
        'boundary.',
      );
    }
    return _permissions.contains(Permission(key));
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is EffectivePermissions &&
          other.tenantId == tenantId &&
          other._permissions.length == _permissions.length &&
          other._permissions.containsAll(_permissions));

  @override
  int get hashCode => Object.hash(tenantId, _permissions.length);

  @override
  String toString() =>
      'EffectivePermissions(tenant: $tenantId, count: ${_permissions.length})';
}
