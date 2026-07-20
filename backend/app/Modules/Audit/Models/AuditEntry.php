<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An APPEND-ONLY audit record.
 *
 * `$timestamps = false` and a `created_at` default from the database: there is
 * no `updated_at` and no `deleted_at`, and the absence is the point. An audit
 * entry is never edited and never hard-deleted; a correction is a NEW entry,
 * exactly as a financial correction is a reversal rather than a rewrite
 * (Rule 04, hard rule 8).
 *
 * The model deliberately exposes NO update or delete helper. Nothing in this
 * codebase should be able to rewrite history conveniently.
 *
 * NEVER WRITE A SECRET HERE — no password, hash, OTP, reset token, raw access
 * token, Authorization header, or cookie. AuditRecorder redacts before writing;
 * this model is the last line, not the first (Rule 03, Rule 21).
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $action
 */
class AuditEntry extends Model
{
    use HasUuids;

    protected $table = 'audit_entries';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'actor_user_id',
        'actor_membership_id',
        'impersonator_user_id',
        'action',
        'subject_type',
        'subject_id',
        'reason',
        'changes',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
