<?php

namespace Tests\Feature;

use App\Events\RoomChanged;
use App\Models\Card;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminRoomsTest extends TestCase
{
    use RefreshDatabase;

    private function room(): array
    {
        Event::fake([RoomChanged::class]);
        Card::factory()->count(12)->create(['domain' => 'custom']);
        $room = $this->postJson('/rooms', ['name' => 'Host', 'capacity' => 2, 'domain' => 'custom', 'rounds' => 3, 'timer' => false])->assertOk()->json();
        $tokens = session('room_tokens');
        $room = $this->withSession(['room_tokens' => []])->postJson('/rooms/join', ['name' => 'Guest', 'code' => $room['code']])->assertOk()->json();

        return [$room, $tokens];
    }

    public function test_players_cannot_open_or_ban_rooms(): void
    {
        [$room] = $this->room();
        $this->get('/admin/rooms')->assertRedirect('/admin/login');
        $this->postJson('/admin/rooms/'.$room['id'].'/ban')->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/admin/rooms/'.$room['id'].'/ban')->assertForbidden();
        $this->assertSame('lobby', Room::first()->status);
    }

    public function test_admin_filters_rooms_by_status(): void
    {
        $make = fn (string $code, string $status, array $extra = []) => Room::create([...['id' => (string) \Illuminate\Support\Str::uuid(), 'code' => $code, 'status' => $status,
            'settings' => ['domain' => 'custom', 'rounds' => 3, 'capacity' => 2, 'timer' => false],
            'members' => [['name' => 'Owner '.$code, 'ready' => false, 'token_hash' => hash('sha256', $code)]], 'expires_at' => now()->addDay()], ...$extra]);
        $make('LOBBY001', 'lobby');
        $make('FINISH01', 'finished');
        $make('BANNED01', 'banned');
        $make('EXPIRED1', 'lobby', ['expires_at' => now()->subMinute()]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        // Every room is rendered once with its bucket; filtering happens in the browser.
        $page = $this->get('/admin/rooms')->assertOk()->assertSee(['LOBBY001', 'FINISH01', 'BANNED01', 'EXPIRED1']);
        $bucketOf = function (string $html, string $code): string {
            preg_match('/data-bucket="(\w+)"(?:(?!<\/article>).)*'.$code.'/s', $html, $match);

            return $match[1] ?? 'missing';
        };
        $html = $page->getContent();
        $this->assertSame('lobby', $bucketOf($html, 'LOBBY001'));
        $this->assertSame('finished', $bucketOf($html, 'FINISH01'));
        $this->assertSame('closed', $bucketOf($html, 'BANNED01'));
        $this->assertSame('closed', $bucketOf($html, 'EXPIRED1'));
        $page->assertSee('<b>4</b>', false)->assertSee('<b>2</b>', false);

        // A bookmarked filter starts on that filter and hides the other rooms before scripts run.
        $finished = $this->get('/admin/rooms?status=finished')->assertOk()->getContent();
        $this->assertStringContainsString("filter: 'finished'", $finished);
        $this->assertMatchesRegularExpression('/data-bucket="lobby"[^>]*style="display:none"/', $finished);
        $this->assertDoesNotMatchRegularExpression('/data-bucket="finished"[^>]*style="display:none"/', $finished);
        $this->get('/admin/rooms?status=playing')->assertOk()->assertSee('No playing rooms right now.');
        $this->assertStringContainsString("filter: 'all'", $this->get('/admin/rooms?status=nonsense')->getContent());
    }

    public function test_admin_sees_rooms_with_players_and_ban_closes_them(): void
    {
        [$room, $hostTokens] = $this->room();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/rooms')->assertOk()
            ->assertSee($room['code'])->assertSee('Host')->assertSee('Guest')->assertSee('Ban')
            ->assertDontSee('token_hash');
        $this->post('/admin/rooms/'.$room['id'].'/ban')->assertRedirect('/admin/rooms')->assertSessionHas('status');
        $this->assertSame('banned', Room::first()->status);
        Event::assertDispatched(RoomChanged::class);
        $this->get('/admin/rooms')->assertSee('Banned');
        $this->withSession(['room_tokens' => $hostTokens])->getJson('/rooms/'.$room['id'])
            ->assertStatus(410)->assertJsonPath('message', 'This room was closed by an administrator.');
        $this->withSession(['room_tokens' => []])->postJson('/rooms/join', ['name' => 'Late', 'code' => $room['code']])->assertStatus(410);
    }
}
