<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacje o płatności')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID płatności')
                                    ->copyable(),

                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge(),

                                TextEntry::make('product_code')
                                    ->label('Produkt')
                                    ->badge(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('amount_in_pln')
                                    ->label('Kwota')
                                    ->money('PLN'),

                                TextEntry::make('currency')
                                    ->label('Waluta'),

                                TextEntry::make('description')
                                    ->label('Opis'),
                            ]),
                    ]),

                Section::make('Przelewy24')
                    ->schema([
                        TextEntry::make('p24_session_id')
                            ->label('Session ID')
                            ->copyable(),

                        TextEntry::make('p24_order_id')
                            ->label('Order ID')
                            ->placeholder('-'),

                        TextEntry::make('p24_transaction_id')
                            ->label('Transaction ID')
                            ->placeholder('-'),

                        TextEntry::make('verification_sign')
                            ->label('Verification Sign')
                            ->placeholder('-'),

                        TextEntry::make('is_verified')
                            ->label('Zweryfikowana')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Tak' : 'Nie'),

                        TextEntry::make('is_sandbox')
                            ->label('Sandbox')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Tak' : 'Nie'),
                    ])
                    ->columns(3),

                Section::make('Użytkownik')
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('Email'),

                        TextEntry::make('user.name')
                            ->label('Nazwa'),

                        TextEntry::make('buyer_email')
                            ->label('Email kupującego')
                            ->placeholder('-'),

                        TextEntry::make('buyer_name')
                            ->label('Nazwa kupującego')
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make('Daty')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Utworzono')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('started_at')
                            ->label('Rozpoczęto')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('paid_at')
                            ->label('Opłacono')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('failed_at')
                            ->label('Nieudane')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('failure_reason')
                            ->label('Powód błędu')
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
