<?php

namespace App\Filament\Actions;

use App\Models\Document;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\TemplateProcessor;

class GenerateWordAction
{
    public static function make(): Action
    {
        return Action::make('generate_word')
            ->label('Générer Word')
            ->icon(Heroicon::DocumentText)
            ->color('info')
            ->action(fn ($record) => self::generate($record));
    }

    public static function generate($record): void
    {
        $templateProcessor = new TemplateProcessor(base_path('templates/template_attestation.docx'));

        // Adresse avec sauts de ligne
        $addressTextRun = new TextRun;
        $addressTextRun->addText($record->applicant->address ?? '');
        $addressTextRun->addTextBreak();
        if ($record->applicant->address2) {
            $addressTextRun->addText($record->applicant->address2);
            $addressTextRun->addTextBreak();
        }
        $addressTextRun->addText(($record->applicant->postal_code ?? '').' '.($record->applicant->city ?? ''));

        $parcelsList = $record->parcels->map(function ($parcel) {
            return $parcel->ident;
        })->implode(', ') ?: 'Aucune parcelle';

        // Mapping des valeurs pour Word
        $mapping = [
            'demandeur.nom' => $record->applicant->last_name ?? 'N/A',
            'demandeur.prenom' => $record->applicant->first_name ?? 'N/A',
            'demandeur.contact' => $record->contact ? "{$record->contact->first_name} {$record->contact->last_name}" : 'N/A',
            'demandeur.adresse' => $addressTextRun,
            'reference' => $record->reference ?? 'N/A',
            'commune.nom' => $record->municipality->name ?? 'N/A',
            'demande.date' => $record->request_date ? $record->request_date->format('d/m/Y') : 'N/A',
            'demande.adresse' => $record->request_address ?? 'N/A',
            'parcelles' => $parcelsList,
            'interlocuteur.nom' => $record->contactPerson->name ?? 'N/A',
            'interlocuteur.tel' => $record->contactPerson->phone ?? 'N/A',
            'statut.adduction' => $record->wastewater_status ? 'Raccordable' : 'Non raccordable',
            'statut.reseauPublic' => $record->water_status ? 'Raccordable' : 'Non raccordable',
            'signataire.nom' => $record->signatory->name ?? '',
            'signataire.fonction' => $record->signatory->title ?? '',
            'certifier.nom' => $record->certifier->name ?? '',
            'certifier.fonction' => $record->certifier->title ?? '',
            'observations' => $record->observations ?? '',
            'utilisateur.nom' => $record->followedByUser 
                ? ($record->followedByUser->first_name 
                    ? "{$record->followedByUser->first_name} {$record->followedByUser->name}"
                    : $record->followedByUser->name)
                : 'N/A',

        ];

        foreach ($mapping as $key => $value) {
            if ($key === 'demandeur.adresse') {
                $templateProcessor->setComplexValue($key, $value);
            } else {
                $templateProcessor->setValue($key, $value);
            }
        }

        // Créer la structure de dossiers organisée par mois (ANNÉE.MOIS)
        $monthFolder = now()->format('Y.m');
        $timestamp = now()->format('YmdHis');
        $wordFileName = "attestation_{$record->id}.docx";
        $relativePath = "{$monthFolder}/{$wordFileName}";
        
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
        
        // Enregistrer dans la base de données avec type 'generated'
        Document::create([
            'request_id' => $record->id,
            'document_type' => 'generated',
            'file_name' => $relativePath,
            'document_name' => "Attestation - {$record->reference}.docx",
            'created_by' => Auth::user()->name,
            'created_date' => now(),
        ]);
        
        // URL pour téléchargement via le symlink storage
        $downloadUrl = asset("storage/{$relativePath}");

        // Notification de succès avec lien de téléchargement
        Notification::make()
            ->title('Attestation générée')
            ->success()
            ->body("L'attestation pour la demande {$record->reference} a été générée avec succès.")
            ->actions([
                Action::make('download')
                    ->label('Télécharger')
                    ->url($downloadUrl)
                    ->openUrlInNewTab(),
            ])
            ->send();
    }
}
