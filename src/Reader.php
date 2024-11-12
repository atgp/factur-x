<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX;

use Atgp\FacturX\Exceptions\UnableToExtractXMLException;
use Atgp\FacturX\XMLExtractors\XMLExtractor;
use Atgp\FacturX\XMLExtractors\PdfParserExtractor;
use Exception;
use InvalidArgumentException;

class Reader
{
    public const FACTURX_FILENAME = 'factur-x.xml';
    public const ZUGFERD_FILENAME = 'zugferd-invoice.xml';
    public const ALLOWED_FILENAMES = [self::FACTURX_FILENAME, self::ZUGFERD_FILENAME];

    private ?XMLExtractor $xmlExtractor = null;

    public function __construct(?XMLExtractor $xmlExtractor = null)
    {
        $this->xmlExtractor = $xmlExtractor;
    }

    /**
     * Extracts Factur-X XML from Factur-X PDF.
     *
     * @param string         $pdfContentOrPath  content or path of the PDF invoice
     * @param bool           $validateXsd       validates Factur-X XML against official XSD and throws exception if validation failed
     * @param ?array<string> $searchFilenames   XML filenames to look for, by default searchs zugferd and factur-x filenames, must be a valid factur-x XML filename
     *
     * @return string the extracted XML
     *
     * @throws UnableToExtractXMLException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function extractXML(
        string $pdfContentOrPath,
        bool $validateXsd = true,
        ?array $searchFilenames = null
    ): string {
        $this->checkXMLFilenamesAreValid($searchFilenames);
        $xmlExtractor = $this->getXMLExtractor();

        if (!$xmlExtractor->isPdf($pdfContentOrPath)) {
            throw new InvalidArgumentException('The $pdfContentOrPath must be content or path of a PDF file.');
        }

        try {
            $xml = $this->getXMLExtractor()
                ->extract($pdfContentOrPath, $searchFilenames ?? self::ALLOWED_FILENAMES);
        } catch (Exception $e) {
            throw new UnableToExtractXMLException('Unable to get Factur-x XML from PDF : ' . $e->getMessage());
        }

        if ($validateXsd) {
            $validator = new XsdValidator();
            $validator->validateWithException($xml);
        }

        return $xml;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function checkXMLFilenamesAreValid(?array $searchFilenames = null): void
    {
        if ($searchFilenames && count(array_diff($searchFilenames, self::ALLOWED_FILENAMES))) {
            throw new InvalidArgumentException('Invalid parameter $searchFilenames, only valid factur-x XML filenames are allowed :' . implode(', ', self::ALLOWED_FILENAMES));
        }
    }

    private function getXMLExtractor(): XMLExtractor
    {
        return $this->xmlExtractor ?? new PdfParserExtractor();
    }

    private function setXMLExtractor(XMLExtractor $xmlExtractor): void
    {
        $this->xmlExtractor = $xmlExtractor;
    }
}
