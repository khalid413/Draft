<?php

namespace App\Support;

/** Loads lang/{locale}.json for the browser; English needs nothing because its keys are the English text. */
final class Translations
{
    private static array $cache = [];

    public static function forBrowser(string $locale): object
    {
        if ($locale === 'en') {
            return (object) [];
        }

        return self::$cache[$locale] ??= (object) (json_decode((string) @file_get_contents(lang_path($locale.'.json')), true) ?: []);
    }
}
