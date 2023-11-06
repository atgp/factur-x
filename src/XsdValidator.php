<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX;

use Atgp\FacturX\Utils\ProfileHandler;

class XsdValidator
{
    public const XSD_FILENAMES = [
        ProfileHandler::PROFILE_FACTURX_MINIMUM => 'factur-x/minimum/FACTUR-X_MINIMUM.xsd',
        ProfileHandler::PROFILE_FACTURX_BASICWL => 'factur-x/basic-wl/FACTUR-X_BASIC-WL.xsd',
        ProfileHandler::PROFILE_FACTURX_BASIC => 'factur-x/basic/FACTUR-X_BASIC.xsd',
        ProfileHandler::PROFILE_FACTURX_EN16931 => 'factur-x/en16931/FACTUR-X_EN16931.xsd',
        ProfileHandler::PROFILE_FACTURX_EXTENDED => 'factur-x/extended/FACTUR-X_EXTENDED.xsd',
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
     * @throws \Exception if validation is not possible
     *
     * @return bool
     */
    public function validate(string $xml, string $profile = null): bool
    {
        $this->xmlErrors = $this->errors = [];
        $this->profile = $profile;

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        if (null === $this->profile) {
            $this->profile = ProfileHandler::get($doc);
        }
        if (!ProfileHandler::has($this->profile)) {
            throw new \Exception("Unexpected profile '$profile' for Factur-X invoice.");
        }

        $xsd = static::getXsd($this->profile);
        try {
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
        } catch (\Exception $e) {
            throw new \Exception('The '.strtoupper($this->profile)." XML file is not valid against the official
            XML Schema Definition : $e.");
        }
    }

    /**
     * Validates XML against XSD and throw exception if encounters some errors.
     *
     * @throws \Exception
     */
    public function validateWithException(string $xml, string $profile = null)
    {
        if (!$this->validate($xml, $profile)) {
            throw new \Exception(strtoupper($this->profile).' XML file invalid schema : '.implode(\PHP_EOL, $this->errors));
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
            throw new \Exception('No available XSD for profile '.$profile);
        }

        return sprintf('%s/../xsd/%s', __DIR__, static::XSD_FILENAMES[$profile]);
    }
}
