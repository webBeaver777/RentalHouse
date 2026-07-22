<?php

declare(strict_types=1);

/**
 * D8: Lifecycle configuration.
 *
 * Defines access periods and retention times.
 * All values are configurable, not hardcoded constants.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Access Period (months)
    |--------------------------------------------------------------------------
    | How long user has access to protocol after payment.
    | Canon: paid_at + access_months = access_expires_at
    */
    'access_months' => (int) env('LIFECYCLE_ACCESS_MONTHS', 12),

    /*
    |--------------------------------------------------------------------------
    | Retention Period (years)
    |--------------------------------------------------------------------------
    | How long data is retained after access expires (RODO).
    | Canon: access_expires_at + retention_years = retention_until
    */
    'retention_years' => (int) env('LIFECYCLE_RETENTION_YEARS', 3),

    /*
    |--------------------------------------------------------------------------
    | Objection Window (hours)
    |--------------------------------------------------------------------------
    | Time window for counterparty to raise objections on check-out.
    */
    'objection_window_hours' => (int) env('LIFECYCLE_OBJECTION_WINDOW_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Invitation Token Expiry (hours)
    |--------------------------------------------------------------------------
    | Magic-link validity period.
    */
    'invitation_token_hours' => (int) env('LIFECYCLE_INVITATION_TOKEN_HOURS', 72),
];
