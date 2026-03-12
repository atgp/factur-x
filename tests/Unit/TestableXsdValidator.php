<?php

namespace Atgp\FacturX\Tests\Unit;

use Atgp\FacturX\XsdValidator;

/**
 * Test-only subclass exposing XsdValidator's protected methods as public.
 */
class TestableXsdValidator extends XsdValidator
{
    public static function publicGetXsd(string $profile): string
    {
        return static::getXsd($profile);
    }
}
