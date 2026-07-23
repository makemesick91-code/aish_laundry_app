<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Ordering\Models\Order;
use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant-scoped entry in the append-only financial ledger (FR-061 … FR-069).
 *
 * There is NO SoftDeletes trait and no `delete()` path: a financial record is
 * never removed (FR-066), and the database refuses a hard delete with an
 * ENABLE ALWAYS trigger regardless of what this model does. A correction is a
 * new `reversal` row (FR-067).
 *
 * Nothing here is client-mass-assignable: every field is set by PaymentRegistry
 * from server-validated values or the verified TenantContext. A client that
 * could set `status` could mark its own order paid — exactly what FR-064 forbids.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string $kind
 * @property string $method
 * @property string $status
 * @property int $amount_rupiah
 * @property ?string $reverses_payment_id
 */
class Payment extends Model
{
    use HasOptimisticVersion;
    use HasUuids;

    public const KIND_PAYMENT = 'payment';

    public const KIND_REVERSAL = 'reversal';

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_QRIS = 'qris';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    protected $table = 'payments';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount_rupiah' => 'integer',
            'received_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function reversesPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_payment_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** @return list<string> */
    public static function methods(): array
    {
        return [self::METHOD_CASH, self::METHOD_BANK_TRANSFER, self::METHOD_QRIS];
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    /** A gateway method settles asynchronously; a counter method settles at once. */
    public function isGatewayMethod(): bool
    {
        return $this->method === self::METHOD_QRIS;
    }
}
