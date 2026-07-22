/// The machine-readable error codes the backend emits in its error envelope.
///
/// These mirror `App\Modules\SharedKernel\Http\ErrorCode` on the server. The two
/// lists are kept aligned deliberately rather than generated, because a client
/// that silently absorbs a NEW server code is precisely the failure this type
/// exists to prevent: [ApiErrorCode.parse] returns `null` for anything absent
/// here, and the caller must then fail safe.
enum ApiErrorCode {
  unauthenticated('UNAUTHENTICATED'),
  sessionExpired('SESSION_EXPIRED'),
  sessionRevoked('SESSION_REVOKED'),
  deviceRevoked('DEVICE_REVOKED'),
  membershipSuspended('MEMBERSHIP_SUSPENDED'),
  membershipRevoked('MEMBERSHIP_REVOKED'),
  tenantAccessDenied('TENANT_ACCESS_DENIED'),
  outletAccessDenied('OUTLET_ACCESS_DENIED'),
  forbidden('FORBIDDEN'),
  validationFailed('VALIDATION_FAILED'),
  rateLimited('RATE_LIMITED'),
  csrfFailed('CSRF_FAILED'),
  serviceUnavailable('SERVICE_UNAVAILABLE'),
  notFound('NOT_FOUND'),
  methodNotAllowed('METHOD_NOT_ALLOWED'),

  /// The caller is editing a version of a record that is no longer current
  /// (threat T-12).
  ///
  /// Distinct from [validationFailed] on purpose: nothing the caller SENT is
  /// wrong, so a surface must not highlight a field. What changed is the record
  /// underneath them. Critically, it is also NOT retryable — resending the same
  /// payload is precisely what would silently overwrite somebody else's edit.
  conflict('CONFLICT'),

  internalError('INTERNAL_ERROR');

  const ApiErrorCode(this.wireValue);

  /// The exact string on the wire.
  final String wireValue;

  /// Parse a wire value, returning `null` when this build does not know it.
  ///
  /// Returning `null` rather than a fallback member is the whole point. A
  /// fallback member would let an unknown code be pattern-matched as though it
  /// were understood; a `null` forces the caller to route it through the
  /// fail-safe path.
  static ApiErrorCode? parse(String? raw) {
    if (raw == null) {
      return null;
    }
    for (final code in ApiErrorCode.values) {
      if (code.wireValue == raw) {
        return code;
      }
    }
    return null;
  }
}
