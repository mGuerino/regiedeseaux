<?php

namespace Tests\Unit;

use App\Exceptions\PdfConversionException;
use App\Services\DocxToPdfConverter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;
use Tests\TestCase;

class DocxToPdfConverterTest extends TestCase
{
    private string $workingDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workingDirectory = sys_get_temp_dir().'/docx-pdf-'.uniqid();
        mkdir($this->workingDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workingDirectory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workingDirectory);

        parent::tearDown();
    }

    private function createDocx(): string
    {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('Attestation de raccordement');

        $path = $this->workingDirectory.'/source.docx';
        (new Word2007($phpWord))->save($path);

        return $path;
    }

    public function test_it_converts_a_word_document_to_pdf(): void
    {
        $converter = new DocxToPdfConverter;

        if (! $converter->isAvailable()) {
            $this->markTestSkipped('LibreOffice n\'est pas installé sur cette machine.');
        }

        $pdfPath = $converter->convert($this->createDocx(), $this->workingDirectory.'/out');

        $this->assertFileExists($pdfPath);
        $this->assertStringEndsWith('source.pdf', $pdfPath);
        $this->assertSame('%PDF', file_get_contents($pdfPath, false, null, 0, 4));
    }

    public function test_it_fails_when_the_source_document_does_not_exist(): void
    {
        $this->expectException(PdfConversionException::class);

        (new DocxToPdfConverter)->convert($this->workingDirectory.'/absent.docx', $this->workingDirectory);
    }

    public function test_it_fails_when_the_configured_binary_is_not_executable(): void
    {
        config()->set('services.libreoffice.path', '/chemin/inexistant/soffice');

        $this->expectException(PdfConversionException::class);
        $this->expectExceptionMessage('LibreOffice est introuvable');

        (new DocxToPdfConverter)->convert($this->createDocx(), $this->workingDirectory);
    }
}
