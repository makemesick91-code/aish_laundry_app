import 'package:aish_core/aish_core.dart';
import 'package:test/test.dart';

void main() {
  group('Environment validation', () {
    Result<Environment> validate({
      String name = 'production',
      String url = 'https://api.contoh-fiktif.id/api/v1',
      String app = 'Aish Laundry',
    }) => Environment.validate(
      environmentName: name,
      apiBaseUrl: url,
      appName: app,
    );

    test('accepts a well-formed https production URL', () {
      final result = validate();
      expect(result.isOk, isTrue);
      expect(result.valueOrNull!.name, EnvironmentName.production);
      expect(result.valueOrNull!.isProduction, isTrue);
    });

    test('rejects an unknown environment name', () {
      final result = validate(name: 'uat');
      expect(result.isErr, isTrue);
      expect(result.failureOrNull!.kind, FailureKind.configuration);
    });

    test('rejects a URL that does not address the versioned API', () {
      // /api/v1 is required, so a build cannot be pointed at an unversioned
      // root and silently start calling whatever lives there.
      final result = validate(url: 'https://api.contoh-fiktif.id');
      expect(result.isErr, isTrue);
      expect(result.failureOrNull!.message, contains('/api/v1'));
    });

    test('rejects a relative URL', () {
      final result = validate(url: '/api/v1');
      expect(result.isErr, isTrue);
    });

    test('rejects a non-http scheme', () {
      final result = validate(url: 'ftp://contoh-fiktif.id/api/v1');
      expect(result.isErr, isTrue);
    });

    test('rejects an empty application name', () {
      expect(validate(app: '   ').isErr, isTrue);
    });

    group('plaintext transport', () {
      test('is permitted for a loopback host in development', () {
        expect(
          validate(
            name: 'development',
            url: 'http://10.0.2.2:8000/api/v1',
          ).isOk,
          isTrue,
        );
        expect(
          validate(
            name: 'development',
            url: 'http://localhost:8000/api/v1',
          ).isOk,
          isTrue,
        );
      });

      test('is REFUSED for a non-loopback development host', () {
        // The temptation is to allow http anywhere in development. That is how
        // a session cookie ends up on the wire on a shared office network.
        final result = validate(
          name: 'development',
          url: 'http://api.contoh-fiktif.id/api/v1',
        );
        expect(result.isErr, isTrue);
        expect(result.failureOrNull!.message, contains('https'));
      });

      test('is REFUSED in staging even for loopback', () {
        expect(
          validate(name: 'staging', url: 'http://localhost:8000/api/v1').isErr,
          isTrue,
        );
      });

      test('is REFUSED in production', () {
        expect(
          validate(name: 'production', url: 'http://localhost/api/v1').isErr,
          isTrue,
        );
      });
    });
  });

  group('Failure', () {
    test('an unknown failure is retryable', () {
      const failure = Failure.unexpected(message: 'apa pun');
      expect(failure.kind, FailureKind.unexpected);
      expect(failure.isRetryable, isTrue);
    });

    test('authorization and validation failures are NOT retryable', () {
      // A retry affordance on a denial teaches an operator to hammer a control
      // that will never work.
      for (final kind in <FailureKind>[
        FailureKind.authorization,
        FailureKind.authentication,
        FailureKind.validation,
        FailureKind.configuration,
        FailureKind.storage,
      ]) {
        expect(
          Failure(kind: kind, message: 'x').isRetryable,
          isFalse,
          reason: '$kind must not be presented as retryable',
        );
      }
    });
  });

  group('Result', () {
    test('map leaves a failure untouched', () {
      const failure = Failure(kind: FailureKind.network, message: 'x');
      final result = const Result<int>.err(failure).map((v) => v * 2);
      expect(result.isErr, isTrue);
      expect(result.failureOrNull, failure);
    });

    test('flatMap chains only on success', () {
      final ok = const Result<int>.ok(2).flatMap((v) => Result<int>.ok(v + 1));
      expect(ok.valueOrNull, 3);
    });

    test('fold collapses both branches', () {
      expect(const Result<int>.ok(1).fold((v) => 'ok', (f) => 'err'), 'ok');
      expect(
        const Result<int>.err(
          Failure(kind: FailureKind.network, message: 'x'),
        ).fold((v) => 'ok', (f) => 'err'),
        'err',
      );
    });
  });

  group('Clock', () {
    test('SystemClock reports UTC', () {
      expect(const SystemClock().nowUtc().isUtc, isTrue);
    });
  });

  group('CorrelationId', () {
    test('generates a distinct value each time', () {
      final a = CorrelationId.generate();
      final b = CorrelationId.generate();
      expect(a.value.length, 32);
      expect(a, isNot(equals(b)));
    });

    test('uses the header name the backend reads', () {
      expect(CorrelationId.headerName, 'X-Request-Id');
    });
  });
}
