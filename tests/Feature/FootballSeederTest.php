<?php

namespace Tests\Feature;

use App\Domains\FootballDomain;
use App\Models\Card;
use Database\Seeders\FootballPlayerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FootballSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_has_photos_two_icons_per_position_and_enough_unique_players_for_a_full_draft(): void
    {
        $this->seed(FootballPlayerSeeder::class);
        $this->assertSame(67, Card::count());
        $this->assertSame(16, Card::where('is_icon', true)->count());
        $domain = new FootballDomain;
        foreach ($domain->inventory(4) as $slot) {
            $this->assertSame(match ($slot['position']) {
                'CM' => 12, 'GK' => 7, default => 8
            }, $slot['count']);
            $this->assertSame(2, Card::where('position', $slot['position'])->where('is_icon', true)->count());
            $this->assertSame(0, $slot['missing']);
        }
        $used = [];
        foreach ($domain->slots() as $slot) {
            foreach ($domain->drawPair($used) as $card) {
                $this->assertSame($slot['position'], $card->position);
                $this->assertNotContains($card->id, $used);
                $used[] = $card->id;
            }
        }
        foreach (Card::all() as $card) {
            $this->assertNotFalse(getimagesize(public_path($card->image_path)));
            $this->assertSame('FC 27', $card->meta['edition']);
        }
    }

    public function test_reseeding_does_not_duplicate_or_overwrite_personal_edits(): void
    {
        $this->seed(FootballPlayerSeeder::class);
        $card = Card::where('name', 'Pedri')->firstOrFail();
        $card->update(['power' => 99, 'image_path' => '/my-upload.png']);
        $this->seed(FootballPlayerSeeder::class);
        $this->assertSame(67, Card::count());
        $this->assertSame(99, $card->fresh()->power);
        $this->assertSame('/my-upload.png', $card->fresh()->image_path);
    }
}
