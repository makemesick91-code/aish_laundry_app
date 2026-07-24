<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Ordering\Models\Order;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Models\QualityControlInspection;
use App\Modules\Production\Services\ProductionRegistry;
use App\Modules\Production\Services\QualityControlService;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * STEP 6 · FR-083 — QC defect-photo evidence. Runs against the LIVE PostgreSQL and
 * the LIVE private MinIO bucket (Rule 43, owner decision); every fixture is a
 * generated, recognisably-fake image (Rule 45) — never a real customer photo.
 *
 * Covers: authenticated + RBAC-gated upload (only production.qc), tenant/outlet
 * isolation (foreign 404), the failed-QC precondition, content-based MIME +
 * dimension + size validation, malformed rejection, client_reference idempotency
 * (one object per logical upload), checksum integrity, PRIVATE storage, short-lived
 * signed-URL retrieval, and the append-only evidence audit at the engine.
 */
final class QualityControlEvidenceTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // FR-083 evidence runs against a real private MinIO bucket. Where MinIO is
        // not provisioned (a workflow that does not start it), skip VISIBLY rather
        // than fail — never silently, and never a false red (mirrors the verifier's
        // precondition-skip discipline, Rule 01). Locally and in the workflow that
        // provisions MinIO, the disk is reachable and every test below runs.
        try {
            Storage::disk('evidence')->exists('__healthcheck_' . Str::random(10));
        } catch (\Throwable $e) {
            $this->markTestSkipped('MinIO evidence disk not reachable in this environment.');
        }
    }

    /** A minimal but VALID RGB PNG of the given size, encoded without GD. */
    private function pngBytes(int $width = 64, int $height = 64): string
    {
        $sig = "\x89PNG\r\n\x1a\n";
        $ihdrData = pack('N', $width) . pack('N', $height) . "\x08\x02\x00\x00\x00";
        $ihdr = $this->pngChunk('IHDR', $ihdrData);

        // One filter byte (0) per row followed by width*3 RGB bytes (mid-grey).
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00" . str_repeat("\x80\x80\x80", $width);
        }
        $idat = $this->pngChunk('IDAT', gzcompress($raw, 6));
        $iend = $this->pngChunk('IEND', '');

        return $sig . $ihdr . $idat . $iend;
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function upload(string $name, string $content): TestingFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /** @return array{tenant: Tenant, outlet: \App\Modules\Organization\Models\Outlet, order_id: string} */
    private function tenant(string $slug): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $customerId, 'tenant_id' => $tenant->id, 'code' => 'CUST-' . Str::upper(Str::random(8)),
            'name' => 'Pelanggan Uji Fiktif', 'phone' => '081200000000',
            'phone_normalized' => '6281200000000', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $serviceId = (string) Str::uuid();
        DB::table('service_catalog')->insert([
            'id' => $serviceId, 'tenant_id' => $tenant->id, 'code' => 'SVC-' . Str::upper(Str::random(8)),
            'name' => 'Cuci Kiloan (fiktif)', 'unit_kind' => 'kiloan', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId, 'tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'customer_id' => $customerId,
            'order_number' => 'ALS-' . Str::upper(Str::random(8)), 'client_reference' => (string) Str::uuid(),
            'status' => Order::STATUS_RECEIVED, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_lines')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'order_id' => $orderId, 'line_number' => 1,
            'service_id' => $serviceId, 'service_name' => 'Cuci Kiloan (fiktif)', 'unit' => 'kilogram',
            'quantity_milli' => 3000, 'unit_price_rupiah' => 8000, 'subtotal_rupiah' => 24000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['tenant' => $tenant, 'outlet' => $outlet, 'order_id' => $orderId];
    }

    private function asRole(Tenant $tenant, string $role, string $outletId): array
    {
        $user = $this->makeUser(self::PASSWORD, Str::lower(Str::random(8)) . '@contoh.fiktif');
        $this->makeMembership($tenant, $user, [$role]);

        return array_merge(
            $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id),
            ['X-Outlet-Id' => $outletId],
        );
    }

    /** Drive a job to a FAILED QC inspection via a manager context; return [job, inspection]. */
    private function failedInspection(array $t): array
    {
        $user = $this->makeUser(self::PASSWORD, 'setup-' . Str::lower(Str::random(6)) . '@contoh.fiktif');
        $membership = $this->makeMembership($t['tenant'], $user, [PermissionRegistry::ROLE_OUTLET_MANAGER]);
        $context = new TenantContext($t['tenant'], $membership, $t['outlet']);
        $order = Order::query()->forTenant($t['tenant']->id)->findOrFail($t['order_id']);

        $registry = new ProductionRegistry();
        $job = $registry->createJobForOrder($context, $order, (string) Str::uuid());
        $registry->startStage($context, $job->id, null, (string) Str::uuid(), 'WASHING');
        $registry->sendToQualityControl($context, $job->id, null, (string) Str::uuid());

        $inspection = (new QualityControlService())->recordInspection(
            $context, $job->id, QualityControlInspection::VERDICT_FAILED, null, (string) Str::uuid(),
            'NODA', 'Masih ada noda (fiktif).', null,
        );

        return ['job' => $job->fresh(), 'inspection' => $inspection];
    }

    private function url(ProductionJob $job, QualityControlInspection $inspection): string
    {
        return "/api/v1/production/jobs/{$job->id}/quality-control/{$inspection->id}/evidence";
    }

    // ---- upload + RBAC ---------------------------------------------------

    public function test_qc_can_upload_a_valid_defect_photo(): void
    {
        $t = $this->tenant('ev-upload');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        $png = $this->pngBytes();

        $response = $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $png),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201);

        $response->assertJsonPath('data.evidence.content_type', 'image/png');
        $response->assertJsonPath('data.evidence.byte_size', strlen($png));
        // The checksum pins the exact bytes stored.
        $response->assertJsonPath('data.evidence.checksum_sha256', hash('sha256', $png));
        // No bytes, no permanent URL, no storage key in the projection.
        $response->assertJsonMissingPath('data.evidence.storage_key');
        $response->assertJsonMissingPath('data.evidence.url');

        // The object really landed in the PRIVATE bucket under a random key.
        $key = DB::table('quality_control_evidence')->value('storage_key');
        $this->assertNotNull($key);
        $this->assertStringStartsWith('evidence/', $key);
        $this->assertTrue(Storage::disk('evidence')->exists($key));
    }

    public function test_content_type_is_server_detected_not_client_declared(): void
    {
        $t = $this->tenant('ev-detect');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);

        // A PNG uploaded under a lying ".jpg" name — the server detects PNG.
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.jpg', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201)->assertJsonPath('data.evidence.content_type', 'image/png');
    }

    public function test_production_operator_cannot_upload(): void
    {
        $t = $this->tenant('ev-operator');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        // The operator holds production.operate but NOT production.qc.
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR, $t['outlet']->id);
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(403);
    }

    public function test_cashier_cannot_upload(): void
    {
        $t = $this->tenant('ev-cashier');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_CASHIER, $t['outlet']->id);
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(403);
    }

    public function test_unauthenticated_upload_is_rejected(): void
    {
        $t = $this->tenant('ev-unauth');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    // ---- isolation + precondition ---------------------------------------

    public function test_foreign_tenant_job_is_not_found(): void
    {
        $a = $this->tenant('ev-a');
        $b = $this->tenant('ev-b');
        ['job' => $jobB, 'inspection' => $inspectionB] = $this->failedInspection($b);
        $headers = $this->asRole($a['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $a['outlet']->id);

        $this->post($this->url($jobB, $inspectionB), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(404);
    }

    public function test_evidence_cannot_attach_to_a_non_failed_inspection(): void
    {
        $t = $this->tenant('ev-passed');
        $user = $this->makeUser(self::PASSWORD, 'm-' . Str::lower(Str::random(6)) . '@contoh.fiktif');
        $membership = $this->makeMembership($t['tenant'], $user, [PermissionRegistry::ROLE_OUTLET_MANAGER]);
        $context = new TenantContext($t['tenant'], $membership, $t['outlet']);
        $order = Order::query()->forTenant($t['tenant']->id)->findOrFail($t['order_id']);
        $registry = new ProductionRegistry();
        $job = $registry->createJobForOrder($context, $order, (string) Str::uuid());
        $registry->startStage($context, $job->id, null, (string) Str::uuid(), 'WASHING');
        $registry->sendToQualityControl($context, $job->id, null, (string) Str::uuid());
        // A PASSED verdict — no defect photo may attach.
        $passed = (new QualityControlService())->recordInspection(
            $context, $job->id, QualityControlInspection::VERDICT_PASSED, null, (string) Str::uuid(), null, null, null,
        );

        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        $this->post($this->url($job->fresh(), $passed), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(409)->assertJsonPath('error.details.inspection.0', 'not_failed');
    }

    // ---- content validation ---------------------------------------------

    public function test_a_non_image_file_is_rejected_by_content(): void
    {
        $t = $this->tenant('ev-text');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        // A text file wearing a .png name — content sniff refuses it.
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', 'ini bukan gambar, hanya teks biasa'),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(422)->assertJsonPath('error.details.photo.0', 'unsupported_type');
    }

    public function test_a_malformed_image_is_rejected(): void
    {
        $t = $this->tenant('ev-malformed');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        // A JPEG (JFIF) start followed by garbage: libmagic sniffs image/jpeg, but
        // the header decoder cannot find the frame, so it is rejected as malformed.
        $garbage = "\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . str_repeat("\x00", 64);
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.jpg', $garbage),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(422)->assertJsonPath('error.details.photo.0', 'malformed');
    }

    public function test_an_image_below_the_minimum_dimensions_is_rejected(): void
    {
        $t = $this->tenant('ev-tiny');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes(8, 8)),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(422)->assertJsonPath('error.details.photo.0', 'too_small_dimensions');
    }

    public function test_an_oversize_file_is_rejected(): void
    {
        $t = $this->tenant('ev-big');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        // 6 MiB > the 5 MiB cap — the file-size rule refuses it.
        $this->post($this->url($job, $inspection), [
            'photo' => UploadedFile::fake()->create('defect.png', 6 * 1024, 'image/png'),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(422);
    }

    // ---- idempotency -----------------------------------------------------

    public function test_upload_is_idempotent_on_client_reference(): void
    {
        $t = $this->tenant('ev-idem');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        $ref = (string) Str::uuid();

        $first = $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => $ref,
        ], $headers)->assertStatus(201)->json('data.evidence.id');
        $second = $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => $ref,
        ], $headers)->assertStatus(201)->json('data.evidence.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('quality_control_evidence')->count());
    }

    // ---- retrieval -------------------------------------------------------

    public function test_retrieval_returns_a_short_lived_signed_url_only(): void
    {
        $t = $this->tenant('ev-url');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        $id = $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->json('data.evidence.id');

        $response = $this->getJson($this->url($job, $inspection) . "/{$id}/url", $headers)
            ->assertStatus(200);
        $response->assertJsonPath('data.expires_in', 300);
        $url = $response->json('data.url');
        // A presigned URL into the PRIVATE bucket — not a permanent public link.
        $this->assertStringContainsString('aish-evidence-dev', $url);
        $this->assertStringContainsString('X-Amz-Signature', $url);
        $this->assertStringContainsString('X-Amz-Expires', $url);
    }

    public function test_index_lists_metadata_without_urls_or_keys(): void
    {
        $t = $this->tenant('ev-index');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201);

        $this->getJson($this->url($job, $inspection), $headers)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['evidence' => [['id', 'content_type', 'checksum_sha256']]]])
            ->assertJsonMissingPath('data.evidence.0.storage_key')
            ->assertJsonMissingPath('data.evidence.0.url');
    }

    public function test_foreign_tenant_cannot_retrieve_evidence(): void
    {
        $a = $this->tenant('ev-ret-a');
        $b = $this->tenant('ev-ret-b');
        ['job' => $jobA, 'inspection' => $inspectionA] = $this->failedInspection($a);
        $headersA = $this->asRole($a['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $a['outlet']->id);
        $id = $this->post($this->url($jobA, $inspectionA), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headersA)->json('data.evidence.id');

        // Tenant B (with the qc role in its OWN tenant) cannot reach A's evidence.
        $headersB = $this->asRole($b['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $b['outlet']->id);
        $this->getJson($this->url($jobA, $inspectionA) . "/{$id}/url", $headersB)->assertStatus(404);
    }

    // ---- append-only DB --------------------------------------------------

    public function test_evidence_row_is_append_only_on_update(): void
    {
        $t = $this->tenant('ev-append');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201);

        $this->expectException(QueryException::class); // append-only trigger refuses UPDATE
        DB::table('quality_control_evidence')->update(['status' => 'tampered']);
    }

    public function test_evidence_row_cannot_be_deleted(): void
    {
        $t = $this->tenant('ev-nodelete');
        ['job' => $job, 'inspection' => $inspection] = $this->failedInspection($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL, $t['outlet']->id);
        $this->post($this->url($job, $inspection), [
            'photo' => $this->upload('defect.png', $this->pngBytes()),
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201);

        $this->expectException(QueryException::class); // append-only trigger refuses DELETE
        DB::table('quality_control_evidence')->delete();
    }
}
