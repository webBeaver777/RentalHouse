<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntitlementResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * G6: Relation manager to show entitlement usages.
 */
class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'Wykorzystanie';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->limit(8),

                Tables\Columns\TextColumn::make('protocol_id')
                    ->label('Protokół')
                    ->limit(8)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('action_performed')
                    ->label('Akcja'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }
}
