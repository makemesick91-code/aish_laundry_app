<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Logging;

use Illuminate\Log\Logger;

/**
 * Monolog "tap" that installs the redaction processor onto a channel.
 *
 * Referenced from `config/logging.php` on every channel that can reach a
 * persistent destination. Applying it as a tap rather than per-call-site is the
 * point: redaction that depends on the author remembering is redaction that
 * eventually does not happen.
 */
final class ConfigureLogRedaction
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if (method_exists($handler, 'pushProcessor')) {
                $handler->pushProcessor(new RedactSensitiveContext());
            }
        }

        $logger->getLogger()->pushProcessor(new RedactSensitiveContext());
    }
}
