<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Controllers;

use App\Modules\Notification\Services\OtpMessenger;
use App\Modules\Ordering\Models\Order;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tracking\Http\PublicTrackingProjection;
use App\Modules\Tracking\Models\TrackingOtpChallenge;
use App\Modules\Tracking\Services\PublicTrackingResolver;
use App\Modules\Tracking\Services\TrackingOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * THE PUBLIC TRACKING SURFACE (FR-089 … FR-092).
 *
 * Unauthenticated by design — a customer must be able to follow their laundry
 * without an account and without installing anything (DEC-0006, DEC-0014, TRK-025).
 * Everything that makes that safe lives in three places: the resolver (enumeration
 * protection, rate limiting, fail-closed tenant derivation), the projection
 * (allow-list, masking at build time), and the headers middleware (noindex,
 * no-store, no-referrer, CSP).
 *
 * ONE FAILURE RESPONSE, USED SIX WAYS
 * -----------------------------------
 * `notAvailable()` is the single response for unknown, malformed, expired, revoked,
 * superseded, and throttled. Identical body, identical status, identical shape, no
 * branch anywhere in this class that could vary it. `PublicTrackingApiTest` asserts
 * the six responses are byte-identical — because a difference of even one character
 * would turn this endpoint into an oracle answering "does this order exist?"
 * (TRK-007, AC-07-02, Rule 48 hard rule 5).
 *
 * The message names the recovery step in Bahasa Indonesia — "minta tautan baru dari
 * outlet" — rather than an error code, because a customer holding a dead link needs
 * to know what to do, not what went wrong internally (Rule 29 hard rule 9,
 * TRACKING_ACCESS_LIFECYCLE §4.2).
 */
final class PublicTrackingController
{
    public function __construct(
        private readonly PublicTrackingResolver $resolver,
        private readonly TrackingOtpService $otp,
        private readonly OtpMessenger $otpMessenger,
    ) {
    }

    /** The server-rendered portal page. */
    public function page(Request $request, string $token): View
    {
        $access = $this->resolver->resolve($token, (string) $request->ip());

        if ($access === null) {
            return view('tracking.unavailable');
        }

        return view('tracking.show', [
            'tracking' => PublicTrackingProjection::build($access->token, $access->order),
        ]);
    }

    /** The JSON projection, for any client that prefers data to markup. */
    public function show(Request $request, string $token): JsonResponse
    {
        $access = $this->resolver->resolve($token, (string) $request->ip());

        if ($access === null) {
            return $this->notAvailable();
        }

        return ApiResponse::success([
            'tracking' => PublicTrackingProjection::build($access->token, $access->order),
        ]);
    }

    /**
     * Request an OTP for one of the two FR-091 sensitive actions.
     *
     * The response is identical whether the challenge was issued, the action was
     * unknown, the token was dead, or the requester is being throttled. A caller
     * that could tell "OTP sent" from "no such link" would have an oracle again.
     */
    public function requestOtp(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:'.implode(',', TrackingOtpChallenge::ACTIONS)],
        ]);

        $access = $this->resolver->resolve($token, (string) $request->ip());

        if ($access !== null) {
            $code = $this->otp->issue($access, $validated['action'], (string) $request->ip());

            if ($code !== null) {
                // The OTP is delivered through the notification subsystem on a
                // template that carries NO tracking link (TRK-029, NOT-014). The
                // code is passed straight through and never persisted here.
                $this->deliverOtp($access->order, $code);
            }
        }

        // Deliberately the same body in every case above.
        return ApiResponse::success([
            'status' => 'otp_requested',
            'message' => 'Bila tautan ini masih aktif, kode verifikasi dikirim ke nomor WhatsApp pelanggan. '
                .'Kode berlaku 5 menit.',
        ]);
    }

    /**
     * Verify a submitted OTP.
     *
     * On success this records that the customer's request was ACCEPTED. It does not
     * reschedule a delivery and does not rewrite an address: both act on Step 8
     * workflow that does not exist, and building the effect here would be the scope
     * leak DEC-0039 §5 forbids. The gate is Step 7's obligation; the effect is not.
     */
    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:'.implode(',', TrackingOtpChallenge::ACTIONS)],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $access = $this->resolver->resolve($token, (string) $request->ip());

        $verified = $access !== null
            && $this->otp->verify($access, $validated['action'], $validated['code']);

        if (! $verified) {
            // One body for every failure: wrong code, expired, consumed, wrong
            // action, attempts exhausted, dead link, throttled.
            return ApiResponse::error(
                ErrorCode::VALIDATION_FAILED,
                'Kode verifikasi tidak dapat diterima. Minta kode baru lalu coba lagi.',
            );
        }

        return ApiResponse::success([
            'status' => 'verified',
            'action' => $validated['action'],
            'message' => 'Permintaan Anda sudah tercatat dan akan ditindaklanjuti oleh outlet.',
        ]);
    }

    /**
     * The single not-available response. One method, so it cannot drift.
     *
     * A 404 with a body that says nothing about WHY. Note it does not use
     * `ErrorCode::NOT_FOUND`'s default message: this surface speaks to a customer
     * holding a link, and the recovery action is specific.
     */
    private function notAvailable(): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'TRACKING_LINK_NOT_AVAILABLE',
                'message' => 'Tautan pelacakan ini tidak dapat dibuka. '
                    .'Silakan minta tautan baru dari outlet tempat Anda menitipkan cucian.',
            ],
        ], 404);
    }

    /**
     * Hand the OTP to the notification subsystem.
     *
     * `OtpMessenger` never throws, so a messaging failure cannot affect the OTP
     * response — the challenge has already been recorded, and FR-099 applies here
     * exactly as everywhere else: messaging is a side effect, never a dependency.
     *
     * The return value is deliberately discarded. Letting the send outcome vary the
     * HTTP response would tell a caller whether the customer's number is reachable,
     * which is an oracle about the customer rather than the link.
     */
    private function deliverOtp(Order $order, string $code): void
    {
        $this->otpMessenger->send($order, $code);
    }
}
