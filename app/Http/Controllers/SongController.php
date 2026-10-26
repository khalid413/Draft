<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Background music: players read the playlist, only the administrator changes it. */
class SongController extends Controller
{
    public function playlist()
    {
        return response()->json(Song::orderBy('position')->orderBy('id')->get()->map->track())
            ->header('Cache-Control', 'no-store');
    }

    public function audio(Song $song)
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($song->file_path), 404);

        // BinaryFileResponse answers Range requests, so the browser can seek and stream.
        return response()->file($disk->path($song->file_path), [
            'Content-Type' => $song->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function index()
    {
        return view('admin.music', ['songs' => Song::orderBy('position')->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $request->merge(['title' => is_string($request->input('title')) ? trim($request->input('title')) : $request->input('title')]);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:1', 'max:80'],
            // audio/mpeg is guessed as "mpga", so both extensions are listed for MP3.
            'file' => ['required', 'file', 'mimes:mp3,mpga,ogg,oga,wav', 'max:20480'],
        ]);
        $file = $request->file('file');
        $path = $file->store('music', 'local');
        abort_unless($path, 500, 'Unable to store the song.');
        try {
            Song::create(['title' => $data['title'], 'file_path' => $path, 'mime' => $file->getMimeType() ?: 'audio/mpeg',
                'position' => (Song::max('position') ?? 0) + 1]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return redirect('/admin/music')->with('status', '"'.$data['title'].'" was added to the playlist.');
    }

    public function move(Request $request, Song $song)
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        DB::transaction(function () use ($song, $direction) {
            $songs = Song::orderBy('position')->orderBy('id')->lockForUpdate()->get()->values();
            $from = $songs->search(fn ($item) => $item->id === $song->id);
            $to = $direction === 'up' ? $from - 1 : $from + 1;
            if ($to < 0 || $to >= $songs->count()) {
                return;
            }
            $order = $songs->all();
            [$order[$from], $order[$to]] = [$order[$to], $order[$from]];
            foreach ($order as $position => $item) {
                $item->update(['position' => $position + 1]);
            }
        });

        return redirect('/admin/music');
    }

    public function destroy(Song $song)
    {
        $song->delete();
        Storage::disk('local')->delete($song->file_path);

        return redirect('/admin/music')->with('status', '"'.$song->title.'" was removed from the playlist.');
    }
}
