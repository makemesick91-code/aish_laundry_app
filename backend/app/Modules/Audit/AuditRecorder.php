<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\SharedKernel\Http\CorrelationId;
use App\Modules\SharedKernel\Support\Redactor;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * THE ONLY WRITE PATH INTO THE AUDIT TRAIL.
 *
 * Every audited action goes through `record()`, which:
 *   1. rejects an action outside the closed vocabulary (AuditAction);
 *   2. rejects a whitespace-only reason where a reason is required;
 *   3. REDACTS metadata through the shared Redactor before it touches the
 *      database — so a credential cannot reach the audit table even if a caller
 *      hands one over by mistake;
 *   4. stamps the request correlation id, making "everything that happened in
 *      this request" answerable across logs and audit alike.
 *
 * WHAT MUST NEVER REACH THIS TABLE
 * --------------------------------
 * Password, password hash, reset token, raw access token, Authorization header,
 * cookie, OTP, a full request body, or PII unrelated to the action. The
 * Redactor handles credential-shaped KEYS; the caller is still responsible for
 * not passing unrelated personal data, because no automated filter can know
 * which PII was relevant to the action (Rule 03, Rule 21, Rule 23).
 *
 * TENANT SCOPE
 * ------------
 * `tenant_id` is NULL only for identity/platform-scope events (login, logout,
 * password reset) which genuinely belong to no tenant. A database CHECK
 * constraint makes the tenant mandatory as soon as the entry references an
 * outlet or a membership, so a tenant-scoped event cannot lose its tenant.
 */
final class AuditRecorder
{
    /**
     * Record an audit entry.
     *
     * @param  array<string, mixed>  $metadata  Redacted before persistence.
     * @param  array<string, mixed>|null  $changes  Before/after values; redacted.
     */
    public function record(
        string $action,
        string $subjectType,
        string $subjectId,
        ?string $tenantId = null,
        ?string $actorUserId = null,
        ?string $actorMembershipId = null,
        ?string $outletId = null,
        ?string $reason = null,
        array $metadata = [],
        ?array $changes = null,
        ?Request $request = null,
        ?string $impersonatorUserId = null,
    ): AuditEntry {
        if (! in_array($action, AuditAction::all(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Audit action "%s" is not in the closed vocabulary. Add it to AuditAction '
                .'deliberately rather than writing free text — a free-text action cannot be '
                .'aggregated or alerted on.',
                $action
            ));
        }

        // A tenant-scoped subject may not lose its tenant. This mirrors the
        // database CHECK constraint; failing here gives a far better message
        // than a SQLSTATE would.
        if ($tenantId === null && ($outletId !== null || $actorMembershipId !== null)) {
            throw new InvalidArgumentException(
                'An audit entry referencing an outlet or a membership must carry its tenant_id '
                .'(Rule 02). Only identity/platform-scope events may have a null tenant.'
            );
        }

        $normalisedReason = $reason === null ? null : trim($reason);

        if ($normalisedReason === '') {
            throw new InvalidArgumentException(
                'A recorded reason must not be whitespace-only (Rule 32, hard rule 16). '
                .'Pass null when no reason is required, or a real reason when one is.'
            );
        }

        /** @var array<string, mixed> $safeMetadata */
        $safeMetadata = Redactor::redact($metadata);
        $safeMetadata['request_id'] = $this->correlationId();

        /** @var array<string, mixed>|null $safeChanges */
        $safeChanges = $changes === null ? null : Redactor::redact($changes);

        return AuditEntry::create([
            'tenant_id' => $tenantId,
            'outlet_id' => $outletId,
            'actor_user_id' => $actorUserId,
            'actor_membership_id' => $actorMembershipId,
            'impersonator_user_id' => $impersonatorUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => $normalisedReason,
            'changes' => $safeChanges,
            'metadata' => $safeMetadata,
            'ip_address' => $request?->ip(),
            // The user agent is recorded because it is genuinely useful for
            // "which device did this". It is never used as an authorization
            // signal (Rule 31, hard rule 12).
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Record a FAILED login.
     *
     * The failure category is recorded for the operator. It is NEVER returned to
     * the client, and the submitted identifier is NEVER stored in plaintext —
     * only a keyed hash, so an attacker who obtains the audit table cannot read
     * off a list of which identifiers were tried against this system, and cannot
     * confirm whether a given account exists here.
     *
     * `subject_id` is a deterministic UUID derived from that same hash, so
     * repeated attempts against one identifier can be correlated and rate-limit
     * analysis remains possible — without the identifier itself being present.
     */
    public function recordLoginFailure(
        string $submittedIdentifier,
        string $failureCategory,
        ?Request $request = null,
        ?string $knownUserId = null,
    ): AuditEntry {
        $identifierHash = hash_hmac(
            'sha256',
            strtolower(trim($submittedIdentifier)),
            (string) config('app.key')
        );

        return $this->record(
            action: AuditAction::AUTH_LOGIN_FAILED,
            subjectType: 'identity.login_attempt',
            subjectId: $this->deterministicUuid($identifierHash),
            actorUserId: $knownUserId,
            metadata: [
                'failure_category' => $failureCategory,
                'identifier_hash' => substr($identifierHash, 0, 32),
            ],
            request: $request,
        );
    }

    /**
     * Derive a stable UUIDv5-shaped value from a hash, so `subject_id` can be a
     * uuid column without carrying the identifier itself.
     */
    private function deterministicUuid(string $hash): string
    {
        $hex = substr($hash, 0, 32);

        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            dechex((hexdec(substr($hex, 16, 1)) & 0x3) | 0x8),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }

    private function correlationId(): string
    {
        if (app()->bound(CorrelationId::class)) {
            return app(CorrelationId::class)->value;
        }

        return 'no-request-context';
    }
}
