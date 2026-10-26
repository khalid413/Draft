<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Card::factory()->count(14)->create(['domain' => 'custom']);
    }

    private function start(array $overrides = []): array
    {
        return $this->postJson('/game/start', array_merge(['players' => ['Alex', 'Sam'], 'domain' => 'custom', 'rounds' => 3, 'timer' => false], $overrides))->assertOk()->json();
    }

    public function test_complete_http_flow_privacy_resume_and_replay(): void
    {
        $state = $this->start();
        for ($round = 0; $round < 3; $round++) {
            $this->assertSame([], $state['cards']);
            $state = $this->postJson('/game/ready', ['version' => $state['version']])->assertOk()->json();
            $this->assertSame(['selectionId', 'hidden'], array_keys($state['cards'][1]));
            $private = Game::first()->state['rounds'][$round]['cards'][1];
            $this->assertStringNotContainsString($private['name'], json_encode($state));
            $this->postJson('/game/pick', ['version' => $state['version'], 'playerIndex' => 1 - $state['activePlayer'], 'cardId' => $state['cards'][0]['selectionId']])->assertUnprocessable();
            $this->postJson('/game/reveal', ['version' => $state['version']])->assertUnprocessable();
            $oldVersion = $state['version'];
            $state = $this->postJson('/game/pick', ['version' => $state['version'], 'playerIndex' => $state['activePlayer'], 'cardId' => $state['cards'][1]['selectionId']])->assertOk()->json();
            $this->assertTrue($state['cards'][1]['hidden']);
            $this->postJson('/game/pick', ['version' => $oldVersion, 'playerIndex' => $state['activePlayer'], 'cardId' => $state['cards'][0]['selectionId']])->assertUnprocessable();
            $this->getJson('/game/state')->assertOk()->assertJsonPath('pickedSlot', 1);
            $state = $this->postJson('/game/reveal', ['version' => $state['version']])->assertOk()->json();
            $this->assertSame($private['name'], $state['cards'][1]['name']);
            $action = $round === 2 ? 'finish' : 'next';
            $state = $this->postJson('/game/'.$action, ['version' => $state['version']])->assertOk()->json();
        }
        $this->assertSame('finished', $state['phase']);
        $this->assertCount(3, $state['recap']);
        $this->assertCount(3, $state['players'][0]['cards']);
        $this->assertCount(2, $state['results']['scores']);
        $this->start();
        $this->assertSame(2, Game::count());
    }

    public function test_validation_and_session_isolation(): void
    {
        $this->postJson('/game/start', ['players' => [' Alex ', 'alex'], 'domain' => 'custom', 'rounds' => 3, 'timer' => false])->assertUnprocessable();
        $this->postJson('/game/start', ['players' => ['Alex', 'Sam'], 'domain' => 'football', 'rounds' => 3, 'timer' => false])->assertUnprocessable();
        $state = $this->start();
        $this->postJson('/game/start', ['players' => ['Alex', 'Sam'], 'domain' => 'custom', 'rounds' => 3, 'timer' => false])->assertUnprocessable();
        $this->postJson('/game/finish', ['version' => $state['version']])->assertUnprocessable();
        $this->withSession(['game_uuid' => 'not-my-game'])->getJson('/game/state')->assertContent('null');
        $this->postJson('/game/ready', ['version' => 0])->assertNotFound();
    }

    public function test_expired_timer_resolves_on_resume_without_hidden_leak(): void
    {
        $state = $this->start(['timer' => true]);
        $this->postJson('/game/ready', ['version' => $state['version']])->assertOk();
        $game = Game::first();
        $private = $game->state;
        $private['pickDeadline'] = time() - 1;
        $game->update(['state' => $private]);
        $response = $this->getJson('/game/state')->assertOk()->assertJsonPath('phase', 'revealing');
        $this->assertCount(1, $response->json('players.0.cards'));
        $this->getJson('/game/state')->assertJsonPath('version', $response->json('version'));
    }

    public function test_demo_is_not_available_in_setup_or_start_endpoint(): void
    {
        $this->get('/')->assertOk()->assertSee('Character cards', false)->assertDontSee('Anime demo')->assertDontSee('/images/cards/');
        $this->postJson('/game/start', ['players' => ['Alex', 'Sam'], 'domain' => 'anime', 'rounds' => 3, 'timer' => false])->assertUnprocessable()->assertJsonValidationErrors('domain');
    }
}

