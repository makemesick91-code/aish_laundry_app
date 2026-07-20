<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\SharedKernel\Logging\RedactSensitiveContext;
use App\Modules\SharedKernel\Support\Redactor;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\Concerns\CapturesLogOutput;
use Tests\TestCase;

/**
 * MATRIX F — Log redaction, asserted against ACTUALLY EMITTED log content.
 *
 * Rule 03, item 20: logs never contain passwords, OTPs, tokens or credentials —
 * "not at debug level, not temporarily, not in a local branch". A log file is
 * the least-guarded copy of a system's secrets: it is read by more people than
 * the database, shipped to more places, and retained longer than anyone intends.
 *
 * WHY THIS MATRIX READS A FILE INSTEAD OF MOCKING THE LOGGER
 * ----------------------------------------------------------
 * Redaction does not happen at the call site. It happens in a Monolog processor
 * attached to the channel's handlers, which runs AFTER `Log::info(...)` returns
 * and BEFORE bytes reach the disk. A `Log::spy()` assertion therefore inspects
 * the arguments the developer passed, not the line that was written — it would
 * pass identically whether the processor is installed, misconfigured, or absent
 * entirely. For a redaction control, only the emitted bytes are evidence.
 *
 * The matrix is deliberately built in two layers so that a failure is
 * attributable rather than merely alarming:
 *
 *   F1 / F2 — the REDACTOR and the PROCESSOR in isolation. If these pass, the
 *             redaction LOGIC is sound.
 *   F3+     — the same secrets driven through the REAL `Log` facade and read
 *             back off disk. If these fail while F1/F2 pass, the logic is fine
 *             and the WIRING is missing — a materially different defect, and
 *             one a mock could never have distinguished.
 *
 * Every value here is fictional (Rule 23).
 */
final class LogRedactionTest extends TestCase
{
    use CapturesLogOutput;

    /**
     * The sensitive shapes that must never survive to disk. Keyed by the log
     * context key; the value is the fictional secret placed under it.
     *
     * @var array<string, string>
     */
    private const SENSITIVE_CONTEXT = [
        'password' => 'placeholder-KataSandiRahasiaUji12345',
        'password_confirmation' => 'placeholder-KataSandiRahasiaUji12345',
        'token' => 'token-rahasia-uji-fiktif-abcdef',
        'access_token' => 'akses-rahasia-uji-fiktif-123456',
        'authorization' => 'Bearer bearer-rahasia-uji-fiktif',
        'cookie' => 'sesi=cookie-rahasia-uji-fiktif',
        'secret' => 'nilai-rahasia-uji-fiktif',
        'client_secret' => 'klien-rahasia-uji-fiktif',
        'otp' => '824193',
        'api_key' => 'kunci-api-rahasia-uji-fiktif',
        'private_key' => 'kunci-privat-rahasia-uji-fiktif',
        'remember_token' => 'ingat-rahasia-uji-fiktif',
    ];

    protected function tearDown(): void
    {
        $this->endLogCapture();
        parent::tearDown();
    }

    // =====================================================================
    // F1 / F2 — the redaction logic in isolation
    // =====================================================================

    public function test_f1_the_redactor_replaces_every_sensitive_key(): void
    {
        $redacted = Redactor::redact(self::SENSITIVE_CONTEXT);

        foreach (self::SENSITIVE_CONTEXT as $key => $secret) {
            $this->assertSame(
                Redactor::PLACEHOLDER,
                $redacted[$key],
                sprintf('Context key "%s" was not redacted.', $key)
            );
            $this->assertStringNotContainsString($secret, json_encode($redacted, JSON_UNESCAPED_SLASHES));
        }

        // Control: non-sensitive values survive. A redactor that blanks
        // everything is not a redactor; it is a broken logger, and it would
        // hide the operational detail logs exist to provide.
        $mixed = Redactor::redact(['order_id' => 'ALS-2026-000042', 'password' => 'rahasia']);
        $this->assertSame('ALS-2026-000042', $mixed['order_id']);
        $this->assertSame(Redactor::PLACEHOLDER, $mixed['password']);
    }

    public function test_f2_the_processor_redacts_nested_and_deeply_nested_context(): void
    {
        $record = new LogRecord(
            datetime: new \Monolog\DateTimeImmutable(true),
            channel: 'testing',
            level: Level::Info,
            message: 'Permintaan masuk',
            context: [
                'request' => [
                    'headers' => ['authorization' => 'Bearer bersarang-rahasia-uji', 'cookie' => 'a=bersarang-cookie-uji'],
                    'body' => ['password' => 'bersarang-sandi-uji', 'identifier' => 'orang@contoh.invalid'],
                ],
                'otp' => '110357',
            ],
        );

        $processed = (new RedactSensitiveContext())($record);
        $blob = json_encode($processed->context, JSON_UNESCAPED_SLASHES);

        foreach (['bersarang-rahasia-uji', 'bersarang-cookie-uji', 'bersarang-sandi-uji', '110357'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $blob, sprintf(
                'Secret "%s" survived the processor at depth.', $secret
            ));
        }

        // Control: the non-sensitive sibling survived, so the assertions above
        // are not passing because the context was discarded wholesale.
        $this->assertStringContainsString('orang@contoh.invalid', (string) $blob);
    }

    // =====================================================================
    // F3 — the real logger, read back off disk
    // =====================================================================

    public function test_f3_secrets_do_not_reach_the_log_file_through_the_real_logger(): void
    {
        $this->beginLogCapture();

        Log::info('Uji redaksi log terstruktur', self::SENSITIVE_CONTEXT);

        $emitted = $this->capturedLog();

        // Control: the record really was written. Without this, every
        // "secret not found" assertion below would pass on an empty file —
        // the classic vacuous redaction proof.
        $this->assertStringContainsString(
            'Uji redaksi log terstruktur',
            $emitted,
            'Control failed: nothing was written to the captured channel, so this matrix would prove nothing.'
        );

        $leaked = [];

        foreach (self::SENSITIVE_CONTEXT as $key => $secret) {
            if (str_contains($emitted, $secret)) {
                $leaked[] = $key;
            }
        }

        $this->assertSame(
            [],
            $leaked,
            sprintf(
                "Sensitive values reached the log file in plaintext under these context keys: %s.\n\n"
                ."Redactor and RedactSensitiveContext both pass in isolation (F1, F2), so the redaction LOGIC "
                ."is correct and the failure is in the WIRING: no log channel installs the processor.\n"
                ."Rule 03 item 20 — logs never contain passwords, OTPs, tokens or credentials.",
                implode(', ', $leaked)
            )
        );
    }

    public function test_f4_secrets_do_not_reach_the_log_file_at_debug_level_either(): void
    {
        $this->beginLogCapture();

        Log::debug('Uji redaksi tingkat debug', self::SENSITIVE_CONTEXT);

        $emitted = $this->capturedLog();

        $this->assertStringContainsString('Uji redaksi tingkat debug', $emitted, 'Control: the debug record must be written.');

        foreach (self::SENSITIVE_CONTEXT as $key => $secret) {
            $this->assertStringNotContainsString(
                $secret,
                $emitted,
                sprintf('Secret under "%s" reached the log at debug level. Rule 03 admits no level exemption.', $key)
            );
        }
    }

    public function test_f5_the_redaction_processor_is_installed_on_the_active_log_channel(): void
    {
        $this->beginLogCapture();

        // Force the channel to build, then inspect what is actually attached.
        Log::info('Bootstrap kanal');

        $logger = Log::channel('single')->getLogger();

        $processors = $logger->getProcessors();

        foreach ($logger->getHandlers() as $handler) {
            if (method_exists($handler, 'getProcessors')) {
                $processors = array_merge($processors, $handler->getProcessors());
            }
        }

        $installed = array_filter(
            $processors,
            static fn ($processor): bool => $processor instanceof RedactSensitiveContext
        );

        $this->assertNotEmpty(
            $installed,
            'No RedactSensitiveContext processor is attached to the active log channel. '
            .'ConfigureLogRedaction exists but is never invoked: config/logging.php declares no `tap` on any '
            .'channel, and nothing in app/, config/ or bootstrap/ references it. The redaction control is '
            .'therefore present in the codebase and absent at runtime.'
        );
    }
}
