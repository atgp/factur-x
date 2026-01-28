<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX;

use Atgp\FacturX\Exceptions\ExceptionInterface;
use Atgp\FacturX\Exceptions\Reader\EmbeddedFileNotReadableException;
use Atgp\FacturX\Exceptions\Reader\FilespecNotFoundException;
use Atgp\FacturX\Exceptions\Reader\InvalidXmlException;
use Atgp\FacturX\Exceptions\Reader\ReaderExceptionInterface;
use Smalot\PdfParser\PDFObject;

class Reader
{
    public const FACTURX_FILENAME = 'factur-x.xml';
    public const ZUGFERD_FILENAME = 'zugferd-invoice.xml';

    public const ALLOWED_FILENAMES = [self::FACTURX_FILENAME, self::ZUGFERD_FILENAME];

    public array $smalotPdfParserCfg = [];
    public ?\Smalot\PdfParser\Config $smalotPdfParserConfig = null;

    /**
     * Extracts Factur-X XML from Factur-X PDF.
     *
     * @param string   $pdfBinary        content of the PDF invoice
     * @param bool     $validateXsd      validates Factur-X XML against official XSD and throws exception if validation failed
     * @param string[] $allowedFilenames by default searchs zugferd and factur-x filenames
     *
     * @throws ReaderExceptionInterface
     * @throws ExceptionInterface
     * @return string
     */
    public function extractXML(string $pdfBinary, bool $validateXsd = true, array $allowedFilenames = self::ALLOWED_FILENAMES): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser($this->smalotPdfParserCfg, $this->smalotPdfParserConfig);
            $pdfParsed = $parser->parseContent($pdfBinary);

            /** @var PDFObject $spec */
            $xml = null;
            foreach ($pdfParsed->getObjectsByType('Filespec') as $spec) {
                if (!in_array($spec->get('F')->getContent(), $allowedFilenames)) {
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
                if (null === $xml = $embeddedFileReference->get('F')->getContent()) {
                    throw new EmbeddedFileNotReadableException('EmbeddedFile not readable.');
                }
            }

            if (!$xml) {
                throw new FilespecNotFoundException('Factur-x Filespec not found.');
            }
        } catch (ExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidXmlException('Unable to get Factur-X Xml from PDF : '.$e->getMessage(), 0, $e);
        }

        if ($validateXsd) {
            $validator = new XsdValidator();
            $validator->validateWithException($xml);
        }

        return $xml;
    }
}
