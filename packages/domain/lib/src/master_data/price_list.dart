import 'package:meta/meta.dart';

import 'rupiah.dart';

/// Where a price list is in its lifecycle (FR-035).
enum PriceListStatus {
  draft,
  active,
  superseded,
  archived;

  static PriceListStatus parse(String value) => switch (value) {
    'draft' => PriceListStatus.draft,
    'active' => PriceListStatus.active,
    'superseded' => PriceListStatus.superseded,
    'archived' => PriceListStatus.archived,
    _ => throw ArgumentError.value(value, 'status', 'Status daftar harga tidak dikenal'),
  };

  /// Bahasa Indonesia label (Rule 30 rule 3 — status labels come from the
  /// canonical set, rendered in their glossary Indonesian form).
  String get label => switch (this) {
    PriceListStatus.draft => 'Draf',
    PriceListStatus.active => 'Aktif',
    PriceListStatus.superseded => 'Digantikan',
    PriceListStatus.archived => 'Diarsipkan',
  };
}

/// A per-brand, versioned price list (FR-034 … FR-036).
///
/// [isEditable] IS READ FROM THE SERVER, NOT INFERRED FROM [status].
/// The server states it explicitly, and the client honours that rather than
/// deriving it. Deriving would put a second copy of the immutability rule in
/// the client, where it could drift from the one that actually enforces it —
/// and a surface that offered an edit the server would refuse is a dead end
/// (Rule 29).
@immutable
final class PriceListSummary {
  const PriceListSummary({
    required this.id,
    required this.brandId,
    required this.code,
    required this.name,
    required this.status,
    required this.isEditable,
    required this.isDefault,
    this.effectiveFrom,
    this.effectiveUntil,
    this.supersedesPriceListId,
    this.items = const <PriceListEntry>[],
    this.version,
  });

  factory PriceListSummary.fromJson(Map<String, Object?> json) =>
      PriceListSummary(
        id: json['id']! as String,
        brandId: json['laundry_brand_id']! as String,
        code: json['code']! as String,
        name: json['name']! as String,
        status: PriceListStatus.parse(json['status']! as String),
        isEditable: json['is_editable'] as bool? ?? false,
        isDefault: json['is_default'] as bool? ?? false,
        effectiveFrom: json['effective_from'] as String?,
        effectiveUntil: json['effective_until'] as String?,
        supersedesPriceListId: json['supersedes_price_list_id'] as String?,
        items: ((json['items'] as List<Object?>?) ?? const <Object?>[])
            .cast<Map<String, Object?>>()
            .map(PriceListEntry.fromJson)
            .toList(growable: false),
        version: json['version'] as String?,
      );

  final String id;
  final String brandId;
  final String code;
  final String name;
  final PriceListStatus status;

  /// See the class comment: read from the server, never derived.
  final bool isEditable;

  final bool isDefault;

  /// ISO dates. A price list applies to business days, not to instants.
  final String? effectiveFrom;

  /// `null` means open-ended: this list applies until something supersedes it.
  final String? effectiveUntil;

  final String? supersedesPriceListId;

  final List<PriceListEntry> items;

  final String? version;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PriceListSummary &&
          other.id == id &&
          other.version == version &&
          other.items.length == items.length);

  @override
  int get hashCode => Object.hash(id, version, items.length);

  @override
  String toString() => 'PriceListSummary($code, ${status.name})';
}

/// One priced line of a price list (FR-037).
///
/// Exactly one of [serviceId], [packageId], [addonId] is set — a row priced
/// against two targets has no defined meaning, and one priced against none is
/// unusable. The server's CHECK constraint enforces it; this type reflects it.
@immutable
final class PriceListEntry {
  const PriceListEntry({
    required this.id,
    required this.amount,
    this.serviceId,
    this.packageId,
    this.addonId,
    this.version,
  });

  factory PriceListEntry.fromJson(Map<String, Object?> json) => PriceListEntry(
    id: json['id']! as String,
    // Refuses a float or a formatted string rather than coercing — see Rupiah.
    amount: Rupiah.parse(json['amount_rupiah']),
    serviceId: json['service_id'] as String?,
    packageId: json['service_package_id'] as String?,
    addonId: json['service_addon_id'] as String?,
    version: json['version'] as String?,
  );

  final String id;

  /// Integer Rupiah. Never a `double`, at any layer (Rule 04 hard rule 2).
  final Rupiah amount;

  final String? serviceId;
  final String? packageId;
  final String? addonId;
  final String? version;

  /// The id of whichever target this row prices.
  String get targetId => serviceId ?? packageId ?? addonId ?? '';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PriceListEntry &&
          other.id == id &&
          other.amount == amount &&
          other.version == version);

  @override
  int get hashCode => Object.hash(id, amount, version);

  @override
  String toString() => 'PriceListEntry($id)';
}
