<?php

namespace App\Rooms;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomAccess
{
    public function index(Request $request, Room $room): int
    {
        abort_if($room->expires_at->isPast() || $room->status === 'closed', 410, 'This room has closed or expired.');
        $token = $request->session()->get('room_tokens.'.$room->id);
        abort_unless(is_string($token), 403, 'Join this room first.');
        foreach ($room->members as $index => $member) {
            if (hash_equals($member['token_hash'], hash('sha256', $token))) {
                return $index;
            }
        }
        abort(403, 'Your seat is no longer in this room.');
    }
}
