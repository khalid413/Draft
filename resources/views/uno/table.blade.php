@extends('layouts.app')
@section('title', __('UNO Classic · Glisa'))
@section('content')
{{-- The table layout is shared with Carta (carta-* classes); uno.css gives it the UNO look. --}}
<div class="carta-shell uno-shell" x-data="uno('{{ $variant }}')">
    <header class="carta-header">
        <a href="/uno" class="carta-brand uno-brand" aria-label="{{ __('Back to the UNO versions') }}"><img src="/cards/uno/classic/back.webp" alt="" width="28" height="46"><span>UNO<small>{{ __('Classic') }}</small></span></a>
        <div class="carta-header-right">
            <button class="carta-leave uno-sound" @click="toggleSound()" :aria-pressed="soundOn" :aria-label="soundOn ? t('Mute sounds') : t('Turn sounds on')" :title="soundOn ? t('Mute sounds') : t('Turn sounds on')"><span x-show="soundOn"><i data-icon="volume-up"></i></span><span x-show="!soundOn"><i data-icon="volume-mute"></i></span></button>
            <button x-show="room" x-cloak class="carta-leave" @click="leave()" :disabled="busy"><i data-icon="box-arrow-right"></i><span>{{ __('Leave') }}</span></button>
        </div>
    </header>

    <main class="carta-main">
        <div x-show="loading" class="carta-loading" role="status">{{ __('Shuffling the deck…') }}</div>
        <div x-cloak x-show="error" class="carta-error" role="alert"><span x-text="error"></span><button class="text-link" @click="error = ''">{{ __('Dismiss') }}</button></div>

        {{-- Home: create or join a table --}}
        <section x-cloak x-show="!loading && screen === 'home'" class="carta-home">
            <div class="carta-hero">
                <span class="carta-eyebrow">{{ __('UNO CLASSIC · 108 CARDS · 2–6 PLAYERS') }}</span>
                <h1>{{ __('Empty your hand.') }} <span>{{ __('Don\'t be the last.') }}</span></h1>
                <p>{{ __('Match the colour, number or symbol. Skip, Reverse and +2 change the game; Wild cards pick the colour. Call UNO before your last card. Players finish one by one, and the last one holding cards loses.') }}</p>
                <div class="carta-hero-cards" aria-hidden="true"><img src="/cards/uno/classic/red_7.png" alt=""><img src="/cards/uno/classic/blue_skip_1.png" alt=""><img src="/cards/uno/classic/wild4_1.png" alt=""></div>
            </div>
            {{-- One compact card, two tabs; invitation links open straight on "Join". Rules are set later, in the room's settings. --}}
            <div class="carta-panel uno-entry">
                <div class="uno-tabs" role="tablist">
                    <button type="button" role="tab" id="uno-tab-create" aria-controls="uno-pane-create" :aria-selected="homeTab === 'create'" :class="{ active: homeTab === 'create' }" @click="homeTab = 'create'">{{ __('Create a table') }}</button>
                    <button type="button" role="tab" id="uno-tab-join" aria-controls="uno-pane-join" :aria-selected="homeTab === 'join'" :class="{ active: homeTab === 'join' }" @click="homeTab = 'join'">{{ __('Join a table') }}</button>
                </div>
                <form x-show="homeTab === 'create'" id="uno-pane-create" role="tabpanel" aria-labelledby="uno-tab-create" class="uno-pane" @submit.prevent="createRoom()">
                    <label>{{ __('Your name') }}<input x-model="form.name" required minlength="2" maxlength="20" autocomplete="nickname" placeholder="{{ __('e.g. Khalid') }}"></label>
                    <fieldset><legend>{{ __('Seats') }}</legend><div class="carta-seat-options"><template x-for="n in [2,3,4,5,6]" :key="n"><button type="button" :class="{ selected: form.capacity === n }" :aria-pressed="form.capacity === n" @click="form.capacity = n" x-text="n"></button></template></div></fieldset>
                    <button class="carta-primary" :disabled="busy"><span x-text="busy ? t('Opening…') : t('Create table')"></span><i data-icon="arrow-right"></i></button>
                </form>
                <form x-show="homeTab === 'join'" x-cloak id="uno-pane-join" role="tabpanel" aria-labelledby="uno-tab-join" class="uno-pane" @submit.prevent="joinRoom()">
                    <label>{{ __('Table code') }}<input x-model="form.code" required minlength="8" maxlength="8" autocomplete="off" dir="ltr" placeholder="{{ __('8 letters') }}" class="carta-code-input" @input="form.code = form.code.toUpperCase()"></label>
                    <label>{{ __('Your name') }}<input x-model="form.joinName" required minlength="2" maxlength="20" autocomplete="nickname" placeholder="{{ __('e.g. Salma') }}"></label>
                    <button class="carta-primary" :disabled="busy"><span x-text="busy ? t('Joining…') : t('Join table')"></span><i data-icon="arrow-right"></i></button>
                </form>
            </div>
        </section>

        {{-- Lobby --}}
        <section x-cloak x-show="!loading && screen === 'lobby' && room" class="carta-lobby">
            <span class="carta-eyebrow">{{ __('TABLE LOBBY') }}</span>
            <h1>{{ __('Gather the players.') }}</h1>
            <div class="carta-code-card"><div><span class="carta-eyebrow">{{ __('TABLE CODE') }}</span><strong dir="ltr" x-text="room?.code"></strong></div>
                <div class="carta-code-actions"><button class="icon-button" :class="{ copied: copied === 'code' }" @click="copy('code')" aria-label="{{ __('Copy table code') }}" title="{{ __('Copy table code') }}"><span x-show="copied !== 'code'"><i data-icon="copy"></i></span><span x-show="copied === 'code'"><i data-icon="check-lg"></i></span></button><button class="icon-button" :class="{ copied: copied === 'link' }" @click="copy('link')" aria-label="{{ __('Copy invite link') }}" title="{{ __('Copy invite link') }}"><span x-show="copied !== 'link'"><i data-icon="share"></i></span><span x-show="copied === 'link'"><i data-icon="check-lg"></i></span></button></div></div>
            {{-- Seats and rules at a glance; the host changes them (and removes players) in the settings window. --}}
            <div class="uno-lobby-mode">
                <div class="uno-rules-summary">
                    <span class="carta-eyebrow">{{ __('RULES') }}</span>
                    <p class="uno-mode-read"><i data-icon="people"></i><span x-text="tn(':count seat|:count seats', room?.settings.capacity || 0)"></span><i data-icon="layers"></i><span x-text="modeText"></span></p>
                </div>
                <button x-show="room?.isHost" class="icon-button uno-gear" @click="openSettings()" aria-label="{{ __('Table settings') }}" title="{{ __('Table settings') }}"><i data-icon="gear"></i></button>
            </div>
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

        {{-- Table --}}
        <template x-if="!loading && screen === 'table' && game">
        <section class="carta-stage" :class="'seats-' + opponents.length + (animating ? ' animating' : '')">
            <div class="carta-hud carta-hud-room">
                <h2>{{ __('UNO Classic') }}</h2>
                <div class="carta-hud-code"><span>{{ __('Room:') }} <b dir="ltr" x-text="room.code"></b></span><button class="carta-hud-copy" :class="{ copied: copied === 'code' }" @click="copy('code')" aria-label="{{ __('Copy room code') }}" title="{{ __('Copy room code') }}"><span x-show="copied !== 'code'"><i data-icon="copy"></i></span><span x-show="copied === 'code'"><i data-icon="check-lg"></i></span></button></div>
                <div class="carta-hud-line uno-hud-mode"><i data-icon="layers"></i><span x-text="modeText"></span></div>
                <button x-show="room.isHost" class="icon-button uno-gear uno-gear-hud" @click="openSettings()" aria-label="{{ __('Table settings') }}" title="{{ __('Table settings') }}"><i data-icon="gear"></i></button>
            </div>
            <div class="carta-hud carta-hud-status">
                <strong x-text="statusTitle"></strong>
                <div class="carta-hud-line"><i data-icon="people"></i><span x-text="t(':active / :total playing', { active: activeCount, total: game.players.length })"></span></div>
                <div class="carta-hud-line carta-hud-rule"><i data-icon="trophy"></i><span>{{ __('First to finish wins') }}</span></div>
                <ol x-show="game.rankings.length" class="carta-hud-ranks"><template x-for="row in game.rankings" :key="row.seat"><li :class="{ win: row.rank === 1, lose: row.status === 'LOSER' }"><b x-text="ordinal(row.rank)"></b><span x-text="row.seat === room.viewerIndex ? t('You') : row.name"></span></li></template></ol>
            </div>

            <div class="carta-oval" :class="'uno-color-' + game.activeColor">
                {{-- Turn direction: the ring of arrows turns the way play is going. --}}
                <div class="uno-direction" :class="game.direction === 1 ? 'cw' : 'ccw'" role="img" :aria-label="game.direction === 1 ? t('Play goes clockwise') : t('Play goes counter-clockwise')">
                    <span x-show="game.direction === 1"><i data-icon="arrow-clockwise"></i></span><span x-show="game.direction !== 1"><i data-icon="arrow-counterclockwise"></i></span>
                </div>
                <div class="carta-center">
                    <span x-show="pendingText" class="carta-pending draw" x-text="pendingText"></span>
                    <div class="carta-center-row">
                        <div class="carta-pile-wrap">
                            <button class="carta-pile draw" :class="{ ready: me?.canDraw }" :disabled="!me?.canDraw || busy || animating" @click="act('draw')" :aria-label="me?.canDraw ? t('Draw a card') : tn('Draw pile, :count card|Draw pile, :count cards', game.drawCount)">
                                <img :src="game.back" alt="" class="stack s3"><img :src="game.back" alt="" class="stack s2"><img :src="game.back" alt="">
                                <span x-show="me?.canDraw" class="carta-pile-hint">{{ __('Tap to draw') }}</span>
                            </button>
                            <div class="carta-pile-label"><b>{{ __('Draw pile') }}</b><span x-text="tn(':count card|:count cards', game.drawCount)"></span></div>
                        </div>
                        <div class="carta-pile-wrap">
                            <div class="carta-pile discard" aria-live="polite" :aria-label="`{{ __('Discard pile') }}: ${cardLabel(game.top)}`"><template x-for="(card, i) in underCards" :key="card.key"><img :src="card.image" alt="" class="uno-under" :style="underStyle(card, i)"></template><template x-for="card in [game.top]" :key="game.discardCount"><img :src="card.image" :alt="cardLabel(card)" class="carta-top"></template></div>
                        </div>
                        <div class="uno-color-panel" :class="'uno-color-' + game.activeColor" role="group" aria-label="{{ __('Active colour') }}">
                            <template x-for="color in [game.activeColor]" :key="color"><span class="uno-color-dot" aria-hidden="true"></span></template>
                            <b x-text="colorName(game.activeColor)"></b>
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
                        <span x-show="player.uno" class="uno-tag">UNO</span>
                        <span x-show="player.left" class="carta-tag left">{{ __('Left') }}</span>
                    </div>
                    <button x-show="player.catchable" class="uno-catch" @click="act('catch', { target: player.seat })" :disabled="busy || animating"><i data-icon="hand-index-thumb"></i> <span x-text="t('Catch :name! (+2)', { name: player.name })"></span></button>
                    <div class="carta-fan" :aria-label="tn(':count card|:count cards', player.cards)"><template x-for="c in backs(player.cards)" :key="c"><img :src="game.back" alt="" :style="fanStyle(c, Math.min(player.cards, 7), 9)"></template></div>
                </div>
            </template>

            <div class="carta-seat-me" :class="{ turn: me?.isTurn && game.status === 'PLAYING' }">
                {{-- Above your hand, so the fanned cards can never cover them. --}}
                <div x-show="me?.canAccept || me?.canPass || me?.canCallUno" class="carta-actions">
                    <button x-show="me?.canCallUno" class="uno-call" @click="act('uno')" :disabled="busy">{{ __('UNO!') }}</button>
                    <button x-show="me?.canAccept" class="carta-danger" @click="act('accept')" :disabled="busy || animating" x-text="tn('Take :count card|Take :count cards', game.pending.draw)"></button>
                    <button x-show="me?.canPass" class="carta-secondary" @click="act('pass')" :disabled="busy || animating">{{ __('Keep it and pass') }}</button>
                </div>
                <div class="carta-hand" :style="`--n:${Math.max(1, game.hand.length)}`">
                    <template x-for="(card, index) in game.hand" :key="card.id"><button class="carta-card" :data-card="card.id" :class="{ playable: card.playable, drawn: card.drawn }" :style="fanStyle(index, game.hand.length, 6)" :disabled="!card.playable || busy || animating" @click="play(card)" :aria-label="card.playable ? t('Play :card', { card: cardLabel(card) }) : cardLabel(card)"><img :src="card.image" :alt="cardLabel(card)"></button></template>
                    <p x-show="me && me.status !== 'ACTIVE' && game.status === 'PLAYING'" class="carta-spectate">{{ __('You are out of cards. Watching the others play it out.') }}</p>
                </div>
                <div class="carta-chip me">
                    <span class="carta-avatar" :class="'tone-' + ((me?.seat ?? 0) % 6)" x-text="initial(myMember?.name)"></span>
                    <div><strong>{{ __('You') }}</strong><small :class="{ win: me?.rank === 1, lose: me?.status === 'LOSER' }" x-text="me ? (me.status === 'ACTIVE' ? tn(':count card|:count cards', game.hand.length) : rankTag({ ...me, cards: 0 })) : ''"></small></div>
                    <span x-show="me?.unoCalled && game.hand.length === 1" class="uno-tag">UNO</span>
                </div>
            </div>
        </section>
        </template>

        {{-- Results --}}
        <div x-cloak x-show="game?.status === 'FINISHED' && !animating" class="carta-overlay" role="dialog" aria-modal="true" aria-labelledby="uno-results-title">
            <div class="carta-results carta-end" :class="'outcome-' + outcome">
                <div x-show="outcome === 'win'" class="carta-confetti" aria-hidden="true"><template x-for="n in 18" :key="n"><i :style="`--i:${n}`"></i></template></div>
                <div class="carta-end-badge" aria-hidden="true">
                    <span x-show="outcome === 'win' || outcome === 'watch'"><i data-icon="trophy-fill"></i></span>
                    <span x-show="outcome === 'place'"><i data-icon="award-fill"></i></span>
                    <span x-show="outcome === 'lose'"><i data-icon="emoji-frown"></i></span>
                </div>
                <span class="carta-eyebrow" x-text="outcomeTitle"></span>
                <h2 id="uno-results-title" x-text="outcomeText"></h2>
                <ol><template x-for="row in game?.rankings || []" :key="row.seat"><li :class="{ win: row.rank === 1, lose: row.status === 'LOSER', you: row.seat === room?.viewerIndex }"><b x-text="ordinal(row.rank)"></b><span x-text="row.seat === room?.viewerIndex ? t(':name (you)', { name: row.name }) : row.name"></span><em x-text="row.rank === 1 ? t('Winner') : (row.status === 'LOSER' ? t('Loser') : '')"></em></li></template></ol>
                <div class="carta-results-actions"><button class="carta-primary" @click="act('rematch')" :disabled="busy"><i data-icon="arrow-counterclockwise"></i> {{ __('Play again') }}</button><button class="carta-secondary" @click="leave()" :disabled="busy">{{ __('Leave table') }}</button></div>
            </div>
        </div>

        {{-- Table settings (host): seats and stacking before the deal, removing players at any time --}}
        <div x-cloak x-show="settingsOpen && room" class="carta-overlay" role="dialog" aria-modal="true" aria-labelledby="uno-settings-title" @keydown.escape.window="settingsOpen = false" @click.self="settingsOpen = false">
            <div class="carta-results uno-settings">
                <div class="uno-settings-head"><h2 id="uno-settings-title">{{ __('Table settings') }}</h2><button class="icon-button" @click="settingsOpen = false" aria-label="{{ __('Close') }}"><i data-icon="x-lg"></i></button></div>
                <p x-show="room?.status !== 'lobby'" class="uno-settings-note">{{ __('Seats and rules are locked once the cards are dealt. You can still remove a player.') }}</p>
                <fieldset :disabled="room?.status !== 'lobby'">
                    <legend>{{ __('Seats') }}</legend>
                    <div class="carta-seat-options"><template x-for="n in [2,3,4,5,6]" :key="n"><button type="button" :class="{ selected: draft.capacity === n }" :aria-pressed="draft.capacity === n" :disabled="n < members.length" @click="draft.capacity = n" x-text="n"></button></template></div>
                </fieldset>
                <fieldset :disabled="room?.status !== 'lobby'">
                    <legend>{{ __('+2 and +4 stacking') }}</legend>
                    <div class="uno-mode-options">
                        <button type="button" :class="{ selected: draft.stacking === 'off' }" :aria-pressed="draft.stacking === 'off'" @click="draft.stacking = 'off'"><b>{{ __('Official') }}</b><small>{{ __('No stacking. A +2 or +4 is always taken.') }}</small></button>
                        <button type="button" :class="{ selected: draft.stacking === 'same' }" :aria-pressed="draft.stacking === 'same'" @click="draft.stacking = 'same'"><b>{{ __('Same cards') }}</b><small>{{ __('Stack +2 on +2 and +4 on +4.') }}</small></button>
                        <button type="button" :class="{ selected: draft.stacking === 'mixed' }" :aria-pressed="draft.stacking === 'mixed'" @click="draft.stacking = 'mixed'"><b>{{ __('Everything') }}</b><small>{{ __('+2 and +4 stack on each other.') }}</small></button>
                    </div>
                </fieldset>
                <div class="uno-settings-players">
                    <span class="uno-settings-label">{{ __('Players') }}</span>
                    <ul><template x-for="(member, i) in members" :key="i"><li x-show="!member.left">
                        <span class="carta-avatar" :class="'tone-' + (i % 6)" x-text="initial(member.name)"></span>
                        <strong x-text="member.name"></strong>
                        <span x-show="i === room?.host" class="carta-tag host">{{ __('Host') }}</span>
                        <button x-show="i !== room?.host" class="uno-kick" :class="{ confirm: kickConfirm === i }" :disabled="busy" @click="kick(i)" x-text="kickConfirm === i ? t('Tap again to remove') : t('Remove')"></button>
                    </li></template></ul>
                    <p x-show="room?.status !== 'lobby'" class="uno-settings-note">{{ __('Removing a player during a match counts as leaving: they lose.') }}</p>
                </div>
                <div class="carta-results-actions" x-show="room?.status === 'lobby'">
                    <button class="carta-primary" @click="saveSettings()" :disabled="busy || !settingsChanged">{{ __('Save') }}</button>
                    <button class="carta-secondary" @click="settingsOpen = false">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
        {{-- Colour choice for a wild card --}}
        <div x-cloak x-show="colorPicker" class="carta-overlay" role="dialog" aria-modal="true" aria-labelledby="uno-color-title" @keydown.escape.window="colorPicker = null">
            <div class="carta-results uno-color-picker">
                <h2 id="uno-color-title">{{ __('Choose the next colour') }}</h2>
                <div class="uno-color-grid"><template x-for="color in colors" :key="color"><button :class="'uno-color-' + color" @click="chooseColor(color)"><span class="uno-color-dot" aria-hidden="true"></span><span x-text="colorName(color)"></span></button></template></div>
                <button class="text-link" @click="colorPicker = null">{{ __('Cancel') }}</button>
            </div>
        </div>

        <div class="carta-toasts" aria-live="polite"><template x-for="toast in toasts" :key="toast.id"><div class="carta-toast" :class="toast.kind" x-text="toast.text"></div></template></div>
    </main>
</div>
@endsection
