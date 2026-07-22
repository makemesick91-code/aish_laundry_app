import 'package:meta/meta.dart';

/// An outlet's Step 4 configuration (FR-041 … FR-047).
///
/// EVERY TIME-OF-DAY HERE IS LOCAL WALL CLOCK, AND [timezone] TRAVELS WITH IT.
/// `08:00` on its own is meaningless — read in the viewer's zone it is a
/// different moment from the one the outlet meant. The timezone is therefore a
/// required field rather than an optional decoration, and every rendering of an
/// opening or quiet-hours time is expected to show it (FR-041, Rule 43).
@immutable
final class OutletMasterData {
  const OutletMasterData({
    required this.id,
    required this.name,
    required this.code,
    required this.timezone,
    required this.isActive,
    required this.quietHoursStart,
    required this.quietHoursEnd,
    this.brandId,
    this.addressLine,
    this.contactPhone,
    this.operatingHours = const <String, OperatingDay>{},
    this.dailyCapacityKg,
    this.dailyCapacityOrders,
    this.version,
  });

  factory OutletMasterData.fromJson(Map<String, Object?> json) {
    final quiet =
        (json['quiet_hours'] as Map<String, Object?>?) ??
        const <String, Object?>{};

    final hours = <String, OperatingDay>{};
    final rawHours = json['operating_hours'] as Map<String, Object?>?;

    if (rawHours != null) {
      for (final entry in rawHours.entries) {
        hours[entry.key] = OperatingDay.fromJson(
          (entry.value as Map<String, Object?>?) ?? const <String, Object?>{},
        );
      }
    }

    return OutletMasterData(
      id: json['id']! as String,
      name: json['name']! as String,
      code: json['code']! as String,
      timezone: json['timezone'] as String? ?? 'Asia/Jakarta',
      isActive: json['is_active'] as bool? ?? true,
      quietHoursStart: quiet['start'] as String? ?? '20:00',
      quietHoursEnd: quiet['end'] as String? ?? '08:00',
      brandId: json['laundry_brand_id'] as String?,
      addressLine: json['address_line'] as String?,
      contactPhone: json['contact_phone'] as String?,
      operatingHours: hours,
      dailyCapacityKg: json['daily_capacity_kg'] as int?,
      dailyCapacityOrders: json['daily_capacity_orders'] as int?,
      version: json['version'] as String?,
    );
  }

  final String id;
  final String name;
  final String code;

  /// The IANA identifier that makes every wall-clock field on this object mean
  /// something. Indonesia spans WIB, WITA and WIT, so this is load-bearing.
  final String timezone;

  final bool isActive;

  /// FR-047 — canonical default 20.00–08.00 outlet local time. The window
  /// spans midnight; a renderer that reads it as `start <= t < end` shows an
  /// empty window.
  final String quietHoursStart;
  final String quietHoursEnd;

  final String? brandId;
  final String? addressLine;
  final String? contactPhone;

  /// Keyed by weekday name. A MISSING key means "not configured" and a present
  /// key with `isOpen: false` means "closed" — different facts, and a surface
  /// must be able to tell them apart (Rule 29 — an empty state says why).
  final Map<String, OperatingDay> operatingHours;

  /// FR-042. Descriptive in Step 4: nothing schedules against it, because
  /// scheduling is production operations in Step 6.
  final int? dailyCapacityKg;
  final int? dailyCapacityOrders;

  final String? version;

  bool get hasOperatingHours => operatingHours.isNotEmpty;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutletMasterData && other.id == id && other.version == version);

  @override
  int get hashCode => Object.hash(id, version);

  @override
  String toString() => 'OutletMasterData($code, $timezone)';
}

/// One weekday of an outlet's opening pattern (FR-041).
@immutable
final class OperatingDay {
  const OperatingDay({required this.isOpen, this.opensAt, this.closesAt});

  factory OperatingDay.fromJson(Map<String, Object?> json) => OperatingDay(
    isOpen: json['is_open'] as bool? ?? false,
    opensAt: json['opens_at'] as String?,
    closesAt: json['closes_at'] as String?,
  );

  final bool isOpen;

  /// Local wall clock. Null when closed — a closed day carries no times, so a
  /// reopened outlet cannot inherit last year's hours.
  final String? opensAt;
  final String? closesAt;

  Map<String, Object?> toJson() => <String, Object?>{
    'is_open': isOpen,
    if (isOpen) 'opens_at': opensAt,
    if (isOpen) 'closes_at': closesAt,
  };

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OperatingDay &&
          other.isOpen == isOpen &&
          other.opensAt == opensAt &&
          other.closesAt == closesAt);

  @override
  int get hashCode => Object.hash(isOpen, opensAt, closesAt);

  @override
  String toString() =>
      isOpen ? 'OperatingDay($opensAt-$closesAt)' : 'OperatingDay(tutup)';
}

/// A pickup and delivery COVERAGE area (FR-043).
///
/// Coverage, not routing. Nothing here sequences a stop, estimates an arrival,
/// or assigns a courier — that is Step 8, and Rule 09 hard rule 1 forbids
/// claiming an optimisation the product does not implement.
@immutable
final class OutletServiceZone {
  const OutletServiceZone({
    required this.id,
    required this.outletId,
    required this.code,
    required this.name,
    required this.isActive,
    this.description,
    this.postalCodes = const <String>[],
    this.version,
  });

  factory OutletServiceZone.fromJson(Map<String, Object?> json) =>
      OutletServiceZone(
        id: json['id']! as String,
        outletId: json['outlet_id']! as String,
        code: json['code']! as String,
        name: json['name']! as String,
        isActive: json['is_active'] as bool? ?? true,
        description: json['description'] as String?,
        postalCodes:
            ((json['postal_codes'] as List<Object?>?) ?? const <Object?>[])
                .cast<String>()
                .toList(growable: false),
        version: json['version'] as String?,
      );

  final String id;
  final String outletId;
  final String code;
  final String name;
  final bool isActive;
  final String? description;
  final List<String> postalCodes;
  final String? version;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutletServiceZone &&
          other.id == id &&
          other.version == version);

  @override
  int get hashCode => Object.hash(id, version);

  @override
  String toString() => 'OutletServiceZone($code)';
}

/// A named working shift (FR-044).
///
/// DEFINITION ONLY. Shift closing, expected-versus-actual cash, and the
/// variance that must be recorded rather than absorbed are Step 5.
@immutable
final class OutletShift {
  const OutletShift({
    required this.id,
    required this.outletId,
    required this.code,
    required this.name,
    required this.startsAt,
    required this.endsAt,
    required this.crossesMidnight,
    required this.isActive,
    this.version,
  });

  factory OutletShift.fromJson(Map<String, Object?> json) => OutletShift(
    id: json['id']! as String,
    outletId: json['outlet_id']! as String,
    code: json['code']! as String,
    name: json['name']! as String,
    startsAt: json['starts_at']! as String,
    endsAt: json['ends_at']! as String,
    crossesMidnight: json['crosses_midnight'] as bool? ?? false,
    isActive: json['is_active'] as bool? ?? true,
    version: json['version'] as String?,
  );

  final String id;
  final String outletId;
  final String code;
  final String name;

  /// Local wall clock, in the outlet's timezone.
  final String startsAt;
  final String endsAt;

  /// Stated by the server rather than inferred from the two times, so a surface
  /// never has to decide what 22:00–06:00 means.
  final bool crossesMidnight;

  final bool isActive;
  final String? version;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutletShift && other.id == id && other.version == version);

  @override
  int get hashCode => Object.hash(id, version);

  @override
  String toString() => 'OutletShift($code)';
}

/// A configured printing DEVICE (FR-045).
///
/// A device, not a document. What a printer eventually prints is FR-052 in
/// Step 5, and this type names no document (DEC-0030).
@immutable
final class OutletPrinter {
  const OutletPrinter({
    required this.id,
    required this.outletId,
    required this.code,
    required this.name,
    required this.deviceKind,
    required this.connectionKind,
    required this.isDefault,
    required this.isActive,
    this.deviceIdentifier,
    this.version,
  });

  factory OutletPrinter.fromJson(Map<String, Object?> json) => OutletPrinter(
    id: json['id']! as String,
    outletId: json['outlet_id']! as String,
    code: json['code']! as String,
    name: json['name']! as String,
    deviceKind: json['device_kind']! as String,
    connectionKind: json['connection_kind']! as String,
    isDefault: json['is_default'] as bool? ?? false,
    isActive: json['is_active'] as bool? ?? true,
    deviceIdentifier: json['device_identifier'] as String?,
    version: json['version'] as String?,
  );

  final String id;
  final String outletId;
  final String code;
  final String name;
  final String deviceKind;
  final String connectionKind;
  final bool isDefault;
  final bool isActive;

  /// A device address — a Bluetooth name, a USB path, a network address. NEVER
  /// a credential; a printer needing authentication reads that from the
  /// environment (Rule 03 hard rule 10).
  final String? deviceIdentifier;

  final String? version;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutletPrinter && other.id == id && other.version == version);

  @override
  int get hashCode => Object.hash(id, version);

  @override
  String toString() => 'OutletPrinter($code, $deviceKind)';
}
