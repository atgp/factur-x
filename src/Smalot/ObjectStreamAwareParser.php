<?php

/*
 * This file is part of PHP Factur-X library.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Atgp\FacturX\Smalot;

use Smalot\PdfParser\Document;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\PDFObject;

/**
 * Parser locating the objects of an object stream at /First + their offset, as required by ISO 32000-1:2008 7.5.7:
 * "A conforming reader shall rely on the First entry in the stream dictionary to locate the first object".
 *
 * When white-space follows the offsets table, Smalot measures the offsets from the first
 * byte behind that white-space. Every object of the stream is lost if the writer (e.g. Spire.PDF) counted the
 * white-space in the offsets, and the Reader finds no Filespec.
 *
 * The Reader only uses this parser when the parser of Smalot finds no Filespec. This class and that second attempt
 * can be removed once every supported version of smalot/pdfparser finds the Filespec of
 * tests/fixtures/pdf/facturx-objstm-leading-whitespace.pdf by itself.
 *
 * @internal
 */
class ObjectStreamAwareParser extends Parser
{
    /**
     * White-space characters of PDF (ISO 32000-1:2008, 7.2.2) as a PCRE character class.
     */
    private const WHITESPACE = '[\x00\t\n\f\r ]';

    /**
     * Parameters are left untyped to remain compatible with every supported version of smalot/pdfparser.
     *
     * @param string        $id
     * @param array         $structure
     * @param Document|null $document
     */
    protected function parseObject($id, $structure, $document)
    {
        if (null === $document || !$this->parseObjectStream($structure, $document)) {
            parent::parseObject($id, $structure, $document);
        }
    }

    /**
     * @param array $dictionary raw dictionary of a stream: keys and values alternate, each one being [type, value, offset]
     *
     * @return array<int, string>|null object numbers indexed by position in $content, ordered by position;
     *                                 null if the stream is not an object stream or if its /First entry is not reliable
     */
    protected function locateObjects(array $dictionary, string $content): ?array
    {
        if ('ObjStm' !== $this->getEntry($dictionary, 'Type', '/')) {
            return null;
        }

        $length = strlen($content);
        $first = $this->toInteger($this->getEntry($dictionary, 'First', 'numeric'), $length - 1);
        $count = $this->toInteger($this->getEntry($dictionary, 'N', 'numeric'), $length);
        if (null === $first || null === $count) {
            return null;
        }

        // The data starts with N pairs of integers: object number and offset of the object relative to /First
        $integers = preg_split('/'.self::WHITESPACE.'+/', substr($content, 0, $first), -1, \PREG_SPLIT_NO_EMPTY);
        if (0 === $count || count($integers) !== 2 * $count) {
            return null;
        }
        // /First splits the last offset
        if (ctype_digit($content[$first - 1].$content[$first])) {
            return null;
        }

        $objectNumbers = [];
        foreach (array_chunk($integers, 2) as [$objectNumber, $offset]) {
            $offset = $this->toInteger($offset, $length - $first - 1);
            if (null === $offset || !ctype_digit($objectNumber)) {
                return null;
            }
            $objectNumbers[$first + $offset] = $objectNumber;
        }
        ksort($objectNumbers);

        // The first object would start with white-space: the offsets are not relative to /First
        if (1 === preg_match('/\G'.self::WHITESPACE.'/', $content, $matches, 0, array_key_first($objectNumbers))) {
            return null;
        }

        return $objectNumbers;
    }

    /**
     * Registers the objects stored in an object stream.
     *
     * @param array $structure raw object: list of [type, value, offset], a decoded stream having a 4th item [decoded data, remaining filters]
     *
     * @return bool false if the object is left to Smalot
     */
    private function parseObjectStream(array $structure, Document $document): bool
    {
        $dictionary = $content = null;
        foreach ($structure as $part) {
            // An undefined object is ['null', 'null', offset]
            if (!is_array($part)) {
                continue;
            }
            if ('<<' === $part[0]) {
                $dictionary = $part[1];
            } elseif ('stream' === $part[0]) {
                $content = $part[3][0] ?? $part[1];
                break;
            }
        }
        if (null === $dictionary || !is_string($content)) {
            return false;
        }

        $objectNumbers = $this->locateObjects($dictionary, $content);
        if (null === $objectNumbers) {
            return false;
        }

        $positions = array_keys($objectNumbers);
        foreach ($positions as $index => $position) {
            $length = ($positions[$index + 1] ?? strlen($content)) - $position;
            $header = Header::parse(substr($content, $position, $length), $document);

            $this->objects[$objectNumbers[$position].'_0'] = PDFObject::factory($document, $header, '', $this->getConfig());
        }

        return true;
    }

    /**
     * @return string|null value of an entry of a raw dictionary, null if it is missing or of another type
     */
    private function getEntry(array $dictionary, string $key, string $type): ?string
    {
        $value = null;
        for ($i = 1, $count = count($dictionary); $i < $count; $i += 2) {
            if ($key === $dictionary[$i - 1][1]) {
                $value = $type === $dictionary[$i][0] ? $dictionary[$i][1] : null;
            }
        }

        return $value;
    }

    /**
     * @return int|null null unless $digits is an integer from 0 to $max
     */
    private function toInteger(?string $digits, int $max): ?int
    {
        return null !== $digits && ctype_digit($digits) && (float) $digits <= $max ? (int) $digits : null;
    }
}
