<?php

namespace App\Services;

use App\Exceptions\PdfConversionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DocxToPdfConverter
{
    /**
     * Convertir un document Word en PDF via LibreOffice en mode headless.
     *
     * @param  string  $sourcePath  Chemin absolu du fichier .docx à convertir
     * @param  string  $outputDirectory  Répertoire absolu de destination du PDF
     * @return string Chemin absolu du PDF généré
     *
     * @throws PdfConversionException
     */
    public function convert(string $sourcePath, string $outputDirectory): string
    {
        if (! file_exists($sourcePath)) {
            throw PdfConversionException::sourceMissing($sourcePath);
        }

        $binary = $this->resolveBinary();

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0755, true) && ! is_dir($outputDirectory)) {
            throw PdfConversionException::conversionFailed("Impossible de créer le répertoire de destination {$outputDirectory}.");
        }

        $result = Process::timeout($this->timeout())->run([
            $binary,
            '--headless',
            '--norestore',
            '-env:UserInstallation=file://'.$this->profileDirectory(),
            '--convert-to',
            'pdf:writer_pdf_Export',
            '--outdir',
            $outputDirectory,
            $sourcePath,
        ]);

        $expectedPdf = rtrim($outputDirectory, '/').'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.pdf';

        if (! $result->successful()) {
            Log::error('Conversion PDF échouée', [
                'source' => $sourcePath,
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error_output' => $result->errorOutput(),
            ]);

            throw PdfConversionException::conversionFailed($result->errorOutput() ?: $result->output());
        }

        if (! file_exists($expectedPdf)) {
            Log::error('Conversion PDF sans fichier de sortie', [
                'source' => $sourcePath,
                'expected' => $expectedPdf,
                'output' => $result->output(),
            ]);

            throw PdfConversionException::outputMissing();
        }

        return $expectedPdf;
    }

    /**
     * Vérifier que LibreOffice est utilisable sur cette machine.
     */
    public function isAvailable(): bool
    {
        try {
            $binary = $this->resolveBinary();
        } catch (PdfConversionException) {
            return false;
        }

        return Process::timeout(30)->run([$binary, '--headless', '--version'])->successful();
    }

    /**
     * Localiser le binaire LibreOffice.
     *
     * @throws PdfConversionException
     */
    protected function resolveBinary(): string
    {
        $configured = config('services.libreoffice.path');

        if (filled($configured)) {
            if (! is_executable($configured)) {
                throw PdfConversionException::binaryNotFound($configured);
            }

            return $configured;
        }

        foreach (['/usr/bin/soffice', '/usr/local/bin/soffice', '/opt/homebrew/bin/soffice'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        throw PdfConversionException::binaryNotFound('soffice');
    }

    /**
     * Répertoire de profil LibreOffice, indispensable pour que l'utilisateur
     * exécutant PHP-FPM puisse lancer la conversion.
     */
    protected function profileDirectory(): string
    {
        $directory = config('services.libreoffice.profile_path') ?: storage_path('app/libreoffice-profile');

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        return $directory;
    }

    protected function timeout(): int
    {
        return (int) config('services.libreoffice.timeout', 120);
    }
}
