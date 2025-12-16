<?php

namespace App\Filament\Pages;

use App\Mail\DocumentEmail;
use App\Models\Agent;
use App\Models\Applicant;
use App\Models\Contact;
use App\Models\Document;
use App\Models\EmailLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendDocumentEmail extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Envoyer des emails';

    protected static ?string $title = 'Envoi de documents par email';

    protected static ?int $navigationSort = 100;

    public ?array $data = [];

    public function getView(): string
    {
        return 'filament.pages.send-document-email';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getAllRecipientsOptions(): array
    {
        $options = [];

        // Applicants avec email
        $applicants = Applicant::whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $applicantOptions = [];
        foreach ($applicants as $applicant) {
            $applicantOptions[$applicant->id.'_applicant'] = sprintf(
                '%s %s (%s)',
                $applicant->first_name,
                $applicant->last_name,
                $applicant->email
            );
        }

        // Contacts avec email
        $contacts = Contact::whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $contactOptions = [];
        foreach ($contacts as $contact) {
            $contactOptions[$contact->id.'_contact'] = sprintf(
                '%s %s (%s)',
                $contact->first_name,
                $contact->last_name,
                $contact->email
            );
        }

        // Agents avec email (tous, pas de filtre sur is_active)
        $agents = Agent::whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $agentOptions = [];
        foreach ($agents as $agent) {
            $agentOptions[$agent->id.'_agent'] = sprintf(
                '%s (%s)',
                $agent->name,
                $agent->email
            );
        }

        // Grouper par type
        if (! empty($applicantOptions)) {
            $options['Demandeurs'] = $applicantOptions;
        }

        if (! empty($contactOptions)) {
            $options['Contacts'] = $contactOptions;
        }

        if (! empty($agentOptions)) {
            $options['Agents'] = $agentOptions;
        }

        return $options;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Destinataires')
                    ->description('Sélectionnez les contacts ou ajoutez des emails manuellement')
                    ->schema([
                        Select::make('recipient_keys')
                            ->label('Destinataires')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn () => $this->getAllRecipientsOptions())
                            ->helperText('Sélectionnez les personnes dans votre base de données (demandeurs, contacts, agents)'),

                        TagsInput::make('manual_emails')
                            ->label('Emails supplémentaires')
                            ->placeholder('email@example.com')
                            ->helperText('Appuyez sur Entrée après chaque email')
                            ->nestedRecursiveRules([
                                'email',
                            ]),
                    ])
                    ->columns(1),

                Section::make('Documents à joindre')
                    ->description('Sélectionnez jusqu\'à 4 documents à envoyer')
                    ->schema([
                        Select::make('document_ids')
                            ->label('Documents')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->preload()
                            ->maxItems(4)
                            ->options(function () {
                                return Document::query()
                                    ->join('requests', 'documents.request_id', '=', 'requests.id')
                                    ->select('documents.id', 'documents.document_name', 'documents.document_type', 'requests.reference')
                                    ->orderBy('documents.created_at', 'desc')
                                    ->limit(500)
                                    ->get()
                                    ->mapWithKeys(fn ($doc) => [
                                        $doc->id => "{$doc->document_name} (Réf: {$doc->reference}) [{$doc->document_type}]",
                                    ]);
                            })
                            ->helperText('Maximum 4 documents par email'),
                    ])
                    ->columns(1),

                Section::make('Message')
                    ->description('Rédigez le contenu de votre email')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Sujet')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Objet du message'),

                        Textarea::make('message')
                            ->label('Message')
                            ->required()
                            ->rows(10)
                            ->placeholder('Rédigez votre message ici...')
                            ->helperText('Le message sera envoyé en texte brut'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Prévisualiser')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading('Prévisualisation de l\'email')
                ->modalContent(fn () => view('filament.modals.email-preview', [
                    'subject' => $this->data['subject'] ?? '',
                    'message' => $this->data['message'] ?? '',
                    'recipients' => $this->getRecipientsList(),
                    'documents' => $this->getDocumentsList(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalWidth('2xl'),

            Action::make('send')
                ->label('Envoyer')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmer l\'envoi')
                ->modalDescription(function () {
                    $count = count($this->getRecipientsList());

                    return $count > 0
                        ? "Vous allez envoyer cet email à {$count} destinataire(s)."
                        : 'Aucun destinataire sélectionné.';
                })
                ->action(fn () => $this->sendEmail()),
        ];
    }

    protected function getRecipientsList(): array
    {
        $emails = [];

        // Récupérer les emails depuis les clés de destinataires (format: id_type)
        if (isset($this->data['recipient_keys']) && is_array($this->data['recipient_keys'])) {
            foreach ($this->data['recipient_keys'] as $key) {
                // Parser la clé (format: "123_applicant", "456_contact", "789_agent")
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
        }

        // Ajouter les emails manuels
        if (isset($this->data['manual_emails']) && is_array($this->data['manual_emails'])) {
            $emails = array_merge($emails, $this->data['manual_emails']);
        }

        return array_unique(array_filter($emails));
    }

    protected function getDocumentsList(): Collection
    {
        if (! isset($this->data['document_ids']) || ! is_array($this->data['document_ids'])) {
            return collect([]);
        }

        return Document::whereIn('id', $this->data['document_ids'])->get();
    }

    public function sendEmail(): void
    {
        // Validation du formulaire
        $state = $this->form->getState();

        // Récupération des destinataires
        $recipients = $this->getRecipientsList();

        if (empty($recipients)) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez sélectionner au moins un destinataire.')
                ->danger()
                ->send();

            return;
        }

        // Récupération des documents
        $documents = $this->getDocumentsList();

        if ($documents->isEmpty()) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez sélectionner au moins un document.')
                ->danger()
                ->send();

            return;
        }

        // Vérification que tous les fichiers existent
        $missingFiles = [];
        foreach ($documents as $document) {
            if (! Storage::exists($document->file_name)) {
                $missingFiles[] = $document->document_name;
            }
        }

        if (! empty($missingFiles)) {
            Notification::make()
                ->title('Fichiers manquants')
                ->body('Les documents suivants sont introuvables : '.implode(', ', $missingFiles))
                ->danger()
                ->send();

            return;
        }

        // Envoi des emails
        $successCount = 0;
        $errors = [];

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new DocumentEmail(
                    subject: $state['subject'],
                    messageContent: $state['message'],
                    documents: $documents,
                ));
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "Erreur pour {$email}: ".$e->getMessage();
            }
        }

        // Enregistrement dans l'historique
        EmailLog::create([
            'subject' => $state['subject'],
            'message' => $state['message'],
            'recipients' => $recipients,
            'recipient_keys' => $state['recipient_keys'] ?? [],
            'document_ids' => $state['document_ids'],
            'sent_by' => Auth::user()->name,
            'recipients_count' => count($recipients),
            'success' => empty($errors),
            'error_message' => ! empty($errors) ? implode("\n", $errors) : null,
        ]);

        // Notification de résultat
        if ($successCount > 0) {
            Notification::make()
                ->title('Emails envoyés')
                ->body("{$successCount} email(s) envoyé(s) avec succès.")
                ->success()
                ->send();

            // Réinitialiser le formulaire
            $this->form->fill();
        }

        if (! empty($errors)) {
            Notification::make()
                ->title('Erreurs d\'envoi')
                ->body(implode("\n", $errors))
                ->danger()
                ->duration(10000)
                ->send();
        }
    }
}
