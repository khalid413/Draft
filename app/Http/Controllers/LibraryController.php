<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\Room;
use Illuminate\Http\Request;

/** Glisa's front page: the library of games, plus one box that routes any table code to its game. */
class LibraryController extends Controller
{
    private const GAME_PATHS = ['draft' => '/draft', 'carta' => '/carta'];

    public function index(Request $request)
    {
        // Invitations shared before the library existed pointed at /?join=CODE (Draft Duel rooms).
        if (is_string($request->query('join'))) {
            return redirect('/draft?join='.urlencode($request->query('join')));
        }

        return view('library');
    }

    /** Saves the visitor's language for a year; every game page and message then follows it. */
    public function locale(Request $request)
    {
        $locale = $request->validate(['locale' => ['required', 'in:'.implode(',', SetLocale::LOCALES)]])['locale'];

        return redirect('/')->withCookie(cookie(SetLocale::COOKIE, $locale, 60 * 24 * 365));
    }

    /** Tells the browser which game a code belongs to, so the join form opens the right table. */
    public function find(Request $request)
    {
        $request->merge(['code' => is_string($request->input('code')) ? strtoupper(trim($request->input('code'))) : $request->input('code')]);
        $code = $request->validate(['code' => 'required|string|size:8|alpha_num:ascii'])['code'];
        $room = Room::where('code', $code)->first();
        abort_if(! $room || $room->isClosed() || ! isset(self::GAME_PATHS[$room->game_type]), 404, __('No open table uses that code.'));

        return response()->json(['url' => self::GAME_PATHS[$room->game_type].'?join='.$code]);
    }
}
