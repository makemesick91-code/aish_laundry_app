import 'package:meta/meta.dart';

/// A customer as a management surface is allowed to see them (FR-021 … FR-026).
///
/// THE PHONE ARRIVES MASKED AND IS NEVER UNMASKED HERE.
/// The server sends `phone_masked` — country code plus last four — and this type
/// has no field for a full number at all. Rule 32 hard rule 5 makes unmasking a
/// deliberate, per-record, permissioned, recorded server action; a client type
/// that could hold the full value would make "unmasked by accident" possible,
/// and a field that does not exist cannot leak.
///
/// THERE IS NO ADDRESS FIELD ON THE SUMMARY EITHER. Rule 32 hard rule 4 forbids
/// rendering an address in a list row, so the list projection does not carry
/// one. That is enforced by the server's allow-list; modelling it the same way
/// here means a careless list widget cannot show one even if the server changed.
@immutable
final class CustomerSummary {
  const CustomerSummary({
    required this.id,
    required this.code,
    required this.name,
    required this.phoneMasked,
    required this.status,
    required this.version,
  });

  factory CustomerSummary.fromJson(Map<String, Object?> json) =>
      CustomerSummary(
        id: json['id']! as String,
        code: json['code']! as String,
        name: json['name']! as String,
        phoneMasked: json['phone_masked'] as String? ?? '',
        status: json['status'] as String? ?? 'active',
        version: json['version'] as String?,
      );

  final String id;

  /// The tenant-scoped human-usable code, e.g. `PLG-000001`.
  ///
  /// NEVER A CREDENTIAL. It identifies a customer inside an already-authorised
  /// tenant scope and grants nothing on its own.
  final String code;

  final String name;

  /// Country code plus last four digits. See the class comment.
  final String phoneMasked;

  /// `active` or `archived`. Archived customers stay resolvable because a
  /// future order may reference them (threat T-18).
  final String status;

  /// The optimistic-concurrency token, echoed back on edit (threat T-12).
  /// Opaque: compare it and return it, never parse it.
  final String? version;

  bool get isArchived => status == 'archived';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CustomerSummary &&
          other.id == id &&
          other.code == code &&
          other.name == name &&
          other.phoneMasked == phoneMasked &&
          other.status == status &&
          other.version == version);

  @override
  int get hashCode =>
      Object.hash(id, code, name, phoneMasked, status, version);

  /// Deliberately omits the masked phone.
  ///
  /// A `toString()` reaches crash reports and diagnostic sinks. Even a masked
  /// number is customer data, and there is no debugging question the code and
  /// the id do not answer better (Rule 46 hard rule 2).
  @override
  String toString() => 'CustomerSummary($id, $code)';
}
