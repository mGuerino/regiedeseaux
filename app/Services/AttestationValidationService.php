<?php

namespace App\Services;

use App\Enums\ValidationStatus;
use App\Filament\Actions\GenerateWordAction;
use App\Filament\Resources\Requests\RequestResource;
use App\Mail\AttestationValidationRejected;
use App\Mail\AttestationValidationRequested;
use App\Models\Document;
use App\Models\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AttestationValidationService
{
    /**
     * Envoyer une attestation en validation : l'attestation est régénérée, son
     * PDF de consultation est produit, puis les superviseurs sont notifiés.
     *
     * @throws \App\Exceptions\PdfConversionException
     * @throws \RuntimeException si l'attestation Word n'a pas pu être générée
     */
    public function sendForValidation(Request $request, User $requestedBy): Document
    {
        $wordDocument = GenerateWordAction::generate($request);

        if (! $wordDocument) {
            throw new \RuntimeException("L'attestation n'a pas pu être générée. Vérifiez qu'un modèle Word par défaut est bien configuré.");
        }

        $pdfDocument = GenerateWordAction::generatePdfFrom($wordDocument, $request);

        $request->update([
            'validation_status' => ValidationStatus::Pending,
            'validation_requested_at' => now(),
            'validation_requested_by' => $requestedBy->id,
            'validated_at' => null,
            'validated_by' => null,
            'rejection_reason' => null,
        ]);

        $this->notifySupervisors($request, $requestedBy);

        return $pdfDocument;
    }

    /**
     * Valider l'attestation : la signature du signataire est apposée sur le
     * document Word, qui est ensuite reconverti en PDF.
     *
     * Le statut validé n'est appliqué qu'en mémoire pendant la génération (c'est
     * lui qui déclenche l'apposition de la signature) et n'est enregistré qu'une
     * fois le PDF signé produit. En cas d'échec, la demande reste en attente de
     * validation et l'attestation Word est régénérée sans signature.
     *
     * @throws \App\Exceptions\PdfConversionException
     * @throws \RuntimeException si l'attestation Word n'a pas pu être générée
     */
    public function approve(Request $request, User $validatedBy): Document
    {
        $request->fill([
            'validation_status' => ValidationStatus::Approved,
            'validated_at' => now(),
            'validated_by' => $validatedBy->id,
            'rejection_reason' => null,
        ]);

        $wordDocument = null;

        try {
            $wordDocument = GenerateWordAction::generate($request);

            if (! $wordDocument) {
                throw new \RuntimeException("L'attestation signée n'a pas pu être générée.");
            }

            $pdfDocument = GenerateWordAction::generatePdfFrom($wordDocument, $request);
        } catch (\Throwable $e) {
            $request->discardChanges();

            if ($wordDocument) {
                $this->restoreUnsignedAttestation($request);
            }

            throw $e;
        }

        $request->save();

        return $pdfDocument;
    }

    /**
     * Régénérer l'attestation Word sans signature après une validation avortée,
     * afin qu'aucun document signé ne subsiste pour une demande non validée.
     */
    protected function restoreUnsignedAttestation(Request $request): void
    {
        try {
            GenerateWordAction::generate($request);
        } catch (\Throwable $e) {
            Log::error('Impossible de régénérer l\'attestation non signée après l\'échec de la validation', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Refuser l'attestation et prévenir l'agent à l'origine de la demande.
     */
    public function reject(Request $request, User $rejectedBy, string $reason): void
    {
        $request->update([
            'validation_status' => ValidationStatus::Rejected,
            'validated_at' => null,
            'validated_by' => null,
            'rejection_reason' => $reason,
        ]);

        $this->notifyRejection($request, $rejectedBy, $reason);
    }

    /**
     * Prévenir par email tous les superviseurs disposant d'une adresse.
     */
    protected function notifySupervisors(Request $request, User $requestedBy): void
    {
        $supervisors = User::supervisors()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        if ($supervisors->isEmpty()) {
            Log::warning('Aucun superviseur à notifier pour la validation d\'attestation', [
                'request_id' => $request->id,
            ]);

            return;
        }

        $validationUrl = RequestResource::getUrl('validation', ['record' => $request]);

        foreach ($supervisors as $supervisor) {
            try {
                Mail::to($supervisor->email)->send(
                    new AttestationValidationRequested($request, $requestedBy, $validationUrl)
                );
            } catch (\Exception $e) {
                Log::error('Envoi de la demande de validation échoué', [
                    'request_id' => $request->id,
                    'supervisor' => $supervisor->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Prévenir l'agent que son attestation a été refusée.
     */
    protected function notifyRejection(Request $request, User $rejectedBy, string $reason): void
    {
        $recipients = collect([
            $request->validationRequestedBy?->email,
            $request->followedByUser?->email,
        ])->filter()->unique();

        if ($recipients->isEmpty()) {
            Log::warning('Aucun destinataire pour la notification de refus d\'attestation', [
                'request_id' => $request->id,
            ]);

            return;
        }

        $requestUrl = RequestResource::getUrl('edit', ['record' => $request]);

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(
                    new AttestationValidationRejected($request, $rejectedBy, $reason, $requestUrl)
                );
            } catch (\Exception $e) {
                Log::error('Envoi de la notification de refus échoué', [
                    'request_id' => $request->id,
                    'recipient' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
