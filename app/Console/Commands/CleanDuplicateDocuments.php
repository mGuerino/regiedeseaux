<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDuplicateDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:clean-duplicates {--dry-run : Afficher les doublons sans les supprimer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nettoyer les documents dupliqués (même request_id, file_name et document_type)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('🔍 Recherche des documents dupliqués...');
        $this->newLine();

        // Trouver les groupes de documents dupliqués
        $duplicateGroups = DB::table('documents')
            ->select('request_id', 'file_name', 'document_type', DB::raw('COUNT(*) as count'))
            ->groupBy('request_id', 'file_name', 'document_type')
            ->having('count', '>', 1)
            ->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('✅ Aucun document dupliqué trouvé.');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  {$duplicateGroups->count()} groupe(s) de doublons trouvé(s)");
        $this->newLine();

        $totalDeleted = 0;
        $totalKept = 0;

        foreach ($duplicateGroups as $group) {
            // Récupérer tous les documents de ce groupe
            $documents = Document::where('request_id', $group->request_id)
                ->where('file_name', $group->file_name)
                ->where('document_type', $group->document_type)
                ->orderBy('id', 'desc') // Garder le plus récent (ID le plus élevé)
                ->get();

            $toKeep = $documents->first();
            $toDelete = $documents->skip(1);

            $this->line("📄 Demande #{$group->request_id} - {$group->file_name}");
            $this->line("   → Garder: Document ID #{$toKeep->id} (créé le {$toKeep->created_at})");
            $this->line("   → Supprimer: " . $toDelete->count() . " doublon(s)");

            if (!$isDryRun) {
                foreach ($toDelete as $doc) {
                    $doc->delete();
                    $totalDeleted++;
                }
                $totalKept++;
            } else {
                $totalDeleted += $toDelete->count();
                $totalKept++;
            }

            $this->newLine();
        }

        if ($isDryRun) {
            $this->info("🔍 Mode dry-run: {$totalKept} document(s) seraient conservé(s), {$totalDeleted} seraient supprimé(s)");
            $this->info('💡 Exécutez sans --dry-run pour effectuer le nettoyage');
        } else {
            $this->info("✅ Nettoyage terminé: {$totalKept} document(s) conservé(s), {$totalDeleted} doublon(s) supprimé(s)");
        }

        return Command::SUCCESS;
    }
}
