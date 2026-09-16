<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile_photo_path')
                    ->label('')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn (User $record) => self::initialsAvatar($record))
                    ->size(40),

                // Nom et email dans une seule colonne : quatre colonnes pour
                // identifier une personne laissaient le tableau à moitié vide.
                TextColumn::make('name')
                    ->label('Utilisateur')
                    ->description(fn (User $record) => $record->email)
                    ->searchable(['name', 'first_name', 'email'])
                    ->sortable(),

                TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Renseigné sur une minorité de comptes : masqué par défaut pour
                // ne pas étaler une colonne de tirets.
                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Un badge par rôle plutôt qu'une matrice de coches : on lit ce
                // que la personne peut faire, pas ce qu'elle ne peut pas.
                TextColumn::make('roles')
                    ->label('Rôles')
                    ->badge()
                    ->getStateUsing(fn (User $record) => self::roleLabels($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Administrateur' => 'primary',
                        'Superviseur' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): ?string => $state === 'Superviseur'
                        ? 'heroicon-o-check-badge'
                        : null),

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
                // Un seul filtre pour les deux rôles : deux listes ternaires
                // séparées obligeaient à croiser « oui / non / tous » pour
                // répondre à la question « qui peut valider ? ».
                SelectFilter::make('role')
                    ->label('Rôle')
                    ->multiple()
                    ->options([
                        'supervisor' => 'Superviseur',
                        'admin' => 'Administrateur',
                        'none' => 'Aucun rôle',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];

                        if ($values === []) {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($values): void {
                            if (in_array('supervisor', $values, true)) {
                                $query->orWhere('is_supervisor', true);
                            }

                            if (in_array('admin', $values, true)) {
                                $query->orWhere('is_admin', true);
                            }

                            if (in_array('none', $values, true)) {
                                $query->orWhere(fn (Builder $query) => $query
                                    ->where('is_admin', false)
                                    ->where('is_supervisor', false));
                            }
                        });
                    }),

                TernaryFilter::make('has_phone')
                    ->label('Téléphone renseigné')
                    ->placeholder('Tous les utilisateurs')
                    ->trueLabel('Avec téléphone')
                    ->falseLabel('Sans téléphone')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('phone')->where('phone', '!=', ''),
                        false: fn (Builder $query) => $query->where(fn (Builder $query) => $query
                            ->whereNull('phone')
                            ->orWhere('phone', '')),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::designateSupervisorsAction(),
                    self::revokeSupervisorsAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Aucun utilisateur')
            ->emptyStateDescription('Créez un compte pour donner accès à l\'application.');
    }

    /**
     * Nommer superviseurs plusieurs comptes d'un coup : à la mise en place du
     * workflow, on désigne l'équipe entière en une fois plutôt que fiche par fiche.
     */
    private static function designateSupervisorsAction(): BulkAction
    {
        return BulkAction::make('designate_supervisors')
            ->label('Nommer superviseurs')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Nommer superviseurs')
            ->modalDescription('Les comptes sélectionnés pourront valider ou refuser les attestations, et recevront les demandes de validation par email.')
            ->modalSubmitActionLabel('Nommer superviseurs')
            ->action(function (Collection $records): void {
                $concerned = $records->where('is_supervisor', false);

                User::whereIn('id', $concerned->pluck('id'))->update(['is_supervisor' => true]);

                // Sans adresse, le superviseur ne recevra jamais la demande de
                // validation : il faut le dire tout de suite.
                $unreachable = $records->filter(fn (User $user) => blank($user->email));

                $notification = Notification::make()
                    ->title(self::outcomeTitle($concerned->count(), $records->count(), 'nommé'))
                    ->success();

                if ($unreachable->isNotEmpty()) {
                    $notification
                        ->warning()
                        ->body($unreachable->count().' compte(s) sans adresse email ne recevront aucune demande de validation : '
                            .$unreachable->map(fn (User $user) => $user->getFilamentName())->implode(', ').'.')
                        ->duration(20000);
                }

                $notification->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Retirer le rôle, pour un départ ou une réorganisation.
     */
    private static function revokeSupervisorsAction(): BulkAction
    {
        return BulkAction::make('revoke_supervisors')
            ->label('Retirer le rôle superviseur')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Retirer le rôle superviseur')
            ->modalDescription('Les attestations déjà envoyées en validation resteront en attente : un autre superviseur devra les traiter.')
            ->modalSubmitActionLabel('Retirer le rôle')
            ->action(function (Collection $records): void {
                $concerned = $records->where('is_supervisor', true);

                User::whereIn('id', $concerned->pluck('id'))->update(['is_supervisor' => false]);

                $notification = Notification::make()
                    ->title(self::outcomeTitle($concerned->count(), $records->count(), 'retiré'))
                    ->success();

                // Plus personne pour valider : les envois en validation seront
                // refusés tant qu'un superviseur n'aura pas été désigné.
                if (! User::query()->supervisors()->exists()) {
                    $notification
                        ->warning()
                        ->body('Plus aucun superviseur n\'est désigné : les agents ne pourront plus envoyer d\'attestation en validation.')
                        ->duration(20000);
                }

                $notification->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Rendre compte de ce qui a changé, et de ce qui l'était déjà.
     */
    private static function outcomeTitle(int $changed, int $selected, string $verb): string
    {
        if ($changed === 0) {
            return 'Aucun changement : le rôle était déjà à jour sur les comptes sélectionnés.';
        }

        $title = $changed > 1
            ? "{$changed} comptes ont été {$verb}s"
            : "1 compte a été {$verb}";

        $unchanged = $selected - $changed;

        return $unchanged > 0
            ? $title." — {$unchanged} l'étaient déjà."
            : $title.'.';
    }

    /**
     * Rôles d'un utilisateur, pour affichage en badges.
     *
     * @return list<string>
     */
    private static function roleLabels(User $user): array
    {
        $roles = [];

        if ($user->is_admin) {
            $roles[] = 'Administrateur';
        }

        if ($user->is_supervisor) {
            $roles[] = 'Superviseur';
        }

        return $roles === [] ? ['Aucun rôle'] : $roles;
    }

    /**
     * Pastille d'initiales générée localement.
     *
     * Les avatars passaient par un service externe : sans accès à internet
     * depuis le poste ou le serveur, chaque ligne affichait une image cassée.
     */
    private static function initialsAvatar(User $user): string
    {
        $name = trim(($user->first_name ?? '').' '.($user->name ?? ''));

        $words = collect(preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        // Un compte sans prénom ne donnerait qu'une lettre isolée dans la
        // pastille : on prend alors les deux premières du nom.
        $initials = $words->count() === 1
            ? mb_strtoupper(mb_substr($words->first(), 0, 2))
            : $words->take(2)->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');

        // Un nom commençant par « & » ou « < » produirait un SVG au XML invalide,
        // donc une pastille cassée sur cette ligne.
        $initials = htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">
                <rect width="40" height="40" rx="20" fill="#EBF4FF"/>
                <text x="20" y="21" fill="#4B6BB7" font-family="system-ui, sans-serif"
                      font-size="15" font-weight="600" text-anchor="middle"
                      dominant-baseline="central">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
