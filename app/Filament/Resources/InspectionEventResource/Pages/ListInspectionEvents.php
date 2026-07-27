<?php

declare(strict_types=1);

namespace App\Filament\Resources\InspectionEventResource\Pages;

use App\Filament\Resources\InspectionEventResource;
use Filament\Resources\Pages\ListRecords;

class ListInspectionEvents extends ListRecords
{
    protected static string $resource = InspectionEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
