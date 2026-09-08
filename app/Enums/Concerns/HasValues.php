<?php

namespace App\Enums\Concerns;

/**
 * Exposes an enum's backing values as a plain string array.
 *
 * Schema builders need raw strings: Blueprint::enum() interpolates each allowed
 * value directly into the generated SQL, so passing cases() would fatal.
 */
trait HasValues
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
