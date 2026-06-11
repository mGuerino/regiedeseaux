<?php

namespace Tests\Unit;

use App\Models\Document;
use PHPUnit\Framework\TestCase;

class DocumentFileNameSanitizationTest extends TestCase
{
    public function test_slashes_are_replaced_so_download_filename_is_valid(): void
    {
        $sanitized = Document::sanitizeFileName('Attestation - VENTE SOHEYLIAN / GABORIAU-LASKOWSKI.docx');

        $this->assertStringNotContainsString('/', $sanitized);
        $this->assertStringNotContainsString('\\', $sanitized);
        $this->assertSame('Attestation - VENTE SOHEYLIAN - GABORIAU-LASKOWSKI.docx', $sanitized);
    }

    public function test_backslashes_are_replaced(): void
    {
        $sanitized = Document::sanitizeFileName('Attestation - LOT A\\B.docx');

        $this->assertSame('Attestation - LOT A-B.docx', $sanitized);
    }

    public function test_clean_names_are_unchanged(): void
    {
        $this->assertSame(
            'Attestation - DM0017.docx',
            Document::sanitizeFileName('Attestation - DM0017.docx')
        );
    }
}
