<?php

declare(strict_types=1);

namespace App\Filament\Resources\GeneratedDocumentResource\Pages;

use App\Filament\Resources\GeneratedDocumentResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewGeneratedDocument extends ViewRecord
{
    protected static string $resource = GeneratedDocumentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacje o dokumencie')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID')
                                    ->copyable(),

                                TextEntry::make('type')
                                    ->label('Typ')
                                    ->badge(),

                                TextEntry::make('template_type')
                                    ->label('Szablon')
                                    ->placeholder('-'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('locale')
                                    ->label('Język'),

                                TextEntry::make('version')
                                    ->label('Wersja'),

                                TextEntry::make('filename')
                                    ->label('Nazwa pliku'),
                            ]),
                    ]),

                Section::make('Plik')
                    ->schema([
                        TextEntry::make('path')
                            ->label('Ścieżka'),

                        TextEntry::make('disk')
                            ->label('Dysk'),

                        TextEntry::make('mime_type')
                            ->label('MIME'),

                        TextEntry::make('size')
                            ->label('Rozmiar')
                            ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).' KB'),

                        TextEntry::make('hash')
                            ->label('Hash SHA-256')
                            ->copyable(),
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

                Section::make('Daty')
                    ->schema([
                        TextEntry::make('generated_at')
                            ->label('Wygenerowano')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('generatedBy.email')
                            ->label('Wygenerował')
                            ->placeholder('System'),

                        TextEntry::make('created_at')
                            ->label('Utworzono')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Zaktualizowano')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(4),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
