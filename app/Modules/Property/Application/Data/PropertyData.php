<?php

namespace App\Modules\Property\Application\Data;

use App\Modules\Property\Domain\Enums\DeclarationType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class PropertyData extends Data
{
    public function __construct(
        #[Required]
        #[Max(255)]
        public string $name,

        #[Required]
        #[Max(255)]
        public string $street,

        #[Required]
        #[Max(20)]
        public string $building_number,

        #[Max(20)]
        public ?string $apartment_number,

        #[Required]
        #[Max(100)]
        public string $city,

        #[Required]
        #[Max(10)]
        public string $postal_code,

        #[Max(2)]
        public string $country = 'PL',

        public DeclarationType $declaration_type = DeclarationType::OWNER,

        public ?string $description = null,

        public ?int $user_id = null,
    ) {}
}
