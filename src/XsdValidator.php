<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX;

use Atgp\FacturX\Exceptions\XsdValidator\InvalidProfileException;
use Atgp\FacturX\Exceptions\XsdValidator\XsdValidationFailureException;
use Atgp\FacturX\Exceptions\XsdValidator\XsdValidatorExceptionInterface;
use Atgp\FacturX\Utils\Exception\ProfileResolutionException;
use Atgp\FacturX\Utils\ProfileHandler;

class XsdValidator
{
    public const XSD_FILENAMES = [
        ProfileHandler::PROFILE_FACTURX_MINIMUM => 'factur-x/minimum/Factur-X_1.08_MINIMUM.xsd',
        ProfileHandler::PROFILE_FACTURX_BASICWL => 'factur-x/basic-wl/Factur-X_1.08_BASICWL.xsd',
        ProfileHandler::PROFILE_FACTURX_BASIC => 'factur-x/basic/Factur-X_1.08_BASIC.xsd',
        ProfileHandler::PROFILE_FACTURX_EN16931 => 'factur-x/en16931/Factur-X_1.08_EN16931.xsd',
        ProfileHandler::PROFILE_FACTURX_EXTENDED => 'factur-x/extended/Factur-X_1.08_EXTENDED.xsd',
        ProfileHandler::PROFILE_ZUGFERD => 'zugferd/ZUGFeRD1p0.xsd',
    ];

    protected ?string $profile = null;

    /**
     * @var \LibXMLError[]
     */
    protected array $xmlErrors = [];

    /**
     * @var string[]
     */
    protected array $errors = [];

    /**
     * Validates Factur-X XML against XSD.
     *
     * @param string      $xml     XML invoice content
     * @param string|null $profile One of \Atgp\FacturX\XsdValidator::XSD_FILENAMES keys (null for auto-detection)
     *
     * @throws XsdValidatorExceptionInterface
     * @return bool
     */
    public function validate(string $xml, ?string $profile = null): bool
    {
        $this->xmlErrors = $this->errors = [];
        $this->profile = $profile;

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        if (null === $this->profile) {
            try {
                $this->profile = ProfileHandler::get($doc);
            } catch (ProfileResolutionException $e) {
                throw new InvalidProfileException($e->getMessage(), $e->getCode(), $e);
            }
        }
        if (!ProfileHandler::has($this->profile)) {
            throw new InvalidProfileException("Unexpected profile '$profile' for Factur-X invoice.");
        }

        $xsd = static::getXsd($this->profile);
        libxml_use_internal_errors(true);
        if (!$doc->schemaValidate($xsd)) {
            $this->xmlErrors = libxml_get_errors();
            foreach ($this->xmlErrors as $xmlError) {
                $this->errors[] = sprintf('[line %d] %s : %s', $xmlError->line, $xmlError->code, $xmlError->message);
            }
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            return false;
        }

        return true;
    }

    /**
     * Validates XML against XSD and throw exception if encounters some errors.
     *
     * @throws XsdValidationFailureException
     * @throws XsdValidatorExceptionInterface
     */
    public function validateWithException(string $xml, ?string $profile = null): void
    {
        if (!$this->validate($xml, $profile)) {
            throw new XsdValidationFailureException(strtoupper($this->profile).' XML file invalid schema : '.implode(\PHP_EOL, $this->errors));
        }
    }

    /**
     * Returns used profile for validation.
     *
     * @return string|null
     */
    public function getProfile(): ?string
    {
        return $this->profile;
    }

    /**
     * @return \LibXMLError[]
     */
    public function getXmlErrors(): array
    {
        return $this->xmlErrors;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    protected static function getXsd(string $profile): string
    {
        if (!array_key_exists($profile, static::XSD_FILENAMES)) {
            throw new InvalidProfileException('No available XSD for profile '.$profile);
        }

        return sprintf('%s/../xsd/%s', __DIR__, static::XSD_FILENAMES[$profile]);
    }
}
