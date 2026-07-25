<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Controllers;

use App\Modules\Ordering\Models\Order;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tracking\Http\TrackingLinkProjection;
use App\Modules\Tracking\Models\TrackingToken;
use App\Modules\Tracking\Services\TrackingTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * THE OPERATOR TRACKING-LINK SURFACE (FR-086 … FR-088).
 *
 * Four operations, matching the lifecycle exactly: read metadata, issue (K-01),
 * rotate (K-10), revoke (K-08). There is no list-all-tokens route and no export
 * route — both would be enumeration surfaces over a tenant's live credentials, and
 * their absence is asserted by test rather than assumed (mirroring the Step 4
 * no-bulk-mutation/no-export discipline in `routes/api.php`).
 *
 * THE PLAINTEXT APPEARS IN EXACTLY TWO RESPONSES
 * ----------------------------------------------
 * `store()` and `rotate()` return it, once, and say so in the payload
 * (`shown_once: true`). `show()` cannot return it and neither can anything else,
 * because after those two moments nothing in the system can produce it — only the
 * hash was kept (TRK-002, TRK-019). The operator UI displays it once with an
 * explicit warning and offers rotation as the recovery path.
 *
 * Every write carries a mandatory reason on revoke and rotate
 * (TRACKING_ACCESS_LIFECYCLE §9) and an `expected_version` for optimistic
 * concurrency, matching the Step 5/6 command contract.
 */
final class TrackingLinkController
{
    public function __construct(private readonly TrackingTokenService $tokens)
    {
    }

    /** Metadata for the order's current link. Never the token. */
    public function show(string $order): JsonResponse
    {
        $context = app(TenantContext::class);
        $orderModel = $this->findOrderOrFail($context, $order);

        Gate::authorize('viewAny', TrackingToken::class);

        // THE LIVE LINK WINS, ALWAYS — not merely the most recently issued one.
        //
        // Ordering by `issued_at` alone is wrong, and the failure is not
        // hypothetical: a rotation mints the new row in the same transaction that
        // supersedes the old one, so both can carry the SAME `issued_at`, and the
        // tiebreak is then arbitrary. Half the time that returns the SUPERSEDED
        // row — which would show the operator a dead token as "current", offer
        // revoke on it (409), and hide the link the customer is actually holding.
        //
        // `ISSUED` is unique per order by the partial index, so this is
        // deterministic. The `id` tiebreak on the fallback keeps the terminal-only
        // case stable too, rather than varying between reads.
        $token = TrackingToken::query()
            ->forTenant($context->tenantId())
            ->where('order_id', $orderModel->id)
            ->orderByRaw("CASE WHEN state = ? THEN 0 ELSE 1 END", [TrackingToken::STATE_ISSUED])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->first();

        if ($token === null) {
            return ApiResponse::success(['tracking_link' => null]);
        }

        return ApiResponse::success([
            'tracking_link' => TrackingLinkProjection::summary($token),
            'timeline' => TrackingLinkProjection::timeline($token),
        ]);
    }

    /** Issue a link (K-01). Returns the plaintext ONCE. */
    public function store(Request $request, string $order): JsonResponse
    {
        $context = app(TenantContext::class);
        $orderModel = $this->findOrderOrFail($context, $order);

        Gate::authorize('create', TrackingToken::class);

        $validated = $request->validate([
            'client_reference' => ['required', 'uuid'],
        ]);

        $issued = $this->tokens->issue($context, $orderModel, $validated['client_reference']);

        return ApiResponse::success([
            'tracking_link' => TrackingLinkProjection::summary($issued->token),
            // The one and only time this value is returned by any endpoint.
            'url' => $issued->url(),
            'shown_once' => true,
            'notice' => 'Tautan ini hanya ditampilkan sekali. Salin dan kirimkan kepada pelanggan sekarang. '
                .'Bila tautan hilang, buat tautan baru melalui rotasi.',
        ], 201);
    }

    /** Rotate (K-10): mint a new link, supersede the old, in one transaction. */
    public function rotate(Request $request, string $token): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findTokenOrFail($context, $token);

        Gate::authorize('manage', $model);

        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $issued = $this->tokens->rotate(
            $context,
            $model->id,
            $validated['expected_version'] ?? null,
            $validated['client_reference'],
            $validated['reason_code'],
            $validated['reason'] ?? null,
        );

        return ApiResponse::success([
            'tracking_link' => TrackingLinkProjection::summary($issued->token),
            'url' => $issued->url(),
            'shown_once' => true,
            'notice' => 'Tautan lama langsung berhenti berlaku. Tautan baru ini hanya ditampilkan sekali.',
        ]);
    }

    /** Revoke (K-08). Terminal, immediate, reason mandatory. */
    public function revoke(Request $request, string $token): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findTokenOrFail($context, $token);

        Gate::authorize('manage', $model);

        $validated = $request->validate([
            // Mandatory, and rejected when whitespace-only: a reason field that
            // accepts a space is a reason field that records nothing (Rule 32
            // hard rule 16).
            'reason_code' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'expected_version' => ['nullable', 'integer'],
        ]);

        if (trim($validated['reason_code']) === '') {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Alasan pencabutan wajib diisi.',
                ['reason_code' => ['required']],
            );
        }

        $revoked = $this->tokens->revoke(
            $context,
            $model->id,
            $validated['expected_version'] ?? null,
            $validated['reason_code'],
            $validated['reason'] ?? null,
        );

        return ApiResponse::success([
            'tracking_link' => TrackingLinkProjection::summary($revoked),
        ]);
    }

    private function findOrderOrFail(TenantContext $context, string $orderId): Order
    {
        $order = Order::query()->forTenant($context->tenantId())->find($orderId);

        if ($order === null) {
            // A foreign-tenant order 404s exactly like an absent one (Rule 48).
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data yang Anda cari tidak ditemukan.');
        }

        return $order;
    }

    private function findTokenOrFail(TenantContext $context, string $tokenId): TrackingToken
    {
        $token = TrackingToken::query()->forTenant($context->tenantId())->find($tokenId);

        if ($token === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data yang Anda cari tidak ditemukan.');
        }

        return $token;
    }
}
