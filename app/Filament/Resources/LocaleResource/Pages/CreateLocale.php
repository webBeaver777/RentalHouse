<?php

declare(strict_types=1);

namespace App\Filament\Resources\LocaleResource\Pages;

use App\Filament\Resources\LocaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocale extends CreateRecord
{
    protected static string $resource = LocaleResource::class;
}
