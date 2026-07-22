<?php

namespace App\Modules\Protocol\Application\Data;

use App\Modules\Protocol\Domain\Enums\ProtocolType;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ProtocolData extends Data
{
    public function __construct(
        #[Required]
        public int $property_id,

        #[Required]
        public ProtocolType $type,

        #[Required]
        #[Max(255)]
        public string $title,

        public ?string $description = null,

        public ?Carbon $scheduled_at = null,

        public ?int $created_by_user_id = null,

        public ?array $metadata = null,
    ) {}
}
