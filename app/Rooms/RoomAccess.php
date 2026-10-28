<?php

namespace App\Rooms;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomAccess
{
    public function index(Request $request, Room $room): int
    {
        abort_if($room->status === 'banned', 410, __('This room was closed by an administrator.'));
        abort_if($room->isClosed(), 410, __('This room has closed or expired.'));
        $token = $request->session()->get('room_tokens.'.$room->id);
        abort_unless(is_string($token), 403, __('Join this room first.'));
        foreach ($room->members as $index => $member) {
            if (hash_equals($member['token_hash'], hash('sha256', $token)) && ! ($member['left'] ?? false)) {
                return $index;
            }
        }
        // Seats the host removed: their browser gets told why, and cannot sit back down with the same session.
        abort_if(in_array(hash('sha256', $token), $room->settings['kicked'] ?? [], true), 403, __('The host removed you from this table.'));
        abort(403, __('Your seat is no longer in this room.'));
    }
}
