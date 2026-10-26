@extends('layouts.app')
@section('content')
<div class="app-shell admin-rooms">
    <header class="site-header"><a href="/" class="wordmark"><span class="brand-mark"><i data-lucide="layers-2"></i></span>DRAFT<span>DUEL</span></a><nav aria-label="Admin controls" class="admin-nav"><span class="eyebrow">ADMIN</span><a href="/admin" class="text-link">Cards</a><a href="{{ route('admin.rooms') }}" class="text-link" aria-current="page">Rooms</a><a href="{{ route('admin.music') }}" class="text-link">Music</a><form method="POST" action="/admin/logout">@csrf<button class="secondary-button">Sign out</button></form></nav></header>
    <main>
        <div class="admin-rooms-head"><h1>Rooms</h1><p>Every online room, newest activity first. Ban closes a room for all of its players.</p></div>
        @if(session('status'))<div class="status-banner" role="status">{{ session('status') }}</div>@endif
        @forelse($rooms as $room)
            @php
                $state = $room->status === 'banned' ? 'banned' : ($room->isClosed() ? ($room->status === 'closed' ? 'closed' : 'expired') : $room->status);
                $s = $room->settings;
            @endphp
            <article class="room-row">
                <div class="room-row-main">
                    <strong class="room-row-code">{{ $room->code }}</strong>
                    <span class="room-status room-status-{{ $state }}">{{ ucfirst($state) }}</span>
                    <small>{{ ($s['domain'] ?? '') === 'football' ? 'Football 4-3-3' : (($s['catalog'] ?? null) ?: 'All characters').' · '.($s['rounds'] ?? '?').' rounds' }} · {{ count($room->members) }}/{{ $s['capacity'] ?? '?' }} seats{{ ($s['timer'] ?? false) ? ' · 30s timer' : '' }}</small>
                </div>
                <ul class="room-row-players" aria-label="Players in room {{ $room->code }}">
                    @foreach($room->members as $index => $member)
                        <li @class(['left' => $member['left'] ?? false])>{{ $member['name'] }}@if($index === $room->host)<span class="host-tag">Host</span>@endif<small>{{ ($member['left'] ?? false) ? 'Left' : ($room->status === 'lobby' ? ($member['ready'] ? 'Ready' : 'Not ready') : 'Seated') }}</small></li>
                    @endforeach
                </ul>
                <div class="room-row-meta"><small>Active {{ $room->updated_at->diffForHumans() }}</small><small>Expires {{ $room->expires_at->diffForHumans() }}</small></div>
                @if($room->isClosed())
                    <button class="secondary-button" disabled>{{ $state === 'banned' ? 'Banned' : 'Closed' }}</button>
                @else
                    <form method="POST" action="{{ route('admin.rooms.ban', $room) }}" onsubmit="return confirm('Ban room {{ $room->code }}? Every player in it will be removed.')">@csrf<button class="danger-button">Ban</button></form>
                @endif
            </article>
        @empty
            <p class="admin-rooms-empty">No rooms yet. Rooms appear here as soon as a player creates one.</p>
        @endforelse
    </main>
</div>
@endsection
