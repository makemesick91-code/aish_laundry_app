import 'package:meta/meta.dart';

import 'role.dart';

/// Lifecycle of a membership, as reported by the server.
enum MembershipStatus {
  active,
  suspended,
  revoked;

  /// Parse a server value, failing SAFE.
  ///
  /// An unrecognised status resolves to [MembershipStatus.suspended], not to
  /// [MembershipStatus.active]. If the server grows a status this build does
  /// not know, the client withholds access and says so rather than assuming the
  /// user is still entitled — the opposite default would turn an unknown string
  /// into a grant.
  static MembershipStatus parse(String raw) {
    for (final value in MembershipStatus.values) {
      if (value.name == raw) {
        return value;
      }
    }
    return MembershipStatus.suspended;
  }
}

/// The join between a user and a tenant, carrying roles.
///
/// Authorization derives from the membership, never from the user account alone
/// (Rule 02). One user may hold several memberships; each is unrelated to the
/// others, and holding one says nothing about any other.
@immutable
final class Membership {
  const Membership({
    required this.id,
    required this.userId,
    required this.tenantId,
    required this.status,
    required this.roles,
    this.defaultOutletId,
  });

  final String id;

  final String userId;

  final String tenantId;

  final MembershipStatus status;

  final List<Role> roles;

  /// Outlet the server suggests as an initial working context, if any. A
  /// suggestion only — outlet selection remains explicit (Rule 28).
  final String? defaultOutletId;

  bool get isActive => status == MembershipStatus.active;

  bool hasRole(String slug) => roles.any((role) => role.slug == slug);

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Membership &&
          other.id == id &&
          other.userId == userId &&
          other.tenantId == tenantId &&
          other.status == status &&
          other.defaultOutletId == defaultOutletId);

  @override
  int get hashCode =>
      Object.hash(id, userId, tenantId, status, defaultOutletId);

  @override
  String toString() => 'Membership($id, tenant: $tenantId, ${status.name})';
}
