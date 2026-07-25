<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Http\NotificationProjection;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Services\ManualWhatsAppLinkBuilder;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Ordering\Models\Order;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * THE OPERATOR NOTIFICATION SURFACE (FR-093 … FR-099).
 *
 * Reads are gated on `notification.view`; anything that causes another send is
 * gated on `notification.send`, because every send costs the tenant real money with
 * a third-party provider (Rule 14 guardrail 8, NOT-020).
 *
 * WHAT THIS SURFACE DELIBERATELY CANNOT DO
 * ----------------------------------------
 * It cannot compose a free-text message. Every send goes through a catalogued
 * template whose category is fixed (FR-096), so there is no endpoint here through
 * which a marketing message could be typed into a transactional path (NOT-024).
 *
 * It cannot mark a message delivered. `SENT` is written only by the dispatcher on a
 * provider acceptance, and the database CHECK refuses `SENT` without `accepted_at`.
 *
 * It cannot delete a notification or an attempt. Both tables are append-only:
 * "failures are visible" (FR-099) is only true if they cannot be tidied away.
 */
final class NotificationController
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly ManualWhatsAppLinkBuilder $manualLink,
        private readonly NotificationProvider $provider,
        private readonly AuditRecorder $audit,
    ) {
    }

    /** Notification history for one order. */
    public function index(Request $request, string $order): JsonResponse
    {
        Gate::authorize('viewAny', NotificationIntent::class);
        $context = app(TenantContext::class);

        $orderModel = Order::query()->forTenant($context->tenantId())->find($order);
        if ($orderModel === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data yang Anda cari tidak ditemukan.');
        }

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = NotificationIntent::query()
            ->forTenant($context->tenantId())
            ->where('order_id', $orderModel->id)
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            [
                'notifications' => array_map(
                    static fn (NotificationIntent $i): array => NotificationProjection::summary($i),
                    $page->items(),
                ),
            ],
            200,
            ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    /** One notification with its full attempt history. */
    public function show(string $intent): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $intent);
        Gate::authorize('view', $model);

        return ApiResponse::success([
            'notification' => NotificationProjection::summary($model),
            'attempts' => NotificationProjection::attempts($model),
        ]);
    }

    /**
     * Retry a failed notification.
     *
     * Refuses a terminal intent. Retrying something already `SENT` would be the
     * duplicate FR-098 forbids, and retrying a `SUPPRESSED` one would be a route
     * around opt-out.
     */
    public function retry(string $intent): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $intent);
        Gate::authorize('send', $model);

        if (! $model->canRetry()) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'Pesan ini tidak dapat dikirim ulang. Gunakan tautan WhatsApp manual bila perlu.',
                ['state' => [$model->state]],
            );
        }

        // Recorded BEFORE the dispatch, so the act of asking is audited even if
        // the send then fails: "who spent the tenant's messaging budget" must be
        // answerable regardless of the provider's answer (Rule 46 hard rule 1).
        $this->audit->record(
            action: AuditAction::NOTIFICATION_RETRY_REQUESTED,
            subjectType: NotificationIntent::class,
            subjectId: $model->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            metadata: [
                'event_type' => $model->event_type,
                'template_key' => $model->template_key,
                'attempt_count' => (int) $model->attempt_count,
                // No recipient, no body: an audit row is not a second copy of
                // personal data (Rule 46 hard rule 2).
            ],
        );

        $result = $this->dispatcher->dispatch($model);

        return ApiResponse::success(['notification' => NotificationProjection::summary($result)]);
    }

    /**
     * Prepare the manual WhatsApp deep link (FR-095).
     *
     * The response says PREPARED, never sent. A staff member must actually send it,
     * and the copy says so — this is a fallback, never automation (NOT-007).
     */
    public function manualLink(string $intent): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $intent);
        Gate::authorize('send', $model);

        $prepared = $this->manualLink->prepare($model);

        $this->audit->record(
            action: AuditAction::NOTIFICATION_MANUAL_LINK_PREPARED,
            subjectType: NotificationIntent::class,
            subjectId: $model->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            metadata: [
                'event_type' => $model->event_type,
                'template_key' => $model->template_key,
                // The URL is NOT recorded: it contains the rendered message body,
                // and an audit row must not become a second copy of what was said
                // to a customer (Rule 46 hard rule 2).
            ],
        );

        return ApiResponse::success([
            'manual_link' => $prepared,
            'notification' => NotificationProjection::summary($model->fresh()),
            'notice' => 'Tautan ini BELUM dikirim. Buka tautan lalu kirim pesannya sendiri melalui WhatsApp Anda.',
        ]);
    }

    /**
     * Provider availability, so the UI can be honest about what is automated.
     *
     * Returns the adapter KEY and whether it is available. It never returns a
     * credential, an endpoint, or a token — a status endpoint that leaked
     * configuration would be a configuration disclosure (Rule 03).
     */
    public function providerState(): JsonResponse
    {
        Gate::authorize('viewAny', NotificationIntent::class);

        $available = $this->provider->isAvailable();

        return ApiResponse::success([
            'provider' => [
                'key' => $this->provider->key(),
                'available' => $available,
                'label' => $available
                    ? 'Pengiriman otomatis aktif'
                    : 'Pengiriman otomatis tidak aktif — gunakan tautan WhatsApp manual',
            ],
        ]);
    }

    private function findOrFail(TenantContext $context, string $intentId): NotificationIntent
    {
        $intent = NotificationIntent::query()->forTenant($context->tenantId())->find($intentId);

        if ($intent === null) {
            // Another tenant's notification 404s exactly like an absent one.
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data yang Anda cari tidak ditemukan.');
        }

        return $intent;
    }
}
