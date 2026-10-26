@extends('layouts.app')
@section('content')
<div class="app-shell" data-admin="true" x-data="draftDuel({{ $customCardCount }}, @js($footballInventory), @js($catalogs))">
    @include('partials.admin-header', ['active' => 'cards'])
    <main><div x-show="error" class="error-banner" role="alert" x-text="error"></div>@include('partials.card-library')</main>
</div>
@endsection
