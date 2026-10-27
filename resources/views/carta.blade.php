@extends('layouts.app')
@section('title', __('Moroccan Carta · Glisa'))
@section('content')
<div class="carta-shell" x-data="carta">
    <header class="carta-header">
        <a href="/" class="carta-brand" aria-label="{{ __('Back to all Glisa games') }}"><img src="/cards/back.webp" alt="" width="28" height="42"><span>CARTA<small>كارطا مغربية</small></span></a>
        <div class="carta-header-right">
            <button x-show="room" x-cloak class="carta-leave" @click="leave()" :disabled="busy"><i data-icon="box-arrow-right"></i><span>{{ __('Leave') }}</span></button>
        </div>
    </header>

    <main class="carta-main">
        <div x-show="loading" class="carta-loading" role="status">{{ __('Shuffling the deck…') }}</div>
        <div x-cloak x-show="error" class="carta-error" role="alert"><span x-text="error"></span><button class="text-link" @click="error = ''">{{ __('Dismiss') }}</button></div>

        {{-- Home: create or join a table --}}
        <section x-cloak x-show="!loading && screen === 'home'" class="carta-home">
            <div class="carta-hero">
                <span class="carta-eyebrow">{{ __('MOROCCAN CARTA · 40 CARDS · 2–6 PLAYERS') }}</span>
                <h1>{{ __('Empty your hand.') }} <span>{{ __('Don\'t be the last.') }}</span></h1>
                <p>{{ __('Match the suit or the number. A 1 skips the next player, a 2 makes them draw two, a 7 changes the suit. Stack 1s and 2s to pass the penalty on.') }}</p>
                <div class="carta-hero-cards" aria-hidden="true"><img src="/cards/coins/coins_1.webp" alt=""><img src="/cards/cups/cups_2.webp" alt=""><img src="/cards/swords/swords_7.webp" alt=""></div>
            </div>
            <div class="carta-forms">
                <form class="carta-panel" @submit.prevent="createRoom()">
                    <h2>{{ __('Open a table') }}</h2>
                    <label>{{ __('Your name') }}<input x-model="form.name" required minlength="2" maxlength="20" autocomplete="nickname" placeholder="{{ __('e.g. Khalid') }}"></label>
                    <fieldset><legend>{{ __('Seats') }}</legend><div class="carta-seat-options"><template x-for="n in [2,3,4,5,6]" :key="n"><button type="button" :class="{ selected: form.capacity === n }" :aria-pressed="form.capacity === n" @click="form.capacity = n" x-text="n"></button></template></div></fieldset>
                    <button class="carta-primary" :disabled="busy"><span x-text="busy ? t('Opening…') : t('Create table')"></span><i data-icon="arrow-right"></i></button>
                </form>
                <form class="carta-panel" @submit.prevent="joinRoom()">
                    <h2>{{ __('Join friends') }}</h2>
                    <label>{{ __('Table code') }}<input x-model="form.code" required minlength="8" maxlength="8" autocomplete="off" dir="ltr" placeholder="{{ __('8 letters') }}" class="carta-code-input" @input="form.code = form.code.toUpperCase()"></label>
                    <label>{{ __('Your name') }}<input x-model="form.joinName" required minlength="2" maxlength="20" autocomplete="nickname" placeholder="{{ __('e.g. Salma') }}"></label>
                    <button class="carta-secondary" :disabled="busy"><span x-text="busy ? t('Joining…') : t('Join table')"></span><i data-icon="arrow-right"></i></button>
                </form>
            </div>
        </section>

        {{-- Lobby --}}
        <section x-cloak x-show="!loading && screen === 'lobby' && room" class="carta-lobby">
            <span class="carta-eyebrow">{{ __('TABLE LOBBY') }}</span>
            <h1>{{ __('Gather the players.') }}</h1>
            <div class="carta-code-card"><div><span class="carta-eyebrow">{{ __('TABLE CODE') }}</span><strong dir="ltr" x-text="room?.code"></strong></div>
                <div class="carta-code-actions"><button class="icon-button" :class="{ copied: copied === 'code' }" @click="copy('code')" aria-label="{{ __('Copy table code') }}" title="{{ __('Copy table code') }}"><span x-show="copied !== 'code'"><i data-icon="copy"></i></span><span x-show="copied === 'code'"><i data-icon="check-lg"></i></span></button><button class="icon-button" :class="{ copied: copied === 'link' }" @click="copy('link')" aria-label="{{ __('Copy invite link') }}" title="{{ __('Copy invite link') }}"><span x-show="copied !== 'link'"><i data-icon="share"></i></span><span x-show="copied === 'link'"><i data-icon="check-lg"></i></span></button></div></div>
            <p class="carta-hint" x-text="members.length < 2 ? t('Waiting for at least one more player.') : (everyoneReady ? (room?.isHost ? t('Everyone is ready. Deal the cards!') : t('Everyone is ready. Waiting for the host to deal.')) : t('Waiting for everyone to tap Ready.'))"></p>
            <ul class="carta-seats">
                <template x-for="i in room?.settings.capacity || 0" :key="i"><li :class="{ open: !members[i-1], ready: members[i-1]?.ready }">
                    <span class="carta-avatar" x-text="(members[i-1]?.name.charAt(0) || '+').toUpperCase()"></span>
                    <div><strong x-text="members[i-1]?.name || t('Open seat')"></strong><span class="carta-tags"><span x-show="members[i-1] && i - 1 === room?.host" class="carta-tag host">{{ __('Host') }}</span><span x-show="members[i-1] && i - 1 === room?.viewerIndex" class="carta-tag you">{{ __('You') }}</span></span></div>
                    <span class="carta-state" x-text="members[i-1] ? (members[i-1].ready ? t('Ready') : t('Not ready')) : t('Empty')"></span>
                </li></template>
            </ul>
            <div class="carta-lobby-actions">
                <button x-show="!myMember?.ready" class="carta-ready" @click="act('ready', { ready: true })" :disabled="busy">{{ __('Ready') }}</button>
                <button x-show="myMember?.ready" class="carta-secondary" @click="act('ready', { ready: false })" :disabled="busy">{{ __('Undo') }}</button>
                <button x-show="room?.isHost" class="carta-primary" @click="act('start')" :disabled="busy || !everyoneReady">{{ __('Deal cards') }} <i data-icon="arrow-right"></i></button>
            </div>
        </section>

        {{-- Table: everyone seated around an oval felt, you at the bottom --}}
        <template x-if="!loading && screen === 'table' && game">
        <section class="carta-stage" :class="'seats-' + opponents.length">
            <div class="carta-hud carta-hud-room">
                <h2>{{ __('Moroccan Carta') }}</h2>
                <div class="carta-hud-code"><span>{{ __('Room:') }} <b dir="ltr" x-text="room.code"></b></span><button class="carta-hud-copy" :class="{ copied: copied === 'code' }" @click="copy('code')" aria-label="{{ __('Copy room code') }}" title="{{ __('Copy room code') }}"><span x-show="copied !== 'code'"><i data-icon="copy"></i></span><span x-show="copied === 'code'"><i data-icon="check-lg"></i></span></button></div>
            </div>
            <div class="carta-hud carta-hud-status">
                <strong x-text="statusTitle"></strong>
                <div class="carta-hud-line"><i data-icon="people"></i><span x-text="t(':active / :total playing', { active: activeCount, total: game.players.length })"></span></div>
                <div class="carta-hud-line carta-hud-rule"><i data-icon="trophy"></i><span>{{ __('First to finish wins') }}</span></div>
                <ol x-show="game.rankings.length" class="carta-hud-ranks"><template x-for="row in game.rankings" :key="row.seat"><li :class="{ win: row.rank === 1, lose: row.status === 'LOSER' }"><b x-text="ordinal(row.rank)"></b><span x-text="row.seat === room.viewerIndex ? t('You') : row.name"></span></li></template></ol>
            </div>

            <div class="carta-oval">
                <div class="carta-center">
                    <span x-show="pendingText()" class="carta-pending" :class="{ draw: game.pending.effect === 'DRAW_TWO' }" x-text="pendingText()"></span>
                    <div class="carta-center-row">
                        <div class="carta-pile-wrap">
                            <button class="carta-pile draw" :class="{ ready: me?.canDraw }" :disabled="!me?.canDraw || busy" @click="act('draw')" :aria-label="me?.canDraw ? t('Draw a card') : tn('Draw pile, :count card|Draw pile, :count cards', game.drawCount)">
                                <img :src="game.back" alt="" class="stack s3"><img :src="game.back" alt="" class="stack s2"><img :src="game.back" alt="">
                                <span x-show="me?.canDraw" class="carta-pile-hint">{{ __('Tap to draw') }}</span>
                            </button>
                            <div class="carta-pile-label"><b>{{ __('Draw pile') }}</b><span x-text="tn(':count card|:count cards', game.drawCount)"></span></div>
                        </div>
                        <div class="carta-pile-wrap">
                            {{-- No caption: the face-up card speaks for itself; screen readers get its name. --}}
                            <div class="carta-pile discard" aria-live="polite" :aria-label="`{{ __('Discard pile') }}: ${cardLabel(game.top)}`"><template x-for="card in [game.top]" :key="game.discardCount"><img :src="card.image" :alt="cardLabel(card)" class="carta-top"></template></div>
                        </div>
                        <div class="carta-suit-panel" :class="'suit-' + game.activeSuit" role="group" aria-label="{{ __('Active suit') }}">
                            <template x-for="suit in [game.activeSuit]" :key="suit"><img class="carta-suit-card" :src="`/cards/suits/${suit}.webp`" alt="" aria-hidden="true"></template>
                            <b x-text="suitTitle(game.activeSuit)"></b><small x-show="suitSubtitle(game.activeSuit)" x-text="suitSubtitle(game.activeSuit)"></small>
                        </div>
                    </div>
                    <p class="carta-turn" :class="{ mine: me?.isTurn }" x-text="turnText"></p>
                </div>
            </div>

            <template x-for="(player, i) in opponents" :key="player.seat">
                <div class="carta-seat" :data-seat="player.seat" :class="'pos-' + seatPosition(i) + (player.isTurn && game.status === 'PLAYING' ? ' turn' : '') + (player.status !== 'ACTIVE' ? ' done' : '')">
                    <div class="carta-chip">
                        <span class="carta-avatar" :class="'tone-' + (player.seat % 6)" x-text="initial(player.name)"></span>
                        <div><strong x-text="player.name"></strong><small :class="{ win: player.rank === 1, lose: player.status === 'LOSER' }" x-text="rankTag(player)"></small></div>
                        <span x-show="player.left" class="carta-tag left">{{ __('Left') }}</span>
                    </div>
                    <div class="carta-fan" :aria-label="tn(':count card|:count cards', player.cards)"><template x-for="c in backs(player.cards)" :key="c"><img :src="game.back" alt="" :style="fanStyle(c, Math.min(player.cards, 7), 9)"></template></div>
                </div>
            </template>

            <div class="carta-seat-me" :class="{ turn: me?.isTurn && game.status === 'PLAYING' }">
                {{-- Above your hand, so the fanned cards can never cover them. --}}
                <div x-show="me?.canAccept" class="carta-actions">
                    <button class="carta-danger" @click="act('accept')" :disabled="busy" x-text="game.pending.effect === 'SKIP' ? t('Accept the skip') : tn('Take :count card|Take :count cards', game.pending.draw)"></button>
                </div>
                <div class="carta-hand" :style="`--n:${Math.max(1, game.hand.length)}`">
                    <template x-for="(card, index) in game.hand" :key="card.id"><button class="carta-card" :data-card="card.id" :class="{ playable: card.playable }" :style="fanStyle(index, game.hand.length, 6)" :disabled="!card.playable || busy" @click="play(card)" :aria-label="card.playable ? t('Play :card', { card: cardLabel(card) }) : cardLabel(card)"><img :src="card.image" :alt="cardLabel(card)"></button></template>
                    <p x-show="me && me.status !== 'ACTIVE' && game.status === 'PLAYING'" class="carta-spectate">{{ __('You are out of cards. Watching the others play it out.') }}</p>
                </div>
                <div class="carta-chip me">
                    <span class="carta-avatar" :class="'tone-' + ((me?.seat ?? 0) % 6)" x-text="initial(myMember?.name)"></span>
                    <div><strong>{{ __('You') }}</strong><small :class="{ win: me?.rank === 1, lose: me?.status === 'LOSER' }" x-text="me ? (me.status === 'ACTIVE' ? tn(':count card|:count cards', game.hand.length) : rankTag({ ...me, cards: 0 })) : ''"></small></div>
                </div>
            </div>
        </section>
        </template>

        {{-- Results --}}
        <div x-cloak x-show="game?.status === 'FINISHED'" class="carta-overlay" role="dialog" aria-modal="true" aria-labelledby="carta-results-title">
            <div class="carta-results carta-end" :class="'outcome-' + outcome">
                {{-- Confetti only for the winner; pure decoration. --}}
                <div x-show="outcome === 'win'" class="carta-confetti" aria-hidden="true"><template x-for="n in 18" :key="n"><i :style="`--i:${n}`"></i></template></div>
                <div class="carta-end-badge" aria-hidden="true">
                    <span x-show="outcome === 'win' || outcome === 'watch'"><i data-icon="trophy-fill"></i></span>
                    <span x-show="outcome === 'place'"><i data-icon="award-fill"></i></span>
                    <span x-show="outcome === 'lose'"><i data-icon="emoji-frown"></i></span>
                </div>
                <span class="carta-eyebrow" x-text="outcomeTitle"></span>
                <h2 id="carta-results-title" x-text="outcomeText"></h2>
                <ol><template x-for="row in game?.rankings || []" :key="row.seat"><li :class="{ win: row.rank === 1, lose: row.status === 'LOSER', you: row.seat === room?.viewerIndex }"><b x-text="ordinal(row.rank)"></b><span x-text="row.seat === room?.viewerIndex ? t(':name (you)', { name: row.name }) : row.name"></span><em x-text="row.rank === 1 ? t('Winner') : (row.status === 'LOSER' ? t('Loser') : '')"></em></li></template></ol>
                <div class="carta-results-actions"><button class="carta-primary" @click="act('rematch')" :disabled="busy"><i data-icon="arrow-counterclockwise"></i> {{ __('Play again') }}</button><button class="carta-secondary" @click="leave()" :disabled="busy">{{ __('Leave table') }}</button></div>
            </div>
        </div>

        {{-- Suit choice for a 7 --}}
        <div x-cloak x-show="suitPicker" class="carta-overlay" role="dialog" aria-modal="true" aria-labelledby="carta-suit-title" @keydown.escape.window="suitPicker = null">
            <div class="carta-results carta-suit-picker">
                <h2 id="carta-suit-title">{{ __('Choose the next suit') }}</h2>
                <div class="carta-suit-grid"><template x-for="suit in Object.keys(suits)" :key="suit"><button :class="'suit-' + suit" @click="chooseSuit(suit)"><img :src="`/cards/${suit}/${suit}_1.webp`" alt=""><span x-text="suitTitle(suit)"></span><small x-show="suitSubtitle(suit)" x-text="suitSubtitle(suit)"></small></button></template></div>
                <button class="text-link" @click="suitPicker = null">{{ __('Cancel') }}</button>
            </div>
        </div>

        <div class="carta-toasts" aria-live="polite"><template x-for="toast in toasts" :key="toast.id"><div class="carta-toast" :class="toast.kind" x-text="toast.text"></div></template></div>
    </main>
</div>
@endsection
