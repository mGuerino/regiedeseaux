<?php

namespace App\Filament\Resources\Applicants\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApplicantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label('Adresse')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('postal_code')
                    ->label('Code postal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label('Ville')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone1')
                    ->label('Téléphone 1')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone2')
                    ->label('Téléphone 2')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('requests_count')
                    ->label('Nb. demandes')
                    ->counts('requests')
                    ->sortable(),
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
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('city')
                    ->label('Ville')
                    ->options(fn () => \App\Models\Applicant::query()
                        ->distinct()
                        ->pluck('city', 'city')
                        ->filter()
                        ->sort()
                    ),
                SelectFilter::make('postal_code')
                    ->label('Code postal')
                    ->options(fn () => \App\Models\Applicant::query()
                        ->distinct()
                        ->pluck('postal_code', 'postal_code')
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
                Filter::make('has_email')
                    ->label('Avec email')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email')->where('email', '!=', '')),
                Filter::make('has_phone')
                    ->label('Avec téléphone')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereNotNull('phone1')->where('phone1', '!=', '')
                            ->orWhereNotNull('phone2')->where('phone2', '!=', '');
                    })),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->deferFilters(false)
            ->defaultSort('last_name');
    }
}
