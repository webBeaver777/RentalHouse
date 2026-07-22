<?php

declare(strict_types=1);

/**
 * D1/D2: Billing configuration.
 *
 * Prices are in grosze (1/100 PLN).
 * Przelewy24 credentials from environment.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Product Prices (in grosze)
    |--------------------------------------------------------------------------
    | MVP products: WJAZD, WYJAZD
    | Prices managed here, not in enum (per ТЗ requirement)
    */
    'prices' => [
        'WJAZD' => (int) env('PRICE_WJAZD', 4900), // 49 PLN
        'WYJAZD' => (int) env('PRICE_WYJAZD', 4900), // 49 PLN

        // Forward-compatible (not active in MVP)
        'FULL_CYCLE' => (int) env('PRICE_FULL_CYCLE', 7900), // 79 PLN
        'TENANT_PROTECTION' => (int) env('PRICE_TENANT_PROTECTION', 2900), // 29 PLN
        'LANDLORD_PRO' => (int) env('PRICE_LANDLORD_PRO', 9900), // 99 PLN/month
        'AGENCY' => (int) env('PRICE_AGENCY', 29900), // 299 PLN/month
    ],

    /*
    |--------------------------------------------------------------------------
    | Przelewy24 Configuration
    |--------------------------------------------------------------------------
    */
    'przelewy24' => [
        'sandbox' => (bool) env('P24_SANDBOX', true),
        'merchant_id' => env('P24_MERCHANT_ID', ''),
        'pos_id' => env('P24_POS_ID', ''),
        'crc' => env('P24_CRC', ''),
        'api_key' => env('P24_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Entitlement Settings
    |--------------------------------------------------------------------------
    */
    'entitlement' => [
        // Default validity period for entitlements (months)
        'validity_months' => (int) env('ENTITLEMENT_VALIDITY_MONTHS', 12),
    ],
];
