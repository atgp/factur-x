<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * (c) Lucas Gouy-Pailler <lucas.gouypailler@atgp.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX;

use Atgp\FacturX\Fpdi\FdpiFacturx;

class Facturx
{
    const VERSION = '1.0';
    const FACTURX_ENCODING = 'UTF-8';
    const FACTURX_FILENAME = 'factur-x.xml';

    const PROFIL_FACTURX_MINIMUM = 'minimum';
    const PROFIL_FACTURX_BASICWL = 'basicwl';
    const PROFIL_FACTURX_BASIC = 'basic';
    const PROFIL_FACTURX_EN16931 = 'en16931';
    const PROFIL_FACTURX_EXTENDED = 'extended';
    const PROFIL_ZUGFERD = 'zugferd';

    const FACTURX_PROFIL_TO_XSD = [
        self::PROFIL_FACTURX_MINIMUM => 'factur-x/minimum/FACTUR-X_MINIMUM.xsd',
        self::PROFIL_FACTURX_BASICWL => 'factur-x/basic-wl/FACTUR-X_BASIC-WL.xsd',
        self::PROFIL_FACTURX_BASIC => 'factur-x/basic/FACTUR-X_BASIC.xsd',
        self::PROFIL_FACTURX_EN16931 => 'factur-x/en16931/FACTUR-X_EN16931.xsd',
        self::PROFIL_FACTURX_EXTENDED => 'factur-x/extended/FACTUR-X_EXTENDED.xsd',
        self::PROFIL_ZUGFERD => 'zugferd/ZUGFeRD1p0.xsd',
    ];
    const FACTURX_LOGO = [
        self::PROFIL_FACTURX_MINIMUM => 'Factur-x_minimum.jpg',
        self::PROFIL_FACTURX_BASICWL => 'Factur-x_basic_wl.jpg',
        self::PROFIL_FACTURX_BASIC => 'Factur-x_basic.jpg',
        self::PROFIL_FACTURX_EN16931 => 'Factur-x_en16931.jpg',
        self::PROFIL_FACTURX_EXTENDED => 'Factur-x_extended.jpg',
    ];
    const FACTURX_PROFIL_TO_XMP = [
        self::PROFIL_FACTURX_MINIMUM => 'MINIMUM',
        self::PROFIL_FACTURX_BASICWL => 'BASIC WL',
        self::PROFIL_FACTURX_BASIC => 'BASIC',
        self::PROFIL_FACTURX_EN16931 => 'EN 16931',
        self::PROFIL_FACTURX_EXTENDED => 'EXTENDED',
    ];
    const FACTURX_XMP = 'Factur-X_extension_schema.xmp';

    private $profil = null;

    /**
     * Get Factur-X XML from Factur-X PDF.
     *
     * @param string $pdfInvoice File name or content of the PDF invoice
     * @param bool   $checkXsd   check Factur-X XML against official XSD
     *
     * @throws \Exception
     *
     * @return string
     */
    public function getFacturxXmlFromPdf($pdfInvoice, $checkXsd = true)
    {
        $pdfBinary = null;
        $pdfFile = null;
        if (@is_file($pdfInvoice)) {
            $pdfFile = $pdfInvoice;
        } elseif (is_string($pdfInvoice)) {
            $pdfBinary = $pdfInvoice;
        } else {
            throw new \Exception('$pdfInvoice argument must be a file or a binary string');
        }
        $xmlString = false;
        try {
            $parser = new \Smalot\PdfParser\Parser();
            if (null != $pdfBinary) {
                $pdfParsed = $parser->parseContent($pdfBinary);
            } elseif (null != $pdfFile) {
                $pdfParsed = $parser->parseFile($pdfFile);
            } else {
                throw new \Exception('$pdfInvoice argument must be a file or a binary string');
            }
            $facturx_found = false;
            $filespec = $pdfParsed->getObjectsByType('Filespec');
            $facturxLength = null;
            foreach ($filespec as $spec) {
                $specDetails = $spec->getDetails();
                if (static::FACTURX_FILENAME == $specDetails['F']) {
                    $facturx_found = true;
                    if (!empty($specDetails['EF']) && isset($specDetails['EF']['F']) && isset($specDetails['EF']['F']['Length'])) {
                        $facturxLength = $specDetails['EF']['F']['Length']; // Get file size
                    }
                    break;
                }
            }
            if (true == $facturx_found) {
                $embeddedFiles = $pdfParsed->getObjectsByType('EmbeddedFile');
                foreach ($embeddedFiles as $embedFile) {
                    $embedDetails = $embedFile->getDetails();
                    if ($embedDetails['Length'] == $facturxLength || null == $facturxLength) { // looking for file with same file length as found before, if empty length, take first EmbeddedFile
                        $xmlString = $embedFile->getContent();
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('Unable to get Factur-X Xml from PDF : '.$e);
        }
        if (false !== $xmlString) {
            if (true == $checkXsd) {
                $this->checkFacturxXsd($xmlString);
            }
            if (null == $this->profil && false !== $xmlString) {
                $doc = new \DOMDocument();
                $doc->loadXML($xmlString);
                $this->profil = $this->getFacturxProfil($doc);
            }
        }

        return $xmlString;
    }

    /**
     * Check Factur-X XML against XSD.
     *
     * @param string $facturxXml    File name or content of the XML invoice
     * @param string $facturxProfil One of \Atgp\FacturX\Facturx::PROFIL_* (null for auto-detection)
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function checkFacturxXsd($facturxXml, $facturxProfil = null)
    {
        if (@is_file($facturxXml)) {
            $xmlString = file_get_contents($facturxXml);
            $doc = new \DOMDocument();
            $doc->loadXML($xmlString);
        } elseif (is_string($facturxXml)) {
            $doc = new \DOMDocument();
            $doc->loadXML($facturxXml);
        } else {
            throw new \Exception('$facturxXml argument must be a file or a string');
        }

        if (!$this->profil) {
            if (null === $facturxProfil) {
                $facturxProfil = $this->getFacturxProfil($doc);
            }
            if (!array_key_exists($facturxProfil, static::FACTURX_PROFIL_TO_XSD)) {
                throw new \Exception("Wrong profil '$facturxProfil' for Factur-X invoice.");
            }
            $this->profil = $facturxProfil;
        }
        $xsdFilename = static::FACTURX_PROFIL_TO_XSD[$this->profil];
        $xsdFile = __DIR__.'/../xsd/'.$xsdFilename;
        try {
            libxml_use_internal_errors(true);
            $schemaValidated = $doc->schemaValidate($xsdFile);
            if (false == $schemaValidated) {
                $errors = libxml_get_errors();
                $errorsMessage = '';
                foreach ($errors as $error) {
                    $errorsMessage .= sprintf('XML error "%s"'."\n", $error->message);
                }
                libxml_clear_errors();
                libxml_use_internal_errors(false);
                throw new \Exception(strtoupper($this->profil).' XML file invalid schema : '.$errorsMessage);
            }
        } catch (\Exception $e) {
            throw new \Exception('The '.strtoupper($this->profil)." XML file is not valid against the official
            XML Schema Definition : $e.");
        }

        return true;
    }

    /**
     * Generate Factur-X PDF from PDF invoice and Factur-X XML.
     *
     * @param string      $pdfInvoice            File name or content of the PDF invoice
     * @param string      $facturxXml            File name or content of the XML invoice
     * @param string|null $facturxProfil         One of \Atgp\FacturX\Facturx::PROFIL_* (null for auto-detection)
     * @param bool        $checkXsd              check Factur-X XML against official XSD
     * @param string      $outputFilePath        Output file path for PDF Factur-X, if empty, file string will be returned
     * @param bool        $addFacturxLogo        Add Factur-X logo on PDF first page according to Factur-X profil
     * @param mixed       $additionalAttachments
     * @param string      $relationship          the embarkation relationship, must be Data|Source|Alternative
     *
     * @throws \Exception
     *
     * @return string
     */
    public function generateFacturxFromFiles(
        $pdfInvoice,
        $facturxXml,
        $facturxProfil = null,
        $checkXsd = true,
        $outputFilePath = '',
        $additionalAttachments = [],
        $addFacturxLogo = false,
        $relationship = 'Data'
    ) {
        $pdfInvoiceRef = null;
        if (@is_file($pdfInvoice)) {
            $pdfInvoiceRef = $pdfInvoice;
        } elseif (is_string($pdfInvoice)) {
            $pdfInvoiceRef = \setasign\Fpdi\PdfParser\StreamReader::createByString($pdfInvoice);
        }
        if (@is_file($facturxXml)) {
            $xmlString = file_get_contents($facturxXml);
            $facturxXmlRef = $facturxXml;
        } elseif (is_string($facturxXml)) {
            if ('<?xml' != substr($facturxXml, 0, 5)) { // Add XML tags
                $facturxXml = "<?xml version='1.0' encoding='".static::FACTURX_ENCODING."' ?>\n".$facturxXml;
            }
            $xmlString = $facturxXml;
            $facturxXmlRef = \setasign\Fpdi\PdfParser\StreamReader::createByString($facturxXml);
        } else {
            throw new \Exception('$facturxXml argument must be a string or a file');
        }
        $docFacturx = new \DOMDocument();
        $docFacturx->loadXML($xmlString);

        if (null === $facturxProfil) {
            $facturxProfil = $this->getFacturxProfil($docFacturx);
        }

        if (!array_key_exists($facturxProfil, static::FACTURX_PROFIL_TO_XSD)) {
            throw new \Exception("Wrong profil '$facturxProfil' for Factur-X invoice.");
        }
        $this->profil = $facturxProfil;

        if (true == $checkXsd) {
            // The profil is validated inside checkFacturxXsd
            $this->checkFacturxXsd($facturxXml, $facturxProfil);
        }

        $pdfWriter = new FdpiFacturx();
        $pageCount = $pdfWriter->setSourceFile($pdfInvoiceRef);
        for ($i = 1; $i <= $pageCount; ++$i) {
            $tplIdx = $pdfWriter->importPage($i, '/MediaBox');
            $pdfWriter->AddPage();
            $pdfWriter->useTemplate($tplIdx, 0, 0, null, null, true);
            if (true == $addFacturxLogo && 1 == $i) { // add Factur-X logo on first page only
                $pdfWriter->Image(__DIR__.'/../img/'.static::FACTURX_LOGO[$this->profil], 197, 2.5, 7);
            }
        }
        if (!in_array($relationship, ['Data', 'Source', 'Alternative'])) {
            throw new \Exception('$relationship argument must be one of the values "Data", "Source", "Alternative".');
        }
        $pdfWriter->Attach($facturxXmlRef, static::FACTURX_FILENAME, 'Factur-X Invoice', $relationship, 'text#2Fxml');
        foreach ($additionalAttachments as $attachment) {
            if (@is_file($attachment['path'])) {
                $attachment_file_ref = $attachment['path'];
            } elseif (is_string($attachment['path'])) {
                $attachment_file_ref = sys_get_temp_dir().'/'.$attachment['name'];
                file_put_contents($attachment_file_ref, $attachment['path']); // creating tmp file to solve mime_content_type errors
            } else {
                throw new \Exception('$attachment_file argument must be a string or a file');
            }
            $pdfWriter->Attach($attachment_file_ref, $attachment['name'], $attachment['desc']);
        }
        $pdfWriter->OpenAttachmentPane();
        $pdfWriter->SetPDFVersion('1.7', true); // version 1.7 according to PDF/A-3 ISO 32000-1
        $pdfWriter = $this->updatePdfMetadata($pdfWriter, $docFacturx);
        $facturxGeneratedFileName = 'invoice-facturx-'.date('Ymdhis').'.pdf';
        if (!empty($outputFilePath)) {
            return $this->generateFacturxFile($pdfWriter, $outputFilePath, $facturxGeneratedFileName);
        }

        return $this->generateFacturxString($pdfWriter, $facturxGeneratedFileName);
    }

    /**
     * Get Factur-X profil.
     *
     * @param \DOMDocument $facturxXml
     *
     * @throws \Exception
     *
     * @return string
     */
    public function getFacturxProfil(\DOMDocument $facturxXml)
    {
        if (!$facturxXml instanceof \DOMDocument) {
            throw new \Exception('$facturxXml must be a DOMDocument object');
        }
        $xpath = new \DOMXpath($facturxXml);
        $elements = $xpath->query('//rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');
        if (0 == $elements->length) {
            throw new \Exception('This XML is not a Factur-X XML because it misses the XML
                tag ExchangedDocumentContext/GuidelineSpecifiedDocumentContextParameter/ram:ID.');
        }
        $doc_id = $elements->item(0)->nodeValue;
        $doc_id_exploded = explode(':', $doc_id);
        $profil = end($doc_id_exploded);
        if (!array_key_exists(strtolower($profil), static::FACTURX_PROFIL_TO_XSD)) {
            $profil = $doc_id_exploded[count($doc_id_exploded) - 2];
        }
        if (!array_key_exists(strtolower($profil), static::FACTURX_PROFIL_TO_XSD)) {
            throw new \Exception('Invalid Factur-X URN : '.$doc_id);
        }

        return $profil;
    }

    /**
     * @param FdpiFacturx $pdfWriter                PDF writer class
     * @param string      $outputFilePath           Output file path for PDF Factur-X
     * @param string      $facturxGeneratedFileName File name of the generated Factur-X PDF
     *
     * @throws \Exception
     *
     * @return string Path of the Factur-X file generated
     */
    protected function generateFacturxFile(FdpiFacturx $pdfWriter, $outputFilePath, $facturxGeneratedFileName)
    {
        if (!is_dir($outputFilePath)) {
            throw new \Exception(sprintf('Invalid output directory : %s', $outputFilePath));
        }
        $outputCompleteFilePath = sprintf('%s/%s', $outputFilePath, $facturxGeneratedFileName);
        $pdfWriter->Output($outputCompleteFilePath, 'F');

        return $outputCompleteFilePath;
    }

    /**
     * @param FdpiFacturx $pdfWriter                PDF writer class
     * @param string      $facturxGeneratedFileName File name of the generated Factur-X PDF
     *
     * @return string String of the Factur-X PDF generated
     */
    protected function generateFacturxString(FdpiFacturx $pdfWriter, $facturxGeneratedFileName)
    {
        return $pdfWriter->Output($facturxGeneratedFileName, 'S');
    }

    /**
     * Update PDF metadata to according to Factur-X XML data.
     *
     * @param FdpiFacturx  $pdfWriter
     * @param \DOMDocument $facturxXml
     *
     * @return FdpiFacturx
     */
    protected function updatePdfMetadata(FdpiFacturx $pdfWriter, \DOMDocument $facturxXml)
    {
        $pdf_metadata_infos = $this->preparePdfMetadata($facturxXml);
        $pdfWriter->set_pdf_metadata_infos($pdf_metadata_infos);

        $xmp = simplexml_load_file(__DIR__.'/../xmp/'.static::FACTURX_XMP);
        $description_nodes = $xmp->xpath('rdf:Description');

        $desc_fx = $description_nodes[0];
        $desc_fx->children('fx', true)->ConformanceLevel = strtoupper(static::FACTURX_PROFIL_TO_XMP[$this->profil]);
        $pdfWriter->AddMetadataDescriptionNode($desc_fx->asXML());

        $pdfWriter->AddMetadataDescriptionNode($description_nodes[1]->asXML());

        $desc_pdfaid = $description_nodes[2];
        $pdfWriter->AddMetadataDescriptionNode($desc_pdfaid->asXML());

        $desc_dc = $description_nodes[3];
        $desc_nodes = $desc_dc->children('dc', true);
        $desc_nodes->title->children('rdf', true)->Alt->li = $pdf_metadata_infos['title'];
        $desc_nodes->creator->children('rdf', true)->Seq->li = $pdf_metadata_infos['author'];
        $desc_nodes->description->children('rdf', true)->Alt->li = $pdf_metadata_infos['subject'];
        $pdfWriter->AddMetadataDescriptionNode($desc_dc->asXML());

        $desc_adobe = $description_nodes[4];
        $desc_adobe->children('pdf', true)->Producer = 'FPDF';
        $pdfWriter->AddMetadataDescriptionNode($desc_adobe->asXML());

        $desc_xmp = $description_nodes[5];
        $xmp_nodes = $desc_xmp->children('xmp', true);
        $xmp_nodes->CreatorTool = sprintf('Factur-X PHP library v%s by @GP', static::VERSION);
        $xmp_nodes->CreateDate = $pdf_metadata_infos['createdDate'];
        $xmp_nodes->ModifyDate = $pdf_metadata_infos['modifiedDate'];
        $pdfWriter->AddMetadataDescriptionNode($desc_xmp->asXML());

        return $pdfWriter;
    }

    /**
     * Prepare PDF Metadata informations from Factur-X XML.
     *
     * @param \DOMDocument $facturxXml
     *
     * @return array
     */
    protected function preparePdfMetadata(\DOMDocument $facturxXml)
    {
        $invoiceInformations = $this->extractInvoiceInformations($facturxXml);
        $dateString = date('Y-m-d', strtotime($invoiceInformations['date']));
        $title = sprintf('%s : %s %s', $invoiceInformations['seller'], $invoiceInformations['docTypeName'], $invoiceInformations['invoiceId']);
        $subject = sprintf('Factur-X %s %s dated %s issued by %s', $invoiceInformations['docTypeName'], $invoiceInformations['invoiceId'], $dateString, $invoiceInformations['seller']);
        $pdf_metadata = [
            'author' => $invoiceInformations['seller'],
            'keywords' => sprintf('%s, Factur-X', $invoiceInformations['docTypeName']),
            'title' => $title,
            'subject' => $subject,
            'createdDate' => $invoiceInformations['date'],
            'modifiedDate' => date('Y-m-d\TH:i:s').'+00:00',
        ];

        return $pdf_metadata;
    }

    /**
     * Extract major invoice information from Factur-X XML.
     *
     * @param \DOMDocument $facturxXml
     *
     * @return array
     */
    protected function extractInvoiceInformations(\DOMDocument $facturxXml)
    {
        $xpath = new \DOMXpath($facturxXml);
        $dateXpath = $xpath->query('//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString');
        $date = $dateXpath->item(0)->nodeValue;
        $dateReformatted = date('Y-m-d\TH:i:s', strtotime($date)).'+00:00';
        $invoiceIdXpath = $xpath->query('//rsm:ExchangedDocument/ram:ID');
        $invoiceId = $invoiceIdXpath->item(0)->nodeValue;
        $sellerXpath = $xpath->query('//ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:Name');
        $seller = $sellerXpath->item(0)->nodeValue;
        $docTypeXpath = $xpath->query('//rsm:ExchangedDocument/ram:TypeCode');
        $docType = $docTypeXpath->item(0)->nodeValue;
        switch ($docType) {
            case '381':
                $docTypeName = 'Refund';
                break;
            default:
                $docTypeName = 'Invoice';
                break;
        }
        $base_info = [
            'invoiceId' => $invoiceId,
            'docTypeName' => $docTypeName,
            'seller' => $seller,
            'date' => $dateReformatted,
        ];

        return $base_info;
    }
}
