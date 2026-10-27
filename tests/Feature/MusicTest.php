<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MusicTest extends TestCase
{
    use RefreshDatabase;

    private function upload(string $title): void
    {
        $this->post('/admin/music', ['title' => $title, 'file' => UploadedFile::fake()->create($title.'.mp3', 64, 'audio/mpeg')])
            ->assertRedirect('/admin/music')->assertSessionHasNoErrors();
    }

    public function test_players_hear_the_playlist_but_cannot_change_it(): void
    {
        $this->getJson('/music')->assertOk()->assertExactJson([]);
        $this->get('/admin/music')->assertRedirect('/admin/login');
        $this->postJson('/admin/music', ['title' => 'Nope'])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/admin/music', ['title' => 'Nope'])->assertForbidden();
        $this->get('/draft')->assertOk()->assertSee('Background music')->assertSee('Turn sound effects')
            ->assertDontSee('How to play')->assertDontSee('Draft Duel home');
    }

    public function test_admin_uploads_orders_and_removes_songs(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->upload('Opening theme');
        $this->upload('Final whistle');
        $this->post('/admin/music', ['title' => 'Not audio', 'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])
            ->assertSessionHasErrors('file');
        $this->get('/admin/music')->assertOk()->assertSee('Opening theme')->assertSee('Final whistle');

        $playlist = $this->getJson('/music')->assertOk()->assertJsonCount(2)->assertJsonPath('0.title', 'Opening theme')->json();
        $this->assertStringNotContainsString('music/', json_encode(array_column($playlist, 'title')).json_encode(array_keys($playlist[0])));
        $this->assertSame(['id', 'title', 'src'], array_keys($playlist[0]));
        $this->get($playlist[0]['src'])->assertOk()->assertHeader('Content-Type', 'audio/mpeg')->assertHeader('X-Content-Type-Options', 'nosniff');

        $second = Song::where('title', 'Final whistle')->firstOrFail();
        $this->post('/admin/music/'.$second->id.'/move', ['direction' => 'up'])->assertRedirect('/admin/music');
        $this->getJson('/music')->assertJsonPath('0.title', 'Final whistle')->assertJsonPath('1.title', 'Opening theme');

        $path = $second->file_path;
        Storage::disk('local')->assertExists($path);
        $this->delete('/admin/music/'.$second->id)->assertRedirect('/admin/music');
        Storage::disk('local')->assertMissing($path);
        $this->getJson('/music')->assertJsonCount(1)->assertJsonPath('0.title', 'Opening theme');
        $this->get('/music/'.$second->id.'/audio')->assertNotFound();
    }
}
