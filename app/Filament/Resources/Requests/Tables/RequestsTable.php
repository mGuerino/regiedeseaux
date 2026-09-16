<?php

namespace App\Filament\Resources\Requests\Tables;

use App\Enums\ValidationStatus;
use App\Filament\Actions\GenerateWordAction;
use App\Filament\Actions\SendForValidationAction;
use App\Filament\Resources\Requests\RequestResource;
use App\Filament\Resources\Requests\Schemas\RequestViewSchema;
use App\Models\Request as RequestModel;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
    /**
     * Résolveur des noms d'affichage des utilisateurs, indexés par identifiant.
     *
     * Mémoïsé dans la portée de `configure()` — donc de la requête HTTP — et non
     * dans une propriété statique : celle-ci survivrait au worker (Octane, suite
     * de tests) et servirait indéfiniment une liste d'utilisateurs périmée, sans
     * les comptes créés ou renommés depuis.
     *
     * @return \Closure(): \Illuminate\Support\Collection<int, string>
     */
    private static function userNamesResolver(): \Closure
    {
        $userNames = null;

        return function () use (&$userNames): \Illuminate\Support\Collection {
            return $userNames ??= User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'first_name'])
                ->mapWithKeys(fn (User $user) => [$user->id => $user->getFilamentName()]);
        };
    }

    public static function configure(Table $table): Table
    {
        $userNames = self::userNamesResolver();

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

                TextColumn::make('validation_status')
                    ->label('Validation')
                    ->badge()
                    ->placeholder('—')
                    ->sortable()
                    ->alignment(Alignment::Center)
                    ->toggleable(),

                // Qui a été sollicité et quand : de quoi relancer la bonne
                // personne sans ouvrir la demande.
                TextColumn::make('validation_requested_at')
                    ->label('Envoyée en validation')
                    ->dateTime('d/m/Y à H:i')
                    ->placeholder('—')
                    ->description(function ($record) use ($userNames): ?string {
                        $ids = $record->validation_notified_to ?? [];

                        if ($ids === []) {
                            return null;
                        }

                        // Les noms sont résolus depuis une table chargée une seule
                        // fois : interroger la base par ligne ajoutait une requête
                        // par demande affichée.
                        $notified = $userNames()->only($ids);

                        return $notified->isEmpty() ? null : 'À '.$notified->implode(', ');
                    })
                    ->sortable()
                    ->toggleable(),

                // 4. AEP - Compact, à côté du statut
                IconColumn::make('water_status')
                    ->label('AEP')
                    // « Non raccordable » est une information, pas une anomalie :
                    // le rouge y faisait lire une erreur à chaque ligne.
                    ->tooltip(fn ($state): string => $state
                        ? 'Raccordable au réseau d\'adduction d\'eau potable'
                        : 'Non raccordable au réseau d\'adduction d\'eau potable')
                    ->boolean()
                    ->falseColor('gray')
                    ->width('1%')
                    ->toggleable(),

                // 5. EU - Compact, à côté du statut
                IconColumn::make('wastewater_status')
                    ->label('EU')
                    ->tooltip(fn ($state): string => $state
                        ? 'Raccordable au réseau d\'eaux usées'
                        : 'Non raccordable au réseau d\'eaux usées')
                    ->boolean()
                    ->falseColor('gray')
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

                SelectFilter::make('validation_status')
                    ->label('Validation')
                    ->options(ValidationStatus::options())
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

                SelectFilter::make('signatory_id')
                    ->label('Signataire')
                    ->relationship('signatory', 'name')
                    // La relation sert à peupler la liste ; le filtrage passe par
                    // la clé étrangère indexée. Le whereHas par défaut produisait
                    // un EXISTS qui parcourait la table des demandes.
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query) => $query->where('signatory_id', $data['value']),
                    ))
                    ->searchable()
                    ->preload()
                    ->native(false),

                // Retrouver ce qu'on a demandé à quelqu'un de valider, sans
                // ouvrir les demandes une par une.
                SelectFilter::make('validation_notified_to')
                    ->label('Validation demandée à')
                    ->options(fn () => User::query()
                        ->supervisors()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $user) => [$user->id => $user->getFilamentName()])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereJsonContains('validation_notified_to', (int) $data['value'])
                        : $query)
                    ->searchable()
                    ->native(false),

                // Une attestation oubliée en validation bloque la réponse au
                // demandeur sans que rien ne le signale.
                TernaryFilter::make('validation_overdue')
                    ->label('En attente depuis plus de '.RequestModel::VALIDATION_OVERDUE_DAYS.' jours')
                    ->placeholder('Toutes les demandes')
                    ->trueLabel('En retard uniquement')
                    ->falseLabel('Dans les délais uniquement')
                    ->queries(
                        true: fn (Builder $query) => $query->validationOverdue(),
                        false: fn (Builder $query) => $query->validationOnTime(),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_response')
                    ->label('Réponse envoyée')
                    ->placeholder('Toutes les demandes')
                    ->trueLabel('Réponse envoyée')
                    ->falseLabel('Sans réponse')
                    ->queries(
                        true: fn (Builder $query) => $query->withResponse(),
                        false: fn (Builder $query) => $query->withoutResponse(),
                        blank: fn (Builder $query) => $query,
                    ),

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
                        $filters['signatory_id'],
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),

                // Section Suivi de la validation
                Section::make('Validation')
                    ->description('Suivre les attestations soumises à un superviseur')
                    ->schema([
                        $filters['validation_status'],
                        $filters['validation_notified_to'],
                        $filters['validation_overdue'],
                        $filters['has_response'],
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),

                // Filtres système (pleine largeur). Tout filtre déclaré doit
                // figurer ici : absent du panneau, son champ n'est pas rendu et
                // la croix de son badge ne parvient plus à le retirer.
                $filters['is_archived']->columnSpanFull(),
                $filters['trashed']->columnSpanFull(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema(RequestViewSchema::getComponents())
                    ->modalWidth(Width::SevenExtraLarge),
                EditAction::make(),
                // Regroupées : alignées en clair, ces actions sortaient de
                // l'écran et n'étaient atteignables qu'en faisant défiler le
                // tableau horizontalement.
                ActionGroup::make([
                    GenerateWordAction::make(),
                    SendForValidationAction::make(),
                    Action::make('validate_attestation')
                        ->label('Valider')
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->color('warning')
                        ->visible(fn ($record) => Auth::user()->canValidateAttestations() && $record->isAwaitingValidation())
                        ->url(fn ($record) => RequestResource::getUrl('validation', ['record' => $record])),
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
                    ->label('Autres actions')
                    ->icon(Heroicon::OutlinedEllipsisHorizontal)
                    ->tooltip('Autres actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('generate_word_bulk')
                        ->label('Générer attestation (Lot)')
                        ->icon(Heroicon::DocumentText)
                        ->color('info')
                        ->action(function (Collection $records): void {
                            // Sans notify: false, chaque demande empilerait sa
                            // propre notification de succès.
                            $generated = $records
                                ->map(fn ($record) => GenerateWordAction::generate($record, notify: false))
                                ->filter()
                                ->count();

                            Notification::make()
                                ->title('Génération terminée')
                                ->body("{$generated} attestation(s) générée(s) sur {$records->count()} demande(s) sélectionnée(s).")
                                ->success()
                                ->send();
                        }),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->deferFilters(false) // Filtres réactifs - application immédiate
            ->persistFiltersInSession() // Persiste les filtres entre les sessions
            ->reorderableColumns() // Permet de réorganiser les colonnes
            ->deferColumnManager(false) // Column manager réactif
            // 16 515 demandes en pages de 10 faisaient 1 652 pages.
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_date', 'desc');
    }
}
