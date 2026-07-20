import 'package:meta/meta.dart';

/// A tenant/organization — the isolation boundary and the billing boundary.
///
/// The canonical hierarchy is
/// `User Account -> Membership -> Tenant/Organization -> Laundry Brand -> Outlet`.
///
/// A [Tenant] instance held by a client is NOT authorization. It records which
/// tenant the server confirmed the user may act in. A tenant identifier the
/// client sends is an untrusted hint that the backend re-verifies on every
/// request (Rule 02, hard rule 9).
@immutable
final class Tenant {
  const Tenant({required this.id, required this.name, required this.isActive});

  final String id;

  final String name;

  /// Whether the tenant itself is active. An inactive tenant is rendered as
  /// unavailable rather than hidden, so a user is not left wondering whether
  /// they mis-remembered which account they have.
  final bool isActive;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Tenant &&
          other.id == id &&
          other.name == name &&
          other.isActive == isActive);

  @override
  int get hashCode => Object.hash(id, name, isActive);

  @override
  String toString() => 'Tenant($id, $name, active: $isActive)';
}
