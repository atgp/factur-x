<?php

namespace Atgp\FacturX\Tests\Unit\Utils;

use Atgp\FacturX\Utils\Exception\ProfileResolutionException;
use Atgp\FacturX\Utils\ProfileHandler;
use PHPUnit\Framework\TestCase;

class ProfileHandlerTest extends TestCase
{
    /**
     * @dataProvider validProfileProvider
     */
    public function testGetReturnsProfileFromValidXml(string $urn, string $expectedProfile): void
    {
        $document = $this->createFacturXDocument($urn);

        self::assertSame($expectedProfile, ProfileHandler::get($document));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function validProfileProvider(): array
    {
        return [
            'minimum' => ['urn:factur-x.eu:1p0:minimum', 'minimum'],
            'basicwl' => ['urn:factur-x.eu:1p0:basicwl', 'basicwl'],
            'basic' => ['urn:factur-x.eu:1p0:basic', 'basic'],
            'en16931' => ['urn:cen.eu:en16931:2017', 'en16931'],
            'extended' => ['urn:factur-x.eu:1p0:extended', 'extended'],
            'second-to-last segment' => ['urn:factur-x.eu:1p0:extended:comfort', 'extended'],
        ];
    }

    public function testGetThrowsExceptionForMissingId(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rsm:CrossIndustryInvoice '
            .'xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" '
            .'xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<rsm:ExchangedDocumentContext/>'
            .'</rsm:CrossIndustryInvoice>';

        $document = new \DOMDocument();
        $document->loadXML($xml);

        $this->expectException(ProfileResolutionException::class);
        ProfileHandler::get($document);
    }

    public function testGetThrowsExceptionForInvalidProfile(): void
    {
        $document = $this->createFacturXDocument('urn:factur-x.eu:1p0:nonexistent');

        $this->expectException(ProfileResolutionException::class);
        $this->expectExceptionMessage('Invalid Factur-X URN');
        ProfileHandler::get($document);
    }

    /**
     * @dataProvider allProfilesProvider
     */
    public function testHasReturnsTrueForAllValidProfiles(string $profile): void
    {
        self::assertTrue(ProfileHandler::has($profile));
    }

    /**
     * @return array<string, array{string}>
     */
    public function allProfilesProvider(): array
    {
        return [
            'minimum' => ['minimum'],
            'basicwl' => ['basicwl'],
            'basic' => ['basic'],
            'en16931' => ['en16931'],
            'extended' => ['extended'],
            'zugferd' => ['zugferd'],
        ];
    }

    public function testHasReturnsFalseForInvalidProfile(): void
    {
        self::assertFalse(ProfileHandler::has('invalid'));
    }

    private function createFacturXDocument(string $urn): \DOMDocument
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rsm:CrossIndustryInvoice '
            .'xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" '
            .'xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<rsm:ExchangedDocumentContext>'
            .'<ram:GuidelineSpecifiedDocumentContextParameter>'
            .'<ram:ID>'.$urn.'</ram:ID>'
            .'</ram:GuidelineSpecifiedDocumentContextParameter>'
            .'</rsm:ExchangedDocumentContext>'
            .'</rsm:CrossIndustryInvoice>';

        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
