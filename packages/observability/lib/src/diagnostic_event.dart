import 'package:aish_core/aish_core.dart';
import 'package:meta/meta.dart';

import 'redaction.dart';

/// How serious a diagnostic is.
enum DiagnosticSeverity { debug, info, warning, error }

/// One diagnostic record, redacted at CONSTRUCTION.
///
/// Redaction happens in the constructor rather than at emission time on purpose.
/// If it happened at emission, an unredacted instance would exist in memory
/// between the two moments, and every new sink would have to remember to
/// redact. Here, an unredacted [DiagnosticEvent] cannot be built.
@immutable
final class DiagnosticEvent {
  DiagnosticEvent({
    required this.severity,
    required String message,
    required this.correlationId,
    required this.appVersion,
    required this.environment,
    required this.occurredAtUtc,
    Map<String, Object?> context = const <String, Object?>{},
    this.failureKind,
    this.failureCode,
  }) : message = Redaction.text(message),
       context = Map<String, Object?>.unmodifiable(Redaction.map(context));

  final DiagnosticSeverity severity;

  /// Developer-facing, already redacted.
  final String message;

  /// Ties this record to a server-side trace. Not a credential.
  final CorrelationId? correlationId;

  final AppVersion appVersion;

  final EnvironmentName environment;

  final DateTime occurredAtUtc;

  /// Supplementary structured data, already redacted.
  final Map<String, Object?> context;

  final FailureKind? failureKind;

  /// The server error code, e.g. `TENANT_ACCESS_DENIED`. A code is a
  /// classification, never a secret.
  final String? failureCode;

  /// Build a record from a [Failure].
  factory DiagnosticEvent.fromFailure(
    Failure failure, {
    required AppVersion appVersion,
    required EnvironmentName environment,
    required DateTime occurredAtUtc,
    DiagnosticSeverity severity = DiagnosticSeverity.error,
  }) => DiagnosticEvent(
    severity: severity,
    message: failure.message,
    correlationId: failure.correlationId == null
        ? null
        : CorrelationId(failure.correlationId!),
    appVersion: appVersion,
    environment: environment,
    occurredAtUtc: occurredAtUtc,
    context: failure.details,
    failureKind: failure.kind,
    failureCode: failure.code,
  );

  /// A single-line form suitable for a console or a file sink.
  String format() => <String>[
    occurredAtUtc.toIso8601String(),
    severity.name.toUpperCase(),
    environment.name,
    appVersion.display,
    if (correlationId != null) 'req=${correlationId!.value}',
    if (failureKind != null) 'kind=${failureKind!.name}',
    if (failureCode != null) 'code=$failureCode',
    message,
  ].join(' | ');

  @override
  String toString() => format();
}
