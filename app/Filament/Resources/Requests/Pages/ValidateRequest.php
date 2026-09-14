<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Exceptions\PdfConversionException;
use App\Filament\Actions\SendEmailFromRequestAction;
use App\Filament\Resources\Requests\RequestResource;
use App\Models\Document;
use App\Services\AttestationValidationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ValidateRequest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RequestResource::class;

    protected string $view = 'filament.resources.requests.pages.validate-request';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Identifiant du document PDF joint à l'email après validation.
     */
    public ?int $signedPdfDocumentId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(Auth::user()->canValidateAttestations(), 403, 'Seuls les superviseurs peuvent valider les attestations.');
    }

    public function getTitle(): string
    {
        return "Validation de l'attestation {$this->record->reference}";
    }

    public function getBreadcrumb(): string
    {
        return 'Validation';
    }

    /**
     * URL d'affichage du PDF dans l'aperçu, ou null si aucun PDF n'est
     * disponible pour cette demande.
     */
    public function getPreviewUrl(): ?string
    {
        return $this->getPreviewDocument()
            ? route('requests.attestation.preview', ['request' => $this->record->id])
            : null;
    }

    public function getPreviewDocument(): ?Document
    {
        $document = $this->record->latestGeneratedDocument('pdf');

        if (! $document || ! Storage::disk('public')->exists($document->file_name)) {
            return null;
        }

        return $document;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->approveAction(),
            $this->rejectAction(),
            $this->sendEmailAction(),
            Action::make('back_to_request')
                ->label('Ouvrir la demande')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => RequestResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    protected function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Valider et envoyer')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn () => $this->record->isAwaitingValidation())
            ->requiresConfirmation()
            ->modalHeading("Valider l'attestation")
            ->modalDescription('La signature du signataire va être apposée sur l\'attestation, puis le formulaire d\'envoi par email s\'ouvrira.')
            ->modalSubmitActionLabel('Valider')
            ->action(function () {
                try {
                    $pdfDocument = app(AttestationValidationService::class)->approve($this->record, Auth::user());
                } catch (PdfConversionException|\RuntimeException $e) {
                    Notification::make()
                        ->title('Validation impossible')
                        ->body($e->getMessage())
                        ->danger()
                        ->duration(15000)
                        ->send();

                    return;
                }

                $this->signedPdfDocumentId = $pdfDocument->id;
                $this->record->refresh();

                Notification::make()
                    ->title('Attestation validée')
                    ->body($this->record->signatory?->hasSignature()
                        ? 'La signature a été apposée sur l\'attestation.'
                        : 'Aucune image de signature n\'était disponible : l\'attestation a été validée sans signature.')
                    ->success()
                    ->send();

                $this->replaceMountedAction('send_email');
            });
    }

    protected function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Refuser')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn () => $this->record->isAwaitingValidation())
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('Motif du refus')
                    ->required()
                    ->rows(5)
                    ->maxLength(2000)
                    ->helperText('Ce motif sera transmis par email à l\'agent qui a demandé la validation.'),
            ])
            ->modalHeading("Refuser l'attestation")
            ->modalSubmitActionLabel('Refuser')
            ->action(function (array $data) {
                app(AttestationValidationService::class)->reject($this->record, Auth::user(), $data['rejection_reason']);

                $this->record->refresh();

                Notification::make()
                    ->title('Attestation refusée')
                    ->body('L\'agent a été prévenu par email du motif du refus.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Formulaire d'envoi de l'attestation validée, ouvert à la suite de la
     * validation. Le PDF signé y est présélectionné.
     */
    protected function sendEmailAction(): Action
    {
        return SendEmailFromRequestAction::make()
            ->record(fn () => $this->record)
            ->visible(fn () => $this->record->isValidated())
            ->label('Envoyer l\'attestation')
            ->mountUsing(fn ($form) => $form->fill(SendEmailFromRequestAction::defaultFormData(
                $this->record,
                $this->signedPdfDocumentId ? [$this->signedPdfDocumentId] : null,
            )));
    }
}
