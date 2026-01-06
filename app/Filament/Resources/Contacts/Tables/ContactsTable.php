<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Filament\Exports\ContactExporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('requests_count')
                    ->label('Demandes')
                    ->counts('requests')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('has_requests')
                    ->label('Avec demandes')
                    ->query(fn (Builder $query): Builder => $query->has('requests')),
                Filter::make('no_requests')
                    ->label('Sans demandes')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('requests')),
                Filter::make('has_email')
                    ->label('Avec email')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email')->where('email', '!=', '')),
                Filter::make('has_phone')
                    ->label('Avec téléphone')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('phone')->where('phone', '!=', '')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->exporter(ContactExporter::class)
                    ->label('Exporter tout')
                    ->formats(['csv', 'xlsx']),
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContactExporter::class)
                        ->label('Exporter la sélection')
                        ->formats(['csv', 'xlsx']),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->deferFilters(false);
    }
}
