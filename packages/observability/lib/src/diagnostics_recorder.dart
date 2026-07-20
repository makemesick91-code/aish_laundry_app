import 'package:aish_core/aish_core.dart';

import 'diagnostic_event.dart';

/// Where diagnostics go.
abstract interface class DiagnosticsRecorder {
  void record(DiagnosticEvent event);
}

/// Builds and records diagnostics with the surface's fixed identity attached.
///
/// Holds the app version, the environment and the clock so a caller supplies
/// only what varies. That is not merely convenience: a caller that has to
/// assemble the whole record every time will eventually assemble one that
/// carries something it should not.
final class Diagnostics {
  const Diagnostics({
    required this.appVersion,
    required this.environment,
    required this.recorder,
    this.clock = const SystemClock(),
  });

  final AppVersion appVersion;
  final EnvironmentName environment;
  final DiagnosticsRecorder recorder;
  final Clock clock;

  void info(String message, {Map<String, Object?> context = const {}}) =>
      _emit(DiagnosticSeverity.info, message, context);

  void warn(String message, {Map<String, Object?> context = const {}}) =>
      _emit(DiagnosticSeverity.warning, message, context);

  void error(String message, {Map<String, Object?> context = const {}}) =>
      _emit(DiagnosticSeverity.error, message, context);

  /// Record a [Failure]. The correlation identifier travels with it, which is
  /// what makes a user's "it failed at about ten past two" into a log lookup.
  void failure(Failure failure) => recorder.record(
    DiagnosticEvent.fromFailure(
      failure,
      appVersion: appVersion,
      environment: environment,
      occurredAtUtc: clock.nowUtc(),
    ),
  );

  void _emit(
    DiagnosticSeverity severity,
    String message,
    Map<String, Object?> context,
  ) => recorder.record(
    DiagnosticEvent(
      severity: severity,
      message: message,
      correlationId: null,
      appVersion: appVersion,
      environment: environment,
      occurredAtUtc: clock.nowUtc(),
      context: context,
    ),
  );
}

/// Keeps records in memory. Used by tests and by an in-app diagnostics view.
final class InMemoryDiagnosticsRecorder implements DiagnosticsRecorder {
  final List<DiagnosticEvent> events = <DiagnosticEvent>[];

  /// Bounded so a long-running Ops shift cannot exhaust memory through
  /// diagnostics alone.
  static const int maxEvents = 500;

  @override
  void record(DiagnosticEvent event) {
    events.add(event);
    if (events.length > maxEvents) {
      events.removeRange(0, events.length - maxEvents);
    }
  }

  void clear() => events.clear();
}
