<?php

namespace Atgp\FacturX\Tests\Unit;

use Atgp\FacturX\Writer;

/**
 * Test-only subclass exposing Writer's protected methods as public.
 */
class TestableWriter extends Writer
{
    public function publicExtractInvoiceInformations(\DOMDocument $doc): array
    {
        return $this->extractInvoiceInformations($doc);
    }

    public function publicPreparePdfMetadata(\DOMDocument $doc): array
    {
        return $this->preparePdfMetadata($doc);
    }
}
