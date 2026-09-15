<?php

namespace App\Filament\Actions;

use App\Exceptions\PdfConversionException;
use App\Models\Request;
use App\Services\AttestationValidationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class SendForValidationAction
{
    public static function make(): Action
    {
        return Action::make('send_for_validation')
            ->label('Envoyer en validation')
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('warning')
            ->visible(fn ($record) => $record->canBeSentForValidation())
            ->requiresConfirmation()
            ->modalHeading('Envoyer l\'attestation en validation')
            ->modalDescription(fn ($record) => "L'attestation de la demande {$record->reference} va être générée puis soumise aux superviseurs, qui recevront un email leur permettant de la consulter et de la valider.")
            ->modalSubmitActionLabel('Envoyer en validation')
            ->action(function ($record) {
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
                    $service->sendForValidation($record, Auth::user());
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
     * Rendre compte de l'envoi en un seul message, plutôt qu'en empilant une
     * notification par point de vigilance.
     */
    private static function sendOutcomeNotification(Request $record, int $notifiedSupervisors): void
    {
        $warnings = [];

        // Des superviseurs existent — contrôlé avant l'envoi — mais aucun mail
        // n'est parti. L'attestation est bien en attente : elle reste visible
        // dans l'onglet « À valider », il ne faut donc pas la renvoyer.
        if ($notifiedSupervisors === 0) {
            $warnings[] = 'Aucun email n\'a pu être envoyé aux superviseurs : vérifiez la configuration d\'envoi. L\'attestation reste visible dans leur onglet « À valider ».';
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
