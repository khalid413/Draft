@extends('layouts.app')
@section('content')
<main class="admin-login app-shell"><a href="/" class="back-button">← Back to Draft Duel</a><form method="POST" action="/admin/login" class="login-panel">@csrf
    <span class="eyebrow">PRIVATE ACCESS</span><h1>Admin login</h1><p>Manage the cards. Shape the next game.</p>
    @if($errors->any())<div class="error-banner" role="alert">{{ $errors->first() }}</div>@endif
    <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="primary-button" type="submit">Sign in →</button>
</form></main>
@endsection
