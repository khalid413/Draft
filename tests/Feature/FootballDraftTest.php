<?php

namespace Tests\Feature;

use App\Domains\FootballDomain;
use App\Game\GameEngine;
use App\Game\GameState;
use App\Models\Card;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FootballDraftTest extends TestCase
{
    use RefreshDatabase;

    private function pool(): void
    {
        foreach (FootballDomain::REQUIREMENTS as $position => $count) {
            Card::factory()->count($count)->create(['domain' => 'football', 'position' => $position, 'power' => 90, 'is_icon' => $position === 'GK']);
        }
    }

    private function settings(): array
    {
        return ['players' => ['Alex', 'Sam'], 'domain' => 'football', 'rounds' => 11, 'timer' => false];
    }

    public function test_complete_position_draft_builds_two_unique_433_teams_and_keeps_icons_private(): void
    {
        $this->pool();
        Card::factory()->count(5)->create(['domain' => 'custom', 'power' => 1000]);
        $state = $this->postJson('/game/start', $this->settings())->assertOk()->assertJsonPath('roundLimit', 11)->assertJsonMissingPath('deck')->json();
        $formation = (new FootballDomain)->slots();
        $snapshot = Game::first()->state;
        // Subsequent library edits cannot change the slot, rating or Icon flag in this game.
        Card::whereKey($snapshot['deck'][1]['id'])->update(['position' => 'ST', 'power' => 1, 'is_icon' => false]);
        for ($round = 0; $round < 11; $round++) {
            $this->assertSame($formation[$round], $state['activeSlot']);
            $this->assertSame($round % 2, $state['activePlayer']);
            $state = $this->postJson('/game/ready', ['version' => $state['version']])->assertOk()->json();
            $this->assertSame($formation[$round]['position'], $state['cards'][0]['position']);
            $this->assertSame(['selectionId', 'hidden'], array_keys($state['cards'][1]));
            $this->assertSame(90, $state['cards'][0]['power']);
            $state = $this->postJson('/game/pick', ['version' => $state['version'], 'playerIndex' => $state['activePlayer'], 'cardId' => $state['cards'][1]['selectionId']])->assertOk()->json();
            $this->assertArrayNotHasKey('is_icon', $state['cards'][1]);
            $state = $this->postJson('/game/reveal', ['version' => $state['version']])->assertOk()->json();
            $this->assertSame($formation[$round]['position'], $state['cards'][1]['position']);
            $this->assertSame($round === 0, $state['cards'][1]['is_icon']);
            if ($round === 0) {
                $this->getJson('/game/state')->assertJsonPath('phase', 'revealing')->assertJsonPath('cards.1.is_icon', true);
                $this->postJson('/game/finish', ['version' => $state['version']])->assertUnprocessable();
            }
            $state = $this->postJson('/game/'.($round === 10 ? 'finish' : 'next'), ['version' => $state['version']])->assertOk()->json();
        }
        $ids = [];
        foreach ($state['players'] as $index => $player) {
            $this->assertCount(11, $player['cards']);
            $this->assertSame(array_column($formation, 'key'), array_column($player['cards'], 'slot'));
            $this->assertSame(['GK' => 1, 'LB' => 1, 'CB' => 2, 'RB' => 1, 'CM' => 3, 'LW' => 1, 'ST' => 1, 'RW' => 1], array_count_values(array_column($player['cards'], 'position')));
            $this->assertSame(990, $state['results']['scores'][$index]['total']);
            $this->assertSame(0, $state['results']['scores'][$index]['bonus']);
            $this->assertEquals(90, $state['results']['scores'][$index]['ovr']);
            $ids = array_merge($ids, array_column($player['cards'], 'id'));
        }
        $this->assertCount(22, array_unique($ids));
        $this->assertSame($state['results']['match']['winners'], $state['results']['winners']);
        $this->assertSame(15000, $state['results']['match']['durationMs']);
        $this->getJson('/game/state')->assertOk()->assertJsonPath('results.match', $state['results']['match']);
        $this->postJson('/game/finish', ['version' => $state['version']])->assertUnprocessable();
        $this->assertEquals(Game::first()->state, GameState::fromArray(Game::first()->state)->jsonSerialize());
    }

    public function test_incomplete_position_pool_is_rejected_even_when_total_count_is_large(): void
    {
        Card::factory()->count(30)->create(['domain' => 'football', 'position' => 'GK', 'power' => 80]);
        $this->postJson('/game/start', $this->settings())->assertUnprocessable()->assertJsonValidationErrors('domain');
        $this->assertDatabaseCount('games', 0);
        $this->expectException(\DomainException::class);
        app(GameEngine::class)->startGame(['Alex', 'Sam'], 'football', 11);
    }

    public function test_football_always_uses_eleven_picks_and_honors_unique_positions(): void
    {
        $this->pool();
        $this->postJson('/game/start', array_replace($this->settings(), ['rounds' => 3]))->assertUnprocessable();
        $state = app(GameEngine::class)->startGame(['Alex', 'Sam'], 'football', 3);
        $this->assertSame(11, $state->roundLimit);
        $this->assertCount(22, $state->deck);
    }

    public function test_separate_creation_validates_anime_1000_football_100_position_and_icon(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        Storage::fake('local');
        $anime = $this->postJson('/cards', ['name' => 'Shared name', 'power' => 1000, 'image' => UploadedFile::fake()->image('anime.jpg')])->assertCreated()->assertJsonPath('power', 1000)->json();
        $football = $this->postJson('/football/cards', ['name' => 'Shared name', 'ovr' => 100, 'position' => 'GK', 'is_icon' => true, 'image' => UploadedFile::fake()->image('football.jpg')])->assertCreated()->assertJsonPath('power', 100)->assertJsonPath('position', 'GK')->assertJsonPath('is_icon', true)->json();
        $this->getJson('/cards')->assertJsonCount(1)->assertJsonPath('0.id', $anime['id']);
        $this->getJson('/football/cards')->assertJsonCount(1)->assertJsonPath('0.id', $football['id']);
        $this->postJson('/cards/'.$football['id'], ['name' => 'Wrong deck', 'power' => 500])->assertNotFound();
        $this->postJson('/football/cards/'.$anime['id'], ['name' => 'Wrong deck', 'ovr' => 80, 'position' => 'ST', 'is_icon' => false])->assertNotFound();
        $this->postJson('/football/cards/'.$football['id'], ['name' => 'Shared name', 'ovr' => 101, 'position' => 'GK', 'is_icon' => false])->assertUnprocessable()->assertJsonValidationErrors('ovr');
        $this->postJson('/football/cards/'.$football['id'], ['name' => 'Shared name', 'ovr' => 80, 'position' => 'ANY', 'is_icon' => false])->assertUnprocessable()->assertJsonValidationErrors('position');
        $this->postJson('/football/cards/'.$football['id'], ['name' => 'Shared name', 'ovr' => 80, 'is_icon' => false])->assertUnprocessable()->assertJsonValidationErrors('position');
        $this->postJson('/football/cards/'.$football['id'], ['name' => 'Shared name', 'ovr' => 80, 'position' => 'ST', 'is_icon' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('is_icon');
        $this->postJson('/football/cards/'.$football['id'], ['name' => 'Shared name', 'ovr' => 80, 'position' => 'ST', 'is_icon' => false])->assertOk()->assertJsonPath('is_icon', false)->assertJsonPath('position', 'ST');
        $this->postJson('/cards/'.$anime['id'], ['name' => 'Shared name', 'power' => 500, 'position' => 'GK'])->assertUnprocessable()->assertJsonValidationErrors('position');
    }
}
