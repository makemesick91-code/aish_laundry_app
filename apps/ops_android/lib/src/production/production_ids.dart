import 'dart:math';

/// Generate a fresh RFC-4122 version-4 UUID for a `client_reference`.
///
/// The production API validates `client_reference` as a UUID, so a plain random
/// hex string (as a correlation id uses) would be rejected. Generated ONCE per
/// logical operator action and reused on every retry — never regenerated, which
/// is the defining rule of the offline design (Rule 07 rule 1). A secure source
/// is used: uniqueness is the requirement, but a predictable reference invites
/// idempotency-key guessing, so unpredictability is the cheap default.
String newClientReference() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
  bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant 10xx
  String hex(int start, int end) {
    final buffer = StringBuffer();
    for (var i = start; i < end; i++) {
      buffer.write(bytes[i].toRadixString(16).padLeft(2, '0'));
    }
    return buffer.toString();
  }

  return '${hex(0, 4)}-${hex(4, 6)}-${hex(6, 8)}-${hex(8, 10)}-${hex(10, 16)}';
}
