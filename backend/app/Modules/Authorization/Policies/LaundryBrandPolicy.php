<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\LaundryBrand;

final class LaundryBrandPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::BRAND_VIEW);
    }

    public function view(User $user, LaundryBrand $brand): bool
    {
        return $this->allowsWithin(PermissionRegistry::BRAND_VIEW, $brand->tenant_id);
    }

    public function manage(User $user, LaundryBrand $brand): bool
    {
        return $this->allowsWithin(PermissionRegistry::BRAND_MANAGE, $brand->tenant_id);
    }
}
