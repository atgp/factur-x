<?php

namespace Atgp\FacturX\Tests\Unit;

use Atgp\FacturX\Exceptions\Writer\InvalidProfileException;
use Atgp\FacturX\Exceptions\Writer\InvalidXmlException;
use Atgp\FacturX\Writer;
use PHPUnit\Framework\TestCase;

class WriterTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $writer = new Writer();

        self::assertNull($writer->getProfile());
        self::assertTrue($writer->doesImportExternalLinks());
    }

    public function testConstructorWithFalseImportExternalLinks(): void
    {
        $writer = new Writer(false);

        self::assertFalse($writer->doesImportExternalLinks());
    }

    public function testSetImportExternalLinks(): void
    {
        $writer = new Writer();
        $result = $writer->setImportExternalLinks(false);

        self::assertSame($writer, $result);
        self::assertFalse($writer->doesImportExternalLinks());
    }

    public function testGenerateThrowsForUnparseableXml(): void
    {
        $writer = new Writer();
        $this->expectException(InvalidXmlException::class);
        $this->expectExceptionMessage('Unable to parse Factur-X XML.');
        set_error_handler(static fn () => true, \E_WARNING);
        try {
            $writer->generate('fake-pdf', 'not valid xml <<<');
        } finally {
            restore_error_handler();
        }
    }

    public function testGenerateThrowsForUnresolvableProfile(): void
    {
        $writer = new Writer();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rsm:CrossIndustryInvoice '
            .'xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" '
            .'xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<rsm:ExchangedDocumentContext/>'
            .'</rsm:CrossIndustryInvoice>';

        $this->expectException(InvalidProfileException::class);
        $writer->generate('fake-pdf', $xml);
    }

    public function testGenerateThrowsForExplicitInvalidProfile(): void
    {
        $writer = new Writer();
        $xml = (string) file_get_contents(__DIR__.'/../fixtures/xml/facturx-minimum.xml');

        $this->expectException(InvalidProfileException::class);
        $this->expectExceptionMessage("Unexpected profile 'nonexistent'");
        $writer->generate('fake-pdf', $xml, 'nonexistent');
    }

    public function testExtractInvoiceInformationsReturnsInvoice(): void
    {
        $writer = new TestableWriter();
        $xml = (string) file_get_contents(__DIR__.'/../fixtures/xml/facturx-minimum.xml');
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $info = $writer->publicExtractInvoiceInformations($doc);

        self::assertSame('F-2023-001', $info['invoiceId']);
        self::assertSame('Invoice', $info['docTypeName']);
        self::assertSame('Seller Company SAS', $info['seller']);
    }

    public function testExtractInvoiceInformationsWithCreditNote(): void
    {
        $writer = new TestableWriter();
        $xml = (string) file_get_contents(__DIR__.'/../fixtures/xml/facturx-minimum.xml');
        // Replace TypeCode 380 with a credit note type
        $xml = str_replace('<ram:TypeCode>380</ram:TypeCode>', '<ram:TypeCode>381</ram:TypeCode>', $xml);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $info = $writer->publicExtractInvoiceInformations($doc);

        self::assertSame('Credit note', $info['docTypeName']);
    }

    public function testExtractInvoiceInformationsWithAliasedNamespaces(): void
    {
        $writer = new TestableWriter();
        // Real-world emitters use arbitrary namespace prefixes (ns1, ns2, ...)
        // and a default namespace for CrossIndustryInvoice instead of rsm/ram/udt.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<CrossIndustryInvoice '
            .'xmlns="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">'
            .'<ns1:ExchangedDocument '
            .'xmlns:ns1="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">'
            .'<ns2:ID xmlns:ns2="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">F00000001</ns2:ID>'
            .'<ns2:TypeCode xmlns:ns2="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">380</ns2:TypeCode>'
            .'<ns2:IssueDateTime xmlns:ns2="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<ns3:DateTimeString xmlns:ns3="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100" format="102">20260702</ns3:DateTimeString>'
            .'</ns2:IssueDateTime>'
            .'</ns1:ExchangedDocument>'
            .'<ns4:SupplyChainTradeTransaction '
            .'xmlns:ns4="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">'
            .'<ns5:ApplicableHeaderTradeAgreement '
            .'xmlns:ns5="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<ns5:SellerTradeParty><ns5:Name>FOURNISSEUR</ns5:Name></ns5:SellerTradeParty>'
            .'</ns5:ApplicableHeaderTradeAgreement>'
            .'</ns4:SupplyChainTradeTransaction>'
            .'</CrossIndustryInvoice>';

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $info = $writer->publicExtractInvoiceInformations($doc);

        self::assertSame('F00000001', $info['invoiceId']);
        self::assertSame('Invoice', $info['docTypeName']);
        self::assertSame('FOURNISSEUR', $info['seller']);
        self::assertStringStartsWith('2026-07-02T', $info['date']);
    }

    public function testExtractInvoiceInformationsThrowsForMissingField(): void
    {
        $writer = new TestableWriter();
        // XML without ram:ID in ExchangedDocument
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rsm:CrossIndustryInvoice '
            .'xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" '
            .'xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" '
            .'xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">'
            .'<rsm:ExchangedDocument>'
            .'<ram:TypeCode>380</ram:TypeCode>'
            .'<ram:IssueDateTime><udt:DateTimeString format="102">20230101</udt:DateTimeString></ram:IssueDateTime>'
            .'</rsm:ExchangedDocument>'
            .'</rsm:CrossIndustryInvoice>';

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $this->expectException(InvalidXmlException::class);
        $this->expectExceptionMessage('Missing XML element for XPath expression');
        $writer->publicExtractInvoiceInformations($doc);
    }
}
