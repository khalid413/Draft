@extends('layouts.app')
@section('content')
<div class="app-shell admin-rooms" x-data="{ filter: @js($filter), counts: @js($filters->map(fn ($item) => $item['count'])), labels: @js($filters->map(fn ($item) => $item['label'])),
    show(key) {
        this.filter = key;
        history.replaceState(null, '', key === 'all' ? '/admin/rooms' : '/admin/rooms?status=' + key);
    } }">
    @include('partials.admin-header', ['active' => 'rooms'])
    <main>
        <div class="admin-page-head"><div><h1>Rooms</h1><p>Every online room, newest activity first. Ban closes a room for all of its players.</p></div></div>
        @if(session('status'))<div class="status-banner" role="status">{{ session('status') }}</div>@endif
        <nav class="room-filters" aria-label="Filter rooms by status">
            @foreach($filters as $key => $item)
                <a href="{{ $key === 'all' ? route('admin.rooms') : route('admin.rooms', ['status' => $key]) }}" @click.prevent="show(@js($key))" class="room-filter room-filter-{{ $key }}" :class="{ active: filter === @js($key) }" :aria-current="filter === @js($key) ? 'page' : null"><span>{{ $item['label'] }}</span><b>{{ $item['count'] }}</b></a>
            @endforeach
        </nav>
        @foreach($rooms as $room)
            @php
                $state = $room->adminState();
                $s = $room->settings;
            @endphp
            <article class="room-row" data-bucket="{{ $buckets[$room->id] }}" x-show="filter === 'all' || filter === @js($buckets[$room->id])" @if($filter !== 'all' && $buckets[$room->id] !== $filter) style="display:none" @endif>
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
                    <form method="POST" action="{{ route('admin.rooms.ban', $room) }}" onsubmit="return confirm('Ban room {{ $room->code }}? Every player in it will be removed.')">@csrf<input type="hidden" name="status" :value="filter"><button class="danger-button">Ban</button></form>
                @endif
            </article>
        @endforeach
        <div class="admin-empty" x-show="!counts[filter]" @if($filters[$filter]['count']) style="display:none" @endif><i data-icon="inbox"></i><p x-text="filter === 'all' ? 'No rooms yet. Rooms appear here as soon as a player creates one.' : 'No ' + labels[filter].toLowerCase() + ' rooms right now.'">{{ $filter === 'all' ? 'No rooms yet. Rooms appear here as soon as a player creates one.' : 'No '.strtolower($filters[$filter]['label']).' rooms right now.' }}</p></div>
    </main>
</div>
@endsection
