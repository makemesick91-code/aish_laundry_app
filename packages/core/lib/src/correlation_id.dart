import 'dart:math';

import 'package:meta/meta.dart';

/// An opaque identifier that stitches one client operation to its server-side
/// trace.
///
/// The client sends this as `X-Request-Id`; the backend echoes it back in the
/// response envelope's `meta.request_id`. That pairing is what makes a support
/// report actionable without asking a user to reproduce anything.
///
/// A correlation identifier is NOT a credential. It grants nothing, so it is
/// safe to display and to log — which is precisely why diagnostics carry this
/// and never a token.
@immutable
final class CorrelationId {
  const CorrelationId(this.value);

  /// Generate a fresh identifier.
  ///
  /// [random] is injectable so a test can pin the value; production uses a
  /// secure source. Uniqueness, not unpredictability, is the requirement — but
  /// a predictable identifier invites request-log correlation by a third party,
  /// so the secure source is the default rather than the exception.
  factory CorrelationId.generate({Random? random}) {
    final source = random ?? Random.secure();
    final buffer = StringBuffer();
    for (var i = 0; i < 32; i++) {
      buffer.write(_alphabet[source.nextInt(_alphabet.length)]);
    }
    return CorrelationId(buffer.toString());
  }

  static const String _alphabet = '0123456789abcdef';

  /// HTTP header the backend reads and echoes.
  static const String headerName = 'X-Request-Id';

  final String value;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CorrelationId && other.value == value);

  @override
  int get hashCode => value.hashCode;

  @override
  String toString() => value;
}
