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

    /**
     * The Factur-X profiles, i.e. every profile but legacy ZUGFeRD 1.0.
     *
     * ZUGFeRD 1.0 is a different format (its own namespaces, CrossIndustryDocument instead
     * of CrossIndustryInvoice) that this library only reads and validates. Do not confuse it
     * with ZUGFeRD 2.x, which *is* CII and is covered by the Factur-X profiles below.
     *
     * Kept in sync with PROFILES by ProfileHandlerTest : PHP 7.4 has no array unpacking in
     * constant expressions, so the two lists cannot be derived from one another here.
     */
    public const PROFILES_FACTURX = [
        self::PROFILE_FACTURX_MINIMUM,
        self::PROFILE_FACTURX_BASICWL,
        self::PROFILE_FACTURX_BASIC,
        self::PROFILE_FACTURX_EN16931,
        self::PROFILE_FACTURX_EXTENDED,
    ];

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
        $xpath = XmlNamespaceHandler::createXPath($document);
        $elements = $xpath->query('//rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');
        if (false === $elements || 0 === $elements->length) {
            throw new ProfileResolutionException(
                'This XML is not a Factur-X XML because it misses the XML '.
                'tag ExchangedDocumentContext/GuidelineSpecifiedDocumentContextParameter/ram:ID.');
        }
        $doc_id = $elements->item(0)->nodeValue;
        $doc_id_exploded = explode(':', $doc_id);
        // The profile is either the last URN segment ("urn:factur-x.eu:1p0:basic") or the
        // penultimate one ("urn:cen.eu:en16931:2017"). Profiles are matched case-insensitively,
        // so the normalized value is returned : callers compare it against self::PROFILES.
        $profile = strtolower((string) end($doc_id_exploded));
        if (!static::has($profile) && count($doc_id_exploded) >= 2) {
            $profile = strtolower($doc_id_exploded[count($doc_id_exploded) - 2]);
        }
        if (!static::has($profile)) {
            throw new ProfileResolutionException('Invalid Factur-X URN : '.$doc_id);
        }

        return $profile;
    }

    public static function has(string $profile): bool
    {
        return in_array($profile, static::PROFILES);
    }

    /**
     * Whether the profile belongs to Factur-X rather than legacy ZUGFeRD 1.0.
     *
     * Callers that produce Factur-X documents gate on this : has() answers "does the library
     * know this profile", which is a wider set.
     */
    public static function isFacturX(string $profile): bool
    {
        return in_array($profile, static::PROFILES_FACTURX, true);
    }
}
