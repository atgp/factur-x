<?php

namespace Atgp\FacturX\XMLExtractors;

use Atgp\FacturX\Exceptions\UnableToExtractXMLException;

class PdfDetachExtractor extends XMLExtractor
{
    /**
     * {@inheritDoc}
     */
    public function extract(string $pdfPathOrContent, array $searchFilenames): string
    {
        try {
            if (!@is_file($pdfPathOrContent)) {
                $tempFile = tempnam(sys_get_temp_dir(), time());
                @file_put_contents($tempFile, $pdfPathOrContent);
                $pdfPathOrContent = $tempFile;
            }

            $xmlAttachmentIndex = self::getXMLAttachmentIndex($pdfPathOrContent, $searchFilenames);

            $xml = self::getAttachmentContent(
                $pdfPathOrContent,
                $xmlAttachmentIndex,
            );

            if (isset($tempFile)) {
                @unlink($tempFile);
            }

            return $xml;
        } catch (\Exception $e) {
            throw new UnableToExtractXMLException($e->getMessage());
        }
    }

    /**
     * @throws UnableToExtractXMLException
     */
    private static function getXMLAttachmentIndex(string $pdfPath, array $searchFilenames): int
    {
        $pdfPath = escapeshellarg($pdfPath);

        exec("pdfdetach -list {$pdfPath}", $output, $resultCode);

        if (0 !== $resultCode) {
            throw new UnableToExtractXMLException((string) $output);
        }

        /*
         * Example output :
         * 1 embedded files
         * 1: factur-x.xml
         */
        foreach ($output as $outputLine) {
            foreach ($searchFilenames as $searchFilename) {
                if (strpos($outputLine, $searchFilename)) {
                    return (int) $outputLine[0];
                }
            }
        }

        throw new UnableToExtractXMLException('No factur-x XML attachment found');
    }

    private static function getAttachmentContent(
        string $pdfPath,
        int $attachmentIndex
    ): string {
        $pdfPath = escapeshellarg($pdfPath);
        $attachmentIndex = escapeshellarg($attachmentIndex);
        $xmlOutputPath = tempnam(sys_get_temp_dir(), time());
        $escapedXmlOutputPath = escapeshellarg($xmlOutputPath);

        exec(
            "pdfdetach -save {$attachmentIndex} {$pdfPath} -o {$escapedXmlOutputPath}",
            $output,
            $resultCode
        );

        if (0 !== $resultCode) {
            throw new UnableToExtractXMLException((string) $output);
        }

        $xml = @file_get_contents($xmlOutputPath);

        @unlink($xmlOutputPath);

        return $xml;
    }
}
