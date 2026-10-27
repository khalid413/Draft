<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Every visitor-facing sentence wrapped in __(), t() or tn() must exist in French and Arabic. */
class TranslationsTest extends TestCase
{
    // Built from variables at runtime, so the scanner cannot see them.
    private const DYNAMIC_KEYS = ['Coins', 'Swords', 'Cups', 'Clubs', 'Connecting…', 'Live', 'Reconnecting…', 'Connection interrupted'];

    // The admin area stays English by design.
    private const ADMIN_ONLY = ['views/admin/', 'views/partials/admin-header', 'views/partials/card-library', 'Console/', 'AdminRoomController', 'AdminController', 'CardController', 'SongController', 'SaveCardRequest'];

    public static function keys(): array
    {
        $root = dirname(__DIR__, 2);
        $keys = self::DYNAMIC_KEYS;
        foreach (['resources/views', 'resources/js', 'app'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir")) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if (! preg_match('/\.(php|js)$/', $path) || array_filter(self::ADMIN_ONLY, fn ($skip) => str_contains($path, $skip))) {
                    continue;
                }
                preg_match_all("/(?<![\\w.])(?:__|tn?)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($path), $matches);
                foreach ($matches[1] as $key) {
                    $keys[] = stripcslashes($key);
                }
            }
        }

        return array_values(array_unique($keys));
    }

    public function test_french_and_arabic_cover_every_visitor_sentence(): void
    {
        $keys = self::keys();
        $this->assertGreaterThan(200, count($keys), 'The scanner should find the wrapped sentences.');
        foreach (['fr', 'ar'] as $locale) {
            $strings = json_decode(file_get_contents(dirname(__DIR__, 2)."/lang/$locale.json"), true, flags: JSON_THROW_ON_ERROR);
            $missing = array_values(array_filter($keys, fn ($key) => ! array_key_exists($key, $strings)));
            $this->assertSame([], $missing, "Missing $locale translations");
            foreach ($keys as $key) {
                // Placeholders like :name must survive translation, and plural keys keep their forms.
                preg_match_all('/:[a-z]+/', $key, $placeholders);
                foreach (array_unique($placeholders[0]) as $placeholder) {
                    $this->assertStringContainsString($placeholder, $strings[$key], "$locale: \"$key\" lost $placeholder");
                }
                if (str_contains($key, '|')) {
                    $this->assertStringContainsString('|', $strings[$key], "$locale: \"$key\" needs plural forms");
                }
            }
        }
    }
}
