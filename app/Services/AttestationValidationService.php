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
    protected int $notifiedSupervisorsCount = 0;

    /**
     * Envoyer une attestation en validation : l'attestation est régénérée, son
     * PDF de consultation est produit, puis les superviseurs sont notifiés.
     *
     * @param  list<int>|null  $supervisorIds  Superviseurs à prévenir : null pour
     *                                         tous, tableau vide pour personne. La
     *                                         validation reste ouverte à tous.
     *
     * @throws \App\Exceptions\PdfConversionException
     * @throws \RuntimeException si l'attestation Word n'a pas pu être générée
     */
    public function sendForValidation(Request $request, User $requestedBy, ?array $supervisorIds = null): Document
    {
        $wordDocument = GenerateWordAction::generate($request, notify: false);

        if (! $wordDocument) {
            throw new \RuntimeException("L'attestation n'a pas pu être générée. Vérifiez qu'un modèle Word par défaut est bien configuré.");
        }

        $pdfDocument = GenerateWordAction::generatePdfFrom($wordDocument, $request);

        $recipients = $this->resolveRecipients($supervisorIds);

        $request->update([
            'validation_status' => ValidationStatus::Pending,
            'validation_requested_at' => now(),
            'validation_requested_by' => $requestedBy->id,
            'validation_notified_to' => $recipients->pluck('id')->all(),
            'validated_at' => null,
            'validated_by' => null,
            'rejection_reason' => null,
        ]);

        if ($recipients->isNotEmpty()) {
            $this->notifySupervisors($request, $requestedBy, $recipients);
        } else {
            $this->notifiedSupervisorsCount = 0;
        }

        return $pdfDocument;
    }

    /**
     * Superviseurs effectivement prévenus : ceux que l'agent a cochés, restreints
     * à ceux qui restent joignables, ou tous à défaut de sélection.
     *
     * @param  list<int>|null  $supervisorIds
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    protected function resolveRecipients(?array $supervisorIds): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->notifiableSupervisors();

        // null : aucun choix exprimé, tous les superviseurs sont prévenus.
        // Tableau vide : l'agent a tout décoché, personne ne reçoit d'email —
        // la validation reste ouverte à tous les superviseurs.
        if ($supervisorIds !== null) {
            $query->whereIn('id', $supervisorIds);
        }

        return $query->orderBy('name')->get();
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
            $wordDocument = GenerateWordAction::generate($request, notify: false);

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
            GenerateWordAction::generate($request, notify: false);
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
     * Un superviseur au moins est-il joignable ? Sans destinataire, l'envoi en
     * validation n'a pas de sens : il laisserait la demande en attente d'une
     * personne qui n'existe pas.
     */
    public function hasNotifiableSupervisors(): bool
    {
        return $this->notifiableSupervisors()->exists();
    }

    /**
     * Superviseurs qui recevront la demande de validation, pour les annoncer à
     * l'agent avant l'envoi.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function supervisorsToNotify(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->notifiableSupervisors()->orderBy('name')->get();
    }

    /**
     * Nombre de superviseurs effectivement prévenus lors du dernier envoi en
     * validation. Un envoi qui échoue (SMTP indisponible) ne doit pas être
     * annoncé à l'agent comme réussi.
     */
    public function notifiedSupervisorsCount(): int
    {
        return $this->notifiedSupervisorsCount;
    }

    /**
     * Requête des superviseurs disposant d'une adresse email exploitable.
     */
    protected function notifiableSupervisors(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->supervisors()
            ->whereNotNull('email')
            ->where('email', '!=', '');
    }

    /**
     * Prévenir par email les superviseurs retenus pour cet envoi.
     */
    protected function notifySupervisors(Request $request, User $requestedBy, \Illuminate\Database\Eloquent\Collection $supervisors): void
    {
        $this->notifiedSupervisorsCount = 0;

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

                $this->notifiedSupervisorsCount++;
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
