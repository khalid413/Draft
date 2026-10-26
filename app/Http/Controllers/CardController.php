<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCardRequest;
use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Administrator-only catalog; running games keep their frozen snapshots. */
class CardController extends Controller
{
    private function domain(Request $request): string
    {
        return $request->routeIs('football.cards.*') ? 'football' : 'custom';
    }

    public function index(Request $request)
    {

        return response()->json(Card::where('domain', $this->domain($request))->orderByDesc('id')->get()->map->snapshot())
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(SaveCardRequest $request)
    {
        return $this->save($request, new Card(['domain' => $this->domain($request), 'series' => '', 'meta' => ['custom' => true]]));
    }

    public function update(SaveCardRequest $request, Card $card)
    {
        abort_unless($card->domain === $this->domain($request), 404);

        return $this->save($request, $card);
    }

    private function save(SaveCardRequest $request, Card $card)
    {

        $path = null;
        try {
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('cards', 'local');
                if (! $path) {
                    throw new \RuntimeException('Unable to store the image.');
                }
                $card->image_path = '/card-images/'.basename($path);
            }
            $card->name = $request->validated('name');
            $card->power = $request->integer($card->domain === 'football' ? 'ovr' : 'power');
            if ($card->domain === 'custom' && $request->exists('series')) {
                $card->series = trim($request->validated('series') ?? '');
            }
            if ($card->domain === 'football') {
                $card->position = $request->validated('position');
                $card->is_icon = $request->boolean('is_icon');
            }
            $card->save();
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        // Old images stay available to immutable snapshots in saved games.
        return response()->json($card->snapshot(), $card->wasRecentlyCreated ? 201 : 200);
    }

    public function image(string $filename)
    {
        abort_unless(preg_match('/\A[a-zA-Z0-9]{40}\.(jpg|jpeg|png|webp)\z/', $filename), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists('cards/'.$filename), 404);

        return response()->file($disk->path('cards/'.$filename), [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
