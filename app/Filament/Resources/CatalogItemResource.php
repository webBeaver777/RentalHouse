<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogItemResource\Pages;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CatalogItemResource extends Resource
{
    protected static ?string $model = CatalogItem::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?string $navigationLabel = 'Elementy katalogu';

    protected static ?string $modelLabel = 'Element katalogu';

    protected static ?string $pluralModelLabel = 'Elementy katalogu';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Podstawowe informacje')
                    ->schema([
                        Select::make('type')
                            ->label('Typ')
                            ->options([
                                'room' => 'Pomieszczenie',
                                'item' => 'Element wyposażenia',
                                'condition' => 'Stan',
                                'defect' => 'Usterka',
                            ])
                            ->required()
                            ->native(false),

                        Select::make('parent_id')
                            ->label('Element nadrzędny')
                            // M8-VERIFY fix: `name` is a translatable jsonb column
                            // (spatie/laravel-translatable) — Postgres cannot
                            // ORDER BY jsonb, which is what plain
                            // relationship('parent', 'name') tries to do
                            // ("could not identify an ordering operator for
                            // type json"). No titleAttribute + explicit label
                            // callback avoids that default ordering/search.
                            ->relationship(name: 'parent')
                            ->getOptionLabelFromRecordUsing(
                                fn (CatalogItem $record): string => $record->getTranslation('name', 'pl')
                            )
                            ->preload()
                            ->nullable(),

                        TextInput::make('sort_order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Aktywny')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Tłumaczenia')
                    ->schema([
                        Tabs::make('Translations')
                            ->tabs([
                                Tabs\Tab::make('Polski')
                                    ->schema([
                                        TextInput::make('name.pl')
                                            ->label('Nazwa (PL)')
                                            ->required(),
                                        Textarea::make('description.pl')
                                            ->label('Opis (PL)')
                                            ->rows(3),
                                    ]),
                                Tabs\Tab::make('English')
                                    ->schema([
                                        TextInput::make('name.en')
                                            ->label('Nazwa (EN)'),
                                        Textarea::make('description.en')
                                            ->label('Opis (EN)')
                                            ->rows(3),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (CatalogItemType $state): string => match ($state) {
                        CatalogItemType::ROOM => 'Pomieszczenie',
                        CatalogItemType::ITEM => 'Element',
                        CatalogItemType::CONDITION => 'Stan',
                        CatalogItemType::DEFECT => 'Usterka',
                    })
                    ->color(fn (CatalogItemType $state): string => match ($state) {
                        CatalogItemType::ROOM => 'info',
                        CatalogItemType::ITEM => 'success',
                        CatalogItemType::CONDITION => 'warning',
                        CatalogItemType::DEFECT => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => $record->getTranslation('name', 'pl')),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Nadrzędny')
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Kolejność')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options([
                        'room' => 'Pomieszczenie',
                        'item' => 'Element',
                        'condition' => 'Stan',
                        'defect' => 'Usterka',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywny'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogItems::route('/'),
            'create' => Pages\CreateCatalogItem::route('/create'),
            'edit' => Pages\EditCatalogItem::route('/{record}/edit'),
        ];
    }
}
