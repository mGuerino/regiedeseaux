<?php

namespace App\Filament\Resources\Parcels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ->defaultSort('ident');
    }
}
