<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WHICH PROOFS A TENANT REQUIRES AT A CUSTODY TRANSFER (FR-046).
 *
 * CONFIGURATION ONLY. Capturing a photo, a signature, an OTP, or a recipient
 * name at an actual pickup or delivery is Step 8. Step 4 records the policy so
 * Step 8 inherits a configured answer instead of defaulting to none.
 *
 * SOME PROOF IS ALWAYS REQUIRED, AND THE DATABASE ENFORCES IT.
 * Rule 09 hard rule 2: "A parcel does not silently change hands." The tenant
 * chooses WHICH proof; it may not choose NONE. Two check constraints make an
 * all-false policy unrepresentable, so the requirement cannot be switched off by
 * any writer — including a future import or console command that never touches
 * this class (Rule 18 hard rule 2).
 *
 * That is why the guarantee lives in the schema rather than in a validator here:
 * an invariant only one code path honours is not an invariant.
 *
 * @property string $id
 * @property string $tenant_id
 */
class TenantProofPolicy extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;

    protected $table = 'tenant_proof_policies';

    /** `tenant_id` is set from the verified TenantContext, never from input. */
    protected $fillable = [
        'pickup_requires_photo',
        'pickup_requires_signature',
        'pickup_requires_recipient_name',
        'pickup_requires_otp',
        'delivery_requires_photo',
        'delivery_requires_signature',
        'delivery_requires_recipient_name',
        'delivery_requires_otp',
    ];

    protected function casts(): array
    {
        return [
            'pickup_requires_photo' => 'boolean',
            'pickup_requires_signature' => 'boolean',
            'pickup_requires_recipient_name' => 'boolean',
            'pickup_requires_otp' => 'boolean',
            'delivery_requires_photo' => 'boolean',
            'delivery_requires_signature' => 'boolean',
            'delivery_requires_recipient_name' => 'boolean',
            'delivery_requires_otp' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** @return list<string> */
    public static function pickupFields(): array
    {
        return [
            'pickup_requires_photo',
            'pickup_requires_signature',
            'pickup_requires_recipient_name',
            'pickup_requires_otp',
        ];
    }

    /** @return list<string> */
    public static function deliveryFields(): array
    {
        return [
            'delivery_requires_photo',
            'delivery_requires_signature',
            'delivery_requires_recipient_name',
            'delivery_requires_otp',
        ];
    }

    /**
     * Does this policy require at least one proof on both legs?
     *
     * Mirrors the database check constraints so the application can refuse with a
     * readable message before PostgreSQL refuses with a constraint name.
     */
    public function requiresSomeProof(): bool
    {
        $any = fn (array $fields): bool => array_reduce(
            $fields,
            fn (bool $carry, string $field): bool => $carry || (bool) $this->{$field},
            false
        );

        return $any(self::pickupFields()) && $any(self::deliveryFields());
    }
}
