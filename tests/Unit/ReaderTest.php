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

    /**
     * Neither the parser of Smalot nor the second attempt find a Filespec with an allowed filename.
     */
    public function testExtractXmlThrowsFilespecNotFoundForOtherFilenameInObjectStream(): void
    {
        $pdfContent = (string) file_get_contents(__DIR__.'/../fixtures/pdf/facturx-objstm-leading-whitespace.pdf');

        $reader = new Reader();
        $this->expectException(FilespecNotFoundException::class);
        $this->expectExceptionMessage('Factur-x Filespec not found.');
        $reader->extractXML($pdfContent, false, ['other.xml']);
    }

    /**
     * @dataProvider objectStreamPdfProvider
     */
    public function testExtractXmlFromObjectStream(string $fixturePath): void
    {
        $pdfContent = (string) file_get_contents($fixturePath);
        $expectedXml = (string) file_get_contents(__DIR__.'/../fixtures/xml/facturx-minimum.xml');

        $reader = new Reader();

        self::assertSame($expectedXml, $reader->extractXML($pdfContent, false));
    }

    /**
     * PDF files whose Filespec is stored in an object stream (/Type /ObjStm): a line break
     * separates the offsets table from the first object, except in the last file.
     *
     * @return array<string, array{string}>
     */
    public static function objectStreamPdfProvider(): array
    {
        $fixturesDir = __DIR__.'/../fixtures/pdf';

        return [
            // Written by Spire.PDF: /First points to the line break and the offsets are relative to /First.
            // A reader following ISO 32000-1 finds the objects, Smalot loses them all.
            'line break counted in the offsets' => [$fixturesDir.'/facturx-objstm-leading-whitespace.pdf'],
            // Malformed: /First points to the line break but the offsets are relative to the first object.
            // Smalot reads it, this must not change.
            'line break not counted in the offsets' => [$fixturesDir.'/facturx-objstm-offsets-after-whitespace.pdf'],
            // Malformed: /First is 0 although the offsets table precedes the objects.
            // Smalot reads it, this must not change.
            '/First is 0' => [$fixturesDir.'/facturx-objstm-invalid-first.pdf'],
        ];
    }
}
