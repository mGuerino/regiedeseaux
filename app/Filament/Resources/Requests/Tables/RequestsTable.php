<?php

namespace App\Filament\Resources\Requests\Tables;

use App\Filament\Actions\GenerateWordAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

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

                TextColumn::make('parcels_list')
                    ->label('Parcelles')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->parcels->map(fn ($parcel) => $parcel->ident))
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('parcels', function ($query) use ($search) {
                            $query->where('ident', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(),

                TextColumn::make('contact.last_name')
                    ->label('Contact')
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->formatStateUsing(fn ($record) => $record->contact ? "{$record->contact->first_name} {$record->contact->last_name}" : '-')
                    ->sortable()
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

                TextColumn::make('followedByUser.name')
                    ->label('Suivi par')
                    ->searchable(['name', 'first_name'])
                    ->formatStateUsing(fn ($record) => $record->followedByUser 
                        ? ($record->followedByUser->first_name 
                            ? "{$record->followedByUser->first_name} {$record->followedByUser->name}"
                            : $record->followedByUser->name)
                        : '-'
                    )
                    ->sortable()
                    ->toggleable(),

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

                TernaryFilter::make('is_archived')
                    ->label('Archivées')
                    ->placeholder('Masquer archivées')
                    ->trueLabel('Afficher uniquement archivées')
                    ->falseLabel('Afficher uniquement non archivées')
                    ->queries(
                        true: fn (Builder $query) => $query->onlyArchived(),
                        false: fn (Builder $query) => $query, // Par défaut, le scope exclut déjà les archivées
                        blank: fn (Builder $query) => $query->withArchived(),
                    )
                    ->default(false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                GenerateWordAction::make(),
                Action::make('toggle_archive')
                    ->label(fn ($record) => $record->is_archived ? 'Désarchiver' : 'Archiver')
                    ->icon(fn ($record) => $record->is_archived ? Heroicon::OutlinedArchiveBoxArrowDown : Heroicon::OutlinedArchiveBox)
                    ->color(fn ($record) => $record->is_archived ? 'success' : 'gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->is_archived ? 'Désarchiver cette demande ?' : 'Archiver cette demande ?')
                    ->modalDescription(fn ($record) => $record->is_archived 
                        ? 'Cette demande redeviendra visible dans la liste principale.'
                        : 'Cette demande sera masquée de la liste principale. Vous pourrez la retrouver en activant le filtre "Archivées".'
                    )
                    ->action(function ($record) {
                        $isArchiving = !$record->is_archived;
                        
                        $record->update([
                            'is_archived' => $isArchiving,
                            'archived_at' => $isArchiving ? now() : null,
                            'archived_by' => $isArchiving ? Auth::user()->name : null,
                        ]);
                        
                        Notification::make()
                            ->title($record->is_archived ? 'Demande archivée' : 'Demande désarchivée')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('generate_word_bulk')
                        ->label('Générer Word (Lot)')
                        ->icon(Heroicon::DocumentText)
                        ->color('info')
                        ->action(fn (Collection $records) => $records->each(
                            fn ($record) => GenerateWordAction::generate($record)
                        )),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_date', 'desc');
    }
}
