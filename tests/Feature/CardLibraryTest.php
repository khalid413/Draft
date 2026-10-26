<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Storage::fake('local');
    }

    private function upload(string $name = 'My hero', int $power = 80)
    {
        return $this->postJson('/cards', ['name' => $name, 'power' => $power, 'image' => UploadedFile::fake()->image('hero.png', 300, 400)]);
    }

    public function test_upload_list_serve_and_edit_card_without_requiring_a_new_picture(): void
    {
        $card = $this->upload()->assertCreated()->assertJsonPath('name', 'My hero')->assertJsonPath('power', 80)->json();
        Storage::disk('local')->assertExists('cards/'.basename($card['image_path']));
        $this->get($card['image_path'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/cards')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'My hero');
        $this->postJson('/cards/'.$card['id'], ['name' => 'Renamed hero', 'power' => 93])
            ->assertOk()->assertJsonPath('name', 'Renamed hero')->assertJsonPath('power', 93)->assertJsonPath('image_path', $card['image_path']);
        $this->assertDatabaseHas('cards', ['domain' => 'custom', 'series' => '', 'name' => 'Renamed hero', 'power' => 93]);
    }

    public function test_validation_rejects_missing_images_svg_oversized_images_bad_power_and_duplicate_names(): void
    {
        $this->postJson('/cards', ['name' => 'Hero', 'power' => 50])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson('/cards', ['name' => 'Hero', 'power' => 1001, 'image' => UploadedFile::fake()->image('hero.jpg')])->assertUnprocessable()->assertJsonValidationErrors('power');
        $this->postJson('/cards', ['name' => 'Hero', 'power' => 0, 'image' => UploadedFile::fake()->image('hero.jpg')])->assertUnprocessable();
        $this->postJson('/cards', ['name' => 'Hero', 'power' => 20, 'image' => UploadedFile::fake()->createWithContent('hero.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertUnprocessable();
        $this->postJson('/cards', ['name' => 'Hero', 'power' => 20, 'image' => UploadedFile::fake()->image('large.jpg')->size(5121)])->assertUnprocessable();
        $this->upload()->assertCreated();
        $this->upload()->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('cards', 1);
    }

    public function test_custom_game_requires_enough_cards_and_freezes_a_private_deck(): void
    {
        $settings = ['players' => ['Alex', 'Sam'], 'domain' => 'custom', 'rounds' => 3, 'timer' => false];
        $this->postJson('/game/start', $settings)->assertUnprocessable()->assertJsonValidationErrors('domain');
        for ($i = 0; $i < 6; $i++) {
            $this->upload('Hero '.$i, 50)->assertCreated();
        }
        $state = $this->postJson('/game/start', $settings)->assertOk()->assertJsonMissingPath('deck')->json();
        $deck = Game::first()->state['deck'];
        $this->assertCount(6, $deck);
        $this->getJson('/cards')->assertOk();
        $this->upload('Extra hero')->assertCreated();
        Card::whereKey($deck[0]['id'])->update(['name' => 'Changed later', 'power' => 1]);
        for ($round = 0; $round < 3; $round++) {
            $state = $this->postJson('/game/ready', ['version' => $state['version']])->assertOk()->json();
            $this->assertSame(50, $state['cards'][0]['power']);
            $this->assertSame(['selectionId', 'hidden'], array_keys($state['cards'][1]));
            $state = $this->postJson('/game/pick', ['version' => $state['version'], 'playerIndex' => $state['activePlayer'], 'cardId' => $state['cards'][0]['selectionId']])->assertOk()->json();
            $state = $this->postJson('/game/reveal', ['version' => $state['version']])->assertOk()->json();
            $state = $this->postJson('/game/'.($round === 2 ? 'finish' : 'next'), ['version' => $state['version']])->assertOk()->json();
        }
        $this->assertSame(['base' => 150, 'bonus' => 0, 'total' => 150], $state['results']['scores'][0]);
        $this->getJson('/cards')->assertOk();
    }

    public function test_abandon_requires_current_version_and_unlocks_library(): void
    {
        Card::factory()->count(6)->create(['domain' => 'custom']);
        $state = $this->postJson('/game/start', ['players' => ['Alex', 'Sam'], 'domain' => 'custom', 'rounds' => 3, 'timer' => false])->assertOk()->json();
        $this->postJson('/game/abandon', ['version' => 99])->assertUnprocessable();
        $this->postJson('/game/abandon', ['version' => $state['version']])->assertOk();
        $this->getJson('/cards')->assertOk();
        $this->getJson('/game/state')->assertContent('null');
        $this->assertDatabaseHas('games', ['status' => 'abandoned']);
    }

    public function test_other_domain_cards_cannot_be_edited_and_old_images_survive_replacement(): void
    {
        $demo = Card::factory()->create(['domain' => 'football']);
        $this->postJson('/cards/'.$demo->id, ['name' => 'Hijack', 'power' => 10])->assertNotFound();
        $card = $this->upload()->assertCreated()->json();
        $updated = $this->postJson('/cards/'.$card['id'], ['name' => $card['name'], 'power' => 70, 'image' => UploadedFile::fake()->image('new.jpg')])->assertOk()->json();
        $this->assertNotSame($card['image_path'], $updated['image_path']);
        $this->get($card['image_path'])->assertOk();
        $this->get($updated['image_path'])->assertOk();
        $this->get('/card-images/not-an-image.php')->assertNotFound();
    }
}
