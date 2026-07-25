<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Support;

use App\Modules\Ordering\Models\Order;

/**
 * THE INTERNAL → CUSTOMER-FACING STATUS MAP (FR-089).
 *
 * The fifteen canonical internal statuses (Rule 19) are an OPERATIONAL vocabulary.
 * Publishing them to an anonymous visitor would disclose how the tenant runs its
 * floor — which stages exist, that an order is in rework, that quality control
 * failed — none of which is the customer's business and some of which is
 * commercially sensitive.
 *
 * So the portal speaks a smaller, blunter vocabulary, in Bahasa Indonesia
 * (Rule 30). This class is the ONE place the mapping lives, so the portal, the
 * timeline, and any notification body can never disagree about what a customer was
 * told.
 *
 * TWO MAPPINGS THAT LOOK ARBITRARY AND ARE NOT
 * --------------------------------------------
 * `REWORK` maps to `PEMERIKSAAN` (quality checking), not back to `DIPROSES`. Going
 * "backwards" in front of a customer invites the question the counter cannot
 * answer, and rework IS a quality activity — describing it as one is accurate, not
 * euphemistic.
 *
 * `DRAFT` has no mapping at all. A draft order is not customer-facing and
 * `TrackingTokenService::issue()` refuses to mint a link against one, so a draft
 * can never reach this map. `for()` treats it as unknown rather than inventing a
 * label for a state the customer should never see.
 */
final class CustomerVisibleStatus
{
    public const RECEIVED = 'DITERIMA';

    public const IN_PROGRESS = 'DIPROSES';

    public const CHECKING = 'PEMERIKSAAN';

    public const READY = 'SIAP_DIAMBIL';

    public const IN_TRANSIT = 'DIANTAR';

    public const COMPLETED = 'SELESAI';

    public const CANCELLED = 'DIBATALKAN';

    public const NEEDS_ATTENTION = 'PERLU_TINDAKAN';

    /** Fallback for a status this map does not recognise. Fails SAFE: it discloses nothing. */
    public const UNKNOWN = 'DALAM_PROSES';

    /**
     * @var array<string, string> internal canonical status → customer-facing code
     */
    private const MAP = [
        'RECEIVED' => self::RECEIVED,
        'AWAITING_PROCESS' => self::RECEIVED,

        'SORTING' => self::IN_PROGRESS,
        'WASHING' => self::IN_PROGRESS,
        'DRYING' => self::IN_PROGRESS,
        'FINISHING' => self::IN_PROGRESS,

        'QUALITY_CONTROL' => self::CHECKING,
        'REWORK' => self::CHECKING,

        'READY_FOR_PICKUP' => self::READY,

        'SCHEDULED_FOR_DELIVERY' => self::IN_TRANSIT,
        'OUT_FOR_DELIVERY' => self::IN_TRANSIT,

        'COMPLETED' => self::COMPLETED,
        'CANCELLED' => self::CANCELLED,
        'ISSUE' => self::NEEDS_ATTENTION,
    ];

    /**
     * @var array<string, string> customer-facing code → Bahasa Indonesia label
     */
    private const LABELS = [
        self::RECEIVED => 'Pesanan diterima',
        self::IN_PROGRESS => 'Sedang dikerjakan',
        self::CHECKING => 'Pemeriksaan mutu',
        self::READY => 'Siap diambil',
        self::IN_TRANSIT => 'Dalam pengantaran',
        self::COMPLETED => 'Selesai',
        self::CANCELLED => 'Dibatalkan',
        self::NEEDS_ATTENTION => 'Perlu tindakan — silakan hubungi outlet',
        self::UNKNOWN => 'Sedang diproses',
    ];

    public static function codeFor(string $internalStatus): string
    {
        return self::MAP[$internalStatus] ?? self::UNKNOWN;
    }

    public static function label(string $code): string
    {
        return self::LABELS[$code] ?? self::LABELS[self::UNKNOWN];
    }

    /**
     * @return array{code: string, label: string}
     */
    public static function for(string $internalStatus): array
    {
        $code = self::codeFor($internalStatus);

        return ['code' => $code, 'label' => self::label($code)];
    }

    /**
     * Is this internal status one a customer may be shown a link for at all?
     *
     * Only DRAFT is excluded, and it is excluded at issuance too. Belt and braces:
     * an order that somehow reached DRAFT again after a link existed must not have
     * its (nonexistent) draft contents rendered.
     */
    public static function isPubliclyRenderable(string $internalStatus): bool
    {
        return $internalStatus !== Order::STATUS_DRAFT;
    }
}
