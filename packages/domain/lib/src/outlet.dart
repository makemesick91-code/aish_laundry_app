import 'package:meta/meta.dart';

/// A physical location belonging to a laundry brand.
///
/// Carries NO address. An outlet address is `RESTRICTED` data and Step 3 has no
/// requirement that puts it on a client surface, so it is not modelled here at
/// all. A field that does not exist cannot leak.
@immutable
final class Outlet {
  const Outlet({
    required this.id,
    required this.tenantId,
    required this.brandId,
    required this.name,
    required this.isActive,
  });

  final String id;

  final String tenantId;

  final String brandId;

  final String name;

  /// An inactive outlet may still be listed, but it can never be selected as
  /// the working context. The distinction is surfaced to the user rather than
  /// silently filtered, so "my outlet vanished" becomes "my outlet is inactive".
  final bool isActive;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Outlet &&
          other.id == id &&
          other.tenantId == tenantId &&
          other.brandId == brandId &&
          other.name == name &&
          other.isActive == isActive);

  @override
  int get hashCode => Object.hash(id, tenantId, brandId, name, isActive);

  @override
  String toString() => 'Outlet($id, $name, active: $isActive)';
}
