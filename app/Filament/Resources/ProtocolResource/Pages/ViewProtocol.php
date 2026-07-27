<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProtocolResource\Pages;

use App\Filament\Resources\ProtocolResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * G6: Read-only view page for protocols.
 *
 * Shows all protocol details without edit capabilities.
 */
class ViewProtocol extends ViewRecord
{
    protected static string $resource = ProtocolResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacje podstawowe')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID protokołu'),

                                TextEntry::make('type')
                                    ->label('Typ')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => $state->value === 'check_in' ? 'Przekazanie' : 'Zdanie'),

                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => $state->label()),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('legal_mode')
                                    ->label('Tryb prawny')
                                    ->formatStateUsing(fn ($state): string => $state?->label() ?? 'Nie określono'),

                                TextEntry::make('initiator_role')
                                    ->label('Rola inicjatora')
                                    ->formatStateUsing(fn ($state): string => $state?->value === 'landlord' ? 'Wynajmujący' : 'Najemca'),

                                TextEntry::make('locale')
                                    ->label('Język'),
                            ]),
                    ]),

                Section::make('Nieruchomość')
                    ->schema([
                        TextEntry::make('property.full_address')
                            ->label('Adres'),

                        TextEntry::make('property.name')
                            ->label('Nazwa'),
                    ])
                    ->columns(2),

                Section::make('Uczestnicy')
                    ->schema([
                        TextEntry::make('initiator.invited_email')
                            ->label('Inicjator (email)'),

                        TextEntry::make('initiator.accepted_at')
                            ->label('Zaakceptowano')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Oczekuje'),

                        TextEntry::make('counterparty.invited_email')
                            ->label('Druga strona (email)')
                            ->placeholder('Nie zaproszono'),

                        TextEntry::make('counterparty.accepted_at')
                            ->label('Zaakceptowano')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Oczekuje'),
                    ])
                    ->columns(2),

                Section::make('Dokumenty')
                    ->schema([
                        TextEntry::make('document_hash')
                            ->label('Hash dokumentu')
                            ->placeholder('Nie wygenerowano')
                            ->copyable(),

                        TextEntry::make('deposit_amount')
                            ->label('Kaucja')
                            ->money('PLN')
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make('Daty')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Utworzono')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('scheduled_at')
                            ->label('Zaplanowano')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('completed_at')
                            ->label('Zakończono')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('paid_at')
                            ->label('Opłacono')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('access_expires_at')
                            ->label('Dostęp wygasa')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('retention_until')
                            ->label('Retencja do')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
