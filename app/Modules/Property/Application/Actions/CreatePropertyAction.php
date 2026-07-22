<?php

namespace App\Modules\Property\Application\Actions;

use App\Modules\Property\Application\Data\PropertyData;
use App\Modules\Property\Infrastructure\Models\Property;

class CreatePropertyAction
{
    public function execute(PropertyData $data, int $userId): Property
    {
        return Property::create([
            'user_id' => $userId,
            'name' => $data->name,
            'street' => $data->street,
            'building_number' => $data->building_number,
            'apartment_number' => $data->apartment_number,
            'city' => $data->city,
            'postal_code' => $data->postal_code,
            'country' => $data->country,
            'declaration_type' => $data->declaration_type,
            'description' => $data->description,
        ]);
    }
}
