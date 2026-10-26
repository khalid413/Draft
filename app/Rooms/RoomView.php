<?php

namespace App\Rooms;

use App\Game\GameState;
use App\Game\PublicGameView;
use App\Models\Game;
use App\Models\Room;

class RoomView
{
    public function transform(Room $room, int $viewer): array
    {
        $game = $room->game_uuid ? Game::findOrFail($room->game_uuid) : null;
        $state = $game ? (new PublicGameView)->transform(GameState::fromArray($game->state), $game->version) : null;
        if ($state) {
            $state['viewerIndex'] = $viewer;
            $state['online'] = true;
            if ($state['phase'] === 'picking' && $viewer !== $state['activePlayer']) {
                $state['cards'] = [];
                $state['pickedSlot'] = null;
            }
        }

        return ['id' => $room->id, 'code' => $room->code, 'status' => $room->status, 'version' => $room->version,
            'settings' => $room->settings, 'viewerIndex' => $viewer, 'host' => $room->host, 'isHost' => $viewer === $room->host,
            'members' => array_map(fn ($member) => ['name' => $member['name'], 'ready' => $member['ready'], 'left' => $member['left'] ?? false], $room->members),
            'game' => $state, 'playback' => $room->playback, 'serverTimeMs' => (int) round(microtime(true) * 1000)];
    }
}
