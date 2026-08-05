<?php

declare(strict_types=1);

namespace App\Filament\Resources\InspectionEventResource\Pages;

use App\Filament\Resources\InspectionEventResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewInspectionEvent extends ViewRecord
{
    protected static string $resource = InspectionEventResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacje o zdarzeniu')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID')
                                    ->copyable(),

                                TextEntry::make('event_type')
                                    ->label('Typ zdarzenia')
                                    ->badge(),

                                TextEntry::make('created_at')
                                    ->label('Data')
                                    ->dateTime('d.m.Y H:i:s'),
                            ]),
                    ]),

                Section::make('Aktor')
                    ->schema([
                        TextEntry::make('actor_type')
                            ->label('Typ aktora'),

                        TextEntry::make('user.email')
                            ->label('Użytkownik')
                            ->placeholder('System'),

                        TextEntry::make('user.name')
                            ->label('Nazwa użytkownika')
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Section::make('Protokół')
                    ->schema([
                        TextEntry::make('protocol_id')
                            ->label('ID protokołu')
                            ->copyable(),

                        TextEntry::make('protocol.type')
                            ->label('Typ protokołu'),

                        TextEntry::make('protocol.status')
                            ->label('Status protokołu'),
                    ])
                    ->columns(3),

                Section::make('Dane techniczne')
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('Adres IP')
                            ->placeholder('-'),

                        TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->placeholder('-')
                            ->columnSpan(2),
                    ])
                    ->columns(3),

                Section::make('Metadane')
                    ->schema([
                        TextEntry::make('metadata')
                            ->label('Dane dodatkowe')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
