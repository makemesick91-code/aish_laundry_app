<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * The mobile/API session credential.
 *
 * TOKENS ARE STORED HASHED. Sanctum stores `hash('sha256', $plainText)` in the
 * `token` column and returns the plaintext exactly once, at creation. This class
 * does not change that and must never be modified to persist a plaintext token
 * (Rule 03, hard rule 6).
 *
 * WHY THIS SUBCLASS EXISTS
 * ------------------------
 * Sanctum's own model deletes a token to revoke it. Deletion cannot distinguish
 * "this session was deliberately revoked" from "this token never existed", so
 * the client receives the same UNAUTHENTICATED either way. That is a worse
 * experience and a worse audit trail than the truth.
 *
 * This subclass therefore adds explicit `revoked_at` / `revoked_by_user_id`
 * columns, letting the API answer SESSION_REVOKED distinctly from
 * SESSION_EXPIRED and from UNAUTHENTICATED — mirroring how `device_sessions`
 * records revocation rather than deleting a row.
 *
 * @property string $id
 * @property string $name
 * @property string|null $device_identifier
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class AccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    protected $table = 'personal_access_tokens';

    /**
     * `token` is the SHA-256 hash of the credential. It is a secret-equivalent
     * value — possession of the hash is not possession of the token, but it
     * still has no business appearing in a response or a log.
     */
    protected $hidden = [
        'token',
    ];

    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'device_identifier',
        'device_name',
        'platform',
        'last_used_ip',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'revoked_at' => 'datetime',
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked();
    }
}
