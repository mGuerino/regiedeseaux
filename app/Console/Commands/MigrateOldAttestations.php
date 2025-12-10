<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrateOldAttestations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attestations:migrate {--dry-run : Afficher ce qui serait fait sans effectuer les changements}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrer les anciennes attestations du dossier public/ vers storage/app/public/{ANNÉE.MOIS}/ et nettoyer';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 MODE TEST - Aucune modification ne sera effectuée');
            $this->newLine();
        }

        // Rechercher tous les fichiers attestation_*.docx dans public/
        $publicPath = public_path();
        $attestationFiles = File::glob($publicPath . '/attestation_*.docx');
        
        if (empty($attestationFiles)) {
            $this->info('✅ Aucune ancienne attestation trouvée dans le dossier public/');
            return Command::SUCCESS;
        }

        $this->info('📄 ' . count($attestationFiles) . ' attestation(s) trouvée(s) dans public/');
        $this->newLine();

        $migratedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($attestationFiles as $filePath) {
            $fileName = basename($filePath);
            
            // Extraire l'ID de la demande depuis le nom du fichier
            preg_match('/attestation_(\d+)\.docx/', $fileName, $matches);
            
            if (!isset($matches[1])) {
                $this->warn("⚠️  Impossible d'extraire l'ID de: {$fileName}");
                $skippedCount++;
                continue;
            }

            $requestId = $matches[1];
            
            // Vérifier que la demande existe (même si soft deleted)
            $request = Request::withTrashed()->find($requestId);
            
            if (!$request) {
                $this->warn("⚠️  Demande #{$requestId} introuvable - Fichier: {$fileName}");
                $skippedCount++;
                continue;
            }

            // Récupérer la date de création du fichier pour déterminer le dossier
            $fileDate = File::lastModified($filePath);
            $monthFolder = date('Y.m', $fileDate);
            $relativePath = "{$monthFolder}/{$fileName}";

            $this->line("📋 Migration de: {$fileName} → storage/app/public/{$relativePath}");

            if (!$isDryRun) {
                try {
                    // Copier le fichier vers storage/app/public/{ANNÉE.MOIS}/
                    Storage::disk('public')->putFileAs(
                        $monthFolder,
                        new \Illuminate\Http\File($filePath),
                        $fileName
                    );

                    // Créer l'enregistrement dans la base de données
                    Document::create([
                        'request_id' => $requestId,
                        'document_type' => 'generated',
                        'file_name' => $relativePath,
                        'document_name' => "Attestation - {$request->reference}.docx",
                        'created_by' => 'System Migration',
                        'created_date' => date('Y-m-d', $fileDate),
                    ]);

                    // Supprimer l'ancien fichier de public/
                    File::delete($filePath);

                    $this->info("   ✅ Migré avec succès");
                    $migratedCount++;
                } catch (\Exception $e) {
                    $this->error("   ❌ Erreur: {$e->getMessage()}");
                    $errorCount++;
                }
            } else {
                $this->info("   ✓ Serait migré vers storage/app/public/{$relativePath}");
                $migratedCount++;
            }
        }

        $this->newLine();
        $this->info('📊 Résumé de la migration:');
        $this->line("   - Migrés: {$migratedCount}");
        
        if ($skippedCount > 0) {
            $this->line("   - Ignorés: {$skippedCount}");
        }
        
        if ($errorCount > 0) {
            $this->line("   - Erreurs: {$errorCount}");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->warn('⚠️  Pour effectuer réellement la migration, lancez la commande sans --dry-run');
        } else {
            $this->newLine();
            $this->info('✅ Migration terminée avec succès!');
        }

        return Command::SUCCESS;
    }
}
