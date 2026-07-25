<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Aish Laundry App — application configuration
|--------------------------------------------------------------------------
|
| Product-specific settings that are not part of a framework config file.
| Every value here is operational (lifetimes, transports). NO product decision
| lives in this file: pricing, the roadmap, the tenant hierarchy and the
| reminder ladder are owner territory and are recorded in the Master Source
| (Rule 14, Rule 16).
|
| Secrets never appear here. Configuration and secrets come from the
| environment, never from a committed file (Rule 03, hard rule 10; Rule 06).
|
*/

return [

    'session' => [
        /*
         * Lifetime of a mobile access token, in days.
         *
         * Bounded deliberately: a credential that never expires is a credential
         * that outlives the device it was issued to. Revocation is immediate and
         * independent of this value — expiry is the backstop, not the control.
         */
        'token_lifetime_days' => (int) env('AISH_TOKEN_LIFETIME_DAYS', 30),

        /*
         * Lifetime of a tenant-scoped device session registration, in days.
         */
        'device_lifetime_days' => (int) env('AISH_DEVICE_LIFETIME_DAYS', 30),
    ],

    'password_reset' => [
        /*
         * LOCAL TRANSPORT ONLY.
         *
         * Step 3 introduces no third-party service. Adding an email or WhatsApp
         * provider requires owner approval and a decision record (Rule 12).
         *
         * THE RESET LINK IS NOT WRITTEN TO A LOG. It previously was, in the
         * message string rather than the context array, deliberately placed
         * where the redaction processor would not reach it. The reasoning was
         * that relying on redaction to carry a secret depends on a control
         * failing safely — which is sound, and led to the wrong conclusion: the
         * fix is not to route around the redactor, it is not to put the token
         * in a log at all. Rule 46 hard rule 2 is absolute: password-reset
         * tokens are never written to logs, "at any log level, temporarily or
         * permanently." Found in Step 3 code by the Step 4 independent review.
         *
         * The link is written to a single-file drop under `storage/app/`, which
         * is git-ignored and never shipped, aggregated, or retained the way a
         * log stream is. The log records only that a link was written and
         * where.
         */
        'link_path' => env(
            'AISH_PASSWORD_RESET_LINK_PATH',
            storage_path('app/password-reset-link.txt')
        ),

        'log_channel' => env('AISH_PASSWORD_RESET_LOG_CHANNEL', 'single'),
    ],

    /*
     * Step 7 — notification and WhatsApp (FR-093 … FR-099).
     *
     * EVERY VALUE HERE IS A TRANSPORT SETTING, NOT A PRODUCT DECISION. Quiet
     * hours are 20.00–08.00 outlet local time by canonical rule (FR-097,
     * NOT-003) and are therefore NOT configurable here — a deployment that
     * could widen them would be a deployment that could evade them, and
     * changing them requires a decision record (NOT-022), not an env var.
     *
     * NO CREDENTIAL IS COMMITTED AND NONE IS DEFAULTED TO A REAL VALUE. Every
     * field below defaults to empty, which makes the official adapter report
     * itself UNAVAILABLE and the system fall back to the manual deep link
     * (Rule 03 hard rule 10, Rule 45).
     *
     * `enabled` defaults to FALSE. Configuring credentials is not the same act
     * as authorising live sends to real customers, and separating the two means
     * a half-finished configuration cannot start messaging anybody.
     */
    'notification' => [
        'whatsapp' => [
            'enabled' => (bool) env('AISH_WHATSAPP_ENABLED', false),

            /*
             * The official WhatsApp Business API endpoint. Empty by default:
             * an adapter with no base URL is not configured, and it says so
             * rather than guessing a vendor's host.
             */
            'base_url' => env('AISH_WHATSAPP_BASE_URL', ''),
            'phone_number_id' => env('AISH_WHATSAPP_PHONE_NUMBER_ID', ''),
            'access_token' => env('AISH_WHATSAPP_ACCESS_TOKEN', ''),
        ],
    ],

];
