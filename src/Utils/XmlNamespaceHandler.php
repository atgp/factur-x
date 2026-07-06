<?php

namespace Atgp\FacturX\Utils;

/**
 * Holds the CII XML namespace URIs and registers them on a DOMXPath.
 *
 * XPath prefixes are resolved by namespace URI, not by the prefixes used in the
 * document. Registering them explicitly makes queries work regardless of the
 * aliases chosen by the emitter (rsm/ram/udt, ns1/ns2, default namespace, etc.).
 */
class XmlNamespaceHandler
{
    public const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
    public const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    public const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    /**
     * Creates a DOMXPath with the CII namespaces already registered.
     */
    public static function createXPath(\DOMDocument $document): \DOMXPath
    {
        return static::registerNamespaces(new \DOMXPath($document));
    }

    /**
     * Registers the CII namespaces (rsm, ram, udt) on the given DOMXPath.
     */
    public static function registerNamespaces(\DOMXPath $xpath): \DOMXPath
    {
        $xpath->registerNamespace('rsm', static::NS_RSM);
        $xpath->registerNamespace('ram', static::NS_RAM);
        $xpath->registerNamespace('udt', static::NS_UDT);

        return $xpath;
    }
}
