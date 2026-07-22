<?php

namespace App\Modules\Protocol\Application\Actions;

use App\Modules\Protocol\Application\Data\ProtocolData;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Infrastructure\Models\Protocol;

class CreateProtocolAction
{
    public function execute(ProtocolData $data, int $userId): Protocol
    {
        return Protocol::create([
            'property_id' => $data->property_id,
            'created_by_user_id' => $userId,
            'type' => $data->type,
            'status' => ProtocolStatus::DRAFT,
            'title' => $data->title,
            'description' => $data->description,
            'scheduled_at' => $data->scheduled_at,
            'metadata' => $data->metadata,
        ]);
    }
}
