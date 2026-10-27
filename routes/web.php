<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminRoomController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CartaController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SongController;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// Everything a visitor sees (pages and the game APIs behind them) speaks their language.
Route::middleware(SetLocale::class)->group(function () {
    Route::get('/', [LibraryController::class, 'index'])->name('home');
    Route::post('/locale', [LibraryController::class, 'locale'])->name('locale')->middleware('throttle:30,1');
    Route::post('/find-table', [LibraryController::class, 'find'])->middleware('throttle:20,1');
    Route::get('/draft', [GameController::class, 'setup'])->name('draft');
    Route::get('/setup', [GameController::class, 'setup'])->name('setup');
    Route::prefix('game')->controller(GameController::class)->group(function () {
        Route::get('/state', 'state')->block();
        foreach (['start', 'ready', 'pick', 'reveal', 'next', 'finish', 'timeout', 'abandon'] as $action) {
            Route::post('/'.$action, $action)->block()->middleware('throttle:120,1');
        }
    });
    Route::get('/music', [SongController::class, 'playlist'])->middleware('throttle:60,1');
    Route::get('/carta', [CartaController::class, 'page'])->name('carta');
    Route::prefix('carta/rooms')->middleware('throttle:rooms')->controller(CartaController::class)->group(function () {
        Route::post('/', 'create')->block()->middleware('throttle:10,1');
        Route::post('/join', 'join')->block()->middleware('throttle:15,1');
        Route::get('/{room}', 'state')->block();
        Route::post('/{room}/{action}', 'action')->whereIn('action', ['ready', 'leave', 'start', 'rematch', 'play', 'draw', 'accept', 'pass'])->block();
    });
    Route::prefix('rooms')->middleware('throttle:rooms')->controller(RoomController::class)->group(function () {
        Route::post('/', 'create')->block()->middleware('throttle:10,1');
        Route::post('/join', 'join')->block()->middleware('throttle:15,1');
        Route::post('/broadcast-auth', 'broadcastAuth')->block();
        Route::get('/{room}', 'state')->block();
        Route::post('/{room}/settings', 'settings')->block();
        Route::post('/{room}/{action}', 'action')->whereIn('action', ['ready', 'editing', 'leave', 'start', 'pick', 'next', 'finish', 'timeout', 'replay', 'rematch'])->block();
    });
});

Route::get('/card-images/{filename}', [CardController::class, 'image']);
Route::get('/music/{song}/audio', [SongController::class, 'audio'])->whereNumber('song');

// Admin area: English only, no locale middleware.
Route::middleware(RequireAdmin::class)->group(function () {
    Route::get('/cards', [CardController::class, 'index'])->block();
    Route::post('/cards', [CardController::class, 'store'])->block()->middleware('throttle:60,1');
    Route::post('/cards/{card}', [CardController::class, 'update'])->block()->middleware('throttle:60,1');
    Route::get('/football/cards', [CardController::class, 'index'])->name('football.cards.index')->block();
    Route::post('/football/cards', [CardController::class, 'store'])->name('football.cards.store')->block()->middleware('throttle:60,1');
    Route::post('/football/cards/{card}', [CardController::class, 'update'])->name('football.cards.update')->block()->middleware('throttle:60,1');
});
Route::get('/admin/login', fn () => view('admin.login'))->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
Route::get('/admin', [GameController::class, 'setup'])->middleware(RequireAdmin::class)->name('admin');
Route::post('/admin/logout', [AdminController::class, 'logout'])->middleware(RequireAdmin::class);
Route::middleware(RequireAdmin::class)->prefix('admin/music')->controller(SongController::class)->group(function () {
    Route::get('/', 'index')->name('admin.music');
    Route::post('/', 'store')->name('admin.music.store')->middleware('throttle:20,1');
    Route::post('/{song}/move', 'move')->name('admin.music.move')->middleware('throttle:60,1');
    Route::delete('/{song}', 'destroy')->name('admin.music.destroy')->middleware('throttle:30,1');
});
Route::middleware(RequireAdmin::class)->prefix('admin/rooms')->controller(AdminRoomController::class)->group(function () {
    Route::get('/', 'index')->name('admin.rooms');
    Route::post('/{room}/ban', 'ban')->name('admin.rooms.ban')->middleware('throttle:30,1');
});
