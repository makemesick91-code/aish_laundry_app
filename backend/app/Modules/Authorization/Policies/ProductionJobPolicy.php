<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Production\Models\ProductionJob;

/**
 * Server-side authorization for production jobs (Step 6, FR-071 … FR-085).
 *
 * THREE PERMISSIONS, split on consequence (Rule 40, DEC-0037 §9):
 *   view    — reading the queue, a job, its items, and its timeline.
 *   operate — advancing stages, block/resume, assignment, batches, and marking
 *             an order ready. A cashier, a courier, and a customer hold NONE of
 *             these: access to an order never confers production mutation.
 *   qc      — recording a QC verdict and driving rework, held by the inspector
 *             role distinctly from the operator being inspected (FR-081).
 *
 * Each check is BOTH a permission check AND a same-tenant check (`allowsWithin`),
 * so a denial for a foreign job is indistinguishable from "does not exist"
 * (Rule 48, Rule 40).
 */
final class ProductionJobPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::PRODUCTION_VIEW);
    }

    public function view(User $user, ProductionJob $job): bool
    {
        return $this->allowsWithin(PermissionRegistry::PRODUCTION_VIEW, $job->tenant_id);
    }

    public function operate(User $user, ProductionJob $job): bool
    {
        return $this->allowsWithin(PermissionRegistry::PRODUCTION_OPERATE, $job->tenant_id);
    }

    public function qualityControl(User $user, ProductionJob $job): bool
    {
        return $this->allowsWithin(PermissionRegistry::PRODUCTION_QC, $job->tenant_id);
    }
}
