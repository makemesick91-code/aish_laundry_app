<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Http;

/**
 * THE CANONICAL ERROR CODE VOCABULARY.
 *
 * Every error response carries one of these stable, machine-readable codes.
 * Clients branch on the CODE, never on the human-readable message, so the
 * message may be reworded or re-translated without breaking a client
 * (Rule 06 — "stable machine-readable error codes").
 *
 * Adding a code is additive. CHANGING or REMOVING one is a breaking API change
 * and requires a new API version, never an in-place edit of `/api/v1`
 * (Rule 06, hard rule 3).
 *
 * WHAT AN ERROR RESPONSE MUST NEVER CONTAIN
 * -----------------------------------------
 * No stack trace, no SQL, no database host or username, no Redis configuration,
 * no token, no password, no policy internals, and no statement about whether a
 * record exists in another tenant. A denial and an absence are indistinguishable
 * across a tenant boundary (Rule 02; Rule 32, hard rule 2).
 */
enum ErrorCode: string
{
    /** No usable credential was presented at all. */
    case UNAUTHENTICATED = 'UNAUTHENTICATED';

    /** A credential was presented but its lifetime has elapsed. */
    case SESSION_EXPIRED = 'SESSION_EXPIRED';

    /** A credential was presented but it was deliberately revoked. */
    case SESSION_REVOKED = 'SESSION_REVOKED';

    /** The device this credential belongs to was revoked for the active tenant. */
    case DEVICE_REVOKED = 'DEVICE_REVOKED';

    /** The membership exists but is suspended. */
    case MEMBERSHIP_SUSPENDED = 'MEMBERSHIP_SUSPENDED';

    /** The membership exists but was revoked. */
    case MEMBERSHIP_REVOKED = 'MEMBERSHIP_REVOKED';

    /**
     * The authenticated user has no ACTIVE membership in the requested tenant.
     *
     * This is deliberately returned whether the tenant does not exist, or exists
     * and the user simply has no membership in it. Distinguishing the two would
     * turn this endpoint into a tenant-enumeration oracle (Rule 02).
     */
    case TENANT_ACCESS_DENIED = 'TENANT_ACCESS_DENIED';

    /** The outlet does not belong to the active tenant, or does not exist. */
    case OUTLET_ACCESS_DENIED = 'OUTLET_ACCESS_DENIED';

    /** Authenticated and tenant-resolved, but lacking the required permission. */
    case FORBIDDEN = 'FORBIDDEN';

    /** Request payload failed validation. */
    case VALIDATION_FAILED = 'VALIDATION_FAILED';

    /** Too many attempts. */
    case RATE_LIMITED = 'RATE_LIMITED';

    /** Stateful (cookie) request arrived without a valid CSRF token. */
    case CSRF_FAILED = 'CSRF_FAILED';

    /** A dependency the request needed is unavailable. */
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    /** The requested resource does not exist within the caller's visible scope. */
    case NOT_FOUND = 'NOT_FOUND';

    /**
     * The caller is editing a version of the record that is no longer current.
     *
     * Distinct from VALIDATION_FAILED on purpose: nothing the caller SENT is
     * wrong, so an interface must not highlight a field. What changed is the
     * record underneath them, and the recovery action is to reload and re-apply —
     * never to retry the same payload, which would silently overwrite somebody
     * else's edit (threat T-12, Rule 07 hard rule 5's principle).
     */
    case CONFLICT = 'CONFLICT';

    /** The HTTP method is not allowed for this route. */
    case METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';

    /** Anything unclassified. Deliberately opaque to the client. */
    case INTERNAL_ERROR = 'INTERNAL_ERROR';

    public function httpStatus(): int
    {
        return match ($this) {
            self::UNAUTHENTICATED,
            self::SESSION_EXPIRED,
            self::SESSION_REVOKED,
            self::DEVICE_REVOKED => 401,

            self::MEMBERSHIP_SUSPENDED,
            self::MEMBERSHIP_REVOKED,
            self::TENANT_ACCESS_DENIED,
            self::OUTLET_ACCESS_DENIED,
            self::FORBIDDEN,
            self::CSRF_FAILED => 403,

            self::NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::CONFLICT => 409,
            self::VALIDATION_FAILED => 422,
            self::RATE_LIMITED => 429,
            self::SERVICE_UNAVAILABLE => 503,
            self::INTERNAL_ERROR => 500,
        };
    }

    /**
     * The default client-facing message, in Bahasa Indonesia (Rule 30).
     *
     * Every message states what happened AND what to do next. An error that only
     * names a failure, with no recovery action, is rejected by Rule 29 hard rule 9.
     */
    public function defaultMessage(): string
    {
        return match ($this) {
            self::UNAUTHENTICATED => 'Anda belum masuk. Silakan masuk kembali untuk melanjutkan.',
            self::SESSION_EXPIRED => 'Sesi Anda telah berakhir. Silakan masuk kembali.',
            self::SESSION_REVOKED => 'Sesi ini telah dicabut. Silakan masuk kembali.',
            self::DEVICE_REVOKED => 'Akses perangkat ini telah dicabut. Hubungi admin tenant Anda.',
            self::MEMBERSHIP_SUSPENDED => 'Keanggotaan Anda pada tenant ini sedang ditangguhkan. Hubungi admin tenant Anda.',
            self::MEMBERSHIP_REVOKED => 'Keanggotaan Anda pada tenant ini telah dicabut. Hubungi admin tenant Anda.',
            self::TENANT_ACCESS_DENIED => 'Anda tidak memiliki akses aktif ke tenant tersebut. Pilih tenant lain dari daftar Anda.',
            self::OUTLET_ACCESS_DENIED => 'Outlet tersebut tidak tersedia pada tenant aktif Anda. Pilih outlet lain dari daftar.',
            self::FORBIDDEN => 'Anda tidak memiliki izin untuk tindakan ini. Hubungi admin tenant Anda bila Anda memerlukannya.',
            self::VALIDATION_FAILED => 'Data yang dikirim belum lengkap atau tidak valid. Periksa kembali isian Anda.',
            self::RATE_LIMITED => 'Terlalu banyak percobaan. Tunggu beberapa saat lalu coba lagi.',
            self::CSRF_FAILED => 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.',
            self::SERVICE_UNAVAILABLE => 'Layanan sedang tidak tersedia. Coba lagi beberapa saat lagi.',
            self::NOT_FOUND => 'Data yang Anda cari tidak ditemukan.',
            self::CONFLICT => 'Data ini sudah diubah oleh orang lain sejak Anda membukanya. Muat ulang untuk melihat perubahan terbaru, lalu ulangi penyuntingan Anda.',
            self::METHOD_NOT_ALLOWED => 'Metode permintaan tidak didukung untuk alamat ini.',
            self::INTERNAL_ERROR => 'Terjadi kesalahan pada sistem. Coba lagi beberapa saat lagi.',
        };
    }
}
