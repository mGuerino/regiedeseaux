<?php

namespace Tests\Feature;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Writer\Word2007;
use Tests\TestCase;

class GenerateWordEscapingTest extends TestCase
{
    public function test_template_values_with_special_characters_produce_valid_docx(): void
    {
        $templatePath = storage_path('app/test_template_escaping.docx');
        $outputPath = storage_path('app/test_output_escaping.docx');

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Demandeur : ${applicant_name}');
        (new Word2007($phpWord))->save($templatePath);

        try {
            $templateProcessor = new TemplateProcessor($templatePath);
            $templateProcessor->setValue('applicant_name', 'Paul SABATIER & Eric GRIMAL <Notaires>');
            $templateProcessor->saveAs($outputPath);

            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($outputPath), 'Le fichier docx généré doit être une archive zip valide.');
            $documentXml = $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertNotFalse($documentXml, 'word/document.xml doit exister dans le docx.');

            $dom = new \DOMDocument;
            $loaded = @$dom->loadXML($documentXml);
            $this->assertTrue($loaded, 'word/document.xml doit être un XML bien formé (caractères spéciaux échappés).');

            $this->assertStringContainsString('Paul SABATIER &amp; Eric GRIMAL', $documentXml);
        } finally {
            @unlink($templatePath);
            @unlink($outputPath);
        }
    }
}
