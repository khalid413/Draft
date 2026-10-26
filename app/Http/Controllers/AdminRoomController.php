<?php

namespace App\Http\Controllers;

use App\Events\RoomChanged;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class AdminRoomController extends Controller
{
    public function index()
    {
        $rooms = Room::orderByRaw("CASE status WHEN 'playing' THEN 0 WHEN 'lobby' THEN 1 WHEN 'finished' THEN 2 ELSE 3 END")
            ->latest('updated_at')->limit(200)->get();

        return view('admin.rooms', ['rooms' => $rooms]);
    }

    /** Ban closes the room for every seat; clients resync, receive 410 and return to the menu. */
    public function ban(Room $room)
    {
        $room = DB::transaction(function () use ($room) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            if ($room->status !== 'banned') {
                $room->update(['status' => 'banned', 'version' => $room->version + 1]);
            }

            return $room;
        }, 3);
        try {
            event(new RoomChanged($room->id, $room->version));
        } catch (\Throwable $error) {
            report($error);
        } // Polling clients still pick up the ban within seconds.

        return redirect('/admin/rooms')->with('status', 'Room '.$room->code.' was banned.');
    }
}
