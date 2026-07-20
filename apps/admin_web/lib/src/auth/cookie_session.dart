import 'package:aish_networking/aish_networking.dart';

/// How Console Web proves who it is.
///
/// COOKIE ONLY. This is a hard requirement, not a preference, and it is the
/// reason this file exists at all rather than the surface simply reusing the
/// Android credential path.
///
/// WHY NO BEARER TOKEN IN WEB STORAGE.
/// `localStorage` and `sessionStorage` are readable by ANY JavaScript running on
/// the origin. One injected script — a compromised dependency, a browser
/// extension, a cross-site scripting hole anywhere on the page — reads the token
/// and replays it from anywhere, and the token keeps working because a bearer
/// token is a bearer instrument. An HTTP-only, `Secure`, `SameSite` cookie is not
/// readable by script at all: a successful injection can make requests as the
/// user while the page is open, which is bad, but it cannot EXFILTRATE a
/// long-lived credential, which is worse.
///
/// WHAT THIS CLASS DOES NOT HAVE, deliberately:
///   * no `token` field, getter, setter or parameter;
///   * no read or write of `window.localStorage` or `window.sessionStorage`;
///   * no `Authorization` header construction of any kind.
///
/// There is nothing here to misuse. That is the design: a class with no token
/// field cannot leak a token, however the surface later evolves.
abstract final class ConsoleAuthTransport {
  /// The only transport this surface may use.
  static const CredentialTransport transport =
      CredentialTransport.sessionCookie;

  /// Whether [candidate] is acceptable for Console Web.
  ///
  /// Exists so the rule is machine-checkable by a test rather than being a
  /// paragraph of prose that a future change can quietly contradict.
  static bool isPermitted(CredentialTransport candidate) =>
      candidate == CredentialTransport.sessionCookie;
}
