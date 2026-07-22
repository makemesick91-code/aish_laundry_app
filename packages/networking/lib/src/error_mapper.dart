import 'package:aish_core/aish_core.dart';

import 'api_error_code.dart';

/// The client-visible consequence of a server error.
///
/// Deliberately separate from [ApiErrorCode]: the wire code says what the server
/// decided, this says what the CLIENT must now do about it. Several distinct
/// wire codes collapse to the same consequence, and several sessions-ending
/// codes must stay distinct because the recovery copy differs.
enum ClientErrorConsequence {
  /// No credible session. Return to sign-in.
  requiresAuthentication,

  /// The session existed and has expired. Sign in again; do NOT discard queued
  /// work.
  sessionExpired,

  /// The session was revoked server-side, deliberately, by somebody.
  sessionRevoked,

  /// This device's access was revoked. Distinguished from a session revocation
  /// because the recovery differs: another device may still be fine.
  deviceRevoked,

  /// Membership in the active tenant is suspended.
  membershipSuspended,

  /// Membership in the active tenant is revoked.
  membershipRevoked,

  /// The tenant or outlet context was refused. Re-select a context.
  contextAccessDenied,

  /// Authenticated, context valid, action refused.
  accessDenied,

  /// The submitted data was rejected.
  validationFailed,

  /// The record changed underneath the caller since they read it.
  ///
  /// Distinct from [validationFailed] because the recovery differs and the
  /// interface must differ with it: nothing the caller typed is wrong, so no
  /// field is highlighted, and the action offered is RELOAD — never retry.
  /// Retrying the same payload is exactly what would overwrite the other
  /// person's edit (threat T-12).
  staleWrite,

  /// Too many requests. Back off.
  rateLimited,

  /// The session's CSRF protection failed — web only. Re-authenticate.
  csrfFailed,

  /// The service is temporarily unavailable.
  serviceUnavailable,

  /// The client could not reach the service at all.
  networkUnavailable,

  /// Anything else. ALWAYS recoverable, never security-relevant.
  recoverableUnknown,
}

/// Translates the backend error envelope into a [Failure] plus a
/// [ClientErrorConsequence].
///
/// FAIL-SAFE CONTRACT. An error code this build does not recognise maps to
/// [ClientErrorConsequence.recoverableUnknown] with [FailureKind.unexpected].
/// It must NEVER be guessed into a session-ending or permission-shaped
/// consequence. Guessing in the permissive direction would let an unknown code
/// look like success; guessing in the restrictive direction would log a user out
/// because the server added a code. Neither is acceptable, so unknown codes are
/// treated as a transient problem the user can retry.
abstract final class ApiErrorMapper {
  /// Map a decoded error envelope.
  ///
  /// [body] is the parsed JSON body, if any. [statusCode] is the HTTP status.
  static (Failure, ClientErrorConsequence) fromEnvelope({
    required int? statusCode,
    required Map<String, Object?>? body,
  }) {
    final error = body?['error'];
    final meta = body?['meta'];

    final String? rawCode = error is Map<String, Object?>
        ? error['code'] as String?
        : null;
    final String? serverMessage = error is Map<String, Object?>
        ? error['message'] as String?
        : null;
    final String? requestId = meta is Map<String, Object?>
        ? meta['request_id'] as String?
        : null;

    final Map<String, Object?> details =
        error is Map<String, Object?> &&
            error['details'] is Map<String, Object?>
        ? error['details']! as Map<String, Object?>
        : const <String, Object?>{};

    final code = ApiErrorCode.parse(rawCode);

    if (code == null) {
      // The fail-safe branch. Note what is NOT done here: no inference from the
      // HTTP status that a 401 without a known code means "logged out", and no
      // inference that a 403 means a permission failure. A status alone is not
      // a decision this client is entitled to interpret.
      return (
        Failure.unexpected(
          message:
              'Unrecognised API error'
              '${rawCode == null ? '' : ' code "$rawCode"'}'
              '${statusCode == null ? '' : ' (HTTP $statusCode)'}.',
          code: rawCode,
          correlationId: requestId,
        ),
        ClientErrorConsequence.recoverableUnknown,
      );
    }

    final (kind, consequence) = _classify(code);

    return (
      Failure(
        kind: kind,
        // The server message is developer-facing context, never user copy.
        message: serverMessage ?? code.wireValue,
        code: code.wireValue,
        correlationId: requestId,
        details: details,
      ),
      consequence,
    );
  }

  /// Recover the consequence of a [Failure] that has already been mapped.
  ///
  /// This exists so a caller holding only a `Result.err` — which is every caller
  /// of [ApiClient], because its methods return `Result<ApiSuccess>` — can still
  /// learn what the failure MEANS for the session without a second network call
  /// and without a second copy of the mapping table.
  ///
  /// It routes through exactly the same [_classify] switch as [fromEnvelope], so
  /// the two can never disagree. Adding a code to one adds it to both.
  ///
  /// FAIL-SAFE, identically to [fromEnvelope]. A failure carrying no recognised
  /// code resolves to a TRANSIENT consequence, never a session-ending one — the
  /// HTTP status and the [FailureKind] are deliberately NOT consulted to guess a
  /// security meaning. A `FailureKind.authentication` with an unknown code means
  /// "this build does not understand what the server said", and logging a user
  /// out on that basis would let an unrecognised string terminate sessions.
  static ClientErrorConsequence consequenceOf(Failure failure) {
    final code = ApiErrorCode.parse(failure.code);
    if (code != null) {
      final (_, consequence) = _classify(code);
      return consequence;
    }

    // No recognised code: this is a transport-level or unclassifiable failure.
    // Mirror the mapping [transport] already applies for the same kinds.
    return switch (failure.kind) {
      FailureKind.network ||
      FailureKind.timeout => ClientErrorConsequence.networkUnavailable,
      FailureKind.serviceUnavailable =>
        ClientErrorConsequence.serviceUnavailable,
      FailureKind.authentication ||
      FailureKind.authorization ||
      FailureKind.validation ||
      FailureKind.rateLimited ||
      FailureKind.storage ||
      FailureKind.configuration ||
      FailureKind.unexpected => ClientErrorConsequence.recoverableUnknown,
    };
  }

  /// The single mapping from a recognised wire code to what it means.
  ///
  /// Kept private and shared rather than inlined, because two copies of this
  /// table would drift the first time a code is added to one of them.
  static (FailureKind, ClientErrorConsequence) _classify(
    ApiErrorCode code,
  ) => switch (code) {
    ApiErrorCode.unauthenticated => (
      FailureKind.authentication,
      ClientErrorConsequence.requiresAuthentication,
    ),
    ApiErrorCode.sessionExpired => (
      FailureKind.authentication,
      ClientErrorConsequence.sessionExpired,
    ),
    ApiErrorCode.sessionRevoked => (
      FailureKind.authentication,
      ClientErrorConsequence.sessionRevoked,
    ),
    ApiErrorCode.deviceRevoked => (
      FailureKind.authentication,
      ClientErrorConsequence.deviceRevoked,
    ),
    ApiErrorCode.membershipSuspended => (
      FailureKind.authorization,
      ClientErrorConsequence.membershipSuspended,
    ),
    ApiErrorCode.membershipRevoked => (
      FailureKind.authorization,
      ClientErrorConsequence.membershipRevoked,
    ),
    // Tenant and outlet denial share a consequence on purpose. A client that
    // rendered them differently would tell the user which of the two exists,
    // and denial must be indistinguishable from absence across a tenant
    // boundary (Rule 32 hard rule 2).
    ApiErrorCode.tenantAccessDenied || ApiErrorCode.outletAccessDenied => (
      FailureKind.authorization,
      ClientErrorConsequence.contextAccessDenied,
    ),
    ApiErrorCode.forbidden => (
      FailureKind.authorization,
      ClientErrorConsequence.accessDenied,
    ),
    // NOT_FOUND is deliberately mapped to the same consequence as FORBIDDEN.
    // Across a tenant boundary the server answers "not found" for a record
    // that exists in another tenant; a client that rendered a distinct
    // "missing" state would leak the distinction the server just hid.
    ApiErrorCode.notFound => (
      FailureKind.authorization,
      ClientErrorConsequence.accessDenied,
    ),
    ApiErrorCode.validationFailed => (
      FailureKind.validation,
      ClientErrorConsequence.validationFailed,
    ),
    // A STALE WRITE, AND DELIBERATELY NOT RETRYABLE (threat T-12).
    //
    // Mapped to FailureKind.validation because that kind is non-retryable — see
    // `Failure.isRetryable`. Falling through to `unexpected` would mark it
    // RETRYABLE, and a surface offering "coba lagi" would resend the same
    // payload and silently overwrite the edit that caused the conflict. The
    // recovery is to reload and re-apply, never to retry.
    ApiErrorCode.conflict => (
      FailureKind.validation,
      ClientErrorConsequence.staleWrite,
    ),
    ApiErrorCode.rateLimited => (
      FailureKind.rateLimited,
      ClientErrorConsequence.rateLimited,
    ),
    ApiErrorCode.csrfFailed => (
      FailureKind.authentication,
      ClientErrorConsequence.csrfFailed,
    ),
    ApiErrorCode.serviceUnavailable => (
      FailureKind.serviceUnavailable,
      ClientErrorConsequence.serviceUnavailable,
    ),
    ApiErrorCode.methodNotAllowed || ApiErrorCode.internalError => (
      FailureKind.unexpected,
      ClientErrorConsequence.recoverableUnknown,
    ),
  };

  /// Map a transport-level problem that never reached an envelope.
  static (Failure, ClientErrorConsequence) transport({
    required FailureKind kind,
    required String message,
    String? correlationId,
  }) => (
    Failure(kind: kind, message: message, correlationId: correlationId),
    switch (kind) {
      FailureKind.network ||
      FailureKind.timeout => ClientErrorConsequence.networkUnavailable,
      FailureKind.serviceUnavailable =>
        ClientErrorConsequence.serviceUnavailable,
      _ => ClientErrorConsequence.recoverableUnknown,
    },
  );
}
