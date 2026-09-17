<?php

namespace App\Filament\Actions;

use App\Mail\DocumentEmail;
use App\Models\Agent;
use App\Models\Applicant;
use App\Models\Contact;
use App\Models\Document;
use App\Models\EmailLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendEmailFromRequestAction
{
    /**
     * Boîte du service urbanisme, en copie de chaque envoi par défaut : l'équipe
     * garde ainsi trace des attestations parties.
     */
    public const DEFAULT_EXTRA_EMAIL = 'urbanisme@eauxdupaysdaix.fr';

    public static function make(): Action
    {
        return Action::make('send_email')
            ->label('Envoyer email')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->mountUsing(fn ($form, $record) => $form->fill(static::defaultFormData($record)))
            ->form(fn ($record) => static::formSchema($record))
            ->action(fn (array $data, $record, Action $action) => static::send($data, $record, $action))
            ->modalHeading('Envoyer des documents par email')
            ->modalSubmitActionLabel('Envoyer')
            ->modalWidth('4xl');
    }

    /**
     * Valeurs de pré-remplissage du formulaire d'envoi.
     *
     * @param  list<int>|null  $documentIds  Documents à présélectionner, tous par défaut
     * @return array<string, mixed>
     */
    public static function defaultFormData($record, ?array $documentIds = null): array
    {
        $recipientKeys = [];

        // Pré-sélectionner le contact s'il a un email
        if ($record->contact && $record->contact->email) {
            $recipientKeys[] = $record->contact->id.'_contact';
        }
        // Sinon pré-sélectionner le demandeur s'il a un email
        elseif ($record->applicant && $record->applicant->email) {
            $recipientKeys[] = $record->applicant->id.'_applicant';
        }

        $applicantName = $record->applicant
            ? "{$record->applicant->first_name} {$record->applicant->last_name}"
            : 'N/A';

        return [
            'document_ids' => $documentIds ?? $record->documents->pluck('id')->toArray(),
            'recipient_keys' => $recipientKeys,
            'manual_emails' => [self::DEFAULT_EXTRA_EMAIL],
            'subject' => "Attestation {$record->reference}",
            'message' => "Bonjour,\n\nVeuillez trouver ci-joint l'attestation pour la demande {$record->reference} concernant {$applicantName}.\n\nCordialement,\n".Auth::user()->name,
            'mark_as_completed' => false,
            'set_response_date' => false,
        ];
    }

    /**
     * Schéma du formulaire d'envoi d'email.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function formSchema($record): array
    {
        return [
            Section::make('Destinataires')
                ->description('Sélectionnez les contacts ou ajoutez des emails manuellement')
                ->schema([
                    Select::make('recipient_keys')
                        ->label('Destinataires')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => static::getRecipientOptions($record))
                        // Au moins un destinataire, dans l'un ou l'autre champ :
                        // l'erreur s'affiche sur le formulaire, qui reste ouvert.
                        ->requiredWithout('manual_emails')
                        ->validationMessages([
                            'required_without' => 'Choisissez un destinataire ou saisissez un email supplémentaire.',
                        ])
                        ->helperText('Contact et demandeur de cette demande, ou autres personnes'),

                    TagsInput::make('manual_emails')
                        ->label('Emails supplémentaires')
                        ->placeholder('email@example.com')
                        ->helperText('Appuyez sur Entrée après chaque email')
                        ->requiredWithout('recipient_keys')
                        ->validationMessages([
                            'required_without' => 'Saisissez un email ou choisissez un destinataire.',
                        ])
                        ->nestedRecursiveRules(['email']),
                ])
                ->columns(1),

            Section::make('Documents à envoyer')
                ->schema([
                    Select::make('document_ids')
                        ->label('Documents')
                        ->multiple()
                        ->required()
                        ->options(fn () => $record->documents->mapWithKeys(
                            fn (Document $doc) => [$doc->id => $doc->getAttachmentPickerLabel()],
                        ))
                        ->helperText('Documents attachés à cette demande'),
                ])
                ->columns(1),

            Section::make('Message')
                ->schema([
                    TextInput::make('subject')
                        ->label('Sujet')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('message')
                        ->label('Message')
                        ->required()
                        ->rows(8)
                        ->helperText('Personnalisez le message si nécessaire'),
                ])
                ->columns(1),

            Section::make('Options')
                ->schema([
                    Checkbox::make('mark_as_completed')
                        ->label('Marquer la demande comme "Terminée" après l\'envoi')
                        ->inline(false),

                    Checkbox::make('set_response_date')
                        ->label('Définir la date de réponse à aujourd\'hui')
                        ->inline(false),
                ])
                ->columns(1),
        ];
    }

    /**
     * Envoyer les documents sélectionnés aux destinataires choisis.
     *
     * Tant qu'aucun email n'est parti, un refus garde la modale ouverte pour que
     * l'utilisateur corrige sans ressaisir. Une fois l'envoi entamé, elle se
     * ferme même en cas d'erreur partielle : la rouvrir inviterait à renvoyer
     * aux destinataires déjà servis.
     *
     * @param  array<string, mixed>  $data
     * @return bool Vrai si au moins un email a été envoyé sans erreur
     */
    public static function send(array $data, $record, ?Action $action = null): bool
    {
        // Récupération des documents
        $documents = $record->documents()->whereIn('id', $data['document_ids'])->get();

        if ($documents->isEmpty()) {
            Notification::make()
                ->title('Erreur')
                ->body('Aucun document sélectionné.')
                ->danger()
                ->send();

            $action?->halt();

            return false;
        }

        // Vérifier que tous les fichiers existent
        $missingFiles = [];
        foreach ($documents as $document) {
            if (! Storage::disk('public')->exists($document->file_name)) {
                $missingFiles[] = $document->document_name;
            }
        }

        if (! empty($missingFiles)) {
            Notification::make()
                ->title('Fichiers manquants')
                ->body('Les fichiers suivants sont introuvables : '.implode(', ', $missingFiles))
                ->danger()
                ->send();

            $action?->halt();

            return false;
        }

        // Récupération des emails depuis les clés + emails manuels
        $emails = static::getRecipientEmails($data['recipient_keys'] ?? [], $data['manual_emails'] ?? []);

        if (empty($emails)) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez sélectionner au moins un destinataire.')
                ->danger()
                ->send();

            $action?->halt();

            return false;
        }

        // Vérification de la taille totale
        $totalSize = 0;
        foreach ($documents as $document) {
            $totalSize += $document->getFileSizeBytes();
        }

        $maxSize = 10 * 1024 * 1024; // 10 MB
        if ($totalSize > $maxSize) {
            $totalSizeMB = round($totalSize / (1024 * 1024), 2);
            Notification::make()
                ->title('Taille de fichiers trop importante')
                ->body("La taille totale des documents ({$totalSizeMB} Mo) dépasse la limite autorisée de 10 Mo.")
                ->danger()
                ->send();

            $action?->halt();

            return false;
        }

        // Envoi des emails
        $successCount = 0;
        $errors = [];

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new DocumentEmail(
                    emailSubject: $data['subject'],
                    messageContent: $data['message'],
                    documents: $documents,
                ));
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "Erreur pour {$email}: ".$e->getMessage();
            }
        }

        // Enregistrement dans l'historique
        EmailLog::create([
            'subject' => $data['subject'],
            'message' => $data['message'],
            'recipients' => $emails,
            'recipient_keys' => $data['recipient_keys'] ?? [],
            'document_ids' => $data['document_ids'],
            'sent_by' => Auth::user()->name,
            'recipients_count' => count($emails),
            'success' => empty($errors),
            'error_message' => ! empty($errors) ? implode("\n", $errors) : null,
        ]);

        // Mise à jour de la demande si demandé
        if ($data['mark_as_completed'] ?? false) {
            $record->update(['request_status' => 2]); // Terminée
        }

        if ($data['set_response_date'] ?? false) {
            $record->update(['response_date' => now()]);
        }

        // Notifications
        if ($successCount > 0) {
            Notification::make()
                ->title('Email(s) envoyé(s)')
                ->body("{$successCount} email(s) envoyé(s) avec succès.")
                ->success()
                ->send();
        }

        if (! empty($errors)) {
            Notification::make()
                ->title('Erreurs d\'envoi')
                ->body(implode("\n", $errors))
                ->danger()
                ->duration(10000)
                ->send();
        }

        return $successCount > 0 && empty($errors);
    }

    /**
     * Obtenir les options de destinataires pour le Select
     */
    protected static function getRecipientOptions($record): array
    {
        $options = [];

        // Demandeur de cette demande
        if ($record->applicant && $record->applicant->email) {
            $options['Demandeur'] = [
                $record->applicant->id.'_applicant' => sprintf(
                    '%s %s (%s)',
                    $record->applicant->first_name,
                    $record->applicant->last_name,
                    $record->applicant->email
                ),
            ];
        }

        // Contact de cette demande
        if ($record->contact && $record->contact->email) {
            $options['Contact'] = [
                $record->contact->id.'_contact' => sprintf(
                    '%s %s (%s)',
                    $record->contact->first_name,
                    $record->contact->last_name,
                    $record->contact->email
                ),
            ];
        }

        // Autres contacts disponibles (en cas de besoin)
        $otherContacts = Contact::whereNotNull('email')
            ->where('email', '!=', '')
            ->when($record->contact_id, fn ($q) => $q->where('id', '!=', $record->contact_id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        if ($otherContacts->isNotEmpty()) {
            $contactOptions = [];
            foreach ($otherContacts as $contact) {
                $contactOptions[$contact->id.'_contact'] = sprintf(
                    '%s %s (%s)',
                    $contact->first_name,
                    $contact->last_name,
                    $contact->email
                );
            }
            $options['Autres contacts'] = $contactOptions;
        }

        // Agents avec email
        $agents = Agent::whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        if ($agents->isNotEmpty()) {
            $agentOptions = [];
            foreach ($agents as $agent) {
                $agentOptions[$agent->id.'_agent'] = sprintf(
                    '%s (%s)',
                    $agent->name,
                    $agent->email
                );
            }
            $options['Agents'] = $agentOptions;
        }

        return $options;
    }

    /**
     * Convertir les clés de destinataires en emails
     */
    protected static function getRecipientEmails(array $recipientKeys, array $manualEmails): array
    {
        $emails = [];

        // Parser les clés (format: "id_type")
        foreach ($recipientKeys as $key) {
            $parts = explode('_', $key);
            if (count($parts) === 2) {
                [$id, $type] = $parts;

                $email = match ($type) {
                    'applicant' => Applicant::find($id)?->email,
                    'contact' => Contact::find($id)?->email,
                    'agent' => Agent::find($id)?->email,
                    default => null,
                };

                if ($email) {
                    $emails[] = $email;
                }
            }
        }

        // Ajouter les emails manuels
        $emails = array_merge($emails, $manualEmails);

        // Retourner emails uniques
        return array_unique(array_filter($emails));
    }
}
