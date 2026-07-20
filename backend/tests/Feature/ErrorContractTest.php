<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * The API error contract.
 *
 * Every failure carries a stable machine-readable code, a Bahasa Indonesia
 * message, and a correlation id. What it must NEVER carry is a stack trace, SQL,
 * a database host or username, Redis configuration, a token, or a policy's
 * internal reasoning — and `APP_DEBUG` does not widen that.
 */
final class ErrorContractTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    public function test_every_response_carries_a_correlation_id_header(): void
    {
        // The operational probes deliberately use their own probe-shaped payload
        // rather than the API envelope, but the correlation header is applied by
        // middleware and is therefore present on EVERY response.
        foreach (['/api/v1/health', '/api/v1/readiness'] as $probe) {
            $this->assertNotNull(
                $this->getJson($probe)->headers->get('X-Request-Id'),
                "Probe {$probe} harus membawa X-Request-Id."
            );
        }
    }

    public function test_an_enveloped_response_repeats_the_correlation_id_in_its_body(): void
    {
        $response = $this->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->assertNotNull($response->json('meta.request_id'));
        $this->assertSame(
            $response->json('meta.request_id'),
            $response->headers->get('X-Request-Id'),
        );
    }

    public function test_a_client_supplied_request_id_is_echoed_back(): void
    {
        $response = $this->getJson('/api/v1/health', ['X-Request-Id' => 'uji-korelasi-123']);

        $this->assertSame('uji-korelasi-123', $response->headers->get('X-Request-Id'));
    }

    public function test_an_error_response_uses_the_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/auth/me')->assertStatus(401);

        $response->assertJsonStructure([
            'error' => ['code', 'message'],
            'meta' => ['request_id'],
        ]);

        $this->assertSame('UNAUTHENTICATED', $response->json('error.code'));
        $this->assertNotSame('', $response->json('error.message'));

        // No success payload on a failure, and no failure payload on success.
        $this->assertNull($response->json('data'));
    }

    public function test_a_validation_failure_reports_fields_without_internals(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [])->assertStatus(422);

        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Field names are returned so a client can attach the message to the
        // right input. Nothing beyond the field names is disclosed.
        $this->assertArrayHasKey('identifier', $response->json('error.details.fields'));
        $this->assertArrayHasKey('password', $response->json('error.details.fields'));
    }

    public function test_an_error_response_leaks_no_internals(): void
    {
        /*
         * Infrastructure and implementation detail only.
         *
         * The literal word "password" is deliberately NOT on this list: it is a
         * legitimate FIELD NAME in a validation message, and a client cannot
         * label the input without it. What must never appear is a credential
         * VALUE, an address, or a piece of the implementation — which is what
         * the entries below detect, and what the secret-value assertion after
         * the loop covers.
         */
        $forbidden = [
            'SQLSTATE', 'select * from', 'insert into', 'pgsql', '127.0.0.1',
            'aish_dev', 'aish_laundry_dev', 'Stack trace', '#0 /',
            'vendor/laravel', '/home/', 'redis', 'Eloquent', 'QueryException',
            'App\\Modules',
        ];

        $responses = [
            $this->getJson('/api/v1/auth/me'),
            $this->postJson('/api/v1/auth/login', []),
            $this->postJson('/api/v1/auth/login', [
                'identifier' => 'tidak.ada@contoh.invalid',
                'password' => 'RahasiaYangDikirim123',
            ]),
            $this->getJson('/api/v1/tidak-ada-rute-ini'),
        ];

        foreach ($responses as $response) {
            $body = (string) $response->getContent();

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $needle,
                    $body,
                    "Respons galat membocorkan detail internal: {$needle}"
                );
            }

            // The credential that was submitted is never echoed back.
            $this->assertStringNotContainsString('RahasiaYangDikirim123', $body);
        }
    }

    public function test_debug_mode_does_not_widen_the_error_contract(): void
    {
        // Laravel's default handler renders a full trace when debug is on. That
        // behaviour is deliberately not reproduced by this API.
        config(['app.debug' => true]);

        $body = (string) $this->getJson('/api/v1/auth/me')->assertStatus(401)->getContent();

        $this->assertStringNotContainsString('trace', $body);
        $this->assertStringNotContainsString('exception', $body);
    }

    public function test_the_health_and_readiness_probes_respond(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/readiness')->assertOk();
    }
}
