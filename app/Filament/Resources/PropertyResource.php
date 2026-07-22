<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\Pages;
use App\Modules\Property\Infrastructure\Models\Property;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Actions\EditAction as TableEditAction;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\RestoreBulkAction;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Nieruchomości';

    protected static ?string $modelLabel = 'Nieruchomość';

    protected static ?string $pluralModelLabel = 'Nieruchomości';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dane podstawowe')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nazwa')
                            ->required()
                            ->maxLength(255),

                        Select::make('declaration_type')
                            ->label('Typ deklaracji')
                            ->options([
                                'owner' => 'Właściciel',
                                'tenant_declared' => 'Zgłoszony przez najemcę',
                            ])
                            ->required()
                            ->default('owner'),
                    ])->columns(2),

                Section::make('Adres')
                    ->schema([
                        TextInput::make('street')
                            ->label('Ulica')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('building_number')
                            ->label('Numer budynku')
                            ->required()
                            ->maxLength(20),

                        TextInput::make('apartment_number')
                            ->label('Numer mieszkania')
                            ->maxLength(20),

                        TextInput::make('city')
                            ->label('Miasto')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('postal_code')
                            ->label('Kod pocztowy')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('00-000'),

                        TextInput::make('country')
                            ->label('Kraj')
                            ->default('PL')
                            ->maxLength(2),
                    ])->columns(3),

                Section::make('Dodatkowe')
                    ->schema([
                        Textarea::make('description')
                            ->label('Opis')
                            ->rows(3),
                    ]),

                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_address')
                    ->label('Adres')
                    ->searchable(['street', 'city']),

                TextColumn::make('declaration_type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner' => 'Właściciel',
                        'tenant_declared' => 'Najemca',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'success',
                        'tenant_declared' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('declaration_type')
                    ->label('Typ deklaracji')
                    ->options([
                        'owner' => 'Właściciel',
                        'tenant_declared' => 'Zgłoszony przez najemcę',
                    ]),

                TrashedFilter::make(),
            ])
            ->actions([
                TableEditAction::make(),
                TableDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'edit' => Pages\EditProperty::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->withTrashed();
    }
}
