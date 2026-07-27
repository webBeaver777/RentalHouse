<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EntitlementResource\Pages;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * G6: Filament resource for viewing entitlements and usages.
 */
class EntitlementResource extends Resource
{
    protected static ?string $model = Entitlement::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Płatności';

    protected static ?string $navigationLabel = 'Uprawnienia';

    protected static ?string $modelLabel = 'Uprawnienie';

    protected static ?string $pluralModelLabel = 'Uprawnienia';

    protected static ?int $navigationSort = 21;

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

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Użytkownik')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_code')
                    ->label('Produkt')
                    ->badge()
                    ->formatStateUsing(fn (ProductCode $state): string => $state->value),

                Tables\Columns\TextColumn::make('allowed_action')
                    ->label('Akcja')
                    ->badge()
                    ->formatStateUsing(fn (AllowedAction $state): string => $state->label()),

                Tables\Columns\TextColumn::make('quantity_total')
                    ->label('Razem')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('quantity_used')
                    ->label('Użyto')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('remaining_quantity')
                    ->label('Pozostało')
                    ->alignCenter()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Ważne do')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Bezterminowe')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_valid')
                    ->label('Ważne')
                    ->boolean()
                    ->getStateUsing(fn (Entitlement $record): bool => $record->isValid()),

                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Aktywowano')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('product_code')
                    ->label('Produkt')
                    ->options(collect(ProductCode::cases())->mapWithKeys(
                        fn (ProductCode $code) => [$code->value => $code->value]
                    )),

                Tables\Filters\SelectFilter::make('allowed_action')
                    ->label('Akcja')
                    ->options(collect(AllowedAction::cases())->mapWithKeys(
                        fn (AllowedAction $action) => [$action->value => $action->label()]
                    )),

                Tables\Filters\TernaryFilter::make('is_valid')
                    ->label('Ważne')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNull('expired_at')
                            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
                            ->whereRaw('quantity_used < quantity_total'),
                        false: fn (Builder $query) => $query->where(
                            fn ($q) => $q->whereNotNull('expired_at')
                                ->orWhereRaw('quantity_used >= quantity_total')
                        ),
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            EntitlementResource\RelationManagers\UsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntitlements::route('/'),
            'view' => Pages\ViewEntitlement::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withTrashed();
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
