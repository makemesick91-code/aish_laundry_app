<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A PLATFORM-MANAGED permission catalogue entry (DEC-0025 §1).
 *
 * A projection of PermissionRegistry::permissions(). Carries no tenant data and
 * grants nothing on its own.
 *
 * @property string $id
 * @property string $key
 * @property string|null $description
 */
class Permission extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'permissions';

    protected $fillable = [
        'key',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission', 'permission_id', 'role_id')
            ->withTimestamps();
    }
}
