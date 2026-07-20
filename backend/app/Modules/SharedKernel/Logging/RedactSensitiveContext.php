<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Logging;

use App\Modules\SharedKernel\Http\CorrelationId;
use App\Modules\SharedKernel\Support\Redactor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Structured-log processor that redacts credential-shaped values ANYWHERE in a
 * log record's context or extra data, and stamps the request correlation id.
 *
 * This runs on every channel (wired in `config/logging.php` via the `tap`
 * mechanism in ConfigureLogRedaction), so a developer cannot leak a credential
 * by remembering to redact at one call site and forgetting at another. Rule 03
 * hard rule 20 is enforced by the pipeline, not by discipline.
 */
final class RedactSensitiveContext implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        /** @var array<string, mixed> $context */
        $context = Redactor::redact($record->context);

        /** @var array<string, mixed> $extra */
        $extra = Redactor::redact($record->extra);

        if (app()->bound(CorrelationId::class)) {
            $extra['request_id'] = app(CorrelationId::class)->value;
        }

        return $record->with(context: $context, extra: $extra);
    }
}
