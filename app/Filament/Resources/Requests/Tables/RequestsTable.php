<?php

namespace App\Filament\Resources\Requests\Tables;

use App\Filament\Actions\GenerateWordAction;
use App\Filament\Resources\Requests\Schemas\RequestViewSchema;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class RequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. ID - Identifiant technique (visible, rétréci)
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->width('60px'),

                // 2. Date demande
                TextColumn::make('request_date')
                    ->label('Date demande')
                    ->date('d/m/Y')
                    ->icon(Heroicon::Calendar)
                    ->sortable()
                    ->toggleable(),

                // 3. Statut - Information prioritaire
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
                    ->sortable()
                    ->searchable()
                    ->alignment(Alignment::Center),

                // 4. AEP - Compact, à côté du statut
                IconColumn::make('water_status')
                    ->label('AEP')
                    ->boolean()
                    ->width('1%')
                    ->toggleable(),

                // 5. EU - Compact, à côté du statut
                IconColumn::make('wastewater_status')
                    ->label('EU')
                    ->boolean()
                    ->width('1%')
                    ->toggleable(),

                // 6. Référence - Identifiant métier principal (avec parcelles en dessous)
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn ($record) => $record->parcels->isEmpty()
                        ? null
                        : new HtmlString(view('filament.components.parcels-badges', ['parcels' => $record->parcels])->render())
                    )
                    ->grow(),

                // 7. Demandeur
                TextColumn::make('applicant.last_name')
                    ->label('Demandeur')
                    ->icon(Heroicon::User)
                    ->searchable(['last_name', 'first_name'])
                    ->formatStateUsing(fn ($record) => $record->applicant
                        ? "{$record->applicant->last_name} {$record->applicant->first_name}"
                        : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 8. Commune
                TextColumn::make('municipality.name')
                    ->label('Commune')
                    ->icon(Heroicon::MapPin)
                    ->searchable()
                    ->sortable(),

                // 9. Date réponse
                TextColumn::make('response_date')
                    ->label('Date réponse')
                    ->date('d/m/Y')
                    ->icon(Heroicon::Calendar)
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                // 10. Contact - Caché par défaut
                TextColumn::make('contact.last_name')
                    ->label('Contact')
                    ->icon(Heroicon::AtSymbol)
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->formatStateUsing(fn ($record) => $record->contact
                        ? "{$record->contact->first_name} {$record->contact->last_name}"
                        : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 11. Suivi par - Caché par défaut
                TextColumn::make('followedByUser.name')
                    ->label('Suivi par')
                    ->icon(Heroicon::UserCircle)
                    ->searchable(['name', 'first_name'])
                    ->formatStateUsing(fn ($record) => $record->followedByUser
                        ? ($record->followedByUser->first_name
                            ? "{$record->followedByUser->first_name} {$record->followedByUser->name}"
                            : $record->followedByUser->name)
                        : '-'
                    )
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 12. Parcelles - Caché par défaut (affichées sous Référence)
                TextColumn::make('parcels_list')
                    ->label('Parcelles')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->parcels->map(fn ($parcel) => $parcel->ident))
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('parcels', function ($query) use ($search) {
                            $query->where('ident', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // 13. Signataire - Caché par défaut
                TextColumn::make('signatory.name')
                    ->label('Signataire')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 14. Attestant - Caché par défaut
                TextColumn::make('certifier.name')
                    ->label('Attestant')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 15. Interlocuteur - Caché par défaut
                TextColumn::make('contactPerson.name')
                    ->label('Interlocuteur')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 16. Créé par - Caché par défaut
                TextColumn::make('created_by')
                    ->label('Créé par')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 17. Date création - Caché par défaut
                TextColumn::make('created_date')
                    ->label('Date création')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 18. Modifié par - Caché par défaut
                TextColumn::make('updated_by')
                    ->label('Modifié par')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 19. Date modification - Caché par défaut
                TextColumn::make('updated_date')
                    ->label('Date modification')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 20. Supprimé le - Caché par défaut
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtre plage de dates - Date de demande
                Filter::make('request_date')
                    ->label('Date de demande')
                    ->schema([
                        DatePicker::make('request_from')
                            ->label('Du')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sélectionner une date'),
                        DatePicker::make('request_until')
                            ->label('Au')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sélectionner une date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['request_from'],
                                fn (Builder $q, $date) => $q->whereDate('request_date', '>=', $date)
                            )
                            ->when(
                                $data['request_until'],
                                fn (Builder $q, $date) => $q->whereDate('request_date', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['request_from'] ?? null) {
                            $indicators[] = 'Demande à partir du '.Carbon::parse($data['request_from'])->format('d/m/Y');
                        }

                        if ($data['request_until'] ?? null) {
                            $indicators[] = 'Demande jusqu\'au '.Carbon::parse($data['request_until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                // Filtre plage de dates - Date de réponse
                Filter::make('response_date')
                    ->label('Date de réponse')
                    ->schema([
                        DatePicker::make('response_from')
                            ->label('Du')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sélectionner une date'),
                        DatePicker::make('response_until')
                            ->label('Au')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sélectionner une date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['response_from'],
                                fn (Builder $q, $date) => $q->whereDate('response_date', '>=', $date)
                            )
                            ->when(
                                $data['response_until'],
                                fn (Builder $q, $date) => $q->whereDate('response_date', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['response_from'] ?? null) {
                            $indicators[] = 'Réponse à partir du '.Carbon::parse($data['response_from'])->format('d/m/Y');
                        }

                        if ($data['response_until'] ?? null) {
                            $indicators[] = 'Réponse jusqu\'au '.Carbon::parse($data['response_until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                // Filtre Commune
                SelectFilter::make('municipality_code')
                    ->label('Commune')
                    ->relationship('municipality', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                // Filtre Statut
                SelectFilter::make('request_status')
                    ->label('Statut')
                    ->options([
                        1 => 'En cours',
                        2 => 'Terminée',
                        3 => 'Annulée',
                    ])
                    ->native(false),

                // Filtre AEP
                SelectFilter::make('water_status')
                    ->label('Raccordable AEP')
                    ->options([
                        true => 'Oui',
                        false => 'Non',
                    ])
                    ->native(false),

                // Filtre EU
                SelectFilter::make('wastewater_status')
                    ->label('Raccordable EU')
                    ->options([
                        true => 'Oui',
                        false => 'Non',
                    ])
                    ->native(false),

                // Filtre Demandeur
                SelectFilter::make('applicant_id')
                    ->label('Demandeur')
                    ->relationship('applicant', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->last_name} {$record->first_name}")
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->native(false),

                // Filtre Contact
                SelectFilter::make('contact_id')
                    ->label('Contact')
                    ->relationship('contact', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name}")
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->native(false),

                // Filtre Suivi par
                SelectFilter::make('followed_by_user_id')
                    ->label('Suivi par')
                    ->relationship('followedByUser', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name
                        ? "{$record->first_name} {$record->name}"
                        : $record->name
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                // Filtre Supprimés (Trashed)
                TrashedFilter::make(),

                // Filtre Archivées
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
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::ThreeExtraLarge)
            ->filtersFormSchema(fn (array $filters): array => [
                // Section Critères généraux
                Section::make('Critères généraux')
                    ->schema([
                        $filters['municipality_code'],
                        $filters['request_status'],
                        $filters['water_status'],
                        $filters['wastewater_status'],
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),

                // Section Dates
                Section::make('Dates')
                    ->description('Filtrer par périodes')
                    ->schema([
                        Fieldset::make('Date de demande')
                            ->schema([
                                $filters['request_date'],
                            ])
                            ->columns(2),
                        Fieldset::make('Date de réponse')
                            ->schema([
                                $filters['response_date'],
                            ])
                            ->columns(2),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),

                // Section Intervenants
                Section::make('Intervenants')
                    ->schema([
                        $filters['applicant_id'],
                        $filters['contact_id'],
                        $filters['followed_by_user_id'],
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->collapsible(),

                // Filtres système (pleine largeur)
                $filters['is_archived']->columnSpanFull(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema(RequestViewSchema::getComponents())
                    ->modalWidth(Width::SevenExtraLarge),
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
                        $isArchiving = ! $record->is_archived;

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
                        ->label('Générer attestation (Lot)')
                        ->icon(Heroicon::DocumentText)
                        ->color('info')
                        ->action(fn (Collection $records) => $records->each(
                            fn ($record) => GenerateWordAction::generate($record)
                        )),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->deferFilters(false) // Filtres réactifs - application immédiate
            ->persistFiltersInSession() // Persiste les filtres entre les sessions
            ->reorderableColumns() // Permet de réorganiser les colonnes
            ->deferColumnManager(false) // Column manager réactif
            ->defaultSort('created_date', 'desc');
    }
}
