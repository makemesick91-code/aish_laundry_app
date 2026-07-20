<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Support;

/**
 * THE SINGLE REDACTION IMPLEMENTATION.
 *
 * Used by BOTH the log processor and the audit recorder, deliberately: two
 * redaction implementations drift, and the one that drifts is the one that
 * leaks. Rule 03 hard rule 20 ("logs never contain passwords, OTPs, tokens, or
 * credentials") and Rule 21 hard rule 18 ("a SECRET value is never logged, never
 * emitted in an event, never placed in telemetry") are enforced from here.
 *
 * The match is on the KEY, at ANY depth, and is substring-based on a lowercased
 * key. `password`, `password_confirmation`, `current_password`, `hashed_password`
 * and `X-Authorization` are all caught by the same rule, because an allowlist of
 * exact key names is exactly the thing that goes stale.
 *
 * FAIL-CLOSED BIAS: it is far better to redact an innocuous field whose name
 * happens to contain "token" than to publish a credential because a new field
 * name was not anticipated.
 */
final class Redactor
{
    public const PLACEHOLDER = '[REDACTED]';

    /**
     * Key fragments that mark a value as never-loggable. Lowercased; matched as
     * a substring of the lowercased key.
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'passwd',
        'secret',
        'token',
        'authorization',
        'auth_header',
        'cookie',
        'otp',
        'credential',
        'private_key',
        'api_key',
        'apikey',
        'session_id',
        'remember_token',
        'signature',
    ];

    /**
     * Recursively redact sensitive values in an arbitrary structure.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public static function redact($value, int $depth = 0)
    {
        // Bound the recursion. A pathological structure must not become a
        // denial-of-service in the logging path.
        if ($depth > 12) {
            return self::PLACEHOLDER;
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $key => $item) {
                $out[$key] = is_string($key) && self::isSensitiveKey($key)
                    ? self::PLACEHOLDER
                    : self::redact($item, $depth + 1);
            }

            return $out;
        }

        if ($value instanceof \JsonSerializable) {
            return self::redact($value->jsonSerialize(), $depth + 1);
        }

        if ($value instanceof \Traversable) {
            return self::redact(iterator_to_array($value), $depth + 1);
        }

        return $value;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalised = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalised, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask a phone number to country code plus last four digits.
     *
     * Rule 32 hard rule 4: customer data is masked by default. Used when a phone
     * number legitimately needs to appear in an operational payload.
     */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        $lead = str_starts_with($digits, '62') ? '+62' : '';
        $tail = substr($digits, -4);

        return $lead.'*****'.$tail;
    }

    /**
     * Mask an email to its first character plus domain.
     */
    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || $email === '' || ! str_contains($email, '@')) {
            return $email === null || $email === '' ? $email : self::PLACEHOLDER;
        }

        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 1).'***@'.$domain;
    }
}
