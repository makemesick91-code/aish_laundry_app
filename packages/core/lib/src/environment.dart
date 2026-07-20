import 'package:meta/meta.dart';

import 'failure.dart';
import 'result.dart';

/// Which deployment a build points at.
enum EnvironmentName {
  development,
  staging,
  production;

  static EnvironmentName? tryParse(String raw) {
    for (final value in EnvironmentName.values) {
      if (value.name == raw) {
        return value;
      }
    }
    return null;
  }
}

/// Runtime configuration supplied at build time via `--dart-define`.
///
/// Configuration is VALIDATED AT STARTUP and a surface refuses to run when it is
/// invalid. The alternative — booting with a broken base URL and discovering it
/// at the first request — produces a login screen that fails in a way no user
/// can distinguish from wrong credentials.
///
/// No secret is ever carried here. A `--dart-define` value is embedded in the
/// shipped binary in clear text, so anything placed in it is published. The API
/// base URL is public information; a credential is not, and none is accepted.
@immutable
final class Environment {
  const Environment._({
    required this.name,
    required this.apiBaseUri,
    required this.appName,
  });

  final EnvironmentName name;

  /// Base URI of the versioned API, always ending in `/api/v1`.
  final Uri apiBaseUri;

  /// Human-readable surface name, used in diagnostics only.
  final String appName;

  bool get isProduction => name == EnvironmentName.production;

  /// Validate raw configuration, returning a [Failure] rather than throwing.
  ///
  /// Every rejection is specific about WHAT is wrong, because the person who
  /// reads it is holding a failing build, not a stack trace.
  static Result<Environment> validate({
    required String environmentName,
    required String apiBaseUrl,
    required String appName,
  }) {
    if (appName.trim().isEmpty) {
      return const Result<Environment>.err(
        Failure(kind: FailureKind.configuration, message: 'APP_NAME is empty.'),
      );
    }

    final parsedName = EnvironmentName.tryParse(environmentName.trim());
    if (parsedName == null) {
      return Result<Environment>.err(
        Failure(
          kind: FailureKind.configuration,
          message:
              'ENVIRONMENT must be one of '
              '${EnvironmentName.values.map((e) => e.name).join(', ')}; '
              'got "$environmentName".',
        ),
      );
    }

    if (apiBaseUrl.trim().isEmpty) {
      return const Result<Environment>.err(
        Failure(
          kind: FailureKind.configuration,
          message: 'API_BASE_URL is empty.',
        ),
      );
    }

    final uri = Uri.tryParse(apiBaseUrl.trim());
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      return Result<Environment>.err(
        Failure(
          kind: FailureKind.configuration,
          message: 'API_BASE_URL is not an absolute URL: "$apiBaseUrl".',
        ),
      );
    }

    if (uri.scheme != 'https' && uri.scheme != 'http') {
      return Result<Environment>.err(
        Failure(
          kind: FailureKind.configuration,
          message:
              'API_BASE_URL scheme must be http or https; got "${uri.scheme}".',
        ),
      );
    }

    // Plaintext transport is permitted only against a loopback development
    // host. Anywhere else it would expose a session cookie or bearer token on
    // the wire, and "it is only staging" is not an exemption (Rule 03).
    final isLoopback =
        uri.host == 'localhost' ||
        uri.host == '127.0.0.1' ||
        uri.host == '10.0.2.2'; // Android emulator loopback alias.
    if (uri.scheme == 'http' &&
        !(parsedName == EnvironmentName.development && isLoopback)) {
      return Result<Environment>.err(
        const Failure(
          kind: FailureKind.configuration,
          message:
              'Plaintext http is permitted only for a loopback host in the '
              'development environment. Use https.',
        ),
      );
    }

    if (!uri.path.endsWith('/api/v1')) {
      return Result<Environment>.err(
        Failure(
          kind: FailureKind.configuration,
          message:
              'API_BASE_URL must address the versioned API and end with '
              '/api/v1; got "${uri.path}".',
        ),
      );
    }

    return Result<Environment>.ok(
      Environment._(name: parsedName, apiBaseUri: uri, appName: appName.trim()),
    );
  }

  /// Read configuration from compile-time `--dart-define` values.
  static Result<Environment> fromDartDefines({required String appName}) =>
      validate(
        environmentName: const String.fromEnvironment(
          'ENVIRONMENT',
          defaultValue: 'development',
        ),
        apiBaseUrl: const String.fromEnvironment(
          'API_BASE_URL',
          defaultValue: 'http://10.0.2.2:8000/api/v1',
        ),
        appName: appName,
      );

  @override
  String toString() =>
      'Environment(${name.name}, $appName, ${apiBaseUri.origin}${apiBaseUri.path})';
}
