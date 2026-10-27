@extends('layouts.app')
@section('content')
<div class="glisa-shell" x-data="library">
    <header class="glisa-header">
        <a href="/" class="glisa-brand" aria-label="{{ __('Glisa home') }}"><span class="glisa-mark" aria-hidden="true">ج</span><span>GLISA<small>{{ __('جلسة · game nights') }}</small></span></a>
        <form method="POST" action="{{ route('locale') }}" class="glisa-lang" aria-label="{{ __('Language') }}">@csrf
            @foreach(['en' => ['English', 'EN'], 'fr' => ['Français', 'FR'], 'ar' => ['العربية', 'ع']] as $code => [$name, $short])
                <button name="locale" value="{{ $code }}" lang="{{ $code }}" @class(['active' => app()->getLocale() === $code]) aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}" title="{{ $name }}"><span class="glisa-lang-full">{{ $name }}</span><span class="glisa-lang-short" aria-hidden="true">{{ $short }}</span></button>
            @endforeach
        </form>
    </header>

    <main class="glisa-main">
        <section class="glisa-hero">
            <span class="glisa-eyebrow">{{ __('PLAY TOGETHER · ONE TABLE · ANY DEVICE') }}</span>
            <h1>{{ __('Pull up a chair.') }}<span>{{ __('Pick a game.') }}</span></h1>
            <p>{{ __('Glisa is where friends sit down to play. Open a private table, share the code, and play from your phones, or pass one device around the room.') }}</p>
        </section>

        <section class="glisa-library" aria-labelledby="glisa-games">
            <h2 id="glisa-games" class="glisa-section-title">{{ __('Games') }}</h2>
            <div class="glisa-games">
                <a href="/draft" class="glisa-game draft">
                    <div class="glisa-game-art" aria-hidden="true"><span class="draft-mystery">?</span><span class="draft-reveal">✦</span></div>
                    <div class="glisa-game-body">
                        <h3>{{ __('Draft Duel') }}</h3>
                        <p>{{ __('Two cards, one choice: keep one, give one. Draft characters or build a football 4-3-3 and let the match decide.') }}</p>
                        <ul class="glisa-tags"><li><i data-icon="people"></i> {{ __('2–4 players') }}</li><li>{{ __('Online rooms') }}</li><li>{{ __('One device') }}</li></ul>
                        <span class="glisa-play">{{ __('Play Draft Duel') }} <i data-icon="arrow-right"></i></span>
                    </div>
                </a>
                <a href="/carta" class="glisa-game carta">
                    <div class="glisa-game-art" aria-hidden="true"><img src="/cards/coins/coins_1.webp" alt=""><img src="/cards/cups/cups_2.webp" alt=""><img src="/cards/swords/swords_7.webp" alt=""></div>
                    <div class="glisa-game-body">
                        <h3>{{ __('Moroccan Carta') }} @if(app()->getLocale() !== 'ar')<small>كارطا</small>@endif</h3>
                        <p>{{ __('Match the suit or the number with the 40-card deck. Skip with a 1, stack +2s, change the suit with a 7. Don\'t be the last one holding cards.') }}</p>
                        <ul class="glisa-tags"><li><i data-icon="people"></i> {{ __('2–6 players') }}</li><li>{{ __('Online rooms') }}</li><li>{{ __('Live ranking') }}</li></ul>
                        <span class="glisa-play">{{ __('Play Carta') }} <i data-icon="arrow-right"></i></span>
                    </div>
                </a>
                <div class="glisa-game soon" aria-label="{{ __('More games coming soon') }}">
                    <div class="glisa-soon-mark" aria-hidden="true">+</div>
                    <h3>{{ __('More games soon') }}</h3>
                    <p>{{ __('New tables are being set up. Same rooms, same friends.') }}</p>
                </div>
            </div>
        </section>

        <section class="glisa-join" aria-labelledby="glisa-join-title">
            <div><h2 id="glisa-join-title">{{ __('Have a table code?') }}</h2><p>{{ __('Friends already playing? Enter their code and we\'ll seat you at the right game.') }}</p></div>
            <form @submit.prevent="join()" class="glisa-join-form">
                <label class="sr-only" for="glisa-code">{{ __('Table code') }}</label>
                <input id="glisa-code" x-model="code" @input="code = code.toUpperCase(); error = ''" required minlength="8" maxlength="8" autocomplete="off" dir="ltr" placeholder="{{ __('8-LETTER CODE') }}">
                <button :disabled="busy"><span x-text="busy ? t('Finding…') : t('Join')"></span><i data-icon="arrow-right"></i></button>
            </form>
            <p x-cloak x-show="error" class="glisa-join-error" role="alert" x-text="error"></p>
        </section>
    </main>

    <footer class="glisa-footer">{{ __('Glisa · جلسة — made for game nights.') }}</footer>
</div>
@endsection
