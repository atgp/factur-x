<?php

namespace Atgp\FacturX\Tests\Unit\Smalot;

use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\PDFObject;

/**
 * The decoded data of an object stream is an offsets table (pairs "object number, offset") followed by the objects.
 * In these tests an object is a dictionary with a single entry: <</V /A>> is 9 bytes long.
 */
class ObjectStreamAwareParserTest extends TestCase
{
    /**
     * The offsets table is 15 bytes long, a space follows (byte 15) and the objects start at bytes 16, 25 and 34,
     * without any white-space between them.
     */
    private const PACKED_OBJECTS = '11 0 12 9 13 18 <</V /A>><</V /B>><</V /C>>';

    /**
     * @dataProvider objectStreamProvider
     */
    public function testLocateObjects(string $content, ?string $n, ?string $first, ?array $expected): void
    {
        $parser = new TestableObjectStreamAwareParser();

        self::assertSame($expected, $parser->publicLocateObjects(self::dictionary($n, $first), $content));
    }

    /**
     * Content of the stream, /N, /First and the expected result: object numbers indexed by position in the content,
     * or null if the stream is left to Smalot.
     *
     * @return array<string, array{string, string|null, string|null, array<int, string>|null}>
     */
    public static function objectStreamProvider(): array
    {
        return [
            // Spire.PDF: /First (11) points to the line break and the offsets are relative to /First.
            // 11 + 2 = 13 and 11 + 12 = 23 are the first bytes of the objects.
            'white-space counted in the offsets' => ["11 2 12 12 \r\n<</V /A>> <</V /B>>", '2', '11', [13 => '11', 23 => '12']],
            '/First is the offset of the first object' => [self::PACKED_OBJECTS, '3', '16', [16 => '11', 25 => '12', 34 => '13']],
            'objects not sorted by offset in the table' => ['11 10 12 0 <</V /B>> <</V /A>>', '2', '11', [11 => '12', 21 => '11']],
            'two objects share an offset: the last one wins' => ['11 0 12 0 13 10 <</V /A>> <</V /C>>', '3', '16', [16 => '12', 26 => '13']],
            // 42 and 43 are objects, not a pair of the table: /First (15) tells them apart
            'integer objects behind the table' => ['11 0 12 3 13 6 42 43 <</V /C>>', '3', '15', [15 => '11', 18 => '12', 21 => '13']],

            // /First (15) points to the space but the offsets are relative to the first object (16):
            // 15 + 0 is white-space, so the offsets cannot be relative to /First
            'white-space not counted in the offsets' => [self::PACKED_OBJECTS, '3', '15', null],
            '/First inside the last offset' => [self::PACKED_OBJECTS, '3', '14', null],
            '/First inside the first object' => [self::PACKED_OBJECTS, '3', '18', null],
            '/First behind the data' => [self::PACKED_OBJECTS, '3', '43', null],
            '/First not an integer' => [self::PACKED_OBJECTS, '3', '16.0', null],
            '/First missing' => [self::PACKED_OBJECTS, '3', null, null],
            '/N differs from the number of pairs in front of /First' => [self::PACKED_OBJECTS, '3', '10', null],
            '/N missing' => [self::PACKED_OBJECTS, null, '16', null],
            '/N is 0' => ['<</V /A>>', '0', '0', null],
            'offset behind the data' => ['11 0 12 999 13 10 <</V /A>> <</V /C>>', '3', '18', null],
            'object number not an integer' => ['11 0 x 9 13 18 <</V /A>><</V /B>><</V /C>>', '3', '15', null],
        ];
    }

    public function testLocateObjectsIgnoresStreamOfAnotherType(): void
    {
        $parser = new TestableObjectStreamAwareParser();

        self::assertNull($parser->publicLocateObjects(self::dictionary('3', '16', 'XRef'), self::PACKED_OBJECTS));
    }

    /**
     * Smalot alone reads both objects 2 bytes too far and registers them without any entry.
     */
    public function testParseObjectRegistersObjectsLocatedWithFirst(): void
    {
        $parser = new TestableObjectStreamAwareParser();

        $objects = $parser->publicParseObject('8_0', self::structure("11 2 12 12 \r\n<</V /A>> <</V /B>>", '2', '11'));

        self::assertSame(['11_0' => 'A', '12_0' => 'B'], self::values($objects));
    }

    public function testParseObjectLeavesUnreliableFirstToSmalot(): void
    {
        $parser = new TestableObjectStreamAwareParser();

        $objects = $parser->publicParseObject('8_0', self::structure(self::PACKED_OBJECTS, '3', '15'));

        self::assertSame(['11_0' => 'A', '12_0' => 'B', '13_0' => 'C'], self::values($objects));
    }

    public function testParseObjectLeavesObjectWithoutStreamToSmalot(): void
    {
        $parser = new TestableObjectStreamAwareParser();

        $objects = $parser->publicParseObject('1_0', [['<<', [['/', 'V', 0], ['/', 'A', 0]]]]);

        self::assertSame(['1_0' => 'A'], self::values($objects));
    }

    /**
     * Raw dictionary of a stream, as built by the raw data parser of Smalot: keys and values alternate,
     * each one being [type, value, offset].
     */
    private static function dictionary(?string $n, ?string $first, string $type = 'ObjStm'): array
    {
        $dictionary = [['/', 'Type', 0], ['/', $type, 0]];
        if (null !== $n) {
            array_push($dictionary, ['/', 'N', 0], ['numeric', $n, 0]);
        }
        if (null !== $first) {
            array_push($dictionary, ['/', 'First', 0], ['numeric', $first, 0]);
        }

        return $dictionary;
    }

    /**
     * Raw object stream, as built by the raw data parser of Smalot.
     */
    private static function structure(string $content, string $n, string $first): array
    {
        return [['<<', self::dictionary($n, $first)], ['stream', $content]];
    }

    /**
     * @param array<string, PDFObject> $objects
     *
     * @return array<string, string|null> value of the entry /V of each object, null if the object has none
     */
    private static function values(array $objects): array
    {
        $values = [];
        foreach ($objects as $id => $object) {
            $values[$id] = $object->has('V') ? $object->get('V')->getContent() : null;
        }

        return $values;
    }
}
