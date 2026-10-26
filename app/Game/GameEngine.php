<?php

namespace App\Game;

use App\Domains\CardPoolDomain;
use App\Domains\CustomDomain;
use App\Domains\DomainRegistry;
use App\Domains\FormationDomainContract;
use App\Enums\GamePhase;
use App\Models\Card;
use Closure;
use DomainException;

/** Transport-free state machine. Catalog access lives behind DomainContract. */
final class GameEngine
{
    public function __construct(private DomainRegistry $domains, private ?Closure $clock = null) {}

    private function now(): int
    {
        return $this->clock ? ($this->clock)() : time();
    }

    private function requirePhase(GameState $state, GamePhase $phase): void
    {
        if ($state->phase !== $phase) {
            throw new DomainException('That action is not available in this phase.');
        }
    }

    public function startGame(array $names, string $domainKey, int $rounds = 5, bool $timer = false, ?string $catalog = null): GameState
    {
        $domain = $this->domains->get($domainKey);
        $catalog = trim($catalog ?? '') ?: null;
        if ($catalog !== null && (! $domain instanceof CustomDomain || mb_strlen($catalog) > 60)) {
            throw new DomainException('Choose a valid character collection.');
        }
        if ($domain instanceof CustomDomain) {
            $domain = $domain->forCatalog($catalog);
        }
        $formation = $domain instanceof FormationDomainContract ? $domain->slots() : [];
        if ($formation) {
            $rounds = count($formation);
        }
        $names = array_map('trim', $names);
        if (count($names) < 2 || count($names) > 4 || count(array_unique(array_map('mb_strtolower', $names))) !== count($names)
            || (! $formation && ! in_array($rounds, [3, 5, 7], true))) {
            throw new DomainException('Use distinct player names and 3, 5 or 7 rounds.');
        }
        foreach ($names as $name) {
            if (mb_strlen($name) < 2 || mb_strlen($name) > 20) {
                throw new DomainException('Player names must contain 2–20 characters.');
            }
        }

        // Freeze the entire game deck. Editing the library cannot change an ongoing duel.
        $deck = [];
        $schedule = DraftSchedule::build(count($names), $rounds);
        foreach ($schedule as $turn) {
            $round = $turn['slotIndex'];
            $pair = ($formation || $turn['count'] === 3) && $domain instanceof CardPoolDomain
                ? $domain->drawCards(array_column($deck, 'id'), $turn['count'], $formation[$round]['position'] ?? null)
                : $domain->drawPair(array_column($deck, 'id'));
            if (count($pair) !== $turn['count'] || count(array_unique(array_map(fn ($c) => $c->id, $pair))) !== count($pair)
                || array_intersect(array_column($deck, 'id'), array_map(fn ($c) => $c->id, $pair))) {
                throw new DomainException('Domain supplied an invalid pair.');
            }
            foreach ($pair as $card) {
                if ($formation && $card->position !== $formation[$round]['position']) {
                    throw new DomainException('Domain supplied a player in the wrong position.');
                }
                $deck[] = array_replace($card->snapshot(), ['power' => $domain->powerOf($card)],
                    $formation ? ['slot' => $formation[$round]['key']] : []);
            }
        }

        return new GameState(players: array_map(fn ($name) => ['name' => $name, 'cards' => []], $names),
            domainKey: $domainKey, phase: GamePhase::PassDevice, seed: bin2hex(random_bytes(32)), roundLimit: count($schedule), timerEnabled: $timer, deck: $deck, formation: $formation, catalog: $catalog, schedule: $schedule, cardsPerPlayer: $rounds);
    }

    public function drawRound(GameState $state): GameState
    {
        $this->requirePhase($state, GamePhase::PassDevice);
        if ($state->currentRound >= $state->roundLimit) {
            throw new DomainException('All rounds have been played.');
        }
        $exclude = [];
        foreach ($state->rounds as $round) {
            foreach ($round['cards'] as $card) {
                $exclude[] = $card['id'];
            }
        }
        $domain = $this->domains->get($state->domainKey);
        $turn = $state->schedule[$state->currentRound] ?? ['offset' => $state->currentRound * 2, 'count' => 2];
        $pair = $state->deck
            ? array_map(fn ($card) => new Card($card), array_slice($state->deck, $turn['offset'], $turn['count']))
            : $domain->drawPair($exclude); // Resume games created before deck snapshots were added.
        if (count($pair) !== $turn['count'] || $pair[0]->id === $pair[1]->id || array_intersect($exclude, [$pair[0]->id, $pair[1]->id])) {
            throw new DomainException('Domain supplied an invalid pair.');
        }
        $cards = $state->deck ? array_slice($state->deck, $turn['offset'], $turn['count']) : array_map(function (Card $card) use ($domain) {
            return array_replace($card->snapshot(), ['power' => $domain->powerOf($card)]);
        }, $pair);
        $state->rounds[] = ['cards' => $cards, 'tokens' => [bin2hex(random_bytes(24)), bin2hex(random_bytes(24))],
            'activePlayer' => $state->startingPlayerIndex, 'recipientPlayer' => $turn['recipientPlayer'] ?? ($state->startingPlayerIndex + 1) % count($state->players),
            'extraPlayer' => $turn['extraPlayer'] ?? null, 'slotIndex' => $turn['slotIndex'] ?? $state->currentRound,
            'pickedSlot' => null, 'revealed' => false];
        $state->phase = GamePhase::Picking;
        $state->pickDeadline = $state->timerEnabled ? $this->now() + 30 : null;

        return $state;
    }

    /** cardId is an opaque per-round selection token, never a hidden catalog ID. */
    public function pick(GameState $state, int $playerIndex, string $cardId): GameState
    {
        $this->requirePhase($state, GamePhase::Picking);
        $round = &$state->rounds[$state->currentRound];
        if ($round['activePlayer'] !== $playerIndex || $round['pickedSlot'] !== null) {
            throw new DomainException('Only the active player may pick, once per round.');
        }
        $slot = array_search($cardId, $round['tokens'], true);
        if ($slot === false) {
            throw new DomainException('Select a card from this round.');
        }
        if ($state->pickDeadline !== null && $this->now() >= $state->pickDeadline) {
            return $this->timeout($state);
        }

        return $this->recordPick($state, $slot);
    }

    public function timeout(GameState $state): GameState
    {
        $this->requirePhase($state, GamePhase::Picking);
        if ($state->pickDeadline === null || $this->now() < $state->pickDeadline) {
            throw new DomainException('The pick timer has not expired.');
        }

        return $this->autoPick($state);
    }

    /** Random pick for an expired timer or a seat whose player has left the room. */
    public function autoPick(GameState $state): GameState
    {
        $this->requirePhase($state, GamePhase::Picking);

        return $this->recordPick($state, random_int(0, 1));
    }

    private function recordPick(GameState $state, int $slot): GameState
    {
        $round = &$state->rounds[$state->currentRound];
        if ($round['pickedSlot'] !== null) {
            throw new DomainException('A pick is already locked in.');
        }
        $round['pickedSlot'] = $slot;
        $state->picks[] = ['round' => $state->currentRound, 'playerIndex' => $round['activePlayer'], 'cardId' => $round['cards'][$slot]['id']];

        // Keep Picking until revealRound: a locked pick still cannot expose the hidden card.
        return $state;
    }

    public function revealRound(GameState $state): GameState
    {
        $this->requirePhase($state, GamePhase::Picking);
        $round = &$state->rounds[$state->currentRound];
        if ($round['pickedSlot'] === null) {
            throw new DomainException('Lock a pick before revealing.');
        }
        foreach ([$round['activePlayer'] => $round['pickedSlot'], $round['recipientPlayer'] => 1 - $round['pickedSlot']] as $player => $slot) {
            $state->players[$player]['cards'][] = $round['cards'][$slot];
        }
        if (($round['extraPlayer'] ?? null) !== null) {
            $state->players[$round['extraPlayer']]['cards'][] = $round['cards'][2];
        }
        $round['revealed'] = true;
        $state->pickDeadline = null;
        $state->phase = GamePhase::Revealing;

        return $state;
    }

    public function nextRound(GameState $state): GameState
    {
        $this->requirePhase($state, GamePhase::Revealing);
        if ($state->currentRound + 1 >= $state->roundLimit) {
            throw new DomainException('Finish the game to see the results.');
        }
        $state->currentRound++;
        $state->startingPlayerIndex = $state->schedule[$state->currentRound]['activePlayer'] ?? $state->currentRound % count($state->players);
        $state->phase = GamePhase::PassDevice;

        return $state;
    }

    public function finish(GameState $state): GameState
    {
        $this->requirePhase($state, GamePhase::Revealing);
        if ($state->currentRound + 1 !== $state->roundLimit) {
            throw new DomainException('Play every round before finishing.');
        }
        $domain = $this->domains->get($state->domainKey);
        $scores = [];
        foreach ($state->players as $player) {
            if ($state->formation) {
                $slots = array_column($player['cards'], 'slot');
                if (count($slots) !== count($state->formation) || count(array_unique($slots)) !== count($slots)
                    || array_diff(array_column($state->formation, 'key'), $slots)) {
                    throw new DomainException('All teams must have a complete formation.');
                }
            }
            $cards = collect($player['cards'])->map(fn ($card) => new Card($card));
            $base = $cards->sum('power');
            $bonus = $domain->applyTwists($cards);
            $scores[] = array_merge(['base' => $base, 'bonus' => $bonus, 'total' => $base + $bonus],
                $state->formation ? ['ovr' => round($base / count($state->formation), 1)] : []);
        }
        $max = max(array_column($scores, 'total'));
        $state->results = ['scores' => $scores, 'winners' => array_keys(array_filter($scores, fn ($score) => $score['total'] === $max))];
        if ($state->domainKey === 'football' && count($state->players) > 2) {
            $league = (new FootballLeague)->simulate($state->players, $state->seed);
            $state->results['league'] = $league;
            $state->results['winners'] = $league['winners'];
        } elseif ($state->domainKey === 'football') {
            $match = (new FootballMatchSimulator)->simulate($state->players, $state->seed);
            $state->results['match'] = $match;
            $state->results['winners'] = $match['winners'];
        }
        $state->phase = GamePhase::Finished;

        return $state;
    }
}
