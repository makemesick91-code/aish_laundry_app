<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\OutboundMessage;
use App\Modules\Notification\Contracts\ProviderResult;

/**
 * A DETERMINISTIC, TEST-ONLY ADAPTER.
 *
 * Every provider outcome a real adapter can produce — accepted, rejected, timeout,
 * malformed, unavailable, error — is reachable here on demand. That is what makes
 * the FR-099 claim testable: "the order is unchanged under provider timeout / 4xx /
 * 5xx / malformed / credentials-absent" is only a claim until each of those can
 * actually be produced.
 *
 * WHY IT IS SAFE TO SHIP IN THE APPLICATION TREE
 * ----------------------------------------------
 * It sends nothing, anywhere, ever — there is no HTTP client in this file. Its only
 * risk would be being wired into a non-testing environment and being mistaken for a
 * working provider, so `NotificationServiceProvider` registers it ONLY when
 * `app()->environment('testing')`, and `ProviderRegistryTest` asserts that a
 * production-like environment resolves the null provider instead.
 *
 * It records what it was asked to send so a test can assert on CONTENT — that a body
 * carries no full address, no OTP alongside a link, no internal identifier.
 */
final class FakeNotificationProvider implements NotificationProvider
{
    public const KEY = 'fake_provider';

    /** @var list<OutboundMessage> */
    public array $sent = [];

    private string $nextOutcome = ProviderResult::ACCEPTED;

    private bool $available = true;

    public function key(): string
    {
        return self::KEY;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function send(OutboundMessage $message): ProviderResult
    {
        $this->sent[] = $message;

        return match ($this->nextOutcome) {
            ProviderResult::REJECTED => ProviderResult::rejected('fake_rejected', 'uji: ditolak'),
            ProviderResult::TIMEOUT => ProviderResult::timeout('uji: waktu habis'),
            ProviderResult::MALFORMED => ProviderResult::malformed('uji: respons tidak terbaca'),
            ProviderResult::UNAVAILABLE => ProviderResult::unavailable('fake_unavailable', 'uji: tidak tersedia'),
            ProviderResult::ERROR => ProviderResult::error('fake_error', 'uji: galat'),
            default => ProviderResult::accepted('fake-ref-'.count($this->sent)),
        };
    }

    /** Drive the next outcome. Chainable so a test reads as one line. */
    public function willReturn(string $outcome): self
    {
        $this->nextOutcome = $outcome;

        return $this;
    }

    public function willBeUnavailable(bool $unavailable = true): self
    {
        $this->available = ! $unavailable;

        return $this;
    }

    public function reset(): self
    {
        $this->sent = [];
        $this->nextOutcome = ProviderResult::ACCEPTED;
        $this->available = true;

        return $this;
    }

    /** The body of the most recent send, for content assertions. */
    public function lastBody(): ?string
    {
        $last = end($this->sent);

        return $last === false ? null : $last->body;
    }
}
