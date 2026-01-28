<?php

namespace Atgp\FacturX\Utils;

use Atgp\FacturX\Utils\Exception\ProfileResolutionException;

class ProfileHandler
{
    public const PROFILE_FACTURX_MINIMUM = 'minimum';
    public const PROFILE_FACTURX_BASICWL = 'basicwl';
    public const PROFILE_FACTURX_BASIC = 'basic';
    public const PROFILE_FACTURX_EN16931 = 'en16931';
    public const PROFILE_FACTURX_EXTENDED = 'extended';
    public const PROFILE_ZUGFERD = 'zugferd';

    public const PROFILES = [
        self::PROFILE_FACTURX_MINIMUM,
        self::PROFILE_FACTURX_BASICWL,
        self::PROFILE_FACTURX_BASIC,
        self::PROFILE_FACTURX_EN16931,
        self::PROFILE_FACTURX_EXTENDED,
        self::PROFILE_ZUGFERD,
    ];

    /**
     * @throws ProfileResolutionException
     */
    public static function get(\DOMDocument $document): string
    {
        $xpath = new \DOMXPath($document);
        $elements = $xpath->query('//rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');
        if (0 == $elements->length) {
            throw new ProfileResolutionException(
                'This XML is not a Factur-X XML because it misses the XML '.
                'tag ExchangedDocumentContext/GuidelineSpecifiedDocumentContextParameter/ram:ID.');
        }
        $doc_id = $elements->item(0)->nodeValue;
        $doc_id_exploded = explode(':', $doc_id);
        $profile = end($doc_id_exploded);
        if (!static::has(strtolower($profile))) {
            $profile = $doc_id_exploded[count($doc_id_exploded) - 2];
        }
        if (!static::has(strtolower($profile))) {
            throw new ProfileResolutionException('Invalid Factur-X URN : '.$doc_id);
        }

        return $profile;
    }

    /**
     * @param string $xml XML content
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function getFromXml(string $xml): string
    {
        return static::get(new \DOMDocument($xml));
    }

    public static function has(string $profile): bool
    {
        return in_array($profile, static::PROFILES);
    }
}
