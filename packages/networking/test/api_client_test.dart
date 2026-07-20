import 'package:aish_core/aish_core.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:dio/dio.dart';
import 'package:test/test.dart';

Environment env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://api.contoh-fiktif.id/api/v1',
  appName: 'Uji',
).valueOrNull!;

/// Captures the request and replies with a scripted response.
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter(this.statusCode, this.body);

  final int statusCode;
  final Object? body;
  RequestOptions? lastRequest;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    lastRequest = options;
    return ResponseBody.fromString(
      body == null ? '' : _encode(body!),
      statusCode,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  static String _encode(Object value) {
    // Minimal, dependency-free encoder for the two shapes used here.
    if (value is String) {
      return value;
    }
    throw UnsupportedError('fixture must be a pre-encoded JSON string');
  }

  @override
  void close({bool force = false}) {}
}

ApiClient clientWith(
  // ignore: library_private_types_in_public_api
  _ScriptedAdapter adapter, {
  CredentialTransport transport = CredentialTransport.bearerToken,
  BearerTokenProvider? token,
}) {
  final dio = Dio();
  dio.httpClientAdapter = adapter;
  return ApiClient(
    environment: env(),
    transport: transport,
    bearerToken: token,
    dio: dio,
  );
}

void main() {
  group('ApiClient envelope decoding', () {
    test('decodes a success envelope and its request id', () async {
      final adapter = _ScriptedAdapter(
        200,
        '{"data":{"id":"x"},"meta":{"request_id":"req_fiktif_00000000"}}',
      );
      final result = await clientWith(adapter).get('auth/me');
      expect(result.isOk, isTrue);
      expect(result.valueOrNull!.requestId, 'req_fiktif_00000000');
      expect(result.valueOrNull!.dataAsMap['id'], 'x');
    });

    test('decodes an error envelope into a classified Failure', () async {
      final adapter = _ScriptedAdapter(
        403,
        '{"error":{"code":"TENANT_ACCESS_DENIED","message":"x"},'
        '"meta":{"request_id":"req_fiktif_00000000"}}',
      );
      final result = await clientWith(adapter).get('context/outlets');
      expect(result.isErr, isTrue);
      expect(result.failureOrNull!.kind, FailureKind.authorization);
      expect(result.failureOrNull!.code, 'TENANT_ACCESS_DENIED');
    });

    test('a non-2xx does not throw — it decodes', () async {
      final adapter = _ScriptedAdapter(
        503,
        '{"error":{"code":"SERVICE_UNAVAILABLE","message":"x"},"meta":{}}',
      );
      final result = await clientWith(adapter).get('health');
      expect(result.isErr, isTrue);
      expect(result.failureOrNull!.kind, FailureKind.serviceUnavailable);
    });
  });

  group('ApiClient request identity', () {
    test('every request carries an X-Request-Id header', () async {
      final adapter = _ScriptedAdapter(200, '{"data":{},"meta":{}}');
      await clientWith(adapter).get('health');
      expect(adapter.lastRequest!.headers[CorrelationId.headerName], isNotNull);
    });

    test('a supplied correlation id is used verbatim', () async {
      final adapter = _ScriptedAdapter(200, '{"data":{},"meta":{}}');
      await clientWith(
        adapter,
      ).get('health', correlationId: const CorrelationId('req_fiktif_dipilih'));
      expect(
        adapter.lastRequest!.headers[CorrelationId.headerName],
        'req_fiktif_dipilih',
      );
    });
  });

  group('ApiClient credential handling', () {
    test('never exposes a token through toString', () async {
      final client = clientWith(
        _ScriptedAdapter(200, '{"data":{},"meta":{}}'),
        token: () async => 'rahasia_token_fiktif_jangan_bocor',
      );
      expect(client.toString(), isNot(contains('rahasia')));
      expect(client.toString(), isNot(contains('Bearer')));
    });

    test(
      'removes the Authorization header after a request completes',
      () async {
        final adapter = _ScriptedAdapter(200, '{"data":{},"meta":{}}');
        final client = clientWith(adapter, token: () async => 'token_fiktif');
        await client.get('auth/me');
        // The header must not linger on the shared Dio instance, where a later
        // request to a different host could pick it up.
        expect(client.dioForTest.options.headers['Authorization'], isNull);
      },
    );

    test('a cookie-transport client attaches no bearer token at all', () async {
      final adapter = _ScriptedAdapter(200, '{"data":{},"meta":{}}');
      final client = clientWith(
        adapter,
        transport: CredentialTransport.sessionCookie,
        token: () async => 'token_yang_tidak_boleh_dipakai',
      );
      await client.get('auth/me');
      expect(adapter.lastRequest!.headers['Authorization'], isNull);
    });

    test(
      'cookie transport requests credentials; bearer transport does not',
      () async {
        final cookieClient = clientWith(
          _ScriptedAdapter(200, '{"data":{},"meta":{}}'),
          transport: CredentialTransport.sessionCookie,
        );
        final bearerClient = clientWith(
          _ScriptedAdapter(200, '{"data":{},"meta":{}}'),
        );
        expect(
          cookieClient.dioForTest.options.extra['withCredentials'],
          isTrue,
        );
        expect(
          bearerClient.dioForTest.options.extra['withCredentials'],
          isFalse,
        );
      },
    );
  });

  group('ApiClient transport failure', () {
    test('a transport error message carries no request detail', () async {
      final dio = Dio();
      dio.httpClientAdapter = _ThrowingAdapter();
      final client = ApiClient(
        environment: env(),
        transport: CredentialTransport.bearerToken,
        bearerToken: () async => 'token_fiktif_rahasia',
        dio: dio,
      );
      final result = await client.get('auth/me');
      expect(result.isErr, isTrue);
      expect(result.failureOrNull!.message, isNot(contains('token_fiktif')));
      expect(result.failureOrNull!.message, isNot(contains('Bearer')));
    });
  });
}

class _ThrowingAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) => throw DioException.connectionError(
    requestOptions: options,
    reason: 'jaringan fiktif mati',
  );

  @override
  void close({bool force = false}) {}
}
