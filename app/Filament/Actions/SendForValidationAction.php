<?php

namespace App\Filament\Actions;

use App\Exceptions\PdfConversionException;
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
            ->modalDescription('L\'attestation va être générée puis soumise aux superviseurs, qui recevront un email leur permettant de la consulter et de la valider.')
            ->modalSubmitActionLabel('Envoyer en validation')
            ->action(function ($record) {
                try {
                    app(AttestationValidationService::class)->sendForValidation($record, Auth::user());
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

                if (! $record->signatory) {
                    Notification::make()
                        ->title('Aucun signataire sur la demande')
                        ->body('Renseignez un signataire avant la validation, sans quoi aucune signature ne pourra être apposée.')
                        ->warning()
                        ->duration(10000)
                        ->send();
                } elseif (! $record->signatory->hasSignature()) {
                    Notification::make()
                        ->title('Signataire sans image de signature')
                        ->body("Aucune image de signature n'est enregistrée pour {$record->signatory->name}. L'attestation sera validée sans signature apposée.")
                        ->warning()
                        ->duration(10000)
                        ->send();
                }

                Notification::make()
                    ->title('Attestation envoyée en validation')
                    ->body('Les superviseurs ont été prévenus par email.')
                    ->success()
                    ->send();
            });
    }
}
