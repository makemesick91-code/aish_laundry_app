<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A PRINTING DEVICE configured at an outlet (FR-045).
 *
 * A DEVICE, NOT A DOCUMENT. FR-045 authorises printer CONFIGURATION as outlet
 * master data. What a printer eventually prints is FR-052 in Step 5, and
 * `receipt`, `nota`, and `struk` remain forbidden tokens under DEC-0030. This
 * class configures hardware and names no document, deliberately.
 *
 * `device_identifier` IS NOT A CREDENTIAL. It holds a Bluetooth name, a USB path,
 * or a network address — the kind of value a technician reads off the device. A
 * printer requiring authentication would read that secret from the environment,
 * never from this row (Rule 03 hard rule 10, Rule 45).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $outlet_id
 * @property string $device_kind
 * @property string $connection_kind
 * @property bool $is_default
 */
class OutletPrinter extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;
    use SoftDeletes;

    public const DEVICE_THERMAL_58 = 'thermal_58mm';

    public const DEVICE_THERMAL_80 = 'thermal_80mm';

    public const DEVICE_LABEL = 'label';

    public const CONNECTION_BLUETOOTH = 'bluetooth';

    public const CONNECTION_USB = 'usb';

    public const CONNECTION_NETWORK = 'network';

    protected $table = 'outlet_printers';

    protected $fillable = [
        'code',
        'name',
        'device_kind',
        'connection_kind',
        'device_identifier',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return list<string> */
    public static function deviceKinds(): array
    {
        return [self::DEVICE_THERMAL_58, self::DEVICE_THERMAL_80, self::DEVICE_LABEL];
    }

    /** @return list<string> */
    public static function connectionKinds(): array
    {
        return [self::CONNECTION_BLUETOOTH, self::CONNECTION_USB, self::CONNECTION_NETWORK];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
