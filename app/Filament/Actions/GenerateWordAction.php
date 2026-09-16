<?php

namespace App\Filament\Actions;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Services\DocxToPdfConverter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class GenerateWordAction
{
    /**
     * Noms de variables Word acceptés pour l'image de signature du signataire.
     *
     * @var list<string>
     */
    public const SIGNATURE_VARIABLES = ['signature', 'signataire.signature'];

    /**
     * Hauteur d'insertion de l'image de signature, en pixels.
     *
     * C'est la hauteur qui doit contraindre l'image : le bloc signataire du
     * modèle Word est une zone de hauteur fixe, qu'une image trop haute ferait
     * déborder sur le pied de page.
     */
    private const SIGNATURE_HEIGHT = 38;

    /**
     * Largeur maximale de l'image de signature, en pixels.
     *
     * PhpWord fait tenir l'image dans la boîte largeur × hauteur qu'on lui
     * donne, et retombe sur une largeur par défaut de 115 px si on ne la
     * précise pas : une signature large serait alors ramenée bien en dessous
     * de la hauteur voulue. Cette largeur, volontairement généreuse, laisse la
     * hauteur décider tant que la signature ne dépasse pas ~10:1.
     */
    private const SIGNATURE_MAX_WIDTH = 400;

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

    /**
     * Générer l'attestation Word de la demande.
     *
     * @param  bool  $notify  Émettre les notifications Filament. À désactiver
     *                        lorsque l'appelant rend compte lui-même du résultat,
     *                        afin de ne pas empiler plusieurs messages.
     */
    public static function generate($record, ?int $templateId = null, bool $notify = true): ?Document
    {
        // Récupérer le template (par défaut ou spécifié)
        $template = $templateId
            ? DocumentTemplate::findOrFail($templateId)
            : DocumentTemplate::getDefault();

        if (! $template) {
            if ($notify) {
                Notification::make()
                    ->title('Erreur')
                    ->body('Aucun template par défaut défini. Veuillez configurer un template dans la page Templates.')
                    ->danger()
                    ->send();
            }

            return null;
        }

        $templateProcessor = new TemplateProcessor($template->getFullPath());

        // Recharger les relations complètes : la table précharge ces relations avec
        // des colonnes limitées (ex: contactPerson:id,name), sans email ni téléphone
        $record->load([
            'applicant',
            'contact',
            'municipality',
            'signatory',
            'certifier',
            'contactPerson',
            'followedByUser',
            'parcels',
            'roads',
        ]);

        // Construire le mapping complet des données
        $dataMapping = self::buildDataMapping($record);

        // Obtenir le mapping complet du template (auto + manuel)
        $templateMapping = $template->getFullMapping();

        // Apposer la signature du signataire dès lors que l'attestation est validée
        self::applySignature($templateProcessor, $record);

        // Appliquer les valeurs pour chaque variable du template
        foreach ($template->variables ?? [] as $variable) {
            // Les variables de signature sont traitées à part (image, pas texte)
            if (in_array($variable, self::SIGNATURE_VARIABLES, true)) {
                continue;
            }

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

        if ($notify) {
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
        }

        return $document;
    }

    /**
     * Apposer l'image de signature du signataire dans le document.
     *
     * La signature n'est apposée que lorsque l'attestation a été validée par un
     * superviseur. Dans tous les autres cas les variables de signature sont
     * vidées afin que le placeholder n'apparaisse pas dans le document final.
     */
    private static function applySignature(TemplateProcessor $templateProcessor, $record): void
    {
        $signaturePath = $record->isValidated()
            ? $record->signatory?->getSignatureFullPath()
            : null;

        if ($signaturePath) {
            self::assertSignatureIsSupported($signaturePath, $record->signatory?->name);
        }

        foreach (self::SIGNATURE_VARIABLES as $variable) {
            if ($signaturePath) {
                $templateProcessor->setImageValue($variable, [
                    'path' => $signaturePath,
                    'width' => self::SIGNATURE_MAX_WIDTH,
                    'height' => self::SIGNATURE_HEIGHT,
                    'ratio' => true,
                ]);

                continue;
            }

            $templateProcessor->setValue($variable, '');
        }
    }

    /**
     * Formats d'image que Word sait afficher. Une signature enregistrée dans un
     * autre format ferait échouer la génération sur une erreur technique de
     * PhpWord, sans indiquer quoi corriger.
     *
     * @var list<string>
     */
    private const SUPPORTED_SIGNATURE_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/bmp'];

    /**
     * @throws \RuntimeException si le format de l'image de signature n'est pas
     *                           affichable dans un document Word
     */
    private static function assertSignatureIsSupported(string $signaturePath, ?string $signatoryName): void
    {
        $mime = @getimagesize($signaturePath)['mime'] ?? null;

        if (in_array($mime, self::SUPPORTED_SIGNATURE_MIMES, true)) {
            return;
        }

        $signatory = $signatoryName ? "de {$signatoryName} " : '';

        // getimagesize() échoue aussi bien sur un format inconnu que sur un
        // fichier disparu du disque : sans cette distinction, un fichier
        // manquant enverrait l'agent changer un format parfaitement valide.
        if (! is_file($signaturePath)) {
            throw new \RuntimeException(
                "L'image de signature {$signatory}est introuvable sur le serveur. "
                .'Réimportez-la depuis la fiche de l\'agent.'
            );
        }

        throw new \RuntimeException(
            "L'image de signature {$signatory}est enregistrée dans un format que Word ne sait pas afficher"
            .($mime ? " ({$mime})" : '')
            .'. Remplacez-la par un fichier PNG ou JPEG depuis la fiche de l\'agent.'
        );
    }

    /**
     * Convertir une attestation Word en PDF et l'enregistrer comme document
     * de la demande.
     *
     * @throws \App\Exceptions\PdfConversionException
     */
    public static function generatePdfFrom(Document $wordDocument, $record): Document
    {
        $sourcePath = Storage::disk('public')->path($wordDocument->file_name);
        $monthFolder = dirname($wordDocument->file_name);
        $outputDirectory = Storage::disk('public')->path($monthFolder);

        $pdfPath = app(DocxToPdfConverter::class)->convert($sourcePath, $outputDirectory);

        $relativePath = $monthFolder.'/'.basename($pdfPath);
        $documentName = Document::sanitizeFileName("Attestation - {$record->reference}.pdf");

        $existing = Document::where('request_id', $record->id)
            ->where('file_name', $relativePath)
            ->where('document_type', 'generated')
            ->first();

        if ($existing) {
            $existing->update([
                'document_name' => $documentName,
                'created_by' => Auth::user()?->name,
                'created_date' => now(),
            ]);

            return $existing;
        }

        return Document::create([
            'request_id' => $record->id,
            'document_type' => 'generated',
            'file_name' => $relativePath,
            'document_name' => $documentName,
            'created_by' => Auth::user()?->name,
            'created_date' => now(),
        ]);
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
