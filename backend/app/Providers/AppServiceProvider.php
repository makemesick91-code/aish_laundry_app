<?php

namespace App\Providers;

use App\Modules\Production\Services\ImageEvidenceValidator;
use App\Modules\Production\Services\QualityControlEvidenceService;
use App\Modules\SharedKernel\Storage\PrivateObjectStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // FR-083: the QC evidence service stores to the PRIVATE `evidence` disk
        // (MinIO in development). The disk name is a constructor string the
        // container cannot auto-resolve, so it is bound here explicitly.
        $this->app->bind(
            QualityControlEvidenceService::class,
            static fn (): QualityControlEvidenceService => new QualityControlEvidenceService(
                new PrivateObjectStorage('evidence'),
                new ImageEvidenceValidator(),
            ),
        );
    }

    public function boot(): void
    {
        //
    }
}
