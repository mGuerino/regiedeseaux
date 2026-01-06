<?php

namespace App\Console\Commands;

use App\Models\DocumentTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class SyncTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:sync {--fix : Automatically fix issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize template records with physical files and extract variables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Scanning templates...');
        $this->newLine();

        $issues = [];
        $fixed = [];

        // 1. Vérifier les templates en base de données
        $this->info('📋 Checking database records...');
        $templates = DocumentTemplate::all();

        foreach ($templates as $template) {
            $this->line("  • Template #{$template->id}: {$template->name}");

            // Vérifier si le fichier physique existe
            if (!$template->fileExists()) {
                $issue = "    ❌ File missing: {$template->file_path}";
                $this->error($issue);
                $issues[] = [
                    'type' => 'missing_file',
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'file_path' => $template->file_path,
                ];
                continue;
            }

            $this->line("    ✓ File exists");

            // Vérifier si les variables sont extraites
            if (empty($template->variables)) {
                $issue = "    ⚠️  No variables extracted";
                $this->warn($issue);

                if ($this->option('fix')) {
                    try {
                        $fullPath = $template->getFullPath();
                        $processor = new TemplateProcessor($fullPath);
                        $variables = $processor->getVariables();

                        $template->update(['variables' => $variables]);

                        $this->info("    ✓ Extracted " . count($variables) . " variables");
                        $fixed[] = [
                            'template_id' => $template->id,
                            'template_name' => $template->name,
                            'action' => 'extracted_variables',
                            'count' => count($variables),
                        ];
                    } catch (\Exception $e) {
                        $this->error("    ❌ Failed to extract variables: {$e->getMessage()}");
                        $issues[] = [
                            'type' => 'extraction_failed',
                            'template_id' => $template->id,
                            'template_name' => $template->name,
                            'error' => $e->getMessage(),
                        ];
                    }
                } else {
                    $issues[] = [
                        'type' => 'no_variables',
                        'template_id' => $template->id,
                        'template_name' => $template->name,
                    ];
                }
            } else {
                $this->line("    ✓ " . count($template->variables) . " variables found");
            }

            $this->newLine();
        }

        // 2. Vérifier les fichiers orphelins
        $this->info('📁 Checking for orphaned files...');
        $files = DocumentTemplate::disk()->files();
        $dbFilePaths = $templates->pluck('file_path')->toArray();

        foreach ($files as $file) {
            if (!in_array($file, $dbFilePaths)) {
                $this->warn("  • Orphaned file: {$file}");
                $issues[] = [
                    'type' => 'orphaned_file',
                    'file_path' => $file,
                ];

                if ($this->option('fix')) {
                    if ($this->confirm("    Delete this orphaned file?")) {
                        DocumentTemplate::disk()->delete($file);
                        $this->info("    ✓ Deleted");
                        $fixed[] = [
                            'action' => 'deleted_orphaned_file',
                            'file_path' => $file,
                        ];
                    }
                }
            }
        }

        $this->newLine();

        // 3. Rapport final
        $this->info('📊 Summary:');
        $this->line("  • Total templates in database: " . $templates->count());
        $this->line("  • Total files on disk: " . count($files));
        $this->line("  • Issues found: " . count($issues));
        $this->line("  • Issues fixed: " . count($fixed));

        if (count($issues) > 0) {
            $this->newLine();
            $this->warn('⚠️  Issues found:');
            foreach ($issues as $issue) {
                $type = $issue['type'];
                switch ($type) {
                    case 'missing_file':
                        $this->line("  • Missing file for template #{$issue['template_id']} ({$issue['template_name']})");
                        break;
                    case 'no_variables':
                        $this->line("  • No variables for template #{$issue['template_id']} ({$issue['template_name']})");
                        break;
                    case 'orphaned_file':
                        $this->line("  • Orphaned file: {$issue['file_path']}");
                        break;
                    case 'extraction_failed':
                        $this->line("  • Extraction failed for template #{$issue['template_id']}: {$issue['error']}");
                        break;
                }
            }

            if (!$this->option('fix')) {
                $this->newLine();
                $this->info('💡 Run with --fix to automatically fix issues');
            }
        } else {
            $this->newLine();
            $this->info('✅ All templates are synchronized!');
        }

        return count($issues) > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
