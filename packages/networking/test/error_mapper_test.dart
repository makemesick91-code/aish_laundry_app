import 'package:aish_core/aish_core.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:test/test.dart';

Map<String, Object?> envelope(String? code) => <String, Object?>{
  'error': <String, Object?>{'code': ?code, 'message': 'Pesan fiktif.'},
  'meta': <String, Object?>{'request_id': 'req_fiktif_00000000'},
};

void main() {
  group('ApiErrorCode.parse', () {
    test('parses every code the backend emits', () {
      const wireValues = <String>[
        'UNAUTHENTICATED',
        'SESSION_EXPIRED',
        'SESSION_REVOKED',
        'DEVICE_REVOKED',
        'MEMBERSHIP_SUSPENDED',
        'MEMBERSHIP_REVOKED',
        'TENANT_ACCESS_DENIED',
        'OUTLET_ACCESS_DENIED',
        'FORBIDDEN',
        'VALIDATION_FAILED',
        'RATE_LIMITED',
        'CSRF_FAILED',
        'SERVICE_UNAVAILABLE',
        'NOT_FOUND',
        'METHOD_NOT_ALLOWED',
        'INTERNAL_ERROR',
      ];
      for (final value in wireValues) {
        expect(
          ApiErrorCode.parse(value),
          isNotNull,
          reason: '$value must be recognised',
        );
      }
    });

    test('returns null — not a fallback member — for an unknown code', () {
      expect(ApiErrorCode.parse('SOMETHING_NEW_FROM_THE_SERVER'), isNull);
      expect(ApiErrorCode.parse(null), isNull);
    });
  });

  group('ApiErrorMapper — the required mappings', () {
    void expectMapping(
      String code,
      FailureKind kind,
      ClientErrorConsequence consequence,
    ) {
      final (failure, actual) = ApiErrorMapper.fromEnvelope(
        statusCode: 401,
        body: envelope(code),
      );
      expect(failure.kind, kind, reason: '$code kind');
      expect(actual, consequence, reason: '$code consequence');
      expect(failure.code, code);
      expect(failure.correlationId, 'req_fiktif_00000000');
    }

    test(
      'UNAUTHENTICATED',
      () => expectMapping(
        'UNAUTHENTICATED',
        FailureKind.authentication,
        ClientErrorConsequence.requiresAuthentication,
      ),
    );

    test(
      'SESSION_EXPIRED',
      () => expectMapping(
        'SESSION_EXPIRED',
        FailureKind.authentication,
        ClientErrorConsequence.sessionExpired,
      ),
    );

    test(
      'SESSION_REVOKED',
      () => expectMapping(
        'SESSION_REVOKED',
        FailureKind.authentication,
        ClientErrorConsequence.sessionRevoked,
      ),
    );

    test(
      'DEVICE_REVOKED',
      () => expectMapping(
        'DEVICE_REVOKED',
        FailureKind.authentication,
        ClientErrorConsequence.deviceRevoked,
      ),
    );

    test(
      'MEMBERSHIP_SUSPENDED',
      () => expectMapping(
        'MEMBERSHIP_SUSPENDED',
        FailureKind.authorization,
        ClientErrorConsequence.membershipSuspended,
      ),
    );

    test(
      'MEMBERSHIP_REVOKED',
      () => expectMapping(
        'MEMBERSHIP_REVOKED',
        FailureKind.authorization,
        ClientErrorConsequence.membershipRevoked,
      ),
    );

    test(
      'TENANT_ACCESS_DENIED',
      () => expectMapping(
        'TENANT_ACCESS_DENIED',
        FailureKind.authorization,
        ClientErrorConsequence.contextAccessDenied,
      ),
    );

    test(
      'OUTLET_ACCESS_DENIED',
      () => expectMapping(
        'OUTLET_ACCESS_DENIED',
        FailureKind.authorization,
        ClientErrorConsequence.contextAccessDenied,
      ),
    );

    test(
      'FORBIDDEN',
      () => expectMapping(
        'FORBIDDEN',
        FailureKind.authorization,
        ClientErrorConsequence.accessDenied,
      ),
    );

    test(
      'VALIDATION_FAILED',
      () => expectMapping(
        'VALIDATION_FAILED',
        FailureKind.validation,
        ClientErrorConsequence.validationFailed,
      ),
    );

    test(
      'RATE_LIMITED',
      () => expectMapping(
        'RATE_LIMITED',
        FailureKind.rateLimited,
        ClientErrorConsequence.rateLimited,
      ),
    );

    test(
      'CSRF_FAILED',
      () => expectMapping(
        'CSRF_FAILED',
        FailureKind.authentication,
        ClientErrorConsequence.csrfFailed,
      ),
    );

    test(
      'SERVICE_UNAVAILABLE',
      () => expectMapping(
        'SERVICE_UNAVAILABLE',
        FailureKind.serviceUnavailable,
        ClientErrorConsequence.serviceUnavailable,
      ),
    );
  });

  group('ApiErrorMapper — fail-safe behaviour', () {
    test('an UNKNOWN code becomes a recoverable, non-security failure', () {
      final (failure, consequence) = ApiErrorMapper.fromEnvelope(
        statusCode: 403,
        body: envelope('SOME_FUTURE_CODE'),
      );
      // The critical assertions: it did NOT guess a permission meaning from
      // the 403, and it did NOT end the session.
      expect(consequence, ClientErrorConsequence.recoverableUnknown);
      expect(failure.kind, FailureKind.unexpected);
      expect(failure.isRetryable, isTrue);
      expect(failure.kind, isNot(FailureKind.authorization));
      expect(failure.kind, isNot(FailureKind.authentication));
    });

    test('a 401 with no code is NOT inferred to be a logout', () {
      final (failure, consequence) = ApiErrorMapper.fromEnvelope(
        statusCode: 401,
        body: envelope(null),
      );
      expect(consequence, ClientErrorConsequence.recoverableUnknown);
      expect(failure.kind, FailureKind.unexpected);
    });

    test('a completely absent body is handled without throwing', () {
      final (failure, consequence) = ApiErrorMapper.fromEnvelope(
        statusCode: 500,
        body: null,
      );
      expect(consequence, ClientErrorConsequence.recoverableUnknown);
      expect(failure.kind, FailureKind.unexpected);
    });

    test('a malformed error member is handled without throwing', () {
      final (_, consequence) = ApiErrorMapper.fromEnvelope(
        statusCode: 500,
        body: <String, Object?>{'error': 'not a map'},
      );
      expect(consequence, ClientErrorConsequence.recoverableUnknown);
    });
  });

  group('ApiErrorMapper — cross-tenant disclosure', () {
    test('NOT_FOUND is indistinguishable from FORBIDDEN', () {
      // Across a tenant boundary the server answers "not found" for a record
      // that exists elsewhere. A client that rendered a distinct "missing"
      // state would leak the distinction the server just hid.
      final (_, notFound) = ApiErrorMapper.fromEnvelope(
        statusCode: 404,
        body: envelope('NOT_FOUND'),
      );
      final (_, forbidden) = ApiErrorMapper.fromEnvelope(
        statusCode: 403,
        body: envelope('FORBIDDEN'),
      );
      expect(notFound, forbidden);
    });

    test('tenant and outlet denial share one consequence', () {
      final (_, tenant) = ApiErrorMapper.fromEnvelope(
        statusCode: 403,
        body: envelope('TENANT_ACCESS_DENIED'),
      );
      final (_, outlet) = ApiErrorMapper.fromEnvelope(
        statusCode: 403,
        body: envelope('OUTLET_ACCESS_DENIED'),
      );
      expect(tenant, outlet);
    });
  });

  group('ApiErrorMapper.transport', () {
    test('network and timeout become networkUnavailable', () {
      final (_, network) = ApiErrorMapper.transport(
        kind: FailureKind.network,
        message: 'x',
      );
      final (_, timeout) = ApiErrorMapper.transport(
        kind: FailureKind.timeout,
        message: 'x',
      );
      expect(network, ClientErrorConsequence.networkUnavailable);
      expect(timeout, ClientErrorConsequence.networkUnavailable);
    });
  });
}
