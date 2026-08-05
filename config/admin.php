<?php

declare(strict_types=1);

/**
 * M8 Phase 1: Admin bootstrap credentials.
 *
 * Wrapping env() in a config file (rather than calling env() directly
 * in AdminSeeder) is required so this still works after
 * `artisan config:cache` in production — direct env() calls outside
 * config files return null once config is cached.
 */

return [
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
