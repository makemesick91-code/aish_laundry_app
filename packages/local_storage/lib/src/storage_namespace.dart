import 'package:meta/meta.dart';

/// A namespace that qualifies every stored key.
///
/// Two separations are enforced, and neither is optional:
///
///   * PER USER. Two accounts on one shared counter device do not see each
///     other's credentials.
///   * PER TENANT. A user who belongs to two competing laundry businesses gets
///     two unrelated key spaces. Rule 07 rule 7 requires that switching tenants
///     cannot expose the previous context's cached data, and a namespace is how
///     that is made structural rather than remembered.
///
/// A namespace is NOT a security boundary on its own — the underlying store is
/// what provides confidentiality. It is a correctness boundary: it makes a
/// cross-context read impossible to write by accident, rather than merely
/// impolite.
@immutable
final class StorageNamespace {
  const StorageNamespace._(this._prefix);

  /// Keys that belong to the installation rather than to any user, such as the
  /// identifier of the last surface configuration validated at startup.
  const StorageNamespace.device() : _prefix = 'device';

  /// Keys scoped to one authenticated user across every tenant they belong to,
  /// such as the session credential itself.
  factory StorageNamespace.user(String userId) {
    _require(userId, 'userId');
    return StorageNamespace._('user:$userId');
  }

  /// Keys scoped to one user acting in ONE tenant.
  factory StorageNamespace.tenant({
    required String userId,
    required String tenantId,
  }) {
    _require(userId, 'userId');
    _require(tenantId, 'tenantId');
    return StorageNamespace._('user:$userId:tenant:$tenantId');
  }

  final String _prefix;

  static void _require(String value, String name) {
    if (value.trim().isEmpty) {
      throw ArgumentError.value(value, name, 'must not be empty');
    }
    if (value.contains(':')) {
      // A colon in an identifier would let one namespace impersonate another,
      // e.g. a userId of "a:tenant:b". Rejecting it keeps the encoding
      // unambiguous.
      throw ArgumentError.value(value, name, 'must not contain ":"');
    }
  }

  /// Qualify [key] with this namespace.
  String qualify(String key) {
    if (key.trim().isEmpty) {
      throw ArgumentError.value(key, 'key', 'must not be empty');
    }
    return '$_prefix/$key';
  }

  /// Whether a qualified key belongs to this namespace.
  bool owns(String qualifiedKey) => qualifiedKey.startsWith('$_prefix/');

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is StorageNamespace && other._prefix == _prefix);

  @override
  int get hashCode => _prefix.hashCode;

  @override
  String toString() => 'StorageNamespace($_prefix)';
}
