import 'package:aish_core/aish_core.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_networking/aish_networking.dart';

import 'backend_auth_service.dart';
import 'session_credentials.dart';

/// The assembled authentication runtime for one surface.
///
/// This is the composition root, and it exists in exactly one place because the
/// defect it closes was a composition failure repeated three times: each
/// application declared an `authServiceProvider` that threw
/// `UnimplementedError`, and nothing ever overrode it outside a test. Three
/// hand-wired copies would be three chances to make that mistake again.
///
/// The wiring order matters and is not incidental. [SessionCredentials] is
/// built first with no credential in it; [ApiClient] is given closures that
/// read from it; [BackendAuthService] is given the client and writes back into
/// the holder. That breaks the construction cycle — the client needs a token
/// the service has not obtained yet — without either object owning the other.
///
/// One [ApiClient] is shared by the service and by every repository the surface
/// later adds, so a tenant switch is observed by all of them at once. A second
/// client built elsewhere would keep addressing the previous tenant.
final class AuthRuntime {
  AuthRuntime._({
    required this.credentials,
    required this.client,
    required this.service,
  });

  /// Assemble the runtime for a surface.
  ///
  /// [transport] decides how the surface proves who it is, and it is the one
  /// genuine difference between the three applications: the Android surfaces
  /// hold a bearer token in the platform keystore, Console Web holds nothing
  /// and relies on an `HttpOnly` cookie the browser manages, because a token in
  /// a browser is readable by any script on the page (Rule 38, hard rule 2).
  ///
  /// [store] defaults to platform secure storage. A test supplies
  /// `InMemoryCredentialStore` instead; there is deliberately no plaintext
  /// option anywhere for either to reach for.
  factory AuthRuntime.create({
    required Environment environment,
    required CredentialTransport transport,
    SecureCredentialStore? store,
    String? deviceName,
    String? platform,
  }) {
    final credentials = SessionCredentials();

    final client = ApiClient(
      environment: environment,
      transport: transport,
      // Closures, not values: both are read when a request is about to be
      // built, so a token obtained after this client was constructed — which is
      // every token — is still picked up, and a tenant switch takes effect on
      // the very next request rather than on the next app launch.
      bearerToken: credentials.token,
      requestContext: credentials.context,
    );

    return AuthRuntime._(
      credentials: credentials,
      client: client,
      service: BackendAuthService(
        client: client,
        credentials: credentials,
        store: store ?? PlatformSecureCredentialStore(),
        transport: transport,
        deviceName: deviceName,
        platform: platform,
      ),
    );
  }

  /// Live credential and selected context. Exposed for wiring and for tests;
  /// application code reads session state from [service] instead.
  final SessionCredentials credentials;

  /// The one HTTP client for this surface.
  final ApiClient client;

  /// The concrete, production authentication service.
  final BackendAuthService service;

  Future<void> dispose() => service.dispose();
}
