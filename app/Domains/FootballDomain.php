<?php

namespace App\Domains;

use App\Models\Card;
use DomainException;
use Illuminate\Support\Collection;

class FootballDomain implements CardPoolDomain, FormationDomainContract
{
    public const REQUIREMENTS = ['GK' => 2, 'LB' => 2, 'CB' => 4, 'RB' => 2, 'CM' => 6, 'LW' => 2, 'ST' => 2, 'RW' => 2];

    public function slots(): array
    {
        return [
            ['key' => 'GK', 'position' => 'GK', 'x' => 50, 'y' => 87],
            ['key' => 'LB', 'position' => 'LB', 'x' => 12, 'y' => 64],
            ['key' => 'CB1', 'position' => 'CB', 'x' => 36, 'y' => 67],
            ['key' => 'CB2', 'position' => 'CB', 'x' => 64, 'y' => 67],
            ['key' => 'RB', 'position' => 'RB', 'x' => 88, 'y' => 64],
            ['key' => 'CM1', 'position' => 'CM', 'x' => 24, 'y' => 40],
            ['key' => 'CM2', 'position' => 'CM', 'x' => 50, 'y' => 43],
            ['key' => 'CM3', 'position' => 'CM', 'x' => 76, 'y' => 40],
            ['key' => 'LW', 'position' => 'LW', 'x' => 18, 'y' => 15],
            ['key' => 'ST', 'position' => 'ST', 'x' => 50, 'y' => 12],
            ['key' => 'RW', 'position' => 'RW', 'x' => 82, 'y' => 15],
        ];
    }

    public function inventory(int $players = 2): array
    {
        $counts = Card::where('domain', 'football')->selectRaw('position, COUNT(*) AS quantity')->groupBy('position')->pluck('quantity', 'position');

        return array_map(fn ($position, $base) => ['position' => $position, 'needed' => $needed = intdiv($base, 2) * $players,
            'count' => (int) ($counts[$position] ?? 0), 'missing' => max(0, $needed - (int) ($counts[$position] ?? 0))],
            array_keys(self::REQUIREMENTS), array_values(self::REQUIREMENTS));
    }

    public function key(): string
    {
        return 'football';
    }

    public function label(): string
    {
        return 'Football';
    }

    public function rulesText(): string
    {
        return 'Draft a 4-3-3: one GK, LB, two CBs, RB, three CMs, LW, ST and RW. A 15-second simulated match decides the winner using each line of your formation and its OVR. Icons have a special reveal, with no rating bonus.';
    }

    public function drawPair(array $excludeCardIds): array
    {
        $slot = $this->slots()[intdiv(count($excludeCardIds), 2)] ?? null;
        if (! $slot) {
            throw new DomainException('All formation slots are filled.');
        }

        return $this->drawCards($excludeCardIds, 2, $slot['position']);
    }

    public function drawCards(array $excludeCardIds, int $count, ?string $position = null): array
    {
        $cards = Card::where('domain', 'football')->where('position', $position)
            ->whereNotIn('id', $excludeCardIds)->inRandomOrder()->limit($count)->get();
        if ($cards->count() !== $count) {
            throw new DomainException('Add more '.$position.' players before starting this draft.');
        }

        return $cards->all();
    }

    public function powerOf(Card $card): int
    {
        return $card->power;
    }

    public function applyTwists(Collection $playerCards): int
    {
        return 0;
    }
}
