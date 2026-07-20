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

    final (kind, consequence) = switch (code) {
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
