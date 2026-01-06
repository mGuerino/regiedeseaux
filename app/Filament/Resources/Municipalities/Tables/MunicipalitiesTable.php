<?php

namespace App\Filament\Resources\Municipalities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MunicipalitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_name')
                    ->label('Nom d\'affichage')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('postal_code')
                    ->label('Code postal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('requests_count')
                    ->label('Nb. demandes')
                    ->counts('requests')
                    ->sortable(),
                TextColumn::make('roads_count')
                    ->label('Nb. rues')
                    ->counts('roads')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('parcels_count')
                    ->label('Nb. parcelles')
                    ->counts('parcels')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('postal_code')
                    ->label('Code postal')
                    ->options(fn () => \App\Models\Municipality::query()
                        ->distinct()
                        ->pluck('postal_code', 'postal_code')
                        ->filter()
                        ->sort()
                    )
                    ->searchable(),
                SelectFilter::make('road_management_mode')
                    ->label('Mode de gestion des rues')
                    ->options(fn () => \App\Models\Municipality::query()
                        ->distinct()
                        ->pluck('road_management_mode', 'road_management_mode')
                        ->filter()
                        ->sort()
                    ),
                SelectFilter::make('park_management_mode')
                    ->label('Mode de gestion des parcs')
                    ->options(fn () => \App\Models\Municipality::query()
                        ->distinct()
                        ->pluck('park_management_mode', 'park_management_mode')
                        ->filter()
                        ->sort()
                    ),
                Filter::make('has_requests')
                    ->label('Avec demandes')
                    ->query(fn (Builder $query): Builder => $query->has('requests')),
                Filter::make('has_roads')
                    ->label('Avec rues')
                    ->query(fn (Builder $query): Builder => $query->has('roads')),
                Filter::make('has_parcels')
                    ->label('Avec parcelles')
                    ->query(fn (Builder $query): Builder => $query->has('parcels')),
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
            ->defaultSort('name');
    }
}
