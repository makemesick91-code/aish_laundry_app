import 'package:aish_core/aish_core.dart';
import 'package:dio/dio.dart';
import 'package:meta/meta.dart';

import 'api_response.dart';
import 'error_mapper.dart';

/// How this client proves who it is.
enum CredentialTransport {
  /// A bearer token held in platform secure storage. Used by the Android
  /// surfaces, where there is a keystore to hold it.
  bearerToken,

  /// A same-site HTTP-only session cookie managed by the browser. Used by
  /// Console Web, where JavaScript-readable storage is reachable by any script
  /// on the page and therefore must never hold a credential.
  sessionCookie,
}

/// Supplies the current bearer token, if the surface uses one.
///
/// A function rather than a stored string so the client never keeps a token in a
/// long-lived field where a `toString()` or a crash dump could reach it.
typedef BearerTokenProvider = Future<String?> Function();

/// The single HTTP entry point to `/api/v1`.
///
/// Three properties are load-bearing:
///
///   1. EVERY request carries a correlation identifier, so a failure a user
///      reports can be found in a server log without reproduction.
///   2. EVERY response — success or failure — is decoded through one envelope
///      reader, so the shape cannot drift per call site.
///   3. NOTHING logs a credential. The client never writes an Authorization
///      header value, a cookie, or a token to any sink, and its own
///      `toString()` cannot expose one because it never stores one.
final class ApiClient {
  ApiClient({
    required Environment environment,
    required this.transport,
    BearerTokenProvider? bearerToken,
    Dio? dio,
    // The parameter is deliberately named `bearerToken`, not `_bearerToken`:
    // an initializing formal would force every caller to pass a private name.
    // ignore: prefer_initializing_formals
  }) : _bearerToken = bearerToken,
       _dio = dio ?? Dio() {
    _dio.options = _dio.options.copyWith(
      baseUrl: _ensureTrailingSlash(environment.apiBaseUri.toString()),
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 20),
      sendTimeout: const Duration(seconds: 20),
      // Never throw on a non-2xx: an error envelope is a normal, decodable
      // outcome, not an exception. Throwing would push envelope handling into
      // catch blocks scattered across the app.
      validateStatus: (_) => true,
      headers: <String, Object?>{
        'Accept': 'application/json',
        // Marks the request as a first-party XHR so Laravel answers with JSON
        // rather than a redirect to a login page.
        'X-Requested-With': 'XMLHttpRequest',
      },
      // Cookie credentials must ride along on web; bearer surfaces never rely
      // on ambient cookies.
      extra: <String, Object?>{
        'withCredentials': transport == CredentialTransport.sessionCookie,
      },
    );
  }

  final Dio _dio;
  final CredentialTransport transport;
  final BearerTokenProvider? _bearerToken;

  static String _ensureTrailingSlash(String value) =>
      value.endsWith('/') ? value : '$value/';

  Future<Result<ApiSuccess>> get(
    String path, {
    Map<String, Object?>? query,
    CorrelationId? correlationId,
  }) => _send(
    () => _dio.get<Object?>(
      path,
      queryParameters: query,
      options: Options(headers: _headers(correlationId)),
    ),
  );

  Future<Result<ApiSuccess>> post(
    String path, {
    Map<String, Object?>? body,
    CorrelationId? correlationId,
    String? expectedVersion,
  }) => _send(
    () => _dio.post<Object?>(
      path,
      data: body,
      options: Options(headers: _headers(correlationId, expectedVersion)),
    ),
  );

  /// Partial update of an existing record.
  ///
  /// [expectedVersion] is the optimistic-concurrency token the caller read with
  /// the record. Sending it means "I am editing the version I saw"; if the
  /// record has moved on, the server answers `CONFLICT` and the edit is refused
  /// rather than silently overwriting somebody else's work (threat T-12).
  ///
  /// A management screen that omits it is choosing last-write-wins, which for
  /// master data governing prices and messaging windows is a defect rather than
  /// a simplification.
  Future<Result<ApiSuccess>> patch(
    String path, {
    Map<String, Object?>? body,
    CorrelationId? correlationId,
    String? expectedVersion,
  }) => _send(
    () => _dio.patch<Object?>(
      path,
      data: body,
      options: Options(headers: _headers(correlationId, expectedVersion)),
    ),
  );

  /// Wholesale replacement — used where a collection is only meaningful as a
  /// whole, such as a service package's composition.
  Future<Result<ApiSuccess>> put(
    String path, {
    Map<String, Object?>? body,
    CorrelationId? correlationId,
    String? expectedVersion,
  }) => _send(
    () => _dio.put<Object?>(
      path,
      data: body,
      options: Options(headers: _headers(correlationId, expectedVersion)),
    ),
  );

  Future<Result<ApiSuccess>> delete(
    String path, {
    CorrelationId? correlationId,
  }) => _send(
    () => _dio.delete<Object?>(
      path,
      options: Options(headers: _headers(correlationId)),
    ),
  );

  /// The optimistic-concurrency precondition header.
  ///
  /// Matches `SharedKernel\Http\OptimisticConcurrency::HEADER` on the server.
  /// The value is an opaque server-issued token: the client compares it and
  /// returns it, and never parses or generates one.
  static const String versionHeaderName = 'If-Unmodified-Since-Version';

  Map<String, Object?> _headers(
    CorrelationId? correlationId, [
    String? expectedVersion,
  ]) => <String, Object?>{
    CorrelationId.headerName: (correlationId ?? CorrelationId.generate()).value,
    if (expectedVersion != null && expectedVersion.isNotEmpty)
      versionHeaderName: expectedVersion,
  };

  Future<Result<ApiSuccess>> _send(
    Future<Response<Object?>> Function() request,
  ) async {
    try {
      final token = await _attachToken();
      final response = await request();
      _detachToken(token);
      return _decode(response);
    } on DioException catch (error) {
      // The message is built from the exception TYPE, never from the request
      // that produced it. Interpolating `error.requestOptions` here would put
      // an Authorization header into an error string, and that string ends up
      // in diagnostics.
      final kind = switch (error.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout => FailureKind.timeout,
        DioExceptionType.connectionError => FailureKind.network,
        DioExceptionType.badCertificate => FailureKind.network,
        _ => FailureKind.unexpected,
      };
      final (failure, _) = ApiErrorMapper.transport(
        kind: kind,
        message: 'Transport failure (${error.type.name}).',
      );
      return Result<ApiSuccess>.err(failure);
    } on Object {
      // Deliberately opaque. An arbitrary object's toString() is not something
      // this layer is willing to put into a failure message.
      return const Result<ApiSuccess>.err(
        Failure(
          kind: FailureKind.unexpected,
          message: 'Unexpected transport error.',
        ),
      );
    }
  }

  Future<String?> _attachToken() async {
    if (transport != CredentialTransport.bearerToken || _bearerToken == null) {
      return null;
    }
    final token = await _bearerToken();
    if (token != null && token.isNotEmpty) {
      _dio.options.headers['Authorization'] = 'Bearer $token';
    }
    return token;
  }

  void _detachToken(String? token) {
    if (token != null) {
      _dio.options.headers.remove('Authorization');
    }
  }

  Result<ApiSuccess> _decode(Response<Object?> response) {
    final status = response.statusCode ?? 0;
    final data = response.data;
    final body = data is Map<String, Object?> ? data : null;

    if (status >= 200 && status < 300) {
      if (body == null) {
        return const Result<ApiSuccess>.err(
          Failure(
            kind: FailureKind.unexpected,
            message:
                'Successful status carried a body that is not an envelope.',
          ),
        );
      }
      final meta = body['meta'];
      return Result<ApiSuccess>.ok(
        ApiSuccess(
          data: body['data'],
          requestId: meta is Map<String, Object?>
              ? meta['request_id'] as String?
              : null,
        ),
      );
    }

    final (failure, _) = ApiErrorMapper.fromEnvelope(
      statusCode: status,
      body: body,
    );
    return Result<ApiSuccess>.err(failure);
  }

  /// Never includes a credential. This is asserted by a test, because a
  /// `toString()` is exactly the thing that reaches a crash report.
  @override
  String toString() => 'ApiClient(${transport.name})';

  @visibleForTesting
  Dio get dioForTest => _dio;
}
