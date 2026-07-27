<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntitlementResource\Pages;

use App\Filament\Resources\EntitlementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextEntry;
use Filament\Schemas\Schema;

class ViewEntitlement extends ViewRecord
{
    protected static string $resource = EntitlementResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacje o uprawnieniu')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID')
                                    ->copyable(),

                                TextEntry::make('product_code')
                                    ->label('Produkt')
                                    ->badge(),

                                TextEntry::make('allowed_action')
                                    ->label('Akcja')
                                    ->badge(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('quantity_total')
                                    ->label('Ilość łącznie'),

                                TextEntry::make('quantity_used')
                                    ->label('Wykorzystano'),

                                TextEntry::make('remaining_quantity')
                                    ->label('Pozostało'),
                            ]),
                    ]),

                Section::make('Użytkownik i źródło')
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('Email użytkownika'),

                        TextEntry::make('user.name')
                            ->label('Nazwa użytkownika'),

                        TextEntry::make('payment.id')
                            ->label('ID płatności')
                            ->placeholder('Brak płatności'),
                    ])
                    ->columns(3),

                Section::make('Daty')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Utworzono')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('activated_at')
                            ->label('Aktywowano')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('valid_until')
                            ->label('Ważne do')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Bezterminowe'),

                        TextEntry::make('expired_at')
                            ->label('Wygasło')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),
                    ])
                    ->columns(4),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
