<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Context\TenantContextResolver;
use App\Modules\Tenancy\Models\DeviceSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RESOLVES THE TENANT CONTEXT AND BINDS IT IMMUTABLY FOR THE REQUEST.
 *
 * WHERE THE CLIENT'S REQUESTED TENANT MAY COME FROM
 * -------------------------------------------------
 * Header `X-Tenant-Id`, route parameter `tenant`, body field `tenant_id`, or the
 * selection previously stored in a first-party session by
 * `POST /api/v1/context/tenant`.
 *
 * ALL FOUR ARE TREATED IDENTICALLY: as an UNTRUSTED REQUEST. None of them is
 * authorization proof. The header is not more trusted than the body, and the
 * session-stored selection is not more trusted either — it too is re-verified
 * against a live membership on every single request, so a membership revoked
 * after selection stops working immediately rather than lasting as long as the
 * session (DEC-0025 §6).
 *
 * IMMUTABILITY
 * ------------
 * The resolved TenantContext is bound as a request-scoped instance and is
 * `readonly`. Nothing downstream can repoint it. A request is authorised as
 * exactly one tenant for its whole lifetime.
 *
 * FAIL-CLOSED: an unresolvable tenant throws. There is no default tenant and no
 * "first tenant the user belongs to" fallback — a fallback is how a request ends
 * up acting on data nobody selected.
 */
final class ResolveTenantContext
{
    public const SESSION_TENANT_KEY = 'active_tenant_id';

    public const SESSION_OUTLET_KEY = 'active_outlet_id';

    public function __construct(private readonly TenantContextResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            // Ordering defect rather than a client error: this middleware must
            // run after authentication. Failing closed either way.
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        $requestedTenantId = $this->requestedTenantId($request);

        if ($requestedTenantId === null) {
            throw ApiException::of(
                ErrorCode::TENANT_ACCESS_DENIED,
                'Tenant aktif belum dipilih. Pilih tenant terlebih dahulu melalui daftar tenant Anda.'
            );
        }

        // The verification. Throws on anything less than an ACTIVE membership.
        $context = $this->resolver->resolve($user, $requestedTenantId);

        // A device whose access to THIS tenant was revoked is rejected here,
        // after the tenant is known — because device revocation is tenant-scoped
        // and cannot be evaluated before the tenant is resolved (Rule 03, hard
        // rule 9).
        $this->assertDeviceNotRevoked($request, $context);

        $requestedOutletId = $this->requestedOutletId($request);

        if ($requestedOutletId !== null) {
            // Outlet is looked up SCOPED BY THE RESOLVED TENANT, so an outlet
            // belonging to another tenant is not found rather than rejected.
            $context = $this->resolver->attachOutlet($context, $requestedOutletId);
        }

        app()->instance(TenantContext::class, $context);

        return $next($request);
    }

    /**
     * A device session revoked for this tenant invalidates the credential for
     * this tenant only. The same user on the same device may still be perfectly
     * legitimate in a different tenant, so this check is deliberately not global.
     */
    private function assertDeviceNotRevoked(Request $request, TenantContext $context): void
    {
        $deviceIdentifier = $this->deviceIdentifier($request);

        if ($deviceIdentifier === null) {
            return;
        }

        $deviceSession = DeviceSession::query()
            ->where('tenant_id', $context->tenantId())
            ->where('user_id', $context->userId())
            ->where('device_identifier', $deviceIdentifier)
            ->first();

        if ($deviceSession === null) {
            // Unknown device is not a denial: device registration happens on
            // tenant selection, and a device identifier is an untrusted hint,
            // never an authorization signal (Rule 31, hard rule 12). Treating an
            // unknown value as a denial would make a spoofable client-supplied
            // string into an access control.
            return;
        }

        if ($deviceSession->isRevoked()) {
            throw ApiException::of(ErrorCode::DEVICE_REVOKED);
        }
    }

    private function requestedTenantId(Request $request): ?string
    {
        $candidates = [
            $request->headers->get('X-Tenant-Id'),
            $request->route('tenant'),
            $request->input('tenant_id'),
            $request->hasSession() ? $request->session()->get(self::SESSION_TENANT_KEY) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function requestedOutletId(Request $request): ?string
    {
        $candidates = [
            $request->headers->get('X-Outlet-Id'),
            $request->input('outlet_id'),
            $request->hasSession() ? $request->session()->get(self::SESSION_OUTLET_KEY) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function deviceIdentifier(Request $request): ?string
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && isset($token->device_identifier) && is_string($token->device_identifier)) {
            return $token->device_identifier;
        }

        $header = $request->headers->get('X-Device-Id');

        return is_string($header) && trim($header) !== '' ? trim($header) : null;
    }
}
