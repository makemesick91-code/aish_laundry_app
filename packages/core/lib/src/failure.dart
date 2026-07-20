import 'package:meta/meta.dart';

/// The closed taxonomy of failure kinds a client surface may observe.
///
/// This enumeration is deliberately small and deliberately CLOSED. Every
/// failure reaching a widget is one of these, so a screen author cannot
/// encounter a kind they never handled. A backend code the client does not
/// recognise maps to [FailureKind.unexpected], which is recoverable — it never
/// maps to a security-relevant kind such as [FailureKind.authorization],
/// because guessing a security meaning from an unknown string is how a client
/// silently downgrades an isolation failure into a retry prompt.
enum FailureKind {
  /// No usable network path. Retrying later is the recovery.
  network,

  /// The request took too long. Retrying is the recovery.
  timeout,

  /// The caller is not authenticated, or its session no longer stands.
  authentication,

  /// The caller is authenticated but the server refused the action, the
  /// tenant, or the outlet. The server is the authority; the client only
  /// reports.
  authorization,

  /// The request was well-formed but its contents were rejected.
  validation,

  /// The caller is being rate limited.
  rateLimited,

  /// The server is reachable but not currently able to serve.
  serviceUnavailable,

  /// Local device storage could not be read or written.
  storage,

  /// The application is misconfigured — for example an absent or malformed
  /// API base URL. This is a startup-blocking condition, not a retry.
  configuration,

  /// Anything the client could not classify. Always treated as recoverable.
  unexpected,
}

/// A failure that crossed a boundary, described in terms a surface can act on.
///
/// [message] is developer-facing. It is never rendered to a user directly:
/// user-facing copy is Bahasa Indonesia written per screen, and a server string
/// pasted into a dialogue is how an internal detail reaches a customer.
@immutable
class Failure {
  const Failure({
    required this.kind,
    required this.message,
    this.code,
    this.correlationId,
    this.details = const <String, Object?>{},
  });

  /// A failure the client could not classify. Fail SAFE: recoverable, never
  /// silently treated as success and never inferred to be a permission result.
  const Failure.unexpected({
    required this.message,
    this.code,
    this.correlationId,
    this.details = const <String, Object?>{},
  }) : kind = FailureKind.unexpected;

  final FailureKind kind;

  /// Developer-facing description. Never rendered verbatim to a user.
  final String message;

  /// The machine-readable server code, when the failure came from the API.
  final String? code;

  /// The request identifier that produced this failure, for support triage.
  final String? correlationId;

  /// Structured, NON-SENSITIVE supplementary data. A token, password, cookie,
  /// OTP or personal datum must never be placed here — this map is eligible for
  /// diagnostics emission.
  final Map<String, Object?> details;

  /// Whether a plain retry of the same operation is a sensible recovery.
  ///
  /// Authorization and validation failures are deliberately NOT retryable: the
  /// same request will be refused again, and a retry affordance on a denial
  /// teaches an operator to hammer a control that will never work.
  bool get isRetryable => switch (kind) {
    FailureKind.network ||
    FailureKind.timeout ||
    FailureKind.rateLimited ||
    FailureKind.serviceUnavailable ||
    FailureKind.unexpected => true,
    FailureKind.authentication ||
    FailureKind.authorization ||
    FailureKind.validation ||
    FailureKind.storage ||
    FailureKind.configuration => false,
  };

  Failure copyWith({String? correlationId}) => Failure(
    kind: kind,
    message: message,
    code: code,
    correlationId: correlationId ?? this.correlationId,
    details: details,
  );

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Failure &&
          other.kind == kind &&
          other.message == message &&
          other.code == code &&
          other.correlationId == correlationId);

  @override
  int get hashCode => Object.hash(kind, message, code, correlationId);

  @override
  String toString() => 'Failure(${kind.name}, code: $code, message: $message)';
}
