<?php

namespace App\Uno;

/**
 * The full, private state of one UNO match. Only the server ever sees it whole;
 * UnoView decides what each player may see.
 */
final class UnoState
{
    public const WAITING = 'WAITING';

    public const PLAYING = 'PLAYING';

    public const FINISHED = 'FINISHED';

    public const CANCELLED = 'CANCELLED';

    public const ACTIVE = 'ACTIVE';

    public const DONE = 'FINISHED';

    public const LOSER = 'LOSER';

    public const NONE = 'NONE';

    public const DRAW_TWO = 'DRAW_TWO';

    public const DRAW_FOUR = 'DRAW_FOUR';

    /** Stacking rules a room can choose: none (official), +2 on +2 and +4 on +4, or +2 and +4 on each other. */
    public const STACK_OFF = 'off';

    public const STACK_SAME = 'same';

    public const STACK_MIXED = 'mixed';

    public const STACKING = [self::STACK_OFF, self::STACK_SAME, self::STACK_MIXED];

    /**
     * @param  array<int, array{name: string, hand: list<string>, status: string, rank: ?int, uno: bool}>  $players  keyed by seat
     * @param  list<string>  $drawPile  last element is drawn next
     * @param  list<string>  $discardPile  last element is the face-up top card
     * @param  ?string  $drawnCard  card drawn this turn that its owner may still play (only that card)
     * @param  ?int  $unoExposed  seat left on one card without calling UNO; any opponent may catch it until the next move
     * @param  list<array<string, mixed>>  $events  moves produced by the current action, persisted as history
     */
    public function __construct(
        public string $status = self::WAITING,
        public array $players = [],
        public array $drawPile = [],
        public array $discardPile = [],
        public ?int $currentSeat = null,
        public int $direction = 1,
        public ?string $activeColor = null,
        public string $pendingEffect = self::NONE,
        public int $pendingDraw = 0,
        public ?string $drawnCard = null,
        public ?int $unoExposed = null,
        public string $stacking = self::STACK_OFF,
        public int $turnNumber = 0,
        public array $events = [],
    ) {}

    /** Reads a room's stacking setting; tables opened before the three-way choice stored true/false. */
    public static function stackingOf(mixed $setting): string
    {
        return match (true) {
            in_array($setting, self::STACKING, true) => $setting,
            $setting === true => self::STACK_SAME,
            default => self::STACK_OFF,
        };
    }

    public function topCard(): UnoCard
    {
        return UnoCard::fromId($this->discardPile[array_key_last($this->discardPile)]);
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
