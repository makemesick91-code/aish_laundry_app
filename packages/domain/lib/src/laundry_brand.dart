import 'package:meta/meta.dart';

/// A commercial brand owned by a tenant. A tenant may have several.
@immutable
final class LaundryBrand {
  const LaundryBrand({
    required this.id,
    required this.tenantId,
    required this.name,
    required this.isActive,
  });

  final String id;

  /// The owning tenant. Present on every business projection without exception,
  /// mirroring the server-side rule that every business record carries a
  /// tenant identifier (Rule 02, hard rule 7).
  final String tenantId;

  final String name;

  final bool isActive;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LaundryBrand &&
          other.id == id &&
          other.tenantId == tenantId &&
          other.name == name &&
          other.isActive == isActive);

  @override
  int get hashCode => Object.hash(id, tenantId, name, isActive);

  @override
  String toString() => 'LaundryBrand($id, $name)';
}
