<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX;

class Reader
{
    public const FACTURX_FILENAME = 'factur-x.xml';

    public array $smalotPdfParserCfg = [];
    public ?\Smalot\PdfParser\Config $smalotPdfParserConfig = null;

    /**
     * Extracts Factur-X XML from Factur-X PDF.
     *
     * @param string $pdfBinary   content of the PDF invoice
     * @param bool   $validateXsd validates Factur-X XML against official XSD and throws exception if validation failed
     *
     * @throws \Exception
     *
     * @return string
     */
    public function extractXML(string $pdfBinary, bool $validateXsd = true): string
    {
        $xml = false;

        try {
            $parser = new \Smalot\PdfParser\Parser($this->smalotPdfParserCfg, $this->smalotPdfParserConfig);
            $pdfParsed = $parser->parseContent($pdfBinary);
            $found = false;
            $filespec = $pdfParsed->getObjectsByType('Filespec');
            $facturxLength = null;
            foreach ($filespec as $spec) {
                $specDetails = $spec->getDetails();
                if (static::FACTURX_FILENAME == $specDetails['F']) {
                    $found = true;
                    if (!empty($specDetails['EF']) && isset($specDetails['EF']['F']) && isset($specDetails['EF']['F']['Length'])) {
                        $facturxLength = $specDetails['EF']['F']['Length']; // Get file size
                    }
                    break;
                }
            }
            if (!$found) {
                throw new \RuntimeException('Factur-x Filespec not found.');
            }

            $embeddedFiles = $pdfParsed->getObjectsByType('EmbeddedFile');
            foreach ($embeddedFiles as $embedFile) {
                $embedDetails = $embedFile->getDetails();
                // looking for file with same file length as found before, if empty length, take first EmbeddedFile
                if ($embedDetails['Length'] == $facturxLength || null == $facturxLength) {
                    $xml = $embedFile->getContent();
                }
            }

            if (!$xml) {
                throw new \RuntimeException('EmbeddedFile not found.');
            }
        } catch (\Exception $e) {
            throw new \Exception('Unable to get Factur-X Xml from PDF : '.$e);
        }

        if ($validateXsd) {
            $validator = new XsdValidator();
            $validator->validateWithException($xml);
        }

        return $xml;
    }
}
