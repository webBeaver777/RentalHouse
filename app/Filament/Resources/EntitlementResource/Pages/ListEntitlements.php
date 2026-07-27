<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntitlementResource\Pages;

use App\Filament\Resources\EntitlementResource;
use Filament\Resources\Pages\ListRecords;

class ListEntitlements extends ListRecords
{
    protected static string $resource = EntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
