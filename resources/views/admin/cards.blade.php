@extends('layouts.app')
@section('content')
<div class="app-shell" data-admin="true" x-data="draftDuel({{ $customCardCount }}, @js($footballInventory), @js($catalogs))">
    <header class="site-header"><a href="/" class="wordmark"><span class="brand-mark"><i data-lucide="layers-2"></i></span>DRAFT<span>DUEL</span></a><nav aria-label="Admin controls" class="admin-nav"><span class="eyebrow">ADMIN</span><a href="/admin" class="text-link" aria-current="page">Cards</a><a href="{{ route('admin.rooms') }}" class="text-link">Rooms</a><form method="POST" action="/admin/logout">@csrf<button class="secondary-button">Sign out</button></form></nav></header>
    <main><div x-show="error" class="error-banner" role="alert" x-text="error"></div>@include('partials.card-library')</main>
</div>
@endsection
