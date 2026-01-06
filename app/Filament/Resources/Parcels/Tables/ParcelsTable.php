<?php

namespace App\Filament\Resources\Parcels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParcelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')
                    ->label('Identifiant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('municipality.name')
                    ->label('Commune')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ccosec')
                    ->label('Section')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parcelle')
                    ->label('Parcelle')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sect_cad')
                    ->label('Section cadastrale')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('dnupla')
                    ->label('Plan')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('requests_count')
                    ->label('Nb. demandes')
                    ->counts('requests')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('codcomm')
                    ->label('Commune')
                    ->relationship('municipality', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('ccosec')
                    ->label('Section')
                    ->options(fn () => \App\Models\Parcel::query()
                        ->distinct()
                        ->pluck('ccosec', 'ccosec')
                        ->filter()
                        ->sort()
                    )
                    ->searchable(),
                SelectFilter::make('sect_cad')
                    ->label('Section cadastrale')
                    ->options(fn () => \App\Models\Parcel::query()
                        ->distinct()
                        ->pluck('sect_cad', 'sect_cad')
                        ->filter()
                        ->sort()
                    )
                    ->searchable(),
                Filter::make('has_requests')
                    ->label('Avec demandes')
                    ->query(fn (Builder $query): Builder => $query->has('requests')),
                Filter::make('no_requests')
                    ->label('Sans demandes')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('requests')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->deferFilters(false)
            ->defaultSort('ident');
    }
}
