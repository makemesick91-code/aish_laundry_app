<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

/**
 * THE PROVIDER BOUNDARY (FR-093, NOT-009).
 *
 * "WhatsApp sending shall sit behind an internal notification interface; no vendor
 * SDK, payload, or identifier shall leak into business logic."
 *
 * This interface and the two value objects either side of it — `OutboundMessage`
 * in, `ProviderResult` out — are the whole of that guarantee. Both are FIRST-PARTY
 * types. No vendor class, no vendor array shape, and no vendor error code crosses
 * this line in either direction; an adapter that receives a vendor error MAPS it to
 * one of `ProviderResult`'s outcomes and discards the original shape.
 *
 * Swapping providers is therefore an adapter plus configuration change. It is not a
 * product rewrite, and `ProviderAbstractionTest` asserts structurally that nothing
 * outside `Modules/Notification/Providers/` names a vendor at all.
 *
 * WHY `isAvailable()` EXISTS AS A SEPARATE QUESTION
 * ------------------------------------------------
 * FR-094 requires the official adapter to be the automated path, and it must FAIL
 * CLOSED without credentials. Asking "are you available?" before "send this" lets
 * the dispatcher record `provider_unavailable` honestly instead of attempting a
 * request that cannot work and then reporting a network error — a distinction that
 * matters because one of those is a misconfiguration a tenant can fix and the other
 * looks like an outage.
 */
interface NotificationProvider
{
    /**
     * A stable, first-party identifier for this adapter, recorded on every attempt.
     *
     * Deliberately OUR name for the adapter, not the vendor's product name, so a
     * stored attempt row never becomes a vendor identifier in business data.
     */
    public function key(): string;

    /**
     * Can this adapter actually send right now?
     *
     * FALSE when credentials are absent, incomplete, or the adapter is disabled.
     * An adapter must never answer true and then fabricate a result.
     */
    public function isAvailable(): bool;

    /**
     * Attempt one send.
     *
     * MUST NOT THROW for an ordinary provider failure — a timeout, a 4xx, a 5xx, a
     * malformed body are all normal outcomes and are returned as a `ProviderResult`.
     * Throwing would push a messaging failure up a call stack that FR-099 requires
     * to be unaffected by messaging.
     */
    public function send(OutboundMessage $message): ProviderResult;
}
