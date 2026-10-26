<?php

namespace App\Http\Controllers;

use App\Events\RoomChanged;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRoomController extends Controller
{
    /** Filter buckets; closed also covers banned and expired rooms. */
    private const FILTERS = ['all' => 'All', 'lobby' => 'Lobby', 'playing' => 'Playing', 'finished' => 'Finished', 'closed' => 'Closed'];

    public function index(Request $request)
    {
        $filter = array_key_exists($request->query('status'), self::FILTERS) ? $request->query('status') : 'all';
        // Status depends on expiry time, so it is resolved per room rather than in SQL.
        $rooms = Room::orderByRaw("CASE status WHEN 'playing' THEN 0 WHEN 'lobby' THEN 1 WHEN 'finished' THEN 2 ELSE 3 END")
            ->latest('updated_at')->limit(500)->get();
        $buckets = $rooms->mapWithKeys(fn (Room $room) => [$room->id => in_array($room->adminState(), ['closed', 'banned', 'expired'], true) ? 'closed' : $room->adminState()]);
        $counts = $buckets->countBy();
        $filters = collect(self::FILTERS)->map(fn ($label, $key) => ['label' => $label,
            'count' => $key === 'all' ? $rooms->count() : ($counts[$key] ?? 0)]);

        // Every room is rendered once; the page switches filters in the browser without reloading.
        return view('admin.rooms', ['rooms' => $rooms, 'buckets' => $buckets, 'filters' => $filters, 'filter' => $filter]);
    }

    /** Ban closes the room for every seat; clients resync, receive 410 and return to the menu. */
    public function ban(Request $request, Room $room)
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

        // Stay on the filter the admin was looking at.
        $filter = $request->input('status');
        $back = in_array($filter, ['lobby', 'playing', 'finished', 'closed'], true) ? route('admin.rooms', ['status' => $filter]) : route('admin.rooms');

        return redirect($back)->with('status', 'Room '.$room->code.' was banned.');
    }
}
