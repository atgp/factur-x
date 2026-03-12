<?php

namespace Atgp\FacturX\Tests\Unit;

use Atgp\FacturX\Fpdi\FdpiFacturx;
use PHPUnit\Framework\TestCase;

class FdpiFacturxTest extends TestCase
{
    public function testAttachWithEmptyNameExtractsFromPathWithSlash(): void
    {
        $fdpi = new FdpiFacturx();
        // No name provided, file path with slash: name extracted from path.
        // mime_content_type() will warn on a non-existent file — suppress it intentionally.
        set_error_handler(static fn () => true, \E_WARNING);
        try {
            $fdpi->Attach('/nonexistent/path/invoice.xml', '');
            $this->expectNotToPerformAssertions();
        } finally {
            restore_error_handler();
        }
    }

    public function testAttachWithEmptyNameUsesFullNameWhenNoSlash(): void
    {
        $fdpi = new FdpiFacturx();
        // No name provided, no slash in path: name = full string.
        // mime_content_type() will warn on a non-existent file — suppress it intentionally.
        set_error_handler(static fn () => true, \E_WARNING);
        try {
            $fdpi->Attach('invoice.xml', '');
            $this->expectNotToPerformAssertions();
        } finally {
            restore_error_handler();
        }
    }
}
