<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProtocolResource\Pages;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Protocol\Domain\Enums\LegalMode;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * G6: Filament resource for viewing protocols.
 *
 * IMPORTANT: Sealed protocols (SIGNED, COMPLETED, ARCHIVED) cannot be edited.
 * This resource is read-only with view-only access for sealed states.
 */
class ProtocolResource extends Resource
{
    protected static ?string $model = Protocol::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Protokoły';

    protected static ?string $navigationLabel = 'Protokoły';

    protected static ?string $modelLabel = 'Protokół';

    protected static ?string $pluralModelLabel = 'Protokoły';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        // Read-only view form - no editing allowed for sealed protocols
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->limit(8)
                    ->tooltip(fn (Protocol $record): string => (string) $record->id),

                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (ProtocolType $state): string => $state->shortLabel())
                    ->color(fn (ProtocolType $state): string => match ($state) {
                        ProtocolType::CHECK_IN => 'success',
                        ProtocolType::CHECK_OUT => 'warning',
                        ProtocolType::PERIODIC => 'info',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProtocolStatus $state): string => $state->label())
                    ->color(fn (ProtocolStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('legal_mode')
                    ->label('Tryb prawny')
                    ->badge()
                    ->formatStateUsing(fn (?LegalMode $state): string => $state?->label() ?? '-')
                    ->color(fn (?LegalMode $state): string => match ($state) {
                        LegalMode::BILATERAL, LegalMode::BILATERAL_COMPLETED => 'success',
                        LegalMode::UNILATERAL_LANDLORD, LegalMode::UNILATERAL_TENANT, LegalMode::UNILATERAL_NO_RESPONSE => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('initiator_role')
                    ->label('Inicjator')
                    ->formatStateUsing(fn (?ParticipantRole $state): string => match ($state) {
                        ParticipantRole::LANDLORD => 'Wynajmujący',
                        ParticipantRole::TENANT => 'Najemca',
                        default => '-',
                    }),

                Tables\Columns\TextColumn::make('property.full_address')
                    ->label('Nieruchomość')
                    ->searchable(['property.street', 'property.city'])
                    ->limit(30),

                Tables\Columns\TextColumn::make('counterparty_email')
                    ->label('Druga strona')
                    // M8-VERIFY fix: 'counterparty.invited_email' dot-notation
                    // crashed with "Call to a member function getRelated() on
                    // null" — Protocol::counterparty() is a helper
                    // (participants()->where(...)->first()) that returns
                    // ?Participant, not an Eloquent Relation, so Filament's
                    // relationship-path resolution can't introspect it.
                    ->getStateUsing(fn (Protocol $record): ?string => $record->counterparty()?->invited_email)
                    ->placeholder('Brak')
                    ->limit(25),

                Tables\Columns\IconColumn::make('document_hash')
                    ->label('PDF')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Protocol $record): bool => $record->document_hash !== null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Zakończono')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options(collect(ProtocolType::cases())->mapWithKeys(
                        fn (ProtocolType $type) => [$type->value => $type->shortLabel()]
                    )),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ProtocolStatus::cases())->mapWithKeys(
                        fn (ProtocolStatus $status) => [$status->value => $status->label()]
                    )),

                Tables\Filters\SelectFilter::make('legal_mode')
                    ->label('Tryb prawny')
                    ->options(collect(LegalMode::cases())->mapWithKeys(
                        fn (LegalMode $mode) => [$mode->value => $mode->label()]
                    )),

                Tables\Filters\TrashedFilter::make(),
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
            'index' => Pages\ListProtocols::route('/'),
            'view' => Pages\ViewProtocol::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withTrashed();
    }

    /**
     * G6: Protocols should not be editable from admin panel.
     * Only viewing is allowed to maintain document integrity.
     */
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
