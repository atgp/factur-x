<?php

namespace Atgp\FacturX\Tests\Integration;

use Atgp\FacturX\Exceptions\Reader\FilespecNotFoundException;
use Atgp\FacturX\Exceptions\Writer\InvalidAttachmentException;
use Atgp\FacturX\Exceptions\Writer\InvalidRelationshipException;
use Atgp\FacturX\Exceptions\XsdValidator\XsdValidationFailureException;
use Atgp\FacturX\Reader;
use Atgp\FacturX\Writer;
use PHPUnit\Framework\TestCase;

class WriterReaderTest extends TestCase
{
    private static string $validXml;
    private static string $blankPdf;

    public static function setUpBeforeClass(): void
    {
        self::$validXml = (string) file_get_contents(__DIR__.'/../fixtures/xml/facturx-minimum.xml');
        self::$blankPdf = self::generateBlankPdf();
    }

    public function testWriteAndReadRoundtrip(): void
    {
        $writer = new Writer();
        $facturxPdf = $writer->generate(self::$blankPdf, self::$validXml);

        self::assertSame('minimum', $writer->getProfile());
        self::assertIsString($facturxPdf);
        self::assertStringContainsString('%PDF', $facturxPdf);

        $reader = new Reader();
        $extractedXml = $reader->extractXML($facturxPdf);

        self::assertStringContainsString('urn:factur-x.eu:1p0:minimum', $extractedXml);
        self::assertStringContainsString('F-2023-001', $extractedXml);
    }

    public function testGenerateWithXmlWithoutDeclaration(): void
    {
        $xmlWithoutDeclaration = preg_replace('/<\?xml[^?]*\?>\n?/', '', self::$validXml);

        $writer = new Writer();
        $facturxPdf = $writer->generate(self::$blankPdf, $xmlWithoutDeclaration);

        $reader = new Reader();
        $extractedXml = $reader->extractXML($facturxPdf);

        self::assertStringContainsString('urn:factur-x.eu:1p0:minimum', $extractedXml);
    }

    public function testGenerateWithLogo(): void
    {
        $writer = new Writer();
        $pdfWithoutLogo = $writer->generate(self::$blankPdf, self::$validXml, null, false);

        $writer = new Writer();
        $pdfWithLogo = $writer->generate(self::$blankPdf, self::$validXml, null, false, [], true);

        self::assertIsString($pdfWithLogo);
        self::assertStringContainsString('%PDF', $pdfWithLogo);
        self::assertGreaterThan(strlen($pdfWithoutLogo), strlen($pdfWithLogo), 'PDF with logo should be larger than PDF without logo');
    }

    public function testGenerateWithInvalidRelationship(): void
    {
        $writer = new Writer();
        $this->expectException(InvalidRelationshipException::class);
        $writer->generate(self::$blankPdf, self::$validXml, null, false, [], false, 'Invalid');
    }

    public function testGenerateWithFileAttachment(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'facturx_test_');
        file_put_contents($tmpFile, 'Test attachment content');

        try {
            $writer = new Writer();
            $facturxPdf = $writer->generate(self::$blankPdf, self::$validXml, null, false, [
                ['path' => $tmpFile, 'name' => 'attachment.txt', 'desc' => 'Test file'],
            ]);
            self::assertIsString($facturxPdf);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testGenerateWithStringAttachment(): void
    {
        $writer = new Writer();
        $facturxPdf = $writer->generate(self::$blankPdf, self::$validXml, null, false, [
            ['path' => 'string content here', 'name' => 'attachment.txt', 'desc' => 'String attachment'],
        ]);

        self::assertIsString($facturxPdf);
    }

    public function testGenerateWithInvalidAttachment(): void
    {
        $writer = new Writer();
        $this->expectException(InvalidAttachmentException::class);
        $writer->generate(self::$blankPdf, self::$validXml, null, false, [
            ['path' => 123, 'name' => 'bad.txt', 'desc' => 'Bad attachment'],
        ]);
    }

    public function testReadWithoutXsdValidation(): void
    {
        // Build an XML that is valid for profile detection but invalid against XSD
        // (missing SpecifiedTradeSettlementHeaderMonetarySummation)
        $incompleteXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rsm:CrossIndustryInvoice '
            .'xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" '
            .'xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" '
            .'xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">'
            .'<rsm:ExchangedDocumentContext>'
            .'<ram:GuidelineSpecifiedDocumentContextParameter>'
            .'<ram:ID>urn:factur-x.eu:1p0:minimum</ram:ID>'
            .'</ram:GuidelineSpecifiedDocumentContextParameter>'
            .'</rsm:ExchangedDocumentContext>'
            .'<rsm:ExchangedDocument>'
            .'<ram:ID>F-INCOMPLETE</ram:ID>'
            .'<ram:TypeCode>380</ram:TypeCode>'
            .'<ram:IssueDateTime><udt:DateTimeString format="102">20230101</udt:DateTimeString></ram:IssueDateTime>'
            .'</rsm:ExchangedDocument>'
            .'<rsm:SupplyChainTradeTransaction>'
            .'<ram:ApplicableHeaderTradeAgreement>'
            .'<ram:SellerTradeParty><ram:Name>Seller</ram:Name></ram:SellerTradeParty>'
            .'<ram:BuyerTradeParty><ram:Name>Buyer</ram:Name></ram:BuyerTradeParty>'
            .'</ram:ApplicableHeaderTradeAgreement>'
            .'<ram:ApplicableHeaderTradeDelivery/>'
            .'<ram:ApplicableHeaderTradeSettlement>'
            .'<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>'
            .'</ram:ApplicableHeaderTradeSettlement>'
            .'</rsm:SupplyChainTradeTransaction>'
            .'</rsm:CrossIndustryInvoice>';

        $writer = new Writer();
        $facturxPdf = $writer->generate(self::$blankPdf, $incompleteXml, 'minimum', false);

        $reader = new Reader();

        // Without XSD validation: succeeds
        $extractedXml = $reader->extractXML($facturxPdf, false);
        self::assertStringContainsString('F-INCOMPLETE', $extractedXml);

        // With XSD validation: fails
        $this->expectException(XsdValidationFailureException::class);
        $reader->extractXML($facturxPdf, true);
    }

    public function testReadWithCustomAllowedFilenames(): void
    {
        $writer = new Writer();
        $facturxPdf = $writer->generate(self::$blankPdf, self::$validXml, null, false);

        // The Writer embeds the XML as 'factur-x.xml'. Reading with only ZUGFeRD filename
        // should fail because 'factur-x.xml' is not in the allowed list.
        $reader = new Reader();
        $this->expectException(FilespecNotFoundException::class);
        $reader->extractXML($facturxPdf, false, [Reader::ZUGFERD_FILENAME]);
    }

    public function testReadSkipsNonMatchingFilenames(): void
    {
        // Generate a PDF with an extra XML attachment (non-facturx filename) to cover the
        // "filename does not match" continue branch in Reader::extractXML
        $extraXmlContent = '<?xml version="1.0" encoding="UTF-8"?><extra><data>test</data></extra>';
        $writer = new Writer();
        $facturxPdf = $writer->generate(self::$blankPdf, self::$validXml, null, false, [
            ['path' => $extraXmlContent, 'name' => 'extra.xml', 'desc' => 'Extra XML'],
        ]);

        $reader = new Reader();
        $extractedXml = $reader->extractXML($facturxPdf, false);

        self::assertStringContainsString('urn:factur-x.eu:1p0:minimum', $extractedXml);
    }

    /**
     * @dataProvider facturxProfileProvider
     */
    public function testWriteAndReadRoundtripWithProfile(string $fixturePath, string $expectedProfile, string $expectedUrn): void
    {
        $xml = (string) file_get_contents($fixturePath);

        $writer = new Writer();
        $facturxPdf = $writer->generate(self::$blankPdf, $xml);

        self::assertSame($expectedProfile, $writer->getProfile());

        $reader = new Reader();
        $extractedXml = $reader->extractXML($facturxPdf);

        self::assertStringContainsString($expectedUrn, $extractedXml);
    }

    /**
     * @dataProvider facturxProfileProvider
     */
    public function testGenerateWithLogoForProfile(string $fixturePath, string $expectedProfile): void
    {
        $xml = (string) file_get_contents($fixturePath);

        $writer = new Writer();
        $pdfWithoutLogo = $writer->generate(self::$blankPdf, $xml, null, false);

        $writer = new Writer();
        $pdfWithLogo = $writer->generate(self::$blankPdf, $xml, null, false, [], true);

        self::assertGreaterThan(strlen($pdfWithoutLogo), strlen($pdfWithLogo), "PDF with logo should be larger for profile $expectedProfile");
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function facturxProfileProvider(): array
    {
        $fixturesDir = __DIR__.'/../fixtures/xml';

        return [
            'minimum' => [$fixturesDir.'/facturx-minimum.xml', 'minimum', 'urn:factur-x.eu:1p0:minimum'],
            'basicwl' => [$fixturesDir.'/facturx-basicwl.xml', 'basicwl', 'urn:factur-x.eu:1p0:basicwl'],
            'basic' => [$fixturesDir.'/facturx-basic.xml', 'basic', 'urn:factur-x.eu:1p0:basic'],
            'en16931' => [$fixturesDir.'/facturx-en16931.xml', 'en16931', 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:en16931'],
            'extended' => [$fixturesDir.'/facturx-extended.xml', 'extended', 'urn:factur-x.eu:1p0:extended'],
        ];
    }

    private static function generateBlankPdf(): string
    {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->AddPage();

        return $pdf->Output('S');
    }
}
