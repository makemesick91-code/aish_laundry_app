<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\SharedKernel\Support\Redactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Structured-log and audit redaction.
 *
 * Rule 03 hard rule 20: logs never contain passwords, OTPs, tokens, or
 * credentials — "not at debug level, not temporarily, not in a local branch".
 * Redaction is applied by KEY anywhere in a structure, at any depth, because a
 * credential nested three levels down is still a credential.
 */
final class RedactorTest extends TestCase
{
    /**
     * @return list<array{string}>
     */
    public static function sensitiveKeys(): array
    {
        return [
            ['password'],
            ['Password'],
            ['user_password'],
            ['passwd'],
            ['token'],
            ['access_token'],
            ['refresh_token'],
            ['remember_token'],
            ['authorization'],
            ['Authorization'],
            ['auth_header'],
            ['cookie'],
            ['Cookie'],
            ['secret'],
            ['client_secret'],
            ['otp'],
            ['otp_code'],
            ['credential'],
            ['private_key'],
            ['api_key'],
            ['apikey'],
            ['session_id'],
            ['signature'],
        ];
    }

    #[DataProvider('sensitiveKeys')]
    public function test_a_sensitive_key_is_redacted(string $key): void
    {
        $redacted = Redactor::redact([$key => 'nilai-rahasia-yang-tidak-boleh-tercatat']);

        $this->assertSame(Redactor::PLACEHOLDER, $redacted[$key]);
    }

    public function test_redaction_reaches_nested_structures(): void
    {
        $redacted = Redactor::redact([
            'request' => [
                'headers' => [
                    'authorization' => 'Bearer rahasia',
                    'cookie' => 'session=rahasia',
                ],
                'body' => [
                    'user' => ['password' => 'rahasia', 'name' => 'Budi Contoh'],
                ],
            ],
        ]);

        $this->assertSame(Redactor::PLACEHOLDER, $redacted['request']['headers']['authorization']);
        $this->assertSame(Redactor::PLACEHOLDER, $redacted['request']['headers']['cookie']);
        $this->assertSame(Redactor::PLACEHOLDER, $redacted['request']['body']['user']['password']);

        // Non-sensitive values survive: redaction that destroys everything makes
        // the log useless and gets switched off.
        $this->assertSame('Budi Contoh', $redacted['request']['body']['user']['name']);
    }

    public function test_no_secret_survives_anywhere_in_the_encoded_output(): void
    {
        $encoded = json_encode(Redactor::redact([
            'password' => 'rahasia-satu',
            'nested' => ['api_key' => 'rahasia-dua', 'deep' => ['otp' => 'rahasia-tiga']],
        ]));

        foreach (['rahasia-satu', 'rahasia-dua', 'rahasia-tiga'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $encoded);
        }
    }

    public function test_an_ordinary_key_is_left_alone(): void
    {
        $redacted = Redactor::redact([
            'tenant_id' => 'tenant-aaa',
            'action' => 'auth.login.succeeded',
            'count' => 3,
        ]);

        $this->assertSame('tenant-aaa', $redacted['tenant_id']);
        $this->assertSame('auth.login.succeeded', $redacted['action']);
        $this->assertSame(3, $redacted['count']);
    }

    public function test_key_detection_is_case_insensitive(): void
    {
        $this->assertTrue(Redactor::isSensitiveKey('PASSWORD'));
        $this->assertTrue(Redactor::isSensitiveKey('Authorization'));
        $this->assertFalse(Redactor::isSensitiveKey('tenant_id'));
    }

    public function test_a_phone_number_is_masked_to_its_last_digits(): void
    {
        $masked = Redactor::maskPhone('081234567890');

        $this->assertNotSame('081234567890', $masked);
        $this->assertStringContainsString('7890', (string) $masked);
    }

    public function test_an_email_is_masked(): void
    {
        $masked = Redactor::maskEmail('budi.contoh@contoh.invalid');

        $this->assertNotSame('budi.contoh@contoh.invalid', $masked);
        $this->assertStringContainsString('@', (string) $masked);
    }

    public function test_masking_tolerates_a_null(): void
    {
        $this->assertNull(Redactor::maskPhone(null));
        $this->assertNull(Redactor::maskEmail(null));
    }
}
