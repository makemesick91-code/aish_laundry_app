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
         * Until then the reset link is written to this log channel, which is
         * honest about what it is rather than pretending to be delivery.
         */
        'log_channel' => env('AISH_PASSWORD_RESET_LOG_CHANNEL', 'single'),
    ],

];
