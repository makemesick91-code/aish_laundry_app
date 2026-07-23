<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Payments\Models\Payment;

/**
 * Server-side authorization for payments (FR-061 … FR-069).
 *
 * THREE PERMISSIONS, SPLIT ON FINANCIAL CONSEQUENCE:
 *   view   — reading the ledger. Kasir, manager, finance, admin, owner.
 *   record — taking a payment at the counter.
 *   refund — reversing one. A financial control point (FR-065), held by manager,
 *            finance, and owner; withheld from the kasir who records and from the
 *            admin deputy.
 *
 * Every check is both a permission AND a same-tenant check (`allowsWithin`); a
 * denial for a foreign payment is indistinguishable from "does not exist"
 * (Rule 48). There is no delete: the ledger is append-only (FR-066).
 */
final class PaymentPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::PAYMENT_VIEW);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->allowsWithin(PermissionRegistry::PAYMENT_VIEW, $payment->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->allows(PermissionRegistry::PAYMENT_RECORD);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $this->allowsWithin(PermissionRegistry::PAYMENT_REFUND, $payment->tenant_id);
    }
}
