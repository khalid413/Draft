<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_library_offers_the_language_switcher(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee(['name="locale"', 'value="fr"', 'value="ar"', 'العربية', 'Français'], false);
        // Only the library page carries the switcher.
        $this->get('/draft')->assertOk()->assertDontSee('name="locale"', false);
    }

    public function test_choosing_a_language_saves_it_in_a_cookie(): void
    {
        $this->post('/locale', ['locale' => 'ar'])->assertRedirect('/')->assertCookie('glisa_locale', 'ar');
        $this->post('/locale', ['locale' => 'de'])->assertSessionHasErrors('locale');
    }

    public function test_visitor_pages_follow_the_saved_language(): void
    {
        $this->withCookie('glisa_locale', 'fr')->get('/')->assertSee('<html lang="fr" dir="ltr">', false)->assertSee('Choisissez');
        $this->withCookie('glisa_locale', 'ar')->get('/carta')->assertSee('<html lang="ar" dir="rtl">', false)->assertSee('الكارطا المغربية');
        // Same-origin fetches carry the cookie, so server messages are translated too.
        $this->withCredentials()->withCookie('glisa_locale', 'ar')->postJson('/find-table', ['code' => 'NOPE0000'])->assertNotFound()
            ->assertJsonFragment(['message' => 'لا توجد طاولة مفتوحة بهذا الرمز.']);
    }

    public function test_the_browser_language_is_the_first_guess(): void
    {
        $this->get('/', ['Accept-Language' => 'fr-FR,fr;q=0.9'])->assertSee('<html lang="fr"', false);
    }

    public function test_the_admin_area_stays_english(): void
    {
        $this->withCookie('glisa_locale', 'ar')->get('/admin/login')->assertOk()->assertDontSee('dir="rtl"', false);
    }
}
