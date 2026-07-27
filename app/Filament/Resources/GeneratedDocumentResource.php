<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GeneratedDocumentResource\Pages;
use App\Modules\Document\Application\Services\PdfGenerationService;
use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * G6: Filament resource for managing generated documents (PDFs).
 *
 * Allows viewing PDF status and triggering regeneration.
 */
class GeneratedDocumentResource extends Resource
{
    protected static ?string $model = GeneratedDocument::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Protokoły';

    protected static ?string $navigationLabel = 'Dokumenty PDF';

    protected static ?string $modelLabel = 'Dokument PDF';

    protected static ?string $pluralModelLabel = 'Dokumenty PDF';

    protected static ?int $navigationSort = 11;

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

                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (DocumentType $state): string => $state->label()),

                Tables\Columns\TextColumn::make('template_type')
                    ->label('Szablon')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('locale')
                    ->label('Język'),

                Tables\Columns\TextColumn::make('version')
                    ->label('Wersja'),

                Tables\Columns\TextColumn::make('filename')
                    ->label('Plik')
                    ->limit(30),

                Tables\Columns\TextColumn::make('size')
                    ->label('Rozmiar')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).' KB'),

                Tables\Columns\TextColumn::make('hash')
                    ->label('Hash')
                    ->limit(12)
                    ->tooltip(fn (GeneratedDocument $record): string => $record->hash ?? ''),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Wygenerowano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('generatedBy.email')
                    ->label('Przez')
                    ->placeholder('System'),
            ])
            ->defaultSort('generated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options([
                        'protocol' => 'Protokół',
                        'summary' => 'Podsumowanie',
                        'comparison' => 'Porównanie',
                    ]),

                Tables\Filters\SelectFilter::make('locale')
                    ->label('Język')
                    ->options([
                        'pl' => 'Polski',
                        'en' => 'English',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('regenerate')
                    ->label('Regeneruj')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regeneruj dokument PDF')
                    ->modalDescription('Czy na pewno chcesz wygenerować nową wersję tego dokumentu? Stara wersja zostanie zachowana.')
                    ->action(function (GeneratedDocument $record): void {
                        try {
                            $service = app(PdfGenerationService::class);
                            $service->regenerateDocument($record);

                            Notification::make()
                                ->title('Dokument został wygenerowany')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Błąd generowania dokumentu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('download')
                    ->label('Pobierz')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (GeneratedDocument $record): string => $record->getTemporaryUrl(60))
                    ->openUrlInNewTab(),
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
            'index' => Pages\ListGeneratedDocuments::route('/'),
            'view' => Pages\ViewGeneratedDocument::route('/{record}'),
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
