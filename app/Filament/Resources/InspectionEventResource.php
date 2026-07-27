<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\InspectionEventResource\Pages;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * G6: Filament resource for viewing audit log (inspection events).
 */
class InspectionEventResource extends Resource
{
    protected static ?string $model = InspectionEvent::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Protokoły';

    protected static ?string $navigationLabel = 'Dziennik zdarzeń';

    protected static ?string $modelLabel = 'Zdarzenie';

    protected static ?string $pluralModelLabel = 'Zdarzenia';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->limit(8),

                Tables\Columns\TextColumn::make('protocol_id')
                    ->label('Protokół')
                    ->searchable()
                    ->limit(8),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Typ zdarzenia')
                    ->badge()
                    ->formatStateUsing(fn (InspectionEventType $state): string => $state->label()),

                Tables\Columns\TextColumn::make('actor_type')
                    ->label('Aktor')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'landlord' => 'Wynajmujący',
                        'tenant' => 'Najemca',
                        'system' => 'System',
                        'admin' => 'Admin',
                        default => $state ?? '-',
                    }),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Użytkownik')
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Typ zdarzenia')
                    ->options(collect(InspectionEventType::cases())->mapWithKeys(
                        fn (InspectionEventType $type) => [$type->value => $type->label()]
                    )),

                Tables\Filters\SelectFilter::make('actor_type')
                    ->label('Aktor')
                    ->options([
                        'landlord' => 'Wynajmujący',
                        'tenant' => 'Najemca',
                        'system' => 'System',
                        'admin' => 'Admin',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspectionEvents::route('/'),
            'view' => Pages\ViewInspectionEvent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
