<?php

namespace App\Filament\Actions;

use App\Exceptions\PdfConversionException;
use App\Models\Request;
use App\Models\User;
use App\Services\AttestationValidationService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class SendForValidationAction
{
    public static function make(): Action
    {
        // Chaque rendu de la modale demandait la liste des superviseurs quatre
        // fois (contenu, options, cochage par défaut, bouton d'envoi) : elle est
        // résolue une seule fois par requête HTTP.
        $supervisors = null;
        $supervisorsToNotify = function () use (&$supervisors): \Illuminate\Database\Eloquent\Collection {
            return $supervisors ??= app(AttestationValidationService::class)->supervisorsToNotify();
        };

        return Action::make('send_for_validation')
            ->label('Envoyer en validation')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->visible(fn ($record) => $record->canBeSentForValidation())
            // Pas de requiresConfirmation() : la modale porte le choix des
            // destinataires, et le sous-titre « Êtes-vous sûr ? » qui l'accompagne
            // n'apporte rien devant un formulaire explicite.
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalIconColor('warning')
            ->modalHeading('Envoyer l\'attestation en validation')
            // Annoncer nommément les destinataires : le superviseur n'est pas le
            // même d'un site à l'autre, et l'agent doit pouvoir vérifier avant
            // d'envoyer.
            ->modalContent(fn ($record) => view('filament.actions.send-for-validation-modal', [
                'reference' => $record->reference,
                'supervisors' => $supervisorsToNotify(),
            ]))
            ->schema(self::recipientsSchema($supervisorsToNotify))
            // Sans destinataire, l'envoi serait de toute façon refusé : autant
            // ne pas proposer de confirmer.
            ->modalSubmitAction(fn () => $supervisorsToNotify()->isNotEmpty() ? null : false)
            ->modalSubmitActionLabel('Envoyer en validation')
            ->action(function ($record, array $data) {
                $service = app(AttestationValidationService::class);

                // Contrôle préalable : sans superviseur joignable, l'envoi
                // laisserait la demande en attente d'une personne inexistante,
                // et l'action disparaîtrait de l'écran faute d'être encore
                // applicable. Mieux vaut ne rien changer.
                if (! $service->hasNotifiableSupervisors()) {
                    Notification::make()
                        ->title('Envoi en validation impossible')
                        ->body('Aucun superviseur joignable : cochez « Superviseur » sur un utilisateur disposant d\'une adresse email (Administration → Utilisateurs), puis renvoyez la demande en validation.')
                        ->danger()
                        ->duration(20000)
                        ->send();

                    return;
                }

                try {
                    $service->sendForValidation($record, Auth::user(), $data['supervisor_ids'] ?? null);
                } catch (PdfConversionException $e) {
                    Notification::make()
                        ->title('Aperçu PDF indisponible')
                        ->body($e->getMessage())
                        ->danger()
                        ->duration(15000)
                        ->send();

                    return;
                } catch (\RuntimeException $e) {
                    Notification::make()
                        ->title('Envoi en validation impossible')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                self::sendOutcomeNotification($record, $service->notifiedSupervisorsCount());
            });
    }

    /**
     * Choix des superviseurs à prévenir. Tous sont cochés par défaut : la liste
     * sert à cibler une relance, pas à restreindre qui peut valider.
     *
     * @param  \Closure(): \Illuminate\Database\Eloquent\Collection<int, User>  $supervisorsToNotify
     * @return array<int, \Filament\Forms\Components\CheckboxList>
     */
    private static function recipientsSchema(\Closure $supervisorsToNotify): array
    {
        return [
            CheckboxList::make('supervisor_ids')
                ->label('Prévenir par email')
                ->options(fn () => $supervisorsToNotify()
                    ->mapWithKeys(fn (User $supervisor) => [
                        $supervisor->id => $supervisor->getFilamentName().' — '.$supervisor->email,
                    ])
                    ->all())
                ->default(fn () => $supervisorsToNotify()->pluck('id')->all())
                ->required()
                ->minItems(1)
                ->bulkToggleable()
                ->visible(fn () => $supervisorsToNotify()->isNotEmpty())
                ->helperText('Tous les superviseurs pourront valider l\'attestation, quels que soient les destinataires de l\'email.'),
        ];
    }

    /**
     * Rendre compte de l'envoi en un seul message, plutôt qu'en empilant une
     * notification par point de vigilance.
     */
    private static function sendOutcomeNotification(Request $record, int $notifiedSupervisors): void
    {
        $warnings = [];

        // Des superviseurs existent — contrôlé avant l'envoi — mais aucun mail
        // n'est parti. L'attestation est bien en attente : elle reste visible
        // dans l'indicateur « En attente de validation », il ne faut donc pas
        // la renvoyer.
        if ($notifiedSupervisors === 0) {
            $warnings[] = 'Aucun email n\'a pu être envoyé aux superviseurs : vérifiez la configuration d\'envoi. L\'attestation reste comptée dans l\'indicateur « En attente de validation » de la liste des demandes.';
        }

        if (! $record->signatory) {
            $warnings[] = 'Aucun signataire n\'est renseigné sur la demande : aucune signature ne pourra être apposée.';
        } elseif (! $record->signatory->hasSignature()) {
            $warnings[] = "Aucune image de signature n'est enregistrée pour {$record->signatory->name} : l'attestation sera validée sans signature.";
        }

        if ($warnings === []) {
            Notification::make()
                ->title('Attestation envoyée en validation')
                ->body('Les superviseurs ont été prévenus par email.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Attestation envoyée en validation')
            ->body(($notifiedSupervisors > 0 ? 'Les superviseurs ont été prévenus par email. ' : '').implode(' ', $warnings))
            ->warning()
            ->duration(20000)
            ->send();
    }
}
