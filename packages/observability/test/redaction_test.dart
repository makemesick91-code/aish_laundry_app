import 'package:aish_core/aish_core.dart';
import 'package:aish_observability/aish_observability.dart';
import 'package:test/test.dart';

const AppVersion version = AppVersion(
  surface: 'uji',
  semanticVersion: '0.1.0',
  buildNumber: '1',
);

void main() {
  group('Redaction of field names', () {
    test('removes the value of every sensitive field', () {
      final redacted = Redaction.map(<String, Object?>{
        'authorization': 'Bearer abc',
        'password': 'kata sandi',
        'otp': '123456',
        'trackingToken': 'tok',
        'phone': '08120000000',
        'address': 'Jalan Fiktif 1',
        'safe_field': 'nilai biasa',
      });
      for (final key in <String>[
        'authorization',
        'password',
        'otp',
        'trackingToken',
        'phone',
        'address',
      ]) {
        expect(redacted[key], Redaction.placeholder, reason: key);
      }
      expect(redacted['safe_field'], 'nilai biasa');
    });

    test('field matching ignores case and separators', () {
      for (final name in <String>[
        'access_token',
        'accessToken',
        'ACCESS-TOKEN',
        'Access Token',
      ]) {
        expect(Redaction.isSensitiveField(name), isTrue, reason: name);
      }
    });

    test('walks nested maps and lists', () {
      final redacted = Redaction.map(<String, Object?>{
        'outer': <String, Object?>{
          'password': 'rahasia',
          'list': <Object?>[
            <String, Object?>{'otp': '000000'},
          ],
        },
      });
      final outer = redacted['outer']! as Map<String, Object?>;
      expect(outer['password'], Redaction.placeholder);
      final list = outer['list']! as List<Object?>;
      expect(
        (list.first! as Map<String, Object?>)['otp'],
        Redaction.placeholder,
      );
    });
  });

  group('Redaction of value shapes in free text', () {
    test('removes a bearer token', () {
      final out = Redaction.text('gagal dengan Bearer abc.def-ghi_jkl123');
      expect(out, isNot(contains('abc.def')));
      expect(out, contains(Redaction.placeholder));
    });

    test('removes a Sanctum-shaped personal access token', () {
      final out = Redaction.text('token 42|aBcDeFgHiJkLmNoPqRsTuVwXyZ012345');
      expect(out, isNot(contains('aBcDeFgHiJkLmNoPqRsTuVwXyZ')));
    });

    test('removes a long opaque secret-shaped string', () {
      final out = Redaction.text('nilai ${'a' * 48} terekam');
      expect(out, isNot(contains('a' * 48)));
    });

    test('removes an Indonesian mobile number', () {
      for (final number in <String>[
        '081200000000',
        '+6281200000000',
        '6281200000000',
      ]) {
        expect(Redaction.text('hubungi $number'), isNot(contains(number)));
      }
    });

    test('removes an email address', () {
      expect(
        Redaction.text('kirim ke nama@contoh-fiktif.id sekarang'),
        isNot(contains('nama@contoh-fiktif.id')),
      );
    });

    test('leaves ordinary text alone', () {
      const message = 'Gagal memuat daftar outlet.';
      expect(Redaction.text(message), message);
    });
  });

  group('DiagnosticEvent redacts at CONSTRUCTION', () {
    test('an unredacted event cannot be built', () {
      final event = DiagnosticEvent(
        severity: DiagnosticSeverity.error,
        message: 'gagal dengan Bearer rahasia_panjang_sekali_1234567890',
        correlationId: const CorrelationId('req_fiktif'),
        appVersion: version,
        environment: EnvironmentName.production,
        occurredAtUtc: DateTime.utc(2026, 7, 20),
        context: <String, Object?>{'password': 'rahasia'},
      );
      expect(event.message, isNot(contains('rahasia_panjang')));
      expect(event.context['password'], Redaction.placeholder);
    });

    test('the formatted line carries identity but never a credential', () {
      final event = DiagnosticEvent.fromFailure(
        const Failure(
          kind: FailureKind.authorization,
          message: 'ditolak untuk Bearer token_rahasia_yang_panjang_sekali',
          code: 'TENANT_ACCESS_DENIED',
          correlationId: 'req_fiktif_00000000',
        ),
        appVersion: version,
        environment: EnvironmentName.production,
        occurredAtUtc: DateTime.utc(2026, 7, 20),
      );
      final line = event.format();
      // Present: what triage needs.
      expect(line, contains('req_fiktif_00000000'));
      expect(line, contains('TENANT_ACCESS_DENIED'));
      expect(line, contains('uji 0.1.0+1'));
      // Absent: the credential.
      expect(line, isNot(contains('token_rahasia')));
    });

    test(
      'an error code is kept — a code is a classification, not a secret',
      () {
        final event = DiagnosticEvent.fromFailure(
          const Failure(
            kind: FailureKind.authorization,
            message: 'x',
            code: 'MEMBERSHIP_REVOKED',
          ),
          appVersion: version,
          environment: EnvironmentName.staging,
          occurredAtUtc: DateTime.utc(2026, 7, 20),
        );
        expect(event.failureCode, 'MEMBERSHIP_REVOKED');
      },
    );
  });

  group('InMemoryDiagnosticsRecorder', () {
    test('is bounded so a long shift cannot exhaust memory', () {
      final recorder = InMemoryDiagnosticsRecorder();
      final diagnostics = Diagnostics(
        appVersion: version,
        environment: EnvironmentName.development,
        recorder: recorder,
      );
      for (var i = 0; i < InMemoryDiagnosticsRecorder.maxEvents + 50; i++) {
        diagnostics.info('pesan $i');
      }
      expect(recorder.events.length, InMemoryDiagnosticsRecorder.maxEvents);
    });
  });
}
