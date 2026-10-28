<?php

namespace Tests\Feature;

use App\Events\RoomChanged;
use App\Models\Room;
use App\Models\UnoGame;
use App\Models\UnoMove;
use App\Models\UnoPlayer;
use App\Uno\Classic\ClassicVariant;
use App\Uno\UnoState;
use App\Uno\UnoStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UnoRoomTest extends TestCase
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

    private function table(int $players = 3, string $stacking = 'off', bool $start = true): array
    {
        $room = $this->postJson('/uno/rooms', ['name' => 'Host', 'capacity' => $players, 'variant' => 'classic', 'stacking' => $stacking])->assertOk()->json();
        $this->seats[0] = session('room_tokens');
        for ($i = 1; $i < $players; $i++) {
            $room = $this->seat($i)->postJson('/uno/rooms/join', ['name' => 'Guest '.$i, 'code' => $room['code']])->assertOk()->json();
            $this->seats[$i] = session('room_tokens');
        }
        if (! $start) {
            return $room;
        }
        foreach (array_keys($room['members']) as $index) {
            $room = $this->seat($index)->postJson('/uno/rooms/'.$room['id'].'/ready', ['version' => $room['version'], 'ready' => true])->assertOk()->json();
        }

        return $this->seat(0)->postJson('/uno/rooms/'.$room['id'].'/start', ['version' => $room['version']])->assertOk()->json();
    }

    private function peek(array $room, int $seat): array
    {
        return $this->seat($seat)->getJson('/uno/rooms/'.$room['id'])->assertOk()->json();
    }

    private function send(array $room, int $seat, string $action, array $payload = []): TestResponse
    {
        return $this->seat($seat)->postJson('/uno/rooms/'.$room['id'].'/'.$action, [...$payload, 'version' => $room['version'], 'actionId' => (string) Str::uuid()]);
    }

    /** Plays the current seat's turn the way a careful player would: play (then call UNO on one card), answer, draw or pass. */
    private function takeTurn(array $room): array
    {
        $seat = $room['game']['currentSeat'];
        $view = $this->peek($room, $seat);
        $you = $view['game']['you'];
        $playable = array_values(array_filter($view['game']['hand'], fn ($card) => $card['playable']));
        if ($playable) {
            $wild = in_array($playable[0]['value'], ['wild', 'wild4'], true) && count($view['game']['hand']) > 1;
            $after = $this->send($view, $seat, 'play', ['card' => $playable[0]['id'], 'color' => $wild ? 'blue' : null])->assertOk()->json();

            return ($after['game']['you']['canCallUno'] ?? false) ? $this->send($after, $seat, 'uno')->assertOk()->json() : $after;
        }
        $action = $you['canPass'] ? 'pass' : ($you['canAccept'] ? 'accept' : 'draw');

        return $this->send($view, $seat, $action)->assertOk()->json();
    }

    /** Rewrites the running match so a test can start from an exact position. */
    private function arrange(array $room, array $hands, string $top, int $current): array
    {
        $game = UnoGame::first();
        $store = app(UnoStore::class);
        $state = $store->load($game);
        $used = array_merge([$top], ...$hands);
        $state->drawPile = array_values(array_diff((new ClassicVariant)->deck(), $used));
        $state->discardPile = [$top];
        foreach ($hands as $seat => $hand) {
            $state->players[$seat]['hand'] = $hand;
        }
        $state->currentSeat = $current;
        $state->activeColor = explode('_', $top)[0];
        $store->save($game, $state);
        Room::whereKey($room['id'])->increment('version');

        return $this->peek($room, $current);
    }

    public function test_a_full_match_over_http_ranks_everyone_and_never_leaks_hidden_cards(): void
    {
        foreach (['off', 'same', 'mixed'] as $stacking) {
            $this->seats = [];
            UnoGame::query()->delete();
            $room = $this->table(4, $stacking);
            $this->assertSame('playing', $room['status']);
            $this->assertCount(7, $room['game']['hand']);
            $this->assertSame(108 - 4 * 7 - 1, $room['game']['drawCount']);
            $this->assertSame($stacking, $room['game']['stacking']);
            for ($turn = 0; $room['status'] === 'playing' && $turn < 4000; $turn++) {
                if ($turn % 7 === 0 && ! UnoMove::where('type', 'reshuffle')->exists()) {
                    $viewer = $turn % 4;
                    $json = json_encode($this->peek($room, $viewer));
                    $game = UnoGame::first();
                    $hidden = [...UnoPlayer::where('uno_game_id', $game->id)->where('seat', '!=', $viewer)->get()->flatMap(fn ($player) => $player->hand)->all(), ...$game->draw_pile];
                    foreach ($hidden as $id) {
                        $this->assertStringNotContainsString('"'.$id.'"', $json);
                    }
                    $this->assertStringNotContainsString('draw_pile', $json);
                    $this->assertStringNotContainsString('legal', $json, 'Whether a +4 was a bluff stays secret.');
                }
                $room = $this->takeTurn($room);
            }
            $this->assertSame('finished', $room['status']);
            $final = $this->peek($room, 0)['game'];
            $this->assertSame([1, 2, 3, 4], array_column($final['rankings'], 'rank'));
            $this->assertSame(UnoState::LOSER, end($final['rankings'])['status']);
            $game = UnoGame::first();
            $ids = array_merge($game->draw_pile, $game->discard_pile, ...UnoPlayer::where('uno_game_id', $game->id)->get()->map->hand->all());
            $this->assertCount(108, $ids);
            $this->assertCount(108, array_unique($ids));
        }
    }

    public function test_illegal_out_of_turn_spoofed_and_stale_actions_are_rejected(): void
    {
        $room = $this->table(3);
        $room = $this->arrange($room, [['red_1_1', 'blue_2_1', 'wild_1'], ['green_3_1', 'yellow_4_1'], ['red_5_1', 'red_6_1']], 'red_9_1', 0);

        $this->send($room, 1, 'play', ['card' => 'green_3_1'])->assertUnprocessable()->assertJsonPath('message', 'It is not your turn.');
        $this->send($room, 0, 'play', ['card' => 'green_3_1'])->assertUnprocessable()->assertJsonPath('message', 'That card is not in your hand.');
        $this->send($room, 0, 'play', ['card' => 'red_10_1'])->assertUnprocessable()->assertJsonPath('message', 'Unknown card.');
        $this->send($room, 0, 'play', ['card' => 'blue_2_1'])->assertUnprocessable();
        $this->send($room, 0, 'play', ['card' => 'wild_1'])->assertUnprocessable()->assertJsonPath('message', 'Choose the next colour for your wild card.');
        $this->send($room, 0, 'play', ['card' => 'wild_1', 'color' => 'purple'])->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->send($room, 0, 'catch', ['target' => 1])->assertUnprocessable();
        $this->send($room, 0, 'pass')->assertUnprocessable();
        $this->seat(0)->postJson('/uno/rooms/'.$room['id'].'/play', ['card' => 'red_1_1', 'version' => $room['version']])->assertUnprocessable()->assertJsonValidationErrors('actionId');
        $this->seat(9)->getJson('/uno/rooms/'.$room['id'])->assertForbidden();
        $this->seat(0)->postJson('/uno/rooms/'.$room['id'].'/draw', ['version' => $room['version'] - 1, 'actionId' => (string) Str::uuid()])->assertUnprocessable()->assertJsonPath('message', 'The table changed. Please try again.');
        $this->assertSame(1, UnoMove::count(), 'Only the deal is recorded; nothing illegal was applied.');
    }

    public function test_a_retried_action_is_applied_once(): void
    {
        $room = $this->table(2);
        $room = $this->arrange($room, [['red_1_1', 'red_2_1', 'blue_3_1'], ['green_3_1', 'yellow_4_1']], 'red_9_1', 0);
        $payload = ['card' => 'red_1_1', 'version' => $room['version'], 'actionId' => (string) Str::uuid()];
        $first = $this->seat(0)->postJson('/uno/rooms/'.$room['id'].'/play', $payload)->assertOk()->json();
        $again = $this->seat(0)->postJson('/uno/rooms/'.$room['id'].'/play', $payload)->assertOk()->json();
        $this->assertSame($first['version'], $again['version']);
        $this->assertSame(1, UnoMove::where('type', 'play')->count());
    }

    public function test_uno_catch_and_mixed_stacking_over_http(): void
    {
        $room = $this->table(3, 'mixed');
        $room = $this->arrange($room, [['red_1_1', 'red_2_1'], ['wild4_1', 'red_3_1', 'blue_3_1'], ['green_4_1', 'green_draw2_1', 'yellow_5_1']], 'red_9_1', 0);
        // Seat 0 plays down to one card without calling; seat 2 sees the catch button and uses it.
        $room = $this->send($room, 0, 'play', ['card' => 'red_1_1'])->assertOk()->json();
        $this->assertTrue($this->peek($room, 2)['game']['players'][0]['catchable']);
        $this->assertTrue($this->peek($room, 0)['game']['you']['exposed']);
        $room = $this->send($room, 2, 'catch', ['target' => 0])->assertOk()->json();
        $this->assertSame(3, $room['game']['players'][0]['cards']);
        // Seat 1 plays a +4; seat 2 answers it with a +2; seat 0 (who could stack again) takes all six instead.
        $room = $this->send($room, 1, 'play', ['card' => 'wild4_1', 'color' => 'green'])->assertOk()->json();
        $target = $this->peek($room, 2)['game'];
        $this->assertSame(['effect' => 'DRAW_FOUR', 'draw' => 4], $target['pending']);
        $this->assertSame(['green_draw2_1'], array_column(array_filter($target['hand'], fn ($card) => $card['playable']), 'id'));
        $this->assertArrayNotHasKey('canChallenge', $target['you']);
        $room = $this->send($this->peek($room, 2), 2, 'play', ['card' => 'green_draw2_1'])->assertOk()->json();
        $this->assertSame(['effect' => 'DRAW_TWO', 'draw' => 6], $room['game']['pending']);
        $this->assertSame(0, $room['game']['currentSeat']);
        $room = $this->send($this->peek($room, 0), 0, 'accept')->assertOk()->json();
        $this->assertCount(9, $room['game']['hand']);
        $this->assertSame(1, $room['game']['currentSeat']);
    }

    public function test_stacking_is_chosen_by_the_host_shown_to_everyone_and_locked_at_the_deal(): void
    {
        $room = $this->table(2, 'off', start: false);
        $this->assertSame('off', $room['settings']['stacking']);
        $this->send($room, 1, 'settings', ['stacking' => 'mixed'])->assertForbidden();
        $this->send($room, 0, 'settings', ['stacking' => 'sometimes'])->assertUnprocessable()->assertJsonValidationErrors('stacking');
        $room = $this->send($room, 0, 'settings', ['stacking' => 'mixed'])->assertOk()->json();
        $this->assertSame('mixed', $room['settings']['stacking']);
        $this->assertSame('mixed', $this->peek($room, 1)['settings']['stacking']);
        $this->assertSame('mixed', Room::find($room['id'])->settings['stacking'], 'Persisted with the room.');
        foreach ([0, 1] as $index) {
            $room = $this->send($room, $index, 'ready', ['ready' => true])->assertOk()->json();
        }
        $room = $this->send($room, 0, 'start')->assertOk()->json();
        $this->assertSame('mixed', $room['game']['stacking']);
        $this->assertSame('mixed', UnoGame::first()->stacking);
        $this->send($room, 0, 'settings', ['stacking' => 'off'])->assertUnprocessable()->assertJsonPath('message', 'Rules are locked once the cards are dealt.');
        $this->postJson('/uno/rooms', ['name' => 'X', 'capacity' => 2, 'variant' => 'flip', 'stacking' => 'off'])->assertUnprocessable()->assertJsonValidationErrors('variant');
    }

    public function test_reconnecting_restores_the_seat_and_leaving_forfeits(): void
    {
        $room = $this->table(3);
        $hand = $this->peek($room, 1)['game']['hand'];
        $again = $this->seat(1)->postJson('/uno/rooms/join', ['name' => 'Guest 1', 'code' => $room['code']])->assertOk()->json();
        $this->assertSame(1, $again['viewerIndex']);
        $this->assertSame($hand, $again['game']['hand']);

        $room = $this->peek($room, 0);
        $this->send($room, 1, 'leave')->assertOk();
        $room = $this->peek($room, 0);
        $this->assertSame(UnoState::LOSER, $room['game']['players'][1]['status']);
        $this->assertSame(3, $room['game']['players'][1]['rank']);
        $this->send($room, 2, 'leave')->assertOk();
        $room = $this->peek($room, 0);
        $this->assertSame('finished', $room['status']);
        $this->assertSame(1, $room['game']['players'][0]['rank']);
    }

    public function test_pages_and_code_lookup(): void
    {
        $this->get('/uno')->assertOk()->assertSee('UNO Classic')->assertSee('UNO Flip')->assertSee('Coming soon');
        $this->get('/uno/classic')->assertOk();
        $this->get('/uno/flip')->assertNotFound();
        $this->get('/')->assertOk()->assertSee('href="/uno"', false);
        $room = $this->table(2, start: false);
        $this->postJson('/find-table', ['code' => $room['code']])->assertOk()->assertJsonPath('url', '/uno/classic?join='.$room['code']);
        $this->postJson('/carta/rooms/join', ['code' => $room['code'], 'name' => 'Nope'])->assertNotFound();
    }
}
