import 'package:aish_core/aish_core.dart';

/// A clock a test drives by hand.
///
/// Time only moves when the test moves it. That is what lets a session-expiry
/// test run in microseconds instead of sleeping, and it removes the class of
/// flaky failure where a test passes on a fast machine and fails in CI.
final class FakeClock implements Clock {
  FakeClock(DateTime initial) : _now = initial.toUtc();

  /// A fixed, obviously fictional starting instant.
  factory FakeClock.atEpochStart() =>
      FakeClock(DateTime.utc(2026, 7, 20, 9, 0, 0));

  DateTime _now;

  @override
  DateTime nowUtc() => _now;

  /// Move time forward. Rejects a negative jump: a clock that can run backwards
  /// makes an expiry test pass for the wrong reason.
  void advance(Duration duration) {
    if (duration.isNegative) {
      throw ArgumentError.value(duration, 'duration', 'must not be negative');
    }
    _now = _now.add(duration);
  }

  void setTo(DateTime instant) => _now = instant.toUtc();
}
