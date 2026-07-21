import 'package:meta/meta.dart';

/// The tenant, outlet and device identifiers a client attaches to a request.
///
/// EVERY value carried here is an UNTRUSTED HINT. The backend re-derives the
/// caller's real tenant scope from its own membership records on every request
/// and treats these headers as a request, never as proof (Rule 02, hard rule 9;
/// Rule 39, hard rule 1). Sending `X-Tenant-Id: t_other` gets a caller nothing
/// except a refusal.
///
/// This type exists because a bearer-token surface has no server-side session in
/// which the backend could remember a selection: `ResolveTenantContext` reads
/// `X-Tenant-Id` for token clients and the session for cookie clients. Without
/// it, an Android surface can authenticate and then reach no tenant-scoped
/// endpoint at all, because it has no way to say which tenant it selected.
///
/// NOTHING SECRET BELONGS HERE. These identifiers travel in plain headers and
/// are recorded in server access logs by design. A token, a password, or an OTP
/// must never be routed through this type (Rule 46, hard rule 2).
@immutable
final class RequestContext {
  const RequestContext({this.tenantId, this.outletId, this.deviceIdentifier});

  /// No context selected. The honest state before a tenant has been chosen.
  static const RequestContext none = RequestContext();

  /// The tenant the client believes it selected. Re-verified server-side.
  final String? tenantId;

  /// The outlet the client believes it selected, within [tenantId].
  final String? outletId;

  /// A stable per-installation identifier, so a single device can be revoked
  /// without disturbing the user's other devices. Never an authorization
  /// signal (Rule 31, hard rule 12).
  final String? deviceIdentifier;

  /// The headers this context contributes. Absent values contribute nothing
  /// rather than an empty header, because an empty `X-Tenant-Id` reads to the
  /// server as a supplied-but-blank selection.
  Map<String, String> toHeaders() => <String, String>{
    if (_usable(tenantId)) 'X-Tenant-Id': tenantId!.trim(),
    if (_usable(outletId)) 'X-Outlet-Id': outletId!.trim(),
    if (_usable(deviceIdentifier)) 'X-Device-Id': deviceIdentifier!.trim(),
  };

  static bool _usable(String? value) =>
      value != null && value.trim().isNotEmpty;

  RequestContext copyWith({
    String? tenantId,
    String? outletId,
    String? deviceIdentifier,
    bool clearTenant = false,
    bool clearOutlet = false,
  }) => RequestContext(
    tenantId: clearTenant ? null : (tenantId ?? this.tenantId),
    // Clearing the tenant necessarily clears the outlet: an outlet from the
    // previous tenant must never survive a switch (Rule 28, hard rule 3).
    outletId: (clearTenant || clearOutlet) ? null : (outletId ?? this.outletId),
    deviceIdentifier: deviceIdentifier ?? this.deviceIdentifier,
  );

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is RequestContext &&
          other.tenantId == tenantId &&
          other.outletId == outletId &&
          other.deviceIdentifier == deviceIdentifier);

  @override
  int get hashCode => Object.hash(tenantId, outletId, deviceIdentifier);

  @override
  String toString() => 'RequestContext(tenant: $tenantId, outlet: $outletId)';
}
