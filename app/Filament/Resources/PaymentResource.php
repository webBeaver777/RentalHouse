<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Modules\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Billing\Infrastructure\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * G6: Filament resource for viewing payments.
 *
 * Read-only view of Przelewy24 payments.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Płatności';

    protected static ?string $navigationLabel = 'Płatności';

    protected static ?string $modelLabel = 'Płatność';

    protected static ?string $pluralModelLabel = 'Płatności';

    protected static ?int $navigationSort = 20;

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
                    ->formatStateUsing(fn ($state): string => $state->value),

                Tables\Columns\TextColumn::make('amount_in_pln')
                    ->label('Kwota')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::PENDING => 'gray',
                        PaymentStatus::STARTED => 'warning',
                        PaymentStatus::PAID => 'success',
                        PaymentStatus::FAILED, PaymentStatus::CANCELLED => 'danger',
                        PaymentStatus::REFUNDED => 'info',
                    }),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Zweryfikowana')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_sandbox')
                    ->label('Sandbox')
                    ->boolean(),

                Tables\Columns\TextColumn::make('p24_transaction_id')
                    ->label('P24 ID')
                    ->placeholder('-')
                    ->limit(15),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Data płatności')
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
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(
                        fn (PaymentStatus $status) => [$status->value => $status->label()]
                    )),

                Tables\Filters\TernaryFilter::make('is_sandbox')
                    ->label('Sandbox'),

                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Zweryfikowana'),
            ])
            ->actions([
                ViewAction::make(),
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
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
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
