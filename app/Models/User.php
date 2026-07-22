<?php

namespace App\Models;

// This class is deprecated. Use App\Modules\Identity\Infrastructure\Models\User instead.
// Kept for backwards compatibility with Laravel's default expectations.

use App\Modules\Identity\Infrastructure\Models\User as IdentityUser;

class User extends IdentityUser
{
    // All functionality is in the Identity module's User model
}
