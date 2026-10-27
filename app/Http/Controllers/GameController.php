<?php

namespace App\Http\Controllers;

use App\Domains\DomainRegistry;
use App\Domains\FootballDomain;
use App\Enums\GamePhase;
use App\Game\GameEngine;
use App\Game\GameState;
use App\Game\GameStore;
use App\Game\PublicGameView;
use App\Http\Requests\GameActionRequest;
use App\Http\Requests\PickRequest;
use App\Http\Requests\StartGameRequest;
use App\Models\Card;
use App\Models\Game;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function __construct(private GameEngine $engine, private GameStore $store, private PublicGameView $view) {}

    public function setup(DomainRegistry $domains)
    {
        return view(request()->routeIs('admin') ? 'admin.cards' : 'game', ['domains' => $domains->catalog(), 'customCardCount' => Card::where('domain', 'custom')->count(),
            'footballInventory' => (new FootballDomain)->inventory(),
            'catalogs' => Card::where('domain', 'custom')->where('series', '!=', '')->selectRaw('series as name, COUNT(*) as count')->groupBy('series')->orderBy('series')->get()]);
    }

    private function current(Request $request): Game
    {
        return Game::whereKey($request->session()->get('game_uuid', ''))->firstOrFail();
    }

    private function response(Game $game)
    {
        return response()->json($this->view->transform(GameState::fromArray($game->state), $game->version))
            ->header('Cache-Control', 'no-store, private');
    }

    public function start(StartGameRequest $request)
    {
        $existing = Game::find($request->session()->get('game_uuid', ''));
        if ($existing && $existing->status !== 'finished' && ! $request->boolean('replace')) {
            throw new DomainException(__('Confirm abandoning the current game first.'));
        }
        $state = $this->engine->startGame($request->validated('players'), $request->validated('domain'),
            $request->integer('rounds'), $request->boolean('timer'), $request->validated('catalog'));
        $game = Game::create(['uuid' => (string) Str::uuid(), 'domain' => $state->domainKey,
            'state' => $state->jsonSerialize(), 'status' => $state->phase->value]);
        $request->session()->put('game_uuid', $game->uuid);

        return $this->response($game->refresh());
    }

    public function state(Request $request)
    {
        $game = Game::find($request->session()->get('game_uuid', ''));
        if (! $game) {
            return response()->json()->setData(null)->header('Cache-Control', 'no-store, private');
        }
        $state = GameState::fromArray($game->state);
        if ($state->phase === GamePhase::Picking && $state->pickDeadline !== null && time() >= $state->pickDeadline
            && $state->rounds[$state->currentRound]['pickedSlot'] === null) {
            $game = $this->store->mutate($game->uuid, $game->version, fn ($state) => $this->engine->revealRound($this->engine->timeout($state)));
        }

        return $this->response($game);
    }

    private function action(GameActionRequest $request, \Closure $action)
    {
        $game = $this->current($request);

        return $this->response($this->store->mutate($game->uuid, $request->integer('version'), $action));
    }

    public function abandon(GameActionRequest $request)
    {
        $game = $this->current($request);
        $changed = Game::whereKey($game->uuid)->where('version', $request->integer('version'))
            ->update(['status' => 'abandoned', 'version' => $request->integer('version') + 1]);
        if (! $changed) {
            throw new DomainException(__('The game has changed. Refresh and try again.'));
        }
        $request->session()->forget('game_uuid');

        return response()->json()->setData(null)->header('Cache-Control', 'no-store, private');
    }

    public function ready(GameActionRequest $request)
    {
        return $this->action($request, fn ($state) => $this->engine->drawRound($state));
    }

    public function pick(PickRequest $request)
    {
        return $this->action($request, fn ($state) => $this->engine->pick($state, $request->integer('playerIndex'), $request->validated('cardId')));
    }

    public function reveal(GameActionRequest $request)
    {
        return $this->action($request, fn ($state) => $this->engine->revealRound($state));
    }

    public function next(GameActionRequest $request)
    {
        return $this->action($request, fn ($state) => $this->engine->nextRound($state));
    }

    public function finish(GameActionRequest $request)
    {
        return $this->action($request, fn ($state) => $this->engine->finish($state));
    }

    public function timeout(GameActionRequest $request)
    {
        return $this->action($request, fn ($state) => $this->engine->revealRound($this->engine->timeout($state)));
    }
}
