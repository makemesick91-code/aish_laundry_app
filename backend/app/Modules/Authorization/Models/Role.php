<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use App\Modules\Authorization\PermissionRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A PLATFORM-MANAGED role catalogue entry (DEC-0025 §1).
 *
 * DEC-0025 §3: "A role record without `tenant_id` does not itself grant access.
 * A row in `roles` is a NAME FOR A CAPABILITY SET, never an entitlement. Reading
 * `roles` conveys nothing about who may do what in which tenant."
 *
 * That is why this table has no `tenant_id` and why reading it is harmless. The
 * tenant-scoped fact lives in `membership_role`.
 *
 * The `category` (tenant vs platform) is NOT a column: it is derived from the
 * PermissionRegistry, which is the single source of truth. Storing it would
 * create a second place for it to be wrong.
 *
 * @property string $id
 * @property string $key
 * @property string|null $description
 */
class Role extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'roles';

    protected $fillable = [
        'key',
        'description',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission', 'role_id', 'permission_id')
            ->withTimestamps();
    }

    public function category(): string
    {
        return PermissionRegistry::isPlatformRole($this->key)
            ? PermissionRegistry::CATEGORY_PLATFORM
            : PermissionRegistry::CATEGORY_TENANT;
    }

    public function isTenantRole(): bool
    {
        return PermissionRegistry::isTenantRole($this->key);
    }

    public function isPlatformRole(): bool
    {
        return PermissionRegistry::isPlatformRole($this->key);
    }
}
