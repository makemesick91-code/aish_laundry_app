<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Organization\Support\OperatingHours;
use App\Modules\Organization\Support\WallClockTime;
use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use App\Modules\Tenancy\Models\Tenant;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical location belonging to a brand (Rule 02, hard rule 4).
 *
 * `outlets` carries `tenant_id` directly, bound to the brand's tenant by a
 * composite foreign key, so an outlet can never belong to a brand in a different
 * tenant. Every outlet lookup in this application is tenant-scoped; a missing
 * scope yields NOTHING rather than another tenant's rows (Rule 02, hard rule 8 —
 * fail closed, never fail open).
 *
 * STEP 4 EXTENDS THIS AGGREGATE, IT DOES NOT REPLACE IT (FR-041 … FR-047).
 * Operating hours, capacity, quiet hours, and contact details are additive
 * columns; zones, shifts, and printers are satellites bound by composite foreign
 * key. No Step 3 column changed, so no Step 3 behaviour changed.
 *
 * EVERY TIME-OF-DAY FIELD HERE IS LOCAL WALL CLOCK, NOT AN INSTANT.
 * `quiet_hours_start`, `quiet_hours_end`, and the entries inside
 * `operating_hours` are read against `timezone`, at the application layer, never
 * converted in the schema (Rule 43; FR-041; FR-047).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $laundry_brand_id
 * @property string $name
 * @property string $code
 * @property string $timezone
 * @property array<string, mixed>|null $operating_hours
 * @property string $quiet_hours_start
 * @property string $quiet_hours_end
 * @property bool $is_active
 */
class Outlet extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;
    use SoftDeletes;

    /** FR-047 — the canonical default, reproduced from Rule 08 hard rule 6. */
    public const DEFAULT_QUIET_HOURS_START = '20:00';

    public const DEFAULT_QUIET_HOURS_END = '08:00';

    protected $table = 'outlets';

    /**
     * Model-side defaults that MIRROR the column defaults exactly.
     *
     * Without these, an outlet created and then read back in the same request
     * has a NULL `quiet_hours_start` in memory while the database holds
     * '20:00' — and `isWithinQuietHours()` would fail on a freshly created
     * outlet while working perfectly on a reloaded one. That is the worst shape
     * a defect can take: correct after a refresh, wrong before it.
     *
     * The database default remains the guarantee (it binds every writer,
     * including one that never loads this class). These make the in-memory model
     * agree with it.
     */
    protected $attributes = [
        'quiet_hours_start' => self::DEFAULT_QUIET_HOURS_START,
        'quiet_hours_end' => self::DEFAULT_QUIET_HOURS_END,
        'is_active' => true,
    ];

    /**
     * `tenant_id` and `laundry_brand_id` remain fillable because Step 3's
     * outlet-creation path sets them from server-resolved values and its tests
     * depend on that shape. Every Step 4 write path below sets them explicitly
     * from the verified TenantContext instead of passing them through a request
     * body, so no Step 4 endpoint accepts either from a client (threat T-05).
     */
    protected $fillable = [
        'tenant_id',
        'laundry_brand_id',
        'name',
        'code',
        'timezone',

        // Step 4 master data.
        'operating_hours',
        'daily_capacity_kg',
        'daily_capacity_orders',
        'quiet_hours_start',
        'quiet_hours_end',
        'contact_phone',
        'address_line',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'operating_hours' => 'array',
            'daily_capacity_kg' => 'integer',
            'daily_capacity_orders' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function laundryBrand(): BelongsTo
    {
        return $this->belongsTo(LaundryBrand::class, 'laundry_brand_id');
    }

    public function serviceZones(): HasMany
    {
        return $this->hasMany(OutletServiceZone::class, 'outlet_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(OutletShift::class, 'outlet_id');
    }

    public function printers(): HasMany
    {
        return $this->hasMany(OutletPrinter::class, 'outlet_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function operatingHours(): ?OperatingHours
    {
        return $this->operating_hours === null
            ? null
            : OperatingHours::fromArray($this->operating_hours);
    }

    /**
     * Is this instant inside the outlet's quiet window (FR-047)?
     *
     * Evaluated in THIS OUTLET'S timezone, not the server's and not the tenant's.
     * A tenant operating in Jakarta and Jayapura has two different quiet windows
     * in absolute time, and reading either against a single timezone would send
     * messages at 03.00 to one of them (Rule 08 hard rule 6).
     *
     * STEP 4 ANSWERS THE QUESTION; IT DOES NOT ACT ON THE ANSWER. Deferring or
     * suppressing a message is Step 7. Nothing in Step 4 sends anything.
     */
    public function isWithinQuietHours(DateTimeImmutable $instant): bool
    {
        return WallClockTime::windowContains(
            WallClockTime::parse($this->quiet_hours_start),
            WallClockTime::parse($this->quiet_hours_end),
            $instant,
            $this->timezone
        );
    }
}
