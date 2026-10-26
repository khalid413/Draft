<?php

namespace Tests\Feature;

use App\Domains\FootballDomain;
use App\Events\RoomChanged;
use App\Models\Card;
use App\Models\Game;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OnlineRoomTest extends TestCase
{
    use RefreshDatabase;

    private array $seats = [];

    private function seat(int $index): self
    {
        return $this->withSession(['room_tokens' => $this->seats[$index] ?? []]);
    }

    private function lobby(int $count = 2, string $domain = 'custom'): array
    {
        Event::fake([RoomChanged::class]);
        if ($domain === 'custom') {
            Card::factory()->count(12)->create(['domain' => 'custom']);
        } else {
            foreach (FootballDomain::REQUIREMENTS as $position => $quantity) {
                Card::factory()->count($quantity * 2)->create(['domain' => 'football', 'position' => $position, 'power' => 80]);
            }
        }
        $room = $this->postJson('/rooms', ['name' => 'Host', 'capacity' => $count, 'domain' => $domain, 'rounds' => $domain === 'football' ? 11 : 3, 'timer' => true])->assertOk()->json();
        $this->seats[0] = session('room_tokens');
        for ($i = 1; $i < $count; $i++) {
            $room = $this->seat($i)->postJson('/rooms/join', ['name' => 'Guest '.$i, 'code' => $room['code']])->assertOk()->json();
            $this->seats[$i] = session('room_tokens');
        }

        return $room;
    }

    private function begin(array $room): array
    {
        foreach (array_keys($room['members']) as $index) {
            $room = $this->seat($index)->postJson('/rooms/'.$room['id'].'/ready', ['version' => $room['version'], 'ready' => true])->assertOk()->json();
        }

        return $this->seat(0)->postJson('/rooms/'.$room['id'].'/start', ['version' => $room['version']])->assertOk()->json();
    }

    public function test_private_lobby_host_controls_and_session_seats(): void
    {
        $room = $this->lobby(3);
        $this->seat(9)->getJson('/rooms/'.$room['id'])->assertForbidden();
        $this->seat(9)->postJson('/rooms/join', ['name' => 'Fourth', 'code' => $room['code']])->assertUnprocessable();
        $this->seat(1)->postJson('/rooms/'.$room['id'].'/start', ['version' => $room['version']])->assertForbidden();
        $this->seat(0)->postJson('/rooms/'.$room['id'].'/start', ['version' => $room['version']])->assertUnprocessable();
        $room = $this->begin($room);
        $this->assertSame('playing', $room['status']);
        $this->assertSame(0, $room['viewerIndex']);
        $this->assertCount(2, $room['game']['cards']);
        $this->seat(1)->getJson('/rooms/'.$room['id'])->assertJsonPath('viewerIndex', 1)->assertJsonPath('game.cards', []);
        $private = Game::first()->state;
        foreach ([1, 2] as $slot) {
            $this->assertStringNotContainsString($private['rounds'][0]['cards'][$slot]['name'], json_encode($room));
        }
        $this->assertStringNotContainsString('token_hash', json_encode($room));
        $this->assertArrayNotHasKey('deck', $room['game']);
        // Spoofing the host index cannot claim the host's pick.
        $this->seat(1)->postJson('/rooms/'.$room['id'].'/pick', ['version' => $room['version'], 'playerIndex' => 0, 'cardId' => $room['game']['cards'][0]['selectionId']])->assertUnprocessable();
        $old = $room['version'];
        $room = $this->seat(0)->postJson('/rooms/'.$room['id'].'/pick', ['version' => $old, 'cardId' => $room['game']['cards'][1]['selectionId']])->assertOk()->assertJsonPath('game.phase', 'revealing')->json();
        $this->seat(0)->postJson('/rooms/'.$room['id'].'/pick', ['version' => $old, 'cardId' => 'duplicate'])->assertUnprocessable();
        $this->seat(1)->getJson('/rooms/'.$room['id'])->assertJsonPath('game.players.2.cards.0.id', $room['game']['extraCard']['id']);
        // Any seated player can advance, so a dropped host cannot stall the draft.
        $room = $this->seat(1)->postJson('/rooms/'.$room['id'].'/next', ['version' => $room['version']])->assertOk()->assertJsonCount(2, 'game.cards')->json();
        $this->seat(0)->getJson('/rooms/'.$room['id'])->assertJsonPath('game.cards', []);
        Event::assertDispatched(RoomChanged::class);
    }

    public function test_expired_timer_resolves_once_and_reconnect_restores_seat(): void
    {
        $room = $this->begin($this->lobby());
        $game = Game::first();
        $state = $game->state;
        $state['pickDeadline'] = time() - 1;
        $game->update(['state' => $state]);
        $updated = $this->seat(1)->getJson('/rooms/'.$room['id'])->assertOk()->assertJsonPath('game.phase', 'revealing')->json();
        $this->seat(0)->getJson('/rooms/'.$room['id'])->assertJsonPath('version', $updated['version']);
        $this->seat(1)->postJson('/rooms/join', ['code' => $room['code'], 'name' => 'Ignored rename'])->assertOk()->assertJsonPath('viewerIndex', 1)->assertJsonPath('members.1.name', 'Guest 1');
    }

    public function test_complete_football_league_and_shared_replay_do_not_reroll_results(): void
    {
        $room = $this->begin($this->lobby(3, 'football'));
        for ($i = 0; $i < 11; $i++) {
            $active = $room['game']['activePlayer'];
            $view = $this->seat($active)->getJson('/rooms/'.$room['id'])->assertOk()->json();
            $room = $this->seat($active)->postJson('/rooms/'.$room['id'].'/pick', ['version' => $view['version'], 'cardId' => $view['game']['cards'][0]['selectionId']])->assertOk()->json();
            $room = $this->seat(0)->postJson('/rooms/'.$room['id'].'/'.($i === 10 ? 'finish' : 'next'), ['version' => $room['version']])->assertOk()->json();
        }
        $this->assertSame('finished', $room['status']);
        $this->assertCount(3, $room['game']['results']['league']['matches']);
        $results = $room['game']['results'];
        $this->seat(1)->getJson('/rooms/'.$room['id'])->assertJsonPath('playback', $room['playback']);
        Room::first()->update(['playback' => ['index' => 0, 'startsAt' => 1]]);
        $this->seat(1)->postJson('/rooms/'.$room['id'].'/replay', ['version' => $room['version'], 'matchIndex' => 1])->assertForbidden();
        $replay = $this->seat(0)->postJson('/rooms/'.$room['id'].'/replay', ['version' => $room['version'], 'matchIndex' => 1])->assertOk()->assertJsonPath('playback.index', 1)->json();
        $this->assertSame($results, $replay['game']['results']);
    }

    public function test_channel_authorization_and_broadcast_payload_do_not_expose_state(): void
    {
        config(['broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        $room = $this->lobby();
        $payload = ['channel_name' => 'private-room.'.$room['id'], 'socket_id' => '123.456'];
        $this->seat(9)->postJson('/rooms/broadcast-auth', $payload)->assertForbidden();
        $this->seat(0)->postJson('/rooms/broadcast-auth', $payload)->assertOk()->assertJsonStructure(['auth']);
        $event = new RoomChanged($room['id'], 7);
        $this->assertSame(['version' => 7], $event->broadcastWith());
    }

    public function test_settings_reset_readiness_and_closed_rooms_reject_access(): void
    {
        $room = $this->lobby();
        $room = $this->seat(1)->postJson('/rooms/'.$room['id'].'/ready', ['version' => $room['version'], 'ready' => true])->assertOk()->json();
        $settings = ['version' => $room['version'], 'name' => 'Host', 'capacity' => 2, 'domain' => 'custom', 'rounds' => 5, 'timer' => false];
        $this->seat(1)->postJson('/rooms/'.$room['id'].'/settings', $settings)->assertForbidden();
        $room = $this->seat(0)->postJson('/rooms/'.$room['id'].'/settings', $settings)->assertOk()->assertJsonPath('members.1.ready', false)->json();
        $this->seat(0)->postJson('/rooms/'.$room['id'].'/leave', ['version' => $room['version']])->assertOk()->assertContent('null');
        $this->seat(0)->getJson('/rooms/'.$room['id'])->assertForbidden();
        $room = $this->seat(1)->getJson('/rooms/'.$room['id'])->assertOk()->assertJsonPath('viewerIndex', 0)
            ->assertJsonPath('isHost', true)->assertJsonCount(1, 'members')->json();
        $this->seat(1)->postJson('/rooms/'.$room['id'].'/leave', ['version' => $room['version']])->assertOk();
        $this->seat(1)->getJson('/rooms/'.$room['id'])->assertStatus(410);
    }

    public function test_host_leaving_mid_match_hands_over_and_the_empty_seat_picks_at_random(): void
    {
        $room = $this->begin($this->lobby());
        $this->seat(1)->postJson('/rooms/'.$room['id'].'/leave', ['version' => $room['version']])->assertUnprocessable();
        $this->assertSame(0, $room['game']['activePlayer']);
        $this->seat(0)->postJson('/rooms/'.$room['id'].'/leave', ['version' => $room['version']])->assertOk()->assertContent('null');
        $this->seat(0)->getJson('/rooms/'.$room['id'])->assertForbidden();
        $room = $this->seat(1)->getJson('/rooms/'.$room['id'])->assertOk()->assertJsonPath('status', 'playing')
            ->assertJsonPath('isHost', true)->assertJsonPath('members.0.left', true)
            ->assertJsonPath('game.phase', 'revealing')->assertJsonCount(1, 'game.players.0.cards')->json();
        $room = $this->seat(1)->postJson('/rooms/'.$room['id'].'/next', ['version' => $room['version']])->assertOk()->assertJsonPath('game.phase', 'picking')->json();
        $room = $this->seat(1)->postJson('/rooms/'.$room['id'].'/pick', ['version' => $room['version'], 'cardId' => $room['game']['cards'][0]['selectionId']])->assertOk()->json();
        // The departed host's turn resolves immediately instead of waiting for a pick.
        $room = $this->seat(1)->postJson('/rooms/'.$room['id'].'/next', ['version' => $room['version']])->assertOk()->assertJsonPath('game.phase', 'revealing')->json();
        $room = $this->seat(1)->postJson('/rooms/'.$room['id'].'/finish', ['version' => $room['version']])->assertOk()->assertJsonPath('status', 'finished')->json();
        foreach ($room['game']['players'] as $player) {
            $this->assertCount(3, $player['cards']);
        }
    }

    public function test_four_online_players_finish_with_equal_rosters(): void
    {
        $room = $this->begin($this->lobby(4));
        $this->assertSame(6, $room['game']['roundLimit']);
        for ($i = 0; $i < 6; $i++) {
            $active = $room['game']['activePlayer'];
            $view = $this->seat($active)->getJson('/rooms/'.$room['id'])->assertOk()->json();
            $room = $this->seat($active)->postJson('/rooms/'.$room['id'].'/pick', ['version' => $view['version'], 'cardId' => $view['game']['cards'][1]['selectionId']])->assertOk()->json();
            $room = $this->seat(0)->postJson('/rooms/'.$room['id'].'/'.($i === 5 ? 'finish' : 'next'), ['version' => $room['version']])->assertOk()->json();
        }
        $this->assertSame('finished', $room['status']);
        foreach ($room['game']['players'] as $player) $this->assertCount(3, $player['cards']);
        $ids = array_merge(...array_map(fn ($player) => array_column($player['cards'], 'id'), $room['game']['players']));
        $this->assertCount(12, array_unique($ids));
    }
}
