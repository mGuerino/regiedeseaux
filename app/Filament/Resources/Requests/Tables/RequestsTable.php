<?php

namespace App\Filament\Resources\Requests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('applicant.last_name')
                    ->label('Demandeur')
                    ->searchable(['last_name', 'first_name'])
                    ->formatStateUsing(fn ($record) => $record->applicant ? "{$record->applicant->last_name} {$record->applicant->first_name}" : '-')
                    ->sortable(),

                TextColumn::make('municipality.name')
                    ->label('Commune')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parcels.ident')
                    ->label('Parcelles')
                    ->formatStateUsing(fn ($record) => $record->parcels->pluck('pivot.parcel_name')->filter()->implode(', ') ?: $record->parcels->pluck('ident')->implode(', '))
                    ->searchable()
                    ->toggleable()
                    ->wrap(),

                TextColumn::make('contact')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('request_date')
                    ->label('Date demande')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('response_date')
                    ->label('Date réponse')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('request_status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        1 => 'En cours',
                        2 => 'Terminée',
                        3 => 'Annulée',
                        default => 'Inconnu',
                    })
                    ->color(fn ($state) => match ($state) {
                        1 => 'warning',
                        2 => 'success',
                        3 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('water_status')
                    ->label('AEP')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('wastewater_status')
                    ->label('EU')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('signatory.name')
                    ->label('Signataire')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('certifier.name')
                    ->label('Attestant')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contactPerson.name')
                    ->label('Interlocuteur')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_by')
                    ->label('Créé par')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_date')
                    ->label('Date création')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_by')
                    ->label('Modifié par')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_date')
                    ->label('Date modification')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('municipality_code')
                    ->label('Commune')
                    ->relationship('municipality', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('request_status')
                    ->label('Statut')
                    ->options([
                        1 => 'En cours',
                        2 => 'Terminée',
                        3 => 'Annulée',
                    ])
                    ->native(false),

                SelectFilter::make('water_status')
                    ->label('Connectable AEP')
                    ->options([
                        true => 'Oui',
                        false => 'Non',
                    ])
                    ->native(false),

                SelectFilter::make('wastewater_status')
                    ->label('Connectable EU')
                    ->options([
                        true => 'Oui',
                        false => 'Non',
                    ])
                    ->native(false),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_date', 'desc');
    }
}
