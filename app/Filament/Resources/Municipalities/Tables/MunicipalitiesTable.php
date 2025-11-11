<?php

namespace App\Filament\Resources\Municipalities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                TextColumn::make('road_management_mode')
                    ->label('Gestion rues')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'AUTO' => 'success',
                        'MANUAL' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('park_management_mode')
                    ->label('Gestion parcs')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'AUTO' => 'success',
                        'MANUAL' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
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
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
