<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nom du template');
            $table->text('description')->nullable()->comment('Description optionnelle');
            $table->string('file_path', 500)->comment('Chemin relatif du fichier');
            $table->boolean('is_active')->default(true)->comment('Template actif/inactif');
            $table->boolean('is_default')->default(false)->comment('Template par défaut');
            $table->json('variables')->nullable()->comment('Variables détectées dans le template');
            $table->json('variable_mappings')->nullable()->comment('Mapping manuel des variables');
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('is_default');
        });

        // Importer le template existant
        $this->importExistingTemplate();
    }

    /**
     * Importer le template existant comme premier template
     */
    private function importExistingTemplate(): void
    {
        $sourceFile = base_path('templates/template_attestation.docx');
        $destinationDir = storage_path('app/templates');
        $destinationFile = $destinationDir . '/template_1_attestation_standard.docx';

        // Créer le dossier si nécessaire
        if (!file_exists($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        // Copier le fichier
        if (file_exists($sourceFile)) {
            copy($sourceFile, $destinationFile);

            // Détecter les variables
            $variables = [];
            try {
                $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($destinationFile);
                $variables = $templateProcessor->getVariables();
            } catch (\Exception) {
                // Ignorer les erreurs de détection
            }

            // Créer l'entrée en base de données
            DB::table('document_templates')->insert([
                'name' => 'Attestation Standard',
                'description' => 'Template d\'attestation par défaut (importé automatiquement)',
                'file_path' => 'templates/template_1_attestation_standard.docx',
                'is_active' => true,
                'is_default' => true,
                'variables' => json_encode($variables),
                'variable_mappings' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
