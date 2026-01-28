<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX;

use Atgp\FacturX\Exceptions\Writer\InvalidAttachmentException;
use Atgp\FacturX\Exceptions\Writer\InvalidProfileException;
use Atgp\FacturX\Exceptions\Writer\InvalidRelationshipException;
use Atgp\FacturX\Exceptions\Writer\WriterExceptionInterface;
use Atgp\FacturX\Exceptions\XsdValidator\XsdValidatorExceptionInterface;
use Atgp\FacturX\Fpdi\FdpiFacturx;
use Atgp\FacturX\Utils\Exception\ProfileResolutionException;
use Atgp\FacturX\Utils\ProfileHandler;

class Writer
{
    public const VERSION = '2.0';
    public const ENCODING = 'UTF-8';

    public const LOGOS = [
        ProfileHandler::PROFILE_FACTURX_MINIMUM => 'Factur-x_minimum.jpg',
        ProfileHandler::PROFILE_FACTURX_BASICWL => 'Factur-x_basic_wl.jpg',
        ProfileHandler::PROFILE_FACTURX_BASIC => 'Factur-x_basic.jpg',
        ProfileHandler::PROFILE_FACTURX_EN16931 => 'Factur-x_en16931.jpg',
        ProfileHandler::PROFILE_FACTURX_EXTENDED => 'Factur-x_extended.jpg',
    ];
    public const XMP_CONFORMANCE_LEVELS = [
        ProfileHandler::PROFILE_FACTURX_MINIMUM => 'MINIMUM',
        ProfileHandler::PROFILE_FACTURX_BASICWL => 'BASIC WL',
        ProfileHandler::PROFILE_FACTURX_BASIC => 'BASIC',
        ProfileHandler::PROFILE_FACTURX_EN16931 => 'EN 16931',
        ProfileHandler::PROFILE_FACTURX_EXTENDED => 'EXTENDED',
    ];
    public const XML_FILENAME = 'Factur-X_extension_schema.xmp';
    private const CREDIT_NOTE_TYPES = [
        '81', // Credit note related to goods or services
        '83', // Credit note related to financial adjustments
        '261', // Self-billed credit note
        '262', // Consolidated credit note - goods and services
        '296', // Credit note for price variation
        '308', // Delcredere credit note
        '381', // Credit note
        '396', // Factored credit note
        '420', // Optical Character Reading (OCR) payment credit note
        '458', // Reversal of credit
        '502', // Self-billed factored Credit Note, Credit note type, Corrected
        '503', // Prepayment credit note, credit note type, Corrected
        '532', // Forwarder's credit note
    ];

    protected ?string $profile = null;

    protected bool $importExternalLinks = true;

    public function __construct(bool $importExternalLinks = true)
    {
        $this->importExternalLinks = $importExternalLinks;
    }

    /**
     * Generates Factur-X PDF from PDF invoice and Factur-X XML.
     *
     * @param string      $pdfInvoice            Content of the PDF invoice
     * @param string      $xml                   Content of the XML invoice
     * @param string|null $profile               One of \Atgp\FacturX\Facturx::PROFIL_* (null for auto-detection)
     * @param bool        $validateXSD           check Factur-X XML against official XSD
     * @param bool        $addLogo               Add Factur-X logo on PDF first page according to Factur-X profile
     * @param mixed       $additionalAttachments
     * @param string      $relationship          the embarkation relationship, must be Data|Source|Alternative
     *
     * @throws WriterExceptionInterface
     * @throws XsdValidatorExceptionInterface
     * @throws \setasign\Fpdi\PdfParser\PdfParserException
     * @throws \setasign\Fpdi\PdfReader\PdfReaderException
     *
     * @return string
     */
    public function generate(string $pdfInvoice, string $xml, ?string $profile = null, bool $validateXSD = true,
        array $additionalAttachments = [], bool $addLogo = false, string $relationship = 'Data'
    ): string {
        $pdfInvoiceRef = \setasign\Fpdi\PdfParser\StreamReader::createByString($pdfInvoice);

        if ('<?xml' != substr($xml, 0, 5)) { // Add XML tags
            $xml = "<?xml version='1.0' encoding='".static::ENCODING."' ?>\n".$xml;
        }
        $facturxXmlRef = \setasign\Fpdi\PdfParser\StreamReader::createByString($xml);

        $docFacturx = new \DOMDocument();
        $docFacturx->loadXML($xml);

        $this->profile = $profile;
        if (null === $this->profile) {
            try {
                $this->profile = ProfileHandler::get($docFacturx);
            } catch (ProfileResolutionException $e) {
                throw new InvalidProfileException($e->getMessage(), $e->getCode(), $e);
            }
        }
        if (!ProfileHandler::has($this->profile)) {
            throw new InvalidProfileException("Unexpected profile '$profile' for Factur-X invoice.");
        }

        if ($validateXSD) {
            $validator = new XsdValidator();
            $validator->validateWithException($xml, $this->profile);
        }

        $pdfWriter = new FdpiFacturx();
        $pageCount = $pdfWriter->setSourceFile($pdfInvoiceRef);
        for ($i = 1; $i <= $pageCount; ++$i) {
            $tplIdx = $pdfWriter->importPage($i, '/MediaBox', $groupXObject = true, $this->importExternalLinks);
            $pdfWriter->AddPage();
            $pdfWriter->useTemplate($tplIdx, 0, 0, null, null, true);

            // add Factur-X logo only on the first page
            if ($addLogo && 1 == $i) {
                $pdfWriter->Image(__DIR__.'/../img/'.static::LOGOS[$this->profile], 197, 2.5, 7);
            }
        }
        if (!in_array($relationship, ['Data', 'Source', 'Alternative'])) {
            throw new InvalidRelationshipException('$relationship argument must be one of the values "Data", "Source", "Alternative".');
        }
        $pdfWriter->Attach($facturxXmlRef, Reader::FACTURX_FILENAME, 'Factur-X Invoice', $relationship, 'text#2Fxml');
        foreach ($additionalAttachments as $attachment) {
            if (@is_file($attachment['path'])) {
                $attachment_file_ref = $attachment['path'];
            } elseif (is_string($attachment['path'])) {
                $attachment_file_ref = sys_get_temp_dir().'/'.$attachment['name'];
                file_put_contents($attachment_file_ref, $attachment['path']); // creating tmp file to solve mime_content_type errors
            } else {
                throw new InvalidAttachmentException('$attachment_file argument must be a string or a file');
            }
            $pdfWriter->Attach($attachment_file_ref, $attachment['name'], $attachment['desc']);
        }
        $pdfWriter->OpenAttachmentPane();
        $pdfWriter->SetPDFVersion('1.7', true); // version 1.7 according to PDF/A-3 ISO 32000-1
        $this->updatePdfMetadata($pdfWriter, $docFacturx);

        return $pdfWriter->Output('S');
    }

    /**
     * Returns used profile for export.
     */
    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function doesImportExternalLinks(): bool
    {
        return $this->importExternalLinks;
    }

    public function setImportExternalLinks(bool $importExternalLinks): self
    {
        $this->importExternalLinks = $importExternalLinks;

        return $this;
    }

    /**
     * Updates PDF metadata to according to Factur-X XML data.
     *
     * @param FdpiFacturx  &$pdfWriter
     * @param \DOMDocument $document
     */
    protected function updatePdfMetadata(FdpiFacturx &$pdfWriter, \DOMDocument $document)
    {
        $pdf_metadata_infos = $this->preparePdfMetadata($document);
        $pdfWriter->set_pdf_metadata_infos($pdf_metadata_infos);

        $xmp = simplexml_load_file(__DIR__.'/../xmp/'.static::XML_FILENAME);
        $description_nodes = $xmp->xpath('rdf:Description');

        $desc_fx = $description_nodes[0];
        $desc_fx->children('fx', true)->ConformanceLevel = strtoupper(static::XMP_CONFORMANCE_LEVELS[$this->profile]);
        $pdfWriter->AddMetadataDescriptionNode($desc_fx->asXML());

        $pdfWriter->AddMetadataDescriptionNode($description_nodes[1]->asXML());

        $descPdfaid = $description_nodes[2];
        $pdfWriter->AddMetadataDescriptionNode($descPdfaid->asXML());

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
    }

    /**
     * Prepares PDF Metadata informations from Factur-X XML.
     *
     * @param \DOMDocument $document
     *
     * @return array
     */
    protected function preparePdfMetadata(\DOMDocument $document): array
    {
        $invoiceInformations = $this->extractInvoiceInformations($document);
        $dateString = date('Y-m-d', strtotime($invoiceInformations['date']));
        $title = sprintf('%s : %s %s', $invoiceInformations['seller'], $invoiceInformations['docTypeName'], $invoiceInformations['invoiceId']);
        $subject = sprintf('Factur-X %s %s dated %s issued by %s',
            $invoiceInformations['docTypeName'],
            $invoiceInformations['invoiceId'],
            $dateString,
            $invoiceInformations['seller']
        );

        $pdfMetadata = [
            'author' => $invoiceInformations['seller'],
            'keywords' => sprintf('%s, Factur-X', $invoiceInformations['docTypeName']),
            'title' => $title,
            'subject' => $subject,
            'createdDate' => $invoiceInformations['date'],
            'modifiedDate' => date('Y-m-d\TH:i:s').'+00:00',
        ];

        return $pdfMetadata;
    }

    /**
     * Extracts major invoice information from Factur-X XML.
     *
     * @param \DOMDocument $document
     *
     * @return array
     */
    protected function extractInvoiceInformations(\DOMDocument $document): array
    {
        $xpath = new \DOMXPath($document);
        $dateXpath = $xpath->query('//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString');
        $date = $dateXpath->item(0)->nodeValue;
        $dateReformatted = date('Y-m-d\TH:i:s', strtotime($date)).'+00:00';
        $invoiceIdXpath = $xpath->query('//rsm:ExchangedDocument/ram:ID');
        $invoiceId = $invoiceIdXpath->item(0)->nodeValue;
        $sellerXpath = $xpath->query('//ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:Name');
        $seller = $sellerXpath->item(0)->nodeValue;
        $docTypeXpath = $xpath->query('//rsm:ExchangedDocument/ram:TypeCode');
        $docType = $docTypeXpath->item(0)->nodeValue;
        $docTypeName = in_array($docType, self::CREDIT_NOTE_TYPES, true)
            ? 'Credit note'
            : 'Invoice';

        return [
            'invoiceId' => $invoiceId,
            'docTypeName' => $docTypeName,
            'seller' => $seller,
            'date' => $dateReformatted,
        ];
    }
}
