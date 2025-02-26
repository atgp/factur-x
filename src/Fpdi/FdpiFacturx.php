<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX\Fpdi;

use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfType;

class FdpiFacturx extends \setasign\Fpdi\Fpdi
{
    public const ICC_PROFILE_PATH = __DIR__.'/icc/sRGB2014.icc';

    protected $files = [];
    protected $metadata_descriptions = [];
    protected $file_spe_dictionnary_index = 0;
    protected $description_index = 0;
    protected $output_intent_index = 0;
    protected $n_files;
    protected $open_attachment_pane = false;
    protected $pdf_metadata_infos = [];

    /**
     * Does this PDF file contains binary data.
     */
    protected bool $containsBinaryData = false;

    public function SetPdfVersion(string $version = '1.3', bool $containsBinaryData = false)
    {
        $this->PDFVersion = sprintf('%.1F', $version);
        $this->containsBinaryData = $containsBinaryData;
    }

    /**
     * Attach file to PDF.
     *
     * @param        $file
     * @param string $name
     * @param string $desc
     * @param string $relationship
     * @param string $mimetype
     * @param bool   $isUTF8
     */
    public function Attach($file, $name = '', $desc = '', $relationship = 'Unspecified', $mimetype = '', $isUTF8 = false)
    {
        if ('' == $name) {
            $p = strrpos($file, '/');
            if (false === $p) {
                $p = strrpos($file, '\\');
            }
            if (false !== $p) {
                $name = substr($file, $p + 1);
            } else {
                $name = $file;
            }
        }
        if (!$isUTF8) {
            $desc = self::utf8_encode($desc);
        }
        if ('' == $mimetype) {
            $mimetype = mime_content_type($file);
            if (!$mimetype) {
                $mimetype = 'application/octet-stream';
            }
        }
        $mimetype = str_replace('/', '#2F', $mimetype);
        $this->files[] = ['file' => $file, 'name' => $name, 'desc' => $desc, 'relationship' => $relationship, 'subtype' => $mimetype];
    }

    /**
     * Open attachment panel on PDF.
     */
    public function OpenAttachmentPane()
    {
        $this->open_attachment_pane = true;
    }

    /**
     * Add metadata description node.
     *
     * @param $description
     */
    public function AddMetadataDescriptionNode($description)
    {
        $this->metadata_descriptions[] = $description;
    }

    /**
     * Set PDF metadata infos.
     *
     * @param array $pdf_metadata_infos
     */
    public function set_pdf_metadata_infos(array $pdf_metadata_infos)
    {
        $this->pdf_metadata_infos = $pdf_metadata_infos;
    }

    protected function _putheader()
    {
        parent::_putheader();

        // ISO 32000-1:2008, Section 7.5.2 (File Header) : If a PDF file contains binary data,
        // the header line shall be immediately followed by a comment line containing at least four binary characters...
        if ($this->containsBinaryData) {
            $this->_put('%'.chr(rand(128, 255)).chr(rand(128, 255)).chr(rand(128, 255)).chr(rand(128, 255)));
        }
    }

    /**
     * Put files.
     *
     * @throws \Exception
     */
    protected function _putfiles()
    {
        foreach ($this->files as $i => &$info) {
            $this->_put_file_specification($info);
            $info['file_index'] = $this->n;
            $this->_put_file_stream($info);
        }

        $this->_put_file_dictionary();
    }

    /**
     * Put file attachment specification.
     *
     * @param array $file_info
     */
    protected function _put_file_specification(array $file_info)
    {
        $this->_newobj();
        $this->file_spe_dictionnary_index = $this->n;
        $this->_put('<<');
        $this->_put('/F ('.$this->_escape($file_info['name']).')');
        $this->_put('/Type /Filespec');
        $this->_put('/UF '.$this->_textstring(self::utf8_encode($file_info['name'])));
        if ($file_info['relationship']) {
            $this->_put('/AFRelationship /'.$file_info['relationship']);
        }
        if ($file_info['desc']) {
            $this->_put('/Desc '.$this->_textstring($file_info['desc']));
        }
        $this->_put('/EF <<');
        $this->_put('/F '.($this->n + 1).' 0 R');
        $this->_put('/UF '.($this->n + 1).' 0 R');
        $this->_put('>>');
        $this->_put('>>');
        $this->_put('endobj');
    }

    /**
     * Put file stream.
     *
     * @param array $file_info
     *
     * @throws \Exception
     */
    protected function _put_file_stream(array $file_info)
    {
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Filter /FlateDecode');
        if ($file_info['subtype']) {
            $this->_put('/Subtype /'.$file_info['subtype']);
        }
        $this->_put('/Type /EmbeddedFile');
        if (is_string($file_info['file']) && @is_file($file_info['file'])) {
            $fc = file_get_contents($file_info['file']);
            $md = @date('YmdHis', filemtime($file_info['file']));
        } else {
            $stream = $file_info['file']->getStream();
            \fseek($stream, 0);
            $fc = stream_get_contents($stream);
            $md = @date('YmdHis');
        }
        if (false === $fc) {
            $this->Error('Cannot open file: '.$file_info['file']);
        }

        $fc = gzcompress($fc);
        $this->_put('/Length '.strlen($fc));
        $this->_put("/Params <</ModDate (D:$md)>>");
        $this->_put('>>');
        $this->_putstream($fc);
        $this->_put('endobj');
    }

    /**
     * Put file dictionnary.
     */
    protected function _put_file_dictionary()
    {
        $this->_newobj();
        $this->n_files = $this->n;
        $this->_put('<<');
        $s = '';
        $files = $this->files;
        usort($files, function ($a, $b) { // Sorting files in name order as PDF specs (if not, issue with Acrobat Reader when trying to download attachments)
            return strcmp($a['name'], $b['name']);
        });
        foreach ($files as $info) {
            $s .= sprintf('%s %s 0 R ', $this->_textstring($info['name']), $info['file_index']);
        }
        $this->_put(sprintf('/Names [%s]', $s));
        $this->_put('>>');
        $this->_put('endobj');
    }

    /**
     * Put metadata descriptions.
     */
    protected function _put_metadata_descriptions()
    {
        $s = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'."\n";
        $s .= '<x:xmpmeta xmlns:x="adobe:ns:meta/">'."\n";
        $s .= '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n";
        $this->_newobj();
        $this->description_index = $this->n;
        foreach ($this->metadata_descriptions as $i => $desc) {
            $s .= $desc."\n";
        }
        $s .= '</rdf:RDF>'."\n";
        $s .= '</x:xmpmeta>'."\n";
        $s .= '<?xpacket end="w"?>';
        $this->_put('<<');
        $this->_put('/Length '.strlen($s));
        $this->_put('/Type /Metadata');
        $this->_put('/Subtype /XML');
        $this->_put('>>');
        $this->_putstream($s);
        $this->_put('endobj');
    }

    /**
     * Put resources including files and metadata descriptions.
     *
     * @throws \Exception
     */
    protected function _putresources()
    {
        parent::_putresources();
        if (!empty($this->files)) {
            $this->_putfiles();
        }
        $this->_putoutputintent();
        if (!empty($this->metadata_descriptions)) {
            $this->_put_metadata_descriptions();
        }
    }

    /**
     * Put output intent with ICC profile.
     */
    protected function _putoutputintent()
    {
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Type /OutputIntent');
        $this->_put('/S /GTS_PDFA1');
        $this->_put('/OuputCondition (sRGB)');
        $this->_put('/OutputConditionIdentifier (Custom)');
        $this->_put('/DestOutputProfile '.($this->n + 1).' 0 R');
        $this->_put('/Info (sRGB V4 ICC)');
        $this->_put('>>');
        $this->_put('endobj');
        $this->output_intent_index = $this->n;

        $icc = file_get_contents($this::ICC_PROFILE_PATH);
        $icc = gzcompress($icc);
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Length '.strlen($icc));
        $this->_put('/N 3');
        $this->_put('/Filter /FlateDecode');
        $this->_put('>>');
        $this->_putstream($icc);
        $this->_put('endobj');
    }

    /**
     * Put catalog node, including associated files.
     */
    protected function _putcatalog()
    {
        parent::_putcatalog();
        if (!empty($this->files)) {
            if (is_array($this->files)) {
                $files_ref_str = '';
                foreach ($this->files as $file) {
                    if ('' != $files_ref_str) {
                        $files_ref_str .= ' ';
                    }
                    $files_ref_str .= sprintf('%s 0 R', $file['file_index']);
                }
                $this->_put(sprintf('/AF [%s]', $files_ref_str));
            } else {
                $this->_put(sprintf('/AF %s 0 R', $this->n_files));
            }
            if (0 != $this->description_index) {
                $this->_put(sprintf('/Metadata %s 0 R', $this->description_index));
            }
            $this->_put('/Names <<');
            $this->_put('/EmbeddedFiles ');
            $this->_put(sprintf('%s 0 R', $this->n_files));
            $this->_put('>>');
        }
        if (0 != $this->output_intent_index) {
            $this->_put(sprintf('/OutputIntents [%s 0 R]', $this->output_intent_index));
        }
        if ($this->open_attachment_pane) {
            $this->_put('/PageMode /UseAttachments');
        }
    }

    /**
     * Put trailer including ID.
     */
    protected function _puttrailer()
    {
        parent::_puttrailer();
        $created_id = md5($this->_generate_metadata_string('created'));
        $modified_id = md5($this->_generate_metadata_string('modified'));
        $this->_put(sprintf('/ID [<%s><%s>]', $created_id, $modified_id));
    }

    /**
     * Generate metadata string.
     *
     * @param string $date_type
     *
     * @return string
     */
    protected function _generate_metadata_string($date_type = 'created')
    {
        $metadata_string = '';
        if (isset($this->pdf_metadata_infos['title'])) {
            $metadata_string .= $this->pdf_metadata_infos['title'];
        }
        if (isset($this->pdf_metadata_infos['subject'])) {
            $metadata_string .= $this->pdf_metadata_infos['subject'];
        }
        switch ($date_type) {
            case 'modified':
                if (isset($this->pdf_metadata_infos['modifiedDate'])) {
                    $metadata_string .= $this->pdf_metadata_infos['modifiedDate'];
                }
                break;
            case 'created':
            default:
                if (isset($this->pdf_metadata_infos['createdDate'])) {
                    $metadata_string .= $this->pdf_metadata_infos['createdDate'];
                }
                break;
        }

        return $metadata_string;
    }

    /**
     * Replacement for utf8_encode which is deprecated since PHP 8.2.
     *
     * @param string $s
     *
     * @return string
     */
    protected static function utf8_encode($s)
    {
        if (\PHP_VERSION_ID < 80200) {
            return utf8_encode($s);
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }

        if (class_exists('UConverter')) {
            return \UConverter::transcode($s, 'UTF8', 'ISO-8859-1');
        }

        if (function_exists('iconv')) {
            return iconv('ISO-8859-1', 'UTF-8', $s);
        }

        /*
         * Fallback to the pure PHP implementation from Symfony Polyfill for PHP 7.2
         *
         * @see https://github.com/symfony/polyfill-php72/blob/v1.26.0/Php72.php
         */
        $s .= $s;
        $len = \strlen($s);

        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80": $s[$j] = $s[$i];
                    break;
                case $s[$i] < "\xC0": $s[$j] = "\xC2";
                    $s[++$j] = $s[$i];
                    break;
                default: $s[$j] = "\xC3";
                    $s[++$j] = \chr(\ord($s[$i]) - 64);
                    break;
            }
        }

        return substr($s, 0, $j);
    }

    /**
     * {@inheritDoc}
     */
    protected function writePdfType(PdfType $value)
    {
        parent::writePdfType($value);

        if ($value instanceof PdfIndirectObject && \PHP_EOL !== substr($this->buffer, -1)) {
            $this->_put('');
        }
    }
}
