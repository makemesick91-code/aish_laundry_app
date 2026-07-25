<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

/**
 * WHAT AN ADAPTER RECEIVES — a first-party value object, never a vendor payload.
 *
 * The dispatcher renders the body from a tenant-scoped template BEFORE this object
 * is built, so an adapter never sees a template, a variable map, or a customer
 * record. It sees a destination and finished text. That is the narrowest thing an
 * adapter can be given and still do its job, and narrowness is the point: an
 * adapter that received the customer could log the customer.
 *
 * WHAT IS DELIBERATELY ABSENT
 * ---------------------------
 * No customer id, no order id, no tenant id, no internal identifier of any kind, no
 * tracking-token plaintext or hash, and no full address (NOT-015). The
 * `correlation_id` is present so an incident can be traced end to end, and it is an
 * opaque request identifier that discloses nothing on its own.
 */
final class OutboundMessage
{
    /**
     * @param  string  $destination  E.164 digits, normalised upstream. Fictional in every fixture.
     * @param  string  $body  Finished Bahasa Indonesia text (Rule 30). Already rendered, already redacted.
     * @param  string  $templateKey  First-party template identity, for provider-side template mapping.
     * @param  string  $category  transactional|marketing — carried so an adapter using a
     *                            provider that separates the two can route correctly. It is
     *                            NEVER the place the category is decided; that is the template.
     */
    public function __construct(
        public readonly string $destination,
        public readonly string $body,
        public readonly string $templateKey,
        public readonly string $category,
        public readonly string $correlationId,
    ) {
    }
}
