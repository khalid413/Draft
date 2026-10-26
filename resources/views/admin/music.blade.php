@extends('layouts.app')
@section('content')
<div class="app-shell admin-rooms admin-music">
    <header class="site-header"><a href="/" class="wordmark"><span class="brand-mark"><i data-lucide="layers-2"></i></span>DRAFT<span>DUEL</span></a><nav aria-label="Admin controls" class="admin-nav"><span class="eyebrow">ADMIN</span><a href="/admin" class="text-link">Cards</a><a href="{{ route('admin.rooms') }}" class="text-link">Rooms</a><a href="{{ route('admin.music') }}" class="text-link" aria-current="page">Music</a><form method="POST" action="/admin/logout">@csrf<button class="secondary-button">Sign out</button></form></nav></header>
    <main>
        <div class="admin-rooms-head"><h1>Music</h1><p>Background songs players can switch between. They play in this order.</p></div>
        @if(session('status'))<div class="status-banner" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error-banner" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('admin.music.store') }}" enctype="multipart/form-data" class="song-upload">@csrf
            <label>Song title<input name="title" value="{{ old('title') }}" maxlength="80" required></label>
            <label>Audio file <small>MP3, OGG or WAV · up to 20 MB</small><input type="file" name="file" accept=".mp3,.ogg,.oga,.wav,audio/mpeg,audio/ogg,audio/wav" required></label>
            <button class="primary-button">Upload song</button>
        </form>
        @forelse($songs as $song)
            <article class="room-row song-row">
                <div class="room-row-main"><small>#{{ $loop->iteration }}</small><strong>{{ $song->title }}</strong></div>
                <audio controls preload="none" src="/music/{{ $song->id }}/audio"></audio>
                <div class="song-actions">
                    <form method="POST" action="{{ route('admin.music.move', $song) }}">@csrf<input type="hidden" name="direction" value="up"><button class="secondary-button" @disabled($loop->first) aria-label="Move {{ $song->title }} up">↑</button></form>
                    <form method="POST" action="{{ route('admin.music.move', $song) }}">@csrf<input type="hidden" name="direction" value="down"><button class="secondary-button" @disabled($loop->last) aria-label="Move {{ $song->title }} down">↓</button></form>
                    <form method="POST" action="{{ route('admin.music.destroy', $song) }}" onsubmit="return confirm('Remove this song from the playlist?')">@csrf @method('DELETE')<button class="danger-button">Remove</button></form>
                </div>
            </article>
        @empty
            <p class="admin-rooms-empty">No songs yet. Upload the first one above and players will see it in the music menu.</p>
        @endforelse
    </main>
</div>
@endsection
