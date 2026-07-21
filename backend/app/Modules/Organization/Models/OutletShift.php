<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Organization\Support\WallClockTime;
use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A named working shift for one outlet (FR-044).
 *
 * DEFINITIONS ONLY. Shift CLOSING — expected cash versus actual cash, and the
 * variance that must be recorded and acknowledged rather than absorbed — is
 * Step 5 (Rule 04 hard rule 10). This model exists so Step 5 has a shift to
 * close and inherits its identity and its hours rather than inventing them.
 *
 * `starts_at` and `ends_at` are LOCAL WALL-CLOCK times in the outlet's own
 * timezone, not instants. See `WallClockTime` for why that distinction is load-
 * bearing in a country spanning three timezones.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $outlet_id
 * @property string $starts_at
 * @property string $ends_at
 * @property bool $crosses_midnight
 */
class OutletShift extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'outlet_shifts';

    /**
     * `crosses_midnight` is absent on purpose: it is DERIVED from the two times
     * by `deriveCrossesMidnight()` and written server-side. Accepting it from a
     * client would allow a row whose flag contradicts its own hours — which the
     * database check constraint would reject anyway, but with a constraint error
     * instead of a clear message.
     */
    protected $fillable = [
        'code',
        'name',
        'starts_at',
        'ends_at',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'crosses_midnight' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * A shift whose end is at or before its start runs past midnight.
     *
     * Derived rather than declared, so the flag and the hours can never disagree.
     */
    public static function deriveCrossesMidnight(string $startsAt, string $endsAt): bool
    {
        return WallClockTime::parse($endsAt)->minutesFromMidnight()
            <= WallClockTime::parse($startsAt)->minutesFromMidnight();
    }
}
