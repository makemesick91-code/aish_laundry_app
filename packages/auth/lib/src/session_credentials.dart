import 'package:aish_networking/aish_networking.dart';

/// The live credential and selected context for one signed-in surface.
///
/// This exists to break what would otherwise be a construction cycle:
/// [ApiClient] needs a way to read the current token and the current tenant
/// context, and the service that produces both needs an [ApiClient] to talk to
/// the server. A small holder that both refer to resolves it without either
/// owning the other.
///
/// IN MEMORY ONLY. Nothing here is persistent — durable storage is
/// `SecureCredentialStore`, and the service is what moves values between the
/// two. Keeping this holder non-persistent means a surface that never restores
/// a session never has a token in it.
///
/// The token is deliberately NOT exposed through a getter, a field, or
/// `toString()`. The only way out is [token], which the client calls when it is
/// about to build a request, so a crash dump of this object carries no
/// credential (Rule 46, hard rule 2).
final class SessionCredentials {
  SessionCredentials({String? deviceIdentifier})
    : _context = RequestContext(deviceIdentifier: deviceIdentifier);

  String? _token;
  RequestContext _context;

  /// Reads the current bearer token. Wired to [ApiClient.bearerToken].
  ///
  /// Async to satisfy `BearerTokenProvider`, whose shape allows a future
  /// implementation to read from the keystore per request.
  Future<String?> token() async => _token;

  /// Reads the current tenant/outlet/device hints. Wired to
  /// [ApiClient.requestContext].
  RequestContext context() => _context;

  /// The device identifier, which outlives sign-in and sign-out.
  String? get deviceIdentifier => _context.deviceIdentifier;

  void setToken(String? value) => _token = value;

  void setDeviceIdentifier(String value) =>
      _context = _context.copyWith(deviceIdentifier: value);

  /// Record the selected tenant, clearing any outlet from the previous tenant.
  void selectTenant(String tenantId) =>
      _context = _context.copyWith(tenantId: tenantId, clearOutlet: true);

  void selectOutlet(String outletId) =>
      _context = _context.copyWith(outletId: outletId);

  /// Drop the credential and every tenant-scoped hint.
  ///
  /// The device identifier SURVIVES: it identifies the installation, not the
  /// user, and regenerating it on every sign-out would make per-device
  /// revocation meaningless because the device would return under a new name.
  void clear() {
    _token = null;
    _context = RequestContext(deviceIdentifier: _context.deviceIdentifier);
  }

  /// Never includes the token. Asserted by test, because a `toString()` is
  /// exactly what reaches a crash report.
  @override
  String toString() =>
      'SessionCredentials(hasToken: ${_token != null}, $_context)';
}
