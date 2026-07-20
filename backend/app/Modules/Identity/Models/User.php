<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Tenancy\Models\Membership;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A USER ACCOUNT is an identity. It is NOT an authorization subject.
 *
 * Rule 02: "Authorization is derived from Membership, never from the user
 * account alone." A user account carries no tenant, no role, and no permission —
 * deliberately. Everything a user may do is a property of a Membership in a
 * specific tenant, which is why there is no `role` column on this table and
 * never will be one.
 *
 * One user may belong to MANY tenants (Rule 02, hard rule 1). Two memberships
 * of the same user in different tenants are unrelated: neither can be reached
 * from the other.
 *
 * @property string $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password
 * @property \Illuminate\Support\Carbon|null $disabled_at
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * Hidden from every array/JSON rendering of this model.
     *
     * This is a backstop, not the control. The control is that no endpoint
     * serialises a model directly — every response is assembled explicitly.
     * But a backstop that costs nothing is worth having (Rule 03).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            // Laravel's default hashing (bcrypt per config/hashing.php).
            // Assigning a plaintext password to ->password hashes it here, so a
            // plaintext value cannot reach the column by accident.
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * A disabled account authenticates to nothing.
     *
     * Checked at the authentication boundary AND on every authenticated request,
     * so disabling takes effect immediately rather than when a token happens to
     * expire.
     */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isDisabled() && $this->deleted_at === null;
    }
}
