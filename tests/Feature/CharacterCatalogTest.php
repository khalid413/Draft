<?php

namespace Tests\Feature;

use App\Game\GameEngine;
use App\Game\GameState;
use App\Models\Card;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CharacterCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_collection_is_frozen_and_survives_resume(): void
    {
        Card::factory()->count(6)->create(['domain' => 'custom', 'series' => 'DC Heroes']);
        Card::factory()->count(6)->create(['domain' => 'custom', 'series' => 'Naruto']);
        $this->postJson('/game/start', ['players' => ['Alex', 'Sam'], 'domain' => 'custom',
            'catalog' => 'DC Heroes', 'rounds' => 3, 'timer' => false])->assertOk()->assertJsonPath('catalog', 'DC Heroes');
        $state = GameState::fromArray(Game::first()->state);
        $this->assertSame(['DC Heroes'], array_values(array_unique(array_column($state->deck, 'series'))));
        Card::where('domain', 'custom')->update(['series' => 'Changed']);
        $this->getJson('/game/state')->assertOk()->assertJsonPath('catalog', 'DC Heroes');
        $engine = app(GameEngine::class);
        $engine->drawRound($state);
        $this->assertSame('DC Heroes', $state->rounds[0]['cards'][1]['series']);
        $this->assertNull($engine->startGame(['Alex', 'Sam'], 'custom', 3)->catalog);
    }

    public function test_insufficient_collection_cannot_borrow_cards_from_another_title(): void
    {
        Card::factory()->count(5)->create(['domain' => 'custom', 'series' => 'DC Heroes']);
        Card::factory()->count(20)->create(['domain' => 'custom', 'series' => 'Naruto']);
        $this->postJson('/game/start', ['players' => ['Alex', 'Sam'], 'domain' => 'custom',
            'catalog' => 'DC Heroes', 'rounds' => 3, 'timer' => false])->assertUnprocessable();
        $this->assertDatabaseCount('games', 0);
    }

    public function test_creator_saves_and_edits_collection_and_legacy_requests_preserve_it(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        Storage::fake('local');
        $card = $this->postJson('/cards', ['name' => 'Batman', 'series' => ' DC Heroes ', 'power' => 600,
            'image' => UploadedFile::fake()->image('batman.png')])->assertCreated()->assertJsonPath('series', 'DC Heroes')->json();
        $this->postJson('/cards/'.$card['id'], ['name' => 'Batman', 'power' => 650])->assertOk()->assertJsonPath('series', 'DC Heroes');
        $this->postJson('/cards/'.$card['id'], ['name' => 'Batman', 'power' => 650, 'series' => 'Gotham'])->assertOk()->assertJsonPath('series', 'Gotham');
        $this->postJson('/cards/'.$card['id'], ['name' => 'Batman', 'power' => 650, 'series' => str_repeat('a', 61)])->assertUnprocessable();
        $this->get('/')->assertOk()->assertSee('Gotham');
    }
}
