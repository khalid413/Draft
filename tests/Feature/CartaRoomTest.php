<?php

namespace Tests\Feature;

use App\Carta\CartaState;
use App\Events\RoomChanged;
use App\Models\CartaGame;
use App\Models\CartaMove;
use App\Models\CartaPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CartaRoomTest extends TestCase
{
    use RefreshDatabase;

    private array $seats = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomChanged::class]);
    }

    private function seat(int $index): self
    {
        return $this->withSession(['room_tokens' => $this->seats[$index] ?? []]);
    }

    private function table(int $players = 3, bool $start = true): array
    {
        $room = $this->postJson('/carta/rooms', ['name' => 'Host', 'capacity' => $players])->assertOk()->json();
        $this->seats[0] = session('room_tokens');
        for ($i = 1; $i < $players; $i++) {
            $room = $this->seat($i)->postJson('/carta/rooms/join', ['name' => 'Guest '.$i, 'code' => $room['code']])->assertOk()->json();
            $this->seats[$i] = session('room_tokens');
        }
        if (! $start) {
            return $room;
        }
        foreach (array_keys($room['members']) as $index) {
            $room = $this->seat($index)->postJson('/carta/rooms/'.$room['id'].'/ready', ['version' => $room['version'], 'ready' => true])->assertOk()->json();
        }

        return $this->seat(0)->postJson('/carta/rooms/'.$room['id'].'/start', ['version' => $room['version']])->assertOk()->json();
    }

    private function peek(array $room, int $seat): array
    {
        return $this->seat($seat)->getJson('/carta/rooms/'.$room['id'])->assertOk()->json();
    }

    private function send(array $room, int $seat, string $action, array $payload = []): TestResponse
    {
        return $this->seat($seat)->postJson('/carta/rooms/'.$room['id'].'/'.$action, [...$payload, 'version' => $room['version'], 'actionId' => (string) Str::uuid()]);
    }

    /** Plays the current seat's turn the way a sensible player would: answer, play, draw, or pass. */
    private function takeTurn(array $room): array
    {
        $seat = $room['game']['currentSeat'];
        $view = $this->peek($room, $seat);
        $you = $view['game']['you'];
        $playable = array_values(array_filter($view['game']['hand'], fn ($card) => $card['playable']));
        if ($playable) {
            return $this->send($view, $seat, 'play', ['card' => $playable[0]['id'], 'suit' => $playable[0]['rank'] === 7 ? 'cups' : null])->assertOk()->json();
        }
        $action = $you['canAccept'] ? 'accept' : ($you['canPass'] ? 'pass' : 'draw');

        return $this->send($view, $seat, $action)->assertOk()->json();
    }

    private function opponentHands(string $gameId, int $viewer): array
    {
        return CartaPlayer::where('carta_game_id', $gameId)->where('seat', '!=', $viewer)->get()->flatMap(fn ($player) => $player->hand)->all();
    }

    public function test_a_full_match_over_http_ranks_everyone_and_never_leaks_hidden_cards(): void
    {
        $room = $this->table(4);
        $this->assertSame('playing', $room['status']);
        $this->assertCount(5, $room['game']['hand']);
        $this->assertSame(40 - 4 * 5 - 1, $room['game']['drawCount']);
        for ($turn = 0; $room['status'] === 'playing' && $turn < 3000; $turn++) {
            if (! CartaMove::where('type', 'reshuffle')->exists()) {
                // Before any reshuffle, no opponent card id or draw pile card can appear in a player's JSON.
                $viewer = $turn % 4;
                $json = json_encode($this->peek($room, $viewer));
                $game = CartaGame::first();
                foreach ([...$this->opponentHands($game->id, $viewer), ...$game->draw_pile] as $hidden) {
                    $this->assertStringNotContainsString('"'.$hidden.'"', $json);
                }
                $this->assertStringNotContainsString('draw_pile', $json);
            }
            $room = $this->takeTurn($room);
        }
        $this->assertSame('finished', $room['status']);
        $final = $this->peek($room, 0)['game'];
        $this->assertSame(CartaState::FINISHED, $final['status']);
        $this->assertSame([1, 2, 3, 4], array_column($final['rankings'], 'rank'));
        $this->assertSame(CartaState::LOSER, end($final['rankings'])['status']);
        $this->assertSame(4, CartaPlayer::whereNotNull('finish_rank')->distinct()->count('finish_rank'));
        $game = CartaGame::first();
        $ids = array_merge($game->draw_pile, $game->discard_pile, ...CartaPlayer::all()->map->hand->all());
        $this->assertCount(40, array_unique($ids));
        $this->assertCount(40, $ids);
    }

    public function test_illegal_out_of_turn_spoofed_and_stale_actions_are_rejected(): void
    {
        $room = $this->table(3);
        $current = $room['game']['currentSeat'];
        $other = ($current + 1) % 3;
        $mine = $this->peek($room, $current)['game']['hand'];
        $theirs = $this->peek($room, $other)['game']['hand'];

        $this->send($room, $other, 'play', ['card' => $theirs[0]['id']])->assertUnprocessable()->assertJsonPath('message', 'It is not your turn.');
        $this->send($room, $current, 'play', ['card' => $theirs[0]['id']])->assertUnprocessable()->assertJsonPath('message', 'That card is not in your hand.');
        $this->send($room, $current, 'play', ['card' => 'coins_8'])->assertUnprocessable()->assertJsonPath('message', 'Unknown card.');
        $this->send($room, $current, 'play', ['card' => $mine[0]['id'], 'suit' => 'stars'])->assertUnprocessable();
        $this->seat($current)->postJson('/carta/rooms/'.$room['id'].'/play', ['card' => $mine[0]['id'], 'version' => $room['version']])->assertUnprocessable()->assertJsonValidationErrors('actionId');
        $unplayable = array_values(array_filter($mine, fn ($card) => ! $card['playable']));
        if ($unplayable) {
            $this->send($room, $current, 'play', ['card' => $unplayable[0]['id']])->assertUnprocessable();
        }
        if (array_filter($mine, fn ($card) => $card['playable'])) {
            $this->send($room, $current, 'draw')->assertUnprocessable()->assertJsonPath('message', 'You have a playable card, so you cannot draw.');
        }
        $this->seat(9)->getJson('/carta/rooms/'.$room['id'])->assertForbidden();
        $this->seat($current)->postJson('/carta/rooms/'.$room['id'].'/draw', ['version' => $room['version'] - 1, 'actionId' => (string) Str::uuid()])->assertUnprocessable()->assertJsonPath('message', 'The table changed. Please try again.');
        $this->assertSame(1, CartaMove::count(), 'Only the deal is recorded; nothing illegal was applied.');
    }

    public function test_a_retried_action_is_applied_once(): void
    {
        $room = $this->table(2);
        $seat = $room['game']['currentSeat'];
        $view = $this->peek($room, $seat);
        $playable = array_values(array_filter($view['game']['hand'], fn ($card) => $card['playable']));
        $payload = $playable ? ['card' => $playable[0]['id'], 'suit' => $playable[0]['rank'] === 7 ? 'coins' : null] : [];
        $action = $playable ? 'play' : 'draw';
        $body = [...$payload, 'version' => $view['version'], 'actionId' => (string) Str::uuid()];
        $first = $this->seat($seat)->postJson('/carta/rooms/'.$room['id'].'/'.$action, $body)->assertOk()->json();
        $moves = CartaMove::count();
        $hand = CartaPlayer::where('seat', $seat)->first()->hand;
        $retry = $this->seat($seat)->postJson('/carta/rooms/'.$room['id'].'/'.$action, $body)->assertOk()->json();
        $this->assertSame($first['version'], $retry['version']);
        $this->assertSame($moves, CartaMove::count());
        $this->assertSame($hand, CartaPlayer::where('seat', $seat)->first()->hand);
    }

    public function test_finished_players_spectate_and_the_match_continues(): void
    {
        $room = $this->table(3);
        $game = CartaGame::first();
        $seat = $room['game']['currentSeat'];
        // Give the current player a single matching card so their next play finishes them.
        $finisher = collect($game->draw_pile)->first(fn ($id) => str_starts_with($id, $game->active_suit.'_') && ! in_array((int) explode('_', $id)[1], [1, 2, 7], true));
        $player = CartaPlayer::where('seat', $seat)->first();
        $pile = array_values(array_diff($game->draw_pile, [$finisher]));
        $game->update(['draw_pile' => [...$pile, ...$player->hand]]);
        $player->update(['hand' => [$finisher]]);

        $room = $this->send($this->peek($room, $seat), $seat, 'play', ['card' => $finisher])->assertOk()->json();
        $this->assertSame('playing', $room['status'], 'The first finisher does not end the match.');
        $mine = $this->peek($room, $seat)['game'];
        $this->assertSame([CartaState::DONE, 1, []], [$mine['you']['status'], $mine['you']['rank'], $mine['hand']]);
        $this->assertNotSame($seat, $mine['currentSeat']);
        $this->send($room, $seat, 'draw')->assertUnprocessable()->assertJsonPath('message', 'You have finished this match. Watch the others play it out.');
        $this->assertSame([['seat' => $seat, 'name' => $mine['players'][$seat]['name'], 'rank' => 1, 'status' => CartaState::DONE]], $mine['rankings']);
    }

    public function test_reconnecting_restores_the_same_seat_and_hand(): void
    {
        $room = $this->table(2);
        $before = $this->peek($room, 1)['game']['hand'];
        $again = $this->seat(1)->postJson('/carta/rooms/join', ['code' => $room['code'], 'name' => 'Ignored'])->assertOk()->json();
        $this->assertSame(1, $again['viewerIndex']);
        $this->assertSame($before, $again['game']['hand']);
        $this->assertSame($before, $this->peek($room, 1)['game']['hand']);
    }

    public function test_a_player_who_leaves_mid_match_forfeits_and_the_rest_play_on(): void
    {
        $room = $this->table(3);
        $this->send($room, 2, 'leave')->assertOk();
        $room = $this->peek($room, 0);
        $this->assertTrue($room['members'][2]['left']);
        $this->assertSame([CartaState::LOSER, 3, 0], [$room['game']['players'][2]['status'], $room['game']['players'][2]['rank'], $room['game']['players'][2]['cards']]);
        for ($turn = 0; $room['status'] === 'playing' && $turn < 3000; $turn++) {
            $this->assertNotSame(2, $room['game']['currentSeat'], 'The departed seat never waits for input.');
            $room = $this->takeTurn($room);
        }
        $this->assertSame('finished', $room['status']);
    }

    public function test_when_the_last_opponent_leaves_the_remaining_player_wins(): void
    {
        $room = $this->table(2);
        $this->send($room, 1, 'leave')->assertOk();
        $room = $this->peek($room, 0);
        $this->assertSame(['finished', CartaState::FINISHED], [$room['status'], $room['game']['status']]);
        $this->assertSame([0, 1, CartaState::DONE], [$room['game']['rankings'][0]['seat'], $room['game']['rankings'][0]['rank'], $room['game']['rankings'][0]['status']]);
        $this->assertSame([1, 2, CartaState::LOSER], [$room['game']['rankings'][1]['seat'], $room['game']['rankings'][1]['rank'], $room['game']['rankings'][1]['status']]);
    }

    public function test_lobby_rules_rematch_and_separation_from_draft_rooms(): void
    {
        $room = $this->table(3, start: false);
        $this->seat(5)->postJson('/carta/rooms/join', ['name' => 'Late', 'code' => $room['code']])->assertUnprocessable();
        $this->seat(1)->postJson('/carta/rooms/'.$room['id'].'/start', ['version' => $room['version']])->assertForbidden();
        $this->seat(0)->postJson('/carta/rooms/'.$room['id'].'/start', ['version' => $room['version']])->assertUnprocessable();
        // Draft Duel endpoints and codes never reach a Carta table.
        $this->seat(0)->getJson('/rooms/'.$room['id'])->assertNotFound();
        $this->seat(6)->postJson('/rooms/join', ['name' => 'Drafter', 'code' => $room['code']])->assertNotFound();

        $room = $this->table(2);
        for ($turn = 0; $room['status'] === 'playing' && $turn < 3000; $turn++) {
            $room = $this->takeTurn($room);
        }
        $room = $this->seat(1)->postJson('/carta/rooms/'.$room['id'].'/rematch', ['version' => $room['version']])->assertOk()
            ->assertJsonPath('status', 'lobby')->assertJsonPath('game', null)->assertJsonPath('members.0.ready', false)->json();
        $this->assertSame(2, count($room['members']));
    }

    public function test_an_admin_ban_closes_a_carta_table(): void
    {
        $room = $this->table(2);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->post('/admin/rooms/'.$room['id'].'/ban')->assertRedirect();
        $this->seat(0)->getJson('/carta/rooms/'.$room['id'])->assertStatus(410);
        $this->assertSame(CartaState::CANCELLED, CartaGame::first()->status);
        $this->get('/admin/rooms')->assertSee('Moroccan Carta');
    }
}
