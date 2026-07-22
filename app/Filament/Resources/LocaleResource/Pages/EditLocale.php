<?php

declare(strict_types=1);

namespace App\Filament\Resources\LocaleResource\Pages;

use App\Filament\Resources\LocaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocale extends EditRecord
{
    protected static string $resource = LocaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
