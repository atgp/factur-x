<?php

namespace Atgp\FacturX\Tests\Unit;

use Atgp\FacturX\Exceptions\XsdValidator\InvalidProfileException;
use Atgp\FacturX\Exceptions\XsdValidator\InvalidXmlException;
use Atgp\FacturX\Exceptions\XsdValidator\XsdValidationFailureException;
use Atgp\FacturX\XsdValidator;
use PHPUnit\Framework\TestCase;

class XsdValidatorTest extends TestCase
{
    private string $validXml;
    private string $invalidXml;

    protected function setUp(): void
    {
        $this->validXml = (string) file_get_contents(__DIR__.'/../fixtures/xml/facturx-minimum.xml');
        $this->invalidXml = '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">'
            .'<rsm:ExchangedDocumentContext/>'
            .'</rsm:CrossIndustryInvoice>';
    }

    public function testValidateThrowsForUnparseableXml(): void
    {
        $validator = new XsdValidator();
        $this->expectException(InvalidXmlException::class);
        set_error_handler(static fn () => true, \E_WARNING);
        try {
            $validator->validate('not valid xml <<<');
        } finally {
            restore_error_handler();
        }
    }

    public function testValidateThrowsForAutoDetectFailure(): void
    {
        $validator = new XsdValidator();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rsm:CrossIndustryInvoice '
            .'xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" '
            .'xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<rsm:ExchangedDocumentContext/>'
            .'</rsm:CrossIndustryInvoice>';

        $this->expectException(InvalidProfileException::class);
        $validator->validate($xml);
    }

    public function testValidateThrowsForExplicitInvalidProfile(): void
    {
        $validator = new XsdValidator();
        $this->expectException(InvalidProfileException::class);
        $validator->validate($this->validXml, 'nonexistent');
    }

    public function testValidateReturnsFalseAndPopulatesErrors(): void
    {
        $validator = new XsdValidator();
        $result = $validator->validate($this->invalidXml, 'minimum');

        self::assertFalse($result);
        self::assertNotEmpty($validator->getXmlErrors());
        self::assertNotEmpty($validator->getErrors());
        self::assertStringContainsString('[line', $validator->getErrors()[0]);
    }

    public function testValidateReturnsTrueForValidMinimumXml(): void
    {
        $validator = new XsdValidator();
        $result = $validator->validate($this->validXml);

        self::assertTrue($result);
    }

    public function testValidateWithExceptionThrowsOnXsdFailure(): void
    {
        $validator = new XsdValidator();
        $this->expectException(XsdValidationFailureException::class);
        $validator->validateWithException($this->invalidXml, 'minimum');
    }

    public function testGetProfileNullBeforeValidation(): void
    {
        $validator = new XsdValidator();
        self::assertNull($validator->getProfile());
    }

    public function testGetProfileAfterValidation(): void
    {
        $validator = new XsdValidator();
        $validator->validate($this->validXml);

        self::assertSame('minimum', $validator->getProfile());
    }

    public function testGetXmlErrorsAfterFailedValidation(): void
    {
        $validator = new XsdValidator();
        $validator->validate($this->invalidXml, 'minimum');

        self::assertIsArray($validator->getXmlErrors());
        self::assertNotEmpty($validator->getXmlErrors());
        self::assertInstanceOf(\LibXMLError::class, $validator->getXmlErrors()[0]);
    }

    public function testGetErrorsAfterFailedValidation(): void
    {
        $validator = new XsdValidator();
        $validator->validate($this->invalidXml, 'minimum');

        self::assertIsArray($validator->getErrors());
        self::assertNotEmpty($validator->getErrors());
    }

    public function testGetXsdThrowsForUnknownProfile(): void
    {
        $this->expectException(InvalidProfileException::class);
        $this->expectExceptionMessage('No available XSD for profile nonexistent');
        TestableXsdValidator::publicGetXsd('nonexistent');
    }

    /**
     * @dataProvider validProfileXmlProvider
     */
    public function testValidateReturnsTrueForProfile(string $fixturePath, ?string $explicitProfile): void
    {
        $xml = (string) file_get_contents($fixturePath);
        $validator = new XsdValidator();
        $result = $validator->validate($xml, $explicitProfile);

        self::assertTrue($result);
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function validProfileXmlProvider(): array
    {
        $fixturesDir = __DIR__.'/../fixtures/xml';

        return [
            'minimum' => [$fixturesDir.'/facturx-minimum.xml', null],
            'basicwl' => [$fixturesDir.'/facturx-basicwl.xml', null],
            'basic' => [$fixturesDir.'/facturx-basic.xml', null],
            'en16931' => [$fixturesDir.'/facturx-en16931.xml', null],
            'extended' => [$fixturesDir.'/facturx-extended.xml', null],
            'zugferd' => [$fixturesDir.'/zugferd.xml', 'zugferd'],
        ];
    }
}
