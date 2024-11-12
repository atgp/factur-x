<?php

namespace Atgp\FacturX\XMLExtractors;

use Atgp\FacturX\Exceptions\UnableToExtractXMLException;
use Smalot\PdfParser\Parser;

class PdfParserExtractor extends XMLExtractor
{
    private array $smalotPdfParserCfg = [];
    private ?\Smalot\PdfParser\Config $smalotPdfParserConfig = null;

    public function __construct(array $smalotPdfParserCfg = [], ?\Smalot\PdfParser\Config $smalotPdfParserConfig = null)
    {
        $this->smalotPdfParserCfg = $smalotPdfParserCfg;
        $this->smalotPdfParserConfig = $smalotPdfParserConfig;
    }

    /**
     * {@inheritDoc}
     */
    public function extract(string $pdfPathOrContent, array $searchFilenames): string
    {
        try {
            $parser = new Parser($this->smalotPdfParserCfg, $this->smalotPdfParserConfig);
            $pdfParsed = @is_file($pdfPathOrContent) ?
                $parser->parseFile($pdfPathOrContent) :
                $parser->parseContent($pdfPathOrContent);

            /** @var PDFObject $spec */
            $xml = null;
            foreach ($pdfParsed->getObjectsByType('Filespec') as $spec) {
                if (!in_array($spec->get('F')->getContent(), $searchFilenames)) {
                    continue;
                }
                // Not an embedded file
                if (!$spec->has('EF')) {
                    continue;
                }

                $embeddedFileReference = $spec->get('EF');
                if (!$embeddedFileReference->has('F')) {
                    // /EF /F contains reference to /EmbeddedFile object
                    // (raw reference is not displayable with Smalot)
                    continue;
                }

                // Smalot resolve embedded stream content directly (without need to search /EmbeddedFile by reference)
                $xml = $embeddedFileReference->get('F')->getContent();
                if (null === $xml) {
                    throw new UnableToExtractXMLException('EmbeddedFile not readable.');
                }
            }

            if (!$xml) {
                throw new UnableToExtractXMLException('Factur-x Filespec not found.');
            }
        } catch (\Exception $e) {
            throw new UnableToExtractXMLException($e->getMessage());
        }

        return $xml;
    }
}
