<?php

namespace Atgp\FacturX\Tests\Unit;

use Atgp\FacturX\Exceptions\Reader\FilespecNotFoundException;
use Atgp\FacturX\Exceptions\Reader\InvalidXmlException;
use Atgp\FacturX\Reader;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase
{
    public function testExtractXmlThrowsForGarbageBinary(): void
    {
        $reader = new Reader();
        $this->expectException(InvalidXmlException::class);
        $this->expectExceptionMessage('Unable to get Factur-X Xml from PDF');
        $reader->extractXML('this is not a pdf at all %%%');
    }

    public function testExtractXmlThrowsFilespecNotFoundForBlankPdf(): void
    {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->AddPage();
        $pdfContent = $pdf->Output('S');

        $reader = new Reader();
        $this->expectException(FilespecNotFoundException::class);
        $this->expectExceptionMessage('Factur-x Filespec not found.');
        $reader->extractXML($pdfContent, false);
    }
}
