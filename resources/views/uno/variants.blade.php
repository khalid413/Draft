@extends('layouts.app')
@section('title', __('UNO · Glisa'))
@section('content')
<div class="glisa-shell uno-variants-shell">
    <header class="glisa-header">
        <a href="/" class="glisa-brand" aria-label="{{ __('Back to all Glisa games') }}"><span class="glisa-mark" aria-hidden="true">ج</span><span>GLISA<small>{{ __('جلسة · game nights') }}</small></span></a>
        <a href="/" class="uno-back"><i data-icon="arrow-left"></i><span>{{ __('All games') }}</span></a>
    </header>

    <main class="glisa-main">
        <section class="uno-variants-hero">
            <h1 class="uno-logo" aria-label="UNO"><span aria-hidden="true">UNO</span></h1>
            <p>{{ __('Match the colour, the number or the symbol. Skip, reverse, make them draw, and shout UNO before your last card. Choose your version.') }}</p>
        </section>

        <section aria-labelledby="uno-variants-title">
            <h2 id="uno-variants-title" class="glisa-section-title">{{ __('Choose a version') }}</h2>
            <div class="uno-variant-grid">
                @foreach($variants as $key => $class)
                    @php
                        $info = [
                            'classic' => [__('UNO Classic'), __('The original 108-card game for 2–6 players. Official rules, with optional +2/+4 stacking.'), ['red_7', 'blue_skip_1', 'wild4_1']],
                            'flip' => [__('UNO Flip'), __('A light side and a dark side, and a card that flips the whole game.'), []],
                            'no-mercy' => [__('UNO No Mercy'), __('Brutal stacks, bigger penalties and no second chances.'), []],
                            'all-wild' => [__('UNO All Wild'), __('Every single card is wild.'), []],
                        ][$key];
                    @endphp
                    @if($class)
                        <a href="/uno/{{ $key }}" class="uno-variant available">
                            <div class="uno-variant-art" aria-hidden="true">@foreach($info[2] as $face)<img src="/cards/uno/classic/{{ $face }}.png" alt="">@endforeach</div>
                            <div class="uno-variant-body">
                                <span class="uno-badge live">{{ __('Available') }}</span>
                                <h3>{{ $info[0] }}</h3>
                                <p>{{ $info[1] }}</p>
                                <span class="glisa-play">{{ __('Play :name', ['name' => $info[0]]) }} <i data-icon="arrow-right"></i></span>
                            </div>
                        </a>
                    @else
                        <div class="uno-variant soon" aria-disabled="true">
                            <div class="uno-variant-art" aria-hidden="true"><img src="/cards/uno/classic/back.webp" alt=""></div>
                            <div class="uno-variant-body">
                                <span class="uno-badge">{{ __('Coming soon') }}</span>
                                <h3>{{ $info[0] }}</h3>
                                <p>{{ $info[1] }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    </main>

    <footer class="glisa-footer">{{ __('Glisa · جلسة — made for game nights.') }}</footer>
</div>
@endsection
