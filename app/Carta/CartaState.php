<?php

namespace App\Carta;

/**
 * The full, private state of one Carta match. Only the server ever sees it whole;
 * CartaView decides what each player may see.
 */
final class CartaState
{
    public const WAITING = 'WAITING';

    public const PLAYING = 'PLAYING';

    public const FINISHED = 'FINISHED';

    public const CANCELLED = 'CANCELLED';

    public const ACTIVE = 'ACTIVE';

    public const DONE = 'FINISHED';

    public const LOSER = 'LOSER';

    public const NONE = 'NONE';

    public const SKIP = 'SKIP';

    public const DRAW_TWO = 'DRAW_TWO';

    /**
     * @param  array<int, array{name: string, hand: list<string>, status: string, rank: ?int}>  $players  keyed by seat
     * @param  list<string>  $drawPile  last element is drawn next
     * @param  list<string>  $discardPile  last element is the face-up top card
     * @param  list<array<string, mixed>>  $events  moves produced by the current action, persisted as history
     */
    public function __construct(
        public string $status = self::WAITING,
        public array $players = [],
        public array $drawPile = [],
        public array $discardPile = [],
        public ?int $currentSeat = null,
        public ?string $activeSuit = null,
        public string $pendingEffect = self::NONE,
        public int $pendingDraw = 0,
        public int $turnNumber = 0,
        public array $events = [],
    ) {}

    public function topCard(): Card
    {
        return Card::fromId($this->discardPile[array_key_last($this->discardPile)]);
    }

    /** @return list<int> seats still holding cards and taking turns */
    public function activeSeats(): array
    {
        return array_keys(array_filter($this->players, fn ($player) => $player['status'] === self::ACTIVE));
    }

    public function isActive(int $seat): bool
    {
        return ($this->players[$seat]['status'] ?? null) === self::ACTIVE;
    }

    /** Every card id currently anywhere in the match: hands, draw pile and discard pile. */
    public function allCardIds(): array
    {
        return array_merge($this->drawPile, $this->discardPile, ...array_map(fn ($player) => $player['hand'], array_values($this->players)));
    }
}
