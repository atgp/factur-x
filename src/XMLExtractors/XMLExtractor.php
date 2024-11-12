<?php

namespace Atgp\FacturX\XMLExtractors;

use Atgp\FacturX\Exceptions\UnableToExtractXMLException;

abstract class XMLExtractor
{
    /**
     * @throws UnableToExtractXMLException
     */
    abstract public function extract(string $pdfPathOrContent, array $searchFilenames): string;

    /**
     * Check if the given parameter is a PDF file or PDF string.
     */
    public function isPdf(string $pdfPathOrContent): bool
    {
        $isInputFile = @is_file($pdfPathOrContent);
        // if given input is not a path, create temp file to check mime type
        $filePath = $isInputFile ? $pdfPathOrContent : tempnam(sys_get_temp_dir(), time());

        // put content in temp file
        if (!$isInputFile) {
            @file_put_contents($filePath, $pdfPathOrContent);
        }

        $mimeType = mime_content_type($filePath);

        // unlink temp file
        if (!$isInputFile) {
            @unlink($filePath);
        }

        return 'application/pdf' === $mimeType;
    }
}
