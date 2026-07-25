<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Support;

use App\Modules\Ordering\Models\Order;
use App\Modules\Tracking\Models\TrackingToken;

/**
 * A successfully resolved public access: the live token and the order it scopes.
 *
 * Exists so `PublicTrackingResolver::resolve()` can return "found" or `null` and
 * nothing in between. A resolver that returned an order plus a status enum would
 * invite a caller to branch on the status — and branching on WHY a lookup failed is
 * exactly how an existence oracle gets built (TRK-007).
 *
 * The token here is the RECORD, never the plaintext. Nothing in this object can be
 * used to reconstruct a link.
 */
final class ResolvedTrackingAccess
{
    public function __construct(
        public readonly TrackingToken $token,
        public readonly Order $order,
    ) {
    }
}
