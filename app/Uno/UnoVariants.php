<?php

namespace App\Uno;

use App\Uno\Classic\ClassicVariant;
use InvalidArgumentException;

/** The UNO family shown on the variant page. Only variants with a class here can open tables. */
final class UnoVariants
{
    /** In display order; null means "coming soon". */
    public const CATALOG = [
        'classic' => ClassicVariant::class,
        'flip' => null,
        'no-mercy' => null,
        'all-wild' => null,
    ];

    public static function available(): array
    {
        return array_keys(array_filter(self::CATALOG));
    }

    public static function isAvailable(?string $key): bool
    {
        return isset(self::CATALOG[$key]);
    }

    public static function get(string $key): UnoVariant
    {
        if (! self::isAvailable($key)) {
            throw new InvalidArgumentException('Unknown UNO variant.');
        }

        return app(self::CATALOG[$key]);
    }
}
