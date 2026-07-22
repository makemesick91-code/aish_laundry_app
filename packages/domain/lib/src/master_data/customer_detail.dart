import 'package:meta/meta.dart';

import 'address_precision.dart';
import 'customer_summary.dart';

/// A customer as the DETAIL projection carries them (FR-021 … FR-030).
///
/// Everything [CustomerSummary] guarantees still holds — the phone is masked and
/// there is no field for a full number — plus the three things a detail view
/// legitimately needs and a list row must never have: the email, the
/// staff-facing internal note, and the addresses.
///
/// WHY ADDRESSES LIVE HERE AND NOWHERE ELSE
/// ----------------------------------------
/// Rule 32 hard rule 4 forbids rendering an address in a list row at all, not
/// merely masking it there. The summary type therefore has no address field, and
/// this type does. A widget that only ever receives a [CustomerSummary] cannot
/// render an address even by mistake, which is the point of splitting them.
///
/// `internalNotes` is STAFF-FACING BY DEFINITION and never reaches a customer
/// surface (FR-030). It is carried here because the Ops counter is a staff
/// surface; the public tracking projection is a different, allow-listed shape
/// entirely and shares no code with this one.
@immutable
final class CustomerDetail {
  const CustomerDetail({
    required this.summary,
    required this.addresses,
    this.email,
    this.internalNotes,
  });

  factory CustomerDetail.fromJson(Map<String, Object?> json) {
    final raw = json['addresses'];

    return CustomerDetail(
      summary: CustomerSummary.fromJson(json),
      email: json['email'] as String?,
      internalNotes: json['internal_notes'] as String?,
      addresses: raw is List
          ? raw
                .cast<Map<String, Object?>>()
                .map(CustomerAddress.fromJson)
                .toList(growable: false)
          : const <CustomerAddress>[],
    );
  }

  /// The fields shared with the list projection. Composition rather than
  /// inheritance so a `CustomerDetail` cannot be passed where a summary-only
  /// widget is expected and quietly widen what that widget can render.
  final CustomerSummary summary;

  final String? email;

  /// Staff-facing only. See the class comment.
  final String? internalNotes;

  final List<CustomerAddress> addresses;

  String get id => summary.id;
  String get code => summary.code;
  String get name => summary.name;
  String get phoneMasked => summary.phoneMasked;
  String get status => summary.status;
  String? get version => summary.version;
  bool get isArchived => summary.isArchived;

  /// The address a pickup would default to, if the customer has one.
  CustomerAddress? get primaryAddress {
    for (final address in addresses) {
      if (address.isPrimary && address.isActive) {
        return address;
      }
    }
    return null;
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CustomerDetail &&
          other.summary == summary &&
          other.email == email &&
          other.internalNotes == internalNotes &&
          other.addresses.length == addresses.length);

  @override
  int get hashCode => Object.hash(summary, email, addresses.length);

  /// Carries neither the email, the note, nor any address component.
  ///
  /// A `toString()` reaches crash reports. Every field omitted here is one that
  /// would be a privacy incident in a diagnostic sink (Rule 46 hard rule 2).
  @override
  String toString() => 'CustomerDetail($id, $code)';
}

/// One address belonging to a customer.
///
/// A `RESTRICTED`-class value (Rule 21 anchor 16). It is rendered at full
/// precision only on a detail surface, to a role with a pickup or delivery
/// reason, and never in a list row (Rule 32 hard rule 4).
@immutable
final class CustomerAddress {
  const CustomerAddress({
    required this.id,
    required this.label,
    required this.addressLine,
    required this.isPrimary,
    required this.isActive,
    required this.isPickupSuitable,
    required this.isDeliverySuitable,
    required this.precision,
    this.version,
    this.district,
    this.city,
    this.province,
    this.postalCode,
    this.notes,
  });

  factory CustomerAddress.fromJson(Map<String, Object?> json) =>
      CustomerAddress(
        id: json['id']! as String,
        label: json['label'] as String? ?? '',
        addressLine: json['address_line'] as String? ?? '',
        district: json['district'] as String?,
        city: json['city'] as String?,
        province: json['province'] as String?,
        postalCode: json['postal_code'] as String?,
        notes: json['notes'] as String?,
        isPickupSuitable: json['is_pickup_suitable'] as bool? ?? false,
        isDeliverySuitable: json['is_delivery_suitable'] as bool? ?? false,
        isPrimary: json['is_primary'] as bool? ?? false,
        isActive: json['is_active'] as bool? ?? true,
        version: json['version'] as String?,
        // FAIL CLOSED on an unrecognised or absent precision. A build that does
        // not know what the server sent must assume it holds the LEAST it is
        // entitled to, not the most: treating an unknown marker as `full` would
        // render a street the server may not have intended to disclose.
        precision: AddressPrecision.parse(json['precision'] as String?),
      );

  final String id;

  /// What the customer calls this address — "Rumah", "Kantor".
  final String label;

  final String addressLine;
  final String? district;
  final String? city;
  final String? province;
  final String? postalCode;
  final String? notes;

  /// Whether a pickup may be scheduled here. Step 4 RECORDS this; scheduling a
  /// pickup against it is Step 8.
  final bool isPickupSuitable;
  final bool isDeliverySuitable;

  final bool isPrimary;
  final bool isActive;

  /// The optimistic-concurrency token read with this address.
  ///
  /// Null on a projection that carries no version. An edit submitted without one
  /// is choosing last-write-wins, which for a delivery address means a parcel at
  /// the wrong house (threat T-12).
  final String? version;

  /// HOW MUCH of this address the server chose to disclose (FR-025).
  ///
  /// Carried explicitly rather than inferred from which fields happen to be
  /// null. A null `addressLine` could mean "masked" or "never filled in", and a
  /// UI that guesses will eventually guess wrong in the disclosing direction.
  final AddressPrecision precision;

  /// The administrative tail, joined for display. Deliberately excludes
  /// [addressLine]: a caller that wants the street must ask for it explicitly,
  /// so a coarse-grained render cannot pick up the precise one by accident.
  String get areaSummary => <String?>[
    district,
    city,
    province,
    postalCode,
  ].where((part) => part != null && part.isNotEmpty).join(', ');

  @override
  bool operator ==(Object other) =>
      identical(this, other) || (other is CustomerAddress && other.id == id);

  @override
  int get hashCode => id.hashCode;

  /// Carries the id and nothing locatable.
  @override
  String toString() => 'CustomerAddress($id)';
}
