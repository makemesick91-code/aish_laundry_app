import 'package:meta/meta.dart';

/// How a service is measured (FR-031).
///
/// An ENUM, not a string, because a downstream reader must be able to tell a
/// weight from a count without parsing a label. `minimumQuantity` means grams
/// under [kiloan] and a number of items under [satuan]; getting that wrong is a
/// thousand-fold error, so the type carries the answer.
enum ServiceUnitKind {
  kiloan,
  satuan;

  static ServiceUnitKind parse(String value) => switch (value) {
    'kiloan' => ServiceUnitKind.kiloan,
    'satuan' => ServiceUnitKind.satuan,
    // An unknown unit is a server/client version mismatch, not something to
    // guess at: guessing would silently price a weight as a count.
    _ => throw ArgumentError.value(value, 'unit_kind', 'Jenis satuan tidak dikenal'),
  };

  String get wireValue => name;

  /// The Bahasa Indonesia label (Rule 30 — user-facing copy is Indonesian; the
  /// enum name is the technical identifier).
  String get label => switch (this) {
    ServiceUnitKind.kiloan => 'Kiloan',
    ServiceUnitKind.satuan => 'Satuan',
  };

  String get quantityUnitLabel => switch (this) {
    ServiceUnitKind.kiloan => 'gram',
    ServiceUnitKind.satuan => 'item',
  };
}

/// A sellable service in the tenant's catalogue (FR-031).
///
/// CARRIES NO PRICE, DELIBERATELY. A service says WHAT is sold; what it costs
/// lives on a per-brand price list, because FR-034 requires the same service to
/// be priced differently per brand and FR-040 requires exactly one canonical
/// price source. A price field here would be a second place a price could live.
@immutable
final class CatalogService {
  const CatalogService({
    required this.id,
    required this.code,
    required this.name,
    required this.unitKind,
    required this.isActive,
    this.categoryId,
    this.description,
    this.minimumQuantity,
    this.turnaroundHours,
    this.version,
  });

  factory CatalogService.fromJson(Map<String, Object?> json) => CatalogService(
    id: json['id']! as String,
    code: json['code']! as String,
    name: json['name']! as String,
    unitKind: ServiceUnitKind.parse(json['unit_kind']! as String),
    isActive: json['is_active'] as bool? ?? true,
    categoryId: json['service_category_id'] as String?,
    description: json['description'] as String?,
    minimumQuantity: json['minimum_quantity'] as int?,
    turnaroundHours: json['turnaround_hours'] as int?,
    version: json['version'] as String?,
  );

  final String id;
  final String code;
  final String name;
  final ServiceUnitKind unitKind;
  final bool isActive;
  final String? categoryId;
  final String? description;

  /// Grams under [ServiceUnitKind.kiloan], item count under
  /// [ServiceUnitKind.satuan]. Integer in both cases — a floating-point weight
  /// is something the scale and the counter can disagree about.
  final int? minimumQuantity;

  /// DESCRIPTIVE ONLY. Step 4 makes no promise about completion time and
  /// nothing enforces this value. A surface that rendered it as a guarantee
  /// would be claiming a capability the product does not provide (Rule 01).
  final int? turnaroundHours;

  final String? version;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CatalogService && other.id == id && other.version == version);

  @override
  int get hashCode => Object.hash(id, version);

  @override
  String toString() => 'CatalogService($code, ${unitKind.name})';
}

/// A named grouping for the catalogue. Presentation structure only.
@immutable
final class CatalogCategory {
  const CatalogCategory({
    required this.id,
    required this.code,
    required this.name,
    required this.isActive,
    this.displayOrder = 0,
    this.version,
  });

  factory CatalogCategory.fromJson(Map<String, Object?> json) =>
      CatalogCategory(
        id: json['id']! as String,
        code: json['code']! as String,
        name: json['name']! as String,
        isActive: json['is_active'] as bool? ?? true,
        displayOrder: json['display_order'] as int? ?? 0,
        version: json['version'] as String?,
      );

  final String id;
  final String code;
  final String name;
  final bool isActive;
  final int displayOrder;
  final String? version;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CatalogCategory && other.id == id && other.version == version);

  @override
  int get hashCode => Object.hash(id, version);

  @override
  String toString() => 'CatalogCategory($code)';
}

/// An add-on such as express handling (FR-033).
///
/// CATALOGUE ENTRY ONLY. Applying an add-on to an order line is Step 5, and
/// this type carries no order, order-line, or quantity field (DEC-0031 B).
@immutable
final class CatalogAddon {
  const CatalogAddon({
    required this.id,
    required this.code,
    required this.name,
    required this.isActive,
    this.description,
    this.version,
  });

  factory CatalogAddon.fromJson(Map<String, Object?> json) => CatalogAddon(
    id: json['id']! as String,
    code: json['code']! as String,
    name: json['name']! as String,
    isActive: json['is_active'] as bool? ?? true,
    description: json['description'] as String?,
    version: json['version'] as String?,
  );

  final String id;
  final String code;
  final String name;
  final bool isActive;
  final String? description;
  final String? version;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CatalogAddon && other.id == id && other.version == version);

  @override
  int get hashCode => Object.hash(id, version);

  @override
  String toString() => 'CatalogAddon($code)';
}

/// A package composing several services (FR-032).
@immutable
final class CatalogPackage {
  const CatalogPackage({
    required this.id,
    required this.code,
    required this.name,
    required this.isActive,
    this.description,
    this.items = const <PackageComposition>[],
    this.version,
  });

  factory CatalogPackage.fromJson(Map<String, Object?> json) => CatalogPackage(
    id: json['id']! as String,
    code: json['code']! as String,
    name: json['name']! as String,
    isActive: json['is_active'] as bool? ?? true,
    description: json['description'] as String?,
    items: ((json['items'] as List<Object?>?) ?? const <Object?>[])
        .cast<Map<String, Object?>>()
        .map(PackageComposition.fromJson)
        .toList(growable: false),
    version: json['version'] as String?,
  );

  final String id;
  final String code;
  final String name;
  final bool isActive;
  final String? description;

  /// Composition only. The package's PRICE is on a price list (FR-034).
  final List<PackageComposition> items;

  final String? version;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CatalogPackage && other.id == id && other.version == version);

  @override
  int get hashCode => Object.hash(id, version);

  @override
  String toString() => 'CatalogPackage($code, ${items.length} item)';
}

/// One line of a package's composition.
@immutable
final class PackageComposition {
  const PackageComposition({required this.serviceId, required this.quantity});

  factory PackageComposition.fromJson(Map<String, Object?> json) =>
      PackageComposition(
        serviceId: json['service_id']! as String,
        quantity: json['quantity']! as int,
      );

  final String serviceId;
  final int quantity;

  Map<String, Object?> toJson() => <String, Object?>{
    'service_id': serviceId,
    'quantity': quantity,
  };

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PackageComposition &&
          other.serviceId == serviceId &&
          other.quantity == quantity);

  @override
  int get hashCode => Object.hash(serviceId, quantity);

  @override
  String toString() => 'PackageComposition($serviceId x$quantity)';
}
