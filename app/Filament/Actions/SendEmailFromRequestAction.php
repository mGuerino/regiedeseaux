<?php

namespace App\Filament\Actions;

use App\Mail\DocumentEmail;
use App\Models\EmailLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SendEmailFromRequestAction
{
    public static function make(): Action
    {
        return Action::make('send_email')
            ->label('Envoyer email')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->mountUsing(function ($form, $record) {
                // Pré-remplir tous les champs AVANT l'ouverture du modal
                $emails = [];
                
                // Priorité au contact, sinon demandeur
                if ($record->contact && $record->contact->email) {
                    $emails[] = $record->contact->email;
                } elseif ($record->applicant && $record->applicant->email) {
                    $emails[] = $record->applicant->email;
                }

                $applicantName = $record->applicant 
                    ? "{$record->applicant->first_name} {$record->applicant->last_name}"
                    : 'N/A';
                
                $form->fill([
                    'document_ids' => $record->documents->pluck('id')->toArray(),
                    'recipient_emails' => $emails,
                    'subject' => "Attestation {$record->reference}",
                    'message' => "Bonjour,\n\nVeuillez trouver ci-joint l'attestation pour la demande {$record->reference} concernant {$applicantName}.\n\nCordialement,\n" . Auth::user()->name,
                    'mark_as_completed' => false,
                    'set_response_date' => false,
                ]);
            })
            ->form(fn ($record) => [
                Select::make('document_ids')
                    ->label('Documents à envoyer')
                    ->multiple()
                    ->required()
                    ->options(fn () => $record->documents->mapWithKeys(function ($doc) {
                        $icon = match ($doc->getFileExtension()) {
                            'pdf' => '📄',
                            'png', 'jpg', 'jpeg', 'bmp', 'gif' => '🖼️',
                            'docx', 'doc' => '📝',
                            default => '📎',
                        };
                        $size = $doc->getFileSizeFormatted();
                        $type = ucfirst($doc->document_type);

                        return [$doc->id => "{$icon} {$doc->document_name} ({$size} • {$type})"];
                    }))
                    ->helperText('Documents attachés à cette demande'),

                TagsInput::make('recipient_emails')
                    ->label('Destinataires')
                    ->placeholder('email@example.com')
                    ->required()
                    ->helperText('Emails pré-remplis avec le contact ou demandeur. Appuyez sur Entrée pour ajouter d\'autres destinataires.')
                    ->nestedRecursiveRules(['email']),

                TextInput::make('subject')
                    ->label('Sujet')
                    ->required()
                    ->maxLength(255),

                Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->rows(8)
                    ->helperText('Personnalisez le message si nécessaire'),

                Checkbox::make('mark_as_completed')
                    ->label('Marquer la demande comme "Terminée" après l\'envoi')
                    ->inline(false),

                Checkbox::make('set_response_date')
                    ->label('Définir la date de réponse à aujourd\'hui')
                    ->inline(false),
            ])
            ->action(function (array $data, $record) {
                // Récupération des documents
                $documents = $record->documents()->whereIn('id', $data['document_ids'])->get();

                if ($documents->isEmpty()) {
                    Notification::make()
                        ->title('Erreur')
                        ->body('Aucun document sélectionné.')
                        ->danger()
                        ->send();

                    return;
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

                    return;
                }

                // Envoi des emails
                $successCount = 0;
                $errors = [];

                foreach ($data['recipient_emails'] as $email) {
                    try {
                        Mail::to($email)->send(new DocumentEmail(
                            subject: $data['subject'],
                            messageContent: $data['message'],
                            documents: $documents,
                        ));
                        $successCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Erreur pour {$email}: " . $e->getMessage();
                    }
                }

                // Enregistrement dans l'historique
                EmailLog::create([
                    'subject' => $data['subject'],
                    'message' => $data['message'],
                    'recipients' => $data['recipient_emails'],
                    'recipient_keys' => [], // Pas de clés car emails directs
                    'document_ids' => $data['document_ids'],
                    'sent_by' => Auth::user()->name,
                    'recipients_count' => count($data['recipient_emails']),
                    'success' => empty($errors),
                    'error_message' => ! empty($errors) ? implode("\n", $errors) : null,
                ]);

                // Mise à jour de la demande si demandé
                if ($data['mark_as_completed']) {
                    $record->update(['request_status' => 2]); // Terminée
                }

                if ($data['set_response_date']) {
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
            })
            ->modalHeading('Envoyer des documents par email')
            ->modalSubmitActionLabel('Envoyer')
            ->modalWidth('3xl');
    }
}
