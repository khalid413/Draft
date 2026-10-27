<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private function room(string $code, string $type, string $status = 'lobby'): Room
    {
        return Room::create(['id' => (string) Str::uuid(), 'code' => $code, 'game_type' => $type, 'status' => $status,
            'settings' => ['capacity' => 2], 'members' => [], 'expires_at' => now()->addDay()]);
    }

    public function test_the_front_page_is_the_glisa_library_of_games(): void
    {
        $this->get('/')->assertOk()->assertSee('<title>Glisa', false)
            ->assertSee(['Draft Duel', 'Moroccan Carta', 'href="/draft"', 'href="/carta"'], false)
            ->assertDontSee('Enter the arena');
        $this->get('/draft')->assertOk()->assertSee('Draft Duel · Glisa')->assertSee('All games');
        $this->get('/carta')->assertOk()->assertSee('Moroccan Carta · Glisa');
    }

    public function test_old_invites_redirect_to_draft_duel(): void
    {
        $this->get('/?join=ABCD1234')->assertRedirect('/draft?join=ABCD1234');
    }

    public function test_a_table_code_finds_its_game(): void
    {
        $this->room('CARTA001', 'carta');
        $this->room('DRAFT001', 'draft');
        $this->room('CLOSED01', 'carta', 'closed');
        $this->postJson('/find-table', ['code' => ' carta001 '])->assertOk()->assertExactJson(['url' => '/carta?join=CARTA001']);
        $this->postJson('/find-table', ['code' => 'DRAFT001'])->assertOk()->assertExactJson(['url' => '/draft?join=DRAFT001']);
        $this->postJson('/find-table', ['code' => 'NOPE0000'])->assertNotFound()->assertJsonPath('message', 'No open table uses that code.');
        $this->postJson('/find-table', ['code' => 'CLOSED01'])->assertNotFound();
        $this->postJson('/find-table', ['code' => 'bad'])->assertUnprocessable();
    }
}
