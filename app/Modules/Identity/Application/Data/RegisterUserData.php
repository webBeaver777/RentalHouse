<?php

namespace App\Modules\Identity\Application\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class RegisterUserData extends Data
{
    public function __construct(
        #[Max(255)]
        public string $name,

        #[Email]
        #[Max(255)]
        #[Unique('users', 'email')]
        public string $email,

        #[Min(8)]
        public string $password,

        #[Max(20)]
        public ?string $phone = null,

        #[Max(5)]
        public string $preferred_locale = 'pl',
    ) {}
}
