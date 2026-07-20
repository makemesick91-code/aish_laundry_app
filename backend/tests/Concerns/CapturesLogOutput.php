<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Capture what the application ACTUALLY WRITES to a log channel.
 *
 * Why a real file and not `Log::spy()` / `Log::shouldReceive()`:
 *
 * A mock intercepts the CALL. It records the arguments the caller passed and
 * then throws the rest away. But redaction does not happen at the call site —
 * it happens in a Monolog PROCESSOR attached to the channel's handlers, which
 * runs after the call and before the bytes hit the disk. A spy therefore proves
 * only "the developer passed a password to Log::info", which is not the
 * question. The question is "did a password reach the log file", and the only
 * honest way to answer it is to read the file.
 *
 * This is the difference between testing intent and testing outcome. For a
 * redaction control, only the outcome counts.
 */
trait CapturesLogOutput
{
    private ?string $capturedLogPath = null;

    /** @var array{default: mixed, path: mixed, level: mixed}|null */
    private ?array $logConfigBeforeCapture = null;

    /**
     * Point the `single` channel at a throwaway file for the duration of the
     * test and return its path.
     */
    protected function beginLogCapture(): string
    {
        // A test may capture more than once (for example: issue a reset token,
        // then capture again for a separate assertion). Release the previous
        // file first, or each additional capture orphans one on disk.
        $this->endLogCapture();

        $this->capturedLogPath = storage_path('logs/uji-'.Str::lower(Str::random(16)).'.log');

        $this->logConfigBeforeCapture = [
            'default' => config('logging.default'),
            'path' => config('logging.channels.single.path'),
            'level' => config('logging.channels.single.level'),
        ];

        config([
            'logging.default' => 'single',
            'logging.channels.single.path' => $this->capturedLogPath,
            'logging.channels.single.level' => 'debug',
        ]);

        // Force the channel to be rebuilt against the new path; a channel
        // already resolved would keep writing to the old handler.
        Log::forgetChannel('single');
        Log::forgetChannel();

        return $this->capturedLogPath;
    }

    /**
     * Everything written to the captured channel so far.
     */
    protected function capturedLog(): string
    {
        if ($this->capturedLogPath === null) {
            return '';
        }

        // Flush handlers so buffered records reach the file before it is read.
        Log::forgetChannel('single');
        Log::forgetChannel();

        return is_file($this->capturedLogPath) ? (string) file_get_contents($this->capturedLogPath) : '';
    }

    protected function endLogCapture(): void
    {
        // Point the channel back at its original destination BEFORE deleting.
        // Anything logged after this point (an exception rendered during the
        // rest of the test, or during teardown) would otherwise recreate the
        // throwaway file the instant it was removed.
        if ($this->logConfigBeforeCapture !== null) {
            config([
                'logging.default' => $this->logConfigBeforeCapture['default'],
                'logging.channels.single.path' => $this->logConfigBeforeCapture['path'],
                'logging.channels.single.level' => $this->logConfigBeforeCapture['level'],
            ]);

            Log::forgetChannel('single');
            Log::forgetChannel();

            $this->logConfigBeforeCapture = null;
        }

        if ($this->capturedLogPath !== null && is_file($this->capturedLogPath)) {
            @unlink($this->capturedLogPath);
        }

        $this->capturedLogPath = null;
    }
}
