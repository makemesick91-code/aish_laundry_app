import 'package:meta/meta.dart';

/// A source of the current instant.
///
/// Nothing in this codebase calls `DateTime.now()` directly. A hard-wired clock
/// makes session-expiry, staleness and freshness logic untestable except by
/// sleeping, and a test that sleeps is a test that is eventually deleted for
/// being slow.
///
/// The server clock remains authoritative for ordering and for anything
/// financial (Rule 07, Rule 20). This abstraction is about the CLIENT's local
/// reading, and client readings are treated as skewed.
abstract interface class Clock {
  /// The current instant in UTC.
  DateTime nowUtc();
}

/// The real clock. The only implementation permitted in production code.
@immutable
final class SystemClock implements Clock {
  const SystemClock();

  @override
  DateTime nowUtc() => DateTime.now().toUtc();
}
