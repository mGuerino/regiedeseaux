<?php

namespace App\Filament\Actions;

use App\Models\Document;
use App\Models\DocumentTemplate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class GenerateWordAction
{
    public static function make(): Action
    {
        return Action::make('generate_word')
            ->label('Générer attestation')
            ->icon(Heroicon::DocumentText)
            ->color('info')
            ->action(fn ($record) => self::generate($record));
    }

    public static function makeWithDownload(): Action
    {
        return Action::make('download_attestation')
            ->label('Télécharger')
            ->icon(Heroicon::ArrowDownTray)
            ->color('success')
            ->action(function ($record) {
                $document = self::generate($record);

                if (! $document) {
                    return;
                }

                // Construire le chemin complet du fichier
                $filePath = Storage::disk('public')->path($document->file_name);

                if (! file_exists($filePath)) {
                    Notification::make()
                        ->title('Erreur')
                        ->body('Le fichier généré est introuvable.')
                        ->danger()
                        ->send();

                    return;
                }

                // Télécharger le fichier
                return response()->download($filePath, Document::sanitizeFileName($document->document_name));
            });
    }

    public static function generate($record, ?int $templateId = null): ?Document
    {
        // Récupérer le template (par défaut ou spécifié)
        $template = $templateId
            ? DocumentTemplate::findOrFail($templateId)
            : DocumentTemplate::getDefault();

        if (! $template) {
            Notification::make()
                ->title('Erreur')
                ->body('Aucun template par défaut défini. Veuillez configurer un template dans la page Templates.')
                ->danger()
                ->send();

            return null;
        }

        $templateProcessor = new TemplateProcessor($template->getFullPath());

        // Construire le mapping complet des données
        $dataMapping = self::buildDataMapping($record);

        // Obtenir le mapping complet du template (auto + manuel)
        $templateMapping = $template->getFullMapping();

        // Appliquer les valeurs pour chaque variable du template
        foreach ($template->variables ?? [] as $variable) {
            // Chercher le mapping de la variable
            $mappingKey = $templateMapping[$variable] ?? null;

            if (! $mappingKey) {
                // Variable non mappée → vide
                $value = '';
            } elseif (str_starts_with($mappingKey, '__FIXED__:')) {
                // Valeur fixe
                $value = substr($mappingKey, 10);
            } else {
                // Résoudre la valeur depuis le mapping
                $value = self::resolveValue($record, $mappingKey, $dataMapping);
            }

            $templateProcessor->setValue($variable, $value ?? '');
        }

        // Créer la structure de dossiers organisée par mois (ANNÉE.MOIS)
        $monthFolder = now()->format('Y.m');
        $timestamp = now()->format('YmdHis');
        $wordFileName = "attestation_{$record->id}.docx";
        $relativePath = "{$monthFolder}/{$wordFileName}";

        // Vérifier si un document identique existe déjà pour cette demande
        $existingDocument = Document::where('request_id', $record->id)
            ->where('file_name', $relativePath)
            ->where('document_type', 'generated')
            ->first();

        // Sauvegarder temporairement pour traitement avec PHPWord
        $tempPath = storage_path("app/temp_{$timestamp}_{$wordFileName}");
        $templateProcessor->saveAs($tempPath);

        // Déplacer vers storage/app/public/{ANNÉE.MOIS}/
        Storage::disk('public')->putFileAs(
            $monthFolder,
            new \Illuminate\Http\File($tempPath),
            $wordFileName
        );

        // Nettoyer le fichier temporaire
        @unlink($tempPath);

        // Mettre à jour le document existant ou créer un nouveau
        if ($existingDocument) {
            $existingDocument->update([
                'document_name' => Document::sanitizeFileName("Attestation - {$record->reference}.docx"),
                'created_by' => Auth::user()->name,
                'created_date' => now(),
            ]);

            $actionMessage = 'régénérée';
            $document = $existingDocument;
        } else {
            $document = Document::create([
                'request_id' => $record->id,
                'document_type' => 'generated',
                'file_name' => $relativePath,
                'document_name' => Document::sanitizeFileName("Attestation - {$record->reference}.docx"),
                'created_by' => Auth::user()->name,
                'created_date' => now(),
            ]);

            $actionMessage = 'générée';
        }

        // URL pour téléchargement via le symlink storage
        $downloadUrl = asset("storage/{$relativePath}");

        // Notification de succès avec lien de téléchargement
        Notification::make()
            ->title('Attestation '.$actionMessage)
            ->success()
            ->body("L'attestation pour la demande {$record->reference} a été {$actionMessage} avec succès.")
            ->actions([
                Action::make('download')
                    ->label('Télécharger')
                    ->url($downloadUrl)
                    ->openUrlInNewTab(),
            ])
            ->send();

        return $document;
    }

    /**
     * Construire le mapping complet des données disponibles
     */
    private static function buildDataMapping($record): array
    {
        // Liste des rues et parcelles
        $parcelsList = $record->parcels->map(fn ($parcel) => $parcel->ident)->implode(', ') ?: 'Aucune parcelle';
        $roadsList = $record->roads->map(fn ($road) => $road->pivot->road_name ?: $road->name)->filter()->implode("\n") ?: 'Aucune rue';

        // Déterminer si pluriel nécessaire
        $parcelsCount = $record->parcels->count();
        $isPlural = $parcelsCount > 1;

        return [
            // Demande
            'reference' => $record->reference ?? 'N/A',
            'request_date' => $record->request_date ? $record->request_date->format('d/m/Y') : 'N/A',
            'response_date' => $record->response_date ? $record->response_date->format('d/m/Y') : 'N/A',
            'request_status_text' => match ($record->request_status) {
                1 => 'En cours',
                2 => 'Terminée',
                3 => 'Annulée',
                default => 'N/A',
            },
            'water_status_text' => $record->water_status
                ? ($isPlural ? 'Raccordables' : 'Raccordable')
                : ($isPlural ? 'Non raccordables' : 'Non raccordable'),
            'wastewater_status_text' => $record->wastewater_status
                ? ($isPlural ? 'Raccordables' : 'Raccordable')
                : ($isPlural ? 'Non raccordables' : 'Non raccordable'),
            'observations' => $record->observations ?? '',
            'map_url' => $record->map_url ?? '',

            // Demandeur
            'applicant.last_name' => $record->applicant->last_name ?? 'N/A',
            'applicant.first_name' => $record->applicant->first_name ?? 'N/A',
            'applicant.full_name' => trim(($record->applicant->first_name ?? '').' '.($record->applicant->last_name ?? '')) ?: 'N/A',
            'applicant.address' => $record->applicant->address ?? '',
            'applicant.address2' => $record->applicant->address2 ?? '',
            'applicant.postal_code' => $record->applicant->postal_code ?? '',
            'applicant.city' => $record->applicant->city ?? '',
            'applicant.full_address' => trim(implode("\n", array_filter([
                $record->applicant->address ?? null,
                $record->applicant->address2 ?? null,
                trim(($record->applicant->postal_code ?? '').' '.($record->applicant->city ?? '')),
            ]))),
            'applicant.email' => $record->applicant->email ?? '',
            'applicant.phone1' => $record->applicant->phone1 ?? '',
            'applicant.phone2' => $record->applicant->phone2 ?? '',

            // Contact
            'contact.first_name' => $record->contact->first_name ?? 'N/A',
            'contact.last_name' => $record->contact->last_name ?? 'N/A',
            'contact.full_name' => $record->contact ? trim("{$record->contact->first_name} {$record->contact->last_name}") : 'N/A',
            'contact.email' => $record->contact->email ?? '',
            'contact.phone' => $record->contact->phone ?? '',

            // Commune
            'municipality.code' => $record->municipality->code ?? 'N/A',
            'municipality.name' => $record->municipality->name ?? 'N/A',
            'municipality.postal_code' => $record->municipality->postal_code ?? '',
            'municipality.display_name' => $record->municipality->display_name ?? '',

            // Signataire
            'signatory.name' => $record->signatory->name ?? '',
            'signatory.title' => $record->signatory->title ?? '',
            'signatory.phone' => $record->signatory->phone ?? '',
            'signatory.email' => $record->signatory->email ?? '',

            // Certificateur
            'certifier.name' => $record->certifier->name ?? '',
            'certifier.title' => $record->certifier->title ?? '',
            'certifier.phone' => $record->certifier->phone ?? '',
            'certifier.email' => $record->certifier->email ?? '',

            // Interlocuteur
            'contactPerson.name' => $record->contactPerson->name ?? 'N/A',
            'contactPerson.title' => $record->contactPerson->title ?? '',
            'contactPerson.phone' => $record->contactPerson->phone ?? 'N/A',
            'contactPerson.email' => $record->contactPerson->email ?? '',

            // Utilisateur
            'followedByUser.name' => $record->followedByUser->name ?? 'N/A',
            'followedByUser.first_name' => $record->followedByUser->first_name ?? '',
            'followedByUser.full_name' => $record->followedByUser
                ? trim(($record->followedByUser->first_name ?? '').' '.($record->followedByUser->name ?? ''))
                : 'N/A',
            'followedByUser.email' => $record->followedByUser->email ?? '',
            'followedByUser.phone' => $record->followedByUser->phone ?? '',

            // Valeurs calculées spéciales
            'parcelles' => $parcelsList,
            'demande.adresse' => $roadsList,
        ];
    }

    /**
     * Résoudre une valeur depuis le mapping
     */
    private static function resolveValue($record, string $mappingKey, array $dataMapping): string
    {
        // Chercher dans le data mapping pré-construit
        if (isset($dataMapping[$mappingKey])) {
            return (string) $dataMapping[$mappingKey];
        }

        // Si pas trouvé, retourner vide
        return '';
    }
}
