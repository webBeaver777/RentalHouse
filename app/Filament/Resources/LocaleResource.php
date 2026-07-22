<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\LocaleResource\Pages;
use App\Modules\Localization\Infrastructure\Models\Locale;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LocaleResource extends Resource
{
    protected static ?string $model = Locale::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-language';

    protected static string|\UnitEnum|null $navigationGroup = 'Ustawienia';

    protected static ?string $navigationLabel = 'Języki';

    protected static ?string $modelLabel = 'Język';

    protected static ?string $pluralModelLabel = 'Języki';

    protected static ?int $navigationSort = 100;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Podstawowe informacje')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kod języka')
                            ->required()
                            ->maxLength(5)
                            ->unique(ignoreRecord: true)
                            ->helperText('np. pl, en, de'),

                        TextInput::make('name')
                            ->label('Nazwa (angielska)')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('native_name')
                            ->label('Nazwa rodzima')
                            ->required()
                            ->maxLength(255)
                            ->helperText('np. Polski, English, Deutsch'),
                    ])
                    ->columns(3),

                Section::make('Ustawienia')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktywny')
                            ->default(true),

                        Toggle::make('is_default')
                            ->label('Domyślny')
                            ->helperText('Tylko jeden język może być domyślny'),

                        TextInput::make('sort_order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable(),

                Tables\Columns\TextColumn::make('native_name')
                    ->label('Nazwa rodzima')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Domyślny')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Kolejność')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywny'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListLocales::route('/'),
            'create' => Pages\CreateLocale::route('/create'),
            'edit' => Pages\EditLocale::route('/{record}/edit'),
        ];
    }
}
