@extends('layouts.app')
@section('content')
<div class="app-shell admin-rooms admin-music" x-data="{ fileName: '', playing: null, current: null,
    preview(id, src) {
        const player = this.$refs.player;
        if (this.playing === id) { player.pause(); return; }
        // State follows the audio element's own play/pause events, so switching songs cannot race.
        this.current = id; player.src = src; player.play().catch(() => { this.playing = null; });
    } }">
    @include('partials.admin-header', ['active' => 'music'])
    <main>
        <div class="admin-page-head"><div><h1>Music</h1><p>Background songs players switch between from the music button. They play in this order.</p></div><span class="music-count">{{ $songs->count() }} {{ Str::plural('song', $songs->count()) }}</span></div>
        @if(session('status'))<div class="status-banner" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error-banner" role="alert">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('admin.music.store') }}" enctype="multipart/form-data" class="song-upload">@csrf
            <label class="song-drop" :class="{ chosen: fileName }">
                <input type="file" name="file" accept=".mp3,.ogg,.oga,.wav,audio/mpeg,audio/ogg,audio/wav" required @change="fileName = $event.target.files[0]?.name || ''">
                <i data-icon="cloud-arrow-up"></i>
                <strong x-text="fileName || 'Choose an audio file'">Choose an audio file</strong>
                <small>MP3, OGG or WAV · up to 20 MB</small>
            </label>
            <div class="song-upload-fields">
                <label>Song title<input name="title" value="{{ old('title') }}" maxlength="80" placeholder="e.g. Arena theme" required></label>
                <button class="primary-button"><i data-icon="cloud-arrow-up"></i> Upload song</button>
            </div>
        </form>

        <audio x-ref="player" preload="none" @play="playing = current" @pause="playing = null" @ended="playing = null"></audio>
        @if($songs->isNotEmpty())
            <ol class="song-list">
                @foreach($songs as $song)
                    <li class="song-item" :class="{ playing: playing === {{ $song->id }} }">
                        <span class="song-number">{{ $loop->iteration }}</span>
                        <button type="button" class="icon-button song-preview" @click="preview({{ $song->id }}, '/music/{{ $song->id }}/audio')" :aria-label="(playing === {{ $song->id }} ? 'Pause ' : 'Play ') + @js($song->title)"><span x-show="playing !== {{ $song->id }}"><i data-icon="play-fill"></i></span><span x-show="playing === {{ $song->id }}" x-cloak><i data-icon="pause-fill"></i></span></button>
                        <div class="song-info"><strong>{{ $song->title }}</strong><small>Added {{ $song->created_at->diffForHumans() }}</small></div>
                        <div class="song-actions">
                            <form method="POST" action="{{ route('admin.music.move', $song) }}">@csrf<input type="hidden" name="direction" value="up"><button class="icon-button" @disabled($loop->first) aria-label="Move {{ $song->title }} up" title="Move up"><i data-icon="arrow-up"></i></button></form>
                            <form method="POST" action="{{ route('admin.music.move', $song) }}">@csrf<input type="hidden" name="direction" value="down"><button class="icon-button" @disabled($loop->last) aria-label="Move {{ $song->title }} down" title="Move down"><i data-icon="arrow-down"></i></button></form>
                            <form method="POST" action="{{ route('admin.music.destroy', $song) }}" onsubmit="return confirm('Remove this song from the playlist?')">@csrf @method('DELETE')<button class="icon-button song-remove" aria-label="Remove {{ $song->title }}" title="Remove"><i data-icon="trash3"></i></button></form>
                        </div>
                    </li>
                @endforeach
            </ol>
        @else
            <div class="admin-empty"><i data-icon="music-note-list"></i><p>No songs yet. Upload the first one above and players will see it in the music menu.</p></div>
        @endif
    </main>
</div>
@endsection
