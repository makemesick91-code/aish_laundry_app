import 'package:meta/meta.dart';

/// The consent channels Step 4 records (FR-027, FR-028).
///
/// Enumerated rather than free-text because a consent record whose type is a
/// string is a consent record that can be written for a channel nobody defined,
/// and the send-time check that reads it would then silently find nothing.
enum ConsentType {
  marketingWhatsapp('marketing_whatsapp'),
  marketingEmail('marketing_email'),
  marketingSms('marketing_sms');

  const ConsentType(this.wireValue);

  final String wireValue;

  static ConsentType? parse(String? value) {
    for (final type in ConsentType.values) {
      if (type.wireValue == value) {
        return type;
      }
    }
    return null;
  }

  String get label => switch (this) {
    ConsentType.marketingWhatsapp => 'Promosi WhatsApp',
    ConsentType.marketingEmail => 'Promosi email',
    ConsentType.marketingSms => 'Promosi SMS',
  };
}

/// Granted or withdrawn. There is no third state and no "unknown" member.
///
/// The ABSENCE of a record is what "never asked" looks like, and it is
/// represented by a null [ConsentState] rather than by a member here. A
/// `notAsked` member would be writable, and a written "not asked" is
/// indistinguishable from a withdrawal that was recorded wrongly.
enum ConsentState {
  granted('granted'),
  withdrawn('withdrawn');

  const ConsentState(this.wireValue);

  final String wireValue;

  static ConsentState? parse(String? value) {
    for (final state in ConsentState.values) {
      if (state.wireValue == value) {
        return state;
      }
    }
    return null;
  }

  String get label => switch (this) {
    ConsentState.granted => 'Disetujui',
    ConsentState.withdrawn => 'Ditarik',
  };
}

/// Where a consent decision was captured.
///
/// Recorded because "the customer said yes at the counter" and "an importer
/// asserted yes" are not the same evidence, and a later dispute needs to tell
/// them apart.
enum ConsentSource {
  counter('counter'),
  customerApp('customer_app'),
  writtenForm('written_form'),
  phone('phone'),
  importer('import');

  const ConsentSource(this.wireValue);

  final String wireValue;

  static ConsentSource? parse(String? value) {
    for (final source in ConsentSource.values) {
      if (source.wireValue == value) {
        return source;
      }
    }
    return null;
  }

  String get label => switch (this) {
    ConsentSource.counter => 'Konter',
    ConsentSource.customerApp => 'Aplikasi pelanggan',
    ConsentSource.writtenForm => 'Formulir tertulis',
    ConsentSource.phone => 'Telepon',
    ConsentSource.importer => 'Impor data',
  };
}

/// One APPEND-ONLY consent record (invariant C5).
///
/// A withdrawal is a NEW record, never an edit of the record that granted. The
/// history is the evidence: if a customer disputes having consented, the answer
/// is the ordered list of what was recorded, when, by whom, and from where. An
/// editable consent row would make that evidence worthless, which is why this
/// type is immutable, carries no `version`, and has no `copyWith`.
@immutable
final class ConsentRecord {
  const ConsentRecord({
    required this.id,
    required this.type,
    required this.state,
    required this.source,
    this.recordedAt,
    this.recordedByMembershipId,
    this.note,
  });

  factory ConsentRecord.fromJson(Map<String, Object?> json) => ConsentRecord(
    id: json['id']! as String,
    type: ConsentType.parse(json['consent_type'] as String?),
    state: ConsentState.parse(json['state'] as String?),
    source: ConsentSource.parse(json['source'] as String?),
    recordedAt: json['recorded_at'] as String?,
    recordedByMembershipId: json['recorded_by_membership_id'] as String?,
    note: json['note'] as String?,
  );

  final String id;

  /// Null when this build does not recognise the wire value.
  ///
  /// Nullable on purpose, and it mirrors `ApiErrorCode.parse`: a client that
  /// coerced an unknown consent type into a known member would render one
  /// channel's decision under another channel's name. An unrecognised record is
  /// shown as unrecognised.
  final ConsentType? type;
  final ConsentState? state;
  final ConsentSource? source;

  /// Server-assigned. A client-suppliable consent timestamp is a backdated
  /// consent record (threat T-07), so nothing in this package can set it.
  final String? recordedAt;

  final String? recordedByMembershipId;
  final String? note;

  @override
  bool operator ==(Object other) =>
      identical(this, other) || (other is ConsentRecord && other.id == id);

  @override
  int get hashCode => id.hashCode;

  @override
  String toString() => 'ConsentRecord($id, ${type?.wireValue})';
}

/// The consent history for one customer, plus the derived current state.
///
/// [current] is computed BY THE SERVER and read here rather than recomputed. Two
/// implementations of "latest record wins" is two chances to get the tie-break
/// wrong, and the one that matters is the one the send-time check consults.
@immutable
final class ConsentLedger {
  const ConsentLedger({required this.records, required this.current});

  factory ConsentLedger.fromJson(Map<String, Object?> json) {
    final rawRecords = json['consents'];
    final rawCurrent = json['current'];

    final current = <ConsentType, ConsentState?>{};
    if (rawCurrent is Map<String, Object?>) {
      for (final entry in rawCurrent.entries) {
        final type = ConsentType.parse(entry.key);
        if (type != null) {
          current[type] = ConsentState.parse(entry.value as String?);
        }
      }
    }

    return ConsentLedger(
      records: rawRecords is List
          ? rawRecords
                .cast<Map<String, Object?>>()
                .map(ConsentRecord.fromJson)
                .toList(growable: false)
          : const <ConsentRecord>[],
      current: current,
    );
  }

  /// Newest first, as the server ordered them.
  final List<ConsentRecord> records;

  /// The derived current state per channel. A type absent from this map, or
  /// mapped to null, means NEVER ASKED — which is not the same as withdrawn and
  /// must never be rendered as one.
  final Map<ConsentType, ConsentState?> current;

  /// Whether marketing on [type] may be sent right now.
  ///
  /// Defaults to FALSE for a channel with no record. Consent is opt-in, and an
  /// absent record is not a grant (Rule 32 hard rule 22).
  bool allows(ConsentType type) => current[type] == ConsentState.granted;

  @override
  String toString() => 'ConsentLedger(${records.length} records)';
}
