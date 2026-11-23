<?php

namespace Atgp\FacturX\Tests\Unit\Smalot;

use Atgp\FacturX\Smalot\ObjectStreamAwareParser;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\PDFObject;

/**
 * Test-only subclass exposing ObjectStreamAwareParser's protected methods as public.
 */
class TestableObjectStreamAwareParser extends ObjectStreamAwareParser
{
    public function publicLocateObjects(array $dictionary, string $content): ?array
    {
        return $this->locateObjects($dictionary, $content);
    }

    /**
     * @return array<string, PDFObject> objects registered by the parser, indexed by "<object number>_<generation number>"
     */
    public function publicParseObject(string $id, array $structure): array
    {
        $this->parseObject($id, $structure, new Document());

        return $this->objects;
    }
}
