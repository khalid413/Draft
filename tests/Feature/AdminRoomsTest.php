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
